<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Get PBXPilot settings
 */
function get_pbxpilot_settings()
{
    $CI = &get_instance();
    return $CI->db->get(db_prefix() . 'pbxpilot_settings')->row();
}

/**
 * Format call type for UI
 */
function pbxpilot_format_call_type($type)
{
    $types = [
        'outgoing' => _l('Outgoing'),
        'incoming' => _l('Incoming'),
        'extension' => _l('Extension'),
    ];

    return $types[$type] ?? $type;
}

/**
 * Get PBXPilot Call Cases (Intent)
 */
function get_pbxpilot_cases()
{
    return [
        'CASE_CLIENT_SUPPORT' => [
            'name' => 'Existing Client Support / Project Discussion',
            'goal' => 'Focus on issue resolution speed, client satisfaction, and identifying any pending deliverables or technical blockers.',
            'default_format' => 'DETAILED_SUMMARY_ACTION'
        ],
        'CASE_NEW_LEAD' => [
            'name' => 'New Lead Consultation (FB/WA Lead)',
            'goal' => 'Identify the specific "trigger" that led them to call, their budget hesitation, and the exact "Problem-Solution" fit.',
            'default_format' => 'DETAILED_SUMMARY_ACTION'
        ],
        'CASE_FOLLOW_UP' => [
            'name' => 'Follow-Up / Old Inquiry Reconnect',
            'goal' => 'Detect buying signals, reasons for previous delay, and what is currently holding them back from a "Yes".',
            'default_format' => 'SHORT_SUMMARY_ACTION'
        ],
        'CASE_GENERAL_INQUIRY' => [
            'name' => 'General Inquiry / Random Discussion',
            'goal' => 'Bridge the gap between their general curiosity and our core services; identify potential future value.',
            'default_format' => 'KEY_TALKING_POINTS'
        ],
        'CASE_DEEP_TECHNICAL' => [
            'name' => 'Deep Technical / Development Discussion',
            'goal' => 'Extract technical specifications, logic flows, and developer-level requirements or blockers.',
            'default_format' => 'DETAILED_SUMMARY_ACTION'
        ],
        'CASE_COMPLAINT' => [
            'name' => 'Complaint / Risk Management',
            'goal' => 'Identify the root cause of frustration, severity of the issue, and immediate steps to prevent churn.',
            'default_format' => 'KEY_TALKING_POINTS'
        ],
    ];
}

/**
 * Get AI Summary Formats
 */
function get_pbxpilot_formats()
{
    return [
        'DETAILED_SUMMARY_ACTION'   => 'Detailed Summary + Executable Actions',
        'SHORT_SUMMARY_BULLET'      => 'Short Bullet Points Only',
        'SHORT_SUMMARY_ACTION'      => 'Short Summary + Actions',
        'KEY_TALKING_POINTS'        => 'Key Talking Points (Analysis)',
        'AGENT_PERFORMANCE_CRITIQUE' => 'Agent Performance Critique (Scorecard)',
        'CUSTOMER_SATISFACTION_INDEX' => 'Customer Satisfaction & Churn Risk',
    ];
}
