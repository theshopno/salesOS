<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Salesos_encryption
{
    private $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        if (!class_exists('CI_Encryption')) {
            $this->CI->load->library('encryption');
        }
    }

    /**
     * Encrypt a string or array (as JSON)
     *
     * @param mixed $data
     * @return string
     */
    public function encrypt($data): string
    {
        if (is_array($data) || is_object($data)) {
            $data = json_encode($data);
        }
        $res = $this->CI->encryption->encrypt($data);
        return is_string($res) ? $res : '';
    }

    /**
     * Decrypt an encrypted payload
     *
     * @param string|null $encrypted_data
     * @param bool $as_array Decrypt and parse JSON as array
     * @return mixed
     */
    public function decrypt(?string $encrypted_data, bool $as_array = false)
    {
        if (empty($encrypted_data)) {
            return null;
        }
        $decrypted = $this->CI->encryption->decrypt($encrypted_data);
        if ($decrypted === false) {
            return null;
        }
        if ($as_array) {
            $decoded = json_decode($decrypted, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }
        return $decrypted;
    }
}
