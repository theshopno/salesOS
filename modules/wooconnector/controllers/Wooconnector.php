<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Wooconnector extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('wooconnector/wooconnector_model');

        if (!staff_can('view', 'wooconnector')) {
            access_denied('WooConnector');
        }
    }

    public function index()
    {
        $date_from = $this->input->get('date_from') ?: date('Y-m-d', strtotime('-30 days'));
        $date_to   = $this->input->get('date_to') ?: date('Y-m-d');

        $sites = $this->wooconnector_model->get_sites();

        $data['title']    = 'WooConnector';
        $data['site']     = !empty($sites) ? $sites[0] : null;
        $data['date_from'] = $date_from;
        $data['date_to']   = $date_to;
        $data['stats']     = $this->wooconnector_model->get_stats($date_from, $date_to);
        $data['daily']     = $this->wooconnector_model->get_daily_breakdown($date_from, $date_to);
        $data['cron']      = $this->cron_status();

        $this->load->view('wooconnector/dashboard', $data);
    }

    public function settings()
    {
        if ($this->input->post()) {
            if (!staff_can('settings', 'wooconnector')) {
                access_denied('WooConnector');
            }

            $this->load->library('form_validation');

            $this->form_validation->set_rules('name', 'Site Name', 'required');
            $this->form_validation->set_rules('site_url', 'Site URL', 'required|valid_url');
            $this->form_validation->set_rules('consumer_key', 'Consumer Key', 'required');
            $this->form_validation->set_rules('consumer_secret', 'Consumer Secret', 'required');
            $this->form_validation->set_rules('default_lead_status_id', 'Default Lead Status', 'required|is_natural_no_zero');

            if ($this->form_validation->run()) {
                $sites = $this->wooconnector_model->get_sites();
                $existing_id = !empty($sites) ? $sites[0]['id'] : null;

                $this->wooconnector_model->save_site($this->input->post(), $existing_id);

                set_alert('success', 'WooConnector settings saved.');
            } else {
                set_alert('warning', validation_errors());
            }

            redirect(admin_url('wooconnector/settings'));
        }

        $sites = $this->wooconnector_model->get_sites();

        $data['title']         = 'WooConnector Settings';
        $data['site']          = !empty($sites) ? $sites[0] : null;
        $data['lead_statuses'] = $this->wooconnector_model->get_lead_statuses();

        $this->load->view('wooconnector/settings', $data);
    }

    public function orders()
    {
        $data['title']    = 'Website Orders';
        $data['orders']   = $this->wooconnector_model->get_pending_orders();
        $data['statuses'] = $this->wooconnector_model->get_selectable_statuses();

        $this->load->view('wooconnector/orders', $data);
    }

    public function retry_conversion($lead_id)
    {
        if (!staff_can('settings', 'wooconnector')) {
            ajax_access_denied();
        }

        $client_id = $this->wooconnector_model->confirm_lead_as_customer((int) $lead_id);

        echo json_encode($client_id
            ? ['success' => true, 'client_id' => $client_id]
            : ['success' => false, 'error' => 'Conversion failed again — check the activity log for details.']);
    }

    public function sync_now()
    {
        if (!staff_can('settings', 'wooconnector')) {
            ajax_access_denied();
        }

        $sites = $this->wooconnector_model->get_sites();

        if (empty($sites)) {
            echo json_encode(['success' => false, 'error' => 'No WooCommerce site configured yet.']);

            return;
        }

        try {
            $counts = $this->wooconnector_model->sync();
            echo json_encode(array_merge(['success' => true], $counts));
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function check_cron_status()
    {
        echo json_encode(array_merge(['success' => true], $this->cron_status()));
    }

    private function cron_status()
    {
        $last_run    = get_option('last_cron_run');
        $seconds_ago = $last_run ? (time() - (int) $last_run) : null;

        // System cron typically fires every 1-5 minutes; 15 minutes of silence means it's not configured / stuck.
        $healthy = $seconds_ago !== null && $seconds_ago <= 900;

        return [
            'healthy'     => $healthy,
            'seconds_ago' => $seconds_ago,
            'cron_url'    => site_url('cron/index' . (defined('APP_CRON_KEY') ? '/' . APP_CRON_KEY : '')),
        ];
    }
}
