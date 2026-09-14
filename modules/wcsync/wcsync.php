<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: WooCommerce Sync (wcsync)
Description: WooCommerce channel connector module (syncs orders via REST API into salesos kernel, manages site credentials).
Version: 1.0.0
Requires at least: 2.3.4
*/

define('WCSYNC_MODULE_NAME', 'wcsync');
define('WCSYNC_VERSION',     '1.0.0');

// ── Lifecycle ────────────────────────────────────────────────────────────────
register_activation_hook(WCSYNC_MODULE_NAME, 'wcsync_activation_hook');
function wcsync_activation_hook(): void
{
    $CI = &get_instance();
    require(__DIR__ . '/install.php');
}

// ── Bootstrap ────────────────────────────────────────────────────────────────
hooks()->add_action('app_init',   'wcsync_load_resources');
hooks()->add_action('admin_init', 'wcsync_register_menu');
hooks()->add_action('admin_init', 'wcsync_register_permissions');
hooks()->add_action('after_cron_run', 'wcsync_cron_run');

function wcsync_load_resources(): void
{
    $CI = &get_instance();
    if (!$CI->app_modules->is_active('salesos')) { return; } // Guard
    $CI->load->model(WCSYNC_MODULE_NAME . '/wcsync_model');
}

// ── Permissions ──────────────────────────────────────────────────────────────
function wcsync_register_permissions(): void
{
    register_staff_capabilities('wcsync', [
        'capabilities' => [
            'view'     => 'View WooCommerce Dashboard',
            'settings' => 'Manage WooCommerce Sites',
            'sync'     => 'Trigger Manual Sync',
        ],
    ], 'WooCommerce Sync');
}

// ── Menu ─────────────────────────────────────────────────────────────────────
function wcsync_register_menu(): void
{
    $CI = &get_instance();
    if (!$CI->app_modules->is_active('salesos')) { return; } // Guard
    if (!staff_can('view', WCSYNC_MODULE_NAME)) { return; }

    $CI->app_menu->add_sidebar_children_item('salesos', [
        'slug'     => 'wcsync-dashboard', 
        'name'     => 'WooCommerce Sync',
        'href'     => admin_url('wcsync'), 
        'position' => 20,
    ]);

    $CI->app_menu->add_sidebar_children_item('salesos', [
        'slug'     => 'wcsync-settings', 
        'name'     => 'WC Settings',
        'href'     => admin_url('wcsync/settings'), 
        'position' => 21,
    ]);
}

// ── Cron Handler ─────────────────────────────────────────────────────────────
function wcsync_cron_run(): void
{
    $CI = &get_instance();
    if (!$CI->app_modules->is_active('salesos') || !$CI->app_modules->is_active('wcsync')) { return; }
    
    $CI->load->model('wcsync/wcsync_model');
    
    // Self-gate: run at most every 5 minutes (300 seconds)
    $last_sync = get_option('wcsync_last_cron_run');
    if ($last_sync == '' || (time() > ((int)$last_sync + 300))) {
        try {
            $CI->wcsync_model->sync();
            update_option('wcsync_last_cron_run', time());
        } catch (Exception $e) {
            log_activity('Wcsync Cron Error: ' . $e->getMessage());
        }
    }
}
