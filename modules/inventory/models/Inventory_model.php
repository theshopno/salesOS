<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Inventory_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get all SKU-tracked products with current stock counts.
     */
    public function get_products(): array
    {
        $this->db->select('p.*, c.name as category_name, SUM(s.qty_on_hand) as stock_on_hand, i.description as item_name, i.rate as rate');
        $this->db->from(db_prefix() . 'inventory_products p');
        $this->db->join(db_prefix() . 'inventory_categories c', 'c.id = p.category_id', 'left');
        $this->db->join(db_prefix() . 'inventory_stock s', 's.product_id = p.id', 'left');
        $this->db->join(db_prefix() . 'items i', 'i.id = p.item_id', 'left');
        $this->db->group_by('p.id');
        return $this->db->get()->result_array();
    }

    public function get_product(int $id)
    {
        $this->db->where('id', $id);
        return $this->db->get(db_prefix() . 'inventory_products')->row_array();
    }

    public function add_product(array $data): int
    {
        $db_data = [
            'item_id'       => (isset($data['item_id']) && $data['item_id'] !== '') ? (int) $data['item_id'] : null,
            'sku'           => !empty($data['sku']) ? trim($data['sku']) : null,
            'name'          => trim($data['name']),
            'category_id'   => !empty($data['category_id']) ? (int) $data['category_id'] : null,
            'image'         => isset($data['image']) ? trim($data['image']) : null,
            'reorder_level' => !empty($data['reorder_level']) ? (float) $data['reorder_level'] : 0.00,
            'is_active'     => isset($data['is_active']) ? (int) $data['is_active'] : 1,
            'created_at'    => date('Y-m-d H:i:s'),
        ];
        $this->db->insert(db_prefix() . 'inventory_products', $db_data);
        return $this->db->insert_id();
    }

    public function update_product(int $id, array $data): bool
    {
        $db_data = [
            'item_id'       => (isset($data['item_id']) && $data['item_id'] !== '') ? (int) $data['item_id'] : null,
            'sku'           => !empty($data['sku']) ? trim($data['sku']) : null,
            'name'          => trim($data['name']),
            'category_id'   => !empty($data['category_id']) ? (int) $data['category_id'] : null,
            'image'         => isset($data['image']) ? trim($data['image']) : null,
            'reorder_level' => !empty($data['reorder_level']) ? (float) $data['reorder_level'] : 0.00,
            'is_active'     => isset($data['is_active']) ? (int) $data['is_active'] : 1,
        ];
        $this->db->where('id', $id);
        return $this->db->update(db_prefix() . 'inventory_products', $db_data);
    }

    public function delete_product(int $id): bool
    {
        $this->db->trans_start();
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'inventory_products');
        
        $this->db->where('product_id', $id);
        $this->db->delete(db_prefix() . 'inventory_stock');
        
        $this->db->where('product_id', $id);
        $this->db->delete(db_prefix() . 'inventory_stock_ledger');
        $this->db->trans_complete();
        return $this->db->trans_status();
    }

    // ── Categories ────────────────────────────────────────────────────────────

    public function get_categories(): array
    {
        return $this->db->get(db_prefix() . 'inventory_categories')->result_array();
    }

    public function add_category(array $data): int
    {
        $this->db->insert(db_prefix() . 'inventory_categories', [
            'name'      => trim($data['name']),
            'parent_id' => !empty($data['parent_id']) ? (int) $data['parent_id'] : null,
        ]);
        return $this->db->insert_id();
    }

    public function update_category(int $id, array $data): bool
    {
        $this->db->where('id', $id);
        return $this->db->update(db_prefix() . 'inventory_categories', [
            'name'      => trim($data['name']),
            'parent_id' => !empty($data['parent_id']) ? (int) $data['parent_id'] : null,
        ]);
    }

    public function delete_category(int $id): bool
    {
        $this->db->where('id', $id);
        return $this->db->delete(db_prefix() . 'inventory_categories');
    }

    // ── Stock Adjustments & Ledger ────────────────────────────────────────────

    /**
     * Core stock adjustment method writing to ledger and updating current stock.
     */
    public function adjust_stock(int $product_id, int $warehouse_id, float $qty, string $movement_type, string $ref_type = null, int $ref_id = null, string $note = null): bool
    {
        $this->db->trans_start();

        $db_prefix = db_prefix();
        $now = date('Y-m-d H:i:s');

        // Atomic upsert instead of read-then-write: two concurrent sales of the same
        // last unit used to both read the same starting qty_on_hand and both succeed
        // (lost-update / oversell). INSERT ... ON DUPLICATE KEY UPDATE takes a row
        // lock on the (product_id, warehouse_id) unique key for this statement, so
        // concurrent adjustments serialize correctly instead of racing.
        $this->db->query(
            "INSERT INTO `{$db_prefix}inventory_stock` (product_id, warehouse_id, qty_on_hand, qty_reserved, updated_at)
             VALUES (?, ?, ?, 0.00, ?)
             ON DUPLICATE KEY UPDATE qty_on_hand = qty_on_hand + VALUES(qty_on_hand), updated_at = VALUES(updated_at)",
            [$product_id, $warehouse_id, $qty, $now]
        );

        // Re-select within the same transaction for the ledger's balance_after —
        // this reads back our own just-written row, so it's race-free even though
        // it's a separate statement.
        $this->db->where('product_id', $product_id);
        $this->db->where('warehouse_id', $warehouse_id);
        $stock = $this->db->get(db_prefix() . 'inventory_stock')->row();
        $balance_after = $stock ? (float) $stock->qty_on_hand : $qty;

        if ($balance_after < 0) {
            log_activity("Inventory: product #{$product_id} warehouse #{$warehouse_id} went negative ({$balance_after}) after a '{$movement_type}' adjustment of {$qty}");
        }

        $ledger_data = [
            'product_id'    => $product_id,
            'warehouse_id'  => $warehouse_id,
            'movement_type' => $movement_type,
            'qty'           => $qty,
            'balance_after' => $balance_after,
            'ref_type'      => $ref_type,
            'ref_id'        => $ref_id,
            'staff_id'      => get_staff_user_id() ?: null,
            'note'          => $note,
            'created_at'    => $now,
        ];
        $this->db->insert(db_prefix() . 'inventory_stock_ledger', $ledger_data);

        $this->db->trans_complete();
        return $this->db->trans_status();
    }

    public function get_default_warehouse_id(): int
    {
        $this->db->where('is_default', 1);
        $wh = $this->db->get(db_prefix() . 'inventory_warehouses')->row();
        return $wh ? (int) $wh->id : 1;
    }

    public function get_ledger(): array
    {
        $this->db->select('l.*, p.name as product_name, p.sku as product_sku, w.name as warehouse_name, s.firstname, s.lastname');
        $this->db->from(db_prefix() . 'inventory_stock_ledger l');
        $this->db->join(db_prefix() . 'inventory_products p', 'p.id = l.product_id');
        $this->db->join(db_prefix() . 'inventory_warehouses w', 'w.id = l.warehouse_id');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = l.staff_id', 'left');
        $this->db->order_by('l.created_at', 'desc');
        return $this->db->get()->result_array();
    }

    // ── Event Handlers ────────────────────────────────────────────────────────

    public function handle_order_confirmed(int $order_id): void
    {
        $this->db->where('order_id', $order_id);
        $items = $this->db->get(db_prefix() . 'ecomcore_order_items')->result_array();
        $wh_id = $this->get_default_warehouse_id();

        foreach ($items as $item) {
            $product_id = null;

            // 1. Try matching by product_id if set
            if (!empty($item['product_id'])) {
                $product_id = (int) $item['product_id'];
            } 
            // 2. Try matching by SKU
            elseif (!empty($item['sku'])) {
                $this->db->where('sku', trim($item['sku']));
                $p = $this->db->get(db_prefix() . 'inventory_products')->row();
                if ($p) {
                    $product_id = $p->id;
                } else {
                    // Auto-create product in catalog from synced order item
                    $this->db->insert(db_prefix() . 'inventory_products', [
                        'sku'           => trim($item['sku']),
                        'name'          => $item['name'] ?? 'Woo Product ' . trim($item['sku']),
                        'reorder_level' => 0.00,
                        'is_active'     => 1,
                        'created_at'    => date('Y-m-d H:i:s'),
                    ]);
                    $product_id = $this->db->insert_id();
                }
            } else {
                // Fallback: match by product name if SKU is empty
                $name = !empty($item['name']) ? trim($item['name']) : '';
                if ($name !== '') {
                    $this->db->where('name', $name);
                    $p = $this->db->get(db_prefix() . 'inventory_products')->row();
                    if ($p) {
                        $product_id = $p->id;
                    } else {
                        // Auto-create product in catalog from synced order item name
                        $dummy_sku = 'WOO-' . substr(md5($name), 0, 8);
                        $this->db->insert(db_prefix() . 'inventory_products', [
                            'sku'           => $dummy_sku,
                            'name'          => $name,
                            'reorder_level' => 0.00,
                            'is_active'     => 1,
                            'created_at'    => date('Y-m-d H:i:s'),
                        ]);
                        $product_id = $this->db->insert_id();
                    }
                }
            }

            if ($product_id) {
                $qty = (float) $item['qty'];
                $this->adjust_stock($product_id, $wh_id, -$qty, 'sale_out', 'ecomcore_order', $order_id, 'Ecomcore order confirmed');
            }
        }
    }

    public function handle_order_cancelled(int $order_id): void
    {
        // Find prior stock deductions for this order
        $this->db->where('ref_type', 'ecomcore_order');
        $this->db->where('ref_id', $order_id);
        $this->db->where('movement_type', 'sale_out');
        $ledger_rows = $this->db->get(db_prefix() . 'inventory_stock_ledger')->result_array();

        foreach ($ledger_rows as $row) {
            // Reverse quantity
            $qty = abs((float) $row['qty']);
            $this->adjust_stock((int) $row['product_id'], (int) $row['warehouse_id'], $qty, 'return_in', 'ecomcore_order', $order_id, 'Ecomcore order cancelled - stock returned');
        }
    }

    public function handle_stock_returned(int $order_id): void
    {
        // Return process
        $this->db->where('ref_type', 'ecomcore_order');
        $this->db->where('ref_id', $order_id);
        $this->db->where('movement_type', 'sale_out');
        $ledger_rows = $this->db->get(db_prefix() . 'inventory_stock_ledger')->result_array();

        foreach ($ledger_rows as $row) {
            $qty = abs((float) $row['qty']);
            $this->adjust_stock((int) $row['product_id'], (int) $row['warehouse_id'], $qty, 'return_in', 'ecomcore_order', $order_id, 'Ecomcore return processed - stock returned');
        }
    }
}
