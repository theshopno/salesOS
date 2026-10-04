<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * pbxpilot_settings accessors — deliberately separate from Perfex's global
 * get_option()/update_option() (docs/pbxpilot-architecture-plan.md §6).
 */
function pbxpilot_get_option(string $key, $default = null)
{
    if (function_exists('get_instance')) {
        $CI = &get_instance();
        if ($CI && isset($CI->db)) {
            $prefix = function_exists('db_prefix') ? db_prefix() : 'tbl';
            $row = $CI->db->query(
                'SELECT svalue FROM `' . $prefix . 'pbxpilot_settings` WHERE skey = ?',
                [$key]
            )->row();

            return $row ? $row->svalue : $default;
        }
    }

    // CLI / Standalone fallback
    static $cli_db = null;
    if ($cli_db === null) {
        if (!defined('APP_DB_HOSTNAME')) {
            $cfg = dirname(__DIR__, 3) . '/application/config/app-config.php';
            if (is_file($cfg)) {
                require_once $cfg;
            }
        }
        if (defined('APP_DB_HOSTNAME')) {
            $cli_db = @new mysqli(APP_DB_HOSTNAME, APP_DB_USERNAME, APP_DB_PASSWORD, APP_DB_NAME);
        }
    }

    if ($cli_db && !$cli_db->connect_errno) {
        $stmt = $cli_db->prepare('SELECT svalue FROM tblpbxpilot_settings WHERE skey = ?');
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $stmt->bind_result($val);
        $found = $stmt->fetch();
        $stmt->close();

        return $found && $val !== null ? $val : $default;
    }

    return $default;
}

function pbxpilot_update_option(string $key, $value): bool
{
    if (function_exists('get_instance')) {
        $CI = &get_instance();
        if ($CI && isset($CI->db)) {
            $prefix = function_exists('db_prefix') ? db_prefix() : 'tbl';
            $CI->db->query(
                'INSERT INTO `' . $prefix . 'pbxpilot_settings` (skey, svalue) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)',
                [$key, $value]
            );

            return true;
        }
    }

    // CLI / Standalone fallback
    static $cli_db_up = null;
    if ($cli_db_up === null && defined('APP_DB_HOSTNAME')) {
        $cli_db_up = @new mysqli(APP_DB_HOSTNAME, APP_DB_USERNAME, APP_DB_PASSWORD, APP_DB_NAME);
    }
    if ($cli_db_up && !$cli_db_up->connect_errno) {
        $stmt = $cli_db_up->prepare(
            'INSERT INTO tblpbxpilot_settings (skey, svalue) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)'
        );
        $stmt->bind_param('ss', $key, $value);
        $stmt->execute();
        $stmt->close();
    }

    return true;
}

/**
 * Voice Escalation Bridge gate (§2, §6): pbxpilot_voice_escalation can only be
 * turned on once docs/salesos-ledger.md shows salesos Phase 1 and Phase 9
 * both `Done`. Parses the ledger's phase table rather than trusting a cached
 * flag, since that file is the authoritative, live progress record.
 *
 * @return array{allowed: bool, reason: string}
 */
function pbxpilot_voice_escalation_gate_status(): array
{
    $ledger_path = FCPATH . 'docs/salesos-ledger.md';

    if (!is_file($ledger_path)) {
        return ['allowed' => false, 'reason' => 'docs/salesos-ledger.md not found — cannot verify salesos Phase 1/9 status.'];
    }

    $phase1_done = false;
    $phase9_done = false;

    foreach (file($ledger_path) as $line) {
        if (strpos($line, '|') !== 0 && substr(trim($line), 0, 1) !== '|') {
            continue;
        }
        $cols = array_map('trim', explode('|', trim($line)));
        // Row shape: | Phase | Module | Status | Started | Finished | Blocker |
        // explode() on a leading '|' yields an empty first element — index 1 is Phase.
        if (!isset($cols[1], $cols[3]) || !is_numeric($cols[1])) {
            continue;
        }
        $phase  = (int) $cols[1];
        $status = $cols[3];
        if ($phase === 1) {
            $phase1_done = ($status === 'Done');
        }
        if ($phase === 9) {
            $phase9_done = ($status === 'Done');
        }
    }

    if ($phase1_done && $phase9_done) {
        return ['allowed' => true, 'reason' => ''];
    }

    $missing = [];
    if (!$phase1_done) $missing[] = 'salesos Phase 1 (salesos kernel)';
    if (!$phase9_done) $missing[] = 'salesos Phase 9 (ordernotifier)';

    return [
        'allowed' => false,
        'reason'  => 'Cannot enable Voice Escalation yet — waiting on ' . implode(' and ', $missing)
                   . ' to reach Done in docs/salesos-ledger.md.',
    ];
}
