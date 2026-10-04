<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Asterisk Manager Interface (AMI) TCP socket client.
 *
 * Connection details come from pbxpilot_settings (§8a) — never hardcoded —
 * so pointing this at a different PBX is a Settings save, not a code change.
 * Every public method fails soft (returns false / [] / an error array) rather
 * than throwing, per §6: the rest of the CRM must never hard-error because
 * Asterisk is briefly unreachable.
 */
class Ami_service
{
    private $socket     = null;
    private $connected  = false;
    private $last_error = '';
    private $timeout    = 5;

    private function host(): string     { return pbxpilot_get_option('pbxpilot_ami_host', ''); }
    private function port(): int        { return (int) pbxpilot_get_option('pbxpilot_ami_port', 5038); }
    private function username(): string { return pbxpilot_get_option('pbxpilot_ami_username', ''); }
    private function secret(): string   { return pbxpilot_get_option('pbxpilot_ami_secret', ''); }

    public function get_last_error(): string
    {
        return $this->last_error;
    }

    // ── Connection ───────────────────────────────────────────────────────────

    public function connect(): bool
    {
        if ($this->connected) {
            return true;
        }

        $host = $this->host();
        if ($host === '') {
            $this->last_error = 'AMI host not configured (Settings → Connection)';
            return false;
        }

        $this->socket = @fsockopen($host, $this->port(), $errno, $errstr, $this->timeout);
        if (!$this->socket) {
            $this->last_error = "AMI connect failed: $errstr ($errno)";
            return false;
        }
        stream_set_timeout($this->socket, $this->timeout);

        $banner = fgets($this->socket, 256);
        if ($banner === false || strpos($banner, 'Asterisk Call Manager') === false) {
            $this->last_error = 'Unexpected AMI banner: ' . var_export($banner, true);
            $this->close_socket();
            return false;
        }

        $this->send_action([
            'Action'   => 'Login',
            'Username' => $this->username(),
            'Secret'   => $this->secret(),
        ]);

        $resp = $this->read_response();
        if (!isset($resp['Response']) || $resp['Response'] !== 'Success') {
            $this->last_error = 'AMI auth failed: ' . ($resp['Message'] ?? 'unknown response');
            $this->close_socket();
            return false;
        }

        $this->connected = true;
        return true;
    }

    public function disconnect(): void
    {
        if ($this->socket) {
            $this->send_action(['Action' => 'Logoff']);
        }
        $this->close_socket();
    }

    private function close_socket(): void
    {
        if ($this->socket) {
            fclose($this->socket);
        }
        $this->socket    = null;
        $this->connected = false;
    }

    // ── Diagnostics (Settings → Connection → Test Connection) ─────────────────

    /**
     * @return array{ok: bool, message: string, banner?: string}
     */
    public function test_connection(): array
    {
        if (!$this->connect()) {
            return ['ok' => false, 'message' => $this->last_error];
        }
        $this->disconnect();
        return ['ok' => true, 'message' => 'Connected and authenticated successfully.'];
    }

    // ── Originate (click-to-call) ───────────────────────────────────────────

    public function originate(string $number, int $agent_id = 0, array $vars = []): array
    {
        if (!$this->connect()) {
            return ['success' => false, 'error' => $this->last_error];
        }

        $extension = '';
        if ($agent_id > 0) {
            $CI = &get_instance();
            $CI->load->model(PBXPILOT_MODULE_NAME . '/agents_model');
            $agent = $CI->agents_model->get_by_staff_id($agent_id);
            $extension = $agent['extension'] ?? '';
        }

        if ($extension === '') {
            $this->disconnect();
            return ['success' => false, 'error' => 'No extension mapped for this agent (Agents settings).'];
        }

        $default_vars = ['CDR(accountcode)' => 'crm-outbound'];
        $all_vars     = array_merge($default_vars, $vars);
        $var_str      = implode(',', array_map(fn ($k, $v) => "$k=$v", array_keys($all_vars), $all_vars));

        $action_id = uniqid('crm-');
        $this->send_action([
            'Action'   => 'Originate',
            'Channel'  => "PJSIP/{$extension}",
            'Context'  => 'from-internal',
            'Exten'    => $number,
            'Priority' => '1',
            'Timeout'  => '60000',
            'Variable' => $var_str,
            'Async'    => 'true',
            'ActionID' => $action_id,
        ]);

        $resp = $this->read_response(true);
        $this->disconnect();

        if (isset($resp['Response']) && $resp['Response'] === 'Success') {
            return ['success' => true, 'action_id' => $action_id];
        }

        return ['success' => false, 'error' => $resp['Message'] ?? 'Originate failed'];
    }

    /**
     * Outbound call directly to a customer entering an IVR dialplan context.
     *
     * @param string $number Customer phone number (11 digits, e.g. 01XXXXXXXXX)
     * @param string $context Asterisk dialplan context (e.g. 'ivr-order-confirm')
     * @param array  $vars Channel variables (e.g. ['ORDER_ID' => 123, 'CUSTOMER_PHONE' => '01...'])
     * @param string $caller_id Caller ID presented to customer
     * @return array{success: bool, action_id?: string, error?: string}
     */
    public function originate_ivr(string $number, string $context, array $vars = [], string $caller_id = '09638881188'): array
    {
        if (!$this->connect()) {
            return ['success' => false, 'error' => $this->last_error];
        }

        $default_vars = ['CDR(accountcode)' => 'ivr-outbound'];
        $all_vars     = array_merge($default_vars, $vars);
        $var_str      = implode(',', array_map(fn ($k, $v) => "$k=$v", array_keys($all_vars), $all_vars));

        $channel = preg_match('/^10[1-9]|11[01]$/', $number)
            ? "PJSIP/{$number}"
            : "PJSIP/{$number}@trunk_ecare";

        $action_id = uniqid('ivr-');
        $this->send_action([
            'Action'   => 'Originate',
            'Channel'  => $channel,
            'Context'  => $context,
            'Exten'    => 's',
            'Priority' => '1',
            'CallerID' => $caller_id,
            'Timeout'  => '45000',
            'Variable' => $var_str,
            'Async'    => 'true',
            'ActionID' => $action_id,
        ]);

        $resp = $this->read_response(true);
        $this->disconnect();

        if (isset($resp['Response']) && $resp['Response'] === 'Success') {
            return ['success' => true, 'action_id' => $action_id];
        }

        return ['success' => false, 'error' => $resp['Message'] ?? 'IVR Originate failed'];
    }

    public function hangup(string $channel): bool
    {
        if (!$this->connect()) {
            return false;
        }
        $this->send_action(['Action' => 'Hangup', 'Channel' => $channel]);
        $resp = $this->read_response(true);
        $this->disconnect();

        return isset($resp['Response']) && $resp['Response'] === 'Success';
    }

    public function get_active_channels(): array
    {
        if (!$this->connect()) {
            return [];
        }

        $this->send_action(['Action' => 'CoreShowChannels', 'ActionID' => uniqid('ch-')]);

        $channels = [];
        while (true) {
            $event = $this->read_response();
            if (empty($event)) {
                break;
            }
            if (($event['Event'] ?? '') === 'CoreShowChannel') {
                $channels[] = $event;
            }
            if (($event['Event'] ?? '') === 'CoreShowChannelsComplete') {
                break;
            }
        }

        $this->disconnect();
        return $channels;
    }

    // ── Raw CLI passthrough (e.g. "file convert" for recording playback) ──────

    /** @return array{success: bool, output: string, error: ?string} */
    public function run_command(string $command): array
    {
        if (!$this->connect()) {
            return ['success' => false, 'output' => '', 'error' => $this->last_error];
        }

        $this->send_action(['Action' => 'Command', 'Command' => $command, 'ActionID' => uniqid('cmd-')]);
        $output = $this->read_command_response();
        $this->disconnect();

        return ['success' => true, 'output' => $output, 'error' => null];
    }

    /**
     * Discover live extensions from Asterisk PBX via 'pjsip show endpoints'.
     * Filters out non-numeric endpoints (e.g. trunks) and parses status.
     *
     * @return array<int, array{extension: string, status: string, is_online: bool, contact: string}>
     */
    public function get_discovered_extensions(): array
    {
        $res = $this->run_command('pjsip show endpoints');
        if (!$res['success'] || empty($res['output'])) {
            return [];
        }

        $endpoints = [];
        $lines = explode("\n", $res['output']);
        $current_ext = null;
        $current_state = '';
        $current_contact = '';

        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^Endpoint:\s+([^\s\/]+)/', $line, $ep_match)) {
                // Flush previous endpoint if pending
                if ($current_ext !== null) {
                    $is_unavail = (stripos($current_state, 'Unavailable') !== false);
                    $is_online  = !$is_unavail && (stripos($current_state, 'Not in use') !== false || stripos($current_state, 'Available') !== false || stripos($current_state, 'In use') !== false);
                    $endpoints[$current_ext] = [
                        'extension' => $current_ext,
                        'status'    => $is_unavail ? 'Unavailable' : ($is_online ? 'Available' : $current_state),
                        'is_online' => $is_online,
                        'contact'   => $current_contact,
                    ];
                }

                $raw_name = $ep_match[1];
                if (ctype_digit($raw_name)) {
                    $current_ext     = $raw_name;
                    $current_state   = '';
                    $current_contact = '';
                    if (preg_match('/^Endpoint:\s+[0-9]+\/[0-9]+\s+([A-Za-z ]+)/', $line, $sm)) {
                        $current_state = trim($sm[1]);
                    }
                } else {
                    // Non-numeric endpoint (e.g. trunk_ecare, trunk_munzu)
                    $current_ext     = null;
                    $current_state   = '';
                    $current_contact = '';
                }
                continue;
            }

            if ($current_ext !== null && preg_match('/Contact:\s+([^\s]+)\s+([^\s]+)\s+([A-Za-z]+)/', $line, $cm)) {
                $current_contact = $cm[1];
                if (stripos($cm[3], 'Avail') !== false) {
                    $current_state = 'Available';
                }
            }
        }

        if ($current_ext !== null) {
            $is_unavail = (stripos($current_state, 'Unavailable') !== false);
            $is_online  = !$is_unavail && (stripos($current_state, 'Not in use') !== false || stripos($current_state, 'Available') !== false || stripos($current_state, 'In use') !== false);
            $endpoints[$current_ext] = [
                'extension' => $current_ext,
                'status'    => $is_unavail ? 'Unavailable' : ($is_online ? 'Available' : $current_state),
                'is_online' => $is_online,
                'contact'   => $current_contact,
            ];
        }

        ksort($endpoints, SORT_NATURAL);
        return array_values($endpoints);
    }

    /**
     * AMI's "Command" action reply isn't the usual flat Key: Value block, and
     * its shape varies by Asterisk version — two formats seen in the wild:
     *   - Older ("Response: Follows"): raw CLI text lines, terminated by a
     *     literal "--END COMMAND--" line, then the closing blank line.
     *   - Newer (18.26.4 confirmed, "Response: Success" + "Message: Command
     *     output follows"): each CLI text line arrives as its own
     *     "Output: <line>" field, terminated by the closing blank line.
     * Handles both so this works against whichever PBX (kutumbari, ecare,
     * or a future one) is configured.
     */
    private function read_command_response(): string
    {
        $deadline  = microtime(true) + $this->timeout;
        $lines     = [];
        $following = false; // "Response: Follows" format, raw lines incoming
        $started   = false; // we've reached OUR action's response body (as
                             // opposed to an unrelated Event: block that may
                             // arrive first, e.g. RTCP/channel events)

        while (microtime(true) < $deadline) {
            $line = fgets($this->socket, 4096);
            if ($line === false) {
                break;
            }
            $line = rtrim($line, "\r\n");

            if (str_starts_with($line, 'Output: ')) {
                $lines[]  = substr($line, 8);
                $started  = true;
                continue;
            }

            if ($line === 'Response: Follows') {
                $following = true;
                $started   = true;
                continue;
            }

            if (str_starts_with($line, 'Response:') || str_starts_with($line, 'Privilege:')
                || str_starts_with($line, 'ActionID:') || str_starts_with($line, 'Message:')) {
                continue;
            }

            if ($line === '--END COMMAND--') {
                break;
            }

            if ($line === '') {
                if ($started) {
                    break;
                }
                continue; // blank terminator of an unrelated event block
            }

            if ($following) {
                $lines[] = $line;
            }
            // else: a field of an unrelated Event: block — ignored
        }

        return implode("\n", $lines);
    }

    // ── Low-level I/O ─────────────────────────────────────────────────────────

    private function send_action(array $fields): void
    {
        $packet = '';
        foreach ($fields as $key => $value) {
            $packet .= "$key: $value\r\n";
        }
        $packet .= "\r\n";
        @fwrite($this->socket, $packet);
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
                    $response[substr($line, 0, $pos)] = substr($line, $pos + 2);
                }
            }

            if ($skip_events && !isset($response['Response']) && isset($response['Event'])) {
                continue;
            }

            return $response;
        }

        return [];
    }
}
