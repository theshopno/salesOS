<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Returns_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get single or all return orders
     */
    public function get($id = '')
    {
        $db_prefix = db_prefix();
        if (is_numeric($id)) {
            $this->db->select("{$db_prefix}returns_orders.*, {$db_prefix}ecomcore_orders.channel, {$db_prefix}ecomcore_orders.channel_ref_id, tblstaff.firstname, tblstaff.lastname");
            $this->db->join($db_prefix . 'ecomcore_orders', $db_prefix . 'ecomcore_orders.id = ' . $db_prefix . 'returns_orders.ecomcore_order_id', 'left');
            $this->db->join('tblstaff', 'tblstaff.staffid = ' . $db_prefix . 'returns_orders.staff_id', 'left');
            $this->db->where($db_prefix . 'returns_orders.id', $id);
            $ret = $this->db->get($db_prefix . 'returns_orders')->row();
            if ($ret) {
                // Fetch line items
                $this->db->select("{$db_prefix}returns_order_items.*, {$db_prefix}ecomcore_order_items.name as product_name, {$db_prefix}ecomcore_order_items.sku as product_sku, {$db_prefix}ecomcore_order_items.product_id");
                $this->db->join($db_prefix . 'ecomcore_order_items', $db_prefix . 'ecomcore_order_items.id = ' . $db_prefix . 'returns_order_items.order_item_id', 'left');
                $this->db->where('return_order_id', $id);
                $ret->items = $this->db->get($db_prefix . 'returns_order_items')->result_array();
            }
            return $ret;
        }

        $this->db->select("{$db_prefix}returns_orders.*, {$db_prefix}ecomcore_orders.channel, {$db_prefix}ecomcore_orders.channel_ref_id");
        $this->db->join($db_prefix . 'ecomcore_orders', $db_prefix . 'ecomcore_orders.id = ' . $db_prefix . 'returns_orders.ecomcore_order_id', 'left');
        $this->db->order_by('created_at', 'DESC');
        return $this->db->get($db_prefix . 'returns_orders')->result_array();
    }

    /**
     * Add return order
     */
    public function add($data)
    {
        $db_prefix = db_prefix();
        $this->db->trans_start();

        $order_id      = (int) $data['ecomcore_order_id'];
        $reason        = isset($data['reason']) ? trim($data['reason']) : '';
        $requested_by  = isset($data['requested_by']) ? trim($data['requested_by']) : 'staff';
        $refund_amount = isset($data['refund_amount']) ? (float) $data['refund_amount'] : 0.00;
        $status        = isset($data['status']) ? trim($data['status']) : 'requested';
        $items         = $data['items'] ?? []; // Format: [ ['order_item_id' => X, 'qty' => Y, 'condition' => Z], ... ]

        $insert_data = [
            'ecomcore_order_id' => $order_id,
            'status'            => $status,
            'reason'            => $reason,
            'requested_by'      => $requested_by,
            'staff_id'          => get_staff_user_id(),
            'refund_amount'     => $refund_amount,
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ];

        $this->db->insert($db_prefix . 'returns_orders', $insert_data);
        $return_id = $this->db->insert_id();

        foreach ($items as $item) {
            $this->db->insert($db_prefix . 'returns_order_items', [
                'return_order_id' => $return_id,
                'order_item_id'   => (int) $item['order_item_id'],
                'qty'             => (float) $item['qty'],
                'condition_note'  => trim($item['condition_note'] ?? 'sellable'),
            ]);
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            return false;
        }

        // If the return is created directly as received/restocked, trigger stock update
        if ($status === 'received' || $status === 'restocked') {
            $this->trigger_stock_adjustment($return_id);
        }

        hooks()->do_action('returns_created', $return_id);
        return $return_id;
    }

    /**
     * Update return status
     */
    public function update_status($id, $status)
    {
        $db_prefix = db_prefix();
        $ret = $this->get($id);
        if (!$ret) {
            return false;
        }

        $old_status = $ret->status;
        if ($old_status === $status) {
            return true;
        }

        $this->db->where('id', $id);
        $this->db->update($db_prefix . 'returns_orders', [
            'status'     => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Trigger stock restock on receiving if not previously done
        if (($status === 'received' || $status === 'restocked') && $old_status !== 'received' && $old_status !== 'restocked') {
            $this->trigger_stock_adjustment($id);
        }

        hooks()->do_action('returns_status_changed', ['id' => $id, 'old_status' => $old_status, 'new_status' => $status]);
        return true;
    }

    /**
     * Delete a return order
     */
    public function delete($id)
    {
        $db_prefix = db_prefix();
        $this->db->where('return_order_id', $id);
        $this->db->delete($db_prefix . 'returns_order_items');

        $this->db->where('id', $id);
        $this->db->delete($db_prefix . 'returns_orders');

        return true;
    }

    /**
     * Returns list of eligible items from ecomcore order to return (checks already returned quantities)
     */
    public function get_eligible_order_items($order_id)
    {
        $db_prefix = db_prefix();
        $this->db->where('order_id', $order_id);
        $items = $this->db->get($db_prefix . 'ecomcore_order_items')->result_array();

        $eligible = [];
        foreach ($items as $item) {
            // Get already returned quantity
            $this->db->select_sum('qty');
            $this->db->where('order_item_id', $item['id']);
            // Only count approved, received, or restocked returns
            $this->db->join($db_prefix . 'returns_orders', $db_prefix . 'returns_orders.id = ' . $db_prefix . 'returns_order_items.return_order_id');
            $this->db->where_in("{$db_prefix}returns_orders.status", ['requested', 'approved', 'received', 'restocked']);
            $returned = (float) $this->db->get($db_prefix . 'returns_order_items')->row('qty') ?? 0.00;

            $max_eligible = (float) $item['qty'] - $returned;
            if ($max_eligible > 0) {
                $item['max_qty'] = $max_eligible;
                $eligible[] = $item;
            }
        }

        return $eligible;
    }

    /**
     * Triggers stock adjustments for sellable items in a return
     */
    private function trigger_stock_adjustment($return_id)
    {
        $db_prefix = db_prefix();
        $ret = $this->get($return_id);
        if (!$ret || empty($ret->items)) {
            return;
        }

        $this->load->model('inventory/inventory_model');
        $wh_id = $this->inventory_model->get_default_warehouse_id();

        foreach ($ret->items as $item) {
            // We only restock if condition is 'sellable' and product_id is valid
            if ($item['condition_note'] === 'sellable' && !empty($item['product_id'])) {
                $qty = (float) $item['qty'];
                $product_id = (int) $item['product_id'];

                $this->inventory_model->adjust_stock(
                    $product_id,
                    $wh_id,
                    $qty,
                    'return_in',
                    'return_order',
                    $return_id,
                    'Returned item restocked (Sellable) - Return #' . $return_id
                );
            }
        }
    }
}
