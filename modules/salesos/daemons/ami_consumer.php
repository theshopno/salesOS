#!/usr/bin/env php
<?php
/**
 * salesos v2 — AMI event consumer (§9 Phase 1 screen-pop)
 *
 * Standalone daemon, not part of the CI web request cycle — run under
 * systemd. Connects to AMI (settings-driven, read straight from
 * salesos_settings — same DB the web app uses, no separate generated
 * config file needed), listens for DialBegin/DialEnd/Hangup, and when a
 * call is ringing a mapped agent's extension, upserts salesos_active_calls
 * so the browser poller (Api::active_calls(), salesos.php's
 * salesos_inject_screenpop_poller()) picks it up within its 5s interval.
 *
 * Usage: php ami_consumer.php
 */

declare(strict_types=1);

// ── Bootstrap (just enough to read config — not a full CI load) ────────────
define('BASEPATH', __DIR__); // satisfies app-config.php's include guard
require dirname(__DIR__, 3) . '/application/config/app-config.php';

$running = true;
pcntl_async_signals(true);
pcntl_signal(SIGTERM, function () use (&$running) { $running = false; });
pcntl_signal(SIGINT,  function () use (&$running) { $running = false; });

function log_line(string $msg): void
{
    fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . "] $msg\n");
}

function db_connect(): mysqli
{
    $db = @new mysqli(APP_DB_HOSTNAME, APP_DB_USERNAME, APP_DB_PASSWORD, APP_DB_NAME);
    if ($db->connect_errno) {
        fwrite(STDERR, "DB connect failed: {$db->connect_error}\n");
        exit(1);
    }

    return $db;
}

function get_setting(mysqli $db, string $key, string $default = ''): string
{
    $stmt = $db->prepare('SELECT svalue FROM tblsalesos_settings WHERE skey = ?');
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $stmt->bind_result($val);
    $found = $stmt->fetch();
    $stmt->close();

    return $found && $val !== null ? (string) $val : $default;
}

/** @return array<string, int> extension => staff_id */
function get_agents(mysqli $db): array
{
    $out = [];
    $res = $db->query('SELECT staff_id, extension FROM tblsalesos_agents WHERE is_active = 1');
    while ($row = $res->fetch_assoc()) {
        $out[$row['extension']] = (int) $row['staff_id'];
    }

    return $out;
}

/** Extract the extension from a channel name like "PJSIP/1002-00000001". */
function extract_extension(string $channel): ?string
{
    return preg_match('/^PJSIP\/(\d+)-/', $channel, $m) ? $m[1] : null;
}

/**
 * Parse one AMI event block (already split on \r\n\r\n) into a key/value map.
 * Pure function — this is what gets unit-tested without needing a live socket.
 */
function parse_event(string $block): array
{
    $event = [];
    foreach (explode("\r\n", trim($block)) as $line) {
        $pos = strpos($line, ': ');
        if ($pos !== false) {
            $event[substr($line, 0, $pos)] = substr($line, $pos + 2);
        }
    }

    return $event;
}

/**
 * Decide what to do with one parsed event, given the current agent map.
 * Pure function — testable without a DB or socket.
 *
 * @return array{action: string, data: array}|null  action: 'upsert'|'clear'
 */
function handle_event(array $event, array $agents_by_ext): ?array
{
    $type = $event['Event'] ?? '';

    if ($type === 'DialBegin') {
        $ext = extract_extension($event['DestChannel'] ?? '');
        if ($ext === null || !isset($agents_by_ext[$ext])) {
            return null;
        }

        return ['action' => 'upsert', 'data' => [
            'uniqueid'  => $event['DestUniqueid'] ?? $event['Uniqueid'] ?? '',
            'src'       => $event['CallerIDNum'] ?? '',
            'dst'       => $event['DestCallerIDNum'] ?? $ext,
            'extension' => $ext,
            'agent_id'  => $agents_by_ext[$ext],
            'state'     => 'ringing',
        ]];
    }

    if (in_array($type, ['DialEnd', 'Hangup'], true)) {
        $uid = $event['DestUniqueid'] ?? $event['Uniqueid'] ?? '';
        if ($uid === '') {
            return null;
        }

        return ['action' => 'clear', 'data' => ['uniqueid' => $uid]];
    }

    return null;
}

function apply_action(mysqli $db, array $result): void
{
    if ($result['action'] === 'upsert') {
        $d = $result['data'];
        $stmt = $db->prepare(
            'INSERT INTO tblsalesos_active_calls (uniqueid, src, dst, extension, agent_id, state)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE src=VALUES(src), dst=VALUES(dst), state=VALUES(state)'
        );
        $stmt->bind_param('ssssis', $d['uniqueid'], $d['src'], $d['dst'], $d['extension'], $d['agent_id'], $d['state']);
        $stmt->execute();
        $stmt->close();
        log_line("active call: {$d['src']} -> ext {$d['extension']} (agent {$d['agent_id']})");
    } elseif ($result['action'] === 'clear') {
        $uid = $result['data']['uniqueid'];
        $stmt = $db->prepare('DELETE FROM tblsalesos_active_calls WHERE uniqueid = ?');
        $stmt->bind_param('s', $uid);
        $stmt->execute();
        $stmt->close();
    }
}

// ── Main loop ────────────────────────────────────────────────────────────

if (php_sapi_name() !== 'cli') {
    exit('CLI only');
}

$db = db_connect();

while ($running) {
    $host   = get_setting($db, 'salesos_ami_host');
    $port   = (int) get_setting($db, 'salesos_ami_port', '5038');
    $user   = get_setting($db, 'salesos_ami_username');
    $secret = get_setting($db, 'salesos_ami_secret');

    if ($host === '') {
        log_line('AMI host not configured — retrying in 10s');
        sleep(10);
        continue;
    }

    $sock = @fsockopen($host, $port, $errno, $errstr, 5);
    if (!$sock) {
        log_line("AMI connect failed: $errstr ($errno) — retrying in 5s");
        sleep(5);
        continue;
    }

    // Sockets from fsockopen() are blocking by default — that's exactly what
    // the event loop below wants (fgets() waits for the next event rather
    // than polling). Do NOT call stream_set_timeout($sock, 0) here: a 0
    // timeout means "time out immediately", not "block forever" — it broke
    // the banner read outright when first tried.
    $banner = fgets($sock, 256);
    if ($banner === false || strpos($banner, 'Asterisk Call Manager') === false) {
        log_line('Unexpected AMI banner — retrying in 5s');
        fclose($sock);
        sleep(5);
        continue;
    }

    fwrite($sock, "Action: Login\r\nUsername: $user\r\nSecret: $secret\r\n\r\n");
    $login_resp = fgets($sock, 4096) . fgets($sock, 4096);
    if (strpos($login_resp, 'Success') === false) {
        log_line('AMI auth failed — retrying in 10s');
        fclose($sock);
        sleep(10);
        continue;
    }

    log_line("Connected to AMI at $host:$port");

    $buffer = '';
    while ($running) {
        // stream_select() with a timeout, not a bare blocking fgets(): a
        // blocking read on an idle socket does not reliably get interrupted
        // by pcntl's SIGTERM handler on this PHP/OS combination (confirmed
        // by testing — the daemon ignored SIGTERM entirely while parked in
        // fgets() with no events arriving). Waking up periodically to check
        // $running is what actually makes shutdown work.
        $read = [$sock];
        $write = $except = null;
        $ready = @stream_select($read, $write, $except, 2);
        if ($ready === false) {
            break; // interrupted (e.g. by the signal) or a real error
        }
        if ($ready === 0) {
            continue; // timed out with nothing to read — loop back, check $running
        }

        $line = fgets($sock, 4096);
        if ($line === false) {
            log_line('AMI connection lost — reconnecting');
            break;
        }

        $buffer .= $line;
        if (rtrim($line, "\r\n") === '') {
            $event = parse_event($buffer);
            $buffer = '';

            if (!empty($event)) {
                $agents = get_agents($db); // small table, cheap to re-read per event
                $result = handle_event($event, $agents);
                if ($result !== null) {
                    apply_action($db, $result);
                }
            }
        }
    }

    fclose($sock);
}

log_line('Shutting down.');
$db->close();
