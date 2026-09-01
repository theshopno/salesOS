#!/usr/bin/env php
<?php
/**
 * SalesOS WebSocket Server Daemon
 *
 * Bridges Redis Streams (consumer group "ws-delivery") to browser clients
 * over WebSocket. Each authenticated client receives events filtered to
 * its subscriptions (agent, queue, or wildcard).
 *
 * Dependencies: composer require cboden/ratchet react/event-loop
 *
 * Usage:
 *   php ws_server.php [--config=/path/to/config.php]
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
    fwrite(STDERR, "[salesos-ws] Config not found: {$cfg_path}\n");
    exit(1);
}

$cfg = require $cfg_path;

// Autoload Ratchet + ReactPHP via Composer
$autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "[salesos-ws] composer autoload not found: {$autoload}\n");
    exit(1);
}
require $autoload;

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\Loop;
use React\Socket\SocketServer;

// ── Signal handling ────────────────────────────────────────────────────────────

$running = true;
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, static function () use (&$running): void { $running = false; });
    pcntl_signal(SIGINT,  static function () use (&$running): void { $running = false; });
}

// ── Logging ───────────────────────────────────────────────────────────────────

function ws_log(string $lvl, string $msg): void
{
    echo sprintf('[%s] [salesos-ws] [%s] %s', date('Y-m-d H:i:s'), strtoupper($lvl), $msg) . "\n";
}

// ── Redis helpers (standalone, no CI) ────────────────────────────────────────

function ws_redis(array $cfg): ?Redis
{
    if (!extension_loaded('redis')) {
        ws_log('error', 'phpredis extension not loaded');
        return null;
    }
    try {
        $r = new Redis();
        $r->connect($cfg['redis_host'], (int) $cfg['redis_port'], 3.0);
        $r->setOption(Redis::OPT_READ_TIMEOUT, -1);
        if (!empty($cfg['redis_password'])) {
            $r->auth($cfg['redis_password']);
        }
        return $r;
    } catch (Exception $e) {
        ws_log('error', 'Redis connect: ' . $e->getMessage());
        return null;
    }
}

// ── Token validation ──────────────────────────────────────────────────────────

function ws_validate_token(Redis $r, string $token): ?array
{
    $key = 'salesos:ws:token:' . $token;
    try {
        $data = $r->get($key);
        if (!$data) return null;
        $payload = json_decode($data, true);
        if (!is_array($payload)) return null;
        if (empty($payload['staff_id'])) return null;
        // Token is single-use: delete after validation
        $r->del($key);
        return $payload;
    } catch (Exception $e) {
        return null;
    }
}

// ── Event filter ──────────────────────────────────────────────────────────────

function ws_should_deliver(array $client_meta, array $event): bool
{
    $event_type = $event['event_type'] ?? '';
    $agent_ext  = $event['agent_ext']  ?? '';
    $staff_id   = (string) ($event['staff_id'] ?? '');

    // Admins and supervisors get all events
    if (in_array($client_meta['role'] ?? '', ['admin', 'supervisor'], true)) {
        return true;
    }

    $client_ext = $client_meta['ext'] ?? '';
    $client_sid = (string) ($client_meta['staff_id'] ?? '');

    if (str_starts_with($event_type, 'agent.')) {
        return ($agent_ext !== '' && $agent_ext === $client_ext)
            || ($staff_id !== '' && $staff_id === $client_sid);
    }

    // Targeted incoming popup: only deliver call.ringing to the mapped agent's session.
    // If staff_id and agent_ext are both empty (e.g. queue call not yet assigned),
    // broadcast so all agents see it — queue ring-all behaviour.
    if ($event_type === 'call.ringing') {
        if ($staff_id !== '') return $staff_id === $client_sid;
        if ($agent_ext !== '') return $agent_ext === $client_ext;
        // Unknown agent → broadcast (queue / ring group)
        return true;
    }

    // call.*, queue.*, recording.* (non-ringing) — broadcast to all authenticated
    return true;
}

// ── WebSocket application ─────────────────────────────────────────────────────

class SalesOsWs implements MessageComponentInterface
{
    private \SplObjectStorage $clients;
    private array $client_meta = [];
    private Redis $r;
    private array $cfg;

    public function __construct(Redis $r, array $cfg)
    {
        $this->clients = new \SplObjectStorage();
        $this->r       = $r;
        $this->cfg     = $cfg;
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);
        $this->client_meta[$conn->resourceId] = [
            'authenticated' => false,
            'staff_id'      => null,
            'role'          => null,
            'ext'           => null,
            'connected_at'  => time(),
        ];
        ws_log('info', "Client #{$conn->resourceId} connected from {$conn->remoteAddress}");
    }

    public function onMessage(ConnectionInterface $conn, $msg): void
    {
        $rid  = $conn->resourceId;
        $data = json_decode($msg, true);

        if (!is_array($data)) {
            $conn->send(json_encode(['type' => 'error', 'msg' => 'invalid_json']));
            return;
        }

        $type = $data['type'] ?? '';

        if ($type === 'auth') {
            $token = $data['token'] ?? '';
            if ($token === '') {
                $conn->send(json_encode(['type' => 'auth_fail', 'reason' => 'missing_token']));
                return;
            }

            $payload = ws_validate_token($this->r, $token);
            if (!$payload) {
                $conn->send(json_encode(['type' => 'auth_fail', 'reason' => 'invalid_token']));
                $conn->close();
                return;
            }

            $session_uuid = bin2hex(random_bytes(8));
            $this->client_meta[$rid] = [
                'authenticated' => true,
                'staff_id'      => (string) $payload['staff_id'],
                'role'          => $payload['role']     ?? 'agent',
                'ext'           => $payload['ext']      ?? '',
                'connected_at'  => time(),
                'session_uuid'  => $session_uuid,
            ];

            // Multi-session tracking: one Set per staff member, one hash per session
            $sid_str  = (string) $payload['staff_id'];
            $sess_key = 'salesos:session:' . $sid_str . ':' . $session_uuid;
            try {
                $this->r->hMSet($sess_key, [
                    'staff_id'     => $sid_str,
                    'role'         => $payload['role'] ?? 'agent',
                    'ext'          => $payload['ext']  ?? '',
                    'connected_at' => (string) time(),
                    'resource_id'  => (string) $rid,
                ]);
                $this->r->expire($sess_key, 28800); // 8h
                $this->r->sAdd('salesos:staff_sessions:' . $sid_str, $session_uuid);
                $this->r->expire('salesos:staff_sessions:' . $sid_str, 28800);
            } catch (Exception $e) {
                ws_log('warn', 'Session Redis write failed: ' . $e->getMessage());
            }

            $conn->send(json_encode(['type' => 'auth_ok', 'staff_id' => $payload['staff_id'], 'session_uuid' => $session_uuid]));
            ws_log('info', "Client #{$rid} authenticated as staff_id={$payload['staff_id']} role={$payload['role']}");

            // Push current call/agent snapshot
            $this->_send_snapshot($conn);
            return;
        }

        if ($type === 'ping') {
            $conn->send(json_encode(['type' => 'pong', 'ts' => time()]));
            return;
        }

        if (!($this->client_meta[$rid]['authenticated'] ?? false)) {
            $conn->send(json_encode(['type' => 'error', 'msg' => 'not_authenticated']));
            return;
        }

        // Unknown message — silently ignore
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $rid  = $conn->resourceId;
        $meta = $this->client_meta[$rid] ?? [];

        // Clean up multi-session Redis entries
        if (!empty($meta['staff_id']) && !empty($meta['session_uuid'])) {
            try {
                $sid_str  = (string) $meta['staff_id'];
                $uuid     = $meta['session_uuid'];
                $this->r->del('salesos:session:' . $sid_str . ':' . $uuid);
                $this->r->sRem('salesos:staff_sessions:' . $sid_str, $uuid);
            } catch (Exception $e) {}
        }

        $this->clients->detach($conn);
        unset($this->client_meta[$rid]);
        ws_log('info', "Client #{$rid} disconnected");
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        ws_log('warn', "Error on #{$conn->resourceId}: " . $e->getMessage());
        $conn->close();
    }

    /** Push current state snapshot to a newly authenticated client. */
    private function _send_snapshot(ConnectionInterface $conn): void
    {
        $rid  = $conn->resourceId;
        $meta = $this->client_meta[$rid] ?? [];

        $is_privileged = in_array($meta['role'] ?? '', ['admin', 'supervisor'], true);
        $client_sid    = (string) ($meta['staff_id'] ?? '');
        $client_ext    = $meta['ext'] ?? '';

        try {
            // Active calls — filtered for agent role to only own call
            $calls = $this->r->hGetAll('salesos:calls');
            if (!empty($calls)) {
                $decoded = [];
                foreach ($calls as $uid => $json) {
                    $c = json_decode($json, true) ?? [];
                    if (!$is_privileged) {
                        $ev_sid = (string) ($c['staff_id']   ?? '');
                        $ev_ext = $c['agent_ext'] ?? '';
                        if ($ev_sid !== '' && $ev_sid !== $client_sid) continue;
                        if ($ev_ext !== '' && $ev_ext !== $client_ext) continue;
                    }
                    $decoded[$uid] = $c;
                }
                if (!empty($decoded)) {
                    $conn->send(json_encode(['type' => 'snapshot', 'scope' => 'calls', 'data' => $decoded]));
                }
            }

            // Agent states
            $agents = $this->r->hGetAll('salesos:agents');
            if (!empty($agents)) {
                $decoded = [];
                foreach ($agents as $ext => $json) {
                    $decoded[$ext] = json_decode($json, true);
                }
                $conn->send(json_encode(['type' => 'snapshot', 'scope' => 'agents', 'data' => $decoded]));
            }

            // Queue states
            $queues = $this->r->hGetAll('salesos:queues');
            if (!empty($queues)) {
                $decoded = [];
                foreach ($queues as $name => $json) {
                    $decoded[$name] = json_decode($json, true);
                }
                $conn->send(json_encode(['type' => 'snapshot', 'scope' => 'queues', 'data' => $decoded]));
            }
        } catch (Exception $e) {
            ws_log('warn', 'Snapshot error: ' . $e->getMessage());
        }
    }

    /** Called by the event loop to broadcast a stream event to all eligible clients. */
    public function broadcast(array $event): void
    {
        $payload = json_encode(['type' => 'event', 'data' => $event]);

        foreach ($this->clients as $conn) {
            $rid  = $conn->resourceId;
            $meta = $this->client_meta[$rid] ?? [];

            if (!($meta['authenticated'] ?? false)) continue;
            if (!ws_should_deliver($meta, $event)) continue;

            try {
                $conn->send($payload);
            } catch (Exception $e) {
                ws_log('warn', "Send to #{$rid} failed: " . $e->getMessage());
            }
        }
    }

    public function clientCount(): int
    {
        return count($this->clients);
    }
}

// ── Main ──────────────────────────────────────────────────────────────────────

ws_log('info', 'Starting WebSocket server.');

$redis = ws_redis($cfg);
if (!$redis) {
    fwrite(STDERR, "[salesos-ws] Redis unavailable.\n");
    exit(1);
}

// Separate Redis connection for Pub/Sub notify channel (blocks on subscribe)
$redis_sub = ws_redis($cfg);

$ws_app = new SalesOsWs($redis, $cfg);
$loop   = Loop::get();
$port   = (int) ($cfg['ws_port'] ?? 8080);

$socket = new SocketServer("0.0.0.0:{$port}", [], $loop);
$server = new IoServer(
    new HttpServer(new WsServer($ws_app)),
    $socket,
    $loop
);

ws_log('info', "WebSocket listening on ws://0.0.0.0:{$port}");

// ── Stream polling: read from ws-delivery consumer group ─────────────────────

$streams   = [
    'salesos:stream:calls',
    'salesos:stream:agents',
    'salesos:stream:queues',
    'salesos:stream:recordings',
    'salesos:stream:webrtc',
];
$consumer  = 'ws-' . gethostname() . '-' . getmypid();
$ack_queue = [];   // stream → [stream_ids]

$loop->addPeriodicTimer(0.1, static function () use (&$ack_queue, $streams, $consumer, $redis, $ws_app, $cfg): void {
    $total = 0;

    foreach ($streams as $stream) {
        try {
            $result = $redis->xReadGroup('ws-delivery', $consumer, [$stream => '>'], 100, null);
        } catch (Exception $e) {
            ws_log('warn', "XREADGROUP {$stream}: " . $e->getMessage());
            return;
        }

        if (empty($result[$stream])) continue;

        foreach ($result[$stream] as $sid => $fields) {
            // Reconstruct typed event for filtering
            $event = $fields;
            $ws_app->broadcast($event);
            $ack_queue[$stream][] = $sid;
            $total++;
        }
    }

    // Flush ACKs
    if (!empty($ack_queue)) {
        foreach ($ack_queue as $stream => $ids) {
            try {
                $redis->xAck($stream, 'ws-delivery', $ids);
            } catch (Exception $e) {
                ws_log('warn', "XACK {$stream}: " . $e->getMessage());
            }
        }
        $ack_queue = [];
    }

    if ($total > 0) {
        ws_log('debug', "Delivered {$total} events to " . $ws_app->clientCount() . ' clients');
    }
});

// ── Pub/Sub: transient notify toasts ─────────────────────────────────────────

if ($redis_sub) {
    $loop->addPeriodicTimer(0.2, static function () use ($redis_sub, $ws_app): void {
        // Non-blocking check via SUBSCRIBE reply buffering is not straightforward
        // with phpredis in synchronous mode. We use a message-available approach:
        // publish a sentinel using XLEN on notify list, then pop.
        try {
            // Use a Redis list as a non-blocking notify queue
            $msg = $redis_sub->lPop('salesos:notify:queue');
            if ($msg === false || $msg === null) return;
            $data = json_decode($msg, true);
            if (!is_array($data)) return;
            $ws_app->broadcast(array_merge(['event_type' => $data['type'] ?? 'notify.generic'], $data));
        } catch (Exception $e) {
            // Silently skip transient notify errors
        }
    });
}

// ── Heartbeat / shutdown check ────────────────────────────────────────────────

$loop->addPeriodicTimer(5.0, static function () use (&$running, $loop, $redis, $cfg): void {
    if (!$running) {
        ws_log('info', 'Shutdown signal received. Stopping.');
        $loop->stop();
        return;
    }

    // Refresh daemon heartbeat
    try {
        $redis->set('salesos:lock:ws_server', '1', ['EX' => 30]);
    } catch (Exception $e) {}
});

$loop->run();

ws_log('info', 'WebSocket server stopped.');
