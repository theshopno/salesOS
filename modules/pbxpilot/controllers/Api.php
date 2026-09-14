<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Api extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model(PBXPILOT_MODULE_NAME . '/pbxpilot_model');

        if (!staff_can('view', PBXPILOT_MODULE_NAME)) {
            access_denied('PBX Pilot API');
        }
    }

    /** POST — originate a click-to-call from the logged-in agent's extension. */
    public function click_to_call()
    {
        if (!$this->input->is_ajax_request() || !staff_can('make', PBXPILOT_MODULE_NAME)) {
            show_404();
        }

        $number = trim((string) $this->input->post('number'));
        if ($number === '') {
            echo json_encode(['success' => false, 'error' => 'Missing number']);
            return;
        }

        $this->load->library(PBXPILOT_MODULE_NAME . '/Ami_service');
        $result = $this->ami_service->originate($number, get_staff_user_id());

        echo json_encode($result);
    }

    /**
     * GET /admin/pbxpilot/api/screenpop?phone=<caller id>
     * The "Desktop Trigger" (§4 — absorbs pbxpopup's cmdIncomingCall URL
     * handler): MicroSIP is configured to open this URL when a call rings.
     * Redirects straight to the matching lead/client; falls back to Perfex's
     * own global search when nothing matches confidently.
     */
    public function screenpop()
    {
        if (pbxpilot_get_option('pbxpilot_channel_desktop', '0') !== '1') {
            show_404();
        }

        $raw    = (string) $this->input->get('phone');
        $digits = preg_replace('/\D/', '', $raw);
        $last10 = substr($digits, -10);

        if (strlen($last10) < 7) {
            redirect(admin_url('leads'));
            return;
        }

        $link = $this->_find_contact_link($last10);
        if ($link) {
            redirect($link);
            return;
        }

        $this->load->model('misc_model');
        $data['phone']   = $raw;
        $data['digits']  = $last10;
        $data['results'] = $this->misc_model->perform_search($last10);
        $data['title']   = 'PBX Pilot Screen Pop';
        $this->load->view(PBXPILOT_MODULE_NAME . '/screenpop_results', $data);
    }

    /** GET — browser channel polling: this agent's current ringing/active calls. */
    public function active_calls()
    {
        if (pbxpilot_get_option('pbxpilot_channel_browser', '0') !== '1') {
            echo json_encode([]);
            return;
        }

        $this->load->model(PBXPILOT_MODULE_NAME . '/agents_model');
        $agent = $this->agents_model->get_by_staff_id(get_staff_user_id());
        if (!$agent) {
            echo json_encode([]);
            return;
        }

        $calls = $this->db->where('agent_id', $agent['staff_id'])
            ->where('popup_shown', 0)
            ->get(db_prefix() . 'pbxpilot_active_calls')
            ->result_array();

        if (!empty($calls)) {
            $ids = array_column($calls, 'id');
            $this->db->where_in('id', $ids)->update(db_prefix() . 'pbxpilot_active_calls', ['popup_shown' => 1]);
        }

        foreach ($calls as &$c) {
            $c['link'] = $this->_find_contact_link(substr(preg_replace('/\D/', '', $c['src']), -10));
        }

        echo json_encode($calls);
    }

    /** Same lookup logic as the old pbxpopup module (§4) — kept self-contained. */
    private function _find_contact_link(string $last10): ?string
    {
        $norm = "RIGHT(REPLACE(REPLACE(phonenumber,' ',''),'-',''), 10)";

        $lead = $this->db->query('SELECT id FROM ' . db_prefix() . "leads WHERE $norm = ? ORDER BY dateadded DESC LIMIT 1", [$last10])->row();
        if ($lead) {
            return admin_url('leads/index/' . $lead->id);
        }

        $client = $this->db->query('SELECT userid FROM ' . db_prefix() . "clients WHERE $norm = ? ORDER BY userid DESC LIMIT 1", [$last10])->row();
        if ($client) {
            return admin_url('clients/client/' . $client->userid);
        }

        $contact = $this->db->query('SELECT userid FROM ' . db_prefix() . "contacts WHERE $norm = ? ORDER BY id DESC LIMIT 1", [$last10])->row();
        if ($contact) {
            return admin_url('clients/client/' . $contact->userid);
        }

        return null;
    }

    /** POST — save disposition/notes on a call (wrap-up). */
    public function wrapup()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $call_id     = (int) $this->input->post('call_id');
        $disposition = trim((string) $this->input->post('disposition_code'));
        $notes       = trim((string) $this->input->post('notes'));

        if ($call_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Missing call_id']);
            return;
        }

        $ok = $this->pbxpilot_model->save_wrapup($call_id, $disposition, $notes);
        echo json_encode(['success' => $ok]);
    }

    /** GET — stream a call's recording as WAV (fetched from the PBX on first request, cached after). */
    public function recording($call_id)
    {
        $call = $this->pbxpilot_model->get_call((int) $call_id);
        if (!$call || empty($call['recordingfile'])) {
            show_404();
        }

        $uniqueid = pathinfo((string) $call['recordingfile'], PATHINFO_FILENAME);

        $this->load->library(PBXPILOT_MODULE_NAME . '/Recording_service');
        $path = $this->recording_service->get_recording_path($uniqueid);

        if ($path === null) {
            show_404();
        }

        header('Content-Type: audio/wav');
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="' . $uniqueid . '.wav"');
        header('Cache-Control: private, max-age=86400');
        readfile($path);
    }
}
