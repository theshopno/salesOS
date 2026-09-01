<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$display_number = $call['direction'] === 'outbound' ? $call['dst'] : $call['src'];
$display_name   = $call['direction'] === 'outbound' ? ($call['dst_name'] ?? '') : ($call['src_name'] ?? '');
$call_ts        = strtotime($call['calldate']);

function sos_event_meta(string $type): array {
    $map = [
        'call.ringing'        => ['fa-bell',           '#e67e22', 'Ringing'],
        'call.answered'       => ['fa-phone',           '#27ae60', 'Answered'],
        'call.bridge'         => ['fa-phone',           '#27ae60', 'Call Bridged'],
        'call.ended'          => ['fa-phone-square',    '#c0392b', 'Call Ended'],
        'call.hangup'         => ['fa-phone-square',    '#c0392b', 'Hangup'],
        'queue.enter'         => ['fa-sign-in',         '#3498db', 'Entered Queue'],
        'queue.agent_connect' => ['fa-handshake-o',    '#2980b9', 'Agent Connected'],
        'queue.complete'      => ['fa-check-circle',    '#27ae60', 'Queue Complete'],
        'queue.abandon'       => ['fa-times-circle',    '#c0392b', 'Queue Abandoned'],
        'agent.dialing'       => ['fa-phone',           '#9b59b6', 'Agent Dialing'],
        'transfer.blind'      => ['fa-share',           '#e67e22', 'Blind Transfer'],
        'transfer.attended'   => ['fa-share-alt',       '#e67e22', 'Attended Transfer'],
        'hold.on'             => ['fa-pause',           '#f39c12', 'On Hold'],
        'hold.off'            => ['fa-play',            '#27ae60', 'Resumed'],
        'dtmf'                => ['fa-keyboard-o',      '#95a5a6', 'DTMF'],
        'recording.start'     => ['fa-dot-circle-o',   '#c0392b', 'Recording Started'],
        'recording.stop'      => ['fa-stop-circle-o',  '#7f8c8d', 'Recording Stopped'],
    ];
    return $map[$type] ?? ['fa-circle', '#95a5a6', $type];
}
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-10 col-md-offset-1">

                <!-- Back bar -->
                <div class="tw-flex tw-items-center tw-gap-3 tw-mb-4">
                    <a href="<?= admin_url('salesos/calls') ?>" class="btn btn-default btn-sm">
                        <i class="fa fa-arrow-left"></i> Calls
                    </a>
                    <span class="text-muted">/</span>
                    <h4 class="tw-text-lg tw-font-bold tw-mb-0">Call Detail</h4>
                    <?= salesos_disposition_badge($call['disposition']) ?>
                    <?= salesos_direction_icon($call['direction']) ?>
                </div>

                <!-- Header card -->
                <div class="sos-detail-header">
                    <div class="sos-detail-number">
                        <span class="sos-big-number"><?= e($display_number) ?></span>
                        <?php if ($display_name): ?>
                            <span class="sos-big-name"><?= e($display_name) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="sos-detail-meta">
                        <div class="sos-meta-item">
                            <i class="fa fa-calendar fa-fw"></i>
                            <?= date('d M Y, H:i', $call_ts) ?>
                        </div>
                        <div class="sos-meta-item">
                            <i class="fa fa-clock-o fa-fw"></i>
                            Ring <?= salesos_format_duration((int)$call['duration']) ?> &nbsp;/&nbsp;
                            Talk <strong><?= salesos_format_duration((int)$call['billsec']) ?></strong>
                        </div>
                        <?php if (!empty($call['firstname'])): ?>
                        <div class="sos-meta-item">
                            <i class="fa fa-headset fa-fw"></i>
                            <?= e($call['firstname'] . ' ' . $call['lastname']) ?>
                            <code class="sos-ext-code">ext <?= e($call['extension'] ?? '') ?></code>
                        </div>
                        <?php endif; ?>
                        <?php if ($call['match_type'] !== 'none' && $entity): ?>
                        <div class="sos-meta-item">
                            <i class="fa fa-link fa-fw"></i>
                            <?= salesos_entity_link($call) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="sos-detail-actions">
                        <?php
                        $entity_url = '';
                        if ($call['match_type'] === 'lead' && !empty($call['lead_id']))
                            $entity_url = admin_url('leads/index/' . $call['lead_id']);
                        elseif (in_array($call['match_type'], ['client', 'contact']) && !empty($call['client_id']))
                            $entity_url = admin_url('clients/client/' . $call['client_id']);
                        ?>
                        <?php if ($entity_url): ?>
                        <a href="<?= $entity_url ?>" class="btn btn-info btn-sm" target="_blank">
                            <i class="fa fa-external-link"></i> Open <?= ucfirst($call['match_type']) ?>
                        </a>
                        <?php endif; ?>
                        <?php if (staff_can('make', 'salesos') && preg_match('/^0[1][3-9]\d{8}$/', preg_replace('/\D/', '', $display_number))): ?>
                        <button class="btn btn-success btn-sm" onclick="SosWorkspace && SosWorkspace.call('<?= e($display_number) ?>')">
                            <i class="fa fa-phone"></i> Call Again
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tabs -->
                <ul class="nav nav-pills sos-detail-tabs" id="sos-detail-tabs" role="tablist">
                    <li class="active"><a href="#sos-tab-timeline"  data-toggle="tab"><i class="fa fa-list-ul"></i>    Timeline</a></li>
                    <li><a href="#sos-tab-overview"  data-toggle="tab"><i class="fa fa-info-circle"></i> Overview</a></li>
                    <li><a href="#sos-tab-crm"       data-toggle="tab"><i class="fa fa-link"></i>        CRM</a></li>
                    <li><a href="#sos-tab-recording" data-toggle="tab"><i class="fa fa-microphone"></i>  Recording</a></li>
                    <li><a href="#sos-tab-notes"     data-toggle="tab"><i class="fa fa-sticky-note-o"></i> Notes</a></li>
                    <li><a href="#sos-tab-ai"        data-toggle="tab"><i class="fa fa-magic"></i>       AI <span class="sos-ai-spark">&#10022;</span></a></li>
                </ul>

                <div class="tab-content sos-detail-tab-content">

                    <!-- ── Timeline ── -->
                    <div class="tab-pane active" id="sos-tab-timeline">
                        <?php if (empty($call_events)): ?>
                        <div class="sos-tl-empty">
                            <i class="fa fa-history fa-2x text-muted"></i>
                            <p class="text-muted tw-mt-2 tw-mb-0">No events recorded for this call.</p>
                        </div>
                        <?php else: ?>
                        <div class="sos-tl-container">
                            <?php foreach ($call_events as $ev): ?>
                            <?php
                                $ev_ts        = strtotime($ev['created_at']);
                                $offset_secs  = $ev_ts - $call_ts;
                                $offset_label = ($offset_secs >= 0 ? '+' : '-') . gmdate('i:s', abs($offset_secs));
                                [$icon, $color, $label] = sos_event_meta($ev['event_type']);
                            ?>
                            <div class="sos-tl-row">
                                <div class="sos-tl-icon" style="background:<?= $color ?>;">
                                    <i class="fa <?= $icon ?>"></i>
                                </div>
                                <div class="sos-tl-body">
                                    <span class="sos-tl-label"><?= $label ?></span>
                                    <span class="sos-tl-time"><?= date('H:i:s', $ev_ts) ?></span>
                                    <span class="sos-tl-offset"><?= $offset_label ?></span>
                                    <?php if ($ev['queue_name']): ?>
                                        <span class="label label-default sos-tl-tag"><?= e($ev['queue_name']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($ev['from_state'] && $ev['to_state']): ?>
                                        <small class="text-muted sos-tl-state"><?= e($ev['from_state']) ?> &rarr; <?= e($ev['to_state']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <?php if ($wrap_up): ?>
                            <div class="sos-tl-row sos-tl-wrapup-row">
                                <div class="sos-tl-icon" style="background:#27ae60;">
                                    <i class="fa fa-check"></i>
                                </div>
                                <div class="sos-tl-body">
                                    <span class="sos-tl-label">Wrap-up <?= $wrap_up['skipped'] ? 'Skipped' : 'Completed' ?></span>
                                    <span class="sos-tl-time"><?= date('H:i:s', strtotime($wrap_up['completed_at'] ?? $wrap_up['created_at'])) ?></span>
                                    <?php if ($wrap_up['disposition']): ?>
                                        <span class="label label-default sos-tl-tag"><?= e($wrap_up['disposition']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($wrap_up['outcome']): ?>
                                        <span class="label label-info sos-tl-tag"><?= e($wrap_up['outcome']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($wrap_up['skipped']): ?>
                                        <span class="label label-warning sos-tl-tag">Skipped</span>
                                    <?php endif; ?>
                                    <?php if ($wrap_up['notes']): ?>
                                        <div class="sos-tl-note"><?= e($wrap_up['notes']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- ── Overview ── -->
                    <div class="tab-pane" id="sos-tab-overview">
                        <table class="table table-condensed sos-overview-table">
                            <tr><th>Direction</th><td><?= salesos_direction_icon($call['direction']) ?> <?= ucfirst($call['direction']) ?></td></tr>
                            <tr><th>From</th><td><code><?= e($call['src']) ?></code><?= $call['src_name'] ? ' — ' . e($call['src_name']) : '' ?></td></tr>
                            <tr><th>To</th><td><code><?= e($call['dst']) ?></code><?= $call['dst_name'] ? ' — ' . e($call['dst_name']) : '' ?></td></tr>
                            <tr><th>Disposition</th><td><?= salesos_disposition_badge($call['disposition']) ?></td></tr>
                            <tr><th>Ring Time</th><td><?= salesos_format_duration((int)$call['duration']) ?></td></tr>
                            <tr><th>Talk Time</th><td><strong><?= salesos_format_duration((int)$call['billsec']) ?></strong></td></tr>
                            <tr><th>Channel</th><td><small class="text-muted"><?= e($call['channel'] ?? '—') ?></small></td></tr>
                            <tr><th>Unique ID</th><td><code style="font-size:10px;"><?= e($call['uniqueid']) ?></code></td></tr>
                            <?php if (!empty($call['session_id'])): ?>
                            <tr><th>Session</th><td><code style="font-size:10px;"><?= e($call['session_id']) ?></code></td></tr>
                            <?php endif; ?>
                            <tr><th>Synced At</th><td><small class="text-muted"><?= e($call['synced_at'] ?? '—') ?></small></td></tr>
                        </table>
                    </div>

                    <!-- ── CRM ── -->
                    <div class="tab-pane" id="sos-tab-crm">
                        <div style="padding:20px;">
                            <?php if ($call['match_type'] !== 'none' && $entity): ?>
                            <div class="alert alert-success" style="margin-bottom:16px;">
                                <span class="label label-success"><?= ucfirst($call['match_type']) ?></span>
                                &nbsp;<?= salesos_entity_link($call) ?>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-warning" style="margin-bottom:16px;">
                                <i class="fa fa-exclamation-triangle"></i> No CRM match found for this call.
                            </div>
                            <?php endif; ?>
                            <div class="form-inline" style="margin-bottom:16px;">
                                <label style="margin-right:8px;">Link to Lead ID:</label>
                                <input type="number" id="remap-lead-id" class="form-control input-sm"
                                    style="width:110px;margin-right:8px;"
                                    placeholder="Lead ID" value="<?= $call['lead_id'] ?? '' ?>">
                                <button class="btn btn-sm btn-primary" onclick="salesos_remap_lead(<?= (int)$call['id'] ?>)">
                                    <i class="fa fa-link"></i> Link
                                </button>
                            </div>
                            <?php if ($wrap_up && $wrap_up['outcome']): ?>
                            <hr>
                            <h5 style="margin-bottom:8px;font-weight:600;">Wrap-up Summary</h5>
                            <table class="table table-condensed" style="max-width:400px;">
                                <tr><th>Disposition</th><td><?= e($wrap_up['disposition'] ?? '—') ?></td></tr>
                                <tr><th>Outcome</th><td><?= e($wrap_up['outcome'] ?? '—') ?></td></tr>
                                <?php if ($wrap_up['lead_status']): ?>
                                <tr><th>Lead Status</th><td><?= e($wrap_up['lead_status']) ?></td></tr>
                                <?php endif; ?>
                                <?php if ($wrap_up['follow_up_at']): ?>
                                <tr><th>Follow-up At</th><td><?= e($wrap_up['follow_up_at']) ?></td></tr>
                                <?php endif; ?>
                            </table>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ── Recording ── -->
                    <div class="tab-pane" id="sos-tab-recording">
                        <div style="padding:20px;">
                            <?php if ($recording_url): ?>
                                <audio controls preload="none" style="width:100%;height:40px;border-radius:8px;outline:none;">
                                    <source src="<?= e($recording_url) ?>" type="audio/wav">
                                </audio>
                                <p class="text-muted" style="font-size:11px;margin-top:8px;">
                                    <i class="fa fa-file-audio-o"></i> <?= e(basename($call['recordingfile'] ?? '')) ?>
                                </p>
                            <?php else: ?>
                                <p class="text-muted tw-mb-0">
                                    <?= !empty($call['recordingfile']) ? 'Recording file not accessible.' : 'No recording for this call.' ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ── Notes ── -->
                    <div class="tab-pane" id="sos-tab-notes">
                        <div style="padding:20px;">
                            <textarea id="call-notes" class="form-control" rows="7"
                                placeholder="Add call notes…"><?= htmlspecialchars($call['notes'] ?? '') ?></textarea>
                            <div style="margin-top:10px;">
                                <button class="btn btn-sm btn-primary" onclick="salesos_save_note(<?= (int)$call['id'] ?>)">
                                    <i class="fa fa-save"></i> Save Note
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ── AI ✦ ── -->
                    <div class="tab-pane" id="sos-tab-ai">
                        <div class="sos-ai-placeholder">
                            <i class="fa fa-magic fa-3x text-muted"></i>
                            <h5>AI Insights</h5>
                            <p class="text-muted">Call transcription, sentiment analysis, and coaching tips will appear here.</p>
                        </div>
                    </div>

                </div>

                <?php if (staff_can('delete', 'salesos')): ?>
                <div style="text-align:right;margin-bottom:30px;margin-top:8px;">
                    <a href="<?= admin_url('salesos/calls/delete/' . (int)$call['id']) ?>"
                       class="btn btn-danger btn-sm"
                       onclick="return confirm('Delete this call record?')">
                        <i class="fa fa-trash"></i> Delete Call
                    </a>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>

<style>
/* ── Detail Header ── */
.sos-detail-header {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    color: #fff;
    border-radius: 10px;
    padding: 18px 22px;
    margin-bottom: 0;
    display: flex;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0;
}
.sos-detail-number { flex: 0 0 auto; }
.sos-big-number    { font-size: 26px; font-weight: 700; letter-spacing: 1px; display: block; }
.sos-big-name      { font-size: 13px; color: #a0b4c5; display: block; margin-top: 2px; }
.sos-detail-meta   { flex: 1; display: flex; flex-direction: column; gap: 5px; }
.sos-meta-item     { font-size: 13px; color: #bdc3c7; }
.sos-meta-item a   { color: #85c1e9; }
.sos-meta-item strong { color: #fff; }
.sos-ext-code      { font-size: 11px; background: rgba(255,255,255,.1); color: #ecf0f1; padding: 1px 5px; border-radius: 3px; }
.sos-detail-actions { flex: 0 0 auto; display: flex; flex-direction: column; gap: 6px; }
/* ── Tabs ── */
.sos-detail-tabs {
    border-radius: 0;
    background: #f5f7fa;
    border: 1px solid #dfe3e8;
    border-top: none;
    padding: 8px 12px 0;
    margin-bottom: 0;
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0;
}
.sos-detail-tabs > li > a {
    border-radius: 4px 4px 0 0;
    font-size: 13px;
    padding: 7px 14px;
    color: #555;
}
.sos-detail-tabs > li.active > a,
.sos-detail-tabs > li.active > a:hover {
    background: #fff;
    color: #333;
    border: 1px solid #dfe3e8;
    border-bottom-color: #fff;
}
.sos-ai-spark { color: #f1c40f; font-size: 14px; }
/* ── Tab content wrapper ── */
.sos-detail-tab-content {
    border: 1px solid #dfe3e8;
    border-top: none;
    background: #fff;
    border-radius: 0 0 8px 8px;
    min-height: 200px;
    margin-bottom: 20px;
}
/* ── Overview table ── */
.sos-overview-table th {
    width: 110px;
    color: #888;
    font-weight: 600;
    font-size: 12px;
    padding: 8px 16px !important;
    border-top: none !important;
}
.sos-overview-table td {
    padding: 8px 16px !important;
    border-top: none !important;
}
.sos-overview-table tr:first-child th,
.sos-overview-table tr:first-child td { border-top: none; }
/* ── Timeline ── */
.sos-tl-empty {
    text-align: center;
    padding: 40px 20px;
}
.sos-tl-container {
    padding: 16px 20px;
}
.sos-tl-row {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 12px;
    position: relative;
}
.sos-tl-row:not(:last-child)::after {
    content: '';
    position: absolute;
    left: 15px;
    top: 32px;
    bottom: -12px;
    width: 2px;
    background: #e8ecf0;
}
.sos-tl-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 12px;
    flex-shrink: 0;
    position: relative;
    z-index: 1;
}
.sos-tl-body {
    padding-top: 5px;
    flex: 1;
    line-height: 1.5;
}
.sos-tl-label  { font-weight: 600; font-size: 13px; color: #333; margin-right: 8px; }
.sos-tl-time   { font-size: 12px; color: #7f8c8d; margin-right: 6px; font-family: monospace; }
.sos-tl-offset { font-size: 11px; color: #aab; background: #f0f2f5; border-radius: 3px; padding: 1px 5px; margin-right: 6px; font-family: monospace; }
.sos-tl-tag    { margin-right: 4px; vertical-align: middle; }
.sos-tl-state  { display: block; font-size: 11px; margin-top: 2px; }
.sos-tl-note   { margin-top: 6px; font-size: 12px; color: #555; background: #f8f9fa; border-left: 3px solid #27ae60; padding: 6px 10px; border-radius: 3px; }
.sos-tl-wrapup-row .sos-tl-body { padding-top: 5px; }
/* ── AI placeholder ── */
.sos-ai-placeholder { text-align: center; padding: 50px 20px; }
.sos-ai-placeholder h5 { margin-top: 16px; font-weight: 600; }
</style>

<script>
function salesos_save_note(id) {
    var notes = document.getElementById('call-notes').value;
    fetch('<?= admin_url('salesos/calls/update_note/') ?>' + id, {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'notes=' + encodeURIComponent(notes) + '&<?= $this->security->get_csrf_token_name() ?>=<?= $this->security->get_csrf_hash() ?>'
    }).then(function(r) { return r.json(); })
    .then(function(d) { alert_float(d.success ? 'success' : 'danger', d.success ? 'Note saved' : 'Error'); });
}
function salesos_remap_lead(id) {
    var lead_id = document.getElementById('remap-lead-id').value;
    fetch('<?= admin_url('salesos/calls/remap_lead/') ?>' + id, {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'lead_id=' + encodeURIComponent(lead_id) + '&<?= $this->security->get_csrf_token_name() ?>=<?= $this->security->get_csrf_hash() ?>'
    }).then(function(r) { return r.json(); })
    .then(function(d) {
        alert_float(d.success ? 'success' : 'danger', d.success ? 'Lead linked' : 'Error');
        if (d.success) setTimeout(function() { location.reload(); }, 900);
    });
}
</script>
