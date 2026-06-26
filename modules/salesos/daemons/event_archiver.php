#!/usr/bin/env php
<?php
/**
 * SalesOS Event Archiver Daemon
 *
 * Reads from all salesos Redis Streams using consumer group "archiver"
 * and batch-inserts rows into MySQL salesos_call_events. XACKs only after
 * a successful DB commit — safe for crash recovery and redelivery.
 *
 * The UNIQUE KEY on stream_id makes every INSERT idempotent:
 * redelivered entries produce a duplicate-key error that is silently ignored.
 *
 * Usage:
 *   php event_archiver.php [--config=/path/to/config.php]
 */

declare(ticks=1);

// ── Bootstrap ─────────────────────────────────────────────────────────────────

$cfg_path = __DIR__ . '/config.php';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--config=')) {
        $cfg_path = substr($arg, 9);
    }
}

if (!is_file($cfg_path)) {
    fwrite(STDERR, "[salesos-archiver] Config not found: {$cfg_path}\n");
    exit(1);
}

$cfg = require $cfg_path;

// ── Signal handling ────────────────────────────────────────────────────────────

$running = true;
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, static function () use (&$running): void { $running = false; });
    pcntl_signal(SIGINT,  static function () use (&$running): void { $running = false; });
}

// ── Logging ───────────────────────────────────────────────────────────────────

$log = static function (string $lvl, string $msg): void {
    echo sprintf('[%s] [salesos-archiver] [%s] %s', date('Y-m-d H:i:s'), strtoupper($lvl), $msg) . "\n";
};

// ── Redis ─────────────────────────────────────────────────────────────────────

function arc_redis(array $cfg, callable $log): ?Redis
{
    if (!extension_loaded('redis')) {
        $log('error', 'phpredis not loaded');
        return null;
    }
    try {
        $r = new Redis();
        $r->connect($cfg['redis_host'], (int) $cfg['redis_port'], 3.0);
        if (!empty($cfg['redis_password'])) {
            $r->auth($cfg['redis_password']);
        }
        return $r;
    } catch (Exception $e) {
        $log('error', 'Redis: ' . $e->getMessage());
        return null;
    }
}

// ── Database ──────────────────────────────────────────────────────────────────

function arc_db(array $cfg, callable $log): ?PDO
{
    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $cfg['db_host'], (int) $cfg['db_port'], $cfg['db_name']
        );
        $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        $log('error', 'DB: ' . $e->getMessage());
        return null;
    }
}

// ── Stream → DB row mapper ────────────────────────────────────────────────────

function arc_map_row(string $stream_id, array $fields, string $pfx): array
{
    $event_type = $fields['event_type'] ?? '';
    $prefix     = explode('.', $event_type)[0];

    // Determine event category for payload compression
    $payload_keys = match ($prefix) {
        'call'      => ['src','dst','channel','bridge_id','hangup_cause','hangup_txt',
                        'dial_status','dial_dst','transfer_exten','transfer_result',
                        'hold_duration_sec','transferee_channel','transfer_target'],
        'agent'     => ['device','device_state','peer','peer_status'],
        'queue'     => ['callerid','position','original_position','hold_time','talk_time',
                        'reason','member_name','status_code','status_text','count','ring_time'],
        'recording' => ['recording_file','recording_state','mute_direction'],
        default     => [],
    };

    $payload = [];
    foreach ($payload_keys as $k) {
        if (isset($fields[$k]) && $fields[$k] !== '') {
            $payload[$k] = $fields[$k];
        }
    }
    // Always include pbx identity in payload
    $payload['pbx_id']   = $fields['pbx_id']   ?? '';
    $payload['pbx_name'] = $fields['pbx_name']  ?? '';

    return [
        'stream_id'       => $stream_id,
        'schema_ver'      => (int) ($fields['schema_ver']      ?? 1),
        'event_type'      => $event_type,
        'pbx_id'          => $fields['pbx_id']          ?? null,
        'pbx_name'        => $fields['pbx_name']         ?? null,
        'session_id'      => $fields['session_id']       ?? null,
        'conversation_id' => $fields['conversation_id']  ?? null,
        'call_uniqueid'   => $fields['call_uid']         ?? '',
        'call_id'         => null,  // FK filled later by CDR sync
        'from_state'      => $fields['from_state']       ?: null,
        'to_state'        => $fields['to_state']         ?: null,
        'direction'       => $fields['direction']         ?: null,
        'queue_name'      => $fields['queue_name']       ?: null,
        'agent_id'        => ($fields['agent_id'] ?? '') !== '' ? (int) $fields['agent_id'] : null,
        'staff_id'        => ($fields['staff_id'] ?? '') !== '' ? (int) $fields['staff_id'] : null,
        'entity_type'     => $fields['entity_type']      ?: null,
        'entity_id'       => ($fields['entity_id'] ?? '') !== '' ? (int) $fields['entity_id'] : null,
        'hold_seq'        => (int) ($fields['hold_seq']  ?? 0),
        'xfer_seq'        => (int) ($fields['xfer_seq']  ?? 0),
        'payload'         => !empty($payload) ? json_encode($payload) : null,
    ];
}

// ── Batch insert ──────────────────────────────────────────────────────────────

function arc_insert_batch(PDO $pdo, string $pfx, array $rows, callable $log): int
{
    if (empty($rows)) return 0;

    $table = $pfx . 'salesos_call_events';

    $cols = [
        'stream_id','schema_ver','event_type','pbx_id','pbx_name',
        'session_id','conversation_id','call_uniqueid','call_id',
        'from_state','to_state','direction','queue_name',
        'agent_id','staff_id','entity_type','entity_id',
        'hold_seq','xfer_seq','payload',
    ];

    $placeholders = implode(',', array_fill(0, count($cols), '?'));
    $row_ph       = '(' . $placeholders . ')';
    $all_ph       = implode(',', array_fill(0, count($rows), $row_ph));

    // INSERT IGNORE makes it idempotent (duplicate stream_id = silently skipped)
    $sql = "INSERT IGNORE INTO `{$table}` (`" . implode('`,`', $cols) . "`) VALUES {$all_ph}";

    $values = [];
    foreach ($rows as $row) {
        foreach ($cols as $col) {
            $values[] = $row[$col] ?? null;
        }
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        $inserted = $stmt->rowCount();
        $pdo->commit();
        return $inserted;
    } catch (PDOException $e) {
        $pdo->rollBack();
        $log('error', 'INSERT batch failed: ' . $e->getMessage());
        return 0;
    }
}

// ── Main ──────────────────────────────────────────────────────────────────────

$log('info', 'Starting event archiver.');

$redis = arc_redis($cfg, $log);
if (!$redis) { fwrite(STDERR, "[salesos-archiver] Redis unavailable.\n"); exit(1); }

$pdo = arc_db($cfg, $log);
if (!$pdo) { fwrite(STDERR, "[salesos-archiver] DB unavailable.\n"); exit(1); }

$pfx = $cfg['db_prefix'];

$streams = [
    'salesos:stream:calls',
    'salesos:stream:agents',
    'salesos:stream:queues',
    'salesos:stream:recordings',
];

// Heartbeat interval: flush every 500ms or when batch >= 50 entries
$batch_rows      = [];
$batch_ids       = [];   // stream → [ids to ACK]
$last_flush      = microtime(true);
$last_heartbeat  = 0;
$consumer        = 'archiver-' . gethostname() . '-' . getmypid();
$lock_key        = 'salesos:lock:event_archiver';

$log('info', "Consumer name: {$consumer}");

while ($running) {
    // Heartbeat every 10s so the health endpoint can detect us
    if (time() - $last_heartbeat >= 10) {
        $redis->set($lock_key, getmypid() . '@' . gethostname(), ['EX' => 60]);
        $last_heartbeat = time();
    }

    // ── Read from each stream ────────────────────────────────────────────
    foreach ($streams as $stream) {
        try {
            $result = $redis->xReadGroup('archiver', $consumer, [$stream => '>'], 50, null);
        } catch (Exception $e) {
            $log('warn', "XREADGROUP {$stream}: " . $e->getMessage());
            sleep(1);
            continue;
        }

        if (empty($result[$stream])) continue;

        foreach ($result[$stream] as $sid => $fields) {
            $row = arc_map_row($sid, $fields, $pfx);
            $batch_rows[]         = $row;
            $batch_ids[$stream][] = $sid;
        }
    }

    // ── Flush when batch full or interval elapsed ─────────────────────────
    $now     = microtime(true);
    $elapsed = $now - $last_flush;

    if (count($batch_rows) >= 50 || ($elapsed >= 0.5 && !empty($batch_rows))) {
        $inserted = arc_insert_batch($pdo, $pfx, $batch_rows, $log);

        // ACK all entries regardless of duplicate status (INSERT IGNORE handles that)
        foreach ($batch_ids as $stream => $ids) {
            try {
                $redis->xAck($stream, 'archiver', $ids);
            } catch (Exception $e) {
                $log('warn', "XACK {$stream}: " . $e->getMessage());
            }
        }

        $log('info', sprintf(
            'Flushed %d entries → %d inserted (%s)',
            count($batch_rows), $inserted,
            implode(', ', array_map(fn($s, $ids) => substr($s, 16) . ':' . count($ids), array_keys($batch_ids), $batch_ids))
        ));

        $batch_rows = [];
        $batch_ids  = [];
        $last_flush = $now;
    } elseif (empty($batch_rows)) {
        // Nothing to do — sleep briefly to avoid busy-wait
        usleep(100000);  // 100ms
    }
}

// ── Shutdown: flush remaining ──────────────────────────────────────────────────

if (!empty($batch_rows)) {
    $log('info', 'Final flush on shutdown.');
    arc_insert_batch($pdo, $pfx, $batch_rows, $log);
    foreach ($batch_ids as $stream => $ids) {
        try { $redis->xAck($stream, 'archiver', $ids); } catch (Exception $e) {}
    }
}

$log('info', 'Archiver stopped.');
