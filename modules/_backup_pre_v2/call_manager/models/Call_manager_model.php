<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Call_manager_model extends App_Model
{
    private $pbx_db = null;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Connect to Issabel PBX Database
     */
    private function connect_pbx()
    {
        if ($this->pbx_db !== null) {
            return $this->pbx_db;
        }

        $config = [
            'hostname' => get_option('call_manager_pbp_db_host'),
            'username' => get_option('call_manager_pbp_db_user'),
            'password' => get_option('call_manager_pbp_db_password'),
            'database' => get_option('call_manager_pbp_db_name'),
            'dbdriver' => 'mysqli',
            'pconnect' => FALSE,
            'db_debug' => FALSE,
        ];

        $this->pbx_db = $this->load->database($config, TRUE);
        return $this->pbx_db;
    }

    /**
     * Get Call History for a specific phone number
     */
    public function get_call_history($phone)
    {
        $db = $this->connect_pbx();
        if (!$db || ($db->conn_id === FALSE)) {
            return [];
        }

        // Clean phone number (remove leading zero or common country codes if necessary)
        // For now, assume exact match or simple wildcard
        $phone = preg_replace('/\D/', '', $phone);

        $db->select('*');
        $db->from('cdr');
        $db->group_start();
        $db->like('src', $phone);
        $db->or_like('dst', $phone);
        $db->group_end();
        $db->order_by('calldate', 'DESC');
        $db->limit(50);

        return $db->get()->result_array();
    }

    /**
     * Get recording file path from uniqueid
     */
    public function get_recording_path($uniqueid)
    {
        $db = $this->connect_pbx();
        if (!$db || ($db->conn_id === FALSE)) {
            return null;
        }

        $db->where('uniqueid', $uniqueid);
        $res = $db->get('cdr')->row();

        if ($res && !empty($res->recordingfile)) {
            return $res->recordingfile;
        }

        return null;
    }
}
