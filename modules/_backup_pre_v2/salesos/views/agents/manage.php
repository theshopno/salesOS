<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">

                <div class="tw-flex tw-justify-between tw-items-center tw-mb-4">
                    <h4 class="tw-text-xl tw-font-bold">Agent Workspace</h4>
                    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#add-agent-modal">
                        <i class="fa fa-plus"></i> Add Agent
                    </button>
                </div>

                <!-- Agent cards grid -->
                <div id="agent-cards-grid" class="sos-agent-grid">
                <?php if (empty($agents)): ?>
                    <p class="text-muted tw-py-8">No agents mapped yet.</p>
                <?php else: ?>
                    <?php foreach ($agents as $a):
                        $routing_label = [
                            'direct'     => 'Direct',
                            'queue'      => 'Queue',
                            'ring_group' => 'Ring Group',
                            'fallback'   => 'Fallback',
                        ][$a['routing_policy'] ?? 'direct'] ?? 'Direct';
                        $device_icon = $a['device_type'] === 'sip_phone' ? 'fa-phone-square' : 'fa-headphones';
                    ?>
                    <div class="sos-agent-card" id="agent-card-<?= $a['id'] ?>" data-ext="<?= e($a['extension']) ?>" data-staff="<?= (int)$a['staff_id'] ?>">
                        <div class="sos-agent-card-top">
                            <div class="sos-agent-avatar">
                                <?= strtoupper(substr($a['firstname'] ?? '?', 0, 1)) ?>
                            </div>
                            <div class="sos-agent-info">
                                <div class="sos-agent-name"><?= e($a['firstname'] . ' ' . $a['lastname']) ?></div>
                                <div class="sos-agent-sub">
                                    <code class="sos-agent-ext">ext <?= e($a['extension']) ?></code>
                                    <span class="sos-agent-dept"><?= e($a['department'] ?? '') ?></span>
                                </div>
                                <div class="sos-agent-badges">
                                    <span class="sos-agent-device"><i class="fa <?= $device_icon ?>"></i> <?= ucfirst($a['device_type'] ?? 'webrtc') ?></span>
                                    <span class="sos-agent-routing"><?= $routing_label ?></span>
                                </div>
                            </div>
                            <div class="sos-agent-presence-wrap">
                                <span class="sos-presence-dot" id="pres-dot-<?= $a['id'] ?>"></span>
                                <span class="sos-presence-label" id="pres-label-<?= $a['id'] ?>">—</span>
                            </div>
                        </div>
                        <div class="sos-agent-metrics">
                            <div class="sos-metric">
                                <span class="sos-metric-val" id="m-calls-<?= $a['id'] ?>"><?= (int)($a['calls_today'] ?? 0) ?></span>
                                <span class="sos-metric-lbl">Calls</span>
                            </div>
                            <div class="sos-metric">
                                <span class="sos-metric-val text-danger" id="m-missed-<?= $a['id'] ?>"><?= (int)($a['missed_today'] ?? 0) ?></span>
                                <span class="sos-metric-lbl">Missed</span>
                            </div>
                            <div class="sos-metric">
                                <span class="sos-metric-val" id="m-avg-<?= $a['id'] ?>">—</span>
                                <span class="sos-metric-lbl">Avg Talk</span>
                            </div>
                        </div>
                        <div class="sos-agent-card-actions">
                            <button class="btn btn-xs btn-default" onclick="salesos_open_sip_modal(<?= $a['id'] ?>, '<?= e($a['firstname'] . ' ' . $a['lastname']) ?>')"
                                title="<?= !empty($a['sip_password']) ? 'SIP password set' : 'No SIP password' ?>">
                                <i class="fa <?= !empty($a['sip_password']) ? 'fa-lock' : 'fa-unlock-alt' ?>"
                                   style="color:<?= !empty($a['sip_password']) ? '#27ae60' : '#aaa' ?>;"></i>
                            </button>
                            <button class="btn btn-xs btn-default" onclick="salesos_open_profile_modal(<?= $a['id'] ?>)" title="Edit profile">
                                <i class="fa fa-pencil"></i>
                            </button>
                            <div class="salesos-toggle <?= $a['is_active'] ? 'active' : '' ?>"
                                onclick="salesos_toggle_active(<?= $a['id'] ?>, '<?= e($a['extension']) ?>', this)"
                                title="<?= $a['is_active'] ? 'Active' : 'Inactive' ?>">
                                <div class="salesos-toggle-knob"></div>
                            </div>
                            <button class="btn btn-xs btn-danger" onclick="salesos_delete_agent(<?= $a['id'] ?>)" title="Remove">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                </div>

                <!-- PJSIP Endpoints (read-only info) -->
                <?php if (!empty($pjsip_endpoints)): ?>
                <div class="panel panel-default tw-mt-4">
                    <div class="panel-heading"><h4 class="panel-title">PJSIP Endpoints</h4></div>
                    <div class="panel-body" style="padding:0;">
                        <table class="table table-condensed tw-mb-0">
                            <thead><tr><th>Endpoint</th><th>State</th><th>Channels</th></tr></thead>
                            <tbody>
                            <?php foreach ($pjsip_endpoints as $ep): ?>
                                <tr>
                                    <td><code><?= e($ep['ObjectName'] ?? $ep['Endpoint'] ?? '—') ?></code></td>
                                    <td><?= e($ep['DeviceState'] ?? $ep['State'] ?? '—') ?></td>
                                    <td><?= e($ep['ActiveChannels'] ?? '0') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<!-- Add Agent Modal -->
<div class="modal fade" id="add-agent-modal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">×</button>
                <h4 class="modal-title">Add Agent</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Staff Member</label>
                    <select id="new-staff-id" class="form-control">
                        <option value="">Select staff…</option>
                        <?php foreach ($unmapped_staff as $s): ?>
                        <option value="<?= $s['staffid'] ?>"><?= e($s['firstname'] . ' ' . $s['lastname']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Extension</label>
                    <input type="text" id="new-extension" class="form-control" placeholder="e.g. 1001">
                </div>
                <div class="form-group">
                    <label>Device Type</label>
                    <select id="new-device-type" class="form-control">
                        <option value="webrtc">WebRTC (Browser)</option>
                        <option value="sip_phone">SIP Phone (Physical)</option>
                        <option value="hybrid">Hybrid (Both)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="salesos_create_agent()">Add</button>
            </div>
        </div>
    </div>
</div>

<!-- SIP Password Modal -->
<div class="modal fade" id="sip-pwd-modal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">×</button>
                <h4 class="modal-title">WebRTC SIP Password</h4>
            </div>
            <div class="modal-body">
                <p class="text-muted" id="sip-modal-agent-name" style="margin-bottom:12px;font-size:13px;"></p>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" id="sip-modal-pwd" class="form-control" placeholder="Enter SIP password…">
                </div>
                <p class="text-muted" style="font-size:11px;">Leave blank to clear.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="salesos_save_sip_modal()">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Profile / Routing Policy Modal -->
<div class="modal fade" id="profile-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">×</button>
                <h4 class="modal-title">Agent Profile</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="profile-agent-id">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Routing Policy</label>
                            <select id="profile-routing" class="form-control">
                                <option value="direct">Direct</option>
                                <option value="queue">Queue (future)</option>
                                <option value="ring_group">Ring Group (future)</option>
                                <option value="fallback">Fallback</option>
                            </select>
                            <small class="text-muted">How inbound calls reach this agent.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Department</label>
                            <input type="text" id="profile-dept" class="form-control" placeholder="e.g. Sales">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Device Type</label>
                            <select id="profile-device" class="form-control">
                                <option value="webrtc">WebRTC (Browser)</option>
                                <option value="sip_phone">SIP Phone</option>
                                <option value="hybrid">Hybrid</option>
                            </select>
                        </div>
                    </div>
                </div>
                <hr>
                <h5>Permissions</h5>
                <div class="row">
                    <?php foreach ([
                        'can_make_outbound'     => 'Make Outbound Calls',
                        'can_receive_inbound'   => 'Receive Inbound Calls',
                        'can_transfer'          => 'Transfer Calls',
                        'can_record'            => 'Record Calls',
                        'can_access_recordings' => 'Access Recordings',
                    ] as $pkey => $plabel): ?>
                    <div class="col-md-6">
                        <div class="checkbox">
                            <label><input type="checkbox" class="profile-perm" data-perm="<?= $pkey ?>" checked> <?= $plabel ?></label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="salesos_save_profile()">Save Profile</button>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<style>
.sos-agent-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.sos-agent-card {
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 10px;
    padding: 16px;
    transition: box-shadow .15s;
}
.sos-agent-card:hover { box-shadow: 0 2px 12px rgba(0,0,0,.08); }
.sos-agent-card-top { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 12px; }
.sos-agent-avatar {
    width: 42px; height: 42px;
    border-radius: 50%;
    background: #3498db;
    color: #fff;
    font-size: 18px;
    font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.sos-agent-info { flex: 1; min-width: 0; }
.sos-agent-name { font-weight: 700; font-size: 14px; color: #2c3e50; }
.sos-agent-sub  { font-size: 11px; color: #888; margin-top: 2px; }
.sos-agent-ext  { font-size: 11px; margin-right: 6px; }
.sos-agent-badges { margin-top: 4px; display: flex; gap: 5px; flex-wrap: wrap; }
.sos-agent-device, .sos-agent-routing {
    font-size: 10px; padding: 1px 6px;
    background: #eef; border-radius: 8px; color: #555;
}
.sos-agent-routing { background: #efe; }
.sos-agent-presence-wrap { display: flex; flex-direction: column; align-items: center; gap: 2px; }
.sos-presence-dot {
    width: 10px; height: 10px; border-radius: 50%; background: #ccc;
    transition: background .3s;
}
.sos-presence-dot.ONLINE,.sos-presence-dot.READY,.sos-presence-dot.REGISTERED { background: #27ae60; }
.sos-presence-dot.BUSY,.sos-presence-dot.RINGING { background: #e67e22; }
.sos-presence-dot.WRAPUP { background: #9b59b6; }
.sos-presence-dot.OFFLINE { background: #ccc; }
.sos-presence-dot.PAUSED,.sos-presence-dot.BREAK,.sos-presence-dot.LUNCH,.sos-presence-dot.MEETING { background: #f39c12; }
.sos-presence-label { font-size: 9px; text-transform: uppercase; color: #888; letter-spacing: .3px; }
.sos-agent-metrics { display: flex; gap: 0; border-top: 1px solid #f0f0f0; padding-top: 10px; margin-bottom: 10px; }
.sos-metric { flex: 1; text-align: center; }
.sos-metric-val { display: block; font-size: 18px; font-weight: 700; color: #2c3e50; line-height: 1.2; }
.sos-metric-lbl { display: block; font-size: 10px; color: #aaa; text-transform: uppercase; }
.sos-agent-card-actions { display: flex; gap: 6px; align-items: center; border-top: 1px solid #f0f0f0; padding-top: 10px; }
.sos-agent-card-actions .salesos-toggle { margin-left: auto; }
/* Toggle */
.salesos-toggle {
    display: inline-block; width: 38px; height: 20px;
    background: #ccc; border-radius: 10px; position: relative;
    cursor: pointer; transition: background .2s; vertical-align: middle;
}
.salesos-toggle.active { background: #27ae60; }
.salesos-toggle-knob {
    position: absolute; top: 2px; left: 2px;
    width: 16px; height: 16px;
    background: #fff; border-radius: 50%; transition: left .2s;
    box-shadow: 0 1px 3px rgba(0,0,0,.3);
}
.salesos-toggle.active .salesos-toggle-knob { left: 20px; }
</style>
<script>
const agentsUrl  = '<?= admin_url('salesos/agents/') ?>';
const _csrfName  = '<?= $this->security->get_csrf_token_name() ?>';
let   _csrfHash  = '<?= $this->security->get_csrf_hash() ?>';

function _csrf() { return '&' + encodeURIComponent(_csrfName) + '=' + encodeURIComponent(_csrfHash); }

function _agentFetch(url, body, onSuccess) {
    fetch(url, {
        method:  'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded'},
        body:    body + _csrf(),
    })
    .then(function(r) {
        var newHash = r.headers.get('X-CSRF-Hash');
        if (newHash) _csrfHash = newHash;
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    })
    .then(function(d) {
        if (d.success) { onSuccess(d); }
        else { alert_float('danger', d.error || 'Server error'); }
    })
    .catch(function(err) { alert_float('danger', 'Request failed: ' + err.message); });
}

function salesos_create_agent() {
    var staff_id    = document.getElementById('new-staff-id').value;
    var extension   = document.getElementById('new-extension').value.trim();
    var device_type = document.getElementById('new-device-type').value;
    if (!staff_id || !extension) { alert('Staff and Extension are required.'); return; }
    _agentFetch(agentsUrl + 'create',
        'staff_id=' + encodeURIComponent(staff_id) +
        '&extension=' + encodeURIComponent(extension) +
        '&device_type=' + encodeURIComponent(device_type),
        function() { location.reload(); });
}

function salesos_toggle_active(id, extension, el) {
    var nowActive = !el.classList.contains('active');
    el.classList.toggle('active', nowActive);
    _agentFetch(agentsUrl + 'update/' + id,
        'extension=' + encodeURIComponent(extension) + '&is_active=' + (nowActive ? 1 : 0),
        function() { alert_float('success', nowActive ? 'Agent activated' : 'Agent deactivated'); });
}

var _sipModalAgentId = null;
function salesos_open_sip_modal(id, name) {
    _sipModalAgentId = id;
    document.getElementById('sip-modal-agent-name').textContent = name;
    document.getElementById('sip-modal-pwd').value = '';
    $('#sip-pwd-modal').modal('show');
    setTimeout(function() { document.getElementById('sip-modal-pwd').focus(); }, 400);
}

function salesos_save_sip_modal() {
    if (_sipModalAgentId === null) return;
    var pwd = document.getElementById('sip-modal-pwd').value;
    if (!pwd && !confirm('Clear the SIP password for this agent?')) return;
    _agentFetch(agentsUrl + 'set_sip_password/' + _sipModalAgentId,
        'sip_password=' + encodeURIComponent(pwd),
        function() { $('#sip-pwd-modal').modal('hide'); alert_float('success', pwd ? 'SIP password saved' : 'Cleared'); });
}

var _profileAgentId = null;
function salesos_open_profile_modal(id) {
    _profileAgentId = id;
    document.getElementById('profile-agent-id').value = id;
    // Load current agent data
    fetch(agentsUrl + 'get/' + id, { headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(function(r){ return r.json(); })
        .then(function(d) {
            if (!d.success) return;
            var a = d.agent;
            document.getElementById('profile-routing').value = a.routing_policy || 'direct';
            document.getElementById('profile-dept').value    = a.department || '';
            document.getElementById('profile-device').value  = a.device_type || 'webrtc';
            var perms = a.permissions ? (typeof a.permissions === 'string' ? JSON.parse(a.permissions) : a.permissions) : {};
            document.querySelectorAll('.profile-perm').forEach(function(cb) {
                var k = cb.dataset.perm;
                cb.checked = perms[k] !== false; // default true
            });
            $('#profile-modal').modal('show');
        });
}

function salesos_save_profile() {
    if (!_profileAgentId) return;
    var perms = {};
    document.querySelectorAll('.profile-perm').forEach(function(cb) {
        perms[cb.dataset.perm] = cb.checked;
    });
    _agentFetch(agentsUrl + 'update_profile/' + _profileAgentId,
        'routing_policy=' + encodeURIComponent(document.getElementById('profile-routing').value) +
        '&department='    + encodeURIComponent(document.getElementById('profile-dept').value) +
        '&device_type='   + encodeURIComponent(document.getElementById('profile-device').value) +
        '&permissions='   + encodeURIComponent(JSON.stringify(perms)),
        function() { $('#profile-modal').modal('hide'); alert_float('success', 'Profile saved'); location.reload(); });
}

function salesos_delete_agent(id) {
    if (!confirm('Remove this agent mapping?')) return;
    _agentFetch(agentsUrl + 'delete/' + id, '_dummy=1',
        function() { document.getElementById('agent-card-' + id).remove(); alert_float('success', 'Removed'); });
}

// ── Real-time presence updates via WS ────────────────────────────────────────

// Map ext → agent_id from rendered data
var _extToCard = {};
document.querySelectorAll('.sos-agent-card').forEach(function(card) {
    _extToCard[card.dataset.ext] = card.id.replace('agent-card-', '');
});

function _updatePresenceCard(ext, presence) {
    var id  = _extToCard[ext];
    if (!id) return;
    var dot = document.getElementById('pres-dot-' + id);
    var lbl = document.getElementById('pres-label-' + id);
    if (dot) dot.className = 'sos-presence-dot ' + (presence || 'OFFLINE');
    if (lbl) lbl.textContent = (presence || 'OFFLINE').charAt(0) + (presence || 'offline').slice(1).toLowerCase();
}

document.addEventListener('DOMContentLoaded', function() {
    // Apply snapshot on load
    if (window.SalesOsWsInstance) {
        window.SalesOsWsInstance.on('snapshot:agents', function(data) {
            Object.keys(data).forEach(function(ext) {
                _updatePresenceCard(ext, (data[ext].presence || 'OFFLINE'));
            });
        });
        window.SalesOsWsInstance.on('agent.*', function(ev) {
            if (ev.agent_ext) _updatePresenceCard(ev.agent_ext, ev.presence || ev.event_type.replace('agent.','').toUpperCase());
        });
        // Initial snapshot already delivered on connect; also poll Redis state now
        fetch('<?= admin_url('salesos/realtime/state') ?>', { headers: {'X-Requested-With':'XMLHttpRequest'} })
            .then(function(r){ return r.json(); })
            .then(function(d) {
                if (d.agents) Object.keys(d.agents).forEach(function(ext) {
                    _updatePresenceCard(ext, d.agents[ext].presence || 'OFFLINE');
                });
            });
    }
});
</script>
