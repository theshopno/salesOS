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

        $data['title'] = _l('salesos_settings_title') ?: 'SalesOS Settings';

        foreach (self::TOGGLE_KEYS as $key) {
            $data[$key] = salesos_get_option($key, '0');
        }
        foreach (self::TEXT_KEYS as $key) {
            $data[$key] = salesos_get_option($key, '');
        }

        $data['escalation_gate'] = salesos_voice_escalation_gate_status();

        $this->load->view('salesos/settings', $data);
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

        foreach (self::TEXT_KEYS as $key) {
            $val = $this->input->post($key);
            if ($val !== null) {
                salesos_update_option($key, $val);
            }
        }

        set_alert('success', _l('settings_updated') ?: 'Settings updated.');
    }
}
