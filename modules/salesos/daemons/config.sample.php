<?php
/**
 * SalesOS Daemon Configuration
 *
 * Copy to config.php and fill in your values.
 * This file is read by ami_consumer.php, event_archiver.php, and ws_server.php.
 * Regenerated automatically by the SalesOS Settings page.
 */
return [
    // ── CRM Database (MySQL) ──────────────────────────────────────────────────
    'db_host'   => '127.0.0.1',
    'db_port'   => 3306,
    'db_name'   => 'your_crm_db',
    'db_user'   => 'your_crm_user',
    'db_pass'   => 'your_crm_password',
    'db_prefix' => 'tbl_',         // Perfex CRM table prefix

    // ── Redis (local to CRM server) ───────────────────────────────────────────
    'redis_host'     => '127.0.0.1',
    'redis_port'     => 6379,
    'redis_password' => '',         // leave empty if no auth

    // ── Asterisk AMI ──────────────────────────────────────────────────────────
    'ami_host'   => '103.42.4.210',
    'ami_port'   => 5038,
    'ami_user'   => 'crm-api',
    'ami_secret' => 'CRM_AMI_S3cr3t#2024',

    // ── PBX Identity (written to every stream entry) ──────────────────────────
    'pbx_id'   => 'pbx-01',
    'pbx_name' => 'Main PBX',

    // ── WebSocket Server ──────────────────────────────────────────────────────
    'ws_port' => 8080,

    // ── Stream limits ─────────────────────────────────────────────────────────
    'stream_maxlen' => 50000,       // approximate MAXLEN per stream
];
