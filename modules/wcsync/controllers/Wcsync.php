<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Wcsync extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('wcsync_model');
        if (!staff_can('view', 'wcsync')) {
            access_denied('WooCommerce Sync');
        }
    }

    public function index()
    {
        $data['title'] = 'WooCommerce Sync Dashboard';
        $data['sites'] = $this->wcsync_model->get_sites();

        // Get total synced orders count grouped by site
        $this->db->select('site_id, COUNT(*) as count');
        $this->db->group_by('site_id');
        $stats_rows = $this->db->get(db_prefix() . 'wcsync_orders')->result_array();
        
        $stats = [];
        foreach ($stats_rows as $row) {
            $stats[$row['site_id']] = (int) $row['count'];
        }
        $data['stats'] = $stats;

        // Recent synced orders log
        $this->db->select('wo.*, s.name as site_name, eo.status as eco_status, eo.total as eco_total');
        $this->db->from(db_prefix() . 'wcsync_orders wo');
        $this->db->join(db_prefix() . 'wcsync_sites s', 's.id = wo.site_id');
        $this->db->join(db_prefix() . 'ecomcore_orders eo', 'eo.id = wo.ecomcore_order_id');
        $this->db->order_by('wo.synced_at', 'desc');
        $this->db->limit(15);
        $data['recent_syncs'] = $this->db->get()->result_array();

        $this->load->view('wcsync/dashboard', $data);
    }

    public function settings()
    {
        if ($this->input->post()) {
            if (!staff_can('settings', 'wcsync')) {
                access_denied('WooCommerce Settings');
            }

            $id = $this->input->post('id') ? (int) $this->input->post('id') : null;
            $this->wcsync_model->save_site($this->input->post(), $id);
            
            set_alert('success', 'WooCommerce Site configuration saved successfully.');
            redirect(admin_url('wcsync/settings'));
        }

        $data['title'] = 'WooCommerce Sync Settings';
        $data['sites'] = $this->wcsync_model->get_sites();

        // Load credentials from vault belonging to wcsync
        $data['credentials'] = $this->db->get_where(db_prefix() . 'ecomcore_credentials', [
            'owner_module' => 'wcsync',
            'is_active'    => 1
        ])->result_array();

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
            echo json_encode(array_merge(['success' => true], $counts));
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}
