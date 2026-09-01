<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <?php echo $title; ?>
                        </h4>
                        <hr class="hr-panel-heading" />

                        <?php echo form_open(admin_url('bizbot/settings')); ?>

                        <div class="row">
                            <div class="col-md-6">
                                <?php echo render_input('settings[bizbot_api_key]', _l('bizbot_api_key'), get_option('bizbot_api_key')); ?>
                                <?php echo render_input('settings[bizbot_channel_guid]', _l('bizbot_channel_guid'), get_option('bizbot_channel_guid')); ?>

                                <div class="form-group">
                                    <label class="control-label">Webhook URL (Copy to Bizbot Dashboard)</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" value="<?php echo site_url('modules/bizbot/webhook.php'); ?>" readonly id="webhook_url">
                                        <span class="input-group-addon">
                                            <a href="#" onclick="copy_webhook_url(); return false;"><i class="fa fa-copy"></i></a>
                                        </span>
                                    </div>
                                    <p class="text-muted small">✅ <strong>এই URL টি Bizbot-এ সেট করুন।</strong> এটা সরাসরি কাজ করে, CSRF বা রাউটিং সমস্যা হবে না।</p>
                                </div>

                                <!-- Test Connection Button -->
                                <div class="form-group">
                                    <button type="button" class="btn btn-default" id="test-connection-btn">
                                        <i class="fa fa-plug"></i> Test Connection
                                    </button>
                                    <button type="button" class="btn btn-warning" id="bulk-sync-btn">
                                        <i class="fa fa-refresh"></i> Bulk Sync Threads
                                    </button>
                                    <span id="connection-result" class="mleft10"></span>
                                    <span id="sync-result" class="mleft10"></span>
                                </div>

                                <div class="form-group">
                                    <label for="bizbot_debug_mode" class="control-label clearfix">
                                        Debug Mode
                                    </label>
                                    <div class="radio radio-primary radio-inline">
                                        <input type="radio" id="y_opt_1_debug"
                                            name="settings[bizbot_debug_mode]" value="1" <?php if (get_option('bizbot_debug_mode') == '1') echo 'checked'; ?>>
                                        <label for="y_opt_1_debug">Enabled</label>
                                    </div>
                                    <div class="radio radio-primary radio-inline">
                                        <input type="radio" id="y_opt_2_debug"
                                            name="settings[bizbot_debug_mode]" value="0" <?php if (get_option('bizbot_debug_mode') == '0') echo 'checked'; ?>>
                                        <label for="y_opt_2_debug">Disabled</label>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="bizbot_ssl_verify" class="control-label clearfix">
                                        <?php echo _l('bizbot_ssl_verify'); ?>
                                    </label>
                                    <div class="radio radio-primary radio-inline">
                                        <input type="radio" id="ssl_opt_1"
                                            name="settings[bizbot_ssl_verify]" value="1" <?php if (get_option('bizbot_ssl_verify') != '0') echo 'checked'; ?>>
                                        <label for="ssl_opt_1">Enabled (Secure)</label>
                                    </div>
                                    <div class="radio radio-primary radio-inline">
                                        <input type="radio" id="ssl_opt_2"
                                            name="settings[bizbot_ssl_verify]" value="0" <?php if (get_option('bizbot_ssl_verify') == '0') echo 'checked'; ?>>
                                        <label for="ssl_opt_2">Disabled (Local/Untrusted SSL)</label>
                                    </div>
                                </div>

                                <?php
                                $selected_admins = get_option('bizbot_notification_admins');
                                $selected_admins = !empty($selected_admins) ? json_decode($selected_admins) : [];
                                echo render_select('settings[bizbot_notification_admins][]', $staff, ['staffid', ['firstname', 'lastname']], 'Notification Admins (Receives "Send to Admin" alerts)', $selected_admins, ['multiple' => true, 'data-actions-box' => true], [], '', '', false);
                                ?>

                                <?php echo render_input('settings[bizbot_bulk_delay]', 'Bulk Message Delay (seconds)', get_option('bizbot_bulk_delay'), 'number', ['min' => 0]); ?>

                                <button type="submit" class="btn btn-info"><?php echo _l('submit'); ?></button>
                            </div>

                            <div class="col-md-6">
                                <h4><?php echo _l('bizbot_test_message'); ?></h4>
                                <div class="form-group">
                                    <label for="test_message_number"><?php echo _l('bizbot_test_number'); ?></label>
                                    <input type="text" name="test_message_number" id="test_message_number"
                                        class="form-control">
                                </div>
                                <p class="text-muted">Save your API settings before sending a test message.</p>
                                <button type="submit" class="btn btn-primary"
                                    onclick="return confirm('Send test message?');"><?php echo _l('bizbot_test_send'); ?></button>
                            </div>
                        </div>

                        <?php echo form_close(); ?>

                    </div>
                </div>
            </div>

            <div class="col-md-12 mtop20">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">📝 Activity Logs (Events & API Calls)</h4>
                        <hr class="hr-panel-heading" />
                        <div class="form-group">
                            <button type="button" class="btn btn-default" id="refresh-activity-logs">
                                <i class="fa fa-refresh"></i> Refresh
                            </button>
                            <button type="button" class="btn btn-danger btn-xs" id="clear-activity-logs">
                                <i class="fa fa-trash"></i> Clear
                            </button>
                        </div>
                        <pre id="activity-logs-content" style="background: #1e1e1e; color: #d4d4d4; padding: 10px; height: 300px; overflow-y: scroll; font-size: 11px;">Loading...</pre>
                        <p class="text-muted small">লিড স্ট্যাটাস চেঞ্জ, মেসেজ পাঠানো সব ইভেন্ট এখানে লগ হবে।</p>
                    </div>
                </div>
            </div>

            <div class="col-md-12 mtop20">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">Webhook Debug Logs</h4>
                        <hr class="hr-panel-heading" />
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <button type="button" class="btn btn-default" id="refresh-webhook-logs">
                                        <i class="fa fa-refresh"></i> Refresh Logs
                                    </button>
                                    <button type="button" class="btn btn-danger" id="clear-webhook-logs">
                                        <i class="fa fa-trash"></i> Clear Logs
                                    </button>
                                </div>
                                <pre id="webhook-logs-content" style="background: #f0f0f0; padding: 10px; height: 300px; overflow-y: scroll; font-size: 11px;">Loading logs...</pre>
                                <p class="text-muted small">Bizbot থেকে আসা লেটেস্ট ওয়েবহুক হিটগুলো এখানে দেখা যাবে।</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    $(function() {
        // Activity Logs
        function fetch_activity_logs() {
            $('#activity-logs-content').text('Fetching...');
            $.getJSON(admin_url + 'bizbot/get_activity_logs', function(data) {
                if (data.success) {
                    $('#activity-logs-content').html(data.logs);
                    var pre = document.getElementById('activity-logs-content');
                    pre.scrollTop = pre.scrollHeight;
                }
            });
        }
        $('#refresh-activity-logs').on('click', fetch_activity_logs);
        $('#clear-activity-logs').on('click', function() {
            if (confirm('Clear activity logs?')) {
                $.post(admin_url + 'bizbot/clear_activity_logs', function() {
                    fetch_activity_logs();
                }, 'json');
            }
        });
        fetch_activity_logs();

        // Webhook Logs
        function fetch_webhook_logs() {
            $('#webhook-logs-content').text('Fetching logs...');
            $.getJSON(admin_url + 'bizbot/get_webhook_logs', function(data) {
                if (data.success) {
                    $('#webhook-logs-content').text(data.logs);
                    var pre = document.getElementById('webhook-logs-content');
                    pre.scrollTop = pre.scrollHeight;
                }
            });
        }

        $('#refresh-webhook-logs').on('click', function() {
            fetch_webhook_logs();
        });

        $('#clear-webhook-logs').on('click', function() {
            if (confirm('Clear all webhook logs?')) {
                $.post(admin_url + 'bizbot/clear_webhook_logs', function(data) {
                    fetch_webhook_logs();
                }, 'json');
            }
        });

        // Initial fetch
        fetch_webhook_logs();

        $('#test-connection-btn').on('click', function() {
            var $btn = $(this);
            var $result = $('#connection-result');

            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Testing...');
            $result.html('');

            $.ajax({
                url: admin_url + 'bizbot/test_connection',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.endpoints) {
                        var html = '<div class="mtop10"><strong>' + response.message + '</strong>';
                        html += '<table class="table table-condensed table-bordered mtop5" style="font-size:11px;">';
                        html += '<tr><th>Endpoint</th><th>Label</th><th>HTTP</th><th>Status</th><th>Response</th></tr>';
                        for (var i = 0; i < response.endpoints.length; i++) {
                            var e = response.endpoints[i];
                            var cls = e.status === 'OK' ? 'success' : 'danger';
                            var resp = (e.response || e.error || '-');
                            if (resp.length > 80) resp = resp.substring(0, 80) + '...';
                            html += '<tr class="' + cls + '"><td>' + e.endpoint + '</td><td>' + e.label + '</td><td>' + e.http_code + '</td><td>' + e.status + '</td><td>' + resp + '</td></tr>';
                        }
                        html += '</table></div>';
                        $result.html(html);
                    } else if (response.success) {
                        $result.html('<span class="text-success"><i class="fa fa-check"></i> ' + response.message + '</span>');
                    } else {
                        $result.html('<span class="text-danger"><i class="fa fa-times"></i> ' + response.message + '</span>');
                    }
                },
                error: function() {
                    $result.html('<span class="text-danger"><i class="fa fa-times"></i> Connection failed</span>');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i class="fa fa-plug"></i> Test Connection');
                }
            });
        });

        $('#bulk-sync-btn').on('click', function() {
            var $btn = $(this);
            var $result = $('#sync-result');
            var totalSynced = 0;

            if (!confirm('This will search Bizbot for existing threads for leads that don\'t have a link yet. Proceed?')) {
                return;
            }

            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Syncing...');
            $result.html('Starting sync...');

            function processBatch() {
                $.ajax({
                    url: admin_url + 'bizbot/bulk_sync_threads',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            totalSynced += response.count;
                            var debugInfo = '';
                            if (response.debug && response.debug.length > 0) {
                                var d = response.debug[0];
                                debugInfo = '<br><small>Lead Phone: ' + (d.lead_phone || d.phone || 'N/A') + ', API: ' + (d.api_status || 'N/A') + ', Found: ' + (d.found_count || 'N/A') + '</small>';
                                if (d.bizbot_phones && d.bizbot_phones.length > 0) {
                                    debugInfo += '<br><small>Bizbot Phones: ' + d.bizbot_phones.join(', ') + '</small>';
                                }
                                if (d.first_contact_keys && d.first_contact_keys.length > 0) {
                                    debugInfo += '<br><small>Contact Fields: ' + d.first_contact_keys.join(', ') + '</small>';
                                }
                                // Show match info if present
                                for (var j = 1; j < response.debug.length; j++) {
                                    var md = response.debug[j];
                                    if (md.type === 'match') {
                                        debugInfo += '<br><small class="text-success">Match: lead=' + md.lead_phone + ' bizbot=' + md.contact_phone + ' field=' + md.thread_field_used + '</small>';
                                    }
                                }
                            }

                            if (response.remaining > 0) {
                                $result.html('<span class="text-warning"><i class="fa fa-refresh fa-spin"></i> Synced ' + totalSynced + ' leads... Remaining: ' + response.remaining + '</span>' + debugInfo);
                                processBatch(); // Recursive call for next batch
                            } else {
                                $result.html('<span class="text-success"><i class="fa fa-check"></i> Finished! Total synced: ' + totalSynced + '</span>' + debugInfo);
                                $btn.prop('disabled', false).html('<i class="fa fa-refresh"></i> Bulk Sync Threads');
                            }
                        } else {
                            $result.html('<span class="text-danger"><i class="fa fa-times"></i> ' + response.message + '</span>');
                            $btn.prop('disabled', false).html('<i class="fa fa-refresh"></i> Bulk Sync Threads');
                        }
                    },
                    error: function() {
                        $result.html('<span class="text-danger"><i class="fa fa-times"></i> Sync failed.</span>');
                        $btn.prop('disabled', false).html('<i class="fa fa-refresh"></i> Bulk Sync Threads');
                    }
                });
            }

            processBatch();
        });
    });
</script>