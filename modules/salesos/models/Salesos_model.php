<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Salesos_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Import a generic order into Salesos tables.
     *
     * @param array $generic_order
     * @return int The created order ID
     */
    public function import_order(array $generic_order): int
    {
        // Guard against re-importing the same channel order before touching the DB —
        // the UNIQUE KEY on (channel, channel_ref_id) would also catch this, but that
        // relies on an uncaught exception path; check explicitly instead.
        $channel = $generic_order['channel'];
        $channel_ref_id = $generic_order['channel_ref_id'] ?? null;
        if ($channel_ref_id !== null) {
            $this->db->where('channel', $channel);
            $this->db->where('channel_ref_id', $channel_ref_id);
            $existing = $this->db->get(db_prefix() . 'salesos_orders')->row();
            if ($existing) {
                return (int) $existing->id;
            }
        }

        $this->db->trans_start();

        // 1. Customer matching
        $customer_data = $generic_order['customer'] ?? [];
        $phone = $customer_data['phone'] ?? '';
        $match = $this->match_or_create_customer($phone, $customer_data);

        // 2. Insert order
        $order_data = [
            'channel'         => $generic_order['channel'],
            'channel_ref_id'  => $generic_order['channel_ref_id'] ?? null,
            'lead_id'         => $match['lead_id'] ?? null,
            'client_id'       => $match['client_id'] ?? null,
            'status'          => $generic_order['status'] ?? 'pending',
            'subtotal'        => $generic_order['subtotal'] ?? 0.00,
            'shipping_charge' => $generic_order['shipping_charge'] ?? 0.00,
            'total'           => $generic_order['total'] ?? 0.00,
            'currency'        => $generic_order['currency'] ?? 'BDT',
            'payment_method'  => $generic_order['payment_method'] ?? null,
            'order_note'      => $generic_order['order_note'] ?? null,
            'order_date'      => $generic_order['order_date'] ?? date('Y-m-d H:i:s'),
        ];

        $this->db->insert(db_prefix() . 'salesos_orders', $order_data);
        $order_id = $this->db->insert_id();

        // 3. Insert order items
        $items = $generic_order['items'] ?? [];
        foreach ($items as $item) {
            $item_data = [
                'order_id'   => $order_id,
                'item_id'    => $item['item_id'] ?? null,
                'product_id' => $item['product_id'] ?? null,
                'name'       => $item['name'] ?? 'Item',
                'sku'        => $item['sku'] ?? null,
                'qty'        => $item['qty'] ?? 1.00,
                'unit_price' => $item['unit_price'] ?? 0.00,
                // Captured now, never recalculated: reading today's cost back for
                // an old order would rewrite last month's margin every time a
                // supplier changed their price.
                'unit_cost'  => $this->current_cost_price($item['product_id'] ?? null),
            ];
            $this->db->insert(db_prefix() . 'salesos_order_items', $item_data);
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            log_activity('Salesos import_order transaction failed for channel ' . $channel . ' ref ' . ($channel_ref_id ?? 'null'));
            return 0;
        }

        // Auto-convert lead to client upon order creation
        if (!empty($order_data['lead_id'])) {
            $converted_client_id = $this->convert_lead_to_customer((int) $order_data['lead_id'], [
                'company' => $customer_data['name'] ?? '',
                'address' => $customer_data['address'] ?? '',
                'city'    => $customer_data['city'] ?? '',
            ]);
            if ($converted_client_id > 0) {
                $this->db->where('id', $order_id)->update(db_prefix() . 'salesos_orders', ['client_id' => $converted_client_id]);
            }
        }

        // 4. Log event and trigger hooks — only reached on a committed transaction
        $this->log_event('order.created', 'order', $order_id, [
            'channel' => $generic_order['channel'],
            'total'   => $generic_order['total'] ?? 0.00,
        ]);

        hooks()->do_action('salesos_order_created', $order_id);

        // If order status is not pending, trigger status hook on import
        if (isset($generic_order['status']) && $generic_order['status'] !== 'pending') {
            $this->trigger_status_hooks($order_id, 'pending', $generic_order['status']);
        }

        return $order_id;
    }

    /**
     * Match a phone number to an existing client or lead, or create a new lead.
     *
     * @param string $phone
     * @param array $data
     * @return array Contains 'client_id' and 'lead_id' (one of them will be null, or both if failed)
     */
    public function match_or_create_customer(string $phone, array $data): array
    {
        $normalized = $this->normalize_phone($phone);

        if ($normalized) {
            // Check client first
            $client_id = $this->find_client_by_phone($normalized);
            if ($client_id) {
                return ['client_id' => $client_id, 'lead_id' => null];
            }

            // Check open lead
            $lead_id = $this->find_open_lead_by_phone($normalized);
            if ($lead_id) {
                return ['client_id' => null, 'lead_id' => $lead_id];
            }
        }

        // Create new lead if no match found
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            $name = 'Salesos Customer';
            if ($phone) {
                $name .= ' (' . $phone . ')';
            }
        }

        $lead_data = [
            'name'        => $name,
            'email'       => $data['email'] ?? '',
            'phonenumber' => $phone,
            'address'     => $data['address'] ?? '',
            'city'        => $data['city'] ?? '',
            'state'       => $data['state'] ?? '',
            'zip'         => $data['zip'] ?? '',
            'country'     => $data['country'] ?? $this->resolve_country_id($data['country_code'] ?? 'BD'),
            'description' => $data['description'] ?? 'Created via Salesos Order Import',
            'status'      => $data['status_id'] ?? $this->get_default_lead_status_id(),
            'source'      => $data['source_id'] ?? $this->get_or_create_source_id(),
            'addedfrom'   => 0,
            'assigned'    => 0,
        ];

        $this->load->model('leads_model');
        $new_lead_id = $this->leads_model->add($lead_data);

        return ['client_id' => null, 'lead_id' => $new_lead_id ?: null];
    }

    /**
     * Seamlessly convert a Lead to Customer (tblclients + tblcontacts).
     * Updates tblleads status to Customer (1) and links orders.
     *
     * @param int $lead_id
     * @param array $extra_data
     * @return int New or existing Client ID
     */
    public function convert_lead_to_customer(int $lead_id, array $extra_data = []): int
    {
        $this->db->where('id', $lead_id);
        $lead = $this->db->get(db_prefix() . 'leads')->row();

        if (!$lead) {
            return 0;
        }

        // Already converted?
        if (!empty($lead->client_id) && $lead->client_id > 0) {
            return (int) $lead->client_id;
        }

        // Check if an existing client matches by phone number
        $clean_phone = preg_replace('/[^0-9]/', '', $lead->phonenumber);
        $phone_suffix = substr($clean_phone, -10);

        if (!empty($phone_suffix)) {
            $existing_client = $this->db->query(
                "SELECT c.userid FROM " . db_prefix() . "clients c
                 LEFT JOIN " . db_prefix() . "contacts con ON con.userid = c.userid
                 WHERE c.phonenumber LIKE ? OR con.phonenumber LIKE ? LIMIT 1",
                ['%' . $phone_suffix, '%' . $phone_suffix]
            )->row();

            if ($existing_client) {
                $client_id = (int) $existing_client->userid;
                $this->db->where('id', $lead_id)->update(db_prefix() . 'leads', [
                    'status'         => 1, // Customer
                    'client_id'      => $client_id,
                    'date_converted' => date('Y-m-d H:i:s'),
                ]);
                $this->db->where('lead_id', $lead_id)->update(db_prefix() . 'salesos_orders', [
                    'client_id' => $client_id,
                ]);
                return $client_id;
            }
        }

        // Create new client record
        $company_name = trim($extra_data['company'] ?? ($lead->company ?: $lead->name));
        if (empty($company_name) || $company_name === 'WhatsApp Lead') {
            $company_name = ($lead->name && $lead->name !== 'WhatsApp Lead') 
                ? $lead->name 
                : 'Customer - ' . ($lead->phonenumber ?: $lead_id);
        }

        $client_data = [
            'company'      => $company_name,
            'phonenumber'  => $lead->phonenumber,
            'address'      => $extra_data['address'] ?? ($lead->address ?: ''),
            'city'         => $extra_data['city'] ?? ($lead->city ?: ''),
            'state'        => $extra_data['state'] ?? ($lead->state ?: ''),
            'zip'          => $extra_data['zip'] ?? ($lead->zip ?: ''),
            'country'      => (int) ($lead->country ?: 18), // 18 = Bangladesh
            'datecreated'  => date('Y-m-d H:i:s'),
            'active'       => 1,
            'leadid'       => $lead_id,
        ];

        $this->db->insert(db_prefix() . 'clients', $client_data);
        $client_id = $this->db->insert_id();

        if (!$client_id) {
            return 0;
        }

        // Create primary contact
        $contact_firstname = ($lead->name && $lead->name !== 'WhatsApp Lead') 
            ? $lead->name 
            : $company_name;

        $this->db->insert(db_prefix() . 'contacts', [
            'userid'      => $client_id,
            'is_primary'  => 1,
            'firstname'   => $contact_firstname,
            'lastname'    => '',
            'email'       => $lead->email ?: '',
            'phonenumber' => $lead->phonenumber,
            'title'       => $lead->title ?: '',
            'datecreated' => date('Y-m-d H:i:s'),
            'active'      => 1,
        ]);

        // Update lead status to Customer (status = 1)
        $this->db->where('id', $lead_id)->update(db_prefix() . 'leads', [
            'status'         => 1,
            'client_id'      => $client_id,
            'date_converted' => date('Y-m-d H:i:s'),
        ]);

        // Update all existing salesos orders for this lead
        $this->db->where('lead_id', $lead_id)->update(db_prefix() . 'salesos_orders', [
            'client_id' => $client_id,
        ]);

        $this->load->model('leads_model');
        $this->leads_model->log_lead_activity($lead_id, 'Lead converted to customer automatically on purchase', true);

        hooks()->do_action('lead_converted_to_customer', ['lead_id' => $lead_id, 'customer_id' => $client_id]);

        return $client_id;
    }

    /**
     * Set/Update status of an order and trigger corresponding events.
     *
     * @param int $order_id
     * @param string $status
     * @return bool
     */
    public function set_order_status(int $order_id, string $status): bool
    {
        $this->db->where('id', $order_id);
        $order = $this->db->get(db_prefix() . 'salesos_orders')->row();

        if (!$order) {
            return false;
        }

        $old_status = $order->status;
        if ($old_status === $status) {
            return true;
        }

        $this->db->where('id', $order_id);
        $updated = $this->db->update(db_prefix() . 'salesos_orders', [
            'status'     => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$updated) {
            log_activity('Salesos set_order_status failed to update order #' . $order_id);
            return false;
        }

        $this->log_event('order.status_changed', 'order', $order_id, [
            'old_status' => $old_status,
            'new_status' => $status,
        ]);

        $this->trigger_status_hooks($order_id, $old_status, $status);

        return true;
    }

    /**
     * Helper to trigger actions based on status change.
     */
    private function trigger_status_hooks(int $order_id, string $old_status, string $new_status)
    {
        if ($new_status === 'confirmed') {
            $this->log_event('order.confirmed', 'order', $order_id);
            hooks()->do_action('salesos_order_confirmed', $order_id);
        } elseif ($new_status === 'cancelled') {
            $this->log_event('order.cancelled', 'order', $order_id);
            hooks()->do_action('salesos_order_cancelled', $order_id);
        } elseif ($new_status === 'returned') {
            $this->log_event('order.returned', 'order', $order_id);
            hooks()->do_action('salesos_stock_returned', $order_id);
        }
    }

    /**
     * Log an event in tblsalesos_events
     *
     * @param string $event_type
     * @param string $entity_type
     * @param int $entity_id
     * @param array $payload
     * @return void
     */
    public function log_event(string $event_type, string $entity_type, int $entity_id, array $payload = []): void
    {
        $this->db->insert(db_prefix() . 'salesos_events', [
            'event_type'  => $event_type,
            'entity_type' => $entity_type,
            'entity_id'   => $entity_id,
            'payload'     => json_encode($payload),
        ]);
    }

    // ── Margin ────────────────────────────────────────────────────────────────

    /** What a product costs us right now, or null if we have never paid for it. */
    private function current_cost_price($product_id): ?float
    {
        if (empty($product_id) || !$this->db->table_exists(db_prefix() . 'inventory_products')) {
            return null;
        }

        $product = $this->db->select('cost_price')->where('id', (int) $product_id)
            ->get(db_prefix() . 'inventory_products')->row();

        return ($product && $product->cost_price !== null) ? (float) $product->cost_price : null;
    }

    /**
     * Revenue, cost and profit over a date range, from the cost captured on each
     * line at the time it sold.
     *
     * Lines with no cost recorded are counted separately rather than treated as
     * free: a margin that silently includes them reads far better than the
     * business actually did, which is the opposite of useful.
     *
     * @return array{revenue: float, cost: float, profit: float, margin: float, lines_without_cost: int}
     */
    public function get_margin_summary(string $from, string $to, array $statuses = ['confirmed', 'delivered']): array
    {
        $prefix = db_prefix();

        if (!$this->db->field_exists('unit_cost', $prefix . 'salesos_order_items')) {
            return ['revenue' => 0.0, 'cost' => 0.0, 'profit' => 0.0, 'margin' => 0.0, 'lines_without_cost' => 0];
        }

        $row = $this->db->query(
            "SELECT
                COALESCE(SUM(i.qty * i.unit_price), 0) AS revenue,
                COALESCE(SUM(CASE WHEN i.unit_cost IS NOT NULL THEN i.qty * i.unit_cost END), 0) AS cost,
                COALESCE(SUM(CASE WHEN i.unit_cost IS NOT NULL THEN i.qty * i.unit_price END), 0) AS costed_revenue,
                SUM(CASE WHEN i.unit_cost IS NULL THEN 1 ELSE 0 END) AS lines_without_cost
             FROM {$prefix}salesos_order_items i
             JOIN {$prefix}salesos_orders o ON o.id = i.order_id
             WHERE o.status IN ('" . implode("','", array_map('addslashes', $statuses)) . "')
               AND o.order_date BETWEEN ? AND ?",
            [$from . ' 00:00:00', $to . ' 23:59:59']
        )->row();

        $revenue = (float) $row->revenue;
        $cost    = (float) $row->cost;
        // Margin is measured only over the lines we actually know the cost of.
        $costed  = (float) $row->costed_revenue;
        $profit  = $costed - $cost;

        return [
            'revenue'            => $revenue,
            'cost'               => $cost,
            'profit'             => $profit,
            'margin'             => $costed > 0 ? round($profit / $costed * 100, 1) : 0.0,
            'lines_without_cost' => (int) $row->lines_without_cost,
        ];
    }

    /**
     * Which products made the most money over a range — the answer to "what
     * should I order more of".
     */
    public function get_product_margins(string $from, string $to, int $limit = 10): array
    {
        $prefix = db_prefix();

        if (!$this->db->field_exists('unit_cost', $prefix . 'salesos_order_items')) {
            return [];
        }

        return $this->db->query(
            "SELECT
                i.product_id, i.name, i.sku,
                SUM(i.qty) AS qty_sold,
                SUM(i.qty * i.unit_price) AS revenue,
                SUM(CASE WHEN i.unit_cost IS NOT NULL THEN i.qty * i.unit_cost END) AS cost,
                SUM(CASE WHEN i.unit_cost IS NULL THEN 1 ELSE 0 END) AS lines_without_cost
             FROM {$prefix}salesos_order_items i
             JOIN {$prefix}salesos_orders o ON o.id = i.order_id
             WHERE o.status IN ('confirmed','delivered')
               AND o.order_date BETWEEN ? AND ?
             GROUP BY i.product_id, i.name, i.sku
             ORDER BY revenue DESC
             LIMIT ?",
            [$from . ' 00:00:00', $to . ' 23:59:59', $limit]
        )->result_array();
    }

    // ── Channel sites ─────────────────────────────────────────────────────────

    /** @return array every connected storefront, newest first, optionally one platform */
    public function get_channel_sites(?string $platform = null, bool $active_only = false): array
    {
        if ($platform !== null) {
            $this->db->where('platform', $platform);
        }
        if ($active_only) {
            $this->db->where('is_active', 1);
        }

        $rows = $this->db->order_by('id', 'desc')
            ->get(db_prefix() . 'salesos_channel_sites')
            ->result_array();

        foreach ($rows as &$row) {
            $row['settings'] = $row['settings'] ? (json_decode($row['settings'], true) ?: []) : [];
        }

        return $rows;
    }

    public function get_channel_site(int $id): ?array
    {
        $row = $this->db->where('id', $id)
            ->get(db_prefix() . 'salesos_channel_sites')
            ->row_array();

        if (!$row) {
            return null;
        }
        $row['settings'] = $row['settings'] ? (json_decode($row['settings'], true) ?: []) : [];

        return $row;
    }

    /** @return int the site id, 0 on failure */
    public function save_channel_site(array $data, ?int $id = null): int
    {
        $row = [
            'platform'               => trim($data['platform']),
            'name'                   => trim($data['name']),
            'site_url'               => rtrim(trim($data['site_url']), '/'),
            'credential_id'          => !empty($data['credential_id']) ? (int) $data['credential_id'] : null,
            'default_lead_status_id' => !empty($data['default_lead_status_id']) ? (int) $data['default_lead_status_id'] : null,
            'settings'               => json_encode($data['settings'] ?? []),
            'is_active'              => isset($data['is_active']) ? (int) $data['is_active'] : 1,
        ];

        if ($id) {
            $this->db->where('id', $id)->update(db_prefix() . 'salesos_channel_sites', $row);

            return $id;
        }

        $row['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'salesos_channel_sites', $row);

        return (int) $this->db->insert_id();
    }

    public function delete_channel_site(int $id): bool
    {
        // Orders stay: they are kernel-owned history, and losing them because a
        // storefront was disconnected would take the stock and courier records
        // attached to them out of context. They simply stop being re-synced.
        $this->db->where('channel_site_id', $id)
            ->update(db_prefix() . 'salesos_orders', ['channel_site_id' => null]);

        return $this->db->where('id', $id)->delete(db_prefix() . 'salesos_channel_sites');
    }

    public function touch_channel_site(int $id): void
    {
        $this->db->where('id', $id)
            ->update(db_prefix() . 'salesos_channel_sites', ['last_synced_at' => date('Y-m-d H:i:s')]);
    }

    // ── Confirmation queue ────────────────────────────────────────────────────

    /**
     * Orders waiting on a confirmation call, oldest first — the oldest order is
     * the one a customer has been waiting on longest, so it is called first.
     *
     * Carries the customer's phone and, when fraudcheck is installed, the risk
     * it already knows about that number: the agent needs both on the row they
     * are about to dial, not a click away.
     */
    public function get_confirmation_queue(int $limit = 100): array
    {
        $prefix = db_prefix();

        $sql = "SELECT o.id, o.channel, o.channel_ref_id, o.status AS channel_status, o.total, o.currency,
                       o.payment_method, o.order_note, o.order_date, o.created_at,
                       COALESCE(s.name, o.channel) AS site_name,
                       COALESCE(NULLIF(TRIM(CONCAT(con.firstname,' ',con.lastname)),''), c.company, l.name, 'Guest') AS customer_name,
                       COALESCE(con.phonenumber, c.phonenumber, l.phonenumber, '') AS customer_phone,
                       COALESCE(c.address, l.address, '') AS customer_address
                FROM {$prefix}salesos_orders o
                LEFT JOIN {$prefix}salesos_channel_sites s ON s.id = o.channel_site_id
                LEFT JOIN {$prefix}contacts con ON con.userid = o.client_id AND con.is_primary = 1
                LEFT JOIN {$prefix}clients c ON c.userid = o.client_id
                LEFT JOIN {$prefix}leads l ON l.id = o.lead_id
                WHERE o.status = 'pending'
                ORDER BY o.order_date ASC, o.id ASC
                LIMIT ?";

        $orders = $this->db->query($sql, [$limit])->result_array();

        foreach ($orders as &$order) {
            $order['items'] = $this->get_order_items((int) $order['id']);
        }
        unset($order);

        return $this->attach_fraud_history($orders);
    }

    /**
     * Add each order's COD delivery history from fraudcheck, if it is installed.
     *
     * Deliberately a second query rather than a join: fraudcheck stores the phone
     * in its own normalised form, and matching it in SQL means comparing a
     * computed expression against a column whose collation need not match — which
     * fails outright on some installs. One batched lookup is portable and still
     * costs a single query however long the queue is.
     */
    private function attach_fraud_history(array $orders): array
    {
        $prefix = db_prefix();

        foreach ($orders as &$order) {
            $order['fraud_ratio'] = null;
            $order['fraud_risk']  = null;
            $order['fraud_color'] = null;
        }
        unset($order);

        $CI = &get_instance();
        if (!$orders
            || !$this->db->table_exists($prefix . 'fraudcheck_lookups')
            || !$CI->app_modules->is_active('fraudcheck')) {
            return $orders;
        }

        $CI->load->model('fraudcheck/fraudcheck_model');

        $phones = [];
        foreach ($orders as &$order) {
            $order['phone_normalised'] = $order['customer_phone'] !== ''
                ? $CI->fraudcheck_model->normalize_phone($order['customer_phone'])
                : '';
            if ($order['phone_normalised'] !== '') {
                $phones[] = $order['phone_normalised'];
            }
        }
        unset($order);

        if (!$phones) {
            return $orders;
        }

        $lookups = [];
        foreach ($this->db->select('phone, success_ratio, risk_level, risk_color')
                     ->where_in('phone', array_unique($phones))
                     ->get($prefix . 'fraudcheck_lookups')
                     ->result_array() as $row) {
            $lookups[$row['phone']] = $row;
        }

        foreach ($orders as &$order) {
            $hit = $lookups[$order['phone_normalised']] ?? null;
            if ($hit) {
                $order['fraud_ratio'] = $hit['success_ratio'];
                $order['fraud_risk']  = $hit['risk_level'];
                $order['fraud_color'] = $hit['risk_color'];
            }
        }
        unset($order);

        return $orders;
    }

    public function count_confirmation_queue(): int
    {
        return (int) $this->db->where('status', 'pending')
            ->count_all_results(db_prefix() . 'salesos_orders');
    }

    /**
     * Record the outcome of a confirmation call.
     *
     * Confirming is what releases the order to the rest of the system: the
     * kernel's own status change fires the events stock, courier and
     * notifications are already listening for, so nothing extra is wired here.
     *
     * @param string $outcome confirmed|cancelled
     */
    public function record_confirmation(int $order_id, string $outcome, string $note = ''): bool
    {
        $valid_outcomes = ['confirmed', 'cancelled', 'no_answer', 'call_later'];
        if (!in_array($outcome, $valid_outcomes, true)) {
            return false;
        }

        $order = $this->get_order($order_id);
        if (!$order || $order['status'] !== 'pending') {
            return false;
        }

        $this->log_event('order.call_' . $outcome, 'order', $order_id, [
            'staff_id' => get_staff_user_id() ?: null,
            'note'     => $note,
        ]);

        $log_note = $note;
        if ($log_note === '') {
            if ($outcome === 'no_answer') $log_note = 'ফোন ধরেনি / No Answer';
            elseif ($outcome === 'call_later') $log_note = 'পরে কল দিতে বলেছেন / Call Later';
        }

        if ($log_note !== '') {
            $prefix = ($outcome === 'confirmed') ? '[Confirmed]' : (($outcome === 'cancelled') ? '[Cancelled]' : '[Attempt]');
            $this->db->where('id', $order_id)->update(db_prefix() . 'salesos_orders', [
                'order_note' => trim(($order['order_note'] ?? '') . "\n" . $prefix . " (" . date('d M, h:i A') . ") " . $log_note),
            ]);
        }

        // For non-closing outcomes (no_answer, call_later), order remains pending in queue
        if ($outcome === 'no_answer' || $outcome === 'call_later') {
            return true;
        }

        return $this->set_order_status($order_id, $outcome);
    }

    /**
     * Confirmation-call activity per day, and per agent, for a date range —
     * what a telesales team is measured on.
     */
    public function get_confirmation_stats(string $from, string $to): array
    {
        $prefix = db_prefix();

        $rows = $this->db->query(
            "SELECT DATE(created_at) AS day, event_type, payload
             FROM {$prefix}salesos_events
             WHERE event_type IN ('order.call_confirmed','order.call_cancelled')
               AND created_at BETWEEN ? AND ?
             ORDER BY created_at ASC",
            [$from . ' 00:00:00', $to . ' 23:59:59']
        )->result_array();

        $daily  = [];
        $agents = [];

        foreach ($rows as $row) {
            $day     = $row['day'];
            $outcome = $row['event_type'] === 'order.call_confirmed' ? 'confirmed' : 'cancelled';

            $daily[$day] = $daily[$day] ?? ['day' => $day, 'confirmed' => 0, 'cancelled' => 0];
            $daily[$day][$outcome]++;

            $staff_id = json_decode($row['payload'] ?? '', true)['staff_id'] ?? null;
            if ($staff_id) {
                $agents[$staff_id] = $agents[$staff_id] ?? ['staff_id' => $staff_id, 'confirmed' => 0, 'cancelled' => 0];
                $agents[$staff_id][$outcome]++;
            }
        }

        foreach ($agents as $id => &$agent) {
            $staff = $this->db->select('firstname, lastname')->where('staffid', $id)
                ->get($prefix . 'staff')->row();
            $agent['name'] = $staff ? trim($staff->firstname . ' ' . $staff->lastname) : ('Staff #' . $id);
            $total = $agent['confirmed'] + $agent['cancelled'];
            $agent['rate'] = $total > 0 ? round($agent['confirmed'] / $total * 100) : 0;
        }
        unset($agent);

        return ['daily' => array_values($daily), 'agents' => array_values($agents)];
    }

    // ── Catalogue matching ────────────────────────────────────────────────────

    /**
     * Find, or create, the inventory product a channel line item refers to.
     *
     * This is generic commerce logic with nothing platform-specific in it, which
     * is why it lives here rather than in a connector: matching by SKU, falling
     * back to name, auto-creating the category, and creating the billing item so
     * the product has a price. A connector's job is only to hand over the
     * generic shape below.
     *
     * @param array $product {sku, name, category_name, image_url, price}
     * @return int|null product id, or null when there is nothing to match on
     */
    public function match_or_create_product(array $product): ?int
    {
        $sku  = trim((string) ($product['sku'] ?? ''));
        $name = trim((string) ($product['name'] ?? ''));

        if ($sku === '' && $name === '') {
            return null;
        }

        $has_inv = $this->db->table_exists(db_prefix() . 'inventory_products');
        if (!$has_inv) {
            // Inventory module is not installed. Match or create core item in tblitems.
            $existing_item = null;
            if ($name !== '') {
                $existing_item = $this->db->where('description', $name)->get(db_prefix() . 'items')->row();
            }
            if ($existing_item) {
                return (int) $existing_item->id;
            }
            $final_sku  = $sku !== '' ? $sku : 'CH-' . substr(md5($name), 0, 8);
            $final_name = $name !== '' ? $name : 'Channel product ' . $final_sku;
            $this->db->insert(db_prefix() . 'items', [
                'description'      => $final_name,
                'long_description' => 'SKU: ' . $final_sku,
                'rate'             => (float) ($product['price'] ?? 0.00),
                'unit'             => '',
                'group_id'         => 0,
            ]);
            return (int) $this->db->insert_id();
        }

        $existing = null;
        if ($sku !== '') {
            $existing = $this->db->where('sku', $sku)->get(db_prefix() . 'inventory_products')->row();
        }
        if (!$existing && $name !== '') {
            $existing = $this->db->where('name', $name)->get(db_prefix() . 'inventory_products')->row();
        }

        if ($existing) {
            // Backfill an image the first time the channel gives us one.
            if (empty($existing->image) && !empty($product['image_url'])) {
                $this->db->where('id', $existing->id)
                    ->update(db_prefix() . 'inventory_products', ['image' => $product['image_url']]);
            }

            return (int) $existing->id;
        }

        $category_id = $this->match_or_create_category($product['category_name'] ?? '');
        $price       = (float) ($product['price'] ?? 0.00);
        $final_sku   = $sku !== '' ? $sku : 'CH-' . substr(md5($name), 0, 8);
        $final_name  = $name !== '' ? $name : 'Channel product ' . $final_sku;

        // The billing item is what carries the price into invoices and the POS.
        $this->db->insert(db_prefix() . 'items', [
            'description'      => $final_name,
            'long_description' => 'SKU: ' . $final_sku,
            'rate'             => $price,
            'unit'             => '',
            'group_id'         => 0,
        ]);
        $item_id = $this->db->insert_id();

        $this->db->insert(db_prefix() . 'inventory_products', [
            'item_id'       => $item_id,
            'sku'           => $final_sku,
            'name'          => $final_name,
            'category_id'   => $category_id,
            'image'         => $product['image_url'] ?? null,
            'reorder_level' => 0.00,
            'is_active'     => 1,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insert_id();
    }

    private function match_or_create_category(string $name): ?int
    {
        $name = trim($name);
        if ($name === '' || !$this->db->table_exists(db_prefix() . 'inventory_categories')) {
            return null;
        }

        $existing = $this->db->where('name', $name)->get(db_prefix() . 'inventory_categories')->row();
        if ($existing) {
            return (int) $existing->id;
        }

        $this->db->insert(db_prefix() . 'inventory_categories', ['name' => $name, 'parent_id' => null]);

        return (int) $this->db->insert_id();
    }

    // ── Read API for add-on modules ───────────────────────────────────────────
    // Add-ons read kernel-owned data through these rather than querying
    // tblsalesos_* directly, so a change to this schema is a change in one
    // place instead of eight. See HOOKS.md for the write/event side.

    /** @return array|null the order row, or null if there is no such order */
    public function get_order(int $order_id): ?array
    {
        $order = $this->db->where('id', $order_id)
            ->get(db_prefix() . 'salesos_orders')
            ->row_array();

        return $order ?: null;
    }

    /** @return array the order's line items, empty if the order has none */
    public function get_order_items(int $order_id): array
    {
        return $this->db->where('order_id', $order_id)
            ->get(db_prefix() . 'salesos_order_items')
            ->result_array();
    }

    /**
     * Fetch a credential and decrypt its payload, but only for the module that
     * owns it. Passing the expected owner is what stops one add-on reading
     * another's secrets by id — a posted credential_id is attacker-controlled,
     * so the ownership check belongs here rather than in each caller.
     *
     * @return array|null the row with `payload` decrypted, or null if it does
     *                    not exist, is inactive, or belongs to another module
     */
    public function get_credential(int $credential_id, string $expected_owner_module): ?array
    {
        $cred = $this->db->where('id', $credential_id)
            ->get(db_prefix() . 'salesos_credentials')
            ->row_array();

        if (!$cred) {
            return null;
        }

        if ($cred['owner_module'] !== $expected_owner_module) {
            log_activity("Salesos: {$expected_owner_module} asked for credential #{$credential_id}, which belongs to {$cred['owner_module']} — refused");

            return null;
        }

        $this->load->library('salesos/salesos_encryption');
        $cred['payload'] = $this->salesos_encryption->decrypt($cred['payload'], true);

        return $cred;
    }

    /**
     * The active credential a module holds for a given type, for callers that
     * configure one credential per purpose rather than referencing one by id.
     *
     * @return array|null the row with `payload` decrypted, or null if the
     *                    module has no active credential of that type
     */
    public function get_active_credential(string $owner_module, string $cred_type): ?array
    {
        $cred = $this->db->where('owner_module', $owner_module)
            ->where('cred_type', $cred_type)
            ->where('is_active', 1)
            ->get(db_prefix() . 'salesos_credentials')
            ->row_array();

        if (!$cred) {
            return null;
        }

        $this->load->library('salesos/salesos_encryption');
        $cred['payload'] = $this->salesos_encryption->decrypt($cred['payload'], true);

        return $cred;
    }

    /**
     * Recent orders in the given statuses, for add-ons that offer an order
     * picker. Returns summary columns only — a caller that needs the whole row
     * asks for it by id.
     */
    public function list_recent_orders(array $statuses, int $limit = 100): array
    {
        return $this->db->select('id, channel, channel_ref_id, total, created_at')
            ->where_in('status', $statuses)
            ->order_by('created_at', 'DESC')
            ->limit($limit)
            ->get(db_prefix() . 'salesos_orders')
            ->result_array();
    }

    /**
     * A module's own credentials for picking one in a UI. Deliberately never
     * returns `payload`: a select box needs the label, not the secret, and
     * encrypted payloads have no business reaching a view.
     */
    public function list_credentials(string $owner_module, bool $active_only = true): array
    {
        $this->db->select('id, owner_module, label, cred_type, is_active, last_used_at')
            ->where('owner_module', $owner_module);

        if ($active_only) {
            $this->db->where('is_active', 1);
        }

        return $this->db->get(db_prefix() . 'salesos_credentials')->result_array();
    }

    // ── Phone matching helpers ────────────────────────────────────────────────

    private function normalize_phone($phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        return $digits !== '' ? substr($digits, -10) : '';
    }

    private function find_client_by_phone(string $phone_suffix)
    {
        // Indexed equality on the generated phone_suffix10 column (see install.php) —
        // not a function-wrapped WHERE, so this can actually use an index as the
        // contacts table grows.
        $this->db->select('userid');
        $this->db->where('phone_suffix10', $phone_suffix);
        $row = $this->db->get(db_prefix() . 'contacts')->row();
        return $row ? (int) $row->userid : null;
    }

    private function find_open_lead_by_phone(string $phone_suffix)
    {
        $this->db->select('id');
        $this->db->where('lost', 0);
        $this->db->where('junk', 0);
        $this->db->where('phone_suffix10', $phone_suffix);
        $row = $this->db->get(db_prefix() . 'leads')->row();
        return $row ? (int) $row->id : null;
    }

    private function get_default_lead_status_id(): int
    {
        $default_status = $this->db->where('isdefault', 1)->get(db_prefix() . 'leads_status')->row();
        return $default_status ? (int) $default_status->id : 1;
    }

    private function get_or_create_source_id(): int
    {
        $this->db->where('name', 'Salesos');
        $source = $this->db->get(db_prefix() . 'leads_sources')->row();
        if ($source) {
            return (int) $source->id;
        }
        $this->db->insert(db_prefix() . 'leads_sources', ['name' => 'Salesos']);
        return (int) $this->db->insert_id();
    }

    private function resolve_country_id(string $iso2): int
    {
        if ($iso2 !== '') {
            $this->db->where('iso2', $iso2);
            $row = $this->db->get(db_prefix() . 'countries')->row();
            if ($row) {
                return (int) $row->country_id;
            }
        }
        // Default to BD (Bangladesh) country ID
        $this->db->where('iso2', 'BD');
        $row = $this->db->get(db_prefix() . 'countries')->row();
        return $row ? (int) $row->country_id : 0;
    }

    /**
     * Get aggregated e-commerce metrics and feeds for Perfex CRM Dashboard Widgets.
     * Uses static caching so multiple widgets on the same request do not repeat queries.
     *
     * @return array
     */
    public function get_dashboard_widget_data(): array
    {
        static $cached_data = null;
        if ($cached_data !== null) {
            return $cached_data;
        }

        $db_prefix = db_prefix();
        $data = [];

        // Safety fallback if database tables do not exist
        if (!$this->db->table_exists($db_prefix . 'salesos_orders')) {
            return [
                'total_orders'         => 0,
                'total_sales'          => 0.00,
                'total_due'            => 0.00,
                'total_revenue'        => 0.00,
                'today_orders'         => 0,
                'today_sales'          => 0.00,
                'month_orders'         => 0,
                'month_sales'          => 0.00,
                'pending_count'        => 0,
                'confirmed_count'      => 0,
                'processing_count'     => 0,
                'ready_count'          => 0,
                'delivered_count'      => 0,
                'cancelled_count'      => 0,
                'courier_booked_count' => 0,
                'collectable_cod'      => 0.00,
                'high_risk_count'      => 0,
                'channels'             => [],
                'chart_data'           => ['labels' => [], 'pos' => [], 'woo' => [], 'manual' => []],
                'urgent_risk_orders'   => [],
                'low_stock_products'   => [],
                'integration_health'   => ['woocommerce' => false, 'courier' => false, 'fraudcheck' => false],
            ];
        }

        // 1. Cumulative High-Level Stats
        $this->db->where('channel !=', 'test_channel');
        $data['total_orders'] = $this->db->count_all_results($db_prefix . 'salesos_orders');

        $this->db->select_sum('total');
        $this->db->where('status', 'confirmed');
        $this->db->where('channel !=', 'test_channel');
        $sales_row = $this->db->get($db_prefix . 'salesos_orders')->row();
        $total_sales = $sales_row ? (float) $sales_row->total : 0.00;

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

        // 4. Courier Consignments & Collectable COD
        $courier_active = $this->app_modules->is_active('courier');
        $booked_count = 0;
        if ($courier_active && $this->db->table_exists($db_prefix . 'courier_consignments')) {
            $b_sql = "SELECT COUNT(DISTINCT salesos_order_id) as c FROM {$db_prefix}courier_consignments";
            $booked_res = $this->db->query($b_sql)->row();
            $booked_count = $booked_res ? (int) $booked_res->c : 0;
        }
        $data['courier_booked_count'] = $booked_count;

        $pos_active = $this->app_modules->is_active('pos') && $this->db->table_exists($db_prefix . 'pos_sales');
        if ($pos_active) {
            $cod_sql = "
                SELECT COALESCE(SUM(
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
                        ELSE 0.00
                    END
                ), 0) as cod_receivable
                FROM {$db_prefix}salesos_orders o
                LEFT JOIN {$db_prefix}pos_sales ps ON ps.salesos_order_id = o.id
                LEFT JOIN {$db_prefix}invoices inv ON inv.id = ps.invoice_id
                WHERE o.status IN ('confirmed', 'processing')
                  AND o.channel != 'test_channel'
            ";
        } else {
            $cod_sql = "
                SELECT COALESCE(SUM(
                    CASE 
                        WHEN o.payment_method IN ('cod', 'pending_payment') THEN o.total
                        ELSE 0.00
                    END
                ), 0) as cod_receivable
                FROM {$db_prefix}salesos_orders o
                WHERE o.status IN ('confirmed', 'processing')
                  AND o.channel != 'test_channel'
            ";
        }
        $cod_res = $this->db->query($cod_sql)->row();
        $data['collectable_cod'] = $cod_res ? (float) $cod_res->cod_receivable : 0.00;

        // 5. High Risk Orders Count
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

        // 6. Omni-Channel Breakdown
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
        $total_rev_sum = max(1, $total_sales);
        foreach ($channels_raw as $ch) {
            $ch['share_percent'] = round(((float)$ch['confirmed_revenue'] / $total_rev_sum) * 100, 1);
            $channels[] = $ch;
        }
        $data['channels'] = $channels;

        // 7. Last 7 Days Daily Sales Trend
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

        // 8. Top Urgent Risk Orders (3 items)
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

        // 9. Low Stock / Zero Stock Products (Top 4 items)
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

        // 10. Integration Health Statuses
        $has_woo = false;
        $has_courier = false;
        $has_fraud = false;
        if ($this->db->table_exists($db_prefix . 'salesos_credentials')) {
            $creds_sql = "
                SELECT owner_module, label, is_active 
                FROM {$db_prefix}salesos_credentials
                WHERE is_active = 1
            ";
            $active_creds = $this->db->query($creds_sql)->result_array();
            foreach ($active_creds as $cr) {
                if ($cr['owner_module'] === 'wcsync') $has_woo = true;
                if ($cr['owner_module'] === 'courier') $has_courier = true;
                if ($cr['owner_module'] === 'fraudcheck') $has_fraud = true;
            }
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

        // 11. Recent 8 Orders
        $recent_sql = "
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
            LIMIT 8
        ";
        $data['recent_orders'] = $this->db->query($recent_sql)->result_array();

        $cached_data = $data;
        return $cached_data;
    }
}
