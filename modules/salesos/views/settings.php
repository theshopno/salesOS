<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <h4 class="tw-text-xl tw-font-bold tw-mb-4">SalesOS Settings</h4>

                <?php if (isset($config_mtime)): ?>
                <div class="alert alert-info tw-mb-4">
                    <i class="fa fa-file-code-o"></i>
                    Daemon config last regenerated: <strong><?= e($config_mtime) ?></strong>
                    <?php if (!$config_writable): ?>
                    &nbsp;<span class="label label-warning"><i class="fa fa-lock"></i> Not writable — check permissions on daemons/config.php</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="<?= admin_url('salesos/settings') ?>">
                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">

                    <!-- AMI -->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h4 class="panel-title">Asterisk AMI Connection</h4>
                        </div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>AMI Host</label>
                                        <input type="text" name="salesos_ami_host" class="form-control" value="<?= e($options['salesos_ami_host']) ?>" placeholder="103.42.4.210">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Port</label>
                                        <input type="number" name="salesos_ami_port" class="form-control" value="<?= e($options['salesos_ami_port']) ?>" placeholder="5038">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Username</label>
                                        <input type="text" name="salesos_ami_username" class="form-control" value="<?= e($options['salesos_ami_username']) ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Secret / Password</label>
                                        <input type="password" name="salesos_ami_secret" class="form-control" value="<?= e($options['salesos_ami_secret']) ?>">
                                    </div>
                                </div>
                                <div class="col-md-6 tw-flex tw-items-end tw-pb-4">
                                    <button type="button" class="btn btn-info btn-sm" id="test-ami">
                                        <i class="fa fa-plug"></i> Test AMI Connection
                                    </button>
                                    <span id="ami-test-result" class="tw-ml-3"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PBX Identity -->
                    <div class="panel panel-default">
                        <div class="panel-heading"><h4 class="panel-title">PBX Identity</h4></div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>PBX ID <small class="text-muted">(written to every stream event)</small></label>
                                        <input type="text" name="salesos_pbx_id" class="form-control" value="<?= e($options['salesos_pbx_id']) ?>" placeholder="pbx-01">
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label>PBX Name</label>
                                        <input type="text" name="salesos_pbx_name" class="form-control" value="<?= e($options['salesos_pbx_name']) ?>" placeholder="Main PBX">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PBX CDR Database -->
                    <div class="panel panel-default">
                        <div class="panel-heading"><h4 class="panel-title">PBX CDR Database</h4></div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label>DB Host</label>
                                        <input type="text" name="salesos_pbx_db_host" class="form-control" value="<?= e($options['salesos_pbx_db_host']) ?>">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Port</label>
                                        <input type="number" name="salesos_pbx_db_port" class="form-control" value="<?= e($options['salesos_pbx_db_port']) ?>">
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label>Database Name</label>
                                        <input type="text" name="salesos_pbx_db_name" class="form-control" value="<?= e($options['salesos_pbx_db_name']) ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Username</label>
                                        <input type="text" name="salesos_pbx_db_user" class="form-control" value="<?= e($options['salesos_pbx_db_user']) ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Password</label>
                                        <input type="password" name="salesos_pbx_db_password" class="form-control" value="<?= e($options['salesos_pbx_db_password']) ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>CDR Sync Batch Size</label>
                                        <input type="number" name="salesos_cdr_batch_size" class="form-control" value="<?= e($options['salesos_cdr_batch_size']) ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>CDR Auto Sync</label>
                                        <select name="salesos_cdr_sync_enabled" class="form-control">
                                            <option value="1" <?= $options['salesos_cdr_sync_enabled'] == '1' ? 'selected' : '' ?>>Enabled</option>
                                            <option value="0" <?= $options['salesos_cdr_sync_enabled'] == '0' ? 'selected' : '' ?>>Disabled</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Redis -->
                    <div class="panel panel-default">
                        <div class="panel-heading"><h4 class="panel-title">Redis</h4></div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label>Redis Host</label>
                                        <input type="text" name="salesos_redis_host" class="form-control" value="<?= e($options['salesos_redis_host']) ?>" placeholder="127.0.0.1">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Port</label>
                                        <input type="number" name="salesos_redis_port" class="form-control" value="<?= e($options['salesos_redis_port']) ?>" placeholder="6379">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Password <small class="text-muted">(leave blank if none)</small></label>
                                        <input type="password" name="salesos_redis_password" class="form-control" value="<?= e($options['salesos_redis_password'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Stream Max Length</label>
                                        <input type="number" name="salesos_stream_maxlen" class="form-control" value="<?= e($options['salesos_stream_maxlen']) ?>" placeholder="50000">
                                        <small class="text-muted">Approximate max entries per Redis Stream</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- WebSocket Server -->
                    <div class="panel panel-default">
                        <div class="panel-heading"><h4 class="panel-title">WebSocket Server</h4></div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Internal Port</label>
                                        <input type="number" name="salesos_ws_internal_port" class="form-control" value="<?= e($options['salesos_ws_internal_port']) ?>" placeholder="8080">
                                        <small class="text-muted">Ratchet listener port (behind Nginx)</small>
                                    </div>
                                </div>
                                <div class="col-md-9">
                                    <div class="form-group">
                                        <label>Public WebSocket URL</label>
                                        <input type="text" name="salesos_ws_url" class="form-control" value="<?= e($options['salesos_ws_url']) ?>" placeholder="wss://your-domain.com/salesos-ws">
                                        <small class="text-muted">Browser connects to this. Leave blank to use REST polling fallback.</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Outbound Calling -->
                    <div class="panel panel-default">
                        <div class="panel-heading"><h4 class="panel-title">Outbound Calling</h4></div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Trunk Endpoint Name</label>
                                        <input type="text" name="salesos_trunk_endpoint" class="form-control" value="<?= e($options['salesos_trunk_endpoint']) ?>" placeholder="endpoint-trunk-bdit">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Caller ID</label>
                                        <input type="text" name="salesos_caller_id" class="form-control" value="<?= e($options['salesos_caller_id']) ?>" placeholder="09649699699">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Outbound Prefix</label>
                                        <input type="text" name="salesos_outbound_prefix" class="form-control" value="<?= e($options['salesos_outbound_prefix']) ?>" placeholder="e.g. 9">
                                        <small class="text-muted">Prepended to dialed number</small>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Click-to-Call</label>
                                        <select name="salesos_click_to_call_enabled" class="form-control">
                                            <option value="0" <?= $options['salesos_click_to_call_enabled'] == '0' ? 'selected' : '' ?>>Direct to Trunk</option>
                                            <option value="1" <?= $options['salesos_click_to_call_enabled'] == '1' ? 'selected' : '' ?>>Ring Agent First</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recordings -->
                    <div class="panel panel-default">
                        <div class="panel-heading"><h4 class="panel-title">Recordings</h4></div>
                        <div class="panel-body">
                            <div class="form-group">
                                <label>Recordings Base URL <small class="text-muted">(public URL served by web server)</small></label>
                                <input type="text" name="salesos_recordings_url" class="form-control" value="<?= e($options['salesos_recordings_url']) ?>" placeholder="https://103.42.4.210/recordings">
                            </div>
                            <div class="form-group">
                                <label>Recordings Path <small class="text-muted">(server-side filesystem path)</small></label>
                                <input type="text" name="salesos_recordings_path" class="form-control" value="<?= e($options['salesos_recordings_path']) ?>" placeholder="/var/spool/asterisk/recording/">
                            </div>
                        </div>
                    </div>

                    <!-- Call Popup -->
                    <div class="panel panel-default">
                        <div class="panel-heading"><h4 class="panel-title">Call Popup</h4></div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Popup Enabled</label>
                                        <select name="salesos_popup_enabled" class="form-control">
                                            <option value="1" <?= $options['salesos_popup_enabled'] == '1' ? 'selected' : '' ?>>Yes</option>
                                            <option value="0" <?= $options['salesos_popup_enabled'] == '0' ? 'selected' : '' ?>>No</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Poll Interval (seconds)</label>
                                        <input type="number" name="salesos_poll_interval" class="form-control" value="<?= e($options['salesos_poll_interval']) ?>" min="2" max="60">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> Save Settings &amp; Regenerate Daemon Config
                    </button>
                    &nbsp;
                    <a href="<?= admin_url('salesos/api/health') ?>" target="_blank" class="btn btn-default">
                        <i class="fa fa-heartbeat"></i> Health Check
                    </a>
                </form>

            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
document.getElementById('test-ami').addEventListener('click', function() {
    var btn    = this;
    var result = document.getElementById('ami-test-result');
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Testing...';
    result.innerHTML = '';

    fetch('<?= admin_url('salesos/api/test_ami') ?>', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded'},
        body: '<?= $this->security->get_csrf_token_name() ?>=<?= $this->security->get_csrf_hash() ?>'
    }).then(function(r) { return r.json(); }).then(function(d) {
        btn.innerHTML = '<i class="fa fa-plug"></i> Test AMI Connection';
        result.innerHTML = d.success
            ? '<span class="label label-success"><i class="fa fa-check"></i> ' + d.message + '</span>'
            : '<span class="label label-danger"><i class="fa fa-times"></i> ' + d.error + '</span>';
    }).catch(function() {
        btn.innerHTML = '<i class="fa fa-plug"></i> Test AMI Connection';
        result.innerHTML = '<span class="label label-danger">Request failed</span>';
    });
});
</script>
</content>
