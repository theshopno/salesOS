<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Self-initialize $CI when called from migration context (not from activation hook)
if (!isset($CI)) {
    $CI = &get_instance();
}

// ── Agents ────────────────────────────────────────────────────────────────────
if (!$CI->db->table_exists(db_prefix() . 'salesos_agents')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'salesos_agents` (
        `id`           INT(11)      NOT NULL AUTO_INCREMENT,
        `staff_id`     INT(11)      NOT NULL,
        `extension`    VARCHAR(20)  NOT NULL,
        `fullname`     VARCHAR(150) DEFAULT NULL,
        `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
        `is_logged_in` TINYINT(1)   NOT NULL DEFAULT 0,
        `last_seen`    DATETIME     DEFAULT NULL,
        `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `staff_id`  (`staff_id`),
        UNIQUE KEY `extension` (`extension`),
        KEY `is_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
}

// ── Calls (CDR mirror + CRM enrichment) ──────────────────────────────────────
if (!$CI->db->table_exists(db_prefix() . 'salesos_calls')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'salesos_calls` (
        `id`             INT(11)      NOT NULL AUTO_INCREMENT,
        `uniqueid`       VARCHAR(64)  NOT NULL,
        `linkedid`       VARCHAR(64)  DEFAULT NULL,
        `calldate`       DATETIME     NOT NULL,
        `direction`      ENUM("inbound","outbound","internal","unknown") NOT NULL DEFAULT "unknown",
        `src`            VARCHAR(80)  NOT NULL DEFAULT "",
        `dst`            VARCHAR(80)  NOT NULL DEFAULT "",
        `src_name`       VARCHAR(120) DEFAULT NULL,
        `dst_name`       VARCHAR(120) DEFAULT NULL,
        `extension`      VARCHAR(20)  DEFAULT NULL,
        `channel`        VARCHAR(120) DEFAULT NULL,
        `dstchannel`     VARCHAR(120) DEFAULT NULL,
        `duration`       INT(11)      NOT NULL DEFAULT 0,
        `billsec`        INT(11)      NOT NULL DEFAULT 0,
        `disposition`    VARCHAR(45)  NOT NULL DEFAULT "",
        `recordingfile`  VARCHAR(512) DEFAULT NULL,
        `agent_id`       INT(11)      DEFAULT NULL,
        `lead_id`        INT(11)      DEFAULT NULL,
        `contact_id`     INT(11)      DEFAULT NULL,
        `client_id`      INT(11)      DEFAULT NULL,
        `match_type`     ENUM("lead","contact","client","none","manual") DEFAULT "none",
        `conversation_id` INT(11)     DEFAULT NULL,
        `notes`          TEXT         DEFAULT NULL,
        `synced_at`      DATETIME     DEFAULT NULL,
        `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniqueid`    (`uniqueid`),
        KEY `calldate`    (`calldate`),
        KEY `src`         (`src`),
        KEY `dst`         (`dst`),
        KEY `agent_id`    (`agent_id`),
        KEY `lead_id`     (`lead_id`),
        KEY `contact_id`  (`contact_id`),
        KEY `client_id`   (`client_id`),
        KEY `direction`   (`direction`),
        KEY `disposition` (`disposition`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
}

// ── Recordings (dedicated table, separate from CDR recordingfile) ──────────────
if (!$CI->db->table_exists(db_prefix() . 'salesos_recordings')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'salesos_recordings` (
        `id`           INT(11)      NOT NULL AUTO_INCREMENT,
        `call_id`      INT(11)      NOT NULL,
        `uniqueid`     VARCHAR(64)  NOT NULL,
        `filename`     VARCHAR(255) NOT NULL,
        `filepath`     VARCHAR(512) NOT NULL,
        `filesize`     INT(11)      DEFAULT 0,
        `duration`     INT(11)      DEFAULT 0,
        `format`       VARCHAR(10)  DEFAULT "wav",
        `public_url`   VARCHAR(512) DEFAULT NULL,
        `is_available` TINYINT(1)   NOT NULL DEFAULT 1,
        `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `call_id`  (`call_id`),
        KEY `uniqueid` (`uniqueid`)
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
        `is_primary`  TINYINT(1)  NOT NULL DEFAULT 0,
        `created_at`  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `phone_entity` (`phone`, `entity_type`, `entity_id`),
        KEY `phone`       (`phone`),
        KEY `entity_type` (`entity_type`),
        KEY `entity_id`   (`entity_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
}

// ── Events (immutable audit log for all module activity) ──────────────────────
if (!$CI->db->table_exists(db_prefix() . 'salesos_events')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'salesos_events` (
        `id`           INT(11)      NOT NULL AUTO_INCREMENT,
        `event_type`   VARCHAR(60)  NOT NULL,
        `call_id`      INT(11)      DEFAULT NULL,
        `agent_id`     INT(11)      DEFAULT NULL,
        `staff_id`     INT(11)      DEFAULT NULL,
        `entity_type`  VARCHAR(20)  DEFAULT NULL,
        `entity_id`    INT(11)      DEFAULT NULL,
        `payload`      JSON         DEFAULT NULL,
        `description`  TEXT         DEFAULT NULL,
        `ip_address`   VARCHAR(45)  DEFAULT NULL,
        `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `event_type`  (`event_type`),
        KEY `call_id`     (`call_id`),
        KEY `agent_id`    (`agent_id`),
        KEY `entity_type` (`entity_type`),
        KEY `entity_id`   (`entity_id`),
        KEY `created_at`  (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
}

// ── Settings (dedicated key/value, replaces options table dependency) ──────────
if (!$CI->db->table_exists(db_prefix() . 'salesos_settings')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'salesos_settings` (
        `id`          INT(11)      NOT NULL AUTO_INCREMENT,
        `skey`        VARCHAR(80)  NOT NULL,
        `svalue`      TEXT         DEFAULT NULL,
        `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `skey` (`skey`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
}

if (get_option('salesos_click_to_call_enabled') === false) {
    add_option('salesos_click_to_call_enabled', '0');
}

// ── Conversations (groups calls into a customer conversation thread) ───────────
if (!$CI->db->table_exists(db_prefix() . 'salesos_conversations')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'salesos_conversations` (
        `id`           INT(11)     NOT NULL AUTO_INCREMENT,
        `entity_type`  ENUM("lead","contact","client") NOT NULL,
        `entity_id`    INT(11)     NOT NULL,
        `phone`        VARCHAR(30) DEFAULT NULL,
        `agent_id`     INT(11)     DEFAULT NULL,
        `total_calls`  INT(11)     NOT NULL DEFAULT 0,
        `last_call_at` DATETIME    DEFAULT NULL,
        `last_direction` ENUM("inbound","outbound","internal") DEFAULT NULL,
        `last_disposition` VARCHAR(45) DEFAULT NULL,
        `status`       ENUM("active","closed","follow_up") NOT NULL DEFAULT "active",
        `notes`        TEXT        DEFAULT NULL,
        `created_at`   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `entity`      (`entity_type`, `entity_id`),
        KEY `phone`       (`phone`),
        KEY `agent_id`    (`agent_id`),
        KEY `last_call_at` (`last_call_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
}

// ── Active calls (real-time popup polling) ────────────────────────────────────
if (!$CI->db->table_exists(db_prefix() . 'salesos_active_calls')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'salesos_active_calls` (
        `id`          INT(11)     NOT NULL AUTO_INCREMENT,
        `uniqueid`    VARCHAR(64) NOT NULL,
        `channel`     VARCHAR(120) DEFAULT NULL,
        `src`         VARCHAR(80)  NOT NULL DEFAULT "",
        `dst`         VARCHAR(80)  NOT NULL DEFAULT "",
        `direction`   ENUM("inbound","outbound","internal") NOT NULL DEFAULT "inbound",
        `extension`   VARCHAR(20)  DEFAULT NULL,
        `agent_id`    INT(11)      DEFAULT NULL,
        `staff_id`    INT(11)      DEFAULT NULL,
        `lead_id`     INT(11)      DEFAULT NULL,
        `contact_id`  INT(11)      DEFAULT NULL,
        `client_id`   INT(11)      DEFAULT NULL,
        `match_type`  VARCHAR(20)  DEFAULT "none",
        `caller_name` VARCHAR(120) DEFAULT NULL,
        `state`       ENUM("ringing","answered","onhold","transferred") NOT NULL DEFAULT "ringing",
        `started_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `popup_shown` TINYINT(1)   NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniqueid` (`uniqueid`),
        KEY `extension` (`extension`),
        KEY `staff_id`  (`staff_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
}

// ── CDR sync log ──────────────────────────────────────────────────────────────
if (!$CI->db->table_exists(db_prefix() . 'salesos_cdr_sync')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'salesos_cdr_sync` (
        `id`              INT(11)     NOT NULL AUTO_INCREMENT,
        `last_sync`       DATETIME    DEFAULT NULL,
        `last_uniqueid`   VARCHAR(64) DEFAULT NULL,
        `records_synced`  INT(11)     NOT NULL DEFAULT 0,
        `status`          ENUM("ok","error","running") NOT NULL DEFAULT "ok",
        `error_msg`       TEXT        DEFAULT NULL,
        `synced_at`       DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
}

// ── Future reserved tables (Phase 2+) ────────────────────────────────────────

if (!$CI->db->table_exists(db_prefix() . 'salesos_transcripts')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'salesos_transcripts` (
        `id`          INT(11)  NOT NULL AUTO_INCREMENT,
        `call_id`     INT(11)  NOT NULL,
        `recording_id` INT(11) DEFAULT NULL,
        `language`    VARCHAR(10) DEFAULT "bn",
        `provider`    VARCHAR(30) DEFAULT NULL,
        `raw_text`    LONGTEXT DEFAULT NULL,
        `segments`    JSON     DEFAULT NULL,
        `confidence`  DECIMAL(5,4) DEFAULT NULL,
        `status`      ENUM("pending","processing","done","failed") NOT NULL DEFAULT "pending",
        `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `call_id`     (`call_id`),
        KEY `recording_id` (`recording_id`),
        KEY `status`      (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
}

if (!$CI->db->table_exists(db_prefix() . 'salesos_ai_jobs')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'salesos_ai_jobs` (
        `id`           INT(11)     NOT NULL AUTO_INCREMENT,
        `job_type`     VARCHAR(50) NOT NULL,
        `call_id`      INT(11)     DEFAULT NULL,
        `transcript_id` INT(11)   DEFAULT NULL,
        `payload`      JSON        DEFAULT NULL,
        `result`       JSON        DEFAULT NULL,
        `status`       ENUM("queued","running","done","failed") NOT NULL DEFAULT "queued",
        `attempts`     TINYINT(1)  NOT NULL DEFAULT 0,
        `last_error`   TEXT        DEFAULT NULL,
        `created_at`   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `processed_at` DATETIME    DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `job_type` (`job_type`),
        KEY `status`   (`status`),
        KEY `call_id`  (`call_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
}

if (!$CI->db->table_exists(db_prefix() . 'salesos_ai_analysis')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'salesos_ai_analysis` (
        `id`              INT(11) NOT NULL AUTO_INCREMENT,
        `call_id`         INT(11) NOT NULL,
        `summary`         TEXT    DEFAULT NULL,
        `sentiment`       ENUM("positive","neutral","negative","mixed") DEFAULT NULL,
        `sentiment_score` DECIMAL(4,3) DEFAULT NULL,
        `intent`          VARCHAR(100) DEFAULT NULL,
        `action_items`    JSON    DEFAULT NULL,
        `key_topics`      JSON    DEFAULT NULL,
        `talk_ratio`      JSON    DEFAULT NULL,
        `objections`      JSON    DEFAULT NULL,
        `ai_provider`     VARCHAR(30) DEFAULT NULL,
        `ai_model`        VARCHAR(50) DEFAULT NULL,
        `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `call_id` (`call_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
}

if (!$CI->db->table_exists(db_prefix() . 'salesos_scorecards')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'salesos_scorecards` (
        `id`          INT(11)     NOT NULL AUTO_INCREMENT,
        `call_id`     INT(11)     NOT NULL,
        `agent_id`    INT(11)     NOT NULL,
        `graded_by`   INT(11)     DEFAULT NULL,
        `total_score` DECIMAL(5,2) DEFAULT NULL,
        `criteria`    JSON        DEFAULT NULL,
        `notes`       TEXT        DEFAULT NULL,
        `graded_at`   DATETIME    DEFAULT NULL,
        `created_at`  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `call_id`  (`call_id`),
        KEY `agent_id` (`agent_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
}

if (!$CI->db->table_exists(db_prefix() . 'salesos_coaching_feedback')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'salesos_coaching_feedback` (
        `id`          INT(11)     NOT NULL AUTO_INCREMENT,
        `agent_id`    INT(11)     NOT NULL,
        `call_id`     INT(11)     DEFAULT NULL,
        `given_by`    INT(11)     DEFAULT NULL,
        `type`        ENUM("manual","ai","peer") NOT NULL DEFAULT "manual",
        `category`    VARCHAR(60) DEFAULT NULL,
        `feedback`    TEXT        NOT NULL,
        `action_plan` TEXT        DEFAULT NULL,
        `created_at`  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `agent_id` (`agent_id`),
        KEY `call_id`  (`call_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
}

// ── Call events (telephony lifecycle archive, separate from CRM audit log) ───
if (!$CI->db->table_exists(db_prefix() . 'salesos_call_events')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'salesos_call_events` (
        `id`              BIGINT       NOT NULL AUTO_INCREMENT,
        `stream_id`       VARCHAR(30)  NOT NULL,
        `schema_ver`      TINYINT      NOT NULL DEFAULT 1,
        `event_type`      VARCHAR(60)  NOT NULL,
        `pbx_id`          VARCHAR(20)  DEFAULT NULL,
        `pbx_name`        VARCHAR(80)  DEFAULT NULL,
        `session_id`      VARCHAR(64)  DEFAULT NULL,
        `conversation_id` VARCHAR(64)  DEFAULT NULL,
        `call_uniqueid`   VARCHAR(64)  NOT NULL DEFAULT "",
        `call_id`         INT(11)      DEFAULT NULL,
        `from_state`      VARCHAR(20)  DEFAULT NULL,
        `to_state`        VARCHAR(20)  DEFAULT NULL,
        `direction`       VARCHAR(10)  DEFAULT NULL,
        `queue_name`      VARCHAR(80)  DEFAULT NULL,
        `agent_id`        INT(11)      DEFAULT NULL,
        `staff_id`        INT(11)      DEFAULT NULL,
        `entity_type`     VARCHAR(20)  DEFAULT NULL,
        `entity_id`       INT(11)      DEFAULT NULL,
        `hold_seq`        TINYINT      NOT NULL DEFAULT 0,
        `xfer_seq`        TINYINT      NOT NULL DEFAULT 0,
        `payload`         JSON         DEFAULT NULL,
        `created_at`      DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
        PRIMARY KEY (`id`),
        UNIQUE KEY `stream_id`      (`stream_id`),
        KEY `call_uniqueid`         (`call_uniqueid`),
        KEY `session_id`            (`session_id`),
        KEY `conversation_id`       (`conversation_id`),
        KEY `event_type`            (`event_type`),
        KEY `call_id`               (`call_id`),
        KEY `agent_id`              (`agent_id`),
        KEY `entity`                (`entity_type`, `entity_id`),
        KEY `queue_name`            (`queue_name`),
        KEY `created_at`            (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
}

// ── Schema migrations for existing tables (idempotent column additions) ───────

// salesos_calls: add pbx_id, pbx_name
$calls_cols = $CI->db->query('SHOW COLUMNS FROM `' . db_prefix() . 'salesos_calls` LIKE "pbx_id"')->result_array();
if (empty($calls_cols)) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'salesos_calls`
        ADD COLUMN `pbx_id`   VARCHAR(20) DEFAULT NULL AFTER `conversation_id`,
        ADD COLUMN `pbx_name` VARCHAR(80) DEFAULT NULL AFTER `pbx_id`');
}

// salesos_active_calls: add pbx_id, pbx_name, conversation_id
$ac_cols = $CI->db->query('SHOW COLUMNS FROM `' . db_prefix() . 'salesos_active_calls` LIKE "pbx_id"')->result_array();
if (empty($ac_cols)) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'salesos_active_calls`
        ADD COLUMN `pbx_id`          VARCHAR(20) DEFAULT NULL AFTER `popup_shown`,
        ADD COLUMN `pbx_name`        VARCHAR(80) DEFAULT NULL AFTER `pbx_id`,
        ADD COLUMN `conversation_id` VARCHAR(64) DEFAULT NULL AFTER `pbx_name`');
}

// salesos_agents: add presence column for extended availability states
$ag_cols = $CI->db->query('SHOW COLUMNS FROM `' . db_prefix() . 'salesos_agents` LIKE "presence"')->result_array();
if (empty($ag_cols)) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'salesos_agents`
        ADD COLUMN `presence` ENUM("ONLINE","OFFLINE","READY","BUSY","RINGING","PAUSED","WRAPUP","BREAK","LUNCH","MEETING")
            NOT NULL DEFAULT "OFFLINE" AFTER `is_logged_in`');
}

// ── Default options (safe: add_option skips if already exists) ────────────────
add_option('salesos_ami_host',           '103.42.4.210');
add_option('salesos_ami_port',           '5038');
add_option('salesos_ami_username',       'crm-api');
add_option('salesos_ami_secret',         'CRM_AMI_S3cr3t#2024');
add_option('salesos_pbx_db_host',        '127.0.0.1');
add_option('salesos_pbx_db_port',        '3307');
add_option('salesos_pbx_db_name',        'asteriskcdrdb');
add_option('salesos_pbx_db_user',        'asterisk');
add_option('salesos_pbx_db_password',    'Ast3r!skDB#2024');
add_option('salesos_recordings_url',     'http://103.42.4.210:8089');
add_option('salesos_recordings_path',    '/var/spool/asterisk/recording/');
add_option('salesos_poll_interval',      '5');
add_option('salesos_popup_enabled',      '1');
add_option('salesos_cdr_sync_enabled',   '1');
add_option('salesos_cdr_batch_size',     '100');
add_option('salesos_outbound_prefix',    '');
add_option('salesos_caller_id',          '09649699699');
add_option('salesos_trunk_endpoint',     'endpoint-trunk-bdit');
add_option('salesos_timeline_enabled',   '1');
add_option('salesos_phone_index_enabled','1');
// Real-time layer options
add_option('salesos_rt_enabled',         '1');
add_option('salesos_redis_host',         '127.0.0.1');
add_option('salesos_redis_port',         '6379');
add_option('salesos_ws_url',             '');
add_option('salesos_ws_internal_port',   '8080');
add_option('salesos_stream_maxlen',      '50000');
add_option('salesos_pbx_id',             'pbx-01');
add_option('salesos_pbx_name',           'Main PBX');
