<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
    /* Hide default Perfex CRM Header and Sidebar Menu */
    #header, #menu {
        display: none !important;
    }
    #wrapper {
        margin-left: 0 !important;
        margin-top: 0 !important;
        padding-top: 0 !important;
        background: #f8fafc !important;
        width: 100% !important;
    }
    
    .pos-container {
        padding: 10px 15px;
    }
    .pos-header-bar {
        background: #fff;
        border-bottom: 1px solid #e2e8f0;
        padding: 8px 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        border-radius: 4px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .pos-header-left {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .pos-header-right {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .pos-btn-icon {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #6366f1;
        color: #fff !important;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: opacity 0.2s;
        font-size: 14px;
    }
    .pos-btn-icon:hover {
        opacity: 0.9;
    }
    .pos-btn-icon.btn-danger {
        background: #ef4444;
    }
    
    /* Workspace Cards */
    .pos-panel {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 12px;
        height: calc(100vh - 80px);
        display: flex;
        flex-direction: column;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    }
    
    /* Left column inputs */
    .pos-cart-header {
        display: grid;
        grid-template-columns: 0.8fr 1.4fr 0.8fr;
        gap: 8px;
        margin-bottom: 12px;
    }
    
    /* Scrollable Cart area */
    .pos-cart-items-container {
        flex-grow: 1;
        overflow-y: auto;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        background: #fff;
        margin-bottom: 12px;
        min-height: 200px;
    }
    .pos-cart-table {
        width: 100%;
        margin: 0;
    }
    .pos-cart-table th {
        background: #f8fafc;
        color: #475569;
        font-weight: 600;
        font-size: 12px;
        padding: 8px 10px;
        border-bottom: 1px solid #e2e8f0;
    }
    .pos-cart-table td {
        padding: 8px 10px;
        font-size: 13px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }
    
    /* Cart Calculation block */
    .pos-calc-block {
        border-top: 1px solid #e2e8f0;
        padding-top: 10px;
        margin-bottom: 10px;
    }
    .pos-calc-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 6px;
        font-size: 13px;
        color: #475569;
    }
    .pos-calc-row-main {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
    }
    .pos-calc-col {
        flex: 1;
    }
    
    /* Total Payable Section */
    .pos-total-payable-bar {
        background: transparent;
        border-top: 1px solid #e2e8f0;
        padding: 10px 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    .pos-total-title {
        font-size: 20px;
        font-weight: 800;
        color: #6366f1;
    }
    
    /* Bottom Toolbar */
    .pos-bottom-toolbar {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 6px;
    }
    
    /* Vertical Category Tab columns */
    .pos-category-sidebar {
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .pos-category-tab {
        background: #fff;
        border: 1px solid #e2e8f0;
        padding: 10px 8px;
        text-align: center;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        color: #64748b;
        transition: all 0.2s;
    }
    .pos-category-tab:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
    }
    .pos-category-tab.active {
        background: #6366f1;
        color: #fff;
        border-color: #6366f1;
    }
    
    /* Right column product listing */
    .pos-products-header {
        margin-bottom: 12px;
    }
    .pos-products-grid {
        flex-grow: 1;
        overflow-y: auto;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
        align-content: start;
        gap: 10px;
    }
    .pos-product-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        padding: 8px;
        text-align: center;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 160px;
        transition: all 0.2s;
        box-shadow: 0 1px 2px rgba(0,0,0,0.02);
    }
    .pos-product-card:hover {
        border-color: #6366f1;
        box-shadow: 0 2px 6px rgba(99, 102, 241, 0.1);
    }
    .pos-product-card.out-of-stock {
        opacity: 0.6;
        cursor: not-allowed;
    }
    .pos-product-card-img {
        width: 100%;
        aspect-ratio: 1 / 1;
        object-fit: cover;
        border-radius: 2px;
        margin-bottom: 6px;
        background: #f1f5f9;
    }
    .pos-product-card-name {
        font-size: 12px;
        font-weight: 700;
        color: #475569;
        margin: 0 0 4px 0;
        min-height: 32px;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }
    
    /* Buttons */
    .bg-purple {
        background: #6366f1 !important;
        border-color: #6366f1 !important;
        color: #fff !important;
    }
    .bg-orange {
        background: #f97316 !important;
        border-color: #f97316 !important;
        color: #fff !important;
    }
    .bg-blue {
        background: #3b82f6 !important;
        border-color: #3b82f6 !important;
        color: #fff !important;
    }
    
    /* Empty cart */
    .pos-empty-cart {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 180px;
        color: #94a3b8;
    }
    .pos-empty-cart i {
        font-size: 32px;
        margin-bottom: 8px;
    }
</style>
<div id="wrapper">
    <div class="pos-container">
    
    <!-- Top utility Header bar -->
    <div class="pos-header-bar">
        <div class="pos-header-left">
            <input type="text" class="form-control input-sm" placeholder="Search Menu" style="width: 140px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px; display: inline-block;">
            <a href="<?= admin_url() ?>" class="btn btn-primary bg-purple btn-sm bold" style="margin-left: 5px;"><i class="fa fa-dashboard"></i> Dashboard</a>
        </div>
        <div class="pos-header-right">
            <button class="pos-btn-icon" title="Language" style="background:#6366f1;"><i class="fa fa-language"></i></button>
            <button class="pos-btn-icon" title="Customer Display" onclick="window.open('<?= admin_url('pos/customer_display') ?>', 'CustomerDisplay', 'width=1100,height=750');" style="background:#6366f1;"><i class="fa fa-desktop"></i></button>
            <button class="pos-btn-icon" title="Calculator" data-toggle="modal" data-target="#pos-calculator-modal" style="background:#6366f1;"><i class="fa fa-calculator"></i></button>
            <button class="pos-btn-icon" title="Toggle Fullscreen" onclick="toggleFullScreen();" style="background:#6366f1;"><i class="fa fa-arrows-alt"></i></button>
            <button class="pos-btn-icon" title="Sync / Reload" onclick="location.reload();" style="background:#0284c7;"><i class="fa fa-database"></i></button>
        </div>
    </div>

    <!-- Main Grid Workspace -->
    <div class="row">
        <!-- 1. Left Column: Product Cards & Search (50% Width) -->
        <div class="col-md-5" style="padding-right: 7px;">
            <div class="pos-panel">
                <!-- Search bar -->
                <div class="pos-products-header">
                    <div class="input-group">
                        <span class="input-group-addon"><i class="fa fa-barcode"></i></span>
                        <input type="text" id="pos-product-search" class="form-control" placeholder="Barcode | Name | SKU | Category...">
                    </div>
                </div>

                <!-- Product Card list grid -->
                <div class="pos-products-grid" id="pos-products-grid">
                    <?php foreach ($products as $prod): ?>
                        <?php
                            $stock = (float) $prod['stock_on_hand'];
                            $is_out = $stock <= 0;
                            // Rate now comes from Inventory_model::get_products()'s own JOIN — this
                            // used to run one extra query per product card (up to hundreds on load).
                            $rate = (float) ($prod['rate'] ?? 0.00);
                        ?>
                        <div class="pos-product-card-container" 
                             data-id="<?= $prod['id'] ?>"
                             data-sku="<?= e($prod['sku']) ?>"
                             data-name="<?= e($prod['name']) ?>"
                             data-category-id="<?= $prod['category_id'] ?>"
                             data-stock="<?= $stock ?>"
                             data-rate="<?= $rate ?>">
                            <div class="pos-product-card <?= $is_out ? 'out-of-stock' : '' ?>">
                                <div>
                                     <!-- Display generic image helper -->
                                     <div class="pos-product-card-img" style="display:flex; align-items:center; justify-content:center; color:#94a3b8; overflow:hidden;">
                                         <?php if (!empty($prod['image'])): ?>
                                             <img src="<?= e($prod['image']) ?>" style="width: 100%; height: 100%; object-fit: cover;" />
                                         <?php else: ?>
                                             <i class="fa fa-picture-o" style="font-size:24px;"></i>
                                         <?php endif; ?>
                                     </div>
                                    <h5 class="pos-product-card-name" style="font-weight: 700; color: #475569; font-size: 11px; margin: 4px 0;"><?= e($prod['name']) ?></h5>
                                </div>
                                <div class="mtop5" style="text-align: center;">
                                    <div class="text-muted" style="font-size: 11px; margin-bottom: 2px; color: #64748b;">Price: <?= salesos_format_number($rate) ?></div>
                                    <div style="font-size: 11px; color: #94a3b8; font-weight: 600;">
                                        Stock: <?= $is_out ? 'N/A' : number_format($stock, 0) . '-PCS' ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Footer Product count display -->
                <div class="mtop10 text-right text-muted" style="font-size: 11px;">
                    <span id="pos-grid-count">Total Products: <?= count($products) ?></span><br>
                    <span class="text-danger">Max 500 Products will be displayed.</span>
                </div>
            </div>
        </div>

        <!-- 2. Middle Column: Categories sidebar vertical list (15% Width) -->
        <div class="col-md-2" style="padding-left: 3px; padding-right: 3px;">
            <div class="pos-panel" style="padding: 10px;">
                <div class="pos-category-sidebar">
                    <div class="pos-category-tab active" data-category-id="all">
                        <i class="fa fa-cubes display-block mbot5"></i> All Categories
                    </div>
                    <?php 
                        // Fetch inventory categories dynamically
                        $cats = $this->db->get(db_prefix() . 'inventory_categories')->result_array();
                        foreach ($cats as $cat):
                    ?>
                        <div class="pos-category-tab" data-category-id="<?= $cat['id'] ?>">
                            <?= e($cat['name']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- 3. Right Column: Checkout, Cart & Summary (35% or 40% Width) -->
        <div class="col-md-5" style="padding-left: 7px;">
            <div class="pos-panel">
                <!-- Cashier header input bar -->
                <div class="pos-cart-header">
                    <div>
                        <input type="text" class="form-control" value="Super Admin" readonly style="background: #f1f5f9; font-weight: bold; border-radius: 4px;">
                    </div>
                    <div class="input-group" style="width: 100%; display: flex;">
                        <div style="flex-grow: 1; min-width: 0;">
                            <select name="client_id" id="client_id" class="selectpicker" data-live-search="true" data-width="100%">
                                <option value="<?= get_option('pos_default_walkin_client_id') ?>">Walk-in Customer</option>
                                <?php foreach ($customers as $cust): ?>
                                    <?php if ($cust['userid'] != get_option('pos_default_walkin_client_id')): ?>
                                        <?php 
                                            $phone = trim($cust['contact_phone'] ?: ($cust['client_phone'] ?: ''));
                                            $email = trim($cust['contact_email'] ?: '');
                                            $subtext = '';
                                            if ($phone !== '' && $email !== '') {
                                                $subtext = $phone . ' | ' . $email;
                                            } elseif ($phone !== '') {
                                                $subtext = $phone;
                                            } elseif ($email !== '') {
                                                $subtext = $email;
                                            }
                                        ?>
                                        <option value="<?= $cust['userid'] ?>" data-subtext="<?= e($subtext) ?>"><?= e($cust['company']) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <span class="input-group-btn" style="width: auto; display: flex;">
                            <button type="button" class="btn btn-primary bg-purple" style="border-radius: 0; height: 36px; padding: 6px 12px;" title="Edit Customer" onclick="openEditCustomerModal();">
                                <i class="fa fa-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-primary bg-purple" style="border-radius: 0 4px 4px 0; height: 36px; padding: 6px 12px;" data-toggle="modal" data-target="#customer-quick-modal" title="Quick Add Customer">
                                <i class="fa fa-user-plus"></i>
                            </button>
                        </span>
                    </div>
                    <div>
                        <select class="form-control" readonly style="background: #f1f5f9; border-radius: 4px;">
                            <option>Regular</option>
                        </select>
                    </div>
                </div>

                <!-- Cart table area -->
                <div class="pos-cart-items-container">
                    <table class="table pos-cart-table" id="pos-cart-table">
                        <thead>
                            <tr>
                                <th width="50%">Product</th>
                                <th width="20%" class="text-center">Qty</th>
                                <th width="20%" class="text-right">Price</th>
                                <th width="10%"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Cart rows are generated dynamically via JavaScript -->
                        </tbody>
                    </table>
                    
                    <div class="pos-empty-cart" id="pos-empty-cart-view">
                        <i class="fa fa-shopping-cart"></i>
                        <p class="bold">Cart is empty</p>
                        <small>Scan products or add them from the list</small>
                    </div>
                </div>

                <!-- Calculation/totals footer block -->
                <div class="pos-calc-block">
                    <!-- Row 1: Subtotal & Tax -->
                    <div class="pos-calc-row">
                        <div>Subtotal: <strong id="calc-subtotal">0.00</strong> BDT</div>
                        <div>Tax: <i class="fa fa-eye text-muted pointer"></i> <strong id="calc-tax">0.00</strong> BDT</div>
                    </div>
                    
                    <!-- Row 2: Discount & Shipping -->
                    <div class="pos-calc-row-main mtop10">
                        <div class="pos-calc-col">
                            <label class="control-label" style="font-size:11px;">Discount</label>
                            <div class="input-group input-group-sm">
                                <input type="number" id="calc-discount-val" class="form-control" value="0" min="0">
                                <span class="input-group-btn" style="width: 55px;">
                                    <select id="calc-discount-type" class="form-control" style="padding: 0 5px; height: 30px;">
                                        <option value="fixed">BDT</option>
                                        <option value="percent">%</option>
                                    </select>
                                </span>
                            </div>
                        </div>
                        <div class="pos-calc-col">
                            <label class="control-label" style="font-size:11px;">Shipping Charge</label>
                            <input type="number" id="calc-shipping" class="form-control input-sm" value="0" min="0">
                        </div>
                    </div>
                </div>

                <!-- Total Payable & Process bar -->
                <div class="pos-total-payable-bar">
                    <div class="pos-total-title" id="pos-total-payable">Total Payable: 0.00 BDT</div>
                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="btn btn-warning bg-purple bold" id="btn-quick-cash">
                            <i class="fa fa-money"></i> Quick Cash
                        </button>
                        <button type="button" class="btn btn-primary bg-purple" data-toggle="modal" data-target="#payment-details-modal">
                            <i class="fa fa-ellipsis-h"></i> More
                        </button>
                    </div>
                </div>

                <!-- Bottom Toolbar buttons -->
                <div class="pos-bottom-toolbar">
                    <button type="button" class="btn btn-warning bg-orange btn-sm bold" id="btn-add-hold"><i class="fa fa-pause"></i> [ ] Add Hold</button>
                    <button type="button" class="btn btn-warning bg-orange btn-sm bold" id="btn-list-hold"><i class="fa fa-folder-open"></i> List Hold</button>
                    <button type="button" class="btn btn-primary bg-purple btn-sm bold" id="btn-sale-list"><i class="fa fa-list"></i> Sale List</button>
                    <a href="<?= admin_url('returns') ?>" class="btn btn-primary bg-blue btn-sm bold" target="_blank"><i class="fa fa-reply"></i> Return</a>
                    <button type="button" class="btn btn-primary bg-blue btn-sm bold" id="btn-pending-payment"><i class="fa fa-clock-o"></i> Pending Payment</button>
                    <button type="button" class="btn btn-danger btn-sm bold" id="btn-clear-cart"><i class="fa fa-trash"></i> Clear</button>
                </div>
            </div>
        </div>
    </div>

<!-- Modal: Close Session -->
<div class="modal fade" id="close-session-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Close Cashier Session</h4>
            </div>
            <?= form_open(admin_url('pos/close_session')) ?>
            <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
            <div class="modal-body">
                <div class="alert alert-info">
                    <strong>Opening Cash Float:</strong> <?= salesos_format_number($session['opening_balance']) ?> BDT
                </div>
                <div class="form-group">
                    <label for="closing_balance" class="control-label">Closing Cash Balance (BDT)</label>
                    <input type="number" step="0.01" name="closing_balance" id="closing_balance" class="form-control" required placeholder="Count physical cash in drawer...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Yes, Close Session</button>
            </div>
            <?= form_close() ?>
        </div>
    </div>
</div>

<!-- Modal: Quick Customer Add -->
<div class="modal fade" id="customer-quick-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Quick Add Customer</h4>
            </div>
            <div class="modal-body">
                <div id="customer-modal-alert" style="display:none;" class="alert alert-danger"></div>
                
                <div class="form-group">
                    <label for="m_company" class="control-label">Customer Name</label>
                    <input type="text" id="m_company" class="form-control" placeholder="Customer Name" required>
                </div>
                <div class="form-group">
                    <label for="m_phone" class="control-label">Phone Number</label>
                    <input type="text" id="m_phone" class="form-control" placeholder="Phone Number" required>
                </div>
                <div class="form-group">
                    <label for="m_email" class="control-label">Email Address (Optional)</label>
                    <input type="email" id="m_email" class="form-control" placeholder="Email Address">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btn-save-customer">Save Customer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Quick Customer Edit -->
<div class="modal fade" id="customer-edit-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Edit Customer</h4>
            </div>
            <div class="modal-body">
                <div id="customer-edit-modal-alert" style="display:none;" class="alert alert-danger"></div>
                <input type="hidden" id="edit_m_id">
                
                <div class="form-group">
                    <label for="edit_m_company" class="control-label">Customer Name</label>
                    <input type="text" id="edit_m_company" class="form-control" placeholder="Customer Name" required>
                </div>
                <div class="form-group">
                    <label for="edit_m_phone" class="control-label">Phone Number</label>
                    <input type="text" id="edit_m_phone" class="form-control" placeholder="Phone Number" required>
                </div>
                <div class="form-group">
                    <label for="edit_m_email" class="control-label">Email Address (Optional)</label>
                    <input type="email" id="edit_m_email" class="form-control" placeholder="Email Address">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btn-update-customer">Update Customer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: More Payment Details -->
<div class="modal fade" id="payment-details-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Enter Payment Details</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="payment_method" class="control-label">Payment Method</label>
                    <select id="payment_method" class="form-control">
                        <option value="cash">Cash</option>
                        <option value="bkash">bKash</option>
                        <option value="nagad">Nagad</option>
                        <option value="card">Card / POS machine</option>
                        <option value="pending_payment">Pending Payment (পেন্ডিং পেমেন্ট)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="payment_ref" class="control-label">Payment Ref / TxnID (Optional)</label>
                    <input type="text" id="payment_ref" class="form-control" placeholder="e.g. bkash TxnID, Card digits...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="btn-process-checkout-more">Complete Checkout</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Holds List -->
<div class="modal fade" id="holds-list-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-folder-open"></i> Held Carts List</h4>
            </div>
            <div class="modal-body" style="max-height: 450px; overflow-y: auto;">
                <table class="table table-bordered table-striped" id="holds-table">
                    <thead>
                        <tr>
                            <th>Hold ID</th>
                            <th>Customer</th>
                            <th>Hold Note</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Populated via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Sales List -->
<div class="modal fade" id="sales-list-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-list"></i> Cash Counter Sales (Current Session)</h4>
            </div>
            <div class="modal-body" style="max-height: 450px; overflow-y: auto;">
                <table class="table table-bordered table-striped" id="sales-table">
                    <thead>
                        <tr>
                            <th>Invoice Number</th>
                            <th>Customer</th>
                            <th>Payment Method</th>
                            <th>Total Amount</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Populated via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Register Status -->
<div class="modal fade" id="register-status-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-desktop"></i> Active Register Status</h4>
            </div>
            <div class="modal-body">
                <div class="list-group">
                    <div class="list-group-item">
                        <span class="badge" style="background:#4f46e5; font-size:12px;" id="reg-status-opening">0.00 BDT</span>
                        Opening cashfloat balance:
                    </div>
                    <div class="list-group-item">
                        <span class="badge" style="background:#22c55e; font-size:12px;" id="reg-status-sales">0.00 BDT</span>
                        Sales registered (paid):
                    </div>
                    <div class="list-group-item">
                        <span class="badge" style="background:#e0e7ff; color:#4f46e5; font-size:12px;" id="reg-status-total">0.00 BDT</span>
                        Expected Drawer cash balance:
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Calculator -->
<div class="modal fade" id="pos-calculator-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document" style="width: 300px; margin: 100px auto;">
        <div class="modal-content" style="background:#1e293b; color:#fff; border-radius:12px; border:none; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3);">
            <div class="modal-header" style="background:#1e293b !important; border-bottom: 1px solid #334155; padding: 12px 15px;">
                <button type="button" class="close" data-dismiss="modal" style="color:#ffffff !important; opacity:0.9; font-size: 24px; text-shadow: none; float: right; border: none; background: transparent; line-height: 1;">&times;</button>
                <h4 class="modal-title" style="color:#ffffff !important; font-weight:700; font-size:15px; margin: 0;"><i class="fa fa-calculator"></i> Calculator</h4>
            </div>
            <div class="modal-body" style="padding:15px; background: #1e293b;">
                <!-- Calculator Screen -->
                <input type="text" id="calc-screen" class="form-control text-right" readonly 
                       style="font-size:24px; height:50px; background:#0f172a; color:#22c55e; border:none; margin-bottom:15px; font-weight:700; padding:10px; border-radius:6px; font-family: monospace;">
                
                <!-- Calculator Keys Grid -->
                <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:8px;">
                    <button type="button" class="btn btn-default calc-btn" data-val="C" style="background:#475569; color:#fff; border:none; font-weight:700; font-size:16px; padding: 12px 0;">C</button>
                    <button type="button" class="btn btn-default calc-btn" data-val="DEL" style="background:#475569; color:#fff; border:none; font-weight:700; font-size:14px; padding: 12px 0;"><i class="fa fa-long-arrow-left"></i></button>
                    <button type="button" class="btn btn-default calc-btn" data-val="/" style="background:#f97316; color:#fff; border:none; font-weight:700; font-size:16px; padding: 12px 0;">/</button>
                    <button type="button" class="btn btn-default calc-btn" data-val="*" style="background:#f97316; color:#fff; border:none; font-weight:700; font-size:16px; padding: 12px 0;">*</button>
                    
                    <button type="button" class="btn btn-default calc-btn" data-val="7" style="background:#334155; color:#fff; border:none; font-weight:700; font-size:16px; padding: 12px 0;">7</button>
                    <button type="button" class="btn btn-default calc-btn" data-val="8" style="background:#334155; color:#fff; border:none; font-weight:700; font-size:16px; padding: 12px 0;">8</button>
                    <button type="button" class="btn btn-default calc-btn" data-val="9" style="background:#334155; color:#fff; border:none; font-weight:700; font-size:16px; padding: 12px 0;">9</button>
                    <button type="button" class="btn btn-default calc-btn" data-val="-" style="background:#f97316; color:#fff; border:none; font-weight:700; font-size:16px; padding: 12px 0;">-</button>
                    
                    <button type="button" class="btn btn-default calc-btn" data-val="4" style="background:#334155; color:#fff; border:none; font-weight:700; font-size:16px; padding: 12px 0;">4</button>
                    <button type="button" class="btn btn-default calc-btn" data-val="5" style="background:#334155; color:#fff; border:none; font-weight:700; font-size:16px; padding: 12px 0;">5</button>
                    <button type="button" class="btn btn-default calc-btn" data-val="6" style="background:#334155; color:#fff; border:none; font-weight:700; font-size:16px; padding: 12px 0;">6</button>
                    <button type="button" class="btn btn-default calc-btn" data-val="+" style="background:#f97316; color:#fff; border:none; font-weight:700; font-size:16px; padding: 12px 0;">+</button>
                    
                    <button type="button" class="btn btn-default calc-btn" data-val="1" style="background:#334155; color:#fff; border:none; font-weight:700; font-size:16px; padding: 12px 0;">1</button>
                    <button type="button" class="btn btn-default calc-btn" data-val="2" style="background:#334155; color:#fff; border:none; font-weight:700; font-size:16px; padding: 12px 0;">2</button>
                    <button type="button" class="btn btn-default calc-btn" data-val="3" style="background:#334155; color:#fff; border:none; font-weight:700; font-size:16px; padding: 12px 0;">3</button>
                    <button type="button" class="btn btn-default calc-btn" data-val="=" style="grid-row: span 2; background:#22c55e; color:#fff; border:none; font-weight:700; font-size:20px; height: 100%; border-radius: 4px;">=</button>
                    
                    <button type="button" class="btn btn-default calc-btn" data-val="0" style="grid-column: span 2; background:#334155; color:#fff; border:none; font-weight:700; font-size:16px; padding: 12px 0;">0</button>
                    <button type="button" class="btn btn-default calc-btn" data-val="." style="background:#334155; color:#fff; border:none; font-weight:700; font-size:16px; padding: 12px 0;">.</button>
                </div>
            </div>
        </div>
    </div>
</div>

    </div> <!-- .pos-container -->
</div> <!-- #wrapper -->

<?php init_tail(); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var cart = {}; // product_id => { id, name, sku, stock, rate, qty }
    var cartChannel = new BroadcastChannel('pos_cart_channel');

    // Core layout buttons
    var btnQuickCash = document.getElementById('btn-quick-cash');
    var btnAddHold = document.getElementById('btn-add-hold');
    var btnListHold = document.getElementById('btn-list-hold');
    var btnSaleList = document.getElementById('btn-sale-list');
    var btnPendingPayment = document.getElementById('btn-pending-payment');
    var btnClearCart = document.getElementById('btn-clear-cart');
    
    // Grid search & category filter tabs
    var posProductSearch = document.getElementById('pos-product-search');
    var categoryTabs = document.querySelectorAll('.pos-category-tab');
    var productContainers = document.querySelectorAll('.pos-product-card-container');

    // Subtotal calculations inputs
    var calcDiscountVal = document.getElementById('calc-discount-val');
    var calcDiscountType = document.getElementById('calc-discount-type');
    var calcShipping = document.getElementById('calc-shipping');
    
    var subtotalDisplay = document.getElementById('calc-subtotal');
    var totalPayableDisplay = document.getElementById('pos-total-payable');
    var emptyCartView = document.getElementById('pos-empty-cart-view');
    var cartTableBody = document.querySelector('#pos-cart-table tbody');

    // ── Cart UI Rendering ────────────────────────────────────────────────────
    
    function renderCart() {
        cartTableBody.innerHTML = '';
        var ids = Object.keys(cart);
        
        if (ids.length === 0) {
            emptyCartView.style.display = 'flex';
            subtotalDisplay.innerText = '0.00';
            totalPayableDisplay.innerText = 'Total Payable: 0.00 BDT';
            
            // Broadcast empty state
            cartChannel.postMessage({
                items: {},
                subtotal: 0,
                discount_val: 0,
                discount_type: 'fixed',
                shipping: 0,
                total: 0
            });
            return;
        }

        emptyCartView.style.display = 'none';
        var subtotal = 0.00;

        ids.forEach(function(id) {
            var item = cart[id];
            var lineTotal = item.qty * item.rate;
            subtotal += lineTotal;

            var row = document.createElement('tr');
            row.innerHTML = 
                '<td style="vertical-align:middle;">' +
                '   <strong class="display-block" style="font-size:12px;">' + item.name + '</strong>' +
                '   <small class="text-muted">SKU: ' + (item.sku || '-') + '</small>' +
                '</td>' +
                '<td class="text-center" style="vertical-align:middle;">' +
                '   <div class="input-group input-group-xs" style="width: 75px; margin: 0 auto;">' +
                '       <span class="input-group-btn">' +
                '           <button type="button" class="btn btn-default btn-xs minus-qty" data-id="' + id + '">-</button>' +
                '       </span>' +
                '       <input type="text" class="form-control text-center" value="' + item.qty + '" style="height:22px; padding:0;" readonly>' +
                '       <span class="input-group-btn">' +
                '           <button type="button" class="btn btn-default btn-xs plus-qty" data-id="' + id + '">+</button>' +
                '       </span>' +
                '   </div>' +
                '</td>' +
                '<td class="text-right" style="vertical-align:middle;">' +
                '   <strong>' + lineTotal.toFixed(2) + '</strong>' +
                '</td>' +
                '<td class="text-center" style="vertical-align:middle;">' +
                '   <button type="button" class="btn btn-danger btn-xs delete-item" data-id="' + id + '"><i class="fa fa-remove"></i></button>' +
                '</td>';

            cartTableBody.appendChild(row);
        });

        subtotalDisplay.innerText = subtotal.toFixed(2);
        recalculateTotals();

        // Plus/Minus click actions
        document.querySelectorAll('.minus-qty').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = this.getAttribute('data-id');
                if (cart[id].qty > 1) {
                    cart[id].qty--;
                } else {
                    delete cart[id];
                }
                renderCart();
            });
        });

        document.querySelectorAll('.plus-qty').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = this.getAttribute('data-id');
                if (cart[id].qty + 1 > cart[id].stock) {
                    alert_float('warning', 'Stock limit reached: ' + cart[id].stock);
                    return;
                }
                cart[id].qty++;
                renderCart();
            });
        });

        document.querySelectorAll('.delete-item').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = this.getAttribute('data-id');
                delete cart[id];
                renderCart();
            });
        });
    }

    function recalculateTotals() {
        var subtotal = parseFloat(subtotalDisplay.innerText) || 0;
        var discountVal = parseFloat(calcDiscountVal.value) || 0;
        var discountType = calcDiscountType.value;
        var shipping = parseFloat(calcShipping.value) || 0;

        var discountTotal = 0;
        if (discountType === 'percent') {
            discountTotal = (subtotal * discountVal) / 100;
        } else {
            discountTotal = discountVal;
        }

        var totalPayable = (subtotal - discountTotal) + shipping;
        if (totalPayable < 0) totalPayable = 0;

        totalPayableDisplay.innerText = 'Total Payable: ' + totalPayable.toFixed(2) + ' BDT';

        // Broadcast to customer display
        cartChannel.postMessage({
            items: cart,
            subtotal: subtotal,
            discount_val: discountVal,
            discount_type: discountType,
            shipping: shipping,
            total: totalPayable
        });
    }

    // Change calculation values keyups
    calcDiscountVal.addEventListener('input', recalculateTotals);
    calcDiscountType.addEventListener('change', recalculateTotals);
    calcShipping.addEventListener('input', recalculateTotals);

    // Add to cart click
    productContainers.forEach(function(container) {
        var card = container.querySelector('.pos-product-card');
        if (!card.classList.contains('out-of-stock')) {
            card.addEventListener('click', function() {
                var id = container.getAttribute('data-id');
                var name = container.getAttribute('data-name');
                var sku = container.getAttribute('data-sku');
                var stock = parseFloat(container.getAttribute('data-stock'));
                var rate = parseFloat(container.getAttribute('data-rate'));

                if (cart[id]) {
                    if (cart[id].qty + 1 > stock) {
                        alert_float('warning', 'Stock limit reached: ' + stock);
                        return;
                    }
                    cart[id].qty++;
                } else {
                    cart[id] = { id: id, name: name, sku: sku, stock: stock, rate: rate, qty: 1 };
                }
                renderCart();
            });
        }
    });

    // ── Grid Filtering (Search & Categories) ─────────────────────────────────
    
    function filterProducts() {
        var searchVal = posProductSearch.value.toLowerCase().trim();
        var activeTab = document.querySelector('.pos-category-tab.active');
        var catId = activeTab ? activeTab.getAttribute('data-category-id') : 'all';

        var visibleCount = 0;

        productContainers.forEach(function(container) {
            var name = container.getAttribute('data-name').toLowerCase();
            var sku = container.getAttribute('data-sku').toLowerCase();
            var pCatId = container.getAttribute('data-category-id');

            var matchesSearch = (searchVal === '' || name.indexOf(searchVal) !== -1 || sku.indexOf(searchVal) !== -1);
            var matchesCategory = (catId === 'all' || pCatId === catId);

            if (matchesSearch && matchesCategory) {
                container.style.display = 'block';
                visibleCount++;
            } else {
                container.style.display = 'none';
            }
        });

        document.getElementById('pos-grid-count').innerText = 'Total Products: ' + visibleCount;
    }

    posProductSearch.addEventListener('input', filterProducts);

    categoryTabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            categoryTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            filterProducts();
        });
    });

    // ── Checkout Checkout Actions ────────────────────────────────────────────
    
    function getCheckoutPayload() {
        var ids = Object.keys(cart);
        var itemsPayload = [];
        ids.forEach(function(id) {
            itemsPayload.push({
                product_id: parseInt(id),
                qty: cart[id].qty,
                rate: cart[id].rate
            });
        });

        return {
            client_id: document.getElementById('client_id').value,
            discount_type: calcDiscountType.value,
            discount_value: parseFloat(calcDiscountVal.value) || 0,
            shipping: parseFloat(calcShipping.value) || 0,
            items: itemsPayload
        };
    }

    function runCheckout(payload) {
        $.post('<?= admin_url('pos/checkout') ?>', {
            client_id: payload.client_id,
            discount_type: payload.discount_type,
            discount_value: payload.discount_value,
            shipping: payload.shipping,
            payment_method: payload.payment_method,
            payment_ref: payload.payment_ref,
            items: JSON.stringify(payload.items)
        }, function(response) {
            var data = JSON.parse(response);
            if (data.success) {
                alert_float('success', 'POS Checkout completed successfully!');
                
                // Clear cart & variables
                cart = {};
                renderCart();
                calcDiscountVal.value = 0;
                calcShipping.value = 0;

                // Automatically open invoice details for printing
                window.open('<?= admin_url('invoices/list_invoices/') ?>' + data.pos_sale_id, '_blank');
                
                $('#payment-details-modal').modal('hide');
            } else {
                alert_float('danger', 'Checkout failed: ' + data.error);
            }
        }).fail(function() {
            alert_float('danger', 'Network / server communication error occurred.');
        });
    }

    // Quick cash checkout
    btnQuickCash.addEventListener('click', function() {
        if (Object.keys(cart).length === 0) {
            alert_float('warning', 'Cart is empty.');
            return;
        }
        var payload = getCheckoutPayload();
        payload.payment_method = 'cash';
        payload.payment_ref = '';
        runCheckout(payload);
    });

    // More payment complete checkout
    document.getElementById('btn-process-checkout-more').addEventListener('click', function() {
        var payload = getCheckoutPayload();
        payload.payment_method = document.getElementById('payment_method').value;
        payload.payment_ref = document.getElementById('payment_ref').value;
        runCheckout(payload);
    });

    // ── Cart holds / Parking ─────────────────────────────────────────────────
    
    btnAddHold.addEventListener('click', function() {
        var ids = Object.keys(cart);
        if (ids.length === 0) {
            alert_float('warning', 'Cannot hold an empty cart.');
            return;
        }

        var note = prompt("Enter a brief hold note / customer name to hold this cart:");
        if (note === null) return; // Cancelled

        var payload = {
            client_id: document.getElementById('client_id').value,
            items: ids.map(id => ({
                product_id: parseInt(id),
                qty: cart[id].qty,
                rate: cart[id].rate,
                name: cart[id].name,
                sku: cart[id].sku,
                stock: cart[id].stock
            })),
            note: note
        };

        $.post('<?= admin_url('pos/add_hold') ?>', {
            client_id: payload.client_id,
            items: JSON.stringify(payload.items),
            note: payload.note
        }, function(response) {
            var data = JSON.parse(response);
            if (data.success) {
                alert_float('success', 'Cart held successfully.');
                cart = {};
                renderCart();
            } else {
                alert_float('danger', 'Failed to hold cart: ' + data.error);
            }
        });
    });

    btnListHold.addEventListener('click', function() {
        $.get('<?= admin_url('pos/get_holds') ?>', function(response) {
            var holds = JSON.parse(response);
            var tbody = document.querySelector('#holds-table tbody');
            tbody.innerHTML = '';

            if (holds.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No held carts found.</td></tr>';
            } else {
                holds.forEach(function(h) {
                    var row = '<tr>' +
                        '  <td>' + h.id + '</td>' +
                        '  <td>' + (h.customer_name || 'Walk-in Customer') + '</td>' +
                        '  <td>' + (h.hold_note || '-') + '</td>' +
                        '  <td>' + h.created_at + '</td>' +
                        '  <td>' +
                        '     <button class="btn btn-info btn-xs load-hold-btn" data-id="' + h.id + '">Restore</button>' +
                        '     <button class="btn btn-danger btn-xs delete-hold-btn mleft5" data-id="' + h.id + '">Delete</button>' +
                        '  </td>' +
                        '</tr>';
                    tbody.insertAdjacentHTML('beforeend', row);
                });

                // Attach hold restore & delete actions
                document.querySelectorAll('.load-hold-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        var id = this.getAttribute('data-id');
                        $.post('<?= admin_url('pos/load_hold') ?>', { hold_id: id }, function(res) {
                            var data = JSON.parse(res);
                            if (data.success) {
                                cart = {};
                                data.hold.items.forEach(function(item) {
                                    cart[item.product_id] = item;
                                });
                                renderCart();
                                $('#holds-list-modal').modal('hide');
                                alert_float('success', 'Cart restored successfully.');
                            } else {
                                alert_float('danger', data.error);
                            }
                        });
                    });
                });

                document.querySelectorAll('.delete-hold-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        var id = this.getAttribute('data-id');
                        if (confirm('Delete this held cart?')) {
                            $.post('<?= admin_url('pos/delete_hold') ?>', { hold_id: id }, function() {
                                btnListHold.click(); // Reload list
                                alert_float('success', 'Held cart deleted.');
                            });
                        }
                    });
                });
            }

            $('#holds-list-modal').modal('show');
        });
    });

    // ── sales history ────────────────────────────────────────────────────────
    
    btnSaleList.addEventListener('click', function() {
        $.get('<?= admin_url('pos/get_sales') ?>', function(response) {
            var sales = JSON.parse(response);
            var tbody = document.querySelector('#sales-table tbody');
            tbody.innerHTML = '';

            if (sales.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No sales completed in this session yet.</td></tr>';
            } else {
                sales.forEach(function(s) {
                    var inv_num = s.prefix + s.number;
                    var row = '<tr>' +
                        '  <td>' + inv_num + '</td>' +
                        '  <td>' + (s.customer_name || 'Walk-in Customer') + '</td>' +
                        '  <td><span class="label label-info">' + s.payment_method + '</span></td>' +
                        '  <td>' + parseFloat(s.invoice_total).toFixed(2) + ' BDT</td>' +
                        '  <td>' + s.created_at + '</td>' +
                        '  <td>' +
                        '     <a href="<?= admin_url('invoices/list_invoices/') ?>' + s.invoice_id + '" class="btn btn-default btn-xs" target="_blank"><i class="fa fa-eye"></i> View</a>' +
                        '  </td>' +
                        '</tr>';
                    tbody.insertAdjacentHTML('beforeend', row);
                });
            }

            $('#sales-list-modal').modal('show');
        });
    });

    // ── Pending Payment Action ───────────────────────────────────────────────
    
    if (btnPendingPayment) {
        btnPendingPayment.addEventListener('click', function() {
            if (Object.keys(cart).length === 0) {
                alert_float('warning', 'Cart is empty.');
                return;
            }
            var payload = getCheckoutPayload();
            payload.payment_method = 'pending_payment';
            payload.payment_ref = '';
            runCheckout(payload);
        });
    }

    // Clear cart
    btnClearCart.addEventListener('click', function() {
        if (confirm('Are you sure you want to clear the cart?')) {
            cart = {};
            renderCart();
            calcDiscountVal.value = 0;
            calcShipping.value = 0;
        }
    });

    // Quick Customer save callback
    document.getElementById('btn-save-customer').addEventListener('click', function() {
        var company = document.getElementById('m_company').value.trim();
        var phone = document.getElementById('m_phone').value.trim();
        var email = document.getElementById('m_email').value.trim();
        var modalAlert = document.getElementById('customer-modal-alert');
        
        if (company === '' || phone === '') {
            modalAlert.style.display = 'block';
            modalAlert.innerText = 'Customer name and phone number are required.';
            return;
        }

        modalAlert.style.display = 'none';
        
        $.post('<?= admin_url('pos/quick_customer') ?>', {
            company: company,
            phone: phone,
            email: email
        }, function(response) {
            var data = JSON.parse(response);
            if (data.success) {
                var select = $('#client_id');
                var opt = $('<option>', {
                    value: data.client_id,
                    text: company
                });
                if (phone || email) {
                    var subtext = phone;
                    if (phone && email) subtext += ' | ' + email;
                    else if (email) subtext = email;
                    opt.attr('data-subtext', subtext);
                }
                select.append(opt);
                select.selectpicker('refresh');
                select.selectpicker('val', data.client_id);
                $('#customer-quick-modal').modal('hide');
                alert_float('success', 'Customer added!');
            } else {
                modalAlert.style.display = 'block';
                modalAlert.innerText = data.error;
            }
        });
    });

    // ── Floating Calculator Logic ────────────────────────────────────────────
    var calcScreen = document.getElementById('calc-screen');
    var currentInput = '';
    
    document.querySelectorAll('.calc-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var val = this.getAttribute('data-val');
            
            if (val === 'C') {
                currentInput = '';
                calcScreen.value = '';
            } else if (val === 'DEL') {
                currentInput = currentInput.slice(0, -1);
                calcScreen.value = currentInput;
            } else if (val === '=') {
                try {
                    if (currentInput.trim() !== '') {
                        // Safe evaluation of basic mathematical operations
                        var cleanInput = currentInput.replace(/[^0-9+\-*/.]/g, '');
                        var result = Function('"use strict";return (' + cleanInput + ')')();
                        calcScreen.value = result;
                        currentInput = result.toString();
                    }
                } catch (e) {
                    calcScreen.value = 'Error';
                    currentInput = '';
                }
            } else {
                currentInput += val;
                calcScreen.value = currentInput;
            }
        });
    });
    // ── Edit Customer Logic ──────────────────────────────────────────────────
    window.openEditCustomerModal = function() {
        var clientId = $('#client_id').val();
        var walkinId = '<?= get_option('pos_default_walkin_client_id') ?>';
        if (!clientId || clientId == walkinId) {
            alert_float('warning', 'Please select a customer first to edit. Walk-in Customer cannot be edited.');
            return;
        }

        // Fetch customer details via AJAX
        $.getJSON('<?= admin_url('pos/get_customer_ajax') ?>/' + clientId, function(res) {
            if (res.success) {
                var c = res.customer;
                document.getElementById('edit_m_id').value = c.userid;
                document.getElementById('edit_m_company').value = c.company || '';
                document.getElementById('edit_m_phone').value = c.contact_phone || (c.client_phone || '');
                document.getElementById('edit_m_email').value = c.contact_email || '';
                
                document.getElementById('customer-edit-modal-alert').style.display = 'none';
                $('#customer-edit-modal').modal('show');
            } else {
                alert_float('danger', res.error || 'Failed to fetch customer details.');
            }
        });
    };

    $('#btn-update-customer').on('click', function() {
        var id = document.getElementById('edit_m_id').value;
        var company = document.getElementById('edit_m_company').value.trim();
        var phone = document.getElementById('edit_m_phone').value.trim();
        var email = document.getElementById('edit_m_email').value.trim();
        var modalAlert = document.getElementById('customer-edit-modal-alert');

        if (company === '' || phone === '') {
            modalAlert.style.display = 'block';
            modalAlert.innerText = 'Name and phone are required.';
            return;
        }

        modalAlert.style.display = 'none';
        
        $.post('<?= admin_url('pos/update_customer_ajax') ?>', {
            id: id,
            company: company,
            phone: phone,
            email: email
        }, function(response) {
            var data = JSON.parse(response);
            if (data.success) {
                // Update selectpicker option text and data-subtext
                var select = $('#client_id');
                var opt = select.find('option[value="' + id + '"]');
                
                var subtext = phone;
                if (phone && email) subtext += ' | ' + email;
                else if (email) subtext = email;
                
                if (opt.length > 0) {
                    opt.text(company);
                    opt.attr('data-subtext', subtext);
                } else {
                    // Fallback
                    var newOpt = $('<option>', {
                        value: id,
                        text: company
                    }).attr('data-subtext', subtext);
                    select.append(newOpt);
                }
                
                select.selectpicker('refresh');
                select.selectpicker('val', id);
                
                $('#customer-edit-modal').modal('hide');
                alert_float('success', 'Customer updated successfully!');
            } else {
                modalAlert.style.display = 'block';
                modalAlert.innerText = data.error;
            }
        });

    // Auto-fullscreen on first user interaction click
    $(document).one('click', function() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(err => {
                console.log(`Auto fullscreen request failed: ${err.message}`);
            });
        }
    });
});

function toggleFullScreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(err => {
            alert(`Error attempting to enable full-screen mode: ${err.message}`);
        });
    } else {
        document.exitFullscreen();
    }
}
</script>
