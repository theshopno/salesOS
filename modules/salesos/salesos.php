<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: E-commerce Core
Description: Master module for the E-commerce Management Suite (owns credentials, orders, events and admin dashboards).
Version: 1.0.0
Requires at least: 2.3.4
*/

define('SALESOS_MODULE_NAME', 'salesos');
define('SALESOS_VERSION',     '1.0.0');

// ── Lifecycle ────────────────────────────────────────────────────────────────
register_activation_hook(SALESOS_MODULE_NAME, 'salesos_activation_hook');
function salesos_activation_hook(): void
{
    $CI = &get_instance();
    require(__DIR__ . '/install.php');
}

// ── Bootstrap ────────────────────────────────────────────────────────────────
hooks()->add_action('app_init',   'salesos_load_resources');
hooks()->add_action('admin_init', 'salesos_register_menu');
hooks()->add_action('admin_init', 'salesos_register_permissions');

function salesos_load_resources(): void
{
    $CI = &get_instance();
    $CI->load->model(SALESOS_MODULE_NAME . '/salesos_model');
}

// ── Permissions ──────────────────────────────────────────────────────────────
/**
 * Is the e-commerce side switched on?
 *
 * An install that only wants telephony turns this off rather than hunting
 * through Setup → Modules for nine separate modules. The modules stay installed
 * and keep their data; they just stop appearing and stop serving pages, so
 * turning it back on restores everything exactly as it was.
 */
function salesos_ecommerce_enabled(): bool
{
    return get_option('salesos_ecommerce_enabled') !== '0';
}

/**
 * Guard for controllers that only make sense when e-commerce is on. The kernel's
 * own settings screen deliberately does NOT call this — that is where the switch
 * lives, so blocking it would leave no way back.
 */
function salesos_require_ecommerce(): void
{
    if (salesos_ecommerce_enabled()) {
        return;
    }

    set_alert('warning', 'E-commerce features are turned off. You can turn them back on in SalesOS → Settings.');
    redirect(admin_url('salesos/settings'));
}

function salesos_register_permissions(): void
{
    register_staff_capabilities('salesos', [
        'capabilities' => [
            'view'     => 'View E-commerce Dashboard',
            'settings' => 'Manage Channels & Credentials',
        ],
    ], 'E-commerce Management');
}

// ── Menu ─────────────────────────────────────────────────────────────────────
function salesos_register_menu(): void
{
    if (!staff_can('view', SALESOS_MODULE_NAME) && !staff_can('settings', SALESOS_MODULE_NAME)) { return; }
    $CI = &get_instance();

    $CI->app_menu->add_sidebar_menu_item(SALESOS_MODULE_NAME, [
        'name'     => 'SalesOS',
        'icon'     => 'fa fa-shopping-cart',
        'position' => 30,
    ]);

    // Main navigation carries only what an operator opens during a normal
    // working day, ordered by how often that happens. Positions 1-9 are reserved
    // for those screens; anything configured once and then left alone lives
    // behind Settings instead (see salesos/views/settings.php).
    // With e-commerce switched off only Settings remains, so the switch itself
    // stays reachable — hiding it too would be a one-way door.
    if (staff_can('view', SALESOS_MODULE_NAME) && salesos_ecommerce_enabled()) {
        $CI->app_menu->add_sidebar_children_item(SALESOS_MODULE_NAME, [
            'slug'     => 'salesos-dashboard',
            'name'     => 'Dashboard',
            'href'     => admin_url('salesos'),
            'position' => 1,
        ]);
        $CI->app_menu->add_sidebar_children_item(SALESOS_MODULE_NAME, [
            'slug'     => 'salesos-orders',
            'name'     => 'Orders',
            'href'     => admin_url('salesos/orders'),
            'position' => 2,
        ]);
    }

    if (staff_can('settings', SALESOS_MODULE_NAME)) {
        $CI->app_menu->add_sidebar_children_item(SALESOS_MODULE_NAME, [
            'slug'     => 'salesos-settings',
            'name'     => 'Settings',
            'href'     => admin_url('salesos/settings'),
            'position' => 99,
        ]);
    }
}

/**
 * Format e-commerce price or numbers dynamically based on system setting:
 * remove_decimals_on_zero (Remove decimals on numbers/money with zero decimals).
 */
function salesos_format_number($number, $decimals = null)
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
