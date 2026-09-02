<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Settings extends AdminController
{
    /** Keys this controller will read/write — the single source of truth for
     *  the Features tab. Keep in sync with install.php's $defaults and with
     *  docs/salesos-v2-architecture-plan.md §6. */
    private const TOGGLE_KEYS = [
        'salesos_core_telephony',
        'salesos_channel_browser',
        'salesos_channel_desktop',
        'salesos_agency_pack',
        'salesos_agency_bizbot_messaging',
        'salesos_agency_bizbot_provisioning',
        'salesos_voice_escalation',
    ];

    private const TEXT_KEYS = [
        'salesos_ai_intelligence_mode',
        'salesos_ai_model',
        'salesos_effective_call_seconds',
        'salesos_recording_retention_days',
    ];

    /** Connection settings (§8a) — where salesos points, not tied to any one box. */
    private const CONNECTION_KEYS = [
        'salesos_ami_host',
        'salesos_ami_port',
        'salesos_ami_username',
        'salesos_ami_secret',
        'salesos_cdr_db_host',
        'salesos_cdr_db_port',
        'salesos_cdr_db_name',
        'salesos_cdr_db_user',
        'salesos_cdr_db_password',
        'salesos_recordings_url',
        'salesos_recordings_monitor_dir',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->load->helper(SALESOS_MODULE_NAME . '/' . SALESOS_MODULE_NAME);

        if (!staff_can('settings', SALESOS_MODULE_NAME)) {
            access_denied('SalesOS Settings');
        }
    }

    public function index()
    {
        if ($this->input->post()) {
            $this->_save();
            redirect(admin_url('salesos/settings'));
        }

        $data['title'] = _l('salesos_settings_title');

        foreach (self::TOGGLE_KEYS as $key) {
            $data[$key] = salesos_get_option($key, '0');
        }
        foreach (self::TEXT_KEYS as $key) {
            $data[$key] = salesos_get_option($key, '');
        }
        foreach (self::CONNECTION_KEYS as $key) {
            $data[$key] = salesos_get_option($key, '');
        }

        $data['escalation_gate'] = salesos_voice_escalation_gate_status();

        $this->load->view('salesos/settings', $data);
    }

    /** AJAX: Settings → Connection → Test Connection button. */
    public function test_ami()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $this->load->library(SALESOS_MODULE_NAME . '/Ami_service');
        echo json_encode($this->ami_service->test_connection());
    }

    /** AJAX: Settings → Connection → Sync CDR Now button. */
    public function sync_cdr()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $this->load->library(SALESOS_MODULE_NAME . '/Cdr_sync_service');
        echo json_encode($this->cdr_sync_service->sync());
    }

    private function _save(): void
    {
        $gate = salesos_voice_escalation_gate_status();

        foreach (self::TOGGLE_KEYS as $key) {
            $val = $this->input->post($key) ? '1' : '0';

            // Refuse to enable the gated flag until ecomcore's ledger says it's
            // ready — never silently ignore, tell the operator why (§6).
            if ($key === 'salesos_voice_escalation' && $val === '1' && !$gate['allowed']) {
                set_alert('warning', $gate['reason']);
                continue;
            }

            salesos_update_option($key, $val);
        }

        foreach (array_merge(self::TEXT_KEYS, self::CONNECTION_KEYS) as $key) {
            $val = $this->input->post($key);
            if ($val !== null) {
                salesos_update_option($key, $val);
            }
        }

        set_alert('success', _l('salesos_settings_updated'));
    }
}
