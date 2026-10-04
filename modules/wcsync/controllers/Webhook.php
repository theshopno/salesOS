<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Webhook extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /**
     * POST/GET /wcsync/webhook/order_created
     *
     * Ingests newly placed WooCommerce orders in real time (< 1s latency).
     * Also handles WooCommerce webhook ping handshake.
     */
    public function order_created()
    {
        try {
            $raw_input = file_get_contents('php://input');
            $method    = $this->input->method(true);
            $headers   = function_exists('getallheaders') ? getallheaders() : [];

            // Detailed debug logging
            $log_data = date('Y-m-d H:i:s') . " | Method: " . $method . "\n"
                      . "Headers: " . json_encode($headers) . "\n"
                      . "Body: " . $raw_input . "\n"
                      . "--------------------------------------------------\n";
            @file_put_contents('/home/fizz/Projects/crm/temp/wc_webhook_debug.log', $log_data, FILE_APPEND);

            // 1. Check for WooCommerce ping header or GET/HEAD
            $topic = $this->input->server('HTTP_X_WC_WEBHOOK_TOPIC');
            $event = $this->input->server('HTTP_X_WC_WEBHOOK_EVENT');

            if (in_array($method, ['GET', 'HEAD'], true)
                || $topic === 'action.woocommerce_webhook_ping'
                || $event === 'woocommerce_webhook_ping'
                || empty($raw_input)) {
                header('HTTP/1.1 200 OK');
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => true,
                    'message' => 'WooCommerce webhook endpoint active and ready',
                    'time'    => date('Y-m-d H:i:s'),
                ]);
                return;
            }

            $order_data = json_decode($raw_input, true);

            // 2. Ping with payload or non-order verification
            if (!is_array($order_data) || isset($order_data['webhook_id']) || empty($order_data['id'])) {
                header('HTTP/1.1 200 OK');
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success'    => true,
                    'message'    => 'Handshake / ping accepted',
                    'webhook_id' => $order_data['webhook_id'] ?? null,
                ]);
                return;
            }

            // 3. Process actual order payload
            if (!$this->app_modules->is_active('salesos') || !$this->app_modules->is_active('wcsync')) {
                header('HTTP/1.1 200 OK');
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => 'Modules not active']);
                return;
            }

            $this->load->model('wcsync/wcsync_model');
            $this->load->library('wcsync/woocommerce_channel');

            $sites = $this->wcsync_model->get_sites();
            $site = !empty($sites) ? $sites[0] : null;

            if (!$site) {
                header('HTTP/1.1 200 OK');
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => 'No active WooCommerce site configured']);
                return;
            }

            $result = $this->woocommerce_channel->handle_order($site, $order_data);

            header('HTTP/1.1 200 OK');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success'     => true,
                'external_id' => $order_data['id'],
                'result'      => $result,
            ]);
        } catch (\Throwable $e) {
            $err_msg = "ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString() . "\n";
            @file_put_contents('/home/fizz/Projects/crm/temp/wc_webhook_debug.log', $err_msg, FILE_APPEND);

            // Return 200 OK with error payload so WooCommerce never sees 500 error
            header('HTTP/1.1 200 OK');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
