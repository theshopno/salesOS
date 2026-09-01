<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
                    <h4 class="tw-text-xl tw-font-bold">Call Logs</h4>
                    <?php if (has_permission('salesos', '', 'settings')): ?>
                    <button class="btn btn-info btn-sm" id="btn-sync">
                        <i class="fa fa-refresh"></i> Sync CDR
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Filters -->
                <form method="GET" class="panel panel-default">
                    <div class="panel-body tw-py-3">
                        <div class="row">
                            <div class="col-md-2">
                                <input type="text" name="search" class="form-control input-sm" placeholder="Search number…" value="<?= e($filters['search']) ?>">
                            </div>
                            <div class="col-md-2">
                                <select name="direction" class="form-control input-sm">
                                    <option value="">All Directions</option>
                                    <?php foreach (['inbound','outbound','internal'] as $d): ?>
                                    <option value="<?= $d ?>" <?= $filters['direction'] === $d ? 'selected' : '' ?>><?= ucfirst($d) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="disposition" class="form-control input-sm">
                                    <option value="">All Statuses</option>
                                    <option value="ANSWERED"  <?= $filters['disposition'] === 'ANSWERED'  ? 'selected' : '' ?>>Answered</option>
                                    <option value="NO ANSWER" <?= $filters['disposition'] === 'NO ANSWER' ? 'selected' : '' ?>>No Answer</option>
                                    <option value="BUSY"      <?= $filters['disposition'] === 'BUSY'      ? 'selected' : '' ?>>Busy</option>
                                    <option value="FAILED"    <?= $filters['disposition'] === 'FAILED'    ? 'selected' : '' ?>>Failed</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="agent_id" class="form-control input-sm">
                                    <option value="">All Agents</option>
                                    <?php foreach ($agents as $a): ?>
                                    <option value="<?= $a['id'] ?>" <?= $filters['agent_id'] == $a['id'] ? 'selected' : '' ?>>
                                        <?= e($a['firstname'] . ' ' . $a['lastname']) ?> (<?= e($a['extension']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="date" name="date_from" class="form-control input-sm" value="<?= e($filters['date_from']) ?>">
                            </div>
                            <div class="col-md-1">
                                <input type="date" name="date_to" class="form-control input-sm" value="<?= e($filters['date_to']) ?>">
                            </div>
                            <div class="col-md-1">
                                <button type="submit" class="btn btn-default btn-sm btn-block"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Table + Drawer wrapper -->
                <div class="sos-log-layout">

                    <!-- Table -->
                    <div class="sos-log-table-wrap" id="sos-log-table-wrap">
                        <div class="panel panel-default">
                            <div class="panel-body" style="padding:0;">
                                <table class="table table-hover table-condensed tw-mb-0" id="salesos-calls-table">
                                    <thead class="bg-light">
                                        <tr>
                                            <th style="width:100px;">Date</th>
                                            <th style="width:24px;">Dir</th>
                                            <th>Customer</th>
                                            <th>Agent</th>
                                            <th style="width:65px;">Talk</th>
                                            <th style="width:100px;">Status</th>
                                            <th style="width:22px;" class="text-center">●</th>
                                            <th style="width:36px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (empty($calls)): ?>
                                        <tr><td colspan="8" class="text-center text-muted tw-py-8">No calls found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($calls as $c): ?>
                                        <tr class="salesos-call-row" data-id="<?= $c['id'] ?>" style="cursor:pointer;">
                                            <td>
                                                <small><?= date('d M', strtotime($c['calldate'])) ?></small>
                                                <small class="text-muted"> <?= date('H:i', strtotime($c['calldate'])) ?></small>
                                            </td>
                                            <td><?= salesos_direction_icon($c['direction']) ?></td>
                                            <td>
                                                <?php
                                                    $cust_name   = $c['direction'] === 'outbound' ? ($c['dst_name'] ?? '') : ($c['src_name'] ?? '');
                                                    $cust_number = $c['direction'] === 'outbound' ? $c['dst'] : $c['src'];
                                                ?>
                                                <code class="salesos-num"><?= e($cust_number) ?></code>
                                                <?php if ($cust_name): ?><br><small class="text-muted"><?= e($cust_name) ?></small><?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($c['agent_firstname']): ?>
                                                    <small><?= e($c['agent_firstname']) ?></small>
                                                    <code class="text-muted" style="font-size:10px;"> <?= e($c['agent_extension'] ?? '') ?></code>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= salesos_format_duration((int)$c['billsec']) ?></td>
                                            <td><?= salesos_disposition_badge($c['disposition']) ?></td>
                                            <td class="text-center">
                                                <?php if (!empty($c['recordingfile'])): ?>
                                                    <i class="fa fa-circle" style="color:#27ae60;font-size:8px;" title="Recording"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="<?= admin_url('salesos/calls/detail/' . $c['id']) ?>"
                                                   class="btn btn-xs btn-default" title="Full detail"
                                                   onclick="event.stopPropagation()">
                                                    <i class="fa fa-external-link"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <?php if ($pages > 1): ?>
                        <div class="text-center">
                            <ul class="pagination pagination-sm">
                                <?php for ($p = 1; $p <= $pages; $p++): ?>
                                <li class="<?= $p === $page ? 'active' : '' ?>">
                                    <a href="?<?= http_build_query(array_merge($filters, ['page' => $p])) ?>"><?= $p ?></a>
                                </li>
                                <?php endfor; ?>
                            </ul>
                            <p class="text-muted">Showing <?= count($calls) ?> of <?= $total ?> calls</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Right-side drawer -->
                    <div id="sos-call-drawer" class="sos-call-drawer" style="display:none;">
                        <div class="sos-drawer-header">
                            <div class="sos-drawer-title">
                                <span id="sos-drawer-number" class="sos-drawer-number">—</span>
                                <span id="sos-drawer-name" class="sos-drawer-name"></span>
                            </div>
                            <div class="sos-drawer-head-actions">
                                <span id="sos-drawer-badge"></span>
                                <button class="sos-drawer-close" onclick="sos_closeDrawer()" title="Close">×</button>
                            </div>
                        </div>

                        <!-- Tabs -->
                        <ul class="nav nav-pills sos-drawer-tabs" id="sos-drawer-tabs">
                            <li class="active"><a data-toggle="pill" href="#sos-dtab-timeline">Timeline</a></li>
                            <li><a data-toggle="pill" href="#sos-dtab-recording">Recording</a></li>
                            <li><a data-toggle="pill" href="#sos-dtab-crm">CRM</a></li>
                            <li><a data-toggle="pill" href="#sos-dtab-notes">Notes</a></li>
                        </ul>

                        <div class="tab-content sos-drawer-content">
                            <!-- Timeline -->
                            <div id="sos-dtab-timeline" class="tab-pane active">
                                <div id="sos-drawer-timeline" class="sos-drawer-timeline">
                                    <div class="sos-drawer-loading"><i class="fa fa-spinner fa-spin"></i> Loading…</div>
                                </div>
                            </div>
                            <!-- Recording -->
                            <div id="sos-dtab-recording" class="tab-pane">
                                <div id="sos-drawer-recording" class="sos-drawer-section">
                                    <p class="text-muted">Select a call to view.</p>
                                </div>
                            </div>
                            <!-- CRM -->
                            <div id="sos-dtab-crm" class="tab-pane">
                                <div id="sos-drawer-crm" class="sos-drawer-section">
                                    <p class="text-muted">No CRM data.</p>
                                </div>
                            </div>
                            <!-- Notes -->
                            <div id="sos-dtab-notes" class="tab-pane">
                                <div class="sos-drawer-section">
                                    <textarea id="sos-drawer-note-input" class="form-control" rows="5"
                                        placeholder="Add note…"></textarea>
                                    <button class="btn btn-sm btn-primary tw-mt-2" onclick="sos_saveDrawerNote()">
                                        <i class="fa fa-save"></i> Save
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Drawer footer actions -->
                        <div class="sos-drawer-footer">
                            <div id="sos-drawer-call-again"></div>
                        </div>
                    </div>

                </div><!-- /sos-log-layout -->

            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<style>
.salesos-num { font-size: 12px; }
.sos-log-layout { display: flex; gap: 0; align-items: flex-start; }
.sos-log-table-wrap { flex: 1; min-width: 0; transition: margin-right .2s; }
.sos-log-table-wrap.drawer-open { margin-right: 360px; }

/* Drawer */
.sos-call-drawer {
    position: fixed;
    top: 60px; right: 0; bottom: 0;
    width: 355px;
    background: #fff;
    border-left: 1px solid #e0e0e0;
    display: flex; flex-direction: column;
    z-index: 1030;
    box-shadow: -2px 0 12px rgba(0,0,0,.08);
    overflow: hidden;
}
.sos-drawer-header {
    padding: 12px 14px 10px;
    background: #2c3e50;
    color: #fff;
    display: flex; align-items: center; justify-content: space-between;
    flex-shrink: 0;
}
.sos-drawer-number { font-size: 15px; font-weight: 700; letter-spacing: .5px; }
.sos-drawer-name   { font-size: 11px; color: #aac; display: block; margin-top: 1px; }
.sos-drawer-head-actions { display: flex; align-items: center; gap: 8px; }
.sos-drawer-close {
    background: none; border: none; color: #fff;
    font-size: 20px; cursor: pointer; line-height: 1; padding: 0;
}
.sos-drawer-tabs { padding: 8px 12px 0; margin: 0; border-bottom: 1px solid #eee; flex-shrink: 0; }
.sos-drawer-tabs > li > a { padding: 5px 10px; font-size: 12px; }
.sos-drawer-content { flex: 1; overflow-y: auto; }
.sos-drawer-section { padding: 14px; }
.sos-drawer-footer {
    padding: 10px 14px;
    border-top: 1px solid #eee;
    flex-shrink: 0;
}
.sos-drawer-loading { padding: 20px; text-align: center; color: #aaa; }

/* Timeline */
.sos-drawer-timeline { padding: 12px 14px; }
.sos-tl-item { display: flex; gap: 10px; margin-bottom: 10px; }
.sos-tl-dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: #3498db; flex-shrink: 0; margin-top: 5px;
}
.sos-tl-time { font-size: 10px; color: #aaa; white-space: nowrap; }
.sos-tl-event { font-size: 12px; color: #555; }

/* Active row */
.salesos-call-row.sos-row-active > td { background: #eaf4fb !important; }
</style>
<script>
const salesosCallsBase = '<?= admin_url('salesos/') ?>';
const salesosApiBase   = '<?= admin_url('salesos/api/') ?>';
const salesosCsrfName  = '<?= $this->security->get_csrf_token_name() ?>';
let   salesosCsrfHash  = '<?= $this->security->get_csrf_hash() ?>';
function _sc() { return '&' + encodeURIComponent(salesosCsrfName) + '=' + encodeURIComponent(salesosCsrfHash); }

var _drawerCallId     = null;
var _drawerNoteCallId = null;

// Row click → open drawer
document.querySelectorAll('.salesos-call-row').forEach(function(row) {
    row.addEventListener('click', function(e) {
        if (e.target.closest('a,button')) return;
        sos_openDrawer(parseInt(row.dataset.id), row);
    });
});

function sos_openDrawer(callId, row) {
    // Deselect previous
    document.querySelectorAll('.salesos-call-row.sos-row-active').forEach(function(r) {
        r.classList.remove('sos-row-active');
    });
    if (row) row.classList.add('sos-row-active');

    _drawerCallId     = callId;
    _drawerNoteCallId = callId;

    var drawer = document.getElementById('sos-call-drawer');
    drawer.style.display = 'flex';
    document.getElementById('sos-log-table-wrap').classList.add('drawer-open');

    // Reset tabs to Timeline
    document.querySelector('#sos-drawer-tabs .active a').click();

    // Load data
    document.getElementById('sos-drawer-timeline').innerHTML =
        '<div class="sos-drawer-loading"><i class="fa fa-spinner fa-spin"></i> Loading…</div>';

    fetch(salesosApiBase + 'call_context/' + callId, {
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    }).then(function(r){ return r.json(); })
    .then(function(d) {
        if (!d.success) return;
        var c = d.call;

        // Header
        var custNum  = c.direction === 'outbound' ? c.dst : c.src;
        var custName = c.direction === 'outbound' ? (c.dst_name || '') : (c.src_name || '');
        document.getElementById('sos-drawer-number').textContent = custNum;
        document.getElementById('sos-drawer-name').textContent   = custName;
        document.getElementById('sos-drawer-badge').innerHTML    = '';

        // Timeline
        var tl = document.getElementById('sos-drawer-timeline');
        if (!d.events || !d.events.length) {
            tl.innerHTML = '<p class="text-muted" style="padding:14px;">No events recorded.</p>';
        } else {
            var html = '';
            d.events.forEach(function(ev) {
                var ts    = ev.created_at ? ev.created_at.substr(11, 8) : '';
                var label = sos_event_label(ev.event_type, ev.from_state, ev.to_state);
                html += '<div class="sos-tl-item"><div class="sos-tl-dot"></div>' +
                        '<div><div class="sos-tl-time">' + ts + '</div>' +
                        '<div class="sos-tl-event">' + label + '</div></div></div>';
            });
            tl.innerHTML = html;
        }

        // Recording
        var recDiv = document.getElementById('sos-drawer-recording');
        if (d.recording_url) {
            recDiv.innerHTML = '<audio controls preload="none" style="width:100%;height:34px;">' +
                               '<source src="' + d.recording_url + '" type="audio/wav"></audio>' +
                               '<p class="text-muted tw-mt-1" style="font-size:11px;">' + (c.recordingfile ? c.recordingfile.split('/').pop() : '') + '</p>';
        } else {
            recDiv.innerHTML = '<p class="text-muted">No recording for this call.</p>';
        }

        // CRM
        var crmDiv = document.getElementById('sos-drawer-crm');
        if (c.match_type && c.match_type !== 'none') {
            var entityName = c.src_name || c.dst_name || '';
            crmDiv.innerHTML =
                '<div class="tw-mb-2"><span class="label label-success">' + c.match_type + '</span> ' +
                '<strong>' + entityName + '</strong></div>' +
                '<div style="font-size:12px;color:#666;">' +
                '<div><i class="fa fa-phone"></i> ' + custNum + '</div>' +
                '</div>';
        } else {
            crmDiv.innerHTML = '<p class="text-muted">No CRM match.</p>';
        }

        // Notes
        document.getElementById('sos-drawer-note-input').value = c.notes || '';

        // Footer: call again
        var callAgainDiv = document.getElementById('sos-drawer-call-again');
        var mobileRe = /^0[1][3-9]\d{8}$/;
        if (mobileRe.test(custNum.replace(/\D/,''))) {
            callAgainDiv.innerHTML = '<button class="btn btn-success btn-sm" onclick="SalesOS.call(\'' + custNum + '\')">' +
                                     '<i class="fa fa-phone"></i> Call Again</button>';
        } else {
            callAgainDiv.innerHTML = '';
        }
    });
}

function sos_closeDrawer() {
    document.getElementById('sos-call-drawer').style.display = 'none';
    document.getElementById('sos-log-table-wrap').classList.remove('drawer-open');
    document.querySelectorAll('.salesos-call-row.sos-row-active').forEach(function(r) {
        r.classList.remove('sos-row-active');
    });
    _drawerCallId = null;
}

function sos_saveDrawerNote() {
    var id    = _drawerNoteCallId;
    var notes = document.getElementById('sos-drawer-note-input').value;
    if (!id) return;
    fetch(salesosCallsBase + 'calls/update_note/' + id, {
        method: 'POST',
        headers: {'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},
        body: 'notes=' + encodeURIComponent(notes) + _sc()
    }).then(function(r){ return r.json(); })
    .then(function(d){ alert_float(d.success ? 'success' : 'danger', d.success ? 'Note saved' : 'Error'); });
}

function sos_event_label(type, from_state, to_state) {
    var labels = {
        'call.ringing'   : '📞 Call Ringing',
        'call.dialing'   : '⏳ Dialing',
        'call.bridged'   : '✓ Answered',
        'call.answered'  : '✓ Answered',
        'call.on_hold'   : '⏸ Put on Hold',
        'call.unhold'    : '▶ Resumed',
        'call.ended'     : '✕ Call Ended',
        'call.failed'    : '✕ Call Failed',
        'recording.started' : '🎙 Recording Started',
        'recording.stopped' : '🎙 Recording Stopped',
    };
    return labels[type] || type.replace(/\./g,' ').replace(/\b\w/g,function(c){return c.toUpperCase();});
}

// Sync CDR
document.getElementById('btn-sync')?.addEventListener('click', function() {
    var btn = this, orig = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
    fetch(salesosApiBase + 'sync_cdr', {
        method:'POST',
        headers:{'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},
        body: '_dummy=1' + _sc()
    }).then(function(r){ return r.json(); })
    .then(function(d){
        btn.disabled = false; btn.innerHTML = orig;
        alert_float(d.success ? 'success' : 'danger', d.success ? 'Synced ' + d.synced + ' records' : d.error);
        if (d.success && d.synced > 0) setTimeout(function(){ location.reload(); }, 1200);
    }).catch(function(err){
        btn.disabled = false; btn.innerHTML = orig;
        alert_float('danger', err.name === 'AbortError' ? 'Timed out' : 'Failed: ' + err.message);
    });
});
</script>
