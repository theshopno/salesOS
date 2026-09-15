<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Inventory Management
Description: Inventory management module family member (SKUs, stock ledger, stock sync, warehouse, order deduct hooks).
Version: 1.0.0
Requires at least: 2.3.4
*/

define('INVENTORY_MODULE_NAME', 'inventory');
define('INVENTORY_VERSION',     '1.0.0');

// ── Lifecycle ────────────────────────────────────────────────────────────────
register_activation_hook(INVENTORY_MODULE_NAME, 'inventory_activation_hook');
function inventory_activation_hook(): void
{
    $CI = &get_instance();
    require(__DIR__ . '/install.php');
}

// ── Bootstrap ────────────────────────────────────────────────────────────────
hooks()->add_action('app_init',   'inventory_load_resources');
hooks()->add_action('admin_init', 'inventory_register_menu');
hooks()->add_action('admin_init', 'inventory_register_permissions');

// Listeners for Salesos order events
hooks()->add_action('salesos_order_confirmed', 'inventory_handle_order_confirmed');
hooks()->add_action('salesos_order_cancelled', 'inventory_handle_order_cancelled');
hooks()->add_action('salesos_stock_returned',  'inventory_handle_stock_returned');

function inventory_load_resources(): void
{
    $CI = &get_instance();
    if (!$CI->app_modules->is_active('salesos')) { return; } // Guard
    $CI->load->model(INVENTORY_MODULE_NAME . '/inventory_model');
}

// ── Permissions ──────────────────────────────────────────────────────────────
function inventory_register_permissions(): void
{
    register_staff_capabilities('inventory', [
        'capabilities' => [
            'view'   => 'View Products & Stock',
            'create' => 'Add Products',
            'edit'   => 'Edit Products',
            'delete' => 'Delete Products',
            'adjust' => 'Adjust Stock (Ledger)',
        ],
    ], 'Inventory Management');
}

// ── Menu ─────────────────────────────────────────────────────────────────────
function inventory_register_menu(): void
{
    $CI = &get_instance();
    if (!$CI->app_modules->is_active('salesos')) { return; } // Guard
    if (!salesos_ecommerce_enabled()) { return; } // e-commerce switched off in SalesOS settings
    if (!staff_can('view', INVENTORY_MODULE_NAME)) { return; }

    // Only the two screens used during normal stock work stay in main
    // navigation. Categories and the inventory settings are set up once and
    // then left alone, so they are reached from SalesOS → Settings instead.
    $CI->app_menu->add_sidebar_children_item('salesos', [
        'slug'     => 'inventory-products',
        'name'     => 'Products',
        'href'     => admin_url('inventory/products'),
        'position' => 4,
    ]);

    $CI->app_menu->add_sidebar_children_item('salesos', [
        'slug'     => 'inventory-adjustments',
        'name'     => 'Stock Ledger',
        'href'     => admin_url('inventory/adjustments'),
        'position' => 7,
    ]);
}

// ── Hook Handlers ────────────────────────────────────────────────────────────
function inventory_handle_order_confirmed($order_id): void
{
    $CI = &get_instance();
    if (!$CI->app_modules->is_active('salesos')) { return; }
    $CI->load->model('inventory/inventory_model');
    $CI->inventory_model->handle_order_confirmed($order_id);
}

function inventory_handle_order_cancelled($order_id): void
{
    $CI = &get_instance();
    if (!$CI->app_modules->is_active('salesos')) { return; }
    $CI->load->model('inventory/inventory_model');
    $CI->inventory_model->handle_order_cancelled($order_id);
}

function inventory_handle_stock_returned($order_id): void
{
    $CI = &get_instance();
    if (!$CI->app_modules->is_active('salesos')) { return; }
    $CI->load->model('inventory/inventory_model');
    $CI->inventory_model->handle_stock_returned($order_id);
}
