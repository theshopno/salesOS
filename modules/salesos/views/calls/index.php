<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
                    <h4 class="tw-text-xl tw-font-bold">Call Logs</h4>
                    <div class="btn-group">
                        <?php if (has_permission('salesos', '', 'settings')): ?>
                        <button class="btn btn-info btn-sm" id="btn-sync">
                            <i class="fa fa-refresh"></i> Sync CDR
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Filters -->
                <form method="GET" class="panel panel-default">
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-2">
                                <input type="text" name="search" class="form-control input-sm" placeholder="Search number..." value="<?= e($filters['search']) ?>">
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
                                    <?php foreach (['ANSWERED','NO ANSWER','BUSY','FAILED'] as $d): ?>
                                    <option value="<?= $d ?>" <?= $filters['disposition'] === $d ? 'selected' : '' ?>><?= $d ?></option>
                                    <?php endforeach; ?>
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

                <!-- Table -->
                <div class="panel panel-default">
                    <div class="panel-body" style="padding:0;">
                        <table class="table table-hover table-condensed tw-mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Date/Time</th>
                                    <th>Dir</th>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>Agent</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                    <th>Match</th>
                                    <th>Rec</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($calls)): ?>
                                <tr><td colspan="10" class="text-center text-muted tw-py-8">No calls found. <a href="<?= admin_url('salesos/calls/sync') ?>">Sync CDR now</a></td></tr>
                            <?php else: ?>
                                <?php foreach ($calls as $c): ?>
                                <tr>
                                    <td>
                                        <small><?= date('Y-m-d', strtotime($c['calldate'])) ?></small><br>
                                        <small class="text-muted"><?= date('H:i:s', strtotime($c['calldate'])) ?></small>
                                    </td>
                                    <td><?= salesos_direction_icon($c['direction']) ?></td>
                                    <td>
                                        <code><?= e($c['src']) ?></code>
                                        <?php if ($c['src_name']): ?><br><small class="text-muted"><?= e($c['src_name']) ?></small><?php endif; ?>
                                    </td>
                                    <td>
                                        <code><?= e($c['dst']) ?></code>
                                        <?php if ($c['dst_name']): ?><br><small class="text-muted"><?= e($c['dst_name']) ?></small><?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($c['agent_firstname']): ?>
                                            <small><?= e($c['agent_firstname'] . ' ' . $c['agent_lastname']) ?></small><br>
                                            <code class="text-muted"><?= e($c['agent_extension'] ?? '') ?></code>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= salesos_format_duration((int)$c['billsec']) ?></td>
                                    <td><?= salesos_disposition_badge($c['disposition']) ?></td>
                                    <td>
                                        <?php if ($c['match_type'] !== 'none'): ?>
                                            <?= salesos_entity_link($c) ?>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($c['recordingfile'])): ?>
                                            <i class="fa fa-microphone text-success" title="Recording available"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?= admin_url('salesos/calls/detail/' . $c['id']) ?>" class="btn btn-xs btn-default">
                                            <i class="fa fa-eye"></i>
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
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
const salesosCsrfName = '<?= $this->security->get_csrf_token_name() ?>';
const salesosCsrfHash = '<?= $this->security->get_csrf_hash() ?>';
document.getElementById('btn-sync')?.addEventListener('click', function() {
    const btn = this;
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 20000);
    const csrfBody = '&' + encodeURIComponent(salesosCsrfName) + '=' + encodeURIComponent(salesosCsrfHash);
    fetch(SalesOS.apiBase + 'sync_cdr', {
        method:'POST',
        headers:{'X-Requested-With':'XMLHttpRequest', 'Content-Type':'application/x-www-form-urlencoded'},
        body: '_dummy=1' + csrfBody,
        signal: controller.signal
    })
        .then(async r => {
            const text = await r.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error(`HTTP ${r.status} ${r.statusText} | ${text.slice(0, 300)}`);
            }
        })
        .then(d => {
            clearTimeout(timer);
            btn.disabled = false;
            btn.innerHTML = original;
            alert_float(d.success ? 'success' : 'danger', d.success ? 'Synced ' + d.synced + ' records' : d.error);
            if (d.success && d.synced > 0) setTimeout(() => location.reload(), 1200);
        })
        .catch(err => {
            clearTimeout(timer);
            btn.disabled = false;
            btn.innerHTML = original;
            alert_float('danger', err.name === 'AbortError' ? 'CDR sync timed out' : ('Sync failed: ' + err.message));
        });
});
</script>
