<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Write to Bizbot activity log file (accessible from Settings page)
 */
function bizbot_log($message)
{
    $log_file = APPPATH . '../modules/bizbot/bizbot_activity.log';
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

function bizbot_check_trigger_and_send($event_slug, $data, $recipients = [])
{
    try {
        $CI = & get_instance();

        bizbot_log("=== EVENT: $event_slug ===");

        bizbot_log("Step 1: Loading models...");
        $CI->load->model('bizbot/Bizbot_model', 'bizbot_model');
        $CI->load->library('bizbot/Bizbot_api', null, 'bizbot_api');
        $CI->load->model('staff_model');
        $CI->load->model('clients_model');
        $CI->load->model('tasks_model');
        $CI->load->model('projects_model');
        bizbot_log("Step 2: Models loaded OK");

        bizbot_log("Step 3: Fetching template for '$event_slug'...");
        $template = $CI->bizbot_model->get_template($event_slug);
        bizbot_log("Step 4: Template result: " . ($template ? "found (active=" . $template->active . ")" : "NOT FOUND"));

        if (!$template || !$template->active) {
            if (!$template) {
                bizbot_log("SKIP: No template for '$event_slug'");
            }
            else {
                bizbot_log("SKIP: Template '$event_slug' is inactive (active=" . $template->active . ")");
            }
            return;
        }

        bizbot_log("Template OK: to_customer=" . ($template->send_to_customer ?? 0) . ", to_staff=" . ($template->send_to_staff ?? 0) . ", to_admin=" . ($template->send_to_admin ?? 0));

        // Determine Recipients
        // NOTE: We build targets as structured entries so we can parse placeholders per recipient
        // (e.g. {staff_firstname} must resolve to the specific staff member receiving the message).
        $targets = [];

        // Send to Customer
        if ($template->send_to_customer) {
            $customer_phone = '';
            if (isset($data['client'])) {
                $customer_phone = $data['client']->phonenumber;
            }
            elseif (isset($data['contact'])) {
                $customer_phone = $data['contact']->phonenumber;
            }
            elseif (isset($data['lead'])) {
                $customer_phone = $data['lead']->phonenumber;
            }
            elseif (isset($data['invoice'])) { // Invoice usually relates to client
                $client = $CI->clients_model->get($data['invoice']->clientid);
                if ($client) {
                    $customer_phone = $client->phonenumber;
                }
            }

            if (!empty($customer_phone)) {
                $targets[] = ['phone' => $customer_phone, 'staff' => null];
                bizbot_log("Recipient: customer phone=$customer_phone");
            }
            else {
                bizbot_log("WARNING: send_to_customer=1 but no phone found");
            }
        }

        // Send to Staff
        if ($template->send_to_staff) {
            if (isset($data['staff_id'])) {
                $staff = $CI->staff_model->get($data['staff_id']);
                if ($staff && !empty($staff->phonenumber)) {
                    $targets[] = ['phone' => $staff->phonenumber, 'staff' => $staff];
                }
            }
            elseif (isset($data['lead']) && $data['lead']->assigned != 0) {
                $staff = $CI->staff_model->get($data['lead']->assigned);
                if ($staff && !empty($staff->phonenumber)) {
                    $targets[] = ['phone' => $staff->phonenumber, 'staff' => $staff];
                }
            }
            elseif (isset($data['task'])) {
                // Tasks can be assigned to multiple staff. Parse per assignee.
                $assignees = $CI->tasks_model->get_task_assignees($data['task']->id);
                foreach ($assignees as $assignee) {
                    if (isset($data['exclude_staff_id']) && $assignee['assigneeid'] == $data['exclude_staff_id'])
                        continue;
                    $staff = $CI->staff_model->get($assignee['assigneeid']);
                    if ($staff && !empty($staff->phonenumber)) {
                        $targets[] = ['phone' => $staff->phonenumber, 'staff' => $staff];
                    }
                }
            }
            elseif (isset($data['project'])) {
                // Projects: Send to all project members
                $members = $CI->projects_model->get_project_members($data['project']->id);
                foreach ($members as $member) {
                    $staff = $CI->staff_model->get($member['staff_id']);
                    if ($staff && !empty($staff->phonenumber)) {
                        $targets[] = ['phone' => $staff->phonenumber, 'staff' => $staff];
                    }
                }
            }
        }

        // Send to Admin (Superadmins or Selected Admins) OR Lead Assigned Staff (Unified check)
        if ($template->send_to_admin) {
            // Special case: If it's a lead event and labeled as 'Assigned Staff' in UI
            if (($event_slug == 'lead_reminder' || $event_slug == 'lead_staff_reminder' || strpos($event_slug, 'lead_') === 0) && isset($data['lead'])) {
                if ($data['lead']->assigned != 0) {
                    $staff = $CI->staff_model->get($data['lead']->assigned);
                    if ($staff && !empty($staff->phonenumber)) {
                        $targets[] = ['phone' => $staff->phonenumber, 'staff' => $staff];
                    }
                }
            }
            else {
                // Standard Admin sending
                $selected_admins = get_option('bizbot_notification_admins');
                $selected_admins = !empty($selected_admins) ? json_decode($selected_admins) : [];

                if (!empty($selected_admins)) {
                    bizbot_log("Category: Admin - Selected IDs: " . implode(',', $selected_admins));
                    // Send only to selected admins
                    foreach ($selected_admins as $staff_id) {
                        // Performer Exclusion
                        if (isset($data['exclude_staff_id']) && $staff_id == $data['exclude_staff_id']) {
                            bizbot_log("EXCLUDE: admin staff_id=$staff_id is the performer");
                            continue;
                        }
                        $admin = $CI->staff_model->get($staff_id);
                        if ($admin && !empty($admin->phonenumber)) {
                            $targets[] = ['phone' => $admin->phonenumber, 'staff' => $admin];
                            bizbot_log("Recipient: admin phone=" . $admin->phonenumber . " (staff_id=$staff_id)");
                        }
                        else {
                            bizbot_log("SKIP: admin staff_id=$staff_id has no phone or not found");
                        }
                    }
                }
                else {
                    bizbot_log("Category: Admin - All Superadmins");
                    // Send to all Superadmins
                    $CI->db->where('admin', 1);
                    $admins = $CI->db->get(db_prefix() . 'staff')->result_array();
                    foreach ($admins as $admin) {
                        // Performer Exclusion
                        if (isset($data['exclude_staff_id']) && $admin['staffid'] == $data['exclude_staff_id']) {
                            bizbot_log("EXCLUDE: superadmin staff_id=" . $admin['staffid'] . " is the performer");
                            continue;
                        }
                        if (!empty($admin['phonenumber'])) {
                            // Convert array to object to match staff_model->get() return type
                            $admin_obj = (object)$admin;
                            $targets[] = ['phone' => $admin['phonenumber'], 'staff' => $admin_obj];
                            bizbot_log("Recipient: admin phone=" . $admin['phonenumber'] . " (staff_id=" . $admin['staffid'] . ")");
                        }
                    }
                }
            }
        }

        // Send to Followers
        if (!empty($template->send_to_followers)) {
            if (isset($data['task'])) {
                $followers = $CI->tasks_model->get_task_followers($data['task']->id);
                foreach ($followers as $follower) {
                    $staff = $CI->staff_model->get($follower['followerid']);
                    if ($staff && !empty($staff->phonenumber)) {
                        $targets[] = ['phone' => $staff->phonenumber, 'staff' => $staff];
                    }
                }
            }
        }

        // Custom Numbers
        if (!empty($template->custom_numbers)) {
            bizbot_log("Custom Numbers: " . $template->custom_numbers);
            $customs = explode(',', $template->custom_numbers);
            foreach ($customs as $num) {
                if (!empty(trim($num))) {
                    $targets[] = ['phone' => trim($num), 'staff' => null];
                }
            }
        }

        // Deduplicate by phone and filter out excluded staff
        $uniqueTargets = [];
        $exclude_staff_id = $data['exclude_staff_id'] ?? null;

        foreach ($targets as $t) {
            // Skip if this staff member is marked for exclusion
            if ($exclude_staff_id && isset($t['staff']) && $t['staff'] && $t['staff']->staffid == $exclude_staff_id) {
                continue;
            }
            $uniqueTargets[$t['phone']] = $t;
        }

        bizbot_log("Total recipients: " . count($uniqueTargets));

        if (empty($uniqueTargets)) {
            bizbot_log("WARNING: No recipients found for '$event_slug'. Aborting.");
            return;
        }

        foreach ($uniqueTargets as $t) {
            $message = $template->message;

            // Merge staff into data for per-recipient parsing
            $dataForRecipient = $data;
            if (!empty($t['staff'])) {
                $dataForRecipient['staff'] = $t['staff'];
            }

            $message = bizbot_parse_placeholders($message, $event_slug, $dataForRecipient);

            $start_time = microtime(true);
            bizbot_log("SENDING to {$t['phone']}: " . substr($message, 0, 80) . '...');
            $result = $CI->bizbot_api->send_message($t['phone'], $message, $event_slug);
            $end_time = microtime(true);
            $duration = round($end_time - $start_time, 2);

            if ($duration > 2.0) {
                bizbot_log("PERFORMANCE WARNING: send_message took {$duration}s for event '$event_slug'");
            }

            bizbot_log("API RESULT: status={$result['status']}, http={$result['http_code']} (took {$duration}s)");
        }

        bizbot_log("=== END: $event_slug ===");
    }
    catch (\Throwable $e) {
        bizbot_log("FATAL ERROR in $event_slug: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
    }
}

function bizbot_parse_placeholders($message, $event, $data)
{
    $CI = & get_instance();
    bizbot_log("DEBUG: Parsing placeholders for event '$event'");
    $replacements = [
        '{company_name}' => get_option('companyname'),
        '{crm_url}' => admin_url(),
    ];

    // Lead
    if (isset($data['lead'])) {
        $l = $data['lead'];
        $l_id = is_object($l) ? ($l->id ?? '') : ($l['id'] ?? '');
        $l_status = is_object($l) ? ($l->status ?? '') : ($l['status'] ?? '');
        $l_assigned = is_object($l) ? ($l->assigned ?? '') : ($l['assigned'] ?? '');
        $l_name = is_object($l) ? ($l->name ?? '') : ($l['name'] ?? '');
        $l_email = is_object($l) ? ($l->email ?? '') : ($l['email'] ?? '');
        $l_phone = is_object($l) ? ($l->phonenumber ?? '') : ($l['phonenumber'] ?? '');
        $l_company = is_object($l) ? ($l->company ?? '') : ($l['company'] ?? '');

        if (empty($data['lead_status_name'])) {
            $CI->load->model('leads_model');
            $status = $CI->leads_model->get_status($l_status);
            if ($status) {
                $data['lead_status_name'] = $status->name;
            }
        }

        if (empty($data['lead_assigned_name']) && empty($data['assigned_staff_name']) && !empty($l_assigned)) {
            $CI->load->model('staff_model');
            $staff = $CI->staff_model->get($l_assigned);
            if ($staff) {
                $data['lead_assigned_name'] = $staff->firstname . ' ' . $staff->lastname;
            }
        }

        // Fetch Last Note
        $CI->db->where('rel_id', $l_id);
        $CI->db->where('rel_type', 'lead');
        $CI->db->order_by('dateadded', 'DESC');
        $CI->db->limit(1);
        $last_note = $CI->db->get(db_prefix() . 'notes')->row();
        $note_content = $last_note ? html_entity_decode(strip_tags($last_note->description), ENT_QUOTES, 'UTF-8') : '';

        $replacements['{lead_name}'] = html_entity_decode($l_name, ENT_QUOTES, 'UTF-8');
        $replacements['{lead_email}'] = $l_email;
        $replacements['{lead_phone}'] = $l_phone;
        $replacements['{lead_phonenumber}'] = $l_phone;
        $replacements['{lead_status}'] = html_entity_decode($data['lead_status_name'] ?? '', ENT_QUOTES, 'UTF-8');
        $replacements['{lead_assignee}'] = html_entity_decode($data['lead_assigned_name'] ?? ($data['assigned_staff_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $replacements['{assigned_staff_name}'] = html_entity_decode($data['assigned_staff_name'] ?? ($data['lead_assigned_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $replacements['{lead_id}'] = $l_id;
        $replacements['{company}'] = html_entity_decode($l_company, ENT_QUOTES, 'UTF-8');
        $replacements['{lead_last_note}'] = $note_content;
    }

    // --- Robust Customer Resolution ---
    $cust_id = '';
    $cust_name = '';
    $cust_company = '';
    $cust_phone = '';

    if (isset($data['client'])) {
        $c = $data['client'];
        $cust_id = is_object($c) ? ($c->userid ?? '') : ($c['userid'] ?? '');
        $cust_company = is_object($c) ? ($c->company ?? '') : ($c['company'] ?? '');
        $cust_name = $cust_company; // Default

        if (isset($data['contact'])) {
            $con = $data['contact'];
            $fname = is_object($con) ? ($con->firstname ?? '') : ($con['firstname'] ?? '');
            $lname = is_object($con) ? ($con->lastname ?? '') : ($con['lastname'] ?? '');
            $cust_name = trim($fname . ' ' . $lname);
            if (empty($cust_name))
                $cust_name = $cust_company;
            $cust_phone = is_object($con) ? ($con->phonenumber ?? '') : ($con['phonenumber'] ?? '');
            if (empty($cust_phone))
                $cust_phone = is_object($c) ? ($c->phonenumber ?? '') : ($c['phonenumber'] ?? '');

            if (empty($cust_name)) {
                $cust_name = is_object($c) ? ($c->company ?? '') : ($c['company'] ?? '');
            }
        }
        else {
            $cust_phone = is_object($c) ? ($c->phonenumber ?? '') : ($c['phonenumber'] ?? '');
        }
    }
    elseif (isset($data['lead'])) {
        $l = $data['lead'];
        $cust_id = is_object($l) ? ($l->id ?? '') : ($l['id'] ?? '');
        $cust_name = is_object($l) ? ($l->name ?? '') : ($l['name'] ?? '');
        $cust_company = is_object($l) ? ($l->company ?? '') : ($l['company'] ?? '');
        $cust_phone = is_object($l) ? ($l->phonenumber ?? '') : ($l['phonenumber'] ?? '');
    }
    elseif (isset($data['task'])) {
        $t = $data['task'];
        // Try to get customer info from task relation if available
        $t_rel_type = is_object($t) ? ($t->rel_type ?? '') : ($t['rel_type'] ?? '');
        $t_rel_id = is_object($t) ? ($t->rel_id ?? '') : ($t['rel_id'] ?? '');

        if ($t_rel_type == 'lead') {
            $CI->load->model('leads_model');
            $l = $CI->leads_model->get($t_rel_id);
            if ($l) {
                $cust_id = $l->id;
                $cust_name = $l->name;
                $cust_company = $l->company;
                $cust_phone = $l->phonenumber;
            }
        }
        elseif ($t_rel_type == 'customer' || $t_rel_type == 'client') {
            $CI->load->model('clients_model');
            $c = $CI->clients_model->get($t_rel_id);
            if ($c) {
                $cust_id = $c->userid;
                $cust_company = $c->company;
                $cust_name = $c->company;
                $cust_phone = $c->phonenumber;
            }
        }
    }
    elseif (isset($data['invoice'])) {
        $inv = $data['invoice'];
        $inv_clientid = is_object($inv) ? ($inv->clientid ?? '') : ($inv['clientid'] ?? '');
        $CI->load->model('clients_model');
        $client = $CI->clients_model->get($inv_clientid);
        if ($client) {
            $cust_id = $client->userid ?? '';
            $cust_company = $client->company ?? '';
            $cust_name = $client->company ?? '';
            $cust_phone = $client->phonenumber ?? '';
        }
    }

    bizbot_log("DEBUG: Resolved Customer: ID=$cust_id, Name=$cust_name, Phone=$cust_phone");

    $replacements['{customer_id}'] = $cust_id;
    $replacements['{customer_name}'] = $cust_name;
    $replacements['{customer_company}'] = $cust_company;
    $replacements['{customer_phone}'] = $cust_phone;

    // Client/Contact (Backward Compatibility)
    if (isset($data['client'])) {
        $c = $data['client'];
        $replacements['{client_company}'] = $c->company ?? '';
        $replacements['{client_id}'] = $c->userid ?? '';
        $replacements['{client_email}'] = $data['contact']->email ?? ($c->company_email ?? '');
        $replacements['{client_vat}'] = $c->vat ?? '';
    }

    // Task
    if (isset($data['task'])) {
        $t = $data['task'];
        $t_id = is_object($t) ? ($t->id ?? '') : ($t['id'] ?? '');
        $t_name = is_object($t) ? ($t->name ?? '') : ($t['name'] ?? '');
        $t_description = is_object($t) ? ($t->description ?? '') : ($t['description'] ?? '');
        $t_status = is_object($t) ? ($t->status ?? '') : ($t['status'] ?? '');
        $t_startdate = is_object($t) ? ($t->startdate ?? '') : ($t['startdate'] ?? '');
        $t_duedate = is_object($t) ? ($t->duedate ?? '') : ($t['duedate'] ?? '');
        $t_priority = is_object($t) ? ($t->priority ?? '') : ($t['priority'] ?? '');

        $startDate = !empty($t_startdate) ? _d($t_startdate) : '';
        $dueDate = !empty($t_duedate) ? _d($t_duedate) : '';

        $assigned_names = '';
        $assignees = $CI->tasks_model->get_task_assignees($t_id);
        if (count($assignees) > 0) {
            $names = [];
            foreach ($assignees as $assignee) {
                $names[] = $assignee['firstname'] . ' ' . $assignee['lastname'];
            }
            $assigned_names = implode(', ', $names);
        }

        $replacements['{task_name}'] = html_entity_decode($t_name, ENT_QUOTES, 'UTF-8');
        $replacements['{task_description}'] = html_entity_decode(strip_tags($t_description), ENT_QUOTES, 'UTF-8');
        $replacements['{task_status}'] = html_entity_decode(format_task_status($t_status, false, true), ENT_QUOTES, 'UTF-8');
        $replacements['{task_startdate}'] = $startDate;
        $replacements['{task_duedate}'] = $dueDate;
        $replacements['{task_due_date}'] = $dueDate;
        $replacements['{task_status_name}'] = $data['task_status_name'] ?? format_task_status($t_status, false, true);
        $replacements['{task_priority}'] = task_priority($t_priority);
        $replacements['{task_assigned_names}'] = $assigned_names;
        $replacements['{task_link}'] = admin_url('tasks/view/' . $t_id);
    }

    // Project
    if (isset($data['project'])) {
        $p = $data['project'];
        $member_names = '';
        if (isset($CI->projects_model)) {
            $members = $CI->projects_model->get_project_members($p->id);
            $names = [];
            foreach ($members as $m) {
                $staff = $CI->staff_model->get($m['staff_id']);
                if ($staff) {
                    $names[] = $staff->firstname . ' ' . $staff->lastname;
                }
            }
            $member_names = implode(', ', $names);
        }

        $days_left = '';
        if (!empty($p->deadline)) {
            $d1 = new DateTime(date('Y-m-d'));
            $d2 = new DateTime($p->deadline);
            $diff = $d1->diff($d2);
            $days_left = ($d1 <= $d2) ? $diff->days : '-' . $diff->days;
        }

        $replacements['{project_name}'] = html_entity_decode($p->name ?? '', ENT_QUOTES, 'UTF-8');
        $replacements['{project_description}'] = html_entity_decode(strip_tags($p->description ?? ''), ENT_QUOTES, 'UTF-8');
        $replacements['{project_start_date}'] = _d($p->start_date) ?? '';
        $replacements['{project_deadline}'] = _d($p->deadline) ?? '';
        $replacements['{project_status}'] = html_entity_decode($data['project_status_name'] ?? '', ENT_QUOTES, 'UTF-8');
        $replacements['{days_left}'] = $days_left;
        $replacements['{days_remaining}'] = $days_left;
        $replacements['{project_assigned_names}'] = $member_names;
        $replacements['{project_link}'] = admin_url('projects/view/' . $p->id);
    }

    // Milestone
    if (isset($data['milestone'])) {
        $m = $data['milestone'];
        $replacements['{milestone_name}'] = html_entity_decode($m->name ?? '', ENT_QUOTES, 'UTF-8');
        $replacements['{milestone_description}'] = html_entity_decode(strip_tags($m->description ?? ''), ENT_QUOTES, 'UTF-8');
        $replacements['{milestone_due_date}'] = _d($m->due_date) ?? '';
    }

    // Invoice
    if (isset($data['invoice'])) {
        $inv = $data['invoice'];
        $replacements['{invoice_number}'] = format_invoice_number($inv->id);
        $replacements['{invoice_amount}'] = app_format_money($inv->total, $inv->currency_name);
        $replacements['{invoice_due_date}'] = _d($inv->duedate);
        $replacements['{invoice_link}'] = site_url('invoice/' . $inv->id . '/' . $inv->hash);
    }

    // Ticket
    if (isset($data['ticket'])) {
        $tk = $data['ticket'];
        $replacements['{ticket_id}'] = $tk->ticketid;
        $replacements['{ticket_subject}'] = $tk->subject;
        $replacements['{ticket_message}'] = strip_tags($tk->message ?? '');
        $replacements['{ticket_link}'] = admin_url('tickets/ticket/' . $tk->ticketid);
    }

    // Staff
    if (isset($data['staff'])) {
        $s = $data['staff'];
        $firstname = is_object($s) ? ($s->firstname ?? '') : ($s['firstname'] ?? '');
        $lastname = is_object($s) ? ($s->lastname ?? '') : ($s['lastname'] ?? '');
        $phonenumber = is_object($s) ? ($s->phonenumber ?? '') : ($s['phonenumber'] ?? '');

        $replacements['{staff_firstname}'] = $firstname;
        $replacements['{staff_lastname}'] = $lastname;
        $replacements['{staff_name}'] = trim($firstname . ' ' . $lastname);
        $replacements['{staff_phone}'] = $phonenumber;
    }

    // Comment
    if (isset($data['comment'])) {
        $comment = $data['comment'];
        $comment_text = html_entity_decode(strip_tags($comment->content ?? ''), ENT_QUOTES, 'UTF-8');
        $replacements['{comment_content}'] = $comment_text;
        $replacements['{comment_body}'] = $comment_text;
        $replacements['{task_comment}'] = $comment_text;
        $replacements['{comment_staff_name}'] = $data['comment_staff_name'] ?? '';
    }

    // Reminder
    if (isset($data['reminder'])) {
        $rem = $data['reminder'];
        $replacements['{reminder_description}'] = strip_tags($rem['description'] ?? '');
        $replacements['{reminder_date}'] = isset($rem['date']) ? _dt($rem['date']) : '';
        $replacements['{reminder_rel_name}'] = $data['rel_name'] ?? '';
        $replacements['{staff_name}'] = $data['staff_name'] ?? '';
        $replacements['{assigned_staff_name}'] = $data['assigned_staff_name'] ?? '';
    }

    // Perform all replacements
    $message = str_replace(array_keys($replacements), array_values($replacements), $message);

    // Final cleanup: Remove any remaining unparsed tags to avoid leaking them to users
    // We only clear common tags that are likely to be in templates
    $tags_to_clear = [
        '{staff_firstname}',
        '{staff_lastname}',
        '{staff_name}',
        '{staff_phone}',
        '{lead_name}',
        '{lead_email}',
        '{lead_phone}',
        '{lead_phonenumber}',
        '{lead_status}',
        '{lead_assignee}',
        '{assigned_staff_name}',
        '{lead_id}',
        '{company}',
        '{customer_id}',
        '{customer_name}',
        '{customer_company}',
        '{customer_phone}',
        '{reminder_description}',
        '{reminder_date}',
        '{reminder_rel_name}'
    ];
    $message = str_replace($tags_to_clear, '', $message);

    return $message;
}

// ---------------- HANDLERS ---------------- //

function bizbot_hook_client_created($client_id)
{
    $CI = & get_instance();
    $client = $CI->clients_model->get($client_id);
    bizbot_check_trigger_and_send('client_created', ['client' => $client]);
}

function bizbot_hook_client_login($contact_id)
{
    // Perfex hook logic might vary by version for login
    // assuming contact id is passed
    // We need contact and client info
    $CI = & get_instance();
    $contact = $CI->clients_model->get_contact($contact_id);
    if ($contact) {
        $client = $CI->clients_model->get($contact->userid);
        bizbot_check_trigger_and_send('client_login', ['client' => $client, 'contact' => $contact]);
    }
}

function bizbot_hook_client_updated($client_id)
{
    $CI = & get_instance();
    $client = $CI->clients_model->get($client_id);
    bizbot_check_trigger_and_send('client_updated', ['client' => $client]);
}

// Lead Hooks
function bizbot_hook_lead_created($lead_id)
{
    $CI = & get_instance();
    $CI->load->model('leads_model');
    $lead = $CI->leads_model->get($lead_id);

    // 1. Auto-sync Bizbot thread GUID (check if contact exists in Bizbot)
    if ($lead && !empty($lead->phonenumber)) {
        bizbot_auto_sync_thread_guid($lead_id, $lead->phonenumber);
    }

    // 2. Immediate Notification (Existing)
    bizbot_check_trigger_and_send('lead_created', ['lead' => $lead]);

    // 3. Schedule Follow-ups
    bizbot_schedule_followups($lead_id);
}

/**
 * Auto-sync Bizbot thread GUID for a lead by searching Bizbot API with phone number.
 * Called on lead creation (manual or auto) and can be called manually.
 */
function bizbot_auto_sync_thread_guid($lead_id, $phone)
{
    $CI = & get_instance();

    // Check if thread_guid already exists for this lead
    $CI->db->select('cv.value');
    $CI->db->from(db_prefix() . 'customfieldsvalues cv');
    $CI->db->join(db_prefix() . 'customfields cf', 'cf.id = cv.fieldid');
    $CI->db->where('cf.slug', 'leads_bizbot_thread_guid');
    $CI->db->where('cv.relid', $lead_id);
    $existing = $CI->db->get()->row();

    if ($existing && !empty($existing->value)) {
        return; // Already has thread_guid
    }

    // Clean phone number
    $clean_phone = preg_replace('/[^0-9]/', '', $phone);
    if (empty($clean_phone))
        return;

    // Try multiple phone formats for BD numbers
    $phones_to_try = [$clean_phone];
    if (strpos($clean_phone, '880') === 0) {
        $phones_to_try[] = '+880' . substr($clean_phone, 3);
        $phones_to_try[] = '0' . substr($clean_phone, 3);
    }
    elseif (strpos($clean_phone, '0') === 0 && strlen($clean_phone) == 11) {
        $phones_to_try[] = '880' . substr($clean_phone, 1);
        $phones_to_try[] = '+880' . substr($clean_phone, 1);
    }

    try {
        $CI->load->library('bizbot/Bizbot_api', null, 'bizbot_api');
        $sync_start = microtime(true);

        foreach ($phones_to_try as $try_phone) {
            // Bail if we've already spent too much time here
            if (microtime(true) - $sync_start > 4.0) {
                bizbot_log("AUTO-SYNC: Bailing out due to timeout (took > 4s)");
                break;
            }

            $result = $CI->bizbot_api->search_contact_by_phone($try_phone);

            if ($result['status'] === 'success' && !empty($result['data'])) {
                $contact_data = $result['data'];

                // Handle array or single contact response
                if (isset($contact_data['data']) && is_array($contact_data['data'])) {
                    $contacts = $contact_data['data'];
                }
                elseif (isset($contact_data['guid'])) {
                    $contacts = [$contact_data];
                }
                else {
                    $contacts = is_array($contact_data) ? $contact_data : [];
                }

                foreach ($contacts as $contact) {
                    $contact_guid = $contact['guid'] ?? '';
                    if (empty($contact_guid))
                        continue;

                    // Try to find thread for this contact via the threads API
                    $thread_guid = bizbot_find_thread_for_contact($CI, $contact_guid);

                    if (!empty($thread_guid)) {
                        bizbot_save_thread_guid_to_lead($lead_id, $thread_guid);
                        bizbot_log("AUTO-SYNC: Lead #$lead_id linked to thread $thread_guid (phone: $try_phone)");
                        return;
                    }
                }
            }
        }

        bizbot_log("AUTO-SYNC: No Bizbot thread found for lead #$lead_id (phone: $clean_phone)");
    }
    catch (\Throwable $e) {
        bizbot_log("AUTO-SYNC ERROR: " . $e->getMessage());
    }
}

/**
 * Find thread GUID for a contact GUID using Bizbot API
 */
function bizbot_find_thread_for_contact($CI, $contact_guid)
{
    $api_key = get_option('bizbot_api_key');
    if (empty($api_key))
        return '';

    // Try to get threads and find one matching this contact
    $endpoints = [
        'https://api.bizbot.one/public/v1/threads?contact=' . $contact_guid,
        'https://api.bizbot.one/public/v1/chat/threads?contact=' . $contact_guid,
    ];

    $headers = [
        'Content-Type: application/json',
        'x-api-key: ' . $api_key
    ];

    foreach ($endpoints as $url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);

        $ssl_verify = get_option('bizbot_ssl_verify');
        if ($ssl_verify === '0') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code >= 200 && $http_code < 300) {
            $data = json_decode($response, true);
            // Find the most recent thread
            $threads = [];
            if (isset($data['data']) && is_array($data['data'])) {
                $threads = $data['data'];
            }
            elseif (isset($data[0])) {
                $threads = $data;
            }

            if (!empty($threads)) {
                // Return the most recent thread's GUID
                $latest = $threads[0];
                return $latest['guid'] ?? '';
            }
        }
    }

    return '';
}

/**
 * Save thread_guid to lead's custom field
 */
function bizbot_save_thread_guid_to_lead($lead_id, $thread_guid)
{
    if (empty($thread_guid))
        return;

    $CI = & get_instance();

    $CI->db->where('slug', 'leads_bizbot_thread_guid');
    $field = $CI->db->get(db_prefix() . 'customfields')->row();
    if (!$field)
        return;

    $CI->db->where('relid', $lead_id);
    $CI->db->where('fieldid', $field->id);
    $CI->db->where('fieldto', 'leads');
    $exists = $CI->db->get(db_prefix() . 'customfieldsvalues')->row();

    if ($exists) {
        $CI->db->where('id', $exists->id);
        $CI->db->update(db_prefix() . 'customfieldsvalues', ['value' => $thread_guid]);
    }
    else {
        $CI->db->insert(db_prefix() . 'customfieldsvalues', [
            'relid' => $lead_id,
            'fieldid' => $field->id,
            'fieldto' => 'leads',
            'value' => $thread_guid
        ]);
    }
}

function bizbot_schedule_followups($lead_id)
{
    $CI = & get_instance();
    $CI->load->model('bizbot/bizbot_model');
    $CI->load->model('leads_model');

    $lead = $CI->leads_model->get($lead_id);
    if (!$lead || empty($lead->phonenumber)) {
        return;
    }

    $followups = $CI->bizbot_model->get_followups();
    foreach ($followups as $f) {
        if (!$f['active'])
            continue;

        // Check blacklist statuses
        if (!empty($f['blacklist_statuses'])) {
            $blacklist = json_decode($f['blacklist_statuses'], true) ?: [];
            if (in_array($lead->status, $blacklist)) {
                continue; // Skip this followup for blacklisted status
            }
        }

        // Calculate delay based on unit
        $delay_value = isset($f['delay_value']) ? (int)$f['delay_value'] : (isset($f['delay_hours']) ? (int)$f['delay_hours'] : 1);
        $delay_unit = isset($f['delay_unit']) ? $f['delay_unit'] : 'hours';

        switch ($delay_unit) {
            case 'minutes':
                $scheduled_at = date('Y-m-d H:i:s', strtotime("+{$delay_value} minutes"));
                break;
            case 'days':
                $scheduled_at = date('Y-m-d H:i:s', strtotime("+{$delay_value} days"));
                break;
            case 'weeks':
                $scheduled_at = date('Y-m-d H:i:s', strtotime("+{$delay_value} weeks"));
                break;
            case 'hours':
            default:
                $scheduled_at = date('Y-m-d H:i:s', strtotime("+{$delay_value} hours"));
                break;
        }

        $CI->db->insert(db_prefix() . 'bizbot_queue', [
            'rel_id' => $lead_id,
            'rel_type' => 'lead',
            'rule_id' => $f['id'],
            'phone' => $lead->phonenumber,
            'message' => $f['message'],
            'scheduled_at' => $scheduled_at,
            'status' => 'pending',
            'original_status' => $lead->status,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
}

function bizbot_hook_lead_status_changed($data)
{
    // $data = ['lead_id' => x, 'old_status' => y, 'new_status' => z];
    $CI = & get_instance();
    $CI->load->model('leads_model');
    $lead = $CI->leads_model->get($data['lead_id']);

    // Fetch status name
    $status = $CI->leads_model->get_status($data['new_status']);

    $payload = [
        'lead' => $lead,
        'lead_status_name' => $status->name,
        'exclude_staff_id' => get_staff_user_id()
    ];

    bizbot_log("DEBUG: Lead status changed to: " . $status->name);

    // Trigger standard status changed event
    bizbot_check_trigger_and_send('lead_status_changed', $payload);

    // Check if it's "Lost"
    // Perfex statuses are dynamic, but we can check if name is "Lost" or if 'lost' column is set
    if (strtolower($status->name) == 'lost' || (!empty($lead->lost) && $lead->lost == 1)) {
        bizbot_log("DEBUG: Triggering lead_lost event");
        bizbot_check_trigger_and_send('lead_lost', $payload);
    }
}

function bizbot_hook_lead_assigned($lead_id)
{
    $CI = & get_instance();
    $CI->load->model('leads_model');
    $lead = $CI->leads_model->get($lead_id);
    if ($lead) {
        bizbot_check_trigger_and_send('lead_assigned', ['lead' => $lead]);
    }
}

function bizbot_hook_lead_converted($data)
{
    // $data = ['lead_id' => x, 'customer_id' => y];
    $CI = & get_instance();
    $CI->load->model('leads_model');
    $CI->load->model('clients_model');
    $lead = $CI->leads_model->get($data['lead_id']);
    $client = $CI->clients_model->get($data['customer_id']);
    bizbot_check_trigger_and_send('lead_converted', ['lead' => $lead, 'client' => $client]);
}

// Task Hooks
function bizbot_hook_task_created($task_id)
{
    bizbot_log("DEBUG: Hook task_created triggered for ID: $task_id");
    $CI = & get_instance();

    // Ensure models are loaded in case this is fired early in the request lifecycle
    $CI->load->model('tasks_model');
    $CI->load->model('clients_model');
    $CI->load->model('leads_model');

    $task = $CI->tasks_model->get($task_id);
    if (!$task) {
        return;
    }

    $payload = ['task' => $task];
    _bizbot_attach_task_relations($payload, $task);

    bizbot_check_trigger_and_send('task_created', $payload);
}

/**
 * Helper to attach client/lead to payload based on task relation
 */
function _bizbot_attach_task_relations(&$payload, $task)
{
    $CI = & get_instance();
    if (!empty($task->rel_type) && !empty($task->rel_id)) {
        switch ($task->rel_type) {
            case 'customer':
            case 'client':
                $CI->load->model('clients_model');
                $client = $CI->clients_model->get($task->rel_id);
                if ($client) {
                    $payload['client'] = $client;
                }
                break;
            case 'lead':
                $CI->load->model('leads_model');
                $lead = $CI->leads_model->get($task->rel_id);
                if ($lead) {
                    $payload['lead'] = $lead;
                }
                break;
        }
    }
}

function bizbot_hook_task_completed($data)
{
    // $data = ['status' => x, 'task_id' => y];
    if ($data['status'] == 5) { // 5 is usually completed in Perfex
        $CI = & get_instance();
        $task = $CI->tasks_model->get($data['task_id']);
        bizbot_check_trigger_and_send('task_completed', [
            'task' => $task,
            'exclude_staff_id' => get_staff_user_id()
        ]);
    }
}

function bizbot_hook_task_status_changed($data)
{
    // $data = ['status' => x, 'task_id' => y]
    $CI = & get_instance();
    $CI->load->model('tasks_model');
    $task = $CI->tasks_model->get($data['task_id']);

    if (!$task)
        return;

    // Get status name
    $status_name = format_task_status($data['status'], false, true);

    $payload = [
        'task' => $task,
        'task_status_name' => $status_name,
        'exclude_staff_id' => get_staff_user_id()
    ];
    _bizbot_attach_task_relations($payload, $task);

    bizbot_check_trigger_and_send('task_status_changed', $payload);

    // Backward compatibility / specific completion event
    if ($data['status'] == 5) {
        $completed_payload = [
            'task' => $task,
            'exclude_staff_id' => get_staff_user_id()
        ];
        _bizbot_attach_task_relations($completed_payload, $task);
        bizbot_check_trigger_and_send('task_completed', $completed_payload);
    }
}

function bizbot_hook_task_assigned($data)
{
    // $data = ['task_id' => x, 'assignee' => y];
    $CI = & get_instance();
    $task = $CI->tasks_model->get($data['task_id']);
    $staff = $CI->staff_model->get($data['assignee']); // Fetch assigned staff
    bizbot_check_trigger_and_send('task_assigned', ['task' => $task, 'staff_id' => $data['assignee'], 'staff' => $staff]);
}

// Ticket Hooks
function bizbot_hook_ticket_created($ticket_id)
{
    $CI = & get_instance();
    $CI->load->model('tickets_model');
    $ticket = $CI->tickets_model->get($ticket_id);
    bizbot_check_trigger_and_send('ticket_created', ['ticket' => $ticket]);
}

function bizbot_hook_ticket_reply_added($data)
{
    // $data = ['ticket_id' => x, 'reply_id' => y, 'message' => z, 'attachments' => ...];
    // if reply is from admin, maybe notify customer
    // This hook fires for both admin and client replies usually
    $CI = & get_instance();
    $CI->load->model('tickets_model');
    $ticket = $CI->tickets_model->get($data['ticket_id']);
    bizbot_check_trigger_and_send('ticket_reply_added', ['ticket' => $ticket]);
}

function bizbot_hook_ticket_closed($data)
{
    // $data = ['ticket_id' => x, 'status' => y];
    if ($data['status'] == 2) { // 2 = Closed usually
        $CI = & get_instance();
        $CI->load->model('tickets_model');
        $ticket = $CI->tickets_model->get($data['ticket_id']);
        bizbot_check_trigger_and_send('ticket_closed', ['ticket' => $ticket]);
    }
}

// Invoice Hooks
function bizbot_hook_invoice_created($invoice_id)
{
    $CI = & get_instance();
    $CI->load->model('invoices_model');
    $invoice = $CI->invoices_model->get($invoice_id);
    $client = $CI->clients_model->get($invoice->clientid);
    bizbot_check_trigger_and_send('invoice_created', ['invoice' => $invoice, 'client' => $client]);
}

function bizbot_hook_invoice_sent($invoice_id)
{
    $CI = & get_instance();
    $CI->load->model('invoices_model');
    $invoice = $CI->invoices_model->get($invoice_id);
    $client = $CI->clients_model->get($invoice->clientid);
    bizbot_check_trigger_and_send('invoice_sent', ['invoice' => $invoice, 'client' => $client]);
}

function bizbot_hook_invoice_overdue($data)
{
    // $data = ['invoice_id' => x]; -- check perfex version, sometimes just ID
    $id = is_array($data) ? $data['invoice_id'] : $data;
    $CI = & get_instance();
    $CI->load->model('invoices_model');
    $invoice = $CI->invoices_model->get($id);
    $client = $CI->clients_model->get($invoice->clientid);
    bizbot_check_trigger_and_send('invoice_overdue', ['invoice' => $invoice, 'client' => $client]);
}

function bizbot_hook_invoice_paid($payment_id)
{
    $CI = & get_instance();
    $CI->load->model('payments_model');
    $payment = $CI->payments_model->get($payment_id);
    // get invoice
    $CI->load->model('invoices_model');
    $invoice = $CI->invoices_model->get($payment->invoiceid);

    // Check if fully paid? The requirement says 'Invoice Paid', maybe 'after_payment_added' is enough?
    // If we want status == paid (2), we check here.
    if ($invoice->status == 2) {
        $client = $CI->clients_model->get($invoice->clientid);
        bizbot_check_trigger_and_send('invoice_paid', ['invoice' => $invoice, 'client' => $client]);
    }
}

// Reminder Hooks
function bizbot_hook_sms_trigger_whatsapp($data)
{
    // $data = ['message' => $message, 'trigger' => $trigger, 'phone' => $phone]
    if ($data['trigger'] == 'staff_reminder') {
        // For staff reminders, we can use a specific event
        // The message is already parsed by Perfex's SMS system, 
        // but we might want to re-parse it with our templates?
        // Or simply send the already parsed message if template message is empty?

        // Let's check if we have a template for this
        $CI = & get_instance();
        $CI->load->model('bizbot/bizbot_model');
        $CI->load->library('bizbot/bizbot_api');
        $template = $CI->bizbot_model->get_template('staff_reminder');

        if ($template && $template->active) {
            // If we have a custom template, we should ideally have the raw data.
            // But since we are hooking into SMS trigger, we mostly have the final message.
            // We'll treat it as a generic sender.
            $CI->bizbot_api->send_message($data['phone'], $data['message'], 'staff_reminder');
        }
    }
}

// Task Comment Hook
function bizbot_hook_task_comment_added($data)
{
    // $data = ['task_id' => $task_id, 'comment_id' => $comment_id]
    $CI = & get_instance();
    $CI->load->model('tasks_model');
    $task = $CI->tasks_model->get($data['task_id']);

    $CI->db->where('id', $data['comment_id']);
    $comment = $CI->db->get(db_prefix() . 'task_comments')->row();

    if ($task && $comment) {
        $staff_name = '';
        if ($comment->staffid != 0) {
            $staff = $CI->staff_model->get($comment->staffid);
            if ($staff) {
                $staff_name = $staff->firstname . ' ' . $staff->lastname;
            }
        }
        else {
            $staff_name = 'Customer';
        }

        $payload = [
            'task' => $task,
            'comment' => $comment,
            'comment_staff_name' => $staff_name,
            'exclude_staff_id' => $comment->staffid // Exclude the person who made the comment
        ];
        _bizbot_attach_task_relations($payload, $task);

        bizbot_check_trigger_and_send('task_comment_added', $payload);
    }
}

function bizbot_reminder_modal_fields($reminder)
{
    $CI = & get_instance();

    $CI->load->model('bizbot/bizbot_model');

    $rel_type = $CI->input->get('rel_type');

    // Check view variables if rel_type is not in GET
    if (empty($rel_type)) {
        $rel_type = $CI->load->get_var('name') ?: $CI->load->get_var('rel_type');
    }

    // URI fallback - common in Perfex for specific sections
    if (empty($rel_type)) {
        if ($CI->uri->segment(3) == 'leads' || $CI->uri->segment(2) == 'leads') {
            $rel_type = 'lead';
        }
    }

    // For existing reminders, rel_type is in the reminder object
    if (!empty($reminder)) {
        if (is_object($reminder) && isset($reminder->rel_type)) {
            $rel_type = $reminder->rel_type;
        }
        elseif (is_array($reminder) && isset($reminder['rel_type'])) {
            $rel_type = $reminder['rel_type'];
        }
    }

    $lead_template = $CI->bizbot_model->get_template('lead_reminder');

    // Choose staff template based on rel_type
    $staff_template_slug = ($rel_type == 'lead') ? 'lead_staff_reminder' : 'staff_reminder';
    $staff_template = $CI->bizbot_model->get_template($staff_template_slug);

    $lead_checked = '';
    $staff_checked = '';

    if (empty($reminder) || !isset($reminder->id)) {
        // Defaults for new reminders
        if ($rel_type == 'lead') {
            if ($lead_template && $lead_template->active)
                $lead_checked = 'checked';
            if ($staff_template && $staff_template->active)
                $staff_checked = 'checked';
        }
        else {
            if ($staff_template && $staff_template->active)
                $staff_checked = 'checked';
        }
    }
    else {
        if (isset($reminder->notify_lead_by_wa) && $reminder->notify_lead_by_wa == 1)
            $lead_checked = 'checked';
        if (isset($reminder->notify_staff_by_wa) && $reminder->notify_staff_by_wa == 1)
            $staff_checked = 'checked';
        // Fallback for legacy reminders
        if (empty($lead_checked) && empty($staff_checked) && isset($reminder->notify_by_whatsapp) && $reminder->notify_by_whatsapp == 1) {
            if ($rel_type == 'lead')
                $lead_checked = 'checked';
            $staff_checked = 'checked';
        }
    }

    echo '<div class="row">';

    if ($rel_type == 'lead') {
        $lead_note = (!$lead_template || !$lead_template->active) ? ' <span class="text-danger" style="font-size:11px;">(Lead Template Disabled)</span>' : '';
        echo '<div class="col-md-6">
                <div class="checkbox checkbox-primary">
                    <input type="checkbox" name="notify_lead_by_wa" id="notify_lead_by_wa" value="1" ' . $lead_checked . '>
                    <label for="notify_lead_by_wa">Send to Lead' . $lead_note . '</label>
                </div>
            </div>';
    }

    $staff_note_text = ($rel_type == 'lead') ? '(Lead Staff Template Disabled)' : '(Staff Template Disabled)';
    $staff_note = (!$staff_template || !$staff_template->active) ? ' <span class="text-danger" style="font-size:11px;">' . $staff_note_text . '</span>' : '';
    $col_width = ($rel_type == 'lead') ? '6' : '12';
    echo '<div class="col-md-' . $col_width . '">
            <div class="checkbox checkbox-primary">
                <input type="checkbox" name="notify_staff_by_wa" id="notify_staff_by_wa" value="1" ' . $staff_checked . '>
                <label for="notify_staff_by_wa">Send to Staff' . $staff_note . '</label>
            </div>
        </div>';

    // Hidden master field for backward compatibility if needed by any third party
    echo '<input type="hidden" name="notify_by_whatsapp" id="notify_by_whatsapp" value="1">';

    echo '</div>';
}

function bizbot_reminder_scripts()
{
?>
<script>
    $(function () {
        // Listen for AJAX requests that fetch reminder data
        $(document).ajaxComplete(function (event, xhr, settings) {
            if (settings.url.indexOf('misc/get_reminder') !== -1 || settings.url.indexOf('tasks/get_reminder') !== -1) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    // Find the appropriate container (modal or task sidebar toggle)
                    var $container = $('.reminder-modal-' + response.rel_type + '-' + response.rel_id);
                    if ($container.length === 0 && $("body").hasClass("all-reminders")) {
                        $container = $(".reminder-modal--");
                    } else if ($("#task-modal").is(":visible")) {
                        $container = $("#newTaskReminderToggle");
                    }

                    if ($container && $container.length > 0) {
                        // Update the WhatsApp checkboxes state
                        $container.find('#notify_lead_by_wa').prop('checked', (response.notify_lead_by_wa == 1));
                        $container.find('#notify_staff_by_wa').prop('checked', (response.notify_staff_by_wa == 1));

                        // Support legacy data display
                        if (response.notify_by_whatsapp == 1 && response.notify_lead_by_wa == 0 && response.notify_staff_by_wa == 0) {
                            if (response.rel_type == 'lead') $container.find('#notify_lead_by_wa').prop('checked', true);
                            $container.find('#notify_staff_by_wa').prop('checked', true);
                        }
                    }
                } catch (e) {
                    // Not a valid JSON or other error
                }
            }
        });

        // Inject WhatsApp checkboxes into reminder modal (in case core hook is missing)
        $(document).on('show.bs.modal', '.modal-reminder, .reminder-modal', function (e) {
            var $modal = $(this);
            if ($modal.find('#notify_lead_by_wa').length === 0) {
                var fields = `<?php echo preg_replace("/\r|\n/", "", bizbot_get_reminder_fields_html()); ?>`;
                // Inject after email notification group
                var $emailGroup = $modal.find('input[name="notify_by_email"]').closest('.form-group');
                if ($emailGroup.length > 0) {
                    $emailGroup.after(fields);
                } else {
                    // Fallback: inject before footer
                    $modal.find('form').append(fields);
                }
            }
        });
    });
</script>
<?php
}

/**
 * Returns the HTML for the WhatsApp reminder fields
 */
function bizbot_get_reminder_fields_html()
{
    ob_start();
    // Pass empty reminder to trigger defaults
    bizbot_reminder_modal_fields([]);
    return ob_get_clean();
}

// CRON JOB
function bizbot_cron()
{
    bizbot_log("CRON: bizbot_cron started");
    $CI = & get_instance();
    $CI->load->model('bizbot/Bizbot_model', 'bizbot_model');
    $CI->load->library('bizbot/Bizbot_api', null, 'bizbot_api');
    $CI->load->model('leads_model');

    // Batch processing: Limit messages per run to avoid timeouts
    $CI->db->where('status', 'pending');
    $CI->db->where('scheduled_at <=', date('Y-m-d H:i:s'));
    $CI->db->limit(50); // Process 50 at a time
    $queue = $CI->db->get(db_prefix() . 'bizbot_queue')->result_array();

    foreach ($queue as $item) {
        // Atomic status update: Mark as processing BEFORE sending
        $CI->db->where('id', $item['id']);
        $CI->db->update(db_prefix() . 'bizbot_queue', ['status' => 'processing']);

        $send = true;
        $data = [];

        // Condition Check (Currently only Lead Status supported in UI)
        if ($item['rel_type'] == 'lead') {
            $lead = $CI->leads_model->get($item['rel_id']);
            if (!$lead) {
                $send = false;
            }
            else {
                $data['lead'] = $lead;

                // Advanced condition check using rule_id
                if (!empty($item['rule_id'])) {
                    $CI->db->where('id', $item['rule_id']);
                    $rule = $CI->db->get(db_prefix() . 'bizbot_followups')->row();

                    if ($rule) {
                        // Check stop_on_status_change - compare original status with current
                        if (!empty($rule->stop_on_status_change) && !empty($item['original_status'])) {
                            if ($lead->status != $item['original_status']) {
                                $send = false;
                                $CI->db->where('id', $item['id']);
                                $CI->db->update(db_prefix() . 'bizbot_queue', ['status' => 'cancelled']);
                                continue;
                            }
                        }

                        // Check target status_id condition
                        if ($rule->status_id && $lead->status != $rule->status_id) {
                            $send = false;
                            $CI->db->where('id', $item['id']);
                            $CI->db->update(db_prefix() . 'bizbot_queue', ['status' => 'cancelled']);
                            continue;
                        }

                        // Check blacklist statuses
                        if (!empty($rule->blacklist_statuses)) {
                            $blacklist = json_decode($rule->blacklist_statuses, true) ?: [];
                            if (in_array($lead->status, $blacklist)) {
                                $send = false;
                                $CI->db->where('id', $item['id']);
                                $CI->db->update(db_prefix() . 'bizbot_queue', ['status' => 'cancelled']);
                                continue;
                            }
                        }

                        // Check max_per_lead limit
                        if (!empty($rule->max_per_lead) && $rule->max_per_lead > 0) {
                            $CI->db->where([
                                'rel_id' => $item['rel_id'],
                                'rel_type' => 'lead',
                                'status' => 'sent'
                            ]);
                            $sent_count = $CI->db->count_all_results(db_prefix() . 'bizbot_queue');

                            if ($sent_count >= $rule->max_per_lead) {
                                $send = false;
                                $CI->db->where('id', $item['id']);
                                $CI->db->update(db_prefix() . 'bizbot_queue', ['status' => 'cancelled']);
                                continue;
                            }
                        }
                    }
                }
            }
        }

        if ($send) {
            $message = bizbot_parse_placeholders($item['message'], 'followup', $data);
            $response = $CI->bizbot_api->send_message($item['phone'], $message, 'automated_followup');

            $new_status = ($response['status'] == 'success') ? 'sent' : 'failed';
            $CI->db->where('id', $item['id']);
            $CI->db->update(db_prefix() . 'bizbot_queue', ['status' => $new_status]);
        }
    }

    // --- IMPROVED: ALL REMINDERS ---
    $CI->load->model('staff_model');

    // Ensure timezone is correct for comparison
    $original_timezone = date_default_timezone_get();
    $timezone = get_option('default_timezone');
    if ($timezone) {
        date_default_timezone_set($timezone);
    }

    $CI->db->select(db_prefix() . 'reminders.*, phonenumber as staff_phonenumber, email as staff_email, firstname as staff_firstname, lastname as staff_lastname');
    $CI->db->join(db_prefix() . 'staff', db_prefix() . 'staff.staffid = ' . db_prefix() . 'reminders.staff');
    $CI->db->join(db_prefix() . 'bizbot_reminders_sent', db_prefix() . 'bizbot_reminders_sent.reminder_id = ' . db_prefix() . 'reminders.id', 'left');

    $CI->db->where('date <=', date('Y-m-d H:i:s'));
    $CI->db->where('date >=', date('Y-m-d H:i:s', strtotime('-48 hours')));

    // Check both new flags and legacy flag
    $CI->db->where('(notify_lead_by_wa = 1 OR notify_staff_by_wa = 1 OR notify_by_whatsapp = 1)');

    // Optimized: Only fetch if not already sent. Limit to 30 per run to prevent timeouts.
    $CI->db->where(db_prefix() . 'bizbot_reminders_sent.id IS NULL');
    $CI->db->limit(30);

    $reminders = $CI->db->get(db_prefix() . 'reminders')->result_array();

    if (count($reminders) > 0) {
        foreach ($reminders as $reminder) {
            if (empty($reminder['rel_id']))
                continue;

            $rel_name = '';
            $lead_phone = '';
            $rel_object = null;

            // Resolve relations
            if ($reminder['rel_type'] == 'lead') {
                $rel_object = $CI->leads_model->get($reminder['rel_id']);
                if ($rel_object) {
                    $rel_name = $rel_object->name;
                    $lead_phone = $rel_object->phonenumber;
                    $assigned_staff_id = $rel_object->assigned;
                }
            }
            elseif ($reminder['rel_type'] == 'task') {
                $CI->load->model('tasks_model');
                $rel_object = $CI->tasks_model->get($reminder['rel_id']);
                if ($rel_object)
                    $rel_name = $rel_object->name;
            }
            elseif ($reminder['rel_type'] == 'invoice') {
                $CI->load->model('invoices_model');
                $rel_object = $CI->invoices_model->get($reminder['rel_id']);
                if ($rel_object)
                    $rel_name = format_invoice_number($rel_object->id);
            }

            // Flags
            $send_to_lead = ($reminder['notify_lead_by_wa'] == 1);
            $send_to_staff = ($reminder['notify_staff_by_wa'] == 1);

            // Legacy support
            if (!$send_to_lead && !$send_to_staff && $reminder['notify_by_whatsapp'] == 1) {
                if ($reminder['rel_type'] == 'lead')
                    $send_to_lead = true;
                $send_to_staff = true;
            }

            $staff_maker_name = trim(($reminder['staff_firstname'] ?? '') . ' ' . ($reminder['staff_lastname'] ?? ''));
            $assigned_staff_name = '';
            if (!empty($assigned_staff_id)) {
                $as = $CI->staff_model->get($assigned_staff_id);
                if ($as)
                    $assigned_staff_name = trim(($as->firstname ?? '') . ' ' . ($as->lastname ?? ''));
            }

            $base_parsing_data = [
                'reminder' => $reminder,
                'staff_name' => $staff_maker_name,
                'assigned_staff_name' => $assigned_staff_name,
                'rel_name' => $rel_name
            ];

            // Add the related object to the parsing data
            if ($rel_object) {
                $base_parsing_data[$reminder['rel_type']] = $rel_object;
            }

            $any_sent = false;

            // 1. Send to Lead (if requested and is a lead)
            if ($send_to_lead && !empty($lead_phone)) {
                $template = $CI->bizbot_model->get_template('lead_reminder');
                if ($template && $template->active) {
                    $msg = bizbot_parse_placeholders($template->message, 'lead_reminder', $base_parsing_data);
                    $CI->bizbot_api->send_message($lead_phone, $msg, 'lead_reminder');
                    $any_sent = true;
                }
            }

            // 2. Send to Staff (if requested)
            if ($send_to_staff && !empty($reminder['staff_phonenumber'])) {
                // Template separation: Resolve specific template based on relation type
                $staff_template_slug = 'staff_reminder';
                if ($reminder['rel_type'] == 'lead') {
                    $staff_template_slug = 'lead_staff_reminder';
                }
                elseif ($reminder['rel_type'] == 'task') {
                    $staff_template_slug = 'task_reminder';
                }
                $template = $CI->bizbot_model->get_template($staff_template_slug);
                if ($template && $template->active) {
                    $msg = bizbot_parse_placeholders($template->message, $staff_template_slug, $base_parsing_data);

                    // Send to the Maker (Always if checkbox is ticked in modal)
                    $CI->bizbot_api->send_message($reminder['staff_phonenumber'], $msg, $staff_template_slug);
                    $any_sent = true;

                    // --- EXTRA: Send to other template-defined recipients if checked in settings ---

                    // a) Lead Assigned Staff (Uses send_to_admin flag in lead context)
                    if ($template->send_to_admin && $reminder['rel_type'] == 'lead' && !empty($assigned_staff_id)) {
                        $as = $CI->staff_model->get($assigned_staff_id);
                        if ($as && !empty($as->phonenumber) && $as->phonenumber != $reminder['staff_phonenumber']) {
                            $CI->bizbot_api->send_message($as->phonenumber, $msg, $staff_template_slug);
                        }
                    }

                    // b) Followers (Uses send_to_followers flag)
                    if ($template->send_to_followers && $reminder['rel_type'] == 'task') {
                        $CI->load->model('tasks_model');
                        $followers = $CI->tasks_model->get_task_followers($reminder['rel_id']);
                        foreach ($followers as $follower) {
                            $f_staff = $CI->staff_model->get($follower['followerid']);
                            if ($f_staff && !empty($f_staff->phonenumber) && $f_staff->phonenumber != $reminder['staff_phonenumber']) {
                                $CI->bizbot_api->send_message($f_staff->phonenumber, $msg, $staff_template_slug);
                            }
                        }
                    }

                    // c) Custom Numbers
                    if (!empty($template->custom_numbers)) {
                        $customs = explode(',', $template->custom_numbers);
                        foreach ($customs as $cn) {
                            if (!empty(trim($cn))) {
                                $CI->bizbot_api->send_message(trim($cn), $msg, $staff_template_slug);
                            }
                        }
                    }
                }
            }

            if ($any_sent) {
                $CI->db->insert(db_prefix() . 'bizbot_reminders_sent', [
                    'reminder_id' => $reminder['id'],
                    'sent_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }

    // --- PROJECT DEADLINE REMINDERS ---
    $deadline_template = $CI->bizbot_model->get_template('project_deadline_reminder');
    if ($deadline_template && $deadline_template->active) {
        $days_before = isset($deadline_template->days_before) ? (int)$deadline_template->days_before : 3;

        if ($days_before > 0) {
            $CI->db->where('deadline <=', date('Y-m-d', strtotime('+' . $days_before . ' days')));
            $CI->db->where('deadline >=', date('Y-m-d'));
            $CI->db->where('status !=', 4); // Not Finished (4 is usually finished)
            $projects = $CI->db->get(db_prefix() . 'projects')->result_array();

            foreach ($projects as $project_data) {
                $trigger_check = 'project_deadline_' . $project_data['id'] . '_' . date('Y-m-d');

                // Check if already sent for this project today
                $CI->db->where('event_trigger', $trigger_check);
                $already_sent = $CI->db->get(db_prefix() . 'bizbot_logs')->row();

                if (!$already_sent) {
                    // Fetch full project object for parser
                    $project = $CI->projects_model->get($project_data['id']);

                    // Get status name
                    $status_name = '';
                    $statuses = $CI->projects_model->get_project_statuses();
                    foreach ($statuses as $s) {
                        if ($s['id'] == $project->status) {
                            $status_name = $s['name'];
                            break;
                        }
                    }

                    bizbot_check_trigger_and_send('project_deadline_reminder', [
                        'project' => $project,
                        'project_status_name' => $status_name
                    ]);

                    // Log it to prevent duplicate sending today
                    $CI->bizbot_model->log_activity([
                        'phone' => 'Project Members',
                        'message' => 'Project Deadline Reminder Sent for: ' . $project->name,
                        'status' => 'success',
                        'event_trigger' => $trigger_check
                    ]);
                }
            }
        }
    }

    // Restore original timezone
    if (isset($original_timezone)) {
        date_default_timezone_set($original_timezone);
    }

    // --- AUTOMATIC LOG CLEANUP (30 days) ---
    $CI->bizbot_model->delete_old_logs(30);
}

// Table Row Decoration
function bizbot_filter_leads_table_whatsapp_button($row, $aRow)
{
    $CI = & get_instance();
    $CI->db->select('value');
    $CI->db->from(db_prefix() . 'customfieldsvalues');
    $CI->db->join(db_prefix() . 'customfields', db_prefix() . 'customfields.id = ' . db_prefix() . 'customfieldsvalues.fieldid');
    $CI->db->where('slug', 'leads_bizbot_thread_guid');
    $CI->db->where('relid', $aRow['id']);
    $field = $CI->db->get()->row();
    $thread_guid = $field ? $field->value : '';

    // In Leads table, PhoneNumber is usually at index 3 or 4
    foreach ($row as $key => $val) {
        if ($key > 2 && !empty($val) && preg_match('/^[0-9\+\-\s\(\)]{7,20}$/', strip_tags($val))) {
            $phone = preg_replace('/[^0-9]/', '', strip_tags($val));
            if (!empty($phone)) {
                // Priority: Bizbot chat with thread → Bizbot chat (phone search) → wa.me
                if (!empty($thread_guid)) {
                    $url = 'https://app.bizbot.one/app/chat/' . $thread_guid;
                    $icon_class = 'text-success'; // Green = linked
                    $title = 'Bizbot Chat (Linked)';
                }
                else {
                    $url = 'https://wa.me/' . $phone;
                    $icon_class = 'text-muted'; // Grey = not linked yet
                    $title = 'WhatsApp (Not synced with Bizbot)';
                }
                $row[$key] = $val . ' <a href="' . $url . '" target="_blank" class="' . $icon_class . ' mleft5" title="' . $title . '"><i class="fa-brands fa-whatsapp fa-lg"></i></a>';
                break;
            }
        }
    }
    return $row;
}

function bizbot_filter_contacts_table_whatsapp_button($row, $aRow)
{
    $CI = & get_instance();
    // Contacts don't have a dedicated thread_guid field yet, but we can check if there's a lead with this phone
    $phone = '';
    foreach ($row as $key => $val) {
        if ($key > 2 && !empty($val) && preg_match('/^[0-9\+\-\s\(\)]{7,20}$/', strip_tags($val))) {
            $phone = preg_replace('/[^0-9]/', '', strip_tags($val));
            break;
        }
    }

    $chat_url = '';
    if (!empty($phone)) {
        $CI->db->select('value');
        $CI->db->from(db_prefix() . 'customfieldsvalues');
        $CI->db->join(db_prefix() . 'customfields', db_prefix() . 'customfields.id = ' . db_prefix() . 'customfieldsvalues.fieldid');
        $CI->db->join(db_prefix() . 'leads', db_prefix() . 'leads.id = ' . db_prefix() . 'customfieldsvalues.relid');
        $CI->db->where('slug', 'leads_bizbot_thread_guid');
        $CI->db->where('phonenumber', $phone);
        $field = $CI->db->get()->row();
        if ($field) {
            $chat_url = 'https://app.bizbot.one/app/chat/' . $field->value;
        }
    }

    // In Contacts table, PhoneNumber is usually at index 3 or 4
    foreach ($row as $key => $val) {
        if ($key > 2 && !empty($val) && preg_match('/^[0-9\+\-\s\(\)]{7,20}$/', strip_tags($val))) {
            $phone = preg_replace('/[^0-9]/', '', strip_tags($val));
            if (!empty($phone)) {
                $url = !empty($chat_url) ? $chat_url : 'https://wa.me/' . $phone;
                $row[$key] = $val . ' <a href="' . $url . '" target="_blank" class="text-success mleft5" title="Chat in Bizbot"><i class="fa-brands fa-whatsapp fa-lg"></i></a>';
                break; // Only once
            }
        }
    }
    return $row;
}

// Milestone Hooks
function bizbot_hook_milestone_added($id)
{
    bizbot_handle_milestone_notifications($id, 'project_milestone');
}

function bizbot_hook_milestone_updated($id)
{
    bizbot_handle_milestone_notifications($id, 'project_milestone');
}

function bizbot_handle_milestone_notifications($id, $event_slug)
{
    $CI = & get_instance();
    $CI->db->where('id', $id);
    $milestone = $CI->db->get(db_prefix() . 'milestones')->row();

    if ($milestone) {
        $CI->load->model('projects_model');
        $project = $CI->projects_model->get($milestone->project_id);

        if ($project) {
            $CI->load->model('clients_model');
            $client = $CI->clients_model->get($project->clientid);

            bizbot_check_trigger_and_send($event_slug, [
                'milestone' => $milestone,
                'project' => $project,
                'client' => $client
            ]);
        }
    }
}

// Project Hooks
function bizbot_hook_project_created($id)
{
    $CI = & get_instance();
    $CI->load->model('projects_model');
    $project = $CI->projects_model->get($id);
    if ($project) {
        $CI->load->model('clients_model');
        $client = $CI->clients_model->get($project->clientid);

        $status_name = '';
        $statuses = $CI->projects_model->get_project_statuses();
        foreach ($statuses as $s) {
            if ($s['id'] == $project->status) {
                $status_name = $s['name'];
                break;
            }
        }

        bizbot_check_trigger_and_send('project_created', [
            'project' => $project,
            'client' => $client,
            'project_status_name' => $status_name,
            'exclude_staff_id' => get_staff_user_id()
        ]);
    }
}

function bizbot_hook_project_status_changed($data)
{
    // $data = ['status' => x, 'project_id' => y]
    $CI = & get_instance();
    $CI->load->model('projects_model');
    $project = $CI->projects_model->get($data['project_id']);
    if ($project) {
        $CI->load->model('clients_model');
        $client = $CI->clients_model->get($project->clientid);

        $status_name = '';
        $statuses = $CI->projects_model->get_project_statuses();
        foreach ($statuses as $s) {
            if ($s['id'] == $data['status']) {
                $status_name = $s['name'];
                break;
            }
        }

        bizbot_check_trigger_and_send('project_status_changed', [
            'project' => $project,
            'client' => $client,
            'project_status_name' => $status_name,
            'exclude_staff_id' => get_staff_user_id()
        ]);
    }
}

/**
 * Triggered after a project discussion comment is added
 */
function bizbot_hook_project_discussion_comment_added($comment_id)
{
    $CI = & get_instance();
    $CI->load->model('projects_model');
    $comment = $CI->projects_model->get_discussion_comment($comment_id);
    if ($comment) {
        // Find project_id
        $project_id = 0;
        if ($comment->discussion_type == 'regular') {
            $discussion = $CI->projects_model->get_discussion($comment->discussion_id);
            if ($discussion) {
                $project_id = $discussion->project_id;
            }
        }
        else {
            // File discussion
            $file = $CI->projects_model->get_file($comment->discussion_id);
            if ($file) {
                $project_id = $file->project_id;
            }
        }

        if ($project_id) {
            $project = $CI->projects_model->get($project_id);
            $CI->load->model('clients_model');
            $client = $CI->clients_model->get($project->clientid);

            bizbot_check_trigger_and_send('project_discussion_comment_added', [
                'project' => $project,
                'client' => $client,
                'comment' => $comment,
                'exclude_staff_id' => get_staff_user_id()
            ]);
        }
    }
}

/**
 * Triggered after a project discussion is created
 */
function bizbot_hook_project_discussion_created($discussion_id)
{
    $CI = & get_instance();
    $CI->load->model('projects_model');
    $discussion = $CI->projects_model->get_discussion($discussion_id);
    if ($discussion) {
        $project = $CI->projects_model->get($discussion->project_id);
        $CI->load->model('clients_model');
        $client = $CI->clients_model->get($project->clientid);

        bizbot_check_trigger_and_send('project_discussion_created', [
            'project' => $project,
            'client' => $client,
            'discussion' => $discussion,
            'exclude_staff_id' => get_staff_user_id()
        ]);
    }
}

/**
 * Automatically inject widget script into CRM portals
 */
function bizbot_inject_widget()
{
    $widget_id = htmlspecialchars(get_option('bizbot_widget_id'), ENT_QUOTES, 'UTF-8');
    $type = get_option('bizbot_widget_type') ?: '3';

    if (empty($widget_id)) {
        return;
    }

    $is_admin = function_exists('is_staff_logged_in') && is_staff_logged_in();
    $is_client = function_exists('is_client_logged_in') && is_client_logged_in();

    $show_admin = get_option('bizbot_widget_show_to_admin');
    $show_client = get_option('bizbot_widget_show_to_customer');

    $should_show = false;
    if ($is_admin && $show_admin == '1') {
        $should_show = true;
    }
    elseif ($is_client && $show_client == '1') {
        $should_show = true;
    }
    elseif (!$is_admin && !$is_client && $show_client == '1') {
        // Show to guests on client portal if enabled for customer
        $should_show = true;
    }

    if ($should_show) {
        $script = '';
        if ($type == '2') {
            $script = "(function(d, s, id){
              var js, fjs = d.getElementsByTagName(s)[0];
              if (d.getElementById(id)) {return;}
              js = d.createElement(s); js.id = id;
              js.src = 'https://api.bizbot.one/widget/{$widget_id}?r=' + encodeURIComponent(window.location);
              fjs.parentNode.insertBefore(js, fjs);
            }(document, 'script', 'contactus-jssdk'));";
        }
        elseif ($type == '3') {
            $script = "(function(d, s, id){
              var js, fjs = d.getElementsByTagName(s)[0];
              if (d.getElementById(id)) return;
              js = d.createElement(s); js.id = id;
              js.src = 'https://api.bizbot.one/widget2/load?id={$widget_id}&r=' + encodeURIComponent(window.location);
              fjs.parentNode.insertBefore(js, fjs);
            }(document, 'script', 'anw2-sdk-{$widget_id}'));";
        }
        else {
            $script = "(function(d, s, id){
              var js, fjs = d.getElementsByTagName(s)[0];
              if (d.getElementById(id)) {return;}
              js = d.createElement(s); js.id = id;
              js.src = 'https://api.bizbot.one/widget/{$widget_id}/livechat-js?r=' + encodeURIComponent(window.location);
              fjs.parentNode.insertBefore(js, fjs);
            }(document, 'script', 'contactus-jssdk'));";
        }
        echo '<script>' . $script . '</script>';
    }
}
/**
 * Handle runtime config (e.g. permitted URI chars)
 */
function bizbot_runtime_config()
{
    $CI = & get_instance();
    $permitted = $CI->config->item('permitted_uri_chars');
    if (strpos($permitted, '@') === false) {
        $CI->config->set_item('permitted_uri_chars', $permitted . '@');
    }
}

/**
 * Intercept POST requests to handle modular logic for reminders and milestones
 */
function bizbot_intercept_post()
{
    $CI = & get_instance();
    if (!$CI->input->post()) {
        return;
    }

    // 1. Intercept Reminders (Misc_model reversion support)
    $is_reminder_post = ($CI->input->post('rel_id') && ($CI->uri->segment(2) == 'misc' || $CI->uri->segment(2) == 'tasks') && ($CI->uri->segment(3) == 'add_reminder' || $CI->uri->segment(3) == 'edit_reminder' || $CI->uri->segment(3) == 'reminder'));

    if ($is_reminder_post) {
        bizbot_log("INTERCEPT: Reminder POST detected. rel_id=" . $CI->input->post('rel_id') . " rel_type=" . $CI->input->post('rel_type'));
        $GLOBALS['bizbot_pending_reminder'] = [
            'notify_lead_by_wa' => $CI->input->post('notify_lead_by_wa') ? 1 : 0,
            'notify_staff_by_wa' => $CI->input->post('notify_staff_by_wa') ? 1 : 0,
            'notify_by_whatsapp' => $CI->input->post('notify_by_whatsapp') ? 1 : 0,
            'rel_id' => $CI->input->post('rel_id'),
            'rel_type' => $CI->input->post('rel_type'),
            'staff' => $CI->input->post('staff'),
        ];
        bizbot_log("INTERCEPT: WA flags set to: lead=" . $GLOBALS['bizbot_pending_reminder']['notify_lead_by_wa'] . " staff=" . $GLOBALS['bizbot_pending_reminder']['notify_staff_by_wa']);

        // Register shutdown function to update the DB after core model finishes
        register_shutdown_function('bizbot_sync_reminder_data');
    }

    // 2. Intercept Milestones (Projects_model reversion support)
    $is_milestone_post = ($CI->uri->segment(2) == 'projects' && $CI->uri->segment(3) == 'milestone');
    if ($is_milestone_post) {
        $GLOBALS['bizbot_milestone_check'] = [
            'project_id' => $CI->input->post('project_id'),
            'name' => $CI->input->post('name'),
            'is_edit' => !empty($CI->uri->segment(4)),
            'id' => $CI->uri->segment(4)
        ];
        register_shutdown_function('bizbot_sync_milestone_data');
    }
}

/**
 * Sync reminder data after core model has executed
 */
function bizbot_sync_reminder_data()
{
    $CI = & get_instance();
    $pending = $GLOBALS['bizbot_pending_reminder'] ?? null;
    if (!$pending)
        return;

    // Wait a bit or verify if record was inserted/updated
    $CI->db->order_by('id', 'desc');
    $CI->db->limit(1);
    $last = $CI->db->get(db_prefix() . 'reminders')->row();

    // Check if it matches our pending reminder (by creator and rel_id)
    if ($last && $last->rel_id == $pending['rel_id']) {
        bizbot_log("SYNC: Found matching reminder ID=" . $last->id . ". Updating WA flags...");
        $CI->db->where('id', $last->id);
        $CI->db->update(db_prefix() . 'reminders', [
            'notify_lead_by_wa' => $pending['notify_lead_by_wa'],
            'notify_staff_by_wa' => $pending['notify_staff_by_wa'],
            'notify_by_whatsapp' => $pending['notify_by_whatsapp']
        ]);
    }
}

/**
 * Trigger milestone notifications after core model has executed
 */
function bizbot_sync_milestone_data()
{
    $CI = & get_instance();
    $check = $GLOBALS['bizbot_milestone_check'] ?? null;
    if (!$check)
        return;

    // Find the milestone
    $CI->db->where('project_id', $check['project_id']);
    $CI->db->where('name', $check['name']);
    $CI->db->order_by('id', 'desc');
    $CI->db->limit(1);
    $milestone = $CI->db->get(db_prefix() . 'milestones')->row();

    if ($milestone) {
        if ($check['is_edit']) {
            bizbot_hook_milestone_updated($milestone->id);
        }
        else {
            bizbot_hook_milestone_added($milestone->id);
        }
    }
}

/**
 * Filter project activity to trigger notifications without core edits
 */
function bizbot_filter_project_activity($data)
{
    // $data keys: description_key, additional_data, project_id, etc.
    if ($data['description_key'] == 'project_activity_created_discussion') {
        // Find the last discussion for this project
        $CI = & get_instance();
        $CI->db->where('project_id', $data['project_id']);
        $CI->db->where('subject', $data['additional_data']);
        $CI->db->order_by('id', 'desc');
        $CI->db->limit(1);
        $discussion = $CI->db->get(db_prefix() . 'projectdiscussions')->row();
        if ($discussion) {
            bizbot_hook_project_discussion_created($discussion->id);
        }
    }

    return $data;
}