<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
if (!staff_can('view', 'salesos') || !salesos_ecommerce_enabled()) {
    return;
}
$CI = &get_instance();
$CI->load->model('salesos/salesos_model');
$d = $CI->salesos_model->get_dashboard_widget_data();
$courier_connected = $d['system_health']['courier'] ?? false;
?>
<div class="widget relative" id="widget-<?php echo create_widget_id(); ?>" data-name="SalesOS: Courier & Cashflow Summary">
    <div class="panel_s" style="border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); margin-bottom: 20px;">
        <div class="panel-body" style="padding: 16px;">
            <div class="widget-dragger"></div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #f1f5f9;">
                <div>
                    <h4 class="no-margin bold" style="font-size: 14px; color: #0f172a; display: flex; align-items: center; gap: 6px;">
                        <i class="fa fa-truck text-primary"></i> কুরিয়ার ও ক্যাশ ফ্লো সামারি
                    </h4>
                    <span class="text-muted" style="font-size: 11px;">পার্সেল ডেলিভারি ও কালেকশনযোগ্য COD</span>
                </div>
                <span class="label label-<?php echo $courier_connected ? 'success' : 'default'; ?>" style="font-size: 10px;">
                    <i class="fa fa-circle" style="font-size: 8px;"></i> <?php echo $courier_connected ? 'Steadfast Live' : 'Courier Offline'; ?>
                </span>
            </div>

            <!-- Metric Row: Booked vs Collectable COD -->
            <div class="row" style="margin: 0 -4px 10px -4px;">
                <div class="col-xs-6" style="padding: 0 4px;">
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; text-align: center;">
                        <span style="font-size: 10px; font-weight: 700; text-transform: uppercase; color: #64748b; display: block;">
                            বুকড পার্সেল
                        </span>
                        <strong style="font-size: 18px; color: #0f172a; line-height: 1.2; display: block; margin-top: 2px;">
                            <?php echo (int)$d['courier_booked_count']; ?>
                        </strong>
                        <small class="text-muted" style="font-size: 10px;">ডেলিভারির পথে</small>
                    </div>
                </div>
                <div class="col-xs-6" style="padding: 0 4px;">
                    <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 6px; padding: 10px 12px; text-align: center;">
                        <span style="font-size: 10px; font-weight: 700; text-transform: uppercase; color: #047857; display: block;">
                            আদায়যোগ্য COD
                        </span>
                        <strong style="font-size: 18px; color: #065f46; line-height: 1.2; display: block; margin-top: 2px;">
                            <?php echo salesos_format_number($d['collectable_cod']); ?>
                        </strong>
                        <small style="font-size: 10px; color: #047857;">কুরিয়ার রিসিভেবল</small>
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 10px; background: #f8fafc; border-radius: 6px; font-size: 11px;">
                <span class="text-muted">
                    <i class="fa fa-clock"></i> পেন্ডিং কনফার্মেশন: <strong><?php echo (int)$d['pending_count']; ?></strong> টি
                </span>
                <a href="<?php echo admin_url('salesos/orders'); ?>" style="font-weight: 600; color: #2563eb; text-decoration: none;">
                    অর্ডার ম্যানেজার &raquo;
                </a>
            </div>

        </div>
    </div>
</div>
