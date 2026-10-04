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
            ? "LEFT JOIN {$db_prefix}courier_consignments cc ON cc.salesos_order_id = o.id"
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
            FROM {$db_prefix}salesos_orders o
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

        // 0. Event-level notification toggle check
        // Default rule: 'created' is disabled (0) by default to enforce single message rule (only confirm after IVR).
        // 'confirmed', 'cancelled', 'shipped', 'delivered' are enabled (1) by default.
        $event_setting = get_option('ordernotifier_notify_on_' . $event_key);
        if ($event_setting === '') {
            $is_event_enabled = ($event_key !== 'created');
        } else {
            $is_event_enabled = ($event_setting === '1');
        }

        if (!$is_event_enabled) {
            // Notification explicitly disabled or suppressed for this event
            return false;
        }

        $whatsapp_sent = false;
        $channel_mode  = get_option('ordernotifier_channel_mode') ?: 'smart_failover';

        // 1. WhatsApp Dispatch (if enabled and channel mode allows)
        $whatsapp_allowed = in_array($channel_mode, ['smart_failover', 'whatsapp_only', 'both'], true)
            && get_option('ordernotifier_whatsapp_enabled') === '1';

        if ($whatsapp_allowed) {
            $template = get_option('ordernotifier_template_' . $event_key . '_whatsapp');
            if (empty($template)) {
                $template = $this->get_default_template('whatsapp', $event_key);
            }
            if (!empty($template)) {
                $message = str_replace(array_keys($placeholders), array_values($placeholders), $template);
                $whatsapp_sent = $this->send_whatsapp($phone, $message, $order_id, $event);
            }
        }

        // 2. SMS Dispatch (EcareSMS)
        // - 'sms_only': Always sends SMS.
        // - 'both': Always sends SMS alongside WhatsApp.
        // - 'smart_failover': Sends SMS ONLY IF WhatsApp failed or was not sent.
        $sms_enabled = get_option('ordernotifier_sms_enabled') === '1';
        $should_send_sms = false;

        if ($sms_enabled) {
            if ($channel_mode === 'sms_only' || $channel_mode === 'both') {
                $should_send_sms = true;
            } elseif ($channel_mode === 'smart_failover') {
                $should_send_sms = !$whatsapp_sent;
            }
        }

        if ($should_send_sms) {
            $template = get_option('ordernotifier_template_' . $event_key . '_sms');
            if (empty($template)) {
                $template = $this->get_default_template('sms', $event_key);
            }
            if (!empty($template)) {
                $message = str_replace(array_keys($placeholders), array_values($placeholders), $template);
                $this->send_sms($phone, $message, $order_id, $event);
            }
        }

        return true;
    }

    /**
     * Get default standard Bengali notification templates (SMS optimized under 70 chars for 1 credit)
     */
    public function get_default_template(string $channel, string $event_key): string
    {
        $defaults = [
            'sms' => [
                'created'   => 'প্রিয় {customer_name}, আপনার অর্ডার #{order_id} গৃহীত হয়েছে। মোট: {total} টাকা।',
                'confirmed' => 'অর্ডার #{order_id} কনফার্ম হয়েছে। মোট: {total} টাকা। দ্রুত পার্সেল পাঠানো হবে।',
                'shipped'   => 'অর্ডার #{order_id} কুরিয়ারে ({courier_name}) পাঠানো হয়েছে। ট্র্যাকিং: {tracking_id}',
                'delivered' => 'অর্ডার #{order_id} ডেলিভারি সম্পন্ন হয়েছে। আমাদের সাথে থাকার জন্য ধন্যবাদ!',
                'cancelled' => 'আপনার অর্ডার #{order_id} বাতিল করা হয়েছে। প্রয়োজনে যোগাযোগ করুন।',
            ],
            'whatsapp' => [
                'created'   => "প্রিয় {customer_name},\nআপনার অর্ডার #{order_id} সফলভাবে গৃহীত হয়েছে।\nমোট প্রদেয়: {total} টাকা।\n\nঅর্ডারটি কনফার্ম করতে '1' বা 'হ্যাঁ' লিখে পাঠান, অথবা বাতিল করতে '2' বা 'না' লিখে পাঠান। ধন্যবাদ!",
                'confirmed' => "প্রিয় {customer_name},\nআপনার অর্ডার #{order_id} সফলভাবে কনফার্ম করা হয়েছে।\nমোট বিল: {total} টাকা।\nদ্রুততম সময়ে আপনার পণ্যটি কুরিয়ারে হ্যান্ডওভার করা হবে।",
                'shipped'   => "প্রিয় {customer_name},\nআপনার অর্ডার #{order_id} কুরিয়ারে পাঠানো হয়েছে।\nকুরিয়ার: {courier_name}\nট্র্যাকিং নম্বর: {tracking_id}\nকালেকশন পরিমাণ: {total} টাকা।",
                'delivered' => "প্রিয় {customer_name},\nআপনার অর্ডার #{order_id} সফলভাবে ডেলিভারি সম্পন্ন হয়েছে।\nআমাদের সাথে কেনাকাটা করার জন্য ধন্যবাদ! আবার আমন্ত্রণ রইল।",
                'cancelled' => "প্রিয় {customer_name},\nআপনার অর্ডার #{order_id} বাতিল করা হয়েছে।\nকোনো জিজ্ঞাসা থাকলে আমাদের সাথে নির্দ্বিধায় যোগাযোগ করুন।",
            ]
        ];

        return $defaults[$channel][$event_key] ?? '';
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
        $channel_guid = get_option('ordernotifier_whatsapp_channel_guid') ?: get_option('bizbot_channel_guid') ?: get_option('nudge_bizbot_channel_guid');

        if (empty($token)) {
            $this->log_notification($order_id, $phone, 'whatsapp', $event, $message, 'failed', 'Missing API Access Token');
            return false;
        }

        if (!empty($channel_guid)) {
            // Official BizBot WhatsApp API shape
            $url = rtrim($base_url, '/') . '/public/v1/chat';
            $payload = json_encode([
                'channel' => $channel_guid,
                'phone'   => $phone,
                'message' => $message
            ]);
            $headers = [
                'Content-Type: application/json',
                'x-api-key: ' . $token
            ];
        } else {
            // Generic fallback
            $url = rtrim($base_url, '/') . '/api/v1/send';
            $payload = json_encode([
                'phone'   => $phone,
                'message' => $message
            ]);
            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            $this->log_notification($order_id, $phone, 'whatsapp', $event, $message, 'failed', 'cURL Error: ' . $curl_error);
            return false;
        }

        $body = json_decode($response, true);
        if ($http_code >= 200 && $http_code < 300) {
            if (is_array($body) && (isset($body['errors']) || (isset($body['success']) && $body['success'] === false))) {
                $this->log_notification($order_id, $phone, 'whatsapp', $event, $message, 'failed', 'BizBot Error: ' . ($body['errors'] ?? 'Unknown error'));
                return false;
            }
            $this->log_notification($order_id, $phone, 'whatsapp', $event, $message, 'sent', $response);
            return true;
        } else {
            $err_msg = 'HTTP ' . $http_code;
            if (is_array($body) && !empty($body['errors'])) {
                $err_msg .= ' (' . $body['errors'] . ')';
            }
            $this->log_notification($order_id, $phone, 'whatsapp', $event, $message, 'failed', $err_msg . ' | Response: ' . $response);
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

        if (strpos($url, 'ecaresms.com') !== false) {
            // eCare SMS API v3 (POST JSON with Bearer Token)
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'recipient' => $phone,
                'sender_id' => $sender_id,
                'type'      => 'plain',
                'message'   => $message,
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $api_key,
                'Accept: application/json',
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
        } else {
            // Compile universal parameters to match most GET-based gateways
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
        }

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
