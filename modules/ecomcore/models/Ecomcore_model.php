<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Ecomcore_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Import a generic order into Ecomcore tables.
     *
     * @param array $generic_order
     * @return int The created order ID
     */
    public function import_order(array $generic_order): int
    {
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

        $this->db->insert(db_prefix() . 'ecomcore_orders', $order_data);
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
            $this->db->insert(db_prefix() . 'ecomcore_order_items', $item_data);
        }

        $this->db->trans_complete();

        // 4. Log event and trigger hooks
        $this->log_event('order.created', 'order', $order_id, [
            'channel' => $generic_order['channel'],
            'total'   => $generic_order['total'] ?? 0.00,
        ]);

        hooks()->do_action('ecomcore_order_created', $order_id);

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
            $name = 'Ecomcore Customer';
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
            'description' => $data['description'] ?? 'Created via Ecomcore Order Import',
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
        $order = $this->db->get(db_prefix() . 'ecomcore_orders')->row();

        if (!$order) {
            return false;
        }

        $old_status = $order->status;
        if ($old_status === $status) {
            return true;
        }

        $this->db->where('id', $order_id);
        $this->db->update(db_prefix() . 'ecomcore_orders', [
            'status'     => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

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
            hooks()->do_action('ecomcore_order_confirmed', $order_id);
        } elseif ($new_status === 'cancelled') {
            $this->log_event('order.cancelled', 'order', $order_id);
            hooks()->do_action('ecomcore_order_cancelled', $order_id);
        } elseif ($new_status === 'returned') {
            $this->log_event('order.returned', 'order', $order_id);
            hooks()->do_action('ecomcore_stock_returned', $order_id);
        }
    }

    /**
     * Log an event in tblecomcore_events
     *
     * @param string $event_type
     * @param string $entity_type
     * @param int $entity_id
     * @param array $payload
     * @return void
     */
    public function log_event(string $event_type, string $entity_type, int $entity_id, array $payload = []): void
    {
        $this->db->insert(db_prefix() . 'ecomcore_events', [
            'event_type'  => $event_type,
            'entity_type' => $entity_type,
            'entity_id'   => $entity_id,
            'payload'     => json_encode($payload),
        ]);
    }

    // ── Phone matching helpers ────────────────────────────────────────────────

    private function normalize_phone($phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        return $digits !== '' ? substr($digits, -10) : '';
    }

    private function phone_suffix_sql($column): string
    {
        return "RIGHT(REPLACE(REPLACE(REPLACE({$column}, '+', ''), ' ', ''), '-', ''), 10)";
    }

    private function find_client_by_phone(string $phone_suffix)
    {
        $sql = 'SELECT userid FROM ' . db_prefix() . 'contacts WHERE ' . $this->phone_suffix_sql('phonenumber') . ' = ? LIMIT 1';
        $row = $this->db->query($sql, [$phone_suffix])->row();
        return $row ? (int) $row->userid : null;
    }

    private function find_open_lead_by_phone(string $phone_suffix)
    {
        $sql = 'SELECT id FROM ' . db_prefix() . 'leads WHERE lost = 0 AND junk = 0 AND ' . $this->phone_suffix_sql('phonenumber') . ' = ? LIMIT 1';
        $row = $this->db->query($sql, [$phone_suffix])->row();
        return $row ? (int) $row->id : null;
    }

    private function get_default_lead_status_id(): int
    {
        $default_status = $this->db->where('isdefault', 1)->get(db_prefix() . 'leads_status')->row();
        return $default_status ? (int) $default_status->id : 1;
    }

    private function get_or_create_source_id(): int
    {
        $this->db->where('name', 'Ecomcore');
        $source = $this->db->get(db_prefix() . 'leads_sources')->row();
        if ($source) {
            return (int) $source->id;
        }
        $this->db->insert(db_prefix() . 'leads_sources', ['name' => 'Ecomcore']);
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
