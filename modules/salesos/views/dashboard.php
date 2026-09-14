<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <!-- Header -->
                <div class="row">
                    <div class="col-md-12 mbot20">
                        <div class="pull-right">
                            <?php if (staff_can('settings', 'salesos')): ?>
                                <a href="<?= admin_url('salesos/integrations') ?>" class="btn btn-primary">
                                    <i class="fa fa-cogs"></i> Manage Integrations
                                </a>
                            <?php endif; ?>
                        </div>
                        <h4 class="no-margin bold font-medium text-primary">E-commerce Suite Dashboard</h4>
                        <span class="text-muted">Master control panel for salesos family modules.</span>
                    </div>
                </div>

                <!-- Stats Grid (Modern Flexbox Style) -->
                <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
                    <div style="flex: 1; min-width: 150px;">
                        <div class="panel_s" style="margin-bottom: 0;">
                            <div class="panel-body text-center">
                                <h3 class="bold no-margin text-success"><?= salesos_format_number($total_sales) ?> BDT</h3>
                                <span class="text-muted text-uppercase font-medium" style="font-size: 11px;">Total Sales</span>
                            </div>
                        </div>
                    </div>
                    <div style="flex: 1; min-width: 150px;">
                        <div class="panel_s" style="margin-bottom: 0;">
                            <div class="panel-body text-center">
                                <h3 class="bold no-margin" style="color: #6366f1;"><?= salesos_format_number($total_revenue) ?> BDT</h3>
                                <span class="text-muted text-uppercase font-medium" style="font-size: 11px;">Total Revenue</span>
                            </div>
                        </div>
                    </div>
                    <div style="flex: 1; min-width: 150px;">
                        <div class="panel_s" style="margin-bottom: 0;">
                            <div class="panel-body text-center">
                                <h3 class="bold no-margin text-danger"><?= salesos_format_number($total_due) ?> BDT</h3>
                                <span class="text-muted text-uppercase font-medium" style="font-size: 11px;">Total Due</span>
                            </div>
                        </div>
                    </div>
                    <div style="flex: 1; min-width: 150px;">
                        <div class="panel_s" style="margin-bottom: 0;">
                            <div class="panel-body text-center">
                                <h3 class="bold no-margin text-primary"><?= (int) $total_orders ?></h3>
                                <span class="text-muted text-uppercase font-medium" style="font-size: 11px;">Total Orders</span>
                            </div>
                        </div>
                    </div>
                    <div style="flex: 1; min-width: 150px;">
                        <div class="panel_s" style="margin-bottom: 0;">
                            <div class="panel-body text-center">
                                <h3 class="bold no-margin text-info"><?= count($channels) ?></h3>
                                <span class="text-muted text-uppercase font-medium" style="font-size: 11px;">Active Channels</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Row -->
                <div class="row">
                    <!-- Recent Orders -->
                    <div class="col-md-8">
                        <div class="panel_s">
                            <div class="panel-body">
                                <h4 class="no-margin font-medium"><i class="fa fa-shopping-cart"></i> Recent Orders</h4>
                                <hr class="hr-panel-heading" />
                                
                                <?php if (empty($recent_orders)): ?>
                                    <p class="text-muted no-margin">No orders imported yet.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped no-mtop">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Customer</th>
                                                    <th>Channel</th>
                                                    <th>Ref ID</th>
                                                    <th>Total (BDT)</th>
                                                    <th>Status</th>
                                                    <th>Order Date</th>
                                                    <th class="text-center">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_orders as $order): ?>
                                                    <tr>
                                                        <td><?= $order['id'] ?></td>
                                                        <td>
                                                            <strong class="display-block"><?= e($order['customer_name']) ?></strong>
                                                            <small class="text-muted"><?= e($order['customer_phone'] ?: 'No Phone') ?></small>
                                                        </td>
                                                        <td>
                                                            <span class="label label-default">
                                                                <?= strtoupper(e($order['channel'])) ?>
                                                            </span>
                                                        </td>
                                                        <td><?= e($order['channel_ref_id'] ?: '-') ?></td>
                                                        <td><strong><?= salesos_format_number($order['total']) ?></strong></td>
                                                        <td>
                                                            <?php 
                                                                $status_class = 'info';
                                                                if ($order['status'] === 'confirmed') $status_class = 'success';
                                                                if ($order['status'] === 'cancelled') $status_class = 'danger';
                                                            ?>
                                                            <span class="label label-<?= $status_class ?>">
                                                                <?= strtoupper(e($order['status'])) ?>
                                                            </span>
                                                        </td>
                                                        <td><?= e($order['order_date']) ?></td>
                                                        <td class="text-center">
                                                            <button class="btn btn-default btn-xs view-order-details-btn" data-id="<?= $order['id'] ?>" title="View Details">
                                                                <i class="fa fa-eye"></i> View
                                                            </button>
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

                    <!-- Channel breakdown & Events -->
                    <div class="col-md-4">
                        <div class="panel_s mbot20">
                            <div class="panel-body">
                                <h4 class="no-margin font-medium"><i class="fa fa-pie-chart"></i> Channel Breakdown</h4>
                                <hr class="hr-panel-heading" />
                                
                                <?php if (empty($channels)): ?>
                                    <p class="text-muted text-center no-margin">No channel statistics available.</p>
                                <?php else: ?>
                                    <ul class="list-group no-margin">
                                        <?php foreach ($channels as $ch): ?>
                                            <li class="list-group-item">
                                                <span class="badge"><?= (int) $ch['count'] ?> orders (<?= salesos_format_number($ch['revenue']) ?> BDT)</span>
                                                <span class="bold"><?= strtoupper(e($ch['channel'])) ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="panel_s">
                            <div class="panel-body">
                                <h4 class="no-margin font-medium"><i class="fa fa-history"></i> Recent Activity Logs</h4>
                                <hr class="hr-panel-heading" />
                                
                                <?php if (empty($recent_events)): ?>
                                    <p class="text-muted no-margin">No integration events logged yet.</p>
                                <?php else: ?>
                                    <div class="activity-feed">
                                        <?php foreach ($recent_events as $event): ?>
                                            <div class="feed-item">
                                                <div class="date"><?= e($event['created_at']) ?></div>
                                                <div class="text">
                                                    <strong><?= e($event['event_type']) ?></strong>
                                                    &mdash; Entity ID: <?= e($event['entity_id']) ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
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
