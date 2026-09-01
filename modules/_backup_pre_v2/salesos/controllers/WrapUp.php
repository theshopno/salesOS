<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * WrapUp — Post-call wrap-up save with outcome automation.
 * POST /salesos/wrapup/save
 * POST /salesos/wrapup/skip
 */
class WrapUp extends AdminController
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

    public function save(): void
    {
        $this->_check_ajax();

        $call_id     = (int) $this->input->post('call_id');
        $uniqueid    = trim((string) ($this->input->post('uniqueid') ?: ''));
        $session_id  = trim((string) ($this->input->post('session_id') ?: ''));
        $disposition = trim((string) ($this->input->post('disposition') ?: ''));
        $outcome     = trim((string) ($this->input->post('outcome') ?: ''));
        $notes       = trim((string) ($this->input->post('notes') ?: ''));
        $lead_status = trim((string) ($this->input->post('lead_status') ?: ''));
        $follow_up   = trim((string) ($this->input->post('follow_up_at') ?: ''));

        if (!$call_id && !$uniqueid) {
            $this->_json(['success' => false, 'error' => 'call_id or uniqueid required']);
            return;
        }

        // Mandatory fields check (supervisor bypass via permission)
        $is_supervisor = is_admin() || staff_can('settings', 'salesos');
        if (!$is_supervisor && (!$disposition || !$outcome)) {
            $this->_json(['success' => false, 'error' => 'Disposition and Outcome are required']);
            return;
        }

        $staff_id = get_staff_user_id();
        $agent    = $this->agents_model->get_by_staff_id($staff_id);

        // Resolve call_id from uniqueid if not given
        if (!$call_id && $uniqueid) {
            $row = $this->db->get_where(db_prefix() . 'salesos_calls', ['uniqueid' => $uniqueid])->row_array();
            if ($row) $call_id = (int) $row['id'];
        }

        $auto_actions = [];
        $follow_up_dt = null;

        // Outcome automation
        switch ($outcome) {
            case 'Interested':
                $follow_up_dt = date('Y-m-d H:i:s', strtotime('+1 day'));
                $auto_actions[] = $this->_create_task($call_id, $staff_id, 'follow_up',
                    'Follow up with interested customer', $follow_up_dt);
                break;
            case 'Busy':
                $follow_up_dt = date('Y-m-d H:i:s', strtotime('+2 hours'));
                $auto_actions[] = $this->_create_task($call_id, $staff_id, 'callback',
                    'Callback — was busy', $follow_up_dt);
                break;
            case 'No Answer':
                $follow_up_dt = date('Y-m-d H:i:s', strtotime('+24 hours'));
                $auto_actions[] = $this->_create_task($call_id, $staff_id, 'callback',
                    'Callback — no answer', $follow_up_dt);
                break;
            case 'Wrong Number':
                $auto_actions[] = 'flag_do_not_call';
                if ($uniqueid) {
                    $this->_flag_do_not_call($uniqueid);
                }
                break;
        }

        // Override with manual follow_up_at if provided
        if ($follow_up) {
            $follow_up_dt = date('Y-m-d H:i:s', strtotime($follow_up));
        }

        // Update call notes if provided
        if ($notes && $call_id) {
            $this->salesos_model->update_call($call_id, ['notes' => $notes]);
        }

        // Update lead status if provided and call is linked to a lead
        if ($lead_status && $call_id) {
            $call = $this->salesos_model->get_call($call_id);
            if ($call && $call['lead_id'] && $call['match_type'] === 'lead') {
                $this->db->update(db_prefix() . 'leads',
                    ['status' => $lead_status],
                    ['id'     => $call['lead_id']]
                );
                // Invalidate context cache for this entity
                $this->_bust_context_cache('lead', (int) $call['lead_id']);
            }
        }

        // Upsert wrap-up record
        $existing = $this->db->get_where(db_prefix() . 'salesos_wrap_up',
            ['call_id' => $call_id])->row_array();

        $record = [
            'call_id'              => $call_id,
            'uniqueid'             => $uniqueid ?: null,
            'session_id'           => $session_id ?: null,
            'agent_id'             => $agent ? (int) $agent['id'] : null,
            'staff_id'             => $staff_id,
            'disposition'          => $disposition ?: null,
            'outcome'              => $outcome ?: null,
            'notes'                => $notes ?: null,
            'lead_status'          => $lead_status ?: null,
            'follow_up_at'         => $follow_up_dt,
            'auto_actions_applied' => json_encode($auto_actions),
            'skipped'              => 0,
            'completed_at'         => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            $this->db->update(db_prefix() . 'salesos_wrap_up', $record, ['call_id' => $call_id]);
        } else {
            $this->db->insert(db_prefix() . 'salesos_wrap_up', $record);
        }

        // Update agent presence to READY in Redis
        if ($agent) {
            $this->_set_agent_ready($agent['extension'], $staff_id);
        }

        $this->_json(['success' => true, 'auto_actions' => $auto_actions]);
    }

    public function skip(): void
    {
        $this->_check_ajax();

        $is_supervisor = is_admin() || staff_can('settings', 'salesos');
        if (!$is_supervisor) {
            $this->_json(['success' => false, 'error' => 'Skip requires supervisor permission']);
            return;
        }

        $call_id    = (int) $this->input->post('call_id');
        $session_id = trim((string) ($this->input->post('session_id') ?: ''));
        $staff_id   = get_staff_user_id();
        $agent      = $this->agents_model->get_by_staff_id($staff_id);

        if ($call_id) {
            $this->db->insert_or_update(db_prefix() . 'salesos_wrap_up', [
                'call_id'     => $call_id,
                'session_id'  => $session_id ?: null,
                'staff_id'    => $staff_id,
                'skipped'     => 1,
                'completed_at'=> date('Y-m-d H:i:s'),
            ]);
        }

        if ($agent) {
            $this->_set_agent_ready($agent['extension'], $staff_id);
        }

        $this->_json(['success' => true]);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function _create_task(int $call_id, int $staff_id, string $type, string $name, string $due): string
    {
        try {
            // Look up entity linked to this call
            $call = $this->salesos_model->get_call($call_id);
            if (!$call || $call['match_type'] === 'none') return 'task_skipped_no_entity';

            $task = [
                'name'         => '[SalesOS] ' . $name,
                'rel_type'     => $call['match_type'],
                'rel_id'       => $call['lead_id'] ?: $call['contact_id'] ?: $call['client_id'],
                'startdate'    => date('Y-m-d'),
                'duedate'      => date('Y-m-d', strtotime($due)),
                'status'       => '1',
                'priority'     => '2',
                'description'  => 'Auto-created from call wrap-up. Call ID: ' . $call_id,
                'addedfrom'    => $staff_id,
                'dateadded'    => date('Y-m-d H:i:s'),
            ];

            $this->db->insert(db_prefix() . 'tasks', $task);
            return 'task_created:' . $type;
        } catch (Exception $e) {
            log_message('error', '[SalesOS] WrapUp task create failed: ' . $e->getMessage());
            return 'task_failed';
        }
    }

    private function _flag_do_not_call(string $uniqueid): void
    {
        try {
            $call = $this->db->get_where(db_prefix() . 'salesos_calls',
                ['uniqueid' => $uniqueid])->row_array();
            if (!$call) return;

            $phone = $call['direction'] === 'inbound' ? $call['src'] : $call['dst'];
            if (!$phone) return;

            $this->db->update(db_prefix() . 'salesos_phone_index',
                ['do_not_call' => 1], ['phone' => $phone]);

            // Bust Redis phone cache
            if (extension_loaded('redis')) {
                $r = new Redis();
                $r->connect(get_option('salesos_redis_host') ?: '127.0.0.1',
                            (int)(get_option('salesos_redis_port') ?: 6379), 1.0);
                $norm = preg_replace('/\D/', '', $phone);
                $r->del('salesos:pcache:' . $norm);
            }
        } catch (Exception $e) {}
    }

    private function _set_agent_ready(string $ext, int $staff_id): void
    {
        if (!extension_loaded('redis')) return;
        try {
            $r = new Redis();
            $r->connect(get_option('salesos_redis_host') ?: '127.0.0.1',
                        (int)(get_option('salesos_redis_port') ?: 6379), 1.0);
            $summary = json_decode($r->hGet('salesos:agents', $ext) ?: '{}', true) ?: [];
            $summary['presence']   = 'READY';
            $summary['updated_at'] = time();
            $r->hSet('salesos:agents', $ext, json_encode($summary));

            // Publish agent.ready event to stream so workspace cards update
            $event = [
                'version'    => '1',
                'timestamp'  => date('c'),
                'source'     => 'wrapup',
                'event_type' => 'agent.ready',
                'agent_ext'  => $ext,
                'staff_id'   => (string) $staff_id,
                'pbx_id'     => get_option('salesos_pbx_id') ?: 'pbx-01',
            ];
            $maxlen = (int)(get_option('salesos_stream_maxlen') ?: 50000);
            $r->xAdd('salesos:stream:agents', '*', $event, $maxlen, true);
        } catch (Exception $e) {}
    }

    private function _bust_context_cache(string $type, int $id): void
    {
        if (!extension_loaded('redis')) return;
        try {
            $r = new Redis();
            $r->connect(get_option('salesos_redis_host') ?: '127.0.0.1',
                        (int)(get_option('salesos_redis_port') ?: 6379), 1.0);
            $r->del('salesos:context:' . $type . ':' . $id);
        } catch (Exception $e) {}
    }

    private function _check_ajax(): void
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }
    }

    private function _json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
