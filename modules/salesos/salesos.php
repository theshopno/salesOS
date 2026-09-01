<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: SalesOS
Description: Voice & Agency Platform for Perfex CRM — Asterisk PBX telephony, AI call intelligence, and agency workflows.
Version: 2.0.0
Requires at least: 2.3.4
Developer: SalesOS
*/

define('SALESOS_MODULE_NAME', 'salesos');
define('SALESOS_VERSION', '2.0.0');

// ── Lifecycle ────────────────────────────────────────────────────────────

register_activation_hook(SALESOS_MODULE_NAME, 'salesos_activation_hook');

function salesos_activation_hook(): void
{
    $CI = &get_instance();
    require __DIR__ . '/install.php';
}

// ── Bootstrap ────────────────────────────────────────────────────────────

hooks()->add_action('app_init', 'salesos_load_resources');
hooks()->add_action('admin_init', 'salesos_register_menu');
hooks()->add_action('admin_init', 'salesos_register_permissions');

function salesos_load_resources(): void
{
    $CI = &get_instance();
    $CI->load->helper(SALESOS_MODULE_NAME . '/' . SALESOS_MODULE_NAME);
}

// ── Permissions ──────────────────────────────────────────────────────────

function salesos_register_permissions(): void
{
    register_staff_capabilities('salesos', [
        'capabilities' => [
            'view'     => 'View Dashboard',
            'settings' => 'Manage Settings',
        ],
    ], 'SalesOS');
}

// ── Menu — Phase 0 shell only (Settings); Dashboard/Calls/Agents land in
//    Phase 1 as the corresponding packs are built ─────────────────────────

function salesos_register_menu(): void
{
    if (!staff_can('settings', SALESOS_MODULE_NAME)) {
        return;
    }

    $CI = &get_instance();

    $CI->app_menu->add_sidebar_menu_item('salesos', [
        'name'     => 'SalesOS',
        'icon'     => 'fa fa-phone',
        'href'     => admin_url('salesos/settings'),
        'position' => 25,
    ]);

    $CI->app_menu->add_sidebar_children_item('salesos', [
        'slug'     => 'salesos-settings',
        'name'     => 'Settings',
        'href'     => admin_url('salesos/settings'),
        'position' => 5,
    ]);
}
