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
                            <div class="col-md-12">
                                <div class="pull-right">
                                    <?php if (staff_can('create', 'purchases') && !empty($suppliers) && !empty($products)): ?>
                                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#po-modal">
                                            <i class="fa fa-plus"></i> Create Purchase Order
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <h4 class="no-margin bold font-medium text-primary">Purchase Orders</h4>
                                <span class="text-muted">Procure products from suppliers and automatically restock warehouses.</span>
                            </div>
                        </div>
                        <hr class="hr-panel-heading" />

                        <?php if (empty($suppliers) || empty($products)): ?>
                            <div class="alert alert-warning">
                                <strong><i class="fa fa-info-circle"></i> Prerequisites missing!</strong>
                                <p>You must have at least one supplier and one inventory product created first.</p>
                                <div style="margin-top:10px;">
                                    <a href="<?= admin_url('purchases/suppliers') ?>" class="btn btn-warning btn-sm">Manage Suppliers</a>
                                    <a href="<?= admin_url('inventory/products') ?>" class="btn btn-default btn-sm">Manage Products</a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Orders List -->
                        <?php if (empty($purchase_orders)): ?>
                            <p class="text-muted no-margin">No purchase orders found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped no-mtop">
                                    <thead>
                                        <tr>
                                            <th>PO ID</th>
                                            <th>Supplier</th>
                                            <th>Items Summary</th>
                                            <th>Total Cost</th>
                                            <th>Status</th>
                                            <th>Created By</th>
                                            <th>Order Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($purchase_orders as $po): ?>
                                            <tr>
                                                <td><strong>#<?= $po['id'] ?></strong></td>
                                                <td><strong><?= e($po['supplier_name']) ?></strong></td>
                                                <td>
                                                    <ul style="padding-left: 15px; margin: 0; font-size:12px;">
                                                        <?php foreach ($po['items'] as $item): ?>
                                                            <li><?= e($item['product_name']) ?> &times; <?= salesos_format_number($item['qty']) ?> (@<?= salesos_format_number($item['unit_cost']) ?> BDT)</li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </td>
                                                <td><strong><?= salesos_format_number($po['total']) ?> BDT</strong></td>
                                                <td>
                                                    <?php 
                                                        $status_class = 'default';
                                                        if ($po['status'] === 'ordered') $status_class = 'info';
                                                        if ($po['status'] === 'received') $status_class = 'success';
                                                        if ($po['status'] === 'cancelled') $status_class = 'danger';
                                                    ?>
                                                    <span class="label label-<?= $status_class ?>">
                                                        <?= strtoupper(e($po['status'])) ?>
                                                    </span>
                                                    <?php if ($po['received_at']): ?>
                                                        <br><small class="text-muted"><?= e($po['received_at']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= $po['firstname'] ? e($po['firstname'] . ' ' . $po['lastname']) : 'System' ?></td>
                                                <td><?= e($po['order_date']) ?></td>
                                                <td>
                                                    <?php if (staff_can('receive', 'purchases') && $po['status'] !== 'received'): ?>
                                                        <a href="<?= admin_url('purchases/receive_po/' . $po['id']) ?>" class="btn btn-success btn-xs" title="Receive stock & Log debit">
                                                            <i class="fa fa-check"></i> Receive Stock
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if (staff_can('delete', 'purchases') && $po['status'] !== 'received'): ?>
                                                        <a href="<?= admin_url('purchases/delete_po/' . $po['id']) ?>" class="btn btn-danger btn-xs _delete">
                                                            <i class="fa fa-remove"></i>
                                                        </a>
                                                    <?php endif; ?>
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
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="po-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Create Purchase Order</h4>
            </div>
            <?= form_open(admin_url('purchases/purchase_orders'), ['id' => 'po-form']) ?>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="supplier_id" class="control-label">Select Supplier</label>
                            <select name="supplier_id" class="form-control" required>
                                <option value="">Select Supplier...</option>
                                <?php foreach ($suppliers as $sup): ?>
                                    <option value="<?= $sup['id'] ?>"><?= e($sup['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="order_date" class="control-label">Order Date</label>
                            <input type="date" name="order_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                </div>

                <hr />
                <h4 style="font-weight:700; font-size:15px; margin-bottom:15px;"><i class="fa fa-barcode"></i> Purchase Order Line Items</h4>
                <div class="table-responsive">
                    <table class="table" id="po-items-table">
                        <thead>
                            <tr>
                                <th style="width: 50%;">Product SKU / Name</th>
                                <th style="width: 20%;">Qty</th>
                                <th style="width: 20%;">Unit Cost (BDT)</th>
                                <th style="width: 10%;"></th>
                            </tr>
                        </thead>
                        <tbody id="po-items-body">
                            <tr class="po-item-row">
                                <td>
                                    <select name="items[0][product_id]" class="form-control selectpicker-prod" required>
                                        <option value="">Select SKU Product...</option>
                                        <?php foreach ($products as $p): ?>
                                            <option value="<?= $p['id'] ?>"><?= e($p['name']) ?> (SKU: <?= e($p['sku']) ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="items[0][qty]" class="form-control input-qty" value="1.00" min="0.01" required>
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="items[0][unit_cost]" class="form-control input-cost" value="0.00" min="0.00" required>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-danger btn-sm remove-row-btn" disabled><i class="fa fa-trash"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mtop10">
                    <button type="button" class="btn btn-default btn-sm" id="btn-add-item-row">
                        <i class="fa fa-plus"></i> Add Line Row
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary">Save Draft PO</button>
            </div>
            <?= form_close() ?>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var addRowBtn = document.getElementById('btn-add-item-row');
    var itemsBody = document.getElementById('po-items-body');
    var rowIndex = 1;

    if (addRowBtn) {
        addRowBtn.addEventListener('click', function() {
            var newRow = document.createElement('tr');
            newRow.className = 'po-item-row';
            
            // Build the HTML template
            var productOptions = '<option value="">Select SKU Product...</option>';
            <?php foreach ($products as $p): ?>
                productOptions += '<option value="<?= $p['id'] ?>"><?= addslashes(e($p['name'])) ?> (SKU: <?= addslashes(e($p['sku'])) ?>)</option>';
            <?php endforeach; ?>

            newRow.innerHTML = '<td>' +
                '<select name="items[' + rowIndex + '][product_id]" class="form-control" required>' + productOptions + '</select>' +
                '</td>' +
                '<td>' +
                '<input type="number" step="0.01" name="items[' + rowIndex + '][qty]" class="form-control" value="1.00" min="0.01" required>' +
                '</td>' +
                '<td>' +
                '<input type="number" step="0.01" name="items[' + rowIndex + '][unit_cost]" class="form-control" value="0.00" min="0.00" required>' +
                '</td>' +
                '<td>' +
                '<button type="button" class="btn btn-danger btn-sm remove-row-btn"><i class="fa fa-trash"></i></button>' +
                '</td>';

            itemsBody.appendChild(newRow);
            rowIndex++;

            // Enable delete buttons if there's more than one row
            var deleteButtons = document.querySelectorAll('.remove-row-btn');
            deleteButtons.forEach(function(btn) {
                btn.removeAttribute('disabled');
            });
        });
    }

    // Handle removing rows
    itemsBody.addEventListener('click', function(e) {
        var btn = e.target.closest('.remove-row-btn');
        if (!btn) return;
        
        var rows = document.querySelectorAll('.po-item-row');
        if (rows.length > 1) {
            btn.closest('tr').remove();
            
            // If only one row remains, disable its delete button
            var remainingRows = document.querySelectorAll('.po-item-row');
            if (remainingRows.length === 1) {
                document.querySelector('.remove-row-btn').setAttribute('disabled', 'disabled');
            }
        }
    });
});
</script>
