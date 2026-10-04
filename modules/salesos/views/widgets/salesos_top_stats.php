<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
if (!staff_can('view', 'salesos') || !salesos_ecommerce_enabled()) {
    return;
}
$CI = &get_instance();
$CI->load->model('salesos/salesos_model');
$d = $CI->salesos_model->get_dashboard_widget_data();
?>
<div class="widget relative" id="widget-<?php echo create_widget_id(); ?>" data-name="SalesOS: Executive KPI Cards">
    <div class="widget-dragger"></div>
    <style>
        .salesos-kpi-card {
            transition: all 0.2s ease-in-out;
            margin-bottom: 15px;
            position: relative;
            display: block;
            color: inherit !important;
            text-decoration: none !important;
            padding: 13px 15px;
            background: #fff;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
            height: 100%;
        }
        .salesos-kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.07) !important;
            border-color: #cbd5e1;
        }
        .salesos-kpi-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            flex-shrink: 0;
        }
    </style>
    <div class="row">
        <!-- 1. Today's Sales -->
        <div class="col-lg-2 col-md-4 col-sm-6 col-xs-12" style="margin-bottom: 12px;">
            <a href="<?php echo admin_url('salesos/orders'); ?>" class="top_stats_wrapper salesos-kpi-card">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                    <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.3px;">
                        আজকের সেলস
                    </span>
                    <span class="salesos-kpi-icon" style="background: #ecfdf5; color: #059669;">
                        <i class="fa fa-shopping-bag"></i>
                    </span>
                </div>
                <div style="font-size: 17px; font-weight: 800; color: #0f172a; line-height: 1.2; margin-bottom: 6px;">
                    <?php echo salesos_format_number($d['today_sales']); ?> <span style="font-size: 10px; font-weight: 600; color: #94a3b8;">BDT</span>
                </div>
                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 11px; color: #64748b;">
                    <span>অর্ডার: <strong style="color: #0f172a;"><?php echo (int)$d['today_orders']; ?></strong> টি</span>
                </div>
                <div style="height: 3px; border-radius: 2px; background: #10b981; margin-top: 8px; width: 100%;"></div>
            </a>
        </div>

        <!-- 2. This Month Sales -->
        <div class="col-lg-2 col-md-4 col-sm-6 col-xs-12" style="margin-bottom: 12px;">
            <a href="<?php echo admin_url('salesos/orders'); ?>" class="top_stats_wrapper salesos-kpi-card">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                    <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.3px;">
                        এই মাসের সেলস
                    </span>
                    <span class="salesos-kpi-icon" style="background: #f5f3ff; color: #7c3aed;">
                        <i class="fa fa-line-chart"></i>
                    </span>
                </div>
                <div style="font-size: 17px; font-weight: 800; color: #0f172a; line-height: 1.2; margin-bottom: 6px;">
                    <?php echo salesos_format_number($d['month_sales']); ?> <span style="font-size: 10px; font-weight: 600; color: #94a3b8;">BDT</span>
                </div>
                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 11px; color: #64748b;">
                    <span>অর্ডার: <strong style="color: #0f172a;"><?php echo (int)$d['month_orders']; ?></strong> টি</span>
                </div>
                <div style="height: 3px; border-radius: 2px; background: #8b5cf6; margin-top: 8px; width: 100%;"></div>
            </a>
        </div>

        <!-- 3. Total Confirmed & Revenue -->
        <div class="col-lg-2 col-md-4 col-sm-6 col-xs-12" style="margin-bottom: 12px;">
            <a href="<?php echo admin_url('salesos/orders?status=confirmed'); ?>" class="top_stats_wrapper salesos-kpi-card">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                    <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.3px;">
                        মোট কনফার্মড
                    </span>
                    <span class="salesos-kpi-icon" style="background: #eff6ff; color: #2563eb;">
                        <i class="fa fa-check-circle"></i>
                    </span>
                </div>
                <div style="font-size: 17px; font-weight: 800; color: #0f172a; line-height: 1.2; margin-bottom: 6px;">
                    <?php echo salesos_format_number($d['total_sales']); ?> <span style="font-size: 10px; font-weight: 600; color: #94a3b8;">BDT</span>
                </div>
                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 11px; color: #64748b;">
                    <span>আদায়: <strong style="color: #0f172a;"><?php echo salesos_format_number($d['total_revenue']); ?></strong></span>
                </div>
                <div style="height: 3px; border-radius: 2px; background: #3b82f6; margin-top: 8px; width: 100%;"></div>
            </a>
        </div>

        <!-- 4. Pending Call Queue -->
        <div class="col-lg-2 col-md-4 col-sm-6 col-xs-12" style="margin-bottom: 12px;">
            <a href="<?php echo admin_url('salesos/confirmations'); ?>" class="top_stats_wrapper salesos-kpi-card" style="<?php echo (int)$d['pending_count'] > 0 ? 'background: #fffdf7; border-color: #fde68a;' : ''; ?>">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                    <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #b45309; letter-spacing: 0.3px;">
                        অপেক্ষমান
                    </span>
                    <span class="salesos-kpi-icon" style="background: #fffbeb; color: #d97706;">
                        <i class="fa fa-phone"></i>
                    </span>
                </div>
                <div style="font-size: 17px; font-weight: 800; color: #b45309; line-height: 1.2; margin-bottom: 6px;">
                    <?php echo (int)$d['pending_count']; ?> <span style="font-size: 10px; font-weight: 600; color: #d97706;">টি</span>
                </div>
                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 11px; color: #b45309; font-weight: 600;">
                    <span>কল কনফার্ম করুন &raquo;</span>
                </div>
                <div style="height: 3px; border-radius: 2px; background: #f59e0b; margin-top: 8px; width: 100%;"></div>
            </a>
        </div>

        <!-- 5. Processing / Ready to Pack -->
        <div class="col-lg-2 col-md-4 col-sm-6 col-xs-12" style="margin-bottom: 12px;">
            <a href="<?php echo admin_url('salesos/orders?status=processing'); ?>" class="top_stats_wrapper salesos-kpi-card">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                    <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.3px;">
                        প্যাকিং ও প্রস্তুত
                    </span>
                    <span class="salesos-kpi-icon" style="background: #e0f2fe; color: #0284c7;">
                        <i class="fa fa-cubes"></i>
                    </span>
                </div>
                <div style="font-size: 17px; font-weight: 800; color: #0f172a; line-height: 1.2; margin-bottom: 6px;">
                    <?php echo (int)$d['ready_count']; ?> <span style="font-size: 10px; font-weight: 600; color: #94a3b8;">টি</span>
                </div>
                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 11px; color: #64748b;">
                    <span>কুরিয়ারের অপেক্ষায়</span>
                </div>
                <div style="height: 3px; border-radius: 2px; background: #0ea5e9; margin-top: 8px; width: 100%;"></div>
            </a>
        </div>

        <!-- 6. High Risk Alerts -->
        <div class="col-lg-2 col-md-4 col-sm-6 col-xs-12" style="margin-bottom: 12px;">
            <a href="<?php echo admin_url('salesos/orders?high_risk=1'); ?>" class="top_stats_wrapper salesos-kpi-card" style="<?php echo (int)$d['high_risk_count'] > 0 ? 'background: #fff8f8; border-color: #fecaca;' : ''; ?>">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                    <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #dc2626; letter-spacing: 0.3px;">
                        ফ্রড অ্যালার্ট
                    </span>
                    <span class="salesos-kpi-icon" style="background: #fef2f2; color: #dc2626;">
                        <i class="fa fa-shield"></i>
                    </span>
                </div>
                <div style="font-size: 17px; font-weight: 800; color: #b91c1c; line-height: 1.2; margin-bottom: 6px;">
                    <?php echo (int)$d['high_risk_count']; ?> <span style="font-size: 10px; font-weight: 600; color: #ef4444;">টি</span>
                </div>
                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 11px; color: #b91c1c;">
                    <span>BDCourier ফ্ল্যাগড</span>
                </div>
                <div style="height: 3px; border-radius: 2px; background: #ef4444; margin-top: 8px; width: 100%;"></div>
            </a>
        </div>
    </div>
</div>
