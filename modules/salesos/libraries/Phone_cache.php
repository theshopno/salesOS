<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Redis-backed phone → entity lookup cache.
 *
 * Lookup order:
 *   1. Redis pcache hit (match or explicit miss) → return immediately, 0 DB queries
 *   2. Redis pcache absent → salesos_phone_index (fast indexed lookup)
 *   3. Still absent → direct leads/contacts/clients table scan
 *   4. Write result to pcache + reverse index
 *
 * Invalidation:
 *   Call invalidate_for_entity() from CRM entity save hooks.
 *   TTL-based expiry is the fallback (1h match, 5m miss).
 */
class Phone_cache
{
    const TTL_MATCH = 3600;   // 1 hour for positive match
    const TTL_MISS  = 300;    // 5 min for negative (prevents DB hammer on unknown numbers)

    private $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->library('salesos/redis_service');
        $this->CI->load->helper('salesos/salesos_helper');
    }

    /**
     * Lookup phone → entity.
     * Returns ['entity_type', 'entity_id', 'entity_name', 'matched_at'] or null on miss.
     */
    public function lookup(string $phone): ?array
    {
        $normalized = salesos_normalize_phone($phone);
        if (empty($normalized)) return null;

        $cached = $this->CI->redis_service->get("salesos:pcache:{$normalized}");

        if ($cached !== null) {
            $data = json_decode($cached, true);
            return (!empty($data['miss'])) ? null : $data;
        }

        $result = $this->_db_lookup($normalized, $phone);

        if ($result) {
            $this->_write_match($normalized, $result);
        } else {
            $this->_write_miss($normalized);
        }

        return $result;
    }

    /**
     * Explicitly cache a confirmed match (e.g. after CDR sync enrichment).
     */
    public function cache_match(string $phone, string $entity_type, int $entity_id, string $entity_name): void
    {
        $normalized = salesos_normalize_phone($phone);
        if (empty($normalized)) return;

        $this->_write_match($normalized, [
            'entity_type' => $entity_type,
            'entity_id'   => $entity_id,
            'entity_name' => $entity_name,
        ]);
    }

    /**
     * Invalidate all cached phone variants for a given entity.
     * Call this from lead/contact/client save hooks.
     */
    public function invalidate_for_entity(string $entity_type, int $entity_id): void
    {
        $idx_key = "salesos:pcache:idx:{$entity_type}:{$entity_id}";
        $phones  = $this->CI->redis_service->smembers($idx_key);

        $del_keys = [$idx_key];
        foreach ($phones as $phone) {
            $del_keys[] = "salesos:pcache:{$phone}";
        }

        $this->CI->redis_service->del(...$del_keys);
    }

    /**
     * Warm the cache for a single entity's phone number (call after entity create).
     */
    public function warm_entity(string $entity_type, int $entity_id, string $phone, string $name): void
    {
        $normalized = salesos_normalize_phone($phone);
        if (empty($normalized)) return;

        $this->_write_match($normalized, [
            'entity_type' => $entity_type,
            'entity_id'   => $entity_id,
            'entity_name' => $name,
        ]);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function _db_lookup(string $normalized, string $original): ?array
    {
        $variants = salesos_phone_variants($normalized);

        // 1. Fast: phone index table
        foreach ($variants as $v) {
            $row = $this->CI->db->get_where(
                db_prefix() . 'salesos_phone_index',
                ['phone' => $v]
            )->row_array();

            if ($row) {
                return [
                    'entity_type' => $row['entity_type'],
                    'entity_id'   => (int) $row['entity_id'],
                    'entity_name' => $row['entity_name'],
                ];
            }
        }

        // 2. Direct table fallback (phone index may be stale)
        foreach ($variants as $v) {
            $r = $this->CI->db->get_where(db_prefix() . 'leads', ['phonenumber' => $v])->row_array();
            if ($r) return ['entity_type' => 'lead', 'entity_id' => (int) $r['id'], 'entity_name' => $r['name']];
        }

        foreach ($variants as $v) {
            $r = $this->CI->db->get_where(db_prefix() . 'contacts', ['phonenumber' => $v])->row_array();
            if ($r) {
                $name = trim(($r['firstname'] ?? '') . ' ' . ($r['lastname'] ?? ''));
                return ['entity_type' => 'contact', 'entity_id' => (int) $r['id'], 'entity_name' => $name];
            }
        }

        foreach ($variants as $v) {
            $r = $this->CI->db->get_where(db_prefix() . 'clients', ['phonenumber' => $v])->row_array();
            if ($r) return ['entity_type' => 'client', 'entity_id' => (int) $r['userid'], 'entity_name' => $r['company']];
        }

        return null;
    }

    private function _write_match(string $normalized, array $result): void
    {
        $result['matched_at'] = time();
        $result['miss']       = false;

        $this->CI->redis_service->set(
            "salesos:pcache:{$normalized}",
            json_encode($result),
            self::TTL_MATCH
        );

        // Reverse index so we can invalidate all variants when entity changes
        $idx_key = "salesos:pcache:idx:{$result['entity_type']}:{$result['entity_id']}";
        $this->CI->redis_service->sadd($idx_key, $normalized);
        $this->CI->redis_service->expire($idx_key, self::TTL_MATCH);
    }

    private function _write_miss(string $normalized): void
    {
        $this->CI->redis_service->set(
            "salesos:pcache:{$normalized}",
            json_encode(['miss' => true, 'checked_at' => time()]),
            self::TTL_MISS
        );
    }
}
