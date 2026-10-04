<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Pos extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if (function_exists('salesos_require_ecommerce')) {
            salesos_require_ecommerce();
        }
        $this->load->model('pos_model');

        // POS sells stock the inventory module owns — without it, every screen
        // here queries tables that do not exist. Fail with a clear message
        // instead of a SQL error the operator cannot act on.
        if (!$this->app_modules->is_active('inventory')) {
            set_alert('danger', 'Point of Sale requires the Inventory module to be active.');
            redirect(admin_url());
        }

        $this->load->model('inventory/inventory_model');

        if (!staff_can('view', 'pos')) {
            access_denied('Point of Sale');
        }
    }

    public function index()
    {
        $staff_id = get_staff_user_id();

        // Retrieve active session
        $active_session = $this->pos_model->get_active_session($staff_id);

        if (!$active_session) {
            // Auto get or seed default register
            $db_prefix = db_prefix();
            $this->db->where('is_active', 1);
            $reg = $this->db->get($db_prefix . 'pos_registers')->row_array();
            if (!$reg) {
                // Seed a default register linked to first default warehouse
                $wh_id = 1;
                if ($this->db->table_exists($db_prefix . 'inventory_warehouses')) {
                    $this->db->where('is_default', 1);
                    $wh = $this->db->get($db_prefix . 'inventory_warehouses')->row_array();
                    if ($wh) {
                        $wh_id = $wh['id'];
                    }
                }
                $this->db->insert($db_prefix . 'pos_registers', [
                    'name'         => 'Default Register',
                    'warehouse_id' => $wh_id,
                    'is_active'    => 1
                ]);
                $register_id = $this->db->insert_id();
            } else {
                $register_id = (int) $reg['id'];
            }

            // Auto-open session with 0 opening balance
            $this->pos_model->open_session($register_id, $staff_id, 0.00);
            $active_session = $this->pos_model->get_active_session($staff_id);
        }

        // Cashier Panel
        $data['title']          = 'POS Cashier Panel';
        $data['session']        = $active_session;
        
        // Fetch inventory products that are active
        $this->db->where('is_active', 1);
        $data['products']       = $this->inventory_model->get_products();

        // Preload all active child variations with stock for zero-lag variation selection modal & barcode scanning
        $prefix = db_prefix();
        $this->db->select("p.*, COALESCE(SUM(s.qty_on_hand), 0) as stock_on_hand, i.rate as rate");
        $this->db->from($prefix . 'inventory_products p');
        $this->db->join($prefix . 'inventory_stock s', 's.product_id = p.id', 'left');
        $this->db->join($prefix . 'items i', 'i.id = p.item_id', 'left');
        $this->db->where('p.parent_id IS NOT NULL');
        $this->db->where('p.is_active', 1);
        $this->db->group_by('p.id');
        $this->db->order_by('p.id', 'asc');
        $all_variations = $this->db->get()->result_array();

        $variations_by_parent = [];
        foreach ($all_variations as $v) {
            $variations_by_parent[(int)$v['parent_id']][] = [
                'id'            => (int) $v['id'],
                'parent_id'     => (int) $v['parent_id'],
                'name'          => $v['name'],
                'sku'           => $v['sku'],
                'rate'          => (float) $v['rate'],
                'stock_on_hand' => (float) $v['stock_on_hand'],
                'image'         => $v['image'],
            ];
        }
        $data['variations_by_parent'] = $variations_by_parent;
        $data['all_variations']       = $all_variations;
        $data['allow_oversell']       = (int) get_option('inventory_allow_oversell');
        
        // Fetch clients for dropdown selector with phone and email for live-search
        $this->db->select('c.userid, c.company, c.phonenumber as client_phone, con.phonenumber as contact_phone, con.email as contact_email');
        $this->db->from(db_prefix() . 'clients c');
        $this->db->join(db_prefix() . 'contacts con', 'con.userid = c.userid AND con.is_primary = 1', 'left');
        $this->db->order_by('c.company', 'asc');
        $data['customers']      = $this->db->get()->result_array();
        
        // Get native invoice payment modes (cash, bank, etc.)
        $this->load->model('payment_modes_model');
        $data['payment_modes'] = $this->payment_modes_model->get();

        // Fetch BD divisions for quick selector
        $this->db->select('id, name, bn_name');
        $this->db->order_by('name', 'asc');
        $data['divisions'] = $this->db->get(db_prefix() . 'bd_divisions')->result_array();

        $this->load->view('pos/dashboard', $data);

    }

    public function customer_display()
    {
        $data['title'] = 'Customer Display - POS';
        $this->load->view('pos/customer_display', $data);
    }

    public function close_session()
    {
        if ($this->input->post()) {
            $session_id      = (int) $this->input->post('session_id');
            $closing_balance = (float) $this->input->post('closing_balance');
            
            $this->pos_model->close_session($session_id, $closing_balance);
            set_alert('success', 'Register session closed successfully.');
        }
        redirect(admin_url('pos'));
    }

    // ── AJAX Endpoints ───────────────────────────────────────────────────────

    public function search_products()
    {
        if (!$this->input->is_ajax_request()) {
            ajax_access_denied();
        }
        $q = trim($this->input->get('q') ?? '');

        $this->db->select('p.*, SUM(s.qty_on_hand) as stock_on_hand');
        $this->db->from(db_prefix() . 'inventory_products p');
        $this->db->join(db_prefix() . 'inventory_stock s', 's.product_id = p.id', 'left');
        $this->db->where('p.is_active', 1);
        
        if ($q !== '') {
            $this->db->group_start();
            $this->db->like('p.name', $q);
            $this->db->or_like('p.sku', $q);
            $this->db->group_end();
        }
        
        $this->db->group_by('p.id');
        $products = $this->db->get()->result_array();

        echo json_encode($products);
        exit;
    }

    public function checkout()
    {
        if (!$this->input->is_ajax_request()) {
            ajax_access_denied();
        }

        $items_raw = $this->input->post('items');
        $items = !empty($items_raw) ? json_decode($items_raw, true) : [];

        $order_mode = trim($this->input->post('order_mode') ?: 'offline');
        $client_id  = (int) $this->input->post('client_id');
        $lead_id    = (int) $this->input->post('lead_id');
        $walkin_id  = (int) get_option('pos_default_walkin_client_id');

        if ($order_mode === 'online') {
            if (empty($lead_id) && ($client_id <= 0 || $client_id === $walkin_id)) {
                echo json_encode([
                    'success' => false,
                    'error'   => 'অনলাইন ডেলিভারি অর্ডারের ক্ষেত্রে Walk-in Customer গ্রহণযোগ্য নয়। দয়া করে কাস্টমার বা লিড নির্বাচন করুন অথবা "Quick Add Customer" দিয়ে নতুন গ্রাহক যুক্ত করুন।'
                ]);
                exit;
            }
        }

        $data = [
            'order_mode'            => $order_mode,
            'client_id'             => $client_id,
            'lead_id'               => $lead_id,
            'recipient_name'        => trim($this->input->post('recipient_name') ?? ''),
            'recipient_phone'       => trim($this->input->post('recipient_phone') ?? ''),
            'division_id'           => $this->input->post('division_id'),
            'district_id'           => $this->input->post('district_id'),
            'upazila_id'            => $this->input->post('upazila_id'),
            'union_id'              => $this->input->post('union_id'),
            'delivery_address'      => trim($this->input->post('delivery_address') ?? ''),
            'discount_type'         => $this->input->post('discount_type'),
            'discount_value'        => $this->input->post('discount_value'),
            'shipping'              => $this->input->post('shipping'),
            'payment_method'        => $this->input->post('payment_method'),
            'payment_ref'           => $this->input->post('payment_ref'),
            'online_payment_option' => trim($this->input->post('online_payment_option') ?: 'cod'),
            'advance_amount'        => $this->input->post('advance_amount'),
            'advance_payment_mode'  => $this->input->post('advance_payment_mode'),
            'items'                 => $items,
        ];


        // Get active session
        $staff_id       = get_staff_user_id();
        $active_session = $this->pos_model->get_active_session($staff_id);

        if (!$active_session) {
            echo json_encode(['success' => false, 'error' => 'No active register session found. Please open register first.']);
            exit;
        }

        $data['session_id'] = $active_session['id'];

        try {
            $pos_sale_id = $this->pos_model->process_sale($data);
            echo json_encode(['success' => true, 'pos_sale_id' => $pos_sale_id]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function quick_customer()
    {
        if (!$this->input->is_ajax_request()) {
            ajax_access_denied();
        }

        $company     = trim($this->input->post('company') ?? '');
        $phone       = trim($this->input->post('phone') ?? '');
        $email       = trim($this->input->post('email') ?? '');
        $address     = trim($this->input->post('address') ?? '');
        $city        = trim($this->input->post('city') ?? '');
        $state       = trim($this->input->post('state') ?? '');
        $division_id = (int) $this->input->post('division_id');
        $district_id = (int) $this->input->post('district_id');
        $upazila_id  = (int) $this->input->post('upazila_id');
        $union_id    = (int) $this->input->post('union_id');

        if ($company === '' || $phone === '') {
            echo json_encode(['success' => false, 'error' => 'Name and phone are required.']);
            exit;
        }

        $this->db->trans_start();

        // Insert client
        $this->db->insert(db_prefix() . 'clients', [
            'company'          => $company,
            'address'          => $address,
            'city'             => $city,
            'state'            => $state,
            'division_id'      => $division_id > 0 ? $division_id : null,
            'district_id'      => $district_id > 0 ? $district_id : null,
            'upazila_id'       => $upazila_id > 0 ? $upazila_id : null,
            'union_id'         => $union_id > 0 ? $union_id : null,
            'shipping_street'  => $address,
            'shipping_city'    => $city,
            'shipping_state'   => $state,
            'phonenumber'      => $phone,
            'datecreated'      => date('Y-m-d H:i:s'),
            'active'           => 1
        ]);
        $client_id = $this->db->insert_id();

        // Insert contact
        $this->db->insert(db_prefix() . 'contacts', [
            'userid'      => $client_id,
            'firstname'   => $company,
            'lastname'    => '',
            'email'       => $email,
            'phonenumber' => $phone,
            'datecreated' => date('Y-m-d H:i:s'),
            'active'      => 1,
            'is_primary'  => 1
        ]);

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            echo json_encode(['success' => false, 'error' => 'Database error saving customer.']);
        } else {
            echo json_encode([
                'success'     => true, 
                'client_id'   => $client_id,
                'company'     => $company,
                'phone'       => $phone,
                'email'       => $email,
                'address'     => $address,
                'city'        => $city,
                'state'       => $state,
                'division_id' => $division_id,
                'district_id' => $district_id,
                'upazila_id'  => $upazila_id,
                'union_id'    => $union_id,
            ]);
        }
        exit;
    }

    public function add_hold()
    {
        if (!$this->input->is_ajax_request()) {
            ajax_access_denied();
        }

        $staff_id = get_staff_user_id();
        $active_session = $this->pos_model->get_active_session($staff_id);

        if (!$active_session) {
            echo json_encode(['success' => false, 'error' => 'No active cashier session found.']);
            exit;
        }

        $client_id = $this->input->post('client_id');
        $items_raw = $this->input->post('items');
        $cart_items = !empty($items_raw) ? json_decode($items_raw, true) : [];
        $note       = trim($this->input->post('note') ?? '');

        $hold_id = $this->pos_model->add_hold($active_session['id'], (int)$client_id, $cart_items, $note);
        echo json_encode(['success' => true, 'hold_id' => $hold_id]);
        exit;
    }

    public function get_holds()
    {
        if (!$this->input->is_ajax_request()) {
            ajax_access_denied();
        }

        $staff_id = get_staff_user_id();
        $active_session = $this->pos_model->get_active_session($staff_id);

        if (!$active_session) {
            echo json_encode([]);
            exit;
        }

        $holds = $this->pos_model->get_holds($active_session['id']);
        echo json_encode($holds);
        exit;
    }

    public function load_hold()
    {
        if (!$this->input->is_ajax_request()) {
            ajax_access_denied();
        }

        $hold_id = (int) $this->input->post('hold_id');
        $hold = $this->pos_model->get_hold($hold_id);

        if (!$hold) {
            echo json_encode(['success' => false, 'error' => 'Held cart not found.']);
            exit;
        }

        // Parse items
        $hold['items'] = json_decode($hold['cart_data'], true);
        
        // Delete hold since we are restoring it to active cart
        $this->pos_model->delete_hold($hold_id);

        echo json_encode(['success' => true, 'hold' => $hold]);
        exit;
    }

    public function delete_hold()
    {
        if (!$this->input->is_ajax_request()) {
            ajax_access_denied();
        }

        $hold_id = (int) $this->input->post('hold_id');
        $this->pos_model->delete_hold($hold_id);

        echo json_encode(['success' => true]);
        exit;
    }

    public function get_sales()
    {
        if (!$this->input->is_ajax_request()) {
            ajax_access_denied();
        }

        $staff_id = get_staff_user_id();
        $active_session = $this->pos_model->get_active_session($staff_id);

        if (!$active_session) {
            echo json_encode([]);
            exit;
        }

        $sales = $this->pos_model->get_sales($active_session['id']);
        echo json_encode($sales);
        exit;
    }

    public function search_customer_or_lead()
    {
        if (!$this->input->is_ajax_request()) {
            ajax_access_denied();
        }

        $q = trim($this->input->get('q') ?? '');
        if ($q === '') {
            echo json_encode([]);
            exit;
        }

        $clean_digits = preg_replace('/[^0-9]/', '', $q);
        $phone_suffix = strlen($clean_digits) >= 6 ? substr($clean_digits, -10) : '';

        $results = [];
        $walkin_id = (int) get_option('pos_default_walkin_client_id');

        // 1. Search Existing Clients (tblclients + tblcontacts)
        $this->db->select('c.userid as id, c.company as name, c.phonenumber as client_phone, con.phonenumber as contact_phone, con.email, c.address, c.city, c.state, c.division_id, c.district_id, c.upazila_id, c.union_id');
        $this->db->from(db_prefix() . 'clients c');
        $this->db->join(db_prefix() . 'contacts con', 'con.userid = c.userid AND con.is_primary = 1', 'left');
        $this->db->group_start();
        $this->db->like('c.company', $q);
        if (is_numeric($q)) {
            $this->db->or_where('c.userid', (int)$q);
        }
        if (!empty($clean_digits)) {
            $this->db->or_like('c.phonenumber', $clean_digits);
            $this->db->or_like('con.phonenumber', $clean_digits);
            if (!empty($phone_suffix)) {
                $this->db->or_like('c.phonenumber', $phone_suffix);
                $this->db->or_like('con.phonenumber', $phone_suffix);
            }
        }
        $this->db->group_end();
        if ($walkin_id > 0) {
            $this->db->where('c.userid !=', $walkin_id);
        }
        $this->db->limit(8);
        $clients = $this->db->get()->result_array();

        foreach ($clients as $c) {
            $phone = $c['contact_phone'] ?: ($c['client_phone'] ?: '');
            $div_id = !empty($c['division_id']) ? (int)$c['division_id'] : null;
            $dist_id = !empty($c['district_id']) ? (int)$c['district_id'] : null;

            // Fallback match division by state name if ID not set
            if (!$div_id && !empty($c['state'])) {
                $div = $this->db->select('id')->like('name', trim($c['state']))->get(db_prefix() . 'bd_divisions')->row();
                if ($div) { $div_id = (int) $div->id; }
            }
            // Fallback match district by city name if ID not set
            if (!$dist_id && !empty($c['city'])) {
                $dist = $this->db->select('id')->like('name', trim($c['city']))->get(db_prefix() . 'bd_districts')->row();
                if ($dist) { $dist_id = (int) $dist->id; }
            }

            $results[] = [
                'type'        => 'client',
                'id'          => (int) $c['id'],
                'name'        => $c['name'],
                'phone'       => $phone,
                'email'       => $c['email'] ?: '',
                'address'     => $c['address'] ?: '',
                'city'        => $c['city'] ?: '',
                'state'       => $c['state'] ?: '',
                'division_id' => $div_id,
                'district_id' => $dist_id,
                'upazila_id'  => !empty($c['upazila_id']) ? (int)$c['upazila_id'] : null,
                'union_id'    => !empty($c['union_id']) ? (int)$c['union_id'] : null,
                'badge'       => 'Customer',
                'badge_cls'   => 'label-success',
            ];
        }

        // 2. Search Open Leads (tblleads)
        $this->db->select('l.id, l.name, l.phonenumber, l.email, l.address, l.city, l.state, l.division_id, l.district_id, l.upazila_id, l.union_id, ls.name as status_name');
        $this->db->from(db_prefix() . 'leads l');
        $this->db->join(db_prefix() . 'leads_status ls', 'ls.id = l.status', 'left');
        $this->db->where('l.status !=', 1); // Exclude already converted (status 1 = Customer)
        $this->db->group_start();
        $this->db->like('l.name', $q);
        if (is_numeric($q)) {
            $this->db->or_where('l.id', (int)$q);
        }
        if (!empty($clean_digits)) {
            $this->db->or_like('l.phonenumber', $clean_digits);
            if (!empty($phone_suffix)) {
                $this->db->or_where('l.phone_suffix10', $phone_suffix);
            }
        }
        $this->db->group_end();
        $this->db->limit(8);
        $leads = $this->db->get()->result_array();

        foreach ($leads as $l) {
            $div_id = !empty($l['division_id']) ? (int)$l['division_id'] : null;
            $dist_id = !empty($l['district_id']) ? (int)$l['district_id'] : null;

            if (!$div_id && !empty($l['state'])) {
                $div = $this->db->select('id')->like('name', trim($l['state']))->get(db_prefix() . 'bd_divisions')->row();
                if ($div) { $div_id = (int) $div->id; }
            }
            if (!$dist_id && !empty($l['city'])) {
                $dist = $this->db->select('id')->like('name', trim($l['city']))->get(db_prefix() . 'bd_districts')->row();
                if ($dist) { $dist_id = (int) $dist->id; }
            }

            $results[] = [
                'type'        => 'lead',
                'id'          => (int) $l['id'],
                'name'        => $l['name'],
                'phone'       => $l['phonenumber'],
                'email'       => $l['email'] ?: '',
                'address'     => $l['address'] ?: '',
                'city'        => $l['city'] ?: '',
                'state'       => $l['state'] ?: '',
                'division_id' => $div_id,
                'district_id' => $dist_id,
                'upazila_id'  => !empty($l['upazila_id']) ? (int)$l['upazila_id'] : null,
                'union_id'    => !empty($l['union_id']) ? (int)$l['union_id'] : null,
                'badge'       => 'Lead (' . ($l['status_name'] ?: 'New') . ')',
                'badge_cls'   => 'label-info',
            ];
        }

        header('Content-Type: application/json');
        echo json_encode($results);
        exit;
    }

    public function get_customer_ajax($id)
    {
        if (!$this->input->is_ajax_request()) {
            ajax_access_denied();
        }

        $this->db->select('c.userid, c.company, c.address, c.city, c.state, c.division_id, c.district_id, c.upazila_id, c.union_id, c.phonenumber as client_phone, con.phonenumber as contact_phone, con.email as contact_email');
        $this->db->from(db_prefix() . 'clients c');
        $this->db->join(db_prefix() . 'contacts con', 'con.userid = c.userid AND con.is_primary = 1', 'left');
        $this->db->where('c.userid', (int)$id);
        $customer = $this->db->get()->row_array();

        if ($customer) {
            // Auto-resolve missing division_id / district_id by name if not yet saved as ID
            if (empty($customer['division_id']) && !empty($customer['state'])) {
                $div = $this->db->select('id')->like('name', trim($customer['state']))->get(db_prefix() . 'bd_divisions')->row();
                if ($div) { $customer['division_id'] = (int) $div->id; }
            }
            if (empty($customer['district_id']) && !empty($customer['city'])) {
                $dist = $this->db->select('id')->like('name', trim($customer['city']))->get(db_prefix() . 'bd_districts')->row();
                if ($dist) { $customer['district_id'] = (int) $dist->id; }
            }

            echo json_encode(['success' => true, 'customer' => $customer]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Customer not found.']);
        }
        exit;
    }

    public function update_customer_ajax()
    {
        if (!$this->input->is_ajax_request()) {
            ajax_access_denied();
        }

        $id          = (int) $this->input->post('id');
        $company     = trim($this->input->post('company') ?? '');
        $phone       = trim($this->input->post('phone') ?? '');
        $email       = trim($this->input->post('email') ?? '');
        $address     = trim($this->input->post('address') ?? '');
        $city        = trim($this->input->post('city') ?? '');
        $state       = trim($this->input->post('state') ?? '');
        $division_id = (int) $this->input->post('division_id');
        $district_id = (int) $this->input->post('district_id');
        $upazila_id  = (int) $this->input->post('upazila_id');
        $union_id    = (int) $this->input->post('union_id');

        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'Invalid customer ID.']);
            exit;
        }

        if ($company === '' || $phone === '') {
            echo json_encode(['success' => false, 'error' => 'Name and phone are required.']);
            exit;
        }

        $this->db->trans_start();

        // Update client
        $update_client = [
            'company'         => $company,
            'address'         => $address,
            'city'            => $city,
            'state'           => $state,
            'phonenumber'     => $phone,
            'shipping_street' => $address,
            'shipping_city'   => $city,
            'shipping_state'  => $state,
        ];
        if ($division_id > 0) $update_client['division_id'] = $division_id;
        if ($district_id > 0) $update_client['district_id'] = $district_id;
        if ($upazila_id > 0)  $update_client['upazila_id']  = $upazila_id;
        if ($union_id > 0)    $update_client['union_id']    = $union_id;

        $this->db->where('userid', $id);
        $this->db->update(db_prefix() . 'clients', $update_client);

        // Check if primary contact exists, if so update, otherwise insert
        $this->db->where('userid', $id);
        $this->db->where('is_primary', 1);
        $contact = $this->db->get(db_prefix() . 'contacts')->row();

        if ($contact) {
            $this->db->where('id', $contact->id);
            $this->db->update(db_prefix() . 'contacts', [
                'firstname'   => $company,
                'email'       => $email,
                'phonenumber' => $phone
            ]);
        } else {
            $this->db->insert(db_prefix() . 'contacts', [
                'userid'      => $id,
                'firstname'   => $company,
                'lastname'    => '',
                'email'       => $email,
                'phonenumber' => $phone,
                'datecreated' => date('Y-m-d H:i:s'),
                'active'      => 1,
                'is_primary'  => 1
            ]);
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            echo json_encode(['success' => false, 'error' => 'Database error updating customer.']);
        } else {
            echo json_encode([
                'success'     => true,
                'division_id' => $division_id,
                'district_id' => $district_id,
                'upazila_id'  => $upazila_id,
                'union_id'    => $union_id,
            ]);
        }
        exit;
    }

    public function scan_barcode()
    {
        if (!$this->input->is_ajax_request()) {
            ajax_access_denied();
        }

        $code = trim($this->input->post('code') ?? '');
        if ($code === '') {
            echo json_encode(['success' => false, 'error' => 'Empty barcode/code']);
            exit;
        }

        $prefix = db_prefix();

        // 1. Check IMEI / Serial Number
        if ($this->db->table_exists($prefix . 'inventory_imei_serials')) {
            $serial = $this->db->where('serial_number', $code)
                ->where('status', 'in_stock')
                ->get($prefix . 'inventory_imei_serials')
                ->row_array();

            if ($serial) {
                $prod = $this->inventory_model->get_product((int)$serial['product_id']);
                if ($prod) {
                    $stock = $this->inventory_model->get_stock_on_hand($prod['id']);
                    echo json_encode([
                        'success'     => true,
                        'match_type'  => 'serial',
                        'serial_code' => $code,
                        'product'     => [
                            'id'            => (int) $prod['id'],
                            'name'          => $prod['name'] . ' [SN: ' . $code . ']',
                            'sku'           => $prod['sku'],
                            'rate'          => (float) ($prod['rate'] ?? 0),
                            'stock'         => (float) $stock,
                            'product_type'  => $prod['product_type'],
                            'serial_number' => $code,
                        ]
                    ]);
                    exit;
                }
            }
        }

        // 2. Check exact SKU match in Child Variations
        $variation = $this->db->select("p.*, COALESCE(SUM(s.qty_on_hand), 0) as stock_on_hand, i.rate as rate")
            ->from($prefix . 'inventory_products p')
            ->join($prefix . 'inventory_stock s', 's.product_id = p.id', 'left')
            ->join($prefix . 'items i', 'i.id = p.item_id', 'left')
            ->where('p.parent_id IS NOT NULL')
            ->where('p.is_active', 1)
            ->where('p.sku', $code)
            ->group_by('p.id')
            ->get()
            ->row_array();

        if ($variation) {
            echo json_encode([
                'success'    => true,
                'match_type' => 'variation',
                'product'    => [
                    'id'           => (int) $variation['id'],
                    'parent_id'    => (int) $variation['parent_id'],
                    'name'         => $variation['name'],
                    'sku'          => $variation['sku'],
                    'rate'         => (float) $variation['rate'],
                    'stock'        => (float) $variation['stock_on_hand'],
                    'product_type' => 'variation',
                ]
            ]);
            exit;
        }

        // 3. Check exact SKU match in Parent / Simple products
        $product = $this->db->select("p.*, COALESCE(SUM(s.qty_on_hand), 0) as stock_on_hand, i.rate as rate")
            ->from($prefix . 'inventory_products p')
            ->join($prefix . 'inventory_stock s', 's.product_id = p.id', 'left')
            ->join($prefix . 'items i', 'i.id = p.item_id', 'left')
            ->where('p.parent_id IS NULL')
            ->where('p.is_active', 1)
            ->where('p.sku', $code)
            ->group_by('p.id')
            ->get()
            ->row_array();

        if ($product) {
            $is_variable = ($product['product_type'] === 'variable');
            $child_variations = [];
            if ($is_variable) {
                $child_variations = $this->inventory_model->get_variations((int)$product['id']);
            }
            echo json_encode([
                'success'         => true,
                'match_type'      => $is_variable ? 'variable_parent' : 'simple',
                'product'         => [
                    'id'           => (int) $product['id'],
                    'name'         => $product['name'],
                    'sku'          => $product['sku'],
                    'rate'         => (float) $product['rate'],
                    'stock'        => (float) $product['stock_on_hand'],
                    'product_type' => $product['product_type'],
                ],
                'variations'      => $child_variations,
            ]);
            exit;
        }

        echo json_encode(['success' => false, 'error' => 'No product found matching code: ' . $code]);
        exit;
    }

    // ── Bangladesh Geocode Endpoints ─────────────────────────────────────────

    public function get_bd_divisions()
    {
        if (!$this->input->is_ajax_request()) {
            ajax_access_denied();
        }
        $this->db->select('id, name, bn_name');
        $this->db->order_by('name', 'asc');
        $divisions = $this->db->get(db_prefix() . 'bd_divisions')->result_array();
        echo json_encode(['success' => true, 'data' => $divisions]);
        exit;
    }

    public function get_bd_districts($division_id = null)
    {
        if (!$this->input->is_ajax_request()) {
            ajax_access_denied();
        }
        $division_id = (int) ($division_id ?: $this->input->get_post('division_id'));
        if (!$division_id) {
            echo json_encode(['success' => false, 'data' => []]);
            exit;
        }
        $this->db->select('id, division_id, name, bn_name');
        $this->db->where('division_id', $division_id);
        $this->db->order_by('name', 'asc');
        $districts = $this->db->get(db_prefix() . 'bd_districts')->result_array();
        echo json_encode(['success' => true, 'data' => $districts]);
        exit;
    }

    public function get_bd_upazilas($district_id = null)
    {
        if (!$this->input->is_ajax_request()) {
            ajax_access_denied();
        }
        $district_id = (int) ($district_id ?: $this->input->get_post('district_id'));
        if (!$district_id) {
            echo json_encode(['success' => false, 'data' => []]);
            exit;
        }
        $this->db->select('id, district_id, name, bn_name');
        $this->db->where('district_id', $district_id);
        $this->db->order_by('name', 'asc');
        $upazilas = $this->db->get(db_prefix() . 'bd_upazilas')->result_array();
        echo json_encode(['success' => true, 'data' => $upazilas]);
        exit;
    }

    public function get_bd_unions($upazila_id = null)
    {
        if (!$this->input->is_ajax_request()) {
            ajax_access_denied();
        }
        $upazila_id = (int) ($upazila_id ?: $this->input->get_post('upazila_id'));
        if (!$upazila_id) {
            echo json_encode(['success' => false, 'data' => []]);
            exit;
        }
        $this->db->select('id, upazila_id, name, bn_name');
        $this->db->where('upazila_id', $upazila_id);
        $this->db->order_by('name', 'asc');
        $unions = $this->db->get(db_prefix() . 'bd_unions')->result_array();
        echo json_encode(['success' => true, 'data' => $unions]);
        exit;
    }
}

