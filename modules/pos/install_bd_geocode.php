<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Bangladesh Geocode DB Installer & Seeder
 * Source: https://github.com/nuhil/bangladesh-geocode
 * Installs divisions, districts, upazilas, and unions with English and Bangla names.
 */

if (!isset($CI)) {
    $CI = &get_instance();
}

$db_prefix = db_prefix();

// 1. Create Tables
$CI->db->query("CREATE TABLE IF NOT EXISTS `{$db_prefix}bd_divisions` (
    `id` int(11) NOT NULL,
    `name` varchar(50) NOT NULL,
    `bn_name` varchar(100) NOT NULL,
    `url` varchar(150) DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$CI->db->query("CREATE TABLE IF NOT EXISTS `{$db_prefix}bd_districts` (
    `id` int(11) NOT NULL,
    `division_id` int(11) NOT NULL,
    `name` varchar(50) NOT NULL,
    `bn_name` varchar(100) NOT NULL,
    `lat` varchar(30) DEFAULT NULL,
    `lon` varchar(30) DEFAULT NULL,
    `url` varchar(150) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `division_id` (`division_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$CI->db->query("CREATE TABLE IF NOT EXISTS `{$db_prefix}bd_upazilas` (
    `id` int(11) NOT NULL,
    `district_id` int(11) NOT NULL,
    `name` varchar(50) NOT NULL,
    `bn_name` varchar(100) NOT NULL,
    `url` varchar(150) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `district_id` (`district_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$CI->db->query("CREATE TABLE IF NOT EXISTS `{$db_prefix}bd_unions` (
    `id` int(11) NOT NULL,
    `upazila_id` int(11) NOT NULL,
    `name` varchar(100) NOT NULL,
    `bn_name` varchar(150) NOT NULL,
    `url` varchar(150) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `upazila_id` (`upazila_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Function to seed table from local or remote SQL dump
if (!function_exists('pos_seed_bd_table')) {
    function pos_seed_bd_table($CI, $tableName, $sourceUrl, $localFile, $replacements = [])
    {
        $db_prefix = db_prefix();
        $fullTable = $db_prefix . $tableName;

        // Check if table already has rows
        $count = (int) $CI->db->count_all($fullTable);
        if ($count > 0) {
            return $count;
        }

        $sqlContent = '';
        if (file_exists($localFile) && filesize($localFile) > 100) {
            $sqlContent = file_get_contents($localFile);
        } else {
            // Fetch from GitHub
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $sourceUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $sqlContent = curl_exec($ch);
            curl_close($ch);

            if (!empty($sqlContent)) {
                @mkdir(dirname($localFile), 0755, true);
                @file_put_contents($localFile, $sqlContent);
            }
        }

        if (empty($sqlContent)) {
            log_message('error', "Failed to load SQL for {$fullTable} from {$sourceUrl}");
            return 0;
        }

        // Apply replacements (e.g. table name and column mapping)
        foreach ($replacements as $search => $replace) {
            $sqlContent = str_replace($search, $replace, $sqlContent);
        }

        // Extract INSERT statements
        preg_match_all('/INSERT\s+INTO\s+[^;]+;/is', $sqlContent, $matches);
        if (!empty($matches[0])) {
            foreach ($matches[0] as $query) {
                $CI->db->query($query);
            }
        }

        return (int) $CI->db->count_all($fullTable);
    }
}

// Ensure data dir exists
$dataDir = module_dir_path('pos', 'data/bd_geocode/');
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
}

// 2. Seed Divisions
pos_seed_bd_table(
    $CI,
    'bd_divisions',
    'https://raw.githubusercontent.com/nuhil/bangladesh-geocode/master/divisions/divisions.sql',
    $dataDir . 'divisions.sql',
    [
        'INSERT INTO `divisions`' => "INSERT INTO `{$db_prefix}bd_divisions`",
        'INSERT INTO divisions'   => "INSERT INTO `{$db_prefix}bd_divisions`",
    ]
);

// 3. Seed Districts
pos_seed_bd_table(
    $CI,
    'bd_districts',
    'https://raw.githubusercontent.com/nuhil/bangladesh-geocode/master/districts/districts.sql',
    $dataDir . 'districts.sql',
    [
        'INSERT INTO `districts`' => "INSERT INTO `{$db_prefix}bd_districts`",
        'INSERT INTO districts'   => "INSERT INTO `{$db_prefix}bd_districts`",
    ]
);

// 4. Seed Upazilas
pos_seed_bd_table(
    $CI,
    'bd_upazilas',
    'https://raw.githubusercontent.com/nuhil/bangladesh-geocode/master/upazilas/upazilas.sql',
    $dataDir . 'upazilas.sql',
    [
        'INSERT INTO `upazilas`' => "INSERT INTO `{$db_prefix}bd_upazilas`",
        'INSERT INTO upazilas'   => "INSERT INTO `{$db_prefix}bd_upazilas`",
    ]
);

// 5. Seed Unions
pos_seed_bd_table(
    $CI,
    'bd_unions',
    'https://raw.githubusercontent.com/nuhil/bangladesh-geocode/master/unions/unions.sql',
    $dataDir . 'unions.sql',
    [
        'INSERT INTO `unions` (`id`, `upazilla_id`, `name`, `bn_name`, `url`)' => "INSERT INTO `{$db_prefix}bd_unions` (`id`, `upazila_id`, `name`, `bn_name`, `url`)",
        'INSERT INTO `unions`' => "INSERT INTO `{$db_prefix}bd_unions`",
        'INSERT INTO unions'   => "INSERT INTO `{$db_prefix}bd_unions`",
        '`upazilla_id`'        => '`upazila_id`',
    ]
);
