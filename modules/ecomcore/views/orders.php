<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        
                        <!-- Header -->
                        <div class="row mbot20">
                            <div class="col-md-6">
                                <h4 class="no-margin bold font-medium text-primary"><i class="fa fa-list"></i> E-commerce Synced Orders</h4>
                                <span class="text-muted">Browse, search, and manage all synced orders from WooCommerce, POS, and other channels.</span>
                            </div>
                            <div class="col-md-6 text-right">
                                <?php if (staff_can('settings', 'ecomcore')): ?>
                                    <a href="<?= admin_url('ecomcore/integrations') ?>" class="btn btn-default">
                                        <i class="fa fa-cogs"></i> Integrations
                                    </a>
                                <?php endif; ?>
                                <a href="<?= admin_url('ecomcore') ?>" class="btn btn-primary">
                                    <i class="fa fa-dashboard"></i> Dashboard
                                </a>
                            </div>
                        </div>
                        <hr class="hr-panel-separator" />

                        <!-- Filters Section -->
                        <?= form_open(admin_url('ecomcore/orders'), ['method' => 'get', 'id' => 'orders-filter-form']) ?>
                        <div class="row mbot20" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 15px 15px 5px 15px; margin: 0 0 20px 0;">
                            
                            <!-- Search -->
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="search" class="control-label">Search Order</label>
                                    <input type="text" name="search" id="search" class="form-control" placeholder="Search ID, customer name, phone, Ref ID..." value="<?= e($search ?? '') ?>">
                                </div>
                            </div>

                            <!-- Channel -->
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="channel" class="control-label">Channel</label>
                                    <select name="channel" id="channel" class="form-control">
                                        <option value="">All Channels</option>
                                        <option value="woo" <?= (isset($selected_channel) && $selected_channel === 'woo') ? 'selected' : '' ?>>WooCommerce (WOO)</option>
                                        <option value="pos" <?= (isset($selected_channel) && $selected_channel === 'pos') ? 'selected' : '' ?>>Point of Sale (POS)</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Status -->
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="status" class="control-label">Status</label>
                                    <select name="status" id="status" class="form-control">
                                        <option value="">All Statuses</option>
                                        <option value="pending" <?= (isset($selected_status) && $selected_status === 'pending') ? 'selected' : '' ?>>Pending</option>
                                        <option value="confirmed" <?= (isset($selected_status) && $selected_status === 'confirmed') ? 'selected' : '' ?>>Confirmed</option>
                                        <option value="cancelled" <?= (isset($selected_status) && $selected_status === 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="col-md-2" style="margin-top: 25px;">
                                <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-filter"></i> Filter</button>
                                <a href="<?= admin_url('ecomcore/orders') ?>" class="btn btn-default btn-block" style="margin-top: 5px;">Reset</a>
                            </div>

                        </div>
                        <?= form_close() ?>

                        <!-- Orders Table -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped no-mtop">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Customer</th>
                                        <th>Channel</th>
                                        <th>Reference ID</th>
                                        <th>Total Amount (BDT)</th>
                                        <th>Status</th>
                                        <th>Order Date</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($orders)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">No orders found matching the filter criteria.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td class="bold">#<?= $order['id'] ?></td>
                                                 <td>
                                                     <strong class="display-block"><?= e($order['customer_name']) ?></strong>
                                                     <small class="text-muted"><?= e($order['customer_phone'] ?: 'No Phone') ?></small>
                                                     <?php if (!empty($order['fraud_risk_level'])): ?>
                                                         <span class="label display-block mtop5 inline-block text-uppercase text-center" 
                                                               style="font-size: 8px; padding: 1px 4px; font-weight: normal; background-color: <?= e($order['fraud_risk_color']) ?>; color: white;" 
                                                               title="BDCourier Success Ratio: <?= e($order['fraud_success_ratio']) ?>%">
                                                              <?php
                                                              $raw_risk = $order['fraud_risk_level'];
                                                              $risk_norm = strtoupper(str_replace('_', ' ', $raw_risk));
                                                              if ($risk_norm === 'HIGH RISK' || $risk_norm === 'RED') {
                                                                  $risk_lbl = 'High Risk';
                                                              } elseif ($risk_norm === 'MEDIUM RISK' || $risk_norm === 'YELLOW' || $risk_norm === 'ORANGE') {
                                                                  $risk_lbl = 'Medium Risk';
                                                              } elseif ($risk_norm === 'NO RISK' || $risk_norm === 'SAFE' || $risk_norm === 'GREEN') {
                                                                  $risk_lbl = 'No Risk';
                                                              } else {
                                                                  $risk_lbl = ucwords(strtolower(str_replace('_', ' ', $raw_risk)));
                                                              }
                                                              echo e($risk_lbl);
                                                              ?> (<?= e(round((float)$order['fraud_success_ratio'])) ?>%)
                                                         </span>
                                                     <?php endif; ?>
                                                 </td>
                                                <td>
                                                    <span class="label label-default">
                                                        <?= strtoupper(e($order['channel'])) ?>
                                                    </span>
                                                </td>
                                                <td><?= e($order['channel_ref_id'] ?: '-') ?></td>
                                                <td><span class="text-semibold text-dark"><?= ecomcore_format_number($order['total']) ?> BDT</span></td>
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
                                                <td class="text-center" style="white-space: nowrap;">
                                                    <button class="btn btn-default btn-xs view-order-details-btn" data-id="<?= $order['id'] ?>" title="View Details">
                                                        <i class="fa fa-eye"></i> View
                                                    </button>
                                                    <?php if (empty($order['consignment_id'])): ?>
                                                        <button class="btn btn-success btn-xs open-courier-selection-btn" 
                                                                data-order-id="<?= $order['id'] ?>" 
                                                                data-customer-name="<?= e($order['customer_name']) ?>"
                                                                data-customer-phone="<?= e($order['customer_phone']) ?>"
                                                                data-customer-address="<?= e($order['customer_address'] ?? '') ?>"
                                                                data-order-total="<?= (float) $order['total'] ?>"
                                                                data-order-channel="<?= e($order['channel']) ?>"
                                                                data-order-note="<?= e($order['order_note'] ?? '') ?>"
                                                                title="Send with Courier">
                                                            <i class="fa fa-truck"></i> Send Courier
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="label label-info mleft5" style="display: inline-block; padding: 4px 6px;"><i class="fa fa-check"></i> Booked</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="row mtop15">
                            <div class="col-md-12 text-right">
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
window.ecomcore_remove_decimals_on_zero = '<?= get_option('remove_decimals_on_zero') ?: '0' ?>';
function ecomcore_format_number(number, decimals) {
    if (decimals === undefined) decimals = 2;
    if (window.ecomcore_remove_decimals_on_zero == '1') {
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
        $('#modal-print-invoice-btn').attr('href', admin_url + 'ecomcore/print_invoice/' + orderId);
        $('#modal-print-label-btn').attr('href', admin_url + 'ecomcore/print_label/' + orderId);
        $('#modal-items-tbody').html('<tr><td colspan="5" class="text-center text-muted"><i class="fa fa-spinner fa-spin"></i> Loading order details...</td></tr>');
        $('#order_details_modal').modal('show');

        // Fetch via AJAX
        $.getJSON(admin_url + 'ecomcore/get_order_details_ajax/' + orderId, function(res) {
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
                        itemsHtml += '<td class="text-right">' + ecomcore_format_number(item.unit_price, 2) + ' BDT</td>';
                        itemsHtml += '<td class="text-right"><strong>' + ecomcore_format_number(itemTotal, 2) + ' BDT</strong></td>';
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

                $('#modal-subtotal').text(ecomcore_format_number(subtotal, 2));
                $('#modal-shipping').text(ecomcore_format_number(shipping, 2));
                $('#modal-total').text(ecomcore_format_number(total, 2));

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
        $('#booking-ecomcore-order-id').val(orderId);
        
        $('#booking-rec-name').text(custName || 'Guest Customer');
        $('#booking-rec-phone').text(custPhone || '-');
        $('#booking-rec-address').text(custAddress || '-');

        // Note pre-fill
        $('#booking-notes').val(orderNote || '');

        // COD Amount defaults
        if (channel === 'pos') {
            $('#booking-cod-amount').val('0.00');
        } else {
            $('#booking-cod-amount').val(ecomcore_format_number(orderTotal, 2));
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

        $.getJSON(admin_url + 'ecomcore/fraudcheck_recheck_ajax/' + phone, function(res) {
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
                                   data-account-name="<?= e($acc['name']) ?>">
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
                                        <h4 class="bold no-margin" style="color: #1e293b; font-size: 16px;"><?= e($acc['name']) ?></h4>
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
                <input type="hidden" name="ecomcore_order_id" id="booking-ecomcore-order-id" value="">
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
