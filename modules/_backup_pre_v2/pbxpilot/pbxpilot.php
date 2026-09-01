<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: PBXPilot - Telemarketing AI Note Generator
Description: AI-powered Bangala call transcription and lead note generator.
Version: 1.0.0
Requires at least: 2.3.2
Developer: PBX Pilot Team
*/

define('PBXPILOT_MODULE_NAME', 'pbxpilot');
define('PBXPILOT_UPLOADS_FOLDER', FCPATH . 'modules/pbxpilot/uploads/');

hooks()->add_action('admin_init', 'pbxpilot_module_init_menu_items');
hooks()->add_action('admin_init', 'pbxpilot_permissions');
hooks()->add_action('after_cron_run', 'pbxpilot_cleanup_cron');

/**
 * Register module permissions
 */
function pbxpilot_permissions()
{
    $capabilities = [
        'capabilities' => [
            'view'   => _l('permission_view') . '(' . _l('permission_global') . ')',
            'upload' => 'Can Upload Audio',
            'process' => 'Can Process Audio',
            'delete' => 'Can Delete Audio',
            'settings' => 'Can Manage Settings',
        ],
    ];

    register_staff_capabilities('pbxpilot', $capabilities, _l('PBXPilot'));
}

/**
 * Register module menu items
 */
function pbxpilot_module_init_menu_items()
{
    if (has_permission('pbxpilot', '', 'view')) {
        $CI = &get_instance();

        $CI->app_menu->add_sidebar_menu_item('pbxpilot', [
            'name'     => 'PBXPilot',
            'icon'     => 'fa fa-microphone',
            'position' => 30,
        ]);

        $CI->app_menu->add_sidebar_children_item('pbxpilot', [
            'slug'     => 'pbxpilot-dashboard',
            'name'     => 'Dashboard',
            'href'     => admin_url('pbxpilot'),
            'position' => 5,
        ]);

        if (has_permission('pbxpilot', '', 'settings')) {
            $CI->app_menu->add_sidebar_children_item('pbxpilot', [
                'slug'     => 'pbxpilot-settings',
                'name'     => 'Settings',
                'href'     => admin_url('pbxpilot/settings'),
                'position' => 10,
            ]);
        }
    }
}

/**
 * Get the module's helper
 */
$CI = &get_instance();
$CI->load->helper(PBXPILOT_MODULE_NAME . '/pbxpilot');

/**
 * Register activation hook
 */
register_activation_hook(PBXPILOT_MODULE_NAME, 'pbxpilot_module_activation_hook');

function pbxpilot_module_activation_hook()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/install.php');
}

/**
 * Cron cleanup task
 */
function pbxpilot_cleanup_cron()
{
    $CI = &get_instance();
    $CI->load->library(PBXPILOT_MODULE_NAME . '/pbxpilot_cleanup_service');
    $CI->pbxpilot_cleanup_service->run_cleanup();
}
