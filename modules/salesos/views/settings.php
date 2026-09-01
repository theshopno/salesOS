<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
<div class="content">
<div class="row">
<div class="col-md-8 col-md-offset-2">

    <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
        <h4 class="tw-text-xl tw-font-bold tw-mb-0">SalesOS Settings</h4>
    </div>

    <div class="panel_s">
        <div class="panel-body">
            <ul class="nav nav-tabs" role="tablist">
                <li role="presentation" class="active">
                    <a href="#features" aria-controls="features" role="tab" data-toggle="tab">
                        <?= _l('salesos_features_tab') ?: 'Features' ?>
                    </a>
                </li>
                <li role="presentation">
                    <a href="#connection" aria-controls="connection" role="tab" data-toggle="tab">
                        <?= _l('salesos_connection_tab') ?: 'Connection' ?>
                    </a>
                </li>
            </ul>

            <form method="POST" action="<?= admin_url('salesos/settings') ?>">
                <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">

                <div class="tab-content tw-mt-4">
                <div role="tabpanel" class="tab-pane active" id="features">

                    <p class="text-muted">
                        Flat toggles + mode dropdowns, no nested config UI (§6). These flags
                        aren't wired to any feature yet — Phase 1 is what starts reading them.
                    </p>

                    <h4>Core Telephony</h4>

                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" name="salesos_core_telephony" id="salesos_core_telephony" <?= $salesos_core_telephony == '1' ? 'checked' : '' ?>>
                        <label for="salesos_core_telephony">
                            Core Telephony
                            <br><small class="text-muted">Administratively always-on — must still fail soft if Asterisk/AMI is briefly unreachable; non-telephony CRM pages must never hard-error because of this.</small>
                        </label>
                    </div>

                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" name="salesos_channel_browser" id="salesos_channel_browser" <?= $salesos_channel_browser == '1' ? 'checked' : '' ?>>
                        <label for="salesos_channel_browser">Browser Channel (WebRTC)</label>
                    </div>

                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" name="salesos_channel_desktop" id="salesos_channel_desktop" <?= $salesos_channel_desktop == '1' ? 'checked' : '' ?>>
                        <label for="salesos_channel_desktop">Desktop Channel (MicroSIP trigger)</label>
                    </div>

                    <hr>
                    <h4>AI Call Intelligence</h4>

                    <div class="form-group">
                        <label for="salesos_ai_intelligence_mode">Mode</label>
                        <select name="salesos_ai_intelligence_mode" id="salesos_ai_intelligence_mode" class="form-control selectpicker">
                            <?php foreach (['off' => 'Off', 'manual' => 'Manual (Analyze button)', 'auto_flagged' => 'Auto — flagged calls only', 'full_auto' => 'Full auto'] as $val => $label): ?>
                            <option value="<?= $val ?>" <?= $salesos_ai_intelligence_mode === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="salesos_ai_model">AI Model</label>
                        <input type="text" name="salesos_ai_model" id="salesos_ai_model" class="form-control" value="<?= e($salesos_ai_model) ?>" placeholder="Provider-agnostic — left blank until Phase 2 wires a model selector">
                    </div>

                    <hr>
                    <h4>Agency Pack <small class="text-muted">(OFF in MVP → ON in v1.1)</small></h4>

                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" name="salesos_agency_pack" id="salesos_agency_pack" <?= $salesos_agency_pack == '1' ? 'checked' : '' ?>>
                        <label for="salesos_agency_pack">Agency Pack <small class="text-muted">— master switch for Call List / follow-ups / reports</small></label>
                    </div>

                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" name="salesos_agency_bizbot_messaging" id="salesos_agency_bizbot_messaging" <?= $salesos_agency_bizbot_messaging == '1' ? 'checked' : '' ?>>
                        <label for="salesos_agency_bizbot_messaging">BizBot Lead Messaging <small class="text-muted">— independent of ecomcore's ordernotifier</small></label>
                    </div>

                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" name="salesos_agency_bizbot_provisioning" id="salesos_agency_bizbot_provisioning" <?= $salesos_agency_bizbot_provisioning == '1' ? 'checked' : '' ?>>
                        <label for="salesos_agency_bizbot_provisioning">BizBot Client Provisioning <small class="text-muted">— /user CRUD</small></label>
                    </div>

                    <hr>
                    <h4>Voice Escalation <small class="text-muted">(hard-gated)</small></h4>

                    <?php if (!$escalation_gate['allowed']): ?>
                    <div class="alert alert-warning">
                        <i class="fa fa-lock"></i> <?= e($escalation_gate['reason']) ?>
                    </div>
                    <?php endif; ?>

                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" name="salesos_voice_escalation" id="salesos_voice_escalation"
                            <?= $salesos_voice_escalation == '1' ? 'checked' : '' ?>
                            <?= !$escalation_gate['allowed'] ? 'disabled' : '' ?>>
                        <label for="salesos_voice_escalation">
                            Voice Escalation Bridge
                            <br><small class="text-muted">Listens to ecomcore order hooks, escalates unconfirmed orders to IVR/agent queue. Cannot be turned on until ecomcore Phase 1 + Phase 9 are Done (§2, §6).</small>
                        </label>
                    </div>

                </div>
                <div role="tabpanel" class="tab-pane" id="connection">

                    <p class="text-muted">
                        Which PBX salesos talks to — settings-driven per §8a, not
                        hardcoded. Get these values from <code>provision_pbx.sh</code>'s
                        output file after provisioning a PBX.
                    </p>

                    <h4>AMI</h4>
                    <div class="row">
                        <div class="col-md-8 form-group">
                            <label for="salesos_ami_host">Host</label>
                            <input type="text" name="salesos_ami_host" id="salesos_ami_host" class="form-control" value="<?= e($salesos_ami_host) ?>" placeholder="e.g. 100.85.86.93">
                        </div>
                        <div class="col-md-4 form-group">
                            <label for="salesos_ami_port">Port</label>
                            <input type="number" name="salesos_ami_port" id="salesos_ami_port" class="form-control" value="<?= e($salesos_ami_port) ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label for="salesos_ami_username">Username</label>
                            <input type="text" name="salesos_ami_username" id="salesos_ami_username" class="form-control" value="<?= e($salesos_ami_username) ?>">
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="salesos_ami_secret">Secret</label>
                            <input type="password" name="salesos_ami_secret" id="salesos_ami_secret" class="form-control" value="<?= e($salesos_ami_secret) ?>" autocomplete="new-password">
                        </div>
                    </div>

                    <button type="button" class="btn btn-default btn-sm" id="btn-test-ami">
                        <i class="fa fa-plug"></i> Test Connection
                    </button>
                    <span id="ami-test-result" class="tw-ml-2"></span>

                    <hr>
                    <h4>CDR Database</h4>
                    <div class="row">
                        <div class="col-md-8 form-group">
                            <label for="salesos_cdr_db_host">Host</label>
                            <input type="text" name="salesos_cdr_db_host" id="salesos_cdr_db_host" class="form-control" value="<?= e($salesos_cdr_db_host) ?>">
                        </div>
                        <div class="col-md-4 form-group">
                            <label for="salesos_cdr_db_port">Port</label>
                            <input type="number" name="salesos_cdr_db_port" id="salesos_cdr_db_port" class="form-control" value="<?= e($salesos_cdr_db_port) ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label for="salesos_cdr_db_name">Database</label>
                            <input type="text" name="salesos_cdr_db_name" id="salesos_cdr_db_name" class="form-control" value="<?= e($salesos_cdr_db_name) ?>">
                        </div>
                        <div class="col-md-4 form-group">
                            <label for="salesos_cdr_db_user">User</label>
                            <input type="text" name="salesos_cdr_db_user" id="salesos_cdr_db_user" class="form-control" value="<?= e($salesos_cdr_db_user) ?>">
                        </div>
                        <div class="col-md-4 form-group">
                            <label for="salesos_cdr_db_password">Password</label>
                            <input type="password" name="salesos_cdr_db_password" id="salesos_cdr_db_password" class="form-control" value="<?= e($salesos_cdr_db_password) ?>" autocomplete="new-password">
                        </div>
                    </div>

                    <button type="button" class="btn btn-default btn-sm" id="btn-sync-cdr">
                        <i class="fa fa-refresh"></i> Sync CDR Now
                    </button>
                    <span id="cdr-sync-result" class="tw-ml-2"></span>

                </div>
                </div>

                <button type="submit" class="btn btn-primary"><?= _l('submit') ?></button>
            </form>
        </div>
    </div>

</div>
</div>
</div>
</div>

<script>
document.getElementById('btn-test-ami').addEventListener('click', function () {
    var btn = this;
    var result = document.getElementById('ami-test-result');
    btn.disabled = true;
    result.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Testing…';

    fetch('<?= admin_url('salesos/settings/test_ami') ?>', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: '<?= $this->security->get_csrf_token_name() ?>=<?= $this->security->get_csrf_hash() ?>',
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        result.innerHTML = data.ok
            ? '<span class="text-success"><i class="fa fa-check-circle"></i> ' + data.message + '</span>'
            : '<span class="text-danger"><i class="fa fa-times-circle"></i> ' + data.message + '</span>';
    })
    .catch(function () {
        result.innerHTML = '<span class="text-danger">Request failed.</span>';
    })
    .finally(function () { btn.disabled = false; });
});

document.getElementById('btn-sync-cdr').addEventListener('click', function () {
    var btn = this;
    var result = document.getElementById('cdr-sync-result');
    btn.disabled = true;
    result.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Syncing…';

    fetch('<?= admin_url('salesos/settings/sync_cdr') ?>', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: '<?= $this->security->get_csrf_token_name() ?>=<?= $this->security->get_csrf_hash() ?>',
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        result.innerHTML = data.ok
            ? '<span class="text-success"><i class="fa fa-check-circle"></i> Synced ' + data.synced + ' call(s).</span>'
            : '<span class="text-danger"><i class="fa fa-times-circle"></i> ' + (data.error || 'Sync failed') + '</span>';
    })
    .catch(function () {
        result.innerHTML = '<span class="text-danger">Request failed.</span>';
    })
    .finally(function () { btn.disabled = false; });
});
</script>

<?php init_tail(); ?>
