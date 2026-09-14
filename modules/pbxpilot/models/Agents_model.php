<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Agents_model extends App_Model
{
    public function get_by_staff_id(int $staff_id): ?array
    {
        $row = $this->db->where('staff_id', $staff_id)
            ->where('is_active', 1)
            ->get(db_prefix() . 'pbxpilot_agents')
            ->row_array();

        return $row ?: null;
    }

    public function get_all(): array
    {
        return $this->db->select('a.*, CONCAT(s.firstname, " ", s.lastname) as staff_name')
            ->from(db_prefix() . 'pbxpilot_agents a')
            ->join(db_prefix() . 'staff s', 's.staffid = a.staff_id', 'left')
            ->order_by('a.is_active', 'desc')
            ->get()->result_array();
    }

    public function save(int $staff_id, string $extension, int $id = 0): bool
    {
        $data = ['staff_id' => $staff_id, 'extension' => $extension];

        if ($id > 0) {
            return (bool) $this->db->where('id', $id)->update(db_prefix() . 'pbxpilot_agents', $data);
        }

        return (bool) $this->db->insert(db_prefix() . 'pbxpilot_agents', $data);
    }

    public function delete(int $id): bool
    {
        return (bool) $this->db->where('id', $id)->delete(db_prefix() . 'pbxpilot_agents');
    }
}
