<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Asterisk AMI TCP socket client.
 * Handles connect/auth, originate, channel list, active call queries.
 */
class Ami_service
{
    private $socket  = null;
    private $host    = '';
    private $port    = 5038;
    private $timeout = 5;
    private $connected = false;
    private $last_error = '';

    public function __construct()
    {
        $this->host = salesos_setting('salesos_ami_host', '127.0.0.1', 'SALESOS_AMI_HOST');
        $this->port = (int) salesos_setting('salesos_ami_port', 5038, 'SALESOS_AMI_PORT');
    }

    // ── Connection ───────────────────────────────────────────────────────────

    public function connect(): bool
    {
        if ($this->connected) {
            return true;
        }

        $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);

        if (!$this->socket) {
            $this->last_error = "AMI connect failed: $errstr ($errno)";
            return false;
        }

        stream_set_timeout($this->socket, $this->timeout);

        // Read banner
        $banner = fgets($this->socket, 256);
        if (strpos($banner, 'Asterisk Call Manager') === false) {
            $this->last_error = 'Not an AMI socket: ' . $banner;
            fclose($this->socket);
            $this->socket = null;
            return false;
        }

        // Authenticate
        $username = salesos_setting('salesos_ami_username', 'crm-api', 'SALESOS_AMI_USERNAME');
        $secret   = salesos_setting('salesos_ami_secret', '', 'SALESOS_AMI_SECRET');

        $this->send_action([
            'Action'   => 'Login',
            'Username' => $username,
            'Secret'   => $secret,
        ]);

        $resp = $this->read_response();

        if (!isset($resp['Response']) || $resp['Response'] !== 'Success') {
            $this->last_error = 'AMI auth failed: ' . ($resp['Message'] ?? 'unknown');
            fclose($this->socket);
            $this->socket = null;
            return false;
        }

        $this->connected = true;
        return true;
    }

    public function disconnect(): void
    {
        if ($this->socket) {
            $this->send_action(['Action' => 'Logoff']);
            fclose($this->socket);
            $this->socket    = null;
            $this->connected = false;
        }
    }

    public function get_last_error(): string
    {
        return $this->last_error;
    }

    // ── Originate ────────────────────────────────────────────────────────────

    /**
     * Originate an outbound call through the trunk.
     * $number   — destination phone number
     * $agent_id — salesos agent id (to find extension)
     * $vars     — extra channel vars (CRM_ID, CALLTYPE, etc.)
     */
    public function originate(string $number, int $agent_id = 0, array $vars = []): array
    {
        if (!$this->connect()) {
            return ['success' => false, 'error' => $this->last_error];
        }

        $trunk    = salesos_setting('salesos_trunk_endpoint', 'endpoint-trunk-bdit', 'SALESOS_TRUNK_ENDPOINT');
        $prefix   = salesos_setting('salesos_outbound_prefix', '', 'SALESOS_OUTBOUND_PREFIX');
        $dial_num = $prefix . $number;

        // Build channel vars string  key1=val1,key2=val2
        $default_vars = [
            'CDR(accountcode)' => 'crm-outbound',
            'CRM_AGENT'        => $agent_id,
        ];
        $all_vars = array_merge($default_vars, $vars);
        $var_str  = implode(',', array_map(fn($k, $v) => "$k=$v", array_keys($all_vars), $all_vars));

        $action = [
            'Action'    => 'Originate',
            'Channel'   => "PJSIP/{$trunk}/{$dial_num}",
            'Timeout'   => '60000',
            'CallerID'  => salesos_setting('salesos_caller_id', '', 'SALESOS_CALLER_ID'),
            'Variable'  => $var_str,
            'Async'     => 'true',
            'ActionID'  => uniqid('crm-'),
        ];

        $click_to_call = salesos_setting('salesos_click_to_call_enabled', '0', 'SALESOS_CLICK_TO_CALL_ENABLED') === '1';

        // If enabled and agent has an extension, use click-to-call: ring agent first
        if ($click_to_call && $agent_id > 0) {
            $CI = &get_instance();
            $CI->load->model('salesos/agents_model');
            $agent = $CI->agents_model->get_by_id($agent_id);
            if ($agent && !empty($agent['extension'])) {
                // Click-to-call: dial agent extension first, bridge to destination
                $action['Channel']  = "PJSIP/{$agent['extension']}";
                $action['Context']  = 'from-internal';
                $action['Exten']    = $dial_num;
                $action['Priority'] = '1';
                unset($action['CallerID']);
            }
        }

        $this->send_action($action);
        $resp = $this->read_response(true);
        $this->disconnect();

        if (isset($resp['Response']) && $resp['Response'] === 'Success') {
            return ['success' => true, 'action_id' => $action['ActionID']];
        }

        return ['success' => false, 'error' => $resp['Message'] ?? 'Originate failed'];
    }

    // ── Active calls ─────────────────────────────────────────────────────────

    public function get_active_channels(): array
    {
        if (!$this->connect()) {
            return [];
        }

        $this->send_action([
            'Action'   => 'CoreShowChannels',
            'ActionID' => uniqid('ch-'),
        ]);

        $channels = [];
        while (true) {
            $event = $this->read_response();
            if (empty($event)) {
                break;
            }
            if (isset($event['Event']) && $event['Event'] === 'CoreShowChannel') {
                $channels[] = $event;
            }
            if (isset($event['Event']) && $event['Event'] === 'CoreShowChannelsComplete') {
                break;
            }
        }

        $this->disconnect();
        return $channels;
    }

    public function get_channel_status(string $channel): array
    {
        if (!$this->connect()) {
            return [];
        }

        $this->send_action([
            'Action'  => 'Status',
            'Channel' => $channel,
            'ActionID' => uniqid('st-'),
        ]);

        $result = $this->read_response(true);
        $this->disconnect();
        return $result;
    }

    // ── Hangup ───────────────────────────────────────────────────────────────

    public function hangup(string $channel): bool
    {
        if (!$this->connect()) {
            return false;
        }

        $this->send_action([
            'Action'  => 'Hangup',
            'Channel' => $channel,
        ]);

        $resp = $this->read_response(true);
        $this->disconnect();

        return isset($resp['Response']) && $resp['Response'] === 'Success';
    }

    // ── PJSIP registration status ─────────────────────────────────────────────

    public function get_pjsip_status(): array
    {
        if (!$this->connect()) {
            return [];
        }

        $this->send_action([
            'Action'   => 'PJSIPShowRegistrationsOutbound',
            'ActionID' => uniqid('pj-'),
        ]);

        $results = [];
        while (true) {
            $event = $this->read_response();
            if (empty($event)) {
                break;
            }
            if (isset($event['Event']) && strpos($event['Event'], 'OutboundRegistration') !== false) {
                $results[] = $event;
            }
            if (isset($event['Event']) && $event['Event'] === 'OutboundRegistrationDetailComplete') {
                break;
            }
        }

        $this->disconnect();
        return $results;
    }

    // ── AMI endpoint list ─────────────────────────────────────────────────────

    public function get_endpoint_list(): array
    {
        if (!$this->connect()) {
            return [];
        }

        $this->send_action([
            'Action'   => 'PJSIPShowEndpoints',
            'ActionID' => uniqid('ep-'),
        ]);

        $results = [];
        while (true) {
            $event = $this->read_response();
            if (empty($event)) {
                break;
            }
            if (isset($event['Event']) && $event['Event'] === 'EndpointList') {
                $results[] = $event;
            }
            if (isset($event['Event']) && $event['Event'] === 'EndpointListComplete') {
                break;
            }
        }

        $this->disconnect();
        return $results;
    }

    // ── Low-level I/O ─────────────────────────────────────────────────────────

    private function send_action(array $fields): void
    {
        $packet = '';
        foreach ($fields as $key => $value) {
            $packet .= "$key: $value\r\n";
        }
        $packet .= "\r\n";
        fwrite($this->socket, $packet);
    }

    private function read_response(bool $skip_events = false): array
    {
        $deadline = microtime(true) + $this->timeout;

        while (microtime(true) < $deadline) {
            $response = [];

            while (microtime(true) < $deadline) {
                $line = fgets($this->socket, 4096);
                if ($line === false) {
                    break 2;
                }
                $line = rtrim($line, "\r\n");
                if ($line === '') {
                    break;
                }
                $pos = strpos($line, ': ');
                if ($pos !== false) {
                    $key   = substr($line, 0, $pos);
                    $value = substr($line, $pos + 2);
                    $response[$key] = $value;
                }
            }

            // When skip_events=true, discard Event-only packets and keep reading
            // until we get a packet with a Response key (direct command reply).
            if ($skip_events && !isset($response['Response']) && isset($response['Event'])) {
                continue;
            }

            return $response;
        }

        return [];
    }
}
