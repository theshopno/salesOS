<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Format call duration from seconds to MM:SS
 */
function format_call_duration($seconds)
{
    $minutes = floor($seconds / 60);
    $seconds = $seconds % 60;
    return sprintf('%02d:%02d', $minutes, $seconds);
}

/**
 * Get color class for call disposition
 */
function get_disposition_class($disposition)
{
    switch ($disposition) {
        case 'ANSWERED':
            return 'success';
        case 'NO ANSWER':
            return 'warning';
        case 'BUSY':
            return 'danger';
        case 'FAILED':
            return 'danger';
        default:
            return 'default';
    }
}
