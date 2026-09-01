<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Self-initialize $CI when called from migration context (not from activation hook)
if (!isset($CI)) {
    $CI = &get_instance();
}

// ── Settings (dedicated key/value store — deliberately NOT Perfex's global
//    `options` table, so salesos config stays namespaced and portable; see
//    docs/salesos-v2-architecture-plan.md §6) ─────────────────────────────
if (!$CI->db->table_exists(db_prefix() . 'salesos_settings')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'salesos_settings` (
        `id`         INT(11)     NOT NULL AUTO_INCREMENT,
        `skey`       VARCHAR(80) NOT NULL,
        `svalue`     TEXT        DEFAULT NULL,
        `updated_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `skey` (`skey`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
}

// ── Default feature flags (§6 of the architecture plan) — seeded once, never
//    overwritten on re-install (INSERT IGNORE) so a running install's saved
//    choices survive a module reactivation ──────────────────────────────────
$defaults = [
    'salesos_core_telephony'             => '1', // administratively always-on; must still fail soft (§6)
    'salesos_channel_browser'            => '1',
    'salesos_channel_desktop'            => '1',
    'salesos_ai_intelligence_mode'       => 'manual', // off|manual|auto_flagged|full_auto
    'salesos_ai_model'                   => '',
    'salesos_agency_pack'                => '0',
    'salesos_agency_bizbot_messaging'    => '0',
    'salesos_agency_bizbot_provisioning' => '0',
    'salesos_voice_escalation'           => '0', // hard-gated on ecomcore ledger — see salesos_helper.php
];

foreach ($defaults as $key => $value) {
    $CI->db->query(
        'INSERT IGNORE INTO `' . db_prefix() . 'salesos_settings` (`skey`, `svalue`) VALUES (?, ?)',
        [$key, $value]
    );
}
