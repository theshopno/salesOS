<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
 Module Name: BizBot
 Description: Integrates BizBot API for automated CRM notifications (WhatsApp, SMS, etc.).
 Version: 1.1.0
 Requires at least: 3.0.0
 Author: Developed By Ecare Solution
 Author URI: https://bizbot.one
 */

define('BIZBOT_MODULE_NAME', 'bizbot');

hooks()->add_action('admin_init', 'bizbot_init_menu_items');
hooks()->add_action('app_init', 'bizbot_load_libraries');
hooks()->add_action('app_init', 'bizbot_migration_config');
hooks()->add_action('admin_init', 'bizbot_intercept_post');

/**
 * Register activation module hook
 */
register_activation_hook(BIZBOT_MODULE_NAME, 'bizbot_activation_hook');

function bizbot_activation_hook()
{
    $CI = & get_instance();
    require_once(__DIR__ . '/install.php');
}

/**
 * Load the module helper
 */
hooks()->add_action('app_init', 'bizbot_load_helper', 5);

function bizbot_load_helper()
{
    $CI = & get_instance();
    $CI->load->helper(BIZBOT_MODULE_NAME . '/bizbot');
}

register_language_files(BIZBOT_MODULE_NAME, [BIZBOT_MODULE_NAME]);

function bizbot_migration_config()
{
    // Migration Logic: Copy old bizbot_whatsapp_* options to new bizbot_* options if they don't exist
    $options_to_migrate = [
        'bizbot_whatsapp_api_key' => 'bizbot_api_key',
        'bizbot_whatsapp_channel_guid' => 'bizbot_channel_guid',
        'bizbot_whatsapp_ssl_verify' => 'bizbot_ssl_verify',
        'bizbot_whatsapp_notification_admins' => 'bizbot_notification_admins',
        'bizbot_whatsapp_bulk_delay' => 'bizbot_bulk_delay',
        'bizbot_whatsapp_widget_id' => 'bizbot_widget_id',
        'bizbot_whatsapp_widget_type' => 'bizbot_widget_type',
        'bizbot_whatsapp_widget_show_to_admin' => 'bizbot_widget_show_to_admin',
        'bizbot_whatsapp_widget_show_to_customer' => 'bizbot_widget_show_to_customer',
        'bizbot_whatsapp_debug_mode' => 'bizbot_debug_mode',
    ];

    foreach ($options_to_migrate as $old => $new) {
        $old_val = get_option($old);
        $new_val = get_option($new);

        // If new doesn't exist but old does, migrate it
        if ($new_val === '' && $old_val !== '') {
            add_option($new, $old_val);
        }
    }
}

function bizbot_load_libraries()
{
    $CI = & get_instance();
    $CI->load->library('bizbot/Bizbot_api', null, 'bizbot_api');
    $CI->load->model('bizbot/Bizbot_model', 'bizbot_model');
}

function bizbot_init_menu_items()
{
    $CI = & get_instance();

    if (has_permission('settings', '', 'view')) {
        $CI->app_menu->add_setup_menu_item('bizbot', [
            'name' => _l('bizbot'), // Localized
            'collapse' => true,
            'position' => 35,
        ]);

        $CI->app_menu->add_setup_children_item('bizbot', [
            'slug' => 'bizbot_settings',
            'name' => _l('settings'), // Core Perfex string or our own
            'href' => admin_url('bizbot/settings'),
            'position' => 5,
        ]);

        $CI->app_menu->add_setup_children_item('bizbot', [
            'slug' => 'bizbot_templates',
            'name' => _l('bizbot_templates'),
            'href' => admin_url('bizbot/templates'),
            'position' => 7,
        ]);

        $CI->app_menu->add_setup_children_item('bizbot', [
            'slug' => 'bizbot_logs',
            'name' => _l('bizbot_logs'),
            'href' => admin_url('bizbot/logs'),
            'position' => 10,
        ]);

        $CI->app_menu->add_setup_children_item('bizbot', [
            'slug' => 'bizbot_followups',
            'name' => _l('bizbot_followups'),
            'href' => admin_url('bizbot/followups'),
            'position' => 12,
        ]);

        $CI->app_menu->add_setup_children_item('bizbot', [
            'slug' => 'bizbot_queue',
            'name' => _l('bizbot_queue'),
            'href' => admin_url('bizbot/queue'),
            'position' => 13,
        ]);

        $CI->app_menu->add_setup_children_item('bizbot', [
            'slug' => 'bizbot_widget',
            'name' => _l('bizbot_widget'),
            'href' => admin_url('bizbot/widget'),
            'position' => 14,
        ]);

        $CI->app_menu->add_setup_children_item('bizbot', [
            'slug' => 'bizbot_bulk',
            'name' => _l('bizbot_bulk'),
            'href' => admin_url('bizbot/bulk'),
            'position' => 15,
        ]);
    }
}

/*
 * Register Event Hooks
 */
// Client Events
hooks()->add_action('after_client_register', 'bizbot_hook_client_created');
hooks()->add_action('contact_logged_in', 'bizbot_hook_client_login');
hooks()->add_action('customer_profile_updated', 'bizbot_hook_client_updated');

// Lead Events
hooks()->add_action('lead_created', 'bizbot_hook_lead_created');
hooks()->add_action('lead_status_changed', 'bizbot_hook_lead_status_changed');
hooks()->add_action('after_lead_assigned_member_notification_sent', 'bizbot_hook_lead_assigned');
hooks()->add_action('lead_converted_to_customer', 'bizbot_hook_lead_converted');

// Task Events
// Support newer task creation hooks for compatibility across Perfex versions.
hooks()->add_action('task_created', 'bizbot_hook_task_created');
hooks()->add_action('after_add_task', 'bizbot_hook_task_created'); // Standard Perfex hook for task creation
hooks()->add_action('task_status_changed', 'bizbot_hook_task_status_changed');
hooks()->add_action('task_assigned', 'bizbot_hook_task_assigned');
hooks()->add_action('task_assignee_added', 'bizbot_hook_task_assigned'); // Standard hook for when an assignee is added

// Project Events
hooks()->add_action('after_add_project', 'bizbot_hook_project_created');
hooks()->add_action('project_status_changed', 'bizbot_hook_project_status_changed');

// Ticket Events
hooks()->add_action('ticket_created', 'bizbot_hook_ticket_created');
hooks()->add_action('ticket_reply_created', 'bizbot_hook_ticket_reply_added');
hooks()->add_action('ticket_status_changed', 'bizbot_hook_ticket_closed');

// Invoice Events
hooks()->add_action('invoice_created', 'bizbot_hook_invoice_created');
hooks()->add_action('invoice_sent', 'bizbot_hook_invoice_sent');
hooks()->add_action('invoice_overdue_notice_sent', 'bizbot_hook_invoice_overdue');
hooks()->add_action('after_payment_added', 'bizbot_hook_invoice_paid');

// SMS/Reminder Interceptor
hooks()->add_action('sms_trigger_triggered', 'bizbot_hook_sms_trigger_whatsapp');

// Task Comment Events
hooks()->add_action('task_comment_added', 'bizbot_hook_task_comment_added');

// Project Discussion Events
hooks()->add_action('after_add_discussion', 'bizbot_hook_project_discussion_created');
hooks()->add_action('after_add_discussion_comment', 'bizbot_hook_project_discussion_comment_added');

// NEW: Use existing activity filter to trigger notifications without core edits
hooks()->add_filter('before_log_project_activity', 'bizbot_filter_project_activity');

hooks()->add_action('after_reminder_modal_fields', 'bizbot_reminder_modal_fields');
hooks()->add_action('after_cron_run', 'bizbot_cron');
hooks()->add_action('app_admin_footer', 'bizbot_reminder_scripts');

// Table Row Decoration (Direct Chat Buttons)
hooks()->add_filter('leads_table_row_data', 'bizbot_filter_leads_table_whatsapp_button', 10, 2);
hooks()->add_filter('customers_contacts_table_row_data', 'bizbot_filter_contacts_table_whatsapp_button', 10, 2);

// Project Milestones
hooks()->add_action('after_add_milestone', 'bizbot_hook_milestone_added');
hooks()->add_action('after_update_milestone', 'bizbot_hook_milestone_updated');

// Widget Injection in CRM
hooks()->add_action('app_admin_footer', 'bizbot_inject_widget');
hooks()->add_action('app_client_footer', 'bizbot_inject_widget');
// Lead Tab Hooks
hooks()->add_filter('lead_tabs', 'bizbot_add_lead_tab');
hooks()->add_action('lead_tabs_content', 'bizbot_add_lead_tab_content');

function bizbot_add_lead_tab($tabs)
{
    $tabs[] = [
        'name' => 'Bizbot Chat',
        'icon' => 'fa-brands fa-whatsapp',
        'slug' => 'bizbot_chat',
        'visible' => true,
    ];
    return $tabs;
}

function bizbot_add_lead_tab_content($lead)
{
    if ($lead) {
        $CI = & get_instance();
        $CI->load->model('bizbot/bizbot_model');

        // Fetch History
        $CI->db->where('lead_id', $lead->id);
        $CI->db->order_by('created_at', 'DESC');
        $data['history'] = $CI->db->get(db_prefix() . 'bizbot_chat_history')->result_array();

        // Fetch thread_guid
        $CI->db->select('value');
        $CI->db->from(db_prefix() . 'customfieldsvalues');
        $CI->db->join(db_prefix() . 'customfields', db_prefix() . 'customfields.id = ' . db_prefix() . 'customfieldsvalues.fieldid');
        $CI->db->where('slug', 'leads_bizbot_thread_guid');
        $CI->db->where('relid', $lead->id);
        $field = $CI->db->get()->row();
        $data['thread_guid'] = $field ? $field->value : '';

        $CI->load->view('bizbot/chat_history', $data);
    }
}

// Inject Preview into General Tab
hooks()->add_action('after_lead_lead_tabs_content', 'bizbot_inject_chat_preview');

function bizbot_inject_chat_preview($lead)
{
    if ($lead) {
        $CI = & get_instance();

        // Fetch last 3 messages only for preview
        $CI->db->where('lead_id', $lead->id);
        $CI->db->order_by('created_at', 'DESC');
        $CI->db->limit(3);
        $history = $CI->db->get(db_prefix() . 'bizbot_chat_history')->result_array();

        if (count($history) > 0) {
            echo '<hr /><h4 class="no-mtop bold">Latest Bizbot Messages</h4>';
            $CI->load->view('bizbot/chat_history', ['history' => array_reverse($history)]);
        }
    }
}