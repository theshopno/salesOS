<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Normalize a phone number to digits only, optionally strip BD country code.
 */
function salesos_normalize_phone(string $phone): string
{
    $digits = preg_replace('/\D/', '', $phone);

    // Strip BD country code 880 → keep local format
    if (strlen($digits) === 13 && str_starts_with($digits, '880')) {
        $digits = '0' . substr($digits, 3);
    }
    // +880 prefix removed too
    if (strlen($digits) === 12 && str_starts_with($digits, '880')) {
        $digits = '0' . substr($digits, 3);
    }

    return $digits;
}

/**
 * Return all realistic variants of a phone number for DB matching.
 */
function salesos_phone_variants(string $normalized): array
{
    if (empty($normalized)) {
        return [];
    }

    $variants = [$normalized];

    // Local: 01XXXXXXXXX (11 digits BD mobile)
    if (strlen($normalized) === 11 && str_starts_with($normalized, '0')) {
        $without_zero = substr($normalized, 1);          // 1XXXXXXXXX
        $with_880     = '880' . $without_zero;           // 8801XXXXXXXXX
        $with_plus880 = '+880' . $without_zero;
        $variants[]   = $without_zero;
        $variants[]   = $with_880;
        $variants[]   = $with_plus880;
    }

    // 09XXXXXXXX hotline (10 digits)
    if (strlen($normalized) === 10 && str_starts_with($normalized, '09')) {
        $variants[] = '880' . $normalized;
    }

    return array_unique($variants);
}

/**
 * Format seconds to MM:SS or HH:MM:SS.
 */
function salesos_format_duration(int $seconds): string
{
    if ($seconds < 3600) {
        return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
    return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
}

/**
 * Return a Bootstrap/FontAwesome badge for call disposition.
 */
function salesos_disposition_badge(string $disposition): string
{
    $map = [
        'ANSWERED'    => ['success', 'fa-phone',    'Answered'],
        'NO ANSWER'   => ['warning', 'fa-phone',    'No Answer'],
        'BUSY'        => ['danger',  'fa-ban',      'Busy'],
        'FAILED'      => ['danger',  'fa-times',    'Failed'],
        'PENDING'     => ['info',    'fa-clock-o',  'Pending'],
        'UNKNOWN'     => ['default', 'fa-minus',    'Not Tracked'],
    ];
    $key  = strtoupper(trim($disposition));
    $conf = $map[$key] ?? ['default', 'fa-minus', 'Not Tracked'];
    return '<span class="label label-' . $conf[0] . '"><i class="fa ' . $conf[1] . '"></i> ' . htmlspecialchars($conf[2]) . '</span>';
}

/**
 * Return a direction icon.
 */
function salesos_direction_icon(string $direction): string
{
    $icons = [
        'inbound'  => '<i class="fa fa-phone-square text-success" title="Inbound"></i>',
        'outbound' => '<i class="fa fa-phone text-primary" title="Outbound"></i>',
        'internal' => '<i class="fa fa-exchange text-info" title="Internal"></i>',
    ];
    return $icons[$direction] ?? '<i class="fa fa-question"></i>';
}

/**
 * Return recording player HTML if URL provided.
 */
function salesos_recording_player(string $url): string
{
    if (!$url) {
        return '<span class="text-muted">—</span>';
    }
    return '<audio controls preload="none" style="height:30px;max-width:220px;">
        <source src="' . htmlspecialchars($url) . '" type="audio/wav">
    </audio>';
}

/**
 * Encrypt a SIP password for at-rest storage (AES-256-CBC, key derived from CI encryption_key).
 */
function salesos_encrypt_sip_password(string $plain): string
{
    $key = _salesos_derive_enc_key();
    $iv  = random_bytes(16);
    $enc = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $enc);
}

/**
 * Decrypt a SIP password stored by salesos_encrypt_sip_password().
 */
function salesos_decrypt_sip_password(string $stored): string
{
    if (empty($stored)) return '';
    $key  = _salesos_derive_enc_key();
    $data = base64_decode($stored, true);
    if ($data === false || strlen($data) < 17) return '';
    $dec = openssl_decrypt(substr($data, 16), 'AES-256-CBC', $key, OPENSSL_RAW_DATA, substr($data, 0, 16));
    return ($dec !== false) ? $dec : '';
}

function _salesos_derive_enc_key(): string
{
    $ci_key = config_item('encryption_key') ?: (defined('APP_SALT') ? APP_SALT : '');
    return substr(hash('sha256', 'salesos:sip:v1:' . $ci_key, true), 0, 32);
}

/**
 * Read a SalesOS setting from Perfex options with optional environment fallback.
 * This keeps local-dev machines flexible when the public IP changes.
 */
function salesos_setting(string $option_key, $default = '', ?string $env_key = null)
{
    $value = get_option($option_key, $default);
    if ($value !== '' && $value !== null) {
        return $value;
    }

    if ($env_key) {
        $env = getenv($env_key);
        if ($env !== false && $env !== '') {
            return $env;
        }
    }

    return $default;
}

/**
 * Entity link helper — returns an anchor to the lead/contact/client.
 */
function salesos_entity_link(array $call): string
{
    $type = $call['match_type'] ?? 'none';
    if ($type === 'lead' && !empty($call['lead_id'])) {
        return '<a href="' . admin_url('leads/index/' . $call['lead_id']) . '">' . htmlspecialchars($call['src_name'] ?? $call['dst_name'] ?? 'Lead #' . $call['lead_id']) . '</a>';
    }
    if (in_array($type, ['contact', 'client']) && !empty($call['client_id'])) {
        return '<a href="' . admin_url('clients/client/' . $call['client_id']) . '">' . htmlspecialchars($call['src_name'] ?? $call['dst_name'] ?? 'Client #' . $call['client_id']) . '</a>';
    }
    return '<span class="text-muted">Unknown</span>';
}
