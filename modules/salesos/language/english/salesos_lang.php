<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Page titles
$lang['salesos_settings_title'] = 'SalesOS Settings';
$lang['salesos_agents_title']   = 'Call Agents';

// Settings — tabs
$lang['salesos_features_tab']   = 'Features';
$lang['salesos_connection_tab'] = 'Connection';

// Settings — Features tab
$lang['salesos_features_intro'] = 'Turn call features on or off for your team. Changes apply as soon as you save.';

$lang['salesos_section_telephony']       = 'Calling';
$lang['salesos_core_telephony']          = 'Enable Calling';
$lang['salesos_core_telephony_tooltip']  = 'Turns the phone system on for your team. If the phone server is briefly unreachable, the rest of the CRM keeps working normally.';
$lang['salesos_channel_browser']         = 'Call from Browser';
$lang['salesos_channel_browser_tooltip'] = "Lets staff make and receive calls right from the CRM in their web browser — no extra software needed.";
$lang['salesos_channel_desktop']         = 'Call from Desktop App';
$lang['salesos_channel_desktop_tooltip'] = "Lets staff use a desktop phone app (like MicroSIP) alongside the CRM. Incoming calls still pop up the matching lead or customer.";

$lang['salesos_section_ai']         = 'AI Call Assistant';
$lang['salesos_ai_mode']            = 'Mode';
$lang['salesos_ai_mode_off']        = 'Off';
$lang['salesos_ai_mode_manual']     = 'Manual — analyze on demand';
$lang['salesos_ai_mode_auto_flagged'] = 'Automatic for flagged calls';
$lang['salesos_ai_mode_full_auto']  = 'Automatic for every call';
$lang['salesos_ai_mode_tooltip']    = 'Controls when calls get an AI summary and transcript. Start with Manual, then switch to automatic once your team is ready.';
$lang['salesos_ai_model']           = 'AI Model';
$lang['salesos_ai_model_placeholder'] = 'Leave blank for now — you can choose a model later';

$lang['salesos_section_reporting']       = 'Call Reporting';
$lang['salesos_effective_call_seconds']  = 'Effective Call Threshold (seconds)';
$lang['salesos_effective_call_seconds_tooltip'] = 'A call counts as "effective" — a real conversation, not just a pickup — when it was answered and lasted at least this many seconds. Used in the Dashboard and Calls report.';

$lang['salesos_section_agency']      = 'Agency Tools';
$lang['salesos_section_agency_note'] = 'Off by default. Turn these on when your team is ready to use call lists, follow-ups, and WhatsApp messaging.';
$lang['salesos_agency_pack']                    = 'Agency Tools';
$lang['salesos_agency_pack_tooltip']            = 'Master switch for the call list, follow-up reminders, and agent performance reports.';
$lang['salesos_agency_bizbot_messaging']        = 'WhatsApp Messaging';
$lang['salesos_agency_bizbot_messaging_tooltip'] = 'Lets staff send WhatsApp messages to leads directly from the CRM.';
$lang['salesos_agency_bizbot_provisioning']     = 'Client Account Setup';
$lang['salesos_agency_bizbot_provisioning_tooltip'] = 'Automatically creates a client messaging account when a new client needs one.';

$lang['salesos_section_escalation']     = 'Unpaid Order Call-Back';
$lang['salesos_voice_escalation']       = 'Enable Call-Back';
$lang['salesos_voice_escalation_tooltip'] = "If a customer doesn't confirm their order in time, this automatically calls them or adds them to an agent's queue.";
$lang['salesos_escalation_locked']      = 'Not available yet';

// Settings — Connection tab
$lang['salesos_connection_intro'] = "These settings tell SalesOS which phone system to connect to. Ask whoever set up your phone system for these values.";

$lang['salesos_section_ami']    = 'Phone System Connection';
$lang['salesos_ami_host']       = 'Server Address';
$lang['salesos_ami_port']       = 'Port';
$lang['salesos_ami_username']   = 'Username';
$lang['salesos_ami_secret']     = 'Password';
$lang['salesos_test_connection'] = 'Test Connection';
$lang['salesos_testing']        = 'Testing…';

$lang['salesos_section_cdr']      = 'Call History Database';
$lang['salesos_cdr_db_host']      = 'Server Address';
$lang['salesos_cdr_db_port']      = 'Port';
$lang['salesos_cdr_db_name']      = 'Database Name';
$lang['salesos_cdr_db_user']      = 'Username';
$lang['salesos_cdr_db_password']  = 'Password';
$lang['salesos_sync_cdr_now']     = 'Sync Call History Now';

$lang['salesos_section_recordings']      = 'Call Recordings';
$lang['salesos_recordings_url']          = 'Recordings Server Address';
$lang['salesos_recordings_url_tooltip']  = 'Where SalesOS fetches call recordings from — usually the local end of the same tunnel used for the phone system connection above.';
$lang['salesos_recordings_monitor_dir']         = 'Recordings Folder On Phone System';
$lang['salesos_recordings_monitor_dir_tooltip'] = 'The folder on the PBX itself where call recordings are saved (e.g. /var/spool/asterisk/monitor). Used to convert a recording to a playable format on demand — leave the default unless the phone system is set up differently.';
$lang['salesos_recording_retention_days'] = 'Keep Recordings For (days)';
$lang['salesos_recording_retention_days_tooltip'] = "How long a played recording stays cached on this server before it's deleted to save space. It can always be fetched again from the phone system later if needed — this only clears the local copy.";
$lang['salesos_syncing']          = 'Syncing…';
$lang['salesos_sync_result']      = 'Synced {count} call(s).';
$lang['salesos_sync_failed']      = 'Sync failed.';
$lang['salesos_request_failed']   = 'Something went wrong — please try again.';

$lang['salesos_settings_updated'] = 'Settings updated successfully.';

// Agents page
$lang['salesos_agents_intro_title'] = 'Assign extensions to your team';
$lang['salesos_agents_intro_text']  = "When a staff member clicks to call, SalesOS rings their assigned extension first, then connects them to the customer.";
$lang['salesos_agents_staff']       = 'Staff Member';
$lang['salesos_agents_extension']   = 'Extension';
$lang['salesos_agents_select_staff'] = '— Select a staff member —';
$lang['salesos_agents_map_btn']     = 'Assign';
$lang['salesos_agents_table_staff'] = 'Staff';
$lang['salesos_agents_table_extension'] = 'Extension';
$lang['salesos_agents_table_status']    = 'Status';
$lang['salesos_agents_status_active']   = 'Active';
$lang['salesos_agents_status_inactive'] = 'Inactive';
$lang['salesos_agents_none']        = 'No agents assigned yet.';
$lang['salesos_agents_required']    = 'Please select a staff member and enter an extension.';
$lang['salesos_agents_extension_numeric'] = 'Extension must contain numbers only.';
$lang['salesos_agent_saved']        = 'Agent mapping saved.';
$lang['salesos_agent_removed']      = 'Agent mapping removed.';
$lang['confirm_remove_agent_mapping'] = 'Remove this agent mapping?';

// Screen-pop toast (browser channel)
$lang['salesos_toast_incoming_call'] = 'Incoming call';
$lang['salesos_toast_view_record']   = 'View Record';
$lang['salesos_toast_no_match']      = 'No matching record';

// Page titles (dashboard/calls)
$lang['salesos_dashboard_title'] = 'SalesOS Dashboard';
$lang['salesos_calls_title']     = 'Call Logs';

// Shared filter bar
$lang['salesos_date_from']      = 'From';
$lang['salesos_date_to']        = 'To';
$lang['salesos_apply_filter']   = 'Apply';
$lang['salesos_clear_filter']   = 'Clear';
$lang['salesos_today']          = 'Today';
$lang['salesos_yesterday']      = 'Yesterday';
$lang['salesos_this_week']      = 'This Week';
$lang['salesos_this_month']     = 'This Month';
$lang['salesos_filter_all']     = 'All';
$lang['salesos_filter_direction']   = 'Direction';
$lang['salesos_direction_inbound']  = 'Inbound';
$lang['salesos_direction_outbound'] = 'Outbound';
$lang['salesos_filter_disposition'] = 'Status';
$lang['salesos_filter_search']      = 'Phone Number';
$lang['salesos_filter_search_placeholder'] = 'Search by number';
$lang['salesos_filter_effective_only'] = 'Effective calls only';

// Dashboard
$lang['salesos_stat_total_calls']     = 'Total Calls';
$lang['salesos_stat_effective_calls'] = 'Effective Calls';
$lang['salesos_stat_answered_calls']  = 'Answered';
$lang['salesos_stat_avg_duration']    = 'Avg. Talk Time';
$lang['salesos_stat_inbound']         = 'Inbound Calls';
$lang['salesos_stat_outbound']        = 'Outbound Calls';
$lang['salesos_effective_calls_tooltip'] = 'Answered and lasted at least %d seconds — a real conversation, not just a pickup.';
$lang['salesos_stat_no_lead_note']       = 'Answered, No Lead Note';
$lang['salesos_no_lead_note_tooltip']    = 'The call was answered and matched to a lead, but the agent never added a lead note ("I got in touch" / "I have not contacted this lead") for it that same day.';
$lang['salesos_filter_no_lead_note_only'] = 'Missing lead note only';
$lang['salesos_agent_breakdown']      = 'By Agent';

// Call Logs list
$lang['salesos_col_date']        = 'Date';
$lang['salesos_col_direction']   = 'Dir';
$lang['salesos_col_number']      = 'Number';
$lang['salesos_col_matched']     = 'Matched To';
$lang['salesos_col_duration']    = 'Duration';
$lang['salesos_col_status']      = 'Status';
$lang['salesos_col_disposition'] = 'Disposition';
$lang['salesos_col_recording']   = 'Recording';
$lang['salesos_no_calls_found']  = 'No calls found for these filters.';
$lang['salesos_showing_results'] = 'Showing %d of %d call(s).';
$lang['salesos_effective']       = 'Effective';
$lang['salesos_play_recording']  = 'Play recording';
