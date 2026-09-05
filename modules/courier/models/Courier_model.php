<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Courier_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('ecomcore/ecomcore_encryption');
        $this->load->model('ecomcore/ecomcore_model');

        // Dynamic Self-Healing Migration
        $db_prefix = db_prefix();
        if (!$this->db->field_exists('store_id', $db_prefix . 'courier_accounts')) {
            $this->db->query("ALTER TABLE `{$db_prefix}courier_accounts` ADD `store_id` VARCHAR(50) DEFAULT NULL AFTER `pickup_address`;");
        }
        if (!$this->db->field_exists('environment', $db_prefix . 'courier_accounts')) {
            $this->db->query("ALTER TABLE `{$db_prefix}courier_accounts` ADD `environment` VARCHAR(30) DEFAULT NULL AFTER `store_id`;");
        }
    }

    /**
     * Get all courier accounts
     */
    public function get_accounts()
    {
        $db_prefix = db_prefix();
        return $this->db->get($db_prefix . 'courier_accounts')->result_array();
    }

    /**
     * Get single courier account with decrypted credentials
     */
    public function get_account($id)
    {
        $db_prefix = db_prefix();
        $this->db->where('id', $id);
        $account = $this->db->get($db_prefix . 'courier_accounts')->row_array();

        if ($account) {
            $this->db->where('id', $account['credential_id']);
            $cred = $this->db->get($db_prefix . 'ecomcore_credentials')->row();
            if ($cred) {
                $decrypted = $this->ecomcore_encryption->decrypt($cred->payload, true);
                if (is_array($decrypted)) {
                    $account['api_key']       = $decrypted['api_key'] ?? '';
                    $account['secret_key']     = $decrypted['secret_key'] ?? '';
                    $account['client_id']      = $decrypted['client_id'] ?? '';
                    $account['client_secret']  = $decrypted['client_secret'] ?? '';
                }
            }
        }
        return $account;
    }

    /**
     * Save courier account (Create or Update)
     */
    public function save_account($data)
    {
        $db_prefix = db_prefix();
        $this->db->trans_start();

        $id             = isset($data['id']) ? (int) $data['id'] : null;
        $provider       = trim($data['provider'] ?? 'steadfast');
        $label          = trim($data['label'] ?? '');
        $pickup_address = trim($data['pickup_address'] ?? '');
        $is_default     = isset($data['is_default']) ? (int) $data['is_default'] : 0;
        $is_active      = isset($data['is_active']) ? (int) $data['is_active'] : 1;
        $environment    = isset($data['environment']) ? trim($data['environment']) : 'live';

        $api_key        = trim($data['api_key'] ?? '');
        $secret_key     = trim($data['secret_key'] ?? '');
        $client_id      = trim($data['client_id'] ?? '');
        $client_secret  = trim($data['client_secret'] ?? '');
        $store_id       = isset($data['store_id']) ? trim($data['store_id']) : null;

        // 1. Connection check and credentials prep
        $secret_payload = [];
        if ($provider === 'steadfast') {
            $secret_payload = [
                'api_key'    => $api_key,
                'secret_key' => $secret_key
            ];
        } elseif ($provider === 'pathao') {
            $secret_payload = [
                'client_id'     => $client_id,
                'client_secret' => $client_secret
            ];
        }

        $encrypted = $this->ecomcore_encryption->encrypt($secret_payload);

        if ($id) {
            $this->db->where('id', $id);
            $account = $this->db->get($db_prefix . 'courier_accounts')->row();
            if ($account) {
                $this->db->where('id', $account['credential_id']);
                $this->db->update($db_prefix . 'ecomcore_credentials', [
                    'payload' => $encrypted,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                $cred_id = $account['credential_id'];
            }
        } else {
            $this->db->insert($db_prefix . 'ecomcore_credentials', [
                'owner_module' => 'courier',
                'label'        => 'Courier Config - ' . $label,
                'cred_type'    => 'api_key',
                'payload'      => $encrypted,
                'is_active'    => 1,
                'created_at'   => date('Y-m-d H:i:s')
            ]);
            $cred_id = $this->db->insert_id();
        }

        // 2. If provider is Pathao and store_id is not set, fetch default store ID automatically
        if ($provider === 'pathao' && empty($store_id)) {
            // Temporary save to get an ID so we can get credentials context
            $temp_account_data = [
                'provider'      => $provider,
                'label'         => $label,
                'credential_id' => $cred_id,
                'environment'   => $environment,
                'is_active'     => 1,
            ];
            if ($id) {
                $this->db->where('id', $id);
                $this->db->update($db_prefix . 'courier_accounts', $temp_account_data);
                $account_db_id = $id;
            } else {
                $temp_account_data['created_at'] = date('Y-m-d H:i:s');
                $this->db->insert($db_prefix . 'courier_accounts', $temp_account_data);
                $account_db_id = $this->db->insert_id();
            }

            // Fetch stores list from Pathao
            // Clean token cache first to force retrieve fresh token
            delete_option('pathao_token_data_' . $account_db_id);
            
            $stores = [];
            try {
                $stores = $this->fetch_pathao_stores($account_db_id);
            } catch (Throwable $e) {
                // Keep empty
            }
            
            if (empty($stores)) {
                $store_id = 'mock_store_id';
                $pickup_address = !empty($pickup_address) ? $pickup_address : 'Placeholder Pathao Address (Offline)';
                set_alert('warning', 'Pathao API credentials could not be verified. Saved with placeholder store ID.');
            } else {
                // Pick default or first store
                $default_store = null;
                foreach ($stores as $st) {
                    if (!empty($st['is_default'])) {
                        $default_store = $st;
                        break;
                    }
                }
                if (!$default_store) {
                    $default_store = $stores[0];
                }

                $store_id = $default_store['store_id'];
                $pickup_address = trim($default_store['store_name'] . ' - ' . ($default_store['store_address'] ?? ''));
            }
            $id = $account_db_id;
        }

        // 3. Reset other default accounts if this is default
        if ($is_default === 1) {
            $this->db->update($db_prefix . 'courier_accounts', ['is_default' => 0]);
        }

        $account_data = [
            'provider'       => $provider,
            'label'          => $label,
            'credential_id'  => $cred_id,
            'pickup_address' => $pickup_address,
            'store_id'       => $store_id,
            'environment'    => $environment,
            'is_default'     => $is_default,
            'is_active'      => $is_active,
        ];

        if ($id) {
            $this->db->where('id', $id);
            $this->db->update($db_prefix . 'courier_accounts', $account_data);
        } else {
            $account_data['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert($db_prefix . 'courier_accounts', $account_data);
            $id = $this->db->insert_id();
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            return false;
        }

        return $id;
    }

    /**
     * Delete courier account and credentials
     */
    public function delete_account($id)
    {
        $db_prefix = db_prefix();
        $this->db->where('id', $id);
        $account = $this->db->get($db_prefix . 'courier_accounts')->row_array();
        if ($account) {
            $this->db->where('id', $account['credential_id']);
            $this->db->delete($db_prefix . 'ecomcore_credentials');

            $this->db->where('id', $id);
            $this->db->delete($db_prefix . 'courier_accounts');
            
            // Cleanup cached options
            delete_option('pathao_token_data_' . $id);
            return true;
        }
        return false;
    }

    /**
     * Query wallet float balance from Steadfast API
     */
    public function check_steadfast_balance($api_key, $secret_key)
    {
        $res = $this->execute_steadfast_request('/get_balance', 'GET', null, $api_key, $secret_key);
        if ($res && isset($res['status']) && (int)$res['status'] === 200) {
            return (float) ($res['current_balance'] ?? 0.00);
        }
        return false;
    }

    // ── Pathao OAuth & Location Fetchers ──────────────────────────────────────

    /**
     * Retrieve cached Pathao Bearer access token or generate a new one
     */
    public function get_pathao_token($account_id, $reset = false)
    {
        $cached = get_option('pathao_token_data_' . $account_id);
        $token_data = $cached ? unserialize($cached) : null;

        // Buffer of 60 seconds
        if ($reset || !$token_data || time() >= ($token_data['expires_in'] - 60)) {
            $account = $this->get_account($account_id);
            if (!$account || empty($account['client_id']) || empty($account['client_secret'])) {
                return false;
            }

            $base_url = ($account['environment'] === 'staging') 
                ? 'https://courier-api-sandbox.pathao.com' 
                : 'https://api-hermes.pathao.com';

            $payload = [
                'client_id'     => $account['client_id'],
                'client_secret' => $account['client_secret']
            ];

            $ch = curl_init($base_url . '/aladdin/api/v1/external/login');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

            $response = curl_exec($ch);
            curl_close($ch);

            if (!$response) {
                return false;
            }

            $res = json_decode($response, true);
            if (isset($res['access_token'])) {
                $token_data = [
                    'access_token'  => $res['access_token'],
                    'expires_in'    => time() + (int) $res['expires_in']
                ];
                update_option('pathao_token_data_' . $account_id, serialize($token_data));
            } else {
                return false;
            }
        }

        return $token_data['access_token'] ?? false;
    }

    /**
     * Fetch Pathao stores list
     */
    public function fetch_pathao_stores($account_id)
    {
        $res = $this->execute_pathao_request($account_id, '/aladdin/api/v1/stores', 'GET');
        return $res['data']['data'] ?? [];
    }

    /**
     * Fetch Pathao cities list
     */
    public function fetch_pathao_cities($account_id)
    {
        $res = $this->execute_pathao_request($account_id, '/aladdin/api/v1/countries/1/city-list', 'GET');
        return $res['data']['data'] ?? [];
    }

    /**
     * Fetch Pathao zones list by City ID
     */
    public function fetch_pathao_zones($account_id, $city_id)
    {
        $res = $this->execute_pathao_request($account_id, "/aladdin/api/v1/cities/{$city_id}/zone-list", 'GET');
        return $res['data']['data'] ?? [];
    }

    /**
     * Fetch Pathao areas list by Zone ID
     */
    public function fetch_pathao_areas($account_id, $zone_id)
    {
        $res = $this->execute_pathao_request($account_id, "/aladdin/api/v1/zones/{$zone_id}/area-list", 'GET');
        return $res['data']['data'] ?? [];
    }

    // ── Book Parcel ──────────────────────────────────────────────────────────

    /**
     * Book order with selected courier account
     */
    public function book_order($ecomcore_order_id, $courier_account_id, $cod_amount, $notes = '', $additional_payload = [])
    {
        $db_prefix = db_prefix();
        
        $account = $this->get_account($courier_account_id);
        if (!$account) {
            throw new Exception("Courier account not found.");
        }

        // If Steadfast, run standard Steadfast logic
        if ($account['provider'] === 'steadfast') {
            return $this->book_order_steadfast($ecomcore_order_id, $courier_account_id, $cod_amount, $notes);
        }

        // ── Pathao Booking Flow ────────────────────────────────────────────────
        $order = $this->get_order_with_customer($ecomcore_order_id);
        if (!$order) {
            throw new Exception("Order ID {$ecomcore_order_id} not found.");
        }

        // Check if already booked
        $this->db->where('ecomcore_order_id', $ecomcore_order_id);
        $existing = $this->db->get($db_prefix . 'courier_consignments')->row();
        if ($existing) {
            throw new Exception("Order ID {$ecomcore_order_id} has already been booked (Tracking ID: {$existing->tracking_id}).");
        }

        // Normalise Phone
        $phone = preg_replace('/[^0-9]/', '', $order['customer_phone'] ?? '');
        if (strlen($phone) > 11 && substr($phone, 0, 3) === '880') {
            $phone = substr($phone, 2);
        } elseif (strlen($phone) > 11 && substr($phone, 0, 2) === '88') {
            $phone = substr($phone, 2);
        }
        $phone = substr($phone, -11);

        if (strlen($phone) !== 11) {
            throw new Exception("Recipient phone must be exactly 11 digits. Received: {$phone}");
        }

        $address = trim($order['customer_address'] ?? '');
        if (empty($address)) {
            $address = 'No Shipping Address Provided';
        }
        $address = substr($address, 0, 248);

        $payload = [
            'store_id'                  => (int) $account['store_id'],
            'merchant_order_id'         => 'ORD-' . $order['id'],
            'recipient_name'            => substr(trim($order['customer_name'] ?? 'Guest Customer'), 0, 98),
            'recipient_phone'           => $phone,
            'recipient_address'         => $address,
            'recipient_city'            => (int) ($additional_payload['recipient_city'] ?? 0),
            'recipient_zone'            => (int) ($additional_payload['recipient_zone'] ?? 0),
            'recipient_area'            => (int) ($additional_payload['recipient_area'] ?? 0),
            'delivery_type'             => (int) ($additional_payload['delivery_type'] ?? 48), // 48 = Normal
            'item_type'                 => (int) ($additional_payload['item_type'] ?? 2), // 2 = Parcel
            'special_instruction'       => substr(trim($notes), 0, 250),
            'item_quantity'             => (int) ($additional_payload['item_quantity'] ?? 1),
            'item_weight'               => (float) ($additional_payload['item_weight'] ?? 0.5),
            'item_description'          => 'E-commerce Shipment',
            'amount_to_collect'         => (int) $cod_amount
        ];

        // API Call
        $res = $this->execute_pathao_request($courier_account_id, '/aladdin/api/v1/orders', 'POST', $payload);

        if ($res && isset($res['code']) && (int)$res['code'] === 200 && isset($res['data'])) {
            $c = $res['data'];
            
            $consignment_data = [
                'ecomcore_order_id'  => $ecomcore_order_id,
                'courier_account_id' => $courier_account_id,
                'consignment_id'     => $c['consignment_id'],
                'tracking_id'        => $c['consignment_id'], // Pathao consignment ID is the tracking ID
                'cod_amount'         => (float) $cod_amount,
                'status'             => 'Order_Created', // Default status slug
                'raw_response'       => json_encode($res),
                'last_synced_at'     => date('Y-m-d H:i:s'),
                'created_at'         => date('Y-m-d H:i:s'),
            ];

            $this->db->insert($db_prefix . 'courier_consignments', $consignment_data);
            $db_id = $this->db->insert_id();

            // Update order status
            $this->db->where('id', $ecomcore_order_id);
            $this->db->update($db_prefix . 'ecomcore_orders', [
                'status' => 'shipped',
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            return $db_id;
        } else {
            $msg = $res['message'] ?? 'Unknown Pathao API Error';
            if (isset($res['errors']) && is_array($res['errors'])) {
                $errs = [];
                foreach ($res['errors'] as $e) {
                    $errs[] = $e['field'] . ': ' . $e['message'];
                }
                $msg .= ' (' . implode(', ', $errs) . ')';
            }
            throw new Exception("Pathao API booking failed: " . $msg);
        }
    }

    /**
     * Book with Steadfast (Internal helper)
     */
    private function book_order_steadfast($ecomcore_order_id, $courier_account_id, $cod_amount, $notes = '')
    {
        $db_prefix = db_prefix();
        $order = $this->get_order_with_customer($ecomcore_order_id);
        if (!$order) {
            throw new Exception("Order ID {$ecomcore_order_id} not found.");
        }

        $account = $this->get_account($courier_account_id);
        
        $invoice = 'ORD-' . $order['id'];
        $phone = preg_replace('/[^0-9]/', '', $order['customer_phone'] ?? '');
        if (strlen($phone) > 11 && substr($phone, 0, 3) === '880') {
            $phone = substr($phone, 2);
        } elseif (strlen($phone) > 11 && substr($phone, 0, 2) === '88') {
            $phone = substr($phone, 2);
        }
        $phone = substr($phone, -11);

        if (strlen($phone) !== 11) {
            throw new Exception("Recipient phone must be exactly 11 digits.");
        }

        $address = substr(trim($order['customer_address'] ?? 'No Shipping Address'), 0, 248);

        $payload = [
            'invoice'           => $invoice,
            'recipient_name'    => substr(trim($order['customer_name'] ?? 'Guest Customer'), 0, 98),
            'recipient_phone'   => $phone,
            'recipient_address' => $address,
            'cod_amount'        => (float) $cod_amount,
            'note'              => substr(trim($notes), 0, 250),
            'item_description'  => 'E-commerce Shipment',
            'delivery_type'     => 0
        ];

        $res = $this->execute_steadfast_request('/create_order', 'POST', $payload, $account['api_key'], $account['secret_key']);
        
        if ($res && isset($res['status']) && (int)$res['status'] === 200 && isset($res['consignment'])) {
            $c = $res['consignment'];
            
            $consignment_data = [
                'ecomcore_order_id'  => $ecomcore_order_id,
                'courier_account_id' => $courier_account_id,
                'consignment_id'     => $c['consignment_id'],
                'tracking_id'        => $c['tracking_code'],
                'cod_amount'         => (float) $cod_amount,
                'status'             => trim($c['status'] ?? 'in_review'),
                'raw_response'       => json_encode($res),
                'last_synced_at'     => date('Y-m-d H:i:s'),
                'created_at'         => date('Y-m-d H:i:s'),
            ];

            $this->db->insert($db_prefix . 'courier_consignments', $consignment_data);
            $db_id = $this->db->insert_id();

            $this->db->where('id', $ecomcore_order_id);
            $this->db->update($db_prefix . 'ecomcore_orders', [
                'status' => 'shipped',
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            return $db_id;
        } else {
            $msg = $res['message'] ?? 'Unknown Steadfast API Error';
            throw new Exception("Steadfast API booking failed: " . $msg);
        }
    }

    /**
     * Get list of all consignments
     */
    public function get_consignments()
    {
        $db_prefix = db_prefix();
        $this->db->select("{$db_prefix}courier_consignments.*, {$db_prefix}ecomcore_orders.channel, {$db_prefix}ecomcore_orders.channel_ref_id, {$db_prefix}courier_accounts.label as account_label, {$db_prefix}courier_accounts.provider");
        $this->db->join($db_prefix . 'ecomcore_orders', $db_prefix . 'ecomcore_orders.id = ' . $db_prefix . 'courier_consignments.ecomcore_order_id', 'left');
        $this->db->join($db_prefix . 'courier_accounts', $db_prefix . 'courier_accounts.id = ' . $db_prefix . 'courier_consignments.courier_account_id', 'left');
        $this->db->order_by('created_at', 'DESC');
        return $this->db->get($db_prefix . 'courier_consignments')->result_array();
    }

    /**
     * Sync active parcel consignment delivery statuses
     */
    public function sync_all_active_statuses()
    {
        $db_prefix = db_prefix();
        
        $this->db->where_not_in('status', ['delivered', 'partial_delivered', 'cancelled', 'returned', 'Return', 'Delivered', 'Pickup_Cancelled', 'Pickup_Failed', 'Delivery_Failed']);
        $consignments = $this->db->get($db_prefix . 'courier_consignments')->result_array();

        if (empty($consignments)) {
            return 0;
        }

        $sync_count = 0;
        $account_cache = [];

        foreach ($consignments as $con) {
            $acc_id = $con['courier_account_id'];
            
            if (!isset($account_cache[$acc_id])) {
                $account_cache[$acc_id] = $this->get_account($acc_id);
            }
            $account = $account_cache[$acc_id];
            if (!$account) {
                continue;
            }

            if ($account['provider'] === 'steadfast') {
                // ── Steadfast Sync ──
                $res = $this->execute_steadfast_request('/status_by_cid/' . $con['consignment_id'], 'GET', null, $account['api_key'], $account['secret_key']);
                if ($res && isset($res['status']) && (int)$res['status'] === 200 && isset($res['delivery_status'])) {
                    $new_status = trim($res['delivery_status']);
                    $old_status = $con['status'];

                    if ($new_status !== $old_status) {
                        $this->update_consignment_status($con['id'], $con['ecomcore_order_id'], $old_status, $new_status);
                        $sync_count++;
                    }
                }
            } elseif ($account['provider'] === 'pathao') {
                // ── Pathao Sync ──
                // Pathao Aladdin API GET /aladdin/api/v1/orders/{id}/tracking
                $res = $this->execute_pathao_request($acc_id, '/aladdin/api/v1/orders/' . $con['consignment_id'] . '/tracking', 'GET');
                if ($res && isset($res['code']) && (int)$res['code'] === 200 && isset($res['data']['order_status_slug'])) {
                    $new_status_slug = trim($res['data']['order_status_slug']);
                    $old_status = $con['status'];

                    // Map Pathao slug to standard delivery status
                    $new_status = $new_status_slug;
                    
                    // Standardise terminal updates
                    if ($new_status_slug === 'order.delivered') { $new_status = 'delivered'; }
                    elseif ($new_status_slug === 'order.pickup-cancelled' || $new_status_slug === 'order.pickup-failed' || $new_status_slug === 'order.delivery-failed') { $new_status = 'cancelled'; }
                    elseif ($new_status_slug === 'order.returned') { $new_status = 'returned'; }

                    if ($new_status !== $old_status) {
                        $this->update_consignment_status($con['id'], $con['ecomcore_order_id'], $old_status, $new_status);
                        $sync_count++;
                    }
                }
            }
        }
        return $sync_count;
    }

    /**
     * Sync delivery status for a single consignment
     */
    public function sync_single_consignment_status($id)
    {
        $db_prefix = db_prefix();
        $this->db->where('id', $id);
        $con = $this->db->get($db_prefix . 'courier_consignments')->row_array();
        if (!$con) {
            return false;
        }

        $account = $this->get_account($con['courier_account_id']);
        if (!$account) {
            return false;
        }

        $new_status = null;

        if ($account['provider'] === 'steadfast') {
            $res = $this->execute_steadfast_request('/status_by_cid/' . $con['consignment_id'], 'GET', null, $account['api_key'], $account['secret_key']);
            if ($res && isset($res['status']) && (int)$res['status'] === 200 && isset($res['delivery_status'])) {
                $new_status = trim($res['delivery_status']);
            }
        } elseif ($account['provider'] === 'pathao') {
            $res = $this->execute_pathao_request($con['courier_account_id'], '/aladdin/api/v1/orders/' . $con['consignment_id'] . '/tracking', 'GET');
            if ($res && isset($res['code']) && (int)$res['code'] === 200 && isset($res['data']['order_status_slug'])) {
                $new_status_slug = trim($res['data']['order_status_slug']);
                $new_status = $new_status_slug;
                if ($new_status_slug === 'order.delivered') { $new_status = 'delivered'; }
                elseif ($new_status_slug === 'order.pickup-cancelled' || $new_status_slug === 'order.pickup-failed' || $new_status_slug === 'order.delivery-failed') { $new_status = 'cancelled'; }
                elseif ($new_status_slug === 'order.returned') { $new_status = 'returned'; }
            }
        }

        if ($new_status !== null) {
            $old_status = $con['status'];
            if ($new_status !== $old_status) {
                $this->update_consignment_status($con['id'], $con['ecomcore_order_id'], $old_status, $new_status);
            }
            return $new_status;
        }

        return false;
    }

    /**
     * Update consignment and trigger event changes (Helper)
     */
    private function update_consignment_status($db_id, $order_id, $old_status, $new_status)
    {
        $db_prefix = db_prefix();
        $this->db->where('id', $db_id);
        $this->db->update($db_prefix . 'courier_consignments', [
            'status'         => $new_status,
            'last_synced_at' => date('Y-m-d H:i:s')
        ]);

        // RESTOCK / MARK DELIVERED OR CANCELLED IN ECOMCORE
        if ($new_status === 'delivered') {
            $this->db->where('id', $order_id);
            $this->db->update($db_prefix . 'ecomcore_orders', [
                'status' => 'delivered',
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        } elseif ($new_status === 'cancelled' || $new_status === 'returned') {
            $this->db->where('id', $order_id);
            $this->db->update($db_prefix . 'ecomcore_orders', [
                'status' => 'cancelled',
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            hooks()->do_action('ecomcore_order_cancelled', $order_id);
        }

        hooks()->do_action('courier_consignment_status_changed', [
            'id'         => $db_id,
            'old_status' => $old_status,
            'new_status' => $new_status
        ]);
    }

    /**
     * Get order details including resolved customer information (from contacts or leads)
     */
    public function get_order_with_customer($order_id)
    {
        $db_prefix = db_prefix();
        $this->db->where('id', $order_id);
        $order = $this->db->get($db_prefix . 'ecomcore_orders')->row_array();
        
        if (!$order) {
            return null;
        }

        $order['customer_name']    = '';
        $order['customer_phone']   = '';
        $order['customer_address'] = '';

        if (!empty($order['client_id'])) {
            $this->db->where('userid', $order['client_id']);
            $this->db->where('is_primary', 1);
            $contact = $this->db->get($db_prefix . 'contacts')->row();
            if ($contact) {
                $order['customer_name']  = trim($contact->firstname . ' ' . $contact->lastname);
                $order['customer_phone'] = $contact->phonenumber;
            }
            $this->db->where('userid', $order['client_id']);
            $client = $this->db->get($db_prefix . 'clients')->row();
            if ($client) {
                if (empty($order['customer_name'])) {
                    $order['customer_name'] = $client->company;
                }
                if (empty($order['customer_phone'])) {
                    $order['customer_phone'] = $client->phonenumber;
                }
                $order['customer_address'] = $client->address;
            }
        } elseif (!empty($order['lead_id'])) {
            $this->db->where('id', $order['lead_id']);
            $lead = $this->db->get($db_prefix . 'leads')->row();
            if ($lead) {
                $order['customer_name']    = $lead->name;
                $order['customer_phone']   = $lead->phonenumber;
                $order['customer_address'] = $lead->address;
            }
        }

        return $order;
    }

    /**
     * Executing Pathao API requests using cURL
     */
    private function execute_pathao_request($account_id, $path, $method, $payload = null)
    {
        $account = $this->get_account($account_id);
        if (!$account) {
            return false;
        }

        $base_url = ($account['environment'] === 'staging') 
            ? 'https://courier-api-sandbox.pathao.com' 
            : 'https://api-hermes.pathao.com';

        $url = $base_url . $path;
        $ch = curl_init($url);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'source: woocommerce'
        ];

        // Retrieve OAuth token (except when requesting token itself!)
        if ($path !== '/aladdin/api/v1/external/login') {
            $token = $this->get_pathao_token($account_id);
            if ($token) {
                $headers[] = 'Authorization: Bearer ' . $token;
            }
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        } else {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            return false;
        }

        return json_decode($response, true);
    }

    /**
     * Executing Steadfast API requests using cURL
     */
    private function execute_steadfast_request($path, $method, $payload, $api_key, $secret_key)
    {
        $url = 'https://portal.packzy.com/api/v1' . $path;
        $ch = curl_init($url);
        
        $headers = [
            'Api-Key: ' . $api_key,
            'Secret-Key: ' . $secret_key,
            'Content-Type: application/json'
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        } else {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            return false;
        }

        return json_decode($response, true);
    }
}
