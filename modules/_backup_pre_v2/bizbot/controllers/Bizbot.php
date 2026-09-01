<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Bizbot extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('bizbot/bizbot_model');
        $this->load->library('bizbot/bizbot_api');
    }

    public function settings()
    {
        if (!has_permission('settings', '', 'view')) {
            access_denied('Settings');
        }

        if ($this->input->post()) {
            if ($this->input->post('test_message_number')) {
                // Handle Test Message
                $phone = $this->input->post('test_message_number');
                $message = "This is a test message from BizBot Module.";
                $response = $this->bizbot_api->send_message($phone, $message, 'manual_test');

                if ($response['status'] == 'success') {
                    set_alert('success', _l('bizbot_test_success'));
                }
                else {
                    $error_msg = isset($response['response']) ? json_encode($response['response']) : $response['message'];
                    set_alert('warning', _l('bizbot_test_failed', $error_msg));
                }
            }
            else {
                // Save Settings
                $data = $this->input->post();
                if (isset($data['settings'])) {
                    // SECURE: Whitelist allowed settings keys to prevent overwriting core CRM options
                    $allowed_settings = [
                        'bizbot_api_key',
                        'bizbot_channel_guid',
                        'bizbot_ssl_verify',
                        'bizbot_debug_mode',
                        'bizbot_notification_admins',
                        'bizbot_bulk_delay',
                        'bizbot_widget_id',
                        'bizbot_widget_type',
                        'bizbot_widget_show_to_admin',
                        'bizbot_widget_show_to_customer',
                    ];

                    foreach ($data['settings'] as $key => $val) {
                        if (!in_array($key, $allowed_settings)) {
                            continue;
                        }
                        if (is_array($val)) {
                            $val = json_encode($val);
                        }
                        update_option($key, $val);
                    }
                    set_alert('success', _l('settings_updated'));
                }
            }
            redirect(admin_url('bizbot/settings'));
        }

        $this->load->model('staff_model');
        $data['staff'] = $this->staff_model->get('', ['active' => 1]);
        $data['title'] = _l('bizbot_settings');
        $this->load->view('bizbot/settings', $data);
    }

    public function templates()
    {
        if (!has_permission('settings', '', 'view')) {
            access_denied('Templates');
        }

        if ($this->input->post()) {
            $data = $this->input->post();
            if (isset($data['templates'])) {
                foreach ($data['templates'] as $event => $template_data) {
                    $template_data['active'] = isset($template_data['active']) ? 1 : 0;
                    $template_data['send_to_customer'] = isset($template_data['send_to_customer']) ? 1 : 0;
                    $template_data['send_to_staff'] = isset($template_data['send_to_staff']) ? 1 : 0;
                    $template_data['send_to_admin'] = isset($template_data['send_to_admin']) ? 1 : 0;
                    $template_data['send_to_followers'] = isset($template_data['send_to_followers']) ? 1 : 0;

                    $this->bizbot_model->update_template($event, $template_data);
                }
                set_alert('success', _l('bizbot_templates_updated'));
            }
            redirect(admin_url('bizbot/templates'));
        }

        $data['title'] = _l('bizbot_templates');
        $data['categories'] = [
            'Leads' => [
                'lead_created' => 'Lead Created',
                'lead_assigned' => 'Lead Assigned',
                'lead_status_changed' => 'Lead Status Changed',
                'lead_lost' => 'Lost Lead',
                'lead_converted' => 'Lead Converted',
                'lead_reminder' => 'Lead Reminder (Customer)',
                'lead_staff_reminder' => 'Lead Staff Reminder',
            ],
            'Customers' => [
                'client_created' => 'Client Created',
                'client_login' => 'Client Login',
                'client_updated' => 'Client Updated',
            ],
            'Tasks' => [
                'task_created' => 'Task Created',
                'task_assigned' => 'Task Assigned',
                'task_status_changed' => 'Task Status Changed',
                'task_completed' => 'Task Completed',
                'task_comment_added' => 'Task Comment Added',
                'task_reminder' => 'Task Reminder',
            ],
            'Projects' => [
                'project_created' => 'Project Created',
                'project_status_changed' => 'Project Status Changed',
                'project_milestone' => 'Project Milestone',
                'project_discussion_created' => 'Project Discussion Created',
                'project_discussion_comment_added' => 'Project Discussion Comment Added',
                'project_deadline_reminder' => 'Project Deadline Reminder',
            ],
            'Finance' => [
                'invoice_created' => 'Invoice Created',
                'invoice_sent' => 'Invoice Sent',
                'invoice_overdue' => 'Invoice Overdue',
                'invoice_paid' => 'Invoice Paid',
            ],
            'Support' => [
                'ticket_created' => 'Ticket Created',
                'ticket_reply_added' => 'Ticket Reply Added',
                'ticket_closed' => 'Ticket Closed',
            ],
            'General & Others' => [
                'staff_reminder' => 'General Staff Reminder',
            ]
        ];

        $data['template_list'] = $this->bizbot_model->get_all_templates();
        $this->load->view('bizbot/templates', $data);
    }

    public function logs()
    {
        if (!has_permission('settings', '', 'view')) {
            access_denied('Logs');
        }
        $data['logs'] = $this->bizbot_model->get_logs();
        $data['title'] = _l('bizbot_logs');
        $this->load->view('bizbot/logs', $data);
    }

    public function retry_log($id)
    {
        if (!has_permission('settings', '', 'view')) {
            access_denied('Retry');
        }

        $log = $this->db->get_where(db_prefix() . 'bizbot_logs', ['id' => $id])->row();

        if ($log) {
            $response = $this->bizbot_api->send_message($log->phone, $log->message, 'retry_manual');
            if ($response['status'] == 'success') {
                set_alert('success', _l('bizbot_retry_success'));
            }
            else {
                set_alert('warning', _l('bizbot_retry_failed'));
            }
        }
        redirect(admin_url('bizbot/logs'));
    }

    public function bulk()
    {
        if (!has_permission('settings', '', 'view')) {
            access_denied('Bulk Message');
        }

        $data['title'] = _l('bizbot_bulk');
        // Pre-load Roles/Staff/Groups for selection
        $this->load->model('staff_model');
        $this->load->model('clients_model');
        $this->load->model('leads_model');

        $data['staff'] = $this->staff_model->get('', ['active' => 1]);
        $data['groups'] = $this->clients_model->get_groups();
        $data['leads_sources'] = $this->leads_model->get_source();
        $data['leads_statuses'] = $this->leads_model->get_status();

        $this->db->select('name');
        $this->db->from(db_prefix() . 'tags');
        $data['tags'] = $this->db->get()->result_array();

        $this->load->view('bizbot/bulk', $data);
    }

    // AJAX for Bulk
    public function get_bulk_recipients()
    {
        if (!$this->input->is_ajax_request())
            return;

        if (!has_permission('settings', '', 'view')) {
            return;
        }

        // Filters from POST
        $lead_status = $this->input->post('lead_status');
        $lead_assigned = $this->input->post('lead_assigned');
        $lead_tags = $this->input->post('lead_tags');
        $customer_group = $this->input->post('customer_group');
        $from_date = $this->input->post('from_date');
        $to_date = $this->input->post('to_date');

        $type = $this->input->post('type');
        $numbers = [];

        // Logic to fetch numbers based on type
        switch ($type) {
            case 'all_leads':
                $this->db->select('phonenumber');
                if (!empty($lead_status)) {
                    $this->db->where('status', $lead_status);
                }
                if (!empty($lead_assigned)) {
                    $this->db->where('assigned', $lead_assigned);
                }
                if (!empty($lead_tags)) {
                    // SECURE: Use query builder to handle escaping of tag names
                    $this->db->where('id IN (SELECT rel_id FROM ' . db_prefix() . 'taggable WHERE rel_type="lead" AND tag_id IN (SELECT id FROM ' . db_prefix() . 'tags WHERE name IN ?))', [$lead_tags]);
                }
                if (!empty($from_date)) {
                    $this->db->where('dateadded >=', to_sql_date($from_date) . ' 00:00:00');
                }
                if (!empty($to_date)) {
                    $this->db->where('dateadded <=', to_sql_date($to_date) . ' 23:59:59');
                }
                $rows = $this->db->get(db_prefix() . 'leads')->result_array();
                foreach ($rows as $r)
                    if (!empty($r['phonenumber']))
                        $numbers[] = $r['phonenumber'];
                break;
            case 'all_staff':
                $this->db->select('phonenumber');
                if (!empty($from_date)) {
                    $this->db->where('datecreated >=', to_sql_date($from_date) . ' 00:00:00');
                }
                if (!empty($to_date)) {
                    $this->db->where('datecreated <=', to_sql_date($to_date) . ' 23:59:59');
                }
                $rows = $this->db->get(db_prefix() . 'staff')->result_array();
                foreach ($rows as $r)
                    if (!empty($r['phonenumber']))
                        $numbers[] = $r['phonenumber'];
                break;
            case 'all_c': // all clients
                $this->db->select('phonenumber');
                if (!empty($customer_group)) {
                    $this->db->join(db_prefix() . 'customer_groups', db_prefix() . 'customer_groups.customer_id = ' . db_prefix() . 'clients.userid');
                    $this->db->where('groupid', $customer_group);
                }
                if (!empty($from_date)) {
                    $this->db->where('datecreated >=', to_sql_date($from_date) . ' 00:00:00');
                }
                if (!empty($to_date)) {
                    $this->db->where('datecreated <=', to_sql_date($to_date) . ' 23:59:59');
                }
                $rows = $this->db->get(db_prefix() . 'clients')->result_array();
                foreach ($rows as $r)
                    if (!empty($r['phonenumber']))
                        $numbers[] = $r['phonenumber'];
                break;
        }

        // Handle CSV
        if (!empty($_FILES['csv_file']['name'])) {
            // Basic CSV upload/read implementation
            // Since the method was missing, we add a simplified version here
            $csv_numbers = $this->handle_csv_upload();
            $numbers = array_merge($numbers, $csv_numbers);
        }

        echo json_encode(array_unique($numbers));
    }

    private function handle_csv_upload()
    {
        $numbers = [];
        if (!empty($_FILES['csv_file']['tmp_name'])) {
            if (($handle = fopen($_FILES['csv_file']['tmp_name'], "r")) !== FALSE) {
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    foreach ($data as $cell) {
                        $cell = trim($cell);
                        if (!empty($cell) && is_numeric(preg_replace('/[^0-9]/', '', $cell))) {
                            $numbers[] = $cell;
                        }
                    }
                }
                fclose($handle);
            }
        }
        return $numbers;
    }

    public function send_single_message()
    {
        if (!$this->input->is_ajax_request())
            return;

        if (!has_permission('settings', '', 'view')) {
            echo json_encode(['status' => 'failed', 'message' => 'Access denied']);
            return;
        }

        $phone = $this->input->post('phone');
        $message = $this->input->post('message');

        // Basic phone validation
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (strlen($phone) < 10 || strlen($phone) > 15) {
            echo json_encode(['status' => 'failed', 'message' => 'Invalid phone number format']);
            return;
        }

        $response = $this->bizbot_api->send_message($phone, $message, 'bulk');
        echo json_encode($response);
    }

    public function followups()
    {
        if (!has_permission('settings', '', 'view')) {
            access_denied('Follow-ups');
        }

        if ($this->input->post()) {
            $blacklist = $this->input->post('blacklist_statuses');
            $insert_data = [
                'name' => $this->input->post('name'),
                'step_number' => $this->input->post('step_number') ?: 1,
                'delay_value' => $this->input->post('delay_value') ?: 1,
                'delay_unit' => $this->input->post('delay_unit') ?: 'hours',
                'message' => $this->input->post('message'),
                'status_id' => !empty($this->input->post('status_id')) ? $this->input->post('status_id') : null,
                'blacklist_statuses' => !empty($blacklist) ? json_encode($blacklist) : null,
                'max_per_lead' => $this->input->post('max_per_lead') ?: 0,
                'stop_on_status_change' => $this->input->post('stop_on_status_change') ? 1 : 0,
                'active' => 1
            ];
            $this->bizbot_model->add_followup($insert_data);
            set_alert('success', _l('bizbot_followup_added'));
            redirect(admin_url('bizbot/followups'));
        }

        $data['statuses'] = $this->db->get(db_prefix() . 'leads_status')->result_array();
        $data['followups'] = $this->bizbot_model->get_followups() ?: [];
        $data['title'] = 'Automated Follow-ups';

        $this->load->view('bizbot/followups', $data);
    }

    public function delete_followup($id)
    {
        if (!has_permission('settings', '', 'view')) {
            access_denied('Delete Follow-up');
        }

        if ($this->bizbot_model->delete_followup($id)) {
            set_alert('success', _l('bizbot_followup_deleted'));
        }
        redirect(admin_url('bizbot/followups'));
    }

    public function queue()
    {
        if (!has_permission('settings', '', 'view')) {
            access_denied('Queue');
        }

        $tableName = db_prefix() . 'bizbot_queue';

        if ($this->input->post()) {
            $data = [
                'rel_id' => 0,
                'rel_type' => 'manual',
                'phone' => $this->input->post('phone'),
                'message' => $this->input->post('message'),
                'scheduled_at' => to_sql_date($this->input->post('scheduled_at'), true),
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
            ];
            $this->db->insert(db_prefix() . 'bizbot_queue', $data);
            set_alert('success', _l('bizbot_message_scheduled'));
            redirect(admin_url('bizbot/queue'));
        }

        // Robust fetch
        $this->db->order_by('scheduled_at', 'DESC');
        $queue_query = $this->db->get($tableName);

        $data['queue'] = $queue_query ? $queue_query->result_array() : [];
        $data['title'] = 'Message Queue';
        $this->load->view('bizbot/queue', $data);
    }

    public function widget()
    {
        if (!has_permission('settings', '', 'view')) {
            access_denied('Widget');
        }

        if ($this->input->post()) {
            update_option('bizbot_widget_id', $this->input->post('widget_id'));
            update_option('bizbot_widget_type', $this->input->post('integration_type'));
            update_option('bizbot_widget_show_to_admin', $this->input->post('show_to_admin') ? 1 : 0);
            update_option('bizbot_widget_show_to_customer', $this->input->post('show_to_customer') ? 1 : 0);
            set_alert('success', _l('bizbot_widget_updated'));
            redirect(admin_url('bizbot/widget'));
        }

        $data['title'] = 'Website WhatsApp Widget';
        $this->load->view('bizbot/widget', $data);
    }

    // ========== NEW METHODS ==========

    public function edit_followup($id)
    {
        if (!has_permission('settings', '', 'view')) {
            access_denied('Edit Follow-up');
        }

        $followup = $this->bizbot_model->get_followup($id);
        if (!$followup) {
            set_alert('danger', 'Follow-up not found');
            redirect(admin_url('bizbot/followups'));
        }

        if ($this->input->post()) {
            $blacklist = $this->input->post('blacklist_statuses');
            $update_data = [
                'name' => $this->input->post('name'),
                'step_number' => $this->input->post('step_number') ?: 1,
                'delay_value' => $this->input->post('delay_value') ?: 1,
                'delay_unit' => $this->input->post('delay_unit') ?: 'hours',
                'message' => $this->input->post('message'),
                'status_id' => !empty($this->input->post('status_id')) ? $this->input->post('status_id') : null,
                'blacklist_statuses' => !empty($blacklist) ? json_encode($blacklist) : null,
                'max_per_lead' => $this->input->post('max_per_lead') ?: 0,
                'stop_on_status_change' => $this->input->post('stop_on_status_change') ? 1 : 0,
                'active' => $this->input->post('active') ? 1 : 0
            ];
            $this->bizbot_model->update_followup($id, $update_data);
            set_alert('success', _l('bizbot_followup_updated'));
            redirect(admin_url('bizbot/followups'));
        }

        $data['followup'] = $followup;
        $data['statuses'] = $this->db->get(db_prefix() . 'leads_status')->result_array();
        $data['title'] = 'Edit Follow-up Step';
        $this->load->view('bizbot/edit_followup', $data);
    }

    public function cancel_queue($id)
    {
        if (!has_permission('settings', '', 'view')) {
            access_denied('Cancel Queue');
        }

        if ($this->bizbot_model->cancel_queue_item($id)) {
            set_alert('success', _l('bizbot_message_cancelled'));
        }
        else {
            set_alert('warning', _l('bizbot_message_cancel_failed'));
        }
        redirect(admin_url('bizbot/queue'));
    }

    public function delete_queue($id)
    {
        if (!has_permission('settings', '', 'view')) {
            access_denied('Delete Queue');
        }

        if ($this->bizbot_model->delete_queue_item($id)) {
            set_alert('success', _l('bizbot_queue_deleted'));
        }
        redirect(admin_url('bizbot/queue'));
    }

    public function delete_log($id)
    {
        if (!has_permission('settings', '', 'view')) {
            access_denied('Delete Log');
        }

        if ($this->bizbot_model->delete_log($id)) {
            set_alert('success', _l('bizbot_log_deleted'));
        }
        redirect(admin_url('bizbot/logs'));
    }

    public function cleanup_logs()
    {
        if (!has_permission('settings', '', 'view')) {
            access_denied('Cleanup Logs');
        }

        $days = $this->input->get('days');
        if ($days === 'all') {
            $this->bizbot_model->delete_old_logs('all');
            set_alert('success', _l('bizbot_all_logs_cleaned'));
        }
        else {
            $days = (int)$days ?: 30;
            $deleted = $this->bizbot_model->delete_old_logs($days);
            set_alert('success', _l('bizbot_logs_cleaned', [$deleted, $days]));
        }
        redirect(admin_url('bizbot/logs'));
    }

    public function get_activity_logs()
    {
        if (!has_permission('settings', '', 'view')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }

        $log_file = APPPATH . '../modules/bizbot/bizbot_activity.log';
        if (!file_exists($log_file)) {
            echo json_encode(['success' => true, 'logs' => 'No activity logs yet. Try changing a lead status.']);
            return;
        }

        $size = filesize($log_file);
        $read_size = ($size > 15000) ? 15000 : $size;
        $f = fopen($log_file, 'r');
        if ($size > 15000) {
            fseek($f, -$read_size, SEEK_END);
        }
        $logs = fread($f, $read_size);
        fclose($f);

        echo json_encode(['success' => true, 'logs' => htmlspecialchars($logs)]);
    }

    public function clear_activity_logs()
    {
        if (!has_permission('settings', '', 'view')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }

        $log_file = APPPATH . '../modules/bizbot/bizbot_activity.log';
        if (file_exists($log_file)) {
            @unlink($log_file);
        }
        echo json_encode(['success' => true, 'message' => 'Activity logs cleared.']);
    }

    public function get_webhook_logs()
    {
        if (!has_permission('settings', '', 'view')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }

        $log_file = APPPATH . '../modules/bizbot/webhook_debug.log';
        if (!file_exists($log_file)) {
            echo json_encode(['success' => true, 'logs' => 'No logs found.']);
            return;
        }

        // Read last 10 KB of logs
        $size = filesize($log_file);
        $read_size = ($size > 10000) ? 10000 : $size;
        $f = fopen($log_file, 'r');
        if ($size > 10000) {
            fseek($f, -$read_size, SEEK_END);
        }
        $logs = fread($f, $read_size);
        fclose($f);

        echo json_encode(['success' => true, 'logs' => htmlspecialchars($logs)]);
    }

    public function test_connection()
    {
        if (!has_permission('settings', '', 'view')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }

        $api_key = get_option('bizbot_api_key');
        if (empty($api_key)) {
            echo json_encode(['success' => false, 'message' => 'API Key not configured']);
            return;
        }

        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $api_key
        ];

        // Probe multiple endpoints to discover the API structure
        $endpoints_to_test = [
            '/public/v1/contact?phone=01700000000' => 'Contact Search (singular)',
            '/public/v1/contacts?phone=01700000000' => 'Contact Search (plural)',
            '/public/v1/threads?page=1&limit=1' => 'Threads List',
            '/public/v1/chat/threads?page=1&limit=1' => 'Chat Threads List',
            '/public/v1/chats?page=1&limit=1' => 'Chats List',
        ];

        $results = [];
        foreach ($endpoints_to_test as $path => $label) {
            $url = 'https://api.bizbot.one' . $path;
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $ssl_verify = get_option('bizbot_ssl_verify');
            if ($ssl_verify === '0') {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            }

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            $results[] = [
                'endpoint' => $path,
                'label' => $label,
                'http_code' => $http_code,
                'status' => ($http_code >= 200 && $http_code < 300) ? 'OK' : 'FAILED',
                'response' => substr($response, 0, 200),
                'error' => $error,
            ];
        }

        // Also test the send endpoint (just check auth, not actually send)
        $working = array_filter($results, function ($r) {
            return $r['status'] === 'OK';
        });

        echo json_encode([
            'success' => true,
            'message' => count($working) . ' of ' . count($results) . ' endpoints available',
            'endpoints' => $results
        ]);
    }

    public function clear_webhook_logs()
    {
        if (!has_permission('settings', '', 'view')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }

        $log_file = APPPATH . '../modules/bizbot/webhook_debug.log';
        if (file_exists($log_file)) {
            @unlink($log_file);
        }
        echo json_encode(['success' => true, 'message' => 'Logs cleared.']);
    }

    public function bulk_sync_threads()
    {
        if (!has_permission('settings', '', 'view')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }

        set_time_limit(120); // Allow 2 minutes for this batch

        $limit = 10; // Small batches to avoid cPanel timeout

        // Get thread_guid custom field ID
        $this->db->where('slug', 'leads_bizbot_thread_guid');
        $cf = $this->db->get(db_prefix() . 'customfields')->row();
        if (!$cf) {
            echo json_encode(['success' => false, 'message' => 'Custom field leads_bizbot_thread_guid not found. Re-activate the module.']);
            return;
        }
        $field_id = $cf->id;

        // Find leads without thread_guid (NULL or empty)
        $this->db->select(db_prefix() . 'leads.id, ' . db_prefix() . 'leads.phonenumber');
        $this->db->from(db_prefix() . 'leads');
        $this->db->join(db_prefix() . 'customfieldsvalues AS cv', 'cv.relid = ' . db_prefix() . 'leads.id AND cv.fieldid = ' . $field_id, 'left');
        $this->db->where('(cv.value IS NULL OR cv.value = "")');
        $this->db->where(db_prefix() . 'leads.phonenumber !=', '');

        // Clone for total count
        $count_db = clone $this->db;
        $total_remaining = $count_db->count_all_results();

        $this->db->limit($limit);
        $leads = $this->db->get()->result_array();

        if (empty($leads)) {
            echo json_encode(['success' => true, 'message' => 'All leads processed.', 'count' => 0, 'remaining' => 0]);
            return;
        }

        $synced_count = 0;
        $skipped_count = 0;
        $debug_info = [];

        foreach ($leads as $lead) {
            $phone = preg_replace('/[^0-9]/', '', $lead['phonenumber']);
            if (empty($phone)) {
                // Mark as 'no_thread' to skip in future batches
                $this->update_lead_thread_guid($lead['id'], 'no_thread');
                $skipped_count++;
                continue;
            }

            $search = $this->bizbot_api->search_contact_by_phone($phone);

            // Log first lead's attempt for debugging
            if (count($debug_info) < 1) {
                $first_contact_phones = [];
                $contacts_data = [];
                if ($search['status'] === 'success') {
                    if (isset($search['data']['data']) && is_array($search['data']['data'])) {
                        $contacts_data = $search['data']['data'];
                    }
                    elseif (isset($search['data']) && is_array($search['data'])) {
                        $contacts_data = $search['data'];
                    }
                    // Show first 3 contact phones for comparison
                    foreach (array_slice($contacts_data, 0, 3) as $cd) {
                        $first_contact_phones[] = $cd['phone'] ?? 'N/A';
                    }
                }
                $debug_info[] = [
                    'lead_phone' => $phone,
                    'api_status' => $search['status'],
                    'found_count' => count($contacts_data),
                    'bizbot_phones' => $first_contact_phones,
                    'first_contact_keys' => !empty($contacts_data) ? array_keys($contacts_data[0]) : []
                ];
            }

            $contacts = [];
            if ($search['status'] === 'success') {
                if (isset($search['data']['data']) && is_array($search['data']['data'])) {
                    $contacts = $search['data']['data'];
                }
                elseif (isset($search['data']) && is_array($search['data'])) {
                    $contacts = $search['data'];
                }
            }

            $matched_contact = null;
            if (!empty($contacts)) {
                foreach ($contacts as $c) {
                    $c_phone = preg_replace('/[^0-9]/', '', $c['phone'] ?? '');

                    // Match strategies:
                    // 1. Exact match
                    // 2. Lead phone ends with API phone (or vice versa) using last 10 digits
                    // 3. BD format: lead=01721... vs API=8801721... or +8801721...
                    if ($c_phone == $phone) {
                        $matched_contact = $c;
                        break;
                    }

                    $p1 = strlen($phone) > 10 ? substr($phone, -10) : $phone;
                    $p2 = strlen($c_phone) > 10 ? substr($c_phone, -10) : $c_phone;
                    if ($p1 === $p2 && strlen($p1) >= 10) {
                        $matched_contact = $c;
                        break;
                    }
                }
            }

            if ($matched_contact) {
                // Try multiple possible field names for thread GUID
                $thread_guid = '';
                $possible_keys = ['last_thread_guid', 'thread_guid', 'guid'];
                foreach ($possible_keys as $key) {
                    if (!empty($matched_contact[$key])) {
                        $thread_guid = $matched_contact[$key];
                        break;
                    }
                }

                // Log match details
                if (count($debug_info) < 3) {
                    $debug_info[] = [
                        'type' => 'match',
                        'lead_phone' => $phone,
                        'contact_phone' => $matched_contact['phone'] ?? 'N/A',
                        'thread_field_used' => $thread_guid ? $key : 'none',
                        'thread_guid' => substr($thread_guid, 0, 20) . '...',
                        'contact_keys' => array_keys($matched_contact),
                    ];
                }

                if (!empty($thread_guid)) {
                    $this->update_lead_thread_guid($lead['id'], $thread_guid);
                    $synced_count++;
                }
                else {
                    // Contact found but no thread GUID — mark to skip
                    $this->update_lead_thread_guid($lead['id'], 'no_thread');
                    $skipped_count++;
                }
            }
            else {
                // No match found — mark to skip in future batches
                $this->update_lead_thread_guid($lead['id'], 'no_thread');
                $skipped_count++;
            }
        }

        // Final remaining count after this batch
        $final_remaining = $total_remaining - count($leads);
        if ($final_remaining < 0)
            $final_remaining = 0;

        echo json_encode([
            'success' => true,
            'message' => $synced_count . ' synced, ' . $skipped_count . ' skipped in this batch.',
            'count' => $synced_count,
            'remaining' => $final_remaining,
            'debug' => $debug_info
        ]);
    }

    private function update_lead_thread_guid($lead_id, $thread_guid)
    {
        $this->db->where('slug', 'leads_bizbot_thread_guid');
        $field = $this->db->get(db_prefix() . 'customfields')->row();

        if ($field) {
            $this->db->where('relid', $lead_id);
            $this->db->where('fieldid', $field->id);
            $exists = $this->db->get(db_prefix() . 'customfieldsvalues')->row();

            if ($exists) {
                $this->db->where('id', $exists->id);
                $this->db->update(db_prefix() . 'customfieldsvalues', ['value' => $thread_guid]);
            }
            else {
                $this->db->insert(db_prefix() . 'customfieldsvalues', [
                    'relid' => $lead_id,
                    'fieldid' => $field->id,
                    'fieldto' => 'leads',
                    'value' => $thread_guid
                ]);
            }
        }
    }
}