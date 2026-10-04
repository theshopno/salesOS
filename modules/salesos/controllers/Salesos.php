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
        salesos_require_license();

        if (!staff_can('view', 'salesos')) {
            access_denied('Salesos Dashboard');
        }

        $data['title'] = 'E-commerce Operations Command Center';
        $db_prefix = db_prefix();

        // 1. Fetch High-Level Cumulative Stats (excluding test_channel)
        $this->db->where('channel !=', 'test_channel');
        $data['total_orders'] = $this->db->count_all_results($db_prefix . 'salesos_orders');
        
        // Total Sales (excluding test_channel)
        $this->db->select_sum('total');
        $this->db->where('status', 'confirmed');
        $this->db->where('channel !=', 'test_channel');
        $sales_row = $this->db->get($db_prefix . 'salesos_orders')->row();
        $total_sales = $sales_row ? (float) $sales_row->total : 0.00;

        // Total Due (excluding test_channel)
        // POS orders due amount (from unpaid/partially paid invoices)
        $pos_due = 0.00;
        if ($this->app_modules->is_active('pos') && $this->db->table_exists($db_prefix . 'pos_sales')) {
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
            $pos_due_res = $this->db->query($pos_due_sql)->row();
            $pos_due = $pos_due_res ? (float) $pos_due_res->pos_due : 0.00;
        }

        // Non-POS Due (WooCommerce COD orders or other channel pending payment)
        $this->db->select_sum('total');
        $this->db->where('status', 'confirmed');
        $this->db->where_not_in('channel', ['pos', 'pos_online', 'test_channel']);
        $this->db->group_start();
        $this->db->where('payment_method', 'pending_payment');
        $this->db->or_where('payment_method', 'cod');

        $this->db->group_end();
        $other_due_row = $this->db->get($db_prefix . 'salesos_orders')->row();
        $other_due = $other_due_row ? (float) $other_due_row->total : 0.00;

        $data['total_due']     = $pos_due + $other_due;
        $data['total_sales']   = $total_sales;
        $data['total_revenue'] = max(0, $total_sales - $data['total_due']);

        // 2. Today's & This Month's Performance Snapshot
        $today_sql = "
            SELECT 
                COUNT(*) as today_orders,
                COALESCE(SUM(CASE WHEN status = 'confirmed' THEN total ELSE 0 END), 0) as today_sales
            FROM {$db_prefix}salesos_orders
            WHERE DATE(created_at) = CURDATE()
              AND channel != 'test_channel'
        ";
        $today_stats = $this->db->query($today_sql)->row_array();
        $data['today_orders'] = (int) ($today_stats['today_orders'] ?? 0);
        $data['today_sales']  = (float) ($today_stats['today_sales'] ?? 0.00);

        $month_sql = "
            SELECT 
                COUNT(*) as month_orders,
                COALESCE(SUM(CASE WHEN status = 'confirmed' THEN total ELSE 0 END), 0) as month_sales
            FROM {$db_prefix}salesos_orders
            WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())
              AND channel != 'test_channel'
        ";
        $month_stats = $this->db->query($month_sql)->row_array();
        $data['month_orders'] = (int) ($month_stats['month_orders'] ?? 0);
        $data['month_sales']  = (float) ($month_stats['month_sales'] ?? 0.00);

        // 3. Operational Order Pipeline Funnel Counts
        $pipeline_sql = "
            SELECT 
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_count,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_count,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_count,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count
            FROM {$db_prefix}salesos_orders
            WHERE channel != 'test_channel'
        ";
        $pipeline = $this->db->query($pipeline_sql)->row_array();
        $data['pending_count']    = (int) ($pipeline['pending_count'] ?? 0);
        $data['confirmed_count']  = (int) ($pipeline['confirmed_count'] ?? 0);
        $data['processing_count'] = (int) ($pipeline['processing_count'] ?? 0);
        $data['ready_count']      = $data['confirmed_count'] + $data['processing_count'];
        $data['delivered_count']  = (int) ($pipeline['delivered_count'] ?? 0);
        $data['cancelled_count']  = (int) ($pipeline['cancelled_count'] ?? 0);

        // 4. Courier Consignments & High Risk Counts
        $courier_active = $this->app_modules->is_active('courier');
        $booked_count = 0;
        if ($courier_active && $this->db->table_exists($db_prefix . 'courier_consignments')) {
            $b_sql = "SELECT COUNT(DISTINCT salesos_order_id) as c FROM {$db_prefix}courier_consignments";
            $booked_res = $this->db->query($b_sql)->row();
            $booked_count = $booked_res ? (int) $booked_res->c : 0;
        }
        $data['courier_booked_count'] = $booked_count;

        $high_risk_count = 0;
        if ($this->app_modules->is_active('fraudcheck') && $this->db->table_exists($db_prefix . 'fraudcheck_lookups')) {
            $hr_sql = "
                SELECT COUNT(DISTINCT o.id) as c 
                FROM {$db_prefix}salesos_orders o
                LEFT JOIN {$db_prefix}contacts con ON con.userid = o.client_id AND con.is_primary = 1
                LEFT JOIN {$db_prefix}clients c ON c.userid = o.client_id
                LEFT JOIN {$db_prefix}leads l ON l.id = o.lead_id
                JOIN {$db_prefix}fraudcheck_lookups fl ON (fl.phone = con.phonenumber OR fl.phone = c.phonenumber OR fl.phone = l.phonenumber)
                WHERE fl.risk_level IN ('high_risk', 'red')
            ";
            $hr_res = $this->db->query($hr_sql)->row();
            $high_risk_count = $hr_res ? (int) $hr_res->c : 0;
        }
        $data['high_risk_count'] = $high_risk_count;

        // 5. Omni-Channel Breakdown & Share Analytics (All Channels + Confirmed Revenue)
        $ch_sql = "
            SELECT 
                channel,
                COUNT(*) as order_count,
                COALESCE(SUM(CASE WHEN status = 'confirmed' THEN total ELSE 0 END), 0) as confirmed_revenue,
                COALESCE(SUM(total), 0) as total_value
            FROM {$db_prefix}salesos_orders
            WHERE channel != 'test_channel'
            GROUP BY channel
            ORDER BY confirmed_revenue DESC
        ";
        $channels_raw = $this->db->query($ch_sql)->result_array();
        $channels = [];
        $total_rev_sum = max(1, $total_sales); // Prevent divide by zero
        foreach ($channels_raw as $ch) {
            $ch['share_percent'] = round(((float)$ch['confirmed_revenue'] / $total_rev_sum) * 100, 1);
            $channels[] = $ch;
        }
        $data['channels'] = $channels;

        // 6. Last 7 Days Daily Sales Trend for Chart.js
        $trend_sql = "
            SELECT 
                DATE(created_at) as order_date,
                channel,
                COUNT(*) as order_count,
                COALESCE(SUM(total), 0) as daily_total
            FROM {$db_prefix}salesos_orders
            WHERE created_at >= CURDATE() - INTERVAL 6 DAY
              AND channel != 'test_channel'
            GROUP BY DATE(created_at), channel
            ORDER BY order_date ASC
        ";
        $trend_rows = $this->db->query($trend_sql)->result_array();

        // Build 7 calendar days sequence
        $chart_days = [];
        $chart_labels = [];
        $pos_trend = [];
        $woo_trend = [];
        $manual_trend = [];

        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $chart_days[$d] = [
                'pos'    => 0.0,
                'woo'    => 0.0,
                'manual' => 0.0,
            ];
            $chart_labels[] = date('d M', strtotime($d));
        }

        foreach ($trend_rows as $tr) {
            $d = $tr['order_date'];
            $c = strtolower($tr['channel']);
            if ($c === 'woocommerce') {
                $c = 'woo';
            } elseif ($c === 'pos_online') {
                $c = 'pos';
            }
            if (isset($chart_days[$d])) {
                if (isset($chart_days[$d][$c])) {
                    $chart_days[$d][$c] += (float) $tr['daily_total'];
                } else {
                    $chart_days[$d]['manual'] += (float) $tr['daily_total'];
                }
            }
        }

        foreach ($chart_days as $day_data) {
            $pos_trend[]    = $day_data['pos'];
            $woo_trend[]    = $day_data['woo'];
            $manual_trend[] = $day_data['manual'];
        }

        $data['chart_data'] = [
            'labels' => $chart_labels,
            'pos'    => $pos_trend,
            'woo'    => $woo_trend,
            'manual' => $manual_trend,
        ];

        // 7. Action Hub: High-Risk Orders Needing Attention (Top 3)
        $urgent_risk_orders = [];
        if ($high_risk_count > 0) {
            $urg_sql = "
                SELECT 
                    o.id,
                    o.channel,
                    o.total,
                    o.status,
                    o.created_at,
                    COALESCE(NULLIF(TRIM(CONCAT(con.firstname, ' ', con.lastname)), ''), c.company, l.name, 'Customer') as customer_name,
                    COALESCE(con.phonenumber, c.phonenumber, l.phonenumber, '') as customer_phone,
                    fl.risk_level,
                    fl.success_ratio
                FROM {$db_prefix}salesos_orders o
                LEFT JOIN {$db_prefix}contacts con ON con.userid = o.client_id AND con.is_primary = 1
                LEFT JOIN {$db_prefix}clients c ON c.userid = o.client_id
                LEFT JOIN {$db_prefix}leads l ON l.id = o.lead_id
                JOIN {$db_prefix}fraudcheck_lookups fl ON (fl.phone = con.phonenumber OR fl.phone = c.phonenumber OR fl.phone = l.phonenumber)
                WHERE fl.risk_level IN ('high_risk', 'red')
                ORDER BY o.created_at DESC
                LIMIT 3
            ";
            $urgent_risk_orders = $this->db->query($urg_sql)->result_array();
        }
        $data['urgent_risk_orders'] = $urgent_risk_orders;

        // 8. Action Hub: Low-Stock / Zero-Stock Products (Top 4 items)
        $data['low_stock_products'] = [];
        if ($this->db->table_exists($db_prefix . 'inventory_stock')) {
            $low_stock_sql = "
                SELECT 
                    i.id,
                    i.description as name,
                    i.rate,
                    COALESCE(s.qty_on_hand, 0) as stock
                FROM {$db_prefix}items i
                LEFT JOIN {$db_prefix}inventory_stock s ON s.product_id = i.id
                ORDER BY stock ASC, i.id ASC
                LIMIT 4
            ";
            $data['low_stock_products'] = $this->db->query($low_stock_sql)->result_array();
        }

        // 9. Integration Health Statuses
        $creds_sql = "
            SELECT owner_module, label, is_active 
            FROM {$db_prefix}salesos_credentials
            WHERE is_active = 1
        ";
        $active_creds = $this->db->query($creds_sql)->result_array();
        $has_woo = false;
        $has_courier = false;
        $has_fraud = false;
        foreach ($active_creds as $cr) {
            if ($cr['owner_module'] === 'wcsync') $has_woo = true;
            if ($cr['owner_module'] === 'courier') $has_courier = true;
            if ($cr['owner_module'] === 'fraudcheck') $has_fraud = true;
        }
        if (!$has_courier && $courier_active) {
            $ca_count = (int) $this->db->count_all_results($db_prefix . 'courier_accounts');
            if ($ca_count > 0) $has_courier = true;
        }
        $has_whatsapp = (get_option('ordernotifier_whatsapp_enabled') === '1');

        $data['system_health'] = [
            'woo'       => $has_woo,
            'courier'   => $has_courier,
            'fraud'     => $has_fraud,
            'whatsapp'  => $has_whatsapp,
        ];

        // 10. Enriched Recent 10 Orders with Courier tracking, Item count, and Fraud risk
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
                cc.id as consignment_id,
                cc.status as courier_status,
                cc.tracking_id as courier_tracking_id,
                ca.provider as courier_provider,
                ca.label as courier_account_name,
                fl.risk_level as fraud_risk_level,
                fl.success_ratio as fraud_success_ratio,
                (SELECT COUNT(*) FROM {$db_prefix}salesos_order_items oi WHERE oi.order_id = o.id) as item_count
            FROM {$db_prefix}salesos_orders o
            LEFT JOIN {$db_prefix}contacts con ON con.userid = o.client_id AND con.is_primary = 1
            LEFT JOIN {$db_prefix}clients c ON c.userid = o.client_id
            LEFT JOIN {$db_prefix}leads l ON l.id = o.lead_id
            LEFT JOIN {$db_prefix}courier_consignments cc ON cc.salesos_order_id = o.id
            LEFT JOIN {$db_prefix}courier_accounts ca ON ca.id = cc.courier_account_id
            LEFT JOIN {$db_prefix}fraudcheck_lookups fl ON (fl.phone = con.phonenumber OR fl.phone = c.phonenumber OR fl.phone = l.phonenumber)
            WHERE o.channel != 'test_channel'
            ORDER BY o.created_at DESC
            LIMIT 10
        ";
        $data['recent_orders'] = $this->db->query($sql)->result_array();

        // Courier accounts for inline booking modal
        $data['courier_accounts'] = [];
        if ($courier_active) {
            $this->load->model('courier/courier_model');
            $data['courier_accounts'] = $this->courier_model->get_accounts();
        }

        $this->load->view('salesos/dashboard', $data);
    }

    /**
     * The confirmation queue: orders waiting on a phone call before they are
     * allowed to reach stock, courier and notifications.
     */
    public function confirmations()
    {
        salesos_require_ecommerce();
        salesos_require_license();

        if (!staff_can('view', 'salesos')) {
            access_denied('Order Confirmations');
        }

        if (!salesos_is_standalone_mode() && class_exists(\Licentra\CodeIgniter\Registry::class)) {
            $service = \Licentra\CodeIgniter\Registry::get('salesos');
            if (! $service->has('order_confirmation')) {
                set_alert('warning', 'Your current SalesOS license does not include the Order Confirmation entitlement.');
                redirect(admin_url('salesos/settings?tab=license'));
            }
        }

        if (!salesos_order_confirmation_required()) {
            set_alert('warning', 'Order confirmation is switched off, so nothing is held for a call. Turn it on in Settings to use this queue.');
            redirect(admin_url('salesos/orders'));
        }

        $prefix = db_prefix();
        $data['title']   = 'Order Confirmations Desk';
        $data['orders']  = $this->salesos_model->get_confirmation_queue();
        $data['waiting'] = $this->salesos_model->count_confirmation_queue();
        $data['stats']   = $this->salesos_model->get_confirmation_stats(
            date('Y-m-d', strtotime('-6 days')),
            date('Y-m-d')
        );

        // Queue total value
        $queue_val_row = $this->db->query("
            SELECT COALESCE(SUM(total), 0) as total_val 
            FROM {$prefix}salesos_orders 
            WHERE status = 'pending' AND channel != 'test_channel'
        ")->row();
        $data['queue_value'] = $queue_val_row ? (float) $queue_val_row->total_val : 0.0;

        // Today's Telesales Calling Stats
        $today_confirmed_row = $this->db->query("
            SELECT COUNT(*) as c 
            FROM {$prefix}salesos_events 
            WHERE event_type = 'order.call_confirmed' AND DATE(created_at) = CURDATE()
        ")->row();
        $data['today_confirmed'] = $today_confirmed_row ? (int) $today_confirmed_row->c : 0;

        $today_cancelled_row = $this->db->query("
            SELECT COUNT(*) as c 
            FROM {$prefix}salesos_events 
            WHERE event_type = 'order.call_cancelled' AND DATE(created_at) = CURDATE()
        ")->row();
        $data['today_cancelled'] = $today_cancelled_row ? (int) $today_cancelled_row->c : 0;

        $today_no_answer_row = $this->db->query("
            SELECT COUNT(*) as c 
            FROM {$prefix}salesos_events 
            WHERE event_type IN ('order.call_no_answer', 'order.call_later') AND DATE(created_at) = CURDATE()
        ")->row();
        $data['today_no_answer'] = $today_no_answer_row ? (int) $today_no_answer_row->c : 0;

        $today_total_decided = $data['today_confirmed'] + $data['today_cancelled'];
        $data['today_success_rate'] = ($today_total_decided > 0) ? round(($data['today_confirmed'] / $today_total_decided) * 100) : 0;

        $this->load->view('salesos/confirmations', $data);
    }

    /** Record a call outcome. Confirming is what releases the order downstream. */
    public function confirm_order($id)
    {
        salesos_require_ecommerce();

        if (!staff_can('edit', 'salesos') && !staff_can('view', 'salesos')) {
            if ($this->input->is_ajax_request()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                return;
            }
            access_denied('Order Confirmations');
        }

        $outcome = $this->input->post('outcome');
        $note    = trim((string) $this->input->post('note'));

        $success = $this->salesos_model->record_confirmation((int) $id, (string) $outcome, $note);
        
        $msg = '';
        if ($success) {
            if ($outcome === 'confirmed') {
                $msg = 'Order #' . $id . ' সফলভাবে কনফার্ম ও রিলিজ হয়েছে।';
            } elseif ($outcome === 'cancelled') {
                $msg = 'Order #' . $id . ' সফলভাবে বাতিল করা হয়েছে।';
            } elseif ($outcome === 'no_answer') {
                $msg = 'Order #' . $id . '-এ কল চেষ্টার নোট ("ফোন ধরেনি") যুক্ত হয়েছে।';
            } elseif ($outcome === 'call_later') {
                $msg = 'Order #' . $id . '-এ "পরে কল" নোট যুক্ত হয়েছে।';
            }
        } else {
            $msg = 'অর্ডারটি আর অপেক্ষমান তালিকায় নেই অথবা অ্যাকশন নেওয়া যায়নি।';
        }

        if ($this->input->is_ajax_request()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success,
                'outcome' => $outcome,
                'message' => $msg,
                'waiting' => $this->salesos_model->count_confirmation_queue()
            ]);
            return;
        }

        if ($success) {
            set_alert('success', $msg);
        } else {
            set_alert('danger', $msg);
        }

        redirect(admin_url('salesos/confirmations'));
    }

    /**
     * Redirect to the Unified Staff & Channel Mapping Hub
     */
    public function agents()
    {
        redirect(admin_url('pbxpilot/agents'));
    }

    public function settings()
    {
        if (!staff_can('settings', 'salesos')) {
            access_denied('Salesos Settings');
        }

        $data['title'] = 'E-commerce Settings';

        if ($this->input->post()) {
            if ($this->input->post('license_settings')) {
                if ($this->input->post('salesos_licentra_api_url') !== null) {
                    update_option('salesos_licentra_api_url', trim((string) $this->input->post('salesos_licentra_api_url')));
                }
                update_option('salesos_license_standalone_mode', $this->input->post('salesos_license_standalone_mode') ? '1' : '0');
                $action = $this->input->post('license_action');
                if ($action === 'save_only') {
                    set_alert('success', 'License configuration saved successfully.');
                    redirect(admin_url('salesos/settings?tab=license'));
                }

                $service = class_exists(\Licentra\CodeIgniter\Registry::class)
                    ? \Licentra\CodeIgniter\Registry::get('salesos')
                    : null;

                if ($service) {
                    if ($action === 'activate') {
                        $key = trim((string) $this->input->post('salesos_license_key'));
                        if ($key === '') {
                            set_alert('danger', 'License key cannot be empty.');
                        } else {
                            try {
                                $license = $service->activate($key);
                                update_option('salesos_license_key', $key);
                                set_alert('success', 'SalesOS activated successfully! Status: ' . $license->getStatus());
                            } catch (\Throwable $e) {
                                set_alert('danger', 'License activation failed: ' . $e->getMessage());
                            }
                        }
                    } elseif ($action === 'validate') {
                        try {
                            $res = $service->validate();
                            if ($res->isValid()) {
                                set_alert('success', 'License is valid! Status: ' . $res->getCode()->value);
                            } else {
                                set_alert('warning', 'License validation check: ' . $res->getMessage());
                            }
                        } catch (\Throwable $e) {
                            set_alert('danger', 'License validation error: ' . $e->getMessage());
                        }
                    } elseif ($action === 'deactivate') {
                        try {
                            $service->deactivate();
                            delete_option('salesos_license_key');
                            set_alert('success', 'License deactivated successfully. Seat released.');
                        } catch (\Throwable $e) {
                            set_alert('danger', 'Deactivation failed: ' . $e->getMessage());
                        }
                    }
                } else {
                    set_alert('danger', 'Licentra CodeIgniter SDK is not available.');
                }

                redirect(admin_url('salesos/settings?tab=license'));
            }

            if ($this->input->post('general_settings')) {
                $enabled = $this->input->post('salesos_ecommerce_enabled') ? '1' : '0';
                update_option('salesos_ecommerce_enabled', $enabled);
                update_option('salesos_require_order_confirmation',
                    $this->input->post('salesos_require_order_confirmation') ? '1' : '0');

                // Inventory & Overselling options
                update_option('inventory_allow_oversell', $this->input->post('inventory_allow_oversell') === '1' ? '1' : '0');
                update_option('remove_decimals_on_zero', $this->input->post('remove_decimals_on_zero') === '1' ? '1' : '0');
                $industry_mode = $this->input->post('inventory_industry_mode');
                if ($industry_mode) {
                    $valid = ['fashion', 'gadgets', 'grocery', 'general'];
                    if (in_array($industry_mode, $valid, true)) {
                        update_option('inventory_industry_mode', $industry_mode);
                    }
                }

                set_alert('success', 'General and inventory settings updated successfully.');
                redirect(admin_url('salesos/settings?tab=general'));
            }

            if ($this->input->post('notifications_settings')) {
                $checkboxes = [
                    'ordernotifier_whatsapp_enabled',
                    'ordernotifier_sms_enabled',
                    'ordernotifier_notify_on_created',
                    'ordernotifier_notify_on_confirmed',
                    'ordernotifier_notify_on_cancelled',
                    'ordernotifier_notify_on_shipped',
                    'ordernotifier_notify_on_delivered',
                    'salesos_bizbot_inbound_enabled',
                    'salesos_bizbot_twoway_confirm_enabled',
                    'salesos_bizbot_moderator_order_enabled',
                    'salesos_bizbot_auto_assign_reply',
                    'salesos_bizbot_lock_assigned_leads',
                    'salesos_bizbot_exclude_staff',
                    'salesos_bizbot_exclude_suppliers',
                ];
                foreach ($checkboxes as $cb) {
                    update_option($cb, $this->input->post($cb) ? '1' : '0');
                }

                $options = [
                    'ordernotifier_channel_mode',
                    'ordernotifier_whatsapp_base_url',
                    'ordernotifier_whatsapp_token',
                    'ordernotifier_whatsapp_channel_guid',
                    'ordernotifier_sms_api_url',
                    'ordernotifier_sms_api_key',
                    'ordernotifier_sms_sender_id',
                    'ordernotifier_template_created_whatsapp',
                    'ordernotifier_template_created_sms',
                    'ordernotifier_template_confirmed_whatsapp',
                    'ordernotifier_template_confirmed_sms',
                    'ordernotifier_template_cancelled_whatsapp',
                    'ordernotifier_template_cancelled_sms',
                    'salesos_bizbot_webhook_key',
                    'salesos_bizbot_default_lead_status',
                    'salesos_bizbot_order_keyword',
                    'salesos_bizbot_capture_mode',
                    'salesos_bizbot_blacklist_phones',
                    'salesos_bizbot_intent_keywords',
                    'salesos_bizbot_manual_lead_keyword',
                    'salesos_bizbot_twoway_reply_confirm',
                    'salesos_bizbot_twoway_reply_cancel',
                ];
                foreach ($options as $opt) {
                    if ($this->input->post($opt) !== null) {
                        update_option($opt, $this->input->post($opt));
                    }
                }

                // Process Agent ID to Staff Mapping
                $agent_guids = $this->input->post('bizbot_agent_guid') ?: [];
                $agent_staff = $this->input->post('bizbot_agent_staff') ?: [];
                $mapping = [];
                for ($i = 0; $i < count($agent_guids); $i++) {
                    $guid = trim($agent_guids[$i] ?? '');
                    $staff = (int) ($agent_staff[$i] ?? 0);
                    if (!empty($guid) && $staff > 0) {
                        $mapping[$guid] = $staff;
                    }
                }
                update_option('salesos_bizbot_agent_mapping', json_encode($mapping));

                // Process Dynamic Staff Aliases
                $staff_aliases_input = $this->input->post('bizbot_staff_aliases') ?: [];
                $staff_aliases_map = [];
                if (is_array($staff_aliases_input)) {
                    foreach ($staff_aliases_input as $staff_id => $aliases_str) {
                        $staff_id = (int) $staff_id;
                        if ($staff_id > 0) {
                            $split = array_filter(array_map('trim', explode(',', $aliases_str)));
                            if (!empty($split)) {
                                $staff_aliases_map[$staff_id] = array_values($split);
                            }
                        }
                    }
                }
                update_option('salesos_bizbot_staff_aliases', json_encode($staff_aliases_map, JSON_UNESCAPED_UNICODE));

                set_alert('success', 'Notification and Bizbot settings updated successfully.');
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

        // Lead Statuses & Staff for Bizbot WhatsApp Inbound Settings
        $data['lead_statuses'] = $this->db->order_by('statusorder', 'asc')->get(db_prefix() . 'leads_status')->result_array();
        $data['all_staff']     = $this->db->get_where(db_prefix() . 'staff', ['active' => 1])->result_array();

        // Licentra Licensing Diagnostics
        $service = class_exists(\Licentra\CodeIgniter\Registry::class)
            ? \Licentra\CodeIgniter\Registry::get('salesos')
            : null;
        $diag = $service ? $service->diagnostics() : [];
        if ($service) {
            $diag['entitlements'] = $service->entitlements();
            $diag['license_key_masked'] = $diag['masked_license_key'] ?? null;
        }
        $data['license_info'] = $diag;
        $data['is_licensed']  = salesos_is_licensed();
        $data['is_dev_mode']  = salesos_is_local_dev();
        $data['is_standalone'] = salesos_is_standalone_mode();

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
        salesos_require_license();

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

        // Query KPI stats across all orders for top metric cards
        $kpi_sql = "
            SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(total), 0) as total_revenue,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_count,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_count,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_count,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count
            FROM {$db_prefix}salesos_orders
        ";
        $kpi = $this->db->query($kpi_sql)->row_array();

        $booked_count = 0;
        if ($courier_active && $this->db->table_exists($db_prefix . 'courier_consignments')) {
            $b_sql = "SELECT COUNT(DISTINCT salesos_order_id) as c FROM {$db_prefix}courier_consignments";
            $booked_res = $this->db->query($b_sql)->row();
            $booked_count = $booked_res ? (int) $booked_res->c : 0;
        }
        $kpi['courier_booked_count'] = $booked_count;

        $high_risk_count = 0;
        if ($this->app_modules->is_active('fraudcheck') && $this->db->table_exists($db_prefix . 'fraudcheck_lookups')) {
            $hr_sql = "
                SELECT COUNT(DISTINCT o.id) as c 
                FROM {$db_prefix}salesos_orders o
                LEFT JOIN {$db_prefix}contacts con ON con.userid = o.client_id AND con.is_primary = 1
                LEFT JOIN {$db_prefix}clients c ON c.userid = o.client_id
                LEFT JOIN {$db_prefix}leads l ON l.id = o.lead_id
                JOIN {$db_prefix}fraudcheck_lookups fl ON (fl.phone = con.phonenumber OR fl.phone = c.phonenumber OR fl.phone = l.phonenumber)
                WHERE fl.risk_level IN ('high_risk', 'red')
            ";
            $hr_res = $this->db->query($hr_sql)->row();
            $high_risk_count = $hr_res ? (int) $hr_res->c : 0;
        }
        $kpi['high_risk_count'] = $high_risk_count;
        $data['kpi'] = $kpi;

        // Build filters
        $where = [];
        $bindings = [];

        $search         = trim($this->input->get('search') ?? '');
        $channel        = trim($this->input->get('channel') ?? '');
        $status         = trim($this->input->get('status') ?? '');
        $date_range     = trim($this->input->get('date_range') ?? '');
        $courier_status = trim($this->input->get('courier_status') ?? '');

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
        if ($date_range !== '') {
            $data['selected_date_range'] = $date_range;
            if ($date_range === 'today') {
                $where[] = "DATE(o.created_at) = CURDATE()";
            } elseif ($date_range === 'yesterday') {
                $where[] = "DATE(o.created_at) = CURDATE() - INTERVAL 1 DAY";
            } elseif ($date_range === 'this_week') {
                $where[] = "YEARWEEK(o.created_at, 1) = YEARWEEK(CURDATE(), 1)";
            } elseif ($date_range === 'this_month') {
                $where[] = "YEAR(o.created_at) = YEAR(CURDATE()) AND MONTH(o.created_at) = MONTH(CURDATE())";
            }
        }
        if ($courier_status !== '' && $courier_active) {
            $data['selected_courier_status'] = $courier_status;
            if ($courier_status === 'booked') {
                $where[] = "cc.id IS NOT NULL";
            } elseif ($courier_status === 'unbooked') {
                $where[] = "cc.id IS NULL";
            }
        }

        $where_sql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        // Count total rows
        $count_courier_join = ($courier_status !== '' && $courier_active)
            ? "LEFT JOIN {$db_prefix}courier_consignments cc ON cc.salesos_order_id = o.id"
            : "";
        $count_sql = "
            SELECT COUNT(*) as count
            FROM {$db_prefix}salesos_orders o
            LEFT JOIN {$db_prefix}contacts con ON con.userid = o.client_id AND con.is_primary = 1
            LEFT JOIN {$db_prefix}clients c ON c.userid = o.client_id
            LEFT JOIN {$db_prefix}leads l ON l.id = o.lead_id
            $count_courier_join
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
            ? "cc.id as consignment_id, cc.tracking_id as courier_tracking_id, cc.status as courier_status, ca.provider as courier_provider, ca.label as courier_account_name"
            : "NULL as consignment_id, NULL as courier_tracking_id, NULL as courier_status, NULL as courier_provider, NULL as courier_account_name";
        $courier_join = $courier_active
            ? "LEFT JOIN {$db_prefix}courier_consignments cc ON cc.salesos_order_id = o.id
               LEFT JOIN {$db_prefix}courier_accounts ca ON ca.id = cc.courier_account_id"
            : "";

        $pos_active = $this->app_modules->is_active('pos') && $this->db->table_exists($db_prefix . 'pos_sales');
        $pos_select = $pos_active
            ? "ps.invoice_id as pos_invoice_id,
               inv.status as pos_invoice_status,
               CASE 
                   WHEN o.channel = 'pos' THEN 0.00
                   WHEN inv.id IS NOT NULL THEN (
                       CASE 
                           WHEN inv.status = 1 THEN inv.total
                           WHEN inv.status = 3 THEN GREATEST(0.00, inv.total - COALESCE((SELECT SUM(amount) FROM {$db_prefix}invoicepaymentrecords WHERE invoiceid = inv.id), 0))
                           ELSE 0.00
                       END
                   )
                   WHEN o.payment_method IN ('cod', 'pending_payment') THEN o.total
                   ELSE o.total
               END as collectable_amount,"
            : "NULL as pos_invoice_id,
               NULL as pos_invoice_status,
               CASE 
                   WHEN o.payment_method IN ('cod', 'pending_payment') THEN o.total
                   ELSE o.total
               END as collectable_amount,";
        $pos_join = $pos_active
            ? "LEFT JOIN {$db_prefix}pos_sales ps ON ps.salesos_order_id = o.id
               LEFT JOIN {$db_prefix}invoices inv ON inv.id = ps.invoice_id"
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
                (SELECT COUNT(*) FROM {$db_prefix}salesos_order_items WHERE order_id = o.id) as item_count,
                {$pos_select}
                {$courier_select}
            FROM {$db_prefix}salesos_orders o
            LEFT JOIN {$db_prefix}contacts con ON con.userid = o.client_id AND con.is_primary = 1
            LEFT JOIN {$db_prefix}clients c ON c.userid = o.client_id
            LEFT JOIN {$db_prefix}leads l ON l.id = o.lead_id
            {$pos_join}
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
        if (!staff_can('view', 'salesos')) {
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

    /**
     * AJAX endpoint to quickly update an order's status
     */
    public function update_order_status_ajax()
    {
        if (!staff_can('edit', 'salesos')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }

        $id = (int)($this->input->post('order_id') ?: $this->input->post('id'));
        $status = trim($this->input->post('status') ?? '');
        $valid = ['pending', 'confirmed', 'processing', 'delivered', 'cancelled'];
        if (!in_array($status, $valid, true) || $id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid status or order ID.']);
            exit;
        }

        $updated = $this->salesos_model->set_order_status($id, $status);

        if ($updated) {
            echo json_encode(['success' => true, 'status' => $status, 'order_id' => $id, 'message' => 'Order status updated!']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update order status.']);
        }
        exit;
    }

    /**
     * AJAX endpoint for bulk status changes
     */
    public function bulk_action_ajax()
    {
        if (!staff_can('edit', 'salesos')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }

        $action = trim($this->input->post('action') ?? '');
        $order_ids = $this->input->post('order_ids') ?? [];

        if (empty($order_ids) || !is_array($order_ids)) {
            echo json_encode(['success' => false, 'error' => 'No orders selected.']);
            exit;
        }

        $clean_ids = array_map('intval', $order_ids);
        $clean_ids = array_filter($clean_ids, function($id) { return $id > 0; });

        if (empty($clean_ids)) {
            echo json_encode(['success' => false, 'error' => 'Invalid order IDs.']);
            exit;
        }

        $valid_statuses = ['confirmed', 'processing', 'cancelled', 'delivered'];
        if (in_array($action, $valid_statuses, true)) {
            $updated_count = 0;
            foreach ($clean_ids as $id) {
                if ($this->salesos_model->set_order_status($id, $action)) {
                    $updated_count++;
                }
            }
            echo json_encode(['success' => true, 'action' => $action, 'count' => $updated_count]);
            exit;
        }

        echo json_encode(['success' => false, 'error' => 'Unsupported bulk action.']);
        exit;
    }
}
