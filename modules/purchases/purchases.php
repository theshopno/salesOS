<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Purchases Management
Description: Purchases management module family member (suppliers, Purchase Orders (PO), PO cost ledger, integration with inventory stock).
Version: 1.0.0
Requires at least: 2.3.4
*/

define('PURCHASES_MODULE_NAME', 'purchases');
define('PURCHASES_VERSION',     '1.0.0');

// ── Lifecycle ────────────────────────────────────────────────────────────────
register_activation_hook(PURCHASES_MODULE_NAME, 'purchases_activation_hook');
function purchases_activation_hook(): void
{
    $CI = &get_instance();
    require(__DIR__ . '/install.php');
}

// ── Bootstrap ────────────────────────────────────────────────────────────────
hooks()->add_action('app_init',   'purchases_load_resources');
hooks()->add_action('admin_init', 'purchases_register_menu');
hooks()->add_action('admin_init', 'purchases_register_permissions');

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

function purchases_load_resources(): void
{
    $CI = &get_instance();
    $CI->load->model(PURCHASES_MODULE_NAME . '/purchases_model');
}

// ── Permissions ──────────────────────────────────────────────────────────────
function purchases_register_permissions(): void
{
    register_staff_capabilities('purchases', [
        'capabilities' => [
            'view'    => 'View Purchases & Suppliers',
            'create'  => 'Add Suppliers & POs',
            'edit'    => 'Edit Suppliers & POs',
            'delete'  => 'Delete Suppliers & POs',
            'receive' => 'Mark POs as Received (Add Stock)',
        ],
    ], 'Purchases Management');
}

// ── Menu ─────────────────────────────────────────────────────────────────────
function purchases_register_menu(): void
{
    $CI = &get_instance();
    if ($CI->app_modules->is_active('salesos') && function_exists('salesos_ecommerce_enabled') && !salesos_ecommerce_enabled()) { 
        return; 
    }
    if (!staff_can('view', PURCHASES_MODULE_NAME)) { return; }

    if ($CI->app_modules->is_active('salesos')) {
        $CI->app_menu->add_sidebar_children_item('salesos', [
            'slug'     => 'purchases-orders', 
            'name'     => 'Purchase Orders',
            'href'     => admin_url('purchases/purchase_orders'),
            'position' => 6,
        ]);
    } else {
        $CI->app_menu->add_sidebar_menu_item('purchases-main', [
            'slug'     => 'purchases-main',
            'name'     => 'Purchases',
            'icon'     => 'fa fa-shopping-bag',
            'href'     => admin_url('purchases/purchase_orders'),
            'position' => 28,
        ]);
        $CI->app_menu->add_sidebar_children_item('purchases-main', [
            'slug'     => 'purchases-orders',
            'name'     => 'Purchase Orders',
            'href'     => admin_url('purchases/purchase_orders'),
            'position' => 5,
        ]);
        $CI->app_menu->add_sidebar_children_item('purchases-main', [
            'slug'     => 'purchases-suppliers',
            'name'     => 'Suppliers',
            'href'     => admin_url('purchases/suppliers'),
            'position' => 10,
        ]);
    }
}
