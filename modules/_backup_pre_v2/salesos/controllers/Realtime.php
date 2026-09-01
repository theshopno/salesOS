<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Realtime — AJAX endpoints consumed by the browser JS layer.
 *
 * GET  /salesos/realtime/token         → issue a WS auth token
 * GET  /salesos/realtime/state         → current call/agent/queue snapshot (REST fallback)
 * GET  /salesos/realtime/call/{uid}    → single-call detail
 * POST /salesos/realtime/notify_test   → admin-only: push a test toast
 */
class Realtime extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('salesos/Redis_service');
        $this->load->library('salesos/Ws_token_service');
        $this->load->model('salesos/Agents_model');
    }

    /** Issue a single-use WS auth token for the authenticated staff member. */
    public function token(): void
    {
        if (!is_staff_logged_in()) {
            $this->_json(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $staff_id = get_staff_user_id();
        $role     = is_admin() ? 'admin' : 'agent';

        $agent = $this->Agents_model->get_by_staff_id($staff_id);
        $ext   = $agent ? ($agent['extension'] ?? '') : '';

        $token  = $this->ws_token_service->issue($staff_id, $role, $ext);
        $ws_url = rtrim(get_option('salesos_ws_url'), '/');

        $this->_json(['success' => true, 'token' => $token, 'ws_url' => $ws_url]);
    }

    /** Current state snapshot — REST fallback when WS is unavailable. */
    public function state(): void
    {
        if (!is_staff_logged_in()) {
            $this->_json(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $calls  = $this->redis_service->get_active_calls();
        $agents = $this->redis_service->get_agent_states();
        $queues = $this->redis_service->get_queue_states();

        // Non-admin agents only see their own active call
        if (!is_admin()) {
            $staff_id = (string) get_staff_user_id();
            $agent    = $this->Agents_model->get_by_staff_id((int) $staff_id);
            $my_ext   = $agent ? ($agent['extension'] ?? '') : '';

            $calls = array_filter($calls, static function ($c) use ($staff_id, $my_ext) {
                $ev_sid = (string) ($c['staff_id']   ?? '');
                $ev_ext = $c['agent_ext'] ?? '';
                if ($ev_sid !== '' && $ev_sid !== $staff_id) return false;
                if ($ev_ext !== '' && $my_ext !== '' && $ev_ext !== $my_ext) return false;
                return true;
            });
        }

        $this->_json([
            'success' => true,
            'calls'   => array_values($calls),
            'agents'  => $agents,
            'queues'  => $queues,
        ]);
    }

    /** Single call detail by Asterisk uniqueid. */
    public function call(string $uid = ''): void
    {
        if (!is_staff_logged_in()) {
            $this->_json(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        if ($uid === '') {
            $this->_json(['success' => false, 'message' => 'Missing uid'], 400);
            return;
        }

        $detail = $this->redis_service->get_call_detail($uid);
        if (!$detail) {
            $this->_json(['success' => false, 'message' => 'Not found'], 404);
            return;
        }

        $this->_json(['success' => true, 'call' => $detail]);
    }

    /** Admin: push a test Pub/Sub notification to verify the pipeline. */
    public function notify_test(): void
    {
        if (!is_admin()) {
            $this->_json(['success' => false, 'message' => 'Forbidden'], 403);
            return;
        }

        $this->redis_service->publish('salesos:notify', [
            'type' => 'notify.test',
            'msg'  => 'SalesOS real-time pipeline OK',
            'ts'   => (string) time(),
        ]);

        $this->_json(['success' => true]);
    }

    private function _json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
