<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!isset($CI)) {
    $CI = &get_instance();
}

$db_prefix = db_prefix();

// 1. Courier Accounts table
if (!$CI->db->table_exists($db_prefix . 'courier_accounts')) {
    $res = $CI->db->query("CREATE TABLE `{$db_prefix}courier_accounts` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `provider` VARCHAR(30) NOT NULL, -- 'steadfast' | 'pathao' | 'redx'
        `label` VARCHAR(150) NOT NULL,
        `credential_id` INT(11) NOT NULL, -- FK tblecomcore_credentials.id
        `pickup_address` TEXT DEFAULT NULL,
        `is_default` TINYINT(1) NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `provider` (`provider`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    if (!$res) {
        $err = $CI->db->error();
        echo "CREATE TABLE courier_accounts FAILED: " . json_encode($err) . "\n";
    }
}

// 2. Courier Consignments table
if (!$CI->db->table_exists($db_prefix . 'courier_consignments')) {
    $res = $CI->db->query("CREATE TABLE `{$db_prefix}courier_consignments` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `ecomcore_order_id` INT(11) NOT NULL,
        `courier_account_id` INT(11) NOT NULL,
        `consignment_id` VARCHAR(100) DEFAULT NULL,
        `tracking_id` VARCHAR(100) DEFAULT NULL,
        `cod_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `status` VARCHAR(30) NOT NULL DEFAULT 'pending', -- pending, booked, delivered, partial_delivered, cancelled, hold, in_review
        `raw_response` TEXT DEFAULT NULL,
        `last_synced_at` DATETIME DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `ecomcore_order_id` (`ecomcore_order_id`),
        KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    if (!$res) {
        $err = $CI->db->error();
        echo "CREATE TABLE courier_consignments FAILED: " . json_encode($err) . "\n";
    }
}

// 3. Dynamic Column Alters for Pathao
if (!$CI->db->field_exists('store_id', $db_prefix . 'courier_accounts')) {
    $CI->db->query("ALTER TABLE `{$db_prefix}courier_accounts` ADD `store_id` VARCHAR(50) DEFAULT NULL AFTER `pickup_address`;");
}
if (!$CI->db->field_exists('environment', $db_prefix . 'courier_accounts')) {
    $CI->db->query("ALTER TABLE `{$db_prefix}courier_accounts` ADD `environment` VARCHAR(30) DEFAULT NULL AFTER `store_id`;");
}
