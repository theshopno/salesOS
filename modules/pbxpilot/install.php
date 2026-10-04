<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Self-initialize $CI when called from migration context (not from activation hook)
if (!isset($CI)) {
    $CI = &get_instance();
}

// ── Migration: this module was called `salesos` until the SalesOS name moved
//    to the e-commerce kernel. Carry an existing install forward in place —
//    RENAME TABLE keeps live agent mappings, call history and the PBX
//    credentials in the settings table, where a drop-and-recreate would lose
//    all of it. Runs before anything below, so the CREATE TABLE guards then
//    see the already-renamed tables and skip. ──────────────────────────────
$migrated_from_salesos = false;

foreach (['settings', 'agents', 'calls', 'phone_index', 'active_calls', 'cdr_sync'] as $legacy_table) {
    $old = db_prefix() . 'salesos_' . $legacy_table;
    $new = db_prefix() . 'pbxpilot_' . $legacy_table;

    if ($CI->db->table_exists($old) && !$CI->db->table_exists($new)) {
        $CI->db->query("RENAME TABLE `{$old}` TO `{$new}`");
        $migrated_from_salesos = true;
    }
}

// CodeIgniter caches the table list for the request, so without this the
// table_exists() guards below would still answer from the pre-rename snapshot
// and try to recreate the tables the RENAME above just produced.
$CI->db->data_cache = [];

// Setting keys are namespaced too (`salesos_ami_host` → `pbxpilot_ami_host`),
// so they have to move with the table or every lookup silently returns the
// default and a live PBX connection appears unconfigured.
if ($CI->db->table_exists(db_prefix() . 'pbxpilot_settings')) {
    $CI->db->query(
        'UPDATE `' . db_prefix() . 'pbxpilot_settings`
         SET skey = CONCAT("pbxpilot_", SUBSTRING(skey, 9))
         WHERE skey LIKE "salesos\_%"'
    );
}

// Drop the old registration, but ONLY in the run that actually migrated this
// module's tables. `salesos` is now the e-commerce kernel's name too, so an
// unconditional delete here would deregister the kernel every time this module
// is reactivated.
if ($migrated_from_salesos) {
    $CI->db->where('module_name', 'salesos');
    $CI->db->delete(db_prefix() . 'modules');
}

// ── Settings (dedicated key/value store — deliberately NOT Perfex's global
//    `options` table, so pbxpilot config stays namespaced and portable; see
//    docs/pbxpilot-architecture-plan.md §6) ─────────────────────────────
if (!$CI->db->table_exists(db_prefix() . 'pbxpilot_settings')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'pbxpilot_settings` (
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
    'pbxpilot_core_telephony'             => '1', // administratively always-on; must still fail soft (§6)
    'pbxpilot_channel_browser'            => '1',
    'pbxpilot_channel_desktop'            => '1',
    'pbxpilot_ai_intelligence_mode'       => 'manual', // off|manual|auto_flagged|full_auto
    'pbxpilot_ai_model'                   => '',
    // A call counts as "effective" (a real conversation, not just a pickup)
    // when it was answered and lasted at least this many seconds — used by
    // the Dashboard and Calls report. Tunable per team, not hardcoded.
    'pbxpilot_effective_call_seconds'     => '120',
    // How long a cached recording MP3 is kept locally before cleanup deletes
    // it. Only trims our local cache — never touches the recording on the
    // PBX itself, and get_mp3_path() will happily re-fetch/re-convert on
    // next request if someone plays an old call after its cache expired.
    'pbxpilot_recording_retention_days'   => '90',
    'pbxpilot_agency_pack'                => '0',
    'pbxpilot_agency_bizbot_messaging'    => '0',
    'pbxpilot_agency_bizbot_provisioning' => '0',
    'pbxpilot_voice_escalation'           => '0', // hard-gated on salesos ledger — see pbxpilot_helper.php

    // PBX connection (§8a) — deliberately left empty here, not hardcoded to any
    // specific box. Filled in via Settings → Connection, from provision_pbx.sh's
    // output. This is what makes "switch which PBX we talk to" a Settings save,
    // not a code deploy.
    'pbxpilot_ami_host'         => '',
    'pbxpilot_ami_port'         => '5038',
    'pbxpilot_ami_username'     => '',
    'pbxpilot_ami_secret'       => '',
    'pbxpilot_cdr_db_host'      => '',
    'pbxpilot_cdr_db_port'      => '3306',
    'pbxpilot_cdr_db_name'      => 'asteriskcdrdb',
    'pbxpilot_cdr_db_user'      => '',
    'pbxpilot_cdr_db_password'  => '',
    // Base URL for fetching call recordings from the PBX's Asterisk HTTP
    // static server (enablestatic=yes), e.g. http://127.0.0.1:18088/static/recordings/
    // — usually the local end of the same SSH tunnel used for AMI/CDR.
    'pbxpilot_recordings_url'   => '',
    // Absolute path on the PBX itself where MixMonitor writes recordings —
    // used to build the "file convert" command run over AMI when a call was
    // recorded in GSM and needs an on-demand WAV copy for browser playback.
    'pbxpilot_recordings_monitor_dir' => '/var/spool/asterisk/monitor',
];

foreach ($defaults as $key => $value) {
    $CI->db->query(
        'INSERT IGNORE INTO `' . db_prefix() . 'pbxpilot_settings` (`skey`, `svalue`) VALUES (?, ?)',
        [$key, $value]
    );
}

// ── Agents (staff ↔ extension & Bizbot WhatsApp mapping) ────────────
if (!$CI->db->table_exists(db_prefix() . 'pbxpilot_agents')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'pbxpilot_agents` (
        `id`                INT(11)      NOT NULL AUTO_INCREMENT,
        `staff_id`          INT(11)      NOT NULL,
        `extension`         VARCHAR(20)  DEFAULT NULL,
        `bizbot_agent_guid` VARCHAR(64)  DEFAULT NULL,
        `whatsapp_aliases`  TEXT         DEFAULT NULL,
        `caller_id`         VARCHAR(32)  DEFAULT NULL,
        `is_active`         TINYINT(1)   NOT NULL DEFAULT 1,
        `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `staff_id`  (`staff_id`),
        UNIQUE KEY `extension` (`extension`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
} else {
    // Migration: ensure new unified channel fields exist
    $fields = $CI->db->list_fields(db_prefix() . 'pbxpilot_agents');
    if (!in_array('bizbot_agent_guid', $fields)) {
        $CI->db->query('ALTER TABLE `' . db_prefix() . 'pbxpilot_agents` ADD COLUMN `bizbot_agent_guid` VARCHAR(64) NULL DEFAULT NULL AFTER `extension`');
    }
    if (!in_array('whatsapp_aliases', $fields)) {
        $CI->db->query('ALTER TABLE `' . db_prefix() . 'pbxpilot_agents` ADD COLUMN `whatsapp_aliases` TEXT NULL DEFAULT NULL AFTER `bizbot_agent_guid`');
    }
    if (!in_array('caller_id', $fields)) {
        $CI->db->query('ALTER TABLE `' . db_prefix() . 'pbxpilot_agents` ADD COLUMN `caller_id` VARCHAR(32) NULL DEFAULT NULL AFTER `whatsapp_aliases`');
    }
}

// ── Calls (CDR mirror + CRM enrichment) ──────────────────────────────────────
if (!$CI->db->table_exists(db_prefix() . 'pbxpilot_calls')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'pbxpilot_calls` (
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
if (!$CI->db->table_exists(db_prefix() . 'pbxpilot_phone_index')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'pbxpilot_phone_index` (
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

// ── Active calls (real-time popup polling — §9 Phase 1 screen-pop) ───────────
if (!$CI->db->table_exists(db_prefix() . 'pbxpilot_active_calls')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'pbxpilot_active_calls` (
        `id`          INT(11)     NOT NULL AUTO_INCREMENT,
        `uniqueid`    VARCHAR(64) NOT NULL,
        `src`         VARCHAR(80) NOT NULL DEFAULT "",
        `dst`         VARCHAR(80) NOT NULL DEFAULT "",
        `extension`   VARCHAR(20) DEFAULT NULL,
        `agent_id`    INT(11)     DEFAULT NULL,
        `state`       ENUM("ringing","answered") NOT NULL DEFAULT "ringing",
        `started_at`  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `popup_shown` TINYINT(1)  NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniqueid` (`uniqueid`),
        KEY `agent_id` (`agent_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
}

// ── CDR sync bookkeeping ──────────────────────────────────────────────────────
if (!$CI->db->table_exists(db_prefix() . 'pbxpilot_cdr_sync')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'pbxpilot_cdr_sync` (
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
