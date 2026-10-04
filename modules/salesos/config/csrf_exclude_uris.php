<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Exempt SalesOS Bizbot Webhook from CSRF verification so BizBot
 * can send POST payloads without a CodeIgniter CSRF token.
 */
return [
    'salesos/bizbot_webhook.*',
    'salesos/bizbot_webhook',
];
