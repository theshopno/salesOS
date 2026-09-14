<?php

defined('BASEPATH') or exit('No direct script access allowed');

$db_prefix = db_prefix();

if (!$CI->db->table_exists($db_prefix . 'ordernotifier_logs')) {
    $CI->db->query("
        CREATE TABLE `{$db_prefix}ordernotifier_logs` (
            `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
            `order_id` INT(11) NOT NULL,
            `phone` VARCHAR(20) NOT NULL,
            `channel` VARCHAR(20) NOT NULL,
            `event` VARCHAR(50) NOT NULL,
            `message` TEXT NOT NULL,
            `status` VARCHAR(20) NOT NULL,
            `response` TEXT NULL,
            `sent_at` DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
}
