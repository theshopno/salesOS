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
                                <button type="button" class="btn btn-success" id="btn-sync-catalog">
                                    <i class="fa fa-cubes"></i> Sync Products Catalog
                                </button>
                                <button type="button" class="btn btn-primary" id="btn-sync-now">
                                    <i class="fa fa-refresh"></i> Sync Orders
                                </button>
                            <?php endif; ?>
                            <a href="<?= admin_url('wcsync/settings') ?>" class="btn btn-default">
                                <i class="fa fa-cog"></i> Settings
                            </a>
                        </div>
                        <h4 class="no-margin bold font-medium text-primary">WooCommerce Store Synchronization</h4>
                        <span class="text-muted">Import and synchronize product catalog, variations, inventory and orders from connected WooCommerce shops.</span>
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
                    <div class="col-md-4 col-sm-4">
                        <div class="panel_s">
                            <div class="panel-body text-center">
                                <h3 class="bold no-margin text-primary"><?= count($sites) ?></h3>
                                <span class="text-muted text-uppercase font-medium">Connected Sites</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-4">
                        <div class="panel_s">
                            <div class="panel-body text-center">
                                <h3 class="bold no-margin text-success"><?= $synced_products_count ?? 0 ?></h3>
                                <span class="text-muted text-uppercase font-medium">Synced Products in CRM</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-4">
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
                                                        <td><strong>#<?= e($row['channel_ref_id']) ?></strong></td>
                                                        <td><?= e($row['site_name'] ?? '—') ?></td>
                                                        <td><strong><?= number_format((float) $row['total'], 2) ?> BDT</strong></td>
                                                        <td>
                                                             <?php 
                                                                 $wc_status = strtolower($row['channel_status'] ?? 'pending');
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
                                                        <td><small><?= e($row['created_at']) ?></small></td>
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
<!-- Catalog Sync & Transfer Modal -->
<div class="modal fade" id="catalog-sync-modal" tabindex="-1" role="dialog" data-backdrop="static">
    <div class="modal-dialog modal-lg" role="document" style="width: 90%; max-width: 1050px;">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title bold text-primary">
                    <i class="fa fa-refresh"></i> WooCommerce ক্যাটালগ ও প্রোডাক্ট ট্রান্সফার (Pull & Push Freedom)
                </h4>
            </div>
            <div class="modal-body" style="padding-top: 15px;">
                <!-- Nav tabs -->
                <ul class="nav nav-tabs" role="tablist" style="margin-bottom: 20px;">
                    <li role="presentation" class="active">
                        <a href="#tab-pull-specific" aria-controls="tab-pull-specific" role="tab" data-toggle="tab">
                            <i class="fa fa-check-square-o"></i> নির্দিষ্ট প্রোডাক্ট আনুন (Pull Specific)
                        </a>
                    </li>
                    <li role="presentation">
                        <a href="#tab-pull-all" aria-controls="tab-pull-all" role="tab" data-toggle="tab">
                            <i class="fa fa-cubes"></i> সমস্ত প্রোডাক্ট সিংক (Sync All)
                        </a>
                    </li>
                    <li role="presentation">
                        <a href="#tab-push-wc" aria-controls="tab-push-wc" role="tab" data-toggle="tab">
                            <i class="fa fa-cloud-upload"></i> ওয়েবসাইটে পুশ (Push to WC)
                        </a>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- Tab 1: Pull Specific Products -->
                    <div role="tabpanel" class="tab-pane active" id="tab-pull-specific">
                        <div class="row mbot15">
                            <div class="col-md-7">
                                <div class="input-group">
                                    <input type="text" id="wc-search-query" class="form-control" placeholder="প্রোডাক্টের নাম বা SKU লিখে সার্চ করুন...">
                                    <span class="input-group-btn">
                                        <button type="button" class="btn btn-info" id="btn-search-wc-store">
                                            <i class="fa fa-search"></i> সার্চ
                                        </button>
                                        <button type="button" class="btn btn-default" id="btn-reset-wc-search" title="সব প্রোডাক্ট দেখুন">
                                            <i class="fa fa-refresh"></i>
                                        </button>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-5 text-right">
                                <button type="button" class="btn btn-success" id="btn-import-selected-wc" disabled>
                                    <i class="fa fa-download"></i> সিলেক্টেড (<span id="wc-selected-count">0</span>) সিআরএম-এ আনুন
                                </button>
                            </div>
                        </div>

                        <!-- Alert Box for import result -->
                        <div id="wc-import-alert" style="display: none; margin-bottom: 15px;"></div>

                        <!-- Products Table -->
                        <div class="table-responsive" style="max-height: 380px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 4px;">
                            <table class="table table-bordered table-striped no-mtop" id="wc-products-search-table">
                                <thead style="background: #f8fafc; position: sticky; top: 0; z-index: 2;">
                                    <tr>
                                        <th style="width: 35px; text-align: center;">
                                            <input type="checkbox" id="chk-wc-select-all" title="Select All Visible">
                                        </th>
                                        <th style="width: 50px; text-align: center;">ছবি</th>
                                        <th>প্রোডাক্টের নাম</th>
                                        <th style="width: 140px;">SKU</th>
                                        <th>ক্যাটাগরি</th>
                                        <th style="width: 110px;">মূল্য</th>
                                        <th style="width: 130px; text-align: center;">CRM স্ট্যাটাস</th>
                                    </tr>
                                </thead>
                                <tbody id="wc-products-search-tbody">
                                    <tr>
                                        <td colspan="7" class="text-center text-muted" style="padding: 30px;">
                                            <i class="fa fa-spinner fa-spin"></i> WooCommerce স্টোর থেকে প্রোডাক্ট লোড হচ্ছে...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="row mtop10">
                            <div class="col-md-6 text-muted font-medium" id="wc-search-pagination-info">
                                Loading products...
                            </div>
                            <div class="col-md-6 text-right" id="wc-search-pagination-btns">
                                <button type="button" class="btn btn-default btn-xs" id="btn-wc-prev-page" disabled><i class="fa fa-chevron-left"></i> Previous</button>
                                <span id="wc-current-page-num" style="margin: 0 8px; font-weight: bold;">Page 1</span>
                                <button type="button" class="btn btn-default btn-xs" id="btn-wc-next-page">Next <i class="fa fa-chevron-right"></i></button>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 2: Sync All Products -->
                    <div role="tabpanel" class="tab-pane" id="tab-pull-all">
                        <div class="alert alert-info">
                            <h4><i class="fa fa-info-circle"></i> সম্পূর্ণ স্টোর ক্যাটালগ সিংক</h4>
                            <p>ওয়েবসাইটে থাকা সমস্ত সক্রিয় প্রোডাক্ট, তাদের ভ্যারিয়েশন (রং, সাইজ ইত্যাদি), ছবি ও মূল্য এক ক্লিকে CRM ইনভেন্টরিতে ব্যাকগ্রাউন্ড ব্যাচে সিংক হবে। পূর্বে ইমপোর্ট করা থাকলে ডাটা অটোমেটিক আপডেট হবে।</p>
                        </div>
                        <div class="text-center mbot20">
                            <button type="button" class="btn btn-primary btn-lg" id="btn-start-full-catalog-sync">
                                <i class="fa fa-play"></i> সমস্ত প্রোডাক্ট সিংক শুরু করুন (Start Full Sync)
                            </button>
                        </div>
                        <div id="catalog-sync-progress-container" style="display: none;">
                            <div id="catalog-sync-status-text" class="mbot10 font-medium text-muted">
                                Connecting to WooCommerce store API...
                            </div>
                            <div class="progress" style="height: 24px;">
                                <div id="catalog-sync-progress-bar" class="progress-bar progress-bar-striped active progress-bar-info" role="progressbar" style="width: 0%; font-weight: bold; line-height: 24px;">
                                    0%
                                </div>
                            </div>
                            <div id="catalog-sync-log" style="max-height: 180px; overflow-y: auto; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 4px; padding: 10px; font-family: monospace; font-size: 12px;">
                                <div>[Init] Ready to synchronize store products...</div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 3: Push to WooCommerce -->
                    <div role="tabpanel" class="tab-pane" id="tab-push-wc">
                        <div class="alert alert-warning">
                            <h4><i class="fa fa-cloud-upload"></i> CRM থেকে ওয়েবসাইটে প্রোডাক্ট ও স্টক পুশ</h4>
                            <p>আপনি CRM এ তৈরি করা প্রোডাক্ট অথবা বর্তমান সকল সক্রিয় প্রোডাক্ট এবং তাদের স্টক লেভেল সরাসরি WooCommerce ওয়েবসাইটে পাঠাতে চাইলে এই অপশনটি ব্যবহার করুন।</p>
                            <p class="text-danger"><strong><i class="fa fa-exclamation-triangle"></i> CRM-First স্টক সতর্কতা:</strong> CRM-First নিয়মে যেসকল পণ্য এখনও পারচেজ অর্ডার বা স্টক এডজাস্টমেন্টের মাধ্যমে ইনওয়ার্ড করা হয়নি, তাদের CRM স্টক 0.00। এই পণ্যগুলো পুশ করলে ওয়েবসাইটে তাদের স্টক 0 (Out of Stock) হয়ে যাবে। নিশ্চিত হয়ে পুশ করুন।</p>
                            <p class="text-muted"><i class="fa fa-lightbulb-o"></i> <strong>টিপস:</strong> আপনি <strong>Inventory > Products</strong> পেজে গিয়েও নির্দিষ্ট প্রোডাক্ট সিলেক্ট করে সহজে পুশ করতে পারবেন।</p>
                        </div>
                        <div id="wc-push-alert" style="display: none; margin-bottom: 15px;"></div>
                        <div class="text-center mbot20">
                            <button type="button" class="btn btn-warning btn-lg" id="btn-push-all-wc">
                                <i class="fa fa-cloud-upload"></i> সকল CRM প্রোডাক্ট ওয়েবসাইটে পুশ করুন (Push All Products)
                            </button>
                        </div>
                        <div id="push-progress-container" style="display: none;">
                            <div id="push-status-text" class="mbot10 font-medium text-muted">
                                Preparing to push products to WooCommerce in safe batches...
                            </div>
                            <div class="progress" style="height: 24px;">
                                <div id="push-progress-bar" class="progress-bar progress-bar-striped active progress-bar-warning" role="progressbar" style="width: 0%; font-weight: bold; line-height: 24px;">
                                    0%
                                </div>
                            </div>
                            <div id="push-sync-log" style="max-height: 180px; overflow-y: auto; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 4px; padding: 10px; font-family: monospace; font-size: 12px;">
                                <div>[Init] Ready to push products in chunks to prevent server timeout...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" id="btn-close-catalog-sync" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ── Orders Sync Handler ──────────────────────────────────────────────
    var syncBtn = document.getElementById('btn-sync-now');
    var alertContainer = document.getElementById('sync-alerts');

    if (syncBtn) {
        syncBtn.addEventListener('click', function() {
            syncBtn.disabled = true;
            syncBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Syncing Orders...';
            alertContainer.style.display = 'none';

            $.post('<?= admin_url('wcsync/sync_now') ?>')
            .done(function(response) {
                syncBtn.disabled = false;
                syncBtn.innerHTML = '<i class="fa fa-refresh"></i> Sync Orders';
                alertContainer.style.display = 'block';

                try {
                    var data = typeof response === 'object' ? response : JSON.parse(response);
                    if (data.success) {
                        alertContainer.innerHTML = '<div class="alert alert-success">' +
                            '<strong><i class="fa fa-check-circle"></i> Orders Sync Completed successfully!</strong>' +
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
                            '<strong><i class="fa fa-exclamation-circle"></i> Sync failed:</strong> ' + (data.error || 'Unknown error') +
                            '</div>';
                    }
                } catch (e) {
                    alertContainer.innerHTML = '<div class="alert alert-danger">' +
                        '<strong><i class="fa fa-exclamation-circle"></i> Invalid response format.</strong>' +
                        '</div>';
                }
            })
            .fail(function(xhr) {
                syncBtn.disabled = false;
                syncBtn.innerHTML = '<i class="fa fa-refresh"></i> Sync Orders';
                alertContainer.style.display = 'block';
                alertContainer.innerHTML = '<div class="alert alert-danger">' +
                    '<strong><i class="fa fa-exclamation-circle"></i> Connection error (' + xhr.status + ')</strong>' +
                    '</div>';
            });
        });
    }

    // ── Catalog Sync Modal Trigger ──────────────────────────────────────────
    var catalogSyncBtn = document.getElementById('btn-sync-catalog');
    var modal = $('#catalog-sync-modal');

    if (catalogSyncBtn) {
        catalogSyncBtn.addEventListener('click', function() {
            modal.modal('show');
        });
    }

    // ── Tab 1: Pull Specific Products Logic ─────────────────────────────────
    var wcCurrentPage = 1;
    var wcSearchQuery = '';
    var wcSelectedIds = {};
    var wcLoadedProducts = false;

    modal.on('shown.bs.modal', function() {
        if (!wcLoadedProducts) {
            loadWcStoreProducts(1, '');
        }
    });

    function loadWcStoreProducts(page, q) {
        wcCurrentPage = page;
        wcSearchQuery = q;
        var tbody = document.getElementById('wc-products-search-tbody');
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted" style="padding: 25px;"><i class="fa fa-spinner fa-spin"></i> WooCommerce স্টোর থেকে প্রোডাক্ট তথ্য আনা হচ্ছে...</td></tr>';
        document.getElementById('chk-wc-select-all').checked = false;

        $.get('<?= admin_url('wcsync/search_store_products_ajax') ?>', { page: page, q: q })
        .done(function(raw) {
            wcLoadedProducts = true;
            try {
                var res = typeof raw === 'object' ? raw : JSON.parse(raw);
                if (!res.success) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-danger text-center"><i class="fa fa-exclamation-triangle"></i> ' + (res.error || 'Failed to fetch products') + '</td></tr>';
                    return;
                }

                var products = res.products || [];
                if (products.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted" style="padding: 25px;">কোন প্রোডাক্ট পাওয়া যায়নি।</td></tr>';
                    document.getElementById('wc-search-pagination-info').innerText = '0 products found.';
                    return;
                }

                var html = '';
                products.forEach(function(p) {
                    var imgSrc = p.image || '';
                    var imgHtml = imgSrc ? '<img src="' + imgSrc + '" style="width:32px;height:32px;object-fit:cover;border-radius:4px;">' : '<i class="fa fa-cube text-muted" style="font-size:24px;"></i>';
                    var isChecked = wcSelectedIds[p.id] ? 'checked' : '';
                    var typeBadge = p.type === 'variable' ? '<span class="label label-info" style="font-size:10px;">Variable</span>' : '<span class="label label-default" style="font-size:10px;">Simple</span>';
                    var crmBadge = p.already_in_crm 
                        ? '<span class="label label-success" id="badge-crm-' + p.id + '"><i class="fa fa-check"></i> In CRM</span>'
                        : '<span class="label label-warning" id="badge-crm-' + p.id + '"><i class="fa fa-plus"></i> New</span>';

                    html += '<tr id="wc-row-' + p.id + '">' +
                        '<td style="text-align:center;"><input type="checkbox" class="chk-wc-item" value="' + p.id + '" ' + isChecked + '></td>' +
                        '<td style="text-align:center;">' + imgHtml + '</td>' +
                        '<td><strong>' + p.name + '</strong> ' + typeBadge + '</td>' +
                        '<td><code>' + p.sku + '</code></td>' +
                        '<td><span class="text-muted">' + (p.category || 'Uncategorized') + '</span></td>' +
                        '<td><strong>' + parseFloat(p.price || 0).toFixed(2) + ' BDT</strong></td>' +
                        '<td style="text-align:center;">' + crmBadge + '</td>' +
                        '</tr>';
                });
                tbody.innerHTML = html;

                var total = res.total || products.length;
                document.getElementById('wc-search-pagination-info').innerHTML = 'Total <strong>' + total + '</strong> products available (Page ' + page + ')';
                document.getElementById('wc-current-page-num').innerText = 'Page ' + page;
                document.getElementById('btn-wc-prev-page').disabled = (page <= 1);
                document.getElementById('btn-wc-next-page').disabled = (products.length < 20);

                attachWcCheckboxHandlers();
            } catch (err) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-danger text-center">Parsing error: ' + err.message + '</td></tr>';
            }
        })
        .fail(function(xhr) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-danger text-center">Connection error (' + xhr.status + ')</td></tr>';
        });
    }

    function attachWcCheckboxHandlers() {
        $('.chk-wc-item').off('change').on('change', function() {
            var val = $(this).val();
            if (this.checked) {
                wcSelectedIds[val] = true;
            } else {
                delete wcSelectedIds[val];
            }
            updateWcSelectedCount();
        });
    }

    function updateWcSelectedCount() {
        var count = Object.keys(wcSelectedIds).length;
        document.getElementById('wc-selected-count').innerText = count;
        document.getElementById('btn-import-selected-wc').disabled = (count === 0);
    }

    $('#chk-wc-select-all').on('change', function() {
        var isChecked = this.checked;
        $('.chk-wc-item').each(function() {
            this.checked = isChecked;
            var val = $(this).val();
            if (isChecked) {
                wcSelectedIds[val] = true;
            } else {
                delete wcSelectedIds[val];
            }
        });
        updateWcSelectedCount();
    });

    $('#btn-search-wc-store').on('click', function() {
        var q = document.getElementById('wc-search-query').value.trim();
        loadWcStoreProducts(1, q);
    });

    $('#wc-search-query').on('keypress', function(e) {
        if (e.which === 13) {
            $('#btn-search-wc-store').click();
        }
    });

    $('#btn-reset-wc-search').on('click', function() {
        document.getElementById('wc-search-query').value = '';
        loadWcStoreProducts(1, '');
    });

    $('#btn-wc-prev-page').on('click', function() {
        if (wcCurrentPage > 1) {
            loadWcStoreProducts(wcCurrentPage - 1, wcSearchQuery);
        }
    });

    $('#btn-wc-next-page').on('click', function() {
        loadWcStoreProducts(wcCurrentPage + 1, wcSearchQuery);
    });

    // Import Selected Products Handler
    $('#btn-import-selected-wc').on('click', function() {
        var ids = Object.keys(wcSelectedIds);
        if (ids.length === 0) return;

        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ইমপোর্ট হচ্ছে...');
        var alertBox = $('#wc-import-alert');
        alertBox.hide();

        $.post('<?= admin_url('wcsync/import_specific_products_ajax') ?>', { wc_ids: ids })
        .done(function(raw) {
            btn.prop('disabled', false).html('<i class="fa fa-download"></i> সিলেক্টেড (<span id="wc-selected-count">0</span>) সিআরএম-এ আনুন');
            try {
                var res = typeof raw === 'object' ? raw : JSON.parse(raw);
                if (res.success) {
                    alertBox.html('<div class="alert alert-success"><strong><i class="fa fa-check-circle"></i> সফল হয়েছে!</strong> মোট ' + res.imported + ' টি প্রোডাক্ট সিআরএম ইনভেন্টরিতে ইমপোর্ট ও সিংক হয়েছে।</div>').show();
                    // Update badges
                    ids.forEach(function(id) {
                        var badge = $('#badge-crm-' + id);
                        if (badge.length) {
                            badge.removeClass('label-warning').addClass('label-success').html('<i class="fa fa-check"></i> In CRM');
                        }
                    });
                    wcSelectedIds = {};
                    updateWcSelectedCount();
                    $('.chk-wc-item').prop('checked', false);
                    $('#chk-wc-select-all').prop('checked', false);
                } else {
                    alertBox.html('<div class="alert alert-danger"><strong><i class="fa fa-exclamation-circle"></i> ব্যর্থ হয়েছে:</strong> ' + (res.error || 'Import failed') + '</div>').show();
                }
            } catch (e) {
                alertBox.html('<div class="alert alert-danger">Invalid server response format.</div>').show();
            }
        })
        .fail(function(xhr) {
            btn.prop('disabled', false).html('<i class="fa fa-download"></i> সিলেক্টেড (<span id="wc-selected-count">0</span>) সিআরএম-এ আনুন');
            alertBox.html('<div class="alert alert-danger">Network error (' + xhr.status + ')</div>').show();
        });
    });

    // ── Tab 2: Full Catalog Sync Handler (Batch/Chunked Paging) ─────────────
    var fullSyncBtn = document.getElementById('btn-start-full-catalog-sync');
    var progressContainer = document.getElementById('catalog-sync-progress-container');
    var progressBar = document.getElementById('catalog-sync-progress-bar');
    var statusText = document.getElementById('catalog-sync-status-text');
    var logBox = document.getElementById('catalog-sync-log');
    var closeBtn = document.getElementById('btn-close-catalog-sync');

    if (fullSyncBtn) {
        fullSyncBtn.addEventListener('click', function() {
            fullSyncBtn.disabled = true;
            closeBtn.disabled = true;
            progressContainer.style.display = 'block';
            progressBar.style.width = '5%';
            progressBar.innerText = '5%';
            progressBar.className = 'progress-bar progress-bar-striped active progress-bar-info';
            statusText.innerText = 'Fetching page 1 from WooCommerce...';
            logBox.innerHTML = '<div>[Start] Requesting catalog from WooCommerce store...</div>';

            var totalProcessed = 0;
            var totalCreated = 0;
            var totalUpdated = 0;

            function syncPage(page) {
                $.post('<?= admin_url('wcsync/sync_catalog_ajax') ?>', { page: page })
                .done(function(raw) {
                    try {
                        var res = typeof raw === 'object' ? raw : JSON.parse(raw);
                        if (!res.success) {
                            statusText.innerText = 'Sync failed: ' + (res.error || 'Unknown error');
                            progressBar.className = 'progress-bar progress-bar-danger';
                            logBox.innerHTML += '<div class="text-danger">[Error] ' + (res.error || 'Error') + '</div>';
                            closeBtn.disabled = false;
                            fullSyncBtn.disabled = false;
                            return;
                        }

                        totalProcessed += (res.processed || 0);
                        totalCreated += (res.created || 0);
                        totalUpdated += (res.updated || 0);

                        var pct = Math.min(100, Math.round((res.page / res.total_pages) * 100));
                        progressBar.style.width = pct + '%';
                        progressBar.innerText = pct + '%';

                        logBox.innerHTML += '<div>[Page ' + res.page + '/' + res.total_pages + '] Processed ' + res.processed + ' products (' + res.created + ' created, ' + res.updated + ' updated)</div>';
                        logBox.scrollTop = logBox.scrollHeight;

                        if (res.page < res.total_pages) {
                            statusText.innerText = 'Syncing page ' + (res.page + 1) + ' of ' + res.total_pages + '...';
                            syncPage(res.page + 1);
                        } else {
                            progressBar.className = 'progress-bar progress-bar-success';
                            statusText.innerHTML = '<strong class="text-success"><i class="fa fa-check-circle"></i> Synchronization Complete!</strong> Total ' + totalProcessed + ' products synchronized (' + totalCreated + ' new, ' + totalUpdated + ' updated).';
                            logBox.innerHTML += '<div class="text-success bold">[Done] All products & variations are now synchronized with CRM Inventory!</div>';
                            logBox.scrollTop = logBox.scrollHeight;
                            closeBtn.disabled = false;
                            closeBtn.className = 'btn btn-primary';
                            closeBtn.onclick = function() { location.reload(); };
                        }
                    } catch (err) {
                        statusText.innerText = 'Parse error: ' + err.message;
                        progressBar.className = 'progress-bar progress-bar-danger';
                        closeBtn.disabled = false;
                        fullSyncBtn.disabled = false;
                    }
                })
                .fail(function(xhr) {
                    statusText.innerText = 'Network error (' + xhr.status + ')';
                    progressBar.className = 'progress-bar progress-bar-danger';
                    logBox.innerHTML += '<div class="text-danger">[HTTP ' + xhr.status + '] ' + xhr.responseText + '</div>';
                    closeBtn.disabled = false;
                    fullSyncBtn.disabled = false;
                });
            }

            syncPage(1);
        });
    }

    // ── Tab 3: Push All Products to WooCommerce Handler (Chunked Safe Batches) ─
    var pushAllBtn = document.getElementById('btn-push-all-wc');
    var pushAlertBox = $('#wc-push-alert');
    var pushProgressContainer = document.getElementById('push-progress-container');
    var pushProgressBar = document.getElementById('push-progress-bar');
    var pushStatusText = document.getElementById('push-status-text');
    var pushLogBox = document.getElementById('push-sync-log');

    if (pushAllBtn) {
        pushAllBtn.addEventListener('click', function() {
            var confirmMsg = "সতর্কতা: CRM-First নিয়ম অনুযায়ী পণ্য CRM-এ স্টক-ইন (Purchase Order / Stock Adjustment) না করা থাকলে বর্তমান CRM স্টক 0.00 হিসেবে ওয়েবসাইটে পুশ হবে এবং ওয়েবসাইটে পণ্যটি Out of Stock হয়ে যেতে পারে।\n\nআপনি কি নিশ্চিত যে CRM-এর সক্রিয় পণ্যগুলো ওয়েবসাইটে নিরাপদ ব্যাচে (Chunked Sync) পুশ করতে চান?";
            if (!confirm(confirmMsg)) {
                return;
            }

            pushAllBtn.disabled = true;
            closeBtn.disabled = true;
            pushAlertBox.hide();
            pushProgressContainer.style.display = 'block';
            pushProgressBar.style.width = '5%';
            pushProgressBar.innerText = '5%';
            pushProgressBar.className = 'progress-bar progress-bar-striped active progress-bar-warning';
            pushStatusText.innerText = 'Pushing batch 1 to WooCommerce...';
            pushLogBox.innerHTML = '<div>[Start] Starting chunked product push to prevent 504 Gateway Timeout...</div>';

            var totalPushed = 0;
            var totalFailed = 0;
            var perPage = 10;

            function pushPage(page) {
                $.post('<?= admin_url('wcsync/push_chunk_ajax') ?>', { page: page, per_page: perPage })
                .done(function(raw) {
                    try {
                        var res = typeof raw === 'object' ? raw : JSON.parse(raw);
                        if (!res.success) {
                            pushStatusText.innerText = 'Push failed: ' + (res.error || 'Unknown error');
                            pushProgressBar.className = 'progress-bar progress-bar-danger';
                            pushLogBox.innerHTML += '<div class="text-danger">[Error] ' + (res.error || 'Push Error') + '</div>';
                            closeBtn.disabled = false;
                            pushAllBtn.disabled = false;
                            return;
                        }

                        totalPushed += (res.chunk_pushed || 0);
                        totalFailed += (res.chunk_failed || 0);

                        var pct = Math.min(100, Math.round((res.page / res.total_pages) * 100));
                        pushProgressBar.style.width = pct + '%';
                        pushProgressBar.innerText = pct + '%';

                        pushLogBox.innerHTML += '<div>[Batch ' + res.page + '/' + res.total_pages + '] Pushed ' + (res.chunk_pushed || 0) + ' items (' + (res.chunk_failed || 0) + ' failed). Progress: ' + res.processed + '/' + res.total_items + '</div>';
                        pushLogBox.scrollTop = pushLogBox.scrollHeight;

                        if (!res.is_done && res.page < res.total_pages) {
                            pushStatusText.innerText = 'Pushing batch ' + (res.page + 1) + ' of ' + res.total_pages + '...';
                            pushPage(res.page + 1);
                        } else {
                            pushProgressBar.className = 'progress-bar progress-bar-success';
                            pushStatusText.innerHTML = '<strong class="text-success"><i class="fa fa-check-circle"></i> Push Complete!</strong> Total ' + totalPushed + ' products successfully pushed' + (totalFailed > 0 ? ' (' + totalFailed + ' failed)' : '') + '.';
                            pushLogBox.innerHTML += '<div class="text-success bold">[Done] All CRM products are now synchronized with WooCommerce catalog!</div>';
                            pushLogBox.scrollTop = pushLogBox.scrollHeight;
                            closeBtn.disabled = false;
                            pushAllBtn.disabled = false;
                            pushAllBtn.innerHTML = '<i class="fa fa-check"></i> সম্পূর্ণ পুশ সম্পন্ন হয়েছে';
                        }
                    } catch (err) {
                        pushStatusText.innerText = 'Parse error: ' + err.message;
                        pushProgressBar.className = 'progress-bar progress-bar-danger';
                        closeBtn.disabled = false;
                        pushAllBtn.disabled = false;
                    }
                })
                .fail(function(xhr) {
                    pushStatusText.innerText = 'Network error (' + xhr.status + ')';
                    pushProgressBar.className = 'progress-bar progress-bar-danger';
                    pushLogBox.innerHTML += '<div class="text-danger">[HTTP ' + xhr.status + '] ' + xhr.responseText + '</div>';
                    closeBtn.disabled = false;
                    pushAllBtn.disabled = false;
                });
            }

            pushPage(1);
        });
    }
});
</script>


