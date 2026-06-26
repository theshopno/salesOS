<?php

defined('BASEPATH') or exit('No direct script access allowed');

return [
    'admin/salesos/api/.+',
    'admin/salesos/api/sync_cdr',
    'admin/salesos/agents/(create|update/[0-9]+|delete/[0-9]+)',
    'admin/salesos/realtime/.+',
    'admin/salesos/calls/.+',
];
