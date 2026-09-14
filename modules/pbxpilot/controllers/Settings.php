<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Settings extends AdminController
{
    /** Keys this controller will read/write — the single source of truth for
     *  the Features tab. Keep in sync with install.php's $defaults and with
     *  docs/pbxpilot-architecture-plan.md §6. */
    private const TOGGLE_KEYS = [
        'pbxpilot_core_telephony',
        'pbxpilot_channel_browser',
        'pbxpilot_channel_desktop',
        'pbxpilot_agency_pack',
        'pbxpilot_agency_bizbot_messaging',
        'pbxpilot_agency_bizbot_provisioning',
        'pbxpilot_voice_escalation',
    ];

    private const TEXT_KEYS = [
        'pbxpilot_ai_intelligence_mode',
        'pbxpilot_ai_model',
        'pbxpilot_effective_call_seconds',
        'pbxpilot_recording_retention_days',
    ];

    /** Connection settings (§8a) — where pbxpilot points, not tied to any one box. */
    private const CONNECTION_KEYS = [
        'pbxpilot_ami_host',
        'pbxpilot_ami_port',
        'pbxpilot_ami_username',
        'pbxpilot_ami_secret',
        'pbxpilot_cdr_db_host',
        'pbxpilot_cdr_db_port',
        'pbxpilot_cdr_db_name',
        'pbxpilot_cdr_db_user',
        'pbxpilot_cdr_db_password',
        'pbxpilot_recordings_url',
        'pbxpilot_recordings_monitor_dir',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->load->helper(PBXPILOT_MODULE_NAME . '/' . PBXPILOT_MODULE_NAME);

        if (!staff_can('settings', PBXPILOT_MODULE_NAME)) {
            access_denied('PBX Pilot Settings');
        }
    }

    public function index()
    {
        if ($this->input->post()) {
            $this->_save();
            redirect(admin_url('pbxpilot/settings'));
        }

        $data['title'] = _l('pbxpilot_settings_title');

        foreach (self::TOGGLE_KEYS as $key) {
            $data[$key] = pbxpilot_get_option($key, '0');
        }
        foreach (self::TEXT_KEYS as $key) {
            $data[$key] = pbxpilot_get_option($key, '');
        }
        foreach (self::CONNECTION_KEYS as $key) {
            $data[$key] = pbxpilot_get_option($key, '');
        }

        $data['escalation_gate'] = pbxpilot_voice_escalation_gate_status();

        $this->load->view('pbxpilot/settings', $data);
    }

    /** AJAX: Settings → Connection → Test Connection button. */
    public function test_ami()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $this->load->library(PBXPILOT_MODULE_NAME . '/Ami_service');
        echo json_encode($this->ami_service->test_connection());
    }

    /** AJAX: Settings → Connection → Sync CDR Now button. */
    public function sync_cdr()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $this->load->library(PBXPILOT_MODULE_NAME . '/Cdr_sync_service');
        echo json_encode($this->cdr_sync_service->sync());
    }

    /**
     * One-time recovery tool, not linked from any UI — see
     * Cdr_sync_service::backfill_range()'s docblock. Visit while logged in
     * as staff with pbxpilot settings permission:
     *   .../pbxpilot/settings/backfill_cdr?from=2026-09-02 00:00:00&until=2026-09-07 23:59:59
     * Requires explicit from/until (no defaults) so it's never triggered by
     * accident. Remove once the 2026-09-07 watermark-bug backfill is done.
     */
    public function backfill_cdr()
    {
        $from  = $this->input->get('from');
        $until = $this->input->get('until');
        if (!$from || !$until) {
            show_404();
        }

        $this->load->library(PBXPILOT_MODULE_NAME . '/Cdr_sync_service');
        echo json_encode($this->cdr_sync_service->backfill_range($from, $until));
    }

    private function _save(): void
    {
        $gate = pbxpilot_voice_escalation_gate_status();

        foreach (self::TOGGLE_KEYS as $key) {
            $val = $this->input->post($key) ? '1' : '0';

            // Refuse to enable the gated flag until salesos's ledger says it's
            // ready — never silently ignore, tell the operator why (§6).
            if ($key === 'pbxpilot_voice_escalation' && $val === '1' && !$gate['allowed']) {
                set_alert('warning', $gate['reason']);
                continue;
            }

            pbxpilot_update_option($key, $val);
        }

        foreach (array_merge(self::TEXT_KEYS, self::CONNECTION_KEYS) as $key) {
            $val = $this->input->post($key);
            if ($val !== null) {
                pbxpilot_update_option($key, $val);
            }
        }

        set_alert('success', _l('pbxpilot_settings_updated'));
    }
}
