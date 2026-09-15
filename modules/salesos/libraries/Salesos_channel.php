<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Base class for a storefront connector.
 *
 * Everything that is the same whatever the platform lives here: paging through a
 * sync, recognising an order that has already been imported, matching its
 * products and customer, importing it, and following the storefront's own status
 * afterwards. A connector supplies only the three things that genuinely differ
 * between WooCommerce, Shopify and a custom site — see the abstract methods at
 * the bottom, and CHANNEL_CONNECTOR.md for a worked example.
 *
 * The generic order shape a connector returns from translate_order():
 *
 *   [
 *     'external_id'     => '1042',            // the storefront's own order id
 *     'external_status' => 'processing',      // the storefront's own status
 *     'status'          => 'pending',         // kernel status, from map_status()
 *     'subtotal'        => 600.00,
 *     'shipping_charge' => 60.00,
 *     'total'           => 660.00,
 *     'currency'        => 'BDT',
 *     'payment_method'  => 'Cash on delivery',
 *     'order_note'      => '',
 *     'order_date'      => '2026-09-15 11:04:00',
 *     'customer' => ['phone','name','email','address','city','state','zip','country_code'],
 *     'items'    => [ ['sku','name','qty','unit_price','category_name','image_url','price'] ],
 *   ]
 */
abstract class Salesos_channel
{
    /** @var CI_Controller */
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->model('salesos/salesos_model');
    }

    // ── What a connector must implement ──────────────────────────────────────

    /** Short, stable key stored on every order this connector imports. */
    abstract public function platform_key(): string;

    /** Human name for the settings screen. */
    abstract public function platform_label(): string;

    /**
     * One page of raw orders from the storefront, newest first.
     *
     * @return array|false the raw orders, or false when the call genuinely
     *                     failed — an empty array means "no more orders", which
     *                     is not the same thing and must not be reported as an
     *                     error.
     */
    abstract public function fetch_orders(array $site, int $page);

    /** Turn one raw order into the generic shape documented above. */
    abstract public function translate_order(array $raw, array $site): array;

    // ── Optional hooks a connector may override ──────────────────────────────

    /** Extra catalogue detail for a line item, when the order payload is thin. */
    protected function enrich_line_item(array $item, array $site): array
    {
        return $item;
    }

    /** How many pages a single sync run will walk before stopping. */
    protected function max_pages(): int
    {
        return 20;
    }

    // ── The shared pipeline ──────────────────────────────────────────────────

    /**
     * Sync every active site this connector owns.
     *
     * @return array{success: bool, new_order: int, status_updated: int, unchanged: int, failed: int, site_errors: array}
     */
    public function sync(): array
    {
        $totals = ['new_order' => 0, 'status_updated' => 0, 'unchanged' => 0, 'failed' => 0];
        $errors = [];

        foreach ($this->CI->salesos_model->get_channel_sites($this->platform_key(), true) as $site) {
            $counts = $this->sync_site($site);

            if (!empty($counts['error'])) {
                $errors[] = $site['name'] . ': ' . $counts['error'];
            }
            unset($counts['error']);

            foreach ($counts as $key => $value) {
                if (isset($totals[$key])) {
                    $totals[$key] += $value;
                }
            }
        }

        $totals['success']     = empty($errors);
        $totals['site_errors'] = $errors;

        return $totals;
    }

    /** @return array counts for one site, plus an `error` key when the fetch failed */
    public function sync_site(array $site): array
    {
        $counts = ['new_order' => 0, 'status_updated' => 0, 'unchanged' => 0, 'failed' => 0, 'error' => null];

        for ($page = 1; $page <= $this->max_pages(); $page++) {
            $orders = $this->fetch_orders($site, $page);

            if ($orders === false) {
                $counts['error'] = 'Could not fetch orders from ' . $site['site_url'];
                break;
            }

            if (empty($orders)) {
                break;
            }

            foreach ($orders as $raw) {
                try {
                    $result = $this->handle_order($site, $raw);
                    if (isset($counts[$result])) {
                        $counts[$result]++;
                    }
                } catch (Throwable $e) {
                    $counts['failed']++;
                    log_activity($this->platform_key() . ' sync: order failed — ' . $e->getMessage());
                }
            }

            if (count($orders) < $this->page_size()) {
                break;
            }
        }

        if ($counts['error'] === null) {
            $this->CI->salesos_model->touch_channel_site((int) $site['id']);
        }

        return $counts;
    }

    /** Expected page size; a short page means the last page. */
    protected function page_size(): int
    {
        return 50;
    }

    /**
     * Import one order, or follow it if it is already here.
     *
     * @return string new_order|status_updated|unchanged
     */
    public function handle_order(array $site, array $raw): string
    {
        $order = $this->translate_order($raw, $site);

        // Recognise the order BEFORE touching the catalogue. Matching products
        // first would mean every unchanged order in the whole history pays for
        // SKU lookups — and possibly category and product writes — on every cron
        // tick, forever.
        $existing = $this->find_existing_order($order['external_id']);

        if ($existing) {
            return $this->follow_existing_order($existing, $order);
        }

        return $this->import_new_order($site, $order);
    }

    protected function find_existing_order(string $external_id): ?array
    {
        $row = $this->CI->db
            ->where('channel', $this->platform_key())
            ->where('channel_ref_id', $external_id)
            ->get(db_prefix() . 'salesos_orders')
            ->row_array();

        return $row ?: null;
    }

    /**
     * The storefront is the authority on its own order, so a status it reports is
     * pushed onto ours through the kernel — which fires the events stock, courier
     * and notifications listen for.
     */
    protected function follow_existing_order(array $existing, array $order): string
    {
        // An order held for a confirmation call is ours to decide, not the
        // storefront's: a shop that still says "processing" must not silently
        // confirm an order nobody has called about yet. A difference the gate
        // suppresses is not a change — counting it as one would report every
        // waiting order as updated on every single sync, forever.
        $held = $this->confirmation_pending($existing, $order);

        $status_changed  = !$held && $existing['status'] !== $order['status'];
        $channel_changed = ($existing['channel_status'] ?? null) !== $order['external_status'];

        if (!$status_changed && !$channel_changed) {
            return 'unchanged';
        }

        if ($status_changed) {
            $this->CI->salesos_model->set_order_status((int) $existing['id'], $order['status']);
        }

        if ($channel_changed) {
            $this->CI->db->where('id', $existing['id'])
                ->update(db_prefix() . 'salesos_orders', ['channel_status' => $order['external_status']]);
        }

        return 'status_updated';
    }

    /** True when this order is waiting on a confirmation call and must not be auto-confirmed. */
    protected function confirmation_pending(array $existing, array $order): bool
    {
        return salesos_order_confirmation_required()
            && $existing['status'] === 'pending'
            && $order['status'] === 'confirmed';
    }

    protected function import_new_order(array $site, array $order): string
    {
        $items = [];
        foreach ($order['items'] as $item) {
            $item = $this->enrich_line_item($item, $site);
            $items[] = [
                'product_id' => $this->CI->salesos_model->match_or_create_product($item),
                'item_id'    => null,
                'name'       => $item['name'] ?? 'Item',
                'sku'        => $item['sku'] ?? null,
                'qty'        => (float) ($item['qty'] ?? 1),
                'unit_price' => (float) ($item['unit_price'] ?? 0),
            ];
        }

        $customer = $order['customer'] ?? [];
        $customer['status_id'] = $site['default_lead_status_id'] ?? null;

        // Everything arrives pending when a confirmation call is required, no
        // matter what the storefront already calls it.
        $status = salesos_order_confirmation_required() ? 'pending' : $order['status'];

        $order_id = $this->CI->salesos_model->import_order([
            'channel'         => $this->platform_key(),
            'channel_ref_id'  => $order['external_id'],
            'status'          => $status,
            'subtotal'        => $order['subtotal'] ?? 0.00,
            'shipping_charge' => $order['shipping_charge'] ?? 0.00,
            'total'           => $order['total'] ?? 0.00,
            'currency'        => $order['currency'] ?? 'BDT',
            'payment_method'  => $order['payment_method'] ?? null,
            'order_note'      => $order['order_note'] ?? null,
            'order_date'      => $order['order_date'] ?? date('Y-m-d H:i:s'),
            'customer'        => $customer,
            'items'           => $items,
        ]);

        if ($order_id > 0) {
            $this->CI->db->where('id', $order_id)->update(db_prefix() . 'salesos_orders', [
                'channel_site_id' => (int) $site['id'],
                'channel_status'  => $order['external_status'] ?? null,
            ]);
        }

        return 'new_order';
    }

    // ── Helpers a connector will usually want ────────────────────────────────

    /** The site's decrypted credential, scoped to the module that owns it. */
    protected function credential(array $site, string $owner_module): ?array
    {
        if (empty($site['credential_id'])) {
            return null;
        }

        return $this->CI->salesos_model->get_credential((int) $site['credential_id'], $owner_module);
    }

    /**
     * @return array|false decoded JSON body, or false on a transport or HTTP error
     */
    protected function get_json(string $url, array $options = [])
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, $options + [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $body   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($body === false || $status < 200 || $status >= 300) {
            log_activity($this->platform_key() . ' API call failed (' . $status . ') ' . $url . ' ' . $err);

            return false;
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : false;
    }
}
