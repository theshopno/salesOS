<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Wcsync_model extends App_Model
{
    private $lost_statuses = ['cancelled', 'refunded', 'failed'];

    public function __construct()
    {
        parent::__construct();
        $this->load->model('ecomcore/ecomcore_model');
        $this->load->library('ecomcore/ecomcore_encryption');
        
        $db_prefix = db_prefix();
        if (!$this->db->field_exists('wc_status', $db_prefix . 'wcsync_orders')) {
            $this->db->query("ALTER TABLE `{$db_prefix}wcsync_orders` ADD COLUMN `wc_status` VARCHAR(50) DEFAULT 'pending' AFTER `ecomcore_order_id`;");
        }
    }

    public function get_sites(): array
    {
        return $this->db->get(db_prefix() . 'wcsync_sites')->result_array();
    }

    public function get_site(int $id)
    {
        $this->db->where('id', $id);
        return $this->db->get(db_prefix() . 'wcsync_sites')->row_array();
    }

    public function save_site(array $data, int $id = null): int
    {
        $db_data = [
            'name'                   => trim($data['name']),
            'site_url'               => rtrim(trim($data['site_url']), '/'),
            'credential_id'          => (int) $data['credential_id'],
            'default_lead_status_id' => (int) $data['default_lead_status_id'],
        ];

        if ($id) {
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'wcsync_sites', $db_data);
            return $id;
        }

        $db_data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'wcsync_sites', $db_data);
        return $this->db->insert_id();
    }

    public function delete_site(int $id): bool
    {
        $this->db->trans_start();
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'wcsync_sites');

        $this->db->where('site_id', $id);
        $this->db->delete(db_prefix() . 'wcsync_orders');
        $this->db->trans_complete();
        return $this->db->trans_status();
    }

    /**
     * Main sync function: loops over all configured sites and syncs orders.
     */
    public function sync(): array
    {
        if (!is_cli()) {
            @set_time_limit(90);
        }
        $totals = [
            'new_order'      => 0,
            'status_updated' => 0,
            'unchanged'      => 0,
            'failed'         => 0,
        ];
        $site_errors = [];

        $sites = $this->get_sites();
        foreach ($sites as $site) {
            $counts = $this->sync_site($site);
            if (!empty($counts['fetch_error'])) {
                $site_errors[] = $site['name'] . ': ' . $counts['fetch_error'];
            }
            unset($counts['fetch_error']);
            foreach ($counts as $k => $v) {
                if (isset($totals[$k])) {
                    $totals[$k] += $v;
                }
            }
        }

        // Real fetch failures (bad credentials, unreachable site, HTTP error) must be
        // visible to the caller — previously indistinguishable from "no orders to
        // sync," so sync_now() always reported success:true even when nothing was
        // actually fetched.
        $totals['success']     = empty($site_errors);
        $totals['site_errors'] = $site_errors;

        return $totals;
    }

    public function sync_site(array $site): array
    {
        $counts = [
            'new_order'      => 0,
            'status_updated' => 0,
            'unchanged'      => 0,
            'failed'         => 0,
            'fetch_error'    => null,
        ];

        $page = 1;

        do {
            $orders = $this->fetch_orders($site, $page);

            if ($orders === false) {
                // A real fetch failure (bad credentials, network/HTTP error) — distinct
                // from "no more orders." fetch_orders() already logs the specific
                // cause via log_activity(); surface a summary here for the caller.
                $counts['fetch_error'] = 'Failed to fetch orders from ' . $site['site_url'];
                break;
            }

            if (empty($orders)) {
                break;
            }

            foreach ($orders as $order) {
                try {
                    $res = $this->handle_order($site, $order);
                    if (isset($counts[$res])) {
                        $counts[$res]++;
                    }
                } catch (Exception $e) {
                    $counts['failed']++;
                    log_activity('Wcsync site ' . $site['name'] . ' Failed order #' . ($order['id'] ?? '?') . ' Error: ' . $e->getMessage());
                }
            }

            $page++;
        } while (count($orders) >= 100);

        // Update site last sync date
        $this->db->where('id', $site['id']);
        $this->db->update(db_prefix() . 'wcsync_sites', [
            'last_synced_at' => date('Y-m-d H:i:s'),
        ]);

        return $counts;
    }

    public function fetch_orders(array $site, int $page = 1)
    {
        // 1. Get credentials from vault
        $this->db->where('id', $site['credential_id']);
        $cred = $this->db->get(db_prefix() . 'ecomcore_credentials')->row();
        if (!$cred) {
            log_activity('Wcsync site ' . $site['name'] . ' has invalid credential_id: ' . $site['credential_id']);
            return false;
        }

        $payload = $this->ecomcore_encryption->decrypt($cred->payload, true);
        if (!$payload || empty($payload['consumer_key']) || empty($payload['consumer_secret'])) {
            log_activity('Wcsync site ' . $site['name'] . ' unable to decrypt credentials.');
            return false;
        }

        $url = $site['site_url'] . '/wp-json/wc/v3/orders?' . http_build_query(array_filter([
            'per_page'       => 100,
            'page'           => $page,
            'orderby'        => 'date',
            'order'          => 'asc',
            'modified_after' => !empty($site['last_synced_at']) ? date('c', strtotime($site['last_synced_at'])) : null,
        ]));

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_USERPWD        => $payload['consumer_key'] . ':' . $payload['consumer_secret'],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response   = curl_exec($ch);
        $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $http_code !== 200 || $curl_error) {
            log_activity('Wcsync failed cURL fetch to ' . $site['site_url'] . ' (HTTP ' . $http_code . ') Error: ' . $curl_error);
            return false;
        }

        return json_decode($response, true);
    }

    public function handle_order(array $site, array $order): string
    {
        $this->db->where('site_id', $site['id']);
        $this->db->where('wc_order_id', $order['id']);
        $existing = $this->db->get(db_prefix() . 'wcsync_orders')->row();

        $wc_status = $order['status'] ?? 'pending';
        
        // Map WC statuses to Ecomcore statuses
        $target_status = 'pending';
        if (in_array($wc_status, $this->lost_statuses)) {
            $target_status = 'cancelled';
        } elseif ($wc_status === 'completed' || $wc_status === 'processing') {
            $target_status = 'confirmed';
        }


        // Pre-create/match products with categories from WooCommerce API
        $line_items = $order['line_items'] ?? [];
        foreach ($line_items as $item) {
            $sku = !empty($item['sku']) ? trim($item['sku']) : '';
            $name = !empty($item['name']) ? trim($item['name']) : '';
            $wc_prod_id = $item['product_id'] ?? null;

            $product_id = null;
            $p = null;
            if ($sku !== '') {
                $this->db->where('sku', $sku);
                $p = $this->db->get(db_prefix() . 'inventory_products')->row();
                if ($p) $product_id = $p->id;
            } elseif ($name !== '') {
                $this->db->where('name', $name);
                $p = $this->db->get(db_prefix() . 'inventory_products')->row();
                if ($p) $product_id = $p->id;
            }

            // If product exists but has no image, fetch details and update
            if ($product_id && $p && empty($p->image) && $wc_prod_id) {
                $wc_prod = $this->fetch_product($site, $wc_prod_id);
                if ($wc_prod && !empty($wc_prod['images'])) {
                    $image_url = $wc_prod['images'][0]['src'] ?? null;
                    if ($image_url) {
                        $this->db->where('id', $product_id);
                        $this->db->update(db_prefix() . 'inventory_products', ['image' => $image_url]);
                        $p->image = $image_url;
                    }
                }
            }

            // If product does not exist, fetch details and auto-create with category
            if (!$product_id && $wc_prod_id) {
                $wc_prod = $this->fetch_product($site, $wc_prod_id);
                $category_id = null;
                $image = null;
                
                if ($wc_prod) {
                    if (!empty($wc_prod['categories'])) {
                        // Get first category name
                        $cat_name = trim($wc_prod['categories'][0]['name'] ?? '');
                        if ($cat_name !== '') {
                            // Check if category exists
                            $this->db->where('name', $cat_name);
                            $cat = $this->db->get(db_prefix() . 'inventory_categories')->row();
                            if ($cat) {
                                $category_id = $cat->id;
                            } else {
                                // Create category
                                $this->db->insert(db_prefix() . 'inventory_categories', [
                                    'name'      => $cat_name,
                                    'parent_id' => null,
                                ]);
                                $category_id = $this->db->insert_id();
                            }
                        }
                    }
                    if (!empty($wc_prod['images'])) {
                        $image = $wc_prod['images'][0]['src'] ?? null;
                    }
                }

                // Create billing item in tblitems first to sync price
                $price = 0.00;
                if ($wc_prod && isset($wc_prod['price']) && $wc_prod['price'] !== '') {
                    $price = (float) $wc_prod['price'];
                } elseif (isset($item['price']) && $item['price'] !== '') {
                    $price = (float) $item['price'];
                }

                $prod_sku = $sku !== '' ? $sku : 'WOO-' . substr(md5($name), 0, 8);
                $prod_name = $name !== '' ? $name : 'Woo Product ' . $prod_sku;

                $this->db->insert(db_prefix() . 'items', [
                    'description'      => $prod_name,
                    'long_description' => $prod_sku ? 'SKU: ' . $prod_sku : '',
                    'rate'             => $price,
                    'unit'             => '',
                    'group_id'         => 0,
                ]);
                $item_id = $this->db->insert_id();

                // Create product with category
                $this->db->insert(db_prefix() . 'inventory_products', [
                    'item_id'       => $item_id,
                    'sku'           => $prod_sku,
                    'name'          => $prod_name,
                    'category_id'   => $category_id,
                    'image'         => $image,
                    'reorder_level' => 0.00,
                    'is_active'     => 1,
                    'created_at'    => date('Y-m-d H:i:s'),
                ]);
            }
        }

        if ($existing) {
            // Check status update (either ecomcore status mismatch or wc status column mismatch/missing)
            $this->db->where('id', $existing->ecomcore_order_id);
            $eco_order = $this->db->get(db_prefix() . 'ecomcore_orders')->row();
            
            $wc_status_mismatch = !isset($existing->wc_status) || $existing->wc_status !== $wc_status;
            
            if ($eco_order && ($eco_order->status !== $target_status || $wc_status_mismatch)) {
                if ($eco_order->status !== $target_status) {
                    $this->ecomcore_model->set_order_status($existing->ecomcore_order_id, $target_status);
                }
                
                $this->db->where('id', $existing->id);
                $this->db->update(db_prefix() . 'wcsync_orders', [
                    'wc_status' => $wc_status,
                    'synced_at' => date('Y-m-d H:i:s'),
                ]);
                return 'status_updated';
            }
            return 'unchanged';
        }

        // New Order Import
        $billing = $order['billing'] ?? [];
        $shipping = $order['shipping'] ?? [];
        
        $customer_name = trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? ''));
        if ($customer_name === '') {
            $customer_name = trim(($shipping['first_name'] ?? '') . ' ' . ($shipping['last_name'] ?? ''));
        }
        if ($customer_name === '') {
            $customer_name = 'WooCommerce Customer';
        }

        $address_src = !empty($billing['address_1']) ? $billing : $shipping;
        
        $generic_order = [
            'channel'         => 'woo',
            'channel_ref_id'  => $order['id'],
            'status'          => $target_status,
            'subtotal'        => (float) ($order['subtotal'] ?? 0.00),
            'shipping_charge' => (float) ($order['shipping_total'] ?? 0.00),
            'total'           => (float) ($order['total'] ?? 0.00),
            'currency'        => $order['currency'] ?? 'BDT',
            'payment_method'  => $order['payment_method_title'] ?? null,
            'order_note'      => $order['customer_note'] ?? null,
            'order_date'      => date('Y-m-d H:i:s', strtotime($order['date_created'])),
            'customer' => [
                'phone'        => $billing['phone'] ?? '',
                'name'         => $customer_name,
                'email'        => $billing['email'] ?? '',
                'address'      => trim(($address_src['address_1'] ?? '') . ' ' . ($address_src['address_2'] ?? '')),
                'city'         => $address_src['city'] ?? '',
                'state'        => $address_src['state'] ?? '',
                'zip'          => $address_src['postcode'] ?? '',
                'country_code' => $address_src['country'] ?? 'BD',
                'status_id'    => $site['default_lead_status_id'],
            ],
            'items' => []
        ];

        // Line items
        $line_items = $order['line_items'] ?? [];
        foreach ($line_items as $item) {
            $generic_order['items'][] = [
                'product_id' => null, // Let Ecomcore match by SKU or handle later
                'item_id'    => null,
                'name'       => $item['name'] ?? 'Woo Item',
                'sku'        => !empty($item['sku']) ? trim($item['sku']) : null,
                'qty'        => (float) ($item['quantity'] ?? 1.00),
                'unit_price' => (float) ($item['price'] ?? 0.00),
            ];
        }

        // Import Order
        $eco_order_id = $this->ecomcore_model->import_order($generic_order);

        // Save mapping
        $this->db->insert(db_prefix() . 'wcsync_orders', [
            'site_id'           => $site['id'],
            'wc_order_id'       => $order['id'],
            'ecomcore_order_id' => $eco_order_id,
            'wc_status'         => $wc_status,
            'synced_at'         => date('Y-m-d H:i:s'),
        ]);

        return 'new_order';
    }

    /**
     * Fetch a single WooCommerce product details from API.
     */
    public function fetch_product(array $site, int $product_id)
    {
        $this->db->where('id', $site['credential_id']);
        $cred = $this->db->get(db_prefix() . 'ecomcore_credentials')->row();
        if (!$cred) return false;
        $payload = $this->ecomcore_encryption->decrypt($cred->payload, true);
        if (!$payload || empty($payload['consumer_key']) || empty($payload['consumer_secret'])) return false;

        $url = $site['site_url'] . '/wp-json/wc/v3/products/' . $product_id;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_USERPWD        => $payload['consumer_key'] . ':' . $payload['consumer_secret'],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        if ($response === false) return false;
        return json_decode($response, true);
    }
}
