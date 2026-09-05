<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Courier Integration Suite
Description: Integrates with Steadfast and other courier delivery APIs for automated booking and status syncing.
Version: 1.0.0
Requires At Least: 2.3.0
*/

define('COURIER_MODULE_NAME', 'courier');

// ── Lifecycle Hooks ──────────────────────────────────────────────────────────
register_activation_hook(COURIER_MODULE_NAME, 'courier_activation_hook');
function courier_activation_hook(): void
{
    $CI = &get_instance();
    require(__DIR__ . '/install.php');
}

// ── Bootstrap ────────────────────────────────────────────────────────────────
hooks()->add_action('app_init',   'courier_load_resources');
hooks()->add_action('admin_init', 'courier_register_permissions');

// Background status sync hook
hooks()->add_action('after_cron_run', 'courier_handle_cron_sync');

function courier_load_resources(): void
{
    $CI = &get_instance();
    if (!$CI->app_modules->is_active('ecomcore')) {
        return;
    }
    $CI->load->model(COURIER_MODULE_NAME . '/courier_model');
}

// ── Permissions ──────────────────────────────────────────────────────────────
function courier_register_permissions(): void
{
    register_staff_capabilities('courier', [
        'capabilities' => [
            'view'   => 'View Courier Config & Consignments',
            'create' => 'Book Consignments',
            'edit'   => 'Edit Accounts / Sync Statuses',
            'delete' => 'Delete Accounts / Consignments',
        ],
    ], 'Courier Management');
}

// ── Cron Handler ─────────────────────────────────────────────────────────────
function courier_handle_cron_sync(): void
{
    $CI = &get_instance();
    if (!$CI->app_modules->is_active(COURIER_MODULE_NAME)) {
        return;
    }
    // Prevent PHP timeout on long cron loops
    @set_time_limit(300);
    
    $CI->load->model('courier/courier_model');
    // Call background status sync (thoroughly throttled internally)
    $CI->courier_model->sync_all_active_statuses();
}
