<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!isset($CI)) {
    $CI = &get_instance();
}

$db_prefix = db_prefix();

// Sites and the order mapping now live in the kernel, shared by every storefront
// connector, so this module owns no tables of its own any more. An existing
// install is carried across here; the old tables are left in place rather than
// dropped, so a migration that went wrong can still be inspected.

if ($CI->db->table_exists($db_prefix . 'wcsync_sites')
    && $CI->db->table_exists($db_prefix . 'salesos_channel_sites')) {

    foreach ($CI->db->get($db_prefix . 'wcsync_sites')->result_array() as $legacy) {
        $already = $CI->db->where('platform', 'woocommerce')
            ->where('site_url', rtrim($legacy['site_url'], '/'))
            ->get($db_prefix . 'salesos_channel_sites')
            ->row();

        if ($already) {
            $new_id = (int) $already->id;
        } else {
            $CI->db->insert($db_prefix . 'salesos_channel_sites', [
                'platform'               => 'woocommerce',
                'name'                   => $legacy['name'],
                'site_url'               => rtrim($legacy['site_url'], '/'),
                'credential_id'          => $legacy['credential_id'] ?: null,
                'default_lead_status_id' => $legacy['default_lead_status_id'] ?: null,
                'settings'               => json_encode([]),
                'is_active'              => 1,
                'last_synced_at'         => $legacy['last_synced_at'],
                'created_at'             => $legacy['created_at'],
            ]);
            $new_id = (int) $CI->db->insert_id();
        }

        // Point the orders this site brought in at their new home. The kernel
        // already dedupes on channel + channel_ref_id, so the old mapping table
        // is only needed to work out which order came from which site.
        if ($CI->db->table_exists($db_prefix . 'wcsync_orders')) {
            $CI->db->query(
                "UPDATE `{$db_prefix}salesos_orders` o
                 JOIN `{$db_prefix}wcsync_orders` w ON w.salesos_order_id = o.id
                 SET o.channel_site_id = ?, o.channel_status = w.wc_status
                 WHERE w.site_id = ? AND o.channel_site_id IS NULL",
                [$new_id, $legacy['id']]
            );
        }
    }

    // Orders were stored under the channel key 'woo'; the connector's platform
    // key is 'woocommerce', and dedup matches on it.
    $CI->db->query(
        "UPDATE `{$db_prefix}salesos_orders` SET channel = 'woocommerce' WHERE channel = 'woo'"
    );

    // Migrated once; a re-activation must not run it again.
    $CI->db->query("RENAME TABLE `{$db_prefix}wcsync_sites` TO `{$db_prefix}wcsync_sites_migrated`");
    if ($CI->db->table_exists($db_prefix . 'wcsync_orders')) {
        $CI->db->query("RENAME TABLE `{$db_prefix}wcsync_orders` TO `{$db_prefix}wcsync_orders_migrated`");
    }
    $CI->db->data_cache = [];
}
