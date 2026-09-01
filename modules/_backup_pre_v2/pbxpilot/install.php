<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!$CI->db->table_exists(db_prefix() . 'pbxpilot_calls')) {
  $CI->db->query('CREATE TABLE `' . db_prefix() . 'pbxpilot_calls` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `lead_id` int(11) DEFAULT NULL,
      `uploaded_by` int(11) NOT NULL,
      `file_name` varchar(255) NOT NULL,
      `file_path` varchar(255) NOT NULL,
      `phone_number` varchar(50) DEFAULT NULL,
      `call_type` varchar(50) DEFAULT NULL,
      `extension_number` varchar(50) DEFAULT NULL,
      `call_source` varchar(50) DEFAULT "manual",
      `transcription_text` text,
      `ai_summary` text,
      `preset_type` varchar(100) DEFAULT NULL,
      `ai_provider` varchar(50) DEFAULT NULL,
      `audio_hash` varchar(100) DEFAULT NULL,
      `duration_seconds` int(11) DEFAULT 0,
      `is_processed` tinyint(1) DEFAULT 0,
      `is_duplicate` tinyint(1) DEFAULT 0,
      `last_error` text,
      `created_at` datetime NOT NULL,
      `deleted_at` datetime DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `lead_id` (`lead_id`),
      KEY `phone_number` (`phone_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
}

if (!$CI->db->table_exists(db_prefix() . 'pbxpilot_settings')) {
  $CI->db->query('CREATE TABLE `' . db_prefix() . 'pbxpilot_settings` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `retention_days` int(11) DEFAULT 30,
      `default_ai_provider` varchar(50) DEFAULT "openai",
      `allow_google_stt` tinyint(1) DEFAULT 0,
      `allow_openai_stt` tinyint(1) DEFAULT 1,
      `max_audio_size` int(11) DEFAULT 10,
      `duplicate_check_enabled` tinyint(1) DEFAULT 1,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');

  // Insert default settings
  $CI->db->insert(db_prefix() . 'pbxpilot_settings', [
    'retention_days' => 30,
    'default_ai_provider' => 'openai',
    'allow_google_stt' => 0,
    'allow_openai_stt' => 1,
    'max_audio_size' => 10,
    'duplicate_check_enabled' => 1,
  ]);
}

// Create uploads directory if it doesn't exist
if (!is_dir(PBXPILOT_UPLOADS_FOLDER)) {
  mkdir(PBXPILOT_UPLOADS_FOLDER, 0755, true);
  fopen(PBXPILOT_UPLOADS_FOLDER . 'index.html', 'w');
}
// Add last_error column if it doesn't exist
if (!$CI->db->field_exists('last_error', db_prefix() . 'pbxpilot_calls')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'pbxpilot_calls` ADD `last_error` TEXT AFTER `is_duplicate`;');
}
