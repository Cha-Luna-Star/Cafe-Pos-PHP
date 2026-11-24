<?php
require __DIR__ . '/controllers/sales_reports.php';

$salesMoney = static fn($value): string => '&#8369;' . number_format((float)$value, 2);
$salesEscape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$averageSale = $completedOrders > 0 ? (float)$totalSales / $completedOrders : 0;

if ($reportError === null && ($_GET['export'] ?? null) === 'csv') {
    require __DIR__ . '/includes/sales_csv.php';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cafe POS | Sales Reports</title>
    <link rel="stylesheet" href="assets/css/reports.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
</head>
<body>

<?php require __DIR__ . '/includes/sidebar.php'; ?>

<header class="report-header">
    <h1>Sales Reports</h1>
</header>

<main class="report-content">
    <form method="get" class="report-filters">
        <div>
            <label for="report-from">From</label>
            <input type="date" id="report-from" name="from" value="<?= $salesEscape($start?->format('Y-m-d') ?? '') ?>" required>
        </div>
        <div>
            <label for="report-to">To</label>
            <input type="date" id="report-to" name="to" value="<?= $salesEscape($end?->format('Y-m-d') ?? '') ?>" required>
        </div>
        <button type="submit">Apply</button>
        <button type="submit" name="export" value="csv">Export CSV</button>
        <a href="sales_reports.php">Reset</a>
    </form>

    <?php if ($reportError !== null): ?>
        <p class="report-error" role="alert"><?= $salesEscape($reportError) ?></p>
    <?php else: ?>
        <p class="report-note">Sales include completed orders by the date the order was placed.</p>

        <dl class="report-summary">
            <div><dt>Total Sales</dt><dd><?= $salesMoney($totalSales) ?></dd></div>
            <div><dt>Completed Orders</dt><dd><?= number_format($completedOrders) ?></dd></div>
            <div><dt>Average Order</dt><dd><?= $salesMoney($averageSale) ?></dd></div>
        </dl>

        <?php if ($completedOrders === 0): ?>
            <p class="report-empty">No completed orders in this date range.</p>
        <?php else: ?>
            <div class="report-breakdowns">
                <section class="report-section">
                    <h2>Daily Sales</h2>
                    <div class="report-table">
                        <table aria-label="Daily Sales">
                            <thead><tr><th scope="col">Date</th><th scope="col">Orders</th><th scope="col">Sales</th></tr></thead>
                            <tbody>
                                <?php foreach ($dailySales as $row): ?>
                                    <tr>
                                        <th scope="row"><?= $salesEscape($row['sale_date']) ?></th>
                                        <td><?= number_format((int)$row['order_count']) ?></td>
                                        <td><?= $salesMoney($row['sales']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                <section class="report-section">
                    <h2>Sales by Payment Method</h2>
                    <div class="report-table">
                        <table aria-label="Sales by Payment Method">
                            <thead><tr><th scope="col">Payment</th><th scope="col">Orders</th><th scope="col">Sales</th></tr></thead>
                            <tbody>
                                <?php foreach ($paymentSales as $row): ?>
                                    <tr>
                                        <th scope="row"><?= $salesEscape($row['payment_method']) ?></th>
                                        <td><?= number_format((int)$row['order_count']) ?></td>
                                        <td><?= $salesMoney($row['sales']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

</body>
</html>
