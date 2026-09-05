<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <!-- Header -->
                <div class="row mbot20">
                    <div class="col-md-12">
                        <div class="pull-right">
                            <?php if (staff_can('sync', 'wcsync') && !empty($sites)): ?>
                                <button type="button" class="btn btn-primary" id="btn-sync-now">
                                    <i class="fa fa-refresh"></i> Sync Now
                                </button>
                            <?php endif; ?>
                            <a href="<?= admin_url('wcsync/settings') ?>" class="btn btn-default">
                                <i class="fa fa-cog"></i> Settings
                            </a>
                        </div>
                        <h4 class="no-margin bold font-medium text-primary">WooCommerce Orders Sync</h4>
                        <span class="text-muted">Import and synchronize orders from connected WooCommerce shops.</span>
                    </div>
                </div>
                <hr class="hr-panel-heading" />

                <!-- Warning if no sites -->
                <?php if (empty($sites)): ?>
                    <div class="alert alert-warning">
                        <strong><i class="fa fa-exclamation-triangle"></i> No WooCommerce sites connected.</strong>
                        <p>Configure a connection first to sync orders.</p>
                        <div style="margin-top:10px;">
                            <a href="<?= admin_url('wcsync/settings') ?>" class="btn btn-warning btn-sm">Configure WooCommerce Sites</a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Sync Alerts Container -->
                <div id="sync-alerts" style="display: none; margin-bottom: 20px;"></div>

                <!-- Stats Tiles (Native Perfex Style) -->
                <div class="row mbot15">
                    <div class="col-md-6 col-sm-6">
                        <div class="panel_s">
                            <div class="panel-body text-center">
                                <h3 class="bold no-margin text-primary"><?= count($sites) ?></h3>
                                <span class="text-muted text-uppercase font-medium">Connected Sites</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-sm-6">
                        <div class="panel_s">
                            <div class="panel-body text-center">
                                <h3 class="bold no-margin text-info"><?= array_sum($stats) ?></h3>
                                <span class="text-muted text-uppercase font-medium">Synced Orders</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Row -->
                <div class="row">
                    <!-- Configured Sites Status -->
                    <div class="col-md-4">
                        <div class="panel_s">
                            <div class="panel-body">
                                <h4 class="no-margin font-medium"><i class="fa fa-desktop"></i> Connected Stores</h4>
                                <hr class="hr-panel-heading" />
                                
                                <?php if (empty($sites)): ?>
                                    <p class="text-muted no-margin">No stores configured.</p>
                                <?php else: ?>
                                    <ul class="list-group no-margin">
                                        <?php foreach ($sites as $site): ?>
                                            <li class="list-group-item">
                                                <span class="badge"><?= isset($stats[$site['id']]) ? $stats[$site['id']] : 0 ?> orders</span>
                                                <strong><?= e($site['name']) ?></strong>
                                                <br><small class="text-muted"><?= e($site['site_url']) ?></small>
                                                <div class="mtop10 text-muted" style="font-size: 11px;">
                                                    Last Synced: <?= $site['last_synced_at'] ? e($site['last_synced_at']) : 'Never' ?>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Sync History -->
                    <div class="col-md-8">
                        <div class="panel_s">
                            <div class="panel-body">
                                <h4 class="no-margin font-medium"><i class="fa fa-history"></i> Recent Synced Orders</h4>
                                <hr class="hr-panel-heading" />
                                
                                <?php if (empty($recent_syncs)): ?>
                                    <p class="text-muted no-margin">No order sync history found.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped no-mtop">
                                            <thead>
                                                <tr>
                                                    <th>WC ID</th>
                                                    <th>Store</th>
                                                    <th>Total</th>
                                                    <th>Status</th>
                                                    <th>Sync Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_syncs as $row): ?>
                                                    <tr>
                                                        <td><strong>#<?= e($row['wc_order_id']) ?></strong></td>
                                                        <td><?= e($row['site_name']) ?></td>
                                                        <td><strong><?= number_format($row['eco_total'], 2) ?> BDT</strong></td>
                                                        <td>
                                                             <?php 
                                                                 $wc_status = strtolower($row['wc_status'] ?? 'pending');
                                                                 $status_class = 'default';
                                                                 if ($wc_status === 'completed') $status_class = 'success';
                                                                 elseif ($wc_status === 'processing') $status_class = 'info';
                                                                 elseif ($wc_status === 'pending' || $wc_status === 'on-hold') $status_class = 'warning';
                                                                 elseif (in_array($wc_status, ['cancelled', 'refunded', 'failed'])) $status_class = 'danger';
                                                             ?>
                                                             <span class="label label-<?= $status_class ?>">
                                                                 <?= strtoupper(e($wc_status)) ?>
                                                             </span>
                                                        </td>
                                                        <td><small><?= e($row['synced_at']) ?></small></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var syncBtn = document.getElementById('btn-sync-now');
    var alertContainer = document.getElementById('sync-alerts');

    if (syncBtn) {
        syncBtn.addEventListener('click', function() {
            syncBtn.disabled = true;
            syncBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Syncing...';
            alertContainer.style.display = 'none';

            $.post('<?= admin_url('wcsync/sync_now') ?>')
            .done(function(response) {
                syncBtn.disabled = false;
                syncBtn.innerHTML = '<i class="fa fa-refresh"></i> Sync Now';
                alertContainer.style.display = 'block';

                try {
                    var data = typeof response === 'object' ? response : JSON.parse(response);
                    if (data.success) {
                        alertContainer.innerHTML = '<div class="alert alert-success">' +
                            '<strong><i class="fa fa-check-circle"></i> Sync Completed successfully!</strong>' +
                            '<ul>' +
                            '<li>New Orders Synced: ' + data.new_order + '</li>' +
                            '<li>Statuses Updated: ' + data.status_updated + '</li>' +
                            '<li>Unchanged: ' + data.unchanged + '</li>' +
                            '<li>Failed: ' + data.failed + '</li>' +
                            '</ul>' +
                            '<p><a href="javascript:location.reload();" class="alert-link">Refresh Page</a> to see updates.</p>' +
                            '</div>';
                    } else {
                        alertContainer.innerHTML = '<div class="alert alert-danger">' +
                            '<strong><i class="fa fa-exclamation-circle"></i> Sync failed:</strong> ' + data.error +
                            '</div>';
                    }
                } catch (e) {
                    alertContainer.innerHTML = '<div class="alert alert-danger">' +
                        '<strong><i class="fa fa-exclamation-circle"></i> Invalid response format received from server.</strong>' +
                        '<pre style="margin-top:10px; text-align:left; background:#fff; color:#333; padding:10px; border:1px solid #ddd;">' + response + '</pre>' +
                        '</div>';
                }
            })
            .fail(function(xhr, status, error) {
                syncBtn.disabled = false;
                syncBtn.innerHTML = '<i class="fa fa-refresh"></i> Sync Now';
                alertContainer.style.display = 'block';
                alertContainer.innerHTML = '<div class="alert alert-danger">' +
                    '<strong><i class="fa fa-exclamation-circle"></i> Connection error (' + xhr.status + '):</strong> ' + xhr.responseText +
                    '</div>';
            });
        });
    }
});
</script>
