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

if (!function_exists('salesos_format_number')) {
    function salesos_format_number($number, $decimals = null)
    {
        if (!is_numeric($number)) {
            return $number;
        }
        if ($decimals === null) {
            $decimals = function_exists('get_decimal_places') ? get_decimal_places() : 2;
        }
        if (get_option('remove_decimals_on_zero') == 1) {
            if (round($number, $decimals) == (int)$number) {
                $decimals = 0;
            }
        }
        $decimal_separator  = get_option('decimal_separator') ?: '.';
        $thousand_separator = get_option('thousand_separator') ?: '';
        return number_format((float)$number, $decimals, $decimal_separator, $thousand_separator);
    }
}

function wcsync_load_resources(): void
{
    $CI = &get_instance();
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
    if (!salesos_ecommerce_enabled()) { return; } // e-commerce switched off in SalesOS settings
    if (!staff_can('view', WCSYNC_MODULE_NAME)) { return; }

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
