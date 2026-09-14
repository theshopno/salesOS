<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Purchases_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('inventory/inventory_model');
    }

    // ── Suppliers CRUD ────────────────────────────────────────────────────────

    public function get_suppliers(): array
    {
        return $this->db->get(db_prefix() . 'purchases_suppliers')->result_array();
    }

    public function get_supplier(int $id)
    {
        $this->db->where('id', $id);
        return $this->db->get(db_prefix() . 'purchases_suppliers')->row_array();
    }

    public function add_supplier(array $data): int
    {
        $db_data = [
            'name'            => trim($data['name']),
            'phone'           => !empty($data['phone']) ? trim($data['phone']) : null,
            'email'           => !empty($data['email']) ? trim($data['email']) : null,
            'address'         => !empty($data['address']) ? trim($data['address']) : null,
            'opening_balance' => !empty($data['opening_balance']) ? (float) $data['opening_balance'] : 0.00,
            'created_at'      => date('Y-m-d H:i:s'),
        ];
        $this->db->insert(db_prefix() . 'purchases_suppliers', $db_data);
        return $this->db->insert_id();
    }

    public function update_supplier(int $id, array $data): bool
    {
        $db_data = [
            'name'            => trim($data['name']),
            'phone'           => !empty($data['phone']) ? trim($data['phone']) : null,
            'email'           => !empty($data['email']) ? trim($data['email']) : null,
            'address'         => !empty($data['address']) ? trim($data['address']) : null,
            'opening_balance' => !empty($data['opening_balance']) ? (float) $data['opening_balance'] : 0.00,
        ];
        $this->db->where('id', $id);
        return $this->db->update(db_prefix() . 'purchases_suppliers', $db_data);
    }

    public function delete_supplier(int $id): bool
    {
        $this->db->trans_start();
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'purchases_suppliers');

        $this->db->where('supplier_id', $id);
        $this->db->delete(db_prefix() . 'purchases_supplier_ledger');
        $this->db->trans_complete();
        return $this->db->trans_status();
    }

    // ── Purchase Orders CRUD ──────────────────────────────────────────────────

    public function get_purchase_orders(): array
    {
        $this->db->select('po.*, s.name as supplier_name, st.firstname, st.lastname');
        $this->db->from(db_prefix() . 'purchases_orders po');
        $this->db->join(db_prefix() . 'purchases_suppliers s', 's.id = po.supplier_id');
        $this->db->join(db_prefix() . 'staff st', 'st.staffid = po.staff_id', 'left');
        $this->db->order_by('po.created_at', 'desc');
        return $this->db->get()->result_array();
    }

    public function get_purchase_order(int $id)
    {
        $this->db->select('po.*, s.name as supplier_name');
        $this->db->from(db_prefix() . 'purchases_orders po');
        $this->db->join(db_prefix() . 'purchases_suppliers s', 's.id = po.supplier_id');
        $this->db->where('po.id', $id);
        return $this->db->get()->row_array();
    }

    public function get_purchase_order_items(int $po_id): array
    {
        $this->db->select('poi.*, ip.name as product_name, ip.sku as product_sku');
        $this->db->from(db_prefix() . 'purchases_order_items poi');
        $this->db->join(db_prefix() . 'inventory_products ip', 'ip.id = poi.product_id');
        $this->db->where('poi.purchase_order_id', $po_id);
        return $this->db->get()->result_array();
    }

    /**
     * Same as get_purchase_order_items() but for many POs in one query — used by
     * the purchase orders list page, which previously ran one query per row.
     *
     * @return array<int, array> items grouped by purchase_order_id
     */
    public function get_purchase_order_items_for_pos(array $po_ids): array
    {
        if (empty($po_ids)) {
            return [];
        }

        $this->db->select('poi.*, ip.name as product_name, ip.sku as product_sku');
        $this->db->from(db_prefix() . 'purchases_order_items poi');
        $this->db->join(db_prefix() . 'inventory_products ip', 'ip.id = poi.product_id');
        $this->db->where_in('poi.purchase_order_id', $po_ids);
        $rows = $this->db->get()->result_array();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['purchase_order_id']][] = $row;
        }
        return $grouped;
    }

    public function add_purchase_order(array $data): int
    {
        $this->db->trans_start();
        
        $db_data = [
            'supplier_id' => (int) $data['supplier_id'],
            'status'      => 'draft',
            'total'       => (float) $data['total'],
            'order_date'  => !empty($data['order_date']) ? $data['order_date'] : date('Y-m-d'),
            'staff_id'    => get_staff_user_id() ?: null,
            'created_at'  => date('Y-m-d H:i:s'),
        ];
        $this->db->insert(db_prefix() . 'purchases_orders', $db_data);
        $po_id = $this->db->insert_id();

        $items = $data['items'] ?? [];
        foreach ($items as $item) {
            $this->db->insert(db_prefix() . 'purchases_order_items', [
                'purchase_order_id' => $po_id,
                'product_id'        => (int) $item['product_id'],
                'qty'               => (float) $item['qty'],
                'unit_cost'         => (float) $item['unit_cost'],
            ]);
        }

        $this->db->trans_complete();
        return $this->db->trans_status() ? $po_id : 0;
    }

    public function update_purchase_order(int $id, array $data): bool
    {
        $this->db->trans_start();

        $po = $this->get_purchase_order($id);
        if (!$po || $po['status'] === 'received') {
            return false;
        }

        $db_data = [
            'supplier_id' => (int) $data['supplier_id'],
            'status'      => $data['status'] ?? $po['status'],
            'total'       => (float) $data['total'],
            'order_date'  => !empty($data['order_date']) ? $data['order_date'] : date('Y-m-d'),
        ];
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'purchases_orders', $db_data);

        // Delete old items and insert updated items
        $this->db->where('purchase_order_id', $id);
        $this->db->delete(db_prefix() . 'purchases_order_items');

        $items = $data['items'] ?? [];
        foreach ($items as $item) {
            $this->db->insert(db_prefix() . 'purchases_order_items', [
                'purchase_order_id' => $id,
                'product_id'        => (int) $item['product_id'],
                'qty'               => (float) $item['qty'],
                'unit_cost'         => (float) $item['unit_cost'],
            ]);
        }

        $this->db->trans_complete();
        return $this->db->trans_status();
    }

    public function delete_purchase_order(int $id): bool
    {
        $po = $this->get_purchase_order($id);
        if (!$po || $po['status'] === 'received') {
            return false;
        }

        $this->db->trans_start();
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'purchases_orders');

        $this->db->where('purchase_order_id', $id);
        $this->db->delete(db_prefix() . 'purchases_order_items');
        $this->db->trans_complete();
        return $this->db->trans_status();
    }

    // ── Receive PO & Supplier Ledger ──────────────────────────────────────────

    public function receive_purchase_order(int $po_id): bool
    {
        $po = $this->get_purchase_order($po_id);
        if (!$po || $po['status'] === 'received') {
            return false;
        }

        $this->db->trans_start();

        // 1. Update PO status
        $this->db->where('id', $po_id);
        $this->db->update(db_prefix() . 'purchases_orders', [
            'status'      => 'received',
            'received_at' => date('Y-m-d H:i:s'),
        ]);

        // 2. Adjust Stock for all items
        $items = $this->get_purchase_order_items($po_id);
        $wh_id = $this->inventory_model->get_default_warehouse_id();
        foreach ($items as $item) {
            $this->inventory_model->adjust_stock(
                (int) $item['product_id'],
                $wh_id,
                (float) $item['qty'],
                'purchase_in',
                'purchase_order',
                $po_id,
                'Purchase order received'
            );
        }

        // 3. Record supplier ledger debit (representing what we owe them for this purchase)
        $this->db->insert(db_prefix() . 'purchases_supplier_ledger', [
            'supplier_id' => (int) $po['supplier_id'],
            'entry_type'  => 'debit',
            'amount'      => (float) $po['total'],
            'ref_type'    => 'purchase_order',
            'ref_id'      => $po_id,
            'note'        => 'Received purchase order #' . $po_id,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->db->trans_complete();
        return $this->db->trans_status();
    }

    public function record_supplier_payment(int $supplier_id, float $amount, string $note = null): bool
    {
        return $this->db->insert(db_prefix() . 'purchases_supplier_ledger', [
            'supplier_id' => $supplier_id,
            'entry_type'  => 'credit', // Credit reduces the amount we owe
            'amount'      => $amount,
            'ref_type'    => 'payment',
            'ref_id'      => null,
            'note'        => $note,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    public function get_supplier_ledger(int $supplier_id): array
    {
        $this->db->where('supplier_id', $supplier_id);
        $this->db->order_by('created_at', 'desc');
        return $this->db->get(db_prefix() . 'purchases_supplier_ledger')->result_array();
    }

    public function get_supplier_balance(int $supplier_id): float
    {
        $supplier = $this->get_supplier($supplier_id);
        if (!$supplier) {
            return 0.00;
        }

        $balance = (float) $supplier['opening_balance'];

        // Add debits (what we owe them)
        $this->db->select_sum('amount');
        $this->db->where('supplier_id', $supplier_id);
        $this->db->where('entry_type', 'debit');
        $debit_row = $this->db->get(db_prefix() . 'purchases_supplier_ledger')->row();
        $balance += $debit_row ? (float) $debit_row->amount : 0.00;

        // Subtract credits (what we paid them)
        $this->db->select_sum('amount');
        $this->db->where('supplier_id', $supplier_id);
        $this->db->where('entry_type', 'credit');
        $credit_row = $this->db->get(db_prefix() . 'purchases_supplier_ledger')->row();
        $balance -= $credit_row ? (float) $credit_row->amount : 0.00;

        return $balance;
    }
}
