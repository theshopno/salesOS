<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!isset($CI)) {
    $CI = &get_instance();
}

$db_prefix = db_prefix();

// 1. Registers table
if (!$CI->db->table_exists($db_prefix . 'pos_registers')) {
    $CI->db->query("CREATE TABLE `{$db_prefix}pos_registers` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) NOT NULL,
        `warehouse_id` INT(11) NOT NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// 2. Sessions table
if (!$CI->db->table_exists($db_prefix . 'pos_sessions')) {
    $CI->db->query("CREATE TABLE `{$db_prefix}pos_sessions` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `register_id` INT(11) NOT NULL,
        `staff_id` INT(11) NOT NULL,
        `opening_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `closing_balance` DECIMAL(15,2) DEFAULT NULL,
        `opened_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `closed_at` DATETIME DEFAULT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'open',
        PRIMARY KEY (`id`),
        KEY `register_id` (`register_id`),
        KEY `staff_id` (`staff_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// 3. Sales link table
if (!$CI->db->table_exists($db_prefix . 'pos_sales')) {
    $CI->db->query("CREATE TABLE `{$db_prefix}pos_sales` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `session_id` INT(11) NOT NULL,
        `invoice_id` INT(11) NOT NULL,
        `salesos_order_id` INT(11) DEFAULT NULL,
        `client_id` INT(11) DEFAULT NULL,
        `cashier_staff_id` INT(11) NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `session_id` (`session_id`),
        KEY `invoice_id` (`invoice_id`),
        KEY `salesos_order_id` (`salesos_order_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// 4. Payments table
if (!$CI->db->table_exists($db_prefix . 'pos_payments')) {
    $CI->db->query("CREATE TABLE `{$db_prefix}pos_payments` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `pos_sale_id` INT(11) NOT NULL,
        `session_id` INT(11) NOT NULL,
        `payment_method` VARCHAR(50) NOT NULL,
        `amount` DECIMAL(15,2) NOT NULL,
        `transaction_ref` VARCHAR(150) DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `pos_sale_id` (`pos_sale_id`),
        KEY `session_id` (`session_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// 5. Holds table (Cart parking)
if (!$CI->db->table_exists($db_prefix . 'pos_holds')) {
    $CI->db->query("CREATE TABLE `{$db_prefix}pos_holds` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `session_id` INT(11) NOT NULL,
        `client_id` INT(11) NOT NULL,
        `cart_data` LONGTEXT NOT NULL,
        `hold_note` VARCHAR(255) DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `session_id` (`session_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// Seeding Default Walk-in Customer
if (!get_option('pos_default_walkin_client_id')) {
    $CI->db->where('company', 'Walk-in Customer');
    $existing_walkin = $CI->db->get($db_prefix . 'clients')->row();
    if ($existing_walkin) {
        add_option('pos_default_walkin_client_id', $existing_walkin->userid);
    } else {
        $CI->db->insert($db_prefix . 'clients', [
            'company' => 'Walk-in Customer',
            'datecreated' => date('Y-m-d H:i:s'),
            'active' => 1
        ]);
        $client_id = $CI->db->insert_id();
        add_option('pos_default_walkin_client_id', $client_id);
    }
}

// Seeding Default Register 1
$wh_id = 1;
if ($CI->db->table_exists($db_prefix . 'inventory_warehouses')) {
    $CI->db->where('is_default', 1);
    $wh = $CI->db->get($db_prefix . 'inventory_warehouses')->row();
    if ($wh) {
        $wh_id = $wh->id;
    }
}

$CI->db->where('name', 'Register 1');
$existing_reg = $CI->db->get($db_prefix . 'pos_registers')->row();
if (!$existing_reg) {
    $CI->db->insert($db_prefix . 'pos_registers', [
        'name' => 'Register 1',
        'warehouse_id' => $wh_id,
        'is_active' => 1
    ]);
}
