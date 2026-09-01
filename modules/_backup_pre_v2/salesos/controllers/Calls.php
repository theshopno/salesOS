<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Calls extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('salesos/salesos_model');
        $this->load->model('salesos/agents_model');
        if (!staff_can('view', 'salesos')) {
            access_denied('SalesOS');
        }
    }

    public function index()
    {
        $filters = [
            'direction'   => $this->input->get('direction'),
            'disposition' => $this->input->get('disposition'),
            'date_from'   => $this->input->get('date_from') ?: date('Y-m-d', strtotime('-7 days')),
            'date_to'     => $this->input->get('date_to')   ?: date('Y-m-d'),
            'search'      => $this->input->get('search'),
            'agent_id'    => $this->input->get('agent_id'),
        ];

        $page   = max(1, (int) $this->input->get('page'));
        $limit  = 25;
        $offset = ($page - 1) * $limit;
        $total  = $this->salesos_model->count_calls($filters);

        $data['title']   = 'Call Logs';
        $data['calls']   = $this->salesos_model->get_calls($filters, $limit, $offset);
        $data['filters'] = $filters;
        $data['total']   = $total;
        $data['page']    = $page;
        $data['pages']   = ceil($total / $limit);
        $data['agents']  = $this->agents_model->get_all(true);

        $this->load->view('salesos/calls/index', $data);
    }

    public function detail($id)
    {
        $call = $this->salesos_model->get_call((int) $id);
        if (!$call) {
            show_404();
        }

        // Enrich with lead/contact/client
        $entity = null;
        if ($call['match_type'] === 'lead' && $call['lead_id']) {
            $this->load->model('leads_model');
            $entity = $this->leads_model->get($call['lead_id']);
        } elseif (in_array($call['match_type'], ['contact', 'client']) && $call['client_id']) {
            $this->load->model('clients_model');
            $entity = $this->clients_model->get($call['client_id']);
        }

        $data['title']  = 'Call Detail';
        $data['call']   = $call;
        $data['entity'] = $entity;

        // Call events timeline
        $data['call_events'] = $this->db->query(
            "SELECT event_type, from_state, to_state, direction, queue_name, agent_id, payload, created_at
             FROM " . db_prefix() . "salesos_call_events
             WHERE call_uniqueid = ? ORDER BY created_at ASC LIMIT 200",
            [$call['uniqueid']]
        )->result_array();

        // Wrap-up data (if completed)
        $data['wrap_up'] = $this->db->get_where(db_prefix() . 'salesos_wrap_up',
            ['call_id' => $call['id']])->row_array() ?: null;

        // Recording proxied through CRM HTTPS to avoid mixed-content blocking
        $data['recording_url'] = '';
        if (!empty($call['recordingfile'])) {
            $file = basename($call['recordingfile']);
            if ($file) {
                $data['recording_url'] = admin_url('salesos/calls/recording/' . rawurlencode($file));
            }
        }

        $this->load->view('salesos/calls/detail', $data);
    }

    public function sync()
    {
        if (!staff_can('settings', 'salesos')) {
            access_denied('SalesOS');
        }

        $this->load->library('salesos/cdr_sync_service');
        $result = $this->cdr_sync_service->sync();

        if ($this->input->is_ajax_request()) {
            echo json_encode($result);
            return;
        }

        if ($result['success']) {
            set_alert('success', 'Synced ' . $result['synced'] . ' records.');
        } else {
            set_alert('danger', 'Sync error: ' . ($result['error'] ?? 'unknown'));
        }
        redirect(admin_url('salesos/calls'));
    }

    public function update_note($id)
    {
        $this->_check_ajax();
        $note = $this->input->post('notes');
        $this->salesos_model->update_call((int) $id, ['notes' => $note]);
        echo json_encode(['success' => true]);
    }

    public function remap_lead($id)
    {
        $this->_check_ajax();
        $lead_id = (int) $this->input->post('lead_id');
        $this->salesos_model->update_call((int) $id, [
            'lead_id'    => $lead_id ?: null,
            'match_type' => $lead_id ? 'manual' : 'none',
        ]);
        echo json_encode(['success' => true]);
    }

    /**
     * Proxy recording WAV files through HTTPS so the browser can play them
     * without mixed-content blocking (CRM is HTTPS, PBX recording server is HTTP).
     */
    public function recording($filename)
    {
        $filename = basename((string) $filename);
        if (!preg_match('/^[a-zA-Z0-9\-_.]+$/', $filename)) {
            show_404();
            return;
        }

        $base = rtrim(salesos_setting('salesos_recordings_url', '', 'SALESOS_RECORDINGS_URL'), '/');
        if (!$base) {
            show_404();
            return;
        }

        $url = $base . '/' . $filename;
        $ctx = stream_context_create(['http' => ['timeout' => 20, 'ignore_errors' => true]]);
        $data = @file_get_contents($url, false, $ctx);

        if ($data === false) {
            show_404();
            return;
        }

        header('Content-Type: audio/wav');
        header('Content-Length: ' . strlen($data));
        header('Cache-Control: private, max-age=3600');
        header('Accept-Ranges: none');
        echo $data;
    }

    public function delete($id)
    {
        if (!staff_can('delete', 'salesos')) {
            access_denied('SalesOS');
        }
        $this->salesos_model->delete_call((int) $id);
        set_alert('success', 'Call record deleted.');
        redirect(admin_url('salesos/calls'));
    }

    private function _check_ajax()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }
    }
}
