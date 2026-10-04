<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
<div class="content">
<div class="row">
<div class="col-md-12">

    <!-- Top Header & Navigation -->
    <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
        <div>
            <h4 class="tw-font-bold tw-mt-0 tw-mb-1 tw-text-neutral-800">
                <i class="fa-solid fa-users-gear text-primary tw-mr-1"></i> টিম ও চ্যানেল ম্যাপিং (Unified Staff &amp; Channels Hub)
            </h4>
            <p class="text-muted tw-mb-0 tw-text-sm">
                Asterisk PBX এক্সটেনশন ও Bizbot WhatsApp চ্যানেলে কর্মীদের এক স্ক্রিন থেকে সহজে ও স্মার্টভাবে সংযুক্ত করুন।
            </p>
        </div>
        <div class="tw-flex tw-gap-2">
            <a href="<?= admin_url('salesos/settings?tab=notifications') ?>" class="btn btn-default btn-sm" title="SalesOS WhatsApp Settings">
                <i class="fa-brands fa-whatsapp text-success"></i> WhatsApp Settings
            </a>
            <a href="<?= admin_url('pbxpilot/settings') ?>" class="btn btn-default btn-sm" title="PBX Pilot Settings">
                <i class="fa-solid fa-gear text-primary"></i> PBX Settings
            </a>
        </div>
    </div>

    <!-- Unmapped Discovered Bizbot Agents Banner (if any) -->
    <?php if (!empty($unmapped_bizbot_agents)): ?>
    <div class="alert alert-info alert-dismissible tw-shadow-sm" style="border-left: 4px solid #0284c7; background: #f0f9ff; color: #0369a1;" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="tw-font-bold tw-text-base tw-mb-1" style="color: #0369a1;">
            <i class="fa-solid fa-satellite-dish tw-mr-1"></i> নতুন বিজবট (WhatsApp) এজেন্ট ডিটেক্ট হয়েছে!
        </h4>
        <p class="tw-text-sm tw-mb-2">
            সম্প্রতি নিচের এজেন্টরা WhatsApp কনসোল থেকে রিপ্লাই দিয়েছেন কিন্তু CRM-এর কোনো কর্মীর সাথে যুক্ত নেই। নিচের তালিকা থেকে ১-ক্লিকে যেকোনো কর্মীকে অ্যাসাইন করতে পারেন:
        </p>
        <div class="tw-flex tw-flex-wrap tw-gap-2">
            <?php foreach ($unmapped_bizbot_agents as $u_guid => $u_data): ?>
                <div class="badge-discovered-agent tw-bg-white tw-border tw-border-sky-200 tw-rounded tw-px-3 tw-py-2 tw-flex tw-items-center tw-gap-3">
                    <div>
                        <span class="tw-font-semibold tw-text-neutral-800"><?= e($u_data['name'] ?? 'Bizbot Agent') ?></span>
                        <code class="tw-text-xs tw-text-sky-700 tw-ml-1"><?= e($u_guid) ?></code>
                        <span class="tw-text-xs tw-text-muted tw-block">লাস্ট সিন: <?= e($u_data['last_seen'] ?? '') ?></span>
                    </div>
                    <div class="tw-flex tw-gap-1">
                        <div class="dropdown">
                            <button class="btn btn-xs btn-primary dropdown-toggle" type="button" data-toggle="dropdown">
                                Assign to Staff <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-right">
                                <?php foreach ($roster as $stf): ?>
                                <li>
                                    <a href="#" class="btn-assign-discovered" data-guid="<?= e($u_guid) ?>" data-staff-id="<?= (int)$stf['staff_id'] ?>">
                                        <?= e($stf['staff_name']) ?> (#<?= $stf['staff_id'] ?>)
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <button type="button" class="btn btn-xs btn-default btn-dismiss-discovered" data-guid="<?= e($u_guid) ?>" title="বাতিল করুন">
                            <i class="fa fa-times text-danger"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Master Unified Staff Roster Table -->
    <div class="panel_s">
        <div class="panel-body">
            
            <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
                <div class="tw-flex tw-items-center tw-gap-3">
                    <span class="tw-font-bold tw-text-neutral-700 font-14">কর্মীদের চ্যানেল তালিকা (<?= count($roster) ?> জন সক্রিয় কর্মী)</span>
                    <?php if (!empty($discovered_extensions)): ?>
                        <?php 
                        $online_count = count(array_filter($discovered_extensions, fn($e) => !empty($e['is_online'])));
                        ?>
                        <span class="label label-info" title="PBX থেকে লাইভ পাওয়া এক্সটেনশন">
                            <i class="fa-solid fa-phone"></i> PBX Extensions: <?= count($discovered_extensions) ?> (<?= $online_count ?> Online)
                        </span>
                    <?php else: ?>
                        <span class="label label-warning" title="PBX সংযোগ পরীক্ষা করুন">
                            <i class="fa-solid fa-phone-slash"></i> PBX Endpoints Scanner Offline
                        </span>
                    <?php endif; ?>
                </div>

                <div class="tw-text-xs text-muted">
                    <i class="fa fa-info-circle"></i> যেকোনো পরিবর্তন শেষে সংশ্লিষ্ট সারির <b>Save</b> বাটনে ক্লিক করুন।
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-bordered" id="table-unified-agents" style="background: #fff;">
                    <thead>
                        <tr style="background: #f8fafc;">
                            <th style="width: 24%;">স্টাফ মেম্বার (CRM Staff)</th>
                            <th style="width: 22%;">📞 PBX এক্সটেনশন (SIP)</th>
                            <th style="width: 22%;">💬 Bizbot WhatsApp Agent ID</th>
                            <th style="width: 22%;">🏷️ WhatsApp পরিচিতি ডাকনাম (Aliases)</th>
                            <th style="width: 10%; text-align: center;">অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roster as $stf): ?>
                        <tr id="staff-row-<?= (int)$stf['staff_id'] ?>" data-staff-id="<?= (int)$stf['staff_id'] ?>">
                            
                            <!-- 1. Staff Info -->
                            <td>
                                <div class="tw-flex tw-items-center tw-gap-2">
                                    <div class="staff-avatar tw-w-9 tw-h-9 tw-rounded-full tw-bg-primary-50 tw-border tw-border-primary-200 tw-flex tw-items-center tw-justify-center tw-text-primary-700 tw-font-bold tw-text-sm">
                                        <?= strtoupper(mb_substr($stf['firstname'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <span class="tw-font-bold tw-text-neutral-800 tw-block font-13">
                                            <?= e($stf['staff_name']) ?>
                                            <?php if (!empty($stf['is_admin'])): ?>
                                                <span class="label label-default" style="font-size: 9px; padding: 1px 4px;">Admin</span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="tw-text-xs text-muted tw-block">
                                            <i class="fa fa-envelope tw-mr-1"></i><?= e($stf['email']) ?>
                                        </span>
                                        <?php if (!empty($stf['phonenumber'])): ?>
                                            <span class="tw-text-xs text-muted tw-block">
                                                <i class="fa fa-phone tw-mr-1"></i><?= e($stf['phonenumber']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <!-- 2. PBX Extension -->
                            <td>
                                <div class="input-group input-group-sm">
                                    <select name="extension" class="form-control select-extension">
                                        <option value="">— কোনো এক্সটেনশন নেই —</option>
                                        <?php 
                                        $ext_matched = false;
                                        if (!empty($discovered_extensions)):
                                            foreach ($discovered_extensions as $ep):
                                                $is_selected = ($stf['extension'] !== '' && (string)$stf['extension'] === (string)$ep['extension']);
                                                if ($is_selected) $ext_matched = true;
                                        ?>
                                                <option value="<?= e($ep['extension']) ?>" <?= $is_selected ? 'selected' : '' ?>>
                                                    <?= !empty($ep['is_online']) ? '🟢' : '⚪' ?> <?= e($ep['extension']) ?> (<?= e($ep['status']) ?>)
                                                </option>
                                        <?php 
                                            endforeach;
                                        endif; 
                                        ?>
                                        <?php if (!empty($stf['extension']) && !$ext_matched): ?>
                                            <option value="<?= e($stf['extension']) ?>" selected>
                                                <?= e($stf['extension']) ?> (Custom / Configured)
                                            </option>
                                        <?php endif; ?>
                                        <option value="custom_ext">✍️ অন্য কোনো এক্সটেনশন...</option>
                                    </select>
                                    <span class="input-group-btn">
                                        <button type="button" class="btn btn-default btn-test-ring" title="টেস্ট রিং বাজান (Test Ring)" <?= empty($stf['extension']) ? 'disabled' : '' ?>>
                                            <i class="fa-solid fa-bell text-warning"></i>
                                        </button>
                                    </span>
                                </div>
                                <div class="custom-ext-container tw-mt-1" style="display: none;">
                                    <input type="text" class="form-control input-sm custom-ext-input" placeholder="Enter numeric extension" value="<?= e($stf['extension']) ?>">
                                </div>
                            </td>

                            <!-- 3. Bizbot WhatsApp Agent GUID -->
                            <td>
                                <div class="input-group input-group-sm">
                                    <input type="text" name="bizbot_agent_guid" class="form-control input-sm input-bizbot-guid font-monospace" 
                                           value="<?= e($stf['bizbot_agent_guid']) ?>" 
                                           placeholder="e.g. 203fb98c-c326-..." 
                                           style="font-size: 11px;">
                                    <?php if (!empty($unmapped_bizbot_agents)): ?>
                                    <div class="input-group-btn">
                                        <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" title="ডিটেক্টেড এজেন্ট থেকে বাছুন">
                                            <i class="fa fa-magic text-sky-600"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-right">
                                            <li class="dropdown-header">ডিটেক্টেড বিজবট এজেন্ট</li>
                                            <?php foreach ($unmapped_bizbot_agents as $u_guid => $u_data): ?>
                                            <li>
                                                <a href="#" class="btn-quick-pick-guid" data-guid="<?= e($u_guid) ?>">
                                                    <b><?= e($u_data['name'] ?? 'Agent') ?></b> <span class="text-muted">(<?= substr($u_guid, 0, 8) ?>...)</span>
                                                </a>
                                            </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <span class="help-block tw-mb-0 tw-text-xs text-muted" style="font-size: 10px;">
                                    Bizbot Console থেকে মেসেজ আসলে স্বয়ংক্রিয়ভাবে লিড অ্যাসাইন হবে।
                                </span>
                            </td>

                            <!-- 4. WhatsApp Intro Aliases -->
                            <td>
                                <div class="input-group input-group-sm">
                                    <input type="text" name="whatsapp_aliases" class="form-control input-sm input-aliases" 
                                           value="<?= e($stf['whatsapp_aliases']) ?>" 
                                           placeholder="ডাকনাম, কমা দিয়ে লিখুন">
                                    <span class="input-group-btn">
                                        <button type="button" class="btn btn-default btn-auto-aliases" title="✨ নাম থেকে স্বয়ংক্রিয় ডাকনাম তৈরি করুন">
                                            <i class="fa fa-wand-magic-sparkles text-primary"></i>
                                        </button>
                                    </span>
                                </div>
                                <span class="help-block tw-mb-0 tw-text-xs text-muted" style="font-size: 10px;">
                                    যেমন: "আমি <b><?= e(mb_strtolower($stf['firstname'])) ?></b> বলছি"
                                </span>
                            </td>

                            <!-- 5. Actions -->
                            <td class="text-center" style="vertical-align: middle;">
                                <button type="button" class="btn btn-success btn-sm btn-save-row" title="সেভ করুন">
                                    <i class="fa fa-save"></i> Save
                                </button>
                            </td>

                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Help Callout -->
            <div class="well well-sm tw-mb-0 tw-mt-3 tw-bg-neutral-50 tw-border-neutral-200">
                <h5 class="tw-font-bold tw-text-neutral-700 tw-mt-0 tw-mb-1">
                    <i class="fa fa-circle-question text-info tw-mr-1"></i> কীভাবে এটি কাজ করে?
                </h5>
                <ul class="tw-text-xs tw-text-neutral-600 tw-pl-4 tw-mb-0 tw-space-y-1">
                    <li><b>PBX এক্সটেনশন:</b> পিবিএক্সের সাথে কানেক্টেড থাকলে এক্সটেনশনগুলো লাইভ পাওয়া যায়। কোনো কর্মীর নামের পাশে এক্সটেনশন সিলেক্ট করলে সিআরএম-এর যে কোনো নাম্বার থেকে Click-to-Call চাপলে তার ফোনে রিং হয়ে কাস্টমারে সংযোগ হবে।</li>
                    <li><b>Bizbot WhatsApp Agent ID:</b> কর্মীরা যখন বিজবট ওয়েব কনসোল থেকে কোনো কাস্টমারকে রিপ্লাই করে, সিআরএম স্বয়ংক্রিয়ভাবে ওই লিডটি উক্ত কর্মীর নামে ক্লেইম/অ্যাসাইন করে নেয়।</li>
                    <li><b>WhatsApp ডাকনাম (Aliases):</b> কর্মীরা যখন সরাসরি মোবাইলের হোয়াটসঅ্যাপ থেকে কাস্টমারকে মেসেজ পাঠায় এবং নিজের পরিচয় দেয় (যেমন: "আমি মোস্তাফিজ বলছি"), সিআরএম মেসেজ স্ক্যান করে স্বয়ংক্রিয়ভাবে লিড তার নামে ক্লেইম করে নেয়।</li>
                </ul>
            </div>

        </div>
    </div>

</div>
</div>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var csrfTokenName = "<?= $this->security->get_csrf_token_name() ?>";
    var csrfHash = "<?= $this->security->get_csrf_hash() ?>";

    // Handle Custom Extension option in dropdown
    $(document).on('change', '.select-extension', function () {
        var $row = $(this).closest('tr');
        var val = $(this).val();
        var $btnRing = $row.find('.btn-test-ring');

        if (val === 'custom_ext') {
            $row.find('.custom-ext-container').slideDown(150);
            $row.find('.custom-ext-input').focus();
            $btnRing.prop('disabled', false);
        } else {
            $row.find('.custom-ext-container').slideUp(150);
            $btnRing.prop('disabled', val === '');
        }
    });

    // Quick pick Bizbot GUID from dropdown
    $(document).on('click', '.btn-quick-pick-guid', function (e) {
        e.preventDefault();
        var guid = $(this).data('guid');
        var $row = $(this).closest('tr');
        $row.find('.input-bizbot-guid').val(guid).trigger('input');
        alert_float('info', 'Bizbot Agent ID সিলেক্ট করা হয়েছে। Save বাটনে ক্লিক করুন।');
    });

    // Auto-generate Aliases via AJAX
    $(document).on('click', '.btn-auto-aliases', function () {
        var $btn = $(this);
        var $row = $btn.closest('tr');
        var staffId = $row.data('staff-id');
        var $input = $row.find('.input-aliases');

        $btn.prop('disabled', true).find('i').addClass('fa-spin');

        $.ajax({
            url: admin_url + 'pbxpilot/agents/auto_aliases_ajax',
            type: 'POST',
            dataType: 'json',
            data: {
                staff_id: staffId,
                [csrfTokenName]: csrfHash
            },
            success: function (res) {
                $btn.prop('disabled', false).find('i').removeClass('fa-spin');
                if (res.success && res.aliases) {
                    var current = $input.val().trim();
                    if (current === '') {
                        $input.val(res.aliases);
                    } else {
                        // Merge uniquely
                        var existingArr = current.split(',').map(function(s){ return s.trim(); });
                        var newArr = res.aliases.split(',').map(function(s){ return s.trim(); });
                        var merged = Array.from(new Set(existingArr.concat(newArr))).join(', ');
                        $input.val(merged);
                    }
                    alert_float('success', 'স্বয়ংক্রিয় ডাকনাম জেনারেট করা হয়েছে!');
                } else {
                    alert_float('warning', res.message || 'ডাকনাম জেনারেট করা যায়নি।');
                }
            },
            error: function () {
                $btn.prop('disabled', false).find('i').removeClass('fa-spin');
                alert_float('danger', 'সার্ভার রেসপন্স করেনি।');
            }
        });
    });

    // Test Ring via AJAX
    $(document).on('click', '.btn-test-ring', function () {
        var $btn = $(this);
        var $row = $btn.closest('tr');
        var extSelect = $row.find('.select-extension').val();
        var ext = extSelect === 'custom_ext' ? $row.find('.custom-ext-input').val().trim() : extSelect;

        if (!ext) {
            alert_float('warning', 'দয়া করে প্রথমে এক্সটেনশন সিলেক্ট করুন।');
            return;
        }

        $btn.prop('disabled', true).find('i').removeClass('fa-bell').addClass('fa-spinner fa-spin');

        $.ajax({
            url: admin_url + 'pbxpilot/agents/test_ring_ajax',
            type: 'POST',
            dataType: 'json',
            data: {
                extension: ext,
                [csrfTokenName]: csrfHash
            },
            success: function (res) {
                $btn.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-bell');
                if (res.success) {
                    alert_float('success', res.message);
                } else {
                    alert_float('danger', res.message || 'টেস্ট রিং পাঠানো সম্ভব হয়নি।');
                }
            },
            error: function () {
                $btn.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-bell');
                alert_float('danger', 'পিবিএক্স কানেকশন ব্যর্থ হয়েছে।');
            }
        });
    });

    // Save Row via AJAX
    $(document).on('click', '.btn-save-row', function () {
        var $btn = $(this);
        var $row = $btn.closest('tr');
        var staffId = $row.data('staff-id');

        var extSelect = $row.find('.select-extension').val();
        var extension = extSelect === 'custom_ext' ? $row.find('.custom-ext-input').val().trim() : extSelect;
        var guid = $row.find('.input-bizbot-guid').val().trim();
        var aliases = $row.find('.input-aliases').val().trim();

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving');

        $.ajax({
            url: admin_url + 'pbxpilot/agents/save_ajax',
            type: 'POST',
            dataType: 'json',
            data: {
                staff_id: staffId,
                extension: extension,
                bizbot_agent_guid: guid,
                whatsapp_aliases: aliases,
                [csrfTokenName]: csrfHash
            },
            success: function (res) {
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save');
                if (res.success) {
                    alert_float('success', res.message);
                    $row.css('background-color', '#f0fdf4');
                    setTimeout(function () {
                        $row.css('background-color', '');
                    }, 1800);
                } else {
                    alert_float('danger', res.message || 'সংরক্ষণে ত্রুটি হয়েছে।');
                }
            },
            error: function () {
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save');
                alert_float('danger', 'সার্ভারে রিকোয়েস্ট পাঠানো সম্ভব হয়নি।');
            }
        });
    });

    // Assign Discovered Agent from Banner
    $(document).on('click', '.btn-assign-discovered', function (e) {
        e.preventDefault();
        var guid = $(this).data('guid');
        var staffId = $(this).data('staff-id');
        var $targetRow = $('#staff-row-' + staffId);

        if ($targetRow.length) {
            $targetRow.find('.input-bizbot-guid').val(guid);
            $targetRow.find('.btn-save-row').trigger('click');
            $(this).closest('.badge-discovered-agent').fadeOut(300);
        }
    });

    // Dismiss Discovered Agent from Banner
    $(document).on('click', '.btn-dismiss-discovered', function () {
        var $badge = $(this).closest('.badge-discovered-agent');
        var guid = $(this).data('guid');

        $.ajax({
            url: admin_url + 'pbxpilot/agents/dismiss_unmapped_ajax',
            type: 'POST',
            dataType: 'json',
            data: {
                guid: guid,
                [csrfTokenName]: csrfHash
            },
            success: function () {
                $badge.fadeOut(300);
            }
        });
    });
});
</script>

<?php init_tail(); ?>
