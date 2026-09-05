<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!isset($CI)) {
    $CI = &get_instance();
}

$db_prefix = db_prefix();

// 1. Categories table
if (!$CI->db->table_exists($db_prefix . 'inventory_categories')) {
    $CI->db->query("CREATE TABLE `{$db_prefix}inventory_categories` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(150) NOT NULL,
        `parent_id` INT(11) DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// 2. Products table
if (!$CI->db->table_exists($db_prefix . 'inventory_products')) {
    $CI->db->query("CREATE TABLE `{$db_prefix}inventory_products` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `item_id` INT(11) DEFAULT NULL,
        `sku` VARCHAR(100) DEFAULT NULL,
        `name` VARCHAR(255) NOT NULL,
        `category_id` INT(11) DEFAULT NULL,
        `image` VARCHAR(255) DEFAULT NULL,
        `reorder_level` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `sku` (`sku`),
        KEY `item_id` (`item_id`),
        KEY `category_id` (`category_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} else {
    if (!$CI->db->field_exists('image', $db_prefix . 'inventory_products')) {
        $CI->db->query("ALTER TABLE `{$db_prefix}inventory_products` ADD COLUMN `image` VARCHAR(255) DEFAULT NULL AFTER `category_id`;");
    }
}

// 3. Warehouses table
if (!$CI->db->table_exists($db_prefix . 'inventory_warehouses')) {
    $CI->db->query("CREATE TABLE `{$db_prefix}inventory_warehouses` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(150) NOT NULL,
        `is_default` TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// Ensure a default warehouse exists
$CI->db->where('is_default', 1);
$default_warehouse = $CI->db->get($db_prefix . 'inventory_warehouses')->row();
if (!$default_warehouse) {
    $CI->db->insert($db_prefix . 'inventory_warehouses', [
        'name'       => 'Default Warehouse',
        'is_default' => 1,
    ]);
}

// 4. Stock level table (snapshot)
if (!$CI->db->table_exists($db_prefix . 'inventory_stock')) {
    $CI->db->query("CREATE TABLE `{$db_prefix}inventory_stock` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `product_id` INT(11) NOT NULL,
        `warehouse_id` INT(11) NOT NULL,
        `qty_on_hand` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `qty_reserved` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `product_warehouse` (`product_id`, `warehouse_id`),
        KEY `warehouse_id` (`warehouse_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// 5. Stock ledger table (append-only)
if (!$CI->db->table_exists($db_prefix . 'inventory_stock_ledger')) {
    $CI->db->query("CREATE TABLE `{$db_prefix}inventory_stock_ledger` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `product_id` INT(11) NOT NULL,
        `warehouse_id` INT(11) NOT NULL,
        `movement_type` VARCHAR(30) NOT NULL,
        `qty` DECIMAL(15,2) NOT NULL,
        `balance_after` DECIMAL(15,2) NOT NULL,
        `ref_type` VARCHAR(30) DEFAULT NULL,
        `ref_id` INT(11) DEFAULT NULL,
        `staff_id` INT(11) DEFAULT NULL,
        `note` TEXT DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `product_id` (`product_id`),
        KEY `warehouse_id` (`warehouse_id`),
        KEY `ref` (`ref_type`, `ref_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}
