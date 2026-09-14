<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Ordernotifier_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Trigger notifications for an order event
     */
    public function notify($order_id, $event)
    {
        $db_prefix = db_prefix();

        // courier is an optional sibling module — its tables only exist when it is
        // installed, so join them conditionally rather than assuming they are there.
        $courier_active = $this->db->table_exists($db_prefix . 'courier_consignments');
        $courier_select = $courier_active
            ? "cc.tracking_id as courier_tracking_id, cc.status as courier_status, cc.courier_account_id as courier_account_id"
            : "NULL as courier_tracking_id, NULL as courier_status, NULL as courier_account_id";
        $courier_join = $courier_active
            ? "LEFT JOIN {$db_prefix}courier_consignments cc ON cc.ecomcore_order_id = o.id"
            : "";

        $sql = "
            SELECT
                o.*,
                COALESCE(
                    NULLIF(TRIM(CONCAT(con.firstname, ' ', con.lastname)), ''),
                    c.company,
                    l.name,
                    'Guest Customer'
                ) as customer_name,
                COALESCE(con.phonenumber, c.phonenumber, l.phonenumber, '') as customer_phone,
                COALESCE(c.address, l.address, '') as customer_address,
                COALESCE(con.email, l.email, '') as customer_email,
                {$courier_select}
            FROM {$db_prefix}ecomcore_orders o
            LEFT JOIN {$db_prefix}contacts con ON con.userid = o.client_id AND con.is_primary = 1
            LEFT JOIN {$db_prefix}clients c ON c.userid = o.client_id
            LEFT JOIN {$db_prefix}leads l ON l.id = o.lead_id
            {$courier_join}
            WHERE o.id = ?
        ";
        $order = $this->db->query($sql, [$order_id])->row_array();

        if (!$order || empty($order['customer_phone'])) {
            return false;
        }

        $phone = $order['customer_phone'];

        // Normalize phone number to standard Bangladeshi 11 digits (e.g. 01XXXXXXXXX)
        $phone = $this->normalize_phone($phone);
        if (strlen($phone) !== 11) {
            return false;
        }

        // Resolve Courier Name if present
        $courier_name = 'Courier Service';
        if (!empty($order['courier_account_id'])) {
            $this->db->where('id', $order['courier_account_id']);
            $acc = $this->db->get($db_prefix . 'courier_accounts')->row_array();
            if ($acc) {
                // The column is `label` — reading `name` here silently produced an
                // empty {courier_name} in every notification.
                $courier_name = $acc['label'];
            }
        }

        // Build variables for replacement
        $placeholders = [
            '{customer_name}' => $order['customer_name'],
            '{order_id}'      => $order['id'],
            '{total}'         => $order['total'],
            '{courier_name}'  => $courier_name,
            '{tracking_id}'   => $order['courier_tracking_id'] ?: '',
        ];

        $event_key = str_replace('order_', '', $event);

        // 1. Send WhatsApp notification
        if (get_option('ordernotifier_whatsapp_enabled') === '1') {
            $template = get_option('ordernotifier_template_' . $event_key . '_whatsapp');
            if (!empty($template)) {
                $message = str_replace(array_keys($placeholders), array_values($placeholders), $template);
                $this->send_whatsapp($phone, $message, $order_id, $event);
            }
        }

        // 2. Send SMS notification
        if (get_option('ordernotifier_sms_enabled') === '1') {
            $template = get_option('ordernotifier_template_' . $event_key . '_sms');
            if (!empty($template)) {
                $message = str_replace(array_keys($placeholders), array_values($placeholders), $template);
                $this->send_sms($phone, $message, $order_id, $event);
            }
        }

        return true;
    }

    /**
     * Normalize phone numbers to 11 digit format (01XXXXXXXXX)
     */
    private function normalize_phone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone, 0, 5) === '88017' || substr($phone, 0, 5) === '88018' || substr($phone, 0, 5) === '88019' || substr($phone, 0, 5) === '88015' || substr($phone, 0, 5) === '88016' || substr($phone, 0, 5) === '88013' || substr($phone, 0, 5) === '88014') {
            $phone = substr($phone, 2);
        } elseif (substr($phone, 0, 4) === '8801') {
            $phone = substr($phone, 2);
        }
        if (strlen($phone) === 10 && $phone[0] !== '0') {
            $phone = '0' . $phone;
        }
        return $phone;
    }

    /**
     * Send WhatsApp notification via bizbot API
     */
    private function send_whatsapp($phone, $message, $order_id, $event)
    {
        $base_url = get_option('ordernotifier_whatsapp_base_url') ?: 'https://api.bizbot.bd';
        $token = get_option('ordernotifier_whatsapp_token');

        if (empty($token)) {
            $this->log_notification($order_id, $phone, 'whatsapp', $event, $message, 'failed', 'Missing API Access Token');
            return false;
        }

        $url = rtrim($base_url, '/') . '/api/v1/send';
        $payload = json_encode([
            'phone'   => $phone,
            'message' => $message
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            $this->log_notification($order_id, $phone, 'whatsapp', $event, $message, 'failed', 'cURL Error: ' . $curl_error);
            return false;
        }

        if ($http_code === 200) {
            $this->log_notification($order_id, $phone, 'whatsapp', $event, $message, 'sent', $response);
            return true;
        } else {
            $this->log_notification($order_id, $phone, 'whatsapp', $event, $message, 'failed', 'HTTP Code ' . $http_code . ' | Response: ' . $response);
            return false;
        }
    }

    /**
     * Send SMS notification via Bulk SMS Gateway URL
     */
    private function send_sms($phone, $message, $order_id, $event)
    {
        $url = get_option('ordernotifier_sms_api_url');
        $api_key = get_option('ordernotifier_sms_api_key');
        $sender_id = get_option('ordernotifier_sms_sender_id');

        if (empty($url)) {
            $this->log_notification($order_id, $phone, 'sms', $event, $message, 'failed', 'Missing SMS API Gateway URL');
            return false;
        }

        // Compile universal parameters to match most gateways
        $params = [
            'api_key'   => $api_key,
            'api_token' => $api_key,
            'apiKey'    => $api_key,
            'to'        => $phone,
            'number'    => $phone,
            'receiver'  => $phone,
            'message'   => $message,
            'msg'       => $message,
            'text'      => $message,
            'sender'    => $sender_id,
            'sender_id' => $sender_id,
            'from'      => $sender_id,
        ];

        // Perform GET request
        $query_string = http_build_query($params);
        $request_url = $url;
        if (strpos($url, '?') === false) {
            $request_url .= '?' . $query_string;
        } else {
            $request_url .= '&' . $query_string;
        }

        $ch = curl_init($request_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            $this->log_notification($order_id, $phone, 'sms', $event, $message, 'failed', 'cURL Error: ' . $curl_error);
            return false;
        }

        if ($http_code === 200) {
            $this->log_notification($order_id, $phone, 'sms', $event, $message, 'sent', $response);
            return true;
        } else {
            $this->log_notification($order_id, $phone, 'sms', $event, $message, 'failed', 'HTTP Code ' . $http_code . ' | Response: ' . $response);
            return false;
        }
    }

    /**
     * Save a notification log to database
     */
    private function log_notification($order_id, $phone, $channel, $event, $message, $status, $response)
    {
        $db_prefix = db_prefix();
        $this->db->insert($db_prefix . 'ordernotifier_logs', [
            'order_id' => $order_id,
            'phone'    => $phone,
            'channel'  => $channel,
            'event'    => $event,
            'message'  => $message,
            'status'   => $status,
            'response' => $response,
            'sent_at'  => date('Y-m-d H:i:s'),
        ]);
    }
}
