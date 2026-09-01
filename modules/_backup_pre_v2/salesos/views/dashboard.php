<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">

                <!-- Header -->
                <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
                    <div>
                        <h4 class="tw-text-xl tw-font-bold">SalesOS Dashboard</h4>
                        <small class="text-muted">
                            <?= e($date_from) ?> — <?= e($date_to) ?>
                            &nbsp;|&nbsp; AMI:
                            <?php if ($ami_status === 'connected'): ?>
                                <span class="label label-success"><i class="fa fa-circle"></i> Connected</span>
                            <?php else: ?>
                                <span class="label label-danger" title="<?= htmlspecialchars($ami_error) ?>"><i class="fa fa-circle"></i> Disconnected</span>
                            <?php endif; ?>
                            &nbsp;|&nbsp; Last sync:
                            <span class="text-muted"><?= $last_sync ? $last_sync['synced_at'] : 'Never' ?></span>
                        </small>
                    </div>
                    <div class="btn-group">
                        <?php if (has_permission('salesos', '', 'settings')): ?>
                        <a href="<?= admin_url('salesos/calls/sync') ?>" class="btn btn-info btn-sm" id="btn-sync-cdr">
                            <i class="fa fa-refresh"></i> Sync CDR
                        </a>
                        <?php endif; ?>
                        <a href="<?= admin_url('salesos/calls') ?>" class="btn btn-default btn-sm">
                            <i class="fa fa-list"></i> All Calls
                        </a>
                    </div>
                </div>

                <!-- Date filter -->
                <form method="GET" class="form-inline tw-mb-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-addon">From</span>
                        <input type="date" name="date_from" class="form-control" value="<?= e($date_from) ?>">
                        <span class="input-group-addon">To</span>
                        <input type="date" name="date_to"   class="form-control" value="<?= e($date_to) ?>">
                        <span class="input-group-btn">
                            <button class="btn btn-default" type="submit"><i class="fa fa-filter"></i></button>
                        </span>
                    </div>
                </form>

                <!-- KPI Cards -->
                <div class="row tw-mb-4">
                    <?php
                    $kpis = [
                        ['label' => 'Total Calls',    'value' => $stats['total'],          'icon' => 'fa-phone',       'color' => 'bg-primary'],
                        ['label' => 'Inbound',        'value' => $stats['inbound'],         'icon' => 'fa-phone-square','color' => 'bg-success'],
                        ['label' => 'Outbound',       'value' => $stats['outbound'],        'icon' => 'fa-phone',       'color' => 'bg-info'],
                        ['label' => 'Answered',       'value' => $stats['answered'],        'icon' => 'fa-check',       'color' => 'bg-success'],
                        ['label' => 'Missed',         'value' => $stats['missed'],          'icon' => 'fa-times',       'color' => 'bg-danger'],
                        ['label' => 'Matched Leads',  'value' => $stats['matched_leads'],   'icon' => 'fa-user',        'color' => 'bg-warning'],
                        ['label' => 'Avg Duration',   'value' => salesos_format_duration($stats['avg_duration']),  'icon' => 'fa-clock-o', 'color' => 'bg-info'],
                        ['label' => 'Total Talk Time','value' => salesos_format_duration($stats['total_duration']),'icon' => 'fa-headphones','color' => 'bg-default'],
                    ];
                    foreach ($kpis as $kpi): ?>
                    <div class="col-lg-3 col-md-4 col-sm-6 tw-mb-3">
                        <div class="panel panel-default" style="border-radius:8px;">
                            <div class="panel-body tw-flex tw-items-center tw-gap-3">
                                <div class="<?= $kpi['color'] ?> tw-rounded-full tw-w-10 tw-h-10 tw-flex tw-items-center tw-justify-center" style="min-width:40px;">
                                    <i class="fa <?= $kpi['icon'] ?> text-white"></i>
                                </div>
                                <div>
                                    <div class="tw-text-2xl tw-font-bold"><?= $kpi['value'] ?></div>
                                    <div class="text-muted tw-text-sm"><?= $kpi['label'] ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="row">
                    <!-- Agent Stats -->
                    <div class="col-md-5">
                        <div class="panel panel-default">
                            <div class="panel-heading"><h4 class="panel-title">Agent Performance</h4></div>
                            <div class="panel-body" style="padding:0;">
                                <table class="table table-condensed tw-mb-0">
                                    <thead>
                                        <tr>
                                            <th>Agent</th>
                                            <th>Ext</th>
                                            <th>Calls</th>
                                            <th>Answered</th>
                                            <th>Talk Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (empty($agent_stats)): ?>
                                        <tr><td colspan="5" class="text-center text-muted">No data</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($agent_stats as $a): ?>
                                        <tr>
                                            <td><?= e(($a['firstname'] ?? '') . ' ' . ($a['lastname'] ?? '')) ?: '—' ?></td>
                                            <td><code><?= e($a['extension'] ?? '—') ?></code></td>
                                            <td><?= (int)$a['total_calls'] ?></td>
                                            <td><?= (int)$a['answered'] ?></td>
                                            <td><?= salesos_format_duration((int)$a['total_billsec']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Calls -->
                    <div class="col-md-7">
                        <div class="panel panel-default">
                            <div class="panel-heading tw-flex tw-justify-between tw-items-center">
                                <h4 class="panel-title">Recent Calls</h4>
                                <a href="<?= admin_url('salesos/calls') ?>" class="btn btn-xs btn-default">View All</a>
                            </div>
                            <div class="panel-body" style="padding:0;">
                                <table class="table table-condensed tw-mb-0">
                                    <thead>
                                        <tr><th>Time</th><th>Dir</th><th>From</th><th>To</th><th>Duration</th><th>Status</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php if (empty($recent)): ?>
                                        <tr><td colspan="6" class="text-center text-muted">No calls synced yet. <a href="<?= admin_url('salesos/calls/sync') ?>">Sync CDR</a></td></tr>
                                    <?php else: ?>
                                        <?php foreach ($recent as $c): ?>
                                        <tr>
                                            <td><small><?= date('M d H:i', strtotime($c['calldate'])) ?></small></td>
                                            <td><?= salesos_direction_icon($c['direction']) ?></td>
                                            <td><small><?= e($c['src']) ?></small></td>
                                            <td><small><?= e($c['dst']) ?></small></td>
                                            <td><small><?= salesos_format_duration((int)$c['billsec']) ?></small></td>
                                            <td><?= salesos_disposition_badge($c['disposition']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div><!-- /row -->

            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
const salesosCsrfName = '<?= $this->security->get_csrf_token_name() ?>';
const salesosCsrfHash = '<?= $this->security->get_csrf_hash() ?>';

// Manual CDR sync via AJAX
document.getElementById('btn-sync-cdr')?.addEventListener('click', function(e) {
    e.preventDefault();
    const btn = this;
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Syncing...';
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
        })
        .catch(err => {
            clearTimeout(timer);
            btn.disabled = false;
            btn.innerHTML = original;
            alert_float('danger', err.name === 'AbortError' ? 'CDR sync timed out' : ('Sync failed: ' + err.message));
        });
});
</script>
