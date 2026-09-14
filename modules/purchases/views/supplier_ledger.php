<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <!-- Left Panel: Summary & Pay Form -->
            <div class="col-md-4">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin font-medium">
                            <i class="fa fa-info-circle"></i> Supplier Summary
                        </h4>
                        <hr class="hr-panel-heading" />
                        
                        <div class="mbot20">
                            <h3 class="no-margin bold text-primary"><?= e($supplier['name']) ?></h3>
                            <p class="text-muted mtop10">
                                <i class="fa fa-phone"></i> <?= e($supplier['phone'] ?: '-') ?><br>
                                <i class="fa fa-envelope"></i> <?= e($supplier['email'] ?: '-') ?><br>
                                <i class="fa fa-map-marker"></i> <?= e($supplier['address'] ?: '-') ?>
                            </p>
                        </div>

                        <?php 
                            $label_class = $current_balance > 0 ? 'danger' : 'success';
                        ?>
                        <div class="well text-center mbot25">
                            <span class="text-muted text-uppercase display-block mbot5" style="font-size:11px; letter-spacing:1px;">Current Payable Balance</span>
                            <h3 class="bold text-<?= $label_class ?> no-margin"><?= salesos_format_number($current_balance) ?> BDT</h3>
                            <small class="text-muted display-block mtop5">Opening Balance: <?= salesos_format_number($supplier['opening_balance']) ?> BDT</small>
                        </div>

                        <!-- Record Payment Form -->
                        <h4 class="no-margin font-medium">
                            <i class="fa fa-money"></i> Record Payment
                        </h4>
                        <hr class="hr-panel-heading" />
                        <?= form_open(admin_url('purchases/supplier_ledger/' . $supplier['id'])) ?>
                        <div class="form-group">
                            <label for="amount" class="control-label">Payment Amount (BDT)</label>
                            <input type="number" step="0.01" name="amount" id="pay_amount" class="form-control" placeholder="e.g. 5000" required>
                        </div>

                        <div class="form-group">
                            <label for="note" class="control-label">Note / Reference</label>
                            <textarea name="note" id="pay_note" class="form-control" rows="3" placeholder="Payment method, cheque/transaction number, bank..." required></textarea>
                        </div>

                        <div class="mtop15">
                            <button type="submit" class="btn btn-primary btn-block">Record Payment (Credit)</button>
                        </div>
                        <?= form_close() ?>
                    </div>
                </div>
            </div>

            <!-- Right Panel: Statement Log -->
            <div class="col-md-8">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin font-medium">
                            <i class="fa fa-history"></i> Ledger Statement History
                        </h4>
                        <hr class="hr-panel-heading" />
                        
                        <?php if (empty($ledger)): ?>
                            <p class="text-muted no-margin">No ledger entries found. Balance matches opening balance.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped no-mtop">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Debit (+)</th>
                                            <th>Credit (-)</th>
                                            <th>Ref / Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ledger as $row): ?>
                                            <tr>
                                                <td><small><?= e($row['created_at']) ?></small></td>
                                                <td>
                                                    <?php 
                                                        $label_type = $row['entry_type'] === 'debit' ? 'danger' : 'success';
                                                    ?>
                                                    <span class="label label-<?= $label_type ?>">
                                                        <?= strtoupper(e($row['entry_type'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong class="text-danger">
                                                        <?= $row['entry_type'] === 'debit' ? '+ ' . salesos_format_number($row['amount']) : '-' ?>
                                                    </strong>
                                                </td>
                                                <td>
                                                    <strong class="text-success">
                                                        <?= $row['entry_type'] === 'credit' ? '- ' . salesos_format_number($row['amount']) : '-' ?>
                                                    </strong>
                                                </td>
                                                <td>
                                                    <?php if ($row['ref_type']): ?>
                                                        <span class="text-muted"><?= strtoupper(e($row['ref_type'])) ?> <?= $row['ref_id'] ? '#' . e($row['ref_id']) : '' ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($row['note']): ?>
                                                        <br><small class="text-muted">"<?= e($row['note']) ?>"</small>
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

<?php init_tail(); ?>
