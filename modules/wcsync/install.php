<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!isset($CI)) {
    $CI = &get_instance();
}

$db_prefix = db_prefix();

// 1. Sites table
if (!$CI->db->table_exists($db_prefix . 'wcsync_sites')) {
    $CI->db->query("CREATE TABLE `{$db_prefix}wcsync_sites` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(150) NOT NULL,
        `site_url` VARCHAR(255) NOT NULL,
        `credential_id` INT(11) NOT NULL,
        `default_lead_status_id` INT(11) NOT NULL,
        `last_synced_at` DATETIME DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// 2. Orders table (synced orders mapping for idempotency)
if (!$CI->db->table_exists($db_prefix . 'wcsync_orders')) {
    $CI->db->query("CREATE TABLE `{$db_prefix}wcsync_orders` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `site_id` INT(11) NOT NULL,
        `wc_order_id` INT(11) NOT NULL,
        `salesos_order_id` INT(11) NOT NULL,
        `wc_status` VARCHAR(50) DEFAULT 'pending',
        `synced_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `site_order` (`site_id`, `wc_order_id`),
        KEY `salesos_order_id` (`salesos_order_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}
