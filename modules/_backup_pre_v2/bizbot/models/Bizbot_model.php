<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Bizbot_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function log_activity($data)
    {
        $data['recorded_at'] = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'bizbot_logs', $data);
        return $this->db->insert_id();
    }

    public function get_logs($limit = 500)
    {
        $this->db->order_by('recorded_at', 'DESC');
        $this->db->limit($limit);
        return $this->db->get(db_prefix() . 'bizbot_logs')->result_array();
    }

    public function get_template($event_slug)
    {
        $this->db->where('event_slug', $event_slug);
        return $this->db->get(db_prefix() . 'bizbot_templates')->row();
    }

    public function update_template($event_slug, $data)
    {
        // Check if exists
        $this->db->where('event_slug', $event_slug);
        $exists = $this->db->get(db_prefix() . 'bizbot_templates')->row();

        if ($exists) {
            $this->db->where('event_slug', $event_slug);
            $this->db->update(db_prefix() . 'bizbot_templates', $data);
        } else {
            $data['event_slug'] = $event_slug;
            $this->db->insert(db_prefix() . 'bizbot_templates', $data);
        }
    }

    public function get_all_templates()
    {
        return $this->db->get(db_prefix() . 'bizbot_templates')->result_array();
    }

    public function get_followups()
    {
        $this->db->order_by('step_number', 'ASC');
        return $this->db->get(db_prefix() . 'bizbot_followups')->result_array();
    }

    public function add_followup($data)
    {
        $this->db->insert(db_prefix() . 'bizbot_followups', $data);
        return $this->db->insert_id();
    }

    public function delete_followup($id)
    {
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'bizbot_followups');
        return $this->db->affected_rows() > 0;
    }

    public function get_followup($id)
    {
        $this->db->where('id', $id);
        return $this->db->get(db_prefix() . 'bizbot_followups')->row();
    }

    public function update_followup($id, $data)
    {
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'bizbot_followups', $data);
        return $this->db->affected_rows() >= 0;
    }

    public function delete_old_logs($days = 30)
    {
        if ($days === 'all') {
            $this->db->empty_table(db_prefix() . 'bizbot_logs');
            return true;
        }

        // Use SQL-based date comparison for better reliability across timezones
        $this->db->where("recorded_at < DATE_SUB(NOW(), INTERVAL " . (int)$days . " DAY)");
        $this->db->delete(db_prefix() . 'bizbot_logs');
        return $this->db->affected_rows();
    }

    public function get_log($id)
    {
        $this->db->where('id', $id);
        return $this->db->get(db_prefix() . 'bizbot_logs')->row();
    }

    public function delete_log($id)
    {
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'bizbot_logs');
        return $this->db->affected_rows() > 0;
    }

    public function cancel_queue_item($id)
    {
        $this->db->where('id', $id);
        $this->db->where('status', 'pending');
        $this->db->update(db_prefix() . 'bizbot_queue', ['status' => 'cancelled']);
        return $this->db->affected_rows() > 0;
    }

    public function delete_queue_item($id)
    {
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'bizbot_queue');
        return $this->db->affected_rows() > 0;
    }
}
