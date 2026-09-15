<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!isset($CI)) {
    $CI = &get_instance();
}

$db_prefix = db_prefix();

// 1. Suppliers table
if (!$CI->db->table_exists($db_prefix . 'purchases_suppliers')) {
    $CI->db->query("CREATE TABLE `{$db_prefix}purchases_suppliers` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(200) NOT NULL,
        `phone` VARCHAR(30) DEFAULT NULL,
        `email` VARCHAR(150) DEFAULT NULL,
        `address` TEXT DEFAULT NULL,
        `opening_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// A supplier you have bought from is never deleted — the purchase orders and the
// payables ledger have to stay. Retiring it keeps the history and takes it out of
// the pickers.
if (!$CI->db->field_exists('is_active', $db_prefix . 'purchases_suppliers')) {
    $CI->db->query("ALTER TABLE `{$db_prefix}purchases_suppliers`
        ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1");
}

// 2. Purchase Orders table
if (!$CI->db->table_exists($db_prefix . 'purchases_orders')) {
    $CI->db->query("CREATE TABLE `{$db_prefix}purchases_orders` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `supplier_id` INT(11) NOT NULL,
        `status` VARCHAR(30) NOT NULL DEFAULT 'draft',
        `total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `order_date` DATE DEFAULT NULL,
        `received_at` DATETIME DEFAULT NULL,
        `staff_id` INT(11) DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `supplier_id` (`supplier_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// 3. Purchase Order Items table
if (!$CI->db->table_exists($db_prefix . 'purchases_order_items')) {
    $CI->db->query("CREATE TABLE `{$db_prefix}purchases_order_items` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `purchase_order_id` INT(11) NOT NULL,
        `product_id` INT(11) NOT NULL,
        `qty` DECIMAL(15,2) NOT NULL,
        `unit_cost` DECIMAL(15,2) NOT NULL,
        PRIMARY KEY (`id`),
        KEY `purchase_order_id` (`purchase_order_id`),
        KEY `product_id` (`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// 4. Supplier Ledger table
if (!$CI->db->table_exists($db_prefix . 'purchases_supplier_ledger')) {
    $CI->db->query("CREATE TABLE `{$db_prefix}purchases_supplier_ledger` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `supplier_id` INT(11) NOT NULL,
        `entry_type` VARCHAR(20) NOT NULL,
        `amount` DECIMAL(15,2) NOT NULL,
        `ref_type` VARCHAR(30) DEFAULT NULL,
        `ref_id` INT(11) DEFAULT NULL,
        `note` TEXT DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `supplier_id` (`supplier_id`),
        KEY `ref` (`ref_type`, `ref_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}
