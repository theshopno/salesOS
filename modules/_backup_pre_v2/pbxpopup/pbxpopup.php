<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: PBX Popup - Call Screen Pop
Description: Looks up an incoming caller's number against Leads/Customers and opens the matching record. Also adds an inline Call Recordings tab to the Lead page that streams recordings live from the PBX. Called from MicroSIP's incoming-call URL action.
Version: 1.0.0
Requires at least: 2.3.2
Developer: Uddoktayon
*/

define('PBXPOPUP_MODULE_NAME', 'pbxpopup');

// Bridge lives on the eCare Asterisk box itself (bridge.php, deployed
// alongside the mp3_cache watcher - see deploy/pbx/). Not a VPN/Tailscale
// link: the CRM's cPanel hosting is a jailed, non-root shell with no
// /dev/net/tun, confirmed by direct inspection, so a VPN mesh between CRM
// and PBX is not possible. HTTPS + shared-secret token is the only viable
// path from this side.
define('PBXBRIDGE_URL', 'https://ecare-pbx.uddoktayon.com/bridge.php');
define('PBXBRIDGE_API_TOKEN', '063dcdfddef828a26565ab5fc29f52358083a1d33cfb8d310546b20704cb65e6');

hooks()->add_action('after_lead_lead_tabs',    'pbxpopup_add_lead_tab');
hooks()->add_action('after_lead_tabs_content', 'pbxpopup_lead_tab_content');

/**
 * Tab header - same pattern as salesos's Call History tab (inline
 * tab-pane, not a link to a separate page).
 */
function pbxpopup_add_lead_tab($lead)
{
    if ($lead) {
        echo '<li role="presentation">
            <a href="#pbxpopup_recordings" aria-controls="pbxpopup_recordings" role="tab" data-toggle="tab">
                <i class="fa fa-headphones menu-icon" aria-hidden="true"></i> Call Recordings
            </a>
        </li>';
    }
}

/**
 * Tab content - fetched from the PBX bridge and rendered inline. Short
 * curl timeouts (see pbxpopup_bridge_request) so a PBX outage never hangs
 * the Lead page, just shows a graceful error in this one tab.
 */
function pbxpopup_lead_tab_content($lead)
{
    if (!$lead) {
        return;
    }

    $CI = &get_instance();

    $recordings = [];
    $error      = null;

    $digits = preg_replace('/\D/', '', (string) $lead->phonenumber);
    $last10 = substr($digits, -10);

    if (strlen($last10) < 7) {
        $error = 'এই লিডের কোনো ফোন নম্বর সেভ করা নেই।';
    } else {
        $result = pbxpopup_bridge_request('list', ['phone' => $last10]);
        if ($result === null) {
            $error = 'PBX-এর সাথে যোগাযোগ করা যাচ্ছে না (অফলাইন থাকতে পারে)। কিছুক্ষণ পর আবার চেষ্টা করুন।';
        } else {
            $recordings = $result['recordings'] ?? [];
        }
    }

    $CI->load->view(PBXPOPUP_MODULE_NAME . '/partials/recordings_tab', [
        'lead'       => $lead,
        'recordings' => $recordings,
        'error'      => $error,
    ]);
}

/**
 * Server-to-server call to the PBX bridge. The master token is used here
 * only (never sent to the browser) - per-recording playback uses the
 * HMAC-signed relpath the bridge returns for each row instead, so the
 * master token never appears in page source.
 */
function pbxpopup_bridge_request($action, $params)
{
    $params['action'] = $action;
    $params['token']  = PBXBRIDGE_API_TOKEN;
    $url = PBXBRIDGE_URL . '?' . http_build_query($params);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
    $response   = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $statusCode !== 200) {
        return null;
    }
    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * Build a direct, pre-signed stream URL for one recording row (as
 * returned by the bridge's list action). null if the bridge couldn't
 * locate a file for that call.
 */
function pbxpopup_stream_url($rec)
{
    if (empty($rec['relpath']) || empty($rec['sig'])) {
        return null;
    }
    return PBXBRIDGE_URL . '?' . http_build_query([
        'action'  => 'stream',
        'relpath' => $rec['relpath'],
        'sig'     => $rec['sig'],
    ]);
}
