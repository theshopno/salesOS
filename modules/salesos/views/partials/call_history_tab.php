<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div role="tabpanel" class="tab-pane" id="salesos_call_history">
<?php
$CI = &get_instance();
$CI->load->model(SALESOS_MODULE_NAME . '/salesos_model');

$phone = $entity->phonenumber ?? '';
$calls = !empty($entity->id) ? $CI->salesos_model->get_calls_for_entity($entity_type, (int) $entity->id, 30) : [];
?>
<div class="tw-p-4">
    <div class="tw-flex tw-justify-between tw-items-center tw-mb-3">
        <h5 class="tw-font-semibold tw-mb-0">
            <i class="fa fa-phone"></i> Call History
            <?php if ($phone): ?><small class="text-muted">(<?= e($phone) ?>)</small><?php endif; ?>
        </h5>
        <?php if ($phone && staff_can('make', SALESOS_MODULE_NAME)): ?>
        <button type="button" class="btn btn-success btn-sm salesos-call-now" data-number="<?= e($phone) ?>">
            <i class="fa fa-phone"></i> Call Now
        </button>
        <?php endif; ?>
    </div>

    <?php if (!$phone): ?>
        <p class="text-muted">No phone number on record.</p>
    <?php elseif (empty($calls)): ?>
        <p class="text-muted">No call history yet.</p>
    <?php else: ?>
    <table class="table table-condensed table-hover">
        <thead>
            <tr>
                <th>Date</th><th>Dir</th><th>Agent</th><th>Duration</th><th>Status</th><th>Disposition</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($calls as $c): ?>
        <tr>
            <td><small><?= date('d M Y H:i', strtotime($c['calldate'])) ?></small></td>
            <td>
                <?php if ($c['direction'] === 'inbound'): ?><i class="fa fa-arrow-down text-success" title="Inbound"></i>
                <?php elseif ($c['direction'] === 'outbound'): ?><i class="fa fa-arrow-up text-primary" title="Outbound"></i>
                <?php else: ?><i class="fa fa-question text-muted" title="Unknown"></i><?php endif; ?>
            </td>
            <td><small><?= e($c['agent_name'] ?? '—') ?></small></td>
            <td><small><?= gmdate('i:s', (int) $c['billsec']) ?></small></td>
            <td><small><?= e($c['disposition'] ?: '—') ?></small></td>
            <td>
                <small class="salesos-wrapup-display" data-call-id="<?= (int) $c['id'] ?>">
                    <?= e($c['disposition_code'] ?: '—') ?>
                </small>
                <?php if (staff_can('view', SALESOS_MODULE_NAME)): ?>
                <a href="#" class="salesos-edit-wrapup" data-call-id="<?= (int) $c['id'] ?>" title="Edit disposition/notes"><i class="fa fa-pencil"></i></a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
</div>

<script>
(function () {
    if (window.__salesosCallHistoryBound) return;
    window.__salesosCallHistoryBound = true;

    var csrfName = '<?= $CI->security->get_csrf_token_name() ?>';
    var csrfHash = '<?= $CI->security->get_csrf_hash() ?>';

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.salesos-call-now');
        if (!btn) return;
        var number = btn.getAttribute('data-number');
        btn.disabled = true;
        fetch('<?= admin_url('salesos/api/click_to_call') ?>', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: csrfName + '=' + csrfHash + '&number=' + encodeURIComponent(number),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            alert(data.success ? 'Call originated.' : ('Call failed: ' + (data.error || 'unknown error')));
        })
        .finally(function () { btn.disabled = false; });
    });

    document.addEventListener('click', function (e) {
        var link = e.target.closest('.salesos-edit-wrapup');
        if (!link) return;
        e.preventDefault();
        var callId = link.getAttribute('data-call-id');
        var disposition = prompt('Disposition code:');
        if (disposition === null) return;
        var notes = prompt('Notes:') || '';

        fetch('<?= admin_url('salesos/api/wrapup') ?>', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: csrfName + '=' + csrfHash + '&call_id=' + encodeURIComponent(callId)
                + '&disposition_code=' + encodeURIComponent(disposition) + '&notes=' + encodeURIComponent(notes),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                document.querySelector('.salesos-wrapup-display[data-call-id="' + callId + '"]').textContent = disposition || '—';
            } else {
                alert('Failed to save.');
            }
        });
    });
}());
</script>
