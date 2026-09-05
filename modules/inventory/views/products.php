<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <!-- Header -->
                        <div class="row mbot20">
                            <div class="col-md-12">
                                <div class="pull-right">
                                    <?php if (staff_can('create', 'inventory')): ?>
                                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#product-modal" id="btn-add-product">
                                            <i class="fa fa-plus"></i> Add New Product
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <h4 class="no-margin bold font-medium text-primary">Products Catalog</h4>
                                <span class="text-muted">Manage SKU-tracked products and link them to invoice items.</span>
                            </div>
                        </div>
                        <hr class="hr-panel-heading" />

                        <!-- Catalog Table -->
                        <?php if (empty($products)): ?>
                            <p class="text-muted no-margin">No inventory products found. Click "Add New Product" to start.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped no-mtop">
                                    <thead>
                                        <tr>
                                            <th>SKU</th>
                                            <th>Name</th>
                                            <th>Category</th>
                                            <th>Linked Item</th>
                                            <th>Reorder Level</th>
                                            <th>Stock level</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($products as $prod): ?>
                                            <?php 
                                                $stock = (float) $prod['stock_on_hand'];
                                                $reorder = (float) $prod['reorder_level'];
                                                
                                                $label_class = 'success';
                                                if ($stock <= 0) {
                                                    $label_class = 'danger';
                                                } elseif ($stock <= $reorder) {
                                                    $label_class = 'warning';
                                                }
                                            ?>
                                            <tr>
                                                <td><strong><?= e($prod['sku'] ?: '-') ?></strong></td>
                                                <td>
                                                     <?php if (!empty($prod['image'])): ?>
                                                         <img src="<?= e($prod['image']) ?>" style="width: 32px; height: 32px; object-fit: cover; border-radius: 4px; margin-right: 8px; vertical-align: middle;" />
                                                     <?php endif; ?>
                                                     <?= e($prod['name']) ?>
                                                 </td>
                                                <td>
                                                    <span class="label label-default"><?= e($prod['category_name'] ?: 'Uncategorized') ?></span>
                                                </td>
                                                <td>
                                                     <?php if ($prod['item_id'] !== null && $prod['item_id'] !== ''): ?>
                                                         <span class="text-info"><i class="fa fa-link"></i> <?= e($prod['item_name']) ?></span>
                                                     <?php else: ?>
                                                         <span class="text-muted">Not linked</span>
                                                     <?php     endif; ?>
                                                </td>
                                                <td><?= number_format($reorder, 2) ?></td>
                                                <td>
                                                    <span class="label label-<?= $label_class ?>">
                                                        <strong><?= number_format($stock, 2) ?></strong>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="label label-<?= $prod['is_active'] ? 'success' : 'danger' ?>">
                                                        <?= $prod['is_active'] ? 'Active' : 'Inactive' ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (staff_can('edit', 'inventory')): ?>
                                                         <button type="button" class="btn btn-default btn-xs edit-product-btn"
                                                                 data-id="<?= $prod['id'] ?>"
                                                                 data-item-id="<?= $prod['item_id'] ?>"
                                                                 data-sku="<?= e($prod['sku']) ?>"
                                                                 data-name="<?= e($prod['name']) ?>"
                                                                 data-category-id="<?= $prod['category_id'] ?>"
                                                                 data-image="<?= e($prod['image']) ?>"
                                                                 data-reorder-level="<?= $prod['reorder_level'] ?>"
                                                                 data-is-active="<?= $prod['is_active'] ?>">
                                                            <i class="fa fa-pencil"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if (staff_can('delete', 'inventory')): ?>
                                                        <a href="<?= admin_url('inventory/delete_product/' . $prod['id']) ?>" class="btn btn-danger btn-xs _delete">
                                                            <i class="fa fa-remove"></i>
                                                        </a>
                                                    <?php endif; ?>
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

<!-- Modal -->
<div class="modal fade" id="product-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="modal-title-text">Add New Product</h4>
            </div>
            <?= form_open(admin_url('inventory/products'), ['id' => 'product-form']) ?>
            <input type="hidden" name="id" id="prod_id">
            <div class="modal-body">
                <div class="form-group">
                    <label for="sku" class="control-label">Product SKU</label>
                    <input type="text" name="sku" id="prod_sku" class="form-control" placeholder="e.g. PROD-100X" required>
                </div>

                <div class="form-group">
                    <label for="name" class="control-label">Product Name</label>
                    <input type="text" name="name" id="prod_name" class="form-control" placeholder="Product name" required>
                </div>

                <div class="form-group">
                    <label for="image" class="control-label">Product Image URL</label>
                    <input type="text" name="image" id="prod_image" class="form-control" placeholder="e.g. https://example.com/image.jpg">
                </div>

                <div class="form-group">
                    <label for="category_id" class="control-label">Category</label>
                    <select name="category_id" id="prod_category_id" class="form-control">
                        <option value="">Select Category...</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="item_id" class="control-label">Link to Billing/Invoice Item (Optional)</label>
                    <select name="item_id" id="prod_item_id" class="form-control">
                        <option value="">None</option>
                        <?php foreach ($items as $item): ?>
                            <option value="<?= $item['id'] ?>"><?= e($item['description']) ?> (Rate: <?= $item['rate'] ?> BDT)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="reorder_level" class="control-label">Reorder Level (Alert threshold)</label>
                    <input type="number" step="0.01" name="reorder_level" id="prod_reorder_level" class="form-control" value="0.00" required>
                </div>

                <div class="checkbox checkbox-primary">
                    <input type="checkbox" name="is_active" id="prod_is_active" value="1" checked>
                    <label for="prod_is_active">Active</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary" id="modal-submit-btn">Save Product</button>
            </div>
            <?= form_close() ?>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var editButtons = document.querySelectorAll('.edit-product-btn');
    var modalTitleText = document.getElementById('modal-title-text');
    var idInput = document.getElementById('prod_id');
    var skuInput = document.getElementById('prod_sku');
    var nameInput = document.getElementById('prod_name');
    var catSelect = document.getElementById('prod_category_id');
    var itemSelect = document.getElementById('prod_item_id');
    var reorderInput = document.getElementById('prod_reorder_level');
    var activeCheckbox = document.getElementById('prod_is_active');
    var submitBtn = document.getElementById('modal-submit-btn');

    editButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var itemId = this.getAttribute('data-item-id');
            var sku = this.getAttribute('data-sku');
            var name = this.getAttribute('data-name');
            var catId = this.getAttribute('data-category-id');
            var image = this.getAttribute('data-image');
            var reorder = this.getAttribute('data-reorder-level');
            var active = this.getAttribute('data-is-active') == '1';

            // Fill form
            idInput.value = id;
            skuInput.value = sku;
            nameInput.value = name;
            document.getElementById('prod_image').value = image || '';
            catSelect.value = catId;
            itemSelect.value = itemId;
            reorderInput.value = reorder;
            activeCheckbox.checked = active;

            modalTitleText.innerText = 'Edit Product';
            submitBtn.innerText = 'Update Product';
            $('#product-modal').modal('show');
        });
    });

    document.getElementById('btn-add-product').addEventListener('click', function() {
        document.getElementById('product-form').reset();
        idInput.value = '';
        document.getElementById('prod_image').value = '';
        modalTitleText.innerText = 'Add New Product';
        submitBtn.innerText = 'Save Product';
    });
});
</script>
