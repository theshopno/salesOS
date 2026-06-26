<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-10 col-md-offset-1">

                <div class="tw-flex tw-justify-between tw-items-center tw-mb-4">
                    <h4 class="tw-text-xl tw-font-bold">Agent / Extension Mapping</h4>
                    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#add-agent-modal">
                        <i class="fa fa-plus"></i> Add Agent
                    </button>
                </div>

                <!-- Agents table -->
                <div class="panel panel-default">
                    <div class="panel-body" style="padding:0;">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Staff Member</th>
                                    <th>Extension</th>
                                    <th>Status</th>
                                    <th>Last Seen</th>
                                    <th>Active</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($agents)): ?>
                                <tr><td colspan="6" class="text-center text-muted tw-py-8">No agents mapped yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($agents as $a): ?>
                                <tr id="agent-row-<?= $a['id'] ?>">
                                    <td>
                                        <strong><?= e($a['firstname'] . ' ' . $a['lastname']) ?></strong><br>
                                        <small class="text-muted"><?= e($a['email'] ?? '') ?></small>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control input-sm" style="width:100px;"
                                            value="<?= e($a['extension']) ?>"
                                            onchange="salesos_update_agent(<?= $a['id'] ?>, this.value, <?= $a['is_active'] ?>)">
                                    </td>
                                    <td>
                                        <?php if ($a['is_logged_in']): ?>
                                            <span class="label label-success"><i class="fa fa-circle"></i> Online</span>
                                        <?php else: ?>
                                            <span class="label label-default"><i class="fa fa-circle"></i> Offline</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small class="text-muted"><?= $a['last_seen'] ? date('d M H:i', strtotime($a['last_seen'])) : '—' ?></small></td>
                                    <td>
                                        <div class="checkbox" style="margin:0;">
                                            <label>
                                                <input type="checkbox" onchange="salesos_update_agent(<?= $a['id'] ?>, '<?= e($a['extension']) ?>', this.checked ? 1 : 0)"
                                                    <?= $a['is_active'] ? 'checked' : '' ?>>
                                            </label>
                                        </div>
                                    </td>
                                    <td>
                                        <button class="btn btn-danger btn-xs" onclick="salesos_delete_agent(<?= $a['id'] ?>)">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- PJSIP Endpoints info -->
                <?php if (!empty($pjsip_endpoints)): ?>
                <div class="panel panel-default">
                    <div class="panel-heading"><h4 class="panel-title">PJSIP Endpoints (from Asterisk)</h4></div>
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
                        <option value="">Select staff...</option>
                        <?php foreach ($unmapped_staff as $s): ?>
                        <option value="<?= $s['staffid'] ?>"><?= e($s['firstname'] . ' ' . $s['lastname']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Extension</label>
                    <input type="text" id="new-extension" class="form-control" placeholder="e.g. 1001">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="salesos_create_agent()">Add</button>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
const agentsUrl  = '<?= admin_url('salesos/agents/') ?>';
const _csrfName  = '<?= $this->security->get_csrf_token_name() ?>';
const _csrfHash  = '<?= $this->security->get_csrf_hash() ?>';
const _csrfParam = '&' + encodeURIComponent(_csrfName) + '=' + encodeURIComponent(_csrfHash);

function _agentFetch(url, body, onSuccess) {
    fetch(url, {
        method:  'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded'},
        body:    body + _csrfParam,
    })
    .then(function(r) {
        if (!r.ok) throw new Error('HTTP ' + r.status + ' ' + r.statusText);
        return r.json();
    })
    .then(function(d) {
        if (d.success) { onSuccess(d); }
        else { alert_float('danger', d.error || 'Server error'); }
    })
    .catch(function(err) {
        alert_float('danger', 'Request failed: ' + err.message);
    });
}

function salesos_create_agent() {
    const staff_id  = document.getElementById('new-staff-id').value;
    const extension = document.getElementById('new-extension').value.trim();
    if (!staff_id || !extension) { alert('Both fields are required.'); return; }

    _agentFetch(
        agentsUrl + 'create',
        'staff_id=' + encodeURIComponent(staff_id) + '&extension=' + encodeURIComponent(extension),
        function() { location.reload(); }
    );
}

function salesos_update_agent(id, extension, is_active) {
    _agentFetch(
        agentsUrl + 'update/' + id,
        'extension=' + encodeURIComponent(extension) + '&is_active=' + is_active,
        function() { alert_float('success', 'Updated'); }
    );
}

function salesos_delete_agent(id) {
    if (!confirm('Remove this agent mapping?')) return;
    _agentFetch(
        agentsUrl + 'delete/' + id,
        '_dummy=1',
        function() { document.getElementById('agent-row-' + id).remove(); alert_float('success', 'Removed'); }
    );
}
</script>
