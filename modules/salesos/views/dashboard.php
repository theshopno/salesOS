<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
/* ── Modern Command Center Styles ── */
.dash-header-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e2e8f0;
}
.dash-quick-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
}
.dash-quick-btn {
    font-size: 12px;
    font-weight: 600;
    padding: 6px 14px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
    text-decoration: none !important;
}
.dash-quick-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.08);
}

/* ── KPI Metric Cards ── */
.dash-kpi-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 14px 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    transition: all 0.2s ease;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    min-height: 84px;
}
.dash-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px -2px rgba(0,0,0,0.08);
    border-color: #cbd5e1;
}
.dash-kpi-lbl {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 3px;
}
.dash-kpi-val {
    font-size: 20px;
    font-weight: 800;
    line-height: 1.2;
}
.dash-kpi-sub {
    font-size: 11px;
    display: block;
    margin-top: 3px;
}
.dash-kpi-icon {
    width: 42px;
    height: 42px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

/* ── Panel Box Design ── */
.dash-panel {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.dash-panel-header {
    padding: 12px 18px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #fafafa;
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
}
.dash-panel-header h4 {
    margin: 0;
    font-size: 14px;
    font-weight: 700;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 8px;
}
.dash-panel-body {
    padding: 18px;
}

/* ── Channel & Status Badges ── */
.pill-channel-woo {
    background: #eff6ff;
    color: #1e40af;
    border: 1px solid #bfdbfe;
    font-size: 10px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.pill-channel-pos {
    background: #f0fdf4;
    color: #166534;
    border: 1px solid #bbf7d0;
    font-size: 10px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.pill-channel-pos-online {
    background: #e0f2fe;
    color: #0369a1;
    border: 1px solid #bae6fd;
    font-size: 10px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.pill-channel-manual {
    background: #fff7ed;
    color: #c2410c;
    border: 1px solid #fed7aa;
    font-size: 10px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.pill-status {
    font-size: 10px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 12px;
    display: inline-block;
    letter-spacing: 0.3px;
}
.pill-status-confirmed { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
.pill-status-pending { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
.pill-status-processing { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; }
.pill-status-delivered { background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe; }
.pill-status-cancelled { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

/* ── WhatsApp & Action Buttons ── */
.btn-whatsapp-quick {
    background: #25d366;
    color: #fff !important;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    text-decoration: none !important;
    box-shadow: 0 1px 2px rgba(37, 211, 102, 0.25);
}
.btn-whatsapp-quick:hover { background: #128c7e; }
.btn-whatsapp-quick i, .fa-whatsapp {
    font-family: 'Font Awesome 6 Brands', 'FontAwesome' !important;
    font-weight: 400 !important;
}

/* ── Pulse Dots ── */
.pulse-dot-green {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: #22c55e;
    display: inline-block;
    box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.25);
}
.pulse-dot-grey {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: #94a3b8;
    display: inline-block;
}
</style>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                
                <!-- ── Top Header & Command Launchpad ── -->
                <div class="dash-header-bar">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%); display: flex; align-items: center; justify-content: center; color: #ffffff; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.25); font-size: 18px; flex-shrink: 0;">
                            <i class="fa fa-store"></i>
                        </div>
                        <div>
                            <h4 class="no-margin bold" style="font-size: 19px; color: #0f172a; letter-spacing: -0.3px;">
                                E-commerce & Retail Command Center
                            </h4>
                            <span class="text-muted" style="font-size: 12px; display: block; margin-top: 2px;">
                                লাইভ সেলস, ওমনি-চ্যানেল ইনভেন্টরি, কুরিয়ার ও অপারেশনস মনিটরিং
                            </span>
                        </div>
                    </div>
                    <div class="dash-quick-actions">
                        <a href="<?= admin_url('pos') ?>" class="dash-quick-btn btn-success">
                            <i class="fa fa-desktop"></i> + New POS Sale
                        </a>
                        <a href="<?= admin_url('salesos/orders') ?>" class="dash-quick-btn btn-primary">
                            <i class="fa fa-shopping-cart"></i> Order Manager
                        </a>
                        <a href="<?= admin_url('salesos/confirmations') ?>" class="dash-quick-btn btn-warning" title="কল ও কনফার্মেশনের জন্য অপেক্ষমান">
                            <i class="fa fa-phone"></i> Confirmations <?= $pending_count > 0 ? '<span class="badge" style="background:#fff; color:#b45309; font-weight:bold; margin-left:4px;">' . $pending_count . '</span>' : '' ?>
                        </a>
                        <a href="<?= admin_url('salesos/settings?tab=channels') ?>" class="dash-quick-btn btn-default" style="border-color: #cbd5e1;">
                            <i class="fa fa-cog"></i> Integrations
                        </a>
                    </div>
                </div>

                <!-- ── Tier-1 Executive KPI Metric Cards (6 Cards) ── -->
                <div class="row mbot10">
                    <!-- Card 1: Total Sales & Revenue -->
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="dash-kpi-card">
                            <div>
                                <div class="dash-kpi-lbl" style="color: #2563eb;">মোট সেলস</div>
                                <div class="dash-kpi-val" style="color: #1d4ed8;"><?= salesos_format_number($total_sales) ?> <span style="font-size: 11px; font-weight: normal; color: #64748b;">BDT</span></div>
                                <span class="dash-kpi-sub text-muted">আদায়: <?= salesos_format_number($total_revenue) ?> BDT</span>
                            </div>
                            <div class="dash-kpi-icon" style="background: #eff6ff; color: #2563eb;">
                                <i class="fa fa-shopping-basket"></i>
                            </div>
                        </div>
                    </div>
                    <!-- Card 2: Today's Snapshot -->
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="dash-kpi-card">
                            <div>
                                <div class="dash-kpi-lbl" style="color: #059669;">আজকের সেলস</div>
                                <div class="dash-kpi-val" style="color: #047857;"><?= salesos_format_number($today_sales) ?> <span style="font-size: 11px; font-weight: normal; color: #64748b;">BDT</span></div>
                                <span class="dash-kpi-sub text-muted">আজকের অর্ডার: <?= (int)$today_orders ?> টি</span>
                            </div>
                            <div class="dash-kpi-icon" style="background: #ecfdf5; color: #059669;">
                                <i class="fa fa-calendar-check"></i>
                            </div>
                        </div>
                    </div>
                    <!-- Card 3: This Month -->
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="dash-kpi-card">
                            <div>
                                <div class="dash-kpi-lbl" style="color: #7c3aed;">এই মাসের সেলস</div>
                                <div class="dash-kpi-val" style="color: #6d28d9;"><?= salesos_format_number($month_sales) ?> <span style="font-size: 11px; font-weight: normal; color: #64748b;">BDT</span></div>
                                <span class="dash-kpi-sub text-muted">মোট অর্ডার: <?= (int)$month_orders ?> টি</span>
                            </div>
                            <div class="dash-kpi-icon" style="background: #f5f3ff; color: #7c3aed;">
                                <i class="fa fa-bar-chart"></i>
                            </div>
                        </div>
                    </div>
                    <!-- Card 4: Pending Confirmation -->
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="dash-kpi-card">
                            <div>
                                <div class="dash-kpi-lbl" style="color: #d97706;">অপেক্ষমান (Pending)</div>
                                <div class="dash-kpi-val" style="color: #b45309;"><?= (int)$pending_count ?> <span style="font-size: 11px; font-weight: normal; color: #64748b;">টি</span></div>
                                <span class="dash-kpi-sub text-muted">কল ও ভেরিফিকেশন বাকি</span>
                            </div>
                            <div class="dash-kpi-icon" style="background: #fef3c7; color: #d97706;">
                                <i class="fa fa-phone"></i>
                            </div>
                        </div>
                    </div>
                    <!-- Card 5: Ready / Processing -->
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="dash-kpi-card">
                            <div>
                                <div class="dash-kpi-lbl" style="color: #4f46e5;">প্রসেসিং ও প্রস্তুত</div>
                                <div class="dash-kpi-val" style="color: #4338ca;"><?= (int)$ready_count ?> <span style="font-size: 11px; font-weight: normal; color: #64748b;">টি</span></div>
                                <span class="dash-kpi-sub text-muted">প্যাকিং ও ডিসপ্যাচ</span>
                            </div>
                            <div class="dash-kpi-icon" style="background: #e0e7ff; color: #4f46e5;">
                                <i class="fa fa-cube"></i>
                            </div>
                        </div>
                    </div>
                    <!-- Card 6: High Risk Fraud Alert -->
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="dash-kpi-card">
                            <div>
                                <div class="dash-kpi-lbl" style="color: #dc2626;">উচ্চ ঝুঁকি অ্যালার্ট</div>
                                <div class="dash-kpi-val" style="color: #b91c1c;"><?= (int)$high_risk_count ?> <span style="font-size: 11px; font-weight: normal; color: #64748b;">টি</span></div>
                                <span class="dash-kpi-sub text-muted">BDCourier ফ্ল্যাগড</span>
                            </div>
                            <div class="dash-kpi-icon" style="background: #fee2e2; color: #dc2626;">
                                <i class="fa fa-shield"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Visual Analytics Row (7-Day Trend Chart + Omni-Channel Share) ── -->
                <div class="row">
                    <!-- Left: 7-Day Trend Line Chart -->
                    <div class="col-md-8">
                        <div class="dash-panel">
                            <div class="dash-panel-header">
                                <h4><i class="fa fa-area-chart text-primary"></i> ৭ দিনের সেলস ও রেভিনিউ ট্রেন্ড (Omni-channel Daily Trend)</h4>
                                <span class="text-muted" style="font-size: 11px;">গত ৭ দিনের চ্যানেলভিত্তিক বিক্রয়</span>
                            </div>
                            <div class="dash-panel-body">
                                <div style="position: relative; height: 215px;">
                                    <canvas id="salesTrendChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Omni-Channel Share & Pipeline Funnel -->
                    <div class="col-md-4">
                        <div class="dash-panel">
                            <div class="dash-panel-header">
                                <h4><i class="fa fa-pie-chart text-primary"></i> ওমনি-চ্যানেল রেভিনিউ শেয়ার</h4>
                                <span class="text-muted" style="font-size: 11px;">মোট কনফার্মড: <?= salesos_format_number($total_sales) ?> BDT</span>
                            </div>
                            <div class="dash-panel-body" style="padding-bottom: 12px;">
                                <?php if (empty($channels)): ?>
                                    <p class="text-muted text-center no-margin">No channel data available.</p>
                                <?php else: ?>
                                    <?php foreach ($channels as $ch): ?>
                                        <?php 
                                            $raw_ch = strtolower($ch['channel']);
                                            $ch_name = strtoupper($ch['channel']);
                                            $ch_icon = 'fa-shopping-bag';
                                            $bar_color = '#2563eb';
                                            if ($raw_ch === 'woo' || $raw_ch === 'woocommerce') { 
                                                $ch_name = 'WooCommerce Store'; 
                                                $ch_icon = 'fa-shopping-cart'; 
                                                $bar_color = '#7c3aed'; 
                                            }
                                            elseif ($raw_ch === 'pos') { 
                                                $ch_name = 'POS Counter'; 
                                                $ch_icon = 'fa-desktop'; 
                                                $bar_color = '#059669'; 
                                            }
                                            elseif ($raw_ch === 'manual') { 
                                                $ch_name = 'Manual / Direct'; 
                                                $ch_icon = 'fa-phone'; 
                                                $bar_color = '#ea580c'; 
                                            }
                                            $share = (float)$ch['share_percent'];
                                        ?>
                                        <div style="margin-bottom: 14px;">
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                                <span style="font-weight: 700; font-size: 12px; color: #0f172a;">
                                                    <i class="fa <?= $ch_icon ?>" style="color: <?= $bar_color ?>; margin-right: 5px;"></i> <?= $ch_name ?>
                                                </span>
                                                <span style="font-size: 12px; font-weight: 700; color: #1e293b;">
                                                    <?= salesos_format_number($ch['confirmed_revenue']) ?> <span style="font-size: 10px; font-weight: normal; color: #64748b;">BDT</span>
                                                </span>
                                            </div>
                                            <div class="progress" style="height: 18px; margin-bottom: 4px; background-color: #f1f5f9; border-radius: 9px; box-shadow: inset 0 1px 2px rgba(0,0,0,0.06); overflow: hidden;">
                                                <div class="progress-bar" role="progressbar" 
                                                     style="width: <?= max(14, $share) ?>%; background-color: <?= $bar_color ?>; line-height: 18px; font-size: 11px; font-weight: 700; color: #ffffff; text-align: center; border-radius: 9px; transition: width 0.6s ease; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                                                    <?= $share ?>%
                                                </div>
                                            </div>
                                            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11px; color: #64748b;">
                                                <span><i class="fa fa-shopping-basket" style="font-size: 10px; color: #94a3b8; margin-right: 3px;"></i> মোট অর্ডার: <strong style="color: #334155;"><?= (int)$ch['order_count'] ?></strong> টি</span>
                                                <span>শেয়ার: <strong style="color: #334155;"><?= $share ?>%</strong></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <hr style="margin: 10px 0 12px 0; border-color: #f1f5f9;" />
                                <!-- Order Status Funnel -->
                                <div style="display: flex; justify-content: space-between; text-align: center; font-size: 11px;">
                                    <div style="flex: 1;">
                                        <span style="font-weight: 700; color: #059669; font-size: 13px; display: block;"><?= $confirmed_count ?></span>
                                        <small class="text-muted">Confirmed</small>
                                    </div>
                                    <div style="flex: 1; border-left: 1px solid #f1f5f9;">
                                        <span style="font-weight: 700; color: #4338ca; font-size: 13px; display: block;"><?= $processing_count ?></span>
                                        <small class="text-muted">Processing</small>
                                    </div>
                                    <div style="flex: 1; border-left: 1px solid #f1f5f9;">
                                        <span style="font-weight: 700; color: #d97706; font-size: 13px; display: block;"><?= $pending_count ?></span>
                                        <small class="text-muted">Pending</small>
                                    </div>
                                    <div style="flex: 1; border-left: 1px solid #f1f5f9;">
                                        <span style="font-weight: 700; color: #dc2626; font-size: 13px; display: block;"><?= $cancelled_count ?></span>
                                        <small class="text-muted">Cancelled</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Operational Bottom Row: Recent Orders & Action Widgets ── -->
                <div class="row">
                    <!-- Left: Recent Orders Live Stream -->
                    <div class="col-md-8">
                        <div class="dash-panel">
                            <div class="dash-panel-header">
                                <h4><i class="fa fa-clock text-primary" style="margin-right: 6px;"></i> সাম্প্রতিক অর্ডারসমূহ (Live Orders Feed)</h4>
                                <a href="<?= admin_url('salesos/orders') ?>" class="btn btn-default btn-xs" style="font-weight: 600; border-color: #cbd5e1;">
                                    সব অর্ডার দেখুন (<?= (int)$total_orders ?>) <i class="fa fa-arrow-right" style="font-size: 10px;"></i>
                                </a>
                            </div>
                            <div class="dash-panel-body no-padding">
                                <?php if (empty($recent_orders)): ?>
                                    <div style="padding: 30px; text-align: center; color: #94a3b8;">
                                        <i class="fa fa-shopping-basket fa-3x" style="color: #cbd5e1; margin-bottom: 8px;"></i>
                                        <p class="no-margin">কোনো অর্ডার পাওয়া যায়নি।</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped no-margin" style="font-size: 12px;">
                                            <thead>
                                                <tr style="background: #f8fafc; font-size: 11px;">
                                                    <th style="width: 60px;"># ID</th>
                                                    <th>Customer & Phone</th>
                                                    <th style="width: 85px;">Channel</th>
                                                    <th style="width: 110px;">Amount</th>
                                                    <th style="width: 95px; text-align: center;">Status</th>
                                                    <th style="width: 120px;">Courier</th>
                                                    <th class="text-center" style="width: 95px;">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_orders as $order): ?>
                                                    <?php 
                                                        $ch = strtolower($order['channel'] ?? '');
                                                        $st = strtolower($order['status'] ?? 'pending');
                                                        $clean_phone = preg_replace('/[^0-9]/', '', (string)$order['customer_phone']);
                                                        if (strpos($clean_phone, '88') !== 0 && strlen($clean_phone) === 11) {
                                                            $clean_phone = '88' . $clean_phone;
                                                        }
                                                        $status_class = 'pill-status-pending';
                                                        if ($st === 'confirmed') $status_class = 'pill-status-confirmed';
                                                        elseif ($st === 'processing') $status_class = 'pill-status-processing';
                                                        elseif ($st === 'delivered') $status_class = 'pill-status-delivered';
                                                        elseif ($st === 'cancelled') $status_class = 'pill-status-cancelled';
                                                    ?>
                                                    <tr>
                                                        <td><strong>#<?= $order['id'] ?></strong></td>
                                                        <td>
                                                            <strong style="color: #0f172a; display: block; font-size: 12px;"><?= e($order['customer_name']) ?></strong>
                                                            <div style="display: flex; gap: 4px; align-items: center; margin-top: 2px;">
                                                                <?php if (!empty($order['customer_phone'])): ?>
                                                                    <a href="tel:<?= e($order['customer_phone']) ?>" class="text-muted" style="font-size: 11px; text-decoration: none;">
                                                                        <i class="fa fa-phone" style="font-size: 10px;"></i> <?= e($order['customer_phone']) ?>
                                                                    </a>
                                                                    <?php if (!empty($clean_phone)): ?>
                                                                        <a href="https://wa.me/<?= e($clean_phone) ?>" target="_blank" class="btn-whatsapp-quick" title="WhatsApp এ মেসেজ">
                                                                            <i class="fa-brands fa-whatsapp"></i> Chat
                                                                        </a>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <small class="text-muted">No Phone</small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?php if ($ch === 'woo'): ?>
                                                                <span class="pill-channel-woo"><i class="fa fa-shopping-cart"></i> WOO</span>
                                                            <?php elseif ($ch === 'pos'): ?>
                                                                <span class="pill-channel-pos"><i class="fa fa-desktop"></i> POS</span>
                                                            <?php elseif ($ch === 'pos_online'): ?>
                                                                <span class="pill-channel-pos-online"><i class="fa fa-truck"></i> POS ONLINE</span>
                                                            <?php else: ?>
                                                                <span class="pill-channel-manual"><i class="fa fa-phone"></i> <?= strtoupper(e($ch ?: 'MANUAL')) ?></span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <strong style="color: #0f172a;"><?= salesos_format_number($order['total']) ?> BDT</strong>
                                                            <small class="display-block text-muted" style="font-size: 10px;">
                                                                <?= (int)($order['item_count'] ?? 1) ?> item<?= ($order['item_count'] ?? 1) > 1 ? 's' : '' ?>
                                                            </small>
                                                        </td>
                                                        <td style="text-align: center;">
                                                            <span class="pill-status <?= $status_class ?>"><?= strtoupper(e($st)) ?></span>
                                                        </td>
                                                        <td>
                                                            <?php if ($ch === 'pos'): ?>
                                                                <span class="label" style="background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; font-size: 10px; padding: 3px 6px;">
                                                                    <i class="fa fa-shopping-bag"></i> In-store
                                                                </span>
                                                            <?php elseif (!empty($order['consignment_id'])): ?>
                                                                <span class="label" style="background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; font-size: 10px; padding: 3px 6px;">
                                                                    <i class="fa fa-truck"></i> <?= e($order['courier_provider'] ? strtoupper($order['courier_provider']) : 'Booked') ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="label label-warning" style="font-size: 10px; padding: 3px 6px;">
                                                                    <i class="fa fa-clock"></i> Unbooked
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-center" style="white-space: nowrap;">
                                                            <button type="button" class="btn btn-default btn-xs view-order-details-btn" data-id="<?= $order['id'] ?>" title="View Order" style="padding: 3px 6px;">
                                                                <i class="fa fa-eye text-primary"></i>
                                                            </button>
                                                            <a href="<?= admin_url('salesos/print_invoice/' . $order['id']) ?>" target="_blank" class="btn btn-default btn-xs" title="Print Invoice" style="padding: 3px 6px;">
                                                                <i class="fa fa-print"></i>
                                                            </a>
                                                            <a href="<?= admin_url('salesos/print_label/' . $order['id']) ?>" target="_blank" class="btn btn-default btn-xs" title="Print 55mm Label" style="padding: 3px 6px;">
                                                                <i class="fa fa-tag text-warning"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Action Hub & System Connectivity Pulse -->
                    <div class="col-md-4">
                        
                        <!-- Widget 1: Action Center (Urgent Attention) -->
                        <div class="dash-panel mbot15">
                            <div class="dash-panel-header">
                                <h4><i class="fa fa-bell text-danger"></i> অ্যাকশন সেন্টার (Attention Needed)</h4>
                                <span class="badge" style="background: #fee2e2; color: #991b1b; font-weight: 700;"><?= (count($urgent_risk_orders) + count($low_stock_products)) ?></span>
                            </div>
                            <div class="dash-panel-body" style="padding: 12px 15px;">
                                
                                <!-- High Risk Alert Orders -->
                                <?php if (!empty($urgent_risk_orders)): ?>
                                    <div class="mbot12">
                                        <span class="bold text-danger" style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">
                                            <i class="fa fa-shield"></i> ফ্রড অ্যালার্ট অর্ডার (High Risk)
                                        </span>
                                        <div style="margin-top: 6px;">
                                            <?php foreach ($urgent_risk_orders as $uo): ?>
                                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 6px 8px; background: #fff5f5; border: 1px solid #fed7d7; border-radius: 6px; margin-bottom: 6px;">
                                                    <div>
                                                        <strong style="color: #991b1b; font-size: 12px;">#<?= $uo['id'] ?> - <?= e($uo['customer_name']) ?></strong>
                                                        <small class="display-block text-muted" style="font-size: 10px;"><?= e($uo['customer_phone']) ?> | BDCourier: <?= round((float)$uo['success_ratio']) ?>%</small>
                                                    </div>
                                                    <div>
                                                        <a href="<?= admin_url('salesos/orders?search=' . $uo['id']) ?>" class="btn btn-danger btn-xs" style="font-size: 10px; padding: 2px 6px;">
                                                            Review
                                                        </a>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Low Stock Warning -->
                                <div>
                                    <span class="bold text-warning" style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">
                                        <i class="fa fa-exclamation-triangle"></i> CRM-First স্টক অ্যালার্ট (Low Stock)
                                    </span>
                                    <div style="margin-top: 6px;">
                                        <?php if (empty($low_stock_products)): ?>
                                            <small class="text-muted">সব পণ্যের পর্যাপ্ত স্টক রয়েছে।</small>
                                        <?php else: ?>
                                            <?php foreach (array_slice($low_stock_products, 0, 3) as $lp): ?>
                                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 5px 8px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 5px;">
                                                    <div style="max-width: 65%;">
                                                        <span style="font-size: 11px; font-weight: 600; color: #1e293b; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                            <?= e($lp['name']) ?>
                                                        </span>
                                                        <small class="text-muted" style="font-size: 10px;"><?= salesos_format_number($lp['rate']) ?> BDT</small>
                                                    </div>
                                                    <div>
                                                        <span class="label" style="background: <?= (float)$lp['stock'] <= 0 ? '#fee2e2; color: #991b1b; border: 1px solid #fca5a5;' : '#fef3c7; color: #92400e; border: 1px solid #fde68a;' ?> font-size: 10px; font-weight: 700;">
                                                            <?= (float)$lp['stock'] ?> PCS
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                            <?php if (count($low_stock_products) > 3): ?>
                                                <div style="text-align: right; margin-top: 3px;">
                                                    <a href="<?= admin_url('inventory/products') ?>" style="font-size: 10px; color: #64748b; text-decoration: none;">
                                                        সব স্টক দেখুন &raquo;
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <!-- Widget 2: Live System & Integration Pulse -->
                        <div class="dash-panel">
                            <div class="dash-panel-header">
                                <h4><i class="fa fa-heartbeat text-success"></i> সিস্টেম ও ইন্টিগ্রেশন পালস</h4>
                                <span class="text-muted" style="font-size: 11px;">কানেক্টিভিটি স্ট্যাটাস</span>
                            </div>
                            <div class="dash-panel-body" style="padding: 12px 15px;">
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 6px 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span class="<?= $system_health['woo'] ? 'pulse-dot-green' : 'pulse-dot-grey' ?>"></span>
                                            <span style="font-size: 12px; font-weight: 600; color: #1e293b;">WooCommerce Store</span>
                                        </div>
                                        <span class="label label-<?= $system_health['woo'] ? 'success' : 'default' ?>" style="font-size: 10px;">
                                            <?= $system_health['woo'] ? 'Live Sync' : 'Inactive' ?>
                                        </span>
                                    </div>

                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 6px 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span class="<?= $system_health['courier'] ? 'pulse-dot-green' : 'pulse-dot-grey' ?>"></span>
                                            <span style="font-size: 12px; font-weight: 600; color: #1e293b;">Steadfast Courier</span>
                                        </div>
                                        <span class="label label-<?= $system_health['courier'] ? 'success' : 'default' ?>" style="font-size: 10px;">
                                            <?= $system_health['courier'] ? 'Ready (StoveBD)' : 'Inactive' ?>
                                        </span>
                                    </div>

                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 6px 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span class="<?= $system_health['fraud'] ? 'pulse-dot-green' : 'pulse-dot-grey' ?>"></span>
                                            <span style="font-size: 12px; font-weight: 600; color: #1e293b;">BDCourier Fraud Guard</span>
                                        </div>
                                        <span class="label label-<?= $system_health['fraud'] ? 'success' : 'default' ?>" style="font-size: 10px;">
                                            <?= $system_health['fraud'] ? 'Active Shield' : 'Inactive' ?>
                                        </span>
                                    </div>

                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 6px 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span class="<?= $system_health['whatsapp'] ? 'pulse-dot-green' : 'pulse-dot-grey' ?>"></span>
                                            <span style="font-size: 12px; font-weight: 600; color: #1e293b;">WhatsApp Gateway</span>
                                        </div>
                                        <span class="label label-<?= $system_health['whatsapp'] ? 'success' : 'default' ?>" style="font-size: 10px;">
                                            <?= $system_health['whatsapp'] ? 'Connected (BizBot)' : 'Inactive' ?>
                                        </span>
                                    </div>
                                </div>
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

<script src="<?= base_url('assets/plugins/Chart.js/Chart.bundle.min.js') ?>"></script>

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
    // ── 7-Day Sales & Revenue Trend Chart ──
    try {
        var chartData = <?= json_encode($chart_data ?? ['labels' => [], 'pos' => [], 'woo' => [], 'manual' => []]) ?>;
        var chartCanvas = document.getElementById('salesTrendChart');
        if (chartCanvas && typeof Chart !== 'undefined') {
            new Chart(chartCanvas, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'POS বিক্রয় (৳)',
                            data: chartData.pos,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.08)',
                            borderWidth: 2,
                            lineTension: 0.35,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            pointBackgroundColor: '#10b981',
                            fill: true
                        },
                        {
                            label: 'WooCommerce (৳)',
                            data: chartData.woo,
                            borderColor: '#7c3aed',
                            backgroundColor: 'rgba(124, 58, 237, 0.08)',
                            borderWidth: 2,
                            lineTension: 0.35,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            pointBackgroundColor: '#7c3aed',
                            fill: true
                        },
                        {
                            label: 'Direct / Manual (৳)',
                            data: chartData.manual,
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37, 99, 235, 0.08)',
                            borderWidth: 2,
                            lineTension: 0.35,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            pointBackgroundColor: '#2563eb',
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: {
                        position: 'top',
                        labels: {
                            boxWidth: 12,
                            fontColor: '#475569',
                            fontSize: 11,
                            padding: 12
                        }
                    },
                    tooltips: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(tooltipItem, data) {
                                var label = data.datasets[tooltipItem.datasetIndex].label || '';
                                return label + ': ' + salesos_format_number(tooltipItem.yLabel) + ' BDT';
                            }
                        }
                    },
                    scales: {
                        xAxes: [{
                            gridLines: {
                                display: false
                            },
                            ticks: {
                                fontColor: '#64748b',
                                fontSize: 10
                            }
                        }],
                        yAxes: [{
                            ticks: {
                                beginAtZero: true,
                                fontColor: '#64748b',
                                fontSize: 10,
                                callback: function(value) {
                                    return value >= 1000 ? (value / 1000) + 'k' : value;
                                }
                            },
                            gridLines: {
                                color: '#f1f5f9',
                                drawBorder: false
                            }
                        }]
                    }
                }
            });
        }
    } catch(e) {
        console.error('Error initializing SalesOS trend chart:', e);
    }
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

<style>
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
