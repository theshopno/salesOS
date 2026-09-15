<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
    <div class="content">

        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin font-medium">
                            <i class="fa fa-phone"></i> Order Confirmations
                            <span class="label label-warning"><?= (int) $waiting ?> waiting</span>
                        </h4>
                        <p class="text-muted no-margin">
                            Call each customer before the order goes to the warehouse. Confirming releases
                            it for stock, courier booking and notifications; cancelling closes it and
                            returns any stock already taken.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($stats['agents'])): ?>
            <div class="row">
                <div class="col-md-12">
                    <div class="panel_s">
                        <div class="panel-body">
                            <h5 class="no-margin bold">Last 7 days</h5>
                            <hr class="hr-panel-heading" />
                            <div class="table-responsive">
                                <table class="table table-bordered no-mtop">
                                    <thead>
                                        <tr>
                                            <th>Agent</th>
                                            <th>Confirmed</th>
                                            <th>Cancelled</th>
                                            <th>Confirmation rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stats['agents'] as $agent): ?>
                                            <tr>
                                                <td><?= e($agent['name']) ?></td>
                                                <td><?= (int) $agent['confirmed'] ?></td>
                                                <td><?= (int) $agent['cancelled'] ?></td>
                                                <td><strong><?= (int) $agent['rate'] ?>%</strong></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">

                        <?php if (empty($orders)): ?>
                            <p class="text-muted no-margin">
                                Nothing is waiting for a call. New channel orders will appear here as they arrive.
                            </p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered no-mtop">
                                    <thead>
                                        <tr>
                                            <th>Order</th>
                                            <th>Customer</th>
                                            <th>Items</th>
                                            <th>Value</th>
                                            <th>COD history</th>
                                            <th style="min-width:260px;">Call outcome</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td>
                                                    <strong>#<?= e($order['channel_ref_id'] ?: $order['id']) ?></strong><br>
                                                    <small class="text-muted">
                                                        <?= e($order['site_name'] ?: $order['channel']) ?>
                                                        <?php if (!empty($order['channel_status'])): ?>
                                                            · shop says <?= e($order['channel_status']) ?>
                                                        <?php endif; ?>
                                                    </small><br>
                                                    <small class="text-muted"><?= e($order['order_date']) ?></small>
                                                </td>
                                                <td>
                                                    <strong><?= e($order['customer_name']) ?></strong><br>
                                                    <?php if (!empty($order['customer_phone'])): ?>
                                                        <a href="tel:<?= e($order['customer_phone']) ?>">
                                                            <i class="fa fa-phone"></i> <?= e($order['customer_phone']) ?>
                                                        </a><br>
                                                    <?php else: ?>
                                                        <span class="text-danger">No phone number</span><br>
                                                    <?php endif; ?>
                                                    <small class="text-muted"><?= e($order['customer_address']) ?></small>
                                                </td>
                                                <td>
                                                    <?php foreach ($order['items'] as $item): ?>
                                                        <div>
                                                            <?= e($item['name']) ?>
                                                            <span class="text-muted">× <?= (float) $item['qty'] ?></span>
                                                            <?php if (!empty($item['sku'])): ?>
                                                                <br><small class="text-muted"><?= e($item['sku']) ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                    <?php if (empty($order['items'])): ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?= salesos_format_number($order['total']) ?></strong>
                                                    <?= e($order['currency']) ?><br>
                                                    <small class="text-muted"><?= e($order['payment_method'] ?: '—') ?></small>
                                                </td>
                                                <td>
                                                    <?php if (isset($order['fraud_risk']) && $order['fraud_risk'] !== null): ?>
                                                        <span class="label" style="background-color: <?= e($order['fraud_color'] ?: '#888') ?>">
                                                            <?= e(strtoupper($order['fraud_risk'])) ?>
                                                        </span><br>
                                                        <small class="text-muted"><?= (float) $order['fraud_ratio'] ?>% delivered</small>
                                                    <?php else: ?>
                                                        <small class="text-muted">Not checked</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?= form_open(admin_url('salesos/confirm_order/' . $order['id'])) ?>
                                                    <div class="form-group">
                                                        <input type="text" name="note" class="form-control input-sm"
                                                               id="note-<?= (int) $order['id'] ?>"
                                                               placeholder="What the customer said (optional)">
                                                    </div>
                                                    <button type="submit" name="outcome" value="confirmed" class="btn btn-sm btn-success">
                                                        <i class="fa fa-check"></i> Confirmed
                                                    </button>
                                                    <button type="submit" name="outcome" value="cancelled" class="btn btn-sm btn-danger">
                                                        <i class="fa fa-times"></i> Cancel
                                                    </button>
                                                    <?= form_close() ?>
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
</body>
</html>
