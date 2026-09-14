<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Pbxpilot_model extends App_Model
{
    public function get_calls_for_entity(string $entity_type, int $entity_id, int $limit = 25): array
    {
        $column = $entity_type . '_id'; // lead_id | contact_id | client_id
        if (!in_array($column, ['lead_id', 'contact_id', 'client_id'], true)) {
            return [];
        }

        return $this->db->select('c.*, CONCAT(s.firstname, " ", s.lastname) as agent_name')
            ->from(db_prefix() . 'pbxpilot_calls c')
            ->join(db_prefix() . 'pbxpilot_agents a', 'a.staff_id = c.agent_id', 'left')
            ->join(db_prefix() . 'staff s', 's.staffid = c.agent_id', 'left')
            ->where($column, $entity_id)
            ->order_by('c.calldate', 'desc')
            ->limit($limit)
            ->get()->result_array();
    }

    public function save_wrapup(int $call_id, string $disposition_code, string $notes): bool
    {
        return (bool) $this->db->where('id', $call_id)->update(db_prefix() . 'pbxpilot_calls', [
            'disposition_code' => $disposition_code,
            'wrapup_notes'     => $notes,
        ]);
    }

    public function get_call(int $call_id): ?array
    {
        $row = $this->db->where('id', $call_id)->get(db_prefix() . 'pbxpilot_calls')->row_array();

        return $row ?: null;
    }

    // ── Dashboard / reporting ────────────────────────────────────────────────

    /**
     * A call counts as "effective" when it was actually answered and lasted
     * at least $effective_seconds — a configurable stand-in for "a real
     * conversation happened," not just "the line picked up." Duration alone
     * is an imperfect signal (a fast, decisive "not interested" is real
     * contact even if short) but it's the one built from data everyone
     * already has, with the threshold left tunable rather than hardcoded so
     * it can be adjusted for how this team's calls actually run.
     */
    public function get_dashboard_stats(string $date_from, string $date_to, int $effective_seconds, int $agent_id = 0): array
    {
        $base = function () use ($date_from, $date_to, $agent_id) {
            $this->db->where('calldate >=', $date_from . ' 00:00:00');
            $this->db->where('calldate <=', $date_to . ' 23:59:59');
            if ($agent_id > 0) {
                $this->db->where('agent_id', $agent_id);
            }
        };

        $base();
        $total = (int) $this->db->count_all_results(db_prefix() . 'pbxpilot_calls');

        $base();
        $answered = (int) $this->db->where('disposition', 'ANSWERED')->count_all_results(db_prefix() . 'pbxpilot_calls');

        $base();
        $effective = (int) $this->db->where('disposition', 'ANSWERED')
            ->where('billsec >=', $effective_seconds)
            ->count_all_results(db_prefix() . 'pbxpilot_calls');

        // Agent spoke to a lead (call was answered and matched to a lead)
        // but never added a Perfex lead note ("I got in touch" / "I have not
        // contacted this lead") for it that same day — Perfex's own contact-
        // tracking system, not pbxpilot's own wrap-up field. Scoped to
        // match_type='lead' because that note feature only exists on leads
        // in Perfex, not on contacts/clients — there's nothing to "forget to
        // log" on a call that never matched a lead in the first place.
        $base();
        $no_lead_note = (int) $this->db->where('disposition', 'ANSWERED')
            ->where('match_type', 'lead') // lead_id is always set when match_type='lead' (see Cdr_sync_service::match_entity)
            ->where(
                'NOT EXISTS (SELECT 1 FROM ' . db_prefix() . 'notes n'
                . ' WHERE n.rel_type = "lead" AND n.rel_id = ' . db_prefix() . 'pbxpilot_calls.lead_id'
                . ' AND n.addedfrom = ' . db_prefix() . 'pbxpilot_calls.agent_id'
                . ' AND DATE(n.dateadded) = DATE(' . db_prefix() . 'pbxpilot_calls.calldate))',
                null,
                false
            )
            ->count_all_results(db_prefix() . 'pbxpilot_calls');

        $base();
        $this->db->where('direction', 'inbound');
        $inbound = (int) $this->db->count_all_results(db_prefix() . 'pbxpilot_calls');

        $base();
        $this->db->where('direction', 'outbound');
        $outbound = (int) $this->db->count_all_results(db_prefix() . 'pbxpilot_calls');

        $base();
        $this->db->select_sum('billsec');
        $total_billsec = (int) ($this->db->get(db_prefix() . 'pbxpilot_calls')->row_array()['billsec'] ?? 0);

        return [
            'total'          => $total,
            'answered'       => $answered,
            'effective'      => $effective,
            'no_lead_note'   => $no_lead_note,
            'inbound'        => $inbound,
            'outbound'       => $outbound,
            'total_billsec'  => $total_billsec,
            'avg_billsec'    => $answered > 0 ? (int) round($total_billsec / $answered) : 0,
        ];
    }

    /** Per-agent breakdown for the same date range — one row per agent, plus totals. */
    public function get_agent_breakdown(string $date_from, string $date_to, int $effective_seconds): array
    {
        $from_esc = $this->db->escape($date_from . ' 00:00:00');
        $to_esc   = $this->db->escape($date_to . ' 23:59:59');

        $notes_table = db_prefix() . 'notes';

        return $this->db->select(
                'a.staff_id, CONCAT(s.firstname, " ", s.lastname) as agent_name, a.extension,'
                . ' COUNT(c.id) as total,'
                . ' SUM(CASE WHEN c.disposition = "ANSWERED" THEN 1 ELSE 0 END) as answered,'
                . ' SUM(CASE WHEN c.disposition = "ANSWERED" AND c.billsec >= ' . (int) $effective_seconds . ' THEN 1 ELSE 0 END) as effective,'
                // Answered + matched to a lead, but no Perfex lead note ("I got in
                // touch" / "I have not contacted this lead") from this agent that
                // same day — see get_dashboard_stats() for why it's lead-scoped.
                . ' SUM(CASE WHEN c.disposition = "ANSWERED" AND c.match_type = "lead" AND NOT EXISTS ('
                . "   SELECT 1 FROM {$notes_table} n WHERE n.rel_type = \"lead\" AND n.rel_id = c.lead_id"
                . '   AND n.addedfrom = c.agent_id AND DATE(n.dateadded) = DATE(c.calldate)'
                . ' ) THEN 1 ELSE 0 END) as no_lead_note,'
                . ' COALESCE(SUM(c.billsec), 0) as total_billsec'
            )
            ->from(db_prefix() . 'pbxpilot_agents a')
            ->join(db_prefix() . 'staff s', 's.staffid = a.staff_id', 'left')
            ->join(
                db_prefix() . 'pbxpilot_calls c',
                "c.agent_id = a.staff_id AND c.calldate >= {$from_esc} AND c.calldate <= {$to_esc}",
                'left'
            )
            ->where('a.is_active', 1)
            ->group_by('a.staff_id')
            ->order_by('total', 'desc')
            ->get()->result_array();
    }

    /** @return array{rows: array, total: int} */
    public function get_calls_list(array $filters, int $limit, int $offset): array
    {
        $apply = function () use ($filters) {
            if (!empty($filters['date_from'])) {
                $this->db->where('c.calldate >=', $filters['date_from'] . ' 00:00:00');
            }
            if (!empty($filters['date_to'])) {
                $this->db->where('c.calldate <=', $filters['date_to'] . ' 23:59:59');
            }
            if (!empty($filters['agent_id'])) {
                $this->db->where('c.agent_id', (int) $filters['agent_id']);
            }
            if (!empty($filters['direction'])) {
                $this->db->where('c.direction', $filters['direction']);
            }
            if (!empty($filters['disposition'])) {
                $this->db->where('c.disposition', $filters['disposition']);
            }
            if (!empty($filters['effective_only'])) {
                $this->db->where('c.disposition', 'ANSWERED');
                $this->db->where('c.billsec >=', (int) $filters['effective_seconds']);
            }
            if (!empty($filters['no_lead_note_only'])) {
                $notes_table = db_prefix() . 'notes';
                $this->db->where('c.disposition', 'ANSWERED');
                $this->db->where('c.match_type', 'lead');
                $this->db->where(
                    "NOT EXISTS (SELECT 1 FROM {$notes_table} n WHERE n.rel_type = \"lead\""
                    . ' AND n.rel_id = c.lead_id AND n.addedfrom = c.agent_id'
                    . ' AND DATE(n.dateadded) = DATE(c.calldate))',
                    null,
                    false
                );
            }
            if (!empty($filters['search'])) {
                $q = $this->db->escape_like_str($filters['search']);
                $this->db->group_start()
                    ->like('c.src', $q)
                    ->or_like('c.dst', $q)
                    ->group_end();
            }
        };

        $this->db->from(db_prefix() . 'pbxpilot_calls c');
        $apply();
        $total = (int) $this->db->count_all_results();

        $rows = $this->db->select(
                'c.*, CONCAT(s.firstname, " ", s.lastname) as agent_name,'
                . ' l.name as lead_name, CONCAT(ct.firstname, " ", ct.lastname) as contact_name,'
                . ' ct.userid as contact_client_id'
            )
            ->from(db_prefix() . 'pbxpilot_calls c')
            ->join(db_prefix() . 'staff s', 's.staffid = c.agent_id', 'left')
            ->join(db_prefix() . 'leads l', 'l.id = c.lead_id', 'left')
            ->join(db_prefix() . 'contacts ct', 'ct.id = c.contact_id', 'left');
        $apply();
        $rows = $rows->order_by('c.calldate', 'desc')
            ->limit($limit, $offset)
            ->get()->result_array();

        return ['rows' => $rows, 'total' => $total];
    }

    /** Distinct dispositions actually seen, for the filter dropdown — not a hardcoded list. */
    public function get_known_dispositions(): array
    {
        return array_column(
            $this->db->select('disposition')->distinct()
                ->where('disposition !=', '')
                ->get(db_prefix() . 'pbxpilot_calls')->result_array(),
            'disposition'
        );
    }
}
