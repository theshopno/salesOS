<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!isset($CI)) {
    $CI = &get_instance();
}

$db_prefix = db_prefix();

// 1. Returns Orders table
if (!$CI->db->table_exists($db_prefix . 'returns_orders')) {
    $res = $CI->db->query("CREATE TABLE `{$db_prefix}returns_orders` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `salesos_order_id` INT(11) NOT NULL,
        `status` VARCHAR(30) NOT NULL DEFAULT 'requested', -- requested, approved, rejected, received, restocked, refunded
        `reason` VARCHAR(255) DEFAULT NULL,
        `requested_by` VARCHAR(20) NOT NULL DEFAULT 'customer', -- customer | staff
        `staff_id` INT(11) DEFAULT NULL,
        `refund_amount` DECIMAL(15,2) DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `salesos_order_id` (`salesos_order_id`),
        KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    if (!$res) {
        $err = $CI->db->error();
        echo "CREATE TABLE returns_orders FAILED: " . json_encode($err) . "\n";
    }
}

// 2. Returns Order Items table
if (!$CI->db->table_exists($db_prefix . 'returns_order_items')) {
    $res = $CI->db->query("CREATE TABLE `{$db_prefix}returns_order_items` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `return_order_id` INT(11) NOT NULL,
        `order_item_id` INT(11) NOT NULL, -- FK tblsalesos_order_items.id
        `qty` DECIMAL(15,2) NOT NULL DEFAULT 1.00,
        `condition_note` VARCHAR(20) NOT NULL DEFAULT 'sellable', -- sellable | damaged
        PRIMARY KEY (`id`),
        KEY `return_order_id` (`return_order_id`),
        KEY `order_item_id` (`order_item_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    if (!$res) {
        $err = $CI->db->error();
        echo "CREATE TABLE returns_order_items FAILED: " . json_encode($err) . "\n";
    }
}
