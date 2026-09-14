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
                            <i class="fa fa-truck"></i> Add New Supplier
                        </h4>
                        <hr class="hr-panel-heading" />
                        
                        <?= form_open(admin_url('purchases/suppliers'), ['id' => 'supplier-form']) ?>
                        <input type="hidden" name="id" id="sup_id">

                        <div class="form-group">
                            <label for="name" class="control-label">Supplier Name</label>
                            <input type="text" name="name" id="sup_name" class="form-control" placeholder="e.g. Acme Corp" required>
                        </div>

                        <div class="form-group">
                            <label for="phone" class="control-label">Phone Number</label>
                            <input type="text" name="phone" id="sup_phone" class="form-control" placeholder="Contact number">
                        </div>

                        <div class="form-group">
                            <label for="email" class="control-label">Email Address</label>
                            <input type="email" name="email" id="sup_email" class="form-control" placeholder="Email">
                        </div>

                        <div class="form-group">
                            <label for="address" class="control-label">Office Address</label>
                            <textarea name="address" id="sup_address" class="form-control" rows="3" placeholder="Address"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="opening_balance" class="control-label">Opening Balance (BDT)</label>
                            <input type="number" step="0.01" name="opening_balance" id="sup_opening_balance" class="form-control" value="0.00" required>
                            <small class="text-muted">Current amount we owe them before starting.</small>
                        </div>

                        <div class="mtop15">
                            <button type="submit" class="btn btn-primary" id="btn-submit">Save Supplier</button>
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
                            <i class="fa fa-list"></i> Suppliers Registry
                        </h4>
                        <hr class="hr-panel-heading" />
                        
                        <?php if (empty($suppliers)): ?>
                            <p class="text-muted no-margin">No suppliers connected yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped no-mtop">
                                    <thead>
                                        <tr>
                                            <th>Supplier Name</th>
                                            <th>Contact Info</th>
                                            <th>Current Balance</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($suppliers as $sup): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= e($sup['name']) ?></strong>
                                                    <br><small class="text-muted"><?= e($sup['address'] ?: 'No address') ?></small>
                                                </td>
                                                <td>
                                                    <i class="fa fa-phone text-muted"></i> <?= e($sup['phone'] ?: '-') ?>
                                                    <br><i class="fa fa-envelope text-muted"></i> <?= e($sup['email'] ?: '-') ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                        $label_class = $sup['current_balance'] > 0 ? 'danger' : 'success';
                                                    ?>
                                                    <span class="label label-<?= $label_class ?>">
                                                        <strong><?= salesos_format_number($sup['current_balance']) ?> BDT</strong>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="<?= admin_url('purchases/supplier_ledger/' . $sup['id']) ?>" class="btn btn-info btn-xs" title="View Statement Ledger">
                                                        <i class="fa fa-history"></i> Ledger
                                                    </a>
                                                    <button type="button" class="btn btn-default btn-xs edit-sup-btn"
                                                            data-id="<?= $sup['id'] ?>"
                                                            data-name="<?= e($sup['name']) ?>"
                                                            data-phone="<?= e($sup['phone']) ?>"
                                                            data-email="<?= e($sup['email']) ?>"
                                                            data-address="<?= e($sup['address']) ?>"
                                                            data-opening-balance="<?= $sup['opening_balance'] ?>">
                                                        <i class="fa fa-pencil"></i>
                                                    </button>
                                                    <a href="<?= admin_url('purchases/delete_supplier/' . $sup['id']) ?>" class="btn btn-danger btn-xs _delete">
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
    var editButtons = document.querySelectorAll('.edit-sup-btn');
    var formTitle = document.getElementById('form-title');
    var idInput = document.getElementById('sup_id');
    var nameInput = document.getElementById('sup_name');
    var phoneInput = document.getElementById('sup_phone');
    var emailInput = document.getElementById('sup_email');
    var addressInput = document.getElementById('sup_address');
    var balanceInput = document.getElementById('sup_opening_balance');
    var submitButton = document.getElementById('btn-submit');
    var cancelButton = document.getElementById('btn-cancel');

    editButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var name = this.getAttribute('data-name');
            var phone = this.getAttribute('data-phone');
            var email = this.getAttribute('data-email');
            var address = this.getAttribute('data-address');
            var balance = this.getAttribute('data-opening-balance');

            // Form fill
            idInput.value = id;
            nameInput.value = name;
            phoneInput.value = phone;
            emailInput.value = email;
            addressInput.value = address;
            balanceInput.value = balance;

            formTitle.innerHTML = '<i class="fa fa-pencil"></i> Edit Supplier';
            submitButton.innerHTML = 'Update Supplier';
            cancelButton.style.display = 'inline-block';
        });
    });

    cancelButton.addEventListener('click', function() {
        document.getElementById('supplier-form').reset();
        idInput.value = '';
        formTitle.innerHTML = '<i class="fa fa-truck"></i> Add New Supplier';
        submitButton.innerHTML = 'Save Supplier';
        this.style.display = 'none';
    });
});
</script>
