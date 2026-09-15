<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Inventory extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        salesos_require_ecommerce();
        $this->load->model('inventory_model');
        if (!staff_can('view', 'inventory')) {
            access_denied('Inventory Management');
        }
    }

    public function index()
    {
        redirect(admin_url('inventory/products'));
    }

    public function products()
    {
        if ($this->input->post()) {
            if ($this->input->post('id')) {
                if (!staff_can('edit', 'inventory')) {
                    access_denied('Edit Product');
                }
                $this->inventory_model->update_product($this->input->post('id'), $this->input->post());
                set_alert('success', 'Product updated successfully.');
            } else {
                if (!staff_can('create', 'inventory')) {
                    access_denied('Create Product');
                }
                $this->inventory_model->add_product($this->input->post());
                set_alert('success', 'Product added successfully.');
            }
            redirect(admin_url('inventory/products'));
        }

        $data['title'] = 'Products Catalog';
        $data['products'] = $this->inventory_model->get_products();
        $data['categories'] = $this->inventory_model->get_categories();
        $data['items'] = $this->db->get(db_prefix() . 'items')->result_array();

        $this->load->view('inventory/products', $data);
    }

    public function delete_product($id)
    {
        if (!staff_can('delete', 'inventory')) {
            access_denied('Delete Product');
        }
        $blocker = $this->inventory_model->product_delete_blocker((int) $id);

        if ($blocker === null) {
            $this->inventory_model->delete_product((int) $id);
            set_alert('success', 'Product deleted.');
        } else {
            // Deleting it would take the stock ledger with it and leave the
            // order lines it appears on pointing at nothing.
            $this->inventory_model->deactivate_product((int) $id);
            set_alert('warning', 'This product could not be deleted because ' . $blocker
                . '. It has been deactivated instead, so it no longer appears for sale while its history is kept.');
        }

        redirect(admin_url('inventory/products'));
    }

    public function categories()
    {
        if ($this->input->post()) {
            if ($this->input->post('id')) {
                if (!staff_can('edit', 'inventory')) {
                    access_denied('Edit Category');
                }
                $this->inventory_model->update_category($this->input->post('id'), $this->input->post());
                set_alert('success', 'Category updated successfully.');
            } else {
                if (!staff_can('create', 'inventory')) {
                    access_denied('Create Category');
                }
                $this->inventory_model->add_category($this->input->post());
                set_alert('success', 'Category added successfully.');
            }
            redirect(admin_url('inventory/categories'));
        }

        $data['title'] = 'Inventory Categories';
        $data['categories'] = $this->inventory_model->get_categories();

        $this->load->view('inventory/categories', $data);
    }

    public function delete_category($id)
    {
        if (!staff_can('delete', 'inventory')) {
            access_denied('Delete Category');
        }
        $released = $this->inventory_model->count_category_products((int) $id);
        $this->inventory_model->delete_category((int) $id);

        set_alert('success', $released > 0
            ? 'Category deleted. ' . $released . ' product(s) are now uncategorised.'
            : 'Category deleted.');

        redirect(admin_url('inventory/categories'));
    }

    public function settings()
    {
        if (!staff_can('edit', 'inventory')) {
            access_denied('Inventory Settings');
        }

        if ($this->input->post()) {
            update_option('inventory_allow_oversell', $this->input->post('inventory_allow_oversell') === '1' ? '1' : '0');
            set_alert('success', 'Inventory settings saved.');
            redirect(admin_url('inventory/settings'));
        }

        $data['title']                   = 'Inventory Settings';
        $data['inventory_allow_oversell'] = get_option('inventory_allow_oversell') === '1';

        $this->load->view('inventory/settings', $data);
    }

    public function adjustments()
    {
        if ($this->input->post()) {
            if (!staff_can('adjust', 'inventory')) {
                access_denied('Adjust Stock');
            }
            
            $product_id = (int) $this->input->post('product_id');
            $qty = (float) $this->input->post('qty');
            $type = $this->input->post('movement_type'); // adjustment | purchase_in
            $note = $this->input->post('note');

            $wh_id = $this->inventory_model->get_default_warehouse_id();
            
            if ($this->inventory_model->adjust_stock($product_id, $wh_id, $qty, $type, 'manual', null, $note)) {
                set_alert('success', 'Stock adjusted successfully.');
            } else {
                $available = $this->inventory_model->get_stock_on_hand($product_id, $wh_id);
                set_alert('danger', 'Stock not adjusted — only ' . $available . ' in stock and overselling is disabled.');
            }

            redirect(admin_url('inventory/adjustments'));
        }

        $data['title'] = 'Stock Ledger & Adjustments';
        $data['ledger'] = $this->inventory_model->get_ledger();
        $data['products'] = $this->inventory_model->get_products();

        $this->load->view('inventory/adjustments', $data);
    }
}
