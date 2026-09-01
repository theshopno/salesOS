<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!$CI->db->table_exists(db_prefix() . 'call_manager_settings')) {
  $CI->db->query('CREATE TABLE `' . db_prefix() . "call_manager_settings` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(255) NOT NULL,
      `value` mediumtext NOT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
}

// Add some default options if they don't exist
add_option('call_manager_pbp_db_host', '192.168.0.202');
add_option('call_manager_pbp_db_name', 'asteriskcdrdb');
add_option('call_manager_pbp_db_user', '');
add_option('call_manager_pbp_db_password', '');
add_option('call_manager_recordings_url', 'https://192.168.0.202/recordings/');
