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
                        <li role="presentation" class="<?= $active_tab === 'general' ? 'active' : '' ?>">
                            <a href="#general" aria-controls="general" role="tab" data-toggle="tab">
                                <i class="fa fa-power-off"></i> General
                            </a>
                        </li>
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
                        <li role="presentation" class="<?= $active_tab === 'setup' ? 'active' : '' ?>">
                            <a href="#setup" aria-controls="setup" role="tab" data-toggle="tab">
                                <i class="fa fa-sliders"></i> Catalogue &amp; Setup
                            </a>
                        </li>
                        <li role="presentation" class="<?= $active_tab === 'license' ? 'active' : '' ?>">
                            <a href="#license" aria-controls="license" role="tab" data-toggle="tab">
                                <i class="fa fa-key"></i> License
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Tab Panes Content -->
                <div class="tab-content">

                    <!-- TAB 0: GENERAL -->
                    <div role="tabpanel" class="tab-pane <?= $active_tab === 'general' ? 'active' : '' ?>" id="general">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="panel_s">
                                    <div class="panel-body">
                                        <h4 class="no-margin font-medium">
                                            <i class="fa fa-power-off"></i> E-commerce Features
                                        </h4>
                                        <hr class="hr-panel-heading" />

                                        <?= form_open(admin_url('salesos/settings')) ?>
                                        <input type="hidden" name="general_settings" value="1">

                                        <div class="checkbox checkbox-primary">
                                            <input type="checkbox"
                                                   name="salesos_ecommerce_enabled"
                                                   id="salesos_ecommerce_enabled"
                                                   value="1"
                                                   <?= salesos_ecommerce_enabled() ? 'checked' : '' ?>>
                                            <label for="salesos_ecommerce_enabled">
                                                Enable orders, POS, inventory, purchases, returns, courier and WooCommerce
                                            </label>
                                        </div>

                                        <p class="text-muted">
                                            Turn this off on an install that only uses the phone system. The
                                            e-commerce screens disappear from the menu and stop opening, while
                                            call handling, call logs and agents carry on untouched.
                                        </p>
                                        <p class="text-muted">
                                            Nothing is deleted — orders, stock and settings all stay where they
                                            are, and turning this back on restores everything exactly as it was.
                                            This page stays reachable either way.
                                        </p>

                                        <hr class="hr-panel-heading" />

                                        <div class="checkbox checkbox-primary">
                                            <input type="checkbox"
                                                   name="salesos_require_order_confirmation"
                                                   id="salesos_require_order_confirmation"
                                                   value="1"
                                                   <?= get_option('salesos_require_order_confirmation') !== '0' ? 'checked' : '' ?>>
                                            <label for="salesos_require_order_confirmation">
                                                Hold channel orders for a confirmation call
                                            </label>
                                        </div>

                                        <p class="text-muted">
                                            On (recommended for cash on delivery): an order from a connected
                                            store waits in <strong>Confirmations</strong> until someone phones
                                            the customer. Only then does it reach stock, courier booking and
                                            notifications.
                                        </p>
                                        <p class="text-muted">
                                            Off: orders go straight to whatever status the store reports.
                                            Suitable when payment is taken online before shipping.
                                        </p>

                                        <div class="alert alert-info" style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:12px 16px;margin-top:15px;">
                                            <div class="tw-flex tw-items-center tw-justify-between">
                                                <div>
                                                    <strong class="text-primary"><i class="fa fa-phone-volume"></i> Automated IVR Order Confirmation</strong>
                                                    <p class="text-muted font-12" style="margin:2px 0 0 0;">অর্ডার আসার সাথে সাথে স্বয়ংক্রিয় রোবটিক কল ও অডিও প্রম্পট পরিচালনা করুন।</p>
                                                </div>
                                                <a href="<?= admin_url('pbxpilot/settings#ivr_order') ?>" class="btn btn-default btn-sm">
                                                    <i class="fa fa-sliders"></i> IVR ও অডিও কনফিগারেশন
                                                </a>
                                            </div>
                                        </div>

                                        <hr class="hr-panel-heading" />
                                        <h4 class="font-medium text-primary mbot15">
                                            <i class="fa fa-cubes"></i> Inventory &amp; Stock Rules (ইনভেন্টরি ও স্টক রুলস)
                                        </h4>

                                        <div class="form-group">
                                            <label for="inventory_industry_mode" class="control-label bold">
                                                <i class="fa fa-briefcase"></i> ব্যবসার ধরণ / Business Industry Profile
                                            </label>
                                            <?php 
                                            $curr_industry = get_option('inventory_industry_mode');
                                            if (empty($curr_industry)) { $curr_industry = 'gadgets'; }
                                            ?>
                                            <select name="inventory_industry_mode" id="inventory_industry_mode" class="form-control">
                                                <option value="gadgets" <?= ($curr_industry === 'gadgets') ? 'selected' : '' ?>>
                                                    📱 গ্যাজেট ও ইলেকট্রনিক্স (Gadgets &amp; Electronics) — ওয়ারেন্টি, মডেল ও IMEI/সিরিয়াল ট্র্যাকিং
                                                </option>
                                                <option value="fashion" <?= ($curr_industry === 'fashion') ? 'selected' : '' ?>>
                                                    👕 ফ্যাশন ও পোশাক (Fashion &amp; Apparel) — সাইজ, কালার ও ভ্যারিয়েশন ম্যাট্রিক্স
                                                </option>
                                                <option value="grocery" <?= ($curr_industry === 'grocery') ? 'selected' : '' ?>>
                                                    🍎 মুদি ও খাদ্যপণ্য (Grocery &amp; FMCG) — ইউনিট অব মেজার (কেজি/গ্রাম/লিটার) ও মেয়াদোত্তীর্ণ তারিখ
                                                </option>
                                                <option value="general" <?= ($curr_industry === 'general') ? 'selected' : '' ?>>
                                                    🏬 সাধারণ রিটেল (General Retail) — স্ট্যান্ডার্ড প্রোডাক্ট ও কাস্টম স্পেসিফিকেশন
                                                </option>
                                            </select>
                                            <p class="text-muted mtop5">
                                                নির্বাচিত ব্যবসার ধরণের উপর ভিত্তি করে প্রোডাক্ট এন্ট্রি ফর্মে কেবল প্রাসঙ্গিক ফিল্ডগুলো (যেমন: পোশাকের জন্য সাইজ/কালার, গ্যাজেটের জন্য ওয়ারেন্টি/IMEI) ডায়নামিক্যালি প্রদর্শিত হবে।
                                            </p>
                                        </div>

                                        <div class="form-group mtop15">
                                            <div class="checkbox checkbox-primary">
                                                <input type="checkbox"
                                                       name="inventory_allow_oversell"
                                                       id="inventory_allow_oversell"
                                                       value="1"
                                                       <?= get_option('inventory_allow_oversell') === '1' ? 'checked' : '' ?>>
                                                <label for="inventory_allow_oversell" class="bold">
                                                    Allow Overselling / Backorders (ওভারসেলিং অনুমোদন করুন)
                                                </label>
                                            </div>
                                            <div class="alert alert-warning mtop10" style="font-size: 13px; line-height: 1.5; margin-bottom: 5px;">
                                                <strong><i class="fa fa-shield"></i> CRM-First কঠোর ইনভেন্টরি নিরাপত্তা:</strong><br>
                                                <span class="text-danger bold">• আনচেকড / Off (সুপারিশকৃত):</span> স্টকে পর্যাপ্ত কোয়ান্টিটি না থাকলে POS বা অনলাইন সেল সঙ্গে সঙ্গে আটকে যাবে এবং স্টক কখনই শূন্যের নিচে নামবে না। গুদামে পারচেজ বা স্টক-ইন ছাড়া পণ্য সেল করা সম্পূর্ণ নিষিদ্ধ থাকবে।<br>
                                                <span class="text-warning bold">• চেকড / On:</span> স্টক শূন্য বা নেগেটিভ হলেও অর্ডার নেয়া ও POS সেল সম্পন্ন হবে (ব্যাকঅর্ডার সাপোর্ট)।
                                            </div>
                                        </div>

                                        <div class="form-group mtop15">
                                            <label class="control-label bold">
                                                <i class="fa fa-money"></i> কারেন্সি ও ডেসিমাল ফরম্যাটিং (Currency &amp; Decimal Formatting)
                                            </label>
                                            <div class="checkbox checkbox-primary">
                                                <input type="checkbox"
                                                       name="remove_decimals_on_zero"
                                                       id="remove_decimals_on_zero"
                                                       value="1"
                                                       <?= get_option('remove_decimals_on_zero') == '1' ? 'checked' : '' ?>>
                                                <label for="remove_decimals_on_zero" class="bold">
                                                    Remove decimals on zero-decimal amounts (পূর্ণসংখ্যায় দশমিকের পর .০০ লুকান)
                                                </label>
                                            </div>
                                            <p class="text-muted mtop5">
                                                বাংলাদেশে খুচরা মূল্যে বা দৈনন্দিন কেনাবেচায় পয়সার হিসাব হয় না। এটি সক্রিয় থাকলে <strong>990.00</strong> হয়ে যাবে <strong>990 BDT</strong> এবং স্টক <strong>20.00</strong> হবে <strong>20</strong>। কিন্তু সূক্ষ্ম অ্যাকাউন্টিং বা ভগ্নাংশ ইনভেন্টরিতে প্রকৃত পয়সা/ভগ্নাংশ (যেমন: <strong>12.50 BDT</strong> বা <strong>2.75 কেজি</strong>) অক্ষুণ্ণ থাকবে।
                                            </p>
                                        </div>

                                        <hr class="hr-panel-heading" />
                                        <button type="submit" class="btn btn-info">Save</button>
                                        <?= form_close() ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    
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
                                                                if ($acc['provider'] === 'steadfast' && isset($CI->courier_model)) {
                                                                    $full_acc = $CI->courier_model->get_account($acc['id']);
                                                                    if ($full_acc && !empty($full_acc['api_key'])) {
                                                                        $cache_key = 'steadfast_balance_' . (int)$acc['id'];
                                                                        $cache_time_key = 'steadfast_balance_time_' . (int)$acc['id'];
                                                                        $cached_val = get_option($cache_key);
                                                                        $cached_time = (int) get_option($cache_time_key);
                                                                        if ($cached_val !== '' && $cached_val !== false && (time() - $cached_time < 600)) {
                                                                            $balance = (float)$cached_val;
                                                                        } else {
                                                                            $balance = $CI->courier_model->check_steadfast_balance($full_acc['api_key'], $full_acc['secret_key']);
                                                                            if ($balance !== false) {
                                                                                update_option($cache_key, (string)$balance);
                                                                                update_option($cache_time_key, (string)time());
                                                                            } elseif ($cached_val !== '' && $cached_val !== false) {
                                                                                $balance = (float)$cached_val;
                                                                            }
                                                                        }
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

                                        <!-- Section A: Delivery Strategy Mode -->
                                        <div class="alert alert-info tw-mb-4" style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:16px;">
                                            <h4 class="bold text-primary no-mtop mbot10">
                                                <i class="fa fa-paper-plane-o"></i> মেসেজ ডেলিভারি স্ট্র্যাটেজি (স্মার্ট ফলব্যাক কন্ট্রোল)
                                            </h4>
                                            <p class="text-muted mbot15">কাস্টমারের কাছে মেসেজ কিভাবে পৌঁছাবে তা নির্বাচন করুন।</p>
                                            
                                            <?php $cur_mode = get_option('ordernotifier_channel_mode') ?: 'smart_failover'; ?>
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <div class="radio radio-primary">
                                                        <input type="radio" name="ordernotifier_channel_mode" id="mode_smart_failover" value="smart_failover" <?= $cur_mode === 'smart_failover' ? 'checked' : '' ?>>
                                                        <label for="mode_smart_failover" class="bold font-13 text-success">
                                                            <i class="fa fa-bolt"></i> Smart Failover (সেরা)
                                                        </label>
                                                    </div>
                                                    <p class="text-muted font-12" style="padding-left:20px;">আগে WhatsApp-এ পাঠাবে। নম্বর না পেলে সাথে সাথে EcareSMS-এ যাবে। (SMS খরচ বাঁচে)</p>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="radio radio-success">
                                                        <input type="radio" name="ordernotifier_channel_mode" id="mode_whatsapp_only" value="whatsapp_only" <?= $cur_mode === 'whatsapp_only' ? 'checked' : '' ?>>
                                                        <label for="mode_whatsapp_only" class="bold font-13 text-success">
                                                            <i class="fa fa-whatsapp"></i> শুধু WhatsApp
                                                        </label>
                                                    </div>
                                                    <p class="text-muted font-12" style="padding-left:20px;">শুধুমাত্র হোয়াটসঅ্যাপেই যাবে; কোনো সাধারণ SMS পাঠানো হবে না।</p>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="radio radio-info">
                                                        <input type="radio" name="ordernotifier_channel_mode" id="mode_sms_only" value="sms_only" <?= $cur_mode === 'sms_only' ? 'checked' : '' ?>>
                                                        <label for="mode_sms_only" class="bold font-13 text-info">
                                                            <i class="fa fa-commenting-o"></i> শুধু Bulk SMS
                                                        </label>
                                                    </div>
                                                    <p class="text-muted font-12" style="padding-left:20px;">সরাসরি মোবাইলে EcareSMS দিয়ে SMS পাঠাবে।</p>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="radio radio-warning">
                                                        <input type="radio" name="ordernotifier_channel_mode" id="mode_both" value="both" <?= $cur_mode === 'both' ? 'checked' : '' ?>>
                                                        <label for="mode_both" class="bold font-13 text-warning">
                                                            <i class="fa fa-send"></i> দুটোতেই (Both)
                                                        </label>
                                                    </div>
                                                    <p class="text-muted font-12" style="padding-left:20px;">প্রত্যেক কাস্টমারকে একই সাথে WhatsApp ও SMS দুটোই পাঠানো হবে।</p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Section B: Order Event Notification Toggles (Single Message Rule) -->
                                        <div class="panel_s" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-bottom:20px;">
                                            <h4 class="bold text-neutral-800 no-mtop mbot10">
                                                <i class="fa fa-toggle-on"></i> কোন কোন ইভেন্টে মেসেজ যাবে (Event Notification Triggers)
                                            </h4>
                                            <p class="text-muted mbot15">যেসব ঘটনায় গ্রাহকের ফোনে মেসেজ পাঠাতে চান সেগুলো টিক দিন।</p>

                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="checkbox checkbox-primary no-mtop">
                                                        <input type="checkbox" name="ordernotifier_notify_on_created" id="ordernotifier_notify_on_created" value="1" <?= get_option('ordernotifier_notify_on_created') === '1' ? 'checked' : '' ?>>
                                                        <label for="ordernotifier_notify_on_created" class="bold">
                                                            অর্ডার প্লেস হলে মেসেজ (Order Created)
                                                        </label>
                                                    </div>
                                                    <p class="text-muted font-12" style="padding-left:20px;">
                                                        <span class="text-danger bold"><i class="fa fa-info-circle"></i> টিপস:</span> IVR অটো কল চালু থাকলে এটি বন্ধ (Unchecked) রাখুন। এর ফলে গ্রাহকের কাছে কল যাওয়ার আগে কোনো অপ্রয়োজনীয় মেসেজ যাবে না।
                                                    </p>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="checkbox checkbox-success no-mtop">
                                                        <input type="checkbox" name="ordernotifier_notify_on_confirmed" id="ordernotifier_notify_on_confirmed" value="1" <?= get_option('ordernotifier_notify_on_confirmed') !== '0' ? 'checked' : '' ?>>
                                                        <label for="ordernotifier_notify_on_confirmed" class="bold text-success">
                                                            অর্ডার কনফার্ম হলে মেসেজ (Order Confirmed)
                                                        </label>
                                                    </div>
                                                    <p class="text-muted font-12" style="padding-left:20px;">IVR কলে ১ চাপলে বা ম্যানুয়ালি কনফার্ম করলে গ্রাহক চূড়ান্ত কনফার্মেশন মেসেজ পাবেন।</p>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="checkbox checkbox-danger no-mtop">
                                                        <input type="checkbox" name="ordernotifier_notify_on_cancelled" id="ordernotifier_notify_on_cancelled" value="1" <?= get_option('ordernotifier_notify_on_cancelled') !== '0' ? 'checked' : '' ?>>
                                                        <label for="ordernotifier_notify_on_cancelled" class="bold text-danger">
                                                            অর্ডার বাতিল হলে মেসেজ (Order Cancelled)
                                                        </label>
                                                    </div>
                                                    <p class="text-muted font-12" style="padding-left:20px;">IVR কলে ২ চাপলে বা ম্যানুয়ালি ক্যানসেল করলে বাতিলকরণের মেসেজ যাবে।</p>
                                                </div>
                                            </div>

                                            <div class="row mtop10">
                                                <div class="col-md-4">
                                                    <div class="checkbox checkbox-info no-mtop">
                                                        <input type="checkbox" name="ordernotifier_notify_on_shipped" id="ordernotifier_notify_on_shipped" value="1" <?= get_option('ordernotifier_notify_on_shipped') !== '0' ? 'checked' : '' ?>>
                                                        <label for="ordernotifier_notify_on_shipped" class="bold text-info">
                                                            কুরিয়ারে শিফট হলে মেসেজ (Order Shipped)
                                                        </label>
                                                    </div>
                                                    <p class="text-muted font-12" style="padding-left:20px;">পার্সেল কুরিয়ারে হ্যান্ডওভার ও ট্র্যাকিং নম্বর জেনারেট হলে মেসেজ যাবে।</p>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="checkbox checkbox-success no-mtop">
                                                        <input type="checkbox" name="ordernotifier_notify_on_delivered" id="ordernotifier_notify_on_delivered" value="1" <?= get_option('ordernotifier_notify_on_delivered') !== '0' ? 'checked' : '' ?>>
                                                        <label for="ordernotifier_notify_on_delivered" class="bold text-success">
                                                            ডেলিভারি সম্পন্ন হলে মেসেজ (Order Delivered)
                                                        </label>
                                                    </div>
                                                    <p class="text-muted font-12" style="padding-left:20px;">কাস্টমার পার্সেল রিসিভ করলে ধন্যবাদ মেসেজ যাবে।</p>
                                                </div>
                                            </div>
                                        </div>

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
                                                    <label for="ordernotifier_whatsapp_token" class="control-label">API Key / Access Token</label>
                                                    <input type="password" name="ordernotifier_whatsapp_token" id="ordernotifier_whatsapp_token" class="form-control" value="<?= e(get_option('ordernotifier_whatsapp_token')) ?>" autocomplete="new-password">
                                                </div>

                                                <div class="form-group">
                                                    <label for="ordernotifier_whatsapp_channel_guid" class="control-label">Channel GUID</label>
                                                    <input type="text" name="ordernotifier_whatsapp_channel_guid" id="ordernotifier_whatsapp_channel_guid" class="form-control" value="<?= e(get_option('ordernotifier_whatsapp_channel_guid') ?: get_option('bizbot_channel_guid')) ?>" placeholder="e.g. 2230cdf9-457d-3e96-beb4-eb554a4a4c56">
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

                                        <hr class="mtop25 mbot25" />
                                        
                                        <div class="row">
                                            <div class="col-md-12">
                                                <h4 class="bold text-success mbot15">
                                                    <i class="fa fa-whatsapp"></i> Bizbot WhatsApp Inbound Webhook & Lead Automation
                                                </h4>
                                                <p class="text-muted mbot20">
                                                    Automatically capture all incoming WhatsApp messages as Leads with name and phone deduplication, assign them to staff by Agent ID, and enable WhatsApp moderator order placement (<span class="text-primary font-medium">#order</span>).
                                                </p>

                                                <div class="row mbot20">
                                                    <div class="col-md-3">
                                                        <div class="checkbox checkbox-success no-mtop">
                                                            <input type="checkbox" name="salesos_bizbot_inbound_enabled" id="salesos_bizbot_inbound_enabled" value="1" <?= get_option('salesos_bizbot_inbound_enabled') === '0' ? '' : 'checked' ?>>
                                                            <label for="salesos_bizbot_inbound_enabled" class="bold font-13">
                                                                <i class="fa fa-user-plus text-success"></i> অটো লিড এন্ট্রি
                                                            </label>
                                                        </div>
                                                        <p class="text-muted font-12" style="padding-left: 20px;">নতুন কাস্টমার মেসেজ পাঠালে SalesOS-এ অটো লিড তৈরি হবে।</p>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <div class="checkbox checkbox-info no-mtop">
                                                            <input type="checkbox" name="salesos_bizbot_auto_assign_reply" id="salesos_bizbot_auto_assign_reply" value="1" <?= get_option('salesos_bizbot_auto_assign_reply') === '0' ? '' : 'checked' ?>>
                                                            <label for="salesos_bizbot_auto_assign_reply" class="bold font-13">
                                                                <i class="fa fa-check-circle text-info"></i> প্রথম রিপ্লাইয়ে ক্লেইম
                                                            </label>
                                                        </div>
                                                        <p class="text-muted font-12" style="padding-left: 20px;">প্রথম যে এজেন্ট Bizbot বা WhatsApp-এ রিপ্লাই দেবে লিড তার নামে অ্যাসাইন হবে।</p>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <div class="checkbox checkbox-warning no-mtop">
                                                            <input type="checkbox" name="salesos_bizbot_lock_assigned_leads" id="salesos_bizbot_lock_assigned_leads" value="1" <?= get_option('salesos_bizbot_lock_assigned_leads') === '0' ? '' : 'checked' ?>>
                                                            <label for="salesos_bizbot_lock_assigned_leads" class="bold font-13">
                                                                <i class="fa fa-lock text-warning"></i> ক্লেইমের পর লক রাখুন
                                                            </label>
                                                        </div>
                                                        <p class="text-muted font-12" style="padding-left: 20px;">একবার অ্যাসাইন হলে অকারণে আর পরিবর্তন হবে না; শুধুমাত্র #assign কমান্ড দিয়ে বদলানো যাবে।</p>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <div class="checkbox checkbox-primary no-mtop">
                                                            <input type="checkbox" name="salesos_bizbot_moderator_order_enabled" id="salesos_bizbot_moderator_order_enabled" value="1" <?= get_option('salesos_bizbot_moderator_order_enabled') === '0' ? '' : 'checked' ?>>
                                                            <label for="salesos_bizbot_moderator_order_enabled" class="bold font-13">
                                                                <i class="fa fa-shopping-cart text-primary"></i> মডারেটর অর্ডার (#order)
                                                            </label>
                                                        </div>
                                                        <p class="text-muted font-12" style="padding-left: 20px;">হোয়াটসঅ্যাপে #order লিখে রিপ্লাই দিলে সেলস অর্ডার তৈরি ও কাস্টমারে কনভার্ট হবে।</p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-12 mbot20">
                                                <div class="panel panel-default" style="border-left: 4px solid #10b981; margin-bottom: 20px;">
                                                    <div class="panel-body" style="background: #f0fdf4;">
                                                        <div class="row">
                                                            <div class="col-md-12">
                                                                <div class="checkbox checkbox-success no-mtop">
                                                                    <input type="checkbox" name="salesos_bizbot_twoway_confirm_enabled" id="salesos_bizbot_twoway_confirm_enabled" value="1" <?= get_option('salesos_bizbot_twoway_confirm_enabled') === '0' ? '' : 'checked' ?>>
                                                                    <label for="salesos_bizbot_twoway_confirm_enabled" class="bold font-14 text-success">
                                                                        <i class="fa fa-refresh"></i> টু-ওয়ে অর্ডার কনফার্মেশন লুপ (WhatsApp Two-Way Order Confirmation Loop)
                                                                    </label>
                                                                </div>
                                                                <p class="text-muted font-12" style="padding-left: 20px; margin-bottom: 10px;">
                                                                    গ্রাহক হোয়াটসঅ্যাপ নোটিফিকেশনের উত্তরে <strong>"1" বা "হ্যাঁ"</strong> লিখলে স্বয়ংক্রিয়ভাবে অর্ডার <strong>Confirmed</strong> হবে এবং <strong>"2" বা "না"</strong> লিখলে অর্ডার <strong>Cancelled</strong> হবে। অর্ডার স্ট্যাটাস বদলানোর সাথে সাথে ইনভেন্টরি রিজার্ভ স্টক সমন্বয় হবে ও গ্রাহকের কাছে স্বয়ংক্রিয় রিপ্লাই যাবে।
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <div class="row mtop10">
                                                            <div class="col-md-6">
                                                                <label class="control-label bold font-12"><i class="fa fa-check-circle text-success"></i> কনফার্মেশন অটো-রিপ্লাই (Confirm Reply Template):</label>
                                                                <textarea name="salesos_bizbot_twoway_reply_confirm" class="form-control font-12" rows="2" placeholder="ডিফল্ট: প্রিয় কাস্টমার, আপনার অর্ডার #{order_id} সফলভাবে কনফার্ম করা হয়েছে! মোট বিল: {total} টাকা। দ্রুততম সময়ে আপনার পণ্যটি কুরিয়ারে পাঠানো হবে।"><?= e(get_option('salesos_bizbot_twoway_reply_confirm')) ?></textarea>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="control-label bold font-12"><i class="fa fa-times-circle text-danger"></i> ক্যানসেলেশন অটো-রিপ্লাই (Cancel Reply Template):</label>
                                                                <textarea name="salesos_bizbot_twoway_reply_cancel" class="form-control font-12" rows="2" placeholder="ডিফল্ট: প্রিয় কাস্টমার, আপনার অনুরোধে অর্ডার #{order_id} বাতিল করা হয়েছে। যেকোনো প্রয়োজনে আমাদের সাথে যোগাযোগ করুন। ধন্যবাদ!"><?= e(get_option('salesos_bizbot_twoway_reply_cancel')) ?></textarea>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-12">
                                                <div class="panel panel-default" style="border-left: 4px solid #0284c7; margin-bottom: 25px;">
                                                    <div class="panel-body" style="background: #f8fafc;">
                                                        <h4 class="bold text-primary font-14 mbot10">
                                                            <i class="fa fa-filter"></i> লিড ক্যাপচার মোড ও স্মার্ট ফিল্টার (Lead Capture Mode &amp; Smart Safeguards)
                                                        </h4>
                                                        <p class="text-muted font-12 mbot15">
                                                            হোয়াটসঅ্যাপে ব্যক্তিগত মেসেজ, গ্রুপ ও সাপ্লায়ারদের অপ্রয়োজনীয় চ্যাট দিয়ে সিআরএম ডাটাবেজ যাতে স্প্যাম না হয়, সেজন্য আপনার পছন্দের মোড বেছে নিন:
                                                        </p>

                                                        <?php $capture_mode = get_option('salesos_bizbot_capture_mode') ?: 'smart'; ?>
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="radio radio-primary" style="background: #fff; padding: 12px 15px; border-radius: 6px; border: 1px solid #e2e8f0;">
                                                                    <input type="radio" name="salesos_bizbot_capture_mode" id="capture_mode_smart" value="smart" <?= $capture_mode === 'smart' ? 'checked' : '' ?>>
                                                                    <label for="capture_mode_smart" class="bold font-13 text-primary">
                                                                        <i class="fa fa-bullseye"></i> স্মার্ট ক্যাপচার (ফেসবুক এড ও ইনটেন্ট ভিত্তিক — রিকমেন্ডেড)
                                                                    </label>
                                                                    <p class="text-muted font-12" style="margin-left: 20px; margin-top: 4px; margin-bottom: 0;">
                                                                        ফেসবুক এড (CTWA) থেকে মেসেজ আসলে, পণ্যের দাম/সাইজ/অর্ডার ইনটেন্ট থাকলে, অথবা চ্যাটে <code>#lead</code> লিখলে তবেই সিআরএম-এ লিড হবে। সাধারণ আলাপ সিআরএম-এ আসবে না।
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="radio radio-primary" style="background: #fff; padding: 12px 15px; border-radius: 6px; border: 1px solid #e2e8f0;">
                                                                    <input type="radio" name="salesos_bizbot_capture_mode" id="capture_mode_all" value="all" <?= $capture_mode === 'all' ? 'checked' : '' ?>>
                                                                    <label for="capture_mode_all" class="bold font-13">
                                                                        <i class="fa fa-inbox"></i> সব মেসেজে লিড (বেসিক ফিল্টার সহ)
                                                                    </label>
                                                                    <p class="text-muted font-12" style="margin-left: 20px; margin-top: 4px; margin-bottom: 0;">
                                                                        যেকোনো নতুন নম্বর থেকে মেসেজ আসলেই সরাসরি লিড এন্ট্রি হবে (গ্রুপ চ্যাট, স্টাফ ও সাপ্লায়ার নম্বর ফিল্টার থাকবে)।
                                                                    </p>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="row mtop15">
                                                            <div class="col-md-6">
                                                                <div class="checkbox checkbox-primary no-mtop">
                                                                    <input type="checkbox" name="salesos_bizbot_exclude_staff" id="salesos_bizbot_exclude_staff" value="1" <?= get_option('salesos_bizbot_exclude_staff') === '0' ? '' : 'checked' ?>>
                                                                    <label for="salesos_bizbot_exclude_staff" class="font-12 bold">
                                                                        <i class="fa fa-user-secret"></i> সিআরএম স্টাফদের ফোন নম্বর বাদ দিন (Ignore staff phones)
                                                                    </label>
                                                                </div>
                                                                <div class="checkbox checkbox-primary no-mtop mtop5">
                                                                    <input type="checkbox" name="salesos_bizbot_exclude_suppliers" id="salesos_bizbot_exclude_suppliers" value="1" <?= get_option('salesos_bizbot_exclude_suppliers') === '0' ? '' : 'checked' ?>>
                                                                    <label for="salesos_bizbot_exclude_suppliers" class="font-12 bold">
                                                                        <i class="fa fa-truck"></i> সাপ্লায়ার ও ভেন্ডরদের ফোন নম্বর বাদ দিন (Ignore supplier phones)
                                                                    </label>
                                                                </div>
                                                                <div class="form-group mtop10">
                                                                    <label for="salesos_bizbot_blacklist_phones" class="control-label font-12 bold">কাস্টম ব্ল্যাকলিস্ট নম্বর (প্রতি লাইনে একটি নম্বর):</label>
                                                                    <textarea name="salesos_bizbot_blacklist_phones" id="salesos_bizbot_blacklist_phones" class="form-control" rows="2" placeholder="01XXXXXXXXX&#10;01YYYYYYYYY"><?= e(get_option('salesos_bizbot_blacklist_phones')) ?></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <label for="salesos_bizbot_intent_keywords" class="control-label font-12 bold">
                                                                        <i class="fa fa-tags"></i> কেনাকাটার ইনটেন্ট কী-ওয়ার্ড তালিকা (কমা দিয়ে আলাদা করুন):
                                                                    </label>
                                                                    <textarea name="salesos_bizbot_intent_keywords" id="salesos_bizbot_intent_keywords" class="form-control" rows="2"><?= e(get_option('salesos_bizbot_intent_keywords') ?: 'দাম, কত, প্রাইস, সাইজ, কালার, স্টক, ডেলিভারি, অর্ডার, নিতে চাই, কিনব, price, size, order, dress, buy, stock, cost, rate, bdt, টাকা, কোড, code, ক্যাশ') ?></textarea>
                                                                    <p class="help-block font-11 text-muted" style="margin-bottom: 5px;">স্মার্ট মোডে গ্রাহকের মেসেজে এসব শব্দের যেকোনো একটি থাকলে বা ফেসবুক এড থেকে আসলে লিড হবে।</p>
                                                                </div>
                                                                <div class="form-group mtop5">
                                                                    <label for="salesos_bizbot_manual_lead_keyword" class="control-label font-12 bold">ম্যানুয়াল লিড তৈরির কী-ওয়ার্ড:</label>
                                                                    <input type="text" name="salesos_bizbot_manual_lead_keyword" id="salesos_bizbot_manual_lead_keyword" class="form-control input-sm" value="<?= e(get_option('salesos_bizbot_manual_lead_keyword') ?: '#lead') ?>" placeholder="#lead">
                                                                    <p class="help-block font-11 text-muted">সাধারণ চ্যাটের ক্ষেত্রে এজেন্ট বা কাস্টমার এই কোডটি লিখলে সাথে সাথে লিড হয়ে যাবে।</p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-6" style="border-right: 1px solid #f1f5f9;">
                                                <div class="form-group">
                                                    <label class="control-label bold">Webhook URL (Set this in Bizbot Webhooks)</label>
                                                    <?php
                                                    $webhook_key = get_option('salesos_bizbot_webhook_key');
                                                    $webhook_url = site_url('salesos/bizbot_webhook' . ($webhook_key ? '/' . $webhook_key : ''));
                                                    ?>
                                                    <div class="input-group">
                                                        <input type="text" id="salesos_bizbot_webhook_url" class="form-control" value="<?= e($webhook_url) ?>" readonly>
                                                        <span class="input-group-btn">
                                                            <button class="btn btn-default" type="button" onclick="navigator.clipboard.writeText(document.getElementById('salesos_bizbot_webhook_url').value); alert_float('success', 'Webhook URL copied to clipboard');">
                                                                <i class="fa fa-copy"></i> Copy
                                                            </button>
                                                        </span>
                                                    </div>
                                                    <p class="help-block text-muted">Paste this URL into Bizbot &gt; Settings &gt; Integrations/Webhooks.</p>
                                                </div>

                                                <div class="form-group">
                                                    <label for="salesos_bizbot_webhook_key" class="control-label">Webhook Secret Key (Optional Security Token)</label>
                                                    <input type="text" name="salesos_bizbot_webhook_key" id="salesos_bizbot_webhook_key" class="form-control" value="<?= e(get_option('salesos_bizbot_webhook_key')) ?>" placeholder="e.g. secret_token_xyz">
                                                    <p class="help-block text-muted">If specified, Bizbot webhook requests must include this token in the URL or header.</p>
                                                </div>

                                                <div class="form-group">
                                                    <label for="salesos_bizbot_default_lead_status" class="control-label">Default Inbound Lead Status</label>
                                                    <select name="salesos_bizbot_default_lead_status" id="salesos_bizbot_default_lead_status" class="form-control selectpicker" data-live-search="true">
                                                        <?php
                                                        $selected_status = (int) (get_option('salesos_bizbot_default_lead_status') ?: 2);
                                                        if (isset($lead_statuses) && is_array($lead_statuses)):
                                                            foreach ($lead_statuses as $st):
                                                        ?>
                                                                <option value="<?= $st['id'] ?>" <?= $selected_status == (int)$st['id'] ? 'selected' : '' ?>>
                                                                    <?= e($st['name']) ?>
                                                                </option>
                                                        <?php 
                                                            endforeach;
                                                        endif;
                                                        ?>
                                                    </select>
                                                    <p class="help-block text-muted">New incoming contacts without purchase will be placed into this Lead status.</p>
                                                </div>

                                                <div class="form-group">
                                                    <label for="salesos_bizbot_order_keyword" class="control-label">Moderator Order Trigger Keyword</label>
                                                    <input type="text" name="salesos_bizbot_order_keyword" id="salesos_bizbot_order_keyword" class="form-control" value="<?= e(get_option('salesos_bizbot_order_keyword') ?: '#order') ?>" placeholder="#order">
                                                    <p class="help-block text-muted">When a moderator replies to a customer on WhatsApp starting with this keyword (e.g. <code>#order POLO-M 1 Mirpur 10</code>), SalesOS automatically creates an order and converts the lead to a customer.</p>
                                                </div>
                                            </div>

                                            <div class="col-md-6" style="padding-left: 25px;">
                                                <!-- Unified Team & Channel Hub Card -->
                                                <div style="border: 1px solid #bae6fd; background: #f0f9ff; border-radius: 6px; padding: 14px; margin-bottom: 20px;">
                                                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px;">
                                                        <div>
                                                            <h5 style="margin: 0 0 4px 0; font-weight: 700; color: #0369a1;">
                                                                <i class="fa-solid fa-users-gear"></i> ইউনিফাইড টিম ও চ্যানেল ম্যাপিং হাব
                                                            </h5>
                                                            <p style="margin: 0; font-size: 12px; color: #0284c7;">
                                                                PBX এক্সটেনশন ও Bizbot WhatsApp আইডি এক স্ক্রিন থেকে সহজে ও স্মার্টভাবে সেট করুন।
                                                            </p>
                                                        </div>
                                                        <a href="<?= admin_url('pbxpilot/agents') ?>" class="btn btn-primary btn-xs" style="white-space: nowrap;">
                                                            টিম হাব ওপেন করুন &rarr;
                                                        </a>
                                                    </div>
                                                </div>

                                                <div class="form-group">
                                                    <label class="control-label bold">Bizbot Agent ID &rarr; Staff Mapping</label>
                                                    <p class="text-muted font-12 mbot10">Map Bizbot Agent UUIDs to SalesOS Staff members. Incoming chats or assigned conversations will automatically be assigned to the matching staff.</p>

                                                    <?php
                                                    $agent_mapping = json_decode(get_option('salesos_bizbot_agent_mapping') ?: '[]', true) ?: [];
                                                    ?>
                                                    <table class="table table-bordered table-striped" id="table-bizbot-agent-mapping">
                                                        <thead>
                                                            <tr>
                                                                <th>Bizbot Agent ID (UUID)</th>
                                                                <th>Assigned Staff</th>
                                                                <th style="width: 45px;"></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php if (!empty($agent_mapping)): ?>
                                                                <?php foreach ($agent_mapping as $guid => $staff_id): ?>
                                                                    <tr>
                                                                        <td>
                                                                            <input type="text" name="bizbot_agent_guid[]" class="form-control input-sm font-monospace" value="<?= e($guid) ?>" placeholder="e.g. 203fb98c-c326-3626-a5ac-97827462a95b" required>
                                                                        </td>
                                                                        <td>
                                                                            <select name="bizbot_agent_staff[]" class="form-control input-sm" required>
                                                                                <option value="">-- Select Staff --</option>
                                                                                <?php if (isset($all_staff) && is_array($all_staff)): ?>
                                                                                    <?php foreach ($all_staff as $stf): ?>
                                                                                        <option value="<?= $stf['staffid'] ?>" <?= (int)$staff_id === (int)$stf['staffid'] ? 'selected' : '' ?>>
                                                                                            <?= e($stf['firstname'] . ' ' . $stf['lastname']) ?>
                                                                                        </option>
                                                                                    <?php endforeach; ?>
                                                                                <?php endif; ?>
                                                                            </select>
                                                                        </td>
                                                                        <td class="text-center">
                                                                            <button type="button" class="btn btn-danger btn-xs btn-remove-agent-row"><i class="fa fa-times"></i></button>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            <?php else: ?>
                                                                <tr>
                                                                    <td>
                                                                        <input type="text" name="bizbot_agent_guid[]" class="form-control input-sm font-monospace" placeholder="e.g. 203fb98c-c326-3626-a5ac-97827462a95b">
                                                                    </td>
                                                                    <td>
                                                                        <select name="bizbot_agent_staff[]" class="form-control input-sm">
                                                                            <option value="">-- Select Staff --</option>
                                                                            <?php if (isset($all_staff) && is_array($all_staff)): ?>
                                                                                <?php foreach ($all_staff as $stf): ?>
                                                                                    <option value="<?= $stf['staffid'] ?>">
                                                                                        <?= e($stf['firstname'] . ' ' . $stf['lastname']) ?>
                                                                                    </option>
                                                                                <?php endforeach; ?>
                                                                            <?php endif; ?>
                                                                        </select>
                                                                    </td>
                                                                    <td class="text-center">
                                                                        <button type="button" class="btn btn-danger btn-xs btn-remove-agent-row"><i class="fa fa-times"></i></button>
                                                                    </td>
                                                                </tr>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>

                                                    <button type="button" class="btn btn-default btn-sm" id="btn-add-agent-map">
                                                        <i class="fa fa-plus"></i> Add Agent Mapping
                                                    </button>
                                                </div>

                                                <div class="mtop25">
                                                    <h4 class="bold font-14 text-primary mbot10">
                                                        <i class="fa fa-users"></i> এজেন্ট পরিচিতি ডাকনাম ও কি-ওয়ার্ড (Staff WhatsApp Intro Aliases)
                                                    </h4>
                                                    <p class="text-muted font-12 mbot15">
                                                        এজেন্টরা হোয়াটসঅ্যাপে কাস্টমারকে রিপ্লাই দেওয়ার সময় ("আমি মোস্তাফিজ বলছি", "This is Al Amin") যেসব ডাকনাম বা বানানে নিজের পরিচয় দেয়, তা এখানে কমা দিয়ে লিখে রাখুন। সিস্টেম স্বয়ংক্রিয়ভাবে লিড তার নামে ক্লেইম করবে।
                                                    </p>

                                                    <?php
                                                    $staff_aliases_json = get_option('salesos_bizbot_staff_aliases');
                                                    $staff_aliases_saved = !empty($staff_aliases_json) ? json_decode($staff_aliases_json, true) : [];
                                                    ?>

                                                    <table class="table table-bordered table-striped" style="background: #fff;">
                                                        <thead>
                                                            <tr style="background: #f8fafc;">
                                                                <th style="width: 30%;">Staff Member</th>
                                                                <th style="width: 70%;">পরিচিতি ডাকনাম / বানানের তালিকা (Aliases - Comma Separated)</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php if (isset($all_staff) && is_array($all_staff)): ?>
                                                                <?php foreach ($all_staff as $stf): 
                                                                    $s_id = (int)$stf['staffid'];
                                                                    $existing_aliases = $staff_aliases_saved[$s_id] ?? [];
                                                                    if (empty($existing_aliases)) {
                                                                        if ($s_id === 1) $existing_aliases = ['mostafizur', 'mostafiz', 'মোস্তাফিজুর', 'মোস্তাফিজ', 'mostafizur rahman'];
                                                                        elseif ($s_id === 2) $existing_aliases = ['al amin', 'alamin', 'আল আমিন', 'আলামিন', 'আল-আমিন', 'amin'];
                                                                        elseif ($s_id === 3) $existing_aliases = ['asafunnahar', 'pakhi', 'আসাফুন্নাহার', 'পাখি'];
                                                                    }
                                                                    $val_str = is_array($existing_aliases) ? implode(', ', $existing_aliases) : (string)$existing_aliases;
                                                                ?>
                                                                    <tr>
                                                                        <td class="bold font-13">
                                                                            <i class="fa fa-user text-muted"></i> <?= e($stf['firstname'] . ' ' . $stf['lastname']) ?>
                                                                            <span class="text-muted font-11 font-normal">(#<?= $stf['staffid'] ?>)</span>
                                                                        </td>
                                                                        <td>
                                                                            <input type="text" name="bizbot_staff_aliases[<?= $stf['staffid'] ?>]" class="form-control input-sm" value="<?= e($val_str) ?>" placeholder="e.g. <?= e(mb_strtolower($stf['firstname'])) ?>, ডাকনাম">
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
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

                    <!-- TAB 4: CATALOGUE & SETUP -->
                    <div role="tabpanel" class="tab-pane <?= $active_tab === 'setup' ? 'active' : '' ?>" id="setup">
                        <div class="row">
                            <div class="col-md-12">
                                <p class="text-muted">
                                    Screens you set up once and then rarely revisit. They stay out of the
                                    main menu so the daily work is easier to reach, but nothing here has
                                    moved or changed — each page is exactly where it was.
                                </p>
                            </div>
                        </div>

                        <?php
                        $CI = &get_instance();
                        $setup_groups = [
                            [
                                'title'  => 'Catalogue',
                                'icon'   => 'fa fa-boxes',
                                'module' => 'inventory',
                                'links'  => [
                                    ['Product Categories', 'Group products for reporting and the POS grid.', admin_url('inventory/categories'), 'fa fa-tags'],
                                ],
                            ],
                            [
                                'title'  => 'Suppliers',
                                'icon'   => 'fa fa-industry',
                                'module' => 'purchases',
                                'links'  => [
                                    ['Suppliers', 'Who you buy stock from.', admin_url('purchases/suppliers'), 'fa fa-address-book'],
                                    ['Supplier Ledger', 'What you owe each supplier, and what you have paid.', admin_url('purchases/supplier_ledger'), 'fa fa-book'],
                                ],
                            ],
                            [
                                'title'  => 'WooCommerce',
                                'icon'   => 'fa fa-shopping-bag',
                                'module' => 'wcsync',
                                'links'  => [
                                    ['Connected Sites', 'Add a store and choose which lead status new orders land in.', admin_url('wcsync/settings'), 'fa fa-plug'],
                                    ['Sync Status', 'When each store last synced, and what came through.', admin_url('wcsync'), 'fa fa-refresh'],
                                ],
                            ],
                        ];
                        ?>

                        <div class="row">
                            <?php foreach ($setup_groups as $group): ?>
                                <?php if (!$CI->app_modules->is_active($group['module'])) { continue; } ?>
                                <div class="col-md-4">
                                    <div class="panel_s">
                                        <div class="panel-body">
                                            <h4 class="no-margin font-medium">
                                                <i class="<?= $group['icon'] ?>"></i> <?= $group['title'] ?>
                                            </h4>
                                            <hr class="hr-panel-heading" />
                                            <?php foreach ($group['links'] as [$label, $description, $href, $icon]): ?>
                                                <a href="<?= $href ?>" class="display-block mbot15">
                                                    <span class="bold"><i class="<?= $icon ?>"></i> <?= $label ?></span>
                                                    <br>
                                                    <small class="text-muted"><?= $description ?></small>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- TAB: LICENSE -->
                    <div role="tabpanel" class="tab-pane <?= $active_tab === 'license' ? 'active' : '' ?>" id="license">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="panel_s">
                                    <div class="panel-body">
                                        <h4 class="no-margin font-medium">
                                            <i class="fa fa-key"></i> SalesOS License Management
                                        </h4>
                                        <hr class="hr-panel-heading" />

                                        <?php 
                                        $info = $license_info ?? [];
                                        $isValid = !empty($is_licensed);
                                        $status = $info['status'] ?? 'unlicensed';
                                        $inGrace = !empty($info['in_grace']);
                                        $maskedKey = $info['license_key_masked'] ?? (get_option('salesos_license_key') ? substr(get_option('salesos_license_key'), 0, 4) . '-••••-••••-' . substr(get_option('salesos_license_key'), -4) : 'Not configured');
                                        $instId = $info['installation_id'] ?? 'Not resolved';
                                        $domain = $info['domain'] ?? 'crm.bizyto.com';
                                        $entitlements = $info['entitlements'] ?? [];
                                        ?>

                                        <?php if (!empty($is_standalone)): ?>
                                            <div class="alert alert-warning" style="border-left: 4px solid #f59e0b; background: #fffbeb; color: #92400e;">
                                                <i class="fa fa-info-circle"></i> <strong>Standalone / Temporary License Active</strong>
                                                <p class="mtop5" style="margin-bottom: 0;">SalesOS is currently operating in standalone mode. All core features and sibling modules are unlocked and functional. When your Licentra cloud license server is deployed, you can connect your license key below.</p>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($is_dev_mode)): ?>
                                            <div class="alert alert-info" style="border-left: 4px solid #0284c7; background: #f0f9ff; color: #0369a1;">
                                                <i class="fa fa-wrench"></i> <strong>Local Development Mode Active</strong>
                                                <p class="mtop5" style="margin-bottom: 0;">You are developing on local environment (<code><?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'local') ?></code>). All SalesOS operations are unlocked. Production license verification is bound to <code>crm.bizyto.com</code>.</p>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($isValid): ?>
                                            <div class="alert alert-success">
                                                <i class="fa fa-check-circle"></i> <strong>License Status: Active &amp; Valid</strong>
                                                <?php if ($inGrace): ?>
                                                    <span class="label label-warning mleft10">Operating in Offline Grace Period</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-danger">
                                                <i class="fa fa-exclamation-triangle"></i> <strong>License Status: <?= htmlspecialchars(strtoupper($status)) ?></strong>
                                                <p class="mtop5">SalesOS e-commerce operations are currently locked. Enter and activate a valid license key below to unlock.</p>
                                            </div>
                                        <?php endif; ?>

                                        <table class="table table-bordered table-striped mtop20">
                                            <tbody>
                                                <tr>
                                                    <th style="width: 30%;">Product</th>
                                                    <td><strong>SalesOS E-commerce Core</strong> (<code>salesos</code>)</td>
                                                </tr>
                                                <tr>
                                                    <th>License Key</th>
                                                    <td><code><?= htmlspecialchars($maskedKey) ?></code></td>
                                                </tr>
                                                <tr>
                                                    <th>Registered Domain</th>
                                                    <td><code><?= htmlspecialchars($domain) ?></code></td>
                                                </tr>
                                                <tr>
                                                    <th>Installation ID</th>
                                                    <td><code><?= htmlspecialchars($instId) ?></code></td>
                                                </tr>
                                                <tr>
                                                    <th>Environment</th>
                                                    <td><span class="label label-default"><?= htmlspecialchars($info['environment'] ?? 'production') ?></span></td>
                                                </tr>
                                            </tbody>
                                        </table>

                                        <?php if (!empty($entitlements)): ?>
                                            <h4 class="mtop25 font-medium"><i class="fa fa-list-alt"></i> Active Entitlements</h4>
                                            <table class="table table-bordered table-condensed">
                                                <thead>
                                                    <tr>
                                                        <th>Feature / Entitlement</th>
                                                        <th>Value</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($entitlements as $feature => $val): ?>
                                                        <tr>
                                                            <td><code><?= htmlspecialchars((string) $feature) ?></code></td>
                                                            <td>
                                                                <?php if (is_bool($val)): ?>
                                                                    <span class="label label-<?= $val ? 'success' : 'danger' ?>"><?= $val ? 'Enabled' : 'Disabled' ?></span>
                                                                <?php else: ?>
                                                                    <strong><?= htmlspecialchars(is_scalar($val) ? (string) $val : json_encode($val)) ?></strong>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        <?php endif; ?>

                                        <hr class="hr-panel-heading" />

                                        <?= form_open(admin_url('salesos/settings')) ?>
                                        <input type="hidden" name="license_settings" value="1">

                                        <div class="form-group">
                                            <label for="salesos_licentra_api_url" class="control-label">Licentra Server API URL (Optional)</label>
                                            <input type="url"
                                                   name="salesos_licentra_api_url"
                                                   id="salesos_licentra_api_url"
                                                   class="form-control"
                                                   placeholder="https://licentra.yourdomain.com"
                                                   value="<?= htmlspecialchars(get_option('salesos_licentra_api_url') ?: '') ?>">
                                            <small class="text-muted">The base URL of your central Licentra license server instance. Leave empty for default.</small>
                                        </div>

                                        <div class="checkbox checkbox-primary mtop15 mbot20">
                                            <input type="checkbox"
                                                   name="salesos_license_standalone_mode"
                                                   id="salesos_license_standalone_mode"
                                                   value="1"
                                                   <?= !empty($is_standalone) ? 'checked' : '' ?>>
                                            <label for="salesos_license_standalone_mode">Enable Standalone Mode (Run without online Licentra connection)</label>
                                        </div>

                                        <div class="form-group">
                                            <label for="salesos_license_key" class="control-label">
                                                <?= $isValid ? 'Change / Re-activate License Key' : 'Enter License Key' ?>
                                            </label>
                                            <div class="input-group">
                                                <input type="text"
                                                       name="salesos_license_key"
                                                       id="salesos_license_key"
                                                       class="form-control"
                                                       placeholder="LCT-XXXX-XXXX-XXXX-XXXX"
                                                       value="<?= htmlspecialchars(get_option('salesos_license_key') ?: '') ?>">
                                                <span class="input-group-btn">
                                                    <button type="submit" name="license_action" value="activate" class="btn btn-primary">
                                                        <i class="fa fa-bolt"></i> Activate
                                                    </button>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="mtop15">
                                            <button type="submit" name="license_action" value="save_only" class="btn btn-default mright5">
                                                <i class="fa fa-save"></i> Save Settings
                                            </button>
                                            <?php if ($isValid): ?>
                                                <button type="submit" name="license_action" value="validate" class="btn btn-info mright5">
                                                    <i class="fa fa-refresh"></i> Check License Now
                                                </button>
                                                <button type="submit" name="license_action" value="deactivate" class="btn btn-danger" onclick="return confirm('Are you sure you want to deactivate this license? This installation will lose access to SalesOS operations.');">
                                                    <i class="fa fa-times-circle"></i> Deactivate
                                                </button>
                                            <?php endif; ?>
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

    // Bizbot Agent Mapping Repeater
    $('#btn-add-agent-map').on('click', function() {
        var staffOptions = '<option value="">-- Select Staff --</option>';
        <?php if (isset($all_staff) && is_array($all_staff)): ?>
            <?php foreach ($all_staff as $stf): ?>
                staffOptions += '<option value="<?= $stf['staffid'] ?>"><?= e(addslashes($stf['firstname'] . ' ' . $stf['lastname'])) ?></option>';
            <?php endforeach; ?>
        <?php endif; ?>

        var row = '<tr>' +
            '<td><input type="text" name="bizbot_agent_guid[]" class="form-control input-sm font-monospace" placeholder="e.g. 203fb98c-c326-3626-a5ac-97827462a95b" required></td>' +
            '<td><select name="bizbot_agent_staff[]" class="form-control input-sm" required>' + staffOptions + '</select></td>' +
            '<td class="text-center"><button type="button" class="btn btn-danger btn-xs btn-remove-agent-row"><i class="fa fa-times"></i></button></td>' +
            '</tr>';
        $('#table-bizbot-agent-mapping tbody').append(row);
    });

    $(document).on('click', '.btn-remove-agent-row', function() {
        if ($('#table-bizbot-agent-mapping tbody tr').length > 1) {
            $(this).closest('tr').remove();
        } else {
            $(this).closest('tr').find('input').val('');
            $(this).closest('tr').find('select').val('');
        }
    });
});
</script>
