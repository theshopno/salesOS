<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
if (!staff_can('view', 'salesos') || !salesos_ecommerce_enabled()) {
    return;
}
$CI = &get_instance();
$CI->load->model('salesos/salesos_model');
$d = $CI->salesos_model->get_dashboard_widget_data();
?>
<div class="widget relative" id="widget-<?php echo create_widget_id(); ?>" data-name="SalesOS: Operations Quick Launcher">
    <div class="panel_s" style="border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); margin-bottom: 20px; background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);">
        <div class="panel-body" style="padding: 12px 16px;">
            <div class="widget-dragger"></div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 34px; height: 34px; border-radius: 8px; background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 15px; box-shadow: 0 2px 6px rgba(79, 70, 229, 0.3);">
                        <i class="fa fa-rocket"></i>
                    </div>
                    <div>
                        <strong style="color: #0f172a; font-size: 13px; display: block;">SalesOS E-commerce Launchpad</strong>
                        <span class="text-muted" style="font-size: 11px;">দ্রুত সেলস কাউন্টার, কনফার্মেশন ও অর্ডার কিউ অ্যাকশন</span>
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <a href="<?php echo admin_url('pos'); ?>" class="btn btn-success btn-sm" style="font-weight: 600; font-size: 12px; border-radius: 6px; padding: 6px 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                        <i class="fa fa-desktop"></i> + New POS Sale
                    </a>

                    <a href="<?php echo admin_url('salesos/confirmations'); ?>" class="btn btn-warning btn-sm" style="font-weight: 600; font-size: 12px; border-radius: 6px; padding: 6px 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                        <i class="fa fa-phone"></i> Confirmations
                        <?php if ((int)$d['pending_count'] > 0): ?>
                            <span class="badge" style="background: #fff; color: #b45309; font-weight: bold; margin-left: 4px;">
                                <?php echo (int)$d['pending_count']; ?>
                            </span>
                        <?php endif; ?>
                    </a>

                    <a href="<?php echo admin_url('salesos/orders'); ?>" class="btn btn-primary btn-sm" style="font-weight: 600; font-size: 12px; border-radius: 6px; padding: 6px 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                        <i class="fa fa-shopping-cart"></i> All Orders
                    </a>

                    <a href="<?php echo admin_url('inventory/products'); ?>" class="btn btn-default btn-sm" style="font-weight: 600; font-size: 12px; border-radius: 6px; padding: 6px 12px; border-color: #cbd5e1;">
                        <i class="fa fa-cubes"></i> Inventory
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>
