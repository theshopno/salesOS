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
        $this->load->model('salesos/salesos_model');
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
}
