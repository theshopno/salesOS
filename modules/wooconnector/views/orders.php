<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
<div class="content">
<div class="row">
<div class="col-md-11 col-md-offset-0">

    <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
        <h4 class="tw-text-xl tw-font-bold tw-mb-0">Website Orders</h4>
        <a href="<?= admin_url('wooconnector') ?>" class="btn btn-default btn-sm">
            <i class="fa fa-dashboard"></i> Dashboard
        </a>
    </div>

    <?php if (empty($orders)): ?>
    <div class="alert alert-info">No pending website orders right now.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Phone</th>
                    <th>Items</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                <tr data-lead-id="<?= (int) $order['id'] ?>">
                    <td>#<?= e($order['wc_order_id']) ?></td>
                    <td><a href="<?= admin_url('leads/index/' . $order['id']) ?>" target="_blank"><?= e($order['name']) ?></a></td>
                    <td><?= e($order['phonenumber']) ?></td>
                    <td class="tw-whitespace-pre-wrap"><?= e($order['custom']['wooconnector_items'] ?? '') ?></td>
                    <td><?= e($order['custom']['wooconnector_total_amount'] ?? '') ?></td>
                    <td><?= e($order['custom']['wooconnector_payment_method'] ?? '') ?></td>
                    <td>
                        <?php if ($order['status_name'] === 'Confirmed'): ?>
                        <span class="label label-danger">Confirm failed</span>
                        <button type="button" class="btn btn-xs btn-warning wc-retry-conversion" data-lead-id="<?= (int) $order['id'] ?>">
                            <i class="fa fa-refresh"></i> Retry Conversion
                        </button>
                        <?php else: ?>
                        <select class="form-control input-sm wc-status-select" data-lead-id="<?= (int) $order['id'] ?>">
                            <?php foreach ($statuses as $status): ?>
                            <option value="<?= $status['id'] ?>" <?= (int) $order['status'] === (int) $status['id'] ? 'selected' : '' ?>>
                                <?= e($status['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
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

<?php init_tail(); ?>

<script>
(function() {
    var csrfName = '<?= $this->security->get_csrf_token_name() ?>';
    var csrfHash = '<?= $this->security->get_csrf_hash() ?>';

    document.querySelectorAll('.wc-status-select').forEach(function(select) {
        select.addEventListener('change', function() {
            var leadId = this.dataset.leadId;
            var statusId = this.value;
            this.disabled = true;

            fetch('<?= admin_url('leads/update_lead_status') ?>', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'leadid=' + encodeURIComponent(leadId) + '&status=' + encodeURIComponent(statusId) + '&' + csrfName + '=' + encodeURIComponent(csrfHash)
            }).finally(function() {
                // The core endpoint returns no body, so the queue itself is the source of truth —
                // reload to see whether the row is gone (converted) or still stuck (needs retry).
                window.location.reload();
            });
        });
    });

    document.querySelectorAll('.wc-retry-conversion').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var leadId = this.dataset.leadId;
            this.disabled = true;
            this.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Retrying...';

            fetch('<?= admin_url('wooconnector/retry_conversion/') ?>' + leadId, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: csrfName + '=' + encodeURIComponent(csrfHash)
            })
            .then(function(r) { return r.json(); })
            .then(function() { window.location.reload(); });
        });
    });
})();
</script>
