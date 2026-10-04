<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Bizbot_webhook extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    public function index($key = '')
    {
        // 1. GET Request: Harmless liveness/status check
        if ($this->input->method(true) !== 'POST') {
            header('Content-Type: text/plain; charset=utf-8');
            echo "SalesOS Bizbot Webhook Endpoint is active.\nServer Time: " . date('Y-m-d H:i:s');
            return;
        }

        // 2. Secret Key Validation (if configured)
        $configured_key = get_option('salesos_bizbot_webhook_key');
        if (!empty($configured_key) && !hash_equals((string) $configured_key, (string) $key)) {
            header('HTTP/1.0 401 Unauthorized');
            echo json_encode(['error' => 'Invalid webhook key']);
            return;
        }

        // 3. Parse JSON Payload
        $raw_payload = file_get_contents('php://input');
        $payload = json_decode($raw_payload, true);

        // Log incoming payload for audit with 5MB auto-rotation
        $this->log_webhook_payload($raw_payload, $payload['event'] ?? 'none');

        if (!$payload || !isset($payload['event'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'ignored', 'reason' => 'missing_event']);
            return;
        }

        $event = $payload['event'];
        $data  = $payload['data'] ?? [];

        switch ($event) {
            case 'chat.message.created':
                $this->handle_message_created($data);
                break;

            case 'contact.created':
                $this->handle_contact_created($data);
                break;
        }

        header('Content-Type: application/json');
        echo json_encode(['status' => 'processed', 'event' => $event]);
    }

    /**
     * Handle chat.message.created event
     */
    private function handle_message_created(array $data)
    {
        $message     = $data['message'] ?? [];
        $contact     = $data['contact'] ?? [];
        $chat        = $data['chat'] ?? [];
        $thread_guid = $data['thread'] ?? ($chat['guid'] ?? ($message['thread_guid'] ?? ''));
        $msg_text    = trim($message['message'] ?? '');
        $message_id  = trim($message['id'] ?? '');
        $sender_type = strtolower(trim($message['sender_type'] ?? ''));
        $direction   = strtolower(trim($message['direction'] ?? ''));
        $is_agent    = !empty($message['from_agent'])
            || in_array($sender_type, ['agent', 'user', 'moderator', 'admin'])
            || $direction === 'outbound'
            || !empty($message['from_me']);
        $is_bot      = !empty($message['is_bot']) || $sender_type === 'bot';

        // 0. Idempotency: Deduplicate repeated webhook deliveries for the same message ID
        if (!empty($message_id) && $this->is_message_already_processed($message_id, 'chat.message.created')) {
            return;
        }

        // Check if group chat -> ignore group chats
        if (!empty($chat['is_group'])) {
            return;
        }
        $source_id = $chat['source_id'] ?? ($contact['source_id'] ?? '');
        if (strpos($source_id, '@g.us') !== false) {
            return;
        }

        // Normalize phone number (BD 11-digit or International E.164)
        $raw_phone = $contact['clean_phone'] ?? ($contact['phone'] ?? '');
        $phone     = $this->normalize_phone($raw_phone);

        // =========================================================================
        // PRIORITY 1: Explicit Moderator / Administrative Commands (#assign, #order)
        // Works whether sent from Bizbot Agent Console or WhatsApp chat directly!
        // =========================================================================

        // A. Explicit #assign command (Admin/Moderator override)
        $assigned_staff_id = $this->detect_staff_command($msg_text);
        if ($assigned_staff_id > 0) {
            $lead = $this->find_lead_by_phone_or_thread($phone, $thread_guid);
            if (!$lead && !$this->is_excluded_contact($phone)) {
                $raw_name = trim($contact['name'] ?? '');
                $name     = ($raw_name !== '') ? $raw_name : 'WhatsApp Lead';
                $this->sync_inbound_lead($phone, $name, $thread_guid, $msg_text, $data);
            }
            $this->assign_lead_to_staff($phone, $thread_guid, $assigned_staff_id, 'via command: ' . $msg_text, true);
            return;
        }

        // B. Explicit #order command
        if (get_option('salesos_bizbot_moderator_order_enabled') !== '0') {
            $order_keyword = trim(get_option('salesos_bizbot_order_keyword') ?: '#order');
            if ($order_keyword !== '' && mb_stripos($msg_text, $order_keyword) === 0) {
                $this->handle_moderator_order_command($data, $msg_text, $order_keyword);
                return;
            }
        }

        // C. Explicit #lead command (Manual Lead Conversion Trigger)
        $is_lead_command = $this->is_manual_lead_trigger($msg_text);
        if ($is_lead_command) {
            if (!$this->is_excluded_contact($phone)) {
                $raw_name = trim($contact['name'] ?? '');
                $name     = ($raw_name !== '') ? $raw_name : 'WhatsApp Lead';
                $this->sync_inbound_lead($phone, $name, $thread_guid, $msg_text, $data);
            }
            if (!$is_agent) {
                // Customer/lead manually sent trigger -> lead created, finish
                return;
            }
        }

        // =========================================================================
        // PRIORITY 2: Moderator / Agent Reply Auto-Assignment (Outbound)
        // =========================================================================
        if ($is_agent) {
            $this->handle_agent_reply_assignment($data, $msg_text, $phone, $thread_guid);
            return;
        }

        // Ignore bot outbound sends
        if ($is_bot) {
            return;
        }

        // =========================================================================
        // PRIORITY 2.5: Inbound Two-Way Order Confirmation / Cancellation Loop
        // =========================================================================
        if (get_option('salesos_bizbot_twoway_confirm_enabled') !== '0' && !empty($phone)) {
            if ($this->handle_two_way_order_reply($phone, $msg_text)) {
                return;
            }
        }

        // =========================================================================
        // PRIORITY 3: Inbound Customer Message Handling (Facebook Ads / Leads)
        // =========================================================================
        if (get_option('salesos_bizbot_inbound_enabled') === '0') {
            return;
        }

        if (empty($phone)) {
            return;
        }

        // 1. Exclusions check (Staff, Suppliers, Blacklisted numbers)
        if ($this->is_excluded_contact($phone)) {
            $this->log_webhook_payload($phone, "Inbound Skipped: $phone is excluded (Staff, Supplier, or Blacklist)");
            return;
        }

        // Name: If found from WhatsApp, use it; else exactly 'WhatsApp Lead'
        $raw_name = trim($contact['name'] ?? '');
        $name     = ($raw_name !== '') ? $raw_name : 'WhatsApp Lead';

        $capture_mode = get_option('salesos_bizbot_capture_mode') ?: 'smart';
        $phone_suffix = substr($phone, -10);

        // Check if already an existing Lead in tblleads
        $existing_lead = $this->db->query(
            "SELECT id, name, status, assigned FROM " . db_prefix() . "leads
             WHERE phone_suffix10 = ? OR phonenumber LIKE ? OR phonenumber = ? LIMIT 1",
            [$phone_suffix, '%' . $phone_suffix, $phone]
        )->row();

        // If NOT an existing lead, apply Smart Mode filters:
        if (!$existing_lead && $capture_mode === 'smart') {
            $is_ad     = $this->is_fb_ad_referral($data);
            $is_intent = $this->has_buyer_intent($msg_text);

            if (!$is_ad && !$is_intent && !$is_lead_command) {
                // Casual / non-commercial message without lead trigger -> Keep in Bizbot only
                $this->log_webhook_payload($phone, "Smart Mode Skipped: $phone (No ad referral, intent, or #lead)");
                return;
            }
        }

        // Inbound leads are created as unassigned so the first replying agent claims them!
        $this->sync_inbound_lead($phone, $name, $thread_guid, $msg_text, $data);
    }

    /**
     * Handle contact.created event
     */
    private function handle_contact_created(array $data)
    {
        if (get_option('salesos_bizbot_inbound_enabled') === '0') {
            return;
        }
        $contact   = $data['contact'] ?? $data;
        $raw_phone = $contact['clean_phone'] ?? ($contact['phone'] ?? '');
        $phone     = $this->normalize_phone($raw_phone);
        if (empty($phone)) {
            return;
        }

        if ($this->is_excluded_contact($phone)) {
            return;
        }

        $capture_mode = get_option('salesos_bizbot_capture_mode') ?: 'smart';
        if ($capture_mode === 'smart') {
            // In smart mode, do not create lead on bare contact.created unless ad referral is present
            if (!$this->is_fb_ad_referral($data)) {
                return;
            }
        }

        $raw_name = trim($contact['name'] ?? '');
        $name     = ($raw_name !== '') ? $raw_name : 'WhatsApp Lead';

        $this->sync_inbound_lead($phone, $name, '', 'Contact Created in Bizbot', $data);
    }

    /**
     * Deduplication & Lead Sync with Atomic Concurrency Guard (MySQL GET_LOCK)
     */
    private function sync_inbound_lead(string $phone, string $name, string $thread_guid, string $last_msg, array $data = [])
    {
        if (empty($phone)) {
            return;
        }

        $phone_suffix = substr($phone, -10);
        $lock_name    = 'salesos_lead_' . $phone;
        $this->db->query("SELECT GET_LOCK(?, 5)", [$lock_name]);

        try {
            // 1. Check if already an existing Customer (tblclients / tblcontacts)
            $existing_client = $this->db->query(
                "SELECT c.userid, c.company FROM " . db_prefix() . "clients c
                 LEFT JOIN " . db_prefix() . "contacts con ON con.userid = c.userid
                 WHERE c.phonenumber LIKE ? OR con.phonenumber LIKE ? OR c.phonenumber = ? LIMIT 1",
                ['%' . $phone_suffix, '%' . $phone_suffix, $phone]
            )->row();

            if ($existing_client) {
                // Already a customer - log interaction to preserve CRM audit trail
                log_activity("Bizbot: WhatsApp message received from existing customer #{$existing_client->userid} ({$phone})");
                return;
            }

            // 2. Check if already an existing Lead in tblleads (Re-checked inside the lock!)
            $existing_lead = $this->db->query(
                "SELECT id, name, status, assigned FROM " . db_prefix() . "leads
                 WHERE phone_suffix10 = ? OR phonenumber LIKE ? OR phonenumber = ? LIMIT 1",
                [$phone_suffix, '%' . $phone_suffix, $phone]
            )->row();

            if ($existing_lead) {
                // Update existing lead activity
                $update = ['lastcontact' => date('Y-m-d H:i:s')];
                // If lead name was default 'WhatsApp Lead' and we now have a real name, update it
                if ($existing_lead->name === 'WhatsApp Lead' && $name !== 'WhatsApp Lead') {
                    $update['name'] = $name;
                }
                // IMPORTANT SAFETY RULE: Never overwrite assignment on customer messages!
                $this->db->where('id', $existing_lead->id)->update(db_prefix() . 'leads', $update);

                if (!empty($thread_guid)) {
                    $this->save_thread_guid($existing_lead->id, $thread_guid);
                }
                return;
            }

            // 3. Create New Lead as UNASSIGNED (assigned = 0) so the first replying agent claims it!
            $status_id = (int) (get_option('salesos_bizbot_default_lead_status') ?: 2);
            $source_id = $this->get_or_create_bizbot_source_id();

            $desc = 'Captured via WhatsApp message';
            $message  = $data['message'] ?? [];
            $chat     = $data['chat'] ?? [];
            $referral = $message['referral'] ?? ($data['referral'] ?? ($chat['referral'] ?? ($message['context']['referral'] ?? [])));
            if (!empty($referral) && is_array($referral)) {
                $headline = $referral['headline'] ?? '';
                $source_id_ref = $referral['source_id'] ?? '';
                $desc = 'Captured via Facebook Ad';
                if ($headline !== '') {
                    $desc .= ": \"$headline\"";
                }
                if ($source_id_ref !== '') {
                    $desc .= " (Ad ID: $source_id_ref)";
                }
            }
            if ($thread_guid) {
                $desc .= " (Thread: $thread_guid)";
            }

            $lead_data = [
                'name'        => $name,
                'phonenumber' => $phone,
                'status'      => $status_id,
                'source'      => $source_id,
                'assigned'    => 0, // Unassigned - waiting for first agent reply/claim
                'description' => $desc,
                'dateadded'   => date('Y-m-d H:i:s'),
                'lastcontact' => date('Y-m-d H:i:s'),
                'addedfrom'   => 0,
            ];

            $this->load->model('leads_model');
            $lead_id = $this->leads_model->add($lead_data);

            if ($lead_id) {
                if (!empty($thread_guid)) {
                    $this->save_thread_guid($lead_id, $thread_guid);
                }
                $this->leads_model->log_lead_activity($lead_id, 'WhatsApp: new lead auto-created from inbound message (unassigned)', true);
            }
        } finally {
            $this->db->query("SELECT RELEASE_LOCK(?)", [$lock_name]);
        }
    }

    /**
     * Moderator Command-Based Order Creator (#order ...)
     */
    private function handle_moderator_order_command(array $data, string $msg_text, string $keyword)
    {
        $contact     = $data['contact'] ?? [];
        $chat        = $data['chat'] ?? [];
        $thread_guid = $data['thread'] ?? ($chat['guid'] ?? '');
        $message_id  = $data['message']['id'] ?? uniqid();

        $raw_phone = $contact['clean_phone'] ?? ($contact['phone'] ?? '');
        $phone     = $this->normalize_phone($raw_phone);
        if (empty($phone)) {
            return;
        }

        $customer_name = trim($contact['name'] ?? '');
        if ($customer_name === '' || $customer_name === 'WhatsApp Lead') {
            $customer_name = '';
        }

        // Strip keyword
        $command_body = trim(mb_substr($msg_text, mb_strlen($keyword)));
        if (empty($command_body)) {
            return;
        }

        // Parse command: supports single-line or multi-line
        $parsed          = $this->parse_order_command($command_body);
        $sku             = $parsed['sku'];
        $qty             = $parsed['qty'] > 0 ? $parsed['qty'] : 1.00;
        $shipping_charge = (float) ($parsed['shipping'] ?? 0.00);
        $address         = $parsed['address'] ?: ($contact['address'] ?? '');

        if (empty($sku)) {
            return;
        }

        // Lookup product by SKU or name in inventory products (or fallback to tblitems if inventory is not installed)
        $product = null;
        if ($this->db->table_exists(db_prefix() . 'inventory_products')) {
            $product = $this->db->query(
                "SELECT p.id, p.sku, p.name, p.item_id, COALESCE(i.rate, 0) as rate
                 FROM " . db_prefix() . "inventory_products p
                 LEFT JOIN " . db_prefix() . "items i ON i.id = p.item_id
                 WHERE p.is_active = 1 AND (p.sku = ? OR p.name LIKE ?)
                 ORDER BY (p.sku = ?) DESC LIMIT 1",
                [$sku, '%' . $sku . '%', $sku]
            )->row();
        } else {
            $product = $this->db->query(
                "SELECT id, description as name, rate, id as item_id, '' as sku
                 FROM " . db_prefix() . "items
                 WHERE description LIKE ? LIMIT 1",
                ['%' . $sku . '%']
            )->row();
        }

        if (!$product) {
            log_activity("Bizbot Webhook: #order failed, product not found for SKU: " . $sku);
            return;
        }

        $unit_price = (float) $product->rate;
        $subtotal   = $qty * $unit_price;
        $total      = $subtotal + $shipping_charge;

        $this->load->model('salesos/salesos_model');

        $generic_order = [
            'channel'         => 'whatsapp',
            'channel_ref_id'  => 'wa_' . $message_id,
            'status'          => 'pending', // Lands in SalesOS -> Confirmations gate
            'subtotal'        => $subtotal,
            'shipping_charge' => $shipping_charge,
            'total'           => $total,
            'currency'        => 'BDT',
            'payment_method'  => 'cod',
            'order_note'      => 'Moderator WhatsApp Order: ' . $msg_text,
            'order_date'      => date('Y-m-d H:i:s'),
            'customer' => [
                'phone'        => $phone,
                'name'         => $customer_name ?: 'Customer - ' . $phone,
                'email'        => $contact['email'] ?? '',
                'address'      => $address,
                'city'         => $contact['city'] ?? '',
                'country_code' => 'BD',
            ],
            'items' => [
                [
                    'product_id' => (int) $product->id,
                    'sku'        => $product->sku,
                    'name'       => $product->name,
                    'qty'        => $qty,
                    'unit_price' => $unit_price,
                ]
            ]
        ];

        $order_id = $this->salesos_model->import_order($generic_order);

        if ($order_id > 0) {
            // Auto convert lead to customer
            $phone_suffix = substr($phone, -10);
            $lead = $this->db->query(
                "SELECT id FROM " . db_prefix() . "leads WHERE phone_suffix10 = ? LIMIT 1",
                [$phone_suffix]
            )->row();

            if ($lead) {
                $this->salesos_model->convert_lead_to_customer((int) $lead->id, [
                    'company' => $customer_name,
                    'address' => $address,
                ]);
            }

            // Reply back via Bizbot WhatsApp Client
            $reply_text = "অর্ডার #{$order_id} তৈরি হয়েছে!\nপণ্য: {$product->name}\nপরিমাণ: {$qty}\nসাবটোটাল: {$subtotal} টাকা";
            if ($shipping_charge > 0) {
                $reply_text .= "\nডেলিভারি চার্জ: {$shipping_charge} টাকা";
            }
            $reply_text .= "\nসর্বমোট: {$total} টাকা।";
            $this->send_whatsapp_reply($phone, $reply_text);
        }
    }

    /**
     * Parse #order command body
     */
    private function parse_order_command(string $text): array
    {
        $sku      = '';
        $qty      = 1.00;
        $shipping = 0.00;
        $address  = '';

        $lines = preg_split('/\r\n|\r|\n/', trim($text));

        if (count($lines) > 1) {
            // Multi-line syntax
            foreach ($lines as $line) {
                $line = trim($line);
                if (preg_match('/^(?:sku|product|item|কোড):\s*(.+)$/iu', $line, $m)) {
                    $sku = trim($m[1]);
                } elseif (preg_match('/^(?:qty|quantity|পরিমাণ):\s*(\d+(?:\.\d+)?)/iu', $line, $m)) {
                    $qty = (float) $m[1];
                } elseif (preg_match('/^(?:shipping|delivery|charge|ডেলিভারি|ভাড়া):\s*(\d+(?:\.\d+)?)/iu', $line, $m)) {
                    $shipping = (float) $m[1];
                } elseif (preg_match('/^(?:address|ঠিকানা|note|নোট):\s*(.+)$/iu', $line, $m)) {
                    $address = trim($m[1]);
                }
            }
        }

        // If not found via multi-line, parse single line: <SKU> [QTY] [ADDRESS]
        if (empty($sku)) {
            $tokens = preg_split('/\s+/', trim($lines[0]), 3);
            if (!empty($tokens[0])) {
                $sku = $tokens[0];
            }
            if (isset($tokens[1])) {
                if (is_numeric($tokens[1])) {
                    $qty = (float) $tokens[1];
                    if (isset($tokens[2])) {
                        $address = trim($tokens[2]);
                    }
                } else {
                    $address = trim($tokens[1] . (isset($tokens[2]) ? ' ' . $tokens[2] : ''));
                }
            }
        }

        // Extract inline delivery/shipping keyword if present in address
        if (preg_match('/\b(?:shipping|delivery|ডেলিভারি|ভাড়া)[:\s]+(\d+(?:\.\d+)?)/iu', $address, $sm)) {
            if ($shipping === 0.0) {
                $shipping = (float) $sm[1];
            }
            $address = trim(preg_replace('/\b(?:shipping|delivery|ডেলিভারি|ভাড়া)[:\s]+(\d+(?:\.\d+)?)/iu', '', $address));
        }

        return ['sku' => $sku, 'qty' => $qty, 'shipping' => $shipping, 'address' => $address];
    }

    /**
     * Send outbound WhatsApp reply via Bizbot
     */
    private function send_whatsapp_reply(string $phone, string $message)
    {
        $token        = get_option('ordernotifier_whatsapp_token') ?: get_option('nudge_bizbot_api_key');
        $channel_guid = get_option('ordernotifier_whatsapp_channel_guid') ?: get_option('bizbot_channel_guid');
        $base_url     = get_option('ordernotifier_whatsapp_base_url') ?: 'https://api.bizbot.bd';

        if (empty($token) || empty($channel_guid)) {
            return;
        }

        $url = rtrim($base_url, '/') . '/public/v1/chat';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'channel' => $channel_guid,
            'phone'   => $phone,
            'message' => $message,
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-key: ' . $token,
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Handle inbound customer reply for two-way order confirmation/cancellation.
     */
    private function handle_two_way_order_reply(string $phone, string $msg_text): bool
    {
        $clean_text = trim($msg_text);
        if ($clean_text === '') {
            return false;
        }

        $intent = $this->detect_confirmation_intent($clean_text);
        if (!$intent) {
            return false;
        }

        $pending_order = $this->find_pending_order_for_phone($phone, $clean_text);
        if (!$pending_order) {
            return false;
        }

        $this->load->model('salesos/salesos_model');
        $order_id = (int) $pending_order->id;

        if ($intent === 'confirm') {
            $ok = $this->salesos_model->set_order_status($order_id, 'confirmed');
            if ($ok) {
                log_activity("SalesOS Two-Way: Order #{$order_id} confirmed via WhatsApp reply from {$phone}: '{$msg_text}'");

                $confirm_reply = get_option('salesos_bizbot_twoway_reply_confirm');
                if (empty($confirm_reply)) {
                    $confirm_reply = "প্রিয় কাস্টমার, আপনার অর্ডার #{order_id} সফলভাবে কনফার্ম করা হয়েছে! মোট বিল: {total} টাকা। দ্রুততম সময়ে আপনার পণ্যটি কুরিয়ারে পাঠানো হবে। আন্তরিক ধন্যবাদ!";
                }
                $reply_message = str_replace(
                    ['{order_id}', '{total}'],
                    [$order_id, number_format((float) ($pending_order->total ?? 0), 2, '.', '')],
                    $confirm_reply
                );
                $this->send_whatsapp_reply($phone, $reply_message);
                return true;
            }
        } elseif ($intent === 'cancel') {
            $ok = $this->salesos_model->set_order_status($order_id, 'cancelled');
            if ($ok) {
                log_activity("SalesOS Two-Way: Order #{$order_id} cancelled via WhatsApp reply from {$phone}: '{$msg_text}'");

                $cancel_reply = get_option('salesos_bizbot_twoway_reply_cancel');
                if (empty($cancel_reply)) {
                    $cancel_reply = "প্রিয় কাস্টমার, আপনার অনুরোধে অর্ডার #{order_id} বাতিল করা হয়েছে। যেকোনো প্রয়োজনে আমাদের সাথে যোগাযোগ করুন। ধন্যবাদ!";
                }
                $reply_message = str_replace(
                    ['{order_id}', '{total}'],
                    [$order_id, number_format((float) ($pending_order->total ?? 0), 2, '.', '')],
                    $cancel_reply
                );
                $this->send_whatsapp_reply($phone, $reply_message);
                return true;
            }
        }

        return false;
    }

    /**
     * Detect if text expresses confirmation or cancellation intent.
     * Returns 'confirm', 'cancel', or null.
     */
    private function detect_confirmation_intent(string $text): ?string
    {
        $normalized = mb_strtolower(trim($text), 'UTF-8');
        // Remove common punctuation except digits and letters
        $stripped = trim(preg_replace('/[#!?,.:;।_]/u', '', $normalized));

        // Exact match tokens for confirm
        $confirm_tokens = [
            '1', '১', 'yes', 'y', 'ha', 'haa', 'hae', 'ok', 'confirm', 'confarm', 'done',
            'হ্যাঁ', 'হ্যা', 'হুম', 'হাঁ', 'কনফার্ম', 'পাঠিয়ে দিন', 'পাঠান', 'নিব', 'অর্ডার দিন'
        ];
        if (in_array($stripped, $confirm_tokens, true)) {
            return 'confirm';
        }

        // Exact match tokens for cancel
        $cancel_tokens = [
            '2', '২', 'no', 'n', 'na', 'nah', 'cancel', 'cancle',
            'না', 'নাহ', 'বাতিল', 'ক্যান্সেল', 'লাগবে না', 'ভুল অর্ডার', 'ভুল হয়েছে'
        ];
        if (in_array($stripped, $cancel_tokens, true)) {
            return 'cancel';
        }

        // Regex pattern checks
        if (preg_match('/^(?:1|১|yes|confirm|confarm|হ্যাঁ|হ্যা|হুম|কনফার্ম)(?:\s+(?:order|অর্ডার|\d+))?$/iu', $normalized)
            || preg_match('/\b(?:confirm|confarm|কনফার্ম|পাঠিয়ে দিন|পাঠান)\b/iu', $normalized)) {
            return 'confirm';
        }

        if (preg_match('/^(?:2|২|no|cancel|cancle|না|নাহ|বাতিল|ক্যান্সেল)(?:\s+(?:order|অর্ডার|\d+))?$/iu', $normalized)
            || preg_match('/\b(?:cancel|cancle|বাতিল|ক্যান্সেল|লাগবে না|ভুল অর্ডার)\b/iu', $normalized)) {
            return 'cancel';
        }

        return null;
    }

    /**
     * Find the pending order for the given phone number or explicit order ID in message.
     */
    private function find_pending_order_for_phone(string $phone, string $msg_text): ?object
    {
        $db_prefix = db_prefix();

        // 1. Check if an explicit order ID is mentioned in the message (e.g. #123, order 123, 123 confirm)
        if (preg_match('/(?:#|order\s*#?|অর্ডার\s*#?|\b)(\d{1,8})\b/iu', $msg_text, $m)) {
            $explicit_id = (int) $m[1];
            if ($explicit_id > 0) {
                $order = $this->db->where('id', $explicit_id)
                    ->where('status', 'pending')
                    ->get($db_prefix . 'salesos_orders')
                    ->row();
                if ($order) {
                    return $order;
                }
            }
        }

        // 2. Lookup recent pending order by phone suffix
        $clean_phone = preg_replace('/[^0-9]/', '', $phone);
        $phone_suffix = substr($clean_phone, -10);

        if (empty($phone_suffix)) {
            return null;
        }

        $sql = "
            SELECT o.*
            FROM {$db_prefix}salesos_orders o
            LEFT JOIN {$db_prefix}contacts con ON con.userid = o.client_id AND con.is_primary = 1
            LEFT JOIN {$db_prefix}clients c ON c.userid = o.client_id
            LEFT JOIN {$db_prefix}leads l ON l.id = o.lead_id
            WHERE o.status = 'pending'
              AND (
                  con.phonenumber LIKE ?
                  OR c.phonenumber LIKE ?
                  OR l.phonenumber LIKE ?
                  OR l.phone_suffix10 = ?
                  OR o.order_note LIKE ?
              )
            ORDER BY o.id DESC
            LIMIT 1
        ";

        $param = '%' . $phone_suffix;
        $order = $this->db->query($sql, [$param, $param, $param, $phone_suffix, '%' . $phone_suffix . '%'])->row();

        return $order ?: null;
    }

    /**
     * Normalize Phone Numbers:
     * - BD Numbers: 11 digits (01XXXXXXXXX)
     * - International Numbers: Clean E.164 (without +, 8-15 digits)
     */
    private function normalize_phone(string $phone): string
    {
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (empty($clean)) {
            return '';
        }

        // BD formatting
        if (strpos($clean, '8801') === 0 && strlen($clean) === 13) {
            $clean = substr($clean, 2);
        } elseif (strpos($clean, '1') === 0 && strlen($clean) === 10) {
            $clean = '0' . $clean;
        }

        if (strlen($clean) === 11 && strpos($clean, '01') === 0) {
            return $clean;
        }

        // Expatriate / International E.164 (e.g. 971501234567, 966501234567, 12025550199)
        $len = strlen($clean);
        if ($len >= 8 && $len <= 15) {
            return $clean;
        }

        return '';
    }

    /**
     * Resolve CRM Staff ID from Bizbot Agent GUID mapping
     */
    private function resolve_staff_from_agent_guid(string $agent_guid): int
    {
        if (empty($agent_guid)) return 0;

        $mapping_raw = get_option('salesos_bizbot_agent_mapping');
        if (!empty($mapping_raw)) {
            $mapping = json_decode($mapping_raw, true);
            if (is_array($mapping) && isset($mapping[$agent_guid])) {
                return (int) $mapping[$agent_guid];
            }
        }

        return 0;
    }

    /**
     * Record an unmapped Bizbot Agent GUID into the discovery queue
     * so admins can assign it to a staff member in 1-click from the Unified Hub.
     */
    private function record_unmapped_bizbot_agent(string $guid, string $name = '')
    {
        if (empty($guid)) return;

        $unmapped = json_decode(get_option('salesos_bizbot_unmapped_agents') ?: '[]', true) ?: [];
        $unmapped[$guid] = [
            'guid'      => $guid,
            'name'      => $name ?: 'Bizbot Agent',
            'last_seen' => date('Y-m-d H:i:s'),
        ];

        // Keep at most 20 recent unmapped items
        if (count($unmapped) > 20) {
            $unmapped = array_slice($unmapped, -20, null, true);
        }

        update_option('salesos_bizbot_unmapped_agents', json_encode($unmapped, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Save Bizbot Thread GUID into leads custom fields
     */
    private function save_thread_guid(int $lead_id, string $thread_guid)
    {
        if (empty($thread_guid)) return;

        $field = $this->db->get_where(db_prefix() . 'customfields', [
            'slug'    => 'leads_bizbot_thread_guid',
            'fieldto' => 'leads'
        ])->row();

        if ($field) {
            $val = $this->db->get_where(db_prefix() . 'customfieldsvalues', [
                'relid'   => $lead_id,
                'fieldid' => $field->id,
                'fieldto' => 'leads'
            ])->row();

            if ($val) {
                $this->db->where('id', $val->id)->update(db_prefix() . 'customfieldsvalues', ['value' => $thread_guid]);
            } else {
                $this->db->insert(db_prefix() . 'customfieldsvalues', [
                    'relid'   => $lead_id,
                    'fieldid' => $field->id,
                    'fieldto' => 'leads',
                    'value'   => $thread_guid
                ]);
            }
        }
    }

    /**
     * Get or create Bizbot source in tblleads_sources
     */
    private function get_or_create_bizbot_source_id(): int
    {
        $row = $this->db->get_where(db_prefix() . 'leads_sources', ['name' => 'Bizbot'])->row();
        if ($row) {
            return (int) $row->id;
        }
        $this->db->insert(db_prefix() . 'leads_sources', ['name' => 'Bizbot']);
        return (int) $this->db->insert_id();
    }

    /**
     * Handle Auto-Assignment when an agent/moderator replies to a customer
     */
    private function handle_agent_reply_assignment(array $data, string $msg_text, string $phone, string $thread_guid)
    {
        if (get_option('salesos_bizbot_auto_assign_reply') === '0') {
            return;
        }

        $lead = $this->find_lead_by_phone_or_thread($phone, $thread_guid);
        $is_locked_mode = (get_option('salesos_bizbot_lock_assigned_leads') !== '0');

        // CRITICAL SAFETY CHECK:
        // If this lead is already assigned to a staff member and lock mode is active,
        // ignore all conversational introductions and auto-claims! It stays with the original agent!
        if ($lead && (int)$lead->assigned > 0 && $is_locked_mode) {
            $this->db->where('id', $lead->id)->update(db_prefix() . 'leads', [
                'lastcontact' => date('Y-m-d H:i:s'),
            ]);
            return;
        }

        $message = $data['message'] ?? [];
        $contact = $data['contact'] ?? [];
        $chat    = $data['chat'] ?? [];

        $staff_id = 0;
        $reason   = '';

        // Priority 1: Check self-introduction in message text
        // e.g. "আমি আল আমিন বলছি", "আমি মোস্তাফিজুর", "This is Pakhi"
        if (!empty($msg_text)) {
            $intro_staff = $this->detect_staff_intro($msg_text);
            if ($intro_staff > 0) {
                $staff_id = $intro_staff;
                $reason   = 'Agent identified via introduction in WhatsApp reply';
            }
        }

        // Priority 2: Explicit Agent GUID on message itself (from Bizbot agent web console)
        if ($staff_id === 0) {
            $sender_agent_guid = $message['from_guid'] ?? ($message['sender_id'] ?? ($message['agent_id'] ?? ''));
            if (!empty($sender_agent_guid)) {
                $resolved = $this->resolve_staff_from_agent_guid($sender_agent_guid);
                if ($resolved > 0) {
                    $staff_id = $resolved;
                    $reason   = 'Bizbot agent console reply (Agent ID: ' . $sender_agent_guid . ')';
                } else {
                    // Auto-record in discovery queue for 1-click admin assignment
                    $this->record_unmapped_bizbot_agent($sender_agent_guid, $message['sender_name'] ?? ($message['sender'] ?? ''));
                }
            }
        }

        // Priority 3: Ambient contact/chat assignment (only as a fallback if lead has never been claimed)
        if ($staff_id === 0) {
            $ambient_guid = $contact['assigned_to'] ?? ($chat['assigned_to'] ?? '');
            if (!empty($ambient_guid)) {
                $resolved = $this->resolve_staff_from_agent_guid($ambient_guid);
                if ($resolved > 0) {
                    $staff_id = $resolved;
                    $reason   = 'Bizbot chat assignment (' . $ambient_guid . ')';
                }
            }
        }

        if ($staff_id > 0) {
            $this->assign_lead_to_staff($phone, $thread_guid, $staff_id, $reason, false);
        }
    }

    /**
     * Reassign or assign lead to a specific staff member
     *
     * @param string $phone Customer phone
     * @param string $thread_guid Bizbot thread GUID
     * @param int $staff_id Target staff ID
     * @param string $reason Activity log explanation
     * @param bool $force_override True for explicit #assign command, False for conversational auto-claim
     * @return bool
     */
    private function assign_lead_to_staff(string $phone, string $thread_guid, int $staff_id, string $reason = '', bool $force_override = false): bool
    {
        $lead = $this->find_lead_by_phone_or_thread($phone, $thread_guid);
        if (!$lead) {
            return false;
        }

        $current_assigned = (int) $lead->assigned;
        $is_locked_mode   = (get_option('salesos_bizbot_lock_assigned_leads') !== '0');

        // SAFETY LOCK: If already assigned and this is NOT an explicit command, do not reassign!
        if ($current_assigned > 0 && !$force_override && $is_locked_mode) {
            $this->db->where('id', $lead->id)->update(db_prefix() . 'leads', [
                'lastcontact' => date('Y-m-d H:i:s'),
            ]);
            return false;
        }

        // If already assigned to the same person, just bump lastcontact
        if ($current_assigned === $staff_id) {
            $this->db->where('id', $lead->id)->update(db_prefix() . 'leads', [
                'lastcontact' => date('Y-m-d H:i:s'),
            ]);
            return true;
        }

        $old_staff_name = $this->get_staff_name($current_assigned);
        $new_staff_name = $this->get_staff_name($staff_id);

        $this->db->where('id', $lead->id)->update(db_prefix() . 'leads', [
            'assigned'    => $staff_id,
            'lastcontact' => date('Y-m-d H:i:s'),
        ]);

        $this->load->model('leads_model');
        if ($current_assigned > 0) {
            $activity_desc = "Lead reassigned from {$old_staff_name} to {$new_staff_name}" . ($reason ? " [{$reason}]" : '');
        } else {
            $activity_desc = "Lead claimed & assigned to {$new_staff_name} (Staff #{$staff_id})" . ($reason ? " [{$reason}]" : '');
        }

        $this->leads_model->log_lead_activity($lead->id, $activity_desc);
        log_activity("Bizbot: Lead #{$lead->id} - {$activity_desc}");

        if (!empty($thread_guid)) {
            $this->save_thread_guid((int)$lead->id, $thread_guid);
        }

        return true;
    }

    /**
     * Find lead by thread GUID or BD phone suffix (last 10 digits)
     */
    private function find_lead_by_phone_or_thread(string $phone, string $thread_guid)
    {
        $phone_suffix = substr($phone, -10);

        // 1. Check by thread guid in custom fields
        if (!empty($thread_guid)) {
            $field = $this->db->get_where(db_prefix() . 'customfields', [
                'slug'    => 'leads_bizbot_thread_guid',
                'fieldto' => 'leads'
            ])->row();

            if ($field) {
                $val = $this->db->get_where(db_prefix() . 'customfieldsvalues', [
                    'fieldid' => $field->id,
                    'fieldto' => 'leads',
                    'value'   => $thread_guid
                ])->row();

                if ($val) {
                    $lead = $this->db->get_where(db_prefix() . 'leads', ['id' => $val->relid])->row();
                    if ($lead) {
                        return $lead;
                    }
                }
            }
        }

        // 2. Check by phone suffix in tblleads
        if (!empty($phone_suffix)) {
            return $this->db->query(
                "SELECT id, assigned, name, status FROM " . db_prefix() . "leads 
                 WHERE phone_suffix10 = ? OR phonenumber LIKE ? LIMIT 1",
                [$phone_suffix, '%' . $phone_suffix]
            )->row();
        }

        return null;
    }

    /**
     * Get human-readable staff full name
     */
    private function get_staff_name(int $staff_id): string
    {
        if ($staff_id <= 0) {
            return 'Unassigned';
        }
        $staff = $this->db->get_where(db_prefix() . 'staff', ['staffid' => $staff_id])->row();
        return $staff ? trim($staff->firstname . ' ' . $staff->lastname) : "Staff #{$staff_id}";
    }

    /**
     * Detect Staff ID from explicit administrative command:
     * #assign <name>, #reassign <name>, #to <name>, #staff <name>, #এসাইন <name>, #বরাদ্দ <name>
     */
    private function detect_staff_command(string $text): int
    {
        if (preg_match('/^#(?:assign|reassign|to|staff|এসাইন|বরাদ্দ)\s+([A-Za-z\p{Bengali}\s\-]+)/iu', trim($text), $m)) {
            return $this->match_staff_identifier(trim($m[1]));
        }
        return 0;
    }

    /**
     * Detect Staff ID from agent self-introduction:
     * Strictly used on agent outbound replies when lead is unassigned.
     * e.g. "আমি আল আমিন বলছি", "আমি মোস্তাফিজুর", "This is Al Amin"
     */
    private function detect_staff_intro(string $text): int
    {
        $intro_patterns = [
            '/(?:আমি|amii|ami)\s+([A-Za-z\p{Bengali}\s\-]+?)(?:\s+বলছি|\s+বলছিলাম|[.,!?\s]|$)/iu',
            '/(?:I\s*am|This\s*is|my\s*name\s*is)\s+([A-Za-z\p{Bengali}\s\-]+?)(?:[.,!?\s]|$)/iu',
        ];

        foreach ($intro_patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $target = trim($matches[1]);
                $staff_id = $this->match_staff_identifier($target);
                if ($staff_id > 0) {
                    return $staff_id;
                }
            }
        }

        return 0;
    }

    /**
     * Match a staff identifier string (name, alias, nickname) against active staff
     */
    private function match_staff_identifier(string $target): int
    {
        $target = mb_strtolower(trim($target));
        if (empty($target)) {
            return 0;
        }

        $all_staff = $this->db->get_where(db_prefix() . 'staff', ['active' => 1])->result_array();
        if (empty($all_staff)) {
            return 0;
        }

        // Map common Bangla spellings and nicknames for staff members
        $aliases = [
            1 => ['mostafizur', 'mostafiz', 'মোস্তাফিজুর', 'মোস্তাফিজ', 'mostafizur rahman'],
            2 => ['al amin', 'alamin', 'আল আমিন', 'আলামিন', 'আল-আমিন', 'amin', 'al-amin'],
            3 => ['asafunnahar', 'pakhi', 'আসাফুন্নাহার', 'পাখি', 'asafunnahar pakhi'],
        ];

        // Dynamic aliases configured via SalesOS Settings UI
        $custom_aliases_raw = get_option('salesos_bizbot_staff_aliases');
        if (!empty($custom_aliases_raw)) {
            $decoded = json_decode($custom_aliases_raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $s_id => $alias_list) {
                    $s_id = (int) $s_id;
                    if (!isset($aliases[$s_id])) {
                        $aliases[$s_id] = [];
                    }
                    if (is_array($alias_list)) {
                        $aliases[$s_id] = array_merge($aliases[$s_id], array_map('mb_strtolower', $alias_list));
                    }
                }
            }
        }

        // 1. Exact match on full name, first name, last name, or aliases
        foreach ($all_staff as $stf) {
            $id    = (int) $stf['staffid'];
            $first = mb_strtolower($stf['firstname']);
            $last  = mb_strtolower($stf['lastname']);
            $full  = mb_strtolower(trim($stf['firstname'] . ' ' . $stf['lastname']));

            if ($target === $full || $target === $first || $target === $last) {
                return $id;
            }
            if (isset($aliases[$id])) {
                foreach ($aliases[$id] as $al) {
                    if ($target === $al) {
                        return $id;
                    }
                }
            }
        }

        // 2. Substring match
        foreach ($all_staff as $stf) {
            $id    = (int) $stf['staffid'];
            $first = mb_strtolower($stf['firstname']);
            $last  = mb_strtolower($stf['lastname']);
            $full  = mb_strtolower(trim($stf['firstname'] . ' ' . $stf['lastname']));

            if (strpos($target, $full) !== false || strpos($target, $first) !== false || (!empty($last) && strpos($target, $last) !== false)) {
                return $id;
            }
            if (isset($aliases[$id])) {
                foreach ($aliases[$id] as $alias) {
                    if (strpos($target, $alias) !== false || strpos($alias, $target) !== false) {
                        return $id;
                    }
                }
            }
        }

        return 0;
    }

    /**
     * Check if contact is excluded (Staff, Supplier, or Custom Blacklist)
     */
    private function is_excluded_contact(string $phone): bool
    {
        if (empty($phone)) {
            return true;
        }
        if ($this->is_staff_phone($phone)) {
            return true;
        }
        if ($this->is_supplier_phone($phone)) {
            return true;
        }
        if ($this->is_blacklisted_phone($phone)) {
            return true;
        }
        return false;
    }

    /**
     * Check if phone belongs to an active staff member
     */
    private function is_staff_phone(string $phone): bool
    {
        if (get_option('salesos_bizbot_exclude_staff') === '0') {
            return false;
        }
        $phone_suffix = substr($phone, -10);
        $row = $this->db->query(
            "SELECT staffid FROM " . db_prefix() . "staff 
             WHERE phonenumber LIKE ? OR phonenumber = ? LIMIT 1",
            ['%' . $phone_suffix, $phone]
        )->row();
        return !empty($row);
    }

    /**
     * Check if phone belongs to a supplier / vendor
     */
    private function is_supplier_phone(string $phone): bool
    {
        if (get_option('salesos_bizbot_exclude_suppliers') === '0') {
            return false;
        }
        $phone_suffix = substr($phone, -10);
        $row = $this->db->query(
            "SELECT id FROM " . db_prefix() . "purchases_suppliers 
             WHERE phone LIKE ? OR phone = ? LIMIT 1",
            ['%' . $phone_suffix, $phone]
        )->row();
        return !empty($row);
    }

    /**
     * Check if phone is in custom blacklist
     */
    private function is_blacklisted_phone(string $phone): bool
    {
        $blacklist_raw = trim(get_option('salesos_bizbot_blacklist_phones') ?: '');
        if ($blacklist_raw === '') {
            return false;
        }
        $lines = preg_split('/[\r\n,]+/', $blacklist_raw);
        $phone_suffix = substr($phone, -10);
        foreach ($lines as $item) {
            $clean = $this->normalize_phone(trim($item));
            if (!empty($clean)) {
                $item_suffix = substr($clean, -10);
                if ($phone_suffix === $item_suffix || $phone === $clean) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Detect if message originated from a Facebook / Instagram CTWA Ad
     */
    private function is_fb_ad_referral(array $data): bool
    {
        $message = $data['message'] ?? [];
        $chat    = $data['chat'] ?? [];

        $referral = $message['referral'] ?? ($data['referral'] ?? ($chat['referral'] ?? ($message['context']['referral'] ?? [])));

        if (!empty($referral)) {
            if (is_array($referral)) {
                $source_type = strtolower($referral['source_type'] ?? '');
                if ($source_type === 'ad' || !empty($referral['source_id']) || !empty($referral['headline']) || !empty($referral['ctwa_clid'])) {
                    return true;
                }
            } elseif (is_string($referral) && !empty($referral)) {
                return true;
            }
        }

        if (!empty($message['is_ad']) || !empty($chat['is_ad'])) {
            return true;
        }

        $source = strtolower($message['source'] ?? ($chat['source'] ?? ''));
        if (in_array($source, ['ad', 'ctwa', 'facebook_ad', 'fb_ad'])) {
            return true;
        }

        return false;
    }

    /**
     * Detect commercial/buyer intent keywords in message text
     */
    private function has_buyer_intent(string $text): bool
    {
        if (trim($text) === '') {
            return false;
        }

        $raw_keywords = get_option('salesos_bizbot_intent_keywords');
        if ($raw_keywords === false || $raw_keywords === null || trim($raw_keywords) === '') {
            $raw_keywords = 'দাম, কত, প্রাইস, সাইজ, কালার, স্টক, ডেলিভারি, অর্ডার, নিতে চাই, কিনব, price, size, order, dress, buy, stock, cost, rate, bdt, টাকা, কোড, code, ক্যাশ';
        }

        $keywords = array_map('trim', explode(',', $raw_keywords));
        $lower_text = mb_strtolower($text, 'UTF-8');

        foreach ($keywords as $kw) {
            if ($kw === '') {
                continue;
            }
            $lower_kw = mb_strtolower($kw, 'UTF-8');
            if (mb_strpos($lower_text, $lower_kw, 0, 'UTF-8') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect manual lead conversion keyword (#lead, #crm, #লিড)
     */
    private function is_manual_lead_trigger(string $text): bool
    {
        $custom_keyword = trim(get_option('salesos_bizbot_manual_lead_keyword') ?: '#lead');
        $triggers = array_filter([$custom_keyword, '#lead', '#crm', '#লিড']);

        $lower_text = mb_strtolower(trim($text), 'UTF-8');
        foreach ($triggers as $trig) {
            $lower_trig = mb_strtolower($trig, 'UTF-8');
            if (mb_strpos($lower_text, $lower_trig, 0, 'UTF-8') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a message was already processed for idempotency
     */
    private function is_message_already_processed(string $message_id, string $event = ''): bool
    {
        if (empty($message_id)) {
            return false;
        }

        $table = db_prefix() . 'salesos_bizbot_processed_messages';
        $this->db->query(
            "INSERT IGNORE INTO {$table} (message_id, event, created_at) VALUES (?, ?, ?)",
            [$message_id, $event, date('Y-m-d H:i:s')]
        );

        return ($this->db->affected_rows() === 0);
    }

    /**
     * Log webhook payload for debugging with 5MB auto-rotation
     */
    private function log_webhook_payload(string $raw_payload, string $event)
    {
        $log_dir = FCPATH . 'temp';
        if (!is_dir($log_dir)) {
            @mkdir($log_dir, 0755, true);
        }

        $last_payload_file = $log_dir . '/bizbot_last_payload.json';
        @file_put_contents($last_payload_file, $raw_payload);

        $log_file = $log_dir . '/bizbot_webhook.log';
        if (file_exists($log_file) && filesize($log_file) > 5 * 1024 * 1024) {
            @rename($log_file, $log_dir . '/bizbot_webhook.log.old');
        }

        $log_entry = '[' . date('Y-m-d H:i:s') . '] Event: ' . $event . ' | Length: ' . strlen($raw_payload) . "\n";
        @file_put_contents($log_file, $log_entry, FILE_APPEND);
    }
}

