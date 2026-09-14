<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>A4 Invoice - Order #<?= $order['id'] ?></title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333;
            margin: 0;
            padding: 40px;
            font-size: 14px;
            line-height: 1.5;
        }
        .invoice-box {
            max-width: 800px;
            margin: auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .company-details h1 {
            margin: 0 0 10px 0;
            font-size: 28px;
            color: #4f46e5;
            font-weight: 700;
        }
        .company-details p {
            margin: 2px 0;
            color: #64748b;
        }
        .invoice-title h2 {
            margin: 0 0 10px 0;
            font-size: 24px;
            font-weight: 700;
            text-align: right;
        }
        .invoice-title p {
            margin: 2px 0;
            text-align: right;
            color: #475569;
        }
        .details-grid {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
        }
        .bill-to, .ship-to {
            width: 48%;
        }
        .details-grid h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 5px;
        }
        .details-grid p {
            margin: 4px 0;
            font-size: 14px;
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .invoice-table th {
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #475569;
        }
        .invoice-table td {
            padding: 12px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }
        .totals-section {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 40px;
        }
        .totals-table {
            width: 250px;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 8px 12px;
            font-size: 14px;
        }
        .totals-table tr.grand-total {
            font-size: 18px;
            font-weight: bold;
            color: #4f46e5;
            border-top: 2px solid #e2e8f0;
        }
        .footer {
            text-align: center;
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
            color: #94a3b8;
            font-size: 12px;
        }
        @media print {
            body {
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-box">
        <div class="no-print" style="margin-bottom: 20px; text-align: right;">
            <button onclick="window.print();" style="padding: 8px 16px; background: #4f46e5; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">Print Invoice</button>
        </div>

        <div class="header">
            <div class="company-details">
                <h1><?= get_option('companyname') ?></h1>
                <p><?= get_option('invoice_company_address') ?></p>
            </div>
            <div class="invoice-title">
                <h2>INVOICE</h2>
                <p><strong>Order ID:</strong> #<?= $order['id'] ?></p>
                <p><strong>Date:</strong> <?= date('d M Y', strtotime($order['order_date'])) ?></p>
                <p><strong>Channel:</strong> <?= ucfirst($order['channel']) ?></p>
            </div>
        </div>

        <div class="details-grid">
            <div class="bill-to">
                <h3>Customer Details</h3>
                <p><strong>Name:</strong> <?= e($order['customer_name']) ?></p>
                <p><strong>Phone:</strong> <?= e($order['customer_phone']) ?></p>
                <?php if (!empty($order['customer_email'])): ?>
                    <p><strong>Email:</strong> <?= e($order['customer_email']) ?></p>
                <?php endif; ?>
            </div>
            <div class="ship-to">
                <h3>Shipping Details</h3>
                <p><strong>Address:</strong> <?= nl2br(e($order['customer_address'])) ?></p>
                <p><strong>Payment Method:</strong> <?= ucfirst(str_replace('_', ' ', $order['payment_method'])) ?></p>
            </div>
        </div>

        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Item Description</th>
                    <th>SKU</th>
                    <th style="text-align: right;">Rate</th>
                    <th style="text-align: center;">Qty</th>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= e($item['name']) ?></td>
                        <td><?= e($item['sku'] ?: '-') ?></td>
                        <td style="text-align: right;"><?= number_format($item['unit_price'], 2) ?> BDT</td>
                        <td style="text-align: center;"><?= number_format($item['qty'], 0) ?></td>
                        <td style="text-align: right;"><?= number_format($item['qty'] * $item['unit_price'], 2) ?> BDT</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td style="color: #64748b;">Subtotal:</td>
                    <td style="text-align: right; font-weight: 600;"><?= number_format($order['subtotal'], 2) ?> BDT</td>
                </tr>
                <tr>
                    <td style="color: #64748b;">Shipping:</td>
                    <td style="text-align: right; font-weight: 600;"><?= number_format($order['shipping_charge'], 2) ?> BDT</td>
                </tr>
                <tr class="grand-total">
                    <td>Total:</td>
                    <td style="text-align: right;"><?= number_format($order['total'], 2) ?> BDT</td>
                </tr>
            </table>
        </div>

        <div class="footer">
            <p>Thank you for your business!</p>
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
