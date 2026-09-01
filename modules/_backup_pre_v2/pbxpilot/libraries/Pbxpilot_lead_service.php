<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Pbxpilot_lead_service
{
    private $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->model('pbxpilot/pbxpilot_model');
    }

    /**
     * Map a call record to a lead based on phone number
     */
    public function map_to_lead($call_id)
    {
        $call = $this->CI->pbxpilot_model->get_call($call_id);
        if (!$call || empty($call->phone_number)) {
            return false;
        }

        $lead = $this->CI->pbxpilot_model->find_lead_by_phone($call->phone_number);
        if ($lead) {
            $this->CI->pbxpilot_model->update_call($call_id, ['lead_id' => $lead->id]);
            return $lead->id;
        }

        return false;
    }

    public function create_lead_note($call_id)
    {
        $call = $this->CI->pbxpilot_model->get_call($call_id);
        if (!$call || !$call->lead_id || empty($call->ai_summary)) {
            return false;
        }

        $presets = get_pbxpilot_presets();
        $preset_name = isset($presets[$call->preset_type]) ? $presets[$call->preset_type]['name'] : $call->preset_type;

        $note_content = "SYSTEM: PBXPilot AI Call Briefing\r\n";
        $note_content .= "----------------------------------\r\n\r\n";
        $note_content .= "Call Type: " . pbxpilot_format_call_type($call->call_type) . "\r\n";
        $note_content .= "Preset: " . $preset_name . "\r\n";
        $note_content .= "Phone: " . $call->phone_number . "\r\n";
        $note_content .= "Duration: " . $call->duration_seconds . "s\r\n\r\n";
        $note_content .= "CALL SUMMARY:\r\n" . $call->ai_summary;

        $this->CI->db->insert(db_prefix() . 'notes', [
            'rel_id' => $call->lead_id,
            'rel_type' => 'lead',
            'description' => nl2br($note_content),
            'addedfrom' => $call->uploaded_by,
            'dateadded' => date('Y-m-d H:i:s'),
        ]);

        return $this->CI->db->insert_id();
    }
    /**
     * Get contact info across leads and clients by phone number
     */
    public function get_contact_info($phone)
    {
        if (empty($phone)) return null;

        // 1. Check Leads
        $this->CI->db->where('phonenumber', $phone);
        $this->CI->db->or_like('phonenumber', $phone, 'both');
        $lead = $this->CI->db->get(db_prefix() . 'leads')->row();

        if ($lead) {
            return [
                'name' => $lead->name,
                'rel_id' => $lead->id,
                'rel_type' => 'lead',
                'link' => admin_url('leads/index/' . $lead->id)
            ];
        }

        // 2. Check Clients (Contacts)
        $this->CI->db->where('phonenumber', $phone);
        $this->CI->db->or_like('phonenumber', $phone, 'both');
        $contact = $this->CI->db->get(db_prefix() . 'contacts')->row();

        if ($contact) {
            // Get client name
            $this->CI->db->where('userid', $contact->userid);
            $client = $this->CI->db->get(db_prefix() . 'clients')->row();
            return [
                'name' => $contact->firstname . ' ' . $contact->lastname . ($client ? ' (' . $client->company . ')' : ''),
                'rel_id' => $contact->userid,
                'rel_type' => 'customer',
                'link' => admin_url('clients/client/' . $contact->userid)
            ];
        }

        return null;
    }

    public function inject_to_notes($call_id, $summary_text)
    {
        $call = $this->CI->pbxpilot_model->get_call($call_id);
        if (!$call) return false;

        $contact = $this->get_contact_info($call->phone_number);
        if (!$contact) return false;

        $note_content = "SYSTEM: PBXPilot AI Call Briefing (Manual)\r\n";
        $note_content .= "-------------------------------------------\r\n\r\n";
        $note_content .= "Call Type: " . pbxpilot_format_call_type($call->call_type) . "\r\n";
        $note_content .= "Phone: " . $call->phone_number . "\r\n";
        $note_content .= "Duration: " . $call->duration_seconds . "s\r\n\r\n";
        $note_content .= "CALL SUMMARY:\r\n" . $summary_text;

        $this->CI->db->insert(db_prefix() . 'notes', [
            'rel_id' => $contact['rel_id'],
            'rel_type' => $contact['rel_type'],
            'description' => nl2br($note_content),
            'addedfrom' => get_staff_user_id() ?: $call->uploaded_by,
            'dateadded' => date('Y-m-d H:i:s'),
        ]);

        return $this->CI->db->insert_id();
    }
}
