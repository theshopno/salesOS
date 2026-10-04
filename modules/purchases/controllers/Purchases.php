<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Purchases extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if (function_exists('salesos_require_ecommerce')) {
            salesos_require_ecommerce();
        }
        $this->load->model('purchases_model');
        if (!staff_can('view', 'purchases')) {
            access_denied('Purchases Management');
        }
    }

    public function index()
    {
        redirect(admin_url('purchases/purchase_orders'));
    }

    // ── Suppliers ─────────────────────────────────────────────────────────────

    public function suppliers()
    {
        if ($this->input->post()) {
            if ($this->input->post('id')) {
                if (!staff_can('edit', 'purchases')) {
                    access_denied('Edit Supplier');
                }
                $this->purchases_model->update_supplier($this->input->post('id'), $this->input->post());
                set_alert('success', 'Supplier updated successfully.');
            } else {
                if (!staff_can('create', 'purchases')) {
                    access_denied('Create Supplier');
                }
                $this->purchases_model->add_supplier($this->input->post());
                set_alert('success', 'Supplier added successfully.');
            }
            redirect(admin_url('purchases/suppliers'));
        }

        $data['title'] = 'Suppliers';
        
        $suppliers = $this->purchases_model->get_suppliers();
        foreach ($suppliers as &$sup) {
            $sup['current_balance'] = $this->purchases_model->get_supplier_balance($sup['id']);
        }
        $data['suppliers'] = $suppliers;

        $this->load->view('purchases/suppliers', $data);
    }

    public function delete_supplier($id)
    {
        if (!staff_can('delete', 'purchases')) {
            access_denied('Delete Supplier');
        }
        $blocker = $this->purchases_model->supplier_delete_blocker((int) $id);

        if ($blocker === null) {
            $this->purchases_model->delete_supplier((int) $id);
            set_alert('success', 'Supplier deleted.');
        } else {
            $this->purchases_model->deactivate_supplier((int) $id);
            set_alert('warning', 'This supplier could not be deleted because ' . $blocker
                . '. It has been deactivated instead, so its purchase history and balance are kept.');
        }

        redirect(admin_url('purchases/suppliers'));
    }

    // ── Purchase Orders ───────────────────────────────────────────────────────

    public function purchase_orders()
    {
        if ($this->input->post()) {
            if (!staff_can('create', 'purchases')) {
                access_denied('Create Purchase Order');
            }

            // Construct items array from post inputs
            $raw_items = $this->input->post('items');
            $items = [];
            $total = 0.00;

            if (!empty($raw_items)) {
                foreach ($raw_items as $item) {
                    if (empty($item['product_id']) || empty($item['qty']) || empty($item['unit_cost'])) {
                        continue;
                    }
                    $qty = (float) $item['qty'];
                    $cost = (float) $item['unit_cost'];
                    
                    $items[] = [
                        'product_id' => (int) $item['product_id'],
                        'qty'        => $qty,
                        'unit_cost'  => $cost,
                    ];
                    $total += $qty * $cost;
                }
            }

            if (empty($items)) {
                set_alert('warning', 'A purchase order must have at least one valid item.');
                redirect(admin_url('purchases/purchase_orders'));
            }

            $po_data = [
                'supplier_id' => $this->input->post('supplier_id'),
                'order_date'  => $this->input->post('order_date'),
                'total'       => $total,
                'items'       => $items,
            ];

            $po_id = $this->purchases_model->add_purchase_order($po_data);
            if ($po_id) {
                set_alert('success', 'Purchase order created successfully.');
            } else {
                set_alert('danger', 'Failed to create purchase order.');
            }
            redirect(admin_url('purchases/purchase_orders'));
        }

        $data['title'] = 'Purchase Orders';
        
        $pos = $this->purchases_model->get_purchase_orders();
        $items_by_po = $this->purchases_model->get_purchase_order_items_for_pos(array_column($pos, 'id'));
        foreach ($pos as &$po) {
            $po['items'] = $items_by_po[(int) $po['id']] ?? [];
        }
        unset($po);
        $data['purchase_orders'] = $pos;
        $data['suppliers'] = $this->purchases_model->get_suppliers();
        
        // Load SKU products from inventory
        $data['products'] = [];
        if ($this->app_modules->is_active('inventory')) {
            $this->load->model('inventory/inventory_model');
            $data['products'] = $this->inventory_model->get_products();
        }

        $this->load->view('purchases/purchase_orders', $data);
    }

    public function delete_po($id)
    {
        if (!staff_can('delete', 'purchases')) {
            access_denied('Delete Purchase Order');
        }
        
        if ($this->purchases_model->delete_purchase_order($id)) {
            set_alert('success', 'Purchase order deleted successfully.');
        } else {
            set_alert('danger', 'Cannot delete a received purchase order.');
        }
        redirect(admin_url('purchases/purchase_orders'));
    }

    public function receive_po($id)
    {
        if (!staff_can('receive', 'purchases')) {
            access_denied('Receive Purchase Order');
        }

        if ($this->purchases_model->receive_purchase_order($id)) {
            set_alert('success', 'Purchase order marked as RECEIVED. Stock levels updated.');
        } else {
            set_alert('danger', 'Failed to receive purchase order or already received.');
        }
        redirect(admin_url('purchases/purchase_orders'));
    }

    // ── Supplier Ledger & Payments ────────────────────────────────────────────

    public function supplier_ledger($supplier_id)
    {
        if ($this->input->post()) {
            if (!staff_can('edit', 'purchases')) {
                access_denied('Record Payment');
            }

            $amount = (float) $this->input->post('amount');
            $note = $this->input->post('note');
            
            $this->purchases_model->record_supplier_payment((int) $supplier_id, $amount, $note);
            set_alert('success', 'Payment recorded in the ledger.');
            redirect(admin_url('purchases/supplier_ledger/' . $supplier_id));
        }

        $supplier = $this->purchases_model->get_supplier((int) $supplier_id);
        if (!$supplier) {
            show_404();
        }

        $data['title'] = 'Ledger: ' . $supplier['name'];
        $data['supplier'] = $supplier;
        $data['ledger'] = $this->purchases_model->get_supplier_ledger((int) $supplier_id);
        $data['current_balance'] = $this->purchases_model->get_supplier_balance((int) $supplier_id);

        $this->load->view('purchases/supplier_ledger', $data);
    }
}
