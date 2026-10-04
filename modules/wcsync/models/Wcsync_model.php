<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Thin wrapper over the WooCommerce connector.
 *
 * Sites, deduplication, catalogue and customer matching, importing and status
 * following all live in the kernel now (Salesos_channel). What remains here is
 * the module's own surface: the screens call these, and they call the connector.
 */
class Wcsync_model extends App_Model
{
    private const PLATFORM = 'woocommerce';

    /** @var Woocommerce_channel */
    private $channel;

    public function __construct()
    {
        parent::__construct();
        if ($this->app_modules->is_active('salesos')) {
            $this->load->model('salesos/salesos_model');
        }
        $this->load->library('wcsync/woocommerce_channel');
        $this->channel = $this->woocommerce_channel;
    }

    // ── Sites ────────────────────────────────────────────────────────────────

    public function get_sites(): array
    {
        return $this->salesos_model->get_channel_sites(self::PLATFORM);
    }

    public function get_site(int $id): ?array
    {
        $site = $this->salesos_model->get_channel_site($id);

        return ($site && $site['platform'] === self::PLATFORM) ? $site : null;
    }

    public function save_site(array $data, ?int $id = null): int
    {
        return $this->salesos_model->save_channel_site([
            'platform'               => self::PLATFORM,
            'name'                   => $data['name'] ?? '',
            'site_url'               => $data['site_url'] ?? '',
            'credential_id'          => $data['credential_id'] ?? null,
            'default_lead_status_id' => $data['default_lead_status_id'] ?? null,
            'is_active'              => $data['is_active'] ?? 1,
        ], $id);
    }

    public function delete_site(int $id): bool
    {
        if (!$this->get_site($id)) {
            return false;
        }

        return $this->salesos_model->delete_channel_site($id);
    }

    // ── Sync ─────────────────────────────────────────────────────────────────

    public function sync(): array
    {
        return $this->channel->sync();
    }

    public function sync_site(array $site): array
    {
        return $this->channel->sync_site($site);
    }

    /** Orders this connector has brought in, newest first, for the dashboard. */
    public function get_synced_orders(int $limit = 15): array
    {
        return $this->db->select('o.id, o.channel_ref_id, o.channel_status, o.status, o.total, o.created_at, s.name as site_name')
            ->from(db_prefix() . 'salesos_orders o')
            ->join(db_prefix() . 'salesos_channel_sites s', 's.id = o.channel_site_id', 'left')
            ->where('o.channel', self::PLATFORM)
            ->order_by('o.created_at', 'desc')
            ->limit($limit)
            ->get()
            ->result_array();
    }

    /** @return array<int,int> order count per site id */
    public function get_site_stats(): array
    {
        $rows = $this->db->select('channel_site_id, COUNT(*) as count')
            ->where('channel', self::PLATFORM)
            ->group_by('channel_site_id')
            ->get(db_prefix() . 'salesos_orders')
            ->result_array();

        $stats = [];
        foreach ($rows as $row) {
            $stats[(int) $row['channel_site_id']] = (int) $row['count'];
        }

        return $stats;
    }

    // ── Catalog Sync ─────────────────────────────────────────────────────────

    public function sync_catalog_page(int $site_id, int $page = 1, int $per_page = 50): array
    {
        $site = $this->get_site($site_id);
        if (!$site) {
            return ['success' => false, 'error' => 'WooCommerce site not found.'];
        }

        return $this->channel->sync_catalog_page($site, $page, $per_page);
    }

    public function push_product(int $product_id, ?int $site_id = null): array
    {
        $site = $site_id ? $this->get_site($site_id) : $this->get_default_site();
        if (!$site) {
            return ['success' => false, 'error' => 'No active WooCommerce site.'];
        }

        return $this->channel->push_product($site, $product_id);
    }

    public function push_stock(int $product_id, float $qty, ?int $site_id = null): array
    {
        $site = $site_id ? $this->get_site($site_id) : $this->get_default_site();
        if (!$site) {
            return ['success' => false, 'error' => 'No active WooCommerce site.'];
        }

        return $this->channel->push_stock($site, $product_id, $qty);
    }

    public function get_default_site(): ?array
    {
        $sites = $this->get_sites();
        return !empty($sites) ? $sites[0] : null;
    }

    public function get_synced_products_count(): int
    {
        return (int) $this->db->where('external_platform', self::PLATFORM)
            ->count_all_results(db_prefix() . 'inventory_products');
    }

    public function search_store_products(string $query = '', int $page = 1, ?int $site_id = null): array
    {
        $site = $site_id ? $this->get_site($site_id) : $this->get_default_site();
        if (!$site) {
            return ['success' => false, 'error' => 'No active WooCommerce site.'];
        }

        return $this->channel->search_store_products($site, $query, $page);
    }

    public function import_specific_products(array $wc_ids, ?int $site_id = null): array
    {
        $site = $site_id ? $this->get_site($site_id) : $this->get_default_site();
        if (!$site) {
            return ['success' => false, 'error' => 'No active WooCommerce site.'];
        }

        return $this->channel->import_specific_products($site, $wc_ids);
    }

    public function push_multiple_products(array $product_ids, ?int $site_id = null): array
    {
        $site = $site_id ? $this->get_site($site_id) : $this->get_default_site();
        if (!$site) {
            return ['success' => false, 'error' => 'No active WooCommerce site.'];
        }

        return $this->channel->push_multiple_products($site, $product_ids);
    }

    public function push_all_products(?int $site_id = null): array
    {
        $site = $site_id ? $this->get_site($site_id) : $this->get_default_site();
        if (!$site) {
            return ['success' => false, 'error' => 'No active WooCommerce site.'];
        }

        return $this->channel->push_all_products($site);
    }

    public function push_chunk_products(int $page = 1, int $per_page = 10, ?int $site_id = null): array
    {
        $site = $site_id ? $this->get_site($site_id) : $this->get_default_site();
        if (!$site) {
            return ['success' => false, 'error' => 'No active WooCommerce site.'];
        }

        return $this->channel->push_chunk_products($site, $page, $per_page);
    }
}


