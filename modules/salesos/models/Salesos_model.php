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
            ];
            $this->db->insert(db_prefix() . 'salesos_order_items', $item_data);
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            log_activity('Salesos import_order transaction failed for channel ' . $channel . ' ref ' . ($channel_ref_id ?? 'null'));
            return 0;
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

        $sql = "SELECT o.id, o.channel, o.channel_ref_id, o.channel_status, o.total, o.currency,
                       o.payment_method, o.order_note, o.order_date, o.created_at,
                       s.name AS site_name,
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
        if (!in_array($outcome, ['confirmed', 'cancelled'], true)) {
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

        if ($note !== '') {
            $this->db->where('id', $order_id)->update(db_prefix() . 'salesos_orders', [
                'order_note' => trim(($order['order_note'] ?? '') . "\n[call] " . $note),
            ]);
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
        if ($name === '') {
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
}
