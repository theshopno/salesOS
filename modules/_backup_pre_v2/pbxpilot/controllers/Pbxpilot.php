<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Pbxpilot extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('pbxpilot/pbxpilot_model');
        $this->load->library('app_modules');

        if (!has_permission('pbxpilot', '', 'view')) {
            access_denied('PBXPilot');
        }
    }

    /**
     * Dashboard view
     */
    public function index()
    {
        $data['title'] = 'PBXPilot Dashboard';
        $this->load->library(PBXPILOT_MODULE_NAME . '/pbxpilot_lead_service');
        $this->load->model('staff_model');

        $calls = $this->pbxpilot_model->get_calls();
        foreach ($calls as &$call) {
            $call['contact_info'] = $this->pbxpilot_lead_service->get_contact_info($call['phone_number']);
            if ($call['staff_id']) {
                $staff = $this->staff_model->get($call['staff_id']);
                $call['assigned_staff'] = $staff ? $staff->firstname . ' ' . $staff->lastname : null;
            } else {
                $call['assigned_staff'] = null;
            }
        }

        $data['calls'] = $calls;
        $data['cases'] = get_pbxpilot_cases();
        $data['formats'] = get_pbxpilot_formats();
        $this->load->view('dashboard', $data);
    }

    /**
     * Settings view
     */
    public function settings()
    {
        if (!has_permission('pbxpilot', '', 'settings')) {
            access_denied('PBXPilot Settings');
        }

        if ($this->input->post()) {
            $post_data = $this->input->post();
            $this->db->update(db_prefix() . 'pbxpilot_settings', [
                'retention_days' => $post_data['retention_days'],
                'default_ai_provider' => $post_data['default_ai_provider'],
                'max_audio_size' => $post_data['max_audio_size'],
                'duplicate_check_enabled' => isset($post_data['duplicate_check_enabled']) ? 1 : 0,
            ]);

            // Save AI provider keys in options table
            update_option('pbxpilot_openai_api_key', $post_data['openai_api_key']);
            update_option('pbxpilot_google_project_id', $post_data['google_project_id']);
            update_option('pbxpilot_google_service_account', $post_data['google_service_account']);
            update_option('pbxpilot_transcription_language', $post_data['transcription_language']);
            update_option('pbxpilot_openai_model', $post_data['openai_model']);
            update_option('pbxpilot_product_service', $post_data['product_service']);

            // Save staff extensions
            if (isset($post_data['staff_extensions'])) {
                update_option('pbxpilot_staff_extensions', json_encode($post_data['staff_extensions']));
            }

            set_alert('success', 'Settings updated successfully');
            redirect(admin_url('pbxpilot/settings'));
        }

        $data['title'] = 'PBXPilot Settings';
        $data['settings'] = get_pbxpilot_settings();
        $data['openai_key'] = get_option('pbxpilot_openai_api_key');

        $this->load->model('staff_model');
        $data['staff_members'] = $this->staff_model->get('', ['active' => 1]);
        $data['staff_extensions'] = json_decode(get_option('pbxpilot_staff_extensions', '[]'), true);

        $this->load->view('settings', $data);
    }

    /**
     * Manually map a lead
     */
    public function map_lead()
    {
        if ($this->input->post()) {
            $call_id = $this->input->post('call_id');
            $lead_id = $this->input->post('lead_id');

            $this->pbxpilot_model->update_call($call_id, ['lead_id' => $lead_id]);
            set_alert('success', 'Lead mapped successfully');
        }
        redirect(admin_url('pbxpilot'));
    }

    /**
     * Re-process a call
     */
    public function reprocess($id)
    {
        if (!has_permission('pbxpilot', '', 'process')) {
            access_denied('PBXPilot');
        }

        $this->load->library(PBXPILOT_MODULE_NAME . '/pbxpilot_ai_service');
        $result = $this->pbxpilot_ai_service->process_call_ai($id);

        if ($result['status']) {
            set_alert('success', 'Call re-processed successfully');
        } else {
            set_alert('danger', 'Reprocess Error: ' . ($result['error'] ?? 'Unknown error'));
        }

        redirect(admin_url('pbxpilot'));
    }

    /**
     * Delete a call record
     */
    public function delete($id)
    {
        if (!has_permission('pbxpilot', '', 'delete')) {
            access_denied('PBXPilot');
        }

        $call = $this->pbxpilot_model->get_call($id);
        if ($call) {
            if (file_exists($call->file_path)) {
                unlink($call->file_path);
            }
            $this->db->where('id', $id);
            $this->db->delete(db_prefix() . 'pbxpilot_calls');
            set_alert('success', 'Call record deleted');
        }

        redirect(admin_url('pbxpilot'));
    }

    /**
     * AJAX method to test AI provider connection
     */
    public function test_connection()
    {
        if (!has_permission('pbxpilot', '', 'settings')) {
            echo json_encode(['status' => false, 'message' => 'Access denied']);
            return;
        }

        $provider = $this->input->post('provider');
        $this->load->library(PBXPILOT_MODULE_NAME . '/pbxpilot_ai_service');

        $result = ['status' => false, 'message' => 'Unknown provider'];

        if ($provider == 'openai') {
            $api_key = $this->input->post('openai_api_key');
            $result = $this->pbxpilot_ai_service->test_openai_connection($api_key);
        } elseif ($provider == 'google') {
            $project_id = $this->input->post('google_project_id');
            $service_account = $this->input->post('google_service_account');
            $result = $this->pbxpilot_ai_service->test_google_connection($project_id, $service_account);
        }

        echo json_encode($result);
    }

    /**
     * Rephrase AI Summary via AJAX
     */
    public function rephrase_ajax()
    {
        if (!has_permission('pbxpilot', '', 'edit')) {
            echo json_encode(['status' => false, 'error' => 'Permission denied']);
            return;
        }

        $id = $this->input->post('call_id');
        $model = $this->input->post('model');
        $format = $this->input->post('preset_type');
        $case = $this->input->post('call_case');

        $this->load->library(PBXPILOT_MODULE_NAME . '/pbxpilot_ai_service');
        $result = $this->pbxpilot_ai_service->rephrase_call_ai($id, $model, $format, $case);

        echo json_encode($result);
    }

    /**
     * Process a single queued call via AJAX (Background trigger)
     */
    public function process_queued_ajax($id)
    {
        if (!has_permission('pbxpilot', '', 'edit')) {
            echo json_encode(['status' => false, 'error' => 'Permission denied']);
            return;
        }

        $this->load->library(PBXPILOT_MODULE_NAME . '/pbxpilot_ai_service');
        $result = $this->pbxpilot_ai_service->process_call_ai($id);

        echo json_encode($result);
    }

    /**
     * Inject a summary into Lead/Client notes via AJAX
     */
    public function inject_note_ajax()
    {
        if (!has_permission('pbxpilot', '', 'edit')) {
            echo json_encode(['status' => false, 'error' => 'Permission denied']);
            return;
        }

        $call_id = $this->input->post('call_id');
        $summary = $this->input->post('summary');

        if (empty($summary)) {
            echo json_encode(['status' => false, 'error' => 'Summary text is empty']);
            return;
        }

        $this->load->library(PBXPILOT_MODULE_NAME . '/pbxpilot_lead_service');
        $note_id = $this->pbxpilot_lead_service->inject_to_notes($call_id, $summary);

        if ($note_id) {
            echo json_encode(['status' => true, 'message' => 'Note injected successfully']);
        } else {
            echo json_encode(['status' => false, 'error' => 'Failed to inject note. Check if call is linked to a Lead/Client.']);
        }
    }
}
