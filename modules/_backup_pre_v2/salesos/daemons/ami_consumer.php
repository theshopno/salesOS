#!/usr/bin/env php
<?php
/**
 * SalesOS AMI Consumer Daemon
 *
 * Maintains a persistent TCP connection to Asterisk AMI, normalizes events
 * through the FSM, enriches with phone→entity lookup, and publishes to
 * Redis Streams. Daemon is managed by systemd (salesos-ami.service).
 *
 * Usage:
 *   php ami_consumer.php [--config=/path/to/config.php]
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
    fwrite(STDERR, "[salesos-ami] Config not found: {$cfg_path}\n");
    exit(1);
}

$cfg = require $cfg_path;

require_once __DIR__ . '/../libraries/Call_state_machine.php';
require_once __DIR__ . '/../libraries/Ami_event_parser.php';

// ── Signal handling ────────────────────────────────────────────────────────────

$running = true;
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, static function () use (&$running): void { $running = false; });
    pcntl_signal(SIGINT,  static function () use (&$running): void { $running = false; });
}

// ── Logging ───────────────────────────────────────────────────────────────────

$log = static function (string $lvl, string $msg): void {
    $line = sprintf('[%s] [salesos-ami] [%s] %s', date('Y-m-d H:i:s'), strtoupper($lvl), $msg);
    echo $line . "\n";
    // systemd captures stdout; journalctl -u salesos-ami shows it
};

// ── Redis ─────────────────────────────────────────────────────────────────────

function r_connect(array $cfg, callable $log): ?Redis
{
    if (!extension_loaded('redis')) {
        $log('error', 'phpredis extension not loaded (dnf install php-pecl-redis)');
        return null;
    }
    try {
        $r = new Redis();
        $r->connect($cfg['redis_host'], (int) $cfg['redis_port'], 3.0);
        if (!empty($cfg['redis_password'])) {
            $r->auth($cfg['redis_password']);
        }
        $r->setOption(Redis::OPT_PREFIX, '');
        return $r;
    } catch (Exception $e) {
        $log('error', 'Redis connect failed: ' . $e->getMessage());
        return null;
    }
}

function r_ensure_groups(Redis $r, callable $log): void
{
    $streams = [
        'salesos:stream:calls',
        'salesos:stream:agents',
        'salesos:stream:queues',
        'salesos:stream:recordings',
        'salesos:stream:webrtc',
    ];
    // All consumer groups registered now, including future AI consumers.
    $groups = ['ws-delivery', 'archiver', 'ai-transcription', 'ai-analysis', 'ai-coaching'];

    foreach ($streams as $stream) {
        foreach ($groups as $group) {
            try {
                $r->rawCommand('XGROUP', 'CREATE', $stream, $group, '0', 'MKSTREAM');
            } catch (Exception $e) {
                // BUSYGROUP = already exists; anything else is a real problem
                if (strpos($e->getMessage(), 'BUSYGROUP') === false) {
                    $log('warn', "XGROUP CREATE {$stream}/{$group}: " . $e->getMessage());
                }
            }
        }
    }
}

// ── Database (PDO) ────────────────────────────────────────────────────────────

function db_connect(array $cfg, callable $log): ?PDO
{
    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $cfg['db_host'], (int) $cfg['db_port'], $cfg['db_name']
        );
        return new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 5,
        ]);
    } catch (PDOException $e) {
        $log('error', 'DB connect failed: ' . $e->getMessage());
        return null;
    }
}

function db_load_ext_map(PDO $pdo, string $pfx): array
{
    $rows = $pdo->query(
        "SELECT id, extension, staff_id, fullname FROM {$pfx}salesos_agents WHERE is_active=1"
    )->fetchAll();

    $map = [];
    foreach ($rows as $row) {
        $map[$row['extension']] = $row;
    }
    return $map;
}

// ── Phone lookup (Redis pcache → phone_index → direct tables) ────────────────

function phone_normalize(string $phone): string
{
    $d = preg_replace('/\D/', '', $phone);
    if (strlen($d) > 11 && str_starts_with($d, '880')) {
        $d = substr($d, 3);
    }
    return $d;
}

function phone_variants(string $normalized): array
{
    if (empty($normalized)) return [];
    $core = ltrim($normalized, '0');
    $core = preg_replace('/^880/', '', $core);
    $core = ltrim($core, '0');
    return array_unique(array_filter([
        $normalized, '0' . $core, '880' . $core, '+880' . $core, $core,
    ]));
}

function phone_lookup(string $phone, Redis $r, PDO $pdo, string $pfx): ?array
{
    $norm   = phone_normalize($phone);
    if (empty($norm)) return null;

    $ckey   = 'salesos:pcache:' . $norm;
    $cached = $r->get($ckey);

    if ($cached !== false) {
        $data = json_decode($cached, true);
        return (!empty($data['miss'])) ? null : $data;
    }

    // DB lookup
    $result = null;
    foreach (phone_variants($norm) as $v) {
        $st = $pdo->prepare("SELECT entity_type, entity_id, entity_name FROM {$pfx}salesos_phone_index WHERE phone=? LIMIT 1");
        $st->execute([$v]);
        $row = $st->fetch();
        if ($row) { $result = $row; break; }
    }

    if (!$result) {
        foreach (phone_variants($norm) as $v) {
            $st = $pdo->prepare("SELECT id, `name` FROM {$pfx}leads WHERE phonenumber=? LIMIT 1");
            $st->execute([$v]);
            $row = $st->fetch();
            if ($row) { $result = ['entity_type'=>'lead','entity_id'=>(int)$row['id'],'entity_name'=>$row['name']]; break; }
        }
    }

    if (!$result) {
        foreach (phone_variants($norm) as $v) {
            $st = $pdo->prepare("SELECT id, firstname, lastname FROM {$pfx}contacts WHERE phonenumber=? LIMIT 1");
            $st->execute([$v]);
            $row = $st->fetch();
            if ($row) {
                $result = ['entity_type'=>'contact','entity_id'=>(int)$row['id'],
                           'entity_name'=>trim($row['firstname'].' '.$row['lastname'])];
                break;
            }
        }
    }

    if (!$result) {
        foreach (phone_variants($norm) as $v) {
            $st = $pdo->prepare("SELECT userid, company FROM {$pfx}clients WHERE phonenumber=? LIMIT 1");
            $st->execute([$v]);
            $row = $st->fetch();
            if ($row) { $result = ['entity_type'=>'client','entity_id'=>(int)$row['userid'],'entity_name'=>$row['company']]; break; }
        }
    }

    if ($result) {
        $result['matched_at'] = time();
        $result['miss']       = false;
        $r->setEx($ckey, 3600, json_encode($result));
        $idx = "salesos:pcache:idx:{$result['entity_type']}:{$result['entity_id']}";
        $r->sAdd($idx, $norm);
        $r->expire($idx, 3600);
    } else {
        $r->setEx($ckey, 300, json_encode(['miss'=>true,'checked_at'=>time()]));
    }

    return $result ?: null;
}

// ── Redis state updaters ──────────────────────────────────────────────────────

function state_update_call(Redis $r, array $ev, int $maxlen): void
{
    $uid = $ev['call_uid'] ?? '';
    if (empty($uid)) return;

    $hk    = "salesos:call:{$uid}";
    $event = $ev['event_type'];

    if ($event === 'call.ringing') {
        $fields = [
            'state'           => 'RINGING',
            'direction'       => $ev['direction']       ?? 'unknown',
            'src'             => $ev['src']              ?? '',
            'dst'             => $ev['dst']              ?? '',
            'channel'         => $ev['channel']          ?? '',
            'session_id'      => $ev['session_id']      ?? $uid,
            'conversation_id' => $ev['conversation_id'] ?? $uid,
            'call_id'         => '',
            'agent_id'        => $ev['agent_id']         ?? '',
            'agent_ext'       => $ev['agent_ext']        ?? '',
            'agent_name'      => $ev['agent_name']       ?? '',
            'staff_id'        => $ev['staff_id']         ?? '',
            'entity_type'     => $ev['entity_type']      ?? '',
            'entity_id'       => $ev['entity_id']        ?? '',
            'entity_name'     => $ev['entity_name']      ?? '',
            'bridge_id'       => '',
            'queue_name'      => '',
            'hold_count'      => '0',
            'hold_total_sec'  => '0',
            'held_since'      => '0',
            'xfer_count'      => '0',
            'recording_file'  => '',
            'pbx_id'          => $ev['pbx_id']           ?? '',
            'pbx_name'        => $ev['pbx_name']         ?? '',
            'started_at'      => (string) time(),
            'answered_at'     => '',
            'last_event_ts'   => $ev['ts']               ?? '',
            'stream_id'       => $ev['_stream_id']       ?? '',
        ];
        $r->hMSet($hk, $fields);
        $r->expire($hk, 14400);

        $r->hSet('salesos:calls', $uid, json_encode([
            'state'       => 'RINGING',
            'direction'   => $fields['direction'],
            'src'         => $fields['src'],
            'dst'         => $fields['dst'],
            'agent_ext'   => $fields['agent_ext'],
            'entity_name' => $fields['entity_name'],
            'started_at'  => $fields['started_at'],
            'pbx_id'      => $fields['pbx_id'],
        ]));
        return;
    }

    // Update state field and timestamp for all other call events
    $updates = ['last_event_ts' => $ev['ts'] ?? ''];

    switch ($event) {
        case 'call.dialing':
            $updates['state'] = 'DIALING';
            break;

        case 'call.answered':
        case 'call.bridged':
            $updates['state']       = 'BRIDGED';
            $updates['answered_at'] = (string) time();
            if (!empty($ev['bridge_id'])) {
                $updates['bridge_id'] = $ev['bridge_id'];
            }
            break;

        case 'call.on_hold':
            $updates['state']      = 'ON_HOLD';
            $updates['held_since'] = (string) time();
            $r->hIncrBy($hk, 'hold_count', 1);
            break;

        case 'call.unhold':
            $held = (int) ($r->hGet($hk, 'held_since') ?: time());
            $dur  = max(0, time() - $held);
            $updates['state'] = 'BRIDGED';
            $r->hIncrBy($hk, 'hold_total_sec', $dur);
            break;

        case 'call.blind_transfer':
            $updates['state'] = 'TRANSFERRING';
            $r->hIncrBy($hk, 'xfer_count', 1);
            break;

        case 'call.consult_started':
            $updates['state'] = 'CONSULT';
            break;

        case 'call.attended_transfer':
            $updates['state'] = 'TRANSFERRED';
            $r->hDel('salesos:calls', $uid);
            break;

        case 'call.consult_cancelled':
            $updates['state'] = 'BRIDGED';
            break;

        case 'call.unbridged':
            $updates['state'] = 'UNBRIDGED';
            break;

        case 'call.ended':
        case 'call.failed':
            $updates['state'] = $event === 'call.ended' ? 'ENDED' : 'FAILED';
            $r->hDel('salesos:calls', $uid);
            break;

        case 'recording.started':
        case 'recording.stopped':
            if (!empty($ev['recording_file'])) {
                $updates['recording_file'] = $ev['recording_file'];
            }
            break;
    }

    if (!empty($updates)) {
        $r->hMSet($hk, $updates);

        // Reflect state in active calls summary hash
        $summary_raw = $r->hGet('salesos:calls', $uid);
        if ($summary_raw !== false) {
            $summary = json_decode($summary_raw, true) ?? [];
            if (isset($updates['state'])) {
                $summary['state'] = $updates['state'];
                $r->hSet('salesos:calls', $uid, json_encode($summary));
            }
        }
    }
}

function state_update_agent(Redis $r, array $ev): void
{
    $ext = $ev['agent_ext'] ?? '';
    if (empty($ext)) return;

    $presence_map = [
        'agent.online'  => 'ONLINE',
        'agent.offline' => 'OFFLINE',
        'agent.ready'   => 'READY',
        'agent.busy'    => 'BUSY',
        'agent.ringing' => 'RINGING',
        'agent.paused'  => 'PAUSED',
        'agent.wrapup'  => 'WRAPUP',
        'agent.break'   => 'BREAK',
        'agent.lunch'   => 'LUNCH',
        'agent.meeting' => 'MEETING',
    ];

    $presence = $presence_map[$ev['event_type']] ?? null;
    if ($presence === null) return;

    $sk = "salesos:agent:{$ext}:state";
    $r->hMSet($sk, [
        'presence'   => $presence,
        'agent_id'   => $ev['agent_id']   ?? '',
        'agent_ext'  => $ext,
        'staff_id'   => $ev['staff_id']   ?? '',
        'agent_name' => $ev['agent_name'] ?? '',
        'pbx_id'     => $ev['pbx_id']     ?? '',
        'updated_at' => (string) time(),
    ]);
    $r->expire($sk, 300);

    $r->hSet('salesos:agents', $ext, json_encode([
        'presence'   => $presence,
        'agent_id'   => $ev['agent_id']   ?? '',
        'staff_id'   => $ev['staff_id']   ?? '',
        'agent_name' => $ev['agent_name'] ?? '',
        'pbx_id'     => $ev['pbx_id']     ?? '',
        'updated_at' => time(),
    ]));
}

function state_update_queue(Redis $r, array $ev): void
{
    $queue = $ev['queue_name'] ?? '';
    if (empty($queue)) return;

    $hk = "salesos:queue:{$queue}";

    switch ($ev['event_type']) {
        case 'queue.caller_join':
            $r->hIncrBy($hk, 'callers_waiting', 1);
            $r->hSet("salesos:queue:{$queue}:callers", $ev['call_uid'] ?? '', json_encode([
                'position'  => $ev['position'] ?? 0,
                'callerid'  => $ev['callerid'] ?? '',
                'joined_at' => time(),
            ]));
            break;

        case 'queue.caller_leave':
            $wait = _queue_calc_wait($r, $queue, $ev['call_uid'] ?? '');
            if ($wait > 0) {
                // Running avg wait time
                $completed = max(1, (int) $r->hGet($hk, 'completed_today'));
                $curr_avg  = (int) $r->hGet($hk, 'avg_wait_sec');
                $r->hSet($hk, 'avg_wait_sec', (int) round(($curr_avg * ($completed - 1) + $wait) / $completed));
            }
            $r->hIncrBy($hk, 'callers_waiting', -1);
            $r->hDel("salesos:queue:{$queue}:callers", $ev['call_uid'] ?? '');
            break;

        case 'queue.caller_abandon':
            $r->hIncrBy($hk, 'abandoned_today', 1);
            $r->hIncrBy($hk, 'callers_waiting', -1);
            $r->hDel("salesos:queue:{$queue}:callers", $ev['call_uid'] ?? '');
            break;

        case 'queue.agent_connect':
            $r->hIncrBy($hk, 'members_busy', 1);
            $r->hIncrBy($hk, 'members_available', -1);
            break;

        case 'queue.agent_complete':
            $r->hIncrBy($hk, 'completed_today', 1);
            $r->hIncrBy($hk, 'members_busy', -1);
            $r->hIncrBy($hk, 'members_available', 1);
            $talk = (int) ($ev['talk_time'] ?? 0);
            if ($talk > 0) {
                $completed = max(1, (int) $r->hGet($hk, 'completed_today'));
                $curr_avg  = (int) $r->hGet($hk, 'avg_talk_sec');
                $new_avg   = (int) round(($curr_avg * ($completed - 1) + $talk) / $completed);
                $r->hSet($hk, 'avg_talk_sec', $new_avg);
            }
            _queue_recalc_sla($r, $hk);
            break;

        case 'queue.member_paused':
            $r->hIncrBy($hk, 'members_paused', 1);
            $r->hIncrBy($hk, 'members_available', -1);
            break;

        case 'queue.member_unpaused':
            $r->hIncrBy($hk, 'members_paused', -1);
            $r->hIncrBy($hk, 'members_available', 1);
            break;
    }

    // Sync summary hash
    $r->hSet('salesos:queues', $queue, json_encode([
        'name'             => $queue,
        'callers_waiting'  => max(0, (int) $r->hGet($hk, 'callers_waiting')),
        'members_available'=> max(0, (int) $r->hGet($hk, 'members_available')),
        'abandoned_today'  => (int) $r->hGet($hk, 'abandoned_today'),
        'completed_today'  => (int) $r->hGet($hk, 'completed_today'),
        'avg_wait_sec'     => (int) $r->hGet($hk, 'avg_wait_sec'),
        'avg_talk_sec'     => (int) $r->hGet($hk, 'avg_talk_sec'),
        'sla_percent'      => (int) $r->hGet($hk, 'sla_percent'),
    ]));
}

function _queue_calc_wait(Redis $r, string $queue, string $uid): int
{
    if (empty($uid)) return 0;
    $caller_raw = $r->hGet("salesos:queue:{$queue}:callers", $uid);
    if (!$caller_raw) return 0;
    $caller = json_decode($caller_raw, true);
    return max(0, time() - (int) ($caller['joined_at'] ?? time()));
}

function _queue_recalc_sla(Redis $r, string $hk, int $threshold_sec = 20): void
{
    $completed = (int) $r->hGet($hk, 'completed_today');
    if ($completed === 0) return;
    $avg_wait = (int) $r->hGet($hk, 'avg_wait_sec');
    // Approximation: if avg wait <= threshold, SLA is likely met
    $sla = ($avg_wait <= $threshold_sec) ? 100 : max(0, (int) round(100 * $threshold_sec / max(1, $avg_wait)));
    $r->hSet($hk, 'sla_percent', $sla);
}

// ── AMI socket I/O ────────────────────────────────────────────────────────────

function ami_open(array $cfg, callable $log): mixed
{
    $sock = @fsockopen($cfg['ami_host'], (int) $cfg['ami_port'], $errno, $errstr, 5);
    if (!$sock) {
        $log('warn', "AMI fsockopen failed: {$errstr} ({$errno})");
        return false;
    }

    stream_set_blocking($sock, false);
    stream_set_timeout($sock, 30);

    // Read banner (up to 2s)
    $banner  = '';
    $dl      = microtime(true) + 2;
    while (microtime(true) < $dl) {
        $l = fgets($sock, 256);
        if ($l) { $banner = $l; break; }
        usleep(20000);
    }

    if (strpos($banner, 'Asterisk Call Manager') === false) {
        $log('warn', 'Not AMI: ' . trim($banner));
        fclose($sock);
        return false;
    }

    // Login
    $login_pkt = "Action: Login\r\nUsername: {$cfg['ami_user']}\r\nSecret: {$cfg['ami_secret']}\r\n\r\n";
    fwrite($sock, $login_pkt);

    $resp = ami_read_pkt($sock, 5.0);
    if (($resp['Response'] ?? '') !== 'Success') {
        $log('error', 'AMI login failed: ' . ($resp['Message'] ?? 'unknown'));
        fclose($sock);
        return false;
    }

    return $sock;
}

function ami_read_pkt(mixed $sock, float $timeout): array
{
    $pkt = [];
    $dl  = microtime(true) + $timeout;

    while (microtime(true) < $dl) {
        $line = @fgets($sock, 4096);
        if ($line === false) {
            usleep(3000);
            continue;
        }
        $line = rtrim($line, "\r\n");
        if ($line === '') break;

        $pos = strpos($line, ': ');
        if ($pos !== false) {
            $pkt[substr($line, 0, $pos)] = substr($line, $pos + 2);
        }
    }

    return $pkt;
}

// ── Main ──────────────────────────────────────────────────────────────────────

$log('info', "Starting. PBX={$cfg['ami_host']}:{$cfg['ami_port']}");

$redis = r_connect($cfg, $log);
if (!$redis) { fwrite(STDERR, "[salesos-ami] Redis unavailable. Exiting.\n"); exit(1); }

$pdo = db_connect($cfg, $log);
if (!$pdo) { fwrite(STDERR, "[salesos-ami] DB unavailable. Exiting.\n"); exit(1); }

// Singleton lock (NX = set only if not exists)
$lock_key = 'salesos:lock:ami_consumer';
if (!$redis->set($lock_key, getmypid() . '@' . gethostname(), ['NX', 'EX' => 30])) {
    fwrite(STDERR, "[salesos-ami] Another instance running (lock {$lock_key} held). Exiting.\n");
    exit(1);
}

r_ensure_groups($redis, $log);

$pfx    = $cfg['db_prefix'];
$ext_map= db_load_ext_map($pdo, $pfx);
$parser = new Ami_event_parser($cfg['pbx_id'], $cfg['pbx_name']);
$parser->set_extension_map($ext_map);

$log('info', 'Extension map: ' . count($ext_map) . ' active agents');

$reconnect_delay  = 1;
$sock             = false;
$last_ping        = time();
$last_ext_reload  = time();
$last_lock_renew  = time();
$buf              = '';

while ($running) {
    // Keep daemon lock alive — every 10s is enough (TTL=30s)
    if (time() - $last_lock_renew >= 10) {
        $redis->expire($lock_key, 30);
        $last_lock_renew = time();
    }

    // ── Connect / reconnect ────────────────────────────────────────────────
    if ($sock === false) {
        $log('info', "Connecting to AMI...");
        $sock = ami_open($cfg, $log);

        if ($sock === false) {
            $redis->publish('salesos:notify', json_encode([
                'type'  => 'notify.daemon_reconnecting',
                'level' => 'warning',
                'msg'   => "AMI reconnecting in {$reconnect_delay}s",
            ]));
            sleep($reconnect_delay);
            $reconnect_delay = min($reconnect_delay * 2, 30);
            continue;
        }

        $reconnect_delay = 1;
        $last_ping = time();
        $log('info', 'AMI connected.');
        $redis->publish('salesos:notify', json_encode([
            'type'  => 'notify.daemon_up',
            'level' => 'success',
            'msg'   => 'AMI Consumer connected to ' . $cfg['ami_host'],
        ]));
    }

    // ── Reload extension map every 5 min ───────────────────────────────────
    if (time() - $last_ext_reload > 300) {
        try {
            $ext_map = db_load_ext_map($pdo, $pfx);
            $parser->set_extension_map($ext_map);
            $last_ext_reload = time();
        } catch (Exception $e) {
            $log('warn', 'Extension map reload failed: ' . $e->getMessage());
        }
    }

    // ── Keepalive Ping every 30s ───────────────────────────────────────────
    if (time() - $last_ping > 30) {
        if (@fwrite($sock, "Action: Ping\r\n\r\n") === false) {
            $log('warn', 'Ping write failed — reconnecting');
            @fclose($sock);
            $sock = false;
            continue;
        }
        $last_ping = time();
    }

    // ── Read one line ──────────────────────────────────────────────────────
    $line = @fgets($sock, 4096);

    if ($line === false) {
        $meta = stream_get_meta_data($sock);
        if (!empty($meta['eof'])) {
            $log('warn', 'AMI EOF — reconnecting');
            @fclose($sock);
            $sock = false;
        }
        usleep(3000);
        continue;
    }

    $line = rtrim($line, "\r\n");

    if ($line !== '') {
        $buf .= $line . "\n";
        continue;
    }

    // ── Empty line = end of packet ─────────────────────────────────────────
    if (empty(trim($buf))) {
        $buf = '';
        continue;
    }

    $pkt = [];
    foreach (explode("\n", trim($buf)) as $raw) {
        $raw = trim($raw);
        $pos = strpos($raw, ': ');
        if ($pos !== false) {
            $pkt[substr($raw, 0, $pos)] = substr($raw, $pos + 2);
        }
    }
    $buf = '';

    if (empty($pkt['Event'])) {
        continue;  // Response packets (Login, Ping, etc.) — skip
    }

    // ── Entity enrichment (phone cache lookup on inbound src) ──────────────
    $uid = $pkt['Uniqueid'] ?? '';
    if ($uid) {
        // Sync parser's in-memory state from Redis hash
        $call_hash = $redis->hGetAll("salesos:call:{$uid}") ?: [];
        if ($call_hash) {
            $parser->sync_call_state($uid, $call_hash);
        }

        // Try to enrich entity if not yet known
        if (empty($call_hash['entity_type'])) {
            $src = $pkt['CallerIDNum'] ?? '';
            if ($src && strlen($src) > 4) {
                $entity = phone_lookup($src, $redis, $pdo, $pfx);
                if ($entity) {
                    $redis->hMSet("salesos:call:{$uid}", [
                        'entity_type' => $entity['entity_type'],
                        'entity_id'   => (string) $entity['entity_id'],
                        'entity_name' => $entity['entity_name'],
                    ]);
                    $call_hash = array_merge($call_hash, [
                        'entity_type' => $entity['entity_type'],
                        'entity_id'   => (string) $entity['entity_id'],
                        'entity_name' => $entity['entity_name'],
                    ]);
                    $parser->sync_call_state($uid, $call_hash);
                }
            }
        }
    }

    // ── Parse and publish ──────────────────────────────────────────────────
    $event = $parser->parse($pkt);
    if (!$event) continue;

    // FSM guard
    $from_state  = $call_hash['state'] ?? '';
    $new_state   = Call_state_machine::transition($from_state, $event['event_type']);
    if ($new_state !== null) {
        $event['to_state'] = $new_state;
    } elseif (!empty($from_state) && str_starts_with($event['event_type'], 'call.')) {
        // Invalid transition — log but still publish the raw event for visibility
        $log('warn', "FSM: invalid transition from={$from_state} on={$event['event_type']}");
    }

    // Standard versioning envelope — every event carries these fields
    $event['version']   = '1';
    $event['timestamp'] = date('c');
    $event['source']    = 'asterisk';
    // pbx_id and session_id are already set by the parser

    // XADD
    $stream = Ami_event_parser::stream_for($event['event_type']);
    try {
        $sid = $redis->xAdd($stream, '*', $event, (int) $cfg['stream_maxlen'], true);
        if ($sid) {
            $event['_stream_id'] = $sid;
            if ($uid) {
                $redis->hSet("salesos:call:{$uid}", 'stream_id', $sid);
            }
            $log('debug', "XADD {$event['event_type']} uid={$uid} sid={$sid}");
        }
    } catch (Exception $e) {
        $log('error', 'XADD failed: ' . $e->getMessage());
    }

    // Update Redis state hashes
    $prefix_char = explode('.', $event['event_type'])[0];
    match ($prefix_char) {
        'call'      => state_update_call($redis, $event, (int) $cfg['stream_maxlen']),
        'agent'     => state_update_agent($redis, $event),
        'queue'     => state_update_queue($redis, $event),
        'recording' => state_update_call($redis, $event, (int) $cfg['stream_maxlen']),
        default     => null,
    };

    // Drop in-memory state for terminal calls
    if (in_array($event['event_type'], ['call.ended', 'call.failed', 'call.attended_transfer'], true)) {
        $parser->drop_call_state($uid);
    }
}

// ── Shutdown ──────────────────────────────────────────────────────────────────

$log('info', 'Shutting down.');
$redis->del($lock_key);
if ($sock) {
    @fwrite($sock, "Action: Logoff\r\n\r\n");
    @fclose($sock);
}
$redis->publish('salesos:notify', json_encode([
    'type'  => 'notify.daemon_down',
    'level' => 'danger',
    'msg'   => 'AMI Consumer stopped',
]));
$log('info', 'Done.');
