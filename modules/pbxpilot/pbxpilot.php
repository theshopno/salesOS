<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: PBX Pilot
Description: Voice & Agency Platform for Perfex CRM — Asterisk PBX telephony, AI call intelligence, and agency workflows.
Version: 1.0.0
Requires at least: 2.3.4
Developer: PBX Pilot
*/

define('PBXPILOT_MODULE_NAME', 'pbxpilot');
define('PBXPILOT_VERSION', '1.0.0');

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
hooks()->add_action('salesos_order_created', 'pbxpilot_handle_salesos_order_created');

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

    // IVR Queue Sweeper: sweeps uncalled night orders and retries unanswered calls
    pbxpilot_sweep_ivr_queue();
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

    if (staff_can('manage', PBXPILOT_MODULE_NAME) || staff_can('settings', PBXPILOT_MODULE_NAME)) {
        $CI->app_menu->add_sidebar_children_item('pbxpilot', [
            'slug'     => 'pbxpilot-agents',
            'name'     => 'টিম ও চ্যানেল ম্যাপিং',
            'href'     => admin_url('pbxpilot/agents'),
            'position' => 3,
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

        var audioCtx = null;
        function getAudioContext() {
            if (!audioCtx) {
                var AudioContextClass = window.AudioContext || window.webkitAudioContext;
                if (AudioContextClass) audioCtx = new AudioContextClass();
            }
            if (audioCtx && audioCtx.state === 'suspended') {
                audioCtx.resume();
            }
            return audioCtx;
        }

        // Unlock audio on first user click anywhere in the window
        window.addEventListener('click', function unlockAudio() {
            getAudioContext();
            window.removeEventListener('click', unlockAudio);
        }, { once: true });

        function beep() {
            try {
                var ctx = getAudioContext();
                if (!ctx) return;
                
                // Classic phone double-ring chime pattern
                var now = ctx.currentTime;
                function playTone(freq, start, duration) {
                    var osc = ctx.createOscillator();
                    var gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(freq, start);
                    gain.gain.setValueAtTime(0.25, start);
                    gain.gain.exponentialRampToValueAtTime(0.01, start + duration);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start(start);
                    osc.stop(start + duration);
                }

                // First ring pair (440Hz + 480Hz US ringback style)
                playTone(523.25, now, 0.4);       // C5
                playTone(659.25, now + 0.05, 0.35); // E5
                
                // Second ring pair after brief pause
                playTone(523.25, now + 0.6, 0.4);
                playTone(659.25, now + 0.65, 0.35);
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

/**
 * Automatically dispatch IVR call on new SalesOS order
 */
function pbxpilot_handle_salesos_order_created($order_id): void
{
    $CI = &get_instance();
    if (!$CI->app_modules->is_active(PBXPILOT_MODULE_NAME) || !$CI->app_modules->is_active('salesos')) {
        return;
    }

    // Check if auto IVR call on order is enabled (default '1')
    if (pbxpilot_get_option('pbxpilot_auto_ivr_enabled', '1') !== '1') {
        return;
    }

    // Calling hours check (default 09:00 - 22:00)
    $start_hour   = (int) pbxpilot_get_option('pbxpilot_ivr_calling_start_hour', '9');
    $end_hour     = (int) pbxpilot_get_option('pbxpilot_ivr_calling_end_hour', '22');
    $current_hour = (int) date('G'); // 0-23

    $CI->load->model('salesos/salesos_model');
    $order = $CI->salesos_model->get_order((int) $order_id);
    if (!$order || ($order['status'] ?? '') !== 'pending') {
        return; // Only call pending (unconfirmed) orders
    }

    if ($current_hour < $start_hour || $current_hour >= $end_hour) {
        log_activity("PBX Pilot IVR: Order #{$order_id} arrived outside calling window ({$current_hour}:00). Held for morning window ({$start_hour}:00).");
        return;
    }

    $CI->load->library(PBXPILOT_MODULE_NAME . '/Ivr_service');
    $res = $CI->ivr_service->trigger_order_confirmation((int) $order_id);
    log_activity("PBX Pilot Auto IVR Trigger for Order #{$order_id}: " . json_encode($res));
}

/**
 * Cron Sweeper: Dispatches calls for pending uncalled orders (e.g. placed at night)
 * and retries unanswered calls once retry delay has passed.
 */
function pbxpilot_sweep_ivr_queue(): void
{
    $CI = &get_instance();
    if (!$CI->app_modules->is_active(PBXPILOT_MODULE_NAME) || !$CI->app_modules->is_active('salesos')) {
        return;
    }

    if (pbxpilot_get_option('pbxpilot_auto_ivr_enabled', '1') !== '1') {
        return;
    }

    $start_hour   = (int) pbxpilot_get_option('pbxpilot_ivr_calling_start_hour', '9');
    $end_hour     = (int) pbxpilot_get_option('pbxpilot_ivr_calling_end_hour', '22');
    $current_hour = (int) date('G');

    // Only sweep during permitted calling hours
    if ($current_hour < $start_hour || $current_hour >= $end_hour) {
        return;
    }

    $max_retries = (int) pbxpilot_get_option('pbxpilot_ivr_max_retries', '2');
    $retry_delay = (int) pbxpilot_get_option('pbxpilot_ivr_retry_delay_minutes', '15');
    $db_prefix   = db_prefix();

    // 1. Sweep uncalled pending orders created in the last 24h
    $uncalled_sql = "
        SELECT o.id 
        FROM {$db_prefix}salesos_orders o
        LEFT JOIN {$db_prefix}pbxpilot_ivr_logs l ON l.order_id = o.id
        WHERE o.status = 'pending'
          AND o.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
          AND l.id IS NULL
        ORDER BY o.id ASC
        LIMIT 3
    ";
    $uncalled = $CI->db->query($uncalled_sql)->result_array();

    $CI->load->library(PBXPILOT_MODULE_NAME . '/Ivr_service');

    foreach ($uncalled as $row) {
        $order_id = (int) $row['id'];
        $CI->ivr_service->trigger_order_confirmation($order_id);
        log_activity("PBX Pilot IVR Queue Sweeper: Dispatched queued morning call for Order #{$order_id}");
    }

    // 2. Sweep retry-due orders (where last attempt was failed/no_input and delay has passed)
    if ($max_retries > 1) {
        $retry_sql = "
            SELECT o.id, l.attempt, l.updated_at
            FROM {$db_prefix}salesos_orders o
            INNER JOIN (
                SELECT order_id, MAX(id) as max_id
                FROM {$db_prefix}pbxpilot_ivr_logs
                GROUP BY order_id
            ) latest ON latest.order_id = o.id
            INNER JOIN {$db_prefix}pbxpilot_ivr_logs l ON l.id = latest.max_id
            WHERE o.status = 'pending'
              AND l.result IN ('no_input', 'failed')
              AND l.attempt < ?
              AND l.updated_at <= DATE_SUB(NOW(), INTERVAL ? MINUTE)
            ORDER BY o.id ASC
            LIMIT 2
        ";
        $retries = $CI->db->query($retry_sql, [$max_retries, $retry_delay])->result_array();

        foreach ($retries as $row) {
            $order_id = (int) $row['id'];
            $CI->ivr_service->trigger_order_confirmation($order_id);
            log_activity("PBX Pilot IVR Queue Sweeper: Dispatched retry call for Order #{$order_id} (Attempt " . ($row['attempt'] + 1) . ")");
        }
    }
}

