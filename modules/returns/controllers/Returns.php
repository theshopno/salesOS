<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Returns extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if (function_exists('salesos_require_ecommerce')) {
            salesos_require_ecommerce();
        }
        $this->load->model('returns_model');
        if ($this->app_modules->is_active('salesos')) {
            $this->load->model('salesos/salesos_model');
        }
    }

    /**
     * List all returns
     */
    public function index()
    {
        if (!staff_can('view', 'returns')) {
            access_denied('Returns Management');
        }

        $data['title']   = 'Returns List';
        $data['returns'] = $this->returns_model->get();
        $this->load->view('returns/list', $data);
    }

    /**
     * Create a new return order
     */
    public function create()
    {
        if (!staff_can('create', 'returns')) {
            access_denied('Create Return');
        }

        if ($this->input->post()) {
            $data = $this->input->post();
            
            // Format line items from post
            $items = [];
            if (isset($data['order_item_id'])) {
                foreach ($data['order_item_id'] as $index => $item_id) {
                    $qty = (float) $data['qty'][$index];
                    if ($qty > 0) {
                        $items[] = [
                            'order_item_id'  => $item_id,
                            'qty'            => $qty,
                            'condition_note' => $data['condition_note'][$index] ?? 'sellable',
                        ];
                    }
                }
            }

            if (empty($items)) {
                set_alert('warning', 'Please select at least one item with quantity greater than zero.');
                redirect(admin_url('returns/create'));
            }

            $insert_data = [
                'salesos_order_id' => $data['salesos_order_id'],
                'reason'            => $data['reason'],
                'requested_by'      => $data['requested_by'] ?? 'staff',
                'refund_amount'     => $data['refund_amount'] ?? 0.00,
                'status'            => $data['status'] ?? 'requested',
                'items'             => $items,
            ];

            $return_id = $this->returns_model->add($insert_data);
            if ($return_id) {
                set_alert('success', 'Return order created successfully.');
                redirect(admin_url('returns/view/' . $return_id));
            } else {
                set_alert('danger', 'Failed to create return order.');
                redirect(admin_url('returns/create'));
            }
        }

        // Recent eligible orders for the picker, capped for lookup efficiency
        $data['orders'] = isset($this->salesos_model) ? $this->salesos_model->list_recent_orders(['confirmed', 'paid'], 100) : [];

        $data['title'] = 'Create Return Order';
        $this->load->view('returns/create', $data);
    }

    /**
     * Get order details for AJAX returns creation
     */
    public function get_order_details_ajax($salesos_order_id)
    {
        if (!staff_can('create', 'returns')) {
            echo json_encode(['error' => 'Permission Denied']);
            return;
        }

        if (!isset($this->salesos_model)) {
            echo json_encode([]);
            return;
        }

        $order = $this->salesos_model->get_order((int) $salesos_order_id);
        if (!$order) {
            echo json_encode([]);
            return;
        }

        $items = $this->returns_model->get_eligible_order_items($salesos_order_id);

        echo json_encode([
            'order' => [
                'id'             => $order['id'],
                'channel'        => $order['channel'],
                'channel_ref_id' => $order['channel_ref_id'],
                'total'          => $order['total'],
                'order_date'     => $order['order_date'],
            ],
            'items' => $items
        ]);
    }

    /**
     * View return order details & process status transitions
     */
    public function view($id)
    {
        if (!staff_can('view', 'returns')) {
            access_denied('View Return');
        }

        $ret = $this->returns_model->get($id);
        if (!$ret) {
            show_404();
        }

        $data['title']  = 'Return details - #' . $id;
        $data['return'] = $ret;
        $this->load->view('returns/view', $data);
    }

    /**
     * Update status endpoint
     */
    public function update_status($id)
    {
        if (!staff_can('edit', 'returns')) {
            access_denied('Process Return');
        }

        if ($this->input->post()) {
            $status = $this->input->post('status');
            $valid_statuses = ['requested', 'approved', 'rejected', 'received', 'restocked', 'refunded'];

            if (!in_array($status, $valid_statuses, true)) {
                set_alert('danger', 'Invalid return status.');
                redirect(admin_url('returns/view/' . $id));
            }

            if ($this->returns_model->update_status($id, $status)) {
                set_alert('success', 'Return status updated successfully.');
            } else {
                set_alert('danger', 'Failed to update return status.');
            }
        }
        redirect(admin_url('returns/view/' . $id));
    }

    /**
     * Delete return order
     */
    public function delete($id)
    {
        if (!staff_can('delete', 'returns')) {
            access_denied('Delete Return');
        }

        if ($this->returns_model->delete($id)) {
            set_alert('success', 'Return order deleted successfully.');
        } else {
            set_alert('danger', 'Failed to delete return order.');
        }
        redirect(admin_url('returns'));
    }
}
