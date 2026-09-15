<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Salesos extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('salesos_model');
        $this->load->library('salesos/salesos_encryption');
    }

    public function index()
    {
        // Guarded per method rather than in the constructor: settings() is where
        // e-commerce gets switched back on, so it must stay reachable.
        salesos_require_ecommerce();

        if (!staff_can('view', 'salesos')) {
            access_denied('Salesos Dashboard');
        }

        $data['title'] = 'E-commerce Dashboard';

        // Fetch stats (excluding test_channel)
        $this->db->where('channel !=', 'test_channel');
        $data['total_orders'] = $this->db->count_all_results(db_prefix() . 'salesos_orders');
        
        // 1. Total Sales (excluding test_channel)
        $this->db->select_sum('total');
        $this->db->where('status', 'confirmed');
        $this->db->where('channel !=', 'test_channel');
        $sales_row = $this->db->get(db_prefix() . 'salesos_orders')->row();
        $total_sales = $sales_row ? (float) $sales_row->total : 0.00;

        // 2. Total Due (excluding test_channel)
        $db_prefix = db_prefix();
        // POS orders due amount (from unpaid/partially paid invoices)
        $pos_due_sql = "
            SELECT COALESCE(SUM(
                CASE 
                    WHEN inv.status = 1 THEN inv.total 
                    WHEN inv.status = 3 THEN (inv.total - COALESCE((SELECT SUM(amount) FROM {$db_prefix}invoicepaymentrecords WHERE invoiceid = inv.id), 0))
                    ELSE 0 
                END
            ), 0) as pos_due
            FROM {$db_prefix}salesos_orders o
            JOIN {$db_prefix}pos_sales s ON s.salesos_order_id = o.id
            JOIN {$db_prefix}invoices inv ON inv.id = s.invoice_id
            WHERE o.status = 'confirmed' AND o.channel != 'test_channel'
        ";
        $pos_due = (float) $this->db->query($pos_due_sql)->row()->pos_due;

        // Non-POS Due (WooCommerce COD orders or other channel pending payment)
        $this->db->select_sum('total');
        $this->db->where('status', 'confirmed');
        $this->db->where('channel !=', 'pos');
        $this->db->where('channel !=', 'test_channel');
        $this->db->group_start();
        $this->db->where('payment_method', 'pending_payment');
        $this->db->or_where('payment_method', 'cod');
        $this->db->group_end();
        $other_due_row = $this->db->get(db_prefix() . 'salesos_orders')->row();
        $other_due = $other_due_row ? (float) $other_due_row->total : 0.00;

        $data['total_due']   = $pos_due + $other_due;
        $data['total_sales'] = $total_sales;
        // Total Revenue = Total Sales - Total Due
        $data['total_revenue'] = max(0, $total_sales - $data['total_due']);

        // Channel breakdown (only confirmed orders, excluding test_channel)
        $this->db->select('channel, COUNT(*) as count, SUM(total) as revenue');
        $this->db->where('status', 'confirmed');
        $this->db->where('channel !=', 'test_channel');
        $this->db->group_by('channel');
        $data['channels'] = $this->db->get(db_prefix() . 'salesos_orders')->result_array();

        // Recent orders with customer details resolved
        $db_prefix = db_prefix();
        $sql = "
            SELECT 
                o.*,
                COALESCE(
                    NULLIF(TRIM(CONCAT(con.firstname, ' ', con.lastname)), ''),
                    c.company,
                    l.name,
                    'Guest Customer'
                ) as customer_name,
                COALESCE(con.phonenumber, c.phonenumber, l.phonenumber, '') as customer_phone,
                COALESCE(c.address, l.address, '') as customer_address,
                COALESCE(con.email, l.email, '') as customer_email
            FROM {$db_prefix}salesos_orders o
            LEFT JOIN {$db_prefix}contacts con ON con.userid = o.client_id AND con.is_primary = 1
            LEFT JOIN {$db_prefix}clients c ON c.userid = o.client_id
            LEFT JOIN {$db_prefix}leads l ON l.id = o.lead_id
            ORDER BY o.created_at DESC
            LIMIT 10
        ";
        $data['recent_orders'] = $this->db->query($sql)->result_array();

        // Recent events
        $this->db->order_by('created_at', 'desc');
        $this->db->limit(10);
        $data['recent_events'] = $this->db->get(db_prefix() . 'salesos_events')->result_array();

        $this->load->view('salesos/dashboard', $data);
    }

    /**
     * The confirmation queue: orders waiting on a phone call before they are
     * allowed to reach stock, courier and notifications.
     */
    public function confirmations()
    {
        salesos_require_ecommerce();

        if (!staff_can('view', 'salesos')) {
            access_denied('Order Confirmations');
        }

        if (!salesos_order_confirmation_required()) {
            set_alert('warning', 'Order confirmation is switched off, so nothing is held for a call. Turn it on in Settings to use this queue.');
            redirect(admin_url('salesos/orders'));
        }

        $data['title']   = 'Order Confirmations';
        $data['orders']  = $this->salesos_model->get_confirmation_queue();
        $data['waiting'] = $this->salesos_model->count_confirmation_queue();
        $data['stats']   = $this->salesos_model->get_confirmation_stats(
            date('Y-m-d', strtotime('-6 days')),
            date('Y-m-d')
        );

        $this->load->view('salesos/confirmations', $data);
    }

    /** Record a call outcome. Confirming is what releases the order downstream. */
    public function confirm_order($id)
    {
        salesos_require_ecommerce();

        if (!staff_can('edit', 'salesos') && !staff_can('view', 'salesos')) {
            access_denied('Order Confirmations');
        }

        $outcome = $this->input->post('outcome');
        $note    = trim((string) $this->input->post('note'));

        if ($this->salesos_model->record_confirmation((int) $id, (string) $outcome, $note)) {
            set_alert('success', $outcome === 'confirmed'
                ? 'Order confirmed and released for fulfilment.'
                : 'Order cancelled.');
        } else {
            set_alert('danger', 'That order is no longer waiting for a call.');
        }

        redirect(admin_url('salesos/confirmations'));
    }

    public function settings()
    {
        if (!staff_can('settings', 'salesos')) {
            access_denied('Salesos Settings');
        }

        $data['title'] = 'E-commerce Settings';

        if ($this->input->post()) {
            if ($this->input->post('general_settings')) {
                $enabled = $this->input->post('salesos_ecommerce_enabled') ? '1' : '0';
                update_option('salesos_ecommerce_enabled', $enabled);
                update_option('salesos_require_order_confirmation',
                    $this->input->post('salesos_require_order_confirmation') ? '1' : '0');
                set_alert('success', $enabled === '1'
                    ? 'E-commerce features turned on.'
                    : 'E-commerce features turned off. Telephony is unaffected, and nothing has been deleted.');
                redirect(admin_url('salesos/settings?tab=general'));
            }

            if ($this->input->post('notifications_settings')) {
                $options = [
                    'ordernotifier_whatsapp_enabled',
                    'ordernotifier_whatsapp_base_url',
                    'ordernotifier_whatsapp_token',
                    'ordernotifier_sms_enabled',
                    'ordernotifier_sms_api_url',
                    'ordernotifier_sms_api_key',
                    'ordernotifier_sms_sender_id',
                    'ordernotifier_template_created_whatsapp',
                    'ordernotifier_template_created_sms',
                    'ordernotifier_template_confirmed_whatsapp',
                    'ordernotifier_template_confirmed_sms',
                    'ordernotifier_template_cancelled_whatsapp',
                    'ordernotifier_template_cancelled_sms',
                ];
                foreach ($options as $opt) {
                    $val = $this->input->post($opt);
                    if ($opt === 'ordernotifier_whatsapp_enabled' || $opt === 'ordernotifier_sms_enabled') {
                        $val = $val ? '1' : '0';
                    }
                    update_option($opt, $val);
                }
                set_alert('success', 'Notification settings updated successfully.');
                redirect(admin_url('salesos/settings?tab=notifications'));
            }

            $id = $this->input->post('id');
            $owner_module = $this->input->post('owner_module');
            $label = $this->input->post('label');
            $cred_type = $this->input->post('cred_type');
            $is_active = $this->input->post('is_active') ? 1 : 0;
            
            $payload_raw = $this->input->post('payload');
            $payload_enc = $this->salesos_encryption->encrypt($payload_raw);

            $db_data = [
                'owner_module' => $owner_module,
                'label'        => $label,
                'cred_type'    => $cred_type,
                'payload'      => $payload_enc,
                'is_active'    => $is_active,
            ];

            if ($id) {
                $this->db->where('id', $id);
                $this->db->update(db_prefix() . 'salesos_credentials', $db_data);
                set_alert('success', 'Credential updated successfully.');
            } else {
                $db_data['created_at'] = date('Y-m-d H:i:s');
                $this->db->insert(db_prefix() . 'salesos_credentials', $db_data);
                set_alert('success', 'Credential added successfully.');
            }

            redirect(admin_url('salesos/settings?tab=channels'));
        }

        $credentials = $this->db->get(db_prefix() . 'salesos_credentials')->result_array();
        
        foreach ($credentials as &$cred) {
            $cred['payload_decrypted'] = $this->salesos_encryption->decrypt($cred['payload'], true);
        }

        $data['credentials'] = $credentials;

        // Fetch Courier Accounts for Tab 2 — courier is an optional sibling module,
        // not a dependency of the kernel, so this must not fatal if it's inactive.
        $data['courier_accounts'] = [];
        if ($this->app_modules->is_active('courier')) {
            $this->load->model('courier/courier_model');
            $data['courier_accounts'] = $this->courier_model->get_accounts();
        }

        $this->load->view('salesos/settings', $data);
    }

    public function delete_credential($id)
    {
        if (!staff_can('settings', 'salesos')) {
            access_denied('Salesos Settings');
        }

        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'salesos_credentials');
        set_alert('success', 'Credential deleted successfully.');
        redirect(admin_url('salesos/settings?tab=channels'));
    }

    public function toggle_credential_status($id)
    {
        if (!staff_can('settings', 'salesos')) {
            access_denied('Salesos Settings');
        }

        $this->db->where('id', $id);
        $cred = $this->db->get(db_prefix() . 'salesos_credentials')->row();
        if ($cred) {
            $new_status = $cred->is_active ? 0 : 1;
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'salesos_credentials', ['is_active' => $new_status]);
            echo json_encode(['success' => true, 'is_active' => $new_status]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }

    /**
     * Get order and order items details via AJAX
     */
    public function get_order_details_ajax($id)
    {
        if (!staff_can('view', 'salesos')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }

        $db_prefix = db_prefix();

        // courier_consignments/courier_accounts only exist if the courier module is
        // active — join them conditionally so this endpoint works with the kernel alone.
        $courier_active = $this->db->table_exists($db_prefix . 'courier_consignments')
            && $this->db->table_exists($db_prefix . 'courier_accounts');
        $courier_select = $courier_active
            ? "cc.id as consignment_id, cc.tracking_id as courier_tracking_id, cc.status as courier_status, cc.last_synced_at as courier_last_synced_at, ca.label as courier_account_name, ca.provider as courier_provider"
            : "NULL as consignment_id, NULL as courier_tracking_id, NULL as courier_status, NULL as courier_last_synced_at, NULL as courier_account_name, NULL as courier_provider";
        $courier_join = $courier_active
            ? "LEFT JOIN {$db_prefix}courier_consignments cc ON cc.salesos_order_id = o.id
               LEFT JOIN {$db_prefix}courier_accounts ca ON ca.id = cc.courier_account_id"
            : "";

        $sql = "
            SELECT
                o.*,
                COALESCE(
                    NULLIF(TRIM(CONCAT(con.firstname, ' ', con.lastname)), ''),
                    c.company,
                    l.name,
                    'Guest Customer'
                ) as customer_name,
                COALESCE(con.phonenumber, c.phonenumber, l.phonenumber, '') as customer_phone,
                COALESCE(c.address, l.address, '') as customer_address,
                COALESCE(con.email, l.email, '') as customer_email,
                {$courier_select}
            FROM {$db_prefix}salesos_orders o
            LEFT JOIN {$db_prefix}contacts con ON con.userid = o.client_id AND con.is_primary = 1
            LEFT JOIN {$db_prefix}clients c ON c.userid = o.client_id
            LEFT JOIN {$db_prefix}leads l ON l.id = o.lead_id
            {$courier_join}
            WHERE o.id = ?
        ";

        $order = $this->db->query($sql, [$id])->row_array();

        if (!$order) {
            echo json_encode(['success' => false, 'error' => 'Order not found.']);
            exit;
        }

        $this->db->where('order_id', $id);
        $items = $this->db->get($db_prefix . 'salesos_order_items')->result_array();

        // Retrieve fraud check lookup details if available. fraudcheck_lookups.phone
        // is stored in fraudcheck's own normalized format (11 digits, 0XXXXXXXXXX) —
        // compare against that same format, not the raw stored customer_phone, or a
        // real match silently misses on any formatting difference.
        $order['fraud_data'] = null;
        if (!empty($order['customer_phone']) && $this->app_modules->is_active('fraudcheck') && $this->db->table_exists($db_prefix . 'fraudcheck_lookups')) {
            $this->load->model('fraudcheck/fraudcheck_model');
            $normalized_phone = $this->fraudcheck_model->normalize_phone($order['customer_phone']);
            $this->db->where('phone', $normalized_phone);
            $lookup = $this->db->get($db_prefix . 'fraudcheck_lookups')->row_array();
            if ($lookup) {
                $lookup['raw_response'] = json_decode($lookup['raw_response'], true);
                $order['fraud_data'] = $lookup;
            }
        }

        echo json_encode([
            'success' => true,
            'order'   => $order,
            'items'   => $items
        ]);
        exit;
    }

    /**
     * List all synced orders with filters and pagination
     */
    public function orders()
    {
        salesos_require_ecommerce();

        if (!staff_can('view', 'salesos')) {
            access_denied('Salesos Orders');
        }

        $db_prefix = db_prefix();
        $this->load->library('pagination');

        $courier_active = $this->app_modules->is_active('courier');
        $data['courier_accounts'] = [];
        if ($courier_active) {
            $this->load->model('courier/courier_model');
            $data['courier_accounts'] = $this->courier_model->get_accounts();
        }

        // Build filters
        $where = [];
        $bindings = [];

        $search  = trim($this->input->get('search') ?? '');
        $channel = trim($this->input->get('channel') ?? '');
        $status  = trim($this->input->get('status') ?? '');

        if ($search !== '') {
            $search_like = '%' . $search . '%';
            $where[] = "(o.id LIKE ? OR o.channel_ref_id LIKE ? OR l.name LIKE ? OR l.phonenumber LIKE ? OR c.company LIKE ? OR con.phonenumber LIKE ?)";
            $bindings = array_merge($bindings, [$search_like, $search_like, $search_like, $search_like, $search_like, $search_like]);
            $data['search'] = $search;
        }
        if ($channel !== '') {
            $where[] = "o.channel = ?";
            $bindings[] = $channel;
            $data['selected_channel'] = $channel;
        }
        if ($status !== '') {
            $where[] = "o.status = ?";
            $bindings[] = $status;
            $data['selected_status'] = $status;
        }

        $where_sql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        // Count total rows
        $count_sql = "
            SELECT COUNT(*) as count
            FROM {$db_prefix}salesos_orders o
            LEFT JOIN {$db_prefix}contacts con ON con.userid = o.client_id AND con.is_primary = 1
            LEFT JOIN {$db_prefix}clients c ON c.userid = o.client_id
            LEFT JOIN {$db_prefix}leads l ON l.id = o.lead_id
            $where_sql
        ";
        $total_rows = (int) $this->db->query($count_sql, $bindings)->row()->count;

        // Pagination Config
        $limit = 20;
        $offset = (int) $this->input->get('page') ?: 0;

        $config['base_url']             = admin_url('salesos/orders');
        $config['total_rows']           = $total_rows;
        $config['per_page']             = $limit;
        $config['page_query_string']    = TRUE;
        $config['query_string_segment'] = 'page';
        
        // Perfex CRM styling links
        $config['full_tag_open']    = '<ul class="pagination no-margin">';
        $config['full_tag_close']   = '</ul>';
        $config['first_link']       = '&laquo; First';
        $config['first_tag_open']   = '<li>';
        $config['first_tag_close']  = '</li>';
        $config['last_link']        = 'Last &raquo;';
        $config['last_tag_open']    = '<li>';
        $config['last_tag_close']   = '</li>';
        $config['next_link']        = 'Next &rsaquo;';
        $config['next_tag_open']    = '<li>';
        $config['next_tag_close']   = '</li>';
        $config['prev_link']        = '&lsaquo; Prev';
        $config['prev_tag_open']    = '<li>';
        $config['prev_tag_close']   = '</li>';
        $config['cur_tag_open']     = '<li class="active"><a>';
        $config['cur_tag_close']    = '</a></li>';
        $config['num_tag_open']     = '<li>';
        $config['num_tag_close']    = '</li>';

        // Preserve GET parameters in pagination links
        if (count($_GET) > 0) {
            $get_params = $_GET;
            unset($get_params['page']); // page is appended automatically
            $config['suffix'] = '&' . http_build_query($get_params);
            $config['first_url'] = $config['base_url'] . '?' . http_build_query($get_params);
        }

        $this->pagination->initialize($config);
        $data['pagination'] = $this->pagination->create_links();

        // Query paginated results with courier consignment check, when courier is active
        $courier_select = $courier_active
            ? "cc.id as consignment_id, cc.tracking_id as courier_tracking_id, cc.status as courier_status"
            : "NULL as consignment_id, NULL as courier_tracking_id, NULL as courier_status";
        $courier_join = $courier_active
            ? "LEFT JOIN {$db_prefix}courier_consignments cc ON cc.salesos_order_id = o.id"
            : "";

        $sql = "
            SELECT
                o.*,
                COALESCE(
                    NULLIF(TRIM(CONCAT(con.firstname, ' ', con.lastname)), ''),
                    c.company,
                    l.name,
                    'Guest Customer'
                ) as customer_name,
                COALESCE(con.phonenumber, c.phonenumber, l.phonenumber, '') as customer_phone,
                COALESCE(c.address, l.address, '') as customer_address,
                COALESCE(con.email, l.email, '') as customer_email,
                {$courier_select}
            FROM {$db_prefix}salesos_orders o
            LEFT JOIN {$db_prefix}contacts con ON con.userid = o.client_id AND con.is_primary = 1
            LEFT JOIN {$db_prefix}clients c ON c.userid = o.client_id
            LEFT JOIN {$db_prefix}leads l ON l.id = o.lead_id
            {$courier_join}
            $where_sql
            ORDER BY o.created_at DESC
            LIMIT ? OFFSET ?
        ";
        $bindings[] = $limit;
        $bindings[] = $offset;
        $orders = $this->db->query($sql, $bindings)->result_array();

        // Initialize fraud fields to null for all orders by default
        foreach ($orders as &$order) {
            $order['fraud_success_ratio'] = null;
            $order['fraud_risk_level']    = null;
            $order['fraud_risk_color']    = null;
        }

        // Retrieve fraud lookup stats for the orders' phone numbers to show risk badges
        // in the list. fraudcheck_lookups.phone is stored in fraudcheck's own
        // normalized format — normalize each order's phone the same way before
        // building the lookup map, or a real match silently misses on formatting.
        $fraudcheck_active = $this->app_modules->is_active('fraudcheck') && $this->db->table_exists($db_prefix . 'fraudcheck_lookups');
        if ($fraudcheck_active && !empty($orders)) {
            $this->load->model('fraudcheck/fraudcheck_model');
            $phones = [];
            foreach ($orders as &$order) {
                $order['customer_phone_normalized'] = !empty($order['customer_phone'])
                    ? $this->fraudcheck_model->normalize_phone($order['customer_phone'])
                    : '';
                if ($order['customer_phone_normalized'] !== '') {
                    $phones[] = $this->db->escape($order['customer_phone_normalized']);
                }
            }
            unset($order);
            if (!empty($phones)) {
                $lookups_sql = "SELECT phone, success_ratio, risk_level, risk_color FROM {$db_prefix}fraudcheck_lookups WHERE phone IN (" . implode(',', $phones) . ")";
                $lookups = $this->db->query($lookups_sql)->result_array();
                $lookups_by_phone = [];
                foreach ($lookups as $l) {
                    $lookups_by_phone[$l['phone']] = $l;
                }
                foreach ($orders as &$order) {
                    $p = $order['customer_phone_normalized'];
                    if (isset($lookups_by_phone[$p])) {
                        $order['fraud_success_ratio'] = $lookups_by_phone[$p]['success_ratio'];
                        $order['fraud_risk_level']    = $lookups_by_phone[$p]['risk_level'];
                        $order['fraud_risk_color']    = $lookups_by_phone[$p]['risk_color'];
                    }
                }
            }
        }
        $data['orders'] = $orders;

        $data['title'] = 'All E-commerce Orders';
        $this->load->view('salesos/orders', $data);
    }

    /**
     * AJAX endpoint to manually trigger a fresh fraud check bypassing cache
     */
    public function fraudcheck_recheck_ajax($phone)
    {
        if (!staff_can('settings', 'salesos')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }

        if (!$this->app_modules->is_active('fraudcheck')) {
            echo json_encode(['success' => false, 'error' => 'Fraud check module is not active.']);
            exit;
        }

        $this->load->model('fraudcheck/fraudcheck_model');
        $lookup = $this->fraudcheck_model->check($phone, true); // Bypass cache

        if ($lookup) {
            $lookup['raw_response'] = json_decode($lookup['raw_response'], true);
            echo json_encode(['success' => true, 'fraud_data' => $lookup]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to query BDCourier API.']);
        }
        exit;
    }

    public function print_invoice($id)
    {
        if (!staff_can('view', 'salesos')) {
            access_denied('Print Invoice');
        }

        $db_prefix = db_prefix();
        $sql = "
            SELECT 
                o.*,
                COALESCE(
                    NULLIF(TRIM(CONCAT(con.firstname, ' ', con.lastname)), ''),
                    c.company,
                    l.name,
                    'Guest Customer'
                ) as customer_name,
                COALESCE(con.phonenumber, c.phonenumber, l.phonenumber, '') as customer_phone,
                COALESCE(c.address, l.address, '') as customer_address,
                COALESCE(con.email, l.email, '') as customer_email
            FROM {$db_prefix}salesos_orders o
            LEFT JOIN {$db_prefix}contacts con ON con.userid = o.client_id AND con.is_primary = 1
            LEFT JOIN {$db_prefix}clients c ON c.userid = o.client_id
            LEFT JOIN {$db_prefix}leads l ON l.id = o.lead_id
            WHERE o.id = ?
        ";
        
        $order = $this->db->query($sql, [$id])->row_array();

        if (!$order) {
            show_404();
        }

        $this->db->where('order_id', $id);
        $items = $this->db->get($db_prefix . 'salesos_order_items')->result_array();

        $data['order'] = $order;
        $data['items'] = $items;

        $this->load->view('salesos/print_invoice', $data);
    }

    public function print_label($id)
    {
        if (!staff_can('view', 'salesos')) {
            access_denied('Print Label');
        }

        $db_prefix = db_prefix();
        $sql = "
            SELECT 
                o.*,
                COALESCE(
                    NULLIF(TRIM(CONCAT(con.firstname, ' ', con.lastname)), ''),
                    c.company,
                    l.name,
                    'Guest Customer'
                ) as customer_name,
                COALESCE(con.phonenumber, c.phonenumber, l.phonenumber, '') as customer_phone,
                COALESCE(c.address, l.address, '') as customer_address,
                COALESCE(con.email, l.email, '') as customer_email
            FROM {$db_prefix}salesos_orders o
            LEFT JOIN {$db_prefix}contacts con ON con.userid = o.client_id AND con.is_primary = 1
            LEFT JOIN {$db_prefix}clients c ON c.userid = o.client_id
            LEFT JOIN {$db_prefix}leads l ON l.id = o.lead_id
            WHERE o.id = ?
        ";
        
        $order = $this->db->query($sql, [$id])->row_array();

        if (!$order) {
            show_404();
        }

        $this->db->where('order_id', $id);
        $items = $this->db->get($db_prefix . 'salesos_order_items')->result_array();

        $data['order'] = $order;
        $data['items'] = $items;

        $this->load->view('salesos/print_label', $data);
    }
}
