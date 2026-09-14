<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
<div class="content">
<div class="row">
<div class="col-md-8 col-md-offset-2">

    <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
        <h4 class="tw-font-bold tw-mt-0 tw-mb-0 tw-text-neutral-800"><?= _l('pbxpilot_settings_title') ?></h4>
        <a href="<?= admin_url('pbxpilot/agents') ?>" class="btn btn-default btn-sm">
            <i class="fa-solid fa-users"></i> <?= _l('pbxpilot_agents_title') ?>
        </a>
    </div>

    <div class="panel_s">
        <div class="panel-body tw-p-0">
            <ul class="nav nav-tabs" role="tablist">
                <li role="presentation" class="active">
                    <a href="#features" aria-controls="features" role="tab" data-toggle="tab">
                        <i class="fa-solid fa-sliders tw-mr-1"></i> <?= _l('pbxpilot_features_tab') ?>
                    </a>
                </li>
                <li role="presentation">
                    <a href="#connection" aria-controls="connection" role="tab" data-toggle="tab">
                        <i class="fa-solid fa-server tw-mr-1"></i> <?= _l('pbxpilot_connection_tab') ?>
                    </a>
                </li>
            </ul>

            <form method="POST" action="<?= admin_url('pbxpilot/settings') ?>">
                <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">

                <div class="tab-content tw-p-6">
                <div role="tabpanel" class="tab-pane active" id="features">

                    <p class="text-muted"><?= _l('pbxpilot_features_intro') ?></p>

                    <h4 class="tw-mb-3"><i class="fa-solid fa-phone tw-mr-1 tw-text-neutral-400"></i> <?= _l('pbxpilot_section_telephony') ?></h4>

                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" name="pbxpilot_core_telephony" id="pbxpilot_core_telephony" <?= $pbxpilot_core_telephony == '1' ? 'checked' : '' ?>>
                        <label for="pbxpilot_core_telephony">
                            <?= _l('pbxpilot_core_telephony') ?>
                            <i class="fa-regular fa-circle-question tw-ml-1 tw-text-neutral-400" data-toggle="tooltip" data-title="<?= _l('pbxpilot_core_telephony_tooltip', '', false) ?>"></i>
                        </label>
                    </div>

                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" name="pbxpilot_channel_browser" id="pbxpilot_channel_browser" <?= $pbxpilot_channel_browser == '1' ? 'checked' : '' ?>>
                        <label for="pbxpilot_channel_browser">
                            <?= _l('pbxpilot_channel_browser') ?>
                            <i class="fa-regular fa-circle-question tw-ml-1 tw-text-neutral-400" data-toggle="tooltip" data-title="<?= _l('pbxpilot_channel_browser_tooltip', '', false) ?>"></i>
                        </label>
                    </div>

                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" name="pbxpilot_channel_desktop" id="pbxpilot_channel_desktop" <?= $pbxpilot_channel_desktop == '1' ? 'checked' : '' ?>>
                        <label for="pbxpilot_channel_desktop">
                            <?= _l('pbxpilot_channel_desktop') ?>
                            <i class="fa-regular fa-circle-question tw-ml-1 tw-text-neutral-400" data-toggle="tooltip" data-title="<?= _l('pbxpilot_channel_desktop_tooltip', '', false) ?>"></i>
                        </label>
                    </div>

                    <hr>
                    <h4 class="tw-mb-3"><i class="fa-solid fa-robot tw-mr-1 tw-text-neutral-400"></i> <?= _l('pbxpilot_section_ai') ?></h4>

                    <div class="form-group">
                        <label for="pbxpilot_ai_intelligence_mode"><?= _l('pbxpilot_ai_mode') ?>
                            <i class="fa-regular fa-circle-question tw-ml-1 tw-text-neutral-400" data-toggle="tooltip" data-title="<?= _l('pbxpilot_ai_mode_tooltip', '', false) ?>"></i>
                        </label>
                        <select name="pbxpilot_ai_intelligence_mode" id="pbxpilot_ai_intelligence_mode" class="form-control selectpicker">
                            <?php $ai_modes = [
                                'off'          => _l('pbxpilot_ai_mode_off'),
                                'manual'       => _l('pbxpilot_ai_mode_manual'),
                                'auto_flagged' => _l('pbxpilot_ai_mode_auto_flagged'),
                                'full_auto'    => _l('pbxpilot_ai_mode_full_auto'),
                            ]; ?>
                            <?php foreach ($ai_modes as $val => $label): ?>
                            <option value="<?= $val ?>" <?= $pbxpilot_ai_intelligence_mode === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="pbxpilot_ai_model"><?= _l('pbxpilot_ai_model') ?></label>
                        <input type="text" name="pbxpilot_ai_model" id="pbxpilot_ai_model" class="form-control" value="<?= e($pbxpilot_ai_model) ?>" placeholder="<?= _l('pbxpilot_ai_model_placeholder', '', false) ?>">
                    </div>

                    <hr>
                    <h4 class="tw-mb-3"><i class="fa-solid fa-chart-line tw-mr-1 tw-text-neutral-400"></i> <?= _l('pbxpilot_section_reporting') ?></h4>

                    <div class="form-group">
                        <label for="pbxpilot_effective_call_seconds"><?= _l('pbxpilot_effective_call_seconds') ?>
                            <i class="fa-regular fa-circle-question tw-ml-1 tw-text-neutral-400" data-toggle="tooltip" data-title="<?= _l('pbxpilot_effective_call_seconds_tooltip', '', false) ?>"></i>
                        </label>
                        <input type="number" min="0" name="pbxpilot_effective_call_seconds" id="pbxpilot_effective_call_seconds" class="form-control" style="max-width:150px" value="<?= e($pbxpilot_effective_call_seconds) ?>">
                    </div>

                    <hr>
                    <h4 class="tw-mb-1"><i class="fa-solid fa-briefcase tw-mr-1 tw-text-neutral-400"></i> <?= _l('pbxpilot_section_agency') ?></h4>
                    <p class="text-muted tw-mb-3"><?= _l('pbxpilot_section_agency_note') ?></p>

                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" name="pbxpilot_agency_pack" id="pbxpilot_agency_pack" <?= $pbxpilot_agency_pack == '1' ? 'checked' : '' ?>>
                        <label for="pbxpilot_agency_pack">
                            <?= _l('pbxpilot_agency_pack') ?>
                            <i class="fa-regular fa-circle-question tw-ml-1 tw-text-neutral-400" data-toggle="tooltip" data-title="<?= _l('pbxpilot_agency_pack_tooltip', '', false) ?>"></i>
                        </label>
                    </div>

                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" name="pbxpilot_agency_bizbot_messaging" id="pbxpilot_agency_bizbot_messaging" <?= $pbxpilot_agency_bizbot_messaging == '1' ? 'checked' : '' ?>>
                        <label for="pbxpilot_agency_bizbot_messaging">
                            <?= _l('pbxpilot_agency_bizbot_messaging') ?>
                            <i class="fa-regular fa-circle-question tw-ml-1 tw-text-neutral-400" data-toggle="tooltip" data-title="<?= _l('pbxpilot_agency_bizbot_messaging_tooltip', '', false) ?>"></i>
                        </label>
                    </div>

                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" name="pbxpilot_agency_bizbot_provisioning" id="pbxpilot_agency_bizbot_provisioning" <?= $pbxpilot_agency_bizbot_provisioning == '1' ? 'checked' : '' ?>>
                        <label for="pbxpilot_agency_bizbot_provisioning">
                            <?= _l('pbxpilot_agency_bizbot_provisioning') ?>
                            <i class="fa-regular fa-circle-question tw-ml-1 tw-text-neutral-400" data-toggle="tooltip" data-title="<?= _l('pbxpilot_agency_bizbot_provisioning_tooltip', '', false) ?>"></i>
                        </label>
                    </div>

                    <hr>
                    <h4 class="tw-mb-3"><i class="fa-solid fa-phone-volume tw-mr-1 tw-text-neutral-400"></i> <?= _l('pbxpilot_section_escalation') ?></h4>

                    <?php if (!$escalation_gate['allowed']): ?>
                    <div class="alert alert-warning tw-flex tw-items-start tw-gap-2">
                        <i class="fa-solid fa-lock tw-mt-0.5"></i>
                        <span><strong><?= _l('pbxpilot_escalation_locked') ?>.</strong> <?= e($escalation_gate['reason']) ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" name="pbxpilot_voice_escalation" id="pbxpilot_voice_escalation"
                            <?= $pbxpilot_voice_escalation == '1' ? 'checked' : '' ?>
                            <?= !$escalation_gate['allowed'] ? 'disabled' : '' ?>>
                        <label for="pbxpilot_voice_escalation">
                            <?= _l('pbxpilot_voice_escalation') ?>
                            <i class="fa-regular fa-circle-question tw-ml-1 tw-text-neutral-400" data-toggle="tooltip" data-title="<?= _l('pbxpilot_voice_escalation_tooltip', '', false) ?>"></i>
                        </label>
                    </div>

                </div>
                <div role="tabpanel" class="tab-pane" id="connection">

                    <p class="text-muted"><?= _l('pbxpilot_connection_intro') ?></p>

                    <h4 class="tw-mb-3"><i class="fa-solid fa-plug tw-mr-1 tw-text-neutral-400"></i> <?= _l('pbxpilot_section_ami') ?></h4>
                    <div class="row">
                        <div class="col-md-8 form-group">
                            <label for="pbxpilot_ami_host"><?= _l('pbxpilot_ami_host') ?></label>
                            <input type="text" name="pbxpilot_ami_host" id="pbxpilot_ami_host" class="form-control" value="<?= e($pbxpilot_ami_host) ?>" placeholder="e.g. 192.168.1.10">
                        </div>
                        <div class="col-md-4 form-group">
                            <label for="pbxpilot_ami_port"><?= _l('pbxpilot_ami_port') ?></label>
                            <input type="number" name="pbxpilot_ami_port" id="pbxpilot_ami_port" class="form-control" value="<?= e($pbxpilot_ami_port) ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label for="pbxpilot_ami_username"><?= _l('pbxpilot_ami_username') ?></label>
                            <input type="text" name="pbxpilot_ami_username" id="pbxpilot_ami_username" class="form-control" value="<?= e($pbxpilot_ami_username) ?>">
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="pbxpilot_ami_secret"><?= _l('pbxpilot_ami_secret') ?></label>
                            <input type="password" name="pbxpilot_ami_secret" id="pbxpilot_ami_secret" class="form-control" value="<?= e($pbxpilot_ami_secret) ?>" autocomplete="new-password">
                        </div>
                    </div>

                    <button type="button" class="btn btn-default btn-sm" id="btn-test-ami">
                        <i class="fa-solid fa-plug"></i> <?= _l('pbxpilot_test_connection') ?>
                    </button>
                    <span id="ami-test-result" class="tw-ml-2"></span>

                    <hr>
                    <h4 class="tw-mb-3"><i class="fa-solid fa-database tw-mr-1 tw-text-neutral-400"></i> <?= _l('pbxpilot_section_cdr') ?></h4>
                    <div class="row">
                        <div class="col-md-8 form-group">
                            <label for="pbxpilot_cdr_db_host"><?= _l('pbxpilot_cdr_db_host') ?></label>
                            <input type="text" name="pbxpilot_cdr_db_host" id="pbxpilot_cdr_db_host" class="form-control" value="<?= e($pbxpilot_cdr_db_host) ?>">
                        </div>
                        <div class="col-md-4 form-group">
                            <label for="pbxpilot_cdr_db_port"><?= _l('pbxpilot_cdr_db_port') ?></label>
                            <input type="number" name="pbxpilot_cdr_db_port" id="pbxpilot_cdr_db_port" class="form-control" value="<?= e($pbxpilot_cdr_db_port) ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label for="pbxpilot_cdr_db_name"><?= _l('pbxpilot_cdr_db_name') ?></label>
                            <input type="text" name="pbxpilot_cdr_db_name" id="pbxpilot_cdr_db_name" class="form-control" value="<?= e($pbxpilot_cdr_db_name) ?>">
                        </div>
                        <div class="col-md-4 form-group">
                            <label for="pbxpilot_cdr_db_user"><?= _l('pbxpilot_cdr_db_user') ?></label>
                            <input type="text" name="pbxpilot_cdr_db_user" id="pbxpilot_cdr_db_user" class="form-control" value="<?= e($pbxpilot_cdr_db_user) ?>">
                        </div>
                        <div class="col-md-4 form-group">
                            <label for="pbxpilot_cdr_db_password"><?= _l('pbxpilot_cdr_db_password') ?></label>
                            <input type="password" name="pbxpilot_cdr_db_password" id="pbxpilot_cdr_db_password" class="form-control" value="<?= e($pbxpilot_cdr_db_password) ?>" autocomplete="new-password">
                        </div>
                    </div>

                    <button type="button" class="btn btn-default btn-sm" id="btn-sync-cdr">
                        <i class="fa-solid fa-arrows-rotate"></i> <?= _l('pbxpilot_sync_cdr_now') ?>
                    </button>
                    <span id="cdr-sync-result" class="tw-ml-2"></span>

                    <hr>
                    <h4 class="tw-mb-3"><i class="fa-solid fa-circle-play tw-mr-1 tw-text-neutral-400"></i> <?= _l('pbxpilot_section_recordings') ?></h4>
                    <div class="form-group">
                        <label for="pbxpilot_recordings_url"><?= _l('pbxpilot_recordings_url') ?>
                            <i class="fa-regular fa-circle-question tw-ml-1 tw-text-neutral-400" data-toggle="tooltip" data-title="<?= _l('pbxpilot_recordings_url_tooltip', '', false) ?>"></i>
                        </label>
                        <input type="text" name="pbxpilot_recordings_url" id="pbxpilot_recordings_url" class="form-control" value="<?= e($pbxpilot_recordings_url) ?>" placeholder="http://127.0.0.1:18088/static/recordings/">
                    </div>
                    <div class="form-group">
                        <label for="pbxpilot_recordings_monitor_dir"><?= _l('pbxpilot_recordings_monitor_dir') ?>
                            <i class="fa-regular fa-circle-question tw-ml-1 tw-text-neutral-400" data-toggle="tooltip" data-title="<?= _l('pbxpilot_recordings_monitor_dir_tooltip', '', false) ?>"></i>
                        </label>
                        <input type="text" name="pbxpilot_recordings_monitor_dir" id="pbxpilot_recordings_monitor_dir" class="form-control" value="<?= e($pbxpilot_recordings_monitor_dir) ?>" placeholder="/var/spool/asterisk/monitor">
                    </div>
                    <div class="form-group">
                        <label for="pbxpilot_recording_retention_days"><?= _l('pbxpilot_recording_retention_days') ?>
                            <i class="fa-regular fa-circle-question tw-ml-1 tw-text-neutral-400" data-toggle="tooltip" data-title="<?= _l('pbxpilot_recording_retention_days_tooltip', '', false) ?>"></i>
                        </label>
                        <input type="number" min="0" name="pbxpilot_recording_retention_days" id="pbxpilot_recording_retention_days" class="form-control" style="max-width:150px" value="<?= e($pbxpilot_recording_retention_days) ?>">
                    </div>

                </div>
                </div>

                <div class="panel-footer text-right">
                    <button type="submit" class="btn btn-primary"><?= _l('submit') ?></button>
                </div>
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
    result.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <?= _l('pbxpilot_testing', '', false) ?>';

    fetch('<?= admin_url('pbxpilot/settings/test_ami') ?>', {
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
            ? '<span class="text-success"><i class="fa-solid fa-circle-check"></i> ' + data.message + '</span>'
            : '<span class="text-danger"><i class="fa-solid fa-circle-xmark"></i> ' + data.message + '</span>';
    })
    .catch(function () {
        result.innerHTML = '<span class="text-danger"><?= _l('pbxpilot_request_failed', '', false) ?></span>';
    })
    .finally(function () { btn.disabled = false; });
});

document.getElementById('btn-sync-cdr').addEventListener('click', function () {
    var btn = this;
    var result = document.getElementById('cdr-sync-result');
    btn.disabled = true;
    result.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <?= _l('pbxpilot_syncing', '', false) ?>';

    fetch('<?= admin_url('pbxpilot/settings/sync_cdr') ?>', {
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
            ? '<span class="text-success"><i class="fa-solid fa-circle-check"></i> ' + '<?= _l('pbxpilot_sync_result', '', false) ?>'.replace('{count}', data.synced) + '</span>'
            : '<span class="text-danger"><i class="fa-solid fa-circle-xmark"></i> ' + (data.error || '<?= _l('pbxpilot_sync_failed', '', false) ?>') + '</span>';
    })
    .catch(function () {
        result.innerHTML = '<span class="text-danger"><?= _l('pbxpilot_request_failed', '', false) ?></span>';
    })
    .finally(function () { btn.disabled = false; });
});

// Deferred to window "load" (fires after every script, wherever init_tail()
// places jQuery in the document) and guarded, so a jQuery-load-order issue
// can only break tooltips, never the click handlers above — those are
// plain JS and always bind regardless of jQuery's state.
window.addEventListener('load', function () {
    if (typeof $ !== 'undefined') {
        $('[data-toggle="tooltip"]').tooltip();
    }
});
</script>

<?php init_tail(); ?>
