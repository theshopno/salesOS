<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Upload extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('pbxpilot/pbxpilot_model');
        $this->load->library(PBXPILOT_MODULE_NAME . '/pbxpilot_audio_service');
        $this->load->library(PBXPILOT_MODULE_NAME . '/pbxpilot_ai_service');
        $this->load->library(PBXPILOT_MODULE_NAME . '/pbxpilot_lead_service');
    }

    /**
     * Handle audio upload and instant processing
     */
    public function do_upload()
    {
        if (!has_permission('pbxpilot', '', 'upload')) {
            access_denied('PBXPilot Upload');
        }

        if ($this->input->post()) {
            $preset_type = $this->input->post('preset_type');
            $ai_provider = $this->input->post('ai_provider');

            // 1. Handle File Upload
            $upload = $this->pbxpilot_audio_service->handle_upload('audio_file');
            if (!$upload['status']) {
                set_alert('danger', 'Upload Error: ' . $upload['error']);
                redirect(admin_url('pbxpilot'));
            }

            $file_data = $upload['data'];
            $file_path = $file_data['full_path'];
            $file_name = $file_data['file_name'];

            // 2. Duplicate Check
            $hash = $this->pbxpilot_audio_service->generate_hash($file_path);
            $is_duplicate = 0;
            if (get_pbxpilot_settings()->duplicate_check_enabled) {
                $existing = $this->pbxpilot_model->get_call_by_hash($hash);
                if ($existing) {
                    $is_duplicate = 1;
                    set_alert('warning', 'Duplicate audio detected. Record marked as duplicate.');
                }
            }

            // 3. Parse Metadata & Duration
            $metadata = $this->pbxpilot_audio_service->parse_filename($file_name);
            $duration = $this->pbxpilot_audio_service->get_duration($file_path);

            // 4. Create Database Record
            $preset_type = $this->input->post('preset_type') ?: 'DETAILED_SUMMARY_ACTION';
            $call_case = $this->input->post('call_case') ?: 'CASE_GENERAL_INQUIRY';
            $ai_provider = get_option('pbxpilot_ai_provider', 'openai');

            $call_id = $this->pbxpilot_model->add_call([
                'uploaded_by' => get_staff_user_id(),
                'file_name' => $file_name,
                'file_path' => $file_path,
                'phone_number' => $metadata['phone_number'],
                'call_type' => $metadata['call_type'],
                'extension_number' => $metadata['extension_number'],
                'staff_id' => $metadata['staff_id'],
                'unique_call_id' => $metadata['unique_call_id'],
                'audio_hash' => $hash,
                'duration_seconds' => $duration,
                'is_duplicate' => $is_duplicate,
                'preset_type' => $preset_type,
                'last_case_used' => $call_case,
                'ai_provider' => $ai_provider,
            ]);

            // 5. Success (Process AI in background via Dashboard AJAX)
            if ($call_id && !$is_duplicate) {
                set_alert('success', 'File uploaded successfully. AI processing started in background.');
            } else if ($is_duplicate) {
                set_alert('warning', 'Duplicate file detected. Skipping AI processing.');
            }

            redirect(admin_url('pbxpilot'));
        }
    }
}
