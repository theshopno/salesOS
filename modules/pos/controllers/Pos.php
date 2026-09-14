<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Pos extends AdminController
{
    public function __construct()
    {
        parent::__construct();
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
        
        // Fetch clients for dropdown selector with phone and email for live-search
        $this->db->select('c.userid, c.company, c.phonenumber as client_phone, con.phonenumber as contact_phone, con.email as contact_email');
        $this->db->from(db_prefix() . 'clients c');
        $this->db->join(db_prefix() . 'contacts con', 'con.userid = c.userid AND con.is_primary = 1', 'left');
        $this->db->order_by('c.company', 'asc');
        $data['customers']      = $this->db->get()->result_array();
        
        // Get native invoice payment modes (cash, bank, etc.)
        $this->load->model('payment_modes_model');
        $data['payment_modes'] = $this->payment_modes_model->get();

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

        $data = [
            'client_id'      => $this->input->post('client_id'),
            'discount_type'  => $this->input->post('discount_type'),
            'discount_value' => $this->input->post('discount_value'),
            'shipping'       => $this->input->post('shipping'),
            'payment_method' => $this->input->post('payment_method'),
            'payment_ref'    => $this->input->post('payment_ref'),
            'items'          => $items,
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

        $company = trim($this->input->post('company') ?? '');
        $phone   = trim($this->input->post('phone') ?? '');
        $email   = trim($this->input->post('email') ?? '');

        if ($company === '' || $phone === '') {
            echo json_encode(['success' => false, 'error' => 'Name and phone are required.']);
            exit;
        }

        $this->db->trans_start();

        // Insert client
        $this->db->insert(db_prefix() . 'clients', [
            'company'     => $company,
            'datecreated' => date('Y-m-d H:i:s'),
            'active'      => 1
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
            echo json_encode(['success' => true, 'client_id' => $client_id]);
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

    public function get_customer_ajax($id)
    {
        if (!$this->input->is_ajax_request()) {
            ajax_access_denied();
        }

        $this->db->select('c.userid, c.company, c.phonenumber as client_phone, con.phonenumber as contact_phone, con.email as contact_email');
        $this->db->from(db_prefix() . 'clients c');
        $this->db->join(db_prefix() . 'contacts con', 'con.userid = c.userid AND con.is_primary = 1', 'left');
        $this->db->where('c.userid', (int)$id);
        $customer = $this->db->get()->row_array();

        if ($customer) {
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

        $id      = (int) $this->input->post('id');
        $company = trim($this->input->post('company') ?? '');
        $phone   = trim($this->input->post('phone') ?? '');
        $email   = trim($this->input->post('email') ?? '');

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
        $this->db->where('userid', $id);
        $this->db->update(db_prefix() . 'clients', [
            'company' => $company
        ]);

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
            echo json_encode(['success' => true]);
        }
        exit;
    }
}
