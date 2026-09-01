<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div role="tabpanel" class="tab-pane" id="pbxpopup_recordings">
    <div class="tw-p-4">
        <h5 class="tw-font-semibold tw-mb-3">
            <i class="fa fa-headphones"></i> Call Recordings
            <?php if (!empty($lead->phonenumber)): ?>
                <small class="text-muted">(<?= htmlspecialchars($lead->phonenumber) ?>)</small>
            <?php endif; ?>
        </h5>

        <?php if ($error): ?>
            <div class="alert alert-warning"><?= htmlspecialchars($error) ?></div>
        <?php elseif (empty($recordings)): ?>
            <p class="text-muted">এই নম্বরে কোনো কল রেকর্ডিং পাওয়া যায়নি।</p>
        <?php else: ?>
        <table class="table table-condensed table-hover">
            <thead>
                <tr>
                    <th>তারিখ/সময়</th>
                    <th>ধরন</th>
                    <th>স্থিতিকাল</th>
                    <th style="width: 260px;">প্লেয়ার</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recordings as $rec): ?>
                <?php
                    $duration  = (int) ($rec['duration'] ?? 0);
                    $mins      = intdiv($duration, 60);
                    $secs      = $duration % 60;
                    $streamUrl = pbxpopup_stream_url($rec);
                    $direction = ($rec['direction'] ?? '') === 'inbound' ? 'ইনকামিং' : 'আউটগোয়িং';
                ?>
                <tr>
                    <td><small><?= htmlspecialchars($rec['calldate'] ?? '') ?></small></td>
                    <td>
                        <small><?= $direction ?></small>
                        <?php if (($rec['disposition'] ?? '') !== 'ANSWERED'): ?>
                            <span class="label label-default"><?= htmlspecialchars($rec['disposition'] ?? '-') ?></span>
                        <?php else: ?>
                            <span class="label label-success">Answered</span>
                        <?php endif; ?>
                    </td>
                    <td><small><?= sprintf('%d:%02d', $mins, $secs) ?></small></td>
                    <td>
                        <?php if ($streamUrl): ?>
                            <audio controls preload="none" style="width: 240px; height: 30px;" src="<?= htmlspecialchars($streamUrl) ?>"></audio>
                        <?php else: ?>
                            <span class="text-muted">রেকর্ডিং পাওয়া যায়নি</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
