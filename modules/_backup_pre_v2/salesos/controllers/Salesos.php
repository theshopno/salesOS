<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Salesos extends AdminController
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
        $date_from = $this->input->get('date_from') ?: date('Y-m-d', strtotime('-30 days'));
        $date_to   = $this->input->get('date_to')   ?: date('Y-m-d');

        $data['title']       = 'SalesOS Dashboard';
        $data['stats']       = $this->salesos_model->get_stats($date_from, $date_to);
        $data['trend']       = $this->salesos_model->get_daily_call_trend($date_from, $date_to);
        $data['agent_stats'] = $this->salesos_model->get_agent_stats($date_from, $date_to);
        $data['recent']      = $this->salesos_model->get_calls([], 10);
        $data['last_sync']   = $this->salesos_model->get_last_sync();
        $data['date_from']   = $date_from;
        $data['date_to']     = $date_to;

        $this->load->library('salesos/ami_service');
        $ami_ok = $this->ami_service->connect();
        $data['ami_status'] = $ami_ok ? 'connected' : 'disconnected';
        $data['ami_error']  = $ami_ok ? '' : $this->ami_service->get_last_error();
        if ($ami_ok) {
            $this->ami_service->disconnect();
        }

        $this->load->view('salesos/dashboard', $data);
    }

    public function settings()
    {
        if (!staff_can('settings', 'salesos')) {
            access_denied('SalesOS Settings');
        }

        if ($this->input->post()) {
            $options = [
                // AMI
                'salesos_ami_host',
                'salesos_ami_port',
                'salesos_ami_username',
                'salesos_ami_secret',
                // PBX CDR DB
                'salesos_pbx_db_host',
                'salesos_pbx_db_port',
                'salesos_pbx_db_name',
                'salesos_pbx_db_user',
                'salesos_pbx_db_password',
                // Recordings
                'salesos_recordings_url',
                'salesos_recordings_path',
                // Popup
                'salesos_poll_interval',
                'salesos_popup_enabled',
                // CDR
                'salesos_cdr_sync_enabled',
                'salesos_cdr_batch_size',
                // Outbound
                'salesos_outbound_prefix',
                'salesos_caller_id',
                'salesos_trunk_endpoint',
                'salesos_click_to_call_enabled',
                // Redis
                'salesos_redis_host',
                'salesos_redis_port',
                'salesos_redis_password',
                // WebSocket
                'salesos_ws_internal_port',
                'salesos_ws_url',
                // PBX identity
                'salesos_pbx_id',
                'salesos_pbx_name',
                // Stream
                'salesos_stream_maxlen',
                // WebRTC browser softphone
                'salesos_webrtc_enabled',
                'salesos_webrtc_wss_url',
                'salesos_webrtc_domain',
                'salesos_webrtc_realm',
                // SSH tunnel (optional DB access override)
                'salesos_pbx_db_conn_host',
                'salesos_pbx_db_conn_port',
            ];

            foreach ($options as $key) {
                $val = $this->input->post($key);
                if ($val !== null) {
                    update_option($key, $val);
                }
            }

            $regen_error = $this->_regenerate_config();

            if ($regen_error) {
                set_alert('warning', 'Settings saved but daemon config could not be written: ' . $regen_error);
            } else {
                set_alert('success', 'Settings saved. Daemon config regenerated.');
            }

            redirect(admin_url('salesos/settings'));
        }

        $option_keys = [
            'salesos_ami_host', 'salesos_ami_port', 'salesos_ami_username', 'salesos_ami_secret',
            'salesos_pbx_db_host', 'salesos_pbx_db_port', 'salesos_pbx_db_name',
            'salesos_pbx_db_user', 'salesos_pbx_db_password',
            'salesos_recordings_url', 'salesos_recordings_path',
            'salesos_poll_interval', 'salesos_popup_enabled',
            'salesos_cdr_sync_enabled', 'salesos_cdr_batch_size',
            'salesos_outbound_prefix', 'salesos_caller_id', 'salesos_trunk_endpoint',
            'salesos_click_to_call_enabled',
            'salesos_redis_host', 'salesos_redis_port', 'salesos_redis_password',
            'salesos_ws_internal_port', 'salesos_ws_url',
            'salesos_pbx_id', 'salesos_pbx_name',
            'salesos_stream_maxlen',
            'salesos_webrtc_enabled', 'salesos_webrtc_wss_url',
            'salesos_webrtc_domain', 'salesos_webrtc_realm',
            'salesos_pbx_db_conn_host', 'salesos_pbx_db_conn_port',
        ];

        $data['title']   = 'SalesOS Settings';
        $data['options'] = [];
        foreach ($option_keys as $key) {
            $data['options'][$key] = get_option($key);
        }

        $config_path = FCPATH . 'modules/salesos/daemons/config.php';
        $data['config_writable']  = is_writable($config_path) || is_writable(dirname($config_path));
        $data['config_mtime']     = is_file($config_path) ? date('Y-m-d H:i:s', filemtime($config_path)) : null;

        $this->load->view('salesos/settings', $data);
    }

    // ── Private: regenerate daemons/config.php from current DB options ────────

    private function _regenerate_config(): ?string
    {
        $config_path = FCPATH . 'modules/salesos/daemons/config.php';

        // DB credentials come from the app bootstrap constants (same connection CI uses)
        $db_host   = defined('APP_DB_HOSTNAME') ? APP_DB_HOSTNAME : '127.0.0.1';
        $db_name   = defined('APP_DB_NAME')     ? APP_DB_NAME     : '';
        $db_user   = defined('APP_DB_USERNAME') ? APP_DB_USERNAME : '';
        $db_pass   = defined('APP_DB_PASSWORD') ? APP_DB_PASSWORD : '';
        $db_prefix = db_prefix();

        // DB port: Perfex does not expose a port constant; default 3306 is correct for all
        // standard installations. Override via salesos_crm_db_port option if needed.
        $db_port = (int) (get_option('salesos_crm_db_port') ?: 3306);

        $values = [
            'db_host'        => $db_host,
            'db_port'        => $db_port,
            'db_name'        => $db_name,
            'db_user'        => $db_user,
            'db_pass'        => $db_pass,
            'db_prefix'      => $db_prefix,
            'redis_host'     => get_option('salesos_redis_host')        ?: '127.0.0.1',
            'redis_port'     => (int) (get_option('salesos_redis_port') ?: 6379),
            'redis_password' => get_option('salesos_redis_password')    ?: '',
            'ami_host'       => get_option('salesos_ami_host')          ?: '',
            'ami_port'       => (int) (get_option('salesos_ami_port')   ?: 5038),
            'ami_user'       => get_option('salesos_ami_username')      ?: '',
            'ami_secret'     => get_option('salesos_ami_secret')        ?: '',
            'pbx_id'         => get_option('salesos_pbx_id')           ?: 'pbx-01',
            'pbx_name'       => get_option('salesos_pbx_name')         ?: 'Main PBX',
            'ws_port'        => (int) (get_option('salesos_ws_internal_port') ?: 8080),
            'stream_maxlen'  => (int) (get_option('salesos_stream_maxlen')    ?: 50000),
        ];

        $lines   = [];
        $lines[] = "<?php\n";
        $lines[] = "// Auto-generated by SalesOS Settings — do not edit manually.\n";
        $lines[] = "// Last generated: " . date('Y-m-d H:i:s') . "\n";
        $lines[] = "// Regenerate: Admin → SalesOS → Settings → Save\n";
        $lines[] = "return [\n";

        foreach ($values as $key => $val) {
            if (is_int($val)) {
                $lines[] = "    '{$key}' => {$val},\n";
            } else {
                $escaped = str_replace(['\\', "'"], ['\\\\', "\\'"], (string) $val);
                $lines[] = "    '{$key}' => '{$escaped}',\n";
            }
        }
        $lines[] = "];\n";

        $written = @file_put_contents($config_path, implode('', $lines));
        if ($written === false) {
            return "Cannot write to {$config_path} — check file permissions (www-data must own or have write access)";
        }

        return null;
    }
}
