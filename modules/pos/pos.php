<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Point of Sale (pos)
Description: POS cashier module for counter sales, integrated with salesos kernel, core invoices, and inventory tracking.
Version: 1.0.0
Requires at least: 2.3.4
*/

define('POS_MODULE_NAME', 'pos');
define('POS_VERSION',     '1.0.0');

// ── Lifecycle ────────────────────────────────────────────────────────────────
register_activation_hook(POS_MODULE_NAME, 'pos_activation_hook');
function pos_activation_hook(): void
{
    $CI = &get_instance();
    require(__DIR__ . '/install.php');
}

// ── Bootstrap ────────────────────────────────────────────────────────────────
hooks()->add_action('app_init',   'pos_load_resources');
hooks()->add_action('admin_init', 'pos_register_menu');
hooks()->add_action('admin_init', 'pos_register_permissions');

function pos_load_resources(): void
{
    $CI = &get_instance();
    if (!$CI->app_modules->is_active('salesos')) { return; } // Guard
    $CI->load->model(POS_MODULE_NAME . '/pos_model');
}

// ── Permissions ──────────────────────────────────────────────────────────────
function pos_register_permissions(): void
{
    register_staff_capabilities('pos', [
        'capabilities' => [
            'view'             => 'Access POS Module',
            'cashier_access'   => 'Open Sessions & Process Sales',
        ],
    ], 'Point of Sale');
}

// ── Menu ─────────────────────────────────────────────────────────────────────
function pos_register_menu(): void
{
    $CI = &get_instance();
    if (!$CI->app_modules->is_active('salesos')) { return; } // Guard
    if (!staff_can('view', POS_MODULE_NAME)) { return; }

    $CI->app_menu->add_sidebar_children_item('salesos', [
        'slug'     => 'pos-cashier',
        'name'     => 'POS Sale',
        'href'     => admin_url('pos'),
        // The counter screen — for a shop seller this is the most-opened page
        // in the whole product, so it sits third, not last.
        'position' => 3,
    ]);

}
