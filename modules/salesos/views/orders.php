<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
/* ── Modern Order Manager Styling ── */
.order-header-wrap {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 20px;
}
.order-header-actions {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}

/* KPI Summary Metric Cards */
.kpi-metric-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    transition: all 0.2s ease;
    cursor: pointer;
    min-height: 78px;
}
.kpi-metric-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.08);
    border-color: #cbd5e1;
}
.kpi-metric-card.active-kpi {
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
}
.kpi-lbl {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 3px;
}
.kpi-val {
    font-size: 20px;
    font-weight: 800;
    line-height: 1.2;
}
.kpi-sub {
    font-size: 11px;
    font-weight: 500;
    margin-top: 3px;
    display: block;
}
.kpi-icon-box {
    width: 44px;
    height: 44px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

/* Filter Toolbar */
.order-filter-toolbar {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 14px 16px 4px 16px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}

/* Bulk Actions Floating Bar */
#bulk-actions-bar {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 6px;
    padding: 10px 16px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}

/* Table Styling */
.table-orders > thead > tr > th {
    background: #f8fafc !important;
    color: #475569;
    font-weight: 700;
    font-size: 12px;
    border-bottom: 2px solid #e2e8f0 !important;
    vertical-align: middle;
}
.table-orders > tbody > tr > td {
    vertical-align: middle !important;
    font-size: 12px;
}
.table-orders > tbody > tr:hover {
    background-color: #f8fafc !important;
}

/* Channel Pills */
.pill-channel-woo {
    background: #f5f3ff;
    color: #6d28d9;
    border: 1px solid #ddd6fe;
    font-weight: 700;
    font-size: 11px;
    padding: 3px 8px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.pill-channel-pos {
    background: #ecfdf5;
    color: #065f46;
    border: 1px solid #a7f3d0;
    font-weight: 700;
    font-size: 11px;
    padding: 3px 8px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.pill-channel-manual {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
    font-weight: 700;
    font-size: 11px;
    padding: 3px 8px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.pill-channel-whatsapp {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;
    font-weight: 700;
    font-size: 11px;
    padding: 3px 8px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.pill-channel-pos-online {
    background: #e0f2fe;
    color: #0369a1;
    border: 1px solid #bae6fd;
    font-weight: 700;
    font-size: 11px;
    padding: 3px 8px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

/* Status Pills */
.pill-status {
    font-weight: 700;
    font-size: 11px;
    padding: 3px 10px;
    border-radius: 9999px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.pill-status-pending {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
}
.pill-status-confirmed {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #86efac;
}
.pill-status-processing {
    background: #e0f2fe;
    color: #0369a1;
    border: 1px solid #7dd3fc;
}
.pill-status-delivered {
    background: #f3e8ff;
    color: #6b21a8;
    border: 1px solid #d8b4fe;
}
.pill-status-cancelled {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

/* Fraud Badge */
.fraud-badge-pill {
    font-size: 10px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 9999px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-top: 4px;
}

/* WhatsApp Quick Connect */
.btn-whatsapp-quick {
    background: #25d366;
    color: #ffffff !important;
    border: 1px solid #22c55e;
    border-radius: 4px;
    padding: 1px 6px;
    font-size: 10px;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    text-decoration: none !important;
    transition: background 0.15s;
}
.btn-whatsapp-quick:hover {
    background: #16a34a;
}
</style>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        
                        <!-- Header -->
                        <div class="order-header-wrap">
                            <div>
                                <h4 class="no-margin bold text-primary" style="font-size: 20px; display: flex; align-items: center; gap: 8px;">
                                    <i class="fa fa-shopping-bag text-primary"></i> Order Manager (ই-কমার্স ও POS অর্ডারসমূহ)
                                </h4>
                                <span class="text-muted" style="display: block; margin-top: 4px;">
                                    WooCommerce, POS কাউন্টার ও সোশ্যাল চ্যানেলের সকল অর্ডার, কাস্টমার ভেরিফিকেশন ও কুরিয়ার ট্র্যাকিং সেন্টার।
                                </span>
                            </div>
                            <div class="order-header-actions">
                                <a href="<?= admin_url('pos') ?>" class="btn btn-success" style="font-weight: 700; border-radius: 6px;">
                                    <i class="fa fa-desktop"></i> Open POS
                                </a>
                                <?php if (staff_can('settings', 'salesos')): ?>
                                    <a href="<?= admin_url('salesos/settings?tab=channels') ?>" class="btn btn-default" style="font-weight: 600; border-color: #cbd5e1; border-radius: 6px;">
                                        <i class="fa fa-plug text-primary"></i> Integrations
                                    </a>
                                <?php endif; ?>
                                <a href="<?= admin_url('salesos') ?>" class="btn btn-primary" style="font-weight: 600; border-radius: 6px;">
                                    <i class="fa fa-tachometer"></i> Dashboard
                                </a>
                            </div>
                        </div>

                        <!-- ── Top KPI Metrics Summary Bar (Clickable) ── -->
                        <div class="row mbot15">
                            <!-- Card 1: Total Orders & Revenue -->
                            <div class="col-md-2 col-sm-4 col-xs-6 mbot10" style="padding: 0 6px;">
                                <div class="kpi-metric-card" id="kpi-card-all" data-filter-status="" title="সকল অর্ডার দেখুন">
                                    <div>
                                        <div class="kpi-lbl" style="color: #2563eb;">মোট অর্ডার</div>
                                        <div class="kpi-val" style="color: #1d4ed8;"><?= (int)($kpi['total_orders'] ?? 0) ?></div>
                                        <span class="kpi-sub text-muted"><?= salesos_format_number((float)($kpi['total_revenue'] ?? 0)) ?> BDT</span>
                                    </div>
                                    <div class="kpi-icon-box" style="background: #eff6ff; color: #2563eb;">
                                        <i class="fa fa-shopping-basket"></i>
                                    </div>
                                </div>
                            </div>
                            <!-- Card 2: Pending Confirmation -->
                            <div class="col-md-2 col-sm-4 col-xs-6 mbot10" style="padding: 0 6px;">
                                <div class="kpi-metric-card" id="kpi-card-pending" data-filter-status="pending" title="পেন্ডিং অর্ডার ফিল্টার করুন">
                                    <div>
                                        <div class="kpi-lbl" style="color: #d97706;">অপেক্ষমান</div>
                                        <div class="kpi-val" style="color: #b45309;"><?= (int)($kpi['pending_count'] ?? 0) ?></div>
                                        <span class="kpi-sub text-muted">কল ও ভেরিফিকেশন বাকি</span>
                                    </div>
                                    <div class="kpi-icon-box" style="background: #fef3c7; color: #d97706;">
                                        <i class="fa fa-phone"></i>
                                    </div>
                                </div>
                            </div>
                            <!-- Card 3: Ready / Confirmed & Processing -->
                            <div class="col-md-2 col-sm-4 col-xs-6 mbot10" style="padding: 0 6px;">
                                <div class="kpi-metric-card" id="kpi-card-ready" data-filter-status="confirmed" title="রেডি ও প্রসেসিং অর্ডার দেখুন">
                                    <div>
                                        <div class="kpi-lbl" style="color: #4f46e5;">প্রসেসিং ও প্রস্তুত</div>
                                        <div class="kpi-val" style="color: #4338ca;"><?= (int)(($kpi['confirmed_count'] ?? 0) + ($kpi['processing_count'] ?? 0)) ?></div>
                                        <span class="kpi-sub text-muted">প্যাকেজিং ও ডিসপ্যাচ</span>
                                    </div>
                                    <div class="kpi-icon-box" style="background: #e0e7ff; color: #4f46e5;">
                                        <i class="fa fa-cube"></i>
                                    </div>
                                </div>
                            </div>
                            <!-- Card 4: Courier Shipped / Booked -->
                            <div class="col-md-3 col-sm-6 col-xs-6 mbot10" style="padding: 0 6px;">
                                <div class="kpi-metric-card" id="kpi-card-courier" data-filter-courier="booked" title="কুরিয়ারে বুক হওয়া পার্সেল">
                                    <div>
                                        <div class="kpi-lbl" style="color: #059669;">কুরিয়ারে প্রেরিত</div>
                                        <div class="kpi-val" style="color: #047857;"><?= (int)($kpi['courier_booked_count'] ?? 0) ?></div>
                                        <span class="kpi-sub text-muted">পার্সেল ডেলিভারিতে আছে</span>
                                    </div>
                                    <div class="kpi-icon-box" style="background: #ecfdf5; color: #059669;">
                                        <i class="fa fa-truck"></i>
                                    </div>
                                </div>
                            </div>
                            <!-- Card 5: High Risk Alert -->
                            <div class="col-md-3 col-sm-6 col-xs-12 mbot10" style="padding: 0 6px;">
                                <div class="kpi-metric-card" id="kpi-card-risk" data-filter-risk="high" title="সন্দেহভাজন ফ্রড অর্ডার দেখুন">
                                    <div>
                                        <div class="kpi-lbl" style="color: #dc2626;">উচ্চ ঝুঁকি অ্যালার্ট</div>
                                        <div class="kpi-val" style="color: #b91c1c;"><?= (int)($kpi['high_risk_count'] ?? 0) ?></div>
                                        <span class="kpi-sub text-muted">কম ডেলিভারি রেশিও ফ্রড</span>
                                    </div>
                                    <div class="kpi-icon-box" style="background: #fee2e2; color: #dc2626;">
                                        <i class="fa fa-shield"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ── Instant Search & Filter Toolbar ── -->
                        <div class="order-filter-toolbar">
                            <div class="row">
                                <div class="col-md-3 col-sm-6 mbot10">
                                    <div class="input-group">
                                        <span class="input-group-addon" style="background: #f8fafc; border-color: #cbd5e1;"><i class="fa fa-search text-muted"></i></span>
                                        <input type="text" id="order-search" class="form-control" placeholder="অর্ডার #, নাম, ফোন, Ref ID..." value="<?= e($search ?? '') ?>" style="border-color: #cbd5e1;">
                                    </div>
                                </div>
                                <div class="col-md-2 col-sm-6 mbot10">
                                    <select id="filter-channel" class="form-control" style="border-color: #cbd5e1;">
                                        <option value="">সকল চ্যানেল (All Channels)</option>
                                        <option value="woo" <?= (isset($selected_channel) && in_array($selected_channel, ['woo', 'woocommerce'], true)) ? 'selected' : '' ?>>WooCommerce (WOO)</option>
                                        <option value="pos" <?= (isset($selected_channel) && $selected_channel === 'pos') ? 'selected' : '' ?>>Point of Sale - Counter (POS)</option>
                                        <option value="pos_online" <?= (isset($selected_channel) && $selected_channel === 'pos_online') ? 'selected' : '' ?>>POS Online Delivery (অনলাইন)</option>
                                        <option value="whatsapp" <?= (isset($selected_channel) && $selected_channel === 'whatsapp') ? 'selected' : '' ?>>WhatsApp (BIZBOT)</option>
                                        <option value="manual" <?= (isset($selected_channel) && $selected_channel === 'manual') ? 'selected' : '' ?>>Manual / Social (MANUAL)</option>
                                    </select>
                                </div>
                                <div class="col-md-2 col-sm-6 mbot10">
                                    <select id="filter-status" class="form-control" style="border-color: #cbd5e1;">
                                        <option value="">সকল স্ট্যাটাস (All Statuses)</option>
                                        <option value="pending" <?= (isset($selected_status) && $selected_status === 'pending') ? 'selected' : '' ?>>Pending (অপেক্ষমান)</option>
                                        <option value="confirmed" <?= (isset($selected_status) && $selected_status === 'confirmed') ? 'selected' : '' ?>>Confirmed (নিশ্চিত)</option>
                                        <option value="processing" <?= (isset($selected_status) && $selected_status === 'processing') ? 'selected' : '' ?>>Processing (প্রসেসিং)</option>
                                        <option value="delivered" <?= (isset($selected_status) && $selected_status === 'delivered') ? 'selected' : '' ?>>Delivered (ডেলিভার্ড)</option>
                                        <option value="cancelled" <?= (isset($selected_status) && $selected_status === 'cancelled') ? 'selected' : '' ?>>Cancelled (বাতিল)</option>
                                    </select>
                                </div>
                                <div class="col-md-2 col-sm-6 mbot10">
                                    <select id="filter-courier" class="form-control" style="border-color: #cbd5e1;">
                                        <option value="">কুরিয়ার স্ট্যাটাস (All)</option>
                                        <option value="unbooked" <?= (isset($selected_courier_status) && $selected_courier_status === 'unbooked') ? 'selected' : '' ?>>কুরিয়ার বাকি (Unbooked)</option>
                                        <option value="booked" <?= (isset($selected_courier_status) && $selected_courier_status === 'booked') ? 'selected' : '' ?>>কুরিয়ারে বুকড (Booked)</option>
                                    </select>
                                </div>
                                <div class="col-md-2 col-sm-6 mbot10">
                                    <select id="filter-date" class="form-control" style="border-color: #cbd5e1;">
                                        <option value="">সকল সময় (All Time)</option>
                                        <option value="today" <?= (isset($selected_date_range) && $selected_date_range === 'today') ? 'selected' : '' ?>>আজকের অর্ডার (Today)</option>
                                        <option value="yesterday" <?= (isset($selected_date_range) && $selected_date_range === 'yesterday') ? 'selected' : '' ?>>গতকাল (Yesterday)</option>
                                        <option value="this_week" <?= (isset($selected_date_range) && $selected_date_range === 'this_week') ? 'selected' : '' ?>>এই সপ্তাহ (This Week)</option>
                                        <option value="this_month" <?= (isset($selected_date_range) && $selected_date_range === 'this_month') ? 'selected' : '' ?>>এই মাস (This Month)</option>
                                    </select>
                                </div>
                                <div class="col-md-1 col-sm-6 mbot10 text-right">
                                    <button type="button" id="btn-reset-filters" class="btn btn-default btn-block" style="border-color: #cbd5e1; font-weight: 600;" title="ফিল্টার রিসেট করুন">
                                        <i class="fa fa-refresh text-muted"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- ── Bulk Actions Floating Toolbar ── -->
                        <div id="bulk-actions-bar" style="display: none;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i class="fa fa-check-square-o text-primary" style="font-size: 16px;"></i>
                                <span style="font-weight: 700; color: #1e40af;"><span id="bulk-selected-count">0</span> টি অর্ডার সিলেক্ট করা হয়েছে</span>
                            </div>
                            <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                                <button type="button" class="btn btn-success btn-xs btn-bulk-action" data-action="confirmed" style="font-weight: 600; padding: 4px 10px;">
                                    <i class="fa fa-check"></i> Mark Confirmed
                                </button>
                                <button type="button" class="btn btn-info btn-xs btn-bulk-action" data-action="processing" style="font-weight: 600; padding: 4px 10px;">
                                    <i class="fa fa-cube"></i> Mark Processing
                                </button>
                                <button type="button" class="btn btn-danger btn-xs btn-bulk-action" data-action="cancelled" style="font-weight: 600; padding: 4px 10px;">
                                    <i class="fa fa-ban"></i> Cancel Selected
                                </button>
                            </div>
                        </div>

                        <!-- ── Modernized Orders Table ── -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-orders no-mtop" id="orders-main-table">
                                <thead>
                                    <tr>
                                        <th style="width: 35px; text-align: center;">
                                            <input type="checkbox" id="select-all-orders" title="Select All Visible">
                                        </th>
                                        <th style="width: 75px;">Order #</th>
                                        <th style="min-width: 170px;">Customer & Contact</th>
                                        <th style="width: 100px;">Channel</th>
                                        <th style="width: 110px;">Reference ID</th>
                                        <th style="width: 125px;">Amount & Items</th>
                                        <th style="width: 120px; text-align: center;">Status</th>
                                        <th style="min-width: 130px;">Courier / Dispatch</th>
                                        <th style="width: 130px;">Date & Time</th>
                                        <th class="text-center" style="width: 120px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="orders-table-body">
                                    <?php if (empty($orders)): ?>
                                        <tr class="no-orders-row">
                                            <td colspan="10" class="text-center text-muted" style="padding: 30px;">
                                                <i class="fa fa-shopping-cart fa-3x" style="color: #cbd5e1; display: block; margin-bottom: 10px;"></i>
                                                কোনো অর্ডার খুঁজে পাওয়া যায়নি।
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($orders as $order): ?>
                                            <?php 
                                                $ch = strtolower($order['channel'] ?? '');
                                                $st = strtolower($order['status'] ?? 'pending');
                                                $raw_risk = $order['fraud_risk_level'] ?? '';
                                                $risk_norm = strtoupper(str_replace('_', ' ', $raw_risk));
                                                $is_high_risk = ($risk_norm === 'HIGH RISK' || $risk_norm === 'RED');
                                                $clean_phone = preg_replace('/[^0-9]/', '', (string)$order['customer_phone']);
                                                if (strpos($clean_phone, '88') !== 0 && strlen($clean_phone) === 11) {
                                                    $clean_phone = '88' . $clean_phone;
                                                }
                                            ?>
                                            <tr class="order-table-row" 
                                                id="order-row-<?= $order['id'] ?>"
                                                data-id="<?= $order['id'] ?>"
                                                data-channel="<?= e($ch) ?>"
                                                data-status="<?= e($st) ?>"
                                                data-courier="<?= !empty($order['consignment_id']) ? 'booked' : 'unbooked' ?>"
                                                data-risk="<?= $is_high_risk ? 'high' : 'normal' ?>"
                                                data-search-text="<?= htmlspecialchars(strtolower($order['id'] . ' ' . $order['customer_name'] . ' ' . $order['customer_phone'] . ' ' . $order['channel_ref_id'] . ' ' . $order['channel']), ENT_QUOTES) ?>">
                                                
                                                <!-- Checkbox -->
                                                <td style="text-align: center;">
                                                    <input type="checkbox" class="order-chk" value="<?= $order['id'] ?>">
                                                </td>

                                                <!-- Order ID -->
                                                <td>
                                                    <strong style="font-size: 13px; color: #1e293b;">#<?= $order['id'] ?></strong>
                                                </td>

                                                <!-- Customer & Contact -->
                                                <td>
                                                    <strong style="color: #0f172a; font-size: 13px; display: block;"><?= e($order['customer_name']) ?></strong>
                                                    <div style="display: flex; align-items: center; gap: 5px; margin-top: 3px; flex-wrap: wrap;">
                                                        <?php if (!empty($order['customer_phone'])): ?>
                                                            <a href="tel:<?= e($order['customer_phone']) ?>" class="text-muted" style="font-size: 11px; text-decoration: none;" title="Call Customer">
                                                                <i class="fa fa-phone text-muted" style="font-size: 10px;"></i> <?= e($order['customer_phone']) ?>
                                                            </a>
                                                            <?php if (!empty($clean_phone)): ?>
                                                                <a href="https://wa.me/<?= e($clean_phone) ?>" target="_blank" class="btn-whatsapp-quick" title="WhatsApp এ মেসেজ পাঠান">
                                                                    <i class="fa fa-whatsapp"></i> Chat
                                                                </a>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <small class="text-muted">No Phone</small>
                                                        <?php endif; ?>
                                                    </div>

                                                    <!-- Fraud Risk Badge -->
                                                    <?php if (!empty($order['fraud_risk_level'])): ?>
                                                        <div>
                                                            <?php
                                                            if ($is_high_risk) {
                                                                $risk_lbl = 'High Risk';
                                                                $badge_style = 'background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5;';
                                                                $risk_icon = 'fa-shield text-danger';
                                                            } elseif ($risk_norm === 'MEDIUM RISK' || $risk_norm === 'YELLOW' || $risk_norm === 'ORANGE') {
                                                                $risk_lbl = 'Medium Risk';
                                                                $badge_style = 'background: #fef3c7; color: #92400e; border: 1px solid #fde68a;';
                                                                $risk_icon = 'fa-exclamation-circle text-warning';
                                                            } else {
                                                                $risk_lbl = 'Safe';
                                                                $badge_style = 'background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0;';
                                                                $risk_icon = 'fa-check-circle text-success';
                                                            }
                                                            ?>
                                                            <span class="fraud-badge-pill" style="<?= $badge_style ?>" title="BDCourier Success Ratio: <?= e($order['fraud_success_ratio']) ?>%">
                                                                <i class="fa <?= $risk_icon ?>"></i> <?= e($risk_lbl) ?> (<?= e(round((float)$order['fraud_success_ratio'])) ?>%)
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>

                                                <!-- Channel -->
                                                <td>
                                                    <?php if ($ch === 'woo' || $ch === 'woocommerce'): ?>
                                                        <span class="pill-channel-woo"><i class="fa fa-shopping-cart"></i> WOO</span>
                                                    <?php elseif ($ch === 'pos'): ?>
                                                        <span class="pill-channel-pos"><i class="fa fa-desktop"></i> POS</span>
                                                    <?php elseif ($ch === 'pos_online'): ?>
                                                        <span class="pill-channel-pos-online"><i class="fa fa-truck"></i> POS ONLINE</span>
                                                    <?php elseif ($ch === 'whatsapp'): ?>
                                                        <span class="pill-channel-whatsapp"><i class="fa fa-whatsapp"></i> WHATSAPP</span>
                                                    <?php else: ?>
                                                        <span class="pill-channel-manual"><i class="fa fa-phone"></i> <?= strtoupper(e($ch ?: 'MANUAL')) ?></span>
                                                    <?php endif; ?>
                                                </td>

                                                <!-- Reference ID -->
                                                <td>
                                                    <span class="label" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; font-family: monospace; font-size: 11px; font-weight: 600;">
                                                        <?= e($order['channel_ref_id'] ?: '-') ?>
                                                    </span>
                                                </td>

                                                <!-- Total Amount & Items -->
                                                <td>
                                                    <div style="font-weight: 700; color: #0f172a; font-size: 13px;">
                                                        <?= salesos_format_number($order['total']) ?> <span style="font-size: 10px; font-weight: normal; color: #64748b;">BDT</span>
                                                    </div>
                                                    <div style="margin-top: 3px;">
                                                        <span class="badge" style="background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; font-size: 10px; font-weight: 600;">
                                                            <?= (int)($order['item_count'] ?? 1) ?> item<?= ($order['item_count'] ?? 1) > 1 ? 's' : '' ?>
                                                        </span>
                                                        <?php if (!empty($order['payment_method'])): ?>
                                                            <span class="text-muted" style="font-size: 10px; margin-left: 2px; text-transform: uppercase;">
                                                                <?= e($order['payment_method']) ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>

                                                <!-- Status (Interactive Dropdown) -->
                                                <td style="text-align: center;">
                                                    <?php 
                                                        $pill_class = 'pill-status-pending';
                                                        $status_icon = 'fa-clock-o';
                                                        if ($st === 'confirmed') { $pill_class = 'pill-status-confirmed'; $status_icon = 'fa-check'; }
                                                        elseif ($st === 'processing') { $pill_class = 'pill-status-processing'; $status_icon = 'fa-cube'; }
                                                        elseif ($st === 'delivered') { $pill_class = 'pill-status-delivered'; $status_icon = 'fa-truck'; }
                                                        elseif ($st === 'cancelled') { $pill_class = 'pill-status-cancelled'; $status_icon = 'fa-ban'; }
                                                    ?>
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-default btn-xs dropdown-toggle pill-status <?= $pill_class ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="border-width: 1px;">
                                                            <i class="fa <?= $status_icon ?>"></i> <?= strtoupper(e($st)) ?> <i class="fa fa-caret-down" style="font-size: 9px; margin-left: 3px;"></i>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-right" style="font-size: 12px; min-width: 130px;">
                                                            <li><a href="#" class="btn-quick-status-change" data-id="<?= $order['id'] ?>" data-status="pending"><i class="fa fa-clock-o text-warning"></i> Pending</a></li>
                                                            <li><a href="#" class="btn-quick-status-change" data-id="<?= $order['id'] ?>" data-status="confirmed"><i class="fa fa-check text-success"></i> Confirmed</a></li>
                                                            <li><a href="#" class="btn-quick-status-change" data-id="<?= $order['id'] ?>" data-status="processing"><i class="fa fa-cube text-primary"></i> Processing</a></li>
                                                            <li><a href="#" class="btn-quick-status-change" data-id="<?= $order['id'] ?>" data-status="delivered"><i class="fa fa-truck text-purple"></i> Delivered</a></li>
                                                            <li role="separator" class="divider"></li>
                                                            <li><a href="#" class="btn-quick-status-change text-danger" data-id="<?= $order['id'] ?>" data-status="cancelled"><i class="fa fa-ban text-danger"></i> Cancelled</a></li>
                                                        </ul>
                                                    </div>
                                                </td>

                                                <!-- Courier / Dispatch -->
                                                <td>
                                                    <?php if ($ch === 'pos'): ?>
                                                        <span class="label" style="background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; font-size: 11px; padding: 4px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;">
                                                            <i class="fa fa-shopping-bag"></i> In-store / Counter
                                                        </span>
                                                    <?php elseif (!empty($order['consignment_id'])): ?>
                                                        <span class="label" style="background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; font-size: 11px; font-weight: 700; padding: 4px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;" title="Courier: <?= e($order['courier_provider'] ?? 'Courier') ?>">
                                                            <i class="fa fa-truck text-primary"></i> <?= e($order['courier_provider'] ? strtoupper($order['courier_provider']) : 'Booked') ?>: <?= e($order['courier_tracking_id'] ?: 'Pending') ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <button class="btn btn-success btn-xs open-courier-selection-btn" 
                                                                data-order-id="<?= $order['id'] ?>" 
                                                                data-customer-name="<?= e($order['customer_name']) ?>"
                                                                data-customer-phone="<?= e($order['customer_phone']) ?>"
                                                                data-customer-address="<?= e($order['customer_address'] ?? '') ?>"
                                                                data-order-total="<?= (float) $order['total'] ?>"
                                                                data-collectable-amount="<?= isset($order['collectable_amount']) ? (float) $order['collectable_amount'] : (float) $order['total'] ?>"
                                                                data-order-channel="<?= e($order['channel']) ?>"
                                                                data-order-note="<?= e($order['order_note'] ?? '') ?>"
                                                                title="Send with Courier"
                                                                style="font-weight: 600; padding: 3px 8px; border-radius: 4px;">
                                                            <i class="fa fa-truck"></i> Send Courier
                                                        </button>
                                                    <?php endif; ?>
                                                </td>

                                                <!-- Date & Time -->
                                                <td>
                                                    <?php 
                                                        $ts = strtotime($order['created_at'] ?: $order['order_date']);
                                                        $d_str = $ts ? date('d M Y', $ts) : '-';
                                                        $t_str = $ts ? date('h:i A', $ts) : '';
                                                    ?>
                                                    <div style="font-weight: 600; color: #334155; font-size: 12px;"><?= $d_str ?></div>
                                                    <small class="text-muted" style="font-size: 10px;"><?= $t_str ?></small>
                                                </td>

                                                <!-- Actions -->
                                                <td class="text-center" style="white-space: nowrap;">
                                                    <div style="display: inline-flex; gap: 4px; align-items: center;">
                                                        <button type="button" class="btn btn-default btn-xs view-order-details-btn" data-id="<?= $order['id'] ?>" title="View Full Details" style="padding: 4px 8px; border-color: #cbd5e1;">
                                                            <i class="fa fa-eye text-primary"></i>
                                                        </button>
                                                        <a href="<?= admin_url('salesos/print_invoice/' . $order['id']) ?>" target="_blank" class="btn btn-default btn-xs" title="Print A4 Invoice" style="padding: 4px 8px; border-color: #cbd5e1;">
                                                            <i class="fa fa-print"></i>
                                                        </a>
                                                        <a href="<?= admin_url('salesos/print_label/' . $order['id']) ?>" target="_blank" class="btn btn-default btn-xs" title="Print 55mm Shipping Label" style="padding: 4px 8px; border-color: #cbd5e1;">
                                                            <i class="fa fa-tag text-warning"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- ── Modern Pagination & Showing Counter ── -->
                        <div class="row mtop15" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap;">
                            <div class="col-sm-6 text-muted" style="font-size: 12px;">
                                মোট <strong id="orders-visible-count"><?= count($orders) ?></strong> টি অর্ডার প্রদর্শিত হচ্ছে
                            </div>
                            <div class="col-sm-6 text-right">
                                <?= $pagination ?>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Premium Order Details Modal -->
<div class="modal fade" id="order_details_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title bold text-primary"><i class="fa fa-shopping-cart"></i> Order Details - #<span id="modal-order-id"></span></h4>
            </div>
            <div class="modal-body" style="background: #f8fafc;">
                
                <!-- Info Panels -->
                <div class="row">
                    <!-- Customer Information -->
                    <div class="col-md-6 mbot15">
                        <div class="panel panel-default" style="border: 1px solid #e2e8f0; border-radius: 4px; box-shadow: none; margin-bottom: 0;">
                            <div class="panel-body">
                                <h5 class="bold text-muted no-margin mbot15"><i class="fa fa-user"></i> Customer & Shipping Details</h5>
                                <p class="mbot8"><strong>Name:</strong> <span id="modal-cust-name">-</span></p>
                                <p class="mbot8"><strong>Phone:</strong> <span id="modal-cust-phone">-</span></p>
                                <p class="mbot8"><strong>Email:</strong> <span id="modal-cust-email">-</span></p>
                                <p class="mbot0"><strong>Address:</strong> <span id="modal-cust-address">-</span></p>
                            </div>
                        </div>
                    </div>
                    <!-- Order Meta Information -->
                    <div class="col-md-6 mbot15">
                        <div class="panel panel-default" style="border: 1px solid #e2e8f0; border-radius: 4px; box-shadow: none; margin-bottom: 0;">
                            <div class="panel-body">
                                <h5 class="bold text-muted no-margin mbot15"><i class="fa fa-info-circle"></i> Order Summary</h5>
                                <p class="mbot8"><strong>Channel:</strong> <span id="modal-order-channel" class="label label-default">-</span></p>
                                <p class="mbot8"><strong>Reference ID:</strong> <span id="modal-order-ref">-</span></p>
                                <p class="mbot8"><strong>Payment Method:</strong> <span id="modal-order-payment">-</span></p>
                                <p class="mbot0"><strong>Order Date:</strong> <span id="modal-order-date">-</span></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Courier Tracking Section (Dynamic) -->
                <div class="row mbot15" id="modal-courier-tracking-row" style="display: none;">
                    <div class="col-md-12">
                        <div class="panel panel-default" style="border: 1px solid #93c5fd; border-radius: 4px; box-shadow: none; background: #eff6ff; margin-bottom: 0;">
                            <div class="panel-body">
                                <div class="row">
                                    <div class="col-sm-3">
                                        <h5 class="bold text-primary mbot10" style="margin-top: 0;"><i class="fa fa-truck"></i> Courier Partner</h5>
                                        <p class="mbot0"><strong id="modal-courier-provider" class="text-uppercase">-</strong></p>
                                        <small class="text-muted" id="modal-courier-account-name">-</small>
                                    </div>
                                    <div class="col-sm-3">
                                        <h5 class="bold text-primary mbot10" style="margin-top: 0;"><i class="fa fa-barcode"></i> Tracking ID</h5>
                                        <p class="mbot0"><strong id="modal-courier-tracking-id">-</strong></p>
                                    </div>
                                    <div class="col-sm-3">
                                        <h5 class="bold text-primary mbot10" style="margin-top: 0;"><i class="fa fa-info-circle"></i> Delivery Status</h5>
                                        <span id="modal-courier-status" class="label label-warning text-uppercase" style="font-size: 11px; padding: 4px 8px; display: inline-block; margin-top: 2px;">-</span>
                                        <small class="display-block text-muted mtop5" style="font-size: 10px;">Synced: <span id="modal-courier-last-synced">-</span></small>
                                    </div>
                                    <div class="col-sm-3 text-right" style="margin-top: 10px;">
                                        <button type="button" class="btn btn-primary btn-sm btn-block" id="modal-refresh-status-btn" data-consignment-id="">
                                            <i class="fa fa-refresh"></i> Refresh Status
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BDCourier Fraud Check Section (Dynamic) -->
                <div class="row mbot15" id="modal-fraudcheck-row" style="display: none;">
                    <div class="col-md-12">
                        <div class="panel panel-default" id="modal-fraud-panel" style="border: 1px solid #cbd5e1; border-radius: 4px; box-shadow: none; margin-bottom: 0;">
                            <div class="panel-heading" style="background: #f8fafc; border-bottom: 1px solid #cbd5e1; padding: 10px 15px;">
                                <div class="row">
                                    <div class="col-xs-6">
                                        <h4 class="no-margin bold text-primary" style="font-size: 14px; margin-top: 4px !important;">
                                            <i class="fa fa-shield"></i> BDCourier Fraud Risk Report
                                        </h4>
                                    </div>
                                    <div class="col-xs-6 text-right">
                                        <button type="button" class="btn btn-default btn-xs" id="modal-fraud-recheck-btn" data-phone="">
                                            <i class="fa fa-refresh"></i> Re-check Fraud History
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="panel-body" style="padding: 15px; background: #f8fafc;">
                                <div class="row">
                                    <!-- Left Column: Circular Rating & Verdict -->
                                    <div class="col-md-4 text-center" style="border-right: 1px solid #cbd5e1; padding-right: 20px;">
                                        
                                        <!-- Circular Progress Rating -->
                                        <div class="rating-circle" id="modal-fraud-rating-circle" style="--percent: 0;">
                                            <div class="rating-circle-text">
                                                <span id="modal-fraud-rating-value" style="font-size: 16px; font-weight: bold; color: #0f172a;">0%</span>
                                                <small style="display: block; font-size: 9px; color: #64748b; margin-top: 2px;">গড় রেটিং</small>
                                            </div>
                                        </div>

                                        <p class="bold text-danger mbot15" style="font-size: 12px; color: #ef4444 !important;">
                                            নম্বর: <span id="modal-fraud-phone-label">-</span>
                                        </p>

                                        <!-- Verdict Card Alert -->
                                        <div class="alert text-center" id="modal-fraud-verdict-box" style="border-radius: 6px; padding: 12px; margin-bottom: 0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                                            <h4 class="bold no-margin" id="modal-fraud-verdict-label" style="font-size: 14px; margin-bottom: 5px !important;">-</h4>
                                            <p class="no-margin text-muted" id="modal-fraud-verdict-action" style="font-size: 10px; font-weight: 500;"></p>
                                        </div>
                                    </div>

                                    <!-- Right Column: Stats Cards & Table Grid -->
                                    <div class="col-md-8" style="padding-left: 20px;">
                                        
                                        <!-- Inline 4 Stats Cards -->
                                        <div class="row mbot15">
                                            <div class="col-xs-3" style="padding: 0 5px;">
                                                <div class="text-center" style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 3px; background: #fff;">
                                                    <h3 class="no-margin bold text-primary" id="stat-total" style="font-size: 15px;">0</h3>
                                                    <small class="text-muted" style="font-size: 9px; display: block; margin-top: 2px;">অর্ডার</small>
                                                </div>
                                            </div>
                                            <div class="col-xs-3" style="padding: 0 5px;">
                                                <div class="text-center" style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 3px; background: #fff;">
                                                    <h3 class="no-margin bold text-success" id="stat-success" style="font-size: 15px;">0</h3>
                                                    <small class="text-muted" style="font-size: 9px; display: block; margin-top: 2px;">ডেলিভারি</small>
                                                </div>
                                            </div>
                                            <div class="col-xs-3" style="padding: 0 5px;">
                                                <div class="text-center" style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 3px; background: #fff;">
                                                    <h3 class="no-margin bold text-danger" id="stat-cancelled" style="font-size: 15px;">0</h3>
                                                    <small class="text-muted" style="font-size: 9px; display: block; margin-top: 2px;">বাতিল</small>
                                                </div>
                                            </div>
                                            <div class="col-xs-3" style="padding: 0 5px;">
                                                <div class="text-center" id="stat-ratio-box" style="border: 1px solid #86efac; border-radius: 6px; padding: 8px 3px; background: #f0fdf4;">
                                                    <h3 class="no-margin bold text-success" id="stat-ratio" style="font-size: 15px;">0%</h3>
                                                    <small class="text-muted" style="font-size: 9px; display: block; margin-top: 2px;">ডেলিভারি হার</small>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Courier breakdown grid -->
                                        <div class="table-responsive" style="border: 1px solid #e2e8f0; border-radius: 6px; background: #fff;">
                                            <table class="table table-bordered table-striped no-margin" style="font-size: 11px;">
                                                <thead>
                                                    <tr style="background: #f8fafc;">
                                                        <th style="padding: 6px 8px;">কুরিয়ার</th>
                                                        <th class="text-center" style="padding: 6px 8px; width: 60px;">অর্ডার</th>
                                                        <th class="text-center" style="padding: 6px 8px; width: 60px;">ডেলিভারি</th>
                                                        <th class="text-center" style="padding: 6px 8px; width: 60px;">বাতিল</th>
                                                        <th class="text-center" style="padding: 6px 8px; width: 90px;">ডেলিভারি হার</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="modal-fraud-stats-tbody">
                                                </tbody>
                                            </table>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Note -->
                <div class="row mbot15" id="modal-note-row" style="display: none;">
                    <div class="col-md-12">
                        <div class="alert alert-info" style="border: 1px solid #bde0fe; border-radius: 4px; background: #ebf8ff; color: #1e40af; padding: 10px 15px; margin-bottom: 0;">
                            <strong><i class="fa fa-comment"></i> Customer Note:</strong>
                            <p class="no-margin mtop5 text-muted" id="modal-order-note" style="white-space: pre-line;"></p>
                        </div>
                    </div>
                </div>

                <!-- Products Table -->
                <div class="panel panel-default" style="border: 1px solid #e2e8f0; border-radius: 4px; box-shadow: none; margin-bottom: 15px;">
                    <div class="panel-body no-padding">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped no-margin">
                                <thead>
                                    <tr style="background: #f1f5f9; border-bottom: 1px solid #cbd5e1;">
                                        <th>Product Name</th>
                                        <th>SKU</th>
                                        <th class="text-center" style="width: 10%;">Qty</th>
                                        <th class="text-right" style="width: 20%;">Unit Price</th>
                                        <th class="text-right" style="width: 20%;">Total</th>
                                    </tr>
                                </thead>
                                <tbody id="modal-items-tbody">
                                    <!-- Dynamic Rows -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Price Breakdown -->
                <div class="row">
                    <div class="col-md-6 col-md-offset-6 text-right">
                        <p class="mbot5"><strong>Subtotal:</strong> <span id="modal-subtotal">0.00</span> BDT</p>
                        <p class="mbot5"><strong>Shipping Charge:</strong> <span id="modal-shipping">0.00</span> BDT</p>
                        <hr style="margin: 8px 0; border-color: #cbd5e1;" />
                        <h4 class="no-margin bold text-primary"><strong>Total:</strong> <span id="modal-total">0.00</span> BDT</h4>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <a href="#" id="modal-print-invoice-btn" target="_blank" class="btn btn-primary"><i class="fa fa-print"></i> Print A4 Invoice</a>
                <a href="#" id="modal-print-label-btn" target="_blank" class="btn btn-warning"><i class="fa fa-tag"></i> Print Label (55mm)</a>
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<script>
window.salesos_remove_decimals_on_zero = '<?= get_option('remove_decimals_on_zero') ?: '0' ?>';
function salesos_format_number(number, decimals) {
    if (decimals === undefined) decimals = 2;
    if (window.salesos_remove_decimals_on_zero == '1') {
        if (Number(number) === Math.floor(Number(number))) {
            decimals = 0;
        }
    }
    return parseFloat(number).toFixed(decimals);
}

document.addEventListener('DOMContentLoaded', function() {
    // ── Instant Search & Filter Logic ──
    window.filterRisk = '';
    window.filterStatusSpecial = '';

    function updateBulkBar() {
        var checkedBoxes = $('.order-chk:checked');
        var count = checkedBoxes.length;
        $('#bulk-selected-count').text(count);
        if (count > 0) {
            $('#bulk-actions-bar').slideDown(150);
        } else {
            $('#bulk-actions-bar').slideUp(150);
        }
    }

    function applyOrderFilters(reloadServer) {
        var search = $('#order-search').val().trim();
        var channel = $('#filter-channel').val();
        var status = $('#filter-status').val();
        var courier = $('#filter-courier').val();
        var date_range = $('#filter-date').val();

        if (reloadServer) {
            var params = new URLSearchParams();
            if (search) params.set('search', search);
            if (channel) params.set('channel', channel);
            if (status) params.set('status', status);
            if (courier) params.set('courier_status', courier);
            if (date_range) params.set('date_range', date_range);
            window.location.href = admin_url + 'salesos/orders' + (params.toString() ? '?' + params.toString() : '');
            return;
        }

        var q = search.toLowerCase();
        var ch = channel ? channel.toLowerCase() : '';
        var st = status ? status.toLowerCase() : '';
        var cr = courier ? courier.toLowerCase() : '';
        var rk = window.filterRisk || '';
        var specialSt = window.filterStatusSpecial || '';

        var visibleCount = 0;
        $('.order-table-row').each(function() {
            var row = $(this);
            var rowSearch = (row.data('search-text') || '').toString().toLowerCase();
            var rowCh = (row.data('channel') || '').toString().toLowerCase();
            var rowSt = (row.data('status') || '').toString().toLowerCase();
            var rowCr = (row.data('courier') || '').toString().toLowerCase();
            var rowRk = (row.data('risk') || '').toString().toLowerCase();

            var matchSearch = !q || rowSearch.indexOf(q) !== -1;
            var matchCh = !ch || (ch === 'woo' ? (rowCh === 'woo' || rowCh === 'woocommerce') : (rowCh === ch));
            var matchSt = true;
            if (specialSt === 'ready') {
                matchSt = (rowSt === 'confirmed' || rowSt === 'processing');
            } else if (st) {
                matchSt = (rowSt === st);
            }
            var matchCr = !cr || rowCr === cr;
            var matchRk = !rk || rowRk === rk;

            if (matchSearch && matchCh && matchSt && matchCr && matchRk) {
                row.show();
                visibleCount++;
            } else {
                row.hide();
            }
        });

        $('#orders-visible-count').text(visibleCount);
        if (visibleCount === 0) {
            if ($('#no-results-client-row').length === 0) {
                $('#orders-table-body').append('<tr id="no-results-client-row"><td colspan="10" class="text-center text-muted" style="padding: 25px;"><i class="fa fa-search fa-2x" style="color: #cbd5e1; display: block; margin-bottom: 8px;"></i>নির্দিষ্ট ফিল্টারে কোনো অর্ডার পাওয়া যায়নি।</td></tr>');
            }
        } else {
            $('#no-results-client-row').remove();
        }

        // Reset check-all state
        $('#select-all-orders').prop('checked', false);
        updateBulkBar();
    }

    // Bind Filter inputs
    $('#order-search').on('input keyup', function(e) {
        if (e.which === 13) {
            applyOrderFilters(true); // Enter triggers full DB search
        } else {
            applyOrderFilters(false);
        }
    });

    $('#filter-channel').on('change', function() {
        applyOrderFilters(false);
    });

    $('#filter-status').on('change', function() {
        window.filterStatusSpecial = '';
        $('.kpi-metric-card').removeClass('active-kpi');
        applyOrderFilters(false);
    });

    $('#filter-courier').on('change', function() {
        $('.kpi-metric-card').removeClass('active-kpi');
        applyOrderFilters(false);
    });

    $('#filter-date').on('change', function() {
        applyOrderFilters(true); // Date requires backend query
    });

    $('#btn-reset-filters').on('click', function() {
        window.location.href = admin_url + 'salesos/orders';
    });

    // KPI Metric Cards click handling
    $('#kpi-card-all').on('click', function() {
        $('.kpi-metric-card').removeClass('active-kpi');
        $(this).addClass('active-kpi');
        $('#order-search').val('');
        $('#filter-channel').val('');
        $('#filter-status').val('');
        $('#filter-courier').val('');
        window.filterRisk = '';
        window.filterStatusSpecial = '';
        applyOrderFilters(false);
    });

    $('#kpi-card-pending').on('click', function() {
        $('.kpi-metric-card').removeClass('active-kpi');
        $(this).addClass('active-kpi');
        $('#filter-status').val('pending');
        window.filterRisk = '';
        window.filterStatusSpecial = '';
        applyOrderFilters(false);
    });

    $('#kpi-card-ready').on('click', function() {
        $('.kpi-metric-card').removeClass('active-kpi');
        $(this).addClass('active-kpi');
        $('#filter-status').val('');
        window.filterRisk = '';
        window.filterStatusSpecial = 'ready';
        applyOrderFilters(false);
    });

    $('#kpi-card-courier').on('click', function() {
        $('.kpi-metric-card').removeClass('active-kpi');
        $(this).addClass('active-kpi');
        $('#filter-courier').val('booked');
        window.filterRisk = '';
        window.filterStatusSpecial = '';
        applyOrderFilters(false);
    });

    $('#kpi-card-risk').on('click', function() {
        if (window.filterRisk === 'high') {
            window.filterRisk = '';
            $(this).removeClass('active-kpi');
        } else {
            $('.kpi-metric-card').removeClass('active-kpi');
            $(this).addClass('active-kpi');
            window.filterRisk = 'high';
        }
        applyOrderFilters(false);
    });

    // ── Checkbox Multi-Select & Bulk Actions ──
    $('#select-all-orders').on('change', function() {
        var isChecked = $(this).is(':checked');
        $('.order-table-row:visible .order-chk').prop('checked', isChecked);
        updateBulkBar();
    });

    $(document).on('change', '.order-chk', function() {
        updateBulkBar();
        var totalVisible = $('.order-table-row:visible .order-chk').length;
        var totalChecked = $('.order-table-row:visible .order-chk:checked').length;
        $('#select-all-orders').prop('checked', totalVisible > 0 && totalVisible === totalChecked);
    });

    $('.btn-bulk-action').on('click', function() {
        var action = $(this).data('action');
        var orderIds = [];
        $('.order-chk:checked').each(function() {
            orderIds.push($(this).val());
        });

        if (orderIds.length === 0) {
            alert_float('warning', 'কোনো অর্ডার সিলেক্ট করা হয়নি!');
            return;
        }

        var actionLabels = {
            'confirmed': 'Mark as Confirmed',
            'processing': 'Mark as Processing',
            'cancelled': 'Cancel Orders'
        };
        var lbl = actionLabels[action] || action;

        if (!confirm('আপনি কি নিশ্চিত যে নির্বাচিত ' + orderIds.length + ' টি অর্ডার ' + lbl + ' করতে চান?')) {
            return;
        }

        var postData = {
            action: action,
            order_ids: orderIds
        };
        if (typeof csrfData !== 'undefined') {
            postData[csrfData.token_name] = csrfData.hash;
        }

        var btn = $(this);
        var origHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');

        $.ajax({
            url: admin_url + 'salesos/bulk_action_ajax',
            type: 'POST',
            data: postData,
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    alert_float('success', res.message || 'Orders updated successfully!');
                    setTimeout(function() {
                        window.location.reload();
                    }, 500);
                } else {
                    alert_float('danger', res.error || 'Failed to update orders.');
                    btn.prop('disabled', false).html(origHtml);
                }
            },
            error: function() {
                alert_float('danger', 'Network error while executing bulk action.');
                btn.prop('disabled', false).html(origHtml);
            }
        });
    });

    // ── Quick Status Dropdown Change ──
    $(document).on('click', '.btn-quick-status-change', function(e) {
        e.preventDefault();
        var orderId = $(this).data('id');
        var newStatus = $(this).data('status');
        if (!orderId || !newStatus) return;

        var postData = {
            order_id: orderId,
            status: newStatus
        };
        if (typeof csrfData !== 'undefined') {
            postData[csrfData.token_name] = csrfData.hash;
        }

        var row = $('#order-row-' + orderId);
        var statusBtn = row.find('.pill-status');
        var origBtnHtml = statusBtn.html();
        statusBtn.html('<i class="fa fa-spinner fa-spin"></i>');

        $.ajax({
            url: admin_url + 'salesos/update_order_status_ajax',
            type: 'POST',
            data: postData,
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    alert_float('success', res.message || 'Order status updated!');
                    row.attr('data-status', newStatus);
                    row.data('status', newStatus);

                    statusBtn.removeClass('pill-status-pending pill-status-confirmed pill-status-processing pill-status-delivered pill-status-cancelled');
                    statusBtn.addClass('pill-status-' + newStatus);

                    var icon = 'fa-clock-o';
                    if (newStatus === 'confirmed') icon = 'fa-check';
                    else if (newStatus === 'processing') icon = 'fa-cube';
                    else if (newStatus === 'delivered') icon = 'fa-truck';
                    else if (newStatus === 'cancelled') icon = 'fa-ban';

                    statusBtn.html('<i class="fa ' + icon + '"></i> ' + newStatus.toUpperCase() + ' <i class="fa fa-caret-down" style="font-size: 9px; margin-left: 3px;"></i>');
                } else {
                    alert_float('danger', res.error || 'Failed to update order status.');
                    statusBtn.html(origBtnHtml);
                }
            },
            error: function() {
                alert_float('danger', 'Network error while updating status.');
                statusBtn.html(origBtnHtml);
            }
        });
    });
    $('.view-order-details-btn').on('click', function() {
        var orderId = $(this).data('id');
        
        // Open empty modal first
        $('#modal-order-id').text(orderId);
        $('#modal-print-invoice-btn').attr('href', admin_url + 'salesos/print_invoice/' + orderId);
        $('#modal-print-label-btn').attr('href', admin_url + 'salesos/print_label/' + orderId);
        $('#modal-items-tbody').html('<tr><td colspan="5" class="text-center text-muted"><i class="fa fa-spinner fa-spin"></i> Loading order details...</td></tr>');
        $('#order_details_modal').modal('show');

        // Fetch via AJAX
        $.getJSON(admin_url + 'salesos/get_order_details_ajax/' + orderId, function(res) {
            if (res.success) {
                var o = res.order;
                
                // Populate Customer Details
                $('#modal-cust-name').text(o.customer_name || 'Guest Customer');
                $('#modal-cust-phone').text(o.customer_phone || '-');
                $('#modal-cust-email').text(o.customer_email || '-');
                $('#modal-cust-address').text(o.customer_address || '-');
                
                // Populate Order Meta
                $('#modal-order-channel').text(o.channel ? o.channel.toUpperCase() : '-');
                $('#modal-order-ref').text(o.channel_ref_id || '-');
                $('#modal-order-payment').text(o.payment_method || '-');
                $('#modal-order-date').text(o.order_date || '-');

                // Populate Courier Shipment Details
                if (o.consignment_id) {
                    $('#modal-courier-provider').text(o.courier_provider || '-');
                    $('#modal-courier-account-name').text(o.courier_account_name || '-');
                    $('#modal-courier-tracking-id').text(o.courier_tracking_id || 'Pending');
                    $('#modal-courier-status').text(o.courier_status || 'Submitted');
                    $('#modal-courier-last-synced').text(o.courier_last_synced_at || 'Never');
                    $('#modal-refresh-status-btn').data('consignment-id', o.consignment_id);
                    $('#modal-courier-tracking-row').show();
                } else {
                    $('#modal-courier-tracking-row').hide();
                }

                // Populate Fraud Check report card
                populateFraudData(o.fraud_data, o.customer_phone);

                // Customer Note
                if (o.order_note && o.order_note.trim() !== '') {
                    $('#modal-order-note').text(o.order_note);
                    $('#modal-note-row').show();
                } else {
                    $('#modal-note-row').hide();
                }

                // Populate Items
                var itemsHtml = '';
                var subtotalCalc = 0;
                
                if (res.items && res.items.length > 0) {
                    res.items.forEach(function(item) {
                        var itemTotal = parseFloat(item.qty) * parseFloat(item.unit_price);
                        subtotalCalc += itemTotal;
                        
                        itemsHtml += '<tr>';
                        itemsHtml += '<td>' + (item.name || 'Product Item') + '</td>';
                        itemsHtml += '<td><code>' + (item.sku || '-') + '</code></td>';
                        itemsHtml += '<td class="text-center">' + parseFloat(item.qty) + '</td>';
                        itemsHtml += '<td class="text-right">' + salesos_format_number(item.unit_price, 2) + ' BDT</td>';
                        itemsHtml += '<td class="text-right"><strong>' + salesos_format_number(itemTotal, 2) + ' BDT</strong></td>';
                        itemsHtml += '</tr>';
                    });
                } else {
                    itemsHtml = '<tr><td colspan="5" class="text-center text-muted">No products registered in this order.</td></tr>';
                }
                
                $('#modal-items-tbody').html(itemsHtml);

                // Price Breakdown
                var subtotal = parseFloat(o.subtotal) > 0 ? parseFloat(o.subtotal) : subtotalCalc;
                var shipping = parseFloat(o.shipping_charge);
                var total = parseFloat(o.total);

                $('#modal-subtotal').text(salesos_format_number(subtotal, 2));
                $('#modal-shipping').text(salesos_format_number(shipping, 2));
                $('#modal-total').text(salesos_format_number(total, 2));

            } else {
                $('#modal-items-tbody').html('<tr><td colspan="5" class="text-center text-danger"><i class="fa fa-exclamation-triangle"></i> ' + (res.error || 'Failed to load details.') + '</td></tr>');
            }
        });
    });

    // Courier selection click to open inline booking modal
    $('.open-courier-selection-btn').on('click', function() {
        var orderId = $(this).data('order-id');
        var custName = $(this).data('customer-name');
        var custPhone = $(this).data('customer-phone');
        var custAddress = $(this).data('customer-address');
        var orderTotal = $(this).data('order-total');
        var channel = $(this).data('order-channel');
        var orderNote = $(this).data('order-note');

        // Set form hidden inputs & text labels
        $('#booking-order-id-label').text(orderId);
        $('#booking-salesos-order-id').val(orderId);
        
        $('#booking-rec-name').text(custName || 'Guest Customer');
        $('#booking-rec-phone').text(custPhone || '-');
        $('#booking-rec-address').text(custAddress || '-');

        // Note pre-fill
        $('#booking-notes').val(orderNote || '');

        // COD Amount defaults
        var collectableAmount = $(this).data('collectable-amount');
        if (collectableAmount !== undefined && collectableAmount !== '') {
            $('#booking-cod-amount').val(salesos_format_number(collectableAmount, 2));
        } else if (channel === 'pos') {
            $('#booking-cod-amount').val('0.00');
        } else {
            $('#booking-cod-amount').val(salesos_format_number(orderTotal, 2));
        }

        // Reset state to selection view
        $('#booking-state-form').hide();
        $('#booking-state-selection').show();

        // Open modal
        $('#courier_booking_modal').modal('show');
    });

    // Account Card selection transitions to form state
    $('.select-courier-account-card').on('click', function(e) {
        e.preventDefault();
        var accountId = $(this).data('account-id');
        var provider = $(this).data('provider');
        var name = $(this).data('account-name');

        $('#booking-courier-account-id').val(accountId);
        $('#booking-selected-account-label').text(name + ' (' + provider.toUpperCase() + ')');

        if (provider === 'pathao') {
            $('#booking-pathao-fields').show();
            $('#booking-recipient-city').attr('required', 'required');
            $('#booking-recipient-zone').attr('required', 'required');
            $('#booking-recipient-area').attr('required', 'required');
            fetchModalCities(accountId);
        } else {
            $('#booking-pathao-fields').hide();
            $('#booking-recipient-city').removeAttr('required');
            $('#booking-recipient-zone').removeAttr('required');
            $('#booking-recipient-area').removeAttr('required');
        }

        // Transition views
        $('#booking-state-selection').fadeOut(150, function() {
            $('#booking-state-form').fadeIn(150);
        });
    });

    // Back to selection button
    $('#booking-back-to-selection').on('click', function() {
        $('#booking-state-form').fadeOut(150, function() {
            $('#booking-state-selection').fadeIn(150);
        });
    });

    // Pathao dynamic lookups
    function fetchModalCities(account_id) {
        var citySelect = $('#booking-recipient-city');
        var zoneSelect = $('#booking-recipient-zone');
        var areaSelect = $('#booking-recipient-area');

        citySelect.html('<option value="">Loading Cities...</option>');
        zoneSelect.html('<option value="">Select Zone...</option>').prop('disabled', true);
        areaSelect.html('<option value="">Select Area...</option>').prop('disabled', true);

        $.getJSON(admin_url + 'courier/get_pathao_cities/' + account_id, function(data) {
            var html = '<option value="">Select City...</option>';
            if (Array.isArray(data)) {
                data.forEach(function(c) {
                    html += '<option value="' + c.city_id + '">' + c.city_name + '</option>';
                });
            }
            citySelect.html(html);
        });
    }

    $('#booking-recipient-city').on('change', function() {
        var account_id = $('#booking-courier-account-id').val();
        var city_id = this.value;
        var zoneSelect = $('#booking-recipient-zone');
        var areaSelect = $('#booking-recipient-area');

        zoneSelect.html('<option value="">Loading Zones...</option>').prop('disabled', true);
        areaSelect.html('<option value="">Select Area...</option>').prop('disabled', true);

        if (!city_id) return;

        $.getJSON(admin_url + 'courier/get_pathao_zones/' + account_id + '/' + city_id, function(data) {
            var html = '<option value="">Select Zone...</option>';
            if (Array.isArray(data)) {
                data.forEach(function(z) {
                    html += '<option value="' + z.zone_id + '">' + z.zone_name + '</option>';
                });
            }
            zoneSelect.html(html).prop('disabled', false);
        });
    });

    $('#booking-recipient-zone').on('change', function() {
        var account_id = $('#booking-courier-account-id').val();
        var zone_id = this.value;
        var areaSelect = $('#booking-recipient-area');

        areaSelect.html('<option value="">Loading Areas...</option>').prop('disabled', true);

        if (!zone_id) return;

        $.getJSON(admin_url + 'courier/get_pathao_areas/' + account_id + '/' + zone_id, function(data) {
            var html = '<option value="">Select Area...</option>';
            if (Array.isArray(data)) {
                data.forEach(function(a) {
                    html += '<option value="' + a.area_id + '">' + a.area_name + '</option>';
                });
            }
            areaSelect.html(html).prop('disabled', false);
        });
    });

    // Inline form submit via AJAX
    $('#inline-courier-booking-form').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var submitBtn = form.find('button[type="submit"]');
        var originalBtnHtml = submitBtn.html();

        submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Booking...');

        $.ajax({
            url: admin_url + 'courier/book_ajax',
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    alert_float('success', res.message || 'Parcel booked successfully!');
                    $('#courier_booking_modal').modal('hide');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    alert_float('danger', res.error || 'Failed to book parcel.');
                    submitBtn.prop('disabled', false).html(originalBtnHtml);
                }
            },
            error: function() {
                alert_float('danger', 'An unexpected error occurred during booking.');
                submitBtn.prop('disabled', false).html(originalBtnHtml);
            }
        });
    });

    // Refresh single delivery status inside Modal via AJAX
    $(document).on('click', '#modal-refresh-status-btn', function() {
        var btn = $(this);
        var consignmentId = btn.data('consignment-id');
        if (!consignmentId) return;
        var originalHtml = btn.html();

        btn.prop('disabled', true).html('<i class="fa fa-refresh fa-spin"></i> Refreshing...');

        $.getJSON(admin_url + 'courier/sync_single_status_ajax/' + consignmentId, function(res) {
            if (res.success) {
                alert_float('success', 'Status updated successfully!');
                $('#modal-courier-status').text(res.status);
                // Also update last synced time to now
                var now = new Date();
                var formatted = now.getFullYear() + '-' + 
                                String(now.getMonth() + 1).padStart(2, '0') + '-' + 
                                String(now.getDate()).padStart(2, '0') + ' ' + 
                                String(now.getHours()).padStart(2, '0') + ':' + 
                                String(now.getMinutes()).padStart(2, '0') + ':' + 
                                String(now.getSeconds()).padStart(2, '0');
                $('#modal-courier-last-synced').text(formatted);
            } else {
                alert_float('danger', res.error || 'Failed to update status.');
            }
            btn.prop('disabled', false).html(originalHtml);
        }).fail(function() {
            alert_float('danger', 'API connection failed or timed out.');
            btn.prop('disabled', false).html(originalHtml);
        });
    });

    // Populate BDCourier Fraud Report Card in modal
    function populateFraudData(data, phone) {
        var recheckBtn = $('#modal-fraud-recheck-btn');
        if (phone && phone.trim() !== '') {
            recheckBtn.data('phone', phone).show();
            $('#modal-fraud-phone-label').text(phone);
        } else {
            recheckBtn.hide();
            $('#modal-fraud-phone-label').text('-');
        }

        // Reset elements to default
        $('#modal-fraud-rating-circle').css({
            'background': 'conic-gradient(#cbd5e1 0%, #e2e8f0 0)',
            '--percent': 0
        });
        $('#modal-fraud-rating-value').text('0%');
        $('#modal-fraud-verdict-box').css({
            'border': '1px solid #e2e8f0',
            'background': '#f8fafc',
            'color': '#64748b'
        });
        $('#modal-fraud-verdict-label').text('Not Checked');
        $('#modal-fraud-verdict-action').text('No fraud details loaded.');
        
        $('#stat-total').text('0');
        $('#stat-success').text('0');
        $('#stat-cancelled').text('0');
        $('#stat-ratio').text('0%');
        $('#stat-ratio-box').css({ 'border-color': '#cbd5e1', 'background': '#fff', 'color': '#64748b' });

        if (data) {
            // Get raw response
            var res = data.raw_response;
            if (typeof res === 'string') {
                try { res = JSON.parse(res); } catch(e) { res = null; }
            }

            if (res && res.status === 'success') {
                var verdict = res.risk_verdict || {};
                var cData = res.data || {};
                var summary = cData.summary || {};
                
                // 1. Setup verdict card styling & colors
                var vBox = $('#modal-fraud-verdict-box');
                var vLabel = $('#modal-fraud-verdict-label');
                var vAction = $('#modal-fraud-verdict-action');
                var gaugeColor = '#cbd5e1';
                
                var vColor = verdict.color || 'grey';
                if (vColor === 'green') {
                    vBox.css({ 'border-color': '#86efac', 'background': '#e8f5e9', 'color': '#2e7d32' });
                    vLabel.html('<i class="fa fa-user-plus"></i> ঝুঁকি মুক্ত');
                    vAction.text('এই কাস্টমারকে নিশ্চিন্তে পার্সেল দিতে পারেন।');
                    gaugeColor = '#2e7d32';
                } else if (vColor === 'yellow' || vColor === 'orange') {
                    vBox.css({ 'border-color': '#fde047', 'background': '#fffde7', 'color': '#f57f17' });
                    vLabel.html('<i class="fa fa-exclamation-triangle"></i> মাঝারি ঝুঁকি');
                    vAction.text('অর্ডারটি কনফার্ম করার আগে গ্রাহকের সাথে কথা বলুন।');
                    gaugeColor = '#f57f17';
                } else if (vColor === 'red') {
                    vBox.css({ 'border-color': '#fca5a5', 'background': '#ffebee', 'color': '#c62828' });
                    vLabel.html('<i class="fa fa-user-times"></i> উচ্চ ঝুঁকি');
                    vAction.text('কাস্টমারের পার্সেল বাতিল হওয়ার রেকর্ড বেশি। সতর্ক থাকুন!');
                    gaugeColor = '#c62828';
                }

                // 2. Summary stats cards
                var total = parseInt(summary.total_parcel ?? 0);
                var success = parseInt(summary.success_parcel ?? 0);
                var cancel = parseInt(summary.cancelled_parcel ?? 0);
                var ratio = parseFloat(summary.success_ratio ?? 0.0);
                var ratioRound = Math.round(ratio);

                $('#stat-total').text(total);
                $('#stat-success').text(success);
                $('#stat-cancelled').text(cancel);
                $('#stat-ratio').text(ratioRound + '%');
                
                // Rating Circle
                $('#modal-fraud-rating-circle').css({
                    'background': 'conic-gradient(' + gaugeColor + ' ' + (ratioRound) + '%, #e2e8f0 0)',
                    '--percent': ratioRound
                });
                $('#modal-fraud-rating-value').text(ratioRound + '%');
                
                // Ratio Stat Box styling
                var ratioBox = $('#stat-ratio-box');
                if (ratioRound >= 80) {
                    ratioBox.css({ 'border-color': '#86efac', 'background': '#f0fdf4', 'color': '#166534' });
                } else if (ratioRound >= 60) {
                    ratioBox.css({ 'border-color': '#fde047', 'background': '#fefce8', 'color': '#854d0e' });
                } else {
                    ratioBox.css({ 'border-color': '#fca5a5', 'background': '#fef2f2', 'color': '#991b1b' });
                }

                // 3. Build stats table rows in exact requested courier list
                var courierList = [
                    { key: 'steadfast', name: 'Steadfast' },
                    { key: 'pathao', name: 'Pathao' },
                    { key: 'redx', name: 'RedX' },
                    { key: 'paperfly', name: 'PaperFly' },
                    { key: 'parceldex', name: 'ParcelDex' },
                    { key: 'carrybee', name: 'CarryBee' }
                ];
                
                var tableHtml = '';
                
                courierList.forEach(function(c) {
                    var cKey = Object.keys(cData).find(function(k) {
                        return k.toLowerCase() === c.key.toLowerCase();
                    });
                    
                    var courier = cKey ? cData[cKey] : null;
                    
                    if (courier) {
                        var cTotal = parseInt(courier.total_parcel ?? 0);
                        var cSuccess = parseInt(courier.success_parcel ?? 0);
                        var cCancel = parseInt(courier.cancelled_parcel ?? 0);
                        var cRatio = parseFloat(courier.success_ratio ?? 0.0);
                        var cRatioRound = Math.round(cRatio);
                        
                        var nameHtml = c.name;
                        var ratioHtml = cRatioRound + '%';
                        
                        if (cTotal > 0) {
                            if (cRatioRound >= 85) {
                                nameHtml = '<strong>' + c.name + '</strong>';
                                if (c.key === 'pathao') {
                                    nameHtml += ' <span style="background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; padding: 2px 6px; border-radius: 12px; font-size: 8px; font-weight: normal; margin-left: 5px;"><i class="fa fa-star"></i> ' + cRatioRound + '% • অসাধারণ গ্রাহক</span>';
                                }
                                ratioHtml = '<span class="label" style="background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; border-radius: 12px; padding: 2px 6px; font-size: 9px;"><i class="fa fa-star"></i> ' + cRatioRound + '%</span>';
                            } else if (cRatioRound >= 70) {
                                ratioHtml = '<span class="label" style="background: #fff8e1; color: #ff8f00; border: 1px solid #ffe082; border-radius: 12px; padding: 2px 6px; font-size: 9px;">' + cRatioRound + '%</span>';
                            } else {
                                ratioHtml = '<span class="label" style="background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; border-radius: 12px; padding: 2px 6px; font-size: 9px;">' + cRatioRound + '%</span>';
                            }
                        }
                        
                        tableHtml += '<tr>';
                        tableHtml += '  <td style="padding: 6px 8px; vertical-align: middle;">' + nameHtml + '</td>';
                        tableHtml += '  <td class="text-center" style="padding: 6px 8px; vertical-align: middle;">' + cTotal + '</td>';
                        tableHtml += '  <td class="text-center" style="padding: 6px 8px; vertical-align: middle;">' + cSuccess + '</td>';
                        tableHtml += '  <td class="text-center" style="padding: 6px 8px; vertical-align: middle;">' + cCancel + '</td>';
                        tableHtml += '  <td class="text-center" style="padding: 6px 8px; vertical-align: middle;">' + ratioHtml + '</td>';
                        tableHtml += '</tr>';
                    } else {
                        tableHtml += '<tr>';
                        tableHtml += '  <td style="padding: 6px 8px; vertical-align: middle;">' + c.name + '</td>';
                        tableHtml += '  <td class="text-center" style="padding: 6px 8px; vertical-align: middle;">0</td>';
                        tableHtml += '  <td class="text-center" style="padding: 6px 8px; vertical-align: middle;">0</td>';
                        tableHtml += '  <td class="text-center" style="padding: 6px 8px; vertical-align: middle;">0</td>';
                        tableHtml += '  <td class="text-center" style="padding: 6px 8px; vertical-align: middle;">0%</td>';
                        tableHtml += '</tr>';
                    }
                });
                
                $('#modal-fraud-stats-tbody').html(tableHtml);
                $('#modal-fraudcheck-row').show();
                return;
            }
        }

        // Placeholder fallback if no check run yet
        if (phone && phone.trim() !== '') {
            $('#modal-fraud-verdict-box').css({
                'border': '1px solid #fed7aa',
                'background': '#fff7ed',
                'color': '#c2410c'
            });
            $('#modal-fraud-verdict-label').html('<i class="fa fa-shield"></i> Not Checked');
            $('#modal-fraud-verdict-action').text('Customer fraud check history has not been loaded yet. Click "Re-check Fraud History" to run verification.');
            
            // Build empty courier list
            var emptyHtml = '';
            var list = ['Steadfast', 'Pathao', 'RedX', 'PaperFly', 'ParcelDex', 'CarryBee'];
            list.forEach(function(c) {
                emptyHtml += '<tr>';
                emptyHtml += '  <td style="padding: 6px 8px; vertical-align: middle;">' + c + '</td>';
                emptyHtml += '  <td class="text-center" style="padding: 6px 8px; vertical-align: middle;">0</td>';
                emptyHtml += '  <td class="text-center" style="padding: 6px 8px; vertical-align: middle;">0</td>';
                emptyHtml += '  <td class="text-center" style="padding: 6px 8px; vertical-align: middle;">0</td>';
                emptyHtml += '  <td class="text-center" style="padding: 6px 8px; vertical-align: middle;">0%</td>';
                emptyHtml += '</tr>';
            });
            $('#modal-fraud-stats-tbody').html(emptyHtml);
            $('#modal-fraudcheck-row').show();
        } else {
            $('#modal-fraudcheck-row').hide();
        }
    }

    // Re-check customer fraud history via AJAX
    $(document).on('click', '#modal-fraud-recheck-btn', function() {
        var btn = $(this);
        var phone = btn.data('phone');
        if (!phone) return;
        var originalHtml = btn.html();

        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Checking...');

        $.getJSON(admin_url + 'salesos/fraudcheck_recheck_ajax/' + phone, function(res) {
            if (res.success) {
                alert_float('success', 'Customer fraud history updated successfully!');
                populateFraudData(res.fraud_data, phone);
            } else {
                alert_float('danger', res.error || 'Failed to check fraud history.');
            }
            btn.prop('disabled', false).html(originalHtml);
        }).fail(function() {
            alert_float('danger', 'API connection failed or timed out.');
            btn.prop('disabled', false).html(originalHtml);
        });
    });
});
</script>

<!-- Inline Courier Booking Modal -->
<div class="modal fade" id="courier_booking_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title bold text-primary"><i class="fa fa-truck"></i> Send to Courier - Order #<span id="booking-order-id-label"></span></h4>
            </div>
            
            <!-- STATE 1: SELECTION VIEW -->
            <div id="booking-state-selection" style="background: #f8fafc; padding: 20px;">
                <?php if (empty($courier_accounts)): ?>
                    <div class="alert alert-warning no-margin text-center">
                        <p class="bold"><i class="fa fa-exclamation-triangle"></i> No Courier Accounts Configured</p>
                        <p class="no-margin mtop5 text-muted">Please configure a courier account first in order to book parcels.</p>
                        <a href="<?= admin_url('courier/settings') ?>" class="btn btn-primary btn-sm mtop10">Configure Accounts</a>
                    </div>
                <?php else: ?>
                    <p class="text-muted mbot20">Select the courier account you wish to use for booking this order:</p>
                    <div class="row">
                        <?php foreach ($courier_accounts as $acc): ?>
                            <div class="col-xs-12 mbot15">
                                <a href="#" class="select-courier-account-card btn-block" 
                                   data-account-id="<?= $acc['id'] ?>"
                                   data-provider="<?= e($acc['provider']) ?>"
                                   data-account-name="<?= e($acc['label'] ?? $acc['name'] ?? 'Courier Account') ?>">
                                    <div class="pull-left" style="font-size: 24px; margin-right: 15px; margin-top: 2px;">
                                        <?php if ($acc['provider'] === 'steadfast'): ?>
                                            <span class="text-danger"><i class="fa fa-paper-plane"></i></span>
                                        <?php else: ?>
                                            <span class="text-primary"><i class="fa fa-motorcycle"></i></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="pull-right">
                                        <span class="label label-<?= $acc['provider'] === 'steadfast' ? 'danger' : 'primary' ?>">
                                            <?= strtoupper(e($acc['provider'])) ?>
                                        </span>
                                    </div>
                                    <div>
                                        <h4 class="bold no-margin" style="color: #1e293b; font-size: 16px;"><?= e($acc['label'] ?? $acc['name'] ?? 'Courier Account') ?></h4>
                                        <small class="text-muted">Click to select and load booking form</small>
                                    </div>
                                    <div class="clearfix"></div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div class="text-right mtop10">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                </div>
            </div>

            <!-- STATE 2: BOOKING FORM VIEW -->
            <div id="booking-state-form" style="display: none; background: #f8fafc; padding: 20px;">
                
                <!-- Recipient Info Card -->
                <div class="panel panel-default" style="border: 1px solid #e2e8f0; border-radius: 4px; box-shadow: none; padding: 15px; background: #fff; margin-bottom: 20px;">
                    <h5 class="bold text-muted no-margin mbot10"><i class="fa fa-user"></i> Recipient & Shipping Information</h5>
                    <p class="mbot5"><strong>Name:</strong> <span id="booking-rec-name">-</span></p>
                    <p class="mbot5"><strong>Phone:</strong> <span id="booking-rec-phone">-</span></p>
                    <p class="mbot0"><strong>Address:</strong> <span id="booking-rec-address">-</span></p>
                </div>

                <?= form_open('#', ['id' => 'inline-courier-booking-form']) ?>
                <input type="hidden" name="salesos_order_id" id="booking-salesos-order-id" value="">
                <input type="hidden" name="courier_account_id" id="booking-courier-account-id" value="">

                <!-- Active Account Notice -->
                <div class="form-group">
                    <label class="control-label bold block">Selected Account:</label>
                    <span id="booking-selected-account-label" class="label label-info block" style="font-size: 13px; padding: 8px 10px; text-align: left;">-</span>
                </div>

                <!-- COD Amount -->
                <div class="form-group">
                    <label for="booking-cod-amount" class="control-label">COD Amount (BDT)</label>
                    <input type="number" step="0.01" min="0" name="cod_amount" id="booking-cod-amount" class="form-control" value="0.00" required>
                    <small class="text-muted">If the order is already paid (e.g. POS), set COD Amount to 0.00.</small>
                </div>

                <!-- Notes -->
                <div class="form-group">
                    <label for="booking-notes" class="control-label">Delivery Note</label>
                    <textarea name="notes" id="booking-notes" class="form-control" rows="2" placeholder="e.g. Call before delivery"></textarea>
                </div>

                <!-- Pathao Specific Fields -->
                <div id="booking-pathao-fields" style="display: none; border-left: 3px solid #3b82f6; padding-left: 15px; margin: 15px 0;">
                    <h5 class="bold text-primary mbot15"><i class="fa fa-motorcycle"></i> Pathao Courier Delivery Settings</h5>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="booking-recipient-city" class="control-label">City</label>
                                <select name="recipient_city" id="booking-recipient-city" class="form-control">
                                    <option value="">Select City...</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="booking-recipient-zone" class="control-label">Zone</label>
                                <select name="recipient_zone" id="booking-recipient-zone" class="form-control" disabled>
                                    <option value="">Select Zone...</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="booking-recipient-area" class="control-label">Area</label>
                                <select name="recipient_area" id="booking-recipient-area" class="form-control" disabled>
                                    <option value="">Select Area...</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="booking-delivery-type" class="control-label">Delivery Type</label>
                                <select name="delivery_type" id="booking-delivery-type" class="form-control">
                                    <option value="48">Normal Delivery (48h)</option>
                                    <option value="12">On Demand Delivery (12h)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="booking-item-type" class="control-label">Item Type</label>
                                <select name="item_type" id="booking-item-type" class="form-control">
                                    <option value="2">Parcel</option>
                                    <option value="1">Document</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="booking-item-weight" class="control-label">Weight (KG)</label>
                                <input type="number" step="0.1" name="item_weight" id="booking-item-weight" class="form-control" value="0.5">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="booking-item-quantity" class="control-label">Quantity</label>
                                <input type="number" min="1" name="item_quantity" id="booking-item-quantity" class="form-control" value="1">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-right mtop25">
                    <button type="button" class="btn btn-default" id="booking-back-to-selection"><i class="fa fa-arrow-left"></i> Change Courier</button>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-paper-plane"></i> Book Parcel</button>
                </div>
                <?= form_close() ?>

            </div>

        </div>
    </div>
</div>

<style>
.select-courier-account-card {
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 15px;
    background: #fff;
    text-decoration: none !important;
    transition: all 0.2s;
    box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05);
}
.select-courier-account-card:hover {
    border-color: #3b82f6 !important;
    background: #f0f7ff !important;
    box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.1), 0 2px 4px -1px rgba(59, 130, 246, 0.06) !important;
    transform: translateY(-1px);
}
.rating-circle {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: conic-gradient(#e2e8f0 0%, #cbd5e1 0);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px auto;
    position: relative;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
}
.rating-circle::before {
    content: "";
    position: absolute;
    width: 84px;
    height: 84px;
    border-radius: 50%;
    background: #f8fafc;
}
.rating-circle-text {
    position: absolute;
    text-align: center;
}
</style>
