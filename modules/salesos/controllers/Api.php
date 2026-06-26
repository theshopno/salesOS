<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Internal REST-style API for JS frontend.
 * All methods return JSON. No page renders.
 */
class Api extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('salesos/salesos_model');
        $this->load->model('salesos/agents_model');

        if (!staff_can('view', 'salesos')) {
            $this->_json(['success' => false, 'error' => 'Access denied'], 403);
        }
    }

    // ── Originate outbound call ───────────────────────────────────────────────

    public function originate()
    {
        if (!staff_can('make', 'salesos')) {
            return $this->_json(['success' => false, 'error' => 'No permission to make calls']);
        }

        $number = $this->input->post('number');
        if (!$number) {
            return $this->_json(['success' => false, 'error' => 'Number required']);
        }

        $staff_id = get_staff_user_id();
        $agent    = $this->agents_model->get_by_staff_id($staff_id);

        if (!$agent) {
            return $this->_json(['success' => false, 'error' => 'No extension mapped for your account. Contact admin.']);
        }

        $vars = [];
        if ($lead_id = $this->input->post('lead_id')) {
            $vars['CRM_LEAD_ID'] = (int) $lead_id;
        }
        if ($contact_id = $this->input->post('contact_id')) {
            $vars['CRM_CONTACT_ID'] = (int) $contact_id;
        }

        $this->load->library('salesos/ami_service');
        $result = $this->ami_service->originate($number, (int) $agent['id'], $vars);

        // Pre-log the outbound call (will be confirmed by CDR sync)
        if ($result['success']) {
            $lead_id    = $vars['CRM_LEAD_ID']    ?? null;
            $contact_id = $vars['CRM_CONTACT_ID'] ?? null;

            $this->salesos_model->upsert_call([
                'uniqueid'    => $result['action_id'],
                'calldate'    => date('Y-m-d H:i:s'),
                'direction'   => 'outbound',
                'src'         => $agent['extension'],
                'dst'         => $number,
                'disposition' => 'PENDING',
                'agent_id'    => (int) $agent['id'],
                'lead_id'     => $lead_id,
                'contact_id'  => $contact_id,
                'match_type'  => $lead_id ? 'lead' : ($contact_id ? 'contact' : 'none'),
                'synced_at'   => date('Y-m-d H:i:s'),
            ]);

            // Write event log
            $this->salesos_model->log_event('call.originated', [
                'staff_id'    => $staff_id,
                'agent_id'    => (int) $agent['id'],
                'entity_type' => $lead_id ? 'lead' : ($contact_id ? 'contact' : null),
                'entity_id'   => $lead_id ?: $contact_id,
                'description' => 'Outbound call initiated to ' . $number,
                'number'      => $number,
                'extension'   => $agent['extension'],
                'action_id'   => $result['action_id'],
            ]);

            // Write lead timeline entry
            if ($lead_id && get_option('salesos_timeline_enabled') == '1') {
                $this->load->model('leads_model');
                if (method_exists($this->leads_model, 'log_lead_activity')) {
                    $ext = $agent['extension'];
                    $this->leads_model->log_lead_activity(
                        (int) $lead_id,
                        "[SalesOS] Outbound call initiated to {$number} from ext {$ext}",
                        'salesos',
                        json_encode([
                            'action_id' => $result['action_id'],
                            'extension' => $ext,
                            'number'    => $number,
                        ])
                    );
                }
            }
        }

        $this->_json($result);
    }

    // ── Active calls polling (for popup) ──────────────────────────────────────

    public function active_calls()
    {
        $staff_id     = get_staff_user_id();
        $active_calls = $this->salesos_model->get_active_calls($staff_id);
        $this->_json(['success' => true, 'calls' => $active_calls]);
    }

    // ── Phone lookup ──────────────────────────────────────────────────────────

    public function lookup()
    {
        $phone = $this->input->get('phone') ?: $this->input->post('phone');
        if (!$phone) {
            return $this->_json(['success' => false, 'error' => 'Phone required']);
        }

        $match = $this->salesos_model->match_phone($phone);
        $this->_json(['success' => true, 'match' => $match]);
    }

    // ── Call history for phone ────────────────────────────────────────────────

    public function history()
    {
        $phone = $this->input->get('phone');
        if (!$phone) {
            return $this->_json(['success' => false, 'error' => 'Phone required']);
        }
        $calls = $this->salesos_model->get_calls_for_phone($phone, 20);
        $this->_json(['success' => true, 'calls' => $calls]);
    }

    // ── AMI connection test ───────────────────────────────────────────────────

    public function test_ami()
    {
        if (!staff_can('settings', 'salesos')) {
            return $this->_json(['success' => false, 'error' => 'Access denied']);
        }

        $this->load->library('salesos/ami_service');
        $ok = $this->ami_service->connect();

        if ($ok) {
            $this->ami_service->disconnect();
            $this->_json(['success' => true, 'message' => 'AMI connected successfully']);
        } else {
            $this->_json(['success' => false, 'error' => $this->ami_service->get_last_error()]);
        }
    }

    // ── CDR sync trigger ──────────────────────────────────────────────────────

    public function sync_cdr()
    {
        if (!staff_can('settings', 'salesos')) {
            return $this->_json(['success' => false, 'error' => 'Access denied']);
        }

        try {
            $this->load->library('salesos/cdr_sync_service');
            $result = $this->cdr_sync_service->sync();
            return $this->_json($result);
        } catch (Throwable $e) {
            log_message('error', '[SalesOS] sync_cdr failed: ' . $e->getMessage());
            return $this->_json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // ── Agent login / logout ──────────────────────────────────────────────────

    public function agent_login()
    {
        $staff_id = get_staff_user_id();
        $this->agents_model->set_login_state($staff_id, true);
        $this->_json(['success' => true]);
    }

    public function agent_logout()
    {
        $staff_id = get_staff_user_id();
        $this->agents_model->set_login_state($staff_id, false);
        $this->_json(['success' => true]);
    }

    public function agent_status()
    {
        $staff_id = get_staff_user_id();
        $agent    = $this->agents_model->get_by_staff_id($staff_id);
        $this->_json([
            'success'      => true,
            'has_extension' => (bool) $agent,
            'extension'    => $agent['extension'] ?? null,
            'is_logged_in' => (bool) ($agent['is_logged_in'] ?? false),
        ]);
    }

    // ── Popup mark seen ───────────────────────────────────────────────────────

    public function popup_seen($id)
    {
        $this->salesos_model->mark_popup_shown((int) $id);
        $this->_json(['success' => true]);
    }

    // ── Hangup ───────────────────────────────────────────────────────────────

    public function hangup()
    {
        if (!staff_can('make', 'salesos')) {
            return $this->_json(['success' => false, 'error' => 'Access denied']);
        }

        $channel = $this->input->post('channel');
        if (!$channel) {
            return $this->_json(['success' => false, 'error' => 'Channel required']);
        }

        $this->load->library('salesos/ami_service');
        $ok = $this->ami_service->hangup($channel);
        $this->_json(['success' => $ok]);
    }

    // ── System health snapshot ────────────────────────────────────────────────

    public function health()
    {
        if (!staff_can('settings', 'salesos')) {
            return $this->_json(['success' => false, 'error' => 'Access denied'], 403);
        }

        $t0     = microtime(true);
        $now    = time();
        $checks = [];

        // ── Redis ─────────────────────────────────────────────────────────────
        $redis  = null;
        $r_ok   = false;
        if (extension_loaded('redis')) {
            try {
                $redis = new Redis();
                $r_t0  = microtime(true);
                $redis->connect(
                    get_option('salesos_redis_host') ?: '127.0.0.1',
                    (int) (get_option('salesos_redis_port') ?: 6379),
                    2.0
                );
                $pw = get_option('salesos_redis_password') ?: '';
                if ($pw !== '') $redis->auth($pw);
                $redis->ping();
                $r_ok = true;
                $checks['redis'] = [
                    'status'     => 'ok',
                    'latency_ms' => round((microtime(true) - $r_t0) * 1000, 2),
                ];
            } catch (Exception $e) {
                $checks['redis'] = ['status' => 'error', 'error' => $e->getMessage()];
            }
        } else {
            $checks['redis'] = ['status' => 'error', 'error' => 'phpredis extension not loaded'];
        }

        // ── Daemon liveness (via Redis lock keys) ─────────────────────────────
        $daemons = [
            'ami_consumer'   => ['key' => 'salesos:lock:ami_consumer',   'stale_after' => 35],
            'event_archiver' => ['key' => 'salesos:lock:event_archiver', 'stale_after' => 70],
            'ws_server'      => ['key' => 'salesos:lock:ws_server',      'stale_after' => 35],
        ];

        foreach ($daemons as $name => $d) {
            if (!$r_ok) {
                $checks[$name] = ['status' => 'unknown', 'reason' => 'redis_unavailable'];
                continue;
            }
            try {
                $val = $redis->get($d['key']);
                $ttl = $redis->ttl($d['key']);
                if ($val === false || $val === null) {
                    $checks[$name] = ['status' => 'down', 'reason' => 'lock_absent'];
                } elseif ($ttl > 0 && ($d['stale_after'] - $ttl) > $d['stale_after']) {
                    $checks[$name] = ['status' => 'stale', 'pid_host' => $val, 'ttl_remaining' => $ttl];
                } else {
                    $checks[$name] = ['status' => 'ok', 'pid_host' => $val, 'ttl_remaining' => $ttl];
                }
            } catch (Exception $e) {
                $checks[$name] = ['status' => 'error', 'error' => $e->getMessage()];
            }
        }

        // ── WebSocket port reachability ────────────────────────────────────────
        $ws_port = (int) (get_option('salesos_ws_internal_port') ?: 8080);
        $ws_sock = @fsockopen('127.0.0.1', $ws_port, $errno, $errstr, 1);
        if ($ws_sock) {
            fclose($ws_sock);
            $checks['ws_port'] = ['status' => 'ok', 'port' => $ws_port];
        } else {
            $checks['ws_port'] = ['status' => 'down', 'port' => $ws_port, 'error' => $errstr];
        }

        // ── Active calls ──────────────────────────────────────────────────────
        $active_calls = 0;
        if ($r_ok) {
            try {
                $active_calls = (int) $redis->hLen('salesos:calls');
            } catch (Exception $e) {}
        }
        $checks['active_calls'] = $active_calls;

        // ── Stream lag per consumer group ─────────────────────────────────────
        $stream_lag = [];
        if ($r_ok) {
            $monitor_streams = ['calls', 'agents', 'recordings'];
            foreach ($monitor_streams as $s) {
                try {
                    $groups = $redis->xInfo('GROUPS', 'salesos:stream:' . $s);
                    foreach ($groups as $g) {
                        $gname = $g['name'] ?? '';
                        if (in_array($gname, ['archiver', 'ws-delivery'], true)) {
                            $stream_lag[$s][$gname] = (int) ($g['lag'] ?? 0);
                        }
                    }
                } catch (Exception $e) {}
            }
        }
        $checks['stream_lag'] = $stream_lag;

        // ── Last stream event ─────────────────────────────────────────────────
        $last_event = null;
        if ($r_ok) {
            try {
                $entries = $redis->xRevRange('salesos:stream:calls', '+', '-', 1);
                if (!empty($entries)) {
                    $fields = reset($entries);
                    $sid    = key($entries);
                    $ts_ms  = (int) explode('-', $sid)[0];
                    $ts     = (int) ($ts_ms / 1000);
                    $last_event = [
                        'stream_id'   => $sid,
                        'event_type'  => $fields['event_type'] ?? '',
                        'timestamp'   => $ts,
                        'seconds_ago' => max(0, $now - $ts),
                    ];
                }
            } catch (Exception $e) {}
        }
        $checks['last_event'] = $last_event;

        // ── DB tables sanity ──────────────────────────────────────────────────
        $db_ok = false;
        try {
            $cnt = $this->db->count_all(db_prefix() . 'salesos_agents');
            $checks['db'] = ['status' => 'ok', 'agents' => $cnt];
            $db_ok = true;
        } catch (Exception $e) {
            $checks['db'] = ['status' => 'error', 'error' => $e->getMessage()];
        }

        // ── Overall status ────────────────────────────────────────────────────
        $critical_down = !$r_ok
            || $checks['ami_consumer']['status']   === 'down'
            || $checks['event_archiver']['status'] === 'down';

        $any_degraded = $checks['ws_server']['status'] !== 'ok'
            || $checks['ws_port']['status']        !== 'ok'
            || !$db_ok;

        if ($critical_down) {
            $overall = 'down';
        } elseif ($any_degraded) {
            $overall = 'degraded';
        } else {
            $overall = 'ok';
        }

        $this->_json([
            'status'        => $overall,
            'timestamp'     => $now,
            'response_ms'   => round((microtime(true) - $t0) * 1000, 2),
            'checks'        => $checks,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function _json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
