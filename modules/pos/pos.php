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

function pos_load_resources(): void
{
    $CI = &get_instance();
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
    if ($CI->app_modules->is_active('salesos') && function_exists('salesos_ecommerce_enabled') && !salesos_ecommerce_enabled()) { 
        return; 
    }
    if (!staff_can('view', POS_MODULE_NAME)) { return; }

    $CI->app_menu->add_sidebar_menu_item('pos-main', [
        'slug'     => 'pos-main',
        'name'     => 'POS',
        'icon'     => 'fa fa-calculator',
        'href'     => admin_url('pos'),
        'position' => 27,
    ]);
}
