<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Salesos_model extends App_Model
{
    private $pbx_db = null;

    public function __construct()
    {
        parent::__construct();
    }

    // ── CDR / Calls ──────────────────────────────────────────────────────────

    public function get_calls(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $this->db->select('
            c.*,
            a.extension as agent_extension,
            s.firstname as agent_firstname,
            s.lastname  as agent_lastname
        ');
        $this->db->from(db_prefix() . 'salesos_calls c');
        $this->db->join(db_prefix() . 'salesos_agents a', 'a.id = c.agent_id', 'left');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = a.staff_id', 'left');

        if (!empty($filters['direction'])) {
            $this->db->where('c.direction', $filters['direction']);
        }
        if (!empty($filters['disposition'])) {
            $this->db->where('c.disposition', $filters['disposition']);
        }
        if (!empty($filters['date_from'])) {
            $this->db->where('c.calldate >=', $filters['date_from'] . ' 00:00:00');
        }
        if (!empty($filters['date_to'])) {
            $this->db->where('c.calldate <=', $filters['date_to'] . ' 23:59:59');
        }
        if (!empty($filters['search'])) {
            $q = $this->db->escape_like_str($filters['search']);
            $this->db->group_start();
            $this->db->like('c.src', $q);
            $this->db->or_like('c.dst', $q);
            $this->db->or_like('c.src_name', $q);
            $this->db->or_like('c.dst_name', $q);
            $this->db->group_end();
        }
        if (!empty($filters['agent_id'])) {
            $this->db->where('c.agent_id', $filters['agent_id']);
        }
        if (!empty($filters['lead_id'])) {
            $this->db->where('c.lead_id', $filters['lead_id']);
        }
        if (!empty($filters['client_id'])) {
            $this->db->where('c.client_id', $filters['client_id']);
        }

        $this->db->order_by('c.calldate', 'DESC');
        $this->db->limit($limit, $offset);

        return $this->db->get()->result_array();
    }

    public function count_calls(array $filters = []): int
    {
        $this->db->from(db_prefix() . 'salesos_calls c');

        if (!empty($filters['direction'])) {
            $this->db->where('c.direction', $filters['direction']);
        }
        if (!empty($filters['date_from'])) {
            $this->db->where('c.calldate >=', $filters['date_from'] . ' 00:00:00');
        }
        if (!empty($filters['date_to'])) {
            $this->db->where('c.calldate <=', $filters['date_to'] . ' 23:59:59');
        }
        if (!empty($filters['search'])) {
            $q = $this->db->escape_like_str($filters['search']);
            $this->db->group_start();
            $this->db->like('c.src', $q);
            $this->db->or_like('c.dst', $q);
            $this->db->group_end();
        }

        return (int) $this->db->count_all_results();
    }

    public function get_call(int $id): ?array
    {
        $this->db->select('c.*, a.extension, s.firstname, s.lastname, s.email as staff_email');
        $this->db->from(db_prefix() . 'salesos_calls c');
        $this->db->join(db_prefix() . 'salesos_agents a', 'a.id = c.agent_id', 'left');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = a.staff_id', 'left');
        $this->db->where('c.id', $id);
        $row = $this->db->get()->row_array();
        return $row ?: null;
    }

    public function get_call_by_uniqueid(string $uniqueid): ?array
    {
        $row = $this->db->get_where(db_prefix() . 'salesos_calls', ['uniqueid' => $uniqueid])->row_array();
        return $row ?: null;
    }

    public function get_calls_for_phone(string $phone, int $limit = 20): array
    {
        $normalized = salesos_normalize_phone($phone);
        $variants   = salesos_phone_variants($normalized);

        if (empty($variants)) {
            return [];
        }

        $this->db->select('c.*, a.extension, s.firstname, s.lastname');
        $this->db->from(db_prefix() . 'salesos_calls c');
        $this->db->join(db_prefix() . 'salesos_agents a', 'a.id = c.agent_id', 'left');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = a.staff_id', 'left');
        $this->db->group_start();
        foreach ($variants as $v) {
            $this->db->or_where('c.src', $v);
            $this->db->or_where('c.dst', $v);
        }
        $this->db->group_end();
        $this->db->order_by('c.calldate', 'DESC');
        $this->db->limit($limit);

        return $this->db->get()->result_array();
    }

    public function upsert_call(array $data): bool
    {
        $existing = $this->get_call_by_uniqueid($data['uniqueid']);
        if ($existing) {
            $this->db->where('uniqueid', $data['uniqueid']);
            return $this->db->update(db_prefix() . 'salesos_calls', $data);
        }
        return $this->db->insert(db_prefix() . 'salesos_calls', $data);
    }

    public function update_call(int $id, array $data): bool
    {
        $this->db->where('id', $id);
        return $this->db->update(db_prefix() . 'salesos_calls', $data);
    }

    public function delete_call(int $id): bool
    {
        return $this->db->delete(db_prefix() . 'salesos_calls', ['id' => $id]);
    }

    // ── Active calls ─────────────────────────────────────────────────────────

    public function get_active_calls(int $staff_id = 0): array
    {
        if ($staff_id) {
            $this->db->where('staff_id', $staff_id);
        }
        return $this->db->get(db_prefix() . 'salesos_active_calls')->result_array();
    }

    public function upsert_active_call(array $data): void
    {
        $existing = $this->db->get_where(db_prefix() . 'salesos_active_calls', ['uniqueid' => $data['uniqueid']])->row_array();
        if ($existing) {
            $this->db->where('uniqueid', $data['uniqueid']);
            $this->db->update(db_prefix() . 'salesos_active_calls', $data);
        } else {
            $this->db->insert(db_prefix() . 'salesos_active_calls', $data);
        }
    }

    public function remove_active_call(string $uniqueid): void
    {
        $this->db->delete(db_prefix() . 'salesos_active_calls', ['uniqueid' => $uniqueid]);
    }

    public function clear_stale_active_calls(int $minutes = 60): void
    {
        $this->db->where('started_at <', date('Y-m-d H:i:s', strtotime("-$minutes minutes")));
        $this->db->delete(db_prefix() . 'salesos_active_calls');
    }

    public function mark_popup_shown(int $id): void
    {
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'salesos_active_calls', ['popup_shown' => 1]);
    }

    // ── Lead / Contact / Client matching ──────────────────────────────────────

    public function match_phone(string $phone): array
    {
        $normalized = salesos_normalize_phone($phone);
        $variants   = salesos_phone_variants($normalized);
        $result     = ['type' => 'none', 'id' => null, 'name' => null, 'entity' => null];

        if (empty($variants)) {
            return $result;
        }

        // 1. Try leads
        foreach ($variants as $v) {
            $this->db->where('phonenumber', $v);
            $lead = $this->db->get(db_prefix() . 'leads')->row_array();
            if ($lead) {
                return [
                    'type'   => 'lead',
                    'id'     => (int) $lead['id'],
                    'name'   => $lead['name'],
                    'entity' => $lead,
                ];
            }
        }

        // 2. Try contacts (clients contacts)
        foreach ($variants as $v) {
            $this->db->where('phonenumber', $v);
            $contact = $this->db->get(db_prefix() . 'contacts')->row_array();
            if ($contact) {
                // Get client
                $client = $this->db->get_where(db_prefix() . 'clients', ['userid' => $contact['userid']])->row_array();
                return [
                    'type'       => 'contact',
                    'id'         => (int) $contact['id'],
                    'client_id'  => (int) ($contact['userid'] ?? 0),
                    'name'       => trim(($contact['firstname'] ?? '') . ' ' . ($contact['lastname'] ?? '')),
                    'entity'     => $contact,
                    'client'     => $client,
                ];
            }
        }

        // 3. Try clients directly
        foreach ($variants as $v) {
            $this->db->where('phonenumber', $v);
            $client = $this->db->get(db_prefix() . 'clients')->row_array();
            if ($client) {
                return [
                    'type'   => 'client',
                    'id'     => (int) $client['userid'],
                    'name'   => $client['company'],
                    'entity' => $client,
                ];
            }
        }

        return $result;
    }

    // ── Dashboard stats ───────────────────────────────────────────────────────

    public function get_stats(string $date_from = '', string $date_to = ''): array
    {
        if (!$date_from) {
            $date_from = date('Y-m-d', strtotime('-30 days'));
        }
        if (!$date_to) {
            $date_to = date('Y-m-d');
        }

        $base = function ($extra_where = '') use ($date_from, $date_to) {
            $this->db->where('calldate >=', $date_from . ' 00:00:00');
            $this->db->where('calldate <=', $date_to . ' 23:59:59');
            if ($extra_where) {
                $this->db->where($extra_where);
            }
            return (int) $this->db->count_all_results(db_prefix() . 'salesos_calls');
        };

        return [
            'total'          => $base(),
            'inbound'        => $base("direction='inbound'"),
            'outbound'       => $base("direction='outbound'"),
            'answered'       => $base("disposition='ANSWERED'"),
            'missed'         => $base("direction='inbound' AND disposition='NO ANSWER'"),
            'total_duration' => $this->get_total_duration($date_from, $date_to),
            'avg_duration'   => $this->get_avg_duration($date_from, $date_to),
            'matched_leads'  => $base("match_type='lead'"),
        ];
    }

    private function get_total_duration(string $from, string $to): int
    {
        $this->db->select_sum('billsec');
        $this->db->where('calldate >=', $from . ' 00:00:00');
        $this->db->where('calldate <=', $to . ' 23:59:59');
        $row = $this->db->get(db_prefix() . 'salesos_calls')->row_array();
        return (int) ($row['billsec'] ?? 0);
    }

    private function get_avg_duration(string $from, string $to): int
    {
        $this->db->select('AVG(billsec) as avg_sec');
        $this->db->where('calldate >=', $from . ' 00:00:00');
        $this->db->where('calldate <=', $to . ' 23:59:59');
        $this->db->where('disposition', 'ANSWERED');
        $row = $this->db->get(db_prefix() . 'salesos_calls')->row_array();
        return (int) ($row['avg_sec'] ?? 0);
    }

    public function get_daily_call_trend(string $date_from, string $date_to): array
    {
        $this->db->select('DATE(calldate) as day, COUNT(*) as total, direction');
        $this->db->where('calldate >=', $date_from . ' 00:00:00');
        $this->db->where('calldate <=', $date_to . ' 23:59:59');
        $this->db->group_by(['DATE(calldate)', 'direction']);
        $this->db->order_by('day', 'ASC');
        return $this->db->get(db_prefix() . 'salesos_calls')->result_array();
    }

    public function get_agent_stats(string $date_from, string $date_to): array
    {
        $this->db->select('
            a.id as agent_id,
            a.extension,
            s.firstname,
            s.lastname,
            COUNT(c.id)              as total_calls,
            SUM(c.billsec)           as total_billsec,
            SUM(c.disposition="ANSWERED") as answered
        ');
        $this->db->from(db_prefix() . 'salesos_calls c');
        $this->db->join(db_prefix() . 'salesos_agents a', 'a.id = c.agent_id', 'left');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = a.staff_id', 'left');
        $this->db->where('c.calldate >=', $date_from . ' 00:00:00');
        $this->db->where('c.calldate <=', $date_to . ' 23:59:59');
        $this->db->group_by('c.agent_id');
        $this->db->order_by('total_calls', 'DESC');
        return $this->db->get()->result_array();
    }

    // ── Event log ────────────────────────────────────────────────────────────

    public function log_event(string $type, array $payload = []): void
    {
        $data = [
            'event_type'  => $type,
            'call_id'     => $payload['call_id']    ?? null,
            'agent_id'    => $payload['agent_id']   ?? null,
            'staff_id'    => $payload['staff_id']   ?? null,
            'entity_type' => $payload['entity_type'] ?? null,
            'entity_id'   => $payload['entity_id']  ?? null,
            'description' => $payload['description'] ?? null,
            'ip_address'  => $this->input->ip_address(),
            'created_at'  => date('Y-m-d H:i:s'),
        ];

        unset($payload['call_id'], $payload['agent_id'], $payload['staff_id'],
              $payload['entity_type'], $payload['entity_id'], $payload['description']);

        if (!empty($payload)) {
            $data['payload'] = json_encode($payload);
        }

        $this->db->insert(db_prefix() . 'salesos_events', $data);
    }

    public function get_events(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        if (!empty($filters['event_type'])) {
            $this->db->where('event_type', $filters['event_type']);
        }
        if (!empty($filters['call_id'])) {
            $this->db->where('call_id', $filters['call_id']);
        }
        if (!empty($filters['entity_type'])) {
            $this->db->where('entity_type', $filters['entity_type']);
        }
        if (!empty($filters['entity_id'])) {
            $this->db->where('entity_id', $filters['entity_id']);
        }
        $this->db->order_by('created_at', 'DESC');
        $this->db->limit($limit, $offset);
        return $this->db->get(db_prefix() . 'salesos_events')->result_array();
    }

    // ── Phone index ───────────────────────────────────────────────────────────

    public function rebuild_phone_index(): void
    {
        $this->db->truncate(db_prefix() . 'salesos_phone_index');

        // Leads
        $leads = $this->db->select('id, name, phonenumber')->get(db_prefix() . 'leads')->result_array();
        foreach ($leads as $l) {
            if (empty($l['phonenumber'])) continue;
            foreach (salesos_phone_variants(salesos_normalize_phone($l['phonenumber'])) as $v) {
                $this->db->replace(db_prefix() . 'salesos_phone_index', [
                    'phone'       => $v,
                    'entity_type' => 'lead',
                    'entity_id'   => $l['id'],
                    'entity_name' => $l['name'],
                    'is_primary'  => ($v === $l['phonenumber']) ? 1 : 0,
                ]);
            }
        }

        // Contacts
        $contacts = $this->db->select('id, userid, firstname, lastname, phonenumber')
            ->get(db_prefix() . 'contacts')->result_array();
        foreach ($contacts as $c) {
            if (empty($c['phonenumber'])) continue;
            $name = trim($c['firstname'] . ' ' . $c['lastname']);
            foreach (salesos_phone_variants(salesos_normalize_phone($c['phonenumber'])) as $v) {
                $this->db->replace(db_prefix() . 'salesos_phone_index', [
                    'phone'       => $v,
                    'entity_type' => 'contact',
                    'entity_id'   => $c['id'],
                    'entity_name' => $name,
                    'is_primary'  => ($v === $c['phonenumber']) ? 1 : 0,
                ]);
            }
        }

        // Clients
        $clients = $this->db->select('userid, company, phonenumber')
            ->get(db_prefix() . 'clients')->result_array();
        foreach ($clients as $cl) {
            if (empty($cl['phonenumber'])) continue;
            foreach (salesos_phone_variants(salesos_normalize_phone($cl['phonenumber'])) as $v) {
                $this->db->replace(db_prefix() . 'salesos_phone_index', [
                    'phone'       => $v,
                    'entity_type' => 'client',
                    'entity_id'   => $cl['userid'],
                    'entity_name' => $cl['company'],
                    'is_primary'  => ($v === $cl['phonenumber']) ? 1 : 0,
                ]);
            }
        }
    }

    public function index_lookup(string $phone): ?array
    {
        $normalized = salesos_normalize_phone($phone);
        $variants   = salesos_phone_variants($normalized);

        foreach ($variants as $v) {
            $row = $this->db->get_where(db_prefix() . 'salesos_phone_index', ['phone' => $v])->row_array();
            if ($row) return $row;
        }
        return null;
    }

    // ── CDR sync log ─────────────────────────────────────────────────────────

    public function get_last_sync(): ?array
    {
        $this->db->order_by('id', 'DESC');
        $this->db->limit(1);
        $row = $this->db->get(db_prefix() . 'salesos_cdr_sync')->row_array();
        return $row ?: null;
    }

    public function log_sync(array $data): void
    {
        $data['synced_at'] = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'salesos_cdr_sync', $data);
    }

    // ── PBX DB connection ─────────────────────────────────────────────────────

    public function get_pbx_db()
    {
        if ($this->pbx_db !== null) {
            return $this->pbx_db;
        }

        $base = [
            'username' => salesos_setting('salesos_pbx_db_user', '', 'SALESOS_PBX_DB_USER'),
            'password' => salesos_setting('salesos_pbx_db_password', '', 'SALESOS_PBX_DB_PASSWORD'),
            'database' => salesos_setting('salesos_pbx_db_name', 'asteriskcdrdb', 'SALESOS_PBX_DB_NAME'),
            'dbdriver' => 'mysqli',
            'pconnect' => false,
            'db_debug' => false,
        ];

        $configured_host = trim((string) salesos_setting('salesos_pbx_db_host', '127.0.0.1', 'SALESOS_PBX_DB_HOST'));
        $configured_port = (int) salesos_setting('salesos_pbx_db_port', 3306, 'SALESOS_PBX_DB_PORT');

        // Build candidate list: configured host first, hardcoded fallback last.
        // 127.0.0.1:3307 SSH-tunnel candidate is included only if explicitly set.
        $candidates = [['hostname' => $configured_host, 'port' => $configured_port]];
        if ($configured_host !== '103.42.4.210' || $configured_port !== 3306) {
            $candidates[] = ['hostname' => '103.42.4.210', 'port' => 3306];
        }
        $tunnel_host = trim((string) salesos_setting('salesos_pbx_db_conn_host', '', ''));
        $tunnel_port = (int) salesos_setting('salesos_pbx_db_conn_port', 0, '');
        if ($tunnel_host && $tunnel_port) {
            array_unshift($candidates, ['hostname' => $tunnel_host, 'port' => $tunnel_port]);
        }

        $prev_timeout = ini_set('default_socket_timeout', 5);

        foreach ($candidates as $candidate) {
            $config = array_merge($base, $candidate);
            try {
                $db = $this->load->database($config, true);
            } catch (Throwable $e) {
                log_message('info', '[SalesOS] PBX DB candidate threw: host=' . $candidate['hostname'] . ':' . $candidate['port'] . ' — ' . $e->getMessage());
                continue;
            }
            if ($db && $db->conn_id !== false) {
                ini_set('default_socket_timeout', $prev_timeout);
                $this->pbx_db = $db;
                return $this->pbx_db;
            }
            log_message('info', '[SalesOS] PBX DB candidate failed: host=' . $candidate['hostname'] . ':' . $candidate['port']);
        }

        ini_set('default_socket_timeout', $prev_timeout);
        return null;
    }

    public function fetch_pbx_cdr(string $since_datetime, int $limit = 100): array
    {
        $db = $this->get_pbx_db();
        if (!$db) {
            return [];
        }

        $db->where('calldate >', $since_datetime);
        $db->order_by('calldate', 'ASC');
        $db->limit($limit);
        return $db->get('cdr')->result_array();
    }

    /**
     * Find a PENDING call record that belongs to this CDR row.
     * For outbound calls the dialplan overwrites CallerID (so CDR src ≠ CRM src).
     * Match by dst + agent_id when available, falling back to dst + src.
     */
    public function find_pending_call(string $src, string $dst, string $calldate, ?int $agent_id = null): ?array
    {
        $window_start = date('Y-m-d H:i:s', strtotime($calldate) - 600);
        $window_end   = date('Y-m-d H:i:s', strtotime($calldate) + 600);

        $this->db->where('disposition', 'PENDING');
        $this->db->where('calldate >=', $window_start);
        $this->db->where('calldate <=', $window_end);

        // When CDR dst='s', the agent's MicroSIP was never answered so the
        // dialplan never ran — Asterisk records 's' as the dialed extension.
        // In that case we can't match on dst (CRM has the real number); fall
        // back to matching only on agent_id + calldate window.
        $dst_is_placeholder = ($dst === 's' || $dst === '');
        if (!$dst_is_placeholder) {
            $this->db->where('dst', $dst);
        }

        if ($agent_id !== null) {
            $this->db->where('agent_id', $agent_id);
        } elseif ($dst_is_placeholder) {
            // No agent_id and no real dst — can't safely match
            return null;
        } else {
            $this->db->where('src', $src);
        }

        $this->db->order_by('calldate', 'ASC');
        $this->db->limit(1);

        $row = $this->db->get(db_prefix() . 'salesos_calls')->row_array();
        return $row ?: null;
    }

    /**
     * Mark PENDING calls older than $minutes as UNKNOWN so they don't
     * linger forever when the CDR was lost (e.g. Asterisk restart before batch flush).
     */
    public function expire_stale_pending_calls(int $minutes = 30): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));
        $this->db->where('disposition', 'PENDING');
        $this->db->where('calldate <', $cutoff);
        $this->db->update(db_prefix() . 'salesos_calls', ['disposition' => 'UNKNOWN']);
        return $this->db->affected_rows();
    }
}
