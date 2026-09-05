<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
<div class="content">
<div class="row">
<div class="col-md-10 col-md-offset-1">

    <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
        <div>
            <h4 class="tw-text-xl tw-font-bold tw-mb-0">WooConnector</h4>
            <small class="text-muted">
                <?= e($date_from) ?> — <?= e($date_to) ?>
                &nbsp;|&nbsp; Last synced:
                <span class="text-muted"><?= $site && $site['last_synced_at'] ? e($site['last_synced_at']) : 'Never' ?></span>
            </small>
        </div>
        <div class="btn-group">
            <?php if (staff_can('settings', 'wooconnector') && $site): ?>
            <button type="button" class="btn btn-info btn-sm" id="wc-sync-now">
                <i class="fa fa-refresh"></i> Sync Now
            </button>
            <?php endif; ?>
            <a href="<?= admin_url('wooconnector/settings') ?>" class="btn btn-default btn-sm">
                <i class="fa fa-cog"></i> Settings
            </a>
        </div>
    </div>

    <?php if (!$site): ?>
    <div class="alert alert-warning">
        No WooCommerce site configured yet. Go to
        <a href="<?= admin_url('wooconnector/settings') ?>" class="alert-link">Settings</a> to connect one.
    </div>
    <?php endif; ?>

    <div id="wc-cron-warning" class="alert alert-warning" style="<?= $cron['healthy'] ? 'display:none' : '' ?>">
        <strong><i class="fa fa-exclamation-triangle"></i> Automatic sync is not running.</strong>
        <p>
            New orders will only come in when you press <strong>Sync Now</strong> until a real, recurring
            system cron is set up to hit the CRM's cron URL. Add this as a cron job in your hosting panel
            (every 1&ndash;5 minutes):
        </p>
        <pre id="wc-cron-command" class="tw-whitespace-pre-wrap tw-break-all">wget -q -O- <?= e($cron['cron_url']) ?></pre>
        <button type="button" class="btn btn-default btn-sm" id="wc-check-cron">
            <i class="fa fa-refresh"></i> Check
        </button>
        <span id="wc-cron-status-text" class="text-muted"></span>
    </div>

    <div id="wc-sync-result"></div>

    <!-- Date filter -->
    <form method="GET" class="form-inline tw-mb-4">
        <div class="input-group input-group-sm">
            <span class="input-group-addon">From</span>
            <input type="date" name="date_from" class="form-control" value="<?= e($date_from) ?>">
        </div>
        &nbsp;
        <div class="input-group input-group-sm">
            <span class="input-group-addon">To</span>
            <input type="date" name="date_to" class="form-control" value="<?= e($date_to) ?>">
        </div>
        &nbsp;
        <button type="submit" class="btn btn-default btn-sm">Filter</button>
    </form>

    <!-- Stat tiles -->
    <div class="row tw-mb-4">
        <div class="col-md-2 col-sm-4">
            <div class="panel_s tw-p-3 tw-text-center">
                <div class="tw-text-2xl tw-font-bold"><?= (int) $stats['total_orders'] ?></div>
                <div class="text-muted">Total Orders</div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4">
            <div class="panel_s tw-p-3 tw-text-center">
                <div class="tw-text-2xl tw-font-bold"><?= (int) $stats['new_leads'] ?></div>
                <div class="text-muted">New Leads</div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4">
            <div class="panel_s tw-p-3 tw-text-center">
                <div class="tw-text-2xl tw-font-bold"><?= (int) $stats['existing_client'] ?></div>
                <div class="text-muted">Repeat Customers</div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4">
            <div class="panel_s tw-p-3 tw-text-center">
                <div class="tw-text-2xl tw-font-bold"><?= (int) $stats['lost_cancelled'] ?></div>
                <div class="text-muted">Cancelled/Refunded</div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4">
            <div class="panel_s tw-p-3 tw-text-center">
                <div class="tw-text-2xl tw-font-bold"><?= (int) $stats['converted'] ?></div>
                <div class="text-muted">Converted to Customer</div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4">
            <div class="panel_s tw-p-3 tw-text-center">
                <div class="tw-text-2xl tw-font-bold">
                    <?= $stats['total_orders'] > 0 ? round($stats['converted'] / $stats['total_orders'] * 100, 1) : 0 ?>%
                </div>
                <div class="text-muted">Conversion Rate</div>
            </div>
        </div>
    </div>

    <!-- Daily breakdown -->
    <div class="panel_s">
        <div class="panel-body">
            <h4>Orders per Day</h4>
            <?php if (empty($daily)): ?>
            <p class="text-muted">No orders in this date range.</p>
            <?php else: ?>
            <table class="table">
                <thead>
                    <tr><th>Date</th><th>Orders</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($daily as $row): ?>
                    <tr>
                        <td><?= e($row['day']) ?></td>
                        <td><?= (int) $row['orders'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

</div>
</div>
</div>
</div>

<?php init_tail(); ?>

<script>
document.getElementById('wc-sync-now') && document.getElementById('wc-sync-now').addEventListener('click', function() {
    var btn = this;
    var resultEl = document.getElementById('wc-sync-result');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Syncing...';

    fetch('<?= admin_url('wooconnector/sync_now') ?>', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
        body: '<?= $this->security->get_csrf_token_name() ?>=<?= $this->security->get_csrf_hash() ?>'
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            resultEl.innerHTML = '<div class="alert alert-success">Synced — '
                + data.new_lead + ' new lead(s), '
                + data.existing_client + ' repeat customer(s), '
                + data.existing_lead + ' existing lead update(s), '
                + data.status_updated + ' status update(s)'
                + (data.failed > 0 ? ', ' + data.failed + ' failed' : '')
                + '.</div>';
            setTimeout(function() { window.location.reload(); }, 1500);
        } else {
            resultEl.innerHTML = '<div class="alert alert-danger">' + data.error + '</div>';
        }
    })
    .catch(function() {
        resultEl.innerHTML = '<div class="alert alert-danger">Sync request failed.</div>';
    })
    .finally(function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-refresh"></i> Sync Now';
    });
});

document.getElementById('wc-check-cron') && document.getElementById('wc-check-cron').addEventListener('click', function() {
    var btn = this;
    var warningEl = document.getElementById('wc-cron-warning');
    var statusText = document.getElementById('wc-cron-status-text');
    btn.disabled = true;

    fetch('<?= admin_url('wooconnector/check_cron_status') ?>', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.healthy) {
            warningEl.style.display = 'none';
        } else {
            warningEl.style.display = '';
            statusText.textContent = data.seconds_ago === null
                ? ' Cron has never run yet.'
                : ' Last cron run: ' + Math.round(data.seconds_ago / 60) + ' minute(s) ago.';
        }
    })
    .catch(function() {
        statusText.textContent = ' Could not check cron status.';
    })
    .finally(function() {
        btn.disabled = false;
    });
});
</script>
