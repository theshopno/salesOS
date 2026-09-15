<?php

defined('BASEPATH') or exit('No direct script access allowed');

// The base class is abstract, so CI's library loader cannot bring it in — it
// would try to instantiate it. Every connector requires it directly instead.
require_once __DIR__ . '/../../salesos/libraries/Salesos_channel.php';

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
}
