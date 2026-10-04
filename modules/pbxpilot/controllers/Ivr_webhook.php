<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Ivr_webhook extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /**
     * POST /pbxpilot/ivr_webhook/order_status
     *
     * Internal endpoint invoked by ami_consumer or dialplan when an IVR call resolves.
     */
    public function order_status()
    {
        try {
            $configured_secret = get_option('pbxpilot_internal_secret');
            if (empty($configured_secret)) {
                $configured_secret = 'pbxpilot_ivr_internal_secret_key_88';
                update_option('pbxpilot_internal_secret', $configured_secret);
            }

            $token    = $this->input->post_get('token');
            $order_id = (int) $this->input->post_get('order_id');
            $status   = trim((string) $this->input->post_get('status'));
            $digit    = trim((string) $this->input->post_get('digit'));
            $phone    = trim((string) $this->input->post_get('phone'));

            if (!hash_equals((string) $configured_secret, (string) $token)) {
                header('HTTP/1.0 401 Unauthorized');
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                return;
            }

            $valid_statuses = ['confirmed', 'cancelled', 'no_input', 'failed'];
            if ($order_id <= 0 || !in_array($status, $valid_statuses, true)) {
                header('HTTP/1.0 400 Bad Request');
                echo json_encode(['success' => false, 'error' => 'Invalid order_id or status']);
                return;
            }

            if (!$this->app_modules->is_active('salesos')) {
                echo json_encode(['success' => false, 'error' => 'SalesOS module is not active']);
                return;
            }

            $this->load->model('salesos/salesos_model');

            // Check current status
            $order = $this->salesos_model->get_order($order_id);
            if (!$order) {
                echo json_encode(['success' => false, 'error' => "Order #{$order_id} not found"]);
                return;
            }

            $success = true;
            if (in_array($status, ['confirmed', 'cancelled'], true)) {
                // Transition status in SalesOS kernel
                // This fires salesos_order_confirmed or salesos_order_cancelled
                $success = $this->salesos_model->set_order_status($order_id, $status);
            } else {
                // For no_input or failed, order remains pending
                // Check if maximum retries reached
                $max_retries = (int) pbxpilot_get_option('pbxpilot_ivr_max_retries', '2');
                $count = (int) $this->db->where('order_id', $order_id)->count_all_results(db_prefix() . 'pbxpilot_ivr_logs');
                if ($count >= $max_retries) {
                    $note = "IVR কল সম্পন্ন হয়নি (কোনো সাড়া মেলেনি, মোট চেষ্টা: {$count} বার)। ম্যানুয়াল কল প্রয়োজন।";
                    $this->salesos_model->log_event('order.ivr_unanswered', 'order', $order_id, ['attempts' => $count, 'reason' => $status]);
                }
            }

            // Update IVR log (or insert if record did not exist)
            $existing_log = $this->db->where('order_id', $order_id)
                ->order_by('id', 'desc')
                ->limit(1)
                ->get(db_prefix() . 'pbxpilot_ivr_logs')
                ->row_array();

            if ($existing_log) {
                $this->db->where('id', $existing_log['id'])
                    ->update(db_prefix() . 'pbxpilot_ivr_logs', [
                        'result'     => $status,
                        'dtmf_digit' => $digit ?: ($status === 'confirmed' ? '1' : ($status === 'cancelled' ? '2' : null)),
                        'notes'      => "IVR status synced to {$status} via Ivr_webhook",
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
            } else {
                $this->db->insert(db_prefix() . 'pbxpilot_ivr_logs', [
                    'order_id'   => $order_id,
                    'phone'      => $phone ?: ($order['customer_phone'] ?? 'Unknown'),
                    'result'     => $status,
                    'dtmf_digit' => $digit ?: ($status === 'confirmed' ? '1' : ($status === 'cancelled' ? '2' : null)),
                    'attempt'    => 1,
                    'notes'      => "IVR status synced to {$status} via Ivr_webhook",
                ]);
            }

            log_activity("PBX Pilot IVR Webhook: Order #{$order_id} set to {$status} (Digit: {$digit}, Phone: {$phone})");

            header('Content-Type: application/json');
            echo json_encode([
                'success'  => (bool) $success,
                'order_id' => $order_id,
                'status'   => $status,
            ]);
        } catch (\Throwable $e) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);
        }
    }
}
