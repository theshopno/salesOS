<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: PBX Pilot
Description: Voice & Agency Platform for Perfex CRM — Asterisk PBX telephony, AI call intelligence, and agency workflows.
Version: 2.0.0
Requires at least: 2.3.4
Developer: PBX Pilot
*/

define('PBXPILOT_MODULE_NAME', 'pbxpilot');
define('PBXPILOT_VERSION', '2.0.0');

register_language_files(PBXPILOT_MODULE_NAME, [PBXPILOT_MODULE_NAME]);

// ── Lifecycle ────────────────────────────────────────────────────────────

register_activation_hook(PBXPILOT_MODULE_NAME, 'pbxpilot_activation_hook');

function pbxpilot_activation_hook(): void
{
    $CI = &get_instance();
    require __DIR__ . '/install.php';
}

// ── Bootstrap ────────────────────────────────────────────────────────────

hooks()->add_action('app_init', 'pbxpilot_load_resources');
hooks()->add_action('admin_init', 'pbxpilot_register_menu');
hooks()->add_action('admin_init', 'pbxpilot_register_permissions');
hooks()->add_action('after_cron_run', 'pbxpilot_cron');

function pbxpilot_load_resources(): void
{
    $CI = &get_instance();
    $CI->load->helper(PBXPILOT_MODULE_NAME . '/' . PBXPILOT_MODULE_NAME);
}

function pbxpilot_cron(): void
{
    $CI = &get_instance();
    $CI->load->helper(PBXPILOT_MODULE_NAME . '/' . PBXPILOT_MODULE_NAME);

    if (pbxpilot_get_option('pbxpilot_core_telephony', '0') !== '1') {
        return;
    }

    $CI->load->library(PBXPILOT_MODULE_NAME . '/Cdr_sync_service');
    $CI->cdr_sync_service->sync();

    // Recording cache cleanup — at most once/day (cron may run far more
    // often than that; a directory scan every tick is needless I/O).
    $last_cleanup = pbxpilot_get_option('pbxpilot_recording_cleanup_last_run', '');
    if ($last_cleanup === '' || strtotime($last_cleanup) < strtotime('-1 day')) {
        $retention_days = (int) pbxpilot_get_option('pbxpilot_recording_retention_days', '90');
        $CI->load->library(PBXPILOT_MODULE_NAME . '/Recording_service');
        $CI->recording_service->cleanup_old_cache($retention_days);
        pbxpilot_update_option('pbxpilot_recording_cleanup_last_run', date('Y-m-d H:i:s'));
    }
}

// ── Permissions ──────────────────────────────────────────────────────────

function pbxpilot_register_permissions(): void
{
    register_staff_capabilities('pbxpilot', [
        'capabilities' => [
            'view'     => 'View Dashboard',
            'make'     => 'Make Outbound Calls',
            'manage'   => 'Manage Agents & Extensions',
            'settings' => 'Manage Settings',
        ],
    ], 'PBX Pilot');
}

// ── Menu — Dashboard, Call Logs, Agents, Settings ───────────────────────────

function pbxpilot_register_menu(): void
{
    if (!staff_can('view', PBXPILOT_MODULE_NAME) && !staff_can('settings', PBXPILOT_MODULE_NAME) && !staff_can('manage', PBXPILOT_MODULE_NAME)) {
        return;
    }

    $CI = &get_instance();

    $CI->app_menu->add_sidebar_menu_item('pbxpilot', [
        'name'     => 'PBX Pilot',
        'icon'     => 'fa fa-phone',
        'href'     => admin_url('pbxpilot/dashboard'),
        'position' => 25,
    ]);

    if (staff_can('view', PBXPILOT_MODULE_NAME)) {
        $CI->app_menu->add_sidebar_children_item('pbxpilot', [
            'slug'     => 'pbxpilot-dashboard',
            'name'     => 'Dashboard',
            'href'     => admin_url('pbxpilot/dashboard'),
            'position' => 1,
        ]);

        $CI->app_menu->add_sidebar_children_item('pbxpilot', [
            'slug'     => 'pbxpilot-calls',
            'name'     => 'Call Logs',
            'href'     => admin_url('pbxpilot/calls'),
            'position' => 2,
        ]);
    }


    if (staff_can('settings', PBXPILOT_MODULE_NAME)) {
        $CI->app_menu->add_sidebar_children_item('pbxpilot', [
            'slug'     => 'pbxpilot-settings',
            'name'     => 'Settings',
            'href'     => admin_url('pbxpilot/settings'),
            'position' => 99,
        ]);
    }
}

// ── Lead/client tab injection — unified call-history tab ───────────────────

hooks()->add_action('after_lead_lead_tabs',     'pbxpilot_lead_tab_header');
hooks()->add_action('after_lead_tabs_content',  'pbxpilot_lead_tab_content');
hooks()->add_action('after_customer_admins_tab', 'pbxpilot_client_tab_header');
hooks()->add_action('after_customer_tabs_content', 'pbxpilot_client_tab_content');

function pbxpilot_lead_tab_header(): void
{
    if (!staff_can('view', PBXPILOT_MODULE_NAME)) {
        return;
    }
    // The core lead modal has no hook point positioned before its hardcoded
    // Notes tab — after_lead_lead_tabs only fires after the whole tab strip,
    // so this always lands last in markup order. Moving it next to Notes in
    // the DOM (right before it) right after insertion is the only way to
    // get it there without patching core; harmless no-op if that tab isn't
    // found for some reason (falls back to wherever it landed).
    echo '<li role="presentation" id="pbxpilot-lead-tab-li"><a href="#pbxpilot_call_history" aria-controls="pbxpilot_call_history" role="tab" data-toggle="tab"><i class="fa fa-phone"></i> ' . _l('Call History') . '</a></li>';
    echo '<script>(function(){var li=document.getElementById("pbxpilot-lead-tab-li");if(!li)return;var notesLink=document.querySelector(\'a[href="#lead_notes"]\');var notesLi=notesLink?notesLink.closest("li"):null;if(notesLi&&notesLi.parentNode){notesLi.parentNode.insertBefore(li,notesLi);}})();</script>';
}

function pbxpilot_lead_tab_content(mixed $lead): void
{
    if (!staff_can('view', PBXPILOT_MODULE_NAME) || !$lead) {
        return;
    }
    $CI = &get_instance();
    $CI->load->view(PBXPILOT_MODULE_NAME . '/partials/call_history_tab', ['entity' => $lead, 'entity_type' => 'lead']);
}

function pbxpilot_client_tab_header(): void
{
    if (!staff_can('view', PBXPILOT_MODULE_NAME)) {
        return;
    }
    echo '<li role="presentation"><a href="#pbxpilot_call_history" aria-controls="pbxpilot_call_history" role="tab" data-toggle="tab"><i class="fa fa-phone"></i> ' . _l('Call History') . '</a></li>';
}

function pbxpilot_client_tab_content(mixed $client): void
{
    if (!staff_can('view', PBXPILOT_MODULE_NAME) || !$client) {
        return;
    }
    $CI = &get_instance();
    $CI->load->view(PBXPILOT_MODULE_NAME . '/partials/call_history_tab', ['entity' => $client, 'entity_type' => 'client']);
}

// ── Browser channel screen-pop: poll active_calls, show a popup on a hit ───

hooks()->add_action('app_admin_footer', 'pbxpilot_inject_screenpop_poller');

function pbxpilot_inject_screenpop_poller(): void
{
    if (!staff_can('view', PBXPILOT_MODULE_NAME)) {
        return;
    }
    if (pbxpilot_get_option('pbxpilot_channel_browser', '0') !== '1') {
        return;
    }
    ?>
    <style>
    #pbxpilot-toast-stack { position: fixed; top: 20px; right: 20px; z-index: 99999; display: flex; flex-direction: column; gap: 10px; }
    .pbxpilot-toast {
        width: 320px; background: #fff; border-radius: 10px; box-shadow: 0 6px 24px rgba(20,25,35,.18);
        border-left: 4px solid #2c6ee0; overflow: hidden; font-family: inherit;
        animation: pbxpilot-toast-in .25s ease-out;
    }
    @keyframes pbxpilot-toast-in { from { transform: translateX(24px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    .pbxpilot-toast-body { display: flex; align-items: flex-start; gap: 12px; padding: 14px 14px 10px; }
    .pbxpilot-toast-icon {
        width: 38px; height: 38px; border-radius: 9999px; background: #eaf2ff; color: #2c6ee0;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 15px;
        animation: pbxpilot-ring 1s infinite;
    }
    @keyframes pbxpilot-ring { 0%,100% { transform: rotate(0deg); } 25% { transform: rotate(-12deg); } 75% { transform: rotate(12deg); } }
    .pbxpilot-toast-title { font-weight: 600; font-size: 0.85rem; color: #222; margin: 0 0 2px; }
    .pbxpilot-toast-number { font-size: 0.95rem; color: #2b3138; font-weight: 700; }
    .pbxpilot-toast-close { background: none; border: none; color: #9aa2b1; font-size: 16px; cursor: pointer; line-height: 1; padding: 2px 4px; }
    .pbxpilot-toast-actions { display: flex; gap: 8px; padding: 0 14px 14px; }
    .pbxpilot-toast-actions a, .pbxpilot-toast-actions button {
        flex: 1; text-align: center; font-size: 0.8rem; padding: 6px 10px; border-radius: 6px; text-decoration: none;
        border: 1px solid #e2e5eb; background: #fff; color: #555; cursor: pointer;
    }
    .pbxpilot-toast-actions .pbxpilot-toast-primary { background: #2c6ee0; border-color: #2c6ee0; color: #fff; }
    .pbxpilot-toast-bar { height: 3px; background: #eaf2ff; }
    .pbxpilot-toast-bar-fill { height: 100%; background: #2c6ee0; width: 100%; transition: width linear; }
    </style>
    <div id="pbxpilot-toast-stack"></div>
    <script>
    (function () {
        var pollUrl = '<?= admin_url('pbxpilot/api/active_calls') ?>';
        var AUTO_DISMISS_MS = 25000;
        var stack = document.getElementById('pbxpilot-toast-stack');

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
            toast.className = 'pbxpilot-toast';
            toast.innerHTML =
                '<div class="pbxpilot-toast-body">' +
                    '<div class="pbxpilot-toast-icon">☎</div>' +
                    '<div style="flex:1">' +
                        '<p class="pbxpilot-toast-title"><?= _l('pbxpilot_toast_incoming_call', '', false) ?></p>' +
                        '<div class="pbxpilot-toast-number">' + c.src + '</div>' +
                    '</div>' +
                    '<button type="button" class="pbxpilot-toast-close" aria-label="Dismiss">&times;</button>' +
                '</div>' +
                '<div class="pbxpilot-toast-actions">' +
                    (c.link
                        ? '<a href="' + c.link + '" class="pbxpilot-toast-primary"><?= _l('pbxpilot_toast_view_record', '', false) ?></a>'
                        : '<span style="flex:1;text-align:center;color:#9aa2b1;font-size:0.8rem;padding:6px 0;"><?= _l('pbxpilot_toast_no_match', '', false) ?></span>') +
                '</div>' +
                '<div class="pbxpilot-toast-bar"><div class="pbxpilot-toast-bar-fill"></div></div>';

            stack.appendChild(toast);
            beep();

            var fill = toast.querySelector('.pbxpilot-toast-bar-fill');
            requestAnimationFrame(function () {
                fill.style.transitionDuration = AUTO_DISMISS_MS + 'ms';
                fill.style.width = '0%';
            });

            var timer = setTimeout(function () { remove(); }, AUTO_DISMISS_MS);
            function remove() {
                clearTimeout(timer);
                toast.remove();
            }
            toast.querySelector('.pbxpilot-toast-close').addEventListener('click', remove);
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
