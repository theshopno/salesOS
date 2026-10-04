<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
if (!staff_can('view', 'salesos') || !salesos_ecommerce_enabled()) {
    return;
}
$CI = &get_instance();
$CI->load->model('salesos/salesos_model');
$d = $CI->salesos_model->get_dashboard_widget_data();
$channels = $d['channels'] ?? [];
?>
<div class="widget relative" id="widget-<?php echo create_widget_id(); ?>" data-name="SalesOS: Channel Share & Funnel">
    <div class="panel_s" style="border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); margin-bottom: 20px;">
        <div class="panel-body" style="padding: 16px;">
            <div class="widget-dragger"></div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #f1f5f9;">
                <div>
                    <h4 class="no-margin bold" style="font-size: 14px; color: #0f172a; display: flex; align-items: center; gap: 6px;">
                        <i class="fa fa-pie-chart text-primary"></i> ওমনি-চ্যানেল শেয়ার ও ফানেল
                    </h4>
                    <span class="text-muted" style="font-size: 11px;">চ্যানেলভিত্তিক বিক্রয় অনুপাত</span>
                </div>
                <span class="badge" style="background: #f1f5f9; color: #475569; font-weight: 700; font-size: 11px;">
                    <?php echo salesos_format_number($d['total_sales']); ?> BDT
                </span>
            </div>

            <?php if (empty($channels)): ?>
                <p class="text-muted text-center" style="font-size: 12px; margin: 15px 0;">কোনো চ্যানেল ডেটা নেই।</p>
            <?php else: ?>
                <div style="margin-bottom: 12px;">
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
                            } elseif ($raw_ch === 'pos') { 
                                $ch_name = 'POS Counter'; 
                                $ch_icon = 'fa-desktop'; 
                                $bar_color = '#059669'; 
                            } elseif ($raw_ch === 'pos_online') { 
                                $ch_name = 'POS Online Delivery'; 
                                $ch_icon = 'fa-truck'; 
                                $bar_color = '#0284c7'; 
                            } elseif ($raw_ch === 'manual') { 
                                $ch_name = 'Manual / Direct'; 
                                $ch_icon = 'fa-phone'; 
                                $bar_color = '#ea580c'; 
                            }
                            $share = (float)$ch['share_percent'];
                        ?>
                        <div style="margin-bottom: 10px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px; font-size: 11px;">
                                <span style="font-weight: 600; color: #1e293b;">
                                    <i class="fa <?php echo $ch_icon; ?>" style="color: <?php echo $bar_color; ?>; margin-right: 4px;"></i> <?php echo $ch_name; ?>
                                </span>
                                <span style="font-weight: 700; color: #0f172a;">
                                    <?php echo salesos_format_number($ch['confirmed_revenue']); ?> BDT (<?php echo $share; ?>%)
                                </span>
                            </div>
                            <div class="progress" style="height: 8px; margin-bottom: 2px; background: #f1f5f9; border-radius: 4px; overflow: hidden;">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: <?php echo max(5, $share); ?>%; background-color: <?php echo $bar_color; ?>; border-radius: 4px;">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <hr style="margin: 10px 0 12px 0; border-color: #f1f5f9;" />

            <!-- Order Status Pipeline Funnel -->
            <div style="display: flex; justify-content: space-between; text-align: center; font-size: 11px;">
                <div style="flex: 1;">
                    <span style="font-weight: 700; color: #059669; font-size: 14px; display: block;"><?php echo (int)$d['confirmed_count']; ?></span>
                    <small class="text-muted">Confirmed</small>
                </div>
                <div style="flex: 1; border-left: 1px solid #f1f5f9;">
                    <span style="font-weight: 700; color: #4338ca; font-size: 14px; display: block;"><?php echo (int)$d['processing_count']; ?></span>
                    <small class="text-muted">Processing</small>
                </div>
                <div style="flex: 1; border-left: 1px solid #f1f5f9;">
                    <span style="font-weight: 700; color: #d97706; font-size: 14px; display: block;"><?php echo (int)$d['pending_count']; ?></span>
                    <small class="text-muted">Pending</small>
                </div>
                <div style="flex: 1; border-left: 1px solid #f1f5f9;">
                    <span style="font-weight: 700; color: #dc2626; font-size: 14px; display: block;"><?php echo (int)$d['cancelled_count']; ?></span>
                    <small class="text-muted">Cancelled</small>
                </div>
            </div>

        </div>
    </div>
</div>
