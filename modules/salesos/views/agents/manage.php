<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
<div class="content">
<div class="row">
<div class="col-md-8 col-md-offset-2">

    <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
        <h4 class="tw-text-xl tw-font-bold tw-mb-0">SalesOS Agents</h4>
        <a href="<?= admin_url('salesos/settings') ?>" class="btn btn-default btn-sm">
            <i class="fa fa-cog"></i> Settings
        </a>
    </div>

    <div class="panel_s">
        <div class="panel-body">
            <h4>Map a staff member to a PBX extension</h4>
            <p class="text-muted">Needed for click-to-call — Ami_service dials the agent's extension first, then bridges to the destination.</p>

            <form method="POST" action="<?= admin_url('salesos/agents') ?>" class="tw-flex tw-items-end tw-gap-2 tw-mb-6">
                <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">

                <div class="form-group tw-mb-0 tw-flex-1">
                    <label for="staff_id">Staff</label>
                    <select name="staff_id" id="staff_id" class="form-control selectpicker" required>
                        <option value="">— select —</option>
                        <?php foreach ($staff as $s): ?>
                        <option value="<?= (int) $s['staffid'] ?>"><?= e($s['firstname'] . ' ' . $s['lastname'] . ' (' . $s['email'] . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group tw-mb-0">
                    <label for="extension">Extension</label>
                    <input type="text" name="extension" id="extension" class="form-control" placeholder="e.g. 1001" pattern="[0-9]+" required>
                </div>

                <button type="submit" class="btn btn-primary"><i class="fa fa-plus"></i> Map</button>
            </form>

            <table class="table">
                <thead>
                    <tr>
                        <th>Staff</th>
                        <th>Extension</th>
                        <th>Active</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($agents)): ?>
                    <tr><td colspan="4" class="text-muted">No agents mapped yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($agents as $a): ?>
                    <tr>
                        <td><?= e($a['staff_name']) ?></td>
                        <td><?= e($a['extension']) ?></td>
                        <td>
                            <?php if ($a['is_active'] == 1): ?>
                                <span class="label label-success">Active</span>
                            <?php else: ?>
                                <span class="label label-default">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right">
                            <a href="<?= admin_url('salesos/agents/delete/' . $a['id']) ?>"
                               class="btn btn-danger btn-icon"
                               onclick="return confirm('<?= _l('confirm_remove_agent_mapping') ?: 'Remove this agent mapping?' ?>');">
                                <i class="fa fa-trash-o"></i>
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
