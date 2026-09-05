<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <!-- Left side: Form -->
            <div class="col-md-4">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin font-medium">
                            <i class="fa fa-sliders"></i> Stock Adjustment
                        </h4>
                        <hr class="hr-panel-heading" />
                        
                        <?= form_open(admin_url('inventory/adjustments'), ['id' => 'adjustment-form']) ?>

                        <div class="form-group">
                            <label for="product_id" class="control-label">Select Product</label>
                            <select name="product_id" id="adj_product_id" class="form-control" required>
                                <option value="">Select SKU Product...</option>
                                <?php foreach ($products as $prod): ?>
                                    <option value="<?= $prod['id'] ?>"><?= e($prod['name']) ?> (SKU: <?= e($prod['sku']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="qty" class="control-label">Adjustment Quantity</label>
                            <input type="number" step="0.01" name="qty" id="adj_qty" class="form-control" placeholder="Use negative for subtraction, e.g. -5" required>
                        </div>

                        <div class="form-group">
                            <label for="movement_type" class="control-label">Adjustment Type</label>
                            <select name="movement_type" id="adj_movement_type" class="form-control" required>
                                <option value="adjustment">Manual Adjustment</option>
                                <option value="purchase_in">Purchase Restock (In)</option>
                                <option value="sale_out">Sales Deduction (Out)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="note" class="control-label">Notes / Reason</label>
                            <textarea name="note" id="adj_note" class="form-control" rows="4" placeholder="Reason for adjustment, e.g. damaged stock, physical count variance..." required></textarea>
                        </div>

                        <div class="mtop15">
                            <button type="submit" class="btn btn-primary">Process Adjustment</button>
                        </div>
                        <?= form_close() ?>
                    </div>
                </div>
            </div>

            <!-- Right side: Ledger History -->
            <div class="col-md-8">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin font-medium">
                            <i class="fa fa-history"></i> Stock Ledger History
                        </h4>
                        <hr class="hr-panel-heading" />
                        
                        <?php if (empty($ledger)): ?>
                            <p class="text-muted no-margin">No stock movements recorded in the ledger yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped no-mtop">
                                    <thead>
                                        <tr>
                                            <th>Product / SKU</th>
                                            <th>Type</th>
                                            <th>Quantity</th>
                                            <th>Balance</th>
                                            <th>Reference</th>
                                            <th>Adjusted By</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ledger as $row): ?>
                                            <?php 
                                                $qty = (float) $row['qty'];
                                                $qty_formatted = ($qty > 0 ? '+' : '') . number_format($qty, 2);
                                                $label_class = ($qty > 0) ? 'success' : 'danger';
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?= e($row['product_name']) ?></strong>
                                                    <br><small class="text-muted">SKU: <?= e($row['product_sku']) ?></small>
                                                </td>
                                                <td>
                                                    <span class="label label-default"><?= strtoupper(e($row['movement_type'])) ?></span>
                                                </td>
                                                <td>
                                                    <span class="label label-<?= $label_class ?>">
                                                        <strong><?= $qty_formatted ?></strong>
                                                    </span>
                                                </td>
                                                <td><strong><?= number_format($row['balance_after'], 2) ?></strong></td>
                                                <td>
                                                    <?php if ($row['ref_type']): ?>
                                                        <span class="text-muted"><?= e($row['ref_type']) ?> #<?= e($row['ref_id']) ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">&mdash;</span>
                                                    <?php endif; ?>
                                                    <?php if ($row['note']): ?>
                                                        <br><small class="text-muted font-italic">"<?= e($row['note']) ?>"</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?= $row['firstname'] ? e($row['firstname'] . ' ' . $row['lastname']) : 'System' ?>
                                                </td>
                                                <td><small><?= e($row['created_at']) ?></small></td>
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

<?php init_tail(); ?>
