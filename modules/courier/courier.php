<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Courier Integration Suite
Description: Integrates with Steadfast and other courier delivery APIs for automated booking and status syncing.
Version: 1.0.0
Requires At Least: 2.3.0
*/

define('COURIER_MODULE_NAME', 'courier');

// ── Lifecycle Hooks ──────────────────────────────────────────────────────────
register_activation_hook(COURIER_MODULE_NAME, 'courier_activation_hook');
function courier_activation_hook(): void
{
    $CI = &get_instance();
    require(__DIR__ . '/install.php');
}

// ── Bootstrap ────────────────────────────────────────────────────────────────
hooks()->add_action('app_init',   'courier_load_resources');
hooks()->add_action('admin_init', 'courier_register_permissions');
hooks()->add_action('admin_init', 'courier_register_menu');

// The module's own event, now that something listens for it: every status the
// courier reports is written to the order's event log, so the history of a
// parcel reads alongside everything else that happened to that order.
hooks()->add_action('courier_consignment_status_changed', 'courier_log_status_change');

// Background status sync hook
hooks()->add_action('after_cron_run', 'courier_handle_cron_sync');

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

function courier_load_resources(): void
{
    $CI = &get_instance();
    $CI->load->model(COURIER_MODULE_NAME . '/courier_model');
}

// ── Permissions ──────────────────────────────────────────────────────────────
function courier_register_permissions(): void
{
    register_staff_capabilities('courier', [
        'capabilities' => [
            'view'   => 'View Courier Config & Consignments',
            'create' => 'Book Consignments',
            'edit'   => 'Edit Accounts / Sync Statuses',
            'delete' => 'Delete Accounts / Consignments',
        ],
    ], 'Courier Management');
}

// ── Cron Handler ─────────────────────────────────────────────────────────────
// ── Menu ─────────────────────────────────────────────────────────────────────
function courier_register_menu(): void
{
    $CI = &get_instance();
    if ($CI->app_modules->is_active('salesos') && function_exists('salesos_ecommerce_enabled') && !salesos_ecommerce_enabled()) { 
        return; 
    }
    if (!staff_can('view', COURIER_MODULE_NAME)) { return; }

    if ($CI->app_modules->is_active('salesos')) {
        $CI->app_menu->add_sidebar_children_item('salesos', [
            'slug'     => 'courier-consignments',
            'name'     => 'Consignments',
            'href'     => admin_url('courier/consignments'),
            'position' => 7,
        ]);
    } else {
        $CI->app_menu->add_sidebar_menu_item('courier-main', [
            'slug'     => 'courier-main',
            'name'     => 'Courier',
            'icon'     => 'fa fa-truck',
            'href'     => admin_url('courier/consignments'),
            'position' => 31,
        ]);
        $CI->app_menu->add_sidebar_children_item('courier-main', [
            'slug'     => 'courier-consignments',
            'name'     => 'Consignments',
            'href'     => admin_url('courier/consignments'),
            'position' => 5,
        ]);
    }
}

function courier_log_status_change($data): void
{
    $CI = &get_instance();
    if (!$CI->app_modules->is_active('salesos')) { return; }

    $CI->db->where('id', (int) ($data['id'] ?? 0));
    $consignment = $CI->db->get(db_prefix() . 'courier_consignments')->row();

    if (!$consignment || empty($consignment->salesos_order_id)) {
        return;
    }

    $CI->load->model('salesos/salesos_model');
    $CI->salesos_model->log_event('order.courier_status', 'order', (int) $consignment->salesos_order_id, [
        'tracking_id' => $consignment->tracking_id,
        'old_status'  => $data['old_status'] ?? null,
        'new_status'  => $data['new_status'] ?? null,
    ]);
}

function courier_handle_cron_sync(): void
{
    $CI = &get_instance();
    if (!$CI->app_modules->is_active(COURIER_MODULE_NAME)) {
        return;
    }
    // Prevent PHP timeout on long cron loops
    @set_time_limit(300);
    
    $CI->load->model('courier/courier_model');
    // Call background status sync (thoroughly throttled internally)
    $CI->courier_model->sync_all_active_statuses();
}
