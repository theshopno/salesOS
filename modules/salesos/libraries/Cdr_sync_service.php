<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Pulls CDR records from the Asterisk PBX database and syncs them
 * into salesos_calls with lead/contact matching and agent mapping.
 */
class Cdr_sync_service
{
    private $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->model('salesos/salesos_model');
        $this->CI->load->model('salesos/agents_model');
        // Helper already loaded by the module hook; loading via main CI instance
        // fails (no module path context) — do not call load->helper() here.
    }

    public function sync(): array
    {
        // Prevent overlapping syncs with a simple lock
        $last = $this->CI->salesos_model->get_last_sync();
        if ($last && $last['status'] === 'running') {
            $started = strtotime($last['synced_at']);
            if (time() - $started < 120) {
                return ['success' => false, 'error' => 'Sync already running'];
            }
        }

        $this->CI->salesos_model->log_sync(['status' => 'running', 'records_synced' => 0]);

        try {
            $result = $this->_do_sync();
            $this->CI->salesos_model->log_sync([
                'status'         => 'ok',
                'last_sync'      => date('Y-m-d H:i:s'),
                'records_synced' => $result['synced'],
            ]);
            return ['success' => true, 'synced' => $result['synced']];
        } catch (Throwable $e) {
            $this->CI->salesos_model->log_sync([
                'status'    => 'error',
                'error_msg' => $e->getMessage(),
            ]);
            log_message('error', '[SalesOS] CDR sync failed: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function _do_sync(): array
    {
        $batch = (int) (get_option('salesos_cdr_batch_size') ?: 100);

        // Determine sync start point
        $last  = $this->CI->salesos_model->get_last_sync();
        $since = ($last && !empty($last['last_sync']))
            ? date('Y-m-d H:i:s', strtotime($last['last_sync']) - 600)
            : date('Y-m-d H:i:s', strtotime('-24 hours'));

        $rows = $this->CI->salesos_model->fetch_pbx_cdr($since, $batch);
        if ($rows === []) {
            $db_ok = $this->CI->salesos_model->get_pbx_db();
            if (!$db_ok) {
                return ['synced' => 0, 'error' => 'PBX database connection failed'];
            }
        }

        if (empty($rows)) {
            return ['synced' => 0];
        }

        $extension_map = $this->CI->agents_model->get_extension_map();
        $our_trunk_caller_id = get_option('salesos_caller_id') ?: '09649699699';
        $synced = 0;

        foreach ($rows as $row) {
            $call = $this->_map_cdr_row($row, $extension_map, $our_trunk_caller_id);
            if (!$call) {
                continue;
            }

            // Phone matching
            $phone   = $call['direction'] === 'inbound' ? $call['src'] : $call['dst'];
            $match   = $this->CI->salesos_model->match_phone($phone);

            $call['match_type'] = $match['type'];
            if ($match['type'] === 'lead') {
                $call['lead_id'] = $match['id'];
            } elseif ($match['type'] === 'contact') {
                $call['contact_id'] = $match['id'];
                $call['client_id']  = $match['client_id'] ?? null;
            } elseif ($match['type'] === 'client') {
                $call['client_id'] = $match['id'];
            }

            // Caller/dest names
            if ($match['type'] !== 'none') {
                if ($call['direction'] === 'inbound') {
                    $call['src_name'] = $match['name'];
                } else {
                    $call['dst_name'] = $match['name'];
                }
            }

            $call['synced_at'] = date('Y-m-d H:i:s');

            $existing = $this->CI->salesos_model->get_call_by_uniqueid($call['uniqueid']);
            $is_new   = !$existing;

            if ($is_new) {
                // Originate flow inserts a PENDING record with a generated uniqueid.
                // Find it by (src, dst, calldate proximity) and update it in-place.
                $pending = $this->CI->salesos_model->find_pending_call(
                    $call['src'], $call['dst'], $call['calldate'], $call['agent_id'] ?? null
                );
                if ($pending) {
                    // Preserve the existing row id and uniqueid so the UI no longer
                    // sees a lingering PENDING record for the same call.
                    $call['id']       = $pending['id'];
                    $call['uniqueid'] = $pending['uniqueid'];

                    // When CDR dst='s' (agent never answered), Asterisk never ran
                    // the dialplan so no real destination was recorded. Keep the
                    // PENDING record's real number and its lead/contact linkage,
                    // then re-derive $match so timeline/event log use the right entity.
                    if ($call['dst'] === 's' || $call['dst'] === '') {
                        $call['dst']        = $pending['dst'];
                        $call['lead_id']    = $pending['lead_id']    ?? null;
                        $call['contact_id'] = $pending['contact_id'] ?? null;
                        $call['client_id']  = $pending['client_id']  ?? null;
                        $call['match_type'] = $pending['match_type'] ?? 'none';
                        $call['dst_name']   = $pending['dst_name']   ?? null;

                        // Re-derive $match from the restored real destination number
                        $match = $this->CI->salesos_model->match_phone($call['dst']);
                        $call['match_type'] = $match['type'];
                    }

                    $this->CI->salesos_model->update_call((int) $pending['id'], $call);
                    $this->_write_timeline($call, $match);
                    $this->_log_event($call, $match);
                    $synced++;
                    continue;
                }
            }

            $this->CI->salesos_model->upsert_call($call);

            if ($is_new) {
                $this->_write_timeline($call, $match);
                $this->_log_event($call, $match);
            }

            $synced++;
        }

        // Expire any PENDING records older than 30 minutes that found no CDR match.
        $this->CI->salesos_model->expire_stale_pending_calls(30);

        return ['synced' => $synced];
    }

    private function _map_cdr_row(array $row, array $ext_map, string $trunk_cid): ?array
    {
        if (empty($row['uniqueid'])) {
            return null;
        }

        $src = $row['src'] ?? '';
        $dst = $row['dst'] ?? '';

        // Determine direction
        $direction = $this->_detect_direction($src, $dst, $ext_map, $trunk_cid);

        // Find agent from channel or extension
        $agent_id  = null;
        $extension = null;

        // Channel like PJSIP/1001-xxxxx → extension 1001
        if (!empty($row['channel'])) {
            preg_match('/PJSIP\/(\d+)/', $row['channel'], $m);
            if (isset($m[1]) && isset($ext_map[$m[1]])) {
                $agent     = $ext_map[$m[1]];
                $agent_id  = $agent['id'];
                $extension = $m[1];
            }
        }

        if (!$agent_id && isset($ext_map[$src])) {
            $agent     = $ext_map[$src];
            $agent_id  = $agent['id'];
            $extension = $src;
        }

        return [
            'uniqueid'      => $row['uniqueid'],
            'linkedid'      => $row['linkedid'] ?? null,
            'calldate'      => $row['calldate'] ?? date('Y-m-d H:i:s'),
            'direction'     => $direction,
            'src'           => $src,
            'dst'           => $dst,
            'extension'     => $extension,
            'channel'       => $row['channel'] ?? null,
            'dstchannel'    => $row['dstchannel'] ?? null,
            'duration'      => (int) ($row['duration'] ?? 0),
            'billsec'       => (int) ($row['billsec'] ?? 0),
            'disposition'   => $this->_normalize_disposition($row['disposition'] ?? ''),
            'recordingfile' => ($row['recordingfile'] ?? '') ?: ($row['userfield'] ?? null),
            'agent_id'      => $agent_id,
        ];
    }

    private function _write_timeline(array $call, array $match): void
    {
        if (get_option('salesos_timeline_enabled') != '1') {
            return;
        }

        if ($match['type'] === 'none') {
            return;
        }

        $dir     = ucfirst($call['direction']);
        $disp    = ucwords(strtolower($call['disposition']));
        $dur     = salesos_format_duration((int) $call['billsec']);
        $ext     = $call['extension'] ? ' (ext ' . $call['extension'] . ')' : '';
        $reclink = '';
        if (!empty($call['recordingfile'])) {
            $reclink = ' | Recording: ' . $call['recordingfile'];
        }

        $desc = "[SalesOS] {$dir} call {$disp} — Duration: {$dur}{$ext}{$reclink}";

        if ($match['type'] === 'lead') {
            $this->CI->load->model('leads_model');
            if (method_exists($this->CI->leads_model, 'log_lead_activity')) {
                $this->CI->leads_model->log_lead_activity(
                    $match['id'],
                    $desc,
                    'salesos',
                    json_encode([
                        'uniqueid'    => $call['uniqueid'],
                        'direction'   => $call['direction'],
                        'disposition' => $call['disposition'],
                        'billsec'     => $call['billsec'],
                        'src'         => $call['src'],
                        'dst'         => $call['dst'],
                    ])
                );
            }
        }
    }

    private function _log_event(array $call, array $match): void
    {
        $synced_call = $this->CI->salesos_model->get_call_by_uniqueid($call['uniqueid']);
        $call_id = $synced_call['id'] ?? null;

        $payload = [
            'call_id'     => $call_id,
            'description' => ucfirst($call['direction']) . ' call ' . strtolower($call['disposition']),
            'direction'   => $call['direction'],
            'disposition' => $call['disposition'],
            'billsec'     => $call['billsec'],
            'src'         => $call['src'],
            'dst'         => $call['dst'],
        ];

        if ($match['type'] !== 'none') {
            $payload['entity_type'] = $match['type'];
            $payload['entity_id']   = $match['id'];
        }

        $this->CI->salesos_model->log_event('cdr.synced', $payload);
    }

    private function _normalize_disposition(string $raw): string
    {
        // Asterisk cdr_odbc may write numeric disposition values in some configs.
        // Map numeric → string. Values from Asterisk's cdr.h enum.
        $num_map = ['0' => 'NO ANSWER', '1' => 'NO ANSWER', '2' => 'NO ANSWER',
                    '3' => 'ANSWERED',  '4' => 'BUSY',       '16' => 'FAILED'];
        $upper = strtoupper(trim($raw));
        if ($upper === '') {
            return 'UNKNOWN';
        }
        if (isset($num_map[$upper])) {
            return $num_map[$upper];
        }
        $valid = ['NO ANSWER', 'ANSWERED', 'BUSY', 'FAILED'];
        return in_array($upper, $valid) ? $upper : 'UNKNOWN';
    }

    private function _detect_direction(string $src, string $dst, array $ext_map, string $trunk_cid): string
    {
        $src_is_agent = isset($ext_map[$src]);
        $dst_is_agent = isset($ext_map[$dst]);

        if ($src_is_agent && $dst_is_agent) {
            return 'internal';
        }
        if ($src_is_agent || $src === $trunk_cid) {
            return 'outbound';
        }
        if ($dst_is_agent) {
            return 'inbound';
        }

        // Fallback: short src is likely internal/extension
        if (strlen($src) <= 4) {
            return 'outbound';
        }

        return 'inbound';
    }
}
