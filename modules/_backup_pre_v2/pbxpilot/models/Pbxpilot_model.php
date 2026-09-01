<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Pbxpilot_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->maybe_upgrade();
    }

    /**
     * Check and add missing columns automatically
     */
    private function maybe_upgrade()
    {
        $table = db_prefix() . 'pbxpilot_calls';

        if (!$this->db->field_exists('duration_seconds', $table)) {
            $this->db->query("ALTER TABLE `$table` ADD `duration_seconds` INT(11) DEFAULT 0 AFTER `audio_hash`;");
        }

        if (!$this->db->field_exists('last_error', $table)) {
            $this->db->query("ALTER TABLE `$table` ADD `last_error` TEXT AFTER `is_duplicate`;");
        }

        if (!$this->db->field_exists('last_model_used', $table)) {
            $this->db->query("ALTER TABLE `$table` ADD `last_model_used` VARCHAR(50) AFTER `ai_provider`;");
        }

        if (!$this->db->field_exists('staff_id', $table)) {
            $this->db->query("ALTER TABLE `$table` ADD `staff_id` INT(11) AFTER `uploaded_by`;");
        }

        if (!$this->db->field_exists('unique_call_id', $table)) {
            $this->db->query("ALTER TABLE `$table` ADD `unique_call_id` VARCHAR(100) AFTER `duration_seconds`;");
        }

        if (!$this->db->field_exists('summary_history', $table)) {
            $this->db->query("ALTER TABLE `$table` ADD `summary_history` LONGTEXT AFTER `ai_summary`;");
        }

        if (!$this->db->field_exists('last_case_used', $table)) {
            $this->db->query("ALTER TABLE `$table` ADD `last_case_used` VARCHAR(100) AFTER `preset_type`;");
        }
    }

    /**
     * Add a new call record
     */
    public function add_call($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'pbxpilot_calls', $data);
        return $this->db->insert_id();
    }

    /**
     * Get call by ID
     */
    public function get_call($id)
    {
        $this->db->where('id', $id);
        return $this->db->get(db_prefix() . 'pbxpilot_calls')->row();
    }

    /**
     * Get call by audio hash
     */
    public function get_call_by_hash($hash)
    {
        $this->db->where('audio_hash', $hash);
        return $this->db->get(db_prefix() . 'pbxpilot_calls')->row();
    }

    /**
     * Update call record
     */
    public function update_call($id, $data)
    {
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'pbxpilot_calls', $data);
        return $this->db->affected_rows() > 0;
    }

    /**
     * Get calls for dashboard
     */
    public function get_calls($where = [])
    {
        $this->db->where($where);
        $this->db->order_by('created_at', 'DESC');
        return $this->db->get(db_prefix() . 'pbxpilot_calls')->result_array();
    }

    /**
     * Find lead by phone number
     */
    public function find_lead_by_phone($phone)
    {
        // Remove non-numeric characters for searching if needed, 
        // but Perfex usually stores exactly what is entered.
        // We'll try exact match first, then partial if needed.

        $this->db->group_start();
        $this->db->where('phonenumber', $phone);
        $this->db->or_like('phonenumber', $phone, 'both');
        $this->db->group_end();

        return $this->db->get(db_prefix() . 'leads')->row();
    }

    /**
     * Delete calls older than X days
     */
    public function get_old_calls($days)
    {
        $date = date('Y-m-d H:i:s', strtotime("-$days days"));
        $this->db->where('created_at <', $date);
        $this->db->where('deleted_at', NULL);
        return $this->db->get(db_prefix() . 'pbxpilot_calls')->result_array();
    }
}
