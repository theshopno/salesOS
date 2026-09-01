<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?php echo $title; ?></h4>
                        <hr class="hr-panel-heading" />

                        <div class="row">
                            <div class="col-md-5">
                                <div class="panel_s">
                                    <div class="panel-body bg-light">
                                        <h4 class="no-margin">Add New Follow-up Step</h4>
                                        <hr class="hr-panel-heading" />
                                        <?php echo form_open(admin_url('bizbot/followups')); ?>

                                        <?php echo render_input('name', 'Step Name', '', 'text', ['placeholder' => 'e.g. Welcome Message']); ?>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <?php echo render_input('step_number', 'Order', '1', 'number', ['min' => 1, 'required' => true]); ?>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="control-label">Delay Time</label>
                                                    <div class="input-group">
                                                        <input type="number" name="delay_value" class="form-control" min="1" value="1" required>
                                                        <select name="delay_unit" class="form-control" style="width:auto;">
                                                            <option value="minutes">Minutes</option>
                                                            <option value="hours" selected>Hours</option>
                                                            <option value="days">Days</option>
                                                            <option value="weeks">Weeks</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <?php echo render_textarea('message', 'Message Content', '', ['rows' => 5, 'required' => true]); ?>
                                        <p class="text-info small">Placeholders: {lead_name}, {lead_email}, {lead_status}, {company_name}</p>

                                        <hr>
                                        <h5>Conditions & Limits</h5>

                                        <?php echo render_select('status_id', $statuses, ['id', 'name'], 'Send only if Lead Status is'); ?>
                                        <p class="text-muted small">Leave blank to send regardless of status.</p>

                                        <?php echo render_select('blacklist_statuses[]', $statuses, ['id', 'name'], 'Do NOT send if Status is', [], ['multiple' => true, 'data-actions-box' => true]); ?>
                                        <p class="text-muted small">Exclude leads with these statuses.</p>

                                        <?php echo render_input('max_per_lead', 'Max Messages Per Lead', '0', 'number', ['min' => 0]); ?>
                                        <p class="text-muted small">0 = Unlimited</p>

                                        <div class="checkbox checkbox-primary">
                                            <input type="checkbox" name="stop_on_status_change" id="stop_on_status_change" value="1">
                                            <label for="stop_on_status_change">Stop if Lead status changes</label>
                                        </div>

                                        <hr>
                                        <button type="submit" class="btn btn-info pull-right">Add Step</button>
                                        <?php echo form_close(); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-7">
                                <h4>Existing Follow-up Sequence</h4>
                                <div class="table-responsive">
                                    <table class="table dt-table" data-order-col="1" data-order-type="asc">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Order</th>
                                                <th>Delay</th>
                                                <th>Target Status</th>
                                                <th>Max</th>
                                                <th>Active</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ((array)($followups ?? []) as $f) { ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars((string)($f['name'] ?? 'Step ' . $f['step_number'])); ?></strong>
                                                        <div class="text-muted small" style="max-width:200px;">
                                                            <?php
                                                            $msg_preview = strip_tags((string)($f['message'] ?? ''));
                                                            echo htmlspecialchars(strlen($msg_preview) > 50 ? substr($msg_preview, 0, 50) . '...' : $msg_preview);
                                                            ?>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars((string)($f['step_number'] ?? '')); ?></td>
                                                    <td>
                                                        <?php
                                                        $delay_val = $f['delay_value'] ?? ($f['delay_hours'] ?? 1);
                                                        $delay_unit = $f['delay_unit'] ?? 'hours';
                                                        echo $delay_val . ' ' . ucfirst($delay_unit);
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        if (!empty($f['status_id'])) {
                                                            foreach ((array)($statuses ?? []) as $s) {
                                                                $s = (object)$s;
                                                                if ($s->id == $f['status_id']) {
                                                                    echo '<span class="label label-info">' . htmlspecialchars((string)$s->name) . '</span>';
                                                                    break;
                                                                }
                                                            }
                                                        } else {
                                                            echo '<span class="text-muted">Any</span>';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $max = $f['max_per_lead'] ?? 0;
                                                        echo $max > 0 ? $max : '<span class="text-muted">∞</span>';
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($f['active'])) { ?>
                                                            <span class="label label-success">On</span>
                                                        <?php } else { ?>
                                                            <span class="label label-default">Off</span>
                                                        <?php } ?>
                                                    </td>
                                                    <td>
                                                        <a href="<?php echo admin_url('bizbot/edit_followup/' . $f['id']); ?>" class="btn btn-default btn-icon" data-toggle="tooltip" title="Edit"><i class="fa fa-pencil"></i></a>
                                                        <a href="<?php echo admin_url('bizbot/delete_followup/' . $f['id']); ?>" class="btn btn-danger btn-icon _delete" data-toggle="tooltip" title="Delete"><i class="fa fa-remove"></i></a>
                                                    </td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>