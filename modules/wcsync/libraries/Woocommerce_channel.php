<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (file_exists(__DIR__ . '/../../salesos/libraries/Salesos_channel.php')) {
    require_once __DIR__ . '/../../salesos/libraries/Salesos_channel.php';
}
if (!class_exists('Salesos_channel')) {
    abstract class Salesos_channel {}
}

/**
 * WooCommerce storefront connector.
 *
 * Everything below is WooCommerce-specific and nothing else is: how to call its
 * REST API, what its order payload looks like, and what its statuses mean. The
 * sync loop, deduplication, catalogue matching, customer matching and status
 * following all come from Salesos_channel.
 */
class Woocommerce_channel extends Salesos_channel
{
    /** WooCommerce statuses that mean the order is not going to happen. */
    private const LOST = ['cancelled', 'refunded', 'failed'];

    /** ...and the ones that mean the shop considers it real. */
    private const CONFIRMED = ['processing', 'completed', 'on-hold'];

    public function platform_key(): string
    {
        return 'woocommerce';
    }

    public function platform_label(): string
    {
        return 'WooCommerce';
    }

    public function fetch_orders(array $site, int $page)
    {
        $cred = $this->credential($site, 'wcsync');
        if (!$cred || empty($cred['payload']['consumer_key']) || empty($cred['payload']['consumer_secret'])) {
            log_activity('WooCommerce site ' . $site['name'] . ' has no usable API credential.');

            return false;
        }

        $query = http_build_query([
            'per_page' => $this->page_size(),
            'page'     => $page,
            'orderby'  => 'date',
            'order'    => 'desc',
        ]);

        // Only orders touched since the last successful sync, so a shop with
        // years of history does not get walked from the beginning every time.
        if (!empty($site['last_synced_at'])) {
            $query .= '&modified_after=' . rawurlencode(date('c', strtotime($site['last_synced_at'] . ' -1 hour')));
        }

        return $this->get_json($site['site_url'] . '/wp-json/wc/v3/orders?' . $query, [
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD  => $cred['payload']['consumer_key'] . ':' . $cred['payload']['consumer_secret'],
        ]);
    }

    public function translate_order(array $raw, array $site): array
    {
        $billing  = $raw['billing'] ?? [];
        $shipping = $raw['shipping'] ?? [];

        $name = trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? ''));
        if ($name === '') {
            $name = trim(($shipping['first_name'] ?? '') . ' ' . ($shipping['last_name'] ?? ''));
        }
        if ($name === '') {
            $name = 'WooCommerce customer';
        }

        // WooCommerce lets a shop fill shipping and leave billing empty.
        $address = !empty($billing['address_1']) ? $billing : $shipping;
        $external_status = $raw['status'] ?? 'pending';

        $items = [];
        foreach ($raw['line_items'] ?? [] as $line) {
            $items[] = [
                'sku'         => !empty($line['sku']) ? trim($line['sku']) : null,
                'name'        => $line['name'] ?? 'WooCommerce item',
                'qty'         => (float) ($line['quantity'] ?? 1),
                'unit_price'  => (float) ($line['price'] ?? 0),
                'price'       => (float) ($line['price'] ?? 0),
                'external_id' => $line['product_id'] ?? null,
            ];
        }

        return [
            'external_id'     => (string) ($raw['id'] ?? ''),
            'external_status' => $external_status,
            'status'          => $this->map_status($external_status),
            'subtotal'        => (float) ($raw['subtotal'] ?? 0),
            'shipping_charge' => (float) ($raw['shipping_total'] ?? 0),
            'total'           => (float) ($raw['total'] ?? 0),
            'currency'        => $raw['currency'] ?? 'BDT',
            'payment_method'  => $raw['payment_method_title'] ?? null,
            'order_note'      => $raw['customer_note'] ?? null,
            'order_date'      => !empty($raw['date_created'])
                ? date('Y-m-d H:i:s', strtotime($raw['date_created']))
                : date('Y-m-d H:i:s'),
            'customer' => [
                'phone'        => $billing['phone'] ?? ($shipping['phone'] ?? ''),
                'name'         => $name,
                'email'        => $billing['email'] ?? '',
                'address'      => trim(($address['address_1'] ?? '') . ' ' . ($address['address_2'] ?? '')),
                'city'         => $address['city'] ?? '',
                'state'        => $address['state'] ?? '',
                'zip'          => $address['postcode'] ?? '',
                'country_code' => $address['country'] ?? 'BD',
            ],
            'items' => $items,
        ];
    }

    private function map_status(string $wc_status): string
    {
        if (in_array($wc_status, self::LOST, true)) {
            return 'cancelled';
        }

        return in_array($wc_status, self::CONFIRMED, true) ? 'confirmed' : 'pending';
    }

    /**
     * An order payload carries a SKU and a price but no category or image, so a
     * product being created for the first time is worth one extra call. Existing
     * products are matched from the order alone — this only runs for new ones.
     */
    protected function enrich_line_item(array $item, array $site): array
    {
        if (empty($item['external_id'])) {
            return $item;
        }

        if ($this->product_known($item)) {
            return $item;
        }

        $cred = $this->credential($site, 'wcsync');
        if (!$cred) {
            return $item;
        }

        $product = $this->get_json(
            $site['site_url'] . '/wp-json/wc/v3/products/' . (int) $item['external_id'],
            [
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD  => $cred['payload']['consumer_key'] . ':' . $cred['payload']['consumer_secret'],
                CURLOPT_TIMEOUT  => 10,
            ]
        );

        if (!$product) {
            return $item;
        }

        $item['category_name'] = $product['categories'][0]['name'] ?? '';
        $item['image_url']     = $product['images'][0]['src'] ?? null;
        if (isset($product['price']) && $product['price'] !== '') {
            $item['price'] = (float) $product['price'];
        }

        return $item;
    }

    private function product_known(array $item): bool
    {
        if (!empty($item['sku'])) {
            return (bool) $this->CI->db->where('sku', trim($item['sku']))
                ->get(db_prefix() . 'inventory_products')->row();
        }

        return (bool) $this->CI->db->where('name', trim($item['name'] ?? ''))
            ->get(db_prefix() . 'inventory_products')->row();
    }

    // ── Bidirectional Catalog Synchronization ────────────────────────────────

    /**
     * Fetch and synchronize one batch page of product catalog from WooCommerce.
     *
     * @return array{success: bool, page: int, total_pages: int, total_products: int, processed: int, created: int, updated: int, error?: string}
     */
    public function sync_catalog_page(array $site, int $page = 1, int $per_page = 50): array
    {
        $cred = $this->credential($site, 'wcsync');
        if (!$cred || empty($cred['payload']['consumer_key']) || empty($cred['payload']['consumer_secret'])) {
            return ['success' => false, 'error' => 'WooCommerce site has no valid credentials.'];
        }

        $url = rtrim($site['site_url'], '/') . '/wp-json/wc/v3/products?' . http_build_query([
            'per_page' => $per_page,
            'page'     => $page,
            'status'   => 'publish',
        ]);

        $response = $this->wc_api_call($url, 'GET', null, $cred);
        if (!$response['success']) {
            return ['success' => false, 'error' => 'Failed to reach WooCommerce API: ' . ($response['error'] ?? 'HTTP ' . $response['status'])];
        }

        $products     = $response['body'];
        $total_items  = isset($response['headers']['x-wp-total']) ? (int) $response['headers']['x-wp-total'] : count($products);
        $total_pages  = isset($response['headers']['x-wp-totalpages']) ? (int) $response['headers']['x-wp-totalpages'] : 1;

        $created = 0;
        $updated = 0;

        foreach ($products as $prod) {
            $result = $this->import_single_wc_product($site, $prod, $cred);
            if ($result === 'created') {
                $created++;
            } elseif ($result === 'updated') {
                $updated++;
            }
        }

        return [
            'success'        => true,
            'page'           => $page,
            'total_pages'    => $total_pages,
            'total_products' => $total_items,
            'processed'      => count($products),
            'created'        => $created,
            'updated'        => $updated,
        ];
    }

    private function import_single_wc_product(array $site, array $wc_prod, array $cred): string
    {
        $wc_id   = (int) $wc_prod['id'];
        $type    = $wc_prod['type'] ?? 'simple';
        $name    = html_entity_decode(trim($wc_prod['name']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $sku     = !empty($wc_prod['sku']) ? trim($wc_prod['sku']) : 'WC-' . $wc_id;
        $price   = isset($wc_prod['price']) && $wc_prod['price'] !== '' ? (float) $wc_prod['price'] : 0.00;
        $image   = !empty($wc_prod['images'][0]['src']) ? $wc_prod['images'][0]['src'] : null;
        $cat_name= !empty($wc_prod['categories'][0]['name']) ? html_entity_decode(trim($wc_prod['categories'][0]['name']), ENT_QUOTES | ENT_HTML5, 'UTF-8') : 'Uncategorized';
        $cat_id  = $this->match_or_create_category($cat_name);

        // Find existing product strictly by external_id, then SKU (never by plain name to avoid collision)
        $existing = $this->CI->db->where('external_platform', 'woocommerce')->where('external_id', $wc_id)->get(db_prefix() . 'inventory_products')->row();
        if (!$existing && $sku !== '') {
            $existing = $this->CI->db->where('sku', $sku)->get(db_prefix() . 'inventory_products')->row();
        }

        $is_variable = ($type === 'variable');
        $product_type = $is_variable ? 'variable' : 'simple';

        // When product is variable, parent container gets -PARENT suffix so child variations can use the real SKU
        $stored_sku = ($is_variable && $sku !== '') ? $sku . '-PARENT' : $sku;

        // 1. Maintain tblitems for billing/invoicing
        $item_id = $existing ? $existing->item_id : null;
        if (!$item_id) {
            $this->CI->db->insert(db_prefix() . 'items', [
                'description'      => $name,
                'long_description' => 'SKU: ' . $stored_sku,
                'rate'             => $price,
                'unit'             => 'pc',
                'group_id'         => 0,
            ]);
            $item_id = $this->CI->db->insert_id();
        } else {
            $this->CI->db->where('id', $item_id)->update(db_prefix() . 'items', [
                'description' => $name,
                'rate'        => $price,
            ]);
        }

        // 2. Insert or update tblinventory_products
        $product_data = [
            'product_type'      => $product_type,
            'parent_id'         => null,
            'item_id'           => $item_id,
            'sku'               => $stored_sku,
            'name'              => $name,
            'category_id'       => $cat_id,
            'image'             => $image,
            'external_platform' => 'woocommerce',
            'external_id'       => $wc_id,
            'sync_to_wc'        => 1,
            'last_synced_at'    => date('Y-m-d H:i:s'),
            'is_active'         => 1,
        ];

        $action = 'unchanged';
        if ($existing) {
            $product_id = (int) $existing->id;
            $this->CI->db->where('id', $product_id)->update(db_prefix() . 'inventory_products', $product_data);
            $action = 'updated';
        } else {
            $product_data['created_at'] = date('Y-m-d H:i:s');
            $this->CI->db->insert(db_prefix() . 'inventory_products', $product_data);
            $product_id = (int) $this->CI->db->insert_id();
            $action = 'created';
        }

        // 3. Handle Stock: Strict CRM-First (Zero initial stock on catalog import)
        // Physical stock must only be added via Purchase Order receipt or manual stock-in.
        if (!$is_variable) {
            $this->ensure_product_stock($product_id, 0.00);
        }

        // 4. If variable product, fetch child variations
        if ($is_variable) {
            $this->import_product_variations($site, $product_id, $wc_id, $name, $sku, $cat_id, $image, $cred);
        }

        return $action;
    }

    private function import_product_variations(array $site, int $parent_crm_id, int $wc_parent_id, string $parent_name, string $parent_sku, ?int $cat_id, ?string $parent_image, array $cred): void
    {
        $url = rtrim($site['site_url'], '/') . '/wp-json/wc/v3/products/' . $wc_parent_id . '/variations?per_page=100';
        $response = $this->wc_api_call($url, 'GET', null, $cred);

        if (!$response['success'] || !is_array($response['body'])) {
            return;
        }

        foreach ($response['body'] as $var) {
            $var_id   = (int) $var['id'];
            $var_sku  = !empty($var['sku']) ? trim($var['sku']) : $parent_sku . '-V' . $var_id;
            $var_price= isset($var['price']) && $var['price'] !== '' ? (float) $var['price'] : 0.00;
            $var_img  = !empty($var['image']['src']) ? $var['image']['src'] : $parent_image;

            $attrs = [];
            $attr_labels = [];
            if (!empty($var['attributes']) && is_array($var['attributes'])) {
                foreach ($var['attributes'] as $attr) {
                    $attr_name = $attr['name'] ?? 'Option';
                    $attr_opt  = $attr['option'] ?? '';
                    $attrs[$attr_name] = $attr_opt;
                    $attr_labels[] = $attr_opt;
                }
            }

            $var_name = $parent_name . ' (' . implode(', ', $attr_labels) . ')';

            // Find existing variation - NEVER match the parent container
            $existing = $this->CI->db->where('external_platform', 'woocommerce')
                ->where('external_id', $var_id)
                ->where('id !=', $parent_crm_id)
                ->get(db_prefix() . 'inventory_products')->row();
            if (!$existing && $var_sku !== '') {
                $existing = $this->CI->db->where('sku', $var_sku)
                    ->where('id !=', $parent_crm_id)
                    ->get(db_prefix() . 'inventory_products')->row();
            }

            // Billing item
            $item_id = $existing ? $existing->item_id : null;
            if (!$item_id) {
                $this->CI->db->insert(db_prefix() . 'items', [
                    'description'      => $var_name,
                    'long_description' => 'SKU: ' . $var_sku,
                    'rate'             => $var_price,
                    'unit'             => 'pc',
                    'group_id'         => 0,
                ]);
                $item_id = $this->CI->db->insert_id();
            } else {
                $this->CI->db->where('id', $item_id)->update(db_prefix() . 'items', [
                    'description' => $var_name,
                    'rate'        => $var_price,
                ]);
            }

            $var_data = [
                'product_type'       => 'variation',
                'parent_id'          => $parent_crm_id,
                'item_id'            => $item_id,
                'sku'                => $var_sku,
                'name'               => $var_name,
                'category_id'        => $cat_id,
                'attributes_json'    => json_encode($attrs),
                'image'              => $var_img,
                'external_platform'  => 'woocommerce',
                'external_id'        => $var_id,
                'external_parent_id' => $wc_parent_id,
                'sync_to_wc'         => 1,
                'last_synced_at'     => date('Y-m-d H:i:s'),
                'is_active'          => 1,
            ];

            if ($existing) {
                $var_product_id = (int) $existing->id;
                $this->CI->db->where('id', $var_product_id)->update(db_prefix() . 'inventory_products', $var_data);
            } else {
                $var_data['created_at'] = date('Y-m-d H:i:s');
                $this->CI->db->insert(db_prefix() . 'inventory_products', $var_data);
                $var_product_id = (int) $this->CI->db->insert_id();
            }

            // Strict CRM-First: initial stock is 0.00 until purchased or inwarded
            $this->ensure_product_stock($var_product_id, 0.00);
        }
    }

    private function ensure_product_stock(int $product_id, float $default_qty = 0.00): void
    {
        $existing = $this->CI->db->where('product_id', $product_id)->get(db_prefix() . 'inventory_stock')->row();
        if (!$existing) {
            $now = date('Y-m-d H:i:s');
            $this->CI->db->insert(db_prefix() . 'inventory_stock', [
                'product_id'   => $product_id,
                'warehouse_id' => 1,
                'qty_on_hand'  => $default_qty,
                'qty_reserved' => 0.00,
                'updated_at'   => $now,
            ]);
            $this->CI->db->insert(db_prefix() . 'inventory_stock_ledger', [
                'product_id'    => $product_id,
                'warehouse_id'  => 1,
                'movement_type' => 'initial',
                'qty'           => $default_qty,
                'balance_after' => $default_qty,
                'ref_type'      => 'wc_catalog_import',
                'ref_id'        => null,
                'staff_id'      => get_staff_user_id() ?: null,
                'note'          => 'Initial stock imported from WooCommerce',
                'created_at'    => $now,
            ]);
        }
    }

    public function match_or_create_category(string $name): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $existing = $this->CI->db->where('name', $name)->get(db_prefix() . 'inventory_categories')->row();
        if ($existing) {
            return (int) $existing->id;
        }

        $this->CI->db->insert(db_prefix() . 'inventory_categories', ['name' => $name, 'parent_id' => null]);
        return (int) $this->CI->db->insert_id();
    }

    // ── CRM to WooCommerce Push ──────────────────────────────────────────────

    /**
     * Push a CRM product (new or update) to WooCommerce.
     */
    public function push_product(array $site, int $product_id): array
    {
        $cred = $this->credential($site, 'wcsync');
        if (!$cred) {
            return ['success' => false, 'error' => 'No credentials.'];
        }

        $product = $this->CI->db->where('id', $product_id)->get(db_prefix() . 'inventory_products')->row_array();
        if (!$product) {
            return ['success' => false, 'error' => 'Product not found.'];
        }

        $item = $this->CI->db->where('id', $product['item_id'])->get(db_prefix() . 'items')->row_array();
        $rate = $item ? (float) $item['rate'] : 0.00;

        $stock_row = $this->CI->db->where('product_id', $product_id)->get(db_prefix() . 'inventory_stock')->row();
        $stock_qty = $stock_row ? (int) $stock_row->qty_on_hand : 0;

        $payload = [
            'name'           => $product['name'],
            'sku'            => $product['sku'],
            'regular_price'  => (string) $rate,
            'manage_stock'   => true,
            'stock_quantity' => $stock_qty,
        ];

        if (!empty($product['image'])) {
            $payload['images'] = [['src' => $product['image']]];
        }

        $baseUrl = rtrim($site['site_url'], '/') . '/wp-json/wc/v3/products';

        if (!empty($product['external_id'])) {
            // Update existing
            $res = $this->wc_api_call($baseUrl . '/' . $product['external_id'], 'PUT', $payload, $cred);
        } else {
            // Create new
            $payload['type'] = $product['product_type'] === 'variable' ? 'variable' : 'simple';
            $res = $this->wc_api_call($baseUrl, 'POST', $payload, $cred);
            if ($res['success'] && !empty($res['body']['id'])) {
                $this->CI->db->where('id', $product_id)->update(db_prefix() . 'inventory_products', [
                    'external_platform' => 'woocommerce',
                    'external_id'       => (int) $res['body']['id'],
                    'last_synced_at'    => date('Y-m-d H:i:s'),
                ]);
            }
        }

        return $res;
    }

    /**
     * Push real-time stock decrement or increment to WooCommerce.
     */
    public function push_stock(array $site, int $product_id, float $qty): array
    {
        $cred = $this->credential($site, 'wcsync');
        if (!$cred) return ['success' => false, 'error' => 'No credentials'];

        $product = $this->CI->db->where('id', $product_id)->get(db_prefix() . 'inventory_products')->row_array();
        if (!$product || empty($product['external_id'])) {
            return ['success' => false, 'error' => 'Product has no linked WooCommerce ID'];
        }

        $baseUrl = rtrim($site['site_url'], '/') . '/wp-json/wc/v3/products';
        $payload = [
            'manage_stock'   => true,
            'stock_quantity' => (int) $qty,
        ];

        if ($product['product_type'] === 'variation' && !empty($product['external_parent_id'])) {
            $url = $baseUrl . '/' . $product['external_parent_id'] . '/variations/' . $product['external_id'];
        } else {
            $url = $baseUrl . '/' . $product['external_id'];
        }

        return $this->wc_api_call($url, 'PUT', $payload, $cred, 3);
    }

    // ── Low-Level REST Helper ────────────────────────────────────────────────

    private function wc_api_call(string $url, string $method, ?array $payload, array $cred, int $timeout = 30): array
    {
        $ch = curl_init($url);
        $headers = [];
        $response_headers = [];

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_USERPWD        => $cred['payload']['consumer_key'] . ':' . $cred['payload']['consumer_secret'],
            CURLOPT_HEADERFUNCTION => function($ch, $header) use (&$response_headers) {
                $len = strlen($header);
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $response_headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return $len;
            }
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($payload);
            $headers[] = 'Content-Type: application/json';
        } elseif ($method === 'PUT') {
            $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
            $options[CURLOPT_POSTFIELDS] = json_encode($payload);
            $headers[] = 'Content-Type: application/json';
        }

        if (!empty($headers)) {
            $options[CURLOPT_HTTPHEADER] = $headers;
        }

        curl_setopt_array($ch, $options);
        $body   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        $decoded = $body ? json_decode($body, true) : null;
        $success = ($status >= 200 && $status < 300);

        return [
            'success' => $success,
            'status'  => $status,
            'body'    => $decoded,
            'headers' => $response_headers,
            'error'   => $err ?: ($success ? null : 'HTTP ' . $status),
        ];
    }

    // ── Granular / Specific Pull & Push Freedom ──────────────────────────────

    /**
     * Search products directly on the WooCommerce storefront for user selection.
     */
    public function search_store_products(array $site, string $query = '', int $page = 1, int $per_page = 20): array
    {
        $cred = $this->credential($site, 'wcsync');
        if (!$cred) return ['success' => false, 'error' => 'No credentials'];

        $params = [
            'per_page' => $per_page,
            'page'     => $page,
            'status'   => 'publish',
        ];
        if (trim($query) !== '') {
            $params['search'] = trim($query);
        }

        $url = rtrim($site['site_url'], '/') . '/wp-json/wc/v3/products?' . http_build_query($params);
        $res = $this->wc_api_call($url, 'GET', null, $cred);
        if (!$res['success'] || !is_array($res['body'])) {
            return ['success' => false, 'error' => $res['error'] ?? 'Search failed'];
        }

        $items = [];
        foreach ($res['body'] as $p) {
            $wc_id = (int)$p['id'];
            $in_crm = (bool) $this->CI->db->where('external_platform', 'woocommerce')
                ->where('external_id', $wc_id)
                ->count_all_results(db_prefix() . 'inventory_products') > 0;

            $items[] = [
                'id'             => $wc_id,
                'name'           => html_entity_decode($p['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'sku'            => $p['sku'] ?: ('WC-' . $wc_id),
                'price'          => (float)($p['price'] ?? 0),
                'type'           => $p['type'] ?? 'simple',
                'image'          => !empty($p['images'][0]['src']) ? $p['images'][0]['src'] : null,
                'category'       => !empty($p['categories'][0]['name']) ? html_entity_decode($p['categories'][0]['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : 'Uncategorized',
                'already_in_crm' => $in_crm,
            ];
        }

        return [
            'success'  => true,
            'products' => $items,
            'total'    => isset($res['headers']['x-wp-total']) ? (int)$res['headers']['x-wp-total'] : count($items),
        ];
    }

    /**
     * Import only specific products selected by the user.
     */
    public function import_specific_products(array $site, array $wc_ids): array
    {
        $cred = $this->credential($site, 'wcsync');
        if (!$cred) return ['success' => false, 'error' => 'No credentials'];

        $imported = 0;
        $failed = 0;

        foreach ($wc_ids as $wc_id) {
            $wc_id = (int)$wc_id;
            if ($wc_id <= 0) continue;

            $url = rtrim($site['site_url'], '/') . '/wp-json/wc/v3/products/' . $wc_id;
            $res = $this->wc_api_call($url, 'GET', null, $cred);
            if ($res['success'] && is_array($res['body'])) {
                $this->import_single_wc_product($site, $res['body'], $cred);
                $imported++;
            } else {
                $failed++;
            }
        }

        return [
            'success'  => true,
            'imported' => $imported,
            'failed'   => $failed,
            'total'    => count($wc_ids),
        ];
    }

    /**
     * Push multiple specific products to WooCommerce.
     */
    public function push_multiple_products(array $site, array $product_ids): array
    {
        $pushed = 0;
        $failed = 0;

        foreach ($product_ids as $pid) {
            $pid = (int)$pid;
            if ($pid <= 0) continue;

            $res = $this->push_product($site, $pid);
            if (!empty($res['success'])) {
                $pushed++;
            } else {
                $failed++;
            }
        }

        return [
            'success' => true,
            'pushed'  => $pushed,
            'failed'  => $failed,
            'total'   => count($product_ids),
        ];
    }

    /**
     * Push ALL active CRM products to WooCommerce.
     */
    public function push_all_products(array $site): array
    {
        $products = $this->CI->db->select('id')
            ->where('parent_id IS NULL')
            ->where('is_active', 1)
            ->get(db_prefix() . 'inventory_products')
            ->result_array();

        $product_ids = array_column($products, 'id');
        return $this->push_multiple_products($site, $product_ids);
    }

    /**
     * Push a chunk/page of CRM products to WooCommerce to avoid HTTP 504 gateway timeout.
     */
    public function push_chunk_products(array $site, int $page = 1, int $per_page = 10): array
    {
        $total = (int) $this->CI->db->where('parent_id IS NULL')
            ->where('is_active', 1)
            ->count_all_results(db_prefix() . 'inventory_products');

        $offset = max(0, ($page - 1) * $per_page);
        $products = $this->CI->db->select('id')
            ->where('parent_id IS NULL')
            ->where('is_active', 1)
            ->order_by('id', 'ASC')
            ->limit($per_page, $offset)
            ->get(db_prefix() . 'inventory_products')
            ->result_array();

        $product_ids = array_column($products, 'id');
        $result = $this->push_multiple_products($site, $product_ids);

        $totalPages = $total > 0 ? (int) ceil($total / $per_page) : 1;
        $processedSoFar = min($total, $page * $per_page);

        return [
            'success'      => true,
            'page'         => $page,
            'per_page'     => $per_page,
            'total_items'  => $total,
            'total_pages'  => $totalPages,
            'processed'    => $processedSoFar,
            'chunk_pushed' => $result['pushed'],
            'chunk_failed' => $result['failed'],
            'is_done'      => ($page >= $totalPages || empty($products)),
        ];
    }
}


