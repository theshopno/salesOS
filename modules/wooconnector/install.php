<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!isset($CI)) {
    $CI = &get_instance();
}

// ── Sites (WooCommerce store connections) ────────────────────────────────────
if (!$CI->db->table_exists(db_prefix() . 'wooconnector_sites')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'wooconnector_sites` (
        `id`                     INT(11)      NOT NULL AUTO_INCREMENT,
        `name`                   VARCHAR(150) NOT NULL,
        `site_url`               VARCHAR(255) NOT NULL,
        `consumer_key`           VARCHAR(255) NOT NULL,
        `consumer_secret`        VARCHAR(255) NOT NULL,
        `default_lead_status_id` INT(11)      NOT NULL,
        `last_synced_at`         DATETIME     DEFAULT NULL,
        `created_at`             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
}

// ── Order → Lead mapping (idempotency) ───────────────────────────────────────
if (!$CI->db->table_exists(db_prefix() . 'wooconnector_orders')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'wooconnector_orders` (
        `id`                INT(11)      NOT NULL AUTO_INCREMENT,
        `site_id`           INT(11)      NOT NULL,
        `wc_order_id`       INT(11)      NOT NULL,
        `lead_id`           INT(11)      NULL,
        `matched_client_id` INT(11)      NULL DEFAULT NULL,
        `wc_status`         VARCHAR(50)  DEFAULT NULL,
        `order_date`        DATETIME     NULL DEFAULT NULL,
        `synced_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `site_order` (`site_id`, `wc_order_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
} else {
    // v1.1.0: repeat-customer matching + order-date-based stats (added after the initial release)
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'wooconnector_orders` MODIFY `lead_id` INT(11) NULL;');

    if (!$CI->db->field_exists('matched_client_id', db_prefix() . 'wooconnector_orders')) {
        $CI->db->query('ALTER TABLE `' . db_prefix() . 'wooconnector_orders` ADD `matched_client_id` INT(11) NULL DEFAULT NULL AFTER `lead_id`;');
    }

    if (!$CI->db->field_exists('order_date', db_prefix() . 'wooconnector_orders')) {
        $CI->db->query('ALTER TABLE `' . db_prefix() . 'wooconnector_orders` ADD `order_date` DATETIME NULL DEFAULT NULL AFTER `wc_status`;');
    }
}

// ── Custom fields on Leads for order details that have no native column ─────
$wooconnector_custom_fields = [
    [
        'name' => 'WooCommerce Order ID',
        'slug' => 'wooconnector_order_id',
        'type' => 'text',
    ],
    [
        'name' => 'WooCommerce Items',
        'slug' => 'wooconnector_items',
        'type' => 'textarea',
    ],
    [
        'name' => 'WooCommerce Shipping Charge',
        'slug' => 'wooconnector_shipping_charge',
        'type' => 'text',
    ],
    [
        'name' => 'WooCommerce Total Amount',
        'slug' => 'wooconnector_total_amount',
        'type' => 'text',
    ],
    [
        'name' => 'WooCommerce Order Note',
        'slug' => 'wooconnector_order_note',
        'type' => 'textarea',
    ],
    [
        'name' => 'WooCommerce Payment Method',
        'slug' => 'wooconnector_payment_method',
        'type' => 'text',
    ],
];

foreach ($wooconnector_custom_fields as $field) {
    $CI->db->where('slug', $field['slug']);
    $CI->db->where('fieldto', 'leads');
    $exists = $CI->db->get(db_prefix() . 'customfields')->row();

    if (!$exists) {
        $CI->db->insert(db_prefix() . 'customfields', [
            'fieldto'                => 'leads',
            'name'                   => $field['name'],
            'slug'                   => $field['slug'],
            'required'               => 0,
            'type'                   => $field['type'],
            'options'                => null,
            'display_inline'         => 0,
            'field_order'            => 0,
            'active'                 => 1,
            'show_on_pdf'            => 0,
            'show_on_ticket_form'    => 0,
            'only_admin'             => 0,
            'show_on_table'          => 0,
            'show_on_client_portal'  => 0,
            'disalow_client_to_edit' => 1,
            'bs_column'              => 12,
            'default_value'          => null,
        ]);
    }
}

// ── Website Orders workflow: extra lead status stages ────────────────────────
// Global rows, shared with the rest of the CRM's lead pipeline (including the core Kanban board).
$wooconnector_statuses = [
    ['name' => 'Called',    'statusorder' => 4, 'color' => '#3597dd'],
    ['name' => 'Confirmed', 'statusorder' => 5, 'color' => '#3ba33b'],
    ['name' => 'Cancelled', 'statusorder' => 6, 'color' => '#e05353'],
];

$wooconnector_status_ids = [];

foreach ($wooconnector_statuses as $status) {
    $CI->db->where('name', $status['name']);
    $existing = $CI->db->get(db_prefix() . 'leads_status')->row();

    if ($existing) {
        $wooconnector_status_ids[$status['name']] = $existing->id;
        continue;
    }

    $CI->db->insert(db_prefix() . 'leads_status', [
        'name'        => $status['name'],
        'statusorder' => $status['statusorder'],
        'color'       => $status['color'],
        'isdefault'   => 0,
    ]);

    $wooconnector_status_ids[$status['name']] = $CI->db->insert_id();
}

// Stored so the lead_status_changed hook can match by id (rename-proof) instead of a hardcoded name.
if (!option_exists('wooconnector_confirmed_status_id')) {
    add_option('wooconnector_confirmed_status_id', $wooconnector_status_ids['Confirmed']);
}

if (!option_exists('wooconnector_cancelled_status_id')) {
    add_option('wooconnector_cancelled_status_id', $wooconnector_status_ids['Cancelled']);
}
