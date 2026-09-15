<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<?php
$status_label = static function (string $status): array {
    $s = strtolower($status);
    if (in_array($s, ['delivered'], true))                    return ['success', 'Delivered'];
    if (in_array($s, ['cancelled'], true))                    return ['danger',  'Cancelled'];
    if (in_array($s, ['returned'], true))                     return ['danger',  'Returned'];
    if (in_array($s, ['partial_delivered'], true))            return ['warning', 'Partly delivered'];
    if (in_array($s, ['hold', 'in_review'], true))            return ['warning', ucfirst(str_replace('_', ' ', $s))];
    return ['info', ucfirst(str_replace(['_', '.'], ' ', $s ?: 'booked'))];
};

$tabs = [
    ''           => 'All',
    'in_transit' => 'In transit',
    'delivered'  => 'Delivered',
    'returned'   => 'Returned',
    'cancelled'  => 'Cancelled',
];
?>

<div id="wrapper">
    <div class="content">

        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h4 class="no-margin font-medium">
                                    <i class="fa fa-truck"></i> Consignments
                                    <?php if (!empty($counts['in_transit'])): ?>
                                        <span class="label label-info"><?= (int) $counts['in_transit'] ?> in transit</span>
                                    <?php endif; ?>
                                </h4>
                                <p class="text-muted no-margin">
                                    Where every parcel is, and what the courier last told us.
                                </p>
                            </div>
                            <div class="col-md-6 text-right">
                                <?= form_open(admin_url('courier/sync_statuses'), ['class' => 'display-inline-block']) ?>
                                <button type="submit" class="btn btn-info">
                                    <i class="fa fa-refresh"></i> Check with courier
                                </button>
                                <?= form_close() ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">

                        <ul class="nav nav-tabs mbot15" role="tablist">
                            <?php foreach ($tabs as $key => $label): ?>
                                <?php
                                $count = $key === '' ? ($counts['all'] ?? 0) : ($counts[$key] ?? 0);
                                $query = array_filter(['status' => $key, 'account_id' => $filters['account_id'], 'search' => $filters['search']]);
                                ?>
                                <li role="presentation" class="<?= $filters['status'] === $key ? 'active' : '' ?>">
                                    <a href="<?= admin_url('courier/consignments' . ($query ? '?' . http_build_query($query) : '')) ?>">
                                        <?= $label ?> <span class="text-muted">(<?= (int) $count ?>)</span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <?= form_open(admin_url('courier/consignments'), ['method' => 'get', 'class' => 'mbot15']) ?>
                        <div class="row">
                            <div class="col-md-5">
                                <input type="text" name="search" id="consignment-search" class="form-control"
                                       value="<?= e($filters['search']) ?>"
                                       placeholder="Tracking number, phone or customer name">
                            </div>
                            <div class="col-md-4">
                                <select name="account_id" id="consignment-account" class="form-control">
                                    <option value="">All couriers</option>
                                    <?php foreach ($accounts as $account): ?>
                                        <option value="<?= (int) $account['id'] ?>"
                                            <?= (string) $filters['account_id'] === (string) $account['id'] ? 'selected' : '' ?>>
                                            <?= e($account['label']) ?> (<?= e($account['provider']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
                                <button type="submit" class="btn btn-default"><i class="fa fa-search"></i> Search</button>
                                <?php if ($filters['search'] !== '' || $filters['account_id'] !== ''): ?>
                                    <a href="<?= admin_url('courier/consignments') ?>" class="btn btn-link">Clear</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?= form_close() ?>

                        <?php if (empty($consignments)): ?>
                            <p class="text-muted no-margin">
                                Nothing here yet. Book a shipment from an order to see it on this screen.
                            </p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered no-mtop">
                                    <thead>
                                        <tr>
                                            <th>Tracking</th>
                                            <th>Customer</th>
                                            <th>Order</th>
                                            <th>COD</th>
                                            <th>Courier</th>
                                            <th>Status</th>
                                            <th>Last checked</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($consignments as $row): ?>
                                            <?php [$class, $label] = $status_label((string) $row['status']); ?>
                                            <tr>
                                                <td>
                                                    <strong><?= e($row['tracking_id'] ?: '—') ?></strong>
                                                    <?php if (!empty($row['consignment_id'])): ?>
                                                        <br><small class="text-muted"><?= e($row['consignment_id']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?= e($row['customer_name']) ?>
                                                    <?php if (!empty($row['customer_phone'])): ?>
                                                        <br><a href="tel:<?= e($row['customer_phone']) ?>"><?= e($row['customer_phone']) ?></a>
                                                    <?php endif; ?>
                                                    <?php if (!empty($row['customer_address'])): ?>
                                                        <br><small class="text-muted"><?= e($row['customer_address']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($row['salesos_order_id'])): ?>
                                                        <a href="<?= admin_url('salesos/orders') ?>">
                                                            #<?= e($row['channel_ref_id'] ?: $row['salesos_order_id']) ?>
                                                        </a>
                                                        <br><small class="text-muted"><?= e($row['channel'] ?: '—') ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><strong><?= salesos_format_number($row['cod_amount']) ?></strong></td>
                                                <td>
                                                    <?php if (!empty($row['account_label'])): ?>
                                                        <?= e($row['account_label']) ?>
                                                        <br><small class="text-muted"><?= e($row['provider']) ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">Account removed</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="label label-<?= $class ?>"><?= e($label) ?></span></td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?= $row['last_synced_at'] ? e($row['last_synced_at']) : 'Never' ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php if (!empty($row['account_label'])): ?>
                                                        <button type="button" class="btn btn-default btn-sm sync-one"
                                                                data-id="<?= (int) $row['id'] ?>">
                                                            <i class="fa fa-refresh"></i>
                                                        </button>
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
<script>
document.addEventListener('click', function (event) {
    var button = event.target.closest('.sync-one');
    if (!button) { return; }

    button.disabled = true;
    button.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

    fetch('<?= admin_url('courier/sync_single_status_ajax') ?>/' + button.dataset.id, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(function (response) { return response.json(); })
        .then(function () { window.location.reload(); })
        .catch(function () {
            button.disabled = false;
            button.innerHTML = '<i class="fa fa-refresh"></i>';
            alert_float('danger', 'Could not reach the courier for that shipment.');
        });
});
</script>
</body>
</html>
