<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin font-medium">
                            <i class="fa fa-cog"></i> Inventory Settings
                        </h4>
                        <hr class="hr-panel-heading" />

                        <?= form_open(admin_url('inventory/settings'), ['id' => 'inventory-settings-form']) ?>

                        <div class="form-group">
                            <label for="inventory_industry_mode" class="control-label bold">
                                <i class="fa fa-briefcase"></i> ব্যবসার ধরণ / Business Industry Profile
                            </label>
                            <select name="inventory_industry_mode" id="inventory_industry_mode" class="form-control">
                                <option value="gadgets" <?= ($inventory_industry_mode === 'gadgets') ? 'selected' : '' ?>>
                                    📱 গ্যাজেট ও ইলেকট্রনিক্স (Gadgets & Electronics) — ওয়ারেন্টি, মডেল ও IMEI/সিরিয়াল ট্র্যাকিং
                                </option>
                                <option value="fashion" <?= ($inventory_industry_mode === 'fashion') ? 'selected' : '' ?>>
                                    👕 ফ্যাশন ও পোশাক (Fashion & Apparel) — সাইজ, কালার ও ভ্যারিয়েশন ম্যাট্রিক্স
                                </option>
                                <option value="grocery" <?= ($inventory_industry_mode === 'grocery') ? 'selected' : '' ?>>
                                    🍎 মুদি ও খাদ্যপণ্য (Grocery & FMCG) — ইউনিট অব মেজার (কেজি/গ্রাম/লিটার) ও মেয়াদোত্তীর্ণ তারিখ
                                </option>
                                <option value="general" <?= ($inventory_industry_mode === 'general') ? 'selected' : '' ?>>
                                    🏬 সাধারণ রিটেল (General Retail) — স্ট্যান্ডার্ড প্রোডাক্ট ও কাস্টম স্পেসিফিকেশন
                                </option>
                            </select>
                            <p class="text-muted mtop5">
                                নির্বাচিত ব্যবসার ধরণের উপর ভিত্তি করে প্রোডাক্ট এন্ট্রি ফর্মে কেবল প্রাসঙ্গিক ফিল্ডগুলো (যেমন: পোশাকের জন্য সাইজ/কালার, গ্যাজেটের জন্য ওয়ারেন্টি/IMEI) ডায়নামিক্যালি প্রদর্শিত হবে।
                            </p>
                        </div>

                        <hr class="hr-panel-heading" />

                        <div class="form-group">
                            <div class="checkbox checkbox-primary">
                                <input type="checkbox"
                                       name="inventory_allow_oversell"
                                       id="inventory_allow_oversell"
                                       value="1"
                                       <?= $inventory_allow_oversell ? 'checked' : '' ?>>
                                <label for="inventory_allow_oversell">
                                    Allow overselling (backorders)
                                </label>
                            </div>
                            <p class="text-muted no-margin">
                                Off (recommended): a sale is refused when there is not enough
                                stock, and stock can never go below zero.<br>
                                On: sales go through even with no stock available, and stock
                                is allowed to go negative.
                            </p>
                        </div>

                        <div class="form-group mtop20">
                            <div class="checkbox checkbox-primary">
                                <input type="checkbox"
                                       name="inventory_stock_reservation_enabled"
                                       id="inventory_stock_reservation_enabled"
                                       value="1"
                                       <?= get_option('inventory_stock_reservation_enabled') !== '0' ? 'checked' : '' ?>>
                                <label for="inventory_stock_reservation_enabled">
                                    Enable stock reservation on unconfirmed orders (স্টক রিজার্ভেশন)
                                </label>
                            </div>
                            <p class="text-muted no-margin">
                                On (recommended): Pending/unconfirmed orders automatically hold reserved stock (<code>qty_reserved</code>),
                                so other customers or telesales cannot sell the same units. Converting the order to confirmed fulfills the reservation into a sale; cancelling releases it immediately.
                            </p>
                        </div>

                        <hr class="hr-panel-heading" />
                        <button type="submit" class="btn btn-info">Save Settings</button>

                        <?= form_close() ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
</body>
</html>
