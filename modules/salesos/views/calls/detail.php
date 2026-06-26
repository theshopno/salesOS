<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">

                <div class="tw-flex tw-items-center tw-gap-3 tw-mb-4">
                    <a href="<?= admin_url('salesos/calls') ?>" class="btn btn-default btn-sm">
                        <i class="fa fa-arrow-left"></i> Back
                    </a>
                    <h4 class="tw-text-xl tw-font-bold tw-mb-0">Call Detail</h4>
                    <?= salesos_disposition_badge($call['disposition']) ?>
                    <?= salesos_direction_icon($call['direction']) ?>
                </div>

                <!-- Call Info -->
                <div class="panel panel-default">
                    <div class="panel-heading"><h4 class="panel-title">Call Information</h4></div>
                    <div class="panel-body">
                        <table class="table table-condensed tw-mb-0">
                            <tr><th width="30%">Date / Time</th><td><?= e($call['calldate']) ?></td></tr>
                            <tr><th>Unique ID</th><td><code><?= e($call['uniqueid']) ?></code></td></tr>
                            <tr><th>Direction</th><td><?= salesos_direction_icon($call['direction']) ?> <?= ucfirst($call['direction']) ?></td></tr>
                            <tr><th>From (src)</th><td><code><?= e($call['src']) ?></code> <?= $call['src_name'] ? '— ' . e($call['src_name']) : '' ?></td></tr>
                            <tr><th>To (dst)</th><td><code><?= e($call['dst']) ?></code> <?= $call['dst_name'] ? '— ' . e($call['dst_name']) : '' ?></td></tr>
                            <tr><th>Extension</th><td><?= e($call['extension'] ?? '—') ?></td></tr>
                            <tr><th>Agent</th><td><?= e(($call['firstname'] ?? '') . ' ' . ($call['lastname'] ?? '')) ?: '—' ?></td></tr>
                            <tr><th>Duration</th><td><?= salesos_format_duration((int)$call['duration']) ?> (ring) / <?= salesos_format_duration((int)$call['billsec']) ?> (talk)</td></tr>
                            <tr><th>Disposition</th><td><?= salesos_disposition_badge($call['disposition']) ?></td></tr>
                            <tr><th>Channel</th><td><small class="text-muted"><?= e($call['channel'] ?? '—') ?></small></td></tr>
                            <tr><th>Last Synced</th><td><small class="text-muted"><?= e($call['synced_at'] ?? '—') ?></small></td></tr>
                        </table>
                    </div>
                </div>

                <!-- Recording -->
                <?php if ($recording_url): ?>
                <div class="panel panel-default">
                    <div class="panel-heading"><h4 class="panel-title"><i class="fa fa-microphone"></i> Recording</h4></div>
                    <div class="panel-body">
                        <?= salesos_recording_player($recording_url) ?>
                        <br><small class="text-muted"><?= e(basename($call['recordingfile'])) ?></small>
                    </div>
                </div>
                <?php endif; ?>

                <!-- CRM Match -->
                <div class="panel panel-default">
                    <div class="panel-heading"><h4 class="panel-title">CRM Match</h4></div>
                    <div class="panel-body">
                        <?php if ($call['match_type'] !== 'none' && $entity): ?>
                            <div class="tw-flex tw-items-center tw-gap-2 tw-mb-3">
                                <span class="label label-success"><?= ucfirst($call['match_type']) ?></span>
                                <?= salesos_entity_link($call) ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No CRM match found for this number.</p>
                        <?php endif; ?>

                        <!-- Manual lead remap -->
                        <hr>
                        <div class="tw-flex tw-items-center tw-gap-2">
                            <input type="number" id="remap-lead-id" class="form-control input-sm" style="width:120px;" placeholder="Lead ID" value="<?= $call['lead_id'] ?? '' ?>">
                            <button class="btn btn-xs btn-primary" onclick="salesos_remap_lead(<?= $call['id'] ?>)">
                                <i class="fa fa-link"></i> Link to Lead
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="panel panel-default">
                    <div class="panel-heading"><h4 class="panel-title">Notes</h4></div>
                    <div class="panel-body">
                        <textarea id="call-notes" class="form-control" rows="4" placeholder="Add call notes..."><?= htmlspecialchars($call['notes'] ?? '') ?></textarea>
                        <button class="btn btn-sm btn-primary tw-mt-2" onclick="salesos_save_note(<?= $call['id'] ?>)">
                            <i class="fa fa-save"></i> Save Note
                        </button>
                    </div>
                </div>

                <?php if (has_permission('salesos', '', 'delete')): ?>
                <div class="tw-text-right tw-mb-4">
                    <a href="<?= admin_url('salesos/calls/delete/' . $call['id']) ?>"
                       class="btn btn-danger btn-sm"
                       onclick="return confirm('Delete this call record?')">
                        <i class="fa fa-trash"></i> Delete
                    </a>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
function salesos_save_note(id) {
    const notes = document.getElementById('call-notes').value;
    fetch(SalesOS.apiBase.replace('/api/', '/calls/update_note/') + id, {
        method: 'POST',
        headers: {'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},
        body: 'notes=' + encodeURIComponent(notes)
    }).then(r => r.json()).then(d => alert_float(d.success ? 'success' : 'danger', d.success ? 'Note saved' : 'Error'));
}

function salesos_remap_lead(id) {
    const lead_id = document.getElementById('remap-lead-id').value;
    fetch(SalesOS.apiBase.replace('/api/', '/calls/remap_lead/') + id, {
        method: 'POST',
        headers: {'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},
        body: 'lead_id=' + encodeURIComponent(lead_id)
    }).then(r => r.json()).then(d => {
        alert_float(d.success ? 'success' : 'danger', d.success ? 'Lead linked' : 'Error');
        if (d.success) setTimeout(() => location.reload(), 1000);
    });
}
</script>
