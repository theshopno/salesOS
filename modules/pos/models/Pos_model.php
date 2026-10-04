<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Pos_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
        if ($this->app_modules->is_active('salesos')) {
            $this->load->model('salesos/salesos_model');
        }
        if ($this->app_modules->is_active('inventory')) {
            $this->load->model('inventory/inventory_model');
        }
        $this->load->model('invoices_model');
        $this->load->model('payments_model');
    }

    // ── Register Management ──────────────────────────────────────────────────

    public function get_registers(): array
    {
        $this->db->select('r.*, w.name as warehouse_name');
        $this->db->from(db_prefix() . 'pos_registers r');
        $this->db->join(db_prefix() . 'inventory_warehouses w', 'w.id = r.warehouse_id', 'left');
        return $this->db->get()->result_array();
    }

    public function get_register(int $id)
    {
        $this->db->where('id', $id);
        return $this->db->get(db_prefix() . 'pos_registers')->row_array();
    }

    public function add_register(array $data): int
    {
        $this->db->insert(db_prefix() . 'pos_registers', [
            'name'         => trim($data['name']),
            'warehouse_id' => (int) $data['warehouse_id'],
            'is_active'    => isset($data['is_active']) ? (int) $data['is_active'] : 1,
        ]);
        return $this->db->insert_id();
    }

    public function update_register(int $id, array $data): bool
    {
        $this->db->where('id', $id);
        return $this->db->update(db_prefix() . 'pos_registers', [
            'name'         => trim($data['name']),
            'warehouse_id' => (int) $data['warehouse_id'],
            'is_active'    => isset($data['is_active']) ? (int) $data['is_active'] : 1,
        ]);
    }

    public function delete_register(int $id): bool
    {
        $this->db->where('id', $id);
        return $this->db->delete(db_prefix() . 'pos_registers');
    }

    // ── Cashier Session Management ───────────────────────────────────────────

    public function get_active_session(int $staff_id)
    {
        $this->db->select('s.*, r.name as register_name, r.warehouse_id');
        $this->db->from(db_prefix() . 'pos_sessions s');
        $this->db->join(db_prefix() . 'pos_registers r', 'r.id = s.register_id');
        $this->db->where('s.staff_id', $staff_id);
        $this->db->where('s.status', 'open');
        return $this->db->get()->row_array();
    }

    public function open_session(int $register_id, int $staff_id, float $opening_balance): int
    {
        // Close any dangling sessions first
        $this->db->where('staff_id', $staff_id);
        $this->db->where('status', 'open');
        $this->db->update(db_prefix() . 'pos_sessions', [
            'status'    => 'closed',
            'closed_at' => date('Y-m-d H:i:s'),
        ]);

        $this->db->insert(db_prefix() . 'pos_sessions', [
            'register_id'     => $register_id,
            'staff_id'        => $staff_id,
            'opening_balance' => $opening_balance,
            'opened_at'       => date('Y-m-d H:i:s'),
            'status'          => 'open',
        ]);
        return $this->db->insert_id();
    }

    public function close_session(int $session_id, float $closing_balance): bool
    {
        $this->db->where('id', $session_id);
        return $this->db->update(db_prefix() . 'pos_sessions', [
            'closing_balance' => $closing_balance,
            'closed_at'       => date('Y-m-d H:i:s'),
            'status'          => 'closed',
        ]);
    }

    // ── Process POS Sale ─────────────────────────────────────────────────────

    public function process_sale(array $data): int
    {
        $session_id     = (int) $data['session_id'];
        $lead_id        = !empty($data['lead_id']) ? (int) $data['lead_id'] : 0;
        $client_id      = (isset($data['client_id']) && $data['client_id'] !== '') ? (int) $data['client_id'] : 0;

        // Auto-convert lead to client if lead_id was selected
        if ($lead_id > 0) {
            if ($this->app_modules->is_active('salesos')) {
                $this->load->model('salesos/salesos_model');
                $converted_id = $this->salesos_model->convert_lead_to_customer($lead_id);
                if ($converted_id > 0) {
                    $client_id = $converted_id;
                }
            } else {
                $this->load->model('leads_model');
                $lead = $this->leads_model->get($lead_id);
                if ($lead && !empty($lead->client_id)) {
                    $client_id = (int)$lead->client_id;
                }
            }
        }

        $order_mode            = trim($data['order_mode'] ?? 'offline');
        $division_id           = !empty($data['division_id']) ? (int) $data['division_id'] : 0;
        $district_id           = !empty($data['district_id']) ? (int) $data['district_id'] : 0;
        $upazila_id            = !empty($data['upazila_id']) ? (int) $data['upazila_id'] : 0;
        $union_id              = !empty($data['union_id']) ? (int) $data['union_id'] : 0;
        $delivery_address      = trim($data['delivery_address'] ?? '');
        $online_payment_option = trim($data['online_payment_option'] ?? 'cod');
        $advance_amount        = (float) ($data['advance_amount'] ?? 0.00);
        $advance_payment_mode  = trim($data['advance_payment_mode'] ?? 'bKash');
        $recipient_name        = trim($data['recipient_name'] ?? '');
        $recipient_phone       = trim($data['recipient_phone'] ?? '');

        // Resolve Bangladesh Geocode names
        $division_name = '';
        $district_name = '';
        $upazila_name  = '';
        $union_name    = '';

        if ($order_mode === 'online') {
            if ($division_id > 0) {
                $r = $this->db->select('name')->where('id', $division_id)->get(db_prefix() . 'bd_divisions')->row();
                if ($r) { $division_name = $r->name; }
            }
            if ($district_id > 0) {
                $r = $this->db->select('name')->where('id', $district_id)->get(db_prefix() . 'bd_districts')->row();
                if ($r) { $district_name = $r->name; }
            }
            if ($upazila_id > 0) {
                $r = $this->db->select('name')->where('id', $upazila_id)->get(db_prefix() . 'bd_upazilas')->row();
                if ($r) { $upazila_name = $r->name; }
            }
            if ($union_id > 0) {
                $r = $this->db->select('name')->where('id', $union_id)->get(db_prefix() . 'bd_unions')->row();
                if ($r) { $union_name = $r->name; }
            }
        }

        $addr_parts = array_filter([$delivery_address, $union_name, $upazila_name, $district_name, $division_name]);
        $composite_address = implode(', ', $addr_parts);

        $walkin_id = (int) get_option('pos_default_walkin_client_id');

        // If client_id is walk-in or 0, but it's an online sale and recipient phone is given:
        if ($order_mode === 'online' && ($client_id === 0 || $client_id === $walkin_id) && !empty($recipient_phone)) {
            $this->db->select('c.userid');
            $this->db->from(db_prefix() . 'clients c');
            $this->db->join(db_prefix() . 'contacts con', 'con.userid = c.userid', 'left');
            $this->db->group_start();
            $this->db->like('c.phonenumber', substr($recipient_phone, -10));
            $this->db->or_like('con.phonenumber', substr($recipient_phone, -10));
            $this->db->group_end();
            $found_client = $this->db->get()->row();

            if ($found_client) {
                $client_id = (int) $found_client->userid;
            } else {
                $c_name = $recipient_name ?: 'Online Customer';
                $this->db->insert(db_prefix() . 'clients', [
                    'company'          => $c_name,
                    'phonenumber'      => $recipient_phone,
                    'address'          => $delivery_address ?: $composite_address,
                    'city'             => $district_name,
                    'state'            => $division_name,
                    'country'          => 18,
                    'division_id'      => $division_id > 0 ? $division_id : null,
                    'district_id'      => $district_id > 0 ? $district_id : null,
                    'upazila_id'       => $upazila_id > 0 ? $upazila_id : null,
                    'union_id'         => $union_id > 0 ? $union_id : null,
                    'shipping_street'  => $composite_address,
                    'shipping_city'    => $district_name,
                    'shipping_state'   => $division_name,
                    'shipping_country' => 18,
                    'datecreated'      => date('Y-m-d H:i:s'),
                    'active'           => 1,
                ]);
                $client_id = $this->db->insert_id();

                $this->db->insert(db_prefix() . 'contacts', [
                    'userid'      => $client_id,
                    'firstname'   => $c_name,
                    'lastname'    => '',
                    'phonenumber' => $recipient_phone,
                    'datecreated' => date('Y-m-d H:i:s'),
                    'active'      => 1,
                    'is_primary'  => 1,
                ]);
            }
        }

        // Persist/Update Geocodes and address for existing customer on online order
        if ($order_mode === 'online' && $client_id > 0 && $client_id !== $walkin_id) {
            $client_update = [];
            if ($division_id > 0) $client_update['division_id'] = $division_id;
            if ($district_id > 0) $client_update['district_id'] = $district_id;
            if ($upazila_id > 0)  $client_update['upazila_id']  = $upazila_id;
            if ($union_id > 0)    $client_update['union_id']    = $union_id;
            if ($division_name !== '') {
                $client_update['state'] = $division_name;
                $client_update['shipping_state'] = $division_name;
            }
            if ($district_name !== '') {
                $client_update['city'] = $district_name;
                $client_update['shipping_city'] = $district_name;
            }
            if (!empty($delivery_address)) {
                $client_update['address'] = $delivery_address;
            }
            if (!empty($composite_address)) {
                $client_update['shipping_street'] = $composite_address;
            }
            if (!empty($recipient_phone)) {
                $client_update['phonenumber'] = $recipient_phone;
            }

            if (!empty($client_update)) {
                $this->db->where('userid', $client_id)->update(db_prefix() . 'clients', $client_update);
            }
        }

        // Also if lead_id was provided, update tblleads
        if ($order_mode === 'online' && !empty($lead_id)) {
            $lead_update = [];
            if ($division_id > 0) $lead_update['division_id'] = $division_id;
            if ($district_id > 0) $lead_update['district_id'] = $district_id;
            if ($upazila_id > 0)  $lead_update['upazila_id']  = $upazila_id;
            if ($union_id > 0)    $lead_update['union_id']    = $union_id;
            if ($division_name !== '') $lead_update['state'] = $division_name;
            if ($district_name !== '') $lead_update['city'] = $district_name;
            if (!empty($delivery_address)) $lead_update['address'] = $delivery_address;
            if (!empty($recipient_phone)) $lead_update['phonenumber'] = $recipient_phone;

            if (!empty($lead_update)) {
                $this->db->where('id', (int) $lead_id)->update(db_prefix() . 'leads', $lead_update);
            }
        }

        if ($client_id === 0) {
            $client_id = $walkin_id;
        }

        $payment_method = trim($data['payment_method'] ?? 'cash');
        $payment_ref    = isset($data['payment_ref']) ? trim($data['payment_ref']) : '';
        $items          = $data['items'] ?? []; // format: [ ['product_id' => X, 'qty' => Y, 'rate' => Z], ... ]

        $discount_type  = trim($data['discount_type'] ?? ''); // 'percent', 'fixed' or ''
        $discount_value = (float) ($data['discount_value'] ?? 0.00);
        $shipping       = (float) ($data['shipping'] ?? 0.00);

        if (empty($items)) {
            throw new Exception("Cannot process sale with an empty cart.");
        }

        // Fetch session
        $this->db->where('id', $session_id);
        $session = $this->db->get(db_prefix() . 'pos_sessions')->row();
        if (!$session || $session->status !== 'open') {
            throw new Exception("No active cashier session found.");
        }

        $this->db->trans_start();

        try {

        // 1. Resolve customer details
        $customer_name    = 'Walk-in Customer';
        $customer_email   = '';
        $customer_phone   = '0000000000';
        $customer_address = '';

        $this->db->where('userid', $client_id);
        $client = $this->db->get(db_prefix() . 'clients')->row();
        if ($client && $client->company !== 'Walk-in Customer') {
            $customer_name    = $client->company;
            $customer_address = $client->address ?: '';
            $customer_phone   = $client->phonenumber ?: '0000000000';

            // If online order has a specific address, update client record
            if ($order_mode === 'online' && !empty($composite_address)) {
                $customer_address = $composite_address;
                $update_fields = ['address' => $composite_address];
                if (!empty($district_name)) { $update_fields['city'] = $district_name; }
                if (!empty($division_name)) { $update_fields['state'] = $division_name; }
                $this->db->where('userid', $client_id);
                $this->db->update(db_prefix() . 'clients', $update_fields);
            }

            // Get primary contact
            $this->db->where('userid', $client_id);
            $this->db->where('is_primary', 1);
            $contact = $this->db->get(db_prefix() . 'contacts')->row();
            if ($contact) {
                $customer_email = $contact->email ?: '';
                if (!empty($contact->phonenumber)) {
                    $customer_phone = $contact->phonenumber;
                }
            }
        }
        
        if ($order_mode === 'online') {
            if (!empty($recipient_name)) { $customer_name = $recipient_name; }
            if (!empty($recipient_phone)) { $customer_phone = $recipient_phone; }
            if (!empty($composite_address)) { $customer_address = $composite_address; }
        }

        // 2. Prepare order line items & calculate totals
        $subtotal = 0.00;
        $generic_items = [];
        $invoice_items = [];
        $item_index = 1;

        foreach ($items as $item) {
            $product_id = (int) $item['product_id'];
            $qty        = (float) $item['qty'];
            $rate       = (float) $item['rate'];
            $line_total = $qty * $rate;
            $subtotal  += $line_total;

            // Fetch product detail
            $this->db->where('id', $product_id);
            $product = $this->db->get(db_prefix() . 'inventory_products')->row();
            if (!$product) {
                throw new Exception("Product ID {$product_id} not found in inventory catalog.");
            }

            // Reject the sale here, before any money is taken. Stock is actually
            // deducted later by inventory's salesos_order_confirmed listener, which
            // runs after the order is committed and so cannot refuse the sale itself.
            if (!$this->inventory_model->oversell_allowed()) {
                // Deliberately the default warehouse, not the register's own
                // warehouse_id: that is the one handle_order_confirmed() will
                // deduct from, and the check has to match the deduction.
                $available = $this->inventory_model->get_stock_on_hand(
                    $product_id,
                    $this->inventory_model->get_default_warehouse_id()
                );
                if ($qty > $available) {
                    throw new Exception("Insufficient stock for {$product->name} — {$available} available, {$qty} requested.");
                }
            }

            $generic_items[] = [
                'product_id' => $product_id,
                'sku'        => $product->sku,
                'name'       => $product->name,
                'qty'        => $qty,
                'unit_price' => $rate,
            ];

            $invoice_items[$item_index] = [
                'description'      => $product->name,
                'long_description' => $product->sku ? 'SKU: ' . $product->sku : '',
                'qty'              => $qty,
                'rate'             => $rate,
                'unit'             => '',
                'order'            => $item_index,
            ];
            $item_index++;
        }

        // Calculate discount total
        $discount_total = 0.00;
        $discount_percent = 0.00;
        if ($discount_type === 'percent') {
            $discount_percent = $discount_value;
            $discount_total = ($subtotal * $discount_percent) / 100;
        } elseif ($discount_type === 'fixed') {
            $discount_total = $discount_value;
            if ($subtotal > 0) {
                $discount_percent = ($discount_total / $subtotal) * 100;
            }
        }

        $total = ($subtotal - $discount_total) + $shipping;

        // 3. Create Generic Order in Salesos
        $is_online = ($order_mode === 'online');
        $effective_channel = $is_online ? 'pos_online' : 'pos';
        $order_note_desc = $is_online
            ? 'POS Online Delivery (' . ($online_payment_option === 'cod' ? 'Cash on Delivery' : ($online_payment_option === 'advance_delivery' ? 'Advance Paid: ৳' . number_format($advance_amount, 2) : 'Full Advance Paid')) . ')'
            : 'POS Cashier Sale';

        $generic_order = [
            'channel'         => $effective_channel,
            'channel_ref_id'  => null,
            'status'          => 'confirmed', // Confirmed on the spot
            'subtotal'        => $subtotal,
            'shipping_charge' => $shipping,
            'total'           => $total,
            'currency'        => 'BDT',
            'payment_method'  => $is_online ? ($online_payment_option === 'cod' ? 'cod' : ($online_payment_option === 'advance_delivery' ? 'advance_paid_cod' : $payment_method)) : $payment_method,
            'order_note'      => $order_note_desc,
            'order_date'      => date('Y-m-d H:i:s'),
            'customer' => [
                'phone'        => $customer_phone,
                'name'         => $customer_name,
                'email'        => $customer_email,
                'address'      => !empty($composite_address) ? $composite_address : $customer_address,
                'city'         => !empty($district_name) ? $district_name : ($client->city ?? ''),
                'state'        => !empty($division_name) ? $division_name : ($client->state ?? ''),
                'zip'          => '',
                'country_code' => 'BD',
                'status_id'    => 1,
            ],
            'items' => $generic_items,
        ];

        // Salesos handles lead matching and stock hooks triggers
        $salesos_order_id = null;
        if ($this->app_modules->is_active('salesos') && isset($this->salesos_model)) {
            $salesos_order_id = $this->salesos_model->import_order($generic_order);
        } else {
            // Standalone POS: Directly deduct stock from default warehouse if inventory is active
            if ($this->app_modules->is_active('inventory') && isset($this->inventory_model)) {
                $wh_id = $this->inventory_model->get_default_warehouse_id();
                foreach ($generic_items as $g_item) {
                    $this->inventory_model->adjust_stock(
                        $g_item['product_id'],
                        $wh_id,
                        -$g_item['qty'],
                        'sale_out',
                        'pos_sale',
                        $session_id,
                        'POS counter sale #' . $session_id
                    );
                }
            }
        }

        // 4. Create Core CRM Invoice
        $this->load->model('currencies_model');
        $base_currency = $this->currencies_model->get_base_currency();
        $currency_id = $base_currency ? $base_currency->id : 1;

        $invoice_data = [
            'clientid'              => $client_id,
            'number'                => get_option('next_invoice_number'),
            'date'                  => date('Y-m-d'),
            'duedate'               => date('Y-m-d'),
            'currency'              => $currency_id,
            'newitems'              => $invoice_items,
            'show_quantity_as'      => 1,
            'subtotal'              => $subtotal,
            'total'                 => $total,
            'discount_percent'      => $discount_percent,
            'discount_total'        => $discount_total,
            'discount_type'         => !empty($discount_type) ? 'before_tax' : '',
            'adjustment'            => $shipping, // adjustment used for shipping charge
            'status'                => 1, // Unpaid first
            'allowed_payment_modes' => [$payment_method],
            'clientnote'            => ($is_online ? 'POS Online Sale' : 'POS In-Store Sale') . ' - Session #' . $session_id,
            'billing_street'        => $customer_address,
            'billing_city'          => !empty($district_name) ? $district_name : ($client->city ?? ''),
            'billing_state'         => !empty($division_name) ? $division_name : ($client->state ?? ''),
            'billing_zip'           => '',
            'billing_country'       => 18,
            'shipping_street'       => !empty($composite_address) ? $composite_address : $customer_address,
            'shipping_city'         => !empty($district_name) ? $district_name : ($client->city ?? ''),
            'shipping_state'        => !empty($division_name) ? $division_name : ($client->state ?? ''),
            'shipping_zip'          => '',
            'shipping_country'      => 18,
            'include_shipping'         => $is_online ? 1 : 0,
            'show_shipping_on_invoice' => $is_online ? 1 : 0,
        ];

        $invoice_id = $this->invoices_model->add($invoice_data);
        if (!$invoice_id) {
            throw new Exception("Failed to generate core billing invoice.");
        }

        // 5. Record Invoice Payment & POS Payment
        $recorded_payment_amount = 0.00;
        $recorded_payment_mode   = $payment_method;
        $recorded_ref            = $payment_ref;

        if ($is_online) {
            if ($online_payment_option === 'cod') {
                // Full COD: Invoice remains Unpaid (status = 1)
                $recorded_payment_amount = 0.00;
                $recorded_payment_mode   = 'cod';
                $recorded_ref            = $payment_ref ?: 'COD pending delivery collection';
            } elseif ($online_payment_option === 'advance_delivery') {
                // Partial Advance: Record advance payment, remaining balance stays due (invoice becomes Partially Paid, status = 3)
                $advance_to_pay = min((float)$advance_amount, $total);
                if ($advance_to_pay > 0) {
                    $mode = !empty($advance_payment_mode) ? $advance_payment_mode : $payment_method;
                    $payment_data = [
                        'invoiceid'   => $invoice_id,
                        'amount'      => $advance_to_pay,
                        'paymentmode' => $mode,
                        'date'        => date('Y-m-d'),
                        'note'        => 'POS Online Advance payment. Session #' . $session_id . ($payment_ref ? ' (Ref: ' . $payment_ref . ')' : '') . '. Due COD: ৳' . number_format($total - $advance_to_pay, 2),
                    ];
                    $payment_id = $this->payments_model->add($payment_data);
                    if (!$payment_id) {
                        throw new Exception("Invoice generated successfully but failed to apply advance payment.");
                    }
                    $recorded_payment_amount = $advance_to_pay;
                    $recorded_payment_mode   = $mode;
                }
            } else {
                // Full Advance Paid
                $payment_data = [
                    'invoiceid'   => $invoice_id,
                    'amount'      => $total,
                    'paymentmode' => $payment_method,
                    'date'        => date('Y-m-d'),
                    'note'        => 'POS Online Full Advance payment. Session #' . $session_id . ($payment_ref ? ' (Ref: ' . $payment_ref . ')' : ''),
                ];
                $payment_id = $this->payments_model->add($payment_data);
                if (!$payment_id) {
                    throw new Exception("Invoice generated successfully but failed to apply payment.");
                }
                $recorded_payment_amount = $total;
            }
        } else {
            // In-store / Offline Sale
            if ($payment_method !== 'pending_payment') {
                $payment_data = [
                    'invoiceid'   => $invoice_id,
                    'amount'      => $total,
                    'paymentmode' => $payment_method,
                    'date'        => date('Y-m-d'),
                    'note'        => 'POS Cashier checkout. Session #' . $session_id . ($payment_ref ? ' (Ref: ' . $payment_ref . ')' : ''),
                ];

                $payment_id = $this->payments_model->add($payment_data);
                if (!$payment_id) {
                    throw new Exception("Invoice generated successfully but failed to apply payment.");
                }
                $recorded_payment_amount = $total;
            }
        }

        // 6. Save POS Sale record
        $this->db->insert(db_prefix() . 'pos_sales', [
            'session_id'        => $session_id,
            'invoice_id'        => $invoice_id,
            'salesos_order_id'  => $salesos_order_id,
            'client_id'         => $client_id,
            'cashier_staff_id'  => get_staff_user_id(),
            'created_at'        => date('Y-m-d H:i:s'),
        ]);
        $pos_sale_id = $this->db->insert_id();

        // 7. Save POS Payment record
        $this->db->insert(db_prefix() . 'pos_payments', [
            'pos_sale_id'     => $pos_sale_id,
            'session_id'      => $session_id,
            'payment_method'  => $recorded_payment_mode,
            'amount'          => $recorded_payment_amount,
            'transaction_ref' => $recorded_ref,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);


        } catch (Exception $e) {
            // Without this, an exception thrown anywhere above (e.g. an unknown
            // product ID) left trans_start()'s transaction dangling — never
            // committed or rolled back — since nothing here previously caught it.
            $this->db->trans_rollback();
            throw $e;
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            throw new Exception("Database transaction error occurred during checkout.");
        }

        return $invoice_id; // Return the invoice ID so we can print it!
    }

    // ── Held Carts Management ────────────────────────────────────────────────

    public function add_hold(int $session_id, int $client_id, array $cart_items, string $note = ''): int
    {
        $this->db->insert(db_prefix() . 'pos_holds', [
            'session_id' => $session_id,
            'client_id'  => $client_id,
            'cart_data'  => json_encode($cart_items),
            'hold_note'  => $note,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->db->insert_id();
    }

    public function get_holds(int $session_id): array
    {
        $this->db->select('h.*, c.company as customer_name');
        $this->db->from(db_prefix() . 'pos_holds h');
        $this->db->join(db_prefix() . 'clients c', 'c.userid = h.client_id', 'left');
        $this->db->where('h.session_id', $session_id);
        $this->db->order_by('h.id', 'desc');
        return $this->db->get()->result_array();
    }

    public function get_hold(int $id)
    {
        $this->db->where('id', $id);
        return $this->db->get(db_prefix() . 'pos_holds')->row_array();
    }

    public function delete_hold(int $id): bool
    {
        $this->db->where('id', $id);
        return $this->db->delete(db_prefix() . 'pos_holds');
    }

    // ── Session Sales List ───────────────────────────────────────────────────

    public function get_sales(int $session_id): array
    {
        $this->db->select('s.*, i.number, i.prefix, i.total as invoice_total, c.company as customer_name, p.payment_method');
        $this->db->from(db_prefix() . 'pos_sales s');
        $this->db->join(db_prefix() . 'invoices i', 'i.id = s.invoice_id');
        $this->db->join(db_prefix() . 'clients c', 'c.userid = s.client_id', 'left');
        $this->db->join(db_prefix() . 'pos_payments p', 'p.pos_sale_id = s.id', 'left');
        $this->db->where('s.session_id', $session_id);
        $this->db->order_by('s.id', 'desc');
        return $this->db->get()->result_array();
    }
}
