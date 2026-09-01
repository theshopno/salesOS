<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Salesos_model extends App_Model
{
    public function get_calls_for_entity(string $entity_type, int $entity_id, int $limit = 25): array
    {
        $column = $entity_type . '_id'; // lead_id | contact_id | client_id
        if (!in_array($column, ['lead_id', 'contact_id', 'client_id'], true)) {
            return [];
        }

        return $this->db->select('c.*, CONCAT(s.firstname, " ", s.lastname) as agent_name')
            ->from(db_prefix() . 'salesos_calls c')
            ->join(db_prefix() . 'salesos_agents a', 'a.staff_id = c.agent_id', 'left')
            ->join(db_prefix() . 'staff s', 's.staffid = c.agent_id', 'left')
            ->where($column, $entity_id)
            ->order_by('c.calldate', 'desc')
            ->limit($limit)
            ->get()->result_array();
    }

    public function save_wrapup(int $call_id, string $disposition_code, string $notes): bool
    {
        return (bool) $this->db->where('id', $call_id)->update(db_prefix() . 'salesos_calls', [
            'disposition_code' => $disposition_code,
            'wrapup_notes'     => $notes,
        ]);
    }

    public function get_call(int $call_id): ?array
    {
        $row = $this->db->where('id', $call_id)->get(db_prefix() . 'salesos_calls')->row_array();

        return $row ?: null;
    }
}
