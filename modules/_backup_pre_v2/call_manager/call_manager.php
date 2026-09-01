<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Call Manager (Issabel PBX Integration)
Description: Integrates Issabel PBX call history and recordings into Perfex CRM.
Version: 1.0.0
Requires at least: 2.3.4
Author: Antigravity
*/

define('CALL_MANAGER_MODULE_NAME', 'call_manager');

hooks()->add_action('admin_init', 'call_manager_init_menu_items');
hooks()->add_action('app_init', 'call_manager_load_libraries');

/**
 * Register activation module hook
 */
register_activation_hook(CALL_MANAGER_MODULE_NAME, 'call_manager_activation_hook');

function call_manager_activation_hook()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/install.php');
}

/**
 * Load the module helper
 */
hooks()->add_action('app_init', 'call_manager_load_helper');

function call_manager_load_helper()
{
    $CI = &get_instance();
    $CI->load->helper(CALL_MANAGER_MODULE_NAME . '/call_manager');
}

function call_manager_load_libraries()
{
    $CI = &get_instance();
    $CI->load->model(CALL_MANAGER_MODULE_NAME . '/call_manager_model');
}

function call_manager_init_menu_items()
{
    $CI = &get_instance();

    if (has_permission('settings', '', 'view')) {
        $CI->app_menu->add_setup_menu_item('call-manager', [
            'name'     => 'Call Manager',
            'collapse' => true,
            'position' => 60,
        ]);

        $CI->app_menu->add_setup_children_item('call-manager', [
            'slug'     => 'call-manager-settings',
            'name'     => 'Settings',
            'href'     => admin_url('call_manager/settings'),
            'position' => 5,
        ]);
    }
}

/**
 * Inject Call History Tab in Lead and Customer Profile
 */
hooks()->add_action('after_lead_tabs_list', 'call_manager_add_lead_tab');
hooks()->add_action('after_lead_tabs_content', 'call_manager_add_lead_tab_content');

hooks()->add_action('after_customer_admins_tab', 'call_manager_add_customer_tab');
hooks()->add_action('after_customer_tabs_content', 'call_manager_add_customer_tab_content');

function call_manager_add_lead_tab($lead)
{
    echo '<li role="presentation">
        <a href="#call_history" aria-controls="call_history" role="tab" data-toggle="tab">
        ' . _l('Call History') . '
        </a>
    </li>';
}

function call_manager_add_lead_tab_content($lead)
{
    $CI = &get_instance();
    $CI->load->view(CALL_MANAGER_MODULE_NAME . '/lead_call_history', ['lead' => $lead]);
}

function call_manager_add_customer_tab($client)
{
    echo '<li role="presentation">
        <a href="#call_history" aria-controls="call_history" role="tab" data-toggle="tab">
        ' . _l('Call History') . '
        </a>
    </li>';
}

function call_manager_add_customer_tab_content($client)
{
    $CI = &get_instance();
    $CI->load->view(CALL_MANAGER_MODULE_NAME . '/lead_call_history', ['client' => $client]);
}
