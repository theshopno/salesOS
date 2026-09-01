<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();

// --- TABLE RENAMING LOGIC (MIGRATION FROM bizbot_whatsapp_*) ---
$tables_to_rename = [
  'bizbot_whatsapp_logs'      => 'bizbot_logs',
  'bizbot_whatsapp_templates' => 'bizbot_templates',
  'bizbot_whatsapp_followups' => 'bizbot_followups',
  'bizbot_whatsapp_queue'     => 'bizbot_queue',
];

foreach ($tables_to_rename as $old_name => $new_name) {
  if ($CI->db->table_exists(db_prefix() . $old_name) && !$CI->db->table_exists(db_prefix() . $new_name)) {
    $CI->db->query('RENAME TABLE `' . db_prefix() . $old_name . '` TO `' . db_prefix() . $new_name . '`;');
  }
}

// 1. Logs Table
if (!$CI->db->table_exists(db_prefix() . 'bizbot_logs')) {
  $CI->db->query('CREATE TABLE `' . db_prefix() . 'bizbot_logs` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `phone` varchar(50) NOT NULL,
      `message` text NOT NULL,
      `response` text,
      `status` varchar(20) NOT NULL,
      `event_trigger` varchar(100) DEFAULT NULL,
      `recorded_at` datetime NOT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

// 2. Templates Table
if (!$CI->db->table_exists(db_prefix() . 'bizbot_templates')) {
  $CI->db->query('CREATE TABLE `' . db_prefix() . 'bizbot_templates` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `event_slug` varchar(100) NOT NULL,
      `template_name` varchar(255) NOT NULL,
      `message` text,
      `active` tinyint(1) DEFAULT 0,
      `send_to_customer` tinyint(1) DEFAULT 0,
      `send_to_staff` tinyint(1) DEFAULT 0,
      `send_to_admin` tinyint(1) DEFAULT 0,
      `send_to_followers` tinyint(1) DEFAULT 0,
      `custom_numbers` text,
      `days_before` int(11) DEFAULT 0,
      PRIMARY KEY (`id`),
      UNIQUE KEY `event_slug` (`event_slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
} else {
  if (!$CI->db->field_exists('days_before', db_prefix() . 'bizbot_templates')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'bizbot_templates` ADD `days_before` INT(11) DEFAULT 0;');
  }
  if (!$CI->db->field_exists('send_to_followers', db_prefix() . 'bizbot_templates')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'bizbot_templates` ADD `send_to_followers` TINYINT(1) DEFAULT 0 AFTER `send_to_admin`;');
  }
}

// 3. Followups Table
if (!$CI->db->table_exists(db_prefix() . 'bizbot_followups')) {
  $CI->db->query('CREATE TABLE `' . db_prefix() . 'bizbot_followups` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(100) DEFAULT NULL,
          `step_number` int(11) NOT NULL DEFAULT 1,
          `delay_value` int(11) NOT NULL DEFAULT 1,
          `delay_unit` enum("minutes","hours","days","weeks") DEFAULT "hours",
          `message` text NOT NULL,
          `status_id` int(11) DEFAULT NULL,
          `blacklist_statuses` text DEFAULT NULL,
          `max_per_lead` int(11) DEFAULT 0,
          `stop_on_status_change` tinyint(1) DEFAULT 0,
          `active` tinyint(1) DEFAULT 1,
          PRIMARY KEY (`id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
} else {
  $followups_table = db_prefix() . 'bizbot_followups';

  if (!$CI->db->field_exists('name', $followups_table)) {
    $CI->db->query("ALTER TABLE `{$followups_table}` ADD `name` VARCHAR(100) DEFAULT NULL AFTER `id`;");
  }

  if (!$CI->db->field_exists('step_number', $followups_table)) {
    $CI->db->query("ALTER TABLE `{$followups_table}` ADD `step_number` INT(11) NOT NULL DEFAULT 1 AFTER `name`;");
  }

  if (!$CI->db->field_exists('delay_value', $followups_table)) {
    $CI->db->query("ALTER TABLE `{$followups_table}` ADD `delay_value` INT(11) NOT NULL DEFAULT 1 AFTER `step_number`;");
    if ($CI->db->field_exists('delay_hours', $followups_table)) {
      $CI->db->query("UPDATE `{$followups_table}` SET `delay_value` = `delay_hours` WHERE `delay_hours` > 0;");
    }
  }

  if (!$CI->db->field_exists('delay_unit', $followups_table)) {
    $CI->db->query("ALTER TABLE `{$followups_table}` ADD `delay_unit` ENUM('minutes','hours','days','weeks') DEFAULT 'hours' AFTER `delay_value`;");
  }

  if (!$CI->db->field_exists('blacklist_statuses', $followups_table)) {
    $CI->db->query("ALTER TABLE `{$followups_table}` ADD `blacklist_statuses` TEXT DEFAULT NULL AFTER `status_id`;");
  }

  if (!$CI->db->field_exists('max_per_lead', $followups_table)) {
    $CI->db->query("ALTER TABLE `{$followups_table}` ADD `max_per_lead` INT(11) DEFAULT 0 AFTER `blacklist_statuses`;");
  }

  if (!$CI->db->field_exists('stop_on_status_change', $followups_table)) {
    $CI->db->query("ALTER TABLE `{$followups_table}` ADD `stop_on_status_change` TINYINT(1) DEFAULT 0 AFTER `max_per_lead`;");
  }
}

// 4. Queue Table
if (!$CI->db->table_exists(db_prefix() . 'bizbot_queue')) {
  $CI->db->query('CREATE TABLE `' . db_prefix() . 'bizbot_queue` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `rel_id` int(11) NOT NULL,
          `rel_type` varchar(20) NOT NULL,
          `rule_id` int(11) DEFAULT NULL,
          `phone` varchar(20) NOT NULL,
          `message` text NOT NULL,
          `original_status` int(11) DEFAULT NULL,
          `scheduled_at` datetime NOT NULL,
          `status` varchar(20) DEFAULT "pending",
          `created_at` datetime NOT NULL,
          PRIMARY KEY (`id`),
          KEY `scheduled_at` (`scheduled_at`),
          KEY `status` (`status`)
      ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
} else {
  if (!$CI->db->field_exists('original_status', db_prefix() . 'bizbot_queue')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'bizbot_queue` ADD `original_status` INT(11) DEFAULT NULL AFTER `message`;');
  }
}

// 5. Reminders Sent Table
if (!$CI->db->table_exists(db_prefix() . 'bizbot_reminders_sent')) {
  $CI->db->query('CREATE TABLE `' . db_prefix() . 'bizbot_reminders_sent` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `reminder_id` int(11) NOT NULL,
          `sent_at` datetime NOT NULL,
          PRIMARY KEY (`id`),
          KEY `reminder_id` (`reminder_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

// 6. Reminders Table Columns
$reminder_fields = [
  'notify_by_whatsapp' => 'TINYINT(1) DEFAULT 0',
  'notify_lead_by_wa'  => 'TINYINT(1) DEFAULT 0',
  'notify_staff_by_wa' => 'TINYINT(1) DEFAULT 0'
];

foreach ($reminder_fields as $field => $def) {
  if (!$CI->db->field_exists($field, db_prefix() . 'reminders')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'reminders` ADD `' . $field . '` ' . $def . ';');
  }
}

// 7. Options
$options = [
  'bizbot_ssl_verify' => '1',
  'bizbot_bulk_delay' => '3',
  'bizbot_debug_mode' => '0',
];

foreach ($options as $key => $val) {
  if (get_option($key) == '') {
    add_option($key, $val);
  }
}

// 8. Custom Fields
$CI->db->where('slug', 'leads_bizbot_thread_guid');
$field = $CI->db->get(db_prefix() . 'customfields')->row();
if (!$field) {
  $CI->db->insert(db_prefix() . 'customfields', [
    'fieldto'   => 'leads',
    'name'      => 'Bizbot Thread GUID',
    'slug'      => 'leads_bizbot_thread_guid',
    'type'      => 'input',
    'show_on_table' => 0,
    'active'    => 1,
    'only_admin' => 1,
  ]);
}

// 9. Chat History Table
if (!$CI->db->table_exists(db_prefix() . 'bizbot_chat_history')) {
  $CI->db->query('CREATE TABLE `' . db_prefix() . 'bizbot_chat_history` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `lead_id` int(11) NOT NULL,
          `message_id` varchar(255) DEFAULT NULL,
          `sender` varchar(50) NOT NULL,
          `message` text NOT NULL,
          `created_at` datetime NOT NULL,
          PRIMARY KEY (`id`),
          KEY `lead_id` (`lead_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}
