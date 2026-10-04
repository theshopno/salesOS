<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
if (!staff_can('view', 'salesos') || !salesos_ecommerce_enabled()) {
    return;
}
$CI = &get_instance();
$CI->load->model('salesos/salesos_model');
$d = $CI->salesos_model->get_dashboard_widget_data();
$urgent_risk_orders = $d['urgent_risk_orders'] ?? [];
$low_stock_products = $d['low_stock_products'] ?? [];
$total_attention = count($urgent_risk_orders) + count($low_stock_products);
?>
<div class="widget relative" id="widget-<?php echo create_widget_id(); ?>" data-name="SalesOS: Action Center & Fraud Alert">
    <div class="panel_s" style="border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); margin-bottom: 20px;">
        <div class="panel-body" style="padding: 16px;">
            <div class="widget-dragger"></div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #f1f5f9;">
                <div>
                    <h4 class="no-margin bold" style="font-size: 14px; color: #0f172a; display: flex; align-items: center; gap: 6px;">
                        <i class="fa fa-bell text-danger"></i> অ্যাকশন সেন্টার (Attention Needed)
                    </h4>
                    <span class="text-muted" style="font-size: 11px;">ফ্রড অ্যালার্ট ও ইনভেন্টরি সতর্কতা</span>
                </div>
                <span class="badge" style="background: <?php echo $total_attention > 0 ? '#fee2e2; color: #991b1b;' : '#f1f5f9; color: #475569;'; ?> font-weight: 700; font-size: 11px;">
                    <?php echo $total_attention; ?>
                </span>
            </div>

            <!-- 1. Flagged Fraud Orders -->
            <div style="margin-bottom: 12px;">
                <span class="bold text-danger" style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 6px;">
                    <i class="fa fa-shield"></i> ফ্রড অ্যালার্ট অর্ডার (High Risk)
                </span>
                <?php if (empty($urgent_risk_orders)): ?>
                    <div style="padding: 8px 10px; background: #f8fafc; border-radius: 6px; font-size: 11px; color: #64748b;">
                        <i class="fa fa-check-circle text-success"></i> কোনো সন্দেহভাজন ফ্রড অর্ডার নেই।
                    </div>
                <?php else: ?>
                    <?php foreach ($urgent_risk_orders as $uo): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 6px 8px; background: #fff5f5; border: 1px solid #fed7d7; border-radius: 6px; margin-bottom: 5px;">
                            <div>
                                <strong style="color: #991b1b; font-size: 11px;">#<?php echo $uo['id']; ?> - <?php echo htmlspecialchars($uo['customer_name']); ?></strong>
                                <small class="display-block text-muted" style="font-size: 10px;">
                                    <?php echo htmlspecialchars($uo['customer_phone']); ?> | সাকসেস রেশিও: <strong><?php echo round((float)$uo['success_ratio']); ?>%</strong>
                                </small>
                            </div>
                            <a href="<?php echo admin_url('salesos/orders?search=' . $uo['id']); ?>" class="btn btn-danger btn-xs" style="font-size: 10px; padding: 2px 6px;">
                                Review
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <hr style="margin: 8px 0 10px 0; border-color: #f1f5f9;" />

            <!-- 2. Low Stock Alerts -->
            <div>
                <span class="bold text-warning" style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 6px;">
                    <i class="fa fa-exclamation-triangle"></i> ইনভেন্টরি স্টক অ্যালার্ট (Low Stock)
                </span>
                <?php if (empty($low_stock_products)): ?>
                    <div style="padding: 8px 10px; background: #f8fafc; border-radius: 6px; font-size: 11px; color: #64748b;">
                        <i class="fa fa-check-circle text-success"></i> সব পণ্যের পর্যাপ্ত স্টক রয়েছে।
                    </div>
                <?php else: ?>
                    <?php foreach ($low_stock_products as $lp): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 5px 8px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 4px;">
                            <div style="max-width: 70%;">
                                <span style="font-size: 11px; font-weight: 600; color: #1e293b; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php echo htmlspecialchars($lp['name']); ?>
                                </span>
                                <small class="text-muted" style="font-size: 10px;">
                                    <?php echo salesos_format_number($lp['rate']); ?> BDT
                                </small>
                            </div>
                            <span class="label" style="background: <?php echo (float)$lp['stock'] <= 0 ? '#fee2e2; color: #991b1b; border: 1px solid #fca5a5;' : '#fef3c7; color: #92400e; border: 1px solid #fde68a;'; ?> font-size: 10px; font-weight: 700;">
                                <?php echo (float)$lp['stock']; ?> PCS
                            </span>
                        </div>
                    <?php endforeach; ?>
                    <div style="text-align: right; margin-top: 5px;">
                        <a href="<?php echo admin_url('inventory/products'); ?>" style="font-size: 10px; color: #64748b; text-decoration: none;">
                            ইনভেন্টরি দেখুন &raquo;
                        </a>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>
