<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!isset($CI)) {
    $CI = &get_instance();
}

$db_prefix = db_prefix();

$charset = !empty($CI->db->char_set) ? $CI->db->char_set : 'utf8mb4';
$collat  = !empty($CI->db->dbcollat) ? " COLLATE={$CI->db->dbcollat}" : "";

// Create fraudcheck lookups table
if (!$CI->db->table_exists($db_prefix . 'fraudcheck_lookups')) {
    $CI->db->query("CREATE TABLE `{$db_prefix}fraudcheck_lookups` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `phone` VARCHAR(20) NOT NULL,
        `total_parcel` INT(11) NOT NULL DEFAULT 0,
        `success_parcel` INT(11) NOT NULL DEFAULT 0,
        `cancelled_parcel` INT(11) NOT NULL DEFAULT 0,
        `success_ratio` DECIMAL(5,2) NOT NULL DEFAULT 0,
        `report_count` INT(11) NOT NULL DEFAULT 0,
        `risk_level` VARCHAR(20) DEFAULT NULL,
        `risk_color` VARCHAR(20) DEFAULT NULL,
        `raw_response` LONGTEXT DEFAULT NULL,
        `checked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `phone` (`phone`)
    ) ENGINE=InnoDB DEFAULT CHARSET={$charset}{$collat};");
} elseif (!empty($CI->db->dbcollat)) {
    // Harmonize collation with Perfex CRM db if it was previously created with server default
    @$CI->db->query("ALTER TABLE `{$db_prefix}fraudcheck_lookups` CONVERT TO CHARACTER SET {$charset} COLLATE {$CI->db->dbcollat};");
}
