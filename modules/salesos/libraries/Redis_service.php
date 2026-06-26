<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Redis wrapper for use inside CodeIgniter (controllers, models, libraries).
 * Daemons use phpredis directly — this class is for the CRM web process only.
 *
 * Requires: php-pecl-redis (phpredis extension)
 *   dnf install php-pecl-redis
 */
class Redis_service
{
    private $redis   = null;
    private $host    = '127.0.0.1';
    private $port    = 6379;
    private $timeout = 2.0;

    public function __construct()
    {
        $this->host = get_option('salesos_redis_host', '127.0.0.1');
        $this->port = (int) get_option('salesos_redis_port', 6379);
    }

    // ── Connection ────────────────────────────────────────────────────────────

    public function connect(): bool
    {
        if ($this->redis !== null) {
            return true;
        }

        if (!extension_loaded('redis')) {
            log_message('error', '[SalesOS/Redis] phpredis extension not loaded');
            return false;
        }

        try {
            $r = new Redis();
            if (!$r->connect($this->host, $this->port, $this->timeout)) {
                log_message('error', '[SalesOS/Redis] connect() returned false');
                return false;
            }
            $this->redis = $r;
            return true;
        } catch (Exception $e) {
            log_message('error', '[SalesOS/Redis] ' . $e->getMessage());
            return false;
        }
    }

    public function available(): bool
    {
        return $this->connect();
    }

    private function r(): ?Redis
    {
        $this->connect();
        return $this->redis;
    }

    // ── Streams ───────────────────────────────────────────────────────────────

    /**
     * XADD stream * field value ... (with approximate MAXLEN trim).
     * Returns stream entry ID or null on failure.
     */
    public function xadd(string $stream, array $fields, int $maxlen = 50000): ?string
    {
        $r = $this->r();
        if (!$r) return null;
        try {
            return $r->xAdd($stream, '*', $fields, $maxlen, true) ?: null;
        } catch (Exception $e) {
            log_message('error', "[SalesOS/Redis] xadd({$stream}): " . $e->getMessage());
            return null;
        }
    }

    /**
     * XREADGROUP GROUP {group} {consumer} COUNT {count} STREAMS {stream} >
     * Returns flat array of [id => fields] for $stream.
     */
    public function xreadgroup(string $group, string $consumer, string $stream, int $count = 100): array
    {
        $r = $this->r();
        if (!$r) return [];
        try {
            $result = $r->xReadGroup($group, $consumer, [$stream => '>'], $count, null);
            return is_array($result) ? ($result[$stream] ?? []) : [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * XACK stream group id [id ...]
     */
    public function xack(string $stream, string $group, array $ids): void
    {
        $r = $this->r();
        if (!$r || empty($ids)) return;
        try {
            $r->xAck($stream, $group, $ids);
        } catch (Exception $e) {
            // non-fatal
        }
    }

    /**
     * XGROUP CREATE stream group $ MKSTREAM (idempotent — ignores BUSYGROUP).
     * Use from='0' to process existing entries on new consumer.
     */
    public function xcreate_group(string $stream, string $group, string $from = '$'): void
    {
        $r = $this->r();
        if (!$r) return;
        try {
            $r->rawCommand('XGROUP', 'CREATE', $stream, $group, $from, 'MKSTREAM');
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'BUSYGROUP') === false) {
                log_message('error', "[SalesOS/Redis] xcreate_group({$stream},{$group}): " . $e->getMessage());
            }
        }
    }

    /**
     * XLEN stream — entry count.
     */
    public function xlen(string $stream): int
    {
        $r = $this->r();
        if (!$r) return 0;
        try {
            return (int) $r->xLen($stream);
        } catch (Exception $e) {
            return 0;
        }
    }

    // ── Hash ──────────────────────────────────────────────────────────────────

    public function hset(string $key, array $fields, int $ttl = 0): bool
    {
        $r = $this->r();
        if (!$r) return false;
        try {
            $r->hMSet($key, $fields);
            if ($ttl > 0) $r->expire($key, $ttl);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function hget(string $key, string $field): ?string
    {
        $r = $this->r();
        if (!$r) return null;
        $v = $r->hGet($key, $field);
        return ($v === false) ? null : $v;
    }

    public function hgetall(string $key): array
    {
        $r = $this->r();
        if (!$r) return [];
        return $r->hGetAll($key) ?: [];
    }

    public function hdel(string $key, string ...$fields): void
    {
        $r = $this->r();
        if (!$r) return;
        $r->hDel($key, ...$fields);
    }

    public function hincrby(string $key, string $field, int $by = 1): int
    {
        $r = $this->r();
        if (!$r) return 0;
        return (int) $r->hIncrBy($key, $field, $by);
    }

    // ── String / Key ──────────────────────────────────────────────────────────

    public function set(string $key, string $value, int $ttl = 0): bool
    {
        $r = $this->r();
        if (!$r) return false;
        try {
            return $ttl > 0
                ? (bool) $r->setEx($key, $ttl, $value)
                : (bool) $r->set($key, $value);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * SET key value NX EX ttl — set only if not exists.
     */
    public function setnx(string $key, string $value, int $ttl = 30): bool
    {
        $r = $this->r();
        if (!$r) return false;
        try {
            return (bool) $r->set($key, $value, ['NX', 'EX' => $ttl]);
        } catch (Exception $e) {
            return false;
        }
    }

    public function get(string $key): ?string
    {
        $r = $this->r();
        if (!$r) return null;
        $v = $r->get($key);
        return ($v === false) ? null : $v;
    }

    public function del(string ...$keys): void
    {
        $r = $this->r();
        if (!$r || empty($keys)) return;
        $r->del($keys);
    }

    public function expire(string $key, int $ttl): void
    {
        $r = $this->r();
        if (!$r) return;
        $r->expire($key, $ttl);
    }

    // ── Set ───────────────────────────────────────────────────────────────────

    public function sadd(string $key, string ...$members): void
    {
        $r = $this->r();
        if (!$r) return;
        $r->sAdd($key, ...$members);
    }

    public function smembers(string $key): array
    {
        $r = $this->r();
        if (!$r) return [];
        return $r->sMembers($key) ?: [];
    }

    // ── List ──────────────────────────────────────────────────────────────────

    public function lpush(string $key, string ...$values): void
    {
        $r = $this->r();
        if (!$r) return;
        foreach ($values as $v) {
            $r->lPush($key, $v);
        }
    }

    public function ltrim(string $key, int $start, int $stop): void
    {
        $r = $this->r();
        if (!$r) return;
        $r->lTrim($key, $start, $stop);
    }

    public function lrange(string $key, int $start, int $stop): array
    {
        $r = $this->r();
        if (!$r) return [];
        return $r->lRange($key, $start, $stop) ?: [];
    }

    // ── Pub/Sub (publish only — subscribe is handled by daemons) ─────────────

    public function publish(string $channel, array $payload): void
    {
        $r = $this->r();
        if (!$r) return;
        try {
            $r->publish($channel, json_encode($payload));
        } catch (Exception $e) {
            // non-fatal
        }
    }

    // ── State snapshot helpers (used by Realtime controller) ─────────────────

    public function get_active_calls(): array
    {
        $calls = $this->hgetall('salesos:calls');
        $result = [];
        foreach ($calls as $uid => $json) {
            $result[$uid] = json_decode($json, true) ?? [];
        }
        return $result;
    }

    public function get_agent_states(): array
    {
        $agents = $this->hgetall('salesos:agents');
        $result = [];
        foreach ($agents as $ext => $json) {
            $result[$ext] = json_decode($json, true) ?? [];
        }
        return $result;
    }

    public function get_queue_states(): array
    {
        $queues = $this->hgetall('salesos:queues');
        $result = [];
        foreach ($queues as $name => $json) {
            $result[$name] = json_decode($json, true) ?? [];
        }
        return $result;
    }

    public function get_call_detail(string $uniqueid): array
    {
        return $this->hgetall("salesos:call:{$uniqueid}");
    }
}
