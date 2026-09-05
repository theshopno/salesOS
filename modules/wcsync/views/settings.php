<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <!-- Left side: Form -->
            <div class="col-md-5">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin font-medium" id="form-title">
                            <i class="fa fa-plug"></i> Add WooCommerce Site
                        </h4>
                        <hr class="hr-panel-heading" />

                        <?php if (empty($credentials)): ?>
                            <div class="alert alert-warning mtop15">
                                <strong><i class="fa fa-exclamation-triangle"></i> No WooCommerce credentials found.</strong>
                                <p>You must add site credentials in the credentials vault first before configuring a site connection.</p>
                                <div style="margin-top:10px;">
                                    <a href="<?= admin_url('ecomcore/integrations') ?>" class="btn btn-warning btn-sm">Go to Credentials Vault</a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?= form_open(admin_url('wcsync/settings'), ['id' => 'site-form']) ?>
                        <input type="hidden" name="id" id="site_id">

                        <div class="form-group">
                            <label for="name" class="control-label">Site Name / Display Label</label>
                            <input type="text" name="name" id="site_name" class="form-control" placeholder="e.g. WooCommerce Main Store" required>
                        </div>

                        <div class="form-group">
                            <label for="site_url" class="control-label">Site URL (with http/https)</label>
                            <input type="url" name="site_url" id="site_url" class="form-control" placeholder="e.g. https://my-woocommerce.com" required>
                        </div>

                        <div class="form-group">
                            <label for="credential_id" class="control-label">Vault Credential Link</label>
                            <select name="credential_id" id="site_credential_id" class="form-control" required <?= empty($credentials) ? 'disabled' : '' ?>>
                                <option value="">Select Credential...</option>
                                <?php foreach ($credentials as $cred): ?>
                                    <option value="<?= $cred['id'] ?>"><?= e($cred['label']) ?> (<?= e($cred['owner_module']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="default_lead_status_id" class="control-label">Default Import Lead Status</label>
                            <select name="default_lead_status_id" id="site_default_lead_status_id" class="form-control" required>
                                <option value="">Select Lead Status...</option>
                                <?php foreach ($lead_statuses as $status): ?>
                                    <option value="<?= $status['id'] ?>"><?= e($status['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mtop15">
                            <button type="submit" class="btn btn-primary" id="btn-submit" <?= empty($credentials) ? 'disabled' : '' ?>>Save Site</button>
                            <button type="button" class="btn btn-default" id="btn-cancel" style="display:none;">Cancel Edit</button>
                        </div>
                        <?= form_close() ?>
                    </div>
                </div>
            </div>

            <!-- Right side: List -->
            <div class="col-md-7">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin font-medium">
                            <i class="fa fa-list"></i> Configured Sites
                        </h4>
                        <hr class="hr-panel-heading" />
                        
                        <?php if (empty($sites)): ?>
                            <p class="text-muted no-margin">No WooCommerce sites connected yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped no-mtop">
                                    <thead>
                                        <tr>
                                            <th>Site Name</th>
                                            <th>Site URL</th>
                                            <th>Last Synced</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sites as $site): ?>
                                            <tr>
                                                <td><strong><?= e($site['name']) ?></strong></td>
                                                <td><a href="<?= e($site['site_url']) ?>" target="_blank"><?= e($site['site_url']) ?></a></td>
                                                <td>
                                                    <span class="text-muted"><?= $site['last_synced_at'] ? e($site['last_synced_at']) : 'Never Synced' ?></span>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-default btn-xs edit-site-btn"
                                                            data-id="<?= $site['id'] ?>"
                                                            data-name="<?= e($site['name']) ?>"
                                                            data-site-url="<?= e($site['site_url']) ?>"
                                                            data-credential-id="<?= $site['credential_id'] ?>"
                                                            data-default-lead-status-id="<?= $site['default_lead_status_id'] ?>">
                                                        <i class="fa fa-pencil"></i>
                                                    </button>
                                                    <a href="<?= admin_url('wcsync/delete_site/' . $site['id']) ?>" class="btn btn-danger btn-xs _delete">
                                                        <i class="fa fa-remove"></i>
                                                    </a>
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
    </div>
</div>

<?php init_tail(); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var editButtons = document.querySelectorAll('.edit-site-btn');
    var formTitle = document.getElementById('form-title');
    var idInput = document.getElementById('site_id');
    var nameInput = document.getElementById('site_name');
    var urlInput = document.getElementById('site_url');
    var credSelect = document.getElementById('site_credential_id');
    var statusSelect = document.getElementById('site_default_lead_status_id');
    var submitButton = document.getElementById('btn-submit');
    var cancelButton = document.getElementById('btn-cancel');

    editButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var name = this.getAttribute('data-name');
            var url = this.getAttribute('data-site-url');
            var credentialId = this.getAttribute('data-credential-id');
            var statusId = this.getAttribute('data-default-lead-status-id');

            // Form fill
            idInput.value = id;
            nameInput.value = name;
            urlInput.value = url;
            credSelect.value = credentialId;
            statusSelect.value = statusId;

            formTitle.innerHTML = '<i class="fa fa-pencil"></i> Edit WooCommerce Site';
            submitButton.innerHTML = 'Update Site';
            cancelButton.style.display = 'inline-block';
        });
    });

    cancelButton.addEventListener('click', function() {
        document.getElementById('site-form').reset();
        idInput.value = '';
        formTitle.innerHTML = '<i class="fa fa-plug"></i> Add WooCommerce Site';
        submitButton.innerHTML = 'Save Site';
        this.style.display = 'none';
    });
});
</script>
