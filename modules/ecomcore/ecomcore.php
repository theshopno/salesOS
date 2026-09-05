<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: E-commerce Core
Description: Master module for the E-commerce Management Suite (owns credentials, orders, events and admin dashboards).
Version: 1.0.0
Requires at least: 2.3.4
*/

define('ECOMCORE_MODULE_NAME', 'ecomcore');
define('ECOMCORE_VERSION',     '1.0.0');

// ── Lifecycle ────────────────────────────────────────────────────────────────
register_activation_hook(ECOMCORE_MODULE_NAME, 'ecomcore_activation_hook');
function ecomcore_activation_hook(): void
{
    $CI = &get_instance();
    require(__DIR__ . '/install.php');
}

// ── Bootstrap ────────────────────────────────────────────────────────────────
hooks()->add_action('app_init',   'ecomcore_load_resources');
hooks()->add_action('admin_init', 'ecomcore_register_menu');
hooks()->add_action('admin_init', 'ecomcore_register_permissions');

function ecomcore_load_resources(): void
{
    $CI = &get_instance();
    $CI->load->model(ECOMCORE_MODULE_NAME . '/ecomcore_model');
}

// ── Permissions ──────────────────────────────────────────────────────────────
function ecomcore_register_permissions(): void
{
    register_staff_capabilities('ecomcore', [
        'capabilities' => [
            'view'     => 'View E-commerce Dashboard',
            'settings' => 'Manage Channels & Credentials',
        ],
    ], 'E-commerce Management');
}

// ── Menu ─────────────────────────────────────────────────────────────────────
function ecomcore_register_menu(): void
{
    if (!staff_can('view', ECOMCORE_MODULE_NAME) && !staff_can('settings', ECOMCORE_MODULE_NAME)) { return; }
    $CI = &get_instance();

    $CI->app_menu->add_sidebar_menu_item(ECOMCORE_MODULE_NAME, [
        'name'     => 'E-commerce', 
        'icon'     => 'fa fa-shopping-cart', 
        'position' => 30,
    ]);
    
    if (staff_can('view', ECOMCORE_MODULE_NAME)) {
        $CI->app_menu->add_sidebar_children_item(ECOMCORE_MODULE_NAME, [
            'slug'     => 'ecomcore-dashboard', 
            'name'     => 'Dashboard',
            'href'     => admin_url('ecomcore'), 
            'position' => 1,
        ]);
        $CI->app_menu->add_sidebar_children_item(ECOMCORE_MODULE_NAME, [
            'slug'     => 'ecomcore-orders', 
            'name'     => 'Orders',
            'href'     => admin_url('ecomcore/orders'), 
            'position' => 1.5,
        ]);
    }

    if (staff_can('settings', ECOMCORE_MODULE_NAME)) {
        $CI->app_menu->add_sidebar_children_item(ECOMCORE_MODULE_NAME, [
            'slug'     => 'ecomcore-settings', 
            'name'     => 'Settings',
            'href'     => admin_url('ecomcore/settings'), 
            'position' => 2,
        ]);
    }
}

/**
 * Format e-commerce price or numbers dynamically based on system setting:
 * remove_decimals_on_zero (Remove decimals on numbers/money with zero decimals).
 */
function ecomcore_format_number($number, $decimals = null)
{
    if (!is_numeric($number)) {
        return $number;
    }
    
    if ($decimals === null) {
        $decimals = get_decimal_places();
    }

    if (get_option('remove_decimals_on_zero') == 1) {
        if (round($number, $decimals) == (int)$number) {
            $decimals = 0;
        }
    }

    $decimal_separator  = get_option('decimal_separator') ?: '.';
    $thousand_separator = get_option('thousand_separator') ?: '';

    return number_format($number, $decimals, $decimal_separator, $thousand_separator);
}
