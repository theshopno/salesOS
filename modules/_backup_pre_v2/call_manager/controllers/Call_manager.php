<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Call_manager extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('call_manager_model');
    }

    /**
     * Module Settings
     */
    public function settings()
    {
        if (!has_permission('settings', '', 'view')) {
            access_denied('Call Manager Settings');
        }

        if ($this->input->post()) {
            if (!has_permission('settings', '', 'edit')) {
                access_denied('Call Manager Settings');
            }

            update_option('call_manager_pbp_db_host', $this->input->post('pbp_db_host'));
            update_option('call_manager_pbp_db_name', $this->input->post('pbp_db_name'));
            update_option('call_manager_pbp_db_user', $this->input->post('pbp_db_user'));
            update_option('call_manager_pbp_db_password', $this->input->post('pbp_db_password'));
            update_option('call_manager_recordings_url', $this->input->post('recordings_url'));

            set_alert('success', _l('settings_updated'));
            redirect(admin_url('call_manager/settings'));
        }

        $data['title'] = 'Call Manager Settings';
        $this->load->view('settings', $data);
    }
}
