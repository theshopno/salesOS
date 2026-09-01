<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Pbxpilot_cleanup_service
{
    private $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->model('pbxpilot/pbxpilot_model');
    }

    /**
     * Run the cleanup process for old audio files
     */
    public function run_cleanup()
    {
        $settings = get_pbxpilot_settings();
        $retention_days = $settings->retention_days;

        if ($retention_days <= 0) {
            return 0;
        }

        $old_calls = $this->CI->pbxpilot_model->get_old_calls($retention_days);
        $deleted_count = 0;

        foreach ($old_calls as $call) {
            // Remove physical file
            $file_path = $call['file_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }

            // Soft delete record
            $this->CI->pbxpilot_model->update_call($call['id'], [
                'deleted_at' => date('Y-m-d H:i:s'),
                'file_path' => '[DELETED]'
            ]);

            $deleted_count++;
        }

        return $deleted_count;
    }
}
