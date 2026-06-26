<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Agents_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function get_all(bool $active_only = false): array
    {
        $this->db->select('a.*, s.firstname, s.lastname, s.email, s.profile_image');
        $this->db->from(db_prefix() . 'salesos_agents a');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = a.staff_id', 'left');
        if ($active_only) {
            $this->db->where('a.is_active', 1);
        }
        $this->db->order_by('s.firstname', 'ASC');
        return $this->db->get()->result_array();
    }

    public function get_by_id(int $id): ?array
    {
        $this->db->select('a.*, s.firstname, s.lastname, s.email');
        $this->db->from(db_prefix() . 'salesos_agents a');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = a.staff_id', 'left');
        $this->db->where('a.id', $id);
        $row = $this->db->get()->row_array();
        return $row ?: null;
    }

    public function get_by_staff_id(int $staff_id): ?array
    {
        $row = $this->db->get_where(db_prefix() . 'salesos_agents', ['staff_id' => $staff_id])->row_array();
        return $row ?: null;
    }

    public function get_by_extension(string $extension): ?array
    {
        $this->db->select('a.*, s.firstname, s.lastname, s.email, s.staffid');
        $this->db->from(db_prefix() . 'salesos_agents a');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = a.staff_id', 'left');
        $this->db->where('a.extension', $extension);
        $row = $this->db->get()->row_array();
        return $row ?: null;
    }

    public function create(array $data): int|false
    {
        $this->db->insert(db_prefix() . 'salesos_agents', $data);
        return $this->db->insert_id() ?: false;
    }

    public function update(int $id, array $data): bool
    {
        $this->db->where('id', $id);
        return $this->db->update(db_prefix() . 'salesos_agents', $data);
    }

    public function delete(int $id): bool
    {
        return $this->db->delete(db_prefix() . 'salesos_agents', ['id' => $id]);
    }

    public function extension_exists(string $extension, int $exclude_id = 0): bool
    {
        $this->db->where('extension', $extension);
        if ($exclude_id) {
            $this->db->where('id !=', $exclude_id);
        }
        return $this->db->count_all_results(db_prefix() . 'salesos_agents') > 0;
    }

    public function set_login_state(int $staff_id, bool $logged_in): void
    {
        $this->db->where('staff_id', $staff_id);
        $this->db->update(db_prefix() . 'salesos_agents', [
            'is_logged_in' => $logged_in ? 1 : 0,
            'last_seen'    => date('Y-m-d H:i:s'),
        ]);
    }

    public function get_logged_in(): array
    {
        $this->db->select('a.*, s.firstname, s.lastname');
        $this->db->from(db_prefix() . 'salesos_agents a');
        $this->db->join(db_prefix() . 'staff s', 's.staffid = a.staff_id', 'left');
        $this->db->where('a.is_logged_in', 1);
        $this->db->where('a.is_active', 1);
        return $this->db->get()->result_array();
    }

    // Returns extension→agent_id map for fast CDR matching
    public function get_extension_map(): array
    {
        $agents = $this->get_all(true);
        $map    = [];
        foreach ($agents as $a) {
            $map[$a['extension']] = $a;
        }
        return $map;
    }
}
