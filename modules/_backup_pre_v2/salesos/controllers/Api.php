<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Internal REST-style API for JS frontend.
 * All methods return JSON. No page renders.
 */
class Api extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('salesos/salesos_model');
        $this->load->model('salesos/agents_model');

        if (!staff_can('view', 'salesos')) {
            $this->_json(['success' => false, 'error' => 'Access denied'], 403);
        }
    }

    // ── Originate outbound call ───────────────────────────────────────────────

    public function originate()
    {
        if (!staff_can('make', 'salesos')) {
            return $this->_json(['success' => false, 'error' => 'No permission to make calls']);
        }

        $number = $this->input->post('number');
        if (!$number) {
            return $this->_json(['success' => false, 'error' => 'Number required']);
        }

        $staff_id = get_staff_user_id();
        $agent    = $this->agents_model->get_by_staff_id($staff_id);

        if (!$agent) {
            return $this->_json(['success' => false, 'error' => 'No extension mapped for your account. Contact admin.']);
        }

        $vars = [];
        if ($lead_id = $this->input->post('lead_id')) {
            $vars['CRM_LEAD_ID'] = (int) $lead_id;
        }
        if ($contact_id = $this->input->post('contact_id')) {
            $vars['CRM_CONTACT_ID'] = (int) $contact_id;
        }

        $this->load->library('salesos/ami_service');
        $result = $this->ami_service->originate($number, (int) $agent['id'], $vars);

        // Pre-log the outbound call (will be confirmed by CDR sync)
        if ($result['success']) {
            $lead_id    = $vars['CRM_LEAD_ID']    ?? null;
            $contact_id = $vars['CRM_CONTACT_ID'] ?? null;

            $this->salesos_model->upsert_call([
                'uniqueid'    => $result['action_id'],
                'calldate'    => date('Y-m-d H:i:s'),
                'direction'   => 'outbound',
                'src'         => $agent['extension'],
                'dst'         => $number,
                'disposition' => 'PENDING',
                'agent_id'    => (int) $agent['id'],
                'lead_id'     => $lead_id,
                'contact_id'  => $contact_id,
                'match_type'  => $lead_id ? 'lead' : ($contact_id ? 'contact' : 'none'),
                'synced_at'   => date('Y-m-d H:i:s'),
            ]);

            // Write event log
            $this->salesos_model->log_event('call.originated', [
                'staff_id'    => $staff_id,
                'agent_id'    => (int) $agent['id'],
                'entity_type' => $lead_id ? 'lead' : ($contact_id ? 'contact' : null),
                'entity_id'   => $lead_id ?: $contact_id,
                'description' => 'Outbound call initiated to ' . $number,
                'number'      => $number,
                'extension'   => $agent['extension'],
                'action_id'   => $result['action_id'],
            ]);

            // Write lead timeline entry
            if ($lead_id && get_option('salesos_timeline_enabled') == '1') {
                $this->load->model('leads_model');
                if (method_exists($this->leads_model, 'log_lead_activity')) {
                    $ext = $agent['extension'];
                    $this->leads_model->log_lead_activity(
                        (int) $lead_id,
                        "[SalesOS] Outbound call initiated to {$number} from ext {$ext}",
                        'salesos',
                        json_encode([
                            'action_id' => $result['action_id'],
                            'extension' => $ext,
                            'number'    => $number,
                        ])
                    );
                }
            }
        }

        $this->_json($result);
    }

    // ── Active calls polling (for popup) ──────────────────────────────────────

    public function active_calls()
    {
        $staff_id     = get_staff_user_id();
        $active_calls = $this->salesos_model->get_active_calls($staff_id);
        $this->_json(['success' => true, 'calls' => $active_calls]);
    }

    // ── Phone lookup ──────────────────────────────────────────────────────────

    public function lookup()
    {
        $phone = $this->input->get('phone') ?: $this->input->post('phone');
        if (!$phone) {
            return $this->_json(['success' => false, 'error' => 'Phone required']);
        }

        $match = $this->salesos_model->match_phone($phone);
        $this->_json(['success' => true, 'match' => $match]);
    }

    // ── Call history for phone ────────────────────────────────────────────────

    public function history()
    {
        $phone = $this->input->get('phone');
        if (!$phone) {
            return $this->_json(['success' => false, 'error' => 'Phone required']);
        }
        $calls = $this->salesos_model->get_calls_for_phone($phone, 20);
        $this->_json(['success' => true, 'calls' => $calls]);
    }

    // ── AMI connection test ───────────────────────────────────────────────────

    public function test_ami()
    {
        if (!staff_can('settings', 'salesos')) {
            return $this->_json(['success' => false, 'error' => 'Access denied']);
        }

        $this->load->library('salesos/ami_service');
        $ok = $this->ami_service->connect();

        if ($ok) {
            $this->ami_service->disconnect();
            $this->_json(['success' => true, 'message' => 'AMI connected successfully']);
        } else {
            $this->_json(['success' => false, 'error' => $this->ami_service->get_last_error()]);
        }
    }

    // ── CDR sync trigger ──────────────────────────────────────────────────────

    public function sync_cdr()
    {
        if (!staff_can('settings', 'salesos')) {
            return $this->_json(['success' => false, 'error' => 'Access denied']);
        }

        try {
            $this->load->library('salesos/cdr_sync_service');
            $result = $this->cdr_sync_service->sync();
            return $this->_json($result);
        } catch (Throwable $e) {
            log_message('error', '[SalesOS] sync_cdr failed: ' . $e->getMessage());
            return $this->_json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // ── Agent login / logout ──────────────────────────────────────────────────

    public function agent_login()
    {
        $staff_id = get_staff_user_id();
        $this->agents_model->set_login_state($staff_id, true);
        $this->_json(['success' => true]);
    }

    public function agent_logout()
    {
        $staff_id = get_staff_user_id();
        $this->agents_model->set_login_state($staff_id, false);
        $this->_json(['success' => true]);
    }

    public function agent_status()
    {
        $staff_id = get_staff_user_id();
        $agent    = $this->agents_model->get_by_staff_id($staff_id);
        $this->_json([
            'success'      => true,
            'has_extension' => (bool) $agent,
            'extension'    => $agent['extension'] ?? null,
            'is_logged_in' => (bool) ($agent['is_logged_in'] ?? false),
        ]);
    }

    // ── Popup mark seen ───────────────────────────────────────────────────────

    public function popup_seen($id)
    {
        $this->salesos_model->mark_popup_shown((int) $id);
        $this->_json(['success' => true]);
    }

    // ── Hangup ───────────────────────────────────────────────────────────────

    public function hangup()
    {
        if (!staff_can('make', 'salesos')) {
            return $this->_json(['success' => false, 'error' => 'Access denied']);
        }

        $channel = $this->input->post('channel');
        if (!$channel) {
            return $this->_json(['success' => false, 'error' => 'Channel required']);
        }

        $this->load->library('salesos/ami_service');
        $ok = $this->ami_service->hangup($channel);
        $this->_json(['success' => $ok]);
    }

    // ── WebRTC softphone configuration ───────────────────────────────────────

    /**
     * Returns SIP credentials + transport config for the logged-in agent's WebRTC softphone.
     * Password is decrypted in-flight and transmitted over HTTPS only.
     * Only returns data for the authenticated agent's own extension.
     */
    public function webrtc_config(): void
    {
        if (!staff_can('make', 'salesos')) {
            $this->_json(['success' => false, 'error' => 'Access denied'], 403);
            return;
        }

        $staff_id = get_staff_user_id();
        $agent    = $this->agents_model->get_by_staff_id($staff_id);

        if (!$agent || empty($agent['extension'])) {
            $this->_json(['success' => false, 'error' => 'No extension assigned to your account']);
            return;
        }

        $sip_password = salesos_decrypt_sip_password($agent['sip_password'] ?? '');

        $this->load->model('staff_model');
        $staff        = $this->staff_model->get($staff_id);
        $display_name = $staff
            ? trim(($staff->firstname ?? '') . ' ' . ($staff->lastname ?? ''))
            : $agent['extension'];

        $domain    = get_option('salesos_webrtc_domain')  ?: 'pbx.bizyto.com';
        $wss_url   = get_option('salesos_webrtc_wss_url') ?: 'wss://' . $domain . '/asterisk/ws';
        $realm     = get_option('salesos_webrtc_realm')   ?: 'asterisk';
        $expires   = (int) (get_option('salesos_webrtc_expires') ?: 300);

        $stun_raw    = get_option('salesos_stun_servers') ?: 'stun:stun.l.google.com:19302';
        $ice_servers = array_values(array_map(
            fn($s) => ['urls' => trim($s)],
            array_filter(array_map('trim', explode(',', $stun_raw)))
        ));

        $this->_json([
            'success'      => true,
            'extension'    => $agent['extension'],
            'display_name' => $display_name,
            'sip_uri'      => 'sip:' . $agent['extension'] . '@' . $domain,
            'sip_password' => $sip_password,
            'ws_server'    => $wss_url,
            'realm'        => $realm,
            'transport'    => 'WSS',
            'ice_servers'  => $ice_servers,
            'expires'      => $expires,
        ]);
    }

    /**
     * Pre-log a WebRTC outbound call: fire event log + lead timeline.
     * CDR sync will create the authoritative salesos_calls record later.
     */
    public function webrtc_call_start(): void
    {
        if (!staff_can('make', 'salesos')) {
            $this->_json(['success' => false, 'error' => 'Access denied'], 403);
            return;
        }

        $number     = $this->input->post('number');
        $lead_id    = (int) ($this->input->post('lead_id') ?: 0) ?: null;
        $contact_id = (int) ($this->input->post('contact_id') ?: 0) ?: null;

        if (!$number) {
            $this->_json(['success' => false, 'error' => 'Number required']);
            return;
        }

        $staff_id = get_staff_user_id();
        $agent    = $this->agents_model->get_by_staff_id($staff_id);

        if (!$agent) {
            $this->_json(['success' => false, 'error' => 'No extension mapped']);
            return;
        }

        $this->salesos_model->log_event('call.originated', [
            'staff_id'    => $staff_id,
            'agent_id'    => (int) $agent['id'],
            'entity_type' => $lead_id ? 'lead' : ($contact_id ? 'contact' : null),
            'entity_id'   => $lead_id ?: $contact_id,
            'description' => 'WebRTC outbound call initiated to ' . $number,
            'number'      => $number,
            'extension'   => $agent['extension'],
            'transport'   => 'webrtc',
        ]);

        if ($lead_id && get_option('salesos_timeline_enabled') == '1') {
            $this->load->model('leads_model');
            if (method_exists($this->leads_model, 'log_lead_activity')) {
                $this->leads_model->log_lead_activity(
                    (int) $lead_id,
                    '[SalesOS] WebRTC outbound call to ' . $number . ' from ext ' . $agent['extension'],
                    'salesos',
                    json_encode(['extension' => $agent['extension'], 'number' => $number, 'transport' => 'webrtc'])
                );
            }
        }

        $this->_json(['success' => true]);
    }

    // ── Universal search (leads, contacts, clients, invoices, tickets, phone) ─

    public function search(): void
    {
        $q = trim((string) ($this->input->get('q') ?: $this->input->post('q')));
        if (strlen($q) < 2) {
            $this->_json(['success' => true, 'results' => []]);
            return;
        }

        $results = [];
        $like    = '%' . $this->db->escape_like_str($q) . '%';

        // Phone lookup first (Redis-cached)
        $this->load->library('salesos/Phone_cache');
        $phone_entity = $this->phone_cache->lookup($q);
        if ($phone_entity) {
            $results[] = [
                'type'  => $phone_entity['entity_type'],
                'id'    => $phone_entity['entity_id'],
                'name'  => $phone_entity['entity_name'],
                'phone' => $q,
                'meta'  => 'Phone match',
            ];
        }

        // Leads
        $leads = $this->db->query(
            "SELECT id, `name`, phonenumber, status FROM " . db_prefix() . "leads
             WHERE `name` LIKE ? OR phonenumber LIKE ? LIMIT 3",
            [$like, $like]
        )->result_array();
        foreach ($leads as $r) {
            $results[] = ['type'=>'lead','id'=>(int)$r['id'],'name'=>$r['name'],
                          'phone'=>$r['phonenumber'],'meta'=>$r['status'] ?? ''];
        }

        // Clients
        $clients = $this->db->query(
            "SELECT userid, company, phonenumber FROM " . db_prefix() . "clients
             WHERE company LIKE ? OR phonenumber LIKE ? LIMIT 3",
            [$like, $like]
        )->result_array();
        foreach ($clients as $r) {
            $results[] = ['type'=>'client','id'=>(int)$r['userid'],'name'=>$r['company'],
                          'phone'=>$r['phonenumber'],'meta'=>'Client'];
        }

        // Contacts
        $contacts = $this->db->query(
            "SELECT id, firstname, lastname, phonenumber FROM " . db_prefix() . "contacts
             WHERE CONCAT(firstname,' ',lastname) LIKE ? OR phonenumber LIKE ? LIMIT 2",
            [$like, $like]
        )->result_array();
        foreach ($contacts as $r) {
            $results[] = ['type'=>'contact','id'=>(int)$r['id'],
                          'name'=>trim($r['firstname'].' '.$r['lastname']),
                          'phone'=>$r['phonenumber'],'meta'=>'Contact'];
        }

        // Invoices (number exact prefix match)
        $invoices = $this->db->query(
            "SELECT id, `number`, total, currency FROM " . db_prefix() . "invoices
             WHERE `number` LIKE ? LIMIT 2",
            [$q . '%']
        )->result_array();
        foreach ($invoices as $r) {
            $results[] = ['type'=>'invoice','id'=>(int)$r['id'],'name'=>$r['number'],
                          'phone'=>null,'meta'=>$r['currency'].' '.$r['total']];
        }

        $this->_json(['success' => true, 'results' => array_values(array_unique($results, SORT_REGULAR))]);
    }

    // ── Incoming call context (CRM enrichment for popup + active workspace) ──

    public function incoming_context(): void
    {
        $caller    = trim((string) ($this->input->get('caller') ?: ''));
        $entity_t  = trim((string) ($this->input->get('entity_type') ?: ''));
        $entity_id = (int) ($this->input->get('entity_id') ?: 0);

        if (!$caller && !$entity_id) {
            $this->_json(['success' => false, 'error' => 'caller or entity_id required']);
            return;
        }

        // Redis context cache (30s TTL per entity)
        $cache_key = null;
        $redis     = null;
        if (extension_loaded('redis')) {
            try {
                $redis = new Redis();
                $redis->connect(get_option('salesos_redis_host') ?: '127.0.0.1',
                                (int)(get_option('salesos_redis_port') ?: 6379), 1.0);
                if ($entity_id && $entity_t) {
                    $cache_key = 'salesos:context:' . $entity_t . ':' . $entity_id;
                    $cached    = $redis->get($cache_key);
                    if ($cached) {
                        $this->_json(json_decode($cached, true));
                        return;
                    }
                }
            } catch (Exception $e) { $redis = null; }
        }

        // Resolve entity from phone if not given directly
        if (!$entity_id && $caller) {
            $this->load->library('salesos/Phone_cache');
            $match = $this->phone_cache->lookup($caller);
            if ($match) {
                $entity_t  = $match['entity_type'];
                $entity_id = (int) $match['entity_id'];
            }
        }

        $entity  = null;
        $history = ['total_calls'=>0,'last_call_date'=>null,'last_call_disposition'=>null,'last_call_duration'=>null,'last_note'=>null];
        $tasks   = ['open'=>0,'overdue'=>0,'next_due'=>null];
        $crm     = ['lifetime_value'=>null,'last_invoice_id'=>null,'last_invoice_amount'=>null,'last_ticket_id'=>null,'last_ticket_subject'=>null];

        if ($entity_id) {
            if ($entity_t === 'lead') {
                $r = $this->db->get_where(db_prefix().'leads', ['id'=>$entity_id])->row_array();
                if ($r) $entity = ['type'=>'lead','id'=>$entity_id,'name'=>$r['name'],
                    'company'=>$r['company']??'','lead_status'=>$r['status']??'',
                    'url'=>admin_url('leads/index/'.$entity_id)];
            } elseif ($entity_t === 'client') {
                $r = $this->db->get_where(db_prefix().'clients',['userid'=>$entity_id])->row_array();
                if ($r) $entity = ['type'=>'client','id'=>$entity_id,'name'=>$r['company'],
                    'url'=>admin_url('clients/client/'.$entity_id)];
            } elseif ($entity_t === 'contact') {
                $r = $this->db->get_where(db_prefix().'contacts',['id'=>$entity_id])->row_array();
                if ($r) $entity = ['type'=>'contact','id'=>$entity_id,
                    'name'=>trim(($r['firstname']??'').' '.($r['lastname']??'')),
                    'url'=>admin_url('contacts/contact/'.$entity_id)];
            }

            // Call history
            $pfx    = db_prefix();
            $h      = $this->db->query(
                "SELECT COUNT(*) AS cnt, MAX(calldate) AS last_date, MAX(disposition) AS last_disp,
                        MAX(billsec) AS last_sec, MAX(notes) AS last_note
                 FROM {$pfx}salesos_calls
                 WHERE ({$entity_t}_id = ? OR match_type = ?)
                   AND {$entity_t}_id = ?",
                [$entity_id, $entity_t, $entity_id]
            )->row_array();
            if ($h) $history = ['total_calls'=>(int)$h['cnt'],'last_call_date'=>$h['last_date'],
                'last_call_disposition'=>$h['last_disp'],'last_call_duration'=>$h['last_sec'],
                'last_note'=>$h['last_note']];

            // Tasks (open/overdue)
            $now = date('Y-m-d H:i:s');
            $ta  = $this->db->query(
                "SELECT COUNT(*) AS open_cnt,
                        SUM(CASE WHEN duedate < ? AND status != 'complete' THEN 1 ELSE 0 END) AS overdue_cnt,
                        MIN(CASE WHEN duedate > ? THEN duedate END) AS next_due
                 FROM {$pfx}tasks WHERE rel_type=? AND rel_id=? AND status != 'complete'",
                [$now, $now, $entity_t, $entity_id]
            )->row_array();
            if ($ta) $tasks = ['open'=>(int)$ta['open_cnt'],'overdue'=>(int)$ta['overdue_cnt'],'next_due'=>$ta['next_due']];

            // CLV + last invoice (client only)
            if ($entity_t === 'client') {
                $inv = $this->db->query(
                    "SELECT SUM(total) AS clv, MAX(id) AS last_id, MAX(total) AS last_amount
                     FROM {$pfx}invoices WHERE clientid=?", [$entity_id]
                )->row_array();
                if ($inv) {
                    $crm['lifetime_value']       = $inv['clv'];
                    $crm['last_invoice_id']      = $inv['last_id'];
                    $crm['last_invoice_amount']  = $inv['last_amount'];
                }
                $tkt = $this->db->query(
                    "SELECT id, subject FROM {$pfx}tickets WHERE userid=? ORDER BY id DESC LIMIT 1", [$entity_id]
                )->row_array();
                if ($tkt) { $crm['last_ticket_id'] = $tkt['id']; $crm['last_ticket_subject'] = $tkt['subject']; }
            }
        }

        $payload = ['success'=>true,'caller'=>$caller,'entity'=>$entity,
                    'history'=>$history,'tasks'=>$tasks,'crm_extras'=>$crm];

        // Cache in Redis for 45 seconds
        if ($redis && $entity_id && $entity_t) {
            if (!$cache_key) $cache_key = 'salesos:context:' . $entity_t . ':' . $entity_id;
            try { $redis->setEx($cache_key, 45, json_encode($payload)); } catch (Exception $e) {}
        }

        $this->_json($payload);
    }

    // ── WebRTC presence update (browser registration state → Redis stream) ──

    public function webrtc_presence(): void
    {
        if (!staff_can('make', 'salesos')) {
            $this->_json(['success' => false, 'error' => 'Access denied'], 403);
            return;
        }

        $state    = $this->input->post('state');   // 'registered' or 'unregistered'
        $staff_id = get_staff_user_id();
        $agent    = $this->agents_model->get_by_staff_id($staff_id);

        if (!$agent) {
            $this->_json(['success' => false, 'error' => 'No extension']);
            return;
        }

        $ext      = $agent['extension'];
        $event    = [
            'version'    => '1',
            'timestamp'  => date('c'),
            'source'     => 'browser',
            'event_type' => 'webrtc.' . ($state === 'registered' ? 'registered' : 'unregistered'),
            'staff_id'   => (string) $staff_id,
            'agent_ext'  => $ext,
            'pbx_id'     => get_option('salesos_pbx_id') ?: 'pbx-01',
        ];

        if (extension_loaded('redis')) {
            try {
                $r = new Redis();
                $r->connect(get_option('salesos_redis_host') ?: '127.0.0.1',
                            (int)(get_option('salesos_redis_port') ?: 6379), 1.0);
                $maxlen = (int)(get_option('salesos_stream_maxlen') ?: 50000);
                $r->xAdd('salesos:stream:webrtc', '*', $event, $maxlen, true);

                // Update agent state in salesos:agents hash
                $summary = json_decode($r->hGet('salesos:agents', $ext) ?: '{}', true) ?: [];
                $summary['webrtc_state'] = $state;
                $summary['updated_at']   = time();
                $r->hSet('salesos:agents', $ext, json_encode($summary));
            } catch (Exception $e) {}
        }

        $this->_json(['success' => true]);
    }

    // ── Call context for drawer (call detail without full page load) ──────────

    public function call_context($id = 0): void
    {
        $id   = (int) $id;
        $call = $this->salesos_model->get_call($id);
        if (!$call) {
            $this->_json(['success' => false, 'error' => 'Not found'], 404);
            return;
        }

        // Call events (timeline)
        $events = $this->db->query(
            "SELECT event_type, from_state, to_state, queue_name, payload, created_at
             FROM " . db_prefix() . "salesos_call_events
             WHERE call_uniqueid = ? ORDER BY created_at ASC LIMIT 100",
            [$call['uniqueid']]
        )->result_array();

        // Recording URL
        $recording_url = '';
        if (!empty($call['recordingfile'])) {
            $file = basename($call['recordingfile']);
            if ($file) $recording_url = admin_url('salesos/calls/recording/' . rawurlencode($file));
        }

        $this->_json([
            'success'       => true,
            'call'          => $call,
            'events'        => $events,
            'recording_url' => $recording_url,
        ]);
    }

    // ── PBX Auto-Discover ────────────────────────────────────────────────────

    public function discover_pbx(): void
    {
        if (!staff_can('settings', 'salesos')) {
            $this->_json(['success' => false, 'error' => 'Access denied'], 403);
            return;
        }

        $host = trim((string) $this->input->post('host'));
        if (!$host || !filter_var($host, FILTER_VALIDATE_IP) && !filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            $this->_json(['success' => false, 'error' => 'Invalid host']);
            return;
        }

        $probes = [
            'ami'      => ['port' => 5038,  'label' => 'Asterisk AMI'],
            'mysql'    => ['port' => 3306,  'label' => 'CDR Database (MySQL)'],
            'rec_8090' => ['port' => 8090,  'label' => 'Recordings Server (:8090)'],
            'rec_8080' => ['port' => 8080,  'label' => 'Recordings Server (:8080)'],
            'webrtc'   => ['port' => 8088,  'label' => 'Asterisk WebRTC/ARI (:8088)'],
            'ssh'      => ['port' => 22,    'label' => 'SSH'],
        ];

        $prev = ini_set('default_socket_timeout', 3);
        $results = [];
        foreach ($probes as $key => $probe) {
            $fp = @fsockopen($host, $probe['port'], $errno, $errstr, 3);
            $results[$key] = [
                'label'     => $probe['label'],
                'port'      => $probe['port'],
                'reachable' => ($fp !== false),
            ];
            if ($fp) fclose($fp);
        }
        ini_set('default_socket_timeout', $prev);

        // Derive suggested recording URL
        $rec_port = $results['rec_8090']['reachable'] ? 8090
                  : ($results['rec_8080']['reachable'] ? 8080 : null);

        $this->_json([
            'success'   => true,
            'host'      => $host,
            'results'   => $results,
            'suggested' => [
                'salesos_ami_host'       => $host,
                'salesos_ami_port'       => 5038,
                'salesos_pbx_db_host'    => $host,
                'salesos_pbx_db_port'    => 3306,
                'salesos_pbx_db_name'    => 'asteriskcdrdb',
                'salesos_webrtc_domain'  => $host,
                'salesos_webrtc_wss_url' => 'wss://' . $host . '/asterisk/ws',
                'salesos_recordings_url' => $rec_port
                    ? 'http://' . $host . ':' . $rec_port . '/recordings/'
                    : '',
            ],
        ]);
    }

    // ── System health snapshot ────────────────────────────────────────────────

    public function health()
    {
        if (!staff_can('settings', 'salesos')) {
            return $this->_json(['success' => false, 'error' => 'Access denied'], 403);
        }

        $t0     = microtime(true);
        $now    = time();
        $checks = [];

        // ── Redis ─────────────────────────────────────────────────────────────
        $redis  = null;
        $r_ok   = false;
        if (extension_loaded('redis')) {
            try {
                $redis = new Redis();
                $r_t0  = microtime(true);
                $redis->connect(
                    get_option('salesos_redis_host') ?: '127.0.0.1',
                    (int) (get_option('salesos_redis_port') ?: 6379),
                    2.0
                );
                $pw = get_option('salesos_redis_password') ?: '';
                if ($pw !== '') $redis->auth($pw);
                $redis->ping();
                $r_ok = true;
                $checks['redis'] = [
                    'status'     => 'ok',
                    'latency_ms' => round((microtime(true) - $r_t0) * 1000, 2),
                ];
            } catch (Exception $e) {
                $checks['redis'] = ['status' => 'error', 'error' => $e->getMessage()];
            }
        } else {
            $checks['redis'] = ['status' => 'error', 'error' => 'phpredis extension not loaded'];
        }

        // ── Daemon liveness (via Redis lock keys) ─────────────────────────────
        $daemons = [
            'ami_consumer'   => ['key' => 'salesos:lock:ami_consumer',   'stale_after' => 35],
            'event_archiver' => ['key' => 'salesos:lock:event_archiver', 'stale_after' => 70],
            'ws_server'      => ['key' => 'salesos:lock:ws_server',      'stale_after' => 35],
        ];

        foreach ($daemons as $name => $d) {
            if (!$r_ok) {
                $checks[$name] = ['status' => 'unknown', 'reason' => 'redis_unavailable'];
                continue;
            }
            try {
                $val = $redis->get($d['key']);
                $ttl = $redis->ttl($d['key']);
                if ($val === false || $val === null) {
                    $checks[$name] = ['status' => 'down', 'reason' => 'lock_absent'];
                } elseif ($ttl > 0 && ($d['stale_after'] - $ttl) > $d['stale_after']) {
                    $checks[$name] = ['status' => 'stale', 'pid_host' => $val, 'ttl_remaining' => $ttl];
                } else {
                    $checks[$name] = ['status' => 'ok', 'pid_host' => $val, 'ttl_remaining' => $ttl];
                }
            } catch (Exception $e) {
                $checks[$name] = ['status' => 'error', 'error' => $e->getMessage()];
            }
        }

        // ── WebSocket port reachability ────────────────────────────────────────
        $ws_port = (int) (get_option('salesos_ws_internal_port') ?: 8080);
        $ws_sock = @fsockopen('127.0.0.1', $ws_port, $errno, $errstr, 1);
        if ($ws_sock) {
            fclose($ws_sock);
            $checks['ws_port'] = ['status' => 'ok', 'port' => $ws_port];
        } else {
            $checks['ws_port'] = ['status' => 'down', 'port' => $ws_port, 'error' => $errstr];
        }

        // ── Active calls ──────────────────────────────────────────────────────
        $active_calls = 0;
        if ($r_ok) {
            try {
                $active_calls = (int) $redis->hLen('salesos:calls');
            } catch (Exception $e) {}
        }
        $checks['active_calls'] = $active_calls;

        // ── Stream lag per consumer group ─────────────────────────────────────
        $stream_lag = [];
        if ($r_ok) {
            $monitor_streams = ['calls', 'agents', 'recordings'];
            foreach ($monitor_streams as $s) {
                try {
                    $groups = $redis->xInfo('GROUPS', 'salesos:stream:' . $s);
                    foreach ($groups as $g) {
                        $gname = $g['name'] ?? '';
                        if (in_array($gname, ['archiver', 'ws-delivery'], true)) {
                            $stream_lag[$s][$gname] = (int) ($g['lag'] ?? 0);
                        }
                    }
                } catch (Exception $e) {}
            }
        }
        $checks['stream_lag'] = $stream_lag;

        // ── Last stream event ─────────────────────────────────────────────────
        $last_event = null;
        if ($r_ok) {
            try {
                $entries = $redis->xRevRange('salesos:stream:calls', '+', '-', 1);
                if (!empty($entries)) {
                    $fields = reset($entries);
                    $sid    = key($entries);
                    $ts_ms  = (int) explode('-', $sid)[0];
                    $ts     = (int) ($ts_ms / 1000);
                    $last_event = [
                        'stream_id'   => $sid,
                        'event_type'  => $fields['event_type'] ?? '',
                        'timestamp'   => $ts,
                        'seconds_ago' => max(0, $now - $ts),
                    ];
                }
            } catch (Exception $e) {}
        }
        $checks['last_event'] = $last_event;

        // ── DB tables sanity ──────────────────────────────────────────────────
        $db_ok = false;
        try {
            $cnt = $this->db->count_all(db_prefix() . 'salesos_agents');
            $checks['db'] = ['status' => 'ok', 'agents' => $cnt];
            $db_ok = true;
        } catch (Exception $e) {
            $checks['db'] = ['status' => 'error', 'error' => $e->getMessage()];
        }

        // ── Overall status ────────────────────────────────────────────────────
        $critical_down = !$r_ok
            || $checks['ami_consumer']['status']   === 'down'
            || $checks['event_archiver']['status'] === 'down';

        $any_degraded = $checks['ws_server']['status'] !== 'ok'
            || $checks['ws_port']['status']        !== 'ok'
            || !$db_ok;

        if ($critical_down) {
            $overall = 'down';
        } elseif ($any_degraded) {
            $overall = 'degraded';
        } else {
            $overall = 'ok';
        }

        $this->_json([
            'status'        => $overall,
            'timestamp'     => $now,
            'response_ms'   => round((microtime(true) - $t0) * 1000, 2),
            'checks'        => $checks,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function _json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
