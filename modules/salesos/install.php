<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!isset($CI)) {
    $CI = &get_instance();
}

$db_prefix = db_prefix();

// 0. Migration: this kernel was called `ecomcore` before the SalesOS name moved
// here from the telephony module. RENAME TABLE carries an existing install
// forward with its orders, line items, credentials and event log intact —
// the CREATE TABLE guards below then see the renamed tables and skip.
$migrated_from_ecomcore = false;

foreach (['credentials', 'orders', 'order_items', 'events'] as $legacy_table) {
    $old = $db_prefix . 'ecomcore_' . $legacy_table;
    $new = $db_prefix . 'salesos_' . $legacy_table;

    if ($CI->db->table_exists($old) && !$CI->db->table_exists($new)) {
        $CI->db->query("RENAME TABLE `{$old}` TO `{$new}`");
        $migrated_from_ecomcore = true;
    }
}

// Deregister the old kernel, or Perfex keeps trying to boot
// modules/ecomcore/ecomcore.php, which no longer exists.
if ($migrated_from_ecomcore) {
    $CI->db->where('module_name', 'ecomcore');
    $CI->db->delete($db_prefix . 'modules');
}

// Sibling modules link back to an order by column name. Renaming the column
// here, from the kernel, keeps the four of them consistent even if one is
// reactivated on its own — each module's own install.php defines the new name
// for fresh installs, but only an existing install needs this rename.
foreach ([
    'courier_consignments' => 'courier',
    'pos_sales'            => 'pos',
    'returns_orders'       => 'returns',
    'wcsync_orders'        => 'wcsync',
] as $table => $owner) {
    $full = $db_prefix . $table;

    if ($CI->db->table_exists($full)
        && $CI->db->field_exists('ecomcore_order_id', $full)
        && !$CI->db->field_exists('salesos_order_id', $full)) {
        // The column type differs between these tables (NOT NULL in courier,
        // returns and wcsync; nullable in pos), so read the existing definition
        // back rather than hardcoding one and silently changing nullability.
        $nullable = $CI->db->query(
            'SELECT IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = "ecomcore_order_id"',
            [$full]
        )->row();
        $null_sql = ($nullable && $nullable->IS_NULLABLE === 'YES') ? 'NULL DEFAULT NULL' : 'NOT NULL';

        $CI->db->query("ALTER TABLE `{$full}` CHANGE `ecomcore_order_id` `salesos_order_id` INT(11) {$null_sql}");
    }
}

// CodeIgniter caches the table and field lists for the lifetime of the request,
// so every table_exists()/field_exists() guard below would still be answering
// from the pre-migration snapshot — and the CREATE TABLE guards would try to
// recreate tables the RENAME above just produced. Drop the cache so the rest of
// this file sees the database as it actually is now.
$CI->db->data_cache = [];

// Feature switch for the whole e-commerce side. On by default; an install that
// only wants telephony turns it off in SalesOS → Settings → General.
add_option('salesos_ecommerce_enabled', '1');

// 1. Credentials table
if (!$CI->db->table_exists($db_prefix . 'salesos_credentials')) {
    $CI->db->query("CREATE TABLE `{$db_prefix}salesos_credentials` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `owner_module` VARCHAR(50) NOT NULL,
        `label` VARCHAR(150) NOT NULL,
        `cred_type` VARCHAR(30) NOT NULL,
        `payload` TEXT NOT NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `last_used_at` DATETIME DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `owner_module` (`owner_module`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// 2. Orders table
if (!$CI->db->table_exists($db_prefix . 'salesos_orders')) {
    $CI->db->query("CREATE TABLE `{$db_prefix}salesos_orders` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `channel` VARCHAR(30) NOT NULL,
        `channel_ref_id` VARCHAR(100) DEFAULT NULL,
        `lead_id` INT(11) DEFAULT NULL,
        `client_id` INT(11) DEFAULT NULL,
        `status` VARCHAR(30) NOT NULL DEFAULT 'pending',
        `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `shipping_charge` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `total` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `currency` VARCHAR(10) DEFAULT 'BDT',
        `payment_method` VARCHAR(60) DEFAULT NULL,
        `order_note` TEXT DEFAULT NULL,
        `order_date` DATETIME DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `channel_order` (`channel`, `channel_ref_id`),
        KEY `lead_id` (`lead_id`),
        KEY `client_id` (`client_id`),
        KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// 3. Order Items table
if (!$CI->db->table_exists($db_prefix . 'salesos_order_items')) {
    $CI->db->query("CREATE TABLE `{$db_prefix}salesos_order_items` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `order_id` INT(11) NOT NULL,
        `item_id` INT(11) DEFAULT NULL,
        `product_id` INT(11) DEFAULT NULL,
        `name` VARCHAR(255) NOT NULL,
        `sku` VARCHAR(100) DEFAULT NULL,
        `qty` DECIMAL(15,2) NOT NULL DEFAULT 1,
        `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `order_id` (`order_id`),
        KEY `product_id` (`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// 4. Events table
if (!$CI->db->table_exists($db_prefix . 'salesos_events')) {
    $CI->db->query("CREATE TABLE `{$db_prefix}salesos_events` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `event_type` VARCHAR(60) NOT NULL,
        `entity_type` VARCHAR(30) DEFAULT NULL,
        `entity_id` INT(11) DEFAULT NULL,
        `payload` JSON DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `event_type` (`event_type`),
        KEY `entity` (`entity_type`, `entity_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// 5. Indexed phone-suffix generated column on core CRM tables, shared by every
// module's phone-matching (salesos, wooconnector). The old approach matched via
// RIGHT(REPLACE(REPLACE(REPLACE(phonenumber,...)))) in the WHERE clause, which
// MySQL/MariaDB cannot use an index through — every lookup was a full table scan.
// A STORED generated column can be indexed normally.
foreach (['leads', 'contacts'] as $table) {
    if (!$CI->db->field_exists('phone_suffix10', $db_prefix . $table)) {
        $CI->db->query("ALTER TABLE `{$db_prefix}{$table}`
            ADD COLUMN `phone_suffix10` VARCHAR(10)
                GENERATED ALWAYS AS (RIGHT(REGEXP_REPLACE(phonenumber, '[^0-9]', ''), 10)) STORED,
            ADD INDEX `phone_suffix10` (`phone_suffix10`);");
    }
}
