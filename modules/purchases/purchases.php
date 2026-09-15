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

function purchases_load_resources(): void
{
    $CI = &get_instance();
    if (!$CI->app_modules->is_active('salesos') || !$CI->app_modules->is_active('inventory')) { return; } // Guard
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
    if (!$CI->app_modules->is_active('salesos') || !$CI->app_modules->is_active('inventory')) { return; } // Guard
    if (!salesos_ecommerce_enabled()) { return; } // e-commerce switched off in SalesOS settings
    if (!staff_can('view', PURCHASES_MODULE_NAME)) { return; }

    $CI->app_menu->add_sidebar_children_item('salesos', [
        'slug'     => 'purchases-orders', 
        'name'     => 'Purchase Orders',
        'href'     => admin_url('purchases/purchase_orders'),
        // Restocking is regular work; supplier records are not.
        'position' => 6,
    ]);
}
