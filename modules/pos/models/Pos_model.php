<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Pos_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('ecomcore/ecomcore_model');
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
        $client_id      = (isset($data['client_id']) && $data['client_id'] !== '') ? (int) $data['client_id'] : (int) get_option('pos_default_walkin_client_id');
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

        // 1. Resolve customer details
        $customer_name  = 'Walk-in Customer';
        $customer_email = '';
        $customer_phone = '0000000000';
        $customer_address = '';

        $this->db->where('userid', $client_id);
        $client = $this->db->get(db_prefix() . 'clients')->row();
        if ($client && $client->company !== 'Walk-in Customer') {
            $customer_name = $client->company;
            // Get primary contact
            $this->db->where('userid', $client_id);
            $this->db->where('is_primary', 1);
            $contact = $this->db->get(db_prefix() . 'contacts')->row();
            if ($contact) {
                $customer_email = $contact->email;
                $customer_phone = $contact->phoneNumber;
            }
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

        // 3. Create Generic Order in Ecomcore
        $generic_order = [
            'channel'         => 'pos',
            'channel_ref_id'  => null,
            'status'          => 'confirmed', // Confirmed on the spot
            'subtotal'        => $subtotal,
            'shipping_charge' => $shipping,
            'total'           => $total,
            'currency'        => 'BDT',
            'payment_method'  => $payment_method,
            'order_note'      => 'POS Cashier Sale',
            'order_date'      => date('Y-m-d H:i:s'),
            'customer' => [
                'phone'        => $customer_phone,
                'name'         => $customer_name,
                'email'        => $customer_email,
                'address'      => $customer_address,
                'city'         => '',
                'state'        => '',
                'zip'          => '',
                'country_code' => 'BD',
                'status_id'    => 1,
            ],
            'items' => $generic_items,
        ];

        // Ecomcore handles lead matching and stock hooks triggers
        $ecomcore_order_id = $this->ecomcore_model->import_order($generic_order);

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
            'status'                => 1, // Unpaid first, then we pay it
            'allowed_payment_modes' => [$payment_method],
            'clientnote'            => 'POS Sale - Session #' . $session_id,
            'billing_street'        => '',
            'billing_city'          => '',
            'billing_state'         => '',
            'billing_zip'           => '',
            'billing_country'       => 0,
            'shipping_street'       => '',
            'shipping_city'         => '',
            'shipping_state'        => '',
            'shipping_zip'          => '',
            'shipping_country'      => 0,
        ];

        $invoice_id = $this->invoices_model->add($invoice_data);
        if (!$invoice_id) {
            throw new Exception("Failed to generate core billing invoice.");
        }

        // 5. Record Invoice Payment (Mark Paid if not pending payment)
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
        }

        // 6. Save POS Sale record
        $this->db->insert(db_prefix() . 'pos_sales', [
            'session_id'        => $session_id,
            'invoice_id'        => $invoice_id,
            'ecomcore_order_id' => $ecomcore_order_id,
            'client_id'         => $client_id,
            'cashier_staff_id'  => get_staff_user_id(),
            'created_at'        => date('Y-m-d H:i:s'),
        ]);
        $pos_sale_id = $this->db->insert_id();

        // 7. Save POS Payment record
        $this->db->insert(db_prefix() . 'pos_payments', [
            'pos_sale_id'     => $pos_sale_id,
            'session_id'      => $session_id,
            'payment_method'  => $payment_method,
            'amount'          => ($payment_method === 'pending_payment') ? 0.00 : $total,
            'transaction_ref' => $payment_ref,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

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
