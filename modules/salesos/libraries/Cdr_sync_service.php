<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Pulls new rows from the PBX's CDR database (asteriskcdrdb.cdr, written by
 * Asterisk's cdr_adaptive_odbc — see provision_pbx.sh) into salesos_calls,
 * with basic direction inference and lead/contact phone matching.
 *
 * Connection is settings-driven (§8a) — a second, ad-hoc CI database
 * connection, not the app's default one. Fails soft: sync() never throws,
 * always returns a result array and records status in salesos_cdr_sync.
 */
class Cdr_sync_service
{
    private const BATCH_SIZE = 200;

    private $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    /** @return array{ok: bool, synced: int, error: ?string} */
    public function sync(): array
    {
        $cdr_db = $this->connect_cdr_db();
        if ($cdr_db === null) {
            $this->log_sync(0, 'error', 'Could not connect to CDR database (Settings → Connection).');
            return ['ok' => false, 'synced' => 0, 'error' => 'CDR DB connection failed'];
        }

        $last_start = $this->last_synced_start();

        $rows = $cdr_db->select('*')
            ->where('start >', $last_start)
            ->order_by('start', 'asc')
            ->limit(self::BATCH_SIZE)
            ->get('cdr')
            ->result_array();

        $agents_by_ext = $this->agents_by_extension();
        $synced = 0;
        $last_uniqueid = null;

        foreach ($rows as $row) {
            if ($this->import_row($row, $agents_by_ext)) {
                $synced++;
            }
            $last_uniqueid = $row['uniqueid'];
        }

        $this->log_sync($synced, 'ok', null, $last_uniqueid);

        return ['ok' => true, 'synced' => $synced, 'error' => null];
    }

    // ── Row import ───────────────────────────────────────────────────────────

    private function import_row(array $row, array $agents_by_ext): bool
    {
        if ($this->CI->db->where('uniqueid', $row['uniqueid'])->count_all_results(db_prefix() . 'salesos_calls') > 0) {
            return false; // already imported — sync is idempotent
        }

        $extension = $this->extract_extension($row['channel'] ?? '');
        $agent     = $extension !== null ? ($agents_by_ext[$extension] ?? null) : null;

        $direction = 'unknown';
        $other_number = null;
        if ($agent !== null) {
            if (($row['src'] ?? '') === $extension) {
                $direction    = 'outbound';
                $other_number = $row['dst'] ?? '';
            } elseif (($row['dst'] ?? '') === $extension) {
                $direction    = 'inbound';
                $other_number = $row['src'] ?? '';
            }
        }

        $match = $other_number ? $this->match_entity($other_number) : ['type' => 'none', 'id' => null];

        $data = [
            'uniqueid'      => $row['uniqueid'],
            'calldate'      => $row['start'],
            'direction'     => $direction,
            'src'           => $row['src']  ?? '',
            'dst'           => $row['dst']  ?? '',
            'extension'     => $extension,
            'duration'      => (int) ($row['duration'] ?? 0),
            'billsec'       => (int) ($row['billsec']  ?? 0),
            'disposition'   => $row['disposition'] ?? '',
            'agent_id'      => $agent['staff_id'] ?? null,
            'match_type'    => $match['type'],
            'lead_id'       => $match['type'] === 'lead'    ? $match['id'] : null,
            'contact_id'    => $match['type'] === 'contact' ? $match['id'] : null,
            'client_id'     => $match['type'] === 'client'  ? $match['id'] : null,
        ];

        return (bool) $this->CI->db->insert(db_prefix() . 'salesos_calls', $data);
    }

    private function extract_extension(string $channel): ?string
    {
        // e.g. "PJSIP/1002-00000001" -> "1002"
        if (preg_match('/^PJSIP\/(\d+)-/', $channel, $m)) {
            return $m[1];
        }

        return null;
    }

    /** @return array{type: string, id: ?int} */
    private function match_entity(string $number): array
    {
        $normalized = $this->normalize_phone($number);
        if ($normalized === '') {
            return ['type' => 'none', 'id' => null];
        }

        $lead = $this->CI->db->select('id')
            ->like('phonenumber', $normalized, 'both')
            ->get(db_prefix() . 'leads')
            ->row_array();
        if ($lead) {
            return ['type' => 'lead', 'id' => (int) $lead['id']];
        }

        $contact = $this->CI->db->select('id')
            ->like('phonenumber', $normalized, 'both')
            ->get(db_prefix() . 'contacts')
            ->row_array();
        if ($contact) {
            return ['type' => 'contact', 'id' => (int) $contact['id']];
        }

        return ['type' => 'none', 'id' => null];
    }

    /** Strip everything but digits, keep the last 8 (loose match across
     *  country-code/leading-zero variations). */
    private function normalize_phone(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number);

        return $digits === '' ? '' : substr($digits, -8);
    }

    // ── Bookkeeping ──────────────────────────────────────────────────────────

    private function last_synced_start(): string
    {
        $row = $this->CI->db->order_by('id', 'desc')->limit(1)
            ->get(db_prefix() . 'salesos_cdr_sync')->row_array();

        return $row['last_sync'] ?? '1970-01-01 00:00:00';
    }

    private function log_sync(int $synced, string $status, ?string $error, ?string $last_uniqueid = null): void
    {
        $this->CI->db->insert(db_prefix() . 'salesos_cdr_sync', [
            'last_sync'      => date('Y-m-d H:i:s'),
            'last_uniqueid'  => $last_uniqueid,
            'records_synced' => $synced,
            'status'         => $status,
            'error_msg'      => $error,
        ]);
    }

    /** @return array<string, array{staff_id: int, extension: string}> keyed by extension */
    private function agents_by_extension(): array
    {
        $rows = $this->CI->db->select('staff_id, extension')
            ->where('is_active', 1)
            ->get(db_prefix() . 'salesos_agents')->result_array();

        $out = [];
        foreach ($rows as $r) {
            $out[$r['extension']] = $r;
        }

        return $out;
    }

    // ── CDR DB connection ────────────────────────────────────────────────────

    private function connect_cdr_db()
    {
        $host = salesos_get_option('salesos_cdr_db_host', '');
        if ($host === '') {
            return null;
        }

        $config = [
            'hostname' => $host,
            'port'     => (int) salesos_get_option('salesos_cdr_db_port', 3306),
            'username' => salesos_get_option('salesos_cdr_db_user', ''),
            'password' => salesos_get_option('salesos_cdr_db_password', ''),
            'database' => salesos_get_option('salesos_cdr_db_name', 'asteriskcdrdb'),
            'dbdriver' => 'mysqli',
            'dbprefix' => '',
            'pconnect' => false,
            'db_debug' => false,
            'cache_on' => false,
            'char_set' => 'utf8mb4',
            'dbcollat' => 'utf8mb4_unicode_ci',
        ];

        try {
            return $this->CI->load->database($config, true);
        } catch (Exception $e) {
            return null;
        }
    }
}
