<?php

/**
 * Bizbot Standalone Webhook Receiver (Pure PDO — No CI dependency)
 * 
 * Handles ALL Anychat webhook events with direct MySQL access.
 * Bypasses CodeIgniter entirely — CSRF, routing, session, bootstrap.
 */

$log_file = __DIR__ . '/webhook_debug.log';
$activity_log = __DIR__ . '/bizbot_activity.log';

function wh_log($msg)
{
    global $activity_log;
    @file_put_contents($activity_log, "[" . date('Y-m-d H:i:s') . "] WH: $msg\n", FILE_APPEND);
}

// --- Read request ---
$raw_payload = file_get_contents('php://input');
$method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
$ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

@file_put_contents($log_file, "--- " . date('Y-m-d H:i:s') . " | $method | $ip ---\n$raw_payload\n\n", FILE_APPEND);

// GET = status check
if ($method === 'GET') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<h2>✅ Bizbot Webhook Endpoint is Active</h2>';
    echo '<p>Method: POST only | Server Time: ' . date('Y-m-d H:i:s') . '</p>';

    // Show recent log entries
    if (file_exists($activity_log)) {
        $lines = array_slice(file($activity_log), -20);
        echo '<h3>Recent Activity (last 20)</h3><pre>' . htmlspecialchars(implode('', $lines)) . '</pre>';
    }
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

$payload = json_decode($raw_payload, true);
if (!$payload || !isset($payload['event'])) {
    http_response_code(400);
    wh_log("ERROR: Invalid JSON or missing event");
    exit(json_encode(['error' => 'Invalid payload']));
}

$event = $payload['event'];
$data = $payload['data'] ?? [];
wh_log("EVENT: $event (IP: $ip)");

// --- Connect to Database ---
$db = get_db_connection();
if (!$db) {
    wh_log("FATAL: Cannot connect to DB");
    http_response_code(200); // Still return 200 so Bizbot doesn't retry
    exit(json_encode(['status' => 'logged_only', 'error' => 'db_failed']));
}

// Detect table prefix
$prefix = detect_prefix($db);
wh_log("DB OK, prefix=$prefix");

// --- Process event ---
try {
    process_event($db, $prefix, $event, $data);
} catch (\Throwable $e) {
    wh_log("PROCESS ERROR: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
}

http_response_code(200);
echo json_encode(['status' => 'processed', 'event' => $event]);
exit;


// ========================================================================
// DATABASE CONNECTION
// ========================================================================

function get_db_connection()
{
    $crm_root = realpath(__DIR__ . '/../../');
    $config_file = $crm_root . '/application/config/app-config.php';

    if (!file_exists($config_file)) {
        wh_log("Config file not found: $config_file");
        return null;
    }

    // Read config file and extract defines
    $config_content = file_get_contents($config_file);

    $db_host = extract_define($config_content, 'APP_DB_HOSTNAME') ?: 'localhost';
    $db_user = extract_define($config_content, 'APP_DB_USERNAME') ?: 'root';
    $db_pass = extract_define($config_content, 'APP_DB_PASSWORD') ?: '';
    $db_name = extract_define($config_content, 'APP_DB_NAME') ?: '';
    $db_charset = extract_define($config_content, 'APP_DB_CHARSET') ?: 'utf8mb4';

    if (empty($db_name)) {
        wh_log("DB name not found in config");
        return null;
    }

    try {
        $db = new PDO(
            "mysql:host=$db_host;dbname=$db_name;charset=$db_charset",
            $db_user,
            $db_pass
        );
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
        return $db;
    } catch (PDOException $e) {
        wh_log("DB CONNECT ERROR: " . $e->getMessage());
        return null;
    }
}

function extract_define($content, $name)
{
    // Match: define('NAME', 'value'); or define('NAME', "value");
    if (preg_match("/define\s*\(\s*['\"]" . preg_quote($name) . "['\"]\s*,\s*['\"](.*)['\"]\s*\)/", $content, $m)) {
        return $m[1];
    }
    return null;
}

function detect_prefix($db)
{
    try {
        $stmt = $db->query("SHOW TABLES LIKE 'tbl%'");
        if ($stmt->rowCount() > 0) return 'tbl';
    } catch (\Exception $e) {
    }
    return 'tbl'; // Perfex default
}


// ========================================================================
// EVENT ROUTER
// ========================================================================

function process_event($db, $prefix, $event, $data)
{
    switch ($event) {
        case 'chat.message.created':
            handle_message_created($db, $prefix, $data);
            break;
        case 'chat.resolved':
            handle_chat_status($db, $prefix, $data, 'resolved');
            break;
        case 'chat.archived':
            handle_chat_status($db, $prefix, $data, 'archived');
            break;
        case 'chat.reopened':
            handle_chat_status($db, $prefix, $data, 'reopened');
            break;
        case 'contact.created':
            handle_contact_created($db, $prefix, $data);
            break;
        case 'contact.updated':
            handle_contact_updated($db, $prefix, $data);
            break;
        default:
            wh_log("UNHANDLED: $event");
            break;
    }
}


// ========================================================================
// EVENT HANDLERS
// ========================================================================

/**
 * chat.message.created → Find/create lead, save chat history, auto-link thread
 */
function handle_message_created($db, $prefix, $data)
{
    $message = $data['message'] ?? [];
    $contact = $data['contact'] ?? [];
    $chat = $data['chat'] ?? [];

    $thread_guid = $data['thread'] ?? $chat['guid'] ?? $message['thread_guid'] ?? '';
    $msg_text = $message['message'] ?? '';
    $msg_id = $message['id'] ?? '';
    $is_agent = !empty($message['from_agent']);
    $is_bot = !empty($message['is_bot']);

    $phone = $contact['clean_phone'] ?? '';
    $name = $contact['name'] ?? 'Unknown';

    if (empty($phone) && !empty($contact['phone'])) {
        $phone = preg_replace('/[^0-9]/', '', $contact['phone']);
    }

    wh_log("MSG: $name ($phone) thread=$thread_guid msg=" . mb_substr($msg_text, 0, 50));

    // 1. Find lead by thread_guid
    $lead_id = find_lead_by_thread($db, $prefix, $thread_guid);

    // 2. Find lead by phone if not found by thread
    if (!$lead_id && !empty($phone)) {
        $lead_id = find_lead_by_phone($db, $prefix, $phone);

        // Auto-link thread to this lead
        if ($lead_id && !empty($thread_guid)) {
            save_thread_guid($db, $prefix, $lead_id, $thread_guid);
            wh_log("AUTO-LINKED: thread $thread_guid → lead #$lead_id");
        }
    }

    // 3. Keyword trigger → create new lead
    if (!$lead_id && preg_match('/#lead|#crm|#লিড/iu', $msg_text) && !empty($phone)) {
        $lead_id = create_lead($db, $prefix, $name, $phone, $thread_guid, 'Keyword');
        wh_log("LEAD CREATED #$lead_id via keyword");
    }

    // 4. Save chat history
    if ($lead_id && !empty($msg_text)) {
        $sender = 'Customer';
        if ($is_agent) $sender = 'Agent';
        if ($is_bot) $sender = 'Bot';

        $stmt = $db->prepare("INSERT INTO {$prefix}bizbot_chat_history 
            (lead_id, message_id, sender, message, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$lead_id, $msg_id, $sender, $msg_text]);
        wh_log("HISTORY: lead #$lead_id ($sender)");
    }
}

/**
 * chat.resolved / chat.archived / chat.reopened → Add note to lead
 */
function handle_chat_status($db, $prefix, $data, $action)
{
    $chat = $data['chat'] ?? $data;
    $thread_guid = $chat['guid'] ?? $data['guid'] ?? '';
    $contact = $data['contact'] ?? [];

    if (empty($thread_guid)) return;

    $lead_id = find_lead_by_thread($db, $prefix, $thread_guid);

    if (!$lead_id && !empty($contact)) {
        $phone = $contact['clean_phone'] ?? preg_replace('/[^0-9]/', '', $contact['phone'] ?? '');
        if (!empty($phone)) {
            $lead_id = find_lead_by_phone($db, $prefix, $phone);
        }
    }

    if (!$lead_id) {
        wh_log("CHAT $action: no lead for thread $thread_guid");
        return;
    }

    $stmt = $db->prepare("INSERT INTO {$prefix}notes (rel_id, rel_type, description, date_contacted, addedfrom, dateadded) 
        VALUES (?, 'lead', ?, NOW(), 0, NOW())");
    $stmt->execute([$lead_id, "Bizbot: Chat $action"]);
    wh_log("CHAT $action: noted on lead #$lead_id");
}

/**
 * contact.created → Auto-create lead
 */
function handle_contact_created($db, $prefix, $data)
{
    $contact = $data['contact'] ?? $data;
    $phone = $contact['clean_phone'] ?? '';
    $name = $contact['name'] ?? 'Bizbot Contact';

    if (empty($phone) && !empty($contact['phone'])) {
        $phone = preg_replace('/[^0-9]/', '', $contact['phone']);
    }
    if (empty($phone)) {
        wh_log("CONTACT NEW: no phone");
        return;
    }

    $existing = find_lead_by_phone($db, $prefix, $phone);
    if ($existing) {
        wh_log("CONTACT NEW: lead exists #$existing");
        return;
    }

    $lead_id = create_lead($db, $prefix, $name, $phone, '', 'Contact Created');
    wh_log("CONTACT NEW: lead #$lead_id ($name, $phone)");
}

/**
 * contact.updated → Update lead info
 */
function handle_contact_updated($db, $prefix, $data)
{
    $contact = $data['contact'] ?? $data;
    $phone = $contact['clean_phone'] ?? '';
    if (empty($phone) && !empty($contact['phone'])) {
        $phone = preg_replace('/[^0-9]/', '', $contact['phone']);
    }
    if (empty($phone)) return;

    $lead_id = find_lead_by_phone($db, $prefix, $phone);
    if (!$lead_id) return;

    $updates = [];
    $params = [];
    if (!empty($contact['name'])) {
        $updates[] = 'name = ?';
        $params[] = $contact['name'];
    }
    if (!empty($contact['email'])) {
        $updates[] = 'email = ?';
        $params[] = $contact['email'];
    }
    if (!empty($contact['city'])) {
        $updates[] = 'city = ?';
        $params[] = $contact['city'];
    }
    if (!empty($contact['company'])) {
        $updates[] = 'company = ?';
        $params[] = $contact['company'];
    }

    if (!empty($updates)) {
        $params[] = $lead_id;
        $db->prepare("UPDATE {$prefix}leads SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);
        wh_log("CONTACT UPD: lead #$lead_id");
    }
}


// ========================================================================
// HELPER FUNCTIONS
// ========================================================================

function find_lead_by_thread($db, $prefix, $thread_guid)
{
    if (empty($thread_guid)) return null;

    $stmt = $db->prepare("
        SELECT cv.relid FROM {$prefix}customfieldsvalues cv
        JOIN {$prefix}customfields cf ON cf.id = cv.fieldid
        WHERE cf.slug = 'leads_bizbot_thread_guid' AND cv.value = ? LIMIT 1
    ");
    $stmt->execute([$thread_guid]);
    $row = $stmt->fetch();
    return $row ? (int)$row->relid : null;
}

function find_lead_by_phone($db, $prefix, $phone)
{
    if (empty($phone)) return null;

    // Build phone variants for BD numbers
    $clean = preg_replace('/[^0-9]/', '', $phone);
    $variants = [$clean];

    if (strpos($clean, '880') === 0) {
        $local = substr($clean, 3);
        $variants[] = '0' . $local;
        $variants[] = $local;
        $variants[] = '+880' . $local;
    } elseif (strpos($clean, '0') === 0 && strlen($clean) >= 10) {
        $without0 = substr($clean, 1);
        $variants[] = '880' . $without0;
        $variants[] = '+880' . $without0;
        $variants[] = $without0;
    }

    $placeholders = implode(',', array_fill(0, count($variants), '?'));
    $stmt = $db->prepare("SELECT id FROM {$prefix}leads WHERE phonenumber IN ($placeholders) LIMIT 1");
    $stmt->execute($variants);
    $row = $stmt->fetch();
    return $row ? (int)$row->id : null;
}

function save_thread_guid($db, $prefix, $lead_id, $thread_guid)
{
    if (empty($thread_guid)) return;

    // Get custom field ID
    $stmt = $db->prepare("SELECT id FROM {$prefix}customfields WHERE slug = 'leads_bizbot_thread_guid' LIMIT 1");
    $stmt->execute();
    $field = $stmt->fetch();
    if (!$field) {
        wh_log("WARN: custom field leads_bizbot_thread_guid not found");
        return;
    }

    // Check if value exists
    $stmt = $db->prepare("SELECT id FROM {$prefix}customfieldsvalues WHERE relid = ? AND fieldid = ? AND fieldto = 'leads' LIMIT 1");
    $stmt->execute([$lead_id, $field->id]);
    $exists = $stmt->fetch();

    if ($exists) {
        $db->prepare("UPDATE {$prefix}customfieldsvalues SET value = ? WHERE id = ?")->execute([$thread_guid, $exists->id]);
    } else {
        $db->prepare("INSERT INTO {$prefix}customfieldsvalues (relid, fieldid, fieldto, value) VALUES (?, ?, 'leads', ?)")
            ->execute([$lead_id, $field->id, $thread_guid]);
    }
    wh_log("THREAD SAVED: lead #$lead_id = $thread_guid");
}

function create_lead($db, $prefix, $name, $phone, $thread_guid, $source_label)
{
    // Get or create Bizbot source
    $stmt = $db->prepare("SELECT id FROM {$prefix}leads_sources WHERE name = 'Bizbot' LIMIT 1");
    $stmt->execute();
    $source = $stmt->fetch();

    if ($source) {
        $source_id = $source->id;
    } else {
        $db->prepare("INSERT INTO {$prefix}leads_sources (name) VALUES ('Bizbot')")->execute();
        $source_id = $db->lastInsertId();
    }

    $db->prepare("INSERT INTO {$prefix}leads (name, phonenumber, source, status, description, dateadded, lastcontact) 
        VALUES (?, ?, ?, 1, ?, NOW(), NOW())")
        ->execute([$name, $phone, $source_id, "Synced via Bizbot ($source_label)"]);
    $lead_id = $db->lastInsertId();

    if ($lead_id && !empty($thread_guid)) {
        save_thread_guid($db, $prefix, $lead_id, $thread_guid);
    }

    return $lead_id;
}
