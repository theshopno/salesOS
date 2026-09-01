<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Bizbot_api
{
    private $ci;
    private $api_key;
    private $channel_guid;
    private $base_url;

    public function __construct()
    {
        $this->ci = & get_instance();
        $this->api_key = get_option('bizbot_api_key');
        $this->channel_guid = get_option('bizbot_channel_guid');
        $this->base_url = 'https://api.bizbot.one/public/v1/chat';
    }

    public function send_message($to, $message, $event_trigger = 'manual')
    {
        if (empty($this->api_key) || empty($this->channel_guid)) {
            if (function_exists('bizbot_log')) {
                bizbot_log("send_message FAIL: API Key or Channel GUID missing");
            }
            return ['status' => 'failed', 'message' => 'API Key or Channel GUID missing', 'http_code' => 0];
        }

        $url = $this->base_url;

        $body = [
            'channel' => $this->channel_guid,
            'phone' => $to,
            'message' => $message
        ];

        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $this->api_key
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        // SSL Verification
        $ssl_verify = get_option('bizbot_ssl_verify');
        if ($ssl_verify === '0') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }
        else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $status = ($http_code >= 200 && $http_code < 300) ? 'success' : 'failed';
        $log_response = $error ? $error : $response;

        if (function_exists('bizbot_log')) {
            bizbot_log("send_message: to=$to, url={$url}, http=$http_code, status=$status" . ($error ? ", curl_error=$error" : '') . ', resp=' . substr($response, 0, 100));
        }

        // Log the attempt
        try {
            $this->ci->load->model('bizbot/Bizbot_model', 'bizbot_model');
            $this->ci->bizbot_model->log_activity([
                'phone' => $to,
                'message' => $message,
                'response' => $log_response,
                'status' => $status,
                'event_trigger' => $event_trigger
            ]);
        }
        catch (\Throwable $e) {
            if (function_exists('bizbot_log')) {
                bizbot_log("log_activity ERROR: " . $e->getMessage());
            }
        }

        return [
            'status' => $status,
            'response' => json_decode($response, true),
            'http_code' => $http_code
        ];
    }

    /**
     * Get Contact details (including tags/labels)
     * @param string $contact_guid
     * @return array
     */
    public function get_contact($contact_guid)
    {
        if (empty($this->api_key)) {
            return ['status' => 'failed', 'message' => 'API Key missing'];
        }

        $url = 'https://api.bizbot.one/public/v1/contact/' . $contact_guid;

        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $this->api_key
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $ssl_verify = get_option('bizbot_ssl_verify');
        if ($ssl_verify === '0') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }
        else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => ($http_code >= 200 && $http_code < 300) ? 'success' : 'failed',
            'data' => json_decode($response, true),
            'http_code' => $http_code
        ];
    }

    /**
     * Search contact by phone - tries multiple endpoints
     */
    public function search_contact_by_phone($phone)
    {
        if (empty($this->api_key)) {
            return ['status' => 'failed', 'message' => 'API Key missing', 'http_code' => 0, 'error' => '', 'response_raw' => ''];
        }

        $endpoints = [
            'https://api.bizbot.one/public/v1/contact?phone=' . urlencode($phone),
            'https://api.bizbot.one/public/v1/contacts?phone=' . urlencode($phone),
        ];

        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $this->api_key
        ];

        $last_response = '';
        $last_http_code = 0;
        $last_error = '';

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
            $curl_error = curl_error($ch);
            curl_close($ch);

            $last_response = $response;
            $last_http_code = $http_code;
            $last_error = $curl_error;

            if ($http_code >= 200 && $http_code < 300) {
                return [
                    'status' => 'success',
                    'data' => json_decode($response, true),
                    'http_code' => $http_code,
                    'error' => $curl_error,
                    'response_raw' => substr($response, 0, 500),
                    'endpoint_used' => $url
                ];
            }
        }

        return [
            'status' => 'failed',
            'data' => null,
            'http_code' => $last_http_code,
            'error' => $last_error,
            'response_raw' => substr($last_response, 0, 500)
        ];
    }

    /**
     * Get chat threads from Bizbot (with pagination)
     */
    public function get_threads($page = 1, $limit = 50)
    {
        if (empty($this->api_key)) {
            return ['status' => 'failed', 'message' => 'API Key missing'];
        }

        $endpoints = [
            'https://api.bizbot.one/public/v1/threads?page=' . $page . '&limit=' . $limit,
            'https://api.bizbot.one/public/v1/chat/threads?page=' . $page . '&limit=' . $limit,
            'https://api.bizbot.one/public/v1/chats?page=' . $page . '&limit=' . $limit,
        ];

        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $this->api_key
        ];

        $last_response = '';
        $last_http_code = 0;

        foreach ($endpoints as $url) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);

            $ssl_verify = get_option('bizbot_ssl_verify');
            if ($ssl_verify === '0') {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            }

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            $last_response = $response;
            $last_http_code = $http_code;
            $last_error = $curl_error;

            if ($http_code >= 200 && $http_code < 300) {
                return [
                    'status' => 'success',
                    'data' => json_decode($response, true),
                    'http_code' => $http_code,
                    'endpoint_used' => $url
                ];
            }
        }

        return [
            'status' => 'failed',
            'data' => null,
            'http_code' => $last_http_code,
            'error' => $last_error,
            'response_raw' => substr($last_response, 0, 500)
        ];
    }
}