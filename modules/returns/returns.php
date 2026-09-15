<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Returns Management
Description: Handles order returns, restocking of sellable goods, and logging of damaged products.
Version: 1.0.0
Requires At Least: 2.3.0
*/

define('RETURNS_MODULE_NAME', 'returns');

// ── Lifecycle Hooks ──────────────────────────────────────────────────────────
register_activation_hook(RETURNS_MODULE_NAME, 'returns_activation_hook');
function returns_activation_hook(): void
{
    $CI = &get_instance();
    require(__DIR__ . '/install.php');
}

// ── Bootstrap ────────────────────────────────────────────────────────────────
hooks()->add_action('app_init',   'returns_load_resources');
hooks()->add_action('admin_init', 'returns_register_menu');
hooks()->add_action('admin_init', 'returns_register_permissions');

function returns_load_resources(): void
{
    $CI = &get_instance();
    // Soft guard: fail soft if salesos is not active
    if (!$CI->app_modules->is_active('salesos')) {
        return;
    }
    $CI->load->model(RETURNS_MODULE_NAME . '/returns_model');
}

// ── Permissions ──────────────────────────────────────────────────────────────
function returns_register_permissions(): void
{
    register_staff_capabilities('returns', [
        'capabilities' => [
            'view'   => 'View Returns',
            'create' => 'Create Return',
            'edit'   => 'Edit/Process Return',
            'delete' => 'Delete Return',
        ],
    ], 'Returns Management');
}

// ── Menu ─────────────────────────────────────────────────────────────────────
function returns_register_menu(): void
{
    $CI = &get_instance();
    if (!$CI->app_modules->is_active('salesos')) {
        return;
    }
    if (!salesos_ecommerce_enabled()) { return; } // e-commerce switched off in SalesOS settings
    if (!staff_can('view', RETURNS_MODULE_NAME)) {
        return;
    }

    $CI->app_menu->add_sidebar_children_item('salesos', [
        'slug'     => 'returns-list',
        'name'     => 'Returns List',
        'href'     => admin_url('returns'),
        'position' => 8,
    ]);
}
