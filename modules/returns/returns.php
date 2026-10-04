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

function returns_load_resources(): void
{
    $CI = &get_instance();
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
    if ($CI->app_modules->is_active('salesos') && function_exists('salesos_ecommerce_enabled') && !salesos_ecommerce_enabled()) { 
        return; 
    }
    if (!staff_can('view', RETURNS_MODULE_NAME)) {
        return;
    }

    if ($CI->app_modules->is_active('salesos')) {
        $CI->app_menu->add_sidebar_children_item('salesos', [
            'slug'     => 'returns-list',
            'name'     => 'Returns List',
            'href'     => admin_url('returns'),
            'position' => 8,
        ]);
    } else {
        $CI->app_menu->add_sidebar_menu_item('returns-main', [
            'slug'     => 'returns-main',
            'name'     => 'Returns',
            'icon'     => 'fa fa-undo',
            'href'     => admin_url('returns'),
            'position' => 29,
        ]);
        $CI->app_menu->add_sidebar_children_item('returns-main', [
            'slug'     => 'returns-list',
            'name'     => 'Returns List',
            'href'     => admin_url('returns'),
            'position' => 5,
        ]);
    }
}
