<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Wcsync extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if (function_exists('salesos_require_ecommerce')) {
            salesos_require_ecommerce();
        }
        if (!$this->app_modules->is_active('salesos')) {
            set_alert('danger', 'WooCommerce Sync requires the SalesOS module to be active.');
            redirect(admin_url());
        }
        $this->load->model('wcsync_model');
        $this->load->model('salesos/salesos_model');
        if (!staff_can('view', 'wcsync')) {
            access_denied('WooCommerce Sync');
        }
    }

    public function index()
    {
        $data['title']                 = 'WooCommerce Sync';
        $data['sites']                 = $this->wcsync_model->get_sites();
        $data['stats']                 = $this->wcsync_model->get_site_stats();
        $data['recent_syncs']          = $this->wcsync_model->get_synced_orders();
        $data['synced_products_count'] = $this->wcsync_model->get_synced_products_count();

        $this->load->view('wcsync/dashboard', $data);
    }

    public function settings()
    {
        if ($this->input->post()) {
            if (!staff_can('settings', 'wcsync')) {
                access_denied('WooCommerce Settings');
            }

            $this->load->library('form_validation');
            $this->form_validation->set_rules('name', 'Site Name', 'required|trim');
            $this->form_validation->set_rules('site_url', 'Site URL', 'required|trim|valid_url');
            $this->form_validation->set_rules('credential_id', 'Credential', 'required|is_natural_no_zero');
            $this->form_validation->set_rules('default_lead_status_id', 'Default Lead Status', 'required|is_natural_no_zero');

            if ($this->form_validation->run() === false) {
                set_alert('danger', validation_errors());
                redirect(admin_url('wcsync/settings'));
            }

            $credential_id = (int) $this->input->post('credential_id');
            if (!$this->salesos_model->get_credential($credential_id, 'wcsync')) {
                set_alert('danger', 'Selected credential is invalid or does not belong to WooCommerce Sync.');
                redirect(admin_url('wcsync/settings'));
            }

            $id = $this->input->post('id') ? (int) $this->input->post('id') : null;
            $this->wcsync_model->save_site($this->input->post(), $id);

            set_alert('success', 'WooCommerce Site configuration saved successfully.');
            redirect(admin_url('wcsync/settings'));
        }

        $data['title'] = 'WooCommerce Sync Settings';
        $data['sites'] = $this->wcsync_model->get_sites();

        // Load credentials from vault belonging to wcsync
        $data['credentials'] = $this->salesos_model->list_credentials('wcsync');

        // Load Lead Statuses
        $data['lead_statuses'] = $this->db->order_by('statusorder', 'asc')->get(db_prefix() . 'leads_status')->result_array();

        $this->load->view('wcsync/settings', $data);
    }

    public function delete_site($id)
    {
        if (!staff_can('settings', 'wcsync')) {
            access_denied('WooCommerce Settings');
        }

        $this->wcsync_model->delete_site($id);
        set_alert('success', 'WooCommerce Site deleted successfully.');
        redirect(admin_url('wcsync/settings'));
    }

    public function sync_now()
    {
        if (!staff_can('sync', 'wcsync')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }

        try {
            $counts = $this->wcsync_model->sync();
            // sync() now reports its own 'success' (false if any site's fetch
            // genuinely failed) — don't override it with a hardcoded true.
            echo json_encode($counts);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function sync_catalog_ajax()
    {
        if (!staff_can('sync', 'wcsync')) {
            ajax_access_denied();
        }

        $site_id = (int) ($this->input->get_post('site_id') ?? 0);
        $page    = (int) ($this->input->get_post('page') ?? 1);

        if (!$site_id) {
            $sites = $this->wcsync_model->get_sites();
            $site_id = !empty($sites) ? (int) $sites[0]['id'] : 0;
        }

        if (!$site_id) {
            echo json_encode(['success' => false, 'error' => 'No active WooCommerce site configured.']);
            exit;
        }

        try {
            $res = $this->wcsync_model->sync_catalog_page($site_id, $page);
            echo json_encode($res);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function push_product_ajax()
    {
        if (!staff_can('edit', 'inventory')) {
            ajax_access_denied();
        }

        $product_id = (int) ($this->input->post('product_id') ?? 0);
        if (!$product_id) {
            echo json_encode(['success' => false, 'error' => 'Product ID is required.']);
            exit;
        }

        try {
            $res = $this->wcsync_model->push_product($product_id);
            echo json_encode($res);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function search_store_products_ajax()
    {
        if (!staff_can('sync', 'wcsync')) {
            ajax_access_denied();
        }

        $q    = trim($this->input->get_post('q') ?? '');
        $page = (int) ($this->input->get_post('page') ?? 1);

        try {
            $res = $this->wcsync_model->search_store_products($q, $page);
            echo json_encode($res);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function import_specific_products_ajax()
    {
        if (!staff_can('sync', 'wcsync')) {
            ajax_access_denied();
        }

        $raw_ids = $this->input->post('wc_ids');
        $wc_ids = is_array($raw_ids) ? $raw_ids : (!empty($raw_ids) ? explode(',', $raw_ids) : []);

        if (empty($wc_ids)) {
            echo json_encode(['success' => false, 'error' => 'No products selected for import.']);
            exit;
        }

        try {
            $res = $this->wcsync_model->import_specific_products($wc_ids);
            echo json_encode($res);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function push_products_ajax()
    {
        if (!staff_can('edit', 'inventory')) {
            ajax_access_denied();
        }

        $push_all = ($this->input->post('push_all') === '1');
        $raw_ids  = $this->input->post('product_ids');
        $product_ids = is_array($raw_ids) ? $raw_ids : (!empty($raw_ids) ? explode(',', $raw_ids) : []);

        try {
            if ($push_all) {
                $res = $this->wcsync_model->push_all_products();
            } elseif (!empty($product_ids)) {
                $res = $this->wcsync_model->push_multiple_products($product_ids);
            } else {
                echo json_encode(['success' => false, 'error' => 'No products selected to push.']);
                exit;
            }
            echo json_encode($res);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function push_chunk_ajax()
    {
        if (!staff_can('edit', 'inventory')) {
            ajax_access_denied();
        }

        $page = max(1, (int) $this->input->post('page'));
        $per_page = min(50, max(1, (int) ($this->input->post('per_page') ?: 10)));

        try {
            $res = $this->wcsync_model->push_chunk_products($page, $per_page);
            echo json_encode($res);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}


