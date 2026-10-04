<?php

defined('BASEPATH') or exit('No direct script access allowed');

$route['salesos/bizbot_webhook/(:any)'] = 'bizbot_webhook/index/$1';
$route['salesos/bizbot_webhook']        = 'bizbot_webhook/index';
