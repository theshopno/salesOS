<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<?php 
// Determine active tab
$active_tab = $this->input->get('tab') ?: 'channels';
?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                
                <!-- Navigation Tabs -->
                <div class="horizontal-scrollable-tabs mbot15">
                    <div class="scroller scroller-left" style="display: none;"><i class="fa fa-chevron-left"></i></div>
                    <div class="scroller scroller-right" style="display: none;"><i class="fa fa-chevron-right"></i></div>
                    <ul class="nav nav-tabs nav-tabs-horizontal" role="tablist">
                        <li role="presentation" class="<?= $active_tab === 'channels' ? 'active' : '' ?>">
                            <a href="#channels" aria-controls="channels" role="tab" data-toggle="tab">
                                <i class="fa fa-plug"></i> Channel Integrations
                            </a>
                        </li>
                        <li role="presentation" class="<?= $active_tab === 'couriers' ? 'active' : '' ?>">
                            <a href="#couriers" aria-controls="couriers" role="tab" data-toggle="tab">
                                <i class="fa fa-truck"></i> Courier Accounts
                            </a>
                        </li>
                        <li role="presentation" class="<?= $active_tab === 'notifications' ? 'active' : '' ?>">
                            <a href="#notifications" aria-controls="notifications" role="tab" data-toggle="tab">
                                <i class="fa fa-bell"></i> Notifications Settings
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Tab Panes Content -->
                <div class="tab-content">
                    
                    <!-- TAB 1: CHANNEL INTEGRATIONS -->
                    <div role="tabpanel" class="tab-pane <?= $active_tab === 'channels' ? 'active' : '' ?>" id="channels">
                        <div class="row">
                            <!-- Left side: Form -->
                            <div class="col-md-5">
                                <div class="panel_s">
                                    <div class="panel-body">
                                        <h4 class="no-margin font-medium text-primary" id="form-title">
                                            <i class="fa fa-key"></i> Add New Credential
                                        </h4>
                                        <hr class="hr-panel-heading" />
                                        
                                        <?= form_open(admin_url('salesos/settings'), ['id' => 'credential-form']) ?>
                                        <input type="hidden" name="id" id="cred_id">

                                        <div class="form-group">
                                            <label for="owner_module" class="control-label">Owner Module</label>
                                            <select name="owner_module" id="cred_owner_module" class="form-control" required>
                                                <option value="">Select Module...</option>
                                                <option value="wcsync">wcsync (WooCommerce Connector)</option>
                                                <option value="courier">courier (Pathao/Steadfast/Redx)</option>
                                                <option value="fraudcheck">fraudcheck (BDCourier Check)</option>
                                                <option value="ordernotifier">ordernotifier (WhatsApp/SMS)</option>
                                            </select>
                                        </div>

                                        <div class="form-group">
                                            <label for="label" class="control-label">Label / Name</label>
                                            <input type="text" name="label" id="cred_label" class="form-control" placeholder="e.g. WooCommerce Prod Store" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="cred_type" class="control-label">Credential Type</label>
                                            <select name="cred_type" id="cred_cred_type" class="form-control" required>
                                                <option value="api_key">API Key</option>
                                                <option value="basic_auth">Basic Auth (Username/Password)</option>
                                                <option value="oauth">OAuth Tokens</option>
                                            </select>
                                        </div>

                                        <!-- Dynamic inputs container (Generates fields dynamically based on selection) -->
                                        <div id="dynamic-fields-container" class="mbot15"></div>

                                        <!-- Hidden advanced payload textarea -->
                                        <div class="form-group" id="raw-payload-group" style="display: none;">
                                            <label for="payload" class="control-label">Raw Payload Config (JSON)</label>
                                            <textarea name="payload" id="cred_payload" class="form-control" rows="6"></textarea>
                                        </div>

                                        <div class="mbot10">
                                            <a href="#" id="toggle-advanced-payload" style="font-size: 11px;"><i class="fa fa-code"></i> Advanced: Show/Edit Raw JSON Config</a>
                                        </div>

                                        <div class="checkbox checkbox-primary">
                                            <input type="checkbox" name="is_active" id="cred_is_active" value="1" checked>
                                            <label for="cred_is_active">Active</label>
                                        </div>

                                        <div class="mtop15">
                                            <button type="submit" class="btn btn-primary" id="btn-submit">Save Credential</button>
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
                                        <h4 class="no-margin font-medium text-primary">
                                            <i class="fa fa-list"></i> Saved Credentials Vault
                                        </h4>
                                        <hr class="hr-panel-heading" />
                                        
                                        <?php if (empty($credentials)): ?>
                                            <p class="text-muted no-margin">No credentials stored in the vault yet.</p>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-bordered table-striped no-mtop">
                                                    <thead>
                                                        <tr>
                                                            <th>Label</th>
                                                            <th>Module</th>
                                                            <th>Type</th>
                                                            <th>Status</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($credentials as $cred): ?>
                                                            <tr>
                                                                <td>
                                                                    <strong><?= e($cred['label']) ?></strong>
                                                                    <br><small class="text-muted">Created: <?= e($cred['created_at']) ?></small>
                                                                </td>
                                                                <td><code><?= e($cred['owner_module']) ?></code></td>
                                                                <td><span class="label label-default text-uppercase"><?= str_replace('_', ' ', e($cred['cred_type'])) ?></span></td>
                                                                <td>
                                                                    <div class="onoffswitch">
                                                                        <input type="checkbox" name="onoffswitch" class="onoffswitch-checkbox toggle-credential-status" id="c_<?= $cred['id'] ?>" data-id="<?= $cred['id'] ?>" <?= $cred['is_active'] ? 'checked' : '' ?>>
                                                                        <label class="onoffswitch-label" for="c_<?= $cred['id'] ?>"></label>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <button class="btn btn-default btn-icon btn-xs edit-credential-btn" 
                                                                            data-id="<?= $cred['id'] ?>"
                                                                            data-owner="<?= e($cred['owner_module']) ?>"
                                                                            data-label="<?= e($cred['label']) ?>"
                                                                            data-type="<?= e($cred['cred_type']) ?>"
                                                                            data-active="<?= $cred['is_active'] ?>"
                                                                            data-payload='<?= htmlspecialchars(json_encode($cred['payload_decrypted'] ?? []), ENT_QUOTES, 'UTF-8') ?>'
                                                                            title="Edit">
                                                                        <i class="fa fa-pencil-square-o"></i>
                                                                    </button>
                                                                    <a href="<?= admin_url('salesos/delete_credential/' . $cred['id']) ?>" class="btn btn-danger btn-icon btn-xs _delete" title="Delete">
                                                                        <i class="fa fa-trash" style="color:#fff !important;"></i>
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

                    <!-- TAB 2: COURIER ACCOUNTS -->
                    <div role="tabpanel" class="tab-pane <?= $active_tab === 'couriers' ? 'active' : '' ?>" id="couriers">
                        <div class="row">
                            <!-- Left side: Accounts Registry -->
                            <div class="col-md-7">
                                <div class="panel_s">
                                    <div class="panel-body">
                                        <h4 class="no-margin bold font-medium text-primary mbot15">
                                            <i class="fa fa-cogs"></i> Courier Accounts Registry
                                        </h4>
                                        <hr class="hr-panel-separator" />

                                        <div class="table-responsive">
                                            <table class="table table-bordered table-striped no-mtop">
                                                <thead>
                                                    <tr>
                                                        <th>Label</th>
                                                        <th>Provider</th>
                                                        <th>Store / Pickup</th>
                                                        <th>Balance</th>
                                                        <th>Default</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (empty($courier_accounts)): ?>
                                                        <tr>
                                                            <td colspan="7" class="text-center text-muted">No courier accounts configured yet.</td>
                                                        </tr>
                                                    <?php else: ?>
                                                        <?php 
                                                            $CI = &get_instance();
                                                            foreach ($courier_accounts as $acc): 
                                                                $balance = false;
                                                                if ($acc['provider'] === 'steadfast') {
                                                                    $full_acc = $CI->courier_model->get_account($acc['id']);
                                                                    if ($full_acc && !empty($full_acc['api_key'])) {
                                                                        $balance = $CI->courier_model->check_steadfast_balance($full_acc['api_key'], $full_acc['secret_key']);
                                                                    }
                                                                }
                                                        ?>
                                                            <tr>
                                                                <td class="bold"><?= e($acc['label']) ?></td>
                                                                <td>
                                                                    <span class="label label-<?= $acc['provider'] === 'pathao' ? 'warning' : 'info' ?>">
                                                                        <?= strtoupper(e($acc['provider'])) ?>
                                                                    </span>
                                                                    <?php if ($acc['provider'] === 'pathao' && $acc['environment']): ?>
                                                                        <small class="text-muted display-block"><?= ucfirst($acc['environment']) ?></small>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?php if ($acc['provider'] === 'pathao' && $acc['store_id']): ?>
                                                                        <small>Store ID: <code><?= e($acc['store_id']) ?></code></small>
                                                                    <?php endif; ?>
                                                                    <small class="text-muted display-block"><?= e($acc['pickup_address'] ?: '-') ?></small>
                                                                </td>
                                                                <td class="bold text-success">
                                                                    <?php if ($acc['provider'] === 'steadfast'): ?>
                                                                        <?= $balance !== false ? number_format($balance, 2) . ' BDT' : '<span class="text-danger">Offline / Error</span>' ?>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">N/A (OAuth)</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?= $acc['is_default'] ? '<span class="label label-success">Yes</span>' : '<span class="label label-default">No</span>' ?>
                                                                </td>
                                                                <td>
                                                                    <?= $acc['is_active'] ? '<span class="label label-success">Active</span>' : '<span class="label label-danger">Inactive</span>' ?>
                                                                </td>
                                                                <td>
                                                                    <?php if (staff_can('delete', 'courier')): ?>
                                                                        <a href="<?= admin_url('courier/delete_account/' . $acc['id']) ?>" class="btn btn-danger btn-icon btn-xs _delete" title="Delete">
                                                                            <i class="fa fa-trash" style="color: #ffffff !important;"></i>
                                                                        </a>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right side: Add Account Form -->
                            <?php if (staff_can('edit', 'courier')): ?>
                                <div class="col-md-5">
                                    <div class="panel_s">
                                        <div class="panel-body">
                                            <h4 class="no-margin bold font-medium text-primary mbot15">
                                                <i class="fa fa-plus"></i> Configure Courier Account
                                            </h4>
                                            <hr class="hr-panel-separator" />

                                            <?= form_open(admin_url('courier/settings')) ?>
                                            
                                            <div class="form-group">
                                                <label for="provider" class="control-label">Courier Provider</label>
                                                <select name="provider" id="provider" class="form-control" required>
                                                    <option value="steadfast" selected>Steadfast Courier Limited</option>
                                                    <option value="pathao">Pathao Courier</option>
                                                </select>
                                            </div>

                                            <div class="form-group">
                                                <label for="label" class="control-label">Account Label</label>
                                                <input type="text" name="label" id="label" class="form-control" required placeholder="e.g. Steadfast Dhaka Office">
                                            </div>

                                            <!-- Steadfast Credentials -->
                                            <div id="steadfast_credentials_group">
                                                <div class="form-group">
                                                    <label for="api_key" class="control-label">API Key</label>
                                                    <input type="password" name="api_key" id="api_key" class="form-control" autocomplete="new-password">
                                                </div>

                                                <div class="form-group">
                                                    <label for="secret_key" class="control-label">Secret Key</label>
                                                    <input type="password" name="secret_key" id="secret_key" class="form-control" autocomplete="new-password">
                                                </div>
                                            </div>

                                            <!-- Pathao Credentials -->
                                            <div id="pathao_credentials_group" style="display: none;">
                                                <div class="form-group">
                                                    <label for="client_id" class="control-label">Client ID</label>
                                                    <input type="password" name="client_id" id="client_id" class="form-control" autocomplete="new-password">
                                                </div>

                                                <div class="form-group">
                                                    <label for="client_secret" class="control-label">Client Secret</label>
                                                    <input type="password" name="client_secret" id="client_secret" class="form-control" autocomplete="new-password">
                                                </div>

                                                <div class="form-group">
                                                    <label for="environment" class="control-label">Environment</label>
                                                    <select name="environment" id="environment" class="form-control">
                                                        <option value="staging">Sandbox (Staging)</option>
                                                        <option value="live" selected>Production (Live)</option>
                                                    </select>
                                                </div>

                                                <div class="form-group">
                                                    <label for="store_id" class="control-label">Store ID (Optional)</label>
                                                    <input type="text" name="store_id" id="store_id" class="form-control" placeholder="e.g. 12345 (Leave empty to fetch automatically)">
                                                </div>
                                            </div>

                                            <div class="form-group" id="pickup_address_group">
                                                <label for="pickup_address" class="control-label">Pickup Address</label>
                                                <textarea name="pickup_address" id="pickup_address" class="form-control" rows="3" placeholder="Exact address for courier dispatch pickup..."></textarea>
                                                <small class="text-muted pathao-help" style="display: none;">For Pathao, the default store and its address will be fetched automatically if Store ID is left empty.</small>
                                            </div>

                                            <div class="checkbox checkbox-primary">
                                                <input type="checkbox" name="is_default" id="is_default" value="1">
                                                <label for="is_default">Set as default courier provider</label>
                                            </div>

                                            <div class="checkbox checkbox-primary">
                                                <input type="checkbox" name="is_active" id="is_active" value="1" checked>
                                                <label for="is_active">Active</label>
                                            </div>

                                            <div class="text-right mtop15">
                                                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Account</button>
                                            </div>

                                            <?= form_close() ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- TAB 3: NOTIFICATIONS SETTINGS -->
                    <div role="tabpanel" class="tab-pane <?= $active_tab === 'notifications' ? 'active' : '' ?>" id="notifications">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="panel_s">
                                    <div class="panel-body">
                                        <h4 class="no-margin font-medium text-primary">
                                            <i class="fa fa-bell"></i> Notifications Channels & Templates
                                        </h4>
                                        <hr class="hr-panel-heading" />

                                        <?= form_open(admin_url('salesos/settings'), ['id' => 'notifications-settings-form']) ?>
                                        <input type="hidden" name="notifications_settings" value="1">

                                        <div class="row">
                                            <!-- WhatsApp Settings -->
                                            <div class="col-md-6" style="border-right: 1px solid #f1f5f9;">
                                                <h4 class="bold text-success mbot15">WhatsApp Channel Settings (Bizbot)</h4>
                                                
                                                <div class="checkbox checkbox-success mbot15">
                                                    <input type="checkbox" name="ordernotifier_whatsapp_enabled" id="ordernotifier_whatsapp_enabled" value="1" <?= get_option('ordernotifier_whatsapp_enabled') === '1' ? 'checked' : '' ?>>
                                                    <label for="ordernotifier_whatsapp_enabled" class="bold">Enable WhatsApp Notifications</label>
                                                </div>

                                                <div class="form-group">
                                                    <label for="ordernotifier_whatsapp_base_url" class="control-label">API Base URL</label>
                                                    <input type="text" name="ordernotifier_whatsapp_base_url" id="ordernotifier_whatsapp_base_url" class="form-control" value="<?= e(get_option('ordernotifier_whatsapp_base_url') ?: 'https://api.bizbot.bd') ?>" required>
                                                </div>

                                                <div class="form-group">
                                                    <label for="ordernotifier_whatsapp_token" class="control-label">API Access Token</label>
                                                    <input type="password" name="ordernotifier_whatsapp_token" id="ordernotifier_whatsapp_token" class="form-control" value="<?= e(get_option('ordernotifier_whatsapp_token')) ?>" autocomplete="new-password">
                                                </div>

                                                <hr />
                                                <h4 class="bold text-muted mbot15"><i class="fa fa-file-text-o"></i> WhatsApp Message Templates</h4>
                                                
                                                <div class="form-group">
                                                    <label for="ordernotifier_template_created_whatsapp" class="control-label">Order Created Template</label>
                                                    <textarea name="ordernotifier_template_created_whatsapp" id="ordernotifier_template_created_whatsapp" class="form-control" rows="3" placeholder="e.g. Hello {customer_name}, your order #{order_id} has been received."><?= e(get_option('ordernotifier_template_created_whatsapp') ?: "Hello {customer_name},\nYour order #{order_id} has been received successfully.\nTotal amount: {total} BDT.\nThank you!") ?></textarea>
                                                </div>

                                                <div class="form-group">
                                                    <label for="ordernotifier_template_confirmed_whatsapp" class="control-label">Order Confirmed Template</label>
                                                    <textarea name="ordernotifier_template_confirmed_whatsapp" id="ordernotifier_template_confirmed_whatsapp" class="form-control" rows="3" placeholder="e.g. Hello {customer_name}, your order #{order_id} is confirmed."><?= e(get_option('ordernotifier_template_confirmed_whatsapp') ?: "Hello {customer_name},\nYour order #{order_id} is confirmed and packed.\nCourier: {courier_name}.\nTracking ID: {tracking_id}.\nThank you!") ?></textarea>
                                                </div>

                                                <div class="form-group">
                                                    <label for="ordernotifier_template_cancelled_whatsapp" class="control-label">Order Cancelled Template</label>
                                                    <textarea name="ordernotifier_template_cancelled_whatsapp" id="ordernotifier_template_cancelled_whatsapp" class="form-control" rows="3" placeholder="e.g. Hello {customer_name}, your order #{order_id} has been cancelled."><?= e(get_option('ordernotifier_template_cancelled_whatsapp') ?: "Hello {customer_name},\nYour order #{order_id} has been cancelled.\nIf you have any questions, please contact our support.") ?></textarea>
                                                </div>
                                            </div>

                                            <!-- SMS Settings -->
                                            <div class="col-md-6" style="padding-left: 25px;">
                                                <h4 class="bold text-primary mbot15"><i class="fa fa-commenting-o"></i> Bulk SMS Channel Settings</h4>
                                                
                                                <div class="checkbox checkbox-primary mbot15">
                                                    <input type="checkbox" name="ordernotifier_sms_enabled" id="ordernotifier_sms_enabled" value="1" <?= get_option('ordernotifier_sms_enabled') === '1' ? 'checked' : '' ?>>
                                                    <label for="ordernotifier_sms_enabled" class="bold">Enable SMS Notifications</label>
                                                </div>

                                                <div class="form-group">
                                                    <label for="ordernotifier_sms_api_url" class="control-label">API Gateway URL</label>
                                                    <input type="text" name="ordernotifier_sms_api_url" id="ordernotifier_sms_api_url" class="form-control" value="<?= e(get_option('ordernotifier_sms_api_url')) ?>" placeholder="e.g. http://bulk.sms-provider.com/api/send">
                                                </div>

                                                <div class="form-group">
                                                    <label for="ordernotifier_sms_api_key" class="control-label">API Key / Token</label>
                                                    <input type="password" name="ordernotifier_sms_api_key" id="ordernotifier_sms_api_key" class="form-control" value="<?= e(get_option('ordernotifier_sms_api_key')) ?>" autocomplete="new-password">
                                                </div>

                                                <div class="form-group">
                                                    <label for="ordernotifier_sms_sender_id" class="control-label">Sender ID / Masking</label>
                                                    <input type="text" name="ordernotifier_sms_sender_id" id="ordernotifier_sms_sender_id" class="form-control" value="<?= e(get_option('ordernotifier_sms_sender_id')) ?>" placeholder="e.g. ELITEMART">
                                                </div>

                                                <hr />
                                                <h4 class="bold text-muted mbot15"><i class="fa fa-file-text-o"></i> SMS Message Templates</h4>
                                                
                                                <div class="form-group">
                                                    <label for="ordernotifier_template_created_sms" class="control-label">Order Created Template</label>
                                                    <textarea name="ordernotifier_template_created_sms" id="ordernotifier_template_created_sms" class="form-control" rows="3" placeholder="e.g. Order #{order_id} received."><?= e(get_option('ordernotifier_template_created_sms') ?: "Order #{order_id} received. Total: {total} BDT. Thank you!") ?></textarea>
                                                </div>

                                                <div class="form-group">
                                                    <label for="ordernotifier_template_confirmed_sms" class="control-label">Order Confirmed Template</label>
                                                    <textarea name="ordernotifier_template_confirmed_sms" id="ordernotifier_template_confirmed_sms" class="form-control" rows="3" placeholder="e.g. Order #{order_id} confirmed."><?= e(get_option('ordernotifier_template_confirmed_sms') ?: "Order #{order_id} is confirmed. Courier: {courier_name}. Tracking: {tracking_id}.") ?></textarea>
                                                </div>

                                                <div class="form-group">
                                                    <label for="ordernotifier_template_cancelled_sms" class="control-label">Order Cancelled Template</label>
                                                    <textarea name="ordernotifier_template_cancelled_sms" id="ordernotifier_template_cancelled_sms" class="form-control" rows="3" placeholder="e.g. Order #{order_id} cancelled."><?= e(get_option('ordernotifier_template_cancelled_sms') ?: "Order #{order_id} has been cancelled.") ?></textarea>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="text-right mtop25" style="border-top: 1px solid #f1f5f9; padding-top: 15px;">
                                            <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Notification Settings</button>
                                        </div>

                                        <?= form_close() ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<!-- Tab State URL Synchronizer -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // When Bootstrap tab is changed, update the URL query string
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        var target = $(e.target).attr("href").replace('#', '');
        var newurl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?tab=' + target;
        window.history.pushState({path:newurl},'',newurl);
    });
});
</script>

<!-- Courier Group Toggle Handler -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var providerSelect = document.getElementById('provider');
    var sfGroup = document.getElementById('steadfast_credentials_group');
    var ptGroup = document.getElementById('pathao_credentials_group');
    var sfApiKey = document.getElementById('api_key');
    var sfSecretKey = document.getElementById('secret_key');
    var ptClientId = document.getElementById('client_id');
    var ptClientSecret = document.getElementById('client_secret');
    var pathaoHelp = document.querySelector('.pathao-help');

    if (providerSelect) {
        providerSelect.addEventListener('change', function() {
            if (this.value === 'steadfast') {
                sfGroup.style.display = 'block';
                ptGroup.style.display = 'none';
                if (pathaoHelp) pathaoHelp.style.display = 'none';
                
                sfApiKey.setAttribute('required', 'required');
                sfSecretKey.setAttribute('required', 'required');
                ptClientId.removeAttribute('required');
                ptClientSecret.removeAttribute('required');
            } else if (this.value === 'pathao') {
                sfGroup.style.display = 'none';
                ptGroup.style.display = 'block';
                if (pathaoHelp) pathaoHelp.style.display = 'block';
                
                sfApiKey.removeAttribute('required');
                sfSecretKey.removeAttribute('required');
                ptClientId.setAttribute('required', 'required');
                ptClientSecret.setAttribute('required', 'required');
            }
        });

        // Run trigger once on load
        providerSelect.dispatchEvent(new Event('change'));
    }
});
</script>

<!-- Channel Credentials UI Builders -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var ownerSelect = document.getElementById('cred_owner_module');
    var typeSelect = document.getElementById('cred_cred_type');
    var container = document.getElementById('dynamic-fields-container');
    var rawTextarea = document.getElementById('cred_payload');

    function buildFields() {
        var owner = ownerSelect.value;
        var type = typeSelect.value;
        var html = '';

        if (owner === 'wcsync' || owner === 'wooconnector') {
            if (type === 'basic_auth') {
                html += '<div class="form-group">';
                html += '  <label class="control-label">WooCommerce Store URL</label>';
                html += '  <input type="text" name="payload[store_url]" class="form-control" required placeholder="https://example.com" data-field="store_url">';
                html += '</div>';
                html += '<div class="form-group">';
                html += '  <label class="control-label">Consumer Key (CK)</label>';
                html += '  <input type="text" name="payload[consumer_key]" class="form-control" required placeholder="ck_..." data-field="consumer_key">';
                html += '</div>';
                html += '<div class="form-group">';
                html += '  <label class="control-label">Consumer Secret (CS)</label>';
                html += '  <input type="password" name="payload[consumer_secret]" class="form-control" required autocomplete="new-password" data-field="consumer_secret">';
                html += '</div>';
            } else if (type === 'api_key') {
                html += '<div class="form-group">';
                html += '  <label class="control-label">API Access Token</label>';
                html += '  <input type="password" name="payload[api_key]" class="form-control" required autocomplete="new-password" data-field="api_key">';
                html += '</div>';
            }
        } else if (owner === 'ordernotifier') {
            html += '<div class="form-group">';
            html += '  <label class="control-label">SMS Provider/Gateway URL</label>';
            html += '  <input type="text" name="payload[gateway_url]" class="form-control" placeholder="https://api.sms-gateway.com/send" data-field="gateway_url">';
            html += '</div>';
            html += '<div class="form-group">';
            html += '  <label class="control-label">Sender Number / Sender ID</label>';
            html += '  <input type="text" name="payload[sender_id]" class="form-control" placeholder="e.g. 88017XXXXXXXX" data-field="sender_id">';
            html += '</div>';
            html += '<div class="form-group">';
            html += '  <label class="control-label">API Key / Token</label>';
            html += '  <input type="password" name="payload[api_token]" class="form-control" autocomplete="new-password" data-field="api_token">';
            html += '</div>';
        } else {
            // Default generic fields based on type
            if (type === 'api_key') {
                html += '<div class="form-group">';
                html += '  <label class="control-label">API Key</label>';
                html += '  <input type="password" name="payload[api_key]" class="form-control" required autocomplete="new-password" data-field="api_key">';
                html += '</div>';
            } else if (type === 'basic_auth') {
                html += '<div class="form-group">';
                html += '  <label class="control-label">Username / Key</label>';
                html += '  <input type="text" name="payload[username]" class="form-control" required data-field="username">';
                html += '</div>';
                html += '<div class="form-group">';
                html += '  <label class="control-label">Password / Secret</label>';
                html += '  <input type="password" name="payload[password]" class="form-control" required autocomplete="new-password" data-field="password">';
                html += '</div>';
            } else if (type === 'oauth') {
                html += '<div class="form-group">';
                html += '  <label class="control-label">Access Token</label>';
                html += '  <input type="password" name="payload[access_token]" class="form-control" required autocomplete="new-password" data-field="access_token">';
                html += '</div>';
                html += '<div class="form-group">';
                html += '  <label class="control-label">Refresh Token</label>';
                html += '  <input type="text" name="payload[refresh_token]" class="form-control" data-field="refresh_token">';
                html += '</div>';
            }
        }

        container.innerHTML = html;
        syncRawPayload();
    }

    // Sync input fields to raw JSON config textarea
    function syncRawPayload() {
        var payloadObj = {};
        $(container).find('input, select').each(function() {
            var fieldName = $(this).data('field');
            if (fieldName) {
                payloadObj[fieldName] = $(this).val();
            }
        });
        rawTextarea.value = JSON.stringify(payloadObj, null, 4);
    }

    $(container).on('input change', 'input, select', function() {
        syncRawPayload();
    });

    ownerSelect.addEventListener('change', buildFields);
    typeSelect.addEventListener('change', buildFields);

    // Initial builder call
    buildFields();

    // Toggle advanced JSON payload editor
    $('#toggle-advanced-payload').on('click', function(e) {
        e.preventDefault();
        $('#raw-payload-group').toggle();
    });

    // Populate form on Edit Button Click
    $('.edit-credential-btn').on('click', function() {
        var btn = $(this);
        var id = btn.data('id');
        var owner = btn.data('owner');
        var label = btn.data('label');
        var type = btn.data('type');
        var active = btn.data('active');
        var payloadObj = btn.data('payload');

        $('#form-title').html('<i class="fa fa-pencil-square-o"></i> Edit Credential #' + id);
        $('#cred_id').val(id);
        $('#cred_owner_module').val(owner);
        $('#cred_label').val(label);
        $('#cred_cred_type').val(type);
        $('#cred_is_active').prop('checked', active == 1);
        
        buildFields();

        // Populate fields with values
        for (var key in payloadObj) {
            if (payloadObj.hasOwnProperty(key)) {
                var fieldInput = $(container).find('[data-field="' + key + '"]');
                if (fieldInput.length > 0) {
                    fieldInput.val(payloadObj[key]);
                }
            }
        }
        
        rawTextarea.value = JSON.stringify(payloadObj, null, 4);
        $('#btn-cancel').show();
        $('html, body').animate({ scrollTop: $('#credential-form').offset().top - 100 }, 300);
    });

    // Cancel edit
    $('#btn-cancel').on('click', function() {
        $('#form-title').html('<i class="fa fa-key"></i> Add New Credential');
        $('#cred_id').val('');
        $('#credential-form')[0].reset();
        buildFields();
        $(this).hide();
    });

    // On switch state toggles
    $('.toggle-credential-status').on('change', function() {
        var id = $(this).data('id');
        $.get(admin_url + 'salesos/toggle_credential_status/' + id, function(res) {
            alert_float('success', 'Status updated successfully.');
        });
    });
});
</script>
