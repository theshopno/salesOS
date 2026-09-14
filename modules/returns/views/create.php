<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin bold font-medium text-primary mbot15">
                            <i class="fa fa-reply"></i> Create Return Order
                        </h4>
                        <hr class="hr-panel-separator" />

                        <?= form_open(admin_url('returns/create'), ['id' => 'create-return-form']) ?>

                        <!-- 1. Select Order -->
                        <div class="form-group">
                            <label for="salesos_order_id" class="control-label">Select Confirmed Order</label>
                            <select name="salesos_order_id" id="salesos_order_id" class="form-control selectpicker" data-live-search="true" required>
                                <option value="">Select Order...</option>
                                <?php foreach ($orders as $ord): ?>
                                    <option value="<?= $ord['id'] ?>">
                                        Order #<?= $ord['id'] ?> (Channel: <?= strtoupper($ord['channel']) ?> - Ref: <?= $ord['channel_ref_id'] ? $ord['channel_ref_id'] : 'None' ?>) - Total: <?= salesos_format_number($ord['total']) ?> BDT (Date: <?= _dt($ord['created_at']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Only confirmed/paid orders are eligible for return.</small>
                        </div>

                        <!-- 2. Order Items (Dynamic block loaded via AJAX) -->
                        <div id="order-items-wrapper" class="mtop20 mbot20" style="display: none;">
                            <h5 class="bold text-muted mbot15"><i class="fa fa-shopping-basket"></i> Select Items to Return</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered" id="return-items-table">
                                    <thead>
                                        <tr class="active">
                                            <th width="40%">Item / Product</th>
                                            <th width="20%">Eligible Qty</th>
                                            <th width="20%">Return Qty</th>
                                            <th width="20%">Condition</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Dynamically generated rows -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- 3. Return Details -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="requested_by" class="control-label">Requested By</label>
                                    <select name="requested_by" id="requested_by" class="form-control">
                                        <option value="customer">Customer</option>
                                        <option value="staff" selected>Staff / Cashier</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="refund_amount" class="control-label">Refund Amount (BDT)</label>
                                    <input type="number" step="0.01" min="0" name="refund_amount" id="refund_amount" class="form-control" value="0.00">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="reason" class="control-label">Reason for Return</label>
                            <input type="text" name="reason" id="reason" class="form-control" required placeholder="e.g. Defective product, Wrong item shipped, Customer mind change">
                        </div>

                        <div class="form-group">
                            <label for="status" class="control-label">Return Order Status</label>
                            <select name="status" id="status" class="form-control">
                                <option value="requested" selected>Requested (Pending Approval)</option>
                                <option value="received">Received (Process restock flow immediately)</option>
                            </select>
                            <small class="text-muted">Selecting "Received" will automatically update inventory stock ledger for sellable items.</small>
                        </div>

                        <div class="text-right mtop20">
                            <a href="<?= admin_url('returns') ?>" class="btn btn-default">Cancel</a>
                            <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Return</button>
                        </div>

                        <?= form_close() ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var orderSelect = document.getElementById('salesos_order_id');
    var itemsWrapper = document.getElementById('order-items-wrapper');
    var tableBody = document.querySelector('#return-items-table tbody');

    $(orderSelect).on('change', function() {
        var orderId = this.value;
        if (!orderId) {
            itemsWrapper.style.display = 'none';
            tableBody.innerHTML = '';
            return;
        }

        $.get('<?= admin_url('returns/get_order_details_ajax/') ?>' + orderId, function(response) {
            var data = JSON.parse(response);
            tableBody.innerHTML = '';

            if (!data.items || data.items.length === 0) {
                alert_float('warning', 'This order has no items eligible for return (already returned).');
                itemsWrapper.style.display = 'none';
                return;
            }

            data.items.forEach(function(item) {
                var row = document.createElement('tr');
                row.innerHTML = 
                    '<td>' +
                    '  <strong class="display-block">' + item.name + '</strong>' +
                    '  <small class="text-muted">SKU: ' + (item.sku || '-') + '</small>' +
                    '  <input type="hidden" name="order_item_id[]" value="' + item.id + '">' +
                    '</td>' +
                    '<td>' +
                    '  <strong>' + parseFloat(item.max_qty).toFixed(0) + '</strong>' +
                    '</td>' +
                    '<td>' +
                    '  <input type="number" name="qty[]" class="form-control input-sm" value="0" min="0" max="' + item.max_qty + '" style="width: 80px;" required>' +
                    '</td>' +
                    '<td>' +
                    '  <select name="condition_note[]" class="form-control input-sm">' +
                    '     <option value="sellable">Sellable (Restocks)</option>' +
                    '     <option value="damaged">Damaged (No Restock)</option>' +
                    '  </select>' +
                    '</td>';
                tableBody.appendChild(row);
            });

            itemsWrapper.style.display = 'block';
        });
    });
});
</script>
