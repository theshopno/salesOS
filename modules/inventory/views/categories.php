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
                            <i class="fa fa-tag"></i> Add New Category
                        </h4>
                        <hr class="hr-panel-heading" />
                        
                        <?= form_open(admin_url('inventory/categories'), ['id' => 'category-form']) ?>
                        <input type="hidden" name="id" id="cat_id">

                        <div class="form-group">
                            <label for="name" class="control-label">Category Name</label>
                            <input type="text" name="name" id="cat_name" class="form-control" placeholder="e.g. Clothing, Electronics" required>
                        </div>

                        <div class="form-group">
                            <label for="parent_id" class="control-label">Parent Category (Optional)</label>
                            <select name="parent_id" id="cat_parent_id" class="form-control">
                                <option value="">None (Top Level)</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mtop15">
                            <button type="submit" class="btn btn-primary" id="btn-submit">Save Category</button>
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
                            <i class="fa fa-list"></i> Categories List
                        </h4>
                        <hr class="hr-panel-heading" />
                        
                        <?php if (empty($categories)): ?>
                            <p class="text-muted no-margin">No categories defined yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped no-mtop">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Parent Category</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                            // Map category names by ID for easy lookup
                                            $cat_map = [];
                                            foreach ($categories as $c) {
                                                $cat_map[$c['id']] = $c['name'];
                                            }
                                        ?>
                                        <?php foreach ($categories as $cat): ?>
                                            <tr>
                                                <td><?= $cat['id'] ?></td>
                                                <td><strong><?= e($cat['name']) ?></strong></td>
                                                <td>
                                                    <?php if ($cat['parent_id'] && isset($cat_map[$cat['parent_id']])): ?>
                                                        <span class="text-muted"><?= e($cat_map[$cat['parent_id']]) ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">&mdash;</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-default btn-xs edit-cat-btn"
                                                            data-id="<?= $cat['id'] ?>"
                                                            data-name="<?= e($cat['name']) ?>"
                                                            data-parent-id="<?= $cat['parent_id'] ?>">
                                                        <i class="fa fa-pencil"></i>
                                                    </button>
                                                    <a href="<?= admin_url('inventory/delete_category/' . $cat['id']) ?>" class="btn btn-danger btn-xs _delete">
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
    var editButtons = document.querySelectorAll('.edit-cat-btn');
    var formTitle = document.getElementById('form-title');
    var idInput = document.getElementById('cat_id');
    var nameInput = document.getElementById('cat_name');
    var parentSelect = document.getElementById('cat_parent_id');
    var submitButton = document.getElementById('btn-submit');
    var cancelButton = document.getElementById('btn-cancel');

    editButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var name = this.getAttribute('data-name');
            var parentId = this.getAttribute('data-parent-id');

            // Form fill
            idInput.value = id;
            nameInput.value = name;
            parentSelect.value = parentId;

            formTitle.innerHTML = '<i class="fa fa-pencil"></i> Edit Category';
            submitButton.innerHTML = 'Update Category';
            cancelButton.style.display = 'inline-block';
        });
    });

    cancelButton.addEventListener('click', function() {
        document.getElementById('category-form').reset();
        idInput.value = '';
        formTitle.innerHTML = '<i class="fa fa-tag"></i> Add New Category';
        submitButton.innerHTML = 'Save Category';
        this.style.display = 'none';
    });
});
</script>
