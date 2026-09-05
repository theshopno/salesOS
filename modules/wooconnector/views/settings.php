<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
<div class="content">
<div class="row">
<div class="col-md-8 col-md-offset-2">

    <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
        <h4 class="tw-text-xl tw-font-bold tw-mb-0">WooConnector Settings</h4>
        <a href="<?= admin_url('wooconnector') ?>" class="btn btn-default btn-sm">
            <i class="fa fa-dashboard"></i> Dashboard
        </a>
    </div>

    <div class="panel_s">
        <div class="panel-body">
            <h4>WooCommerce Site Connection</h4>
            <p class="text-muted">
                Generate a Consumer Key/Secret from your WordPress site under
                <strong>WooCommerce &rarr; Settings &rarr; Advanced &rarr; REST API</strong> (read-only permission is enough).
            </p>

            <form method="POST" action="<?= admin_url('wooconnector/settings') ?>">
                <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">

                <div class="form-group">
                    <label for="name">Site Name</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?= e($site['name'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="site_url">Site URL</label>
                    <input type="url" id="site_url" name="site_url" class="form-control" placeholder="https://example.com" value="<?= e($site['site_url'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="consumer_key">Consumer Key</label>
                    <input type="text" id="consumer_key" name="consumer_key" class="form-control" value="<?= e($site['consumer_key'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="consumer_secret">Consumer Secret</label>
                    <input type="password" id="consumer_secret" name="consumer_secret" class="form-control" value="<?= e($site['consumer_secret'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="default_lead_status_id">New leads get this status</label>
                    <select id="default_lead_status_id" name="default_lead_status_id" class="form-control selectpicker" required>
                        <?php foreach ($lead_statuses as $status): ?>
                        <option value="<?= $status['id'] ?>" <?= (isset($site['default_lead_status_id']) && (int) $site['default_lead_status_id'] === (int) $status['id']) ? 'selected' : '' ?>>
                            <?= e($status['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (staff_can('settings', 'wooconnector')): ?>
                <button type="submit" class="btn btn-primary"><?= _l('submit') ?></button>
                <?php endif; ?>
            </form>
        </div>
    </div>

</div>
</div>
</div>
</div>

<?php init_tail(); ?>
