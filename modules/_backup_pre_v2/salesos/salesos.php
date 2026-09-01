<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: SalesOS Telephony
Description: Asterisk 22 telephony integration for Perfex CRM. Agent mapping, CDR sync, call popup, lead matching, call recording.
Version: 1.1.0
Requires at least: 2.3.4
Developer: SalesOS
*/

define('SALESOS_MODULE_NAME', 'salesos');
define('SALESOS_VERSION',     '1.1.0');

// ── Lifecycle ───────────────────────────────────────────────────────────────

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
hooks()->add_action('after_cron_run', 'salesos_cron');

// Phone cache invalidation: bust cached phone entries when lead/client data changes
hooks()->add_action('after_lead_updated',    'salesos_invalidate_lead_phone_cache');
hooks()->add_action('after_client_updated',  'salesos_invalidate_client_phone_cache');
hooks()->add_action('after_contact_updated', 'salesos_invalidate_contact_phone_cache');
hooks()->add_action('after_lead_deleted',    'salesos_invalidate_lead_phone_cache');
hooks()->add_action('after_client_deleted',  'salesos_invalidate_client_phone_cache');

// Context cache invalidation: bust incoming_context Redis cache when CRM data changes
hooks()->add_action('after_lead_updated',    'salesos_invalidate_lead_context_cache');
hooks()->add_action('after_client_updated',  'salesos_invalidate_client_context_cache');
hooks()->add_action('after_contact_updated', 'salesos_invalidate_contact_context_cache');
hooks()->add_action('after_lead_deleted',    'salesos_invalidate_lead_context_cache');
hooks()->add_action('after_client_deleted',  'salesos_invalidate_client_context_cache');

function salesos_load_resources(): void
{
    $CI = &get_instance();
    $CI->load->model(SALESOS_MODULE_NAME . '/salesos_model');
    $CI->load->model(SALESOS_MODULE_NAME . '/agents_model');
    $CI->load->helper(SALESOS_MODULE_NAME . '/' . SALESOS_MODULE_NAME);
}

// ── Permissions ──────────────────────────────────────────────────────────────

function salesos_register_permissions(): void
{
    register_staff_capabilities('salesos', [
        'capabilities' => [
            'view'     => 'View Calls & Dashboard',
            'make'     => 'Make Outbound Calls',
            'manage'   => 'Manage Agents & Extensions',
            'settings' => 'Manage Settings',
            'delete'   => 'Delete Call Records',
        ],
    ], 'SalesOS Telephony');
}

// ── Menu ─────────────────────────────────────────────────────────────────────

function salesos_register_menu(): void
{
    if (!staff_can('view', SALESOS_MODULE_NAME)) {
        return;
    }

    $CI = &get_instance();

    $CI->app_menu->add_sidebar_menu_item('salesos', [
        'name'     => 'SalesOS',
        'icon'     => 'fa fa-phone',
        'position' => 25,
    ]);

    $CI->app_menu->add_sidebar_children_item('salesos', [
        'slug'     => 'salesos-dashboard',
        'name'     => 'Dashboard',
        'href'     => admin_url('salesos'),
        'position' => 5,
    ]);

    $CI->app_menu->add_sidebar_children_item('salesos', [
        'slug'     => 'salesos-calls',
        'name'     => 'Call Logs',
        'href'     => admin_url('salesos/calls'),
        'position' => 10,
    ]);

    if (staff_can('manage', SALESOS_MODULE_NAME)) {
        $CI->app_menu->add_sidebar_children_item('salesos', [
            'slug'     => 'salesos-agents',
            'name'     => 'Agents',
            'href'     => admin_url('salesos/agents'),
            'position' => 15,
        ]);
    }

    if (staff_can('settings', SALESOS_MODULE_NAME)) {
        $CI->app_menu->add_sidebar_children_item('salesos', [
            'slug'     => 'salesos-settings',
            'name'     => 'Settings',
            'href'     => admin_url('salesos/settings'),
            'position' => 20,
        ]);
    }
}

// ── Lead tab injection ────────────────────────────────────────────────────────

hooks()->add_action('after_lead_lead_tabs',    'salesos_lead_tab_header');
hooks()->add_action('after_lead_tabs_content', 'salesos_lead_tab_content');

function salesos_lead_tab_header(): void
{
    if (!staff_can('view', SALESOS_MODULE_NAME)) {
        return;
    }
    echo '<li role="presentation">
        <a href="#salesos_call_history" aria-controls="salesos_call_history" role="tab" data-toggle="tab">
            <i class="fa fa-phone"></i> ' . _l('Call History') . '
        </a>
    </li>';
}

function salesos_lead_tab_content(mixed $lead): void
{
    if (!staff_can('view', SALESOS_MODULE_NAME) || !$lead) {
        return;
    }
    $CI = &get_instance();
    $CI->load->view(SALESOS_MODULE_NAME . '/partials/call_history_tab', ['entity' => $lead, 'entity_type' => 'lead']);
}

// ── Client/contact tab injection ──────────────────────────────────────────────

hooks()->add_action('after_customer_admins_tab',  'salesos_client_tab_header');
hooks()->add_action('after_customer_tabs_content', 'salesos_client_tab_content');

function salesos_client_tab_header(): void
{
    if (!staff_can('view', SALESOS_MODULE_NAME)) {
        return;
    }
    echo '<li role="presentation">
        <a href="#salesos_call_history" aria-controls="salesos_call_history" role="tab" data-toggle="tab">
            <i class="fa fa-phone"></i> ' . _l('Call History') . '
        </a>
    </li>';
}

function salesos_client_tab_content(mixed $client): void
{
    if (!staff_can('view', SALESOS_MODULE_NAME) || !$client) {
        return;
    }
    $CI = &get_instance();
    $CI->load->view(SALESOS_MODULE_NAME . '/partials/call_history_tab', ['entity' => $client, 'entity_type' => 'client']);
}

// ── Global popup + assets ─────────────────────────────────────────────────────

hooks()->add_action('app_admin_head',   'salesos_inject_head');
hooks()->add_action('app_admin_footer', 'salesos_inject_footer');

function salesos_inject_head(): void
{
    if (!staff_can('view', SALESOS_MODULE_NAME)) {
        return;
    }
    $module_url = module_dir_url(SALESOS_MODULE_NAME, 'assets/');
    echo '<link rel="stylesheet" href="' . $module_url . 'css/salesos.css?v=' . SALESOS_VERSION . '">';
}

function salesos_inject_footer(): void
{
    if (!staff_can('view', SALESOS_MODULE_NAME) || get_option('salesos_popup_enabled') != '1') {
        return;
    }
    $CI = &get_instance();
    $module_url    = module_dir_url(SALESOS_MODULE_NAME, 'assets/');
    $poll_interval = (int) (get_option('salesos_poll_interval') ?: 5);
    $can_make_call = staff_can('make', SALESOS_MODULE_NAME) ? 'true' : 'false';
    $rt_enabled    = get_option('salesos_rt_enabled') == '1';

    $webrtc_enabled = get_option('salesos_webrtc_enabled') == '1' && staff_can('make', SALESOS_MODULE_NAME);

    echo '<script>
    var SalesOS = {
        pollInterval:   ' . ($poll_interval * 1000) . ',
        canMakeCall:    ' . $can_make_call . ',
        apiBase:        "' . admin_url('salesos/api/') . '",
        staffId:        ' . get_staff_user_id() . ',
        rtEnabled:      ' . ($rt_enabled ? 'true' : 'false') . ',
        webrtcEnabled:  ' . ($webrtc_enabled ? 'true' : 'false') . ',
        tokenUrl:       "' . admin_url('salesos/realtime/token') . '",
        csrf: { name: "' . $CI->security->get_csrf_token_name() . '", hash: "' . $CI->security->get_csrf_hash() . '" }
    };
    </script>';
    echo '<script src="' . $module_url . 'js/salesos.js?v=' . SALESOS_VERSION . '"></script>';

    if ($rt_enabled) {
        echo '<script src="' . $module_url . 'js/salesos_ws.js?v=' . SALESOS_VERSION . '"></script>';
        echo '<script>
        (function () {
            if (typeof SalesOsWs === "undefined") return;
            var ws = new SalesOsWs({
                tokenUrl: SalesOS.tokenUrl,
                debug: false
            });
            window.SalesOsWsInstance = ws;
            ws.connect();
        }());
        </script>';
    }

    $CI->load->view(SALESOS_MODULE_NAME . '/partials/phone_workspace');
    $CI->load->view(SALESOS_MODULE_NAME . '/partials/wrap_up');

    echo '<script src="' . $module_url . 'js/phone_workspace.js?v=' . SALESOS_VERSION . '"></script>';

    if ($webrtc_enabled) {
        echo '<script src="' . $module_url . 'js/salesos_webrtc.js?v=' . SALESOS_VERSION . '"></script>';
    }
}

// ── Phone cache invalidation ──────────────────────────────────────────────────

function _salesos_invalidate_phone_cache(string $type, mixed $id): void
{
    if (!get_option('salesos_rt_enabled')) return;
    try {
        $CI = &get_instance();
        $CI->load->library(SALESOS_MODULE_NAME . '/Phone_cache');
        $CI->phone_cache->invalidate_for_entity($type, (int) $id);
    } catch (Exception) {
        // Non-fatal — cache will expire on TTL
    }
}

function salesos_invalidate_lead_phone_cache(mixed $id): void    { _salesos_invalidate_phone_cache('lead',    $id); }
function salesos_invalidate_client_phone_cache(mixed $id): void  { _salesos_invalidate_phone_cache('client',  $id); }
function salesos_invalidate_contact_phone_cache(mixed $id): void { _salesos_invalidate_phone_cache('contact', $id); }

// ── Context cache invalidation ────────────────────────────────────────────────

function _salesos_invalidate_context_cache(string $type, mixed $id): void
{
    if (!get_option('salesos_rt_enabled')) return;
    if (!extension_loaded('redis')) return;
    try {
        $r = new Redis();
        $r->connect(
            get_option('salesos_redis_host') ?: '127.0.0.1',
            (int)(get_option('salesos_redis_port') ?: 6379),
            1.0
        );
        $r->del('salesos:context:' . $type . ':' . (int) $id);
    } catch (Exception) {}
}

function salesos_invalidate_lead_context_cache(mixed $id): void    { _salesos_invalidate_context_cache('lead',    $id); }
function salesos_invalidate_client_context_cache(mixed $id): void  { _salesos_invalidate_context_cache('client',  $id); }
function salesos_invalidate_contact_context_cache(mixed $id): void { _salesos_invalidate_context_cache('contact', $id); }

// ── Cron ─────────────────────────────────────────────────────────────────────

function salesos_cron(): void
{
    if (get_option('salesos_cdr_sync_enabled') != '1') {
        return;
    }
    $CI = &get_instance();
    $CI->load->library(SALESOS_MODULE_NAME . '/Cdr_sync_service');
    $CI->cdr_sync_service->sync();
}
