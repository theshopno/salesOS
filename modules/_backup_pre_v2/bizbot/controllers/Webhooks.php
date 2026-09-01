<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Webhooks extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('bizbot/bizbot_api');
        $this->load->model('leads_model');
        $this->load->database();
        $this->load->helper('perfex_crm');
    }

    public function index()
    {
        $raw_payload = file_get_contents('php://input');
        $this->log_webhook($raw_payload);

        $payload = json_decode($raw_payload, true);

        if (!$payload || !isset($payload['event'])) {
            return;
        }

        $event = $payload['event'];
        $data = $payload['data'];

        switch ($event) {
            case 'chat.message.created':
                $this->handle_message($data);
                break;

            case 'chat.resolved':
            case 'chat.archived':
                $this->handle_chat_status($data);
                break;
        }
    }

    private function handle_message($data)
    {
        $message = isset($data['message']['message']) ? $data['message']['message'] : '';
        $thread_guid = isset($data['thread']) ? $data['thread'] : (isset($data['message']['thread_guid']) ? $data['message']['thread_guid'] : '');
        $contact_guid = isset($data['message']['from_guid']) ? $data['message']['from_guid'] : '';

        if (preg_match('/#lead|#crm/i', $message)) {
            $this->sync_lead($contact_guid, $thread_guid, 'Keyword Trigger');
        }

        $this->sync_history($data);
    }

    private function handle_chat_status($data)
    {
        $contact_guid = isset($data['chat']['contact']) ? $data['chat']['contact'] : '';
        $thread_guid = isset($data['thread']) ? $data['thread'] : '';

        if (empty($contact_guid)) return;

        $contact = $this->bizbot_api->get_contact($contact_guid);

        if ($contact['status'] === 'success') {
            $labels = isset($contact['data']['labels']) ? $contact['data']['labels'] : [];
            if (in_array('CRM', $labels) || in_array('crm', $labels)) {
                $this->sync_lead($contact_guid, $thread_guid, 'Tag Trigger');
            }
        }
    }

    private function sync_lead($contact_guid, $thread_guid, $source = 'Bizbot')
    {
        $contact_info = $this->bizbot_api->get_contact($contact_guid);
        if ($contact_info['status'] !== 'success') return;

        $cdata = $contact_info['data'];
        $phone = isset($cdata['phone']) ? preg_replace('/[^0-9]/', '', $cdata['phone']) : '';
        $name = isset($cdata['name']) ? $cdata['name'] : 'Bizbot Lead';

        if (empty($phone)) return;

        $this->db->where('phonenumber', $phone);
        $existing = $this->db->get(db_prefix() . 'leads')->row();

        if ($existing) {
            $this->update_lead_custom_field($existing->id, $thread_guid);
        } else {
            $lead_data = [
                'name'        => $name,
                'phonenumber' => $phone,
                'source'      => $this->get_bizbot_source_id(),
                'status'      => 1,
                'description' => "Synced via Bizbot ($source). Thread: $thread_guid",
            ];
            $lead_id = $this->leads_model->add($lead_data);
            if ($lead_id) {
                $this->update_lead_custom_field($lead_id, $thread_guid);
            }
        }
    }

    private function get_bizbot_source_id()
    {
        $this->db->where('name', 'Bizbot');
        $source = $this->db->get(db_prefix() . 'leads_sources')->row();
        if ($source) return $source->id;

        $this->db->insert(db_prefix() . 'leads_sources', ['name' => 'Bizbot']);
        return $this->db->insert_id();
    }

    private function update_lead_custom_field($lead_id, $thread_guid)
    {
        $this->db->where('slug', 'leads_bizbot_thread_guid');
        $field = $this->db->get(db_prefix() . 'customfields')->row();

        if ($field && !empty($thread_guid)) {
            $this->db->where('relid', $lead_id);
            $this->db->where('fieldid', $field->id);
            $this->db->where('fieldto', 'leads');
            $exists = $this->db->get(db_prefix() . 'customfieldsvalues')->row();

            if ($exists) {
                $this->db->where('id', $exists->id);
                $this->db->update(db_prefix() . 'customfieldsvalues', ['value' => $thread_guid]);
            } else {
                $this->db->insert(db_prefix() . 'customfieldsvalues', [
                    'relid'   => $lead_id,
                    'fieldid' => $field->id,
                    'fieldto' => 'leads',
                    'value'   => $thread_guid
                ]);
            }
        }
    }

    private function sync_history($data)
    {
        $thread_guid = isset($data['thread']) ? $data['thread'] : (isset($data['message']['thread_guid']) ? $data['message']['thread_guid'] : '');
        $message_id = isset($data['message']['id']) ? $data['message']['id'] : '';
        $message_text = isset($data['message']['message']) ? $data['message']['message'] : '';
        $is_bot = isset($data['message']['is_bot']) ? $data['message']['is_bot'] : false;

        if (empty($thread_guid) || empty($message_text)) return;

        $this->db->select('relid');
        $this->db->from(db_prefix() . 'customfieldsvalues');
        $this->db->join(db_prefix() . 'customfields', db_prefix() . 'customfields.id = ' . db_prefix() . 'customfieldsvalues.fieldid');
        $this->db->where('slug', 'leads_bizbot_thread_guid');
        $this->db->where('value', $thread_guid);
        $lead = $this->db->get()->row();

        if ($lead) {
            $this->db->insert(db_prefix() . 'bizbot_chat_history', [
                'lead_id'    => $lead->relid,
                'message_id' => $message_id,
                'sender'     => $is_bot ? 'Agent' : 'Customer',
                'message'    => $message_text,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }

    private function log_webhook($payload)
    {
        $log_file = APPPATH . '../modules/bizbot/webhook_debug.log';
        file_put_contents($log_file, "--- REC " . date('Y-m-d H:i:s') . " ---\n" . $payload . "\n\n", FILE_APPEND);
    }
}
