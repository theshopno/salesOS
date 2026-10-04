<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
if (!staff_can('view', 'salesos') || !salesos_ecommerce_enabled()) {
    return;
}
$CI = &get_instance();
$CI->load->model('salesos/salesos_model');
$d = $CI->salesos_model->get_dashboard_widget_data();
$recent_orders = $d['recent_orders'] ?? [];
?>
<div class="widget relative" id="widget-<?php echo create_widget_id(); ?>" data-name="SalesOS: Recent Live Orders Stream">
    <div class="panel_s" style="border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); margin-bottom: 20px;">
        <div class="panel-body" style="padding: 16px;">
            <div class="widget-dragger"></div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #f1f5f9;">
                <div>
                    <h4 class="no-margin bold" style="font-size: 14px; color: #0f172a; display: flex; align-items: center; gap: 6px;">
                        <i class="fa fa-clock text-primary"></i> সাম্প্রতিক লাইভ অর্ডারসমূহ (Live Orders Feed)
                    </h4>
                    <span class="text-muted" style="font-size: 11px;">সর্বশেষ ইনকামিং ও ওমনি-চ্যানেল অর্ডার প্রবাহ</span>
                </div>
                <a href="<?php echo admin_url('salesos/orders'); ?>" class="btn btn-default btn-xs" style="font-weight: 600; border-color: #cbd5e1; font-size: 11px;">
                    সব অর্ডার দেখুন (<?php echo (int)$d['total_orders']; ?>) &raquo;
                </a>
            </div>

            <?php if (empty($recent_orders)): ?>
                <div style="padding: 25px; text-align: center; color: #94a3b8;">
                    <i class="fa fa-shopping-basket fa-2x" style="color: #cbd5e1; margin-bottom: 6px;"></i>
                    <p class="no-margin" style="font-size: 12px;">কোনো অর্ডার পাওয়া যায়নি।</p>
                </div>
            <?php else: ?>
                <div class="table-responsive" style="margin: 0 -16px -16px -16px;">
                    <table class="table table-bordered table-striped no-margin" style="font-size: 12px; border-top: none;">
                        <thead>
                            <tr style="background: #f8fafc; font-size: 11px;">
                                <th style="width: 60px;"># ID</th>
                                <th>Customer</th>
                                <th style="width: 85px;">Channel</th>
                                <th style="width: 100px;">Amount</th>
                                <th style="width: 90px; text-align: center;">Status</th>
                                <th style="width: 100px;">Courier</th>
                                <th class="text-center" style="width: 70px;">Action</th>
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
                                    
                                    $status_bg = '#fef3c7';
                                    $status_col = '#b45309';
                                    if ($st === 'confirmed') { $status_bg = '#ecfdf5'; $status_col = '#047857'; }
                                    elseif ($st === 'processing') { $status_bg = '#e0e7ff'; $status_col = '#4338ca'; }
                                    elseif ($st === 'delivered') { $status_bg = '#f0fdf4'; $status_col = '#15803d'; }
                                    elseif ($st === 'cancelled') { $status_bg = '#fef2f2'; $status_col = '#b91c1c'; }
                                ?>
                                <tr>
                                    <td><strong>#<?php echo $order['id']; ?></strong></td>
                                    <td>
                                        <strong style="color: #0f172a; display: block; font-size: 12px;"><?php echo htmlspecialchars($order['customer_name']); ?></strong>
                                        <div style="display: flex; gap: 6px; align-items: center; margin-top: 2px;">
                                            <?php if (!empty($order['customer_phone'])): ?>
                                                <span class="text-muted" style="font-size: 11px;">
                                                    <i class="fa fa-phone" style="font-size: 9px;"></i> <?php echo htmlspecialchars($order['customer_phone']); ?>
                                                </span>
                                                <?php if (!empty($clean_phone)): ?>
                                                    <a href="https://wa.me/<?php echo htmlspecialchars($clean_phone); ?>" target="_blank" style="color: #25d366; font-size: 11px; text-decoration: none;" title="WhatsApp এ মেসেজ">
                                                        <i class="fa fa-whatsapp"></i> Chat
                                                    </a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($ch === 'woo'): ?>
                                            <span class="label" style="background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; font-size: 10px;">WOO</span>
                                        <?php elseif ($ch === 'pos'): ?>
                                            <span class="label" style="background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; font-size: 10px;">POS</span>
                                        <?php elseif ($ch === 'pos_online'): ?>
                                            <span class="label" style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-size: 10px;">POS ONLINE</span>
                                        <?php else: ?>
                                            <span class="label label-default" style="font-size: 10px;"><?php echo strtoupper(htmlspecialchars($ch ?: 'MANUAL')); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong style="color: #0f172a; font-size: 12px;"><?php echo salesos_format_number($order['total']); ?> BDT</strong>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="label" style="background: <?php echo $status_bg; ?>; color: <?php echo $status_col; ?>; font-size: 10px; font-weight: 700;">
                                            <?php echo strtoupper(htmlspecialchars($st)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($ch === 'pos'): ?>
                                            <span class="label" style="background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; font-size: 9px;">Counter</span>
                                        <?php elseif (!empty($order['consignment_id'])): ?>
                                            <span class="label" style="background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; font-size: 9px;">Booked</span>
                                        <?php else: ?>
                                            <span class="label label-warning" style="font-size: 9px;">Unbooked</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center" style="white-space: nowrap;">
                                        <a href="<?php echo admin_url('salesos/orders?search=' . $order['id']); ?>" class="btn btn-default btn-xs" title="View Order" style="padding: 2px 5px;">
                                            <i class="fa fa-eye text-primary"></i>
                                        </a>
                                        <a href="<?php echo admin_url('salesos/print_invoice/' . $order['id']); ?>" target="_blank" class="btn btn-default btn-xs" title="Print Invoice" style="padding: 2px 5px;">
                                            <i class="fa fa-print"></i>
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
