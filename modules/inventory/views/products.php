<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<?php
    $industry_labels = [
        'gadgets' => ['icon' => 'fa fa-mobile', 'title' => 'গ্যাজেট ও ইলেকট্রনিক্স (Gadgets & Electronics)', 'desc' => 'ওয়ারেন্টি ও IMEI/সিরিয়াল ট্র্যাকিং'],
        'fashion' => ['icon' => 'fa fa-tags', 'title' => 'ফ্যাশন ও পোশাক (Fashion & Apparel)', 'desc' => 'সাইজ ও কালার ভ্যারিয়েশন ম্যাট্রিক্স'],
        'grocery' => ['icon' => 'fa fa-shopping-basket', 'title' => 'মুদি ও খাদ্যপণ্য (Grocery & FMCG)', 'desc' => 'UOM (কেজি/লিটার) ও মেয়াদোত্তীর্ণ তারিখ'],
        'general' => ['icon' => 'fa fa-cubes', 'title' => 'সাধারণ রিটেল (General Retail)', 'desc' => 'স্ট্যান্ডার্ড প্রোডাক্ট ও কাস্টম স্পেসিফিকেশন'],
    ];
    $current_ind = $industry_labels[$industry_mode ?? 'gadgets'] ?? $industry_labels['gadgets'];

    $total_products = count($products ?? []);
    $in_stock_count = 0;
    $out_of_stock_count = 0;
    $wc_synced_count = 0;

    foreach (($products ?? []) as $p) {
        $stk = (float)($p['stock_on_hand'] ?? 0);
        if ($stk > 0) {
            $in_stock_count++;
        } else {
            $out_of_stock_count++;
        }
        if (!empty($p['external_platform']) && $p['external_platform'] === 'woocommerce') {
            $wc_synced_count++;
        }
    }

    $remove_decimals = (get_option('remove_decimals_on_zero') == 1);
    $fmt_smart = function($val) use ($remove_decimals) {
        if ($val === null || $val === '') return '-';
        $n = (float)$val;
        if ($remove_decimals && floor($n) == $n) {
            return number_format($n, 0);
        }
        return number_format($n, 2);
    };
?>

<style>
/* ── Modern Products Catalog Layout ── */
.inv-header-wrap {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 20px;
}
.inv-header-title h4 {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.inv-header-title .sub-title {
    color: #64748b;
    font-size: 13px;
    margin-top: 3px;
    display: block;
}
.inv-header-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.inv-header-actions .btn {
    font-weight: 600;
    border-radius: 6px;
    padding: 7px 14px;
    font-size: 13px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

/* KPI Summary Cards */
.kpi-metric-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 14px 16px;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 100%;
}
.kpi-metric-card:hover, .kpi-metric-card.active-kpi {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.08);
}
.kpi-metric-card.active-kpi {
    border-color: #2563eb !important;
    background: #f0f9ff !important;
}
.kpi-metric-card .kpi-lbl {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.kpi-metric-card .kpi-val {
    font-size: 24px;
    font-weight: 800;
    line-height: 1.2;
    margin-top: 2px;
}
.kpi-metric-card .kpi-sub {
    font-size: 11px;
    margin-top: 3px;
    display: block;
}
.kpi-metric-card .kpi-icon-box {
    width: 44px;
    height: 44px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

/* Filter Toolbar */
.inv-filter-toolbar {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 15px;
}
.inv-filter-toolbar .form-control {
    border-radius: 6px;
    border-color: #cbd5e1;
    height: 38px;
    font-size: 13px;
}
.inv-filter-toolbar .form-control:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59,130,246,0.15);
}

/* Table Styling */
#products-table {
    margin-bottom: 0;
    border-color: #e2e8f0;
}
#products-table thead th {
    background: #f8fafc;
    color: #334155;
    font-weight: 700;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    border-bottom: 2px solid #cbd5e1;
    padding: 12px 10px;
    vertical-align: middle;
}
#products-table tbody td {
    vertical-align: middle !important;
    padding: 10px;
    font-size: 13px;
    border-color: #f1f5f9;
}
#products-table tbody tr:hover {
    background-color: #f8fafc;
}

/* Thumbnails */
.prod-thumb-img {
    width: 38px;
    height: 38px;
    border-radius: 6px;
    object-fit: cover;
    border: 1px solid #e2e8f0;
    vertical-align: middle;
    margin-right: 10px;
    flex-shrink: 0;
}
.prod-thumb-placeholder {
    width: 38px;
    height: 38px;
    border-radius: 6px;
    background: #f1f5f9;
    color: #94a3b8;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-right: 10px;
    font-size: 16px;
    border: 1px solid #e2e8f0;
    flex-shrink: 0;
}

/* SKU Badge */
.sku-badge {
    font-family: monospace;
    font-size: 12px;
    font-weight: 700;
    background: #f1f5f9;
    color: #1e293b;
    padding: 3px 7px;
    border-radius: 5px;
    border: 1px solid #e2e8f0;
    display: inline-block;
}

/* Actions cell and buttons */
.prod-actions-cell {
    white-space: nowrap !important;
    text-align: center;
    width: 120px;
}
.prod-actions-wrap {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    gap: 5px;
    white-space: nowrap !important;
}
.prod-actions-wrap .btn {
    padding: 5px 8px;
    font-size: 12px;
    border-radius: 5px;
    line-height: 1;
    transition: transform 0.15s;
}
.prod-actions-wrap .btn:hover {
    transform: scale(1.08);
}

/* Pagination bar */
.inv-pagination-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    padding: 14px 5px 5px 5px;
}
.inv-pagination-bar .pagination {
    margin: 0;
}
.inv-pagination-bar .pagination > li > a {
    border-radius: 4px;
    margin: 0 2px;
    color: #334155;
    font-weight: 600;
    font-size: 12px;
    cursor: pointer;
}
.inv-pagination-bar .pagination > .active > a {
    background-color: #2563eb;
    border-color: #2563eb;
    color: #fff;
}
</style>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        
                        <!-- Header with Flex Alignment -->
                        <div class="inv-header-wrap">
                            <div class="inv-header-title">
                                <h4>
                                    <i class="fa fa-cubes text-primary"></i> Products Catalog &amp; Smart Inventory
                                    <span class="label" style="background: #e0f2fe; color: #0369a1; border: 1px solid #7dd3fc; font-weight: 700; font-size: 12px; padding: 5px 12px; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px;">
                                        <i class="<?= $current_ind['icon'] ?>" style="color: #0284c7;"></i> <?= $current_ind['title'] ?>
                                    </span>
                                </h4>
                                <span class="sub-title">Manage SKU-tracked products, variations, prices, and website synchronized catalog.</span>
                            </div>
                            <div class="inv-header-actions">
                                <?php if (staff_can('create', 'inventory')): ?>
                                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#product-modal" id="btn-add-product">
                                        <i class="fa fa-plus"></i> Add New Product
                                    </button>
                                <?php endif; ?>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-info dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="fa fa-shopping-cart"></i> WooCommerce <span class="caret"></span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-right">
                                        <li>
                                            <a href="javascript:void(0);" id="btn-header-push-all-wc">
                                                <i class="fa fa-cloud-upload text-primary"></i> সকল প্রোডাক্ট ওয়েবসাইটে পুশ করুন (Push All)
                                            </a>
                                        </li>
                                        <li>
                                            <a href="<?= admin_url('wcsync') ?>">
                                                <i class="fa fa-download text-success"></i> ওয়েবসাইট থেকে প্রোডাক্ট আনুন (Pull from WC)
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                                <?php if ($CI->app_modules->is_active('salesos')): ?>
                                <a href="<?= admin_url('salesos/settings?tab=general') ?>" class="btn btn-default" title="Change Business Profile &amp; Inventory Settings">
                                    <i class="fa fa-cog text-muted"></i> Profile &amp; Settings
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Top KPI Summary Cards -->
                        <div class="row mbot15">
                            <div class="col-md-3 col-sm-6 mbot10">
                                <div class="kpi-metric-card active-kpi" id="kpi-card-all" data-stock="all" title="সকল প্রোডাক্ট দেখুন">
                                    <div>
                                        <div class="kpi-lbl" style="color: #64748b;">মোট প্রোডাক্ট (Total)</div>
                                        <div class="kpi-val" style="color: #0f172a;"><?= $total_products ?></div>
                                        <span class="kpi-sub text-muted">ক্যাটালগ আইটেম</span>
                                    </div>
                                    <div class="kpi-icon-box" style="background: #f1f5f9; color: #3b82f6;">
                                        <i class="fa fa-cubes"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6 mbot10">
                                <div class="kpi-metric-card" id="kpi-card-in-stock" data-stock="in_stock" title="শুধুমাত্র ইন-স্টক প্রোডাক্ট দেখুন">
                                    <div>
                                        <div class="kpi-lbl" style="color: #15803d;">ইন-স্টক (In Stock)</div>
                                        <div class="kpi-val" style="color: #16a34a;"><?= $in_stock_count ?></div>
                                        <span class="kpi-sub text-muted">POS ও সেল রেডি</span>
                                    </div>
                                    <div class="kpi-icon-box" style="background: #dcfce7; color: #16a34a;">
                                        <i class="fa fa-check-circle"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6 mbot10">
                                <div class="kpi-metric-card" id="kpi-card-out-of-stock" data-stock="out_of_stock" title="স্টক শূন্য প্রোডাক্ট ফিল্টার করুন">
                                    <div>
                                        <div class="kpi-lbl" style="color: #b91c1c;">স্টক শূন্য (0 Stock)</div>
                                        <div class="kpi-val" style="color: #dc2626;"><?= $out_of_stock_count ?></div>
                                        <span class="kpi-sub text-muted">CRM-First লকড (বিক্রি নিষিদ্ধ)</span>
                                    </div>
                                    <div class="kpi-icon-box" style="background: #fee2e2; color: #dc2626;">
                                        <i class="fa fa-ban"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6 mbot10">
                                <div class="kpi-metric-card" id="kpi-card-wc" data-stock="wc" title="WooCommerce সিংকড প্রোডাক্ট দেখুন">
                                    <div>
                                        <div class="kpi-lbl" style="color: #7e22ce;">WooCommerce সিংকড</div>
                                        <div class="kpi-val" style="color: #7c3aed;"><?= $wc_synced_count ?></div>
                                        <span class="kpi-sub text-muted">ওয়েবসাইট স্টোরে লাইভ</span>
                                    </div>
                                    <div class="kpi-icon-box" style="background: #f3e8ff; color: #7c3aed;">
                                        <i class="fa fa-shopping-cart"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Instant Filter & Search Toolbar -->
                        <div class="inv-filter-toolbar">
                            <div class="row">
                                <div class="col-md-4 col-sm-6 mbot5">
                                    <div class="input-group">
                                        <span class="input-group-addon" style="background: #fff; border-right: none; color: #94a3b8;">
                                            <i class="fa fa-search"></i>
                                        </span>
                                        <input type="text" id="catalog-search" class="form-control" placeholder="প্রোডাক্ট নাম, SKU বা বারকোড খুঁজুন..." style="border-left: none;">
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6 mbot5">
                                    <select id="filter-category" class="form-control">
                                        <option value="">সকল ক্যাটাগরি (All Categories)</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= htmlspecialchars(strtolower($cat['name']), ENT_QUOTES) ?>"><?= e($cat['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 col-sm-6 mbot5">
                                    <select id="filter-stock" class="form-control">
                                        <option value="all">সকল স্টক স্ট্যাটাস (All Stock)</option>
                                        <option value="in_stock">ইন-স্টক (> 0)</option>
                                        <option value="out_of_stock">স্টক শূন্য (0 Stock)</option>
                                        <option value="wc">WooCommerce সিংকড</option>
                                    </select>
                                </div>
                                <div class="col-md-2 col-sm-6 text-right mbot5">
                                    <button type="button" id="btn-reset-filters" class="btn btn-default btn-block" style="height: 38px; font-weight: 600;" title="ফিল্টার রিসেট করুন">
                                        <i class="fa fa-refresh text-muted"></i> রিসেট
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Bulk Push to WooCommerce Toolbar -->
                        <div id="bulk-push-toolbar" class="alert alert-info" style="display: none; margin-bottom: 15px; padding: 10px 18px; border-left: 5px solid #0284c7; background: #e0f2fe; color: #0369a1; border-radius: 6px;">
                            <div class="row" style="display: flex; align-items: center; flex-wrap: wrap;">
                                <div class="col-md-7 col-sm-12" style="padding-top: 2px;">
                                    <strong><i class="fa fa-check-circle"></i> <span id="selected-prod-count" style="font-size: 15px; font-weight: 700;">0</span> টি প্রোডাক্ট সিলেক্ট করা হয়েছে</strong>
                                    <span class="text-muted" style="margin-left: 8px;">(সিলেক্টেড প্রোডাক্টগুলো সরাসরি WooCommerce স্টোরে পুশ ও স্টক সিংক হবে)</span>
                                </div>
                                <div class="col-md-5 col-sm-12 text-right">
                                    <button type="button" class="btn btn-success btn-sm" id="btn-push-bulk-wc" style="font-weight: 600;">
                                        <i class="fa fa-cloud-upload"></i> সিলেক্টেড প্রোডাক্ট পুশ করুন (Push Selected)
                                    </button>
                                    <button type="button" class="btn btn-default btn-sm" id="btn-clear-selection">
                                        সিলেকশন বাতিল
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Catalog Table -->
                        <?php if (empty($products)): ?>
                            <div class="alert alert-info text-center" style="padding: 30px;">
                                <i class="fa fa-cubes fa-3x text-muted mbot15"></i>
                                <h4>কোনো প্রোডাক্ট পাওয়া যায়নি</h4>
                                <p class="text-muted">ক্যাটালগে নতুন প্রোডাক্ট যোগ করতে উপরের "+ Add New Product" বাটনে ক্লিক করুন অথবা WooCommerce থেকে প্রোডাক্ট আনুন।</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive" style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                                <table class="table table-bordered table-striped no-mtop" id="products-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 38px; text-align: center;">
                                                <input type="checkbox" id="chk-select-all-prods" title="Select All">
                                            </th>
                                            <th style="width: 140px;">SKU / Code</th>
                                            <th>Product Name</th>
                                            <th style="width: 130px;">Category</th>
                                            <th style="width: 120px;">Selling Price</th>
                                            <th style="width: 110px; text-align: center;">Stock on Hand</th>
                                            <th style="width: 85px; text-align: center;">Reorder</th>
                                            <th style="width: 80px; text-align: center;">Status</th>
                                            <th style="width: 120px; text-align: center; white-space: nowrap;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="products-table-body">
                                        <?php foreach ($products as $prod): ?>
                                            <?php 
                                                $stock = (float) $prod['stock_on_hand'];
                                                $reserved = (float) ($prod['stock_reserved'] ?? 0);
                                                $available = (float) ($prod['stock_available'] ?? max(0, $stock - $reserved));
                                                $reorder = (float) $prod['reorder_level'];
                                                $is_variable = ($prod['product_type'] === 'variable' || (int)($prod['variation_count'] ?? 0) > 0);
                                                
                                                $label_class = 'success';
                                                if ($available <= 0) {
                                                    $label_class = 'danger';
                                                } elseif ($available <= $reorder) {
                                                    $label_class = 'warning';
                                                }
                                            ?>
                                            <tr id="prod-row-<?= $prod['id'] ?>" 
                                                class="prod-data-row"
                                                data-name="<?= htmlspecialchars(strtolower($prod['name']), ENT_QUOTES) ?>"
                                                data-sku="<?= htmlspecialchars(strtolower($prod['sku'] ?? ''), ENT_QUOTES) ?>"
                                                data-barcode="<?= htmlspecialchars(strtolower($prod['barcode'] ?? ''), ENT_QUOTES) ?>"
                                                data-category="<?= htmlspecialchars(strtolower($prod['category_name'] ?: 'uncategorized'), ENT_QUOTES) ?>"
                                                data-stock-status="<?= $available > 0 ? 'in_stock' : 'out_of_stock' ?>"
                                                data-wc="<?= ($prod['external_platform'] === 'woocommerce') ? '1' : '0' ?>"
                                                data-type="<?= $is_variable ? 'variable' : 'simple' ?>">
                                                
                                                <td style="text-align: center;">
                                                    <input type="checkbox" class="chk-prod-row" value="<?= $prod['id'] ?>" data-name="<?= htmlspecialchars($prod['name'], ENT_QUOTES) ?>">
                                                </td>
                                                <td>
                                                    <span class="sku-badge"><?= e($prod['sku'] ?: '-') ?></span>
                                                    <?php if (!empty($prod['barcode'])): ?>
                                                        <br><small class="text-muted" style="font-size: 11px;"><i class="fa fa-barcode"></i> <?= e($prod['barcode']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div style="display: flex; align-items: center;">
                                                        <?php if (!empty($prod['image'])): ?>
                                                            <?php 
                                                                $img_src = (strpos($prod['image'], 'http://') === 0 || strpos($prod['image'], 'https://') === 0 || strpos($prod['image'], '/') === 0) 
                                                                    ? $prod['image'] 
                                                                    : base_url($prod['image']);
                                                            ?>
                                                            <img src="<?= e($img_src) ?>" alt="<?= e($prod['name']) ?>" class="prod-thumb-img" />
                                                        <?php else: ?>
                                                            <div class="prod-thumb-placeholder"><i class="fa fa-cube"></i></div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <strong style="color: #1e293b; font-size: 13px;"><?= e($prod['name']) ?></strong>
                                                            
                                                            <?php if ($prod['external_platform'] === 'woocommerce'): ?>
                                                                <span class="label" title="Synced with WooCommerce" style="font-size: 11px; margin-left: 6px; background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; font-weight: 700; padding: 2px 7px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;">
                                                                    <i class="fa fa-shopping-cart" style="color: #2563eb;"></i> WC
                                                                </span>
                                                            <?php endif; ?>

                                                            <?php if ($is_variable): ?>
                                                                <div class="mtop5">
                                                                    <button type="button" class="btn btn-default btn-xs btn-toggle-variations" data-id="<?= $prod['id'] ?>" style="font-size: 11px; background: #f8fafc; border-color: #cbd5e1; color: #1e293b; font-weight: 600;">
                                                                        <i class="fa fa-sitemap text-primary"></i> <span class="badge" style="background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; font-size: 11px; font-weight: 700; padding: 2px 6px;"><?= (int)$prod['variation_count'] ?></span> Variations <i class="fa fa-chevron-down" style="font-size: 9px; margin-left: 2px;"></i>
                                                                    </button>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="label label-default" style="font-size: 11px; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;"><?= e($prod['category_name'] ?: 'Uncategorized') ?></span>
                                                </td>
                                                <td>
                                                    <div style="font-weight: 700; color: #0f172a; font-size: 13px;"><?= $fmt_smart($prod['rate'] ?? 0) ?> <span style="font-size: 10px; font-weight: normal; color: #64748b;">BDT</span></div>
                                                    <small class="text-muted" style="font-size: 11px;">/ <?= e($prod['uom'] ?: 'pc') ?></small>
                                                </td>
                                                <td style="text-align: center;">
                                                    <?php if ($stock > 0): ?>
                                                        <span class="label" style="background: #dcfce7; color: #166534; border: 1px solid #86efac; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 9999px; display: inline-flex; align-items: center; gap: 4px;" title="হাতে থাকা মোট স্টক (On Hand)">
                                                            <i class="fa fa-cube" style="color: #15803d;"></i> <?= $fmt_smart($stock) ?>
                                                        </span>
                                                        <?php if ($reserved > 0): ?>
                                                            <div style="margin-top: 4px;">
                                                                <span class="label" style="background: #fef3c7; color: #92400e; border: 1px solid #fde68a; font-size: 10px; font-weight: 600; padding: 2px 6px; border-radius: 4px;" title="আনকনফার্মড অর্ডারে রিজার্ভকৃত">
                                                                    <i class="fa fa-lock"></i> রিজার্ভ: <?= $fmt_smart($reserved) ?>
                                                                </span>
                                                            </div>
                                                            <div style="margin-top: 3px; font-size: 11px; font-weight: 700; color: #0284c7;" title="বিক্রয়যোগ্য ফাঁকা স্টক (Available)">
                                                                বিক্রয়যোগ্য: <?= $fmt_smart($available) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="label" style="background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 9999px; display: inline-flex; align-items: center; gap: 4px;">
                                                            <i class="fa fa-times" style="color: #b91c1c;"></i> 0
                                                        </span>
                                                        <br><small style="color: #b91c1c; font-size: 10px; font-weight: 700; display: inline-block; margin-top: 2px;">স্টক নেই</small>
                                                    <?php endif; ?>

                                                    <?php if ($is_variable): ?>
                                                        <br><small class="text-muted" style="font-size: 10px;">Total in variations</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="text-align: center;">
                                                    <?= $reorder > 0 ? $fmt_smart($reorder) : '<span class="text-muted">-</span>' ?>
                                                </td>
                                                <td style="text-align: center;">
                                                    <span class="label" style="<?= $prod['is_active'] ? 'background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;' : 'background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;' ?> font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 4px;">
                                                        <?= $prod['is_active'] ? 'Active' : 'Inactive' ?>
                                                    </span>
                                                </td>
                                                <td class="prod-actions-cell">
                                                    <div class="prod-actions-wrap">
                                                        <?php if (staff_can('edit', 'inventory')): ?>
                                                            <button type="button" class="btn btn-info btn-xs btn-push-single-wc"
                                                                    data-id="<?= $prod['id'] ?>"
                                                                    data-name="<?= htmlspecialchars($prod['name'], ENT_QUOTES) ?>"
                                                                    title="WooCommerce এ পুশ বা স্টক আপডেট করুন">
                                                                <i class="fa fa-cloud-upload"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-default btn-xs edit-product-btn"
                                                                    data-id="<?= $prod['id'] ?>"
                                                                    data-sku="<?= e($prod['sku']) ?>"
                                                                    data-barcode="<?= e($prod['barcode'] ?? '') ?>"
                                                                    data-name="<?= e($prod['name']) ?>"
                                                                    data-rate="<?= e($prod['rate'] ?? 0) ?>"
                                                                    data-cost-price="<?= e($prod['cost_price'] ?? '') ?>"
                                                                    data-uom="<?= e($prod['uom'] ?? 'pc') ?>"
                                                                    data-category-id="<?= $prod['category_id'] ?>"
                                                                    data-image="<?= e($prod['image']) ?>"
                                                                    data-reorder-level="<?= $prod['reorder_level'] ?>"
                                                                    data-is-active="<?= $prod['is_active'] ?>"
                                                                    data-product-type="<?= $prod['product_type'] ?? 'simple' ?>"
                                                                    data-industry-data="<?= htmlspecialchars($prod['industry_data_json'] ?? '{}', ENT_QUOTES) ?>"
                                                                    title="Edit Product">
                                                                <i class="fa fa-pencil"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <?php if (staff_can('delete', 'inventory')): ?>
                                                            <a href="<?= admin_url('inventory/delete_product/' . $prod['id']) ?>" class="btn btn-danger btn-xs _delete" title="Delete Product">
                                                                <i class="fa fa-trash"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <!-- Variations Sub-row Container -->
                                            <?php if ($is_variable): ?>
                                                <tr id="variations-row-<?= $prod['id'] ?>" class="variations-sub-row" style="display: none; background: #fafafa;">
                                                    <td colspan="9" style="padding: 12px 20px;">
                                                        <div id="variations-container-<?= $prod['id'] ?>">
                                                            <i class="fa fa-spinner fa-spin"></i> Loading variations...
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination & Showing Counter Bar -->
                            <div class="inv-pagination-bar">
                                <div class="text-muted" style="font-size: 12px;" id="table-entries-info">
                                    Showing <span id="info-start">1</span> to <span id="info-end">25</span> of <span id="info-total"><?= $total_products ?></span> products
                                </div>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: #64748b;">
                                        <span>প্রতি পেজে:</span>
                                        <select id="select-page-size" class="form-control input-sm" style="width: 75px; height: 32px; padding: 4px 8px; border-radius: 4px;">
                                            <option value="25" selected>25</option>
                                            <option value="50">50</option>
                                            <option value="100">100</option>
                                            <option value="all">All</option>
                                        </select>
                                    </div>
                                    <ul class="pagination pagination-sm no-margin" id="catalog-pagination"></ul>
                                </div>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Product Modal with Smart Industry Tabs -->
<div class="modal fade" id="product-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="modal-title-text"><i class="fa fa-cube"></i> Add New Product</h4>
            </div>
            <?= form_open(admin_url('inventory/products'), ['id' => 'product-form']) ?>
            <input type="hidden" name="id" id="prod_id">
            <input type="hidden" name="product_type" id="prod_product_type" value="simple">

            <div class="modal-body" style="padding-top: 10px;">
                <!-- Nav tabs -->
                <ul class="nav nav-tabs" role="tablist" style="margin-bottom: 20px;">
                    <li role="presentation" class="active">
                        <a href="#tab-general" aria-controls="tab-general" role="tab" data-toggle="tab">
                            <i class="fa fa-info-circle"></i> General Info
                        </a>
                    </li>
                    <li role="presentation">
                        <a href="#tab-industry" aria-controls="tab-industry" role="tab" data-toggle="tab">
                            <i class="<?= $current_ind['icon'] ?>"></i> Business Attributes & Variations
                        </a>
                    </li>
                    <li role="presentation">
                        <a href="#tab-storefront" aria-controls="tab-storefront" role="tab" data-toggle="tab">
                            <i class="fa fa-shopping-cart"></i> Website Sync
                        </a>
                    </li>
                </ul>

                <!-- Tab panes -->
                <div class="tab-content">
                    <!-- Tab 1: General Info -->
                    <div role="tabpanel" class="tab-pane active" id="tab-general">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="prod_sku" class="control-label bold">Product SKU <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="text" name="sku" id="prod_sku" class="form-control" placeholder="e.g. GAD-2001" required>
                                        <span class="input-group-btn">
                                            <button type="button" class="btn btn-default" id="btn-autogen-sku" title="Auto-generate SKU">
                                                <i class="fa fa-magic"></i> Auto
                                            </button>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="prod_barcode" class="control-label">Barcode / UPC (POS Scanner)</label>
                                    <input type="text" name="barcode" id="prod_barcode" class="form-control" placeholder="e.g. 890123456789">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="prod_name" class="control-label bold">Product Title / Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="prod_name" class="form-control" placeholder="Full product name" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="prod_category_id" class="control-label">Category</label>
                                    <select name="category_id" id="prod_category_id" class="form-control selectpicker" data-live-search="true">
                                        <option value="">Select Category...</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="prod_uom" class="control-label">Unit of Measure (UOM)</label>
                                    <select name="uom" id="prod_uom" class="form-control">
                                        <option value="pc">Piece (পিস)</option>
                                        <option value="kg">Kilogram (কেজি)</option>
                                        <option value="gm">Gram (গ্রাম)</option>
                                        <option value="ltr">Litre (লিটার)</option>
                                        <option value="ml">MilliLitre (মিলি)</option>
                                        <option value="box">Box (বক্স)</option>
                                        <option value="set">Set (সেট)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="prod_rate" class="control-label bold">Selling Price (BDT) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" name="rate" id="prod_rate" class="form-control" placeholder="0.00" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="prod_cost_price" class="control-label">Cost Price (BDT)</label>
                                    <input type="number" step="0.01" name="cost_price" id="prod_cost_price" class="form-control" placeholder="0.00">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="prod_initial_stock" class="control-label">Initial Stock (Default WH)</label>
                                    <input type="number" step="1" name="initial_stock" id="prod_initial_stock" class="form-control" value="0">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="prod_image" class="control-label">Product Image URL</label>
                            <input type="text" name="image" id="prod_image" class="form-control" placeholder="https://example.com/image.jpg">
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="prod_reorder_level" class="control-label">Reorder Alert Level</label>
                                    <input type="number" step="1" name="reorder_level" id="prod_reorder_level" class="form-control" value="5.00">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group" style="padding-top: 25px;">
                                    <div class="checkbox checkbox-primary">
                                        <input type="checkbox" name="is_active" id="prod_is_active" value="1" checked>
                                        <label for="prod_is_active">Product Active for Sales & POS</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 2: Dynamic Industry Attributes & Variations -->
                    <div role="tabpanel" class="tab-pane" id="tab-industry">
                        <!-- Mode A: Fashion & Apparel -->
                        <?php if (($industry_mode ?? '') === 'fashion'): ?>
                            <div class="alert alert-info" style="font-size: 12px;">
                                <i class="fa fa-info-circle"></i> <strong>ফ্যাশন ও পোশাক ভ্যারিয়েশন:</strong> সাইজ এবং কালার নির্বাচন করে "Generate Variation Matrix" চাপলে অটোমেটিক চাইল্ড SKU সহ ভ্যারিয়েশন তালিকা তৈরি হবে।
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="bold">Available Sizes (সাইজসমূহ)</label>
                                    <div style="margin-bottom: 10px;">
                                        <label class="checkbox-inline"><input type="checkbox" class="var-size-chk" value="S"> S</label>
                                        <label class="checkbox-inline"><input type="checkbox" class="var-size-chk" value="M" checked> M</label>
                                        <label class="checkbox-inline"><input type="checkbox" class="var-size-chk" value="L" checked> L</label>
                                        <label class="checkbox-inline"><input type="checkbox" class="var-size-chk" value="XL" checked> XL</label>
                                        <label class="checkbox-inline"><input type="checkbox" class="var-size-chk" value="XXL"> XXL</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="bold">Colors (কমা দিয়ে লিখুন, যেমন: Black, Navy, Maroon)</label>
                                    <input type="text" id="input-colors" class="form-control" value="Black, Navy Blue" placeholder="e.g. Red, Blue, Black">
                                </div>
                            </div>
                            <div class="mtop10 mbot15">
                                <button type="button" class="btn btn-info btn-sm" id="btn-build-matrix">
                                    <i class="fa fa-magic"></i> Generate Variation Matrix
                                </button>
                            </div>
                            <div id="variation-matrix-container" style="display: none;">
                                <h5 class="bold font-medium text-primary">Generated Variations</h5>
                                <table class="table table-bordered table-condensed" id="variation-matrix-table">
                                    <thead>
                                        <tr style="background:#f1f5f9;">
                                            <th>Child SKU</th>
                                            <th>Variation Title</th>
                                            <th>Price (BDT)</th>
                                            <th>Initial Stock</th>
                                            <th style="width: 40px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="variation-matrix-body"></tbody>
                                </table>
                            </div>

                        <!-- Mode B: Gadgets & Electronics -->
                        <?php elseif (($industry_mode ?? '') === 'gadgets'): ?>
                            <div class="alert alert-info" style="font-size: 12px;">
                                <i class="fa fa-info-circle"></i> <strong>গ্যাজেট ও ইলেকট্রনিক্স স্পেসিফিকেশন:</strong> ওয়ারেন্টি মেয়াদ ও সিরিয়াল ট্র্যাকিং কনফিগার করুন।
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="ind_warranty" class="control-label bold">Warranty Period (ওয়ারেন্টি)</label>
                                        <select name="industry_data_json[warranty]" id="ind_warranty" class="form-control">
                                            <option value="No Warranty">No Warranty (ওয়ারেন্টি নেই)</option>
                                            <option value="7 Days Replacement">7 Days Replacement Warranty</option>
                                            <option value="1 Month Official">1 Month Official Warranty</option>
                                            <option value="6 Months Official">6 Months Official Warranty</option>
                                            <option value="1 Year Brand Warranty" selected>1 Year Brand Warranty (১ বছর ওয়ারেন্টি)</option>
                                            <option value="2 Years Brand Warranty">2 Years Brand Warranty</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="ind_model" class="control-label bold">Model / Brand</label>
                                        <input type="text" name="industry_data_json[model]" id="ind_model" class="form-control" placeholder="e.g. T900 Ultra / Apple">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="checkbox checkbox-primary">
                                    <input type="checkbox" name="industry_data_json[imei_required]" id="ind_imei" value="1">
                                    <label for="ind_imei"><strong>Require IMEI / Serial Number Tracking (প্রতিটি ইউনিটের জন্য সিরিয়াল ট্র্যাকিং চালু করুন)</strong></label>
                                </div>
                                <p class="text-muted" style="margin-left: 20px;">এটি সক্রিয় থাকলে পারচেজ বা বিক্রির সময় আলাদা আলাদা IMEI/সিরিয়াল ইনপুট নেওয়ার অপশন আসবে।</p>
                            </div>

                        <!-- Mode C: Grocery & Food -->
                        <?php elseif (($industry_mode ?? '') === 'grocery'): ?>
                            <div class="alert alert-info" style="font-size: 12px;">
                                <i class="fa fa-info-circle"></i> <strong>মুদি ও খাদ্যপণ্য ট্র্যাকিং:</strong> ব্যাচ নম্বর এবং মেয়াদোত্তীর্ণ তারিখ (Expiry Date) সেট করুন।
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="ind_batch" class="control-label bold">Batch / Lot Number</label>
                                        <input type="text" name="industry_data_json[batch_no]" id="ind_batch" class="form-control" placeholder="e.g. B-2026-09">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="ind_expiry" class="control-label bold">Expiry Date (মেয়াদোত্তীর্ণ তারিখ)</label>
                                        <input type="date" name="industry_data_json[expiry_date]" id="ind_expiry" class="form-control">
                                    </div>
                                </div>
                            </div>

                        <!-- Mode D: General Retail -->
                        <?php else: ?>
                            <div class="alert alert-info" style="font-size: 12px;">
                                <i class="fa fa-info-circle"></i> <strong>জেনারেল রিটেল স্পেসিফিকেশন:</strong> ব্র্যান্ড বা অতিরিক্ত কাস্টম ফিল্ড সেট করুন।
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="ind_brand" class="control-label bold">Brand / Manufacturer</label>
                                        <input type="text" name="industry_data_json[brand]" id="ind_brand" class="form-control" placeholder="e.g. Sony, Samsung">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="ind_specs" class="control-label bold">Color / Specification</label>
                                        <input type="text" name="industry_data_json[specs]" id="ind_specs" class="form-control" placeholder="e.g. Black / 100% Cotton">
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Tab 3: Website Storefront Sync -->
                    <div role="tabpanel" class="tab-pane" id="tab-storefront">
                        <div class="form-group">
                            <div class="checkbox checkbox-primary">
                                <input type="checkbox" name="sync_to_wc" id="prod_sync_to_wc" value="1" checked>
                                <label for="prod_sync_to_wc"><strong>Sync with WooCommerce Online Store</strong></label>
                            </div>
                            <p class="text-muted">এই প্রোডাক্টটি সেভ হওয়ার পর সংযুক্ত WooCommerce স্টোরে স্বয়ংক্রিয়ভাবে পুশ ও আপডেট হয়ে যাবে।</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary" id="modal-submit-btn">Save Product</button>
            </div>
            <?= form_close() ?>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ── Edit Product Handler ─────────────────────────────────────────────
    var editButtons = document.querySelectorAll('.edit-product-btn');
    var modalTitleText = document.getElementById('modal-title-text');
    var submitBtn = document.getElementById('modal-submit-btn');

    editButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var sku = this.getAttribute('data-sku');
            var barcode = this.getAttribute('data-barcode');
            var name = this.getAttribute('data-name');
            var rate = this.getAttribute('data-rate');
            var costPrice = this.getAttribute('data-cost-price');
            var uom = this.getAttribute('data-uom') || 'pc';
            var catId = this.getAttribute('data-category-id');
            var image = this.getAttribute('data-image');
            var reorder = this.getAttribute('data-reorder-level');
            var active = this.getAttribute('data-is-active') == '1';
            var productType = this.getAttribute('data-product-type') || 'simple';
            var indDataRaw = this.getAttribute('data-industry-data') || '{}';

            document.getElementById('prod_id').value = id;
            document.getElementById('prod_sku').value = sku;
            document.getElementById('prod_barcode').value = barcode;
            document.getElementById('prod_name').value = name;
            document.getElementById('prod_rate').value = rate;
            document.getElementById('prod_cost_price').value = costPrice;
            document.getElementById('prod_uom').value = uom;
            document.getElementById('prod_category_id').value = catId;
            document.getElementById('prod_image').value = image || '';
            document.getElementById('prod_reorder_level').value = reorder;
            document.getElementById('prod_is_active').checked = active;
            document.getElementById('prod_product_type').value = productType;

            try {
                var indData = JSON.parse(indDataRaw);
                if (document.getElementById('ind_warranty') && indData.warranty) {
                    document.getElementById('ind_warranty').value = indData.warranty;
                }
                if (document.getElementById('ind_model') && indData.model) {
                    document.getElementById('ind_model').value = indData.model;
                }
                if (document.getElementById('ind_imei')) {
                    document.getElementById('ind_imei').checked = (indData.imei_required == '1');
                }
                if (document.getElementById('ind_batch') && indData.batch_no) {
                    document.getElementById('ind_batch').value = indData.batch_no;
                }
                if (document.getElementById('ind_expiry') && indData.expiry_date) {
                    document.getElementById('ind_expiry').value = indData.expiry_date;
                }
            } catch (e) {}

            modalTitleText.innerHTML = '<i class="fa fa-pencil"></i> Edit Product';
            submitBtn.innerText = 'Update Product';
            $('#product-modal').modal('show');
        });
    });

    // ── Add New Product Button ───────────────────────────────────────────
    var addBtn = document.getElementById('btn-add-product');
    if (addBtn) {
        addBtn.addEventListener('click', function() {
            document.getElementById('product-form').reset();
            document.getElementById('prod_id').value = '';
            document.getElementById('prod_product_type').value = 'simple';
            modalTitleText.innerHTML = '<i class="fa fa-cube"></i> Add New Product';
            submitBtn.innerText = 'Save Product';
            if (document.getElementById('variation-matrix-container')) {
                document.getElementById('variation-matrix-container').style.display = 'none';
                document.getElementById('variation-matrix-body').innerHTML = '';
            }
        });
    }

    // ── Auto-generate SKU ────────────────────────────────────────────────
    var autoGenBtn = document.getElementById('btn-autogen-sku');
    if (autoGenBtn) {
        autoGenBtn.addEventListener('click', function() {
            var name = document.getElementById('prod_name').value.trim();
            var prefix = 'PROD';
            if (name.length > 2) {
                prefix = name.substring(0, 4).toUpperCase().replace(/[^A-Z0-9]/g, '');
            }
            var rnd = Math.floor(1000 + Math.random() * 9000);
            document.getElementById('prod_sku').value = prefix + '-' + rnd;
        });
    }

    // ── Expand/Collapse Product Variations in Catalog Table ─────────────
    $('.btn-toggle-variations').on('click', function() {
        var parentId = $(this).attr('data-id');
        var subRow = $('#variations-row-' + parentId);
        var container = $('#variations-container-' + parentId);
        var btn = $(this);

        if (subRow.is(':visible')) {
            subRow.hide();
            btn.find('i.fa-chevron-up').removeClass('fa-chevron-up').addClass('fa-chevron-down');
        } else {
            subRow.show();
            btn.find('i.fa-chevron-down').removeClass('fa-chevron-down').addClass('fa-chevron-up');

            // Load via AJAX if not already loaded
            if (container.find('table').length === 0) {
                $.getJSON('<?= admin_url('inventory/get_variations/') ?>' + parentId, function(vars) {
                    if (!vars || vars.length === 0) {
                        container.html('<p class="text-muted">No child variations recorded.</p>');
                        return;
                    }

                    var removeDecimals = '<?= get_option('remove_decimals_on_zero') ?: '0' ?>';
                    var fmtSmartJs = function(val) {
                        var n = parseFloat(val || 0);
                        if (removeDecimals === '1' && Math.floor(n) === n) {
                            return n.toLocaleString('en-US');
                        }
                        return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    };

                    var html = '<table class="table table-bordered table-condensed" style="background:#fff; margin-bottom:0;">';
                    html += '<thead style="background:#f8fafc;"><tr><th>Child SKU</th><th>Variation Name</th><th>Attributes</th><th>Price</th><th style="text-align:center;">Stock Details</th></tr></thead><tbody>';
                    vars.forEach(function(v) {
                        var attrsStr = '-';
                        try {
                            var aObj = JSON.parse(v.attributes_json || '{}');
                            attrsStr = Object.keys(aObj).map(function(k){ return k + ': ' + aObj[k]; }).join(', ');
                        } catch(e) {}

                        var vStock = parseFloat(v.stock_on_hand || 0);
                        var vReserved = parseFloat(v.stock_reserved || 0);
                        var vAvail = parseFloat(v.stock_available || Math.max(0, vStock - vReserved));

                        var stockBadge = (vStock > 0)
                            ? '<span class="label" style="background:#dcfce7; color:#166534; border:1px solid #86efac; font-weight:700; padding:3px 8px; border-radius:12px; font-size:11px;" title="On Hand"><i class="fa fa-cube"></i> ' + fmtSmartJs(vStock) + '</span>'
                            : '<span class="label" style="background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; font-weight:700; padding:3px 8px; border-radius:12px; font-size:11px;"><i class="fa fa-times"></i> 0</span>';

                        if (vReserved > 0) {
                            stockBadge += '<div style="margin-top:3px;"><span class="label" style="background:#fef3c7; color:#92400e; border:1px solid #fde68a; font-size:10px; padding:2px 5px; border-radius:4px;"><i class="fa fa-lock"></i> ' + fmtSmartJs(vReserved) + ' Reserved</span></div>';
                            stockBadge += '<div style="margin-top:2px; font-size:10px; font-weight:700; color:#0284c7;">Avail: ' + fmtSmartJs(vAvail) + '</div>';
                        }

                        html += '<tr>';
                        html += '<td><code>' + (v.sku || '-') + '</code></td>';
                        html += '<td>' + (v.name || '-') + '</td>';
                        html += '<td><span class="label label-default" style="background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; font-size:11px;">' + attrsStr + '</span></td>';
                        html += '<td style="font-weight:700; color:#0f172a;">' + fmtSmartJs(v.rate) + ' <span style="font-size:10px; font-weight:normal; color:#64748b;">BDT</span></td>';
                        html += '<td style="text-align:center;">' + stockBadge + '</td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table>';
                    container.html(html);
                });
            }
        }
    });

    // ── Build Fashion Variation Matrix ───────────────────────────────────
    var buildMatrixBtn = document.getElementById('btn-build-matrix');
    if (buildMatrixBtn) {
        buildMatrixBtn.addEventListener('click', function() {
            var selectedSizes = [];
            $('.var-size-chk:checked').each(function() {
                selectedSizes.push($(this).val());
            });

            var colorsRaw = document.getElementById('input-colors').value.trim();
            var colors = colorsRaw ? colorsRaw.split(',').map(function(s){ return s.trim(); }).filter(Boolean) : [];

            if (selectedSizes.length === 0 || colors.length === 0) {
                alert('অনুগ্রহ করে অন্তত একটি সাইজ এবং একটি কালার নির্বাচন করুন।');
                return;
            }

            var baseSku = document.getElementById('prod_sku').value.trim() || 'PROD';
            var baseName = document.getElementById('prod_name').value.trim() || 'Item';
            var baseRate = document.getElementById('prod_rate').value.trim() || '0.00';
            var tbody = document.getElementById('variation-matrix-body');
            tbody.innerHTML = '';

            var idx = 0;
            colors.forEach(function(col) {
                selectedSizes.forEach(function(sz) {
                    var colCode = col.substring(0, 3).toUpperCase().replace(/[^A-Z0-9]/g, '');
                    var varSku = baseSku + '-' + colCode + '-' + sz;
                    var varTitle = baseName + ' - ' + col + ' (' + sz + ')';

                    var tr = document.createElement('tr');
                    tr.innerHTML = '<td><input type="text" name="variations[' + idx + '][sku]" value="' + varSku + '" class="form-control input-sm" required></td>' +
                        '<td><input type="text" name="variations[' + idx + '][name]" value="' + varTitle + '" class="form-control input-sm" required>' +
                        '<input type="hidden" name="variations[' + idx + '][attributes_json][Color]" value="' + col + '">' +
                        '<input type="hidden" name="variations[' + idx + '][attributes_json][Size]" value="' + sz + '"></td>' +
                        '<td><input type="number" step="0.01" name="variations[' + idx + '][rate]" value="' + baseRate + '" class="form-control input-sm"></td>' +
                        '<td><input type="number" step="1" name="variations[' + idx + '][initial_stock]" value="10" class="form-control input-sm"></td>' +
                        '<td><button type="button" class="btn btn-danger btn-xs" onclick="$(this).closest(\'tr\').remove();"><i class="fa fa-trash"></i></button></td>';
                    tbody.appendChild(tr);
                    idx++;
                });
            });

            document.getElementById('prod_product_type').value = 'variable';
            document.getElementById('variation-matrix-container').style.display = 'block';
        });
    }

    // ── Checkbox Selection & Bulk Push to WooCommerce ───────────────────
    var selectAllChks = document.getElementById('chk-select-all-prods');
    var bulkToolbar = document.getElementById('bulk-push-toolbar');
    var selectedCountSpan = document.getElementById('selected-prod-count');
    var clearSelectionBtn = document.getElementById('btn-clear-selection');
    var pushBulkBtn = document.getElementById('btn-push-bulk-wc');

    function updateBulkSelectionState() {
        var checkedBoxes = document.querySelectorAll('.chk-prod-row:checked');
        var count = checkedBoxes.length;
        if (selectedCountSpan) selectedCountSpan.innerText = count;

        if (bulkToolbar) {
            if (count > 0) {
                bulkToolbar.style.display = 'block';
            } else {
                bulkToolbar.style.display = 'none';
            }
        }
    }

    if (selectAllChks) {
        selectAllChks.addEventListener('change', function() {
            var isChecked = this.checked;
            $('.chk-prod-row').each(function() {
                if ($(this).closest('tr').is(':visible')) {
                    this.checked = isChecked;
                }
            });
            updateBulkSelectionState();
        });
    }

    $(document).on('change', '.chk-prod-row', function() {
        updateBulkSelectionState();
    });

    if (clearSelectionBtn) {
        clearSelectionBtn.addEventListener('click', function() {
            document.querySelectorAll('.chk-prod-row').forEach(function(chk) {
                chk.checked = false;
            });
            if (selectAllChks) selectAllChks.checked = false;
            updateBulkSelectionState();
        });
    }

    // ── Push Single Product to WooCommerce ──────────────────────────────
    $(document).on('click', '.btn-push-single-wc', function() {
        var btn = $(this);
        var id = btn.attr('data-id');
        var name = btn.attr('data-name') || 'প্রোডাক্ট';
        var originalHtml = btn.html();

        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

        $.post('<?= admin_url('wcsync/push_products_ajax') ?>', { product_ids: [id] })
        .done(function(raw) {
            btn.prop('disabled', false).html(originalHtml);
            try {
                var res = typeof raw === 'object' ? raw : JSON.parse(raw);
                if (res.success && res.pushed > 0) {
                    if (typeof alert_float === 'function') {
                        alert_float('success', '"' + name + '" সফলভাবে WooCommerce এ পুশ হয়েছে!');
                    } else {
                        alert('"' + name + '" সফলভাবে WooCommerce এ পুশ হয়েছে!');
                    }
                    var row = $('#prod-row-' + id);
                    if (row.find('span:contains("WC")').length === 0) {
                        row.find('td:nth-child(3) strong').after(' <span class="label" title="Synced with WooCommerce" style="font-size: 11px; margin-left: 6px; background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; font-weight: 700; padding: 2px 7px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;"><i class="fa fa-shopping-cart" style="color: #2563eb;"></i> WC</span>');
                    }
                } else {
                    var errMsg = res.error || 'Failed to push';
                    if (typeof alert_float === 'function') {
                        alert_float('danger', 'পুশ ব্যর্থ হয়েছে: ' + errMsg);
                    } else {
                        alert('পুশ ব্যর্থ হয়েছে: ' + errMsg);
                    }
                }
            } catch(e) {
                alert('Response parsing error');
            }
        })
        .fail(function(xhr) {
            btn.prop('disabled', false).html(originalHtml);
            alert('Connection error (' + xhr.status + ')');
        });
    });

    // ── Push Bulk Selected Products to WooCommerce ──────────────────────
    if (pushBulkBtn) {
        pushBulkBtn.addEventListener('click', function() {
            var selectedIds = [];
            document.querySelectorAll('.chk-prod-row:checked').forEach(function(chk) {
                selectedIds.push(chk.value);
            });

            if (selectedIds.length === 0) {
                alert('অনুগ্রহ করে অন্তত একটি প্রোডাক্ট সিলেক্ট করুন।');
                return;
            }

            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> পুশ হচ্ছে...');

            $.post('<?= admin_url('wcsync/push_products_ajax') ?>', { product_ids: selectedIds })
            .done(function(raw) {
                btn.prop('disabled', false).html('<i class="fa fa-cloud-upload"></i> সিলেক্টেড প্রোডাক্ট পুশ করুন (Push Selected)');
                try {
                    var res = typeof raw === 'object' ? raw : JSON.parse(raw);
                    if (res.success) {
                        var msg = 'সফলভাবে ' + res.pushed + ' টি প্রোডাক্ট WooCommerce এ পুশ হয়েছে!' + (res.failed > 0 ? ' (' + res.failed + ' টি ফেইল)' : '');
                        if (typeof alert_float === 'function') {
                            alert_float('success', msg);
                        } else {
                            alert(msg);
                        }
                        if (clearSelectionBtn) clearSelectionBtn.click();
                    } else {
                        alert('পুশ ব্যর্থ হয়েছে: ' + (res.error || 'Unknown error'));
                    }
                } catch(e) {
                    alert('Response parse error');
                }
            })
            .fail(function(xhr) {
                btn.prop('disabled', false).html('<i class="fa fa-cloud-upload"></i> সিলেক্টেড প্রোডাক্ট পুশ করুন (Push Selected)');
                alert('Connection error (' + xhr.status + ')');
            });
        });
    }

    // ── Push All Products from Header Dropdown ──────────────────────────
    var pushAllHeaderBtn = document.getElementById('btn-header-push-all-wc');
    if (pushAllHeaderBtn) {
        pushAllHeaderBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (!confirm('আপনি কি নিশ্চিত যে CRM-এর সকল সক্রিয় প্রোডাক্ট WooCommerce ওয়েবসাইটে পুশ ও স্টক আপডেট করতে চান?')) {
                return;
            }

            var origText = $(this).html();
            $(this).html('<i class="fa fa-spinner fa-spin"></i> পুশ হচ্ছে...');

            $.post('<?= admin_url('wcsync/push_products_ajax') ?>', { push_all: '1' })
            .done(function(raw) {
                $(pushAllHeaderBtn).html(origText);
                try {
                    var res = typeof raw === 'object' ? raw : JSON.parse(raw);
                    if (res.success) {
                        var msg = 'সম্পন্ন হয়েছে! মোট ' + res.pushed + ' টি প্রোডাক্ট সফলভাবে WooCommerce ওয়েবসাইটে পুশ হয়েছে।';
                        if (typeof alert_float === 'function') {
                            alert_float('success', msg);
                        } else {
                            alert(msg);
                        }
                    } else {
                        alert('পুশ ব্যর্থ হয়েছে: ' + (res.error || 'Unknown error'));
                    }
                } catch(e) {
                    alert('Response parse error');
                }
            })
            .fail(function(xhr) {
                $(pushAllHeaderBtn).html(origText);
                alert('Network error (' + xhr.status + ')');
            });
        });
    }

    // ── Instant Filter, Search & Pagination Logic ──────────────────────
    var currentPage = 1;
    var pageSize = 25;
    var visibleRows = [];

    function renderPagination() {
        var total = visibleRows.length;
        var tableBody = $('#products-table-body');
        tableBody.find('#no-matching-rows').remove();

        if (total === 0) {
            tableBody.append('<tr id="no-matching-rows"><td colspan="9" class="text-center text-muted" style="padding: 30px;"><i class="fa fa-search fa-2x mbot10"></i><br>কোনো প্রোডাক্ট পাওয়া যায়নি। ফিল্টার রিসেট করতে "রিসেট" বাটনে ক্লিক করুন।</td></tr>');
            $('#info-start').text(0);
            $('#info-end').text(0);
            $('#info-total').text(0);
            $('#catalog-pagination').html('');
            return;
        }

        var isAll = (pageSize === 'all');
        var numericPageSize = isAll ? total : parseInt(pageSize, 10);
        var totalPages = isAll ? 1 : Math.ceil(total / numericPageSize);

        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        var startIdx = (currentPage - 1) * numericPageSize;
        var endIdx = isAll ? total : Math.min(startIdx + numericPageSize, total);

        // Show/hide based on pagination
        visibleRows.forEach(function(row, idx) {
            var prodId = row.attr('id').replace('prod-row-', '');
            if (idx >= startIdx && idx < endIdx) {
                row.show();
            } else {
                row.hide();
                $('#variations-row-' + prodId).hide();
            }
        });

        // Update counter info
        $('#info-start').text(startIdx + 1);
        $('#info-end').text(endIdx);
        $('#info-total').text(total);

        // Render pagination controls
        var paginationHtml = '';
        if (totalPages > 1) {
            // Previous
            paginationHtml += '<li class="' + (currentPage === 1 ? 'disabled' : '') + '">';
            paginationHtml += '<a href="javascript:void(0);" data-page="' + (currentPage - 1) + '">&laquo;</a></li>';

            // Page Numbers (Window of 5)
            var startPage = Math.max(1, currentPage - 2);
            var endPage = Math.min(totalPages, startPage + 4);
            if (endPage - startPage < 4) {
                startPage = Math.max(1, endPage - 4);
            }

            if (startPage > 1) {
                paginationHtml += '<li><a href="javascript:void(0);" data-page="1">1</a></li>';
                if (startPage > 2) paginationHtml += '<li class="disabled"><span>...</span></li>';
            }

            for (var p = startPage; p <= endPage; p++) {
                paginationHtml += '<li class="' + (p === currentPage ? 'active' : '') + '">';
                paginationHtml += '<a href="javascript:void(0);" data-page="' + p + '">' + p + '</a></li>';
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) paginationHtml += '<li class="disabled"><span>...</span></li>';
                paginationHtml += '<li><a href="javascript:void(0);" data-page="' + totalPages + '">' + totalPages + '</a></li>';
            }

            // Next
            paginationHtml += '<li class="' + (currentPage === totalPages ? 'disabled' : '') + '">';
            paginationHtml += '<a href="javascript:void(0);" data-page="' + (currentPage + 1) + '">&raquo;</a></li>';
        }

        $('#catalog-pagination').html(paginationHtml);
    }

    function applyFiltersAndPagination(resetPage) {
        if (resetPage !== false) {
            currentPage = 1;
        }

        var searchVal = ($('#catalog-search').val() || '').toLowerCase().trim();
        var catVal = ($('#filter-category').val() || '').toLowerCase().trim();
        var stockVal = $('#filter-stock').val() || 'all';

        visibleRows = [];
        $('.prod-data-row').each(function() {
            var row = $(this);
            var rName = row.attr('data-name') || '';
            var rSku = row.attr('data-sku') || '';
            var rBarcode = row.attr('data-barcode') || '';
            var rCat = row.attr('data-category') || '';
            var rStock = row.attr('data-stock-status');
            var rWc = row.attr('data-wc');

            var matchSearch = (!searchVal || rName.indexOf(searchVal) !== -1 || rSku.indexOf(searchVal) !== -1 || rBarcode.indexOf(searchVal) !== -1);
            var matchCat = (!catVal || rCat === catVal);
            var matchStock = true;
            if (stockVal === 'in_stock') {
                matchStock = (rStock === 'in_stock');
            } else if (stockVal === 'out_of_stock') {
                matchStock = (rStock === 'out_of_stock');
            } else if (stockVal === 'wc') {
                matchStock = (rWc === '1');
            }

            if (matchSearch && matchCat && matchStock) {
                visibleRows.push(row);
            } else {
                row.hide();
                var prodId = row.attr('id').replace('prod-row-', '');
                $('#variations-row-' + prodId).hide();
            }
        });

        renderPagination();
    }

    // Filter Events
    $('#catalog-search').on('keyup input', function() {
        applyFiltersAndPagination();
    });

    $('#filter-category').on('change', function() {
        applyFiltersAndPagination();
    });

    $('#filter-stock').on('change', function() {
        var val = $(this).val();
        $('.kpi-metric-card').removeClass('active-kpi');
        if (val === 'all') $('#kpi-card-all').addClass('active-kpi');
        else if (val === 'in_stock') $('#kpi-card-in-stock').addClass('active-kpi');
        else if (val === 'out_of_stock') $('#kpi-card-out-of-stock').addClass('active-kpi');
        else if (val === 'wc') $('#kpi-card-wc').addClass('active-kpi');

        applyFiltersAndPagination();
    });

    // KPI Metric Card Click Handler
    $('.kpi-metric-card').on('click', function() {
        var stockType = $(this).attr('data-stock');
        $('.kpi-metric-card').removeClass('active-kpi');
        $(this).addClass('active-kpi');
        $('#filter-stock').val(stockType);
        applyFiltersAndPagination();
    });

    // Reset Filters Button
    $('#btn-reset-filters').on('click', function() {
        $('#catalog-search').val('');
        $('#filter-category').val('');
        $('#filter-stock').val('all');
        $('.kpi-metric-card').removeClass('active-kpi');
        $('#kpi-card-all').addClass('active-kpi');
        applyFiltersAndPagination();
    });

    // Page Size Selector
    $('#select-page-size').on('change', function() {
        pageSize = $(this).val();
        applyFiltersAndPagination(true);
    });

    // Pagination Click Handler
    $(document).on('click', '#catalog-pagination a[data-page]', function(e) {
        e.preventDefault();
        var page = parseInt($(this).attr('data-page'), 10);
        if (page && page !== currentPage) {
            currentPage = page;
            renderPagination();
            // Smooth scroll to table top
            var tbl = document.getElementById('products-table');
            if (tbl) {
                tbl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }
    });

    // Initial filter & pagination setup
    applyFiltersAndPagination();
});
</script>
