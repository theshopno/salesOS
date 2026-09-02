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

register_language_files(SALESOS_MODULE_NAME, [SALESOS_MODULE_NAME]);

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
hooks()->add_action('after_cron_run', 'salesos_cron');

function salesos_load_resources(): void
{
    $CI = &get_instance();
    $CI->load->helper(SALESOS_MODULE_NAME . '/' . SALESOS_MODULE_NAME);
}

function salesos_cron(): void
{
    $CI = &get_instance();
    $CI->load->helper(SALESOS_MODULE_NAME . '/' . SALESOS_MODULE_NAME);

    if (salesos_get_option('salesos_core_telephony', '0') !== '1') {
        return;
    }

    $CI->load->library(SALESOS_MODULE_NAME . '/Cdr_sync_service');
    $CI->cdr_sync_service->sync();

    // Recording cache cleanup — at most once/day (cron may run far more
    // often than that; a directory scan every tick is needless I/O).
    $last_cleanup = salesos_get_option('salesos_recording_cleanup_last_run', '');
    if ($last_cleanup === '' || strtotime($last_cleanup) < strtotime('-1 day')) {
        $retention_days = (int) salesos_get_option('salesos_recording_retention_days', '90');
        $CI->load->library(SALESOS_MODULE_NAME . '/Recording_service');
        $CI->recording_service->cleanup_old_cache($retention_days);
        salesos_update_option('salesos_recording_cleanup_last_run', date('Y-m-d H:i:s'));
    }
}

// ── Permissions ──────────────────────────────────────────────────────────

function salesos_register_permissions(): void
{
    register_staff_capabilities('salesos', [
        'capabilities' => [
            'view'     => 'View Dashboard',
            'make'     => 'Make Outbound Calls',
            'manage'   => 'Manage Agents & Extensions',
            'settings' => 'Manage Settings',
        ],
    ], 'SalesOS');
}

// ── Menu — Dashboard, Call Logs, Agents, Settings ───────────────────────────

function salesos_register_menu(): void
{
    if (!staff_can('view', SALESOS_MODULE_NAME) && !staff_can('settings', SALESOS_MODULE_NAME) && !staff_can('manage', SALESOS_MODULE_NAME)) {
        return;
    }

    $CI = &get_instance();

    $CI->app_menu->add_sidebar_menu_item('salesos', [
        'name'     => 'SalesOS',
        'icon'     => 'fa fa-phone',
        'href'     => admin_url('salesos/dashboard'),
        'position' => 25,
    ]);

    if (staff_can('view', SALESOS_MODULE_NAME)) {
        $CI->app_menu->add_sidebar_children_item('salesos', [
            'slug'     => 'salesos-dashboard',
            'name'     => 'Dashboard',
            'href'     => admin_url('salesos/dashboard'),
            'position' => 1,
        ]);

        $CI->app_menu->add_sidebar_children_item('salesos', [
            'slug'     => 'salesos-calls',
            'name'     => 'Call Logs',
            'href'     => admin_url('salesos/calls'),
            'position' => 2,
        ]);
    }

    if (staff_can('manage', SALESOS_MODULE_NAME)) {
        $CI->app_menu->add_sidebar_children_item('salesos', [
            'slug'     => 'salesos-agents',
            'name'     => 'Agents',
            'href'     => admin_url('salesos/agents'),
            'position' => 3,
        ]);
    }

    if (staff_can('settings', SALESOS_MODULE_NAME)) {
        $CI->app_menu->add_sidebar_children_item('salesos', [
            'slug'     => 'salesos-settings',
            'name'     => 'Settings',
            'href'     => admin_url('salesos/settings'),
            'position' => 5,
        ]);
    }
}

// ── Lead/client tab injection — unified call-history tab ───────────────────

hooks()->add_action('after_lead_lead_tabs',     'salesos_lead_tab_header');
hooks()->add_action('after_lead_tabs_content',  'salesos_lead_tab_content');
hooks()->add_action('after_customer_admins_tab', 'salesos_client_tab_header');
hooks()->add_action('after_customer_tabs_content', 'salesos_client_tab_content');

function salesos_lead_tab_header(): void
{
    if (!staff_can('view', SALESOS_MODULE_NAME)) {
        return;
    }
    // The core lead modal has no hook point positioned before its hardcoded
    // Notes tab — after_lead_lead_tabs only fires after the whole tab strip,
    // so this always lands last in markup order. Moving it next to Notes in
    // the DOM (right before it) right after insertion is the only way to
    // get it there without patching core; harmless no-op if that tab isn't
    // found for some reason (falls back to wherever it landed).
    echo '<li role="presentation" id="salesos-lead-tab-li"><a href="#salesos_call_history" aria-controls="salesos_call_history" role="tab" data-toggle="tab"><i class="fa fa-phone"></i> ' . _l('Call History') . '</a></li>';
    echo '<script>(function(){var li=document.getElementById("salesos-lead-tab-li");if(!li)return;var notesLink=document.querySelector(\'a[href="#lead_notes"]\');var notesLi=notesLink?notesLink.closest("li"):null;if(notesLi&&notesLi.parentNode){notesLi.parentNode.insertBefore(li,notesLi);}})();</script>';
}

function salesos_lead_tab_content(mixed $lead): void
{
    if (!staff_can('view', SALESOS_MODULE_NAME) || !$lead) {
        return;
    }
    $CI = &get_instance();
    $CI->load->view(SALESOS_MODULE_NAME . '/partials/call_history_tab', ['entity' => $lead, 'entity_type' => 'lead']);
}

function salesos_client_tab_header(): void
{
    if (!staff_can('view', SALESOS_MODULE_NAME)) {
        return;
    }
    echo '<li role="presentation"><a href="#salesos_call_history" aria-controls="salesos_call_history" role="tab" data-toggle="tab"><i class="fa fa-phone"></i> ' . _l('Call History') . '</a></li>';
}

function salesos_client_tab_content(mixed $client): void
{
    if (!staff_can('view', SALESOS_MODULE_NAME) || !$client) {
        return;
    }
    $CI = &get_instance();
    $CI->load->view(SALESOS_MODULE_NAME . '/partials/call_history_tab', ['entity' => $client, 'entity_type' => 'client']);
}

// ── Browser channel screen-pop: poll active_calls, show a popup on a hit ───

hooks()->add_action('app_admin_footer', 'salesos_inject_screenpop_poller');

function salesos_inject_screenpop_poller(): void
{
    if (!staff_can('view', SALESOS_MODULE_NAME)) {
        return;
    }
    if (salesos_get_option('salesos_channel_browser', '0') !== '1') {
        return;
    }
    ?>
    <style>
    #salesos-toast-stack { position: fixed; top: 20px; right: 20px; z-index: 99999; display: flex; flex-direction: column; gap: 10px; }
    .salesos-toast {
        width: 320px; background: #fff; border-radius: 10px; box-shadow: 0 6px 24px rgba(20,25,35,.18);
        border-left: 4px solid #2c6ee0; overflow: hidden; font-family: inherit;
        animation: salesos-toast-in .25s ease-out;
    }
    @keyframes salesos-toast-in { from { transform: translateX(24px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    .salesos-toast-body { display: flex; align-items: flex-start; gap: 12px; padding: 14px 14px 10px; }
    .salesos-toast-icon {
        width: 38px; height: 38px; border-radius: 9999px; background: #eaf2ff; color: #2c6ee0;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 15px;
        animation: salesos-ring 1s infinite;
    }
    @keyframes salesos-ring { 0%,100% { transform: rotate(0deg); } 25% { transform: rotate(-12deg); } 75% { transform: rotate(12deg); } }
    .salesos-toast-title { font-weight: 600; font-size: 0.85rem; color: #222; margin: 0 0 2px; }
    .salesos-toast-number { font-size: 0.95rem; color: #2b3138; font-weight: 700; }
    .salesos-toast-close { background: none; border: none; color: #9aa2b1; font-size: 16px; cursor: pointer; line-height: 1; padding: 2px 4px; }
    .salesos-toast-actions { display: flex; gap: 8px; padding: 0 14px 14px; }
    .salesos-toast-actions a, .salesos-toast-actions button {
        flex: 1; text-align: center; font-size: 0.8rem; padding: 6px 10px; border-radius: 6px; text-decoration: none;
        border: 1px solid #e2e5eb; background: #fff; color: #555; cursor: pointer;
    }
    .salesos-toast-actions .salesos-toast-primary { background: #2c6ee0; border-color: #2c6ee0; color: #fff; }
    .salesos-toast-bar { height: 3px; background: #eaf2ff; }
    .salesos-toast-bar-fill { height: 100%; background: #2c6ee0; width: 100%; transition: width linear; }
    </style>
    <div id="salesos-toast-stack"></div>
    <script>
    (function () {
        var pollUrl = '<?= admin_url('salesos/api/active_calls') ?>';
        var AUTO_DISMISS_MS = 25000;
        var stack = document.getElementById('salesos-toast-stack');

        function beep() {
            try {
                var ctx = new (window.AudioContext || window.webkitAudioContext)();
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                osc.frequency.value = 880;
                gain.gain.setValueAtTime(0.08, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35);
                osc.connect(gain).connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.35);
            } catch (e) {}
        }

        function showToast(c) {
            var toast = document.createElement('div');
            toast.className = 'salesos-toast';
            toast.innerHTML =
                '<div class="salesos-toast-body">' +
                    '<div class="salesos-toast-icon">☎</div>' +
                    '<div style="flex:1">' +
                        '<p class="salesos-toast-title"><?= _l('salesos_toast_incoming_call', '', false) ?></p>' +
                        '<div class="salesos-toast-number">' + c.src + '</div>' +
                    '</div>' +
                    '<button type="button" class="salesos-toast-close" aria-label="Dismiss">&times;</button>' +
                '</div>' +
                '<div class="salesos-toast-actions">' +
                    (c.link
                        ? '<a href="' + c.link + '" class="salesos-toast-primary"><?= _l('salesos_toast_view_record', '', false) ?></a>'
                        : '<span style="flex:1;text-align:center;color:#9aa2b1;font-size:0.8rem;padding:6px 0;"><?= _l('salesos_toast_no_match', '', false) ?></span>') +
                '</div>' +
                '<div class="salesos-toast-bar"><div class="salesos-toast-bar-fill"></div></div>';

            stack.appendChild(toast);
            beep();

            var fill = toast.querySelector('.salesos-toast-bar-fill');
            requestAnimationFrame(function () {
                fill.style.transitionDuration = AUTO_DISMISS_MS + 'ms';
                fill.style.width = '0%';
            });

            var timer = setTimeout(function () { remove(); }, AUTO_DISMISS_MS);
            function remove() {
                clearTimeout(timer);
                toast.remove();
            }
            toast.querySelector('.salesos-toast-close').addEventListener('click', remove);
        }

        setInterval(function () {
            fetch(pollUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (calls) {
                    (calls || []).forEach(showToast);
                })
                .catch(function () {});
        }, 5000);
    }());
    </script>
    <?php
}
