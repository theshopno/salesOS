<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
<div class="content">
<div class="row">
<div class="col-md-12">

    <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
        <h4 class="tw-font-bold tw-mt-0 tw-mb-0 tw-text-neutral-800"><?= _l('salesos_calls_title') ?></h4>
        <div>
            <a href="<?= admin_url('salesos/dashboard') ?>" class="btn btn-default btn-sm">
                <i class="fa-solid fa-gauge"></i> <?= _l('salesos_dashboard_title') ?>
            </a>
        </div>
    </div>

    <div class="panel_s">
        <div class="panel-body">
            <form method="GET" action="<?= admin_url('salesos/calls') ?>" class="tw-flex tw-items-end tw-gap-2 tw-flex-wrap">
                <?= render_date_input('date_from', 'salesos_date_from', $filters['date_from']) ?>
                <?= render_date_input('date_to', 'salesos_date_to', $filters['date_to']) ?>

                <div class="form-group tw-mb-4">
                    <label><?= _l('salesos_agents_table_staff') ?></label>
                    <select name="agent_id" class="form-control selectpicker" data-none-selected-text="<?= _l('salesos_filter_all') ?>">
                        <option value=""><?= _l('salesos_filter_all') ?></option>
                        <?php foreach ($agents as $a): ?>
                        <option value="<?= (int) $a['staff_id'] ?>" <?= (int) $filters['agent_id'] === (int) $a['staff_id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group tw-mb-4">
                    <label><?= _l('salesos_filter_direction') ?></label>
                    <select name="direction" class="form-control selectpicker" data-none-selected-text="<?= _l('salesos_filter_all') ?>">
                        <option value=""><?= _l('salesos_filter_all') ?></option>
                        <?php foreach (['inbound' => _l('salesos_direction_inbound'), 'outbound' => _l('salesos_direction_outbound')] as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $filters['direction'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group tw-mb-4">
                    <label><?= _l('salesos_filter_disposition') ?></label>
                    <select name="disposition" class="form-control selectpicker" data-none-selected-text="<?= _l('salesos_filter_all') ?>">
                        <option value=""><?= _l('salesos_filter_all') ?></option>
                        <?php foreach ($dispositions as $d): ?>
                        <option value="<?= e($d) ?>" <?= $filters['disposition'] === $d ? 'selected' : '' ?>><?= e($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group tw-mb-4">
                    <label><?= _l('salesos_filter_search') ?></label>
                    <input type="text" name="search" class="form-control" value="<?= e($filters['search'] ?? '') ?>" placeholder="<?= _l('salesos_filter_search_placeholder') ?>">
                </div>

                <div class="checkbox checkbox-primary tw-mb-4">
                    <input type="checkbox" name="effective_only" id="effective_only" value="1" <?= !empty($filters['effective_only']) ? 'checked' : '' ?>>
                    <label for="effective_only">
                        <?= _l('salesos_filter_effective_only') ?>
                        <i class="fa-regular fa-circle-question tw-ml-1" data-toggle="tooltip"
                           data-title="<?= _l('salesos_effective_calls_tooltip', (int) $filters['effective_seconds']) ?>"></i>
                    </label>
                </div>

                <div class="checkbox checkbox-primary tw-mb-4">
                    <input type="checkbox" name="no_lead_note_only" id="no_lead_note_only" value="1" <?= !empty($filters['no_lead_note_only']) ? 'checked' : '' ?>>
                    <label for="no_lead_note_only">
                        <?= _l('salesos_filter_no_lead_note_only') ?>
                        <i class="fa-regular fa-circle-question tw-ml-1" data-toggle="tooltip" data-title="<?= _l('salesos_no_lead_note_tooltip', '', false) ?>"></i>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary tw-mb-4"><i class="fa-solid fa-filter"></i> <?= _l('salesos_apply_filter') ?></button>
                <a href="<?= admin_url('salesos/calls') ?>" class="btn btn-default tw-mb-4"><?= _l('salesos_clear_filter') ?></a>
            </form>
        </div>
    </div>

    <div class="panel_s">
        <div class="panel-body">
            <p class="text-muted"><?= _l('salesos_showing_results', [count($calls), (int) $total]) ?></p>
            <div class="table-responsive">
            <table class="table table-condensed table-hover">
                <thead>
                    <tr>
                        <th><?= _l('salesos_col_date') ?></th>
                        <th><?= _l('salesos_col_direction') ?></th>
                        <th><?= _l('salesos_agents_table_staff') ?></th>
                        <th><?= _l('salesos_col_number') ?></th>
                        <th><?= _l('salesos_col_matched') ?></th>
                        <th><?= _l('salesos_col_duration') ?></th>
                        <th><?= _l('salesos_col_status') ?></th>
                        <th><?= _l('salesos_col_disposition') ?></th>
                        <th><?= _l('salesos_col_recording') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($calls)): ?>
                    <tr><td colspan="9" class="text-muted"><?= _l('salesos_no_calls_found') ?></td></tr>
                <?php endif; ?>
                <?php foreach ($calls as $c):
                    $is_effective = $c['disposition'] === 'ANSWERED' && (int) $c['billsec'] >= (int) $filters['effective_seconds'];
                    $entity_link = null;
                    if (!empty($c['lead_id'])) {
                        $entity_link = admin_url('leads/index/' . $c['lead_id']);
                    } elseif (!empty($c['contact_id']) && !empty($c['contact_client_id'])) {
                        $entity_link = admin_url('clients/client/' . $c['contact_client_id']);
                    }
                ?>
                <tr>
                    <td><small><?= date('d M Y H:i', strtotime($c['calldate'])) ?></small></td>
                    <td>
                        <?php if ($c['direction'] === 'inbound'): ?><i class="fa fa-arrow-down text-success" title="<?= _l('salesos_direction_inbound') ?>"></i>
                        <?php elseif ($c['direction'] === 'outbound'): ?><i class="fa fa-arrow-up text-primary" title="<?= _l('salesos_direction_outbound') ?>"></i>
                        <?php else: ?><i class="fa fa-question text-muted" title="—"></i><?php endif; ?>
                    </td>
                    <td><small><?= e($c['agent_name'] ?? '—') ?></small></td>
                    <td><small><?= e($c['direction'] === 'inbound' ? $c['src'] : $c['dst']) ?></small></td>
                    <td>
                        <?php if ($entity_link): ?>
                            <a href="<?= $entity_link ?>" target="_blank"><small><?= e($c['lead_name'] ?? $c['contact_name'] ?? '—') ?></small></a>
                        <?php else: ?>
                            <small class="text-muted">—</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <small><?= gmdate('i:s', (int) $c['billsec']) ?></small>
                        <?php if ($is_effective): ?><span class="label label-success tw-ml-1"><?= _l('salesos_effective') ?></span><?php endif; ?>
                    </td>
                    <td><small><?= e($c['disposition'] ?: '—') ?></small></td>
                    <td><small><?= e($c['disposition_code'] ?: '—') ?></small></td>
                    <td>
                        <?php if (!empty($c['recordingfile'])): ?>
                        <a href="#" class="salesos-play-recording" data-call-id="<?= (int) $c['id'] ?>" title="<?= _l('salesos_play_recording') ?>">
                            <i class="fa-solid fa-circle-play"></i>
                        </a>
                        <?php else: ?>
                        <small class="text-muted">—</small>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <ul class="pagination">
                <?php for ($p = 1; $p <= $total_pages; $p++):
                    $q = array_merge($_GET, ['page' => $p]);
                ?>
                <li class="<?= $p === $page ? 'active' : '' ?>">
                    <a href="<?= admin_url('salesos/calls?' . http_build_query($q)) ?>"><?= $p ?></a>
                </li>
                <?php endfor; ?>
            </ul>
            <?php endif; ?>

            <audio id="salesos-recording-player" style="display:none" controls></audio>
        </div>
    </div>

</div>
</div>
</div>
</div>

<script>
document.addEventListener('click', function (e) {
    var link = e.target.closest('.salesos-play-recording');
    if (!link) return;
    e.preventDefault();
    var callId = link.getAttribute('data-call-id');
    var player = document.getElementById('salesos-recording-player');
    player.style.display = '';
    player.src = '<?= admin_url('salesos/api/recording') ?>/' + encodeURIComponent(callId);
    player.play().catch(function () {});
});

// Deferred to "load" and guarded — see views/settings.php for why (jQuery
// may not be defined yet at this point in document order).
window.addEventListener('load', function () {
    if (typeof $ !== 'undefined') {
        $('[data-toggle="tooltip"]').tooltip();
    }
});
</script>

<?php init_tail(); ?>
