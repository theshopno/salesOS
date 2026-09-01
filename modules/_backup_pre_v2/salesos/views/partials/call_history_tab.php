<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div role="tabpanel" class="tab-pane" id="salesos_call_history">
    <?php
    $CI = &get_instance();
    $CI->load->model('salesos/salesos_model');

    // Get the phone number depending on entity type
    $phone = '';
    if ($entity_type === 'lead') {
        $phone = $entity->phonenumber ?? '';
    } elseif ($entity_type === 'client') {
        $phone = $entity->phonenumber ?? '';
    }

    $calls = $phone ? $CI->salesos_model->get_calls_for_phone($phone, 30) : [];
    ?>

    <div class="tw-p-4">
        <!-- Header with call button -->
        <div class="tw-flex tw-justify-between tw-items-center tw-mb-3">
            <h5 class="tw-font-semibold tw-mb-0">
                <i class="fa fa-phone"></i> Call History
                <?php if ($phone): ?>
                    <small class="text-muted">(<?= e($phone) ?>)</small>
                <?php endif; ?>
            </h5>
            <?php if ($phone && has_permission('salesos', '', 'make')): ?>
            <button class="btn btn-success btn-sm" onclick="salesos_originate('<?= e($phone) ?>', '<?= $entity_type === 'lead' ? ($entity->id ?? '') : '' ?>')">
                <i class="fa fa-phone"></i> Call Now
            </button>
            <?php endif; ?>
        </div>

        <?php if (!$phone): ?>
            <p class="text-muted">No phone number on record.</p>
        <?php elseif (empty($calls)): ?>
            <p class="text-muted">No call history for this number.</p>
        <?php else: ?>
        <table class="table table-condensed table-hover">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Dir</th>
                    <th>Agent</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th>Rec</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($calls as $c): ?>
            <tr>
                <td><small><?= date('d M Y H:i', strtotime($c['calldate'])) ?></small></td>
                <td><?= salesos_direction_icon($c['direction']) ?></td>
                <td><small><?= e(($c['firstname'] ?? '') . ' ' . ($c['lastname'] ?? '')) ?: '—' ?></small></td>
                <td><small><?= salesos_format_duration((int)$c['billsec']) ?></small></td>
                <td><?= salesos_disposition_badge($c['disposition']) ?></td>
                <td>
                    <?php
                    $rec_url = '';
                    if (!empty($c['recordingfile'])) {
                        $base    = rtrim(salesos_setting('salesos_recordings_url', 'http://103.42.4.210:8089', 'SALESOS_RECORDINGS_URL'), '/');
                        $file    = basename($c['recordingfile']);
                        $rec_url = $base ? $base . '/' . $file : '';
                    }
                    if ($rec_url): ?>
                    <audio controls preload="none" style="height:24px;max-width:160px;">
                        <source src="<?= e($rec_url) ?>" type="audio/wav">
                    </audio>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td>
                    <a href="<?= admin_url('salesos/calls/detail/' . $c['id']) ?>" class="btn btn-xs btn-default">
                        <i class="fa fa-eye"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
