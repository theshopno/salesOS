<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
/* Self-contained layout/card styles — deliberately not relying on Tailwind
   utility classes here: this app's tw- CSS is a precompiled static bundle,
   not live JIT, so a utility only works if some other page already used it.
   Plain CSS guarantees this page renders the same regardless of that. */
.pbxpilot-card { background: #fff; border: 1px solid #e7e9ee; border-radius: 8px; box-shadow: none; }
.pbxpilot-card-body { padding: 16px; }
.pbxpilot-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 8px; }
.pbxpilot-filter-bar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; padding: 10px 16px; }
.pbxpilot-preset-bar { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
.pbxpilot-preset-bar .btn { padding: 4px 12px; font-size: 0.8rem; }
.pbxpilot-date-form { display: flex; align-items: center; gap: 8px; }
.pbxpilot-date-form input[type="date"] { width: 140px; }
.pbxpilot-kpi-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 12px; }
.pbxpilot-kpi-grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin-bottom: 16px; }
@media (max-width: 900px) {
    .pbxpilot-kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .pbxpilot-kpi-grid-3 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
}
.pbxpilot-kpi { display: flex; align-items: center; gap: 14px; padding: 16px; text-decoration: none; color: inherit; }
.pbxpilot-kpi-icon { width: 42px; height: 42px; border-radius: 9999px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
.pbxpilot-kpi-value { font-size: 1.6rem; font-weight: 700; line-height: 1.1; color: #2b3138; }
.pbxpilot-kpi-label { font-size: 0.8rem; color: #8a94a6; margin-top: 2px; }
.pbxpilot-kpi-sub .pbxpilot-kpi { padding: 13px 16px; }
.pbxpilot-kpi-sub .pbxpilot-kpi-value { font-size: 1.2rem; }
.pbxpilot-kpi-sub .pbxpilot-kpi-icon { width: 34px; height: 34px; font-size: 13px; }
.pbxpilot-icon-blue   { background: #eaf2ff; color: #2c6ee0; }
.pbxpilot-icon-neutral{ background: #f0f1f4; color: #767d8a; }
.pbxpilot-icon-green  { background: #e8f8ee; color: #1aa350; }
.pbxpilot-icon-cyan   { background: #e7f7fb; color: #17a2b8; }
.pbxpilot-icon-red    { background: #fdecec; color: #e53e3e; }
.pbxpilot-value-green { color: #1aa350; }
.pbxpilot-value-red   { color: #e53e3e; }
.pbxpilot-agent-avatar { width: 30px; height: 30px; border-radius: 9999px; background: #eef1f6; color: #5b6577; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 600; margin-right: 8px; flex-shrink: 0; }
</style>

<div id="wrapper">
<div class="content">
<div class="row">
<div class="col-md-10 col-md-offset-1">

    <div class="pbxpilot-header">
        <h4 class="tw-font-bold tw-mt-0 tw-mb-0 tw-text-neutral-800"><?= _l('pbxpilot_dashboard_title') ?></h4>
        <div>
            <a href="<?= admin_url('pbxpilot/calls') ?>" class="btn btn-default btn-sm">
                <i class="fa-solid fa-list"></i> <?= _l('pbxpilot_calls_title') ?>
            </a>
            <a href="<?= admin_url('pbxpilot/agents') ?>" class="btn btn-default btn-sm">
                <i class="fa-solid fa-users"></i> <?= _l('pbxpilot_agents_title') ?>
            </a>
            <a href="<?= admin_url('pbxpilot/settings') ?>" class="btn btn-default btn-sm">
                <i class="fa-solid fa-gear"></i> <?= _l('pbxpilot_settings_title') ?>
            </a>
        </div>
    </div>

    <!-- Minimalist filter bar -->
    <div class="pbxpilot-card tw-mb-4">
        <div class="pbxpilot-filter-bar">
            <div class="pbxpilot-preset-bar">
                <?php foreach ($presets as $key => $p): ?>
                <a href="<?= admin_url('pbxpilot/dashboard?date_from=' . $p['from'] . '&date_to=' . $p['to']) ?>"
                   class="btn <?= $active_preset === $key ? 'btn-primary' : 'btn-default' ?>">
                    <?= e($p['label']) ?>
                </a>
                <?php endforeach; ?>
            </div>
            <form method="GET" action="<?= admin_url('pbxpilot/dashboard') ?>" class="pbxpilot-date-form">
                <input type="date" name="date_from" value="<?= e($date_from) ?>" class="form-control input-sm">
                <span class="text-muted">–</span>
                <input type="date" name="date_to" value="<?= e($date_to) ?>" class="form-control input-sm">
                <button type="submit" class="btn btn-default btn-sm"><i class="fa-solid fa-filter"></i></button>
            </form>
        </div>
    </div>

    <!-- Primary KPIs -->
    <div class="pbxpilot-kpi-grid">
        <div class="pbxpilot-card">
            <div class="pbxpilot-kpi">
                <div class="pbxpilot-kpi-icon pbxpilot-icon-blue"><i class="fa-solid fa-phone"></i></div>
                <div>
                    <div class="pbxpilot-kpi-value"><?= (int) $stats['total'] ?></div>
                    <div class="pbxpilot-kpi-label"><?= _l('pbxpilot_stat_total_calls') ?></div>
                </div>
            </div>
        </div>
        <div class="pbxpilot-card">
            <div class="pbxpilot-kpi">
                <div class="pbxpilot-kpi-icon pbxpilot-icon-neutral"><i class="fa-solid fa-phone-volume"></i></div>
                <div>
                    <div class="pbxpilot-kpi-value"><?= (int) $stats['answered'] ?></div>
                    <div class="pbxpilot-kpi-label"><?= _l('pbxpilot_stat_answered_calls') ?></div>
                </div>
            </div>
        </div>
        <div class="pbxpilot-card">
            <div class="pbxpilot-kpi">
                <div class="pbxpilot-kpi-icon pbxpilot-icon-green"><i class="fa-solid fa-bolt"></i></div>
                <div>
                    <div class="pbxpilot-kpi-value pbxpilot-value-green"><?= (int) $stats['effective'] ?></div>
                    <div class="pbxpilot-kpi-label">
                        <?= _l('pbxpilot_stat_effective_calls') ?>
                        <i class="fa-regular fa-circle-question" data-toggle="tooltip"
                           data-title="<?= _l('pbxpilot_effective_calls_tooltip', (int) $effective_seconds) ?>"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="pbxpilot-card">
            <div class="pbxpilot-kpi">
                <div class="pbxpilot-kpi-icon pbxpilot-icon-cyan"><i class="fa-solid fa-clock"></i></div>
                <div>
                    <div class="pbxpilot-kpi-value"><?= gmdate('H:i:s', (int) $stats['avg_billsec']) ?></div>
                    <div class="pbxpilot-kpi-label"><?= _l('pbxpilot_stat_avg_duration') ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Direction + attention breakdown -->
    <div class="pbxpilot-kpi-grid-3">
        <div class="pbxpilot-card pbxpilot-kpi-sub">
            <div class="pbxpilot-kpi">
                <div class="pbxpilot-kpi-icon pbxpilot-icon-neutral"><i class="fa-solid fa-arrow-down"></i></div>
                <div>
                    <div class="pbxpilot-kpi-value"><?= (int) $stats['inbound'] ?></div>
                    <div class="pbxpilot-kpi-label"><?= _l('pbxpilot_stat_inbound') ?></div>
                </div>
            </div>
        </div>
        <div class="pbxpilot-card pbxpilot-kpi-sub">
            <div class="pbxpilot-kpi">
                <div class="pbxpilot-kpi-icon pbxpilot-icon-neutral"><i class="fa-solid fa-arrow-up"></i></div>
                <div>
                    <div class="pbxpilot-kpi-value"><?= (int) $stats['outbound'] ?></div>
                    <div class="pbxpilot-kpi-label"><?= _l('pbxpilot_stat_outbound') ?></div>
                </div>
            </div>
        </div>
        <div class="pbxpilot-card pbxpilot-kpi-sub">
            <?php $has_gap = (int) $stats['no_lead_note'] > 0; ?>
            <a href="<?= admin_url('pbxpilot/calls?no_lead_note_only=1&date_from=' . e($date_from) . '&date_to=' . e($date_to)) ?>" class="pbxpilot-kpi">
                <div class="pbxpilot-kpi-icon <?= $has_gap ? 'pbxpilot-icon-red' : 'pbxpilot-icon-neutral' ?>"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div>
                    <div class="pbxpilot-kpi-value <?= $has_gap ? 'pbxpilot-value-red' : '' ?>"><?= (int) $stats['no_lead_note'] ?></div>
                    <div class="pbxpilot-kpi-label">
                        <?= _l('pbxpilot_stat_no_lead_note') ?>
                        <i class="fa-regular fa-circle-question" data-toggle="tooltip" data-title="<?= _l('pbxpilot_no_lead_note_tooltip', '', false) ?>"></i>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="pbxpilot-card">
        <div class="pbxpilot-card-body">
            <h4 class="tw-mt-0 tw-mb-3"><?= _l('pbxpilot_agent_breakdown') ?></h4>
            <div class="table-responsive">
            <table class="table table-condensed table-hover">
                <thead>
                    <tr class="text-muted">
                        <th><?= _l('pbxpilot_agents_table_staff') ?></th>
                        <th><?= _l('pbxpilot_agents_table_extension') ?></th>
                        <th class="text-right"><?= _l('pbxpilot_stat_total_calls') ?></th>
                        <th class="text-right"><?= _l('pbxpilot_stat_answered_calls') ?></th>
                        <th class="text-right"><?= _l('pbxpilot_stat_effective_calls') ?></th>
                        <th class="text-right">
                            <?= _l('pbxpilot_stat_no_lead_note') ?>
                            <i class="fa-regular fa-circle-question" data-toggle="tooltip" data-title="<?= _l('pbxpilot_no_lead_note_tooltip', '', false) ?>"></i>
                        </th>
                        <th class="text-right"><?= _l('pbxpilot_stat_avg_duration') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($agent_breakdown)): ?>
                    <tr><td colspan="7" class="text-muted"><?= _l('pbxpilot_agents_none') ?></td></tr>
                <?php endif; ?>
                <?php foreach ($agent_breakdown as $a):
                    $initials = '';
                    foreach (explode(' ', trim((string) ($a['agent_name'] ?? ''))) as $part) {
                        if ($part !== '') { $initials .= mb_strtoupper(mb_substr($part, 0, 1)); }
                    }
                    $initials = mb_substr($initials, 0, 2) ?: '?';
                ?>
                    <tr>
                        <td>
                            <a href="<?= admin_url('pbxpilot/calls?agent_id=' . (int) $a['staff_id'] . '&date_from=' . e($date_from) . '&date_to=' . e($date_to)) ?>" style="display:flex;align-items:center;text-decoration:none;">
                                <span class="pbxpilot-agent-avatar"><?= e($initials) ?></span>
                                <?= e($a['agent_name'] ?? '—') ?>
                            </a>
                        </td>
                        <td><span class="text-muted"><?= e($a['extension']) ?></span></td>
                        <td class="text-right"><?= (int) $a['total'] ?></td>
                        <td class="text-right"><?= (int) $a['answered'] ?></td>
                        <td class="text-right"><span class="label label-success"><?= (int) $a['effective'] ?></span></td>
                        <td class="text-right">
                            <?php if ((int) $a['no_lead_note'] > 0): ?>
                            <a href="<?= admin_url('pbxpilot/calls?agent_id=' . (int) $a['staff_id'] . '&no_lead_note_only=1&date_from=' . e($date_from) . '&date_to=' . e($date_to)) ?>">
                                <span class="label label-danger"><?= (int) $a['no_lead_note'] ?></span>
                            </a>
                            <?php else: ?>
                            <span class="label label-default">0</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right"><?= gmdate('H:i:s', (int) ($a['answered'] > 0 ? $a['total_billsec'] / $a['answered'] : 0)) ?></td>
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
</div>

<script>
// Deferred to "load" and guarded — see views/settings.php for why (jQuery
// may not be defined yet at this point in document order).
window.addEventListener('load', function () {
    if (typeof $ !== 'undefined') {
        $('[data-toggle="tooltip"]').tooltip();
    }
});
</script>

<?php init_tail(); ?>
