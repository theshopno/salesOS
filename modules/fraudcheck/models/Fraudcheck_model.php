<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Fraudcheck_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Normalize phone number to standard 11 digits starting with 01
     */
    public function normalize_phone($phone)
    {
        // Strip non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Remove +88 or 88 country prefixes
        if (strlen($phone) === 13 && substr($phone, 0, 2) === '88') {
            $phone = substr($phone, 2);
        }
        
        // Add leading zero if 10 digits starting with 1
        if (strlen($phone) === 10 && substr($phone, 0, 1) === '1') {
            $phone = '0' . $phone;
        }

        return $phone;
    }

    /**
     * Check customer risk analysis and delivery statistics via BDCourier API
     */
    public function check($phone, $bypass_cache = false)
    {
        $phone = $this->normalize_phone($phone);
        if (empty($phone) || strlen($phone) < 11) {
            return false;
        }

        $db_prefix = db_prefix();
        
        // 1. Check Cache
        if (!$bypass_cache) {
            $this->db->where('phone', $phone);
            $cache = $this->db->get($db_prefix . 'fraudcheck_lookups')->row_array();
            if ($cache) {
                $checked_time = strtotime($cache['checked_at']);
                $age = time() - $checked_time;
                if ($age < 48 * 3600) { // Cache TTL: 48 Hours
                    return $cache;
                }
            }
        }

        // 2. Fetch API Key from the kernel's credential vault
        $this->load->model('salesos/salesos_model');
        $cred = $this->salesos_model->get_active_credential('fraudcheck', 'api_key');

        if (!$cred) {
            log_message('error', 'Fraudcheck: No active BDCourier API Key credential found in vault.');
            // Fallback to cache if available, even if expired
            $this->db->where('phone', $phone);
            return $this->db->get($db_prefix . 'fraudcheck_lookups')->row_array();
        }

        $api_key = $cred['payload']['api_key'] ?? '';

        if (empty($api_key)) {
            log_message('error', 'Fraudcheck: Decrypted BDCourier API Key is empty.');
            $this->db->where('phone', $phone);
            return $this->db->get($db_prefix . 'fraudcheck_lookups')->row_array();
        }

        // 3. Query BDCourier API
        $url = 'https://api.bdcourier.com/courier-check';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['phone' => $phone]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        
        $response_raw = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200 || !$response_raw) {
            log_message('error', "Fraudcheck: BDCourier API returned HTTP Code {$http_code}. Response: " . ($response_raw ?: 'None'));
            $this->db->where('phone', $phone);
            return $this->db->get($db_prefix . 'fraudcheck_lookups')->row_array();
        }

        $response = json_decode($response_raw, true);
        if (!$response || !isset($response['status']) || $response['status'] !== 'success') {
            log_message('error', 'Fraudcheck: BDCourier API response parsing failed. Response: ' . $response_raw);
            $this->db->where('phone', $phone);
            return $this->db->get($db_prefix . 'fraudcheck_lookups')->row_array();
        }

        // 4. Parse Results & Save to Database
        $summary = $response['data']['summary'] ?? [];
        $verdict = $response['risk_verdict'] ?? [];
        $reports = $response['reports'] ?? [];

        $db_data = [
            'phone'            => $phone,
            'total_parcel'     => (int) ($summary['total_parcel'] ?? 0),
            'success_parcel'   => (int) ($summary['success_parcel'] ?? 0),
            'cancelled_parcel' => (int) ($summary['cancelled_parcel'] ?? 0),
            'success_ratio'    => (float) ($summary['success_ratio'] ?? 0.0),
            'report_count'     => count($reports),
            'risk_level'       => $verdict['level'] ?? 'unknown',
            'risk_color'       => $verdict['color'] ?? 'grey',
            'raw_response'     => $response_raw,
            'checked_at'       => date('Y-m-d H:i:s')
        ];

        // Perform UPSERT
        $this->db->where('phone', $phone);
        $exists = $this->db->get($db_prefix . 'fraudcheck_lookups')->row_array();
        if ($exists) {
            $this->db->where('id', $exists['id']);
            $this->db->update($db_prefix . 'fraudcheck_lookups', $db_data);
            $db_data['id'] = $exists['id'];
        } else {
            $this->db->insert($db_prefix . 'fraudcheck_lookups', $db_data);
            $db_data['id'] = $this->db->insert_id();
        }

        return $db_data;
    }
}
