<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: WooConnector
Description: Pulls WooCommerce orders into the CRM as Leads for telesales confirmation.
Version: 1.2.0
Requires at least: 2.3.4
*/

define('WOOCONNECTOR_MODULE_NAME', 'wooconnector');
define('WOOCONNECTOR_VERSION',     '1.2.0');

// ── Lifecycle ────────────────────────────────────────────────────────────────

register_activation_hook(WOOCONNECTOR_MODULE_NAME, 'wooconnector_activation_hook');

function wooconnector_activation_hook(): void
{
    $CI = &get_instance();
    require(__DIR__ . '/install.php');
}

// ── Bootstrap ────────────────────────────────────────────────────────────────

hooks()->add_action('app_init',      'wooconnector_load_resources');
hooks()->add_action('admin_init',    'wooconnector_register_menu');
hooks()->add_action('admin_init',    'wooconnector_register_permissions');
// Retired. WooCommerce is served by the wcsync connector on the shared channel
// contract, which also covers the confirmation call this module was built for —
// see modules/salesos/CHANNEL_CONNECTOR.md. Its cron is disconnected so the two
// cannot import the same order twice; the module's own screens and data stay
// reachable so an existing install can be read and migrated.
// hooks()->add_action('after_cron_run', 'wooconnector_cron');
hooks()->add_action('lead_status_changed', 'wooconnector_on_lead_status_changed');

function wooconnector_load_resources(): void
{
    $CI = &get_instance();
    $CI->load->model(WOOCONNECTOR_MODULE_NAME . '/wooconnector_model');
}

// ── Permissions ──────────────────────────────────────────────────────────────

function wooconnector_register_permissions(): void
{
    register_staff_capabilities('wooconnector', [
        'capabilities' => [
            'view'     => 'View Dashboard',
            'settings' => 'Manage Settings & Sync',
        ],
    ], 'WooConnector');
}

// ── Menu ─────────────────────────────────────────────────────────────────────

function wooconnector_register_menu(): void
{
    if (!staff_can('view', WOOCONNECTOR_MODULE_NAME)) {
        return;
    }

    $CI = &get_instance();

    $CI->app_menu->add_sidebar_menu_item('wooconnector', [
        'name'     => 'WooConnector',
        'icon'     => 'fa fa-shopping-cart',
        'position' => 60,
    ]);

    $CI->app_menu->add_sidebar_children_item('wooconnector', [
        'slug'     => 'wooconnector-dashboard',
        'name'     => 'Dashboard',
        'href'     => admin_url('wooconnector'),
        'position' => 5,
    ]);

    $CI->app_menu->add_sidebar_children_item('wooconnector', [
        'slug'     => 'wooconnector-orders',
        'name'     => 'Website Orders',
        'href'     => admin_url('wooconnector/orders'),
        'position' => 7,
    ]);

    if (staff_can('settings', WOOCONNECTOR_MODULE_NAME)) {
        $CI->app_menu->add_sidebar_children_item('wooconnector', [
            'slug'     => 'wooconnector-settings',
            'name'     => 'Settings',
            'href'     => admin_url('wooconnector/settings'),
            'position' => 10,
        ]);
    }
}

// ── Cron ─────────────────────────────────────────────────────────────────────

function wooconnector_cron(): void
{
    $CI = &get_instance();
    $CI->load->model(WOOCONNECTOR_MODULE_NAME . '/wooconnector_model');
    $CI->wooconnector_model->sync();
}

// ── Website Orders: auto-convert on "Confirmed" ───────────────────────────────

function wooconnector_on_lead_status_changed($data): void
{
    $CI = &get_instance();

    $CI->db->where('lead_id', $data['lead_id']);
    $mapping = $CI->db->get(db_prefix() . 'wooconnector_orders')->row();

    if (!$mapping) {
        return; // Not a WooCommerce-sourced lead — never touch it.
    }

    $confirmed_status_id = (int) get_option('wooconnector_confirmed_status_id');
    if (!$confirmed_status_id) {
        return;
    }

    // Match by the CURRENT name of the stored id, not a hardcoded string — survives renames
    // via Setup > Leads > Statuses, since the hook payload only carries status names.
    $CI->db->where('id', $confirmed_status_id);
    $confirmed_status = $CI->db->get(db_prefix() . 'leads_status')->row();

    if (!$confirmed_status || $data['new_status'] !== $confirmed_status->name) {
        return;
    }

    $CI->load->model(WOOCONNECTOR_MODULE_NAME . '/wooconnector_model');
    $CI->wooconnector_model->confirm_lead_as_customer($data['lead_id']);
}
