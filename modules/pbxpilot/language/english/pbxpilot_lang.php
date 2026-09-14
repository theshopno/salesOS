<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Page titles
$lang['pbxpilot_settings_title'] = 'PBX Pilot Settings';
$lang['pbxpilot_agents_title']   = 'Call Agents';

// Settings — tabs
$lang['pbxpilot_features_tab']   = 'Features';
$lang['pbxpilot_connection_tab'] = 'Connection';

// Settings — Features tab
$lang['pbxpilot_features_intro'] = 'Turn call features on or off for your team. Changes apply as soon as you save.';

$lang['pbxpilot_section_telephony']       = 'Calling';
$lang['pbxpilot_core_telephony']          = 'Enable Calling';
$lang['pbxpilot_core_telephony_tooltip']  = 'Turns the phone system on for your team. If the phone server is briefly unreachable, the rest of the CRM keeps working normally.';
$lang['pbxpilot_channel_browser']         = 'Call from Browser';
$lang['pbxpilot_channel_browser_tooltip'] = "Lets staff make and receive calls right from the CRM in their web browser — no extra software needed.";
$lang['pbxpilot_channel_desktop']         = 'Call from Desktop App';
$lang['pbxpilot_channel_desktop_tooltip'] = "Lets staff use a desktop phone app (like MicroSIP) alongside the CRM. Incoming calls still pop up the matching lead or customer.";

$lang['pbxpilot_section_ai']         = 'AI Call Assistant';
$lang['pbxpilot_ai_mode']            = 'Mode';
$lang['pbxpilot_ai_mode_off']        = 'Off';
$lang['pbxpilot_ai_mode_manual']     = 'Manual — analyze on demand';
$lang['pbxpilot_ai_mode_auto_flagged'] = 'Automatic for flagged calls';
$lang['pbxpilot_ai_mode_full_auto']  = 'Automatic for every call';
$lang['pbxpilot_ai_mode_tooltip']    = 'Controls when calls get an AI summary and transcript. Start with Manual, then switch to automatic once your team is ready.';
$lang['pbxpilot_ai_model']           = 'AI Model';
$lang['pbxpilot_ai_model_placeholder'] = 'Leave blank for now — you can choose a model later';

$lang['pbxpilot_section_reporting']       = 'Call Reporting';
$lang['pbxpilot_effective_call_seconds']  = 'Effective Call Threshold (seconds)';
$lang['pbxpilot_effective_call_seconds_tooltip'] = 'A call counts as "effective" — a real conversation, not just a pickup — when it was answered and lasted at least this many seconds. Used in the Dashboard and Calls report.';

$lang['pbxpilot_section_agency']      = 'Agency Tools';
$lang['pbxpilot_section_agency_note'] = 'Off by default. Turn these on when your team is ready to use call lists, follow-ups, and WhatsApp messaging.';
$lang['pbxpilot_agency_pack']                    = 'Agency Tools';
$lang['pbxpilot_agency_pack_tooltip']            = 'Master switch for the call list, follow-up reminders, and agent performance reports.';
$lang['pbxpilot_agency_bizbot_messaging']        = 'WhatsApp Messaging';
$lang['pbxpilot_agency_bizbot_messaging_tooltip'] = 'Lets staff send WhatsApp messages to leads directly from the CRM.';
$lang['pbxpilot_agency_bizbot_provisioning']     = 'Client Account Setup';
$lang['pbxpilot_agency_bizbot_provisioning_tooltip'] = 'Automatically creates a client messaging account when a new client needs one.';

$lang['pbxpilot_section_escalation']     = 'Unpaid Order Call-Back';
$lang['pbxpilot_voice_escalation']       = 'Enable Call-Back';
$lang['pbxpilot_voice_escalation_tooltip'] = "If a customer doesn't confirm their order in time, this automatically calls them or adds them to an agent's queue.";
$lang['pbxpilot_escalation_locked']      = 'Not available yet';

// Settings — Connection tab
$lang['pbxpilot_connection_intro'] = "These settings tell PBX Pilot which phone system to connect to. Ask whoever set up your phone system for these values.";

$lang['pbxpilot_section_ami']    = 'Phone System Connection';
$lang['pbxpilot_ami_host']       = 'Server Address';
$lang['pbxpilot_ami_port']       = 'Port';
$lang['pbxpilot_ami_username']   = 'Username';
$lang['pbxpilot_ami_secret']     = 'Password';
$lang['pbxpilot_test_connection'] = 'Test Connection';
$lang['pbxpilot_testing']        = 'Testing…';

$lang['pbxpilot_section_cdr']      = 'Call History Database';
$lang['pbxpilot_cdr_db_host']      = 'Server Address';
$lang['pbxpilot_cdr_db_port']      = 'Port';
$lang['pbxpilot_cdr_db_name']      = 'Database Name';
$lang['pbxpilot_cdr_db_user']      = 'Username';
$lang['pbxpilot_cdr_db_password']  = 'Password';
$lang['pbxpilot_sync_cdr_now']     = 'Sync Call History Now';

$lang['pbxpilot_section_recordings']      = 'Call Recordings';
$lang['pbxpilot_recordings_url']          = 'Recordings Server Address';
$lang['pbxpilot_recordings_url_tooltip']  = 'Where PBX Pilot fetches call recordings from — usually the local end of the same tunnel used for the phone system connection above.';
$lang['pbxpilot_recordings_monitor_dir']         = 'Recordings Folder On Phone System';
$lang['pbxpilot_recordings_monitor_dir_tooltip'] = 'The folder on the PBX itself where call recordings are saved (e.g. /var/spool/asterisk/monitor). Used to convert a recording to a playable format on demand — leave the default unless the phone system is set up differently.';
$lang['pbxpilot_recording_retention_days'] = 'Keep Recordings For (days)';
$lang['pbxpilot_recording_retention_days_tooltip'] = "How long a played recording stays cached on this server before it's deleted to save space. It can always be fetched again from the phone system later if needed — this only clears the local copy.";
$lang['pbxpilot_syncing']          = 'Syncing…';
$lang['pbxpilot_sync_result']      = 'Synced {count} call(s).';
$lang['pbxpilot_sync_failed']      = 'Sync failed.';
$lang['pbxpilot_request_failed']   = 'Something went wrong — please try again.';

$lang['pbxpilot_settings_updated'] = 'Settings updated successfully.';

// Agents page
$lang['pbxpilot_agents_intro_title'] = 'Assign extensions to your team';
$lang['pbxpilot_agents_intro_text']  = "When a staff member clicks to call, PBX Pilot rings their assigned extension first, then connects them to the customer.";
$lang['pbxpilot_agents_staff']       = 'Staff Member';
$lang['pbxpilot_agents_extension']   = 'Extension';
$lang['pbxpilot_agents_select_staff'] = '— Select a staff member —';
$lang['pbxpilot_agents_map_btn']     = 'Assign';
$lang['pbxpilot_agents_table_staff'] = 'Staff';
$lang['pbxpilot_agents_table_extension'] = 'Extension';
$lang['pbxpilot_agents_table_status']    = 'Status';
$lang['pbxpilot_agents_status_active']   = 'Active';
$lang['pbxpilot_agents_status_inactive'] = 'Inactive';
$lang['pbxpilot_agents_none']        = 'No agents assigned yet.';
$lang['pbxpilot_agents_required']    = 'Please select a staff member and enter an extension.';
$lang['pbxpilot_agents_extension_numeric'] = 'Extension must contain numbers only.';
$lang['pbxpilot_agent_saved']        = 'Agent mapping saved.';
$lang['pbxpilot_agent_removed']      = 'Agent mapping removed.';
$lang['confirm_remove_agent_mapping'] = 'Remove this agent mapping?';

// Screen-pop toast (browser channel)
$lang['pbxpilot_toast_incoming_call'] = 'Incoming call';
$lang['pbxpilot_toast_view_record']   = 'View Record';
$lang['pbxpilot_toast_no_match']      = 'No matching record';

// Page titles (dashboard/calls)
$lang['pbxpilot_dashboard_title'] = 'PBX Pilot Dashboard';
$lang['pbxpilot_calls_title']     = 'Call Logs';

// Shared filter bar
$lang['pbxpilot_date_from']      = 'From';
$lang['pbxpilot_date_to']        = 'To';
$lang['pbxpilot_apply_filter']   = 'Apply';
$lang['pbxpilot_clear_filter']   = 'Clear';
$lang['pbxpilot_today']          = 'Today';
$lang['pbxpilot_yesterday']      = 'Yesterday';
$lang['pbxpilot_this_week']      = 'This Week';
$lang['pbxpilot_this_month']     = 'This Month';
$lang['pbxpilot_filter_all']     = 'All';
$lang['pbxpilot_filter_direction']   = 'Direction';
$lang['pbxpilot_direction_inbound']  = 'Inbound';
$lang['pbxpilot_direction_outbound'] = 'Outbound';
$lang['pbxpilot_filter_disposition'] = 'Status';
$lang['pbxpilot_filter_search']      = 'Phone Number';
$lang['pbxpilot_filter_search_placeholder'] = 'Search by number';
$lang['pbxpilot_filter_effective_only'] = 'Effective calls only';

// Dashboard
$lang['pbxpilot_stat_total_calls']     = 'Total Calls';
$lang['pbxpilot_stat_effective_calls'] = 'Effective Calls';
$lang['pbxpilot_stat_answered_calls']  = 'Answered';
$lang['pbxpilot_stat_avg_duration']    = 'Avg. Talk Time';
$lang['pbxpilot_stat_inbound']         = 'Inbound Calls';
$lang['pbxpilot_stat_outbound']        = 'Outbound Calls';
$lang['pbxpilot_effective_calls_tooltip'] = 'Answered and lasted at least %d seconds — a real conversation, not just a pickup.';
$lang['pbxpilot_stat_no_lead_note']       = 'Answered, No Lead Note';
$lang['pbxpilot_no_lead_note_tooltip']    = 'The call was answered and matched to a lead, but the agent never added a lead note ("I got in touch" / "I have not contacted this lead") for it that same day.';
$lang['pbxpilot_filter_no_lead_note_only'] = 'Missing lead note only';
$lang['pbxpilot_agent_breakdown']      = 'By Agent';

// Call Logs list
$lang['pbxpilot_col_date']        = 'Date';
$lang['pbxpilot_col_direction']   = 'Dir';
$lang['pbxpilot_col_number']      = 'Number';
$lang['pbxpilot_col_matched']     = 'Matched To';
$lang['pbxpilot_col_duration']    = 'Duration';
$lang['pbxpilot_col_status']      = 'Status';
$lang['pbxpilot_col_disposition'] = 'Disposition';
$lang['pbxpilot_col_recording']   = 'Recording';
$lang['pbxpilot_no_calls_found']  = 'No calls found for these filters.';
$lang['pbxpilot_showing_results'] = 'Showing %d of %d call(s).';
$lang['pbxpilot_effective']       = 'Effective';
$lang['pbxpilot_play_recording']  = 'Play recording';
