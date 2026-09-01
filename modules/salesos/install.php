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

    // PBX connection (§8a) — deliberately left empty here, not hardcoded to any
    // specific box. Filled in via Settings → Connection, from provision_pbx.sh's
    // output. This is what makes "switch which PBX we talk to" a Settings save,
    // not a code deploy.
    'salesos_ami_host'         => '',
    'salesos_ami_port'         => '5038',
    'salesos_ami_username'     => '',
    'salesos_ami_secret'       => '',
    'salesos_cdr_db_host'      => '',
    'salesos_cdr_db_port'      => '3306',
    'salesos_cdr_db_name'      => 'asteriskcdrdb',
    'salesos_cdr_db_user'      => '',
    'salesos_cdr_db_password'  => '',
];

foreach ($defaults as $key => $value) {
    $CI->db->query(
        'INSERT IGNORE INTO `' . db_prefix() . 'salesos_settings` (`skey`, `svalue`) VALUES (?, ?)',
        [$key, $value]
    );
}

// ── Agents (staff ↔ extension mapping — needed by click-to-call) ────────────
if (!$CI->db->table_exists(db_prefix() . 'salesos_agents')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'salesos_agents` (
        `id`         INT(11)      NOT NULL AUTO_INCREMENT,
        `staff_id`   INT(11)      NOT NULL,
        `extension`  VARCHAR(20)  NOT NULL,
        `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
        `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `staff_id`  (`staff_id`),
        UNIQUE KEY `extension` (`extension`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
}

// ── Calls (CDR mirror + CRM enrichment) ──────────────────────────────────────
if (!$CI->db->table_exists(db_prefix() . 'salesos_calls')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'salesos_calls` (
        `id`            INT(11)      NOT NULL AUTO_INCREMENT,
        `uniqueid`      VARCHAR(64)  NOT NULL,
        `calldate`      DATETIME     NOT NULL,
        `direction`     ENUM("inbound","outbound","internal","unknown") NOT NULL DEFAULT "unknown",
        `src`           VARCHAR(80)  NOT NULL DEFAULT "",
        `dst`           VARCHAR(80)  NOT NULL DEFAULT "",
        `extension`     VARCHAR(20)  DEFAULT NULL,
        `duration`      INT(11)      NOT NULL DEFAULT 0,
        `billsec`       INT(11)      NOT NULL DEFAULT 0,
        `disposition`   VARCHAR(45)  NOT NULL DEFAULT "",
        `recordingfile` VARCHAR(512) DEFAULT NULL,
        `agent_id`      INT(11)      DEFAULT NULL,
        `lead_id`       INT(11)      DEFAULT NULL,
        `contact_id`    INT(11)      DEFAULT NULL,
        `client_id`     INT(11)      DEFAULT NULL,
        `match_type`    ENUM("lead","contact","client","none","manual") DEFAULT "none",
        `disposition_code` VARCHAR(60) DEFAULT NULL,
        `wrapup_notes`  TEXT         DEFAULT NULL,
        `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniqueid`  (`uniqueid`),
        KEY `calldate`   (`calldate`),
        KEY `src`        (`src`),
        KEY `dst`        (`dst`),
        KEY `agent_id`   (`agent_id`),
        KEY `lead_id`    (`lead_id`),
        KEY `contact_id` (`contact_id`),
        KEY `client_id`  (`client_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
}

// ── Phone index (fast lookup: phone → entity) ─────────────────────────────────
if (!$CI->db->table_exists(db_prefix() . 'salesos_phone_index')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'salesos_phone_index` (
        `id`          INT(11)     NOT NULL AUTO_INCREMENT,
        `phone`       VARCHAR(30) NOT NULL,
        `entity_type` ENUM("lead","contact","client") NOT NULL,
        `entity_id`   INT(11)     NOT NULL,
        `entity_name` VARCHAR(200) DEFAULT NULL,
        `created_at`  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `phone_entity` (`phone`, `entity_type`, `entity_id`),
        KEY `phone` (`phone`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
}

// ── CDR sync bookkeeping ──────────────────────────────────────────────────────
if (!$CI->db->table_exists(db_prefix() . 'salesos_cdr_sync')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'salesos_cdr_sync` (
        `id`             INT(11)     NOT NULL AUTO_INCREMENT,
        `last_sync`      DATETIME    DEFAULT NULL,
        `last_uniqueid`  VARCHAR(64) DEFAULT NULL,
        `records_synced` INT(11)     NOT NULL DEFAULT 0,
        `status`         ENUM("ok","error","running") NOT NULL DEFAULT "ok",
        `error_msg`      TEXT        DEFAULT NULL,
        `synced_at`      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
}
