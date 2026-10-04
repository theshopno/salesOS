<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
/* ── Telesales Confirmation Desk Styles ── */
.conf-header-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #ffffff;
    padding: 18px 20px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    margin-bottom: 15px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.conf-kpi-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
    margin-bottom: 15px;
}
.conf-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.06);
}
.conf-kpi-val {
    font-size: 20px;
    font-weight: 800;
    line-height: 1.2;
    margin: 3px 0 2px 0;
}
.conf-kpi-lbl {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.conf-kpi-sub {
    font-size: 11px;
    color: #64748b;
}
.conf-kpi-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

/* ── Filter Toolbar ── */
.conf-filter-toolbar {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 15px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
}

/* ── Table & Row Components ── */
.conf-table th {
    background: #f8fafc;
    color: #475569;
    font-weight: 700;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    padding: 10px 12px !important;
    vertical-align: middle !important;
    border-bottom: 2px solid #e2e8f0 !important;
}
.conf-table td {
    padding: 12px !important;
    vertical-align: top !important;
    border-color: #f1f5f9 !important;
}
.conf-table tr.order-row:hover {
    background-color: #f8fafc !important;
}

/* ── Channel & Risk Pills ── */
.pill-channel-woo {
    background: #f5f3ff;
    color: #7c3aed;
    border: 1px solid #ddd6fe;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}
.pill-channel-pos {
    background: #ecfdf5;
    color: #059669;
    border: 1px solid #a7f3d0;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}
.pill-channel-pos-online {
    background: #e0f2fe;
    color: #0369a1;
    border: 1px solid #bae6fd;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}
.pill-channel-manual {
    background: #fff7ed;
    color: #ea580c;
    border: 1px solid #fed7aa;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}

/* ── Dialing & WhatsApp Buttons ── */
.btn-call-quick {
    background: #eff6ff;
    color: #1d4ed8 !important;
    border: 1px solid #bfdbfe;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    text-decoration: none !important;
    transition: all 0.15s;
}
.btn-call-quick:hover {
    background: #dbeafe;
    color: #1e40af !important;
}
.btn-whatsapp-quick {
    background: #25d366;
    color: #ffffff !important;
    font-size: 11px;
    font-weight: 600;
    padding: 3px 9px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    text-decoration: none !important;
    box-shadow: 0 1px 2px rgba(37, 211, 102, 0.25);
    transition: all 0.15s;
}
.btn-whatsapp-quick:hover {
    background: #128c7e;
}
.btn-whatsapp-quick i, .fa-whatsapp {
    font-family: 'Font Awesome 6 Brands', 'FontAwesome' !important;
    font-weight: 400 !important;
}

/* ── Quick Note Preset Chips ── */
.conf-chips-container {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-bottom: 6px;
}
.btn-preset-chip {
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    color: #334155;
    font-size: 10px;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.15s;
    line-height: 1.3;
}
.btn-preset-chip:hover {
    background: #e2e8f0;
    border-color: #94a3b8;
    color: #0f172a;
}

/* ── Action Buttons ── */
.conf-action-group {
    display: flex;
    gap: 4px;
}
.conf-action-group .btn {
    font-weight: 700;
    font-size: 11px;
    padding: 4px 8px;
    border-radius: 5px;
}

/* Row Flash Animations */
@keyframes flashGreen {
    0% { background-color: #dcfce7; }
    100% { background-color: transparent; }
}
@keyframes flashRed {
    0% { background-color: #fee2e2; }
    100% { background-color: transparent; }
}
@keyframes flashYellow {
    0% { background-color: #fef3c7; }
    100% { background-color: transparent; }
}
.row-success-flash { animation: flashGreen 0.6s ease-out; }
.row-danger-flash { animation: flashRed 0.6s ease-out; }
.row-warning-flash { animation: flashYellow 0.6s ease-out; }
</style>

<div id="wrapper">
    <div class="content">

        <!-- ── Top Header & Command Bar ── -->
        <div class="conf-header-bar">
            <div style="display: flex; align-items: center; gap: 14px;">
                <div style="width: 44px; height: 44px; border-radius: 12px; background: linear-gradient(135deg, #ea580c 0%, #f97316 100%); display: flex; align-items: center; justify-content: center; color: #ffffff; box-shadow: 0 4px 10px rgba(234, 88, 12, 0.25); font-size: 20px; flex-shrink: 0;">
                    <i class="fa fa-phone"></i>
                </div>
                <div>
                    <h4 class="no-margin bold text-primary" style="font-size: 19px; color: #0f172a;">
                        Order Confirmations Desk
                    </h4>
                    <span class="text-muted" style="font-size: 12px; display: block; margin-top: 2px;">
                        টেলিসেলস কল সেন্টার: গ্রাহককে কল করে অর্ডার কনফার্ম করুন। কনফার্ম হলে ওয়্যারহাউজ ও কুরিয়ারে যাবে, বাতিল হলে স্টক আনলক হবে।
                    </span>
                </div>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <button type="button" class="btn btn-default btn-sm" onclick="window.location.reload();" title="কিউ রিফ্রেশ করুন">
                    <i class="fa fa-refresh"></i> রিফ্রেশ
                </button>
                <a href="<?= admin_url('salesos/orders') ?>" class="btn btn-primary btn-sm">
                    <i class="fa fa-shopping-cart"></i> Order Manager
                </a>
                <a href="<?= admin_url('salesos') ?>" class="btn btn-default btn-sm">
                    <i class="fa fa-tachometer"></i> Dashboard
                </a>
            </div>
        </div>

        <!-- ── 5 Tier-1 Telesales KPI Metric Cards ── -->
        <div class="row">
            <!-- Card 1: Waiting Queue -->
            <div class="col-md-2 col-sm-4 col-xs-6" style="width: 20%;">
                <div class="conf-kpi-card">
                    <div>
                        <div class="conf-kpi-lbl" style="color: #ea580c;">অপেক্ষমান কল</div>
                        <div class="conf-kpi-val" id="kpi-waiting-val" style="color: #c2410c;"><?= (int) $waiting ?> <span style="font-size: 12px; font-weight: normal; color: #64748b;">টি</span></div>
                        <span class="conf-kpi-sub">কলের জন্য অপেক্ষমান</span>
                    </div>
                    <div class="conf-kpi-icon" style="background: #fff7ed; color: #ea580c;">
                        <i class="fa fa-phone"></i>
                    </div>
                </div>
            </div>

            <!-- Card 2: Today's Confirmed -->
            <div class="col-md-2 col-sm-4 col-xs-6" style="width: 20%;">
                <div class="conf-kpi-card">
                    <div>
                        <div class="conf-kpi-lbl" style="color: #059669;">আজকে কনফার্মড</div>
                        <div class="conf-kpi-val" id="kpi-today-confirmed-val" style="color: #047857;"><?= (int) $today_confirmed ?> <span style="font-size: 12px; font-weight: normal; color: #64748b;">টি</span></div>
                        <span class="conf-kpi-sub">সফলভাবে নিশ্চিতকৃত</span>
                    </div>
                    <div class="conf-kpi-icon" style="background: #ecfdf5; color: #059669;">
                        <i class="fa fa-calendar-check"></i>
                    </div>
                </div>
            </div>

            <!-- Card 3: Today's Cancelled -->
            <div class="col-md-2 col-sm-4 col-xs-6" style="width: 20%;">
                <div class="conf-kpi-card">
                    <div>
                        <div class="conf-kpi-lbl" style="color: #dc2626;">আজকে বাতিল</div>
                        <div class="conf-kpi-val" id="kpi-today-cancelled-val" style="color: #b91c1c;"><?= (int) $today_cancelled ?> <span style="font-size: 12px; font-weight: normal; color: #64748b;">টি</span></div>
                        <span class="conf-kpi-sub">গ্রাহক বা ফেক বাতিল</span>
                    </div>
                    <div class="conf-kpi-icon" style="background: #fee2e2; color: #dc2626;">
                        <i class="fa fa-times-circle"></i>
                    </div>
                </div>
            </div>

            <!-- Card 4: Success Rate -->
            <div class="col-md-2 col-sm-4 col-xs-6" style="width: 20%;">
                <div class="conf-kpi-card">
                    <div>
                        <div class="conf-kpi-lbl" style="color: #7c3aed;">কল সাকসেস রেট</div>
                        <div class="conf-kpi-val" id="kpi-success-rate-val" style="color: #6d28d9;"><?= (int) $today_success_rate ?>%</div>
                        <span class="conf-kpi-sub">আজকের কনভার্সন</span>
                    </div>
                    <div class="conf-kpi-icon" style="background: #f5f3ff; color: #7c3aed;">
                        <i class="fa fa-line-chart"></i>
                    </div>
                </div>
            </div>

            <!-- Card 5: Queue Value -->
            <div class="col-md-2 col-sm-4 col-xs-6" style="width: 20%;">
                <div class="conf-kpi-card">
                    <div>
                        <div class="conf-kpi-lbl" style="color: #2563eb;">কিউ অর্ডার ভ্যালু</div>
                        <div class="conf-kpi-val" style="color: #1d4ed8;"><?= salesos_format_number($queue_value) ?> <span style="font-size: 11px; font-weight: normal; color: #64748b;">BDT</span></div>
                        <span class="conf-kpi-sub">মোট পেন্ডিং মূল্য</span>
                    </div>
                    <div class="conf-kpi-icon" style="background: #eff6ff; color: #2563eb;">
                        <i class="fa fa-money"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Collapsible Agent Performance (Last 7 Days) ── -->
        <?php if (!empty($stats['agents'])): ?>
            <div class="panel panel-default" style="border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: none; margin-bottom: 15px;">
                <div class="panel-heading" style="background: #f8fafc; padding: 10px 16px; cursor: pointer;" data-toggle="collapse" data-target="#agent_stats_collapse">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span class="bold text-muted" style="font-size: 12px;">
                            <i class="fa fa-users text-primary"></i> টেলিসেলস এজেন্ট পারফরম্যান্স (গত ৭ দিনের হিস্ট্রি)
                        </span>
                        <span class="text-muted" style="font-size: 11px;">
                            ক্লিক করে দেখুন / লুকান <i class="fa fa-chevron-down"></i>
                        </span>
                    </div>
                </div>
                <div id="agent_stats_collapse" class="panel-collapse collapse">
                    <div class="panel-body no-padding">
                        <div class="table-responsive">
                            <table class="table table-bordered no-margin" style="font-size: 12px;">
                                <thead>
                                    <tr style="background: #f8fafc;">
                                        <th>Agent Name</th>
                                        <th class="text-center" style="width: 130px;">Confirmed</th>
                                        <th class="text-center" style="width: 130px;">Cancelled</th>
                                        <th style="width: 200px;">Success Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats['agents'] as $agent): ?>
                                        <tr>
                                            <td><strong><?= e($agent['name']) ?></strong></td>
                                            <td class="text-center text-success bold"><?= (int) $agent['confirmed'] ?></td>
                                            <td class="text-center text-danger bold"><?= (int) $agent['cancelled'] ?></td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <div class="progress" style="flex: 1; height: 8px; margin: 0; background: #f1f5f9; border-radius: 4px;">
                                                        <div class="progress-bar progress-bar-success" style="width: <?= (int)$agent['rate'] ?>%; border-radius: 4px;"></div>
                                                    </div>
                                                    <span class="bold" style="font-size: 11px; width: 35px;"><?= (int) $agent['rate'] ?>%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- ── Filter & Search Toolbar ── -->
        <div class="conf-filter-toolbar">
            <!-- Search Input -->
            <div style="flex: 1; min-width: 220px; position: relative;">
                <i class="fa fa-search" style="position: absolute; left: 10px; top: 9px; color: #94a3b8;"></i>
                <input type="text" id="queue-search-input" class="form-control input-sm" 
                       placeholder="গ্রাহকের নাম, ফোন নম্বর, বা অর্ডার # দিয়ে খুঁজুন..." 
                       style="padding-left: 28px; border-radius: 6px; border-color: #cbd5e1;">
            </div>

            <!-- Channel Filter -->
            <div style="width: 140px;">
                <select id="queue-channel-filter" class="form-control input-sm" style="border-radius: 6px; border-color: #cbd5e1;">
                    <option value="">সব চ্যানেল</option>
                    <option value="woo">WooCommerce</option>
                    <option value="manual">Manual / Direct</option>
                    <option value="pos">POS Counter</option>
                    <option value="pos_online">POS Online (ডেলিভারি)</option>
                </select>
            </div>

            <!-- Risk Filter -->
            <div style="width: 140px;">
                <select id="queue-risk-filter" class="form-control input-sm" style="border-radius: 6px; border-color: #cbd5e1;">
                    <option value="">সব রিস্ক লেভেল</option>
                    <option value="high">High Risk (উচ্চ ঝুঁকি)</option>
                    <option value="safe">Safe (নিরাপদ)</option>
                </select>
            </div>

            <!-- Sort By -->
            <div style="width: 140px;">
                <select id="queue-sort-filter" class="form-control input-sm" style="border-radius: 6px; border-color: #cbd5e1;">
                    <option value="oldest">পুরানো আগে (FIFO)</option>
                    <option value="newest">নতুন আগে</option>
                </select>
            </div>

            <!-- Queue Counter Badge -->
            <div>
                <span class="label label-default" id="visible-counter-badge" style="font-size: 11px; padding: 6px 10px; border-radius: 6px;">
                    প্রদর্শিত: <?= count($orders) ?> টি অর্ডার
                </span>
            </div>
        </div>

        <!-- ── Main Confirmation Queue Panel ── -->
        <div class="panel_s" style="border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
            <div class="panel-body no-padding">

                <?php if (empty($orders)): ?>
                    <div style="padding: 50px 20px; text-align: center; color: #64748b;" id="queue-empty-state">
                        <div style="width: 60px; height: 60px; border-radius: 50%; background: #ecfdf5; color: #059669; display: flex; align-items: center; justify-content: center; font-size: 26px; margin: 0 auto 15px auto;">
                            <i class="fa fa-check"></i>
                        </div>
                        <h4 class="bold" style="color: #0f172a; margin-bottom: 5px;">সব কল সম্পন্ন হয়েছে!</h4>
                        <p class="no-margin" style="font-size: 13px;">বর্তমানে কোনো অপেক্ষমান অর্ডার নেই। নতুন চ্যানেল অর্ডার আসলে তা এখানে যুক্ত হবে।</p>
                        <div style="margin-top: 15px;">
                            <button type="button" class="btn btn-default btn-sm" onclick="window.location.reload();">
                                <i class="fa fa-refresh"></i> কিউ রিলোড করুন
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table conf-table no-margin" id="confirmations-queue-table">
                            <thead>
                                <tr>
                                    <th style="width: 140px;">Order & Channel</th>
                                    <th style="width: 230px;">Customer & 1-Click Dial</th>
                                    <th>Ordered Items</th>
                                    <th style="width: 120px;">Value</th>
                                    <th style="width: 125px; text-align: center;">COD Risk (BDCourier)</th>
                                    <th style="width: 280px;">Call Outcome & Action Desk</th>
                                </tr>
                            </thead>
                            <tbody id="queue-table-tbody">
                                <?php foreach ($orders as $order): ?>
                                    <?php 
                                        $ch = strtolower($order['channel'] ?? '');
                                        $clean_phone = preg_replace('/[^0-9]/', '', (string)$order['customer_phone']);
                                        if (strpos($clean_phone, '88') !== 0 && strlen($clean_phone) === 11) {
                                            $clean_phone = '88' . $clean_phone;
                                        }

                                        // WhatsApp confirmation greeting template
                                        $order_ref_label = '#' . ($order['channel_ref_id'] ?: $order['id']);
                                        $wa_message = "আসসালামু আলাইকুম " . $order['customer_name'] . ",\nআপনার শপিং অর্ডারের (" . $order_ref_label . ") কনফার্মেশনের জন্য যোগাযোগ করছি।\nমোট মূল্য: " . salesos_format_number($order['total']) . " BDT (" . strtoupper($order['payment_method'] ?: 'COD') . ")।\nঅনুগ্রহ করে অর্ডারটি কনফার্ম করুন। ধন্যবাদ!";
                                        $wa_url = "https://wa.me/" . $clean_phone . "?text=" . urlencode($wa_message);

                                        // Fraud risk normalization
                                        $fraud_level = strtolower($order['fraud_risk'] ?? '');
                                        $is_high_risk = ($fraud_level === 'high_risk' || $fraud_level === 'red' || ((float)($order['fraud_ratio'] ?? 100) < 60 && !empty($order['fraud_risk'])));
                                        $risk_data_attr = $is_high_risk ? 'high' : 'safe';

                                        // Searchable text for instant JS filter
                                        $search_haystack = strtolower(
                                            $order['id'] . ' ' . 
                                            $order['channel_ref_id'] . ' ' . 
                                            $order['customer_name'] . ' ' . 
                                            $order['customer_phone'] . ' ' . 
                                            $order['customer_address']
                                        );
                                    ?>
                                    <tr class="order-row" 
                                        id="order-row-<?= (int)$order['id'] ?>" 
                                        data-id="<?= (int)$order['id'] ?>"
                                        data-channel="<?= e($ch) ?>"
                                        data-risk="<?= $risk_data_attr ?>"
                                        data-search="<?= e($search_haystack) ?>">
                                        
                                        <!-- 1. Order & Channel -->
                                        <td>
                                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                                                <strong style="font-size: 13px; color: #0f172a;"><?= $order_ref_label ?></strong>
                                                <button type="button" class="btn btn-default btn-xs view-order-details-btn" data-id="<?= $order['id'] ?>" title="সম্পূর্ণ বিবরণ দেখুন" style="padding: 1px 5px;">
                                                    <i class="fa fa-eye text-primary"></i>
                                                </button>
                                            </div>
                                            <div style="margin-bottom: 3px;">
                                                <?php if ($ch === 'woo'): ?>
                                                    <span class="pill-channel-woo"><i class="fa fa-shopping-cart"></i> WOO</span>
                                                <?php elseif ($ch === 'pos'): ?>
                                                    <span class="pill-channel-pos"><i class="fa fa-desktop"></i> POS</span>
                                                <?php elseif ($ch === 'pos_online'): ?>
                                                    <span class="pill-channel-pos-online"><i class="fa fa-truck"></i> POS ONLINE</span>
                                                <?php else: ?>
                                                    <span class="pill-channel-manual"><i class="fa fa-phone"></i> <?= strtoupper(e($ch ?: 'MANUAL')) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted display-block" style="font-size: 11px;">
                                                <i class="fa fa-clock text-muted"></i> <?= date('d M, h:i A', strtotime($order['created_at'] ?: $order['order_date'])) ?>
                                            </small>
                                        </td>

                                        <!-- 2. Customer & 1-Click Dialing Desk -->
                                        <td>
                                            <strong style="color: #0f172a; font-size: 13px; display: block; margin-bottom: 4px;">
                                                <?= e($order['customer_name']) ?>
                                            </strong>
                                            
                                            <!-- Dialing buttons -->
                                            <div style="display: flex; gap: 5px; align-items: center; margin-bottom: 6px;">
                                                <?php if (!empty($order['customer_phone'])): ?>
                                                    <a href="tel:<?= e($order['customer_phone']) ?>" class="btn-call-quick" title="সরাসরি কল করুন">
                                                        <i class="fa fa-phone"></i> <?= e($order['customer_phone']) ?>
                                                    </a>
                                                    <?php if (!empty($clean_phone)): ?>
                                                        <a href="<?= $wa_url ?>" target="_blank" class="btn-whatsapp-quick" title="WhatsApp এ কনফার্মেশন মেসেজ পাঠান">
                                                            <i class="fa-brands fa-whatsapp"></i> Chat
                                                        </a>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="label label-danger" style="font-size: 10px;">ফোন নম্বর নেই</span>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Address -->
                                            <?php if (!empty($order['customer_address'])): ?>
                                                <small class="text-muted display-block" style="font-size: 11px; line-height: 1.3;">
                                                    <i class="fa fa-map-marker text-danger" style="margin-right: 2px;"></i> <?= e($order['customer_address']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>

                                        <!-- 3. Ordered Items -->
                                        <td>
                                            <?php if (empty($order['items'])): ?>
                                                <span class="text-muted">—</span>
                                            <?php else: ?>
                                                <?php foreach ($order['items'] as $item): ?>
                                                    <div style="margin-bottom: 6px; padding-bottom: 4px; border-bottom: 1px dashed #f1f5f9;">
                                                        <div style="display: flex; justify-content: space-between; align-items: baseline;">
                                                            <span style="font-size: 12px; font-weight: 600; color: #1e293b;">
                                                                <?= e($item['name']) ?>
                                                            </span>
                                                            <span class="badge" style="background: #f1f5f9; color: #334155; font-size: 11px; font-weight: 700;">
                                                                × <?= (float) $item['qty'] ?>
                                                            </span>
                                                        </div>
                                                        <?php if (!empty($item['sku'])): ?>
                                                            <small class="text-muted" style="font-size: 10px; font-family: monospace;">SKU: <?= e($item['sku']) ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </td>

                                        <!-- 4. Value & Payment -->
                                        <td>
                                            <strong style="font-size: 13px; color: #0f172a; display: block;">
                                                <?= salesos_format_number($order['total']) ?> <span style="font-size: 10px; color: #64748b; font-weight: normal;">BDT</span>
                                            </strong>
                                            <span class="label label-default" style="font-size: 10px; text-transform: uppercase; margin-top: 3px; display: inline-block;">
                                                <?= e($order['payment_method'] ?: 'COD') ?>
                                            </span>
                                        </td>

                                        <!-- 5. COD History & BDCourier Risk -->
                                        <td style="text-align: center;">
                                            <?php if ($is_high_risk): ?>
                                                <span class="label" style="background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 12px; display: inline-block;">
                                                    <i class="fa fa-shield"></i> HIGH RISK
                                                </span>
                                                <small class="display-block bold text-danger" style="font-size: 10px; margin-top: 3px;">
                                                    <?= round((float)$order['fraud_ratio']) ?>% সাকসেস রেশিও
                                                </small>
                                            <?php elseif (!empty($order['fraud_risk'])): ?>
                                                <span class="label" style="background: #dcfce7; color: #166534; border: 1px solid #86efac; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 12px; display: inline-block;">
                                                    <i class="fa fa-check"></i> SAFE
                                                </span>
                                                <small class="display-block text-success bold" style="font-size: 10px; margin-top: 3px;">
                                                    <?= round((float)$order['fraud_ratio']) ?>% সাকসেস রেশিও
                                                </small>
                                            <?php else: ?>
                                                <span class="label" style="background: #f1f5f9; color: #64748b; font-size: 10px;">Not Checked</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- 6. Call Outcome & Fast Action Desk -->
                                        <td>
                                            <!-- Quick Note Chips (1-Click Insertion) -->
                                            <div class="conf-chips-container">
                                                <button type="button" class="btn-preset-chip" data-target="#note-<?= $order['id'] ?>" data-text="সাইজ ও কালার নিশ্চিত">✓ সাইজ/কালার ঠিক</button>
                                                <button type="button" class="btn-preset-chip" data-target="#note-<?= $order['id'] ?>" data-text="ঠিকানা পরিবর্তন">📍 ঠিকানা পরিবর্তন</button>
                                                <button type="button" class="btn-preset-chip" data-target="#note-<?= $order['id'] ?>" data-text="ফোন ধরেনি / ব্যস্ত">☎ ফোন ধরেনি</button>
                                                <button type="button" class="btn-preset-chip" data-target="#note-<?= $order['id'] ?>" data-text="পরে কল দিতে বলেছেন">⏰ পরে কল</button>
                                                <button type="button" class="btn-preset-chip" data-target="#note-<?= $order['id'] ?>" data-text="গ্রাহক বাতিল করেছেন">✗ বাতিল</button>
                                            </div>

                                            <!-- Note input box -->
                                            <input type="text" name="note" id="note-<?= $order['id'] ?>" 
                                                   class="form-control input-sm order-call-note" 
                                                   placeholder="কলের নোট লিখুন বা চিপে চাপুন..." 
                                                   style="border-radius: 6px; font-size: 11px; margin-bottom: 6px; border-color: #cbd5e1;">

                                            <!-- 3 Action Triggers -->
                                            <div class="conf-action-group">
                                                <button type="button" class="btn btn-sm btn-success btn-call-action" 
                                                        data-id="<?= $order['id'] ?>" data-outcome="confirmed" 
                                                        title="অর্ডার নিশ্চিত করুন এবং ওয়্যারহাউজে পাঠান" style="flex: 1;">
                                                    <i class="fa fa-check"></i> কনফার্ম
                                                </button>
                                                <button type="button" class="btn btn-sm btn-warning btn-call-action" 
                                                        data-id="<?= $order['id'] ?>" data-outcome="no_answer" 
                                                        title="অর্ডার বন্ধ না করে কল চেষ্টার হিস্ট্রি রাখুন">
                                                    <i class="fa fa-phone"></i> ধরেনি
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger btn-call-action" 
                                                        data-id="<?= $order['id'] ?>" data-outcome="cancelled" 
                                                        title="অর্ডার বাতিল করুন এবং স্টক ফিরিয়ে দিন">
                                                    <i class="fa fa-times"></i> বাতিল
                                                </button>
                                            </div>
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
</div>

<!-- Premium Order Details Modal (Shared for Quick View) -->
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
                    <div class="col-md-6 mbot15">
                        <div class="panel panel-default" style="border: 1px solid #e2e8f0; border-radius: 4px; box-shadow: none; margin-bottom: 0;">
                            <div class="panel-body">
                                <h5 class="bold text-muted no-margin mbot15"><i class="fa fa-user"></i> Customer Details</h5>
                                <p class="mbot8"><strong>Name:</strong> <span id="modal-cust-name">-</span></p>
                                <p class="mbot8"><strong>Phone:</strong> <span id="modal-cust-phone">-</span></p>
                                <p class="mbot8"><strong>Email:</strong> <span id="modal-cust-email">-</span></p>
                                <p class="mbot0"><strong>Address:</strong> <span id="modal-cust-address">-</span></p>
                            </div>
                        </div>
                    </div>
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

                <!-- Items Table -->
                <div class="panel panel-default" style="border: 1px solid #e2e8f0; border-radius: 4px; box-shadow: none;">
                    <div class="panel-heading" style="background: #ffffff;">
                        <h5 class="bold no-margin text-primary"><i class="fa fa-list"></i> Order Items</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered no-margin" style="font-size: 12px;">
                            <thead>
                                <tr style="background: #f8fafc;">
                                    <th>Item</th>
                                    <th>SKU</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-right">Price</th>
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody id="modal-items-tbody">
                                <tr><td colspan="5" class="text-center text-muted">No items</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" id="modal-print-invoice-btn" target="_blank" class="btn btn-primary"><i class="fa fa-print"></i> Print A4 Invoice</a>
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {

    // ── 1. Quick Note Preset Chip Click ──
    $(document).on('click', '.btn-preset-chip', function(e) {
        e.preventDefault();
        var targetInput = $(this).data('target');
        var text = $(this).data('text');
        var input = $(targetInput);
        if (input.length) {
            var current = input.val().trim();
            if (current === '') {
                input.val(text);
            } else if (current.indexOf(text) === -1) {
                input.val(current + ' | ' + text);
            }
            input.focus();
        }
    });

    // ── 2. High-Speed AJAX Action Desk (Confirm / No Answer / Cancel) ──
    $(document).on('click', '.btn-call-action', function(e) {
        e.preventDefault();
        var btn = $(this);
        var orderId = btn.data('id');
        var outcome = btn.data('outcome');
        var row = $('#order-row-' + orderId);
        var noteInput = $('#note-' + orderId);
        var note = noteInput.val() ? noteInput.val().trim() : '';

        // Prevent double submit
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

        $.ajax({
            url: admin_url + 'salesos/confirm_order/' + orderId,
            type: 'POST',
            dataType: 'json',
            data: {
                outcome: outcome,
                note: note,
                <?= $this->security->get_csrf_token_name() ?>: '<?= $this->security->get_csrf_hash() ?>'
            },
            success: function(res) {
                if (res.success) {
                    alert_float('success', res.message);

                    // Update Top Counter
                    if (res.waiting !== undefined) {
                        $('#kpi-waiting-val').html(res.waiting + ' <span style="font-size: 12px; font-weight: normal; color: #64748b;">টি</span>');
                    }

                    if (outcome === 'confirmed') {
                        // Update today confirmed counter
                        var curConf = parseInt($('#kpi-today-confirmed-val').text()) || 0;
                        $('#kpi-today-confirmed-val').html((curConf + 1) + ' <span style="font-size: 12px; font-weight: normal; color: #64748b;">টি</span>');

                        // Animate & Remove Row
                        row.addClass('row-success-flash');
                        setTimeout(function() {
                            row.fadeOut(350, function() {
                                $(this).remove();
                                checkEmptyQueue();
                            });
                        }, 300);
                    } else if (outcome === 'cancelled') {
                        // Update today cancelled counter
                        var curCanc = parseInt($('#kpi-today-cancelled-val').text()) || 0;
                        $('#kpi-today-cancelled-val').html((curCanc + 1) + ' <span style="font-size: 12px; font-weight: normal; color: #64748b;">টি</span>');

                        // Animate & Remove Row
                        row.addClass('row-danger-flash');
                        setTimeout(function() {
                            row.fadeOut(350, function() {
                                $(this).remove();
                                checkEmptyQueue();
                            });
                        }, 300);
                    } else if (outcome === 'no_answer' || outcome === 'call_later') {
                        // Keep order in queue, flash yellow and show badge
                        row.addClass('row-warning-flash');
                        noteInput.val('');
                        btn.prop('disabled', false).html('<i class="fa fa-phone"></i> ধরেনি');
                        setTimeout(function() {
                            row.removeClass('row-warning-flash');
                        }, 1000);
                    }
                } else {
                    alert_float('danger', res.message || 'অ্যাকশন সম্পন্ন করা যায়নি।');
                    btn.prop('disabled', false).html(outcome === 'confirmed' ? '<i class="fa fa-check"></i> কনফার্ম' : (outcome === 'cancelled' ? '<i class="fa fa-times"></i> বাতিল' : '<i class="fa fa-phone"></i> ধরেনি'));
                }
            },
            error: function() {
                alert_float('danger', 'সার্ভার সংযোগে ত্রুটি হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।');
                btn.prop('disabled', false).html(outcome === 'confirmed' ? '<i class="fa fa-check"></i> কনফার্ম' : (outcome === 'cancelled' ? '<i class="fa fa-times"></i> বাতিল' : '<i class="fa fa-phone"></i> ধরেনি'));
            }
        });
    });

    function checkEmptyQueue() {
        var remaining = $('#queue-table-tbody tr.order-row').length;
        $('#visible-counter-badge').text('প্রদর্শিত: ' + remaining + ' টি অর্ডার');
        if (remaining === 0) {
            window.location.reload();
        }
    }

    // ── 3. Real-time Search & Filter Engine ──
    function applyQueueFilters() {
        var searchText = ($('#queue-search-input').val() || '').toLowerCase().trim();
        var channel = $('#queue-channel-filter').val();
        var risk = $('#queue-risk-filter').val();

        var visibleCount = 0;
        $('#queue-table-tbody tr.order-row').each(function() {
            var row = $(this);
            var rowSearch = row.data('search') || '';
            var rowChannel = row.data('channel') || '';
            var rowRisk = row.data('risk') || '';

            var matchSearch = (searchText === '' || rowSearch.indexOf(searchText) !== -1);
            var matchChannel = (channel === '' || rowChannel === channel);
            var matchRisk = (risk === '' || rowRisk === risk);

            if (matchSearch && matchChannel && matchRisk) {
                row.show();
                visibleCount++;
            } else {
                row.hide();
            }
        });

        $('#visible-counter-badge').text('প্রদর্শিত: ' + visibleCount + ' টি অর্ডার');
    }

    $('#queue-search-input').on('keyup input', applyQueueFilters);
    $('#queue-channel-filter, #queue-risk-filter').on('change', applyQueueFilters);

    // Sort table rows (FIFO vs Newest)
    $('#queue-sort-filter').on('change', function() {
        var sortType = $(this).val();
        var rows = $('#queue-table-tbody tr.order-row').get();
        rows.sort(function(a, b) {
            var idA = parseInt($(a).data('id'));
            var idB = parseInt($(b).data('id'));
            return sortType === 'newest' ? (idB - idA) : (idA - idB);
        });
        $.each(rows, function(idx, row) {
            $('#queue-table-tbody').append(row);
        });
    });

    // ── 4. Order Details Modal AJAX Handler ──
    $('.view-order-details-btn').on('click', function() {
        var orderId = $(this).data('id');
        $('#modal-order-id').text(orderId);
        $('#modal-print-invoice-btn').attr('href', admin_url + 'salesos/print_invoice/' + orderId);
        $('#modal-items-tbody').html('<tr><td colspan="5" class="text-center text-muted"><i class="fa fa-spinner fa-spin"></i> Loading details...</td></tr>');
        $('#order_details_modal').modal('show');

        $.getJSON(admin_url + 'salesos/get_order_details_ajax/' + orderId, function(res) {
            if (res.success && res.order) {
                var o = res.order;
                $('#modal-cust-name').text(o.customer_name || '-');
                $('#modal-cust-phone').text(o.customer_phone || '-');
                $('#modal-cust-email').text(o.customer_email || '-');
                $('#modal-cust-address').text(o.customer_address || '-');
                $('#modal-order-channel').text(o.channel ? o.channel.toUpperCase() : '-');
                $('#modal-order-ref').text(o.channel_ref_id || ('#' + o.id));
                $('#modal-order-payment').text(o.payment_method || 'COD');
                $('#modal-order-date').text(o.order_date || o.created_at || '-');

                var itemsHtml = '';
                if (res.items && res.items.length) {
                    res.items.forEach(function(item) {
                        var lineTot = parseFloat(item.price || 0) * parseFloat(item.qty || 1);
                        itemsHtml += '<tr>';
                        itemsHtml += '  <td><strong>' + (item.name || '-') + '</strong></td>';
                        itemsHtml += '  <td>' + (item.sku || '-') + '</td>';
                        itemsHtml += '  <td class="text-center">' + item.qty + '</td>';
                        itemsHtml += '  <td class="text-right">' + parseFloat(item.price || 0).toFixed(2) + '</td>';
                        itemsHtml += '  <td class="text-right bold">' + lineTot.toFixed(2) + ' BDT</td>';
                        itemsHtml += '</tr>';
                    });
                } else {
                    itemsHtml = '<tr><td colspan="5" class="text-center text-muted">No items found.</td></tr>';
                }
                $('#modal-items-tbody').html(itemsHtml);
            }
        });
    });

});
</script>
</body>
</html>
