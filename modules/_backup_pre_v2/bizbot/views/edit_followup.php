<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?php echo $title; ?></h4>
                        <hr class="hr-panel-heading" />

                        <?php echo form_open(admin_url('bizbot/edit_followup/' . $followup->id)); ?>

                        <?php
                        $name = isset($followup->name) ? $followup->name : '';
                        echo render_input('name', 'Step Name', $name, 'text', ['placeholder' => 'e.g. Welcome Message']);
                        ?>

                        <div class="row">
                            <div class="col-md-4">
                                <?php echo render_input('step_number', 'Order', $followup->step_number, 'number', ['min' => 1, 'required' => true]); ?>
                            </div>
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label class="control-label">Delay Time</label>
                                    <div class="input-group">
                                        <?php
                                        $delay_val = isset($followup->delay_value) ? $followup->delay_value : ($followup->delay_hours ?? 1);
                                        $delay_unit = isset($followup->delay_unit) ? $followup->delay_unit : 'hours';
                                        ?>
                                        <input type="number" name="delay_value" class="form-control" min="1" value="<?php echo $delay_val; ?>" required>
                                        <select name="delay_unit" class="form-control" style="width:auto;">
                                            <option value="minutes" <?php echo $delay_unit == 'minutes' ? 'selected' : ''; ?>>Minutes</option>
                                            <option value="hours" <?php echo $delay_unit == 'hours' ? 'selected' : ''; ?>>Hours</option>
                                            <option value="days" <?php echo $delay_unit == 'days' ? 'selected' : ''; ?>>Days</option>
                                            <option value="weeks" <?php echo $delay_unit == 'weeks' ? 'selected' : ''; ?>>Weeks</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php echo render_textarea('message', 'Message Content', $followup->message, ['rows' => 6, 'required' => true]); ?>
                        <p class="text-info small">Placeholders: {lead_id}, {lead_name}, {lead_email}, {lead_phone}, {lead_phonenumber}, {company}, {lead_status}, {lead_assignee}, {lead_last_note}, {company_name}</p>

                        <hr>
                        <h5>Conditions & Limits</h5>

                        <?php
                        $status_id = isset($followup->status_id) ? $followup->status_id : '';
                        echo render_select('status_id', $statuses, ['id', 'name'], 'Send only if Lead Status is', $status_id);
                        ?>
                        <p class="text-muted small">Leave blank to send regardless of status.</p>

                        <?php
                        $blacklist = [];
                        if (!empty($followup->blacklist_statuses)) {
                            $blacklist = json_decode($followup->blacklist_statuses, true) ?: [];
                        }
                        echo render_select('blacklist_statuses[]', $statuses, ['id', 'name'], 'Do NOT send if Status is', $blacklist, ['multiple' => true, 'data-actions-box' => true]);
                        ?>
                        <p class="text-muted small">Exclude leads with these statuses.</p>

                        <?php
                        $max_per_lead = isset($followup->max_per_lead) ? $followup->max_per_lead : 0;
                        echo render_input('max_per_lead', 'Max Messages Per Lead', $max_per_lead, 'number', ['min' => 0]);
                        ?>
                        <p class="text-muted small">0 = Unlimited</p>

                        <div class="checkbox checkbox-primary">
                            <?php $stop = isset($followup->stop_on_status_change) ? $followup->stop_on_status_change : 0; ?>
                            <input type="checkbox" name="stop_on_status_change" id="stop_on_status_change" value="1" <?php echo $stop ? 'checked' : ''; ?>>
                            <label for="stop_on_status_change">Stop if Lead status changes</label>
                        </div>

                        <hr>

                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" name="active" id="active" value="1" <?php echo $followup->active ? 'checked' : ''; ?>>
                            <label for="active">Active</label>
                        </div>

                        <hr>
                        <a href="<?php echo admin_url('bizbot/followups'); ?>" class="btn btn-default">Cancel</a>
                        <button type="submit" class="btn btn-info pull-right">Update Follow-up</button>
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>