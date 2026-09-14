<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
<div class="content">
<div class="row">
<div class="col-md-8 col-md-offset-2">

    <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
        <h4 class="tw-font-bold tw-mt-0 tw-mb-0 tw-text-neutral-800"><?= _l('pbxpilot_agents_title') ?></h4>
        <a href="<?= admin_url('pbxpilot/settings') ?>" class="btn btn-default btn-sm">
            <i class="fa-solid fa-gear"></i> <?= _l('pbxpilot_settings_title') ?>
        </a>
    </div>

    <div class="panel_s">
        <div class="panel-body">
            <h4 class="tw-mb-1"><?= _l('pbxpilot_agents_intro_title') ?></h4>
            <p class="text-muted"><?= _l('pbxpilot_agents_intro_text') ?></p>

            <form method="POST" action="<?= admin_url('pbxpilot/agents') ?>" class="tw-flex tw-items-end tw-gap-2 tw-mb-6">
                <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">

                <div class="form-group tw-mb-0 tw-flex-1">
                    <label for="staff_id"><?= _l('pbxpilot_agents_staff') ?></label>
                    <select name="staff_id" id="staff_id" class="form-control selectpicker" required>
                        <option value=""><?= _l('pbxpilot_agents_select_staff') ?></option>
                        <?php foreach ($staff as $s): ?>
                        <option value="<?= (int) $s['staffid'] ?>"><?= e($s['firstname'] . ' ' . $s['lastname'] . ' (' . $s['email'] . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group tw-mb-0">
                    <label for="extension"><?= _l('pbxpilot_agents_extension') ?></label>
                    <input type="text" name="extension" id="extension" class="form-control" placeholder="e.g. 1001" pattern="[0-9]+" required>
                </div>

                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> <?= _l('pbxpilot_agents_map_btn') ?></button>
            </form>

            <table class="table">
                <thead>
                    <tr>
                        <th><?= _l('pbxpilot_agents_table_staff') ?></th>
                        <th><?= _l('pbxpilot_agents_table_extension') ?></th>
                        <th><?= _l('pbxpilot_agents_table_status') ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($agents)): ?>
                    <tr><td colspan="4" class="text-muted"><?= _l('pbxpilot_agents_none') ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($agents as $a): ?>
                    <tr>
                        <td><?= e($a['staff_name']) ?></td>
                        <td><?= e($a['extension']) ?></td>
                        <td>
                            <?php if ($a['is_active'] == 1): ?>
                                <span class="label label-success"><?= _l('pbxpilot_agents_status_active') ?></span>
                            <?php else: ?>
                                <span class="label label-default"><?= _l('pbxpilot_agents_status_inactive') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right">
                            <a href="<?= admin_url('pbxpilot/agents/delete/' . $a['id']) ?>"
                               class="btn btn-danger btn-icon"
                               onclick="return confirm('<?= _l('confirm_remove_agent_mapping') ?>');">
                                <i class="fa-solid fa-trash"></i>
                            </a>
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
