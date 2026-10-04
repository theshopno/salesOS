<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Agents extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model(PBXPILOT_MODULE_NAME . '/agents_model');

        if (!staff_can('manage', PBXPILOT_MODULE_NAME)) {
            access_denied('PBX Pilot Agents');
        }
    }

    public function index()
    {
        if ($this->input->post() && !$this->input->is_ajax_request()) {
            $this->_save();
            redirect(admin_url('pbxpilot/agents'));
        }

        $this->load->library(PBXPILOT_MODULE_NAME . '/ami_service');

        $data['title']                  = 'টিম ও চ্যানেল ম্যাপিং (PBX & WhatsApp)';
        $data['roster']                 = $this->agents_model->get_unified_roster();
        
        // Discover live Asterisk extensions safely
        $discovered = [];
        try {
            $discovered = $this->ami_service->get_discovered_extensions();
        } catch (\Throwable $e) {
            log_activity('PBX Extension discovery error: ' . $e->getMessage());
        }
        $data['discovered_extensions']  = $discovered;

        // Unmapped Bizbot agents detected from WhatsApp traffic
        $unmapped_raw = get_option('salesos_bizbot_unmapped_agents') ?: '[]';
        $data['unmapped_bizbot_agents'] = json_decode($unmapped_raw, true) ?: [];

        $this->load->view('pbxpilot/agents/manage', $data);
    }

    /**
     * AJAX endpoint to save a staff member's unified channels atomically.
     */
    public function save_ajax()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $staff_id  = (int) $this->input->post('staff_id');
        $extension = trim((string) $this->input->post('extension'));
        $guid      = trim((string) $this->input->post('bizbot_agent_guid'));
        $aliases   = trim((string) $this->input->post('whatsapp_aliases'));
        $caller_id = trim((string) $this->input->post('caller_id'));
        $is_active = (int) ($this->input->post('is_active') ?? 1);

        if ($staff_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid staff ID.']);
            return;
        }

        $data = [
            'extension'         => $extension,
            'bizbot_agent_guid' => $guid,
            'whatsapp_aliases'  => $aliases,
            'caller_id'         => $caller_id,
            'is_active'         => $is_active,
        ];

        $ok = $this->agents_model->save_unified($staff_id, $data);

        // If this GUID was in the unmapped queue, remove it now
        if ($ok && !empty($guid)) {
            $unmapped = json_decode(get_option('salesos_bizbot_unmapped_agents') ?: '[]', true) ?: [];
            if (isset($unmapped[$guid])) {
                unset($unmapped[$guid]);
                update_option('salesos_bizbot_unmapped_agents', json_encode($unmapped, JSON_UNESCAPED_UNICODE));
            }
        }

        echo json_encode([
            'success' => $ok,
            'message' => $ok ? 'চ্যানেল সেটিংস সফলভাবে সংরক্ষিত হয়েছে।' : 'সংরক্ষণে ব্যর্থ হয়েছে।',
        ]);
    }

    /**
     * AJAX endpoint: Auto-generate Bengali and English aliases from staff name.
     */
    public function auto_aliases_ajax()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $staff_id = (int) $this->input->post('staff_id');
        $staff = $this->db->select('firstname, lastname')->where('staffid', $staff_id)->get(db_prefix() . 'staff')->row_array();

        if (!$staff) {
            echo json_encode(['success' => false, 'message' => 'Staff not found.']);
            return;
        }

        $aliases = $this->agents_model->generate_aliases_for_name($staff['firstname'], $staff['lastname']);

        echo json_encode([
            'success' => true,
            'aliases' => implode(', ', $aliases),
        ]);
    }

    /**
     * AJAX endpoint: Test ring staff extension for 5 seconds.
     */
    public function test_ring_ajax()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $extension = trim((string) $this->input->post('extension'));
        if (empty($extension)) {
            echo json_encode(['success' => false, 'message' => 'Extension is empty.']);
            return;
        }

        $this->load->library(PBXPILOT_MODULE_NAME . '/ami_service');
        
        // Originate ring to extension and send to Milliwatt (standard dialplan ring test) or s@test
        $action_id = uniqid('test-ring-');
        $this->ami_service->connect();
        $ref = new \ReflectionClass($this->ami_service);
        $method = $ref->getMethod('send_action');
        $method->setAccessible(true);
        $method->invoke($this->ami_service, [
            'Action'   => 'Originate',
            'Channel'  => "PJSIP/{$extension}",
            'Context'  => 'from-internal',
            'Exten'    => 's',
            'Priority' => '1',
            'Timeout'  => '15000',
            'Async'    => 'true',
            'ActionID' => $action_id,
        ]);
        $this->ami_service->disconnect();

        echo json_encode([
            'success' => true,
            'message' => "এক্সটেনশন {$extension}-এ টেস্ট কল পাঠানো হয়েছে। আপনার ফোনে রিং বাজছে কিনা চেক করুন।",
        ]);
    }

    /**
     * Dismiss an unmapped Bizbot GUID from the suggestion banner.
     */
    public function dismiss_unmapped_ajax()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $guid = trim((string) $this->input->post('guid'));
        if (!empty($guid)) {
            $unmapped = json_decode(get_option('salesos_bizbot_unmapped_agents') ?: '[]', true) ?: [];
            unset($unmapped[$guid]);
            update_option('salesos_bizbot_unmapped_agents', json_encode($unmapped, JSON_UNESCAPED_UNICODE));
        }

        echo json_encode(['success' => true]);
    }

    public function delete($id)
    {
        $this->agents_model->delete((int) $id);
        set_alert('success', _l('pbxpilot_agent_removed'));
        redirect(admin_url('pbxpilot/agents'));
    }

    private function _save(): void
    {
        $staff_id  = (int) $this->input->post('staff_id');
        $extension = trim((string) $this->input->post('extension'));

        if ($staff_id <= 0) {
            set_alert('warning', _l('pbxpilot_agents_required'));
            return;
        }

        $data = [
            'extension'         => $extension,
            'bizbot_agent_guid' => trim((string) $this->input->post('bizbot_agent_guid')),
            'whatsapp_aliases'  => trim((string) $this->input->post('whatsapp_aliases')),
            'caller_id'         => trim((string) $this->input->post('caller_id')),
            'is_active'         => (int) ($this->input->post('is_active') ?? 1),
        ];

        $this->agents_model->save_unified($staff_id, $data);
        set_alert('success', 'এজেন্ট চ্যানেল ম্যাপিং সংরক্ষিত হয়েছে।');
    }
}
