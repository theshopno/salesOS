<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Display - POS</title>
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #0f172a;
            color: #f1f5f9;
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        /* Premium Header */
        .display-header {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #6366f1;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
        }
        .display-logo {
            font-size: 24px;
            font-weight: 800;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 12px;
            letter-spacing: 0.5px;
        }
        .display-logo i {
            color: #38bdf8;
            animation: pulse 2s infinite;
        }
        .display-status {
            font-size: 14px;
            background: rgba(255, 255, 255, 0.15);
            padding: 8px 16px;
            border-radius: 30px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .status-dot {
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            box-shadow: 0 0 10px #10b981;
        }
        
        /* Main Body Grid */
        .display-body {
            flex-grow: 1;
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            height: calc(100vh - 80px);
        }
        
        /* Left Column: Welcome / Cart Items */
        .cart-side {
            padding: 30px 40px;
            background: #1e293b;
            display: flex;
            flex-direction: column;
            border-right: 1px solid #334155;
            overflow-y: auto;
        }
        .welcome-screen {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            gap: 20px;
            animation: fadeIn 0.8s ease-out;
        }
        .welcome-icon {
            font-size: 80px;
            color: #6366f1;
            background: rgba(99, 102, 241, 0.15);
            width: 160px;
            height: 160px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            border: 2px dashed #6366f1;
        }
        .welcome-screen h2 {
            font-size: 32px;
            font-weight: 800;
            color: #fff;
        }
        .welcome-screen p {
            font-size: 18px;
            color: #94a3b8;
            max-width: 500px;
        }
        
        /* Cart List */
        .items-list-container {
            display: none;
            flex-direction: column;
            gap: 15px;
            animation: fadeInUp 0.5s ease-out;
        }
        .cart-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 15px;
            border-left: 4px solid #6366f1;
            padding-left: 10px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
        }
        .items-table th {
            text-align: left;
            padding: 12px 15px;
            color: #94a3b8;
            font-weight: 600;
            font-size: 14px;
            border-bottom: 1px solid #334155;
        }
        .items-table td {
            padding: 16px 15px;
            border-bottom: 1px solid #334155;
            font-size: 16px;
            vertical-align: middle;
        }
        .item-qty-badge {
            background: #6366f1;
            color: #fff;
            padding: 4px 10px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 14px;
        }
        
        /* Right Column: Receipt Totals Panel */
        .summary-side {
            background: #0f172a;
            padding: 30px 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .receipt-card {
            background: #1e293b;
            border-radius: 12px;
            padding: 25px;
            border: 1px solid #334155;
            box-shadow: 0 4px 30px rgba(0,0,0,0.25);
        }
        .receipt-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px dashed #334155;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 15px;
            color: #94a3b8;
        }
        .summary-row strong {
            color: #f1f5f9;
        }
        
        /* Total Block */
        .payable-block {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px dashed #334155;
            text-align: center;
        }
        .payable-title {
            font-size: 16px;
            font-weight: 600;
            color: #94a3b8;
            margin-bottom: 8px;
        }
        .payable-value {
            font-size: 44px;
            font-weight: 800;
            color: #22c55e;
            text-shadow: 0 0 15px rgba(34, 197, 94, 0.2);
            animation: pricePulse 1s alternate infinite;
        }
        .customer-advertisement {
            text-align: center;
            color: #64748b;
            font-size: 13px;
            margin-top: 20px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 8px;
            border: 1px dashed #334155;
        }
        
        /* Animations */
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        @keyframes pricePulse {
            0% { transform: scale(1); }
            100% { transform: scale(1.02); }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

    <!-- Header bar -->
    <header class="display-header">
        <div class="display-logo">
            <i class="fas fa-shopping-basket"></i> Customer Billing Display
        </div>
        <div class="display-status">
            <span class="status-dot"></span> Live Counter Connected
        </div>
    </header>

    <!-- Main Workspace -->
    <div class="display-body">
        
        <!-- Left Section -->
        <div class="cart-side">
            
            <!-- Default Welcome Display -->
            <div class="welcome-screen" id="welcome-view">
                <div class="welcome-icon">
                    <i class="fas fa-cash-register"></i>
                </div>
                <h2>Welcome to Our Store!</h2>
                <p>Please wait while the cashier scans your items. Your real-time purchase list and totals will appear here.</p>
            </div>
            
            <!-- Active Purchase List -->
            <div class="items-list-container" id="cart-list-view">
                <h3 class="cart-title">Your Purchase Items</h3>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th width="55%">Product Description</th>
                            <th width="20%" class="text-center">Qty</th>
                            <th width="25%" class="text-right">Total Price</th>
                        </tr>
                    </thead>
                    <tbody id="cart-items-body">
                        <!-- Populated dynamically via BroadcastChannel -->
                    </tbody>
                </table>
            </div>

        </div>
        
        <!-- Right Section: Totals Summary -->
        <div class="summary-side">
            <div class="receipt-card">
                <div class="receipt-title">
                    <span>BILL SUMMARY</span>
                    <i class="fas fa-file-invoice-dollar" style="color: #6366f1;"></i>
                </div>
                
                <div class="summary-row">
                    <span>Subtotal:</span>
                    <strong id="summary-subtotal">0.00 BDT</strong>
                </div>
                <div class="summary-row">
                    <span>Discount:</span>
                    <strong id="summary-discount" style="color: #ef4444;">0.00 BDT</strong>
                </div>
                <div class="summary-row">
                    <span>Shipping:</span>
                    <strong id="summary-shipping">0.00 BDT</strong>
                </div>
                
                <div class="payable-block">
                    <div class="payable-title">TOTAL PAYABLE</div>
                    <div class="payable-value" id="summary-total">0.00 BDT</div>
                </div>
            </div>
            
            <!-- Bottom Advertisement message -->
            <div class="customer-advertisement">
                <i class="fas fa-heart" style="color: #ef4444; margin-bottom: 5px;"></i>
                <p class="bold" style="color: #cbd5e1; font-weight:600;">Thank you for shopping with us!</p>
                <p>We appreciate your support. Please check your receipt.</p>
            </div>
        </div>

    </div>

    <!-- Script to listen to BroadcastChannel -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var welcomeView = document.getElementById('welcome-view');
            var cartListView = document.getElementById('cart-list-view');
            var itemsBody = document.getElementById('cart-items-body');
            
            var summarySubtotal = document.getElementById('summary-subtotal');
            var summaryDiscount = document.getElementById('summary-discount');
            var summaryShipping = document.getElementById('summary-shipping');
            var summaryTotal = document.getElementById('summary-total');

            // Establish BroadcastChannel listener
            var channel = new BroadcastChannel('pos_cart_channel');

            channel.onmessage = function(event) {
                var data = event.data;
                
                if (!data || Object.keys(data.items).length === 0) {
                    // Show welcome display
                    welcomeView.style.display = 'flex';
                    cartListView.style.display = 'none';
                    
                    summarySubtotal.innerText = '0.00 BDT';
                    summaryDiscount.innerText = '0.00 BDT';
                    summaryShipping.innerText = '0.00 BDT';
                    summaryTotal.innerText = '0.00 BDT';
                    return;
                }

                // Show cart list
                welcomeView.style.display = 'none';
                cartListView.style.display = 'flex';

                // Populate items
                itemsBody.innerHTML = '';
                var ids = Object.keys(data.items);
                
                ids.forEach(function(id) {
                    var item = data.items[id];
                    var lineTotal = item.qty * item.rate;

                    var row = document.createElement('tr');
                    row.innerHTML = 
                        '<td>' +
                        '  <strong style="color: #fff;">' + item.name + '</strong><br>' +
                        '  <small style="color: #64748b;">SKU: ' + (item.sku || '-') + '</small>' +
                        '</td>' +
                        '<td class="text-center" style="text-align:center;">' +
                        '  <span class="item-qty-badge">' + item.qty + '</span>' +
                        '</td>' +
                        '<td style="text-align:right; font-weight:700; color: #38bdf8;">' +
                           number_format(lineTotal, 2) + ' BDT' +
                        '</td>';
                    itemsBody.appendChild(row);
                });

                // Calculate discount absolute value
                var discountTotal = 0;
                if (data.discount_type === 'percent') {
                    discountTotal = (data.subtotal * data.discount_val) / 100;
                } else {
                    discountTotal = data.discount_val;
                }

                // Update summary card
                summarySubtotal.innerText = number_format(data.subtotal, 2) + ' BDT';
                summaryDiscount.innerText = number_format(discountTotal, 2) + ' BDT';
                summaryShipping.innerText = number_format(data.shipping, 2) + ' BDT';
                summaryTotal.innerText = number_format(data.total, 2) + ' BDT';
            };
        });
    </script>
</body>
</html>
