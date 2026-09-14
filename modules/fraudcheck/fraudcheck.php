<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Fraud Checking Module
Description: Customer risk analysis integration with BDCourier checker API. Auto-triggers on specific lead status changes.
Version: 1.0.0
Requires at least: 2.3.4
*/

define('FRAUDCHECK_MODULE_NAME', 'fraudcheck');

// ── Lifecycle Hooks ──────────────────────────────────────────────────────────
register_activation_hook(FRAUDCHECK_MODULE_NAME, 'fraudcheck_activation_hook');
function fraudcheck_activation_hook(): void
{
    $CI = &get_instance();
    require(__DIR__ . '/install.php');
}

// ── Bootstrap ────────────────────────────────────────────────────────────────
hooks()->add_action('app_init', 'fraudcheck_load_resources');
hooks()->add_action('salesos_order_created', 'fraudcheck_handle_order_created');

function fraudcheck_load_resources(): void
{
    $CI = &get_instance();
    if (!$CI->app_modules->is_active('salesos')) {
        return;
    }
    $CI->load->model(FRAUDCHECK_MODULE_NAME . '/fraudcheck_model');
}

/**
 * Hook listener: automatically fraud-check a customer's phone as soon as their
 * order is created — fires for every channel (POS, WooCommerce, future
 * Shopify/Laravel connectors) via the kernel's own order-creation event,
 * rather than watching for a WooCommerce-specific lead-status transition that
 * no channel actually creates (the previous design waited on a
 * `wcsync_called_status_id` option wcsync never sets, so this never ran).
 */
function fraudcheck_handle_order_created($order_id): void
{
    $CI = &get_instance();
    if (!$CI->app_modules->is_active(FRAUDCHECK_MODULE_NAME)) {
        return;
    }

    $db_prefix = db_prefix();
    $sql = "
        SELECT COALESCE(con.phonenumber, c.phonenumber, l.phonenumber, '') as customer_phone
        FROM {$db_prefix}salesos_orders o
        LEFT JOIN {$db_prefix}contacts con ON con.userid = o.client_id AND con.is_primary = 1
        LEFT JOIN {$db_prefix}clients c ON c.userid = o.client_id
        LEFT JOIN {$db_prefix}leads l ON l.id = o.lead_id
        WHERE o.id = ?
    ";
    $row = $CI->db->query($sql, [(int) $order_id])->row();

    if ($row && !empty($row->customer_phone)) {
        $CI->load->model('fraudcheck/fraudcheck_model');
        // Pull API and cache the results automatically; the model's own 48h
        // cache keeps repeat orders from the same customer cheap.
        $CI->fraudcheck_model->check($row->customer_phone);
    }
}
