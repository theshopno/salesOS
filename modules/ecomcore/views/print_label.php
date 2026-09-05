<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Label 55mm - Order #<?= $order['id'] ?></title>
    <style>
        @page {
            size: 55mm auto;
            margin: 0;
        }
        body {
            width: 55mm;
            margin: 0;
            padding: 4mm;
            font-family: 'Courier New', Courier, monospace, Arial, sans-serif;
            font-size: 11px;
            line-height: 1.3;
            color: #000;
            background: #fff;
            box-sizing: border-box;
        }
        .label-container {
            width: 100%;
        }
        .header {
            text-align: center;
            border-bottom: 1px dashed #000;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }
        .header h1 {
            font-size: 14px;
            margin: 0;
            font-weight: bold;
        }
        .header p {
            margin: 2px 0 0 0;
            font-size: 10px;
        }
        .customer-section {
            margin-bottom: 6px;
            border-bottom: 1px dashed #000;
            padding-bottom: 4px;
        }
        .customer-section p {
            margin: 2px 0;
        }
        .customer-name {
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .customer-phone {
            font-size: 13px;
            font-weight: bold;
        }
        .items-section {
            margin-bottom: 6px;
            border-bottom: 1px dashed #000;
            padding-bottom: 4px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
        }
        .items-table td {
            padding: 1px 0;
            vertical-align: top;
        }
        .total-section {
            text-align: right;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 6px;
        }
        .footer {
            text-align: center;
            font-size: 9px;
        }
        .no-print {
            text-align: center;
            margin-bottom: 10px;
        }
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print();" style="width: 100%; padding: 4px; background: #000; color: #fff; border: none; font-size: 10px; font-weight: bold; cursor: pointer;">PRINT LABEL</button>
    </div>

    <div class="label-container">
        <div class="header">
            <h1><?= get_option('companyname') ?></h1>
            <p>Order: #<?= $order['id'] ?> (<?= ucfirst($order['channel']) ?>)</p>
            <p><?= date('d-m-Y H:i', strtotime($order['created_at'])) ?></p>
        </div>

        <div class="customer-section">
            <p class="customer-name"><?= e($order['customer_name']) ?></p>
            <p class="customer-phone">Tel: <?= e($order['customer_phone']) ?></p>
            <p class="customer-address">Add: <?= e($order['customer_address']) ?></p>
        </div>

        <div class="items-section">
            <table class="items-table">
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td style="width: 10%; font-weight: bold;"><?= number_format($item['qty'], 0) ?>x</td>
                        <td style="width: 90%;"><?= e($item['name']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="total-section">
            Total: <?= number_format($order['total'], 2) ?> BDT
        </div>

        <div class="footer">
            <p>Thank you for shopping!</p>
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
