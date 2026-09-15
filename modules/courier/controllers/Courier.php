<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Courier extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        salesos_require_ecommerce();
        $this->load->model('courier_model');
        $this->load->model('salesos/salesos_model');
    }

    /**
     * List & configure Courier Settings (Handles Form Submission, Redirects to Unified Salesos Settings Page)
     */
    /**
     * Every shipment and where it currently is — the screen a cash-on-delivery
     * operation lives in during the day.
     */
    public function consignments()
    {
        if (!staff_can('view', 'courier')) {
            access_denied('Courier Consignments');
        }

        $filters = [
            'status'     => $this->input->get('status') ?: '',
            'account_id' => $this->input->get('account_id') ?: '',
            'search'     => $this->input->get('search') ?: '',
        ];

        $data['title']        = 'Consignments';
        $data['filters']      = $filters;
        $data['consignments'] = $this->courier_model->get_consignments($filters);
        $data['counts']       = $this->courier_model->count_consignments_by_status();
        $data['accounts']     = $this->courier_model->get_accounts();

        $this->load->view('courier/consignments', $data);
    }

    /** Ask every courier what has happened to the shipments still in transit. */
    public function sync_statuses()
    {
        if (!staff_can('edit', 'courier')) {
            access_denied('Sync Consignments');
        }

        try {
            $synced = $this->courier_model->sync_all_active_statuses();
            set_alert('success', is_numeric($synced)
                ? $synced . ' shipment(s) checked with the courier.'
                : 'Shipment statuses checked with the courier.');
        } catch (Throwable $e) {
            set_alert('danger', 'Could not reach the courier: ' . $e->getMessage());
        }

        redirect(admin_url('courier/consignments'));
    }

    public function settings()
    {
        if (!staff_can('view', 'courier')) {
            access_denied('Courier Settings');
        }

        if ($this->input->post()) {
            if (!staff_can('edit', 'courier')) {
                access_denied('Edit Courier Settings');
            }

            try {
                $data = $this->input->post();
                $id = $this->courier_model->save_account($data);
                if ($id) {
                    set_alert('success', 'Courier Account saved successfully.');
                } else {
                    set_alert('danger', 'Failed to save courier account.');
                }
            } catch (Throwable $e) {
                set_alert('danger', $e->getMessage());
            }
        }
        redirect(admin_url('salesos/settings?tab=couriers'));
    }

    /**
     * Delete Courier Account
     */
    public function delete_account($id)
    {
        if (!staff_can('settings', 'courier')) {
            access_denied('Delete Courier Account');
        }

        $blocker = $this->courier_model->account_delete_blocker((int) $id);

        if ($blocker === null) {
            $this->courier_model->delete_account((int) $id);
            set_alert('success', 'Courier account deleted.');
        } else {
            $this->courier_model->deactivate_account((int) $id);
            set_alert('warning', 'This account could not be deleted because ' . $blocker
                . '. It has been deactivated instead, so nothing new can be booked through it '
                . 'while those shipments can still be tracked.');
        }

        redirect(admin_url('courier/settings'));
    }

    // ── Pathao Dynamic AJAX Endpoints ─────────────────────────────────────────

    public function get_pathao_cities($account_id)
    {
        if (!staff_can('view', 'courier')) {
            ajax_respond([], 403);
        }
        $cities = $this->courier_model->fetch_pathao_cities($account_id);
        echo json_encode($cities);
        exit;
    }

    public function get_pathao_zones($account_id, $city_id)
    {
        if (!staff_can('view', 'courier')) {
            ajax_respond([], 403);
        }
        $zones = $this->courier_model->fetch_pathao_zones($account_id, $city_id);
        echo json_encode($zones);
        exit;
    }

    public function get_pathao_areas($account_id, $zone_id)
    {
        if (!staff_can('view', 'courier')) {
            ajax_respond([], 403);
        }
        $areas = $this->courier_model->fetch_pathao_areas($account_id, $zone_id);
        echo json_encode($areas);
        exit;
    }

    /**
     * Book a new parcel consignment via AJAX
     */
    public function book_ajax()
    {
        if (!staff_can('create', 'courier')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }

        if ($this->input->post()) {
            try {
                $order_id      = (int) $this->input->post('salesos_order_id');
                $account_id    = (int) $this->input->post('courier_account_id');
                $cod_amount    = (float) $this->input->post('cod_amount');
                $notes         = $this->input->post('notes');

                // Additional Pathao Payload
                $additional = [
                    'recipient_city'    => $this->input->post('recipient_city'),
                    'recipient_zone'    => $this->input->post('recipient_zone'),
                    'recipient_area'    => $this->input->post('recipient_area'),
                    'delivery_type'     => $this->input->post('delivery_type'),
                    'item_type'         => $this->input->post('item_type'),
                    'item_weight'       => $this->input->post('item_weight'),
                    'item_quantity'     => $this->input->post('item_quantity')
                ];

                $db_id = $this->courier_model->book_order($order_id, $account_id, $cod_amount, $notes, $additional);
                if ($db_id) {
                    echo json_encode(['success' => true, 'message' => 'Parcel booked successfully.']);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to book parcel.']);
                }
            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }
        echo json_encode(['success' => false, 'error' => 'Invalid request.']);
        exit;
    }

    /**
     * AJAX endpoint to poll status of a single consignment
     */
    public function sync_single_status_ajax($id)
    {
        if (!staff_can('edit', 'courier')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied.']);
            exit;
        }

        $new_status = $this->courier_model->sync_single_consignment_status($id);
        if ($new_status) {
            echo json_encode(['success' => true, 'status' => strtoupper($new_status)]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No status update or api connection failed.']);
        }
        exit;
    }
}
