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
