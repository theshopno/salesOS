<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        
                        <div class="pull-left">
                            <h4 class="no-margin bold font-medium text-primary">
                                <i class="fa fa-reply"></i> Return Details - #<?= $return->id ?>
                            </h4>
                            <span class="text-muted">Created at: <?= _dt($return->created_at) ?></span>
                        </div>
                        
                        <div class="pull-right">
                            <a href="<?= admin_url('returns') ?>" class="btn btn-default"><i class="fa fa-reply"></i> Back to list</a>
                        </div>
                        <div class="clearfix"></div>
                        <hr class="hr-panel-separator" />

                        <!-- Status alert logs -->
                        <?php if ($return->status === 'received' || $return->status === 'restocked'): ?>
                            <div class="alert alert-success">
                                <strong><i class="fa fa-check-circle"></i> Stock Processed:</strong> Items marked as <strong>Sellable</strong> have been automatically restocked to the default warehouse stock ledger.
                            </div>
                            <div class="alert alert-warning">
                                <strong><i class="fa fa-exclamation-triangle"></i> Damaged Items:</strong> Any items marked as <strong>Damaged</strong> were <strong>NOT</strong> auto-restocked, preventing data corruption of inventory levels.
                            </div>
                        <?php endif; ?>

                        <div class="row mtop20">
                            <!-- Left metadata -->
                            <div class="col-md-6">
                                <table class="table table-striped table-bordered">
                                    <tbody>
                                        <tr>
                                            <td class="bold" width="40%">Associated Order ID:</td>
                                            <td>
                                                <span class="label label-default">#<?= $return->salesos_order_id ?></span>
                                                <?php if ($return->channel): ?>
                                                    <span class="label label-info mleft5"><?= strtoupper($return->channel) ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="bold">Requested By:</td>
                                            <td><?= e(ucfirst($return->requested_by)) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="bold">Staff Handling:</td>
                                            <td><?= e($return->firstname . ' ' . $return->lastname) ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Right metadata -->
                            <div class="col-md-6">
                                <table class="table table-striped table-bordered">
                                    <tbody>
                                        <tr>
                                            <td class="bold" width="40%">Current Status:</td>
                                            <td>
                                                <?php
                                                    $status = $return->status;
                                                    $class = 'default';
                                                    if ($status === 'approved') { $class = 'info'; }
                                                    elseif ($status === 'rejected') { $class = 'danger'; }
                                                    elseif ($status === 'received' || $status === 'restocked') { $class = 'success'; }
                                                    elseif ($status === 'refunded') { $class = 'warning'; }
                                                ?>
                                                <span class="label label-<?= $class ?> bold" style="font-size:12px;">
                                                    <?= strtoupper($status) ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="bold">Refund Amount:</td>
                                            <td class="bold text-success"><?= salesos_format_number($return->refund_amount) ?> BDT</td>
                                        </tr>
                                        <tr>
                                            <td class="bold">Reason:</td>
                                            <td><?= e($return->reason) ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Return items table -->
                        <h5 class="bold text-muted mtop20 mbot15"><i class="fa fa-shopping-basket"></i> Returned Items</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr class="active">
                                        <th>Item Name</th>
                                        <th>SKU</th>
                                        <th class="text-center">Returned Qty</th>
                                        <th>Condition</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($return->items as $item): ?>
                                        <tr>
                                            <td><?= e($item['product_name']) ?></td>
                                            <td><code><?= e($item['product_sku'] ? $item['product_sku'] : 'None') ?></code></td>
                                            <td class="text-center bold"><?= salesos_format_number($item['qty'], 0) ?></td>
                                            <td>
                                                <?php if ($item['condition_note'] === 'sellable'): ?>
                                                    <span class="label label-success"><i class="fa fa-check"></i> Sellable (Restocked)</span>
                                                <?php else: ?>
                                                    <span class="label label-danger"><i class="fa fa-times"></i> Damaged (Inspection Needed)</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Status Action Controls Form -->
                        <?php if (staff_can('edit', 'returns') && !in_array($return->status, ['received', 'restocked', 'rejected'])): ?>
                            <hr class="hr-panel-separator" />
                            <h5 class="bold text-primary mbot15"><i class="fa fa-cogs"></i> Process Return Workflow</h5>
                            
                            <?= form_open(admin_url('returns/update_status/' . $return->id), ['class' => 'form-inline']) ?>
                            <div class="form-group">
                                <label for="status" class="control-label mright10">Transition Status to:</label>
                                <select name="status" id="status" class="form-control mright10">
                                    <?php if ($return->status === 'requested'): ?>
                                        <option value="approved">Approved</option>
                                        <option value="rejected">Rejected</option>
                                    <?php endif; ?>
                                    <option value="received">Received (Process restock flow)</option>
                                    <option value="refunded">Refunded</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-info"><i class="fa fa-check"></i> Submit Transition</button>
                            <?= form_close() ?>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
