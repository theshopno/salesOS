<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Service to orchestrate Automated IVR Outbound Calls for Order Confirmation.
 */
class Ivr_service
{
    private $CI;
    private $ami;
    private $db_conn;

    public function __construct()
    {
        if (function_exists('get_instance')) {
            $this->CI = &get_instance();
        }

        if ($this->CI && isset($this->CI->load)) {
            $this->CI->load->helper(PBXPILOT_MODULE_NAME . '/' . PBXPILOT_MODULE_NAME);
            $this->CI->load->library(PBXPILOT_MODULE_NAME . '/Ami_service');
            $this->ami = $this->CI->ami_service;
        } else {
            require_once __DIR__ . '/../helpers/pbxpilot_helper.php';
            require_once __DIR__ . '/Ami_service.php';
            $this->ami = new Ami_service();
        }
    }

    private function get_db(): mysqli
    {
        if ($this->db_conn !== null) {
            return $this->db_conn;
        }

        if (!defined('APP_DB_HOSTNAME')) {
            $cfg = dirname(__DIR__, 3) . '/application/config/app-config.php';
            if (is_file($cfg)) {
                require_once $cfg;
            }
        }

        $this->db_conn = new mysqli(APP_DB_HOSTNAME, APP_DB_USERNAME, APP_DB_PASSWORD, APP_DB_NAME);
        if ($this->db_conn->connect_errno) {
            throw new RuntimeException('DB connection failed: ' . $this->db_conn->connect_error);
        }

        return $this->db_conn;
    }

    /**
     * Trigger an automated IVR confirmation call for a specific order.
     *
     * @param int $order_id ID in tblsalesos_orders
     * @param string|null $override_phone Optional phone number to call (for manual test or override)
     * @return array{success: bool, message: string, log_id?: int, phone?: string}
     */
    public function trigger_order_confirmation(int $order_id, ?string $override_phone = null): array
    {
        $db = $this->get_db();

        $stmt = $db->prepare('SELECT id, lead_id, client_id, status, total, currency FROM tblsalesos_orders WHERE id = ?');
        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order) {
            if ($order_id === 0 && !empty($override_phone)) {
                $order = ['id' => 0, 'lead_id' => null, 'client_id' => null, 'status' => 'test', 'total' => 0, 'currency' => 'BDT'];
            } else {
                return ['success' => false, 'message' => "Order #{$order_id} not found."];
            }
        }

        $phone = $override_phone ?: $this->get_customer_phone($order);
        $is_extension = (bool) preg_match('/^10[1-9]|11[01]$/', (string) $phone);

        if ($is_extension) {
            $normalized = (string) $phone;
        } else {
            $normalized = $this->normalize_phone((string) $phone);
            if (!$this->is_valid_bd_phone($normalized)) {
                return [
                    'success' => false,
                    'message' => "Invalid phone number or extension: '{$phone}'."
                ];
            }
        }

        // Count previous attempts
        $stmt = $db->prepare('SELECT COUNT(*) as c FROM tblpbxpilot_ivr_logs WHERE order_id = ?');
        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $attempts = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
        $stmt->close();

        $attempt_num = $attempts + 1;
        $status      = 'pending';
        $notes       = "IVR call initiated (attempt {$attempt_num})";

        $stmt = $db->prepare('INSERT INTO tblpbxpilot_ivr_logs (order_id, phone, attempt, result, notes) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('isiss', $order_id, $normalized, $attempt_num, $status, $notes);
        $stmt->execute();
        $log_id = $stmt->insert_id;
        $stmt->close();

        $prompt_main    = pbxpilot_get_option('pbxpilot_ivr_prompt_main', 'custom/ivr_confirmation');
        $prompt_confirm = pbxpilot_get_option('pbxpilot_ivr_prompt_confirm', 'custom/ivr_press_1');
        $prompt_cancel  = pbxpilot_get_option('pbxpilot_ivr_prompt_cancel', 'custom/ivr_press_2');

        // Originate call via Asterisk AMI
        $res = $this->ami->originate_ivr(
            $normalized,
            'ivr-order-confirm',
            [
                'ORDER_ID'       => $order_id,
                'CUSTOMER_PHONE' => $normalized,
                'IVR_LOG_ID'     => $log_id,
                'PROMPT_MAIN'    => $prompt_main,
                'PROMPT_CONFIRM' => $prompt_confirm,
                'PROMPT_CANCEL'  => $prompt_cancel,
            ]
        );

        if (!empty($res['success'])) {
            $action_id = $res['action_id'] ?? '';
            $notes_ok  = "Call queued with ActionID {$action_id}.";

            $stmt = $db->prepare('UPDATE tblpbxpilot_ivr_logs SET uniqueid = ?, notes = ? WHERE id = ?');
            $stmt->bind_param('ssi', $action_id, $notes_ok, $log_id);
            $stmt->execute();
            $stmt->close();

            return [
                'success' => true,
                'message' => "IVR call successfully initiated to {$normalized} for Order #{$order_id}.",
                'log_id'  => $log_id,
                'phone'   => $normalized,
            ];
        }

        // Originate failed
        $err = $res['error'] ?? 'Unknown AMI originate error';
        $res_failed = 'failed';
        $notes_fail = "Originate failed: {$err}";

        $stmt = $db->prepare('UPDATE tblpbxpilot_ivr_logs SET result = ?, notes = ? WHERE id = ?');
        $stmt->bind_param('ssi', $res_failed, $notes_fail, $log_id);
        $stmt->execute();
        $stmt->close();

        return [
            'success' => false,
            'message' => "Failed to initiate IVR call: {$err}",
            'log_id'  => $log_id,
        ];
    }

    /**
     * Retrieve customer phone from client or lead record.
     */
    private function get_customer_phone(array $order): string
    {
        $db = $this->get_db();

        if (!empty($order['client_id'])) {
            $stmt = $db->prepare('SELECT phonenumber FROM tblcontacts WHERE userid = ? AND is_primary = 1');
            $stmt->bind_param('i', $order['client_id']);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!empty($row['phonenumber'])) {
                return $row['phonenumber'];
            }

            $stmt = $db->prepare('SELECT phonenumber FROM tblclients WHERE userid = ?');
            $stmt->bind_param('i', $order['client_id']);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!empty($row['phonenumber'])) {
                return $row['phonenumber'];
            }
        }

        if (!empty($order['lead_id'])) {
            $stmt = $db->prepare('SELECT phonenumber FROM tblleads WHERE id = ?');
            $stmt->bind_param('i', $order['lead_id']);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!empty($row['phonenumber'])) {
                return $row['phonenumber'];
            }
        }

        return '';
    }

    /**
     * Normalize Bangladeshi phone number to standard 11 digits: 01XXXXXXXXX.
     */
    public function normalize_phone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw);

        if (strlen($digits) === 13 && strpos($digits, '8801') === 0) {
            return substr($digits, 2);
        }

        if (strlen($digits) === 10 && strpos($digits, '1') === 0) {
            return '0' . $digits;
        }

        if (strlen($digits) === 11 && strpos($digits, '01') === 0) {
            return $digits;
        }

        return $digits;
    }

    /**
     * Validate Bangladeshi mobile format: 013-019 (11 digits).
     */
    public function is_valid_bd_phone(string $phone): bool
    {
        return (bool) preg_match('/^01[3-9]\d{8}$/', $phone);
    }
}
