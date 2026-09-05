<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Fraud Checking Module
Description: Customer risk analysis integration with BDCourier checker API. Auto-triggers on specific lead status changes.
Version: 1.0.0
Requires at least: 2.3.4
*/

define('FRAUDCHECK_MODULE_NAME', 'fraudcheck');

// ── Lifecycle Hooks ──────────────────────────────────────────────────────────
register_activation_hook(FRAUDCHECK_MODULE_NAME, 'fraudcheck_activation_hook');
function fraudcheck_activation_hook(): void
{
    $CI = &get_instance();
    require(__DIR__ . '/install.php');
}

// ── Bootstrap ────────────────────────────────────────────────────────────────
hooks()->add_action('app_init', 'fraudcheck_load_resources');
hooks()->add_action('lead_status_changed', 'fraudcheck_handle_status_change');

function fraudcheck_load_resources(): void
{
    $CI = &get_instance();
    if (!$CI->app_modules->is_active('ecomcore')) {
        return;
    }
    $CI->load->model(FRAUDCHECK_MODULE_NAME . '/fraudcheck_model');
}

/**
 * Hook listener: Check customer risk automatically when their WooCommerce lead transitions to "Called"
 */
function fraudcheck_handle_status_change($data): void
{
    $CI = &get_instance();
    if (!$CI->app_modules->is_active(FRAUDCHECK_MODULE_NAME)) {
        return;
    }

    $lead_id = (int) $data['lead_id'];
    $new_status_id = (int) $data['new_status_id'];

    // Get the status ID configured for WooCommerce "Called" orders
    $called_status_id = (int) get_option('wcsync_called_status_id');
    if ($called_status_id === 0 || $new_status_id !== $called_status_id) {
        return;
    }

    // Retrieve lead phone number
    $CI->db->select('phonenumber');
    $CI->db->where('id', $lead_id);
    $lead = $CI->db->get(db_prefix() . 'leads')->row();
    
    if ($lead && !empty($lead->phonenumber)) {
        $CI->load->model('fraudcheck/fraudcheck_model');
        // Pull API and cache the results automatically
        $CI->fraudcheck_model->check($lead->phonenumber);
    }
}
