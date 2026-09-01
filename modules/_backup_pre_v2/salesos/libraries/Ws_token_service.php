<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Ws_token_service — issues single-use WebSocket auth tokens via Redis.
 *
 * Tokens are 32-byte random hex strings stored at
 * salesos:ws:token:{token} with a 30-second TTL.
 * The WS server validates and immediately deletes each token on use,
 * so tokens are not reusable and expire automatically.
 */
class Ws_token_service
{
    private CI_Controller $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->library('salesos/Redis_service');
    }

    /**
     * Issue a new WS auth token for the given staff member.
     *
     * @param  int    $staff_id
     * @param  string $role     'admin'|'supervisor'|'agent'
     * @param  string $ext      Agent extension (empty for non-agents)
     * @return string           The token to send to the browser
     */
    public function issue(int $staff_id, string $role = 'agent', string $ext = ''): string
    {
        $token = bin2hex(random_bytes(32));
        $key   = 'salesos:ws:token:' . $token;

        $payload = json_encode([
            'staff_id'   => $staff_id,
            'role'       => $role,
            'ext'        => $ext,
            'issued_at'  => time(),
        ]);

        $this->CI->redis_service->set($key, $payload, 30);

        return $token;
    }

    /**
     * Revoke a token before it expires (e.g. on logout).
     */
    public function revoke(string $token): void
    {
        $this->CI->redis_service->del('salesos:ws:token:' . $token);
    }
}
