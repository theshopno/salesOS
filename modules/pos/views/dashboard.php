<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
    /* Prevent any horizontal scrollbar */
    html, body {
        overflow-x: hidden !important;
        max-width: 100vw;
    }
    /* Hide default Perfex CRM Header and Sidebar Menu */
    #header, #menu {
        display: none !important;
    }
    #wrapper {
        margin-left: 0 !important;
        margin-top: 0 !important;
        padding-top: 0 !important;
        background: #f1f5f9 !important;
        width: 100% !important;
        max-width: 100vw;
        min-height: 100vh;
        overflow-x: hidden !important;
    }
    
    .pos-container {
        padding: 8px 10px;
        overflow-x: hidden !important;
        max-width: 100%;
        box-sizing: border-box;
    }
    .pos-main-row {
        margin-left: -5px !important;
        margin-right: -5px !important;
    }
    .pos-main-row > [class*="col-"] {
        padding-left: 5px !important;
        padding-right: 5px !important;
    }
    .pos-header-bar {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        padding: 8px 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .pos-header-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .pos-header-right {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .pos-brand {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 15px;
        font-weight: 800;
        color: #0f172a;
        text-decoration: none !important;
    }
    .pos-brand i {
        color: #4f46e5;
        font-size: 18px;
    }
    .pos-badge-cashier {
        background: #e0e7ff;
        color: #4338ca;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .pos-btn-icon {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8fafc;
        color: #475569;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 13px;
        outline: none !important;
    }
    .pos-btn-icon:hover {
        background: #4f46e5;
        color: #fff !important;
        border-color: #4f46e5;
    }
    .pos-btn-icon.active {
        background: #4f46e5;
        color: #fff !important;
        border-color: #4f46e5;
    }
    
    /* Workspace Panels */
    .pos-panel {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 12px;
        height: calc(100vh - 66px);
        display: flex;
        flex-direction: column;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    }
    
    /* Left Panel: Search & Category Pills */
    .pos-products-header {
        margin-bottom: 8px;
    }
    .pos-category-pills-wrap {
        margin-bottom: 10px;
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 8px;
    }
    .pos-category-pills {
        display: flex;
        gap: 6px;
        overflow-x: auto;
        white-space: nowrap;
        padding: 2px 2px 4px 2px;
        scrollbar-width: thin;
    }
    .pos-category-pills::-webkit-scrollbar {
        height: 4px;
    }
    .pos-category-pills::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }
    .pos-category-pill {
        background: #f8fafc;
        color: #475569;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        padding: 4px 12px;
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s ease;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
        outline: none !important;
    }
    .pos-category-pill:hover {
        background: #e2e8f0;
        color: #0f172a;
    }
    .pos-category-pill.active {
        background: #4f46e5;
        color: #ffffff;
        border-color: #4f46e5;
        box-shadow: 0 2px 4px rgba(79, 70, 229, 0.2);
    }
    
    /* Left Panel: Product Cards Grid */
    .pos-products-grid {
        flex-grow: 1;
        overflow-y: auto;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(135px, 1fr));
        align-content: start;
        gap: 10px;
        padding: 2px;
    }
    .pos-product-card-container {
        user-select: none;
        cursor: pointer;
    }
    .pos-product-card {
        position: relative;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 8px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 100%;
        min-height: 175px;
        transition: all 0.15s ease-in-out;
        box-shadow: 0 1px 2px rgba(0,0,0,0.02);
    }
    .pos-product-card:hover {
        border-color: #6366f1;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.15);
    }
    .pos-product-card.out-of-stock {
        background: #f8fafc;
        border-color: #e2e8f0;
        opacity: 0.82;
    }
    .pos-product-card.out-of-stock .pos-product-card-img {
        filter: grayscale(80%);
        opacity: 0.6;
    }
    .pos-card-out-badge {
        position: absolute;
        top: 36px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(220, 38, 38, 0.92);
        color: #ffffff;
        font-size: 10px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 12px;
        letter-spacing: 0.3px;
        pointer-events: none;
        box-shadow: 0 2px 4px rgba(0,0,0,0.25);
        white-space: nowrap;
        z-index: 2;
    }
    .pos-product-card-img {
        width: 100%;
        aspect-ratio: 1 / 1;
        object-fit: cover;
        border-radius: 6px;
        margin-bottom: 6px;
        background: #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    .pos-product-card-name {
        font-size: 12px;
        font-weight: 600;
        color: #1e293b;
        margin: 0 0 4px 0;
        line-height: 1.35;
        height: 32px;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        text-align: left;
    }
    .pos-card-bottom {
        margin-top: 4px;
        border-top: 1px dashed #e2e8f0;
        padding-top: 5px;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .pos-card-price {
        font-size: 12px;
        font-weight: 700;
        color: #0f172a;
        text-align: left;
    }
    .pos-card-stock {
        font-size: 11px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    /* Right Panel: Cart & Checkout */
    .pos-cart-header {
        display: flex;
        gap: 8px;
        align-items: center;
        margin-bottom: 8px;
    }
    .pos-cart-items-container {
        flex-grow: 1;
        overflow-y: auto;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        background: #ffffff;
        margin-bottom: 8px;
        min-height: 160px;
    }
    .pos-cart-table {
        width: 100%;
        margin: 0;
    }
    .pos-cart-table th {
        background: #f8fafc;
        color: #475569;
        font-weight: 600;
        font-size: 11px;
        padding: 7px 8px;
        border-bottom: 1px solid #e2e8f0;
    }
    .pos-cart-table td {
        padding: 6px 8px;
        font-size: 12px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }
    .pos-empty-cart {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 160px;
        color: #94a3b8;
    }
    .pos-empty-cart i {
        font-size: 28px;
        margin-bottom: 6px;
    }
    
    /* Calculations & Payable */
    .pos-calc-block {
        border-top: 1px solid #e2e8f0;
        padding-top: 8px;
        margin-bottom: 8px;
    }
    .pos-calc-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 4px;
        font-size: 12px;
        color: #475569;
    }
    .pos-calc-row-main {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 6px;
    }
    .pos-calc-col {
        flex: 1;
    }
    
    .pos-total-payable-card {
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 8px 12px;
        margin-bottom: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .pos-total-title {
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .pos-total-amount {
        font-size: 20px;
        font-weight: 800;
        color: #16a34a;
        line-height: 1.2;
    }
    
    /* Bottom Toolbar */
    .pos-bottom-toolbar {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 5px;
    }
    .pos-bottom-toolbar .btn {
        padding: 6px 4px;
        font-size: 11px;
    }
    
    .bg-purple {
        background: #4f46e5 !important;
        border-color: #4f46e5 !important;
        color: #fff !important;
    }
    .pos-var-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: #2563eb !important;
        color: #ffffff !important;
        font-size: 10px !important;
        font-weight: 700 !important;
        padding: 3px 8px !important;
        border-radius: 12px !important;
        box-shadow: 0 1px 3px rgba(37, 99, 235, 0.35);
        border: none !important;
        text-shadow: none !important;
        line-height: 1.3;
    }
    .pos-var-badge i, .pos-var-badge span {
        color: #ffffff !important;
    }

    /* Order Mode Switcher & Delivery Card */
    .pos-order-mode-toggle .btn {
        border-color: #cbd5e1;
        transition: all 0.2s ease;
    }
    .pos-order-mode-toggle .btn.active {
        background: #0f172a !important;
        color: #ffffff !important;
        border-color: #0f172a !important;
        box-shadow: 0 2px 4px rgba(15, 23, 42, 0.2);
    }
    .pos-shipping-preset-btn {
        border-color: #cbd5e1 !important;
        color: #334155 !important;
        background: #ffffff !important;
        transition: all 0.15s ease;
    }
    .pos-shipping-preset-btn:hover {
        background: #e0f2fe !important;
        border-color: #38bdf8 !important;
        color: #0369a1 !important;
    }
    .pos-shipping-preset-btn.active {
        background: #0284c7 !important;
        color: #ffffff !important;
        border-color: #0284c7 !important;
        font-weight: 700;
        box-shadow: 0 1px 3px rgba(2, 132, 199, 0.3);
    }
    .pos-online-pay-btn {
        border-color: #cbd5e1 !important;
        color: #475569 !important;
        background: #ffffff !important;
        transition: all 0.15s ease;
    }
    .pos-online-pay-btn:hover {
        background: #f1f5f9 !important;
    }
    .pos-online-pay-btn.active {
        background: #0f172a !important;
        color: #ffffff !important;
        border-color: #0f172a !important;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.25);
    }

    /* Clean Minimal Buttons for Online Delivery Modal (Single 1px Border, No Doubling) */
    .modal-ship-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #ffffff;
        border: 1px solid #cbd5e1 !important;
        border-radius: 6px;
        color: #334155 !important;
        font-size: 11px;
        font-weight: 600;
        padding: 5px 12px;
        cursor: pointer;
        transition: all 0.15s ease-in-out;
        outline: none !important;
        box-shadow: none !important;
        line-height: 1.3;
        user-select: none;
    }
    .modal-ship-pill:hover {
        background: #f8fafc;
        border-color: #94a3b8 !important;
        color: #0f172a !important;
    }
    .modal-ship-pill.active {
        background: #0284c7 !important;
        border-color: #0284c7 !important;
        color: #ffffff !important;
        font-weight: 700;
    }

    .modal-pay-pill {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        background: #ffffff;
        border: 1px solid #cbd5e1 !important;
        border-radius: 6px;
        color: #475569 !important;
        font-size: 11px;
        font-weight: 600;
        padding: 7px 8px;
        cursor: pointer;
        transition: all 0.15s ease-in-out;
        outline: none !important;
        box-shadow: none !important;
        line-height: 1.3;
        user-select: none;
    }
    .modal-pay-pill:hover {
        background: #f8fafc;
        border-color: #94a3b8 !important;
        color: #0f172a !important;
    }
    .modal-pay-pill.active {
        background: #0f172a !important;
        border-color: #0f172a !important;
        color: #ffffff !important;
        font-weight: 700;
    }
    .modal-pay-pill.active i {
        color: #38bdf8 !important;
    }

    .modal-btn-cancel {
        background: #ffffff;
        border: 1px solid #cbd5e1 !important;
        border-radius: 6px;
        color: #475569 !important;
        font-weight: 600;
        font-size: 12px;
        padding: 7px 16px;
        outline: none !important;
        box-shadow: none !important;
        cursor: pointer;
        transition: all 0.15s;
    }
    .modal-btn-cancel:hover {
        background: #f1f5f9;
        border-color: #94a3b8 !important;
        color: #0f172a !important;
    }

    .modal-btn-confirm {
        background: #16a34a !important;
        border: 1px solid #16a34a !important;
        border-radius: 6px;
        color: #ffffff !important;
        font-weight: 700;
        font-size: 13px;
        padding: 7px 20px;
        outline: none !important;
        box-shadow: 0 1px 3px rgba(22, 163, 74, 0.25) !important;
        cursor: pointer;
        transition: all 0.15s;
    }
    .modal-btn-confirm:hover {
        background: #15803d !important;
        border-color: #15803d !important;
        color: #ffffff !important;
    }
</style>

<div id="wrapper">
    <div class="pos-container">
    
    <!-- Top utility Header bar -->
    <div class="pos-header-bar">
        <div class="pos-header-left">
            <a href="<?= admin_url('pos') ?>" class="pos-brand">
                <i class="fa fa-shopping-bag"></i> SalesOS POS
            </a>
            <a href="<?= admin_url() ?>" class="btn btn-default btn-xs" style="font-weight:600; border-color:#cbd5e1;">
                <i class="fa fa-arrow-left"></i> Dashboard
            </a>
            <span class="pos-badge-cashier">
                <i class="fa fa-user-circle"></i> <?= e($session['staff_name'] ?? get_staff_full_name()) ?>
            </span>
            <button type="button" class="btn btn-default btn-xs" data-toggle="modal" data-target="#register-status-modal" title="View Register Cash Float & Sales" style="font-weight:600; border-color:#cbd5e1;">
                <i class="fa fa-desktop text-success"></i> Register Status
            </button>
        </div>
        <div class="pos-header-right">
            <button class="pos-btn-icon" title="Customer Display" onclick="window.open('<?= admin_url('pos/customer_display') ?>', 'CustomerDisplay', 'width=1100,height=750');">
                <i class="fa fa-tv"></i>
            </button>
            <button class="pos-btn-icon" title="Calculator (F8)" data-toggle="modal" data-target="#pos-calculator-modal">
                <i class="fa fa-calculator"></i>
            </button>
            <button class="pos-btn-icon" title="Toggle Fullscreen" onclick="toggleFullScreen();">
                <i class="fa fa-arrows-alt"></i>
            </button>
            <button class="pos-btn-icon" title="Sync / Reload" onclick="location.reload();">
                <i class="fa fa-refresh"></i>
            </button>
            <button type="button" class="btn btn-danger btn-xs bold" data-toggle="modal" data-target="#close-session-modal" style="height:32px; padding:6px 12px; border-radius:6px;">
                <i class="fa fa-power-off"></i> Close Session
            </button>
        </div>
    </div>

    <!-- Main Grid Workspace (Modern 2-Column POS Layout) -->
    <div class="row pos-main-row">
        <!-- 1. Left Column: Product Cards, Search & Category Pills (60% Width) -->
        <div class="col-md-7">
            <div class="pos-panel">
                <!-- Search bar -->
                <div class="pos-products-header">
                    <div class="input-group">
                        <span class="input-group-addon" style="background:#f8fafc; border-color:#cbd5e1; color:#6366f1;"><i class="fa fa-barcode"></i></span>
                        <input type="text" id="pos-product-search" class="form-control" placeholder="Scan Barcode / IMEI / SKU / Name... (F4)" autocomplete="off" style="border-color:#cbd5e1;">
                        <span class="input-group-btn">
                            <button class="btn btn-default" type="button" onclick="$('#pos-product-search').val('').trigger('input').focus();" title="Clear Search" style="border-color:#cbd5e1;">
                                <i class="fa fa-times text-muted"></i>
                            </button>
                        </span>
                    </div>
                </div>

                <!-- Horizontal Category Pills Bar -->
                <div class="pos-category-pills-wrap">
                    <div class="pos-category-pills" id="pos-category-pills">
                        <button type="button" class="pos-category-pill active" data-category-id="all">
                            <i class="fa fa-cubes"></i> All Categories
                        </button>
                        <?php 
                            $cats = $this->db->get(db_prefix() . 'inventory_categories')->result_array();
                            foreach ($cats as $cat):
                        ?>
                            <button type="button" class="pos-category-pill" data-category-id="<?= $cat['id'] ?>">
                                <?= e($cat['name']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Product Cards Grid -->
                <div class="pos-products-grid" id="pos-products-grid">
                    <?php foreach ($products as $prod): ?>
                        <?php
                            $is_variable = ($prod['product_type'] === 'variable');
                            $stock = (float) $prod['stock_on_hand'];
                            $is_out = $stock <= 0;
                            $rate = (float) ($prod['rate'] ?? 0.00);
                        ?>
                        <div class="pos-product-card-container" 
                             data-id="<?= $prod['id'] ?>"
                             data-sku="<?= e($prod['sku']) ?>"
                             data-name="<?= e($prod['name']) ?>"
                             data-category-id="<?= $prod['category_id'] ?>"
                             data-product-type="<?= e($prod['product_type']) ?>"
                             data-stock="<?= $stock ?>"
                             data-rate="<?= $rate ?>">
                            <div class="pos-product-card <?= (!$is_variable && $is_out && empty($allow_oversell)) ? 'out-of-stock' : '' ?>" style="<?= $is_variable ? 'border-bottom: 2px solid #6366f1;' : '' ?>">
                                <?php if (!$is_variable && $is_out && empty($allow_oversell)): ?>
                                    <div class="pos-card-out-badge">স্টক নেই</div>
                                <?php endif; ?>
                                <div>
                                    <!-- Display image helper -->
                                    <div class="pos-product-card-img">
                                        <?php if (!empty($prod['image'])): ?>
                                            <?php 
                                                $img_src = (strpos($prod['image'], 'http://') === 0 || strpos($prod['image'], 'https://') === 0 || strpos($prod['image'], '/') === 0) 
                                                    ? $prod['image'] 
                                                    : base_url($prod['image']);
                                            ?>
                                            <img src="<?= e($img_src) ?>" alt="<?= e($prod['name']) ?>" style="width: 100%; height: 100%; object-fit: cover;" />
                                        <?php else: ?>
                                            <i class="fa fa-cube" style="font-size:28px; color:#cbd5e1;"></i>
                                        <?php endif; ?>
                                    </div>
                                    <h5 class="pos-product-card-name" title="<?= e($prod['name']) ?>"><?= e($prod['name']) ?></h5>
                                    <?php if ($is_variable): ?>
                                        <div style="margin: 4px 0;">
                                            <span class="pos-var-badge">
                                                <i class="fa fa-sitemap"></i> <span>Options (<?= (int)$prod['variation_count'] ?>)</span>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="pos-card-bottom">
                                    <div class="pos-card-price"><?= salesos_format_number($rate) ?> BDT</div>
                                    <div class="pos-card-stock">
                                        <small class="text-muted">SKU: <?= e($prod['sku'] ?: '-') ?></small>
                                        <?php if ($is_variable): ?>
                                            <?= $is_out ? '<span class="label label-danger" style="font-size:9px;">0 pcs in var</span>' : '<span class="label label-success" style="font-size:9px;">' . number_format($stock, 0) . ' pcs</span>' ?>
                                        <?php else: ?>
                                            <?= $is_out ? '<span class="label label-danger" style="font-size:9px;">0 pcs</span>' : '<span class="label label-success" style="font-size:9px;">' . number_format($stock, 0) . ' pcs</span>' ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Footer Product count display -->
                <div class="pos-grid-footer" style="display:flex; justify-content:space-between; align-items:center; padding-top:8px; border-top:1px solid #e2e8f0; font-size:11px; color:#64748b; margin-top:6px;">
                    <span id="pos-grid-count"><i class="fa fa-cubes"></i> Total Products: <?= count($products) ?></span>
                    <span class="text-muted"><i class="fa fa-keyboard-o"></i> Barcode Search: <strong>F4</strong></span>
                </div>
            </div>
        </div>

        <!-- 2. Right Column: Checkout, Cart & Summary (40% Width) -->
        <div class="col-md-5">
            <div class="pos-panel">
                <!-- Order Mode Switcher (In-Store vs Online) -->
                <div class="pos-mode-switcher-container" style="margin-bottom: 8px;">
                    <div class="btn-group btn-group-justified pos-order-mode-toggle" role="group">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-default active" id="btn-mode-offline" onclick="setPosOrderMode('offline')" style="font-weight: 600; font-size: 12px; height: 32px;">
                                <i class="fa fa-shopping-bag text-primary"></i> In-Store (দোকানে)
                            </button>
                        </div>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-default" id="btn-mode-online" onclick="setPosOrderMode('online')" style="font-weight: 600; font-size: 12px; height: 32px;">
                                <i class="fa fa-truck text-success"></i> Online (ডেলিভারি)
                            </button>
                        </div>
                    </div>
                    <input type="hidden" name="order_mode" id="pos_order_mode" value="offline">
                </div>

                <!-- Customer selection header bar -->
                <div class="pos-cart-header">
                    <div style="flex-grow: 1; min-width: 0; position: relative;">
                        <input type="hidden" name="client_id" id="client_id" value="<?= get_option('pos_default_walkin_client_id') ?>">
                        <input type="hidden" name="lead_id" id="lead_id" value="">
                        
                        <div class="input-group input-group-sm" style="width: 100%;">
                            <span class="input-group-addon" id="pos-cust-search-icon" style="background: #f8fafc; border-color: #cbd5e1; color: #475569;"><i class="fa fa-user"></i></span>
                            <input type="text" id="pos_customer_search" class="form-control" placeholder="Search Customer / Lead by Phone, Name, or ID..." autocomplete="off" style="font-size: 12px; font-weight: 500; height: 30px;">
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-default" id="btn-edit-customer" style="height: 30px; padding: 4px 8px; border-color: #cbd5e1; display: none;" title="Edit Customer" onclick="openEditCustomerModal();">
                                     <i class="fa fa-pencil text-muted"></i>
                                </button>
                                <button type="button" class="btn btn-primary bg-purple" style="height: 30px; padding: 4px 9px; border-radius: 0 4px 4px 0;" data-toggle="modal" data-target="#customer-quick-modal" title="Quick Add Customer">
                                    <i class="fa fa-user-plus"></i>
                                </button>
                            </span>
                        </div>

                        <!-- Selected Customer / Lead Badge Display -->
                        <div id="pos-selected-badge" style="display: none; margin-top: 5px; padding: 4px 8px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 4px; font-size: 12px; color: #166534; justify-content: space-between; align-items: center;">
                            <span id="pos-selected-badge-text" style="font-weight: 600;"></span>
                            <a href="#" id="pos-selected-remove" style="color: #ef4444; font-weight: bold; margin-left: 8px; text-decoration: none;" title="Reset to Walk-in Customer">&times; Clear</a>
                        </div>

                        <!-- Dynamic Live Search Dropdown -->
                        <div id="pos-customer-search-results" style="display: none; position: absolute; top: 34px; left: 0; right: 0; background: #fff; border: 1px solid #cbd5e1; border-radius: 4px; box-shadow: 0 6px 18px rgba(0,0,0,0.15); z-index: 9999; max-height: 280px; overflow-y: auto;">
                        </div>
                    </div>
                </div>

                <!-- Online Mode Status Indicator (Compact & Non-intrusive) -->
                <div id="pos-online-status-strip" style="display: none; margin-bottom: 8px; padding: 6px 10px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; font-size: 11px; color: #166534; justify-content: space-between; align-items: center;">
                    <span><i class="fa fa-truck text-success"></i> <strong>অনলাইন ডেলিভারি মোড সক্রিয়</strong></span>
                    <button type="button" class="btn btn-default btn-xs" onclick="openDeliveryConfirmModal();" style="padding: 2px 8px; font-size: 11px; font-weight: 600; border-color: #86efac; color: #166534; background: #fff;">
                        <i class="fa fa-map-marker text-danger"></i> ডেলিভারি তথ্য দেখুন / এডিট
                    </button>
                </div>

                <!-- Hidden inputs for active delivery state -->
                <input type="hidden" id="pos_recipient_name" value="">
                <input type="hidden" id="pos_recipient_phone" value="">
                <input type="hidden" id="pos_division_id" value="">
                <input type="hidden" id="pos_district_id" value="">
                <input type="hidden" id="pos_upazila_id" value="">
                <input type="hidden" id="pos_union_id" value="">
                <input type="hidden" id="pos_delivery_address" value="">
                <input type="hidden" id="pos_online_payment_option" value="cod">
                <input type="hidden" id="pos_advance_amount" value="0">
                <input type="hidden" id="pos_advance_payment_mode" value="bkash">
                <input type="hidden" id="pos_advance_ref" value="">


                <!-- Cart table area -->
                <div class="pos-cart-items-container">
                    <table class="table pos-cart-table" id="pos-cart-table">
                        <thead>
                            <tr>
                                <th width="48%">Product</th>
                                <th width="24%" class="text-center">Qty</th>
                                <th width="20%" class="text-right">Price</th>
                                <th width="8%" class="text-center"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Cart rows are generated dynamically via JavaScript -->
                        </tbody>
                    </table>
                    
                    <div class="pos-empty-cart" id="pos-empty-cart-view">
                        <div style="background:#f1f5f9; border-radius:50%; width:56px; height:56px; display:flex; align-items:center; justify-content:center; margin-bottom:8px;">
                            <i class="fa fa-shopping-cart" style="font-size:24px; color:#94a3b8; margin:0;"></i>
                        </div>
                        <div style="font-weight:600; color:#475569; font-size:13px;">Cart is empty</div>
                        <div style="color:#94a3b8; font-size:11px;">Click a product or scan barcode</div>
                    </div>
                </div>

                <!-- Calculation/totals footer block -->
                <div class="pos-calc-block">
                    <!-- Row 1: Subtotal & Tax -->
                    <div class="pos-calc-row">
                        <span>Subtotal:</span>
                        <span><strong id="calc-subtotal">0</strong> BDT</span>
                    </div>
                    
                    <!-- Row 2: Discount & Shipping -->
                    <div class="pos-calc-row-main">
                        <div class="pos-calc-col">
                            <label class="control-label" style="font-size:11px; margin-bottom:2px;">Discount</label>
                            <div class="input-group input-group-sm">
                                <input type="number" id="calc-discount-val" class="form-control" value="0" min="0" step="any" style="border-color:#cbd5e1;">
                                <span class="input-group-btn" style="width: 55px;">
                                    <select id="calc-discount-type" class="form-control" style="padding: 0 5px; height: 30px; border-color:#cbd5e1;">
                                        <option value="fixed">BDT</option>
                                        <option value="percent">%</option>
                                    </select>
                                </span>
                            </div>
                        </div>
                        <div class="pos-calc-col">
                            <label class="control-label" style="font-size:11px; margin-bottom:2px;">Shipping Charge</label>
                            <input type="number" id="calc-shipping" class="form-control input-sm" value="0" min="0" step="any" style="border-color:#cbd5e1;">
                        </div>
                    </div>
                </div>

                <!-- Total Payable & Quick Cash bar -->
                <div class="pos-total-payable-card">
                    <div>
                        <div class="pos-total-title">Total Payable</div>
                        <div class="pos-total-amount" id="pos-total-payable">0 BDT</div>
                    </div>
                    <div>
                        <button type="button" class="btn btn-success bold" id="btn-quick-cash" style="background:#16a34a; border-color:#16a34a; padding:7px 16px; font-size:13px; border-radius:6px; box-shadow:0 2px 4px rgba(22,163,74,0.25);" title="Quick Cash / Complete Order (F2)">
                            <i class="fa fa-money" id="btn-quick-cash-icon"></i> <span id="btn-quick-cash-text">Quick Cash</span> <span id="btn-quick-cash-kbd" style="font-size:10px; opacity:0.85;">(F2)</span>
                        </button>
                    </div>

                </div>

                <!-- Bottom Toolbar buttons -->
                <div class="pos-bottom-toolbar">
                    <button type="button" class="btn btn-primary bg-purple btn-sm bold" data-toggle="modal" data-target="#payment-details-modal" title="Other Payment Methods">
                        <i class="fa fa-credit-card"></i> More Pay
                    </button>
                    <button type="button" class="btn btn-warning btn-sm bold" id="btn-add-hold" style="background:#f59e0b; border-color:#f59e0b; color:#fff;" title="Hold Cart (F9)">
                        <i class="fa fa-pause"></i> Hold Cart <span style="font-size:9px; opacity:0.85;">(F9)</span>
                    </button>
                    <button type="button" class="btn btn-default btn-sm bold" id="btn-list-hold" style="border-color:#cbd5e1; color:#475569;" title="View Held Carts">
                        <i class="fa fa-folder-open text-warning"></i> Held Carts
                    </button>
                    <button type="button" class="btn btn-default btn-sm bold" id="btn-sale-list" style="border-color:#cbd5e1; color:#475569;" title="Sales of Current Shift">
                        <i class="fa fa-list text-primary"></i> Sale List
                    </button>
                    <a href="<?= admin_url('returns') ?>" class="btn btn-default btn-sm bold" target="_blank" style="border-color:#cbd5e1; color:#475569;" title="Manage Returns">
                        <i class="fa fa-reply text-info"></i> Return
                    </a>
                    <button type="button" class="btn btn-danger btn-sm bold" id="btn-clear-cart" style="background:#ef4444; border-color:#ef4444;" title="Clear Cart (ESC)">
                        <i class="fa fa-trash"></i> Clear <span style="font-size:9px; opacity:0.85;">(ESC)</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

<!-- Modal: Close Session -->
<div class="modal fade" id="close-session-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Close Cashier Session</h4>
            </div>
            <?= form_open(admin_url('pos/close_session')) ?>
            <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
            <div class="modal-body">
                <div class="alert alert-info">
                    <strong>Opening Cash Float:</strong> <?= salesos_format_number($session['opening_balance']) ?> BDT
                </div>
                <div class="form-group">
                    <label for="closing_balance" class="control-label">Closing Cash Balance (BDT)</label>
                    <input type="number" step="0.01" name="closing_balance" id="closing_balance" class="form-control" required placeholder="Count physical cash in drawer...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Yes, Close Session</button>
            </div>
            <?= form_close() ?>
        </div>
    </div>
</div>

<!-- Modal: Quick Customer Add -->
<div class="modal fade" id="customer-quick-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Quick Add Customer</h4>
            </div>
            <div class="modal-body">
                <div id="customer-modal-alert" style="display:none;" class="alert alert-danger"></div>
                
                <div class="form-group">
                    <label for="m_company" class="control-label">Customer Name</label>
                    <input type="text" id="m_company" class="form-control" placeholder="Customer Name" required>
                </div>
                <div class="form-group">
                    <label for="m_phone" class="control-label">Phone Number</label>
                    <input type="text" id="m_phone" class="form-control" placeholder="Phone Number" required>
                </div>
                <div class="form-group">
                    <label for="m_email" class="control-label">Email Address (Optional)</label>
                    <input type="email" id="m_email" class="form-control" placeholder="Email Address">
                </div>
                <!-- Cascading BD Geocode Dropdowns -->
                <div class="row" style="margin: 0 -5px 10px -5px;">
                    <div class="col-xs-6" style="padding: 0 5px;">
                        <label for="m_division_id" class="control-label" style="font-size: 11px;">বিভাগ (Division)</label>
                        <select id="m_division_id" class="form-control input-sm">
                            <option value="">-- বিভাগ নির্বাচন --</option>
                            <?php if (!empty($divisions)) : ?>
                                <?php foreach ($divisions as $div) : ?>
                                    <option value="<?= $div['id'] ?>" data-name="<?= htmlspecialchars($div['name']) ?>"><?= htmlspecialchars($div['name']) ?> (<?= htmlspecialchars($div['bn_name']) ?>)</option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-xs-6" style="padding: 0 5px;">
                        <label for="m_district_id" class="control-label" style="font-size: 11px;">জেলা (District)</label>
                        <select id="m_district_id" class="form-control input-sm" disabled>
                            <option value="">-- আগে বিভাগ বাছুন --</option>
                        </select>
                    </div>
                </div>

                <div class="row" style="margin: 0 -5px 10px -5px;">
                    <div class="col-xs-6" style="padding: 0 5px;">
                        <label for="m_upazila_id" class="control-label" style="font-size: 11px;">উপজেলা / থানা (Upazila)</label>
                        <select id="m_upazila_id" class="form-control input-sm" disabled>
                            <option value="">-- আগে জেলা বাছুন --</option>
                        </select>
                    </div>
                    <div class="col-xs-6" style="padding: 0 5px;">
                        <label for="m_union_id" class="control-label" style="font-size: 11px;">ইউনিয়ন / এলাকা (Union)</label>
                        <select id="m_union_id" class="form-control input-sm" disabled>
                            <option value="">-- ইউনিয়ন বাছুন --</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="m_address" class="control-label" style="font-size: 11px;">বাসা/রোড/সেক্টর (Detailed Street Address)</label>
                    <input type="text" id="m_address" class="form-control input-sm" placeholder="বাসা নম্বর, রোড নম্বর, এলাকা বা ল্যান্ডমার্ক...">
                </div>
                <input type="hidden" id="m_city">
                <input type="hidden" id="m_state">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btn-save-customer">Save Customer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Quick Customer Edit -->
<div class="modal fade" id="customer-edit-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Edit Customer</h4>
            </div>
            <div class="modal-body">
                <div id="customer-edit-modal-alert" style="display:none;" class="alert alert-danger"></div>
                <input type="hidden" id="edit_m_id">
                
                <div class="form-group">
                    <label for="edit_m_company" class="control-label">Customer Name</label>
                    <input type="text" id="edit_m_company" class="form-control" placeholder="Customer Name" required>
                </div>
                <div class="form-group">
                    <label for="edit_m_phone" class="control-label">Phone Number</label>
                    <input type="text" id="edit_m_phone" class="form-control" placeholder="Phone Number" required>
                </div>
                <div class="form-group">
                    <label for="edit_m_email" class="control-label">Email Address (Optional)</label>
                    <input type="email" id="edit_m_email" class="form-control" placeholder="Email Address">
                </div>
                <!-- Cascading BD Geocode Dropdowns -->
                <div class="row" style="margin: 0 -5px 10px -5px;">
                    <div class="col-xs-6" style="padding: 0 5px;">
                        <label for="edit_m_division_id" class="control-label" style="font-size: 11px;">বিভাগ (Division)</label>
                        <select id="edit_m_division_id" class="form-control input-sm">
                            <option value="">-- বিভাগ নির্বাচন --</option>
                            <?php if (!empty($divisions)) : ?>
                                <?php foreach ($divisions as $div) : ?>
                                    <option value="<?= $div['id'] ?>" data-name="<?= htmlspecialchars($div['name']) ?>"><?= htmlspecialchars($div['name']) ?> (<?= htmlspecialchars($div['bn_name']) ?>)</option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-xs-6" style="padding: 0 5px;">
                        <label for="edit_m_district_id" class="control-label" style="font-size: 11px;">জেলা (District)</label>
                        <select id="edit_m_district_id" class="form-control input-sm" disabled>
                            <option value="">-- আগে বিভাগ বাছুন --</option>
                        </select>
                    </div>
                </div>

                <div class="row" style="margin: 0 -5px 10px -5px;">
                    <div class="col-xs-6" style="padding: 0 5px;">
                        <label for="edit_m_upazila_id" class="control-label" style="font-size: 11px;">উপজেলা / থানা (Upazila)</label>
                        <select id="edit_m_upazila_id" class="form-control input-sm" disabled>
                            <option value="">-- আগে জেলা বাছুন --</option>
                        </select>
                    </div>
                    <div class="col-xs-6" style="padding: 0 5px;">
                        <label for="edit_m_union_id" class="control-label" style="font-size: 11px;">ইউনিয়ন / এলাকা (Union)</label>
                        <select id="edit_m_union_id" class="form-control input-sm" disabled>
                            <option value="">-- ইউনিয়ন বাছুন --</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="edit_m_address" class="control-label" style="font-size: 11px;">বাসা/রোড/সেক্টর (Detailed Street Address)</label>
                    <input type="text" id="edit_m_address" class="form-control input-sm" placeholder="বাসা নম্বর, রোড নম্বর, এলাকা বা ল্যান্ডমার্ক...">
                </div>
                <input type="hidden" id="edit_m_city">
                <input type="hidden" id="edit_m_state">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btn-update-customer">Update Customer</button>
            </div>

        </div>
    </div>
</div>

<!-- Modal: Online Delivery & Courier Confirmation Modal -->
<div class="modal fade" id="online-delivery-confirm-modal" tabindex="-1" role="dialog" data-backdrop="static">
    <div class="modal-dialog" role="document" style="max-width: 580px;">
        <div class="modal-content" style="border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.25);">
            <div class="modal-header" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: #fff; border-top-left-radius: 8px; border-top-right-radius: 8px; padding: 12px 16px;">
                <button type="button" class="close" data-dismiss="modal" style="color: #fff; opacity: 0.9; font-size: 22px;">&times;</button>
                <h4 class="modal-title" style="font-weight: 600; font-size: 15px; display: flex; align-items: center; justify-content: space-between; margin: 0;">
                    <span><i class="fa fa-truck"></i> অনলাইন ডেলিভারি ও কুরিয়ার তথ্য নিশ্চিতকরণ</span>
                    <span class="badge" style="background: #38bdf8; color: #082f49; font-weight: 700; font-size: 10px; padding: 4px 8px;">SalesOS & Courier</span>
                </h4>
            </div>
            <div class="modal-body" style="padding: 16px; font-size: 12px;">
                <div id="delivery-modal-alert" style="display: none;" class="alert alert-danger"></div>

                <!-- Customer selection context banner -->
                <div id="modal-customer-context" style="margin-bottom: 12px; padding: 6px 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <span class="text-muted" style="font-size: 11px;">কাস্টমার / লিড:</span>
                        <strong id="modal-customer-label" style="color: #0f172a; margin-left: 4px;">Walk-in Customer</strong>
                    </div>
                    <span id="modal-customer-badge" class="label label-default" style="font-size: 10px;">Direct Sale</span>
                </div>

                <!-- Recipient Info Row -->
                <div class="row" style="margin: 0 -5px 10px -5px;">
                    <div class="col-xs-6" style="padding: 0 5px;">
                        <label class="control-label" style="font-size: 11px; margin-bottom: 3px;">প্রাপকের নাম (Recipient Name) <span class="text-danger">*</span></label>
                        <input type="text" id="modal_recipient_name" class="form-control input-sm" placeholder="e.g. Rahim Khan" style="height: 30px;" required>
                    </div>
                    <div class="col-xs-6" style="padding: 0 5px;">
                        <label class="control-label" style="font-size: 11px; margin-bottom: 3px;">১১ ডিজিট মোবাইল (Phone) <span class="text-danger">*</span></label>
                        <input type="text" id="modal_recipient_phone" class="form-control input-sm" placeholder="017xxxxxxxx" maxlength="14" style="height: 30px;" required>
                    </div>
                </div>

                <!-- BD Geocode Cascading Dropdowns: Division & District -->
                <div class="row" style="margin: 0 -5px 10px -5px;">
                    <div class="col-xs-6" style="padding: 0 5px;">
                        <label class="control-label" style="font-size: 11px; margin-bottom: 3px;">বিভাগ (Division) <span class="text-danger">*</span></label>
                        <select id="modal_division_id" class="form-control input-sm" style="height: 30px;">
                            <option value="">-- বিভাগ নির্বাচন করুন --</option>
                            <?php if (!empty($divisions)) : ?>
                                <?php foreach ($divisions as $div) : ?>
                                    <option value="<?= $div['id'] ?>" data-name="<?= htmlspecialchars($div['name']) ?>"><?= htmlspecialchars($div['name']) ?> (<?= htmlspecialchars($div['bn_name']) ?>)</option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-xs-6" style="padding: 0 5px;">
                        <label class="control-label" style="font-size: 11px; margin-bottom: 3px;">জেলা (District) <span class="text-danger">*</span></label>
                        <select id="modal_district_id" class="form-control input-sm" style="height: 30px;" disabled>
                            <option value="">-- আগে বিভাগ বাছুন --</option>
                        </select>
                    </div>
                </div>

                <!-- BD Geocode Cascading Dropdowns: Upazila & Union -->
                <div class="row" style="margin: 0 -5px 10px -5px;">
                    <div class="col-xs-6" style="padding: 0 5px;">
                        <label class="control-label" style="font-size: 11px; margin-bottom: 3px;">উপজেলা / থানা (Upazila) <span class="text-danger">*</span></label>
                        <select id="modal_upazila_id" class="form-control input-sm" style="height: 30px;" disabled>
                            <option value="">-- আগে জেলা বাছুন --</option>
                        </select>
                    </div>
                    <div class="col-xs-6" style="padding: 0 5px;">
                        <label class="control-label" style="font-size: 11px; margin-bottom: 3px;">ইউনিয়ন / এলাকা (Union / Ward)</label>
                        <select id="modal_union_id" class="form-control input-sm" style="height: 30px;" disabled>
                            <option value="">-- ইউনিয়ন বাছুন --</option>
                        </select>
                    </div>
                </div>

                <!-- Detailed Address -->
                <div class="form-group" style="margin-bottom: 10px;">
                    <label class="control-label" style="font-size: 11px; margin-bottom: 3px;">বাসা নম্বর, রোড, সেক্টর বা ল্যান্ডমার্ক (Detailed Address) <span class="text-danger">*</span></label>
                    <input type="text" id="modal_delivery_address" class="form-control input-sm" placeholder="যেমন: বাসা ১২, রোড ৪, সেক্টর ৭..." style="height: 30px;">
                </div>

                <!-- Delivery Charge Presets -->
                <div class="form-group" style="margin-bottom: 12px;">
                    <label class="control-label" style="font-size: 11px; margin-bottom: 4px;">ডেলিভারি চার্জ (Shipping Charge):</label>
                    <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                        <button type="button" class="modal-ship-pill modal-ship-preset" id="modal-preset-70" onclick="setModalShipping(70, 'inside_dhaka')">
                            ৳৭০ ঢাকা ভিতরে
                        </button>
                        <button type="button" class="modal-ship-pill modal-ship-preset" id="modal-preset-130" onclick="setModalShipping(130, 'outside_dhaka')">
                            ৳১৩০ ঢাকা বাইরে
                        </button>
                        <button type="button" class="modal-ship-pill modal-ship-preset" id="modal-preset-0" onclick="setModalShipping(0, 'free')">
                            ৳০ ফ্রি
                        </button>
                        <div class="input-group input-group-sm" style="width: 120px; margin-left: auto;">
                            <span class="input-group-addon" style="padding: 2px 6px; background: #f8fafc; border-color: #cbd5e1; font-weight: 700;">৳</span>
                            <input type="number" id="modal_shipping_input" class="form-control" value="130" min="0" step="any" style="height: 28px; font-size: 12px; font-weight: 600; border-color: #cbd5e1;">
                        </div>
                    </div>
                </div>

                <!-- Online Payment Options -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; margin-bottom: 12px;">
                    <label class="control-label" style="font-size: 11px; margin-bottom: 5px; display: block;">পেমেন্ট মেথড হ্যান্ডলিং (Payment Handling):</label>
                    <div style="display: flex; gap: 6px; margin-bottom: 8px;">
                        <button type="button" class="modal-pay-pill modal-pay-btn active" id="modal-btn-pay-cod" onclick="setModalPaymentOption('cod')">
                            <i class="fa fa-money text-success"></i> ফুল COD
                        </button>
                        <button type="button" class="modal-pay-pill modal-pay-btn" id="modal-btn-pay-adv" onclick="setModalPaymentOption('advance_delivery')">
                            <i class="fa fa-bolt text-warning"></i> অগ্রিম ডেলিভারি
                        </button>
                        <button type="button" class="modal-pay-pill modal-pay-btn" id="modal-btn-pay-full" onclick="setModalPaymentOption('full_advance')">
                            <i class="fa fa-check-circle text-primary"></i> সম্পূর্ণ অগ্রিম
                        </button>
                    </div>
                    <input type="hidden" id="modal_payment_option" value="cod">

                    <!-- Advance payment inputs -->
                    <div id="modal-advance-details" style="display: none; background: #fff; border: 1px solid #cbd5e1; border-radius: 4px; padding: 8px; margin-top: 6px;">
                        <div class="row" style="margin: 0 -4px;">
                            <div class="col-xs-4" style="padding: 0 4px;">
                                <label style="font-size: 10px; color: #64748b; margin-bottom: 2px;">অগ্রিম টাকা (৳)</label>
                                <input type="number" id="modal_advance_amount" class="form-control input-sm" value="130" min="0" step="any" style="height: 28px; font-weight: 600; border-color: #cbd5e1;">
                            </div>
                            <div class="col-xs-4" style="padding: 0 4px;">
                                <label style="font-size: 10px; color: #64748b; margin-bottom: 2px;">পেমেন্ট মাধ্যম</label>
                                <select id="modal_advance_payment_mode" class="form-control input-sm" style="height: 28px; padding: 2px 4px; border-color: #cbd5e1;">
                                    <option value="bkash">bKash</option>
                                    <option value="nagad">Nagad</option>
                                    <option value="rocket">Rocket</option>
                                    <option value="bank">Bank</option>
                                    <option value="cash">Cash</option>
                                </select>
                            </div>
                            <div class="col-xs-4" style="padding: 0 4px;">
                                <label style="font-size: 10px; color: #64748b; margin-bottom: 2px;">TrxID / Ref</label>
                                <input type="text" id="modal_advance_ref" class="form-control input-sm" placeholder="Txn ID" style="height: 28px; border-color: #cbd5e1;">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Financial Calculation & Collectable COD Box -->
                <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 10px;">
                    <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px; color: #334155;">
                        <span>প্রোডাক্ট সাবটোটাল: <strong id="modal-calc-subtotal">৳0</strong></span>
                        <span>শিপিং: <strong id="modal-calc-shipping">৳0</strong></span>
                        <span>সর্বমোট বিল: <strong id="modal-calc-total" style="color: #0f172a;">৳0</strong></span>
                    </div>
                    <div style="border-top: 1px dashed #93c5fd; margin-top: 6px; padding-top: 6px; display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: 600; color: #1e3a8a; font-size: 13px;">কুরিয়ার থেকে আদায়যোগ্য COD:</span>
                        <span id="modal-calc-cod" style="font-size: 16px; font-weight: 800; color: #0284c7;">৳0</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                <button type="button" class="modal-btn-cancel" data-dismiss="modal">
                    <i class="fa fa-times"></i> বাতিল / Cancel
                </button>
                <button type="button" class="modal-btn-confirm" id="btn-submit-delivery-modal">
                    <i class="fa fa-check"></i> নিশ্চিত ও অর্ডার সম্পন্ন করুন (Confirm & Place Order)
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: More Payment Details -->
<div class="modal fade" id="payment-details-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Enter Payment Details</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="payment_method" class="control-label">Payment Method</label>
                    <select id="payment_method" class="form-control">
                        <option value="cash">Cash</option>
                        <option value="bkash">bKash</option>
                        <option value="nagad">Nagad</option>
                        <option value="card">Card / POS machine</option>
                        <option value="pending_payment">Pending Payment (পেন্ডিং পেমেন্ট)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="payment_ref" class="control-label">Payment Ref / TxnID (Optional)</label>
                    <input type="text" id="payment_ref" class="form-control" placeholder="e.g. bkash TxnID, Card digits...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="btn-process-checkout-more">Complete Checkout</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Holds List -->
<div class="modal fade" id="holds-list-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-folder-open"></i> Held Carts List</h4>
            </div>
            <div class="modal-body" style="max-height: 450px; overflow-y: auto;">
                <table class="table table-bordered table-striped" id="holds-table">
                    <thead>
                        <tr>
                            <th>Hold ID</th>
                            <th>Customer</th>
                            <th>Hold Note</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Populated via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Sales List -->
<div class="modal fade" id="sales-list-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-list"></i> Cash Counter Sales (Current Session)</h4>
            </div>
            <div class="modal-body" style="max-height: 450px; overflow-y: auto;">
                <table class="table table-bordered table-striped" id="sales-table">
                    <thead>
                        <tr>
                            <th>Invoice Number</th>
                            <th>Customer</th>
                            <th>Payment Method</th>
                            <th>Total Amount</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Populated via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Register Status -->
<div class="modal fade" id="register-status-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-desktop"></i> Active Register Status</h4>
            </div>
            <div class="modal-body">
                <div class="list-group">
                    <div class="list-group-item">
                        <span class="badge" style="background:#4f46e5; font-size:12px;" id="reg-status-opening">0 BDT</span>
                        Opening cashfloat balance:
                    </div>
                    <div class="list-group-item">
                        <span class="badge" style="background:#22c55e; font-size:12px;" id="reg-status-sales">0 BDT</span>
                        Sales registered (paid):
                    </div>
                    <div class="list-group-item">
                        <span class="badge" style="background:#e0e7ff; color:#4f46e5; font-size:12px;" id="reg-status-total">0 BDT</span>
                        Expected Drawer cash balance:
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Calculator -->
<div class="modal fade" id="pos-calculator-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document" style="width: 300px; margin: 100px auto;">
        <div class="modal-content" style="background:#1e293b; color:#fff; border-radius:12px; border:none; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3);">
            <div class="modal-header" style="background:#1e293b !important; border-bottom: 1px solid #334155; padding: 12px 15px;">
                <button type="button" class="close" data-dismiss="modal" style="color:#ffffff !important; opacity:0.9; font-size: 24px; text-shadow: none; float: right; border: none; background: transparent; line-height: 1;">&times;</button>
                <h4 class="modal-title" style="color:#ffffff !important; font-weight:700; font-size:15px; margin: 0;"><i class="fa fa-calculator"></i> Calculator</h4>
            </div>
            <div class="modal-body" style="padding:15px; background: #1e293b;">
                <!-- Calculator Screen -->
                <input type="text" id="calc-screen" class="form-control text-right" readonly 
                       style="font-size:24px; height:50px; background:#0f172a; color:#22c55e; border:none; margin-bottom:15px; font-weight:700; padding:10px; border-radius:6px; font-family: monospace;">
                
                <!-- Calculator Keys Grid -->
                <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:8px;">
                    <button type="button" class="btn btn-default calc-btn" data-val="C" style="background:#475569; color:#fff; border:none; font-weight:700; font-size:16px; padding: 12px 0;">C</button>
                    <button type="button" class="btn btn-default calc-btn" data-val="DEL" style="background:#475569; color:#fff; border:none; font-weight:700; font-size:14px; padding: 12px 0;"><i class="fa fa-long-arrow-left"></i></button>
                    <button type="button" class="btn btn-default calc-btn" data-val="/" style="background:#f97316; color:#fff; border:none; font-weight:700; font-size:16px; padding: 12px 0;">/</button>
                    <button type="button" class="btn btn-default calc-btn" data-val="*" style="background:#f97316; color:#fff; border:none; font-weight:700; font-size:16px; padding: 12px 0;">*</button>
                    
                    <button type="button" class="btn btn-default calc-btn" data-val="7" style="background:#334155; color:#fff; border:none; font-weight:700; font-size:16px; padding: 12px 0;">7</button>
                    <button type="button" class="btn btn-default calc-btn" data-val="8" style="background:#334155; color:#fff; border:none; font-weight:700; font-size:16px; padding: 12px 0;">8</button>
                    <button type="button" class="btn btn-default calc-btn" data-val="9" style="background:#334155; color:#fff; border:none; font-weight:700; font-size:16px; padding: 12px 0;">9</button>
                    <button type="button" class="btn btn-default calc-btn" data-val="-" style="background:#f97316; color:#fff; border:none; font-weight:700; font-size:16px; padding: 12px 0;">-</button>
                    
                    <button type="button" class="btn btn-default calc-btn" data-val="4" style="background:#334155; color:#fff; border:none; font-weight:700; font-size:16px; padding: 12px 0;">4</button>
                    <button type="button" class="btn btn-default calc-btn" data-val="5" style="background:#334155; color:#fff; border:none; font-weight:700; font-size:16px; padding: 12px 0;">5</button>
                    <button type="button" class="btn btn-default calc-btn" data-val="6" style="background:#334155; color:#fff; border:none; font-weight:700; font-size:16px; padding: 12px 0;">6</button>
                    <button type="button" class="btn btn-default calc-btn" data-val="+" style="background:#f97316; color:#fff; border:none; font-weight:700; font-size:16px; padding: 12px 0;">+</button>
                    
                    <button type="button" class="btn btn-default calc-btn" data-val="1" style="background:#334155; color:#fff; border:none; font-weight:700; font-size:16px; padding: 12px 0;">1</button>
                    <button type="button" class="btn btn-default calc-btn" data-val="2" style="background:#334155; color:#fff; border:none; font-weight:700; font-size:16px; padding: 12px 0;">2</button>
                    <button type="button" class="btn btn-default calc-btn" data-val="3" style="background:#334155; color:#fff; border:none; font-weight:700; font-size:16px; padding: 12px 0;">3</button>
                    <button type="button" class="btn btn-default calc-btn" data-val="=" style="grid-row: span 2; background:#22c55e; color:#fff; border:none; font-weight:700; font-size:20px; height: 100%; border-radius: 4px;">=</button>
                    
                    <button type="button" class="btn btn-default calc-btn" data-val="0" style="grid-column: span 2; background:#334155; color:#fff; border:none; font-weight:700; font-size:16px; padding: 12px 0;">0</button>
                    <button type="button" class="btn btn-default calc-btn" data-val="." style="background:#334155; color:#fff; border:none; font-weight:700; font-size:16px; padding: 12px 0;">.</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Zero-Lag POS Variation Selector -->
<div class="modal fade" id="modal-variation-selector" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title font-bold" id="variation-modal-title" style="display:flex; align-items:center; gap:8px;">
                    <i class="fa fa-sitemap text-primary"></i> <span id="var-parent-name">ভ্যারিয়েশন নির্বাচন করুন</span>
                </h4>
            </div>
            <div class="modal-body" style="padding: 15px;">
                <div class="row mbot15">
                    <div class="col-xs-3 col-sm-2 text-center">
                        <div id="var-parent-img-container" style="width:70px; height:70px; border-radius:6px; border:1px solid #e2e8f0; overflow:hidden; display:flex; align-items:center; justify-content:center; background:#f1f5f9; margin:0 auto;">
                            <i class="fa fa-cube text-muted" style="font-size:28px;"></i>
                        </div>
                    </div>
                    <div class="col-xs-9 col-sm-10">
                        <h4 id="var-parent-heading" style="margin-top:0; font-weight:700; color:#1e293b;">Product Name</h4>
                        <p class="text-muted" style="margin-bottom:0; font-size:12px;">
                            Parent SKU: <code id="var-parent-sku">-</code> | 
                            মোট ভ্যারিয়েশন: <strong id="var-count-badge" class="text-primary">0</strong> টি
                        </p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="table-variation-picker" style="margin-bottom:0;">
                        <thead>
                            <tr style="background:#f8fafc;">
                                <th style="width:45%;">ভ্যারিয়েশন / সাইজ / কালার</th>
                                <th style="width:20%;">SKU</th>
                                <th style="width:15%; text-align:right;">মূল্য (BDT)</th>
                                <th style="width:10%; text-align:center;">স্টক</th>
                                <th style="width:10%; text-align:center;">একশন</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-variation-items">
                            <!-- Populated dynamically via JS from preloaded variations -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer" style="background:#f8fafc;">
                <button type="button" class="btn btn-default" data-dismiss="modal">বন্ধ করুন</button>
            </div>
        </div>
    </div>
</div>

    </div> <!-- .pos-container -->
</div> <!-- #wrapper -->

<?php init_tail(); ?>

<script>
$(function() {
    window.salesos_remove_decimals_on_zero = '<?= get_option('remove_decimals_on_zero') ?: '1' ?>';
    function pos_format_money(number, decimals) {
        if (decimals === undefined) decimals = 2;
        var num = parseFloat(number);
        if (isNaN(num)) return '0';
        if (window.salesos_remove_decimals_on_zero == '1' && num === Math.floor(num)) {
            return Math.floor(num).toString();
        }
        var formatted = num.toFixed(decimals);
        if (window.salesos_remove_decimals_on_zero == '1') {
            formatted = formatted.replace(/\.?0+$/, '');
        }
        return formatted;
    }

    var variationsByParent = <?= json_encode($variations_by_parent ?? []) ?>;
    var allVariationsList = <?= json_encode($all_variations ?? []) ?>;
    var allowOversell = <?= !empty($allow_oversell) ? 1 : 0 ?>;

    var cart = {}; // product_id => { id, name, sku, stock, rate, qty }
    var cartChannel = new BroadcastChannel('pos_cart_channel');

    // Core layout buttons
    var btnQuickCash = document.getElementById('btn-quick-cash');
    var btnAddHold = document.getElementById('btn-add-hold');
    var btnListHold = document.getElementById('btn-list-hold');
    var btnSaleList = document.getElementById('btn-sale-list');
    var btnPendingPayment = document.getElementById('btn-pending-payment');
    var btnClearCart = document.getElementById('btn-clear-cart');
    
    // Grid search & category filter tabs
    var posProductSearch = document.getElementById('pos-product-search');
    var categoryTabs = document.querySelectorAll('.pos-category-pill');
    var productContainers = document.querySelectorAll('.pos-product-card-container');

    // Subtotal calculations inputs
    var calcDiscountVal = document.getElementById('calc-discount-val');
    var calcDiscountType = document.getElementById('calc-discount-type');
    var calcShipping = document.getElementById('calc-shipping');
    
    var subtotalDisplay = document.getElementById('calc-subtotal');
    var totalPayableDisplay = document.getElementById('pos-total-payable');
    var emptyCartView = document.getElementById('pos-empty-cart-view');
    var cartTableBody = document.querySelector('#pos-cart-table tbody');

    // ── Cart UI Rendering ────────────────────────────────────────────────────
    
    function renderCart() {
        cartTableBody.innerHTML = '';
        var ids = Object.keys(cart);
        
        if (ids.length === 0) {
            emptyCartView.style.display = 'flex';
            subtotalDisplay.innerText = pos_format_money(0);
            subtotalDisplay.setAttribute('data-raw', '0');
            totalPayableDisplay.innerText = pos_format_money(0) + ' BDT';
            
            // Broadcast empty state
            cartChannel.postMessage({
                items: {},
                subtotal: 0,
                discount_val: 0,
                discount_type: 'fixed',
                shipping: 0,
                total: 0
            });
            return;
        }

        emptyCartView.style.display = 'none';
        var subtotal = 0;

        ids.forEach(function(id) {
            var item = cart[id];
            var lineTotal = item.qty * item.rate;
            subtotal += lineTotal;

            var row = document.createElement('tr');
            row.innerHTML = 
                '<td style="vertical-align:middle;">' +
                '   <strong class="display-block" style="font-size:12px;">' + item.name + '</strong>' +
                '   <small class="text-muted">SKU: ' + (item.sku || '-') + '</small>' +
                '</td>' +
                '<td class="text-center" style="vertical-align:middle;">' +
                '   <div class="input-group input-group-xs" style="width: 75px; margin: 0 auto;">' +
                '       <span class="input-group-btn">' +
                '           <button type="button" class="btn btn-default btn-xs minus-qty" data-id="' + id + '">-</button>' +
                '       </span>' +
                '       <input type="text" class="form-control text-center" value="' + item.qty + '" style="height:22px; padding:0;" readonly>' +
                '       <span class="input-group-btn">' +
                '           <button type="button" class="btn btn-default btn-xs plus-qty" data-id="' + id + '">+</button>' +
                '       </span>' +
                '   </div>' +
                '</td>' +
                '<td class="text-right" style="vertical-align:middle;">' +
                '   <strong>' + pos_format_money(lineTotal) + '</strong>' +
                '</td>' +
                '<td class="text-center" style="vertical-align:middle;">' +
                '   <button type="button" class="btn btn-danger btn-xs delete-item" data-id="' + id + '"><i class="fa fa-remove"></i></button>' +
                '</td>';

            cartTableBody.appendChild(row);
        });

        subtotalDisplay.innerText = pos_format_money(subtotal);
        subtotalDisplay.setAttribute('data-raw', subtotal);
        recalculateTotals();

        // Plus/Minus click actions
        document.querySelectorAll('.minus-qty').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = this.getAttribute('data-id');
                if (cart[id].qty > 1) {
                    cart[id].qty--;
                } else {
                    delete cart[id];
                }
                renderCart();
            });
        });

        document.querySelectorAll('.plus-qty').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = this.getAttribute('data-id');
                if (cart[id].qty + 1 > cart[id].stock) {
                    alert_float('warning', 'Stock limit reached: ' + cart[id].stock);
                    return;
                }
                cart[id].qty++;
                renderCart();
            });
        });

        document.querySelectorAll('.delete-item').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = this.getAttribute('data-id');
                delete cart[id];
                renderCart();
            });
        });
    }

    function recalculateTotals() {
        var subtotal = parseFloat(subtotalDisplay.getAttribute('data-raw') || subtotalDisplay.innerText) || 0;
        var discountVal = parseFloat(calcDiscountVal.value) || 0;
        var discountType = calcDiscountType.value;
        var shipping = parseFloat(calcShipping.value) || 0;

        var discountTotal = 0;
        if (discountType === 'percent') {
            discountTotal = (subtotal * discountVal) / 100;
        } else {
            discountTotal = discountVal;
        }

        var totalPayable = (subtotal - discountTotal) + shipping;
        if (totalPayable < 0) totalPayable = 0;

        totalPayableDisplay.innerText = pos_format_money(totalPayable) + ' BDT';

        // Online mode COD calculation sync
        var orderModeElem = document.getElementById('pos_order_mode');
        if (orderModeElem && orderModeElem.value === 'online') {
            var onlinePayOptElem = document.getElementById('pos_online_payment_option');
            var onlinePayOpt = onlinePayOptElem ? onlinePayOptElem.value : 'cod';
            var advInput = document.getElementById('pos_advance_amount');
            var codRemVal = document.getElementById('pos-cod-remaining-val');
            if (onlinePayOpt === 'cod') {
                if (codRemVal) codRemVal.innerText = pos_format_money(totalPayable);
            } else if (onlinePayOpt === 'advance_delivery') {
                var advAmt = parseFloat(advInput ? advInput.value : 0) || 0;
                var rem = Math.max(0, totalPayable - advAmt);
                if (codRemVal) codRemVal.innerText = pos_format_money(rem);
            } else if (onlinePayOpt === 'full_advance') {
                if (advInput) advInput.value = pos_format_money(totalPayable);
                if (codRemVal) codRemVal.innerText = pos_format_money(0);
            }
        }

        // Broadcast to customer display
        cartChannel.postMessage({
            items: cart,
            subtotal: subtotal,
            discount_val: discountVal,
            discount_type: discountType,
            shipping: shipping,
            total: totalPayable
        });
    }


    // Change calculation values keyups
    calcDiscountVal.addEventListener('input', recalculateTotals);
    calcDiscountType.addEventListener('change', recalculateTotals);
    calcShipping.addEventListener('input', recalculateTotals);

    // Audio feedback for barcode scanning & POS actions
    function playBeepSound(type) {
        try {
            var AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            var ctx = new AudioCtx();
            var osc = ctx.createOscillator();
            var gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            if (type === 'error') {
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(220, ctx.currentTime);
                gain.gain.setValueAtTime(0.15, ctx.currentTime);
                osc.start();
                osc.stop(ctx.currentTime + 0.22);
            } else {
                osc.type = 'sine';
                osc.frequency.setValueAtTime(1760, ctx.currentTime);
                gain.gain.setValueAtTime(0.1, ctx.currentTime);
                osc.start();
                osc.stop(ctx.currentTime + 0.08);
            }
        } catch(e) {}
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    // Zero-lag variation modal opener
    function openVariationModal(container) {
        var parentId = container.attr('data-id');
        var parentName = container.attr('data-name');
        var parentSku = container.attr('data-sku');
        var parentImg = container.find('img').attr('src') || '';

        $('#var-parent-name').text(parentName);
        $('#var-parent-heading').text(parentName);
        $('#var-parent-sku').text(parentSku || 'N/A');

        if (parentImg) {
            $('#var-parent-img-container').html('<img src="' + parentImg + '" style="width:100%;height:100%;object-fit:cover;">');
        } else {
            $('#var-parent-img-container').html('<i class="fa fa-cube text-muted" style="font-size:28px;"></i>');
        }

        var vars = variationsByParent[parentId] || [];
        $('#var-count-badge').text(vars.length);

        var tbody = $('#tbody-variation-items');
        tbody.empty();

        if (vars.length === 0) {
            tbody.html('<tr><td colspan="5" class="text-center text-muted" style="padding:20px;">এই পণ্যের কোন ভ্যারিয়েশন পাওয়া যায়নি।</td></tr>');
        } else {
            vars.forEach(function(v) {
                var stockVal = parseFloat(v.stock_on_hand) || 0;
                var isOut = stockVal <= 0;
                var stockBadge = isOut 
                    ? '<span class="label label-danger">Out of stock (0)</span>' 
                    : '<span class="label label-success">' + stockVal + ' pcs</span>';

                var btnHtml = '';
                if (isOut && !allowOversell) {
                    btnHtml = '<button class="btn btn-default btn-xs" disabled title="CRM-First: স্টক নেই"><i class="fa fa-ban"></i> Out</button>';
                } else {
                    btnHtml = '<button class="btn btn-primary btn-xs btn-add-variant-to-cart" ' +
                        'data-var-id="' + v.id + '" ' +
                        'data-parent-id="' + parentId + '" ' +
                        'data-name="' + escapeHtml(v.name) + '" ' +
                        'data-sku="' + escapeHtml(v.sku) + '" ' +
                        'data-rate="' + v.rate + '" ' +
                        'data-stock="' + stockVal + '" ' +
                        'style="background:#2563eb !important; border-color:#2563eb !important; color:#ffffff !important; font-weight:700 !important; padding:4px 10px; border-radius:4px; text-shadow:none;">' +
                        '<i class="fa fa-plus" style="color:#ffffff !important;"></i> <span style="color:#ffffff !important;">যোগ করুন</span></button>';
                }

                var tr = $('<tr></tr>');
                tr.append('<td><strong>' + escapeHtml(v.name) + '</strong></td>');
                tr.append('<td><code>' + escapeHtml(v.sku) + '</code></td>');
                tr.append('<td style="text-align:right;"><strong>' + pos_format_money(v.rate) + ' BDT</strong></td>');
                tr.append('<td style="text-align:center;">' + stockBadge + '</td>');
                tr.append('<td style="text-align:center;">' + btnHtml + '</td>');
                tbody.append(tr);
            });
        }

        $('#modal-variation-selector').modal('show');
    }

    // Add variant to cart handler
    $(document).on('click', '.btn-add-variant-to-cart', function(e) {
        e.preventDefault();
        var btn = $(this);
        var id = btn.attr('data-var-id');
        var name = btn.attr('data-name');
        var sku = btn.attr('data-sku');
        var rate = parseFloat(btn.attr('data-rate')) || 0;
        var stock = parseFloat(btn.attr('data-stock')) || 0;

        if (cart[id]) {
            if (!allowOversell && cart[id].qty + 1 > stock) {
                playBeepSound('error');
                alert_float('warning', 'স্টক সীমা শেষ: ' + stock);
                return;
            }
            cart[id].qty++;
        } else {
            if (!allowOversell && stock <= 0) {
                playBeepSound('error');
                alert_float('warning', 'পণ্যটি বর্তমানে স্টকে নেই (0.00)।');
                return;
            }
            cart[id] = { id: id, name: name, sku: sku, stock: stock, rate: rate, qty: 1 };
        }
        playBeepSound('success');
        renderCart();
        $('#modal-variation-selector').modal('hide');
        alert_float('success', name + ' কার্টে যোগ করা হয়েছে।');
    });

    // Add to cart click with event delegation
    $(document).on('click', '.pos-product-card-container', function(e) {
        var container = $(this);
        var pType = container.attr('data-product-type');

        if (pType === 'variable') {
            openVariationModal(container);
            return;
        }

        var card = container.find('.pos-product-card');
        var stock = parseFloat(container.attr('data-stock')) || 0;
        if (!allowOversell && (card.hasClass('out-of-stock') || stock <= 0)) {
            playBeepSound('error');
            alert_float('warning', 'পণ্যটি বর্তমানে স্টকে নেই (Stock 0.00)। CRM-First নিয়মে স্টক ছাড়া বিক্রি সম্ভব নয়।');
            return;
        }

        var id = container.attr('data-id');
        var name = container.attr('data-name');
        var sku = container.attr('data-sku');
        var rate = parseFloat(container.attr('data-rate')) || 0;

        if (cart[id]) {
            if (!allowOversell && cart[id].qty + 1 > stock) {
                playBeepSound('error');
                alert_float('warning', 'Stock limit reached: ' + stock);
                return;
            }
            cart[id].qty++;
        } else {
            cart[id] = { id: id, name: name, sku: sku, stock: stock, rate: rate, qty: 1 };
        }
        playBeepSound('success');
        renderCart();
        focusBarcodeScanner();
    });

    // ── Multi-Level Barcode / IMEI Scanner (Enter Key Listener) ──────────────
    $('#pos-product-search').on('keydown', function(e) {
        if (e.which === 13 || e.keyCode === 13) {
            e.preventDefault();
            var code = $.trim($(this).val());
            if (!code) return;
            handleBarcodeScan(code);
        }
    });

    function handleBarcodeScan(code) {
        var codeLower = code.toLowerCase();

        // Level 2: Match exact SKU in preloaded Child Variations
        var matchedVar = allVariationsList.find(function(v) {
            return v.sku && v.sku.toLowerCase() === codeLower;
        });

        if (matchedVar) {
            var varStock = parseFloat(matchedVar.stock_on_hand) || 0;
            if (!allowOversell && varStock <= 0) {
                playBeepSound('error');
                alert_float('warning', 'ভ্যারিয়েশন ' + matchedVar.sku + ' বর্তমানে স্টকে নেই (0.00)।');
                return;
            }
            var vid = matchedVar.id;
            if (cart[vid]) {
                if (!allowOversell && cart[vid].qty + 1 > varStock) {
                    playBeepSound('error');
                    alert_float('warning', 'স্টক সীমা শেষ: ' + varStock);
                    return;
                }
                cart[vid].qty++;
            } else {
                cart[vid] = {
                    id: vid,
                    name: matchedVar.name,
                    sku: matchedVar.sku,
                    stock: varStock,
                    rate: parseFloat(matchedVar.rate) || 0,
                    qty: 1
                };
            }
            renderCart();
            playBeepSound('success');
            $('#pos-product-search').val('');
            filterProducts();
            alert_float('success', 'বারকোড স্ক্যান সফল: ' + matchedVar.name);
            return;
        }

        // Level 3: Match exact SKU in Parent / Simple Product cards
        var matchedCard = null;
        productContainers.forEach(function(c) {
            var pSku = (c.getAttribute('data-sku') || '').toLowerCase();
            if (pSku && pSku === codeLower) {
                matchedCard = $(c);
            }
        });

        if (matchedCard) {
            var pType = matchedCard.attr('data-product-type');
            if (pType === 'variable') {
                playBeepSound('success');
                $('#pos-product-search').val('');
                filterProducts();
                openVariationModal(matchedCard);
                return;
            } else {
                var pStock = parseFloat(matchedCard.attr('data-stock')) || 0;
                if (!allowOversell && pStock <= 0) {
                    playBeepSound('error');
                    alert_float('warning', 'পণ্যটি বর্তমানে স্টকে নেই (0.00)।');
                    return;
                }
                matchedCard.trigger('click');
                $('#pos-product-search').val('');
                filterProducts();
                return;
            }
        }

        // Level 1: Server Lookup (IMEI / Serial Number, Barcode)
        $.post('<?= admin_url('pos/scan_barcode') ?>', { code: code })
        .done(function(raw) {
            try {
                var res = typeof raw === 'object' ? raw : JSON.parse(raw);
                if (res.success && res.product) {
                    var p = res.product;
                    if (res.match_type === 'variable_parent') {
                        var card = $('.pos-product-card-container[data-id="' + p.id + '"]');
                        if (card.length) {
                            playBeepSound('success');
                            $('#pos-product-search').val('');
                            filterProducts();
                            openVariationModal(card);
                            return;
                        }
                    }

                    if (!allowOversell && p.stock <= 0) {
                        playBeepSound('error');
                        alert_float('warning', p.name + ' বর্তমানে স্টকে নেই (0.00)।');
                        return;
                    }

                    if (cart[p.id]) {
                        if (!allowOversell && cart[p.id].qty + 1 > p.stock) {
                            playBeepSound('error');
                            alert_float('warning', 'স্টক সীমা শেষ: ' + p.stock);
                            return;
                        }
                        cart[p.id].qty++;
                    } else {
                        cart[p.id] = {
                            id: p.id,
                            name: p.name,
                            sku: p.sku,
                            stock: p.stock,
                            rate: p.rate,
                            qty: 1,
                            serial_number: p.serial_number || null
                        };
                    }
                    renderCart();
                    playBeepSound('success');
                    $('#pos-product-search').val('');
                    filterProducts();
                    alert_float('success', (res.match_type === 'serial' ? 'IMEI/Serial ' : 'Barcode ') + 'স্ক্যান সফল: ' + p.name);
                } else {
                    playBeepSound('error');
                    alert_float('warning', res.error || 'কোন পণ্য পাওয়া যায়নি: ' + code);
                }
            } catch (e) {
                playBeepSound('error');
                alert_float('danger', 'স্ক্যানিং ত্রুটি: ' + e.message);
            }
        })
        .fail(function() {
            playBeepSound('error');
            alert_float('danger', 'সার্ভার সংযোগ ত্রুটি।');
        });
    }

    // ── Grid Filtering (Search & Categories) ─────────────────────────────────
    
    function filterProducts() {
        var searchVal = posProductSearch.value.toLowerCase().trim();
        var activeTab = document.querySelector('.pos-category-pill.active');
        var catId = activeTab ? activeTab.getAttribute('data-category-id') : 'all';

        var visibleCount = 0;

        productContainers.forEach(function(container) {
            var name = container.getAttribute('data-name').toLowerCase();
            var sku = container.getAttribute('data-sku').toLowerCase();
            var pCatId = container.getAttribute('data-category-id');

            var matchesSearch = (searchVal === '' || name.indexOf(searchVal) !== -1 || sku.indexOf(searchVal) !== -1);
            var matchesCategory = (catId === 'all' || pCatId === catId);

            if (matchesSearch && matchesCategory) {
                container.style.display = 'block';
                visibleCount++;
            } else {
                container.style.display = 'none';
            }
        });

        var countElem = document.getElementById('pos-grid-count');
        if (countElem) {
            countElem.innerHTML = '<i class="fa fa-cubes"></i> Total Products: ' + visibleCount;
        }
    }

    posProductSearch.addEventListener('input', filterProducts);

    categoryTabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            categoryTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            filterProducts();
        });
    });

    // ── Checkout Checkout Actions ────────────────────────────────────────────
    
    function getCheckoutPayload() {
        var ids = Object.keys(cart);
        var itemsPayload = [];
        ids.forEach(function(id) {
            itemsPayload.push({
                product_id: parseInt(id),
                qty: cart[id].qty,
                rate: cart[id].rate
            });
        });

        var isOnline = (document.getElementById('pos_order_mode').value === 'online');
        var payload = {
            order_mode: isOnline ? 'online' : 'offline',
            client_id: document.getElementById('client_id').value,
            lead_id: document.getElementById('lead_id') ? document.getElementById('lead_id').value : '',
            discount_type: calcDiscountType.value,
            discount_value: parseFloat(calcDiscountVal.value) || 0,
            shipping: parseFloat(calcShipping.value) || 0,
            items: itemsPayload
        };

        if (isOnline) {
            payload.recipient_name        = document.getElementById('pos_recipient_name').value.trim();
            payload.recipient_phone       = document.getElementById('pos_recipient_phone').value.trim();
            payload.division_id           = document.getElementById('pos_division_id').value;
            payload.district_id           = document.getElementById('pos_district_id').value;
            payload.upazila_id            = document.getElementById('pos_upazila_id').value;
            payload.union_id              = document.getElementById('pos_union_id').value;
            payload.delivery_address      = document.getElementById('pos_delivery_address').value.trim();
            payload.online_payment_option = document.getElementById('pos_online_payment_option').value;
            payload.advance_amount        = parseFloat(document.getElementById('pos_advance_amount').value) || 0;
            payload.advance_payment_mode  = document.getElementById('pos_advance_payment_mode').value;
            payload.payment_ref           = document.getElementById('pos_advance_ref').value.trim();
        }

        return payload;
    }

    function runCheckout(payload) {
        if (payload.order_mode === 'online') {
            var cId = payload.client_id;
            var lId = payload.lead_id;
            var walkinId = '<?= get_option("pos_default_walkin_client_id") ?>';
            if (!lId && (!cId || cId == walkinId)) {
                alert_float('warning', 'অনলাইন ডেলিভারি অর্ডারের ক্ষেত্রে Walk-in Customer গ্রহণযোগ্য নয়! দয়া করে কাস্টমার বা লিড সিলেক্ট করুন অথবা Quick Add Customer ব্যবহার করুন।');
                $('#pos_customer_search').focus();
                return;
            }
            if (!payload.recipient_name) {
                alert_float('warning', 'দয়া করে প্রাপকের নাম প্রদান করুন (Please enter recipient name).');
                document.getElementById('pos_recipient_name').focus();
                return;
            }
            if (!payload.recipient_phone || payload.recipient_phone.length < 11) {
                alert_float('warning', 'দয়া করে প্রাপকের সঠিক ১১ ডিজিট মোবাইল নম্বর দিন (Valid 11-digit phone number is required).');
                document.getElementById('pos_recipient_phone').focus();
                return;
            }
            if (!payload.division_id) {
                alert_float('warning', 'বিভাগ নির্বাচন করুন (Please select Division).');
                document.getElementById('pos_division_id').focus();
                return;
            }
            if (!payload.district_id) {
                alert_float('warning', 'জেলা নির্বাচন করুন (Please select District).');
                document.getElementById('pos_district_id').focus();
                return;
            }
            if (!payload.delivery_address) {
                alert_float('warning', 'বিস্তারিত ডেলিভারি ঠিকানা প্রদান করুন (Please enter detailed delivery address).');
                document.getElementById('pos_delivery_address').focus();
                return;
            }
        }

        $.post('<?= admin_url('pos/checkout') ?>', {
            order_mode: payload.order_mode,
            client_id: payload.client_id,
            lead_id: payload.lead_id || '',
            recipient_name: payload.recipient_name || '',
            recipient_phone: payload.recipient_phone || '',
            division_id: payload.division_id || '',
            district_id: payload.district_id || '',
            upazila_id: payload.upazila_id || '',
            union_id: payload.union_id || '',
            delivery_address: payload.delivery_address || '',
            discount_type: payload.discount_type,
            discount_value: payload.discount_value,
            shipping: payload.shipping,
            payment_method: payload.payment_method,
            payment_ref: payload.payment_ref || '',
            online_payment_option: payload.online_payment_option || 'cod',
            advance_amount: payload.advance_amount || 0,
            advance_payment_mode: payload.advance_payment_mode || '',
            items: JSON.stringify(payload.items)
        }, function(response) {
            var data = JSON.parse(response);
            if (data.success) {
                alert_float('success', payload.order_mode === 'online' ? 'Online Delivery Order Placed Successfully!' : 'POS Checkout completed successfully!');
                
                // Clear cart & variables
                cart = {};
                renderCart();
                calcDiscountVal.value = 0;
                calcShipping.value = 0;
                if (typeof resetToWalkin === 'function') {
                    resetToWalkin();
                }
                resetOnlineDeliveryForm();

                // Automatically open invoice details for printing
                window.open('<?= admin_url('invoices/list_invoices/') ?>' + data.pos_sale_id, '_blank');
                
                $('#payment-details-modal').modal('hide');
            } else {
                alert_float('danger', 'Checkout failed: ' + data.error);
            }
        }).fail(function() {
            alert_float('danger', 'Network / server communication error occurred.');
        });
    }

    // ── BD Geocoding Reusable Cascading Dropdown Loaders ────────────────────
    function loadBdDistricts(divisionId, targetSelect, selectedDistrictId, callback) {
        var $dist = $(targetSelect);
        $dist.html('<option value="">-- লোড হচ্ছে... --</option>').prop('disabled', true);
        if (!divisionId) {
            $dist.html('<option value="">-- আগে বিভাগ বাছুন --</option>').prop('disabled', true);
            if (callback) callback();
            return;
        }
        $.getJSON('<?= admin_url('pos/get_bd_districts') ?>', { division_id: divisionId }, function(res) {
            if (res.success && res.data) {
                var opts = '<option value="">-- জেলা নির্বাচন করুন --</option>';
                res.data.forEach(function(d) {
                    var isSel = (selectedDistrictId && (selectedDistrictId == d.id || (typeof selectedDistrictId === 'string' && selectedDistrictId.toLowerCase() === d.name.toLowerCase()))) ? ' selected' : '';
                    opts += '<option value="' + d.id + '" data-name="' + escapeHtml(d.name) + '"' + isSel + '>' + escapeHtml(d.name) + ' (' + escapeHtml(d.bn_name) + ')</option>';
                });
                $dist.html(opts).prop('disabled', false);
            } else {
                $dist.html('<option value="">-- কোন জেলা পাওয়া যায়নি --</option>');
            }
            if (callback) callback();
        });
    }

    function loadBdUpazilas(districtId, targetSelect, selectedUpazilaId, callback) {
        var $upz = $(targetSelect);
        $upz.html('<option value="">-- লোড হচ্ছে... --</option>').prop('disabled', true);
        if (!districtId) {
            $upz.html('<option value="">-- আগে জেলা বাছুন --</option>').prop('disabled', true);
            if (callback) callback();
            return;
        }
        $.getJSON('<?= admin_url('pos/get_bd_upazilas') ?>', { district_id: districtId }, function(res) {
            if (res.success && res.data) {
                var opts = '<option value="">-- উপজেলা / থানা বাছুন --</option>';
                res.data.forEach(function(u) {
                    var isSel = (selectedUpazilaId && (selectedUpazilaId == u.id || (typeof selectedUpazilaId === 'string' && selectedUpazilaId.toLowerCase() === u.name.toLowerCase()))) ? ' selected' : '';
                    opts += '<option value="' + u.id + '" data-name="' + escapeHtml(u.name) + '"' + isSel + '>' + escapeHtml(u.name) + ' (' + escapeHtml(u.bn_name) + ')</option>';
                });
                $upz.html(opts).prop('disabled', false);
            } else {
                $upz.html('<option value="">-- কোন উপজেলা পাওয়া যায়নি --</option>');
            }
            if (callback) callback();
        });
    }

    function loadBdUnions(upazilaId, targetSelect, selectedUnionId, callback) {
        var $un = $(targetSelect);
        $un.html('<option value="">-- লোড হচ্ছে... --</option>').prop('disabled', true);
        if (!upazilaId) {
            $un.html('<option value="">-- ইউনিয়ন বাছুন --</option>').prop('disabled', true);
            if (callback) callback();
            return;
        }
        $.getJSON('<?= admin_url('pos/get_bd_unions') ?>', { upazila_id: upazilaId }, function(res) {
            if (res.success && res.data) {
                var opts = '<option value="">-- ইউনিয়ন বাছুন (ঐচ্ছিক) --</option>';
                res.data.forEach(function(un) {
                    var isSel = (selectedUnionId && (selectedUnionId == un.id || (typeof selectedUnionId === 'string' && selectedUnionId.toLowerCase() === un.name.toLowerCase()))) ? ' selected' : '';
                    opts += '<option value="' + un.id + '"' + isSel + '>' + escapeHtml(un.name) + ' (' + escapeHtml(un.bn_name) + ')</option>';
                });
                $un.html(opts).prop('disabled', false);
            } else {
                $un.html('<option value="">-- কোন ইউনিয়ন পাওয়া যায়নি --</option>');
            }
            if (callback) callback();
        });
    }

    // ── Online Delivery Order Mode & Modal Helpers ───────────────────────────
    window.setPosOrderMode = function(mode) {
        document.getElementById('pos_order_mode').value = mode;
        var btnOffline = document.getElementById('btn-mode-offline');
        var btnOnline = document.getElementById('btn-mode-online');
        var strip = document.getElementById('pos-online-status-strip');
        var btnLabel = document.getElementById('btn-quick-cash-text');
        var btnIcon = document.getElementById('btn-quick-cash-icon');
        var btnKbd = document.getElementById('btn-quick-cash-kbd');

        if (mode === 'online') {
            btnOnline.classList.add('active');
            btnOffline.classList.remove('active');
            if (strip) strip.style.display = 'flex';
            if (btnLabel) btnLabel.innerText = 'Confirm Delivery';
            if (btnIcon) { btnIcon.className = 'fa fa-truck'; }
            if (btnKbd) btnKbd.innerText = '(F2)';

            // Default to Inside Dhaka 70 if shipping is 0
            var shipVal = parseFloat(calcShipping.value) || 0;
            if (shipVal === 0) {
                calcShipping.value = 70;
            }
        } else {
            btnOffline.classList.add('active');
            btnOnline.classList.remove('active');
            if (strip) strip.style.display = 'none';
            if (btnLabel) btnLabel.innerText = 'Quick Cash';
            if (btnIcon) { btnIcon.className = 'fa fa-money'; }
            if (btnKbd) btnKbd.innerText = '(F2)';
        }
        recalculateTotals();
    };

    window.openDeliveryConfirmModal = function() {
        if (Object.keys(cart).length === 0) {
            alert_float('warning', 'কার্ট খালি! অনুগ্রহ করে প্রথমে পণ্য যোগ করুন (Cart is empty).');
            return;
        }

        var cId = $('#client_id').val();
        var lId = $('#lead_id').val();
        var isWalkin = (!lId && (!cId || cId == walkinId || (currentSelectedEntity && currentSelectedEntity.type === 'walkin')));
        if (isWalkin) {
            alert_float('warning', 'অনলাইন ডেলিভারি অর্ডারের ক্ষেত্রে Walk-in Customer গ্রহণযোগ্য নয়! দয়া করে কাস্টমার বা লিড সিলেক্ট করুন অথবা Quick Add Customer ব্যবহার করুন।');
            $('#pos_customer_search').focus();
            return;
        }

        $('#delivery-modal-alert').hide().text('');

        // 1. Customer Context Banner
        var cLabel = 'Walk-in Customer';
        var cBadge = 'Direct Sale';
        var cBadgeCls = 'label-default';
        if (currentSelectedEntity && currentSelectedEntity.type === 'client') {
            cLabel = currentSelectedEntity.name + (currentSelectedEntity.phone ? ' (' + currentSelectedEntity.phone + ')' : '');
            cBadge = 'Customer #' + currentSelectedEntity.id;
            cBadgeCls = 'label-success';
        } else if (currentSelectedEntity && currentSelectedEntity.type === 'lead') {
            cLabel = currentSelectedEntity.name + (currentSelectedEntity.phone ? ' (' + currentSelectedEntity.phone + ')' : '');
            cBadge = 'Lead #' + currentSelectedEntity.id;
            cBadgeCls = 'label-info';
        }
        $('#modal-customer-label').text(cLabel);
        $('#modal-customer-badge').attr('class', 'label ' + cBadgeCls).text(cBadge);

        // 2. Pre-fill Recipient Name & Phone from stage or selected entity (excluding Walk-in)
        var isEntityWalkin = (!currentSelectedEntity || currentSelectedEntity.type === 'walkin');
        var nameVal = $('#pos_recipient_name').val() || (!isEntityWalkin ? currentSelectedEntity.name : '');
        var phoneVal = $('#pos_recipient_phone').val() || (!isEntityWalkin ? currentSelectedEntity.phone : '');
        var addrVal = $('#pos_delivery_address').val() || (!isEntityWalkin ? currentSelectedEntity.address : '');

        $('#modal_recipient_name').val(nameVal);
        $('#modal_recipient_phone').val(phoneVal);
        $('#modal_delivery_address').val(addrVal);

        // 3. Shipping Charge Sync
        var currentShip = parseFloat(calcShipping.value);
        if (isNaN(currentShip) || currentShip <= 0) {
            currentShip = 70;
        }
        setModalShipping(currentShip);

        // 4. Payment Option Sync
        var currentPayOpt = $('#pos_online_payment_option').val() || 'cod';
        setModalPaymentOption(currentPayOpt);
        if ($('#pos_advance_amount').val()) {
            $('#modal_advance_amount').val($('#pos_advance_amount').val());
        }
        if ($('#pos_advance_payment_mode').val()) {
            $('#modal_advance_payment_mode').val($('#pos_advance_payment_mode').val());
        }
        if ($('#pos_advance_ref').val()) {
            $('#modal_advance_ref').val($('#pos_advance_ref').val());
        }

        // 5. BD Geocode Matching & Cascading Dropdowns
        var divId = $('#pos_division_id').val() || (currentSelectedEntity ? currentSelectedEntity.division_id : '');
        var distId = $('#pos_district_id').val() || (currentSelectedEntity ? currentSelectedEntity.district_id : '');
        var upzId = $('#pos_upazila_id').val() || (currentSelectedEntity ? currentSelectedEntity.upazila_id : '');
        var unId = $('#pos_union_id').val() || (currentSelectedEntity ? currentSelectedEntity.union_id : '');

        // Auto-match division if not yet set but customer has state/division
        if (!divId && currentSelectedEntity && currentSelectedEntity.state) {
            var stateStr = currentSelectedEntity.state.trim().toLowerCase();
            $('#modal_division_id option').each(function() {
                var optName = ($(this).data('name') || $(this).text()).toLowerCase();
                if (optName.indexOf(stateStr) !== -1 || stateStr.indexOf(optName) !== -1) {
                    divId = $(this).val();
                    return false;
                }
            });
        }

        $('#modal_division_id').val(divId || '');
        if (divId) {
            var targetDist = distId || (currentSelectedEntity ? (currentSelectedEntity.district_id || currentSelectedEntity.city) : '');
            loadBdDistricts(divId, '#modal_district_id', targetDist, function() {
                var selectedDist = $('#modal_district_id').val() || distId;
                if (selectedDist) {
                    var targetUpz = upzId || (currentSelectedEntity ? currentSelectedEntity.upazila_id : '');
                    loadBdUpazilas(selectedDist, '#modal_upazila_id', targetUpz, function() {
                        var selectedUpz = $('#modal_upazila_id').val() || upzId;
                        if (selectedUpz) {
                            var targetUn = unId || (currentSelectedEntity ? currentSelectedEntity.union_id : '');
                            loadBdUnions(selectedUpz, '#modal_union_id', targetUn);
                        }
                    });
                }
            });
        } else {
            $('#modal_district_id').html('<option value="">-- আগে বিভাগ বাছুন --</option>').prop('disabled', true);
            $('#modal_upazila_id').html('<option value="">-- আগে জেলা বাছুন --</option>').prop('disabled', true);
            $('#modal_union_id').html('<option value="">-- ইউনিয়ন বাছুন --</option>').prop('disabled', true);
        }

        recalculateModalTotals();
        $('#online-delivery-confirm-modal').modal('show');
    };

    window.setModalShipping = function(amount, type) {
        $('#modal_shipping_input').val(amount);
        $('.modal-ship-preset').removeClass('active');
        if (amount === 70) $('#modal-preset-70').addClass('active');
        else if (amount === 130) $('#modal-preset-130').addClass('active');
        else if (amount === 0) $('#modal-preset-0').addClass('active');

        if ($('#modal_payment_option').val() === 'advance_delivery') {
            $('#modal_advance_amount').val(amount);
        }
        recalculateModalTotals();
    };

    $('#modal_shipping_input').on('input', function() {
        var val = parseFloat($(this).val()) || 0;
        $('.modal-ship-preset').removeClass('active');
        if (val === 70) $('#modal-preset-70').addClass('active');
        else if (val === 130) $('#modal-preset-130').addClass('active');
        else if (val === 0) $('#modal-preset-0').addClass('active');

        if ($('#modal_payment_option').val() === 'advance_delivery') {
            $('#modal_advance_amount').val(val);
        }
        recalculateModalTotals();
    });

    window.setModalPaymentOption = function(opt) {
        $('#modal_payment_option').val(opt);
        $('.modal-pay-btn').removeClass('active');
        if (opt === 'cod') {
            $('#modal-btn-pay-cod').addClass('active');
            $('#modal-advance-details').slideUp(150);
        } else if (opt === 'advance_delivery') {
            $('#modal-btn-pay-adv').addClass('active');
            $('#modal-advance-details').slideDown(150);
            var shipVal = parseFloat($('#modal_shipping_input').val()) || 70;
            $('#modal_advance_amount').val(shipVal);
        } else if (opt === 'full_advance') {
            $('#modal-btn-pay-full').addClass('active');
            $('#modal-advance-details').slideDown(150);
        }
        recalculateModalTotals();
    };

    $('#modal_advance_amount').on('input', function() {
        recalculateModalTotals();
    });

    function recalculateModalTotals() {
        var subtotal = parseFloat(subtotalDisplay.getAttribute('data-raw') || subtotalDisplay.innerText) || 0;
        var discountVal = parseFloat(calcDiscountVal.value) || 0;
        var discountType = calcDiscountType.value;
        var discountTotal = (discountType === 'percent') ? ((subtotal * discountVal) / 100) : discountVal;
        var netSubtotal = Math.max(0, subtotal - discountTotal);
        var shipping = parseFloat($('#modal_shipping_input').val()) || 0;
        var total = netSubtotal + shipping;

        var opt = $('#modal_payment_option').val() || 'cod';
        var advance = 0;
        if (opt === 'cod') {
            advance = 0;
        } else if (opt === 'advance_delivery') {
            advance = parseFloat($('#modal_advance_amount').val()) || 0;
        } else if (opt === 'full_advance') {
            advance = total;
            $('#modal_advance_amount').val(pos_format_money(total));
        }

        var collectableCod = Math.max(0, total - advance);

        $('#modal-calc-subtotal').text('৳' + pos_format_money(netSubtotal));
        $('#modal-calc-shipping').text('৳' + pos_format_money(shipping));
        $('#modal-calc-total').text('৳' + pos_format_money(total));
        $('#modal-calc-cod').text('৳' + pos_format_money(collectableCod));
    }

    // Modal BD Geocode Change Listeners
    $('#modal_division_id').on('change', function() {
        var divId = $(this).val();
        $('#modal_upazila_id').html('<option value="">-- আগে জেলা বাছুন --</option>').prop('disabled', true);
        $('#modal_union_id').html('<option value="">-- ইউনিয়ন বাছুন --</option>').prop('disabled', true);

        if (divId == 6) {
            setModalShipping(70, 'inside_dhaka');
        } else if (divId) {
            setModalShipping(130, 'outside_dhaka');
        }

        loadBdDistricts(divId, '#modal_district_id', '');
    });

    $('#modal_district_id').on('change', function() {
        var distId = $(this).val();
        var selectedName = $(this).find('option:selected').data('name') || '';
        $('#modal_union_id').html('<option value="">-- ইউনিয়ন বাছুন --</option>').prop('disabled', true);

        if (selectedName.toLowerCase() === 'dhaka') {
            setModalShipping(70, 'inside_dhaka');
        } else if (distId) {
            setModalShipping(130, 'outside_dhaka');
        }

        loadBdUpazilas(distId, '#modal_upazila_id', '');
    });

    $('#modal_upazila_id').on('change', function() {
        var upzId = $(this).val();
        loadBdUnions(upzId, '#modal_union_id', '');
    });

    // Modal Submit & Confirm Action
    $('#btn-submit-delivery-modal').on('click', function() {
        var alertBox = $('#delivery-modal-alert');
        alertBox.hide().text('');

        var cId = $('#client_id').val();
        var lId = $('#lead_id').val();
        var isWalkin = (!lId && (!cId || cId == walkinId || (currentSelectedEntity && currentSelectedEntity.type === 'walkin')));
        if (isWalkin) {
            alertBox.text('অনলাইন ডেলিভারি অর্ডারের ক্ষেত্রে Walk-in Customer গ্রহণযোগ্য নয়! দয়া করে কাস্টমার বা লিড সিলেক্ট করুন অথবা Quick Add Customer ব্যবহার করুন।').show();
            return;
        }

        var name = $('#modal_recipient_name').val().trim();
        var phone = $('#modal_recipient_phone').val().trim();
        var divId = $('#modal_division_id').val();
        var distId = $('#modal_district_id').val();
        var upzId = $('#modal_upazila_id').val();
        var unId = $('#modal_union_id').val();
        var address = $('#modal_delivery_address').val().trim();
        var shipping = parseFloat($('#modal_shipping_input').val()) || 0;
        var payOpt = $('#modal_payment_option').val() || 'cod';
        var advAmt = parseFloat($('#modal_advance_amount').val()) || 0;
        var advMode = $('#modal_advance_payment_mode').val() || 'bkash';
        var advRef = $('#modal_advance_ref').val().trim();

        if (!name) {
            alertBox.text('দয়া করে প্রাপকের নাম প্রদান করুন (Please enter recipient name).').show();
            $('#modal_recipient_name').focus();
            return;
        }

        var cleanPhone = phone.replace(/[^0-9]/g, '');
        if (!cleanPhone || cleanPhone.length < 11) {
            alertBox.text('দয়া করে প্রাপকের সঠিক ১১ ডিজিট মোবাইল নম্বর দিন (Valid 11-digit mobile number required).').show();
            $('#modal_recipient_phone').focus();
            return;
        }

        if (!divId) {
            alertBox.text('বিভাগ নির্বাচন করুন (Please select Division).').show();
            $('#modal_division_id').focus();
            return;
        }

        if (!distId) {
            alertBox.text('জেলা নির্বাচন করুন (Please select District).').show();
            $('#modal_district_id').focus();
            return;
        }

        if (!upzId) {
            alertBox.text('উপজেলা / থানা নির্বাচন করুন (Please select Upazila/Thana).').show();
            $('#modal_upazila_id').focus();
            return;
        }

        if (!address) {
            alertBox.text('বিস্তারিত ডেলিভারি ঠিকানা প্রদান করুন (Please enter detailed delivery address).').show();
            $('#modal_delivery_address').focus();
            return;
        }

        if (payOpt !== 'cod' && advAmt <= 0) {
            alertBox.text('অগ্রিম পেমেন্টের পরিমাণ দিন (Please enter valid advance amount).').show();
            $('#modal_advance_amount').focus();
            return;
        }

        // Transfer values to POS hidden inputs
        $('#pos_recipient_name').val(name);
        $('#pos_recipient_phone').val(cleanPhone);
        $('#pos_division_id').val(divId);
        $('#pos_district_id').val(distId);
        $('#pos_upazila_id').val(upzId);
        $('#pos_union_id').val(unId);
        $('#pos_delivery_address').val(address);
        calcShipping.value = shipping;
        $('#pos_online_payment_option').val(payOpt);
        $('#pos_advance_amount').val(advAmt);
        $('#pos_advance_payment_mode').val(advMode);
        $('#pos_advance_ref').val(advRef);

        if (currentSelectedEntity) {
            currentSelectedEntity.division_id = divId;
            currentSelectedEntity.district_id = distId;
            currentSelectedEntity.upazila_id = upzId;
            currentSelectedEntity.union_id = unId;
            currentSelectedEntity.address = address;
        }

        recalculateTotals();

        // Close modal
        $('#online-delivery-confirm-modal').modal('hide');

        // Build checkout payload and submit
        var payload = getCheckoutPayload();
        if (payOpt === 'cod') {
            payload.payment_method = 'cod';
        } else {
            payload.payment_method = advMode;
        }
        payload.payment_ref = advRef;

        runCheckout(payload);
    });

    window.resetOnlineDeliveryForm = function() {
        $('#pos_recipient_name').val('');
        $('#pos_recipient_phone').val('');
        $('#pos_division_id').val('');
        $('#pos_district_id').val('');
        $('#pos_upazila_id').val('');
        $('#pos_union_id').val('');
        $('#pos_delivery_address').val('');
        $('#pos_online_payment_option').val('cod');
        $('#pos_advance_amount').val('0');
        $('#pos_advance_payment_mode').val('bkash');
        $('#pos_advance_ref').val('');
    };

    // Quick cash / Confirm Delivery checkout trigger
    btnQuickCash.addEventListener('click', function() {
        if (Object.keys(cart).length === 0) {
            alert_float('warning', 'কার্ট খালি! অনুগ্রহ করে প্রথমে পণ্য যোগ করুন (Cart is empty).');
            return;
        }

        var mode = document.getElementById('pos_order_mode').value;
        if (mode === 'online') {
            var cId = $('#client_id').val();
            var lId = $('#lead_id').val();
            var isWalkin = (!lId && (!cId || cId == walkinId || (currentSelectedEntity && currentSelectedEntity.type === 'walkin')));
            if (isWalkin) {
                alert_float('warning', 'অনলাইন ডেলিভারি অর্ডারের ক্ষেত্রে Walk-in Customer গ্রহণযোগ্য নয়! দয়া করে কাস্টমার বা লিড সিলেক্ট করুন অথবা Quick Add Customer ব্যবহার করুন।');
                $('#pos_customer_search').focus();
                return;
            }

            // Online mode: open delivery popup modal to verify & complete gaps
            openDeliveryConfirmModal();
            return;
        }

        // Offline in-store counter sale: direct quick cash
        var payload = getCheckoutPayload();
        payload.payment_method = 'cash';
        payload.payment_ref = '';
        runCheckout(payload);
    });

    // More payment complete checkout
    document.getElementById('btn-process-checkout-more').addEventListener('click', function() {
        var payload = getCheckoutPayload();
        payload.payment_method = document.getElementById('payment_method').value;
        payload.payment_ref = document.getElementById('payment_ref').value;
        runCheckout(payload);
    });

    // ── Quick Add Customer Cascading BD Geocoding ────────────────────────────
    $('#m_division_id').on('change', function() {
        var divId = $(this).val();
        $('#m_upazila_id').html('<option value="">-- আগে জেলা বাছুন --</option>').prop('disabled', true);
        $('#m_union_id').html('<option value="">-- ইউনিয়ন বাছুন --</option>').prop('disabled', true);
        loadBdDistricts(divId, '#m_district_id', '');
    });

    $('#m_district_id').on('change', function() {
        var distId = $(this).val();
        $('#m_union_id').html('<option value="">-- ইউনিয়ন বাছুন --</option>').prop('disabled', true);
        loadBdUpazilas(distId, '#m_upazila_id', '');
    });

    $('#m_upazila_id').on('change', function() {
        var upzId = $(this).val();
        loadBdUnions(upzId, '#m_union_id', '');
    });

    // ── Quick Edit Customer Cascading BD Geocoding ───────────────────────────
    $('#edit_m_division_id').on('change', function() {
        var divId = $(this).val();
        $('#edit_m_upazila_id').html('<option value="">-- আগে জেলা বাছুন --</option>').prop('disabled', true);
        $('#edit_m_union_id').html('<option value="">-- ইউনিয়ন বাছুন --</option>').prop('disabled', true);
        loadBdDistricts(divId, '#edit_m_district_id', '');
    });

    $('#edit_m_district_id').on('change', function() {
        var distId = $(this).val();
        $('#edit_m_union_id').html('<option value="">-- ইউনিয়ন বাছুন --</option>').prop('disabled', true);
        loadBdUpazilas(distId, '#edit_m_upazila_id', '');
    });

    $('#edit_m_upazila_id').on('change', function() {
        var upzId = $(this).val();
        loadBdUnions(upzId, '#edit_m_union_id', '');
    });


    // ── Cart holds / Parking ─────────────────────────────────────────────────
    
    btnAddHold.addEventListener('click', function() {
        var ids = Object.keys(cart);
        if (ids.length === 0) {
            alert_float('warning', 'Cannot hold an empty cart.');
            return;
        }

        var note = prompt("Enter a brief hold note / customer name to hold this cart:");
        if (note === null) return; // Cancelled

        var payload = {
            client_id: document.getElementById('client_id').value,
            items: ids.map(id => ({
                product_id: parseInt(id),
                qty: cart[id].qty,
                rate: cart[id].rate,
                name: cart[id].name,
                sku: cart[id].sku,
                stock: cart[id].stock
            })),
            note: note
        };

        $.post('<?= admin_url('pos/add_hold') ?>', {
            client_id: payload.client_id,
            items: JSON.stringify(payload.items),
            note: payload.note
        }, function(response) {
            var data = JSON.parse(response);
            if (data.success) {
                alert_float('success', 'Cart held successfully.');
                cart = {};
                renderCart();
            } else {
                alert_float('danger', 'Failed to hold cart: ' + data.error);
            }
        });
    });

    btnListHold.addEventListener('click', function() {
        $.get('<?= admin_url('pos/get_holds') ?>', function(response) {
            var holds = JSON.parse(response);
            var tbody = document.querySelector('#holds-table tbody');
            tbody.innerHTML = '';

            if (holds.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No held carts found.</td></tr>';
            } else {
                holds.forEach(function(h) {
                    var row = '<tr>' +
                        '  <td>' + h.id + '</td>' +
                        '  <td>' + (h.customer_name || 'Walk-in Customer') + '</td>' +
                        '  <td>' + (h.hold_note || '-') + '</td>' +
                        '  <td>' + h.created_at + '</td>' +
                        '  <td>' +
                        '     <button class="btn btn-info btn-xs load-hold-btn" data-id="' + h.id + '">Restore</button>' +
                        '     <button class="btn btn-danger btn-xs delete-hold-btn mleft5" data-id="' + h.id + '">Delete</button>' +
                        '  </td>' +
                        '</tr>';
                    tbody.insertAdjacentHTML('beforeend', row);
                });

                // Attach hold restore & delete actions
                document.querySelectorAll('.load-hold-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        var id = this.getAttribute('data-id');
                        $.post('<?= admin_url('pos/load_hold') ?>', { hold_id: id }, function(res) {
                            var data = JSON.parse(res);
                            if (data.success) {
                                cart = {};
                                data.hold.items.forEach(function(item) {
                                    cart[item.product_id] = item;
                                });
                                renderCart();
                                $('#holds-list-modal').modal('hide');
                                alert_float('success', 'Cart restored successfully.');
                            } else {
                                alert_float('danger', data.error);
                            }
                        });
                    });
                });

                document.querySelectorAll('.delete-hold-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        var id = this.getAttribute('data-id');
                        if (confirm('Delete this held cart?')) {
                            $.post('<?= admin_url('pos/delete_hold') ?>', { hold_id: id }, function() {
                                btnListHold.click(); // Reload list
                                alert_float('success', 'Held cart deleted.');
                            });
                        }
                    });
                });
            }

            $('#holds-list-modal').modal('show');
        });
    });

    // ── sales history ────────────────────────────────────────────────────────
    
    btnSaleList.addEventListener('click', function() {
        $.get('<?= admin_url('pos/get_sales') ?>', function(response) {
            var sales = JSON.parse(response);
            var tbody = document.querySelector('#sales-table tbody');
            tbody.innerHTML = '';

            if (sales.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No sales completed in this session yet.</td></tr>';
            } else {
                sales.forEach(function(s) {
                    var inv_num = s.prefix + s.number;
                    var row = '<tr>' +
                        '  <td>' + inv_num + '</td>' +
                        '  <td>' + (s.customer_name || 'Walk-in Customer') + '</td>' +
                        '  <td><span class="label label-info">' + s.payment_method + '</span></td>' +
                        '  <td>' + pos_format_money(s.invoice_total) + ' BDT</td>' +
                        '  <td>' + s.created_at + '</td>' +
                        '  <td>' +
                        '     <a href="<?= admin_url('invoices/list_invoices/') ?>' + s.invoice_id + '" class="btn btn-default btn-xs" target="_blank"><i class="fa fa-eye"></i> View</a>' +
                        '  </td>' +
                        '</tr>';
                    tbody.insertAdjacentHTML('beforeend', row);
                });
            }

            $('#sales-list-modal').modal('show');
        });
    });

    // ── Pending Payment Action ───────────────────────────────────────────────
    
    if (btnPendingPayment) {
        btnPendingPayment.addEventListener('click', function() {
            if (Object.keys(cart).length === 0) {
                alert_float('warning', 'Cart is empty.');
                return;
            }
            var payload = getCheckoutPayload();
            payload.payment_method = 'pending_payment';
            payload.payment_ref = '';
            runCheckout(payload);
        });
    }

    // Clear cart
    btnClearCart.addEventListener('click', function() {
        if (confirm('Are you sure you want to clear the cart?')) {
            cart = {};
            renderCart();
            calcDiscountVal.value = 0;
            calcShipping.value = 0;
        }
    });

    // Quick Customer save callback
    document.getElementById('btn-save-customer').addEventListener('click', function() {
        var company = document.getElementById('m_company').value.trim();
        var phone = document.getElementById('m_phone').value.trim();
        var email = document.getElementById('m_email').value.trim();
        var street = document.getElementById('m_address') ? document.getElementById('m_address').value.trim() : '';
        
        var divSelect = document.getElementById('m_division_id');
        var distSelect = document.getElementById('m_district_id');
        var upzSelect = document.getElementById('m_upazila_id');
        var unSelect = document.getElementById('m_union_id');

        var divName = (divSelect && divSelect.selectedIndex > 0) ? (divSelect.options[divSelect.selectedIndex].getAttribute('data-name') || '') : '';
        var distName = (distSelect && distSelect.selectedIndex > 0) ? (distSelect.options[distSelect.selectedIndex].getAttribute('data-name') || '') : '';
        var upzName = (upzSelect && upzSelect.selectedIndex > 0) ? (upzSelect.options[upzSelect.selectedIndex].getAttribute('data-name') || '') : '';
        var unName = (unSelect && unSelect.selectedIndex > 0) ? unSelect.options[unSelect.selectedIndex].text.split('(')[0].trim() : '';

        var divId = divSelect ? divSelect.value : '';
        var distId = distSelect ? distSelect.value : '';
        var upzId = upzSelect ? upzSelect.value : '';
        var unId = unSelect ? unSelect.value : '';

        // Compose full address: e.g. "House 12, Road 4, Union, Upazila"
        var fullAddressParts = [];
        if (street) fullAddressParts.push(street);
        if (unName) fullAddressParts.push(unName);
        if (upzName) fullAddressParts.push(upzName);
        var composedAddress = fullAddressParts.join(', ');

        var modalAlert = document.getElementById('customer-modal-alert');
        
        if (company === '' || phone === '') {
            modalAlert.style.display = 'block';
            modalAlert.innerText = 'Customer name and phone number are required.';
            return;
        }

        modalAlert.style.display = 'none';
        
        $.post('<?= admin_url('pos/quick_customer') ?>', {
            company: company,
            phone: phone,
            email: email,
            address: composedAddress || street,
            city: distName,
            state: divName,
            division_id: divId,
            district_id: distId,
            upazila_id: upzId,
            union_id: unId
        }, function(response) {
            var data = JSON.parse(response);
            if (data.success) {
                selectEntity('client', data.client_id, company, phone, email, composedAddress || street, distName, divName, divId, distId, upzId, unId);
                
                // If geocodes were selected, stage them into hidden pos fields
                if (divId) $('#pos_division_id').val(divId);
                if (distId) $('#pos_district_id').val(distId);
                if (upzId) $('#pos_upazila_id').val(upzId);
                if (unId) $('#pos_union_id').val(unId);

                $('#customer-quick-modal').modal('hide');
                alert_float('success', 'Customer added successfully!');
            } else {
                modalAlert.style.display = 'block';
                modalAlert.innerText = data.error;
            }
        });
    });

    // ── Unified Fast Customer & Lead Live Search ──────────────────────────────
    var custSearchInput   = document.getElementById('pos_customer_search');
    var custSearchResults = document.getElementById('pos-customer-search-results');
    var selectedBadge     = document.getElementById('pos-selected-badge');
    var selectedBadgeText = document.getElementById('pos-selected-badge-text');
    var selectedRemove    = document.getElementById('pos-selected-remove');
    var btnEditCustomer   = document.getElementById('btn-edit-customer');
    var hiddenClientId    = document.getElementById('client_id');
    var hiddenLeadId      = document.getElementById('lead_id');
    var walkinId          = '<?= get_option("pos_default_walkin_client_id") ?>';
    var custSearchTimer   = null;

    var currentSelectedEntity = {
        type: 'walkin',
        id: walkinId,
        name: 'Walk-in Customer',
        phone: '',
        email: '',
        address: '',
        city: '',
        state: '',
        division_id: '',
        district_id: '',
        upazila_id: '',
        union_id: ''
    };

    function selectEntity(type, id, name, phone, email, address, city, state, division_id, district_id, upazila_id, union_id) {
        currentSelectedEntity = {
            type: type,
            id: id,
            name: name,
            phone: phone,
            email: email,
            address: address,
            city: city,
            state: state,
            division_id: division_id || '',
            district_id: district_id || '',
            upazila_id: upazila_id || '',
            union_id: union_id || ''
        };

        if (type === 'client') {
            hiddenClientId.value = id;
            hiddenLeadId.value = '';
            selectedBadgeText.innerHTML = '<span class="label label-success" style="margin-right: 5px;"><i class="fa fa-user"></i> Customer #' + id + '</span> ' + escapeHtml(name) + (phone ? ' (' + escapeHtml(phone) + ')' : '');
            selectedBadge.style.display = 'flex';
            selectedBadge.style.background = '#f0fdf4';
            selectedBadge.style.borderColor = '#bbf7d0';
            selectedBadge.style.color = '#166534';
            btnEditCustomer.style.display = 'inline-block';
        } else if (type === 'lead') {
            hiddenClientId.value = '';
            hiddenLeadId.value = id;
            selectedBadgeText.innerHTML = '<span class="label label-info" style="margin-right: 5px;"><i class="fa fa-bullhorn"></i> Lead #' + id + '</span> ' + escapeHtml(name) + (phone ? ' (' + escapeHtml(phone) + ')' : '') + ' <small style="color: #0369a1; margin-left: 5px;">[Will auto-convert to customer on sale]</small>';
            selectedBadge.style.display = 'flex';
            selectedBadge.style.background = '#e0f2fe';
            selectedBadge.style.borderColor = '#bae6fd';
            selectedBadge.style.color = '#0369a1';
            btnEditCustomer.style.display = 'none';
        }
        custSearchInput.value = '';
        custSearchResults.style.display = 'none';

        // Pre-fill delivery recipient fields
        if (name) $('#pos_recipient_name').val(name);
        if (phone) $('#pos_recipient_phone').val(phone);
        if (address) $('#pos_delivery_address').val(address);

        // Pre-fill hidden POS geocode fields
        $('#pos_division_id').val(division_id || '');
        $('#pos_district_id').val(district_id || '');
        $('#pos_upazila_id').val(upazila_id || '');
        $('#pos_union_id').val(union_id || '');
    }

    function resetToWalkin() {
        currentSelectedEntity = {
            type: 'walkin',
            id: walkinId,
            name: 'Walk-in Customer',
            phone: '',
            email: '',
            address: '',
            city: '',
            state: '',
            division_id: '',
            district_id: '',
            upazila_id: '',
            union_id: ''
        };
        hiddenClientId.value = walkinId;
        hiddenLeadId.value = '';
        selectedBadge.style.display = 'none';
        btnEditCustomer.style.display = 'none';
        custSearchInput.value = '';
        custSearchResults.style.display = 'none';
        $('#pos_division_id').val('');
        $('#pos_district_id').val('');
        $('#pos_upazila_id').val('');
        $('#pos_union_id').val('');
        resetOnlineDeliveryForm();
    }

    if (selectedRemove) {
        selectedRemove.addEventListener('click', function(e) {
            e.preventDefault();
            resetToWalkin();
        });
    }

    if (custSearchInput) {
        custSearchInput.addEventListener('input', function() {
            clearTimeout(custSearchTimer);
            var q = this.value.trim();
            if (q.length < 1) {
                custSearchResults.style.display = 'none';
                custSearchResults.innerHTML = '';
                return;
            }

            custSearchTimer = setTimeout(function() {
                $.getJSON('<?= admin_url("pos/search_customer_or_lead") ?>', { q: q }, function(res) {
                    if (!res || res.length === 0) {
                        custSearchResults.innerHTML = '<div style="padding: 10px; color: #64748b; text-align: center; font-size: 12px;">No customers or leads matched "<b>' + escapeHtml(q) + '</b>"</div>';
                        custSearchResults.style.display = 'block';
                        return;
                    }

                    var html = '';
                    res.forEach(function(item) {
                        var sub = item.phone || '';
                        if (item.city) sub += (sub ? ' • ' : '') + item.city;
                        html += '<div class="pos-search-item" ' +
                            'data-type="' + item.type + '" ' +
                            'data-id="' + item.id + '" ' +
                            'data-name="' + escapeHtml(item.name) + '" ' +
                            'data-phone="' + escapeHtml(item.phone) + '" ' +
                            'data-email="' + escapeHtml(item.email) + '" ' +
                            'data-address="' + escapeHtml(item.address || '') + '" ' +
                            'data-city="' + escapeHtml(item.city || '') + '" ' +
                            'data-state="' + escapeHtml(item.state || '') + '" ' +
                            'data-division-id="' + (item.division_id || '') + '" ' +
                            'data-district-id="' + (item.district_id || '') + '" ' +
                            'data-upazila-id="' + (item.upazila_id || '') + '" ' +
                            'data-union-id="' + (item.union_id || '') + '" ' +
                            'style="padding: 8px 12px; border-bottom: 1px solid #f1f5f9; cursor: pointer; display: flex; align-items: center; justify-content: space-between;">' +
                            '<div>' +
                                '<span class="label ' + item.badge_cls + '" style="font-size: 10px; margin-right: 6px;">' + item.badge + '</span>' +
                                '<strong style="color: #1e293b; font-size: 13px;">' + escapeHtml(item.name) + '</strong>' +
                                (sub ? '<div style="font-size: 11px; color: #64748b; margin-top: 2px;"><i class="fa fa-phone"></i> ' + escapeHtml(sub) + '</div>' : '') +
                            '</div>' +
                            '<i class="fa fa-chevron-right text-muted" style="font-size: 11px;"></i>' +
                        '</div>';
                    });

                    custSearchResults.innerHTML = html;
                    custSearchResults.style.display = 'block';

                    $('.pos-search-item').on('click', function() {
                        var type = $(this).data('type');
                        var id = $(this).data('id');
                        var name = $(this).data('name');
                        var phone = $(this).data('phone');
                        var email = $(this).data('email');
                        var address = $(this).data('address') || '';
                        var city = $(this).data('city') || '';
                        var state = $(this).data('state') || '';
                        var divId = $(this).data('division-id') || '';
                        var distId = $(this).data('district-id') || '';
                        var upzId = $(this).data('upazila-id') || '';
                        var unId = $(this).data('union-id') || '';
                        selectEntity(type, id, name, phone, email, address, city, state, divId, distId, upzId, unId);
                    });
                });
            }, 150);
        });
    }


    $(document).on('click', function(e) {
        if (!$(e.target).closest('#pos_customer_search, #pos-customer-search-results').length) {
            if (custSearchResults) custSearchResults.style.display = 'none';
        }
    });

    function escapeHtml(text) {
        if (!text) return '';
        return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    // ── Floating Calculator Logic ────────────────────────────────────────────
    var calcScreen = document.getElementById('calc-screen');
    var currentInput = '';
    
    document.querySelectorAll('.calc-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var val = this.getAttribute('data-val');
            
            if (val === 'C') {
                currentInput = '';
                calcScreen.value = '';
            } else if (val === 'DEL') {
                currentInput = currentInput.slice(0, -1);
                calcScreen.value = currentInput;
            } else if (val === '=') {
                try {
                    if (currentInput.trim() !== '') {
                        // Safe evaluation of basic mathematical operations
                        var cleanInput = currentInput.replace(/[^0-9+\-*/.]/g, '');
                        var result = Function('"use strict";return (' + cleanInput + ')')();
                        calcScreen.value = result;
                        currentInput = result.toString();
                    }
                } catch (e) {
                    calcScreen.value = 'Error';
                    currentInput = '';
                }
            } else {
                currentInput += val;
                calcScreen.value = currentInput;
            }
        });
    });
    // ── Edit Customer Logic ──────────────────────────────────────────────────
    window.openEditCustomerModal = function() {
        var clientId = $('#client_id').val();
        var walkinId = '<?= get_option('pos_default_walkin_client_id') ?>';
        if (!clientId || clientId == walkinId) {
            alert_float('warning', 'Please select a customer first to edit. Walk-in Customer cannot be edited.');
            return;
        }

        // Fetch customer details via AJAX
        $.getJSON('<?= admin_url('pos/get_customer_ajax') ?>/' + clientId, function(res) {
            if (res.success) {
                var c = res.customer;
                document.getElementById('edit_m_id').value = c.userid;
                document.getElementById('edit_m_company').value = c.company || '';
                document.getElementById('edit_m_phone').value = c.contact_phone || (c.client_phone || '');
                document.getElementById('edit_m_email').value = c.contact_email || '';
                if (document.getElementById('edit_m_address')) {
                    document.getElementById('edit_m_address').value = c.address || '';
                }

                // Match Division & Cascading District, Upazila, Union
                var editDivId = c.division_id || '';
                if (!editDivId && c.state) {
                    var sLower = c.state.trim().toLowerCase();
                    $('#edit_m_division_id option').each(function() {
                        var optName = ($(this).data('name') || $(this).text()).toLowerCase();
                        if (optName.indexOf(sLower) !== -1 || sLower.indexOf(optName) !== -1) {
                            editDivId = $(this).val();
                            return false;
                        }
                    });
                }
                $('#edit_m_division_id').val(editDivId);
                if (editDivId) {
                    var editDist = c.district_id || c.city || '';
                    loadBdDistricts(editDivId, '#edit_m_district_id', editDist, function() {
                        var selectedDist = $('#edit_m_district_id').val() || c.district_id;
                        if (selectedDist) {
                            loadBdUpazilas(selectedDist, '#edit_m_upazila_id', c.upazila_id, function() {
                                var selectedUpz = $('#edit_m_upazila_id').val() || c.upazila_id;
                                if (selectedUpz) {
                                    loadBdUnions(selectedUpz, '#edit_m_union_id', c.union_id);
                                }
                            });
                        }
                    });
                } else {
                    $('#edit_m_district_id').html('<option value="">-- আগে বিভাগ বাছুন --</option>').prop('disabled', true);
                    $('#edit_m_upazila_id').html('<option value="">-- আগে জেলা বাছুন --</option>').prop('disabled', true);
                    $('#edit_m_union_id').html('<option value="">-- ইউনিয়ন বাছুন --</option>').prop('disabled', true);
                }
                
                document.getElementById('customer-edit-modal-alert').style.display = 'none';
                $('#customer-edit-modal').modal('show');
            } else {
                alert_float('danger', res.error || 'Failed to fetch customer details.');
            }
        });
    };

    $('#btn-update-customer').on('click', function() {
        var id = document.getElementById('edit_m_id').value;
        var company = document.getElementById('edit_m_company').value.trim();
        var phone = document.getElementById('edit_m_phone').value.trim();
        var email = document.getElementById('edit_m_email').value.trim();
        var street = document.getElementById('edit_m_address') ? document.getElementById('edit_m_address').value.trim() : '';

        var divSelect = document.getElementById('edit_m_division_id');
        var distSelect = document.getElementById('edit_m_district_id');
        var upzSelect = document.getElementById('edit_m_upazila_id');
        var unSelect = document.getElementById('edit_m_union_id');

        var divName = (divSelect && divSelect.selectedIndex > 0) ? (divSelect.options[divSelect.selectedIndex].getAttribute('data-name') || '') : '';
        var distName = (distSelect && distSelect.selectedIndex > 0) ? (distSelect.options[distSelect.selectedIndex].getAttribute('data-name') || '') : '';
        var upzName = (upzSelect && upzSelect.selectedIndex > 0) ? (upzSelect.options[upzSelect.selectedIndex].getAttribute('data-name') || '') : '';
        var unName = (unSelect && unSelect.selectedIndex > 0) ? unSelect.options[unSelect.selectedIndex].text.split('(')[0].trim() : '';

        var divId = divSelect ? divSelect.value : '';
        var distId = distSelect ? distSelect.value : '';
        var upzId = upzSelect ? upzSelect.value : '';
        var unId = unSelect ? unSelect.value : '';

        var fullParts = [];
        if (street) fullParts.push(street);
        if (unName) fullParts.push(unName);
        if (upzName) fullParts.push(upzName);
        var composedAddress = fullParts.join(', ');

        var modalAlert = document.getElementById('customer-edit-modal-alert');

        if (company === '' || phone === '') {
            modalAlert.style.display = 'block';
            modalAlert.innerText = 'Name and phone are required.';
            return;
        }

        modalAlert.style.display = 'none';
        
        $.post('<?= admin_url('pos/update_customer_ajax') ?>', {
            id: id,
            company: company,
            phone: phone,
            email: email,
            address: composedAddress || street,
            city: distName,
            state: divName,
            division_id: divId,
            district_id: distId,
            upazila_id: upzId,
            union_id: unId
        }, function(response) {
            var data = JSON.parse(response);
            if (data.success) {
                selectEntity('client', id, company, phone, email, composedAddress || street, distName, divName, divId, distId, upzId, unId);
                if (divId) $('#pos_division_id').val(divId);
                if (distId) $('#pos_district_id').val(distId);
                if (upzId) $('#pos_upazila_id').val(upzId);
                if (unId) $('#pos_union_id').val(unId);

                $('#customer-edit-modal').modal('hide');
                alert_float('success', 'Customer updated successfully!');
            } else {
                modalAlert.style.display = 'block';
                modalAlert.innerText = data.error;
            }
        });
    });


    // ── POS Ergonomics: Barcode Auto-Focus & Keyboard Shortcuts ─────────────
    window.focusBarcodeScanner = function() {
        setTimeout(function() {
            if ($('#pos-product-search').length && $('.modal.in').length === 0) {
                $('#pos-product-search').focus();
            }
        }, 80);
    };

    // Auto-focus on initial load
    focusBarcodeScanner();

    // Hotkeys handler
    $(document).on('keydown', function(e) {
        var modalOpen = $('.modal.in').length > 0;
        if (e.key === 'F2') {
            e.preventDefault();
            if (!modalOpen) $('#btn-quick-cash').trigger('click');
        } else if (e.key === 'F4') {
            e.preventDefault();
            $('#pos-product-search').focus().select();
        } else if (e.key === 'F8') {
            e.preventDefault();
            $('#pos-calculator-modal').modal('toggle');
        } else if (e.key === 'F9') {
            e.preventDefault();
            if (!modalOpen) $('#btn-add-hold').trigger('click');
        } else if (e.key === 'Escape') {
            if (!modalOpen && Object.keys(cart).length > 0) {
                e.preventDefault();
                $('#btn-clear-cart').trigger('click');
            }
        }
    });

    // Refocus scanner after modal closes
    $('.modal').on('hidden.bs.modal', function() {
        focusBarcodeScanner();
    });
});

function toggleFullScreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(err => {
            alert(`Error attempting to enable full-screen mode: ${err.message}`);
        });
    } else {
        document.exitFullscreen();
    }
}
</script>
