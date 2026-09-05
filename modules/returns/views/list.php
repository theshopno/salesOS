<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="_buttons">
                            <?php if (staff_can('create', 'returns')): ?>
                                <a href="<?= admin_url('returns/create') ?>" class="btn btn-primary pull-left display-block">
                                    <i class="fa-regular fa-plus tw-mr-1"></i> New Return
                                </a>
                            <?php endif; ?>
                            <div class="clearfix"></div>
                        </div>
                        <hr class="hr-panel-separator" />
                        
                        <h4 class="mbot15 bold"><i class="fa fa-reply"></i> Returns Management</h4>
                        
                        <table class="table dt-table" data-order-col="0" data-order-type="desc">
                            <thead>
                                <tr>
                                    <th>Return ID</th>
                                    <th>Ecomcore Order ID</th>
                                    <th>Channel</th>
                                    <th>Reason</th>
                                    <th>Requested By</th>
                                    <th>Refund Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($returns as $ret): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= admin_url('returns/view/' . $ret['id']) ?>" class="bold">
                                                #<?= $ret['id'] ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="label label-default">
                                                #<?= $ret['ecomcore_order_id'] ?>
                                            </span>
                                            <?php if ($ret['channel_ref_id']): ?>
                                                <small class="text-muted display-block">Ref: <?= e($ret['channel_ref_id']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="label label-info">
                                                <?= strtoupper($ret['channel'] ?? 'N/A') ?>
                                            </span>
                                        </td>
                                        <td><?= e($ret['reason']) ?></td>
                                        <td><?= e(ucfirst($ret['requested_by'])) ?></td>
                                        <td class="bold"><?= ecomcore_format_number($ret['refund_amount']) ?> BDT</td>
                                        <td>
                                            <?php
                                                $status = $ret['status'];
                                                $class = 'default';
                                                if ($status === 'approved') { $class = 'info'; }
                                                elseif ($status === 'rejected') { $class = 'danger'; }
                                                elseif ($status === 'received' || $status === 'restocked') { $class = 'success'; }
                                                elseif ($status === 'refunded') { $class = 'warning'; }
                                            ?>
                                            <span class="label label-<?= $class ?>">
                                                <?= strtoupper($status) ?>
                                            </span>
                                        </td>
                                        <td><?= _dt($ret['created_at']) ?></td>
                                        <td>
                                            <a href="<?= admin_url('returns/view/' . $ret['id']) ?>" class="btn btn-default btn-icon btn-xs" title="View details">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                            <?php if (staff_can('delete', 'returns')): ?>
                                                <a href="<?= admin_url('returns/delete/' . $ret['id']) ?>" class="btn btn-danger btn-icon btn-xs _delete" title="Delete">
                                                    <i class="fa fa-trash" style="color: #ffffff !important;"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
