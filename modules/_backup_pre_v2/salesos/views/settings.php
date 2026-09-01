<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
// Shorthand so view isn't polluted with long get_option() calls
$o = $options; // controller passes $options array keyed by salesos_* name
?>
<div id="wrapper">
<div class="content">
<div class="row">
<div class="col-md-9 col-md-offset-1">

    <!-- ── Page header ───────────────────────────────────────────── -->
    <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
        <div>
            <h4 class="tw-text-xl tw-font-bold tw-mb-0">SalesOS Settings</h4>
            <?php if ($config_mtime): ?>
            <small class="text-muted">
                <i class="fa fa-check-circle text-success"></i>
                Config synced: <strong><?= e($config_mtime) ?></strong>
                <?php if (!$config_writable): ?>
                &nbsp;<span class="label label-warning"><i class="fa fa-lock"></i> File not writable</span>
                <?php endif; ?>
            </small>
            <?php endif; ?>
        </div>
        <button type="button" class="btn btn-default btn-sm" id="btn-health">
            <i class="fa fa-heartbeat"></i> System Status
        </button>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════
         MAIN FORM — one form, every DB key is here.
         Hidden mirror: salesos_pbx_db_host mirrors salesos_ami_host via JS.
         All keys that already have values keep them even if not visible.
         ═══════════════════════════════════════════════════════════════ -->
    <form method="POST" action="<?= admin_url('salesos/settings') ?>" id="sos-form">
        <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">

        <!-- ╔══════════════════════════════════════════════════════════╗
             ║  TIER 1 — QUICK SETUP                                   ║
             ╚══════════════════════════════════════════════════════════╝ -->
        <div class="sos-card">
            <div class="sos-card-head">
                <i class="fa fa-bolt"></i> Quick Setup
                <small>PBX সেটআপ হওয়ার পর শুধু এই তথ্যগুলো দিন</small>
            </div>
            <div class="sos-card-body">

                <!-- Row 1: PBX IP + Discover -->
                <div class="sos-row">
                    <div class="sos-grow">
                        <label class="sos-lbl">
                            PBX Server IP
                            <span class="sos-tip" data-toggle="tooltip"
                                title="আপনার Asterisk PBX সার্ভারের IP ঠিকানা। sysadmin-কে জিজ্ঞেস করুন বা VPS control panel চেক করুন।">
                                <i class="fa fa-question-circle"></i>
                            </span>
                        </label>
                        <div class="sos-input-row">
                            <input type="text" name="salesos_ami_host" id="pbx-ip" class="form-control"
                                value="<?= e($o['salesos_ami_host']) ?>"
                                placeholder="যেমন: 103.42.4.210"
                                autocomplete="off">
                            <button type="button" id="btn-discover" class="btn btn-info">
                                <i class="fa fa-search"></i> সংযোগ পরীক্ষা
                            </button>
                        </div>
                        <!-- Discover results appear here -->
                        <div id="discover-results" style="display:none;margin-top:10px;">
                            <div id="discover-chips" class="sos-chips"></div>
                            <div id="discover-note"  class="sos-discover-note"></div>
                        </div>
                    </div>
                </div>

                <!-- Row 2: Credentials side by side -->
                <div class="sos-row sos-cred-row">
                    <div class="sos-cred-box">
                        <div class="sos-cred-title">
                            <i class="fa fa-terminal"></i> AMI Access
                            <span class="sos-tip" data-toggle="tooltip"
                                title="Asterisk Manager Interface — CRM এই connection দিয়ে call করে।&#10;&#10;কোথায় পাবেন: PBX সার্ভারে&#10;/etc/asterisk/manager.conf">
                                <i class="fa fa-question-circle"></i>
                            </span>
                        </div>
                        <div class="sos-cred-hint">manager.conf</div>
                        <div class="sos-field-pair">
                            <div>
                                <label class="sos-lbl">Username</label>
                                <input type="text" name="salesos_ami_username" class="form-control"
                                    value="<?= e($o['salesos_ami_username']) ?>"
                                    placeholder="crm-api" autocomplete="off">
                            </div>
                            <div>
                                <label class="sos-lbl">Password</label>
                                <input type="password" name="salesos_ami_secret" class="form-control"
                                    value="<?= e($o['salesos_ami_secret']) ?>"
                                    autocomplete="new-password">
                            </div>
                        </div>
                    </div>

                    <div class="sos-cred-box">
                        <div class="sos-cred-title">
                            <i class="fa fa-database"></i> Database Access
                            <span class="sos-tip" data-toggle="tooltip"
                                title="Call record (CDR) database — Asterisk MySQL।&#10;&#10;কোথায় পাবেন: PBX সার্ভারে&#10;/etc/asterisk/cdr_mysql.conf">
                                <i class="fa fa-question-circle"></i>
                            </span>
                        </div>
                        <div class="sos-cred-hint">cdr_mysql.conf</div>
                        <div class="sos-field-pair">
                            <div>
                                <label class="sos-lbl">Username</label>
                                <input type="text" name="salesos_pbx_db_user" class="form-control"
                                    value="<?= e($o['salesos_pbx_db_user']) ?>"
                                    placeholder="asterisk" autocomplete="off">
                            </div>
                            <div>
                                <label class="sos-lbl">Password</label>
                                <input type="password" name="salesos_pbx_db_password" class="form-control"
                                    value="<?= e($o['salesos_pbx_db_password']) ?>"
                                    autocomplete="new-password">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 3: Trunk + Caller ID -->
                <div class="sos-row sos-trunk-row">
                    <div style="flex:1;min-width:200px;">
                        <label class="sos-lbl">
                            SIP Trunk Endpoint
                            <span class="sos-tip" data-toggle="tooltip"
                                title="PBX-এর PJSIP endpoint নাম যেটা দিয়ে outbound call যায়।&#10;&#10;কোথায় পাবেন:&#10;/etc/asterisk/pjsip.conf&#10;[endpoint-xxx] section খুঁজুন।">
                                <i class="fa fa-question-circle"></i>
                            </span>
                        </label>
                        <input type="text" name="salesos_trunk_endpoint" class="form-control"
                            value="<?= e($o['salesos_trunk_endpoint']) ?>"
                            placeholder="endpoint-trunk-bdit">
                    </div>
                    <div style="flex:0 0 180px;">
                        <label class="sos-lbl">
                            Outbound Number (Caller ID)
                            <span class="sos-tip" data-toggle="tooltip"
                                title="Customer যে নম্বর দেখবে। SIP provider দেওয়া DID নম্বর।">
                                <i class="fa fa-question-circle"></i>
                            </span>
                        </label>
                        <input type="text" name="salesos_caller_id" class="form-control"
                            value="<?= e($o['salesos_caller_id']) ?>"
                            placeholder="09649699699">
                    </div>
                </div>

                <!-- Save -->
                <div class="sos-save-row">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fa fa-save"></i> Save &amp; Connect
                    </button>
                    <div class="sos-save-note">
                        <i class="fa fa-info-circle"></i>
                        এই বাটনে ক্লিক করলে সব settings একসাথে সেভ হবে
                    </div>
                </div>

            </div><!-- /.sos-card-body -->
        </div><!-- /.sos-card (Quick Setup) -->


        <!-- ╔══════════════════════════════════════════════════════════╗
             ║  TIER 2 — PREFERENCES (collapsed)                       ║
             ╚══════════════════════════════════════════════════════════╝ -->
        <div class="sos-accordion">
            <a class="sos-accordion-toggle" href="#pref-body" data-toggle="collapse">
                <i class="fa fa-sliders"></i> Preferences
                <i class="fa fa-chevron-down sos-chevron"></i>
            </a>
            <div id="pref-body" class="collapse sos-accordion-body">
                <div class="sos-row" style="flex-wrap:wrap;gap:16px;">

                    <div style="flex:0 0 200px;">
                        <label class="sos-lbl">
                            Click-to-Call Mode
                            <span class="sos-tip" data-toggle="tooltip"
                                title="Direct: Customer-কে সরাসরি call করে।&#10;Ring Agent First: Agent-কে আগে call করে, agent pick up করলে customer-কে connect করে।">
                                <i class="fa fa-question-circle"></i>
                            </span>
                        </label>
                        <select name="salesos_click_to_call_enabled" class="form-control">
                            <option value="0" <?= $o['salesos_click_to_call_enabled'] == '0' ? 'selected' : '' ?>>Direct to Customer</option>
                            <option value="1" <?= $o['salesos_click_to_call_enabled'] == '1' ? 'selected' : '' ?>>Ring Agent First</option>
                        </select>
                    </div>

                    <div style="flex:0 0 180px;">
                        <label class="sos-lbl">
                            Browser Softphone
                            <span class="sos-tip" data-toggle="tooltip"
                                title="Browser থেকে সরাসরি call করার সুবিধা (WebRTC)। PBX-এ SSL ও port 8088 লাগবে।">
                                <i class="fa fa-question-circle"></i>
                            </span>
                        </label>
                        <select name="salesos_webrtc_enabled" class="form-control">
                            <option value="1" <?= $o['salesos_webrtc_enabled'] == '1' ? 'selected' : '' ?>>চালু (WebRTC)</option>
                            <option value="0" <?= $o['salesos_webrtc_enabled'] != '1' ? 'selected' : '' ?>>বন্ধ</option>
                        </select>
                    </div>

                    <div style="flex:0 0 180px;">
                        <label class="sos-lbl">
                            CDR Auto Sync
                            <span class="sos-tip" data-toggle="tooltip" title="Call Logs স্বয়ংক্রিয়ভাবে PBX থেকে import করবে কিনা।">
                                <i class="fa fa-question-circle"></i>
                            </span>
                        </label>
                        <select name="salesos_cdr_sync_enabled" class="form-control">
                            <option value="1" <?= $o['salesos_cdr_sync_enabled'] == '1' ? 'selected' : '' ?>>চালু</option>
                            <option value="0" <?= $o['salesos_cdr_sync_enabled'] == '0' ? 'selected' : '' ?>>বন্ধ</option>
                        </select>
                    </div>

                    <div style="flex:0 0 180px;">
                        <label class="sos-lbl">
                            Incoming Call Popup
                            <span class="sos-tip" data-toggle="tooltip" title="Inbound call এলে browser notification দেখাবে।">
                                <i class="fa fa-question-circle"></i>
                            </span>
                        </label>
                        <select name="salesos_popup_enabled" class="form-control">
                            <option value="1" <?= $o['salesos_popup_enabled'] == '1' ? 'selected' : '' ?>>চালু</option>
                            <option value="0" <?= $o['salesos_popup_enabled'] == '0' ? 'selected' : '' ?>>বন্ধ</option>
                        </select>
                    </div>

                </div>
                <div class="sos-accordion-save">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-save"></i> Save All Settings</button>
                </div>
            </div>
        </div>


        <!-- ╔══════════════════════════════════════════════════════════╗
             ║  TIER 3 — EXPERT SETTINGS (collapsed, warning)          ║
             ║  All DevOps/infra fields live here.                     ║
             ║  Nothing is removed — all DB keys covered.              ║
             ╚══════════════════════════════════════════════════════════╝ -->
        <div class="sos-accordion sos-accordion-expert">
            <a class="sos-accordion-toggle" href="#expert-body" data-toggle="collapse">
                <i class="fa fa-cogs"></i> Expert Settings
                <span class="label label-warning" style="font-size:10px;margin-left:6px;">sysadmin only</span>
                <i class="fa fa-chevron-down sos-chevron"></i>
            </a>
            <div id="expert-body" class="collapse sos-accordion-body">

                <div class="alert alert-warning" style="margin:0 0 16px;font-size:12px;">
                    <i class="fa fa-exclamation-triangle"></i>
                    <strong>এই সেকশনের settings sysadmin নির্দেশনা ছাড়া পরিবর্তন করবেন না।</strong>
                    ভুল value দিলে system কাজ করা বন্ধ করতে পারে।
                </div>

                <!-- WebRTC Details -->
                <div class="sos-expert-group">
                    <div class="sos-expert-group-title"><i class="fa fa-microphone"></i> Browser Softphone (WebRTC)</div>
                    <div class="sos-row" style="flex-wrap:wrap;gap:12px;">
                        <div style="flex:1;min-width:220px;">
                            <label class="sos-lbl">WSS URL <span class="sos-auto-badge" id="badge-wss">auto</span></label>
                            <input type="text" name="salesos_webrtc_wss_url" id="f-wss-url" class="form-control"
                                value="<?= e($o['salesos_webrtc_wss_url']) ?>"
                                placeholder="wss://pbx.bizyto.com/asterisk/ws">
                        </div>
                        <div style="flex:0 0 180px;">
                            <label class="sos-lbl">SIP Domain <span class="sos-auto-badge" id="badge-sip-domain">auto</span></label>
                            <input type="text" name="salesos_webrtc_domain" id="f-sip-domain" class="form-control"
                                value="<?= e($o['salesos_webrtc_domain']) ?>"
                                placeholder="pbx.bizyto.com">
                        </div>
                        <div style="flex:0 0 120px;">
                            <label class="sos-lbl">SIP Realm</label>
                            <input type="text" name="salesos_webrtc_realm" class="form-control"
                                value="<?= e($o['salesos_webrtc_realm'] ?: 'asterisk') ?>"
                                placeholder="asterisk">
                        </div>
                        <div style="flex:0 0 120px;">
                            <label class="sos-lbl">Outbound Prefix
                                <span class="sos-tip" data-toggle="tooltip" title="Dialplan prefix যেমন '9'। বেশিরভাগ ক্ষেত্রে blank।">
                                    <i class="fa fa-question-circle"></i>
                                </span>
                            </label>
                            <input type="text" name="salesos_outbound_prefix" class="form-control"
                                value="<?= e($o['salesos_outbound_prefix']) ?>"
                                placeholder="blank = none">
                        </div>
                    </div>
                </div>

                <!-- Recordings -->
                <div class="sos-expert-group">
                    <div class="sos-expert-group-title"><i class="fa fa-file-audio-o"></i> Recordings</div>
                    <div class="sos-row" style="flex-wrap:wrap;gap:12px;">
                        <div style="flex:1;min-width:240px;">
                            <label class="sos-lbl">Recording Base URL <span class="sos-auto-badge" id="badge-rec-url">auto</span></label>
                            <input type="text" name="salesos_recordings_url" id="f-rec-url" class="form-control"
                                value="<?= e($o['salesos_recordings_url']) ?>"
                                placeholder="http://103.42.4.210:8090/recordings/">
                            <small class="text-muted">CRM এই URL দিয়ে recording serve করে (HTTPS proxy)।</small>
                        </div>
                        <div style="flex:1;min-width:240px;">
                            <label class="sos-lbl">Recordings Path (PBX filesystem)</label>
                            <input type="text" name="salesos_recordings_path" class="form-control"
                                value="<?= e($o['salesos_recordings_path']) ?>"
                                placeholder="/var/spool/asterisk/recording/">
                            <small class="text-muted">Daemon verify করতে ব্যবহার করে। PBX local path।</small>
                        </div>
                    </div>
                </div>

                <!-- PBX Connection overrides -->
                <div class="sos-expert-group">
                    <div class="sos-expert-group-title"><i class="fa fa-plug"></i> PBX Connection Overrides</div>
                    <div class="sos-row" style="flex-wrap:wrap;gap:12px;padding:0 0 12px 0;border-bottom:1px solid #f0f0f0;margin-bottom:12px;">
                        <div style="flex:1;min-width:180px;">
                            <label class="sos-lbl">
                                CDR DB Host
                                <span class="sos-tip" data-toggle="tooltip"
                                    title="সাধারণত PBX IP-এর সমান। শুধু পরিবর্তন করুন যদি CDR Database আলাদা server-এ থাকে। Quick Setup-এর PBX IP পরিবর্তন হলে এটিও স্বয়ংক্রিয় আপডেট হয় (যদি কাস্টম না করা থাকে)।">
                                    <i class="fa fa-question-circle"></i>
                                </span>
                            </label>
                            <input type="text" name="salesos_pbx_db_host" id="f-db-host" class="form-control"
                                value="<?= e($o['salesos_pbx_db_host'] ?: $o['salesos_ami_host']) ?>"
                                placeholder="PBX IP (যেমন 103.42.4.210)"
                                autocomplete="off">
                            <small class="text-muted">সাধারণত PBX IP। DB আলাদা server-এ থাকলে তার IP দিন।</small>
                        </div>
                        <div style="flex:0 0 110px;">
                            <label class="sos-lbl">AMI Port</label>
                            <input type="number" name="salesos_ami_port" class="form-control"
                                value="<?= e($o['salesos_ami_port'] ?: 5038) ?>" placeholder="5038">
                        </div>
                        <div style="flex:0 0 110px;">
                            <label class="sos-lbl">DB Port</label>
                            <input type="number" name="salesos_pbx_db_port" class="form-control"
                                value="<?= e($o['salesos_pbx_db_port'] ?: 3306) ?>" placeholder="3306">
                        </div>
                        <div style="flex:0 0 155px;">
                            <label class="sos-lbl">DB Name</label>
                            <input type="text" name="salesos_pbx_db_name" class="form-control"
                                value="<?= e($o['salesos_pbx_db_name'] ?: 'asteriskcdrdb') ?>" placeholder="asteriskcdrdb">
                        </div>
                        <div style="flex:0 0 160px;">
                            <label class="sos-lbl">PBX Label</label>
                            <input type="text" name="salesos_pbx_name" class="form-control"
                                value="<?= e($o['salesos_pbx_name'] ?: 'Main PBX') ?>" placeholder="Main PBX">
                        </div>
                        <div style="flex:0 0 110px;">
                            <label class="sos-lbl">PBX ID</label>
                            <input type="text" name="salesos_pbx_id" class="form-control"
                                value="<?= e($o['salesos_pbx_id'] ?: 'pbx-01') ?>" placeholder="pbx-01">
                        </div>
                    </div>

                    <!-- SSH Tunnel override (optional) -->
                    <div class="sos-hint" style="margin-bottom:8px;">
                        <i class="fa fa-lock"></i> <strong>SSH Tunnel Override</strong>
                        — শুধু ব্যবহার করুন যখন CRM server থেকে PBX DB-তে direct TCP সংযোগ নেই।
                        দুটোই blank = tunnel ব্যবহার নেই।
                    </div>
                    <div class="sos-row" style="flex-wrap:wrap;gap:12px;padding:0;">
                        <div style="flex:1;min-width:180px;">
                            <label class="sos-lbl">
                                Tunnel Host
                                <span class="sos-tip" data-toggle="tooltip"
                                    title="SSH tunnel যেখানে শেষ হয়েছে সেই host। যেমন 127.0.0.1 (CRM server নিজে যদি tunnel করে)।">
                                    <i class="fa fa-question-circle"></i>
                                </span>
                            </label>
                            <input type="text" name="salesos_pbx_db_conn_host" class="form-control"
                                value="<?= e($o['salesos_pbx_db_conn_host'] ?? '') ?>"
                                placeholder="blank = tunnel নেই">
                        </div>
                        <div style="flex:0 0 130px;">
                            <label class="sos-lbl">
                                Tunnel Port
                                <span class="sos-tip" data-toggle="tooltip"
                                    title="SSH tunnel local port। যেমন 3307।">
                                    <i class="fa fa-question-circle"></i>
                                </span>
                            </label>
                            <input type="number" name="salesos_pbx_db_conn_port" class="form-control"
                                value="<?= e($o['salesos_pbx_db_conn_port'] ?? '') ?>"
                                placeholder="blank">
                        </div>
                    </div>
                </div>

                <!-- Redis -->
                <div class="sos-expert-group">
                    <div class="sos-expert-group-title"><i class="fa fa-bolt"></i> Redis (CRM Server)</div>
                    <div class="sos-hint tw-mb-2">CRM server-এ locally চলে। PBX IP-এর সাথে সম্পর্ক নেই।</div>
                    <div class="sos-row" style="flex-wrap:wrap;gap:12px;">
                        <div style="flex:0 0 175px;">
                            <label class="sos-lbl">Host</label>
                            <input type="text" name="salesos_redis_host" class="form-control"
                                value="<?= e($o['salesos_redis_host'] ?: '127.0.0.1') ?>">
                        </div>
                        <div style="flex:0 0 90px;">
                            <label class="sos-lbl">Port</label>
                            <input type="number" name="salesos_redis_port" class="form-control"
                                value="<?= e($o['salesos_redis_port'] ?: 6379) ?>">
                        </div>
                        <div style="flex:0 0 160px;">
                            <label class="sos-lbl">Password</label>
                            <input type="password" name="salesos_redis_password" class="form-control"
                                value="<?= e($o['salesos_redis_password'] ?? '') ?>"
                                placeholder="blank = no auth">
                        </div>
                        <div style="flex:0 0 145px;">
                            <label class="sos-lbl">Stream Max Length</label>
                            <input type="number" name="salesos_stream_maxlen" class="form-control"
                                value="<?= e($o['salesos_stream_maxlen'] ?: 50000) ?>">
                        </div>
                    </div>
                </div>

                <!-- WebSocket Daemon -->
                <div class="sos-expert-group">
                    <div class="sos-expert-group-title"><i class="fa fa-exchange"></i> WebSocket Daemon (CRM)</div>
                    <div class="sos-hint tw-mb-2">Ratchet daemon যা real-time events browser-এ push করে। Nginx proxy করে।</div>
                    <div class="sos-row" style="flex-wrap:wrap;gap:12px;">
                        <div style="flex:0 0 150px;">
                            <label class="sos-lbl">Internal Port</label>
                            <input type="number" name="salesos_ws_internal_port" class="form-control"
                                value="<?= e($o['salesos_ws_internal_port'] ?: 8080) ?>">
                        </div>
                        <div style="flex:1;min-width:240px;">
                            <label class="sos-lbl">Public WebSocket URL</label>
                            <input type="text" name="salesos_ws_url" class="form-control"
                                value="<?= e($o['salesos_ws_url']) ?>"
                                placeholder="wss://crm.bizyto.com/salesos-ws">
                        </div>
                    </div>
                </div>

                <!-- CDR tuning -->
                <div class="sos-expert-group" style="border-bottom:none;">
                    <div class="sos-expert-group-title"><i class="fa fa-refresh"></i> CDR Performance</div>
                    <div class="sos-row" style="flex-wrap:wrap;gap:12px;">
                        <div style="flex:0 0 140px;">
                            <label class="sos-lbl">Batch Size</label>
                            <input type="number" name="salesos_cdr_batch_size" class="form-control"
                                value="<?= e($o['salesos_cdr_batch_size'] ?: 100) ?>">
                        </div>
                        <div style="flex:0 0 155px;">
                            <label class="sos-lbl">Poll Interval (sec)</label>
                            <input type="number" name="salesos_poll_interval" class="form-control"
                                value="<?= e($o['salesos_poll_interval'] ?: 5) ?>"
                                min="2" max="60">
                        </div>
                    </div>
                </div>

                <div class="sos-accordion-save">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-save"></i> Save All Settings</button>
                </div>
            </div>
        </div>

    </form>

</div><!-- /col -->
</div><!-- /row -->
</div><!-- /content -->
</div><!-- /wrapper -->


<!-- ══ System Status Modal ════════════════════════════════════════════ -->
<div class="modal fade" id="health-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" style="max-width:600px;">
        <div class="modal-content">
            <div class="modal-header" style="background:#2c3e50;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:.8;">&times;</button>
                <h4 class="modal-title" style="color:#fff;font-size:15px;">
                    <i class="fa fa-heartbeat"></i> System Status
                </h4>
            </div>
            <div class="modal-body" id="health-modal-body" style="padding:20px 22px;">
                <div class="text-center" style="padding:28px;">
                    <i class="fa fa-spinner fa-spin fa-2x text-muted"></i>
                    <div class="text-muted tw-mt-2">Checking…</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default btn-sm" id="btn-health-refresh">
                    <i class="fa fa-refresh"></i> Refresh
                </button>
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<style>
/* ── Card (Quick Setup) ───────────────────────────────────── */
.sos-card {
    border: 2px solid #2980b9;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 16px;
}
.sos-card-head {
    background: #2980b9;
    color: #fff;
    padding: 12px 20px;
    font-size: 15px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
}
.sos-card-head small { font-weight: 400; font-size: 12px; color: #d0e8f8; }
.sos-card-body { background: #fff; }

/* ── Rows ─────────────────────────────────────────────────── */
.sos-row {
    display: flex;
    align-items: flex-end;
    gap: 12px;
    padding: 14px 20px;
    border-bottom: 1px solid #edf0f4;
}
.sos-row:last-child { border-bottom: none; }
.sos-grow { flex: 1; }

/* ── IP input row ─────────────────────────────────────────── */
.sos-input-row { display: flex; gap: 8px; }
.sos-input-row .form-control { flex: 1; font-size: 15px; font-weight: 600; }

/* ── Credential boxes ─────────────────────────────────────── */
.sos-cred-row { gap: 16px; flex-wrap: wrap; }
.sos-cred-box {
    flex: 1; min-width: 240px;
    background: #f8fafc;
    border: 1px solid #e0e6ed;
    border-radius: 8px;
    padding: 12px 14px;
}
.sos-cred-title {
    font-size: 12px; font-weight: 700; color: #2c3e50;
    border-left: 3px solid #2980b9; padding-left: 8px; margin-bottom: 2px;
}
.sos-cred-hint { font-size: 11px; color: #aaa; margin-bottom: 8px; }
.sos-field-pair { display: flex; gap: 8px; }
.sos-field-pair > div { flex: 1; }

/* ── Trunk row ────────────────────────────────────────────── */
.sos-trunk-row { flex-wrap: wrap; }

/* ── Save row ─────────────────────────────────────────────── */
.sos-save-row {
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    background: #f0f7ff;
    border-top: 1px solid #d0e4f7;
}
.sos-save-note { font-size: 12px; color: #6a8fb5; }

/* ── Labels / tips ────────────────────────────────────────── */
.sos-lbl {
    font-size: 11px; font-weight: 700; color: #888;
    text-transform: uppercase; letter-spacing: .3px;
    display: block; margin-bottom: 4px;
}
.sos-tip { color: #c5d3de; margin-left: 3px; cursor: help; font-size: 12px; }
.sos-tip:hover { color: #2980b9; }
.sos-hint { font-size: 11px; color: #aaa; }

/* ── Discover chips ───────────────────────────────────────── */
.sos-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.sos-chip {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 16px; font-size: 11px; font-weight: 700;
    border: 1px solid transparent;
}
.sos-chip.ok   { background: #eafaf1; border-color: #27ae60; color: #1e8449; }
.sos-chip.fail { background: #f8f9fa; border-color: #ddd;    color: #bbb; }
.sos-chip.note { background: #eaf4fb; border-color: #aed6f1; color: #1a5276; }
.sos-discover-note { font-size: 12px; margin-top: 6px; }
.sos-auto-badge {
    display: inline-block;
    background: #d5e8f8; color: #2980b9;
    font-size: 9px; font-weight: 700; text-transform: uppercase;
    padding: 1px 5px; border-radius: 8px; margin-left: 4px;
    vertical-align: middle; letter-spacing: .5px;
}

/* ── Accordion ────────────────────────────────────────────── */
.sos-accordion { margin-bottom: 10px; }
.sos-accordion-toggle {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 16px;
    background: #f4f6f8; border: 1px solid #dde3ea;
    border-radius: 8px; color: #444; font-size: 13px; font-weight: 700;
    text-decoration: none; cursor: pointer;
}
.sos-accordion-toggle:hover { background: #eaecf0; color: #222; text-decoration: none; }
.sos-accordion.sos-accordion-expert .sos-accordion-toggle { color: #7d6608; background: #fef9e7; border-color: #f0c040; }
.sos-chevron { margin-left: auto; font-size: 11px; transition: transform .2s; }
.sos-accordion-toggle[aria-expanded="true"] .sos-chevron { transform: rotate(180deg); }
.sos-accordion-body {
    border: 1px solid #dde3ea; border-top: none;
    border-radius: 0 0 8px 8px; background: #fff;
    padding: 16px 20px;
}
.sos-accordion-save { padding-top: 14px; border-top: 1px solid #eee; margin-top: 8px; }

/* ── Expert groups ────────────────────────────────────────── */
.sos-expert-group {
    border-bottom: 1px solid #f0f0f0;
    padding-bottom: 14px;
    margin-bottom: 14px;
}
.sos-expert-group-title {
    font-size: 11px; font-weight: 800; text-transform: uppercase;
    letter-spacing: .6px; color: #888; margin-bottom: 10px;
    display: flex; align-items: center; gap: 6px;
}

/* ── Health modal ─────────────────────────────────────────── */
.sos-health-sum {
    display: flex; align-items: center; gap: 10px;
    padding: 11px 14px; border-radius: 6px; margin-bottom: 14px;
    font-weight: 700; font-size: 14px; border: 1px solid transparent;
}
.sos-health-sum.ok   { background: #eafaf1; color: #1e8449; border-color: #27ae60; }
.sos-health-sum.warn { background: #fef9e7; color: #9a7d0a; border-color: #f39c12; }
.sos-health-sum.fail { background: #fdedec; color: #922b21; border-color: #e74c3c; }
.sos-health-list { border: 1px solid #eee; border-radius: 6px; overflow: hidden; }
.sos-health-row {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 14px; border-bottom: 1px solid #f5f5f5; font-size: 13px;
}
.sos-health-row:last-child { border-bottom: none; }
.sos-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
.sos-dot.ok   { background: #27ae60; box-shadow: 0 0 0 3px #27ae6022; }
.sos-dot.warn { background: #f39c12; }
.sos-dot.fail { background: #e74c3c; }
.sos-svc-name { font-weight: 700; min-width: 160px; }
.sos-svc-detail { font-size: 12px; color: #888; }
.sos-health-foot { font-size: 12px; color: #888; margin-top: 12px; }
</style>
<script>
$(function() {
    $('[data-toggle="tooltip"]').tooltip({ trigger: 'hover', html: true, container: 'body' });

    // Animate accordion chevron via Bootstrap collapse events
    $(document).on('show.bs.collapse hide.bs.collapse', '.collapse', function() {
        var toggle = $('[href="#' + this.id + '"]');
        toggle.attr('aria-expanded', function(i, v){ return v === 'true' ? 'false' : 'true'; });
    });
});

var _api = '<?= admin_url('salesos/api/') ?>';
var _cN  = '<?= $this->security->get_csrf_token_name() ?>';
var _cH  = '<?= $this->security->get_csrf_hash() ?>';
function _csrf() { return encodeURIComponent(_cN) + '=' + encodeURIComponent(_cH); }

// ── Smart sync: ami_host → pbx_db_host (only when they were previously equal) ──
// Tracks whether db_host was customised separately from ami_host.
var _savedAmiHost = '<?= addslashes($o['salesos_ami_host']) ?>';
var _savedDbHost  = '<?= addslashes($o['salesos_pbx_db_host'] ?: $o['salesos_ami_host']) ?>';
var _dbHostInSync = (_savedDbHost === '' || _savedDbHost === _savedAmiHost);

function _syncHost(ip) {
    if (_dbHostInSync) {
        var dbField = document.getElementById('f-db-host');
        if (dbField) { dbField.value = ip; }
    }
    if (ip) {
        var wss  = document.querySelector('[name="salesos_webrtc_wss_url"]');
        var dom  = document.querySelector('[name="salesos_webrtc_domain"]');
        var rec  = document.querySelector('[name="salesos_recordings_url"]');
        if (wss && !wss.value) { wss.value = 'wss://' + ip + '/asterisk/ws'; _markAuto('badge-wss'); }
        if (dom && !dom.value) { dom.value = ip;                              _markAuto('badge-sip-domain'); }
        if (rec && !rec.value) { rec.value = 'http://' + ip + ':8090/recordings/'; _markAuto('badge-rec-url'); }
    }
}
function _markAuto(id) {
    var b = document.getElementById(id);
    if (b) { b.style.display = 'inline-block'; }
}

document.getElementById('pbx-ip').addEventListener('blur', function() {
    _syncHost(this.value.trim());
});
document.getElementById('pbx-ip').addEventListener('input', function() {
    if (_dbHostInSync) {
        var dbField = document.getElementById('f-db-host');
        if (dbField) { dbField.value = this.value.trim(); }
    }
});

// If user manually edits db_host, break the auto-sync
var _dbHostEl = document.getElementById('f-db-host');
if (_dbHostEl) {
    _dbHostEl.addEventListener('input', function() { _dbHostInSync = false; });
}

// Clear "auto" badge when user manually edits expert fields
['salesos_webrtc_wss_url','salesos_webrtc_domain','salesos_recordings_url'].forEach(function(n, i) {
    var el = document.querySelector('[name="' + n + '"]');
    var badges = ['badge-wss','badge-sip-domain','badge-rec-url'];
    if (el) el.addEventListener('input', function() {
        var b = document.getElementById(badges[i]);
        if (b) b.style.display = 'none';
    });
});

// ── Discover ───────────────────────────────────────────────────────────────
document.getElementById('btn-discover').addEventListener('click', function() {
    var ip = document.getElementById('pbx-ip').value.trim();
    if (!ip) { alert('PBX IP ঠিকানা দিন।'); return; }

    var btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

    fetch(_api + 'discover_pbx', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'host=' + encodeURIComponent(ip) + '&' + _csrf()
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        btn.disabled = false;
        if (!d.success) {
            btn.innerHTML = '<i class="fa fa-search"></i> সংযোগ পরীক্ষা';
            alert_float('danger', d.error || 'Discovery failed');
            return;
        }
        btn.innerHTML = '<i class="fa fa-check-circle"></i> পাওয়া গেছে';

        // Render chips
        // Special keys: port 8088 is localhost-only on PBX (design, not failure);
        // port 8080 is alt recordings — irrelevant if :8090 found.
        var chips = document.getElementById('discover-chips');
        chips.innerHTML = '';
        var anyFail = [], anyOk = [], anyNote = [];
        Object.keys(d.results).forEach(function(k) {
            var r = d.results[k];
            var special = null;
            if (k === 'webrtc') {
                special = { label: r.label + ' — Browser-only (Nginx proxy)', note: 'Port 8088 সরাসরি accessible নয় — এটা স্বাভাবিক। Browser wss:// দিয়ে Nginx-এর মাধ্যমে পৌঁছায়।' };
            } else if (k === 'rec_8080' && d.results.rec_8090 && d.results.rec_8090.reachable) {
                special = { label: r.label + ' — Alt port (not needed)', note: null };
            }
            if (special) {
                anyNote.push(special.note);
                chips.innerHTML += '<span class="sos-chip note">'
                    + '<i class="fa fa-info-circle"></i>'
                    + special.label + '</span>';
            } else {
                var ok = r.reachable;
                (ok ? anyOk : anyFail).push(r.label);
                chips.innerHTML += '<span class="sos-chip ' + (ok ? 'ok' : 'fail') + '">'
                    + '<i class="fa ' + (ok ? 'fa-check-circle' : 'fa-times-circle') + '"></i>'
                    + r.label + '</span>';
            }
        });
        document.getElementById('discover-results').style.display = '';
        var note = document.getElementById('discover-note');
        var noteLines = [];
        if (!anyFail.length) {
            noteLines.push('<span class="text-success"><i class="fa fa-check-circle"></i> সব service পাওয়া গেছে। Credentials দিয়ে Save করুন।</span>');
        } else {
            noteLines.push('<span class="text-warning"><i class="fa fa-exclamation-triangle"></i> পাওয়া যায়নি: <strong>' + anyFail.join(', ') + '</strong> — firewall বা service বন্ধ থাকতে পারে।</span>');
        }
        anyNote.filter(Boolean).forEach(function(n) {
            noteLines.push('<small class="text-muted"><i class="fa fa-info-circle"></i> ' + n + '</small>');
        });
        note.innerHTML = noteLines.join('<br>');

        // Auto-derive expert fields from suggested values (only if empty)
        var s = d.suggested;
        var wss = document.querySelector('[name="salesos_webrtc_wss_url"]');
        var dom = document.querySelector('[name="salesos_webrtc_domain"]');
        var rec = document.querySelector('[name="salesos_recordings_url"]');
        if (wss && !wss.value && s.salesos_webrtc_wss_url) { wss.value = s.salesos_webrtc_wss_url; _markAuto('badge-wss'); }
        if (dom && !dom.value && s.salesos_webrtc_domain)  { dom.value = s.salesos_webrtc_domain;  _markAuto('badge-sip-domain'); }
        if (rec && !rec.value && s.salesos_recordings_url) { rec.value = s.salesos_recordings_url; _markAuto('badge-rec-url'); }

        // Sync db_host only if still in lockstep with ami_host
        if (_dbHostInSync) {
            var dbEl = document.getElementById('f-db-host');
            if (dbEl) { dbEl.value = ip; }
        }
    })
    .catch(function(err) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-search"></i> সংযোগ পরীক্ষা';
        alert_float('danger', 'Error: ' + err.message);
    });
});

// ── Health Check ───────────────────────────────────────────────────────────
function _healthLoad() {
    document.getElementById('health-modal-body').innerHTML =
        '<div class="text-center" style="padding:28px;">'
        + '<i class="fa fa-spinner fa-spin fa-2x text-muted"></i>'
        + '<div class="text-muted tw-mt-2">Checking…</div></div>';

    fetch(_api + 'health', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(function(r) { return r.json(); })
    .then(function(d) { _healthRender(d); })
    .catch(function(e) {
        document.getElementById('health-modal-body').innerHTML =
            '<div class="alert alert-danger"><i class="fa fa-times-circle"></i> ' + e.message + '</div>';
    });
}

function _healthRender(d) {
    var st  = d.status || 'unknown';
    var cls = {ok:'ok', degraded:'warn', down:'fail'}[st] || 'fail';
    var ico = {ok:'fa-check-circle', degraded:'fa-exclamation-triangle', down:'fa-times-circle'}[st] || 'fa-times-circle';
    var msg = {ok:'সব ঠিক আছে', degraded:'কিছু service সমস্যা', down:'System down', unknown:'Status অজানা'}[st] || 'Unknown';

    var svcs = [
        { k:'redis',          label:'Redis',            icon:'fa-bolt'     },
        { k:'ami_consumer',   label:'AMI Consumer',     icon:'fa-terminal' },
        { k:'event_archiver', label:'Event Archiver',   icon:'fa-archive'  },
        { k:'ws_server',      label:'WebSocket Server', icon:'fa-exchange' },
        { k:'db',             label:'CDR Database',     icon:'fa-database' },
    ];

    var html = '<div class="sos-health-sum ' + cls + '"><i class="fa ' + ico + '"></i> ' + msg + '</div>'
        + '<div class="sos-health-list">';
    svcs.forEach(function(s) {
        var val  = d[s.k];
        var isOk = (val === true || val === 'ok' || val === 'connected' || val === 'up');
        var dot  = isOk ? 'ok' : (val ? 'warn' : 'fail');
        var det  = typeof val === 'boolean'
            ? (isOk ? '<span class="text-success">Running</span>' : '<span class="text-danger">Not running</span>')
            : (val ? String(val) : '<span class="text-muted">—</span>');
        if (s.k === 'ws_server' && isOk && d.ws_port) det = ':' + d.ws_port;
        if (s.k === 'redis' && isOk && d.stream_lag) {
            var lag = Object.keys(d.stream_lag||{}).map(function(k){ return k+': '+d.stream_lag[k]; }).join(', ');
            if (lag) det += ' <small class="text-muted">lag: ' + lag + '</small>';
        }
        html += '<div class="sos-health-row">'
            + '<div class="sos-dot ' + dot + '"></div>'
            + '<i class="fa ' + s.icon + ' text-muted" style="width:15px;text-align:center;"></i>'
            + '<div class="sos-svc-name">' + s.label + '</div>'
            + '<div class="sos-svc-detail">' + det + '</div>'
            + '</div>';
    });
    html += '</div>';
    if (d.active_calls !== undefined || d.last_event) {
        html += '<div class="sos-health-foot">';
        if (d.active_calls !== undefined) html += '<i class="fa fa-phone"></i> Active: <strong>' + d.active_calls + '</strong>&emsp;';
        if (d.last_event)                 html += '<i class="fa fa-clock-o"></i> Last event: ' + d.last_event;
        html += '</div>';
    }
    document.getElementById('health-modal-body').innerHTML = html;
}

document.getElementById('btn-health').addEventListener('click', function() {
    $('#health-modal').modal('show');
    _healthLoad();
});
document.getElementById('btn-health-refresh').addEventListener('click', _healthLoad);
</script>
