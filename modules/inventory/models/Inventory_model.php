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
    /**
     * Get all SKU-tracked products with current stock counts and variation stats.
     */
    public function get_products(bool $include_variations = false): array
    {
        $prefix = db_prefix();
        $this->db->select("p.*, c.name as category_name, 
            COALESCE(SUM(s.qty_on_hand), 0) as direct_stock,
            COALESCE(SUM(s.qty_reserved), 0) as direct_reserved,
            (SELECT COALESCE(SUM(vs.qty_on_hand), 0) FROM {$prefix}inventory_stock vs JOIN {$prefix}inventory_products vp ON vp.id = vs.product_id WHERE vp.parent_id = p.id) as variation_stock,
            (SELECT COALESCE(SUM(vs.qty_reserved), 0) FROM {$prefix}inventory_stock vs JOIN {$prefix}inventory_products vp ON vp.id = vs.product_id WHERE vp.parent_id = p.id) as variation_reserved,
            (SELECT COUNT(*) FROM {$prefix}inventory_products vp WHERE vp.parent_id = p.id) as variation_count,
            i.description as item_name, i.rate as rate");
        $this->db->from($prefix . 'inventory_products p');
        $this->db->join($prefix . 'inventory_categories c', 'c.id = p.category_id', 'left');
        $this->db->join($prefix . 'inventory_stock s', 's.product_id = p.id', 'left');
        $this->db->join($prefix . 'items i', 'i.id = p.item_id', 'left');
        if (!$include_variations) {
            $this->db->where('p.parent_id IS NULL');
        }
        $this->db->group_by('p.id');
        $this->db->order_by('p.id', 'desc');
        $rows = $this->db->get()->result_array();

        foreach ($rows as &$r) {
            if ($r['product_type'] === 'variable' && (int) $r['variation_count'] > 0) {
                $r['stock_on_hand']  = (float) $r['variation_stock'];
                $r['stock_reserved'] = (float) $r['variation_reserved'];
            } else {
                $r['stock_on_hand']  = (float) $r['direct_stock'];
                $r['stock_reserved'] = (float) $r['direct_reserved'];
            }
            $r['stock_available'] = max(0.00, $r['stock_on_hand'] - $r['stock_reserved']);
        }

        return $rows;
    }

    public function get_product(int $id)
    {
        $this->db->select('p.*, c.name as category_name, i.rate as rate, i.unit as unit');
        $this->db->from(db_prefix() . 'inventory_products p');
        $this->db->join(db_prefix() . 'inventory_categories c', 'c.id = p.category_id', 'left');
        $this->db->join(db_prefix() . 'items i', 'i.id = p.item_id', 'left');
        $this->db->where('p.id', $id);
        $prod = $this->db->get()->row_array();

        if ($prod && $prod['product_type'] === 'variable') {
            $prod['variations'] = $this->get_variations($id);
        }

        return $prod;
    }

    public function get_variations(int $parent_id): array
    {
        $this->db->select('p.*, COALESCE(SUM(s.qty_on_hand), 0) as stock_on_hand, COALESCE(SUM(s.qty_reserved), 0) as stock_reserved, i.rate as rate');
        $this->db->from(db_prefix() . 'inventory_products p');
        $this->db->join(db_prefix() . 'inventory_stock s', 's.product_id = p.id', 'left');
        $this->db->join(db_prefix() . 'items i', 'i.id = p.item_id', 'left');
        $this->db->where('p.parent_id', $parent_id);
        $this->db->group_by('p.id');
        $this->db->order_by('p.id', 'asc');
        $variations = $this->db->get()->result_array();
        foreach ($variations as &$v) {
            $v['stock_on_hand']   = (float) $v['stock_on_hand'];
            $v['stock_reserved']  = (float) ($v['stock_reserved'] ?? 0);
            $v['stock_available'] = max(0.00, $v['stock_on_hand'] - $v['stock_reserved']);
        }
        return $variations;
    }

    public function add_product(array $data): int
    {
        $uom = !empty($data['uom']) ? trim($data['uom']) : 'pc';
        $name = trim($data['name']);
        $sku = !empty($data['sku']) ? trim($data['sku']) : null;
        $product_type = !empty($data['product_type']) ? trim($data['product_type']) : 'simple';

        // 1. Auto-create or link with tblitems if rate is given
        $item_id = (isset($data['item_id']) && $data['item_id'] !== '') ? (int) $data['item_id'] : null;
        $rate = isset($data['rate']) ? (float) $data['rate'] : 0.00;

        if (empty($item_id)) {
            $this->db->insert(db_prefix() . 'items', [
                'description'      => $name,
                'long_description' => 'SKU: ' . ($sku ?: ''),
                'rate'             => $rate,
                'unit'             => $uom,
                'group_id'         => 0,
            ]);
            $item_id = $this->db->insert_id();
        }

        $db_data = [
            'product_type'       => $product_type,
            'parent_id'          => !empty($data['parent_id']) ? (int) $data['parent_id'] : null,
            'item_id'            => $item_id,
            'sku'                => $sku,
            'barcode'            => !empty($data['barcode']) ? trim($data['barcode']) : null,
            'name'               => $name,
            'uom'                => $uom,
            'category_id'        => !empty($data['category_id']) ? (int) $data['category_id'] : null,
            'attributes_json'    => is_array($data['attributes_json'] ?? null) ? json_encode($data['attributes_json']) : ($data['attributes_json'] ?? null),
            'industry_data_json' => is_array($data['industry_data_json'] ?? null) ? json_encode($data['industry_data_json']) : ($data['industry_data_json'] ?? null),
            'image'              => isset($data['image']) ? trim($data['image']) : null,
            'reorder_level'      => !empty($data['reorder_level']) ? (float) $data['reorder_level'] : 0.00,
            'cost_price'         => isset($data['cost_price']) && $data['cost_price'] !== '' ? (float) $data['cost_price'] : null,
            'external_platform'  => !empty($data['external_platform']) ? trim($data['external_platform']) : null,
            'external_id'        => !empty($data['external_id']) ? (int) $data['external_id'] : null,
            'external_parent_id' => !empty($data['external_parent_id']) ? (int) $data['external_parent_id'] : null,
            'sync_to_wc'         => isset($data['sync_to_wc']) ? (int) $data['sync_to_wc'] : 1,
            'is_active'          => isset($data['is_active']) ? (int) $data['is_active'] : 1,
            'created_at'         => date('Y-m-d H:i:s'),
        ];

        $this->db->insert(db_prefix() . 'inventory_products', $db_data);
        $product_id = (int) $this->db->insert_id();

        // 2. Initial stock setup if provided
        if (!empty($data['initial_stock']) && (float) $data['initial_stock'] > 0) {
            $warehouse_id = !empty($data['warehouse_id']) ? (int) $data['warehouse_id'] : 1;
            $this->adjust_stock($product_id, $warehouse_id, (float) $data['initial_stock'], 'initial', 'manual', null, 'Initial stock entry');
        }

        // 3. Child variations matrix if provided
        if ($product_type === 'variable' && !empty($data['variations']) && is_array($data['variations'])) {
            foreach ($data['variations'] as $v) {
                if (empty($v['sku']) && empty($v['name'])) {
                    continue;
                }
                $v['parent_id'] = $product_id;
                $v['product_type'] = 'variation';
                $v['category_id'] = $db_data['category_id'];
                $v['uom'] = $uom;
                $v['image'] = !empty($v['image']) ? $v['image'] : $db_data['image'];
                $this->add_product($v);
            }
        }

        return $product_id;
    }

    public function update_product(int $id, array $data): bool
    {
        $uom = !empty($data['uom']) ? trim($data['uom']) : 'pc';
        $name = trim($data['name']);
        $sku = !empty($data['sku']) ? trim($data['sku']) : null;

        $db_data = [
            'sku'                => $sku,
            'barcode'            => !empty($data['barcode']) ? trim($data['barcode']) : null,
            'name'               => $name,
            'uom'                => $uom,
            'category_id'        => !empty($data['category_id']) ? (int) $data['category_id'] : null,
            'image'              => isset($data['image']) ? trim($data['image']) : null,
            'reorder_level'      => !empty($data['reorder_level']) ? (float) $data['reorder_level'] : 0.00,
            'sync_to_wc'         => isset($data['sync_to_wc']) ? (int) $data['sync_to_wc'] : 1,
            'is_active'          => isset($data['is_active']) ? (int) $data['is_active'] : 1,
        ];

        if (isset($data['product_type'])) {
            $db_data['product_type'] = $data['product_type'];
        }
        if (isset($data['attributes_json'])) {
            $db_data['attributes_json'] = is_array($data['attributes_json']) ? json_encode($data['attributes_json']) : $data['attributes_json'];
        }
        if (isset($data['industry_data_json'])) {
            $db_data['industry_data_json'] = is_array($data['industry_data_json']) ? json_encode($data['industry_data_json']) : $data['industry_data_json'];
        }
        if (isset($data['cost_price']) && $data['cost_price'] !== '') {
            $db_data['cost_price'] = (float) $data['cost_price'];
        }

        // Update tblitems price and unit if linked
        $item_id = (isset($data['item_id']) && $data['item_id'] !== '') ? (int) $data['item_id'] : null;
        if ($item_id) {
            $db_data['item_id'] = $item_id;
            if (isset($data['rate'])) {
                $this->db->where('id', $item_id)->update(db_prefix() . 'items', [
                    'rate'        => (float) $data['rate'],
                    'unit'        => $uom,
                    'description' => $name,
                ]);
            }
        } elseif (isset($data['rate'])) {
            $this->db->insert(db_prefix() . 'items', [
                'description'      => $name,
                'long_description' => 'SKU: ' . ($sku ?: ''),
                'rate'             => (float) $data['rate'],
                'unit'             => $uom,
                'group_id'         => 0,
            ]);
            $db_data['item_id'] = $this->db->insert_id();
        }

        $this->db->where('id', $id);
        $updated = $this->db->update(db_prefix() . 'inventory_products', $db_data);

        // Update or insert child variations if submitted
        if (!empty($data['variations']) && is_array($data['variations'])) {
            foreach ($data['variations'] as $v) {
                if (!empty($v['id'])) {
                    $this->update_product((int) $v['id'], $v);
                } else {
                    $v['parent_id'] = $id;
                    $v['product_type'] = 'variation';
                    $v['category_id'] = $db_data['category_id'];
                    $v['uom'] = $uom;
                    $this->add_product($v);
                }
            }
        }

        return $updated;
    }

    /**
     * Why a product is ever refused deletion.
     *
     * @return string|null the reason, or null when it is safe to delete
     */
    public function product_delete_blocker(int $id): ?string
    {
        $prefix = db_prefix();

        $sold = $this->db->table_exists($prefix . 'salesos_order_items')
            ? (int) $this->db->where('product_id', $id)->count_all_results($prefix . 'salesos_order_items')
            : 0;
        if ($sold > 0) {
            return "it appears on {$sold} order line(s)";
        }

        $purchased = $this->db->table_exists($prefix . 'purchases_order_items')
            ? (int) $this->db->where('product_id', $id)->count_all_results($prefix . 'purchases_order_items')
            : 0;
        if ($purchased > 0) {
            return "it appears on {$purchased} purchase order line(s)";
        }

        $movements = (int) $this->db->where('product_id', $id)
            ->count_all_results($prefix . 'inventory_stock_ledger');
        if ($movements > 0) {
            return "it has {$movements} stock movement(s) on record";
        }

        return null;
    }

    /**
     * Delete a product that was never traded.
     *
     * A product that has been bought or sold is refused rather than removed:
     * deleting it took its whole stock ledger with it — the audit trail of what
     * actually moved, which has to outlive the catalogue entry — and left order
     * lines pointing at a product id that resolves to nothing. Retiring such a
     * product is what deactivate_product() is for; it keeps the history and takes
     * it out of every picker.
     *
     * @return bool false when the product is in use; ask
     *              product_delete_blocker() why
     */
    public function delete_product(int $id): bool
    {
        if ($this->product_delete_blocker($id) !== null) {
            return false;
        }

        $this->db->trans_start();
        $this->db->where('id', $id)->delete(db_prefix() . 'inventory_products');
        $this->db->where('product_id', $id)->delete(db_prefix() . 'inventory_stock');
        $this->db->where('product_id', $id)->delete(db_prefix() . 'inventory_stock_ledger');
        $this->db->trans_complete();

        return $this->db->trans_status();
    }

    // ── Cost ─────────────────────────────────────────────────────────────────

    /**
     * Fold a newly received batch into a product's average cost.
     *
     * Weighted average, not last-paid: a seller who bought 100 units at 50 and
     * then 10 at 80 is not suddenly holding stock worth 80 each, and pricing off
     * the last invoice is how a margin quietly goes negative. The first batch
     * ever received sets the cost outright, since there is nothing to average
     * against.
     */
    public function apply_received_cost(int $product_id, float $qty, float $unit_cost): void
    {
        if ($qty <= 0 || $unit_cost < 0) {
            return;
        }

        $product = $this->db->select('cost_price')->where('id', $product_id)
            ->get(db_prefix() . 'inventory_products')->row();
        if (!$product) {
            return;
        }

        // Stock held before this batch arrived, across every warehouse.
        $held = (float) ($this->db->select_sum('qty_on_hand')->where('product_id', $product_id)
            ->get(db_prefix() . 'inventory_stock')->row()->qty_on_hand ?? 0);
        $existing_qty = max(0.0, $held - $qty);

        $existing_cost = $product->cost_price === null ? null : (float) $product->cost_price;

        if ($existing_cost === null || $existing_qty <= 0) {
            $new_cost = $unit_cost;
        } else {
            $new_cost = (($existing_qty * $existing_cost) + ($qty * $unit_cost)) / ($existing_qty + $qty);
        }

        $this->db->where('id', $product_id)
            ->update(db_prefix() . 'inventory_products', ['cost_price' => round($new_cost, 2)]);
    }

    /** Products that have been sold but have no cost, so their margin is unknown. */
    public function count_products_without_cost(): int
    {
        return (int) $this->db->where('cost_price IS NULL', null, false)
            ->where('is_active', 1)
            ->count_all_results(db_prefix() . 'inventory_products');
    }

    /** Retire a product without losing what it did. */
    public function deactivate_product(int $id): bool
    {
        return $this->db->where('id', $id)
            ->update(db_prefix() . 'inventory_products', ['is_active' => 0]);
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

    /**
     * Delete a category, releasing its products first.
     *
     * A category is a label, not an owner — the products outlive it. Without this
     * they kept pointing at an id that no longer existed, which quietly dropped
     * them out of category filters and the POS grid.
     */
    public function delete_category(int $id): bool
    {
        $this->db->trans_start();

        $this->db->where('category_id', $id)
            ->update(db_prefix() . 'inventory_products', ['category_id' => null]);

        // Nested categories are re-parented to the top rather than orphaned.
        if ($this->db->field_exists('parent_id', db_prefix() . 'inventory_categories')) {
            $this->db->where('parent_id', $id)
                ->update(db_prefix() . 'inventory_categories', ['parent_id' => null]);
        }

        $this->db->where('id', $id)->delete(db_prefix() . 'inventory_categories');
        $this->db->trans_complete();

        return $this->db->trans_status();
    }

    /** How many products a category would release if it were deleted. */
    public function count_category_products(int $id): int
    {
        return (int) $this->db->where('category_id', $id)
            ->count_all_results(db_prefix() . 'inventory_products');
    }

    // ── Stock Adjustments & Ledger ────────────────────────────────────────────

    /** Is selling below available stock (backorder) permitted? */
    public function oversell_allowed(): bool
    {
        return get_option('inventory_allow_oversell') === '1';
    }

    /** Is stock reservation enabled for unconfirmed orders? */
    public function is_reservation_enabled(): bool
    {
        return get_option('inventory_stock_reservation_enabled') !== '0';
    }

    /** Current on-hand quantity for a product in a warehouse. */
    public function get_stock_on_hand(int $product_id, int $warehouse_id): float
    {
        $this->db->where('product_id', $product_id);
        $this->db->where('warehouse_id', $warehouse_id);
        $stock = $this->db->get(db_prefix() . 'inventory_stock')->row();

        return $stock ? (float) $stock->qty_on_hand : 0.00;
    }

    /** Current reserved quantity for a product in a warehouse. */
    public function get_stock_reserved(int $product_id, int $warehouse_id): float
    {
        $this->db->where('product_id', $product_id);
        $this->db->where('warehouse_id', $warehouse_id);
        $stock = $this->db->get(db_prefix() . 'inventory_stock')->row();

        return $stock ? (float) $stock->qty_reserved : 0.00;
    }

    /** Current available (sellable) quantity for a product in a warehouse. */
    public function get_stock_available(int $product_id, int $warehouse_id): float
    {
        $this->db->where('product_id', $product_id);
        $this->db->where('warehouse_id', $warehouse_id);
        $stock = $this->db->get(db_prefix() . 'inventory_stock')->row();

        if (!$stock) {
            return 0.00;
        }

        return max(0.00, (float) $stock->qty_on_hand - (float) $stock->qty_reserved);
    }

    /**
     * Reserve stock for an unconfirmed order.
     * Increases qty_reserved without changing qty_on_hand.
     */
    public function reserve_stock(int $product_id, int $warehouse_id, float $qty, ?string $ref_type = null, ?int $ref_id = null, ?string $note = null): bool
    {
        if ($qty <= 0) {
            return true;
        }

        if (!$this->is_reservation_enabled()) {
            return false;
        }

        $this->db->trans_start();

        $db_prefix = db_prefix();
        $now = date('Y-m-d H:i:s');

        if (!$this->oversell_allowed()) {
            // Enforce available stock check: (qty_on_hand - qty_reserved) >= $qty
            $this->db->query(
                "UPDATE `{$db_prefix}inventory_stock`
                 SET qty_reserved = qty_reserved + ?, updated_at = ?
                 WHERE product_id = ? AND warehouse_id = ? AND (qty_on_hand - qty_reserved) >= ?",
                [$qty, $now, $product_id, $warehouse_id, $qty]
            );

            if ($this->db->affected_rows() === 0) {
                $this->db->trans_complete();
                log_activity("Inventory: refused reservation of {$qty} for product #{$product_id} in warehouse #{$warehouse_id} — insufficient available stock");
                return false;
            }
        } else {
            // Atomic upsert with overselling enabled
            $this->db->query(
                "INSERT INTO `{$db_prefix}inventory_stock` (product_id, warehouse_id, qty_on_hand, qty_reserved, updated_at)
                 VALUES (?, ?, 0.00, ?, ?)
                 ON DUPLICATE KEY UPDATE qty_reserved = qty_reserved + VALUES(qty_reserved), updated_at = VALUES(updated_at)",
                [$product_id, $warehouse_id, $qty, $now]
            );
        }

        // Read current on-hand for ledger snapshot
        $this->db->where('product_id', $product_id);
        $this->db->where('warehouse_id', $warehouse_id);
        $stock = $this->db->get($db_prefix . 'inventory_stock')->row();
        $balance_after = $stock ? (float) $stock->qty_on_hand : 0.00;

        $ledger_data = [
            'product_id'    => $product_id,
            'warehouse_id'  => $warehouse_id,
            'movement_type' => 'reserve_hold',
            'qty'           => $qty,
            'balance_after' => $balance_after,
            'ref_type'      => $ref_type,
            'ref_id'        => $ref_id,
            'staff_id'      => get_staff_user_id() ?: null,
            'note'          => $note ?: 'Stock reserved for unconfirmed order',
            'created_at'    => $now,
        ];
        $this->db->insert($db_prefix . 'inventory_stock_ledger', $ledger_data);

        $this->db->trans_complete();
        $trans_ok = $this->db->trans_status();

        if ($trans_ok && $stock) {
            $available = max(0.00, (float) $stock->qty_on_hand - (float) $stock->qty_reserved);
            $this->sync_stock_to_wc($product_id, $available);
        }

        return $trans_ok;
    }

    /**
     * Release any active stock reservations for an order.
     * Decreases qty_reserved without changing qty_on_hand.
     */
    public function release_order_reservations(int $order_id, ?string $note = null): bool
    {
        $db_prefix = db_prefix();

        $rows = $this->db->select('product_id, warehouse_id, movement_type, qty')
            ->where('ref_type', 'salesos_order')
            ->where('ref_id', $order_id)
            ->where_in('movement_type', ['reserve_hold', 'reserve_release', 'reserve_consume'])
            ->get($db_prefix . 'inventory_stock_ledger')
            ->result_array();

        if (empty($rows)) {
            return false;
        }

        $outstanding = [];
        foreach ($rows as $row) {
            $key = $row['product_id'] . ':' . $row['warehouse_id'];
            $qty = abs((float) $row['qty']);

            if (!isset($outstanding[$key])) {
                $outstanding[$key] = [
                    'product_id'   => (int) $row['product_id'],
                    'warehouse_id' => (int) $row['warehouse_id'],
                    'qty'          => 0.00,
                ];
            }

            if ($row['movement_type'] === 'reserve_hold') {
                $outstanding[$key]['qty'] += $qty;
            } else {
                $outstanding[$key]['qty'] -= $qty;
            }
        }

        $now = date('Y-m-d H:i:s');
        foreach ($outstanding as $entry) {
            $rel_qty = $entry['qty'];
            if ($rel_qty <= 0) {
                continue;
            }

            $p_id = $entry['product_id'];
            $wh_id = $entry['warehouse_id'];

            $this->db->trans_start();

            // Atomically decrease qty_reserved (floored at 0)
            $this->db->query(
                "UPDATE `{$db_prefix}inventory_stock`
                 SET qty_reserved = GREATEST(0.00, qty_reserved - ?), updated_at = ?
                 WHERE product_id = ? AND warehouse_id = ?",
                [$rel_qty, $now, $p_id, $wh_id]
            );

            $this->db->where('product_id', $p_id);
            $this->db->where('warehouse_id', $wh_id);
            $stock = $this->db->get($db_prefix . 'inventory_stock')->row();
            $balance_after = $stock ? (float) $stock->qty_on_hand : 0.00;

            $this->db->insert($db_prefix . 'inventory_stock_ledger', [
                'product_id'    => $p_id,
                'warehouse_id'  => $wh_id,
                'movement_type' => 'reserve_release',
                'qty'           => -$rel_qty,
                'balance_after' => $balance_after,
                'ref_type'      => 'salesos_order',
                'ref_id'        => $order_id,
                'staff_id'      => get_staff_user_id() ?: null,
                'note'          => $note ?: 'Stock reservation released',
                'created_at'    => $now,
            ]);

            $this->db->trans_complete();

            if ($this->db->trans_status() && $stock) {
                $available = max(0.00, (float) $stock->qty_on_hand - (float) $stock->qty_reserved);
                $this->sync_stock_to_wc($p_id, $available);
            }
        }

        return true;
    }

    /**
     * Core stock adjustment method writing to ledger and updating current stock.
     *
     * Returns false without writing anything when the movement would take stock
     * below zero and overselling is disabled.
     */
    public function adjust_stock(int $product_id, int $warehouse_id, float $qty, string $movement_type, ?string $ref_type = null, ?int $ref_id = null, ?string $note = null): bool
    {
        $this->db->trans_start();

        $db_prefix = db_prefix();
        $now = date('Y-m-d H:i:s');
        $guard_negative = $qty < 0 && !$this->oversell_allowed();

        if ($guard_negative) {
            // Enforce the floor inside the UPDATE itself rather than reading the
            // balance first and deciding in PHP — the check and the write have to be
            // one statement, or two concurrent sales of the same last unit can both
            // pass the check before either writes.
            $this->db->query(
                "UPDATE `{$db_prefix}inventory_stock`
                 SET qty_on_hand = qty_on_hand + ?, updated_at = ?
                 WHERE product_id = ? AND warehouse_id = ? AND qty_on_hand + ? >= 0",
                [$qty, $now, $product_id, $warehouse_id, $qty]
            );

            // Zero rows means either no stock row exists (on-hand is 0) or the
            // movement would have gone below zero — both are refusals.
            if ($this->db->affected_rows() === 0) {
                $this->db->trans_complete();
                log_activity("Inventory: refused '{$movement_type}' of {$qty} for product #{$product_id} in warehouse #{$warehouse_id} — insufficient stock and overselling is disabled");

                return false;
            }
        } else {
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
        }

        // Re-select within the same transaction for the ledger's balance_after —
        // this reads back our own just-written row, so it's race-free even though
        // it's a separate statement.
        $this->db->where('product_id', $product_id);
        $this->db->where('warehouse_id', $warehouse_id);
        $stock = $this->db->get(db_prefix() . 'inventory_stock')->row();
        $balance_after = $stock ? (float) $stock->qty_on_hand : $qty;

        if ($balance_after < 0) {
            log_activity("Inventory: product #{$product_id} warehouse #{$warehouse_id} is negative ({$balance_after}) after a '{$movement_type}' adjustment of {$qty} — overselling is enabled");
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
        $trans_ok = $this->db->trans_status();

        if ($trans_ok && $movement_type !== 'initial') {
            $available = max(0.00, (float) $balance_after - (float) ($stock->qty_reserved ?? 0));
            $this->sync_stock_to_wc($product_id, $available);
        }

        return $trans_ok;
    }

    public function sync_stock_to_wc(int $product_id, ?float $new_qty = null): void
    {
        $prod = $this->db->where('id', $product_id)->get(db_prefix() . 'inventory_products')->row();
        if ($prod && $prod->external_platform === 'woocommerce' && !empty($prod->external_id) && (int) $prod->sync_to_wc === 1) {
            try {
                if ($new_qty === null) {
                    $new_qty = $this->get_stock_available($product_id, $this->get_default_warehouse_id());
                }
                if ($this->load->is_loaded('wcsync_model') || file_exists(APP_MODULES_PATH . 'wcsync/models/Wcsync_model.php')) {
                    $this->load->model('wcsync/wcsync_model');
                    $this->wcsync_model->push_stock($product_id, $new_qty);
                }
            } catch (Throwable $e) {
                log_activity('Failed to push stock to WooCommerce for product #' . $product_id . ': ' . $e->getMessage());
            }
        }
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

    /**
     * Resolve or create product in catalog from order line item.
     */
    public function resolve_or_create_product_from_item(array $item): ?int
    {
        $db_prefix = db_prefix();

        // 1. Try matching by product_id if set
        if (!empty($item['product_id'])) {
            return (int) $item['product_id'];
        }

        // 2. Try matching by SKU
        if (!empty($item['sku'])) {
            $this->db->where('sku', trim($item['sku']));
            $p = $this->db->get($db_prefix . 'inventory_products')->row();
            if ($p) {
                return (int) $p->id;
            }

            // Auto-create product in catalog from synced order item SKU
            $this->db->insert($db_prefix . 'inventory_products', [
                'sku'           => trim($item['sku']),
                'name'          => $item['name'] ?? 'Product ' . trim($item['sku']),
                'reorder_level' => 0.00,
                'is_active'     => 1,
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
            return (int) $this->db->insert_id();
        }

        // 3. Fallback: match by product name if SKU is empty
        $name = !empty($item['name']) ? trim($item['name']) : '';
        if ($name !== '') {
            $this->db->where('name', $name);
            $p = $this->db->get($db_prefix . 'inventory_products')->row();
            if ($p) {
                return (int) $p->id;
            }

            // Auto-create product in catalog from synced order item name
            $dummy_sku = 'PRD-' . substr(md5($name), 0, 8);
            $this->db->insert($db_prefix . 'inventory_products', [
                'sku'           => $dummy_sku,
                'name'          => $name,
                'reorder_level' => 0.00,
                'is_active'     => 1,
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
            return (int) $this->db->insert_id();
        }

        return null;
    }

    public function handle_order_created(int $order_id): void
    {
        if (!$this->is_reservation_enabled()) {
            return;
        }

        $this->load->model('salesos/salesos_model');
        $order = $this->salesos_model->get_order($order_id);

        // Only reserve stock for unconfirmed / pending orders!
        if (!$order || ($order['status'] !== 'pending' && $order['status'] !== 'unconfirmed')) {
            return;
        }

        $items = $this->salesos_model->get_order_items($order_id);
        $wh_id = $this->get_default_warehouse_id();

        foreach ($items as $item) {
            $product_id = $this->resolve_or_create_product_from_item($item);
            if ($product_id) {
                $qty = (float) ($item['qty'] ?? 1);
                $this->reserve_stock(
                    $product_id,
                    $wh_id,
                    $qty,
                    'salesos_order',
                    $order_id,
                    'Stock reserved for pending order #' . $order_id
                );
            }
        }
    }

    public function handle_order_confirmed(int $order_id): void
    {
        $this->load->model('salesos/salesos_model');
        $items = $this->salesos_model->get_order_items($order_id);
        $wh_id = $this->get_default_warehouse_id();
        $db_prefix = db_prefix();
        $now = date('Y-m-d H:i:s');

        // Check which items were already reserved for this order
        $reserved_rows = $this->db->select('product_id, warehouse_id, movement_type, qty')
            ->where('ref_type', 'salesos_order')
            ->where('ref_id', $order_id)
            ->where_in('movement_type', ['reserve_hold', 'reserve_consume'])
            ->get($db_prefix . 'inventory_stock_ledger')
            ->result_array();

        $active_reservations = [];
        foreach ($reserved_rows as $rr) {
            $key = $rr['product_id'] . ':' . $rr['warehouse_id'];
            $q = abs((float) $rr['qty']);
            if (!isset($active_reservations[$key])) {
                $active_reservations[$key] = 0.00;
            }
            if ($rr['movement_type'] === 'reserve_hold') {
                $active_reservations[$key] += $q;
            } else {
                $active_reservations[$key] -= $q;
            }
        }

        foreach ($items as $item) {
            $product_id = $this->resolve_or_create_product_from_item($item);
            if (!$product_id) {
                continue;
            }

            $qty = (float) ($item['qty'] ?? 1);
            $res_key = $product_id . ':' . $wh_id;
            $reserved_qty = $active_reservations[$res_key] ?? 0.00;

            if ($reserved_qty > 0) {
                // Fulfill reservation: consume reserved quantity and deduct on_hand
                $consume_qty = min($qty, $reserved_qty);
                $active_reservations[$res_key] -= $consume_qty;

                $this->db->trans_start();

                // Atomically decrement both qty_reserved and qty_on_hand
                $this->db->query(
                    "UPDATE `{$db_prefix}inventory_stock`
                     SET qty_on_hand = qty_on_hand - ?,
                         qty_reserved = GREATEST(0.00, qty_reserved - ?),
                         updated_at = ?
                     WHERE product_id = ? AND warehouse_id = ?",
                    [$qty, $consume_qty, $now, $product_id, $wh_id]
                );

                $this->db->where('product_id', $product_id);
                $this->db->where('warehouse_id', $wh_id);
                $stock = $this->db->get($db_prefix . 'inventory_stock')->row();
                $balance_after = $stock ? (float) $stock->qty_on_hand : 0.00;

                // Record reserve_consume to balance out reserve_hold
                $this->db->insert($db_prefix . 'inventory_stock_ledger', [
                    'product_id'    => $product_id,
                    'warehouse_id'  => $wh_id,
                    'movement_type' => 'reserve_consume',
                    'qty'           => -$consume_qty,
                    'balance_after' => $balance_after,
                    'ref_type'      => 'salesos_order',
                    'ref_id'        => $order_id,
                    'staff_id'      => get_staff_user_id() ?: null,
                    'note'          => 'Reservation consumed on order confirmation',
                    'created_at'    => $now,
                ]);

                // Record actual sale_out movement
                $this->db->insert($db_prefix . 'inventory_stock_ledger', [
                    'product_id'    => $product_id,
                    'warehouse_id'  => $wh_id,
                    'movement_type' => 'sale_out',
                    'qty'           => -$qty,
                    'balance_after' => $balance_after,
                    'ref_type'      => 'salesos_order',
                    'ref_id'        => $order_id,
                    'staff_id'      => get_staff_user_id() ?: null,
                    'note'          => 'Salesos order confirmed (reservation fulfilled)',
                    'created_at'    => $now,
                ]);

                $this->db->trans_complete();

                if ($this->db->trans_status() && $stock) {
                    $available = max(0.00, (float) $stock->qty_on_hand - (float) $stock->qty_reserved);
                    $this->sync_stock_to_wc($product_id, $available);
                }
            } else {
                // Not previously reserved (e.g. POS direct sale): standard sale_out
                $deducted = $this->adjust_stock($product_id, $wh_id, -$qty, 'sale_out', 'salesos_order', $order_id, 'Salesos order confirmed');
                if (!$deducted) {
                    log_activity("Inventory: order #{$order_id} confirmed but {$qty} x product #{$product_id} could not be deducted — insufficient stock and overselling is disabled");
                }
            }
        }
    }

    public function handle_order_cancelled(int $order_id): void
    {
        // 1. Release any unconfirmed stock reservations held for this order
        $this->release_order_reservations($order_id, 'Salesos order cancelled - reservation released');

        // 2. Put back physical stock if this order was already confirmed before being cancelled
        $this->return_order_stock($order_id, 'Salesos order cancelled - stock returned');
    }

    public function handle_stock_returned(int $order_id): void
    {
        $this->return_order_stock($order_id, 'Salesos return processed - stock returned');
    }

    /**
     * Put back whatever of an order's stock has not already been put back.
     *
     * An order can reach here more than once — a courier consignment that goes
     * cancelled and then returned fires two separate events for the same goods,
     * and a cancellation can follow a partial return. Reversing every sale_out
     * row each time would credit the same units repeatedly, so this nets what
     * went out against what has already come back per product and warehouse,
     * and only moves the difference. Running it again with nothing outstanding
     * is a no-op.
     */
    private function return_order_stock(int $order_id, string $note): void
    {
        $rows = $this->db->select('product_id, warehouse_id, movement_type, qty')
            ->where('ref_type', 'salesos_order')
            ->where('ref_id', $order_id)
            ->where_in('movement_type', ['sale_out', 'return_in'])
            ->get(db_prefix() . 'inventory_stock_ledger')
            ->result_array();

        $outstanding = [];
        foreach ($rows as $row) {
            $key = $row['product_id'] . ':' . $row['warehouse_id'];
            $qty = abs((float) $row['qty']);

            if (!isset($outstanding[$key])) {
                $outstanding[$key] = [
                    'product_id'   => (int) $row['product_id'],
                    'warehouse_id' => (int) $row['warehouse_id'],
                    'qty'          => 0.00,
                ];
            }

            $outstanding[$key]['qty'] += ($row['movement_type'] === 'sale_out') ? $qty : -$qty;
        }

        foreach ($outstanding as $entry) {
            if ($entry['qty'] <= 0) {
                continue;
            }

            $this->adjust_stock(
                $entry['product_id'],
                $entry['warehouse_id'],
                $entry['qty'],
                'return_in',
                'salesos_order',
                $order_id,
                $note
            );
        }
    }

    // ── Smart Industry & IMEI Helpers ──────────────────────────────────────────

    public function get_industry_mode(): string
    {
        $mode = get_option('inventory_industry_mode');
        return !empty($mode) ? $mode : 'gadgets';
    }

    public function set_industry_mode(string $mode): bool
    {
        $valid = ['fashion', 'gadgets', 'grocery', 'general'];
        if (!in_array($mode, $valid, true)) {
            $mode = 'general';
        }
        return update_option('inventory_industry_mode', $mode);
    }

    public function add_imei_serials(int $product_id, array $serials, ?int $purchase_order_id = null): int
    {
        $count = 0;
        foreach ($serials as $sn) {
            $sn = trim((string)$sn);
            if ($sn === '') continue;

            $exists = $this->db->where('serial_number', $sn)->get(db_prefix() . 'inventory_imei_serials')->row();
            if (!$exists) {
                $this->db->insert(db_prefix() . 'inventory_imei_serials', [
                    'product_id'        => $product_id,
                    'serial_number'     => $sn,
                    'status'            => 'in_stock',
                    'purchase_order_id' => $purchase_order_id,
                    'created_at'        => date('Y-m-d H:i:s'),
                ]);
                $count++;
            }
        }
        return $count;
    }

    public function get_imei_serials(int $product_id, string $status = 'in_stock'): array
    {
        $this->db->where('product_id', $product_id);
        if ($status !== 'all') {
            $this->db->where('status', $status);
        }
        return $this->db->get(db_prefix() . 'inventory_imei_serials')->result_array();
    }
}

