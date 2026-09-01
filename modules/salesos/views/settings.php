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
                </div>

                <button type="submit" class="btn btn-primary"><?= _l('submit') ?></button>
            </form>
        </div>
    </div>

</div>
</div>
</div>
</div>

<?php init_tail(); ?>
