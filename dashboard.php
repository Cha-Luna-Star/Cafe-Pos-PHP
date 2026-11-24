<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'Cashier';
$canViewSales = in_array($role, ['Admin', 'Manager'], true);
require __DIR__ . '/includes/dashboard_data.php';

$escapeDashboard = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$dashboardMoney = static fn($value): string => '&#8369;' . number_format((float)$value, 2);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cafe POS | Dashboard</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <script src="assets/js/dashboard.js" defer></script>
</head>
<body>

<?php require __DIR__ . '/includes/sidebar.php'; ?>

<main class="main-content">
    <header>
        <div>
            <h1>Dashboard</h1>
            <p>Welcome, <?= htmlspecialchars($username) ?>.</p>
        </div>

        <div class="user-info">
            <?= htmlspecialchars($role) ?>
        </div>
    </header>



    <div class="dashboard-actions">
        <a class="dashboard-primary" href="pos.php">New Order</a>
        <a href="orders.php">Order History</a>
        <a href="inventory.php">Inventory</a>
    </div>

    <?php if ($dashboardError !== null): ?>
        <p class="dashboard-error" role="alert"><?= $escapeDashboard($dashboardError) ?></p>
    <?php else: ?>
        <section class="stats" aria-label="Summary">
            <div class="stat-card"><p>Today's Sales</p><h2><?= $dashboardMoney($todaySales) ?></h2></div>
            <div class="stat-card"><p>Today's Orders</p><h2><?= number_format($todayOrders) ?></h2></div>
            <div class="stat-card"><p>Active Menu Items</p><h2><?= number_format((int)$stock['menu_count']) ?></h2></div>
            <div class="stat-card"><p>Low Stock Ingredients</p><h2><?= number_format((int)$stock['low_ingredients']) ?></h2></div>
        </section>

        <div class="dashboard-panels">
            <section class="dashboard-panel" aria-labelledby="attention-heading">
                <h2 id="attention-heading">Needs Attention</h2>
                <ul class="dashboard-alerts">
                    <li><a href="orders.php?status=Pending">Pending orders <strong><?= number_format($pendingCount) ?></strong></a></li>
                    <li><a href="inventory.php?filter=out-of-stock">Out-of-stock items <strong><?= number_format((int)$stock['out_of_stock']) ?></strong></a></li>
                    <li><a href="inventory.php?filter=low-stock">Low-stock items <strong><?= number_format((int)$stock['low_items']) ?></strong></a></li>
                    <li><a href="inventory.php?filter=expired">Expired batches with stock <strong><?= number_format($expiredCount) ?></strong></a></li>
                </ul>
                <?php if ($pendingOrders): ?>
                    <h3>Oldest Pending Orders</h3>
                    <ul class="dashboard-alerts pending-orders">
                        <?php foreach ($pendingOrders as $order): ?>
                            <li><a href="order_details.php?id=<?= (int)$order['id'] ?>">Order #<?= (int)$order['id'] ?> <strong><?= $dashboardMoney($order['total']) ?></strong></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="dashboard-panel" aria-labelledby="recent-heading">
                <h2 id="recent-heading">Recent Orders</h2>
                <?php if (!$recentOrders): ?>
                    <p>No orders yet.</p>
                <?php else: ?>
                    <div class="dashboard-table">
                        <table>
                            <thead><tr><th scope="col">Order</th><th scope="col">Placed</th><th scope="col">Status</th><th scope="col">Total</th></tr></thead>
                            <tbody>
                                <?php foreach ($recentOrders as $order): ?>
                                    <tr>
                                        <th scope="row"><a href="order_details.php?id=<?= (int)$order['id'] ?>">#<?= (int)$order['id'] ?></a></th>
                                        <td><?= $escapeDashboard($order['placed_at'] ?? 'Unavailable') ?></td>
                                        <td><?= $escapeDashboard($order['status']) ?></td>
                                        <td><?= $dashboardMoney($order['total']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <?php if ($canViewSales): ?>
                <section class="dashboard-panel dashboard-sales" aria-labelledby="trend-heading">
                    <div class="dashboard-panel-heading">
                        <h2 id="trend-heading">Sales and Order Trends</h2>
                        <?php if ($chartStart !== null): ?>
                            <a href="sales_reports.php?from=<?= $chartStart->format('Y-m-d') ?>&amp;to=<?= $todayDate ?>">Open Sales Reports</a>
                        <?php endif; ?>
                    </div>

                    <nav class="dashboard-ranges" aria-label="Trend date range">
                        <?php foreach ($rangeLabels as $value => $label): ?>
                            <a href="dashboard.php?range=<?= $value ?>"
                                <?= $range === $value ? 'aria-current="page"' : '' ?>><?= $label ?></a>
                        <?php endforeach; ?>
                    </nav>

                    <?php if (!$chartPeriods): ?>
                        <p>No orders to chart yet.</p>
                    <?php else: ?>
                        <script type="application/json" id="dashboard-chart-data"><?= json_encode(
                            $chartPeriods,
                            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                        ) ?></script>
                        <div class="dashboard-charts">
                            <section aria-labelledby="chart-sales">
                                <h3 id="chart-sales">Sales Trend</h3>
                                <p class="dashboard-chart-caption">Completed sales in PHP</p>
                                <canvas class="dashboard-chart" data-trend="sales" aria-hidden="true"></canvas>
                            </section>
                            <section aria-labelledby="chart-orders">
                                <h3 id="chart-orders">Orders Placed</h3>
                                <p class="dashboard-chart-caption">All order statuses</p>
                                <canvas class="dashboard-chart" data-trend="orders" aria-hidden="true"></canvas>
                            </section>
                        </div>
                        <details class="dashboard-chart-data-view">
                            <summary>View sales and order totals</summary>
                            <div class="dashboard-table">
                                <table>
                                    <thead><tr><th scope="col">Period</th><th scope="col">Completed Sales</th><th scope="col">Orders Placed</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($chartPeriods as $period): ?>
                                            <tr>
                                                <th scope="row"><?= $escapeDashboard($period['label']) ?></th>
                                                <td><?= $dashboardMoney($period['sales']) ?></td>
                                                <td><?= number_format($period['orders']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </details>
                        <section class="dashboard-best-selling" aria-labelledby="chart-best-selling">
                            <h3 id="chart-best-selling">Most-Sold Menu Items</h3>
                            <p class="dashboard-chart-caption">Units sold on completed orders</p>
                            <?php if (!$dashboardBestSellers): ?>
                                <p>No completed item sales in this range.</p>
                            <?php else: ?>
                                <?php $topUnits = max(1, max(array_column($dashboardBestSellers, 'units_sold'))); ?>
                                <dl class="dashboard-chart-values">
                                    <?php foreach ($dashboardBestSellers as $item): ?>
                                        <?php $width = max(0, (int)$item['units_sold']) / $topUnits * 100; ?>
                                        <div class="dashboard-chart-row">
                                            <dt><?= $escapeDashboard($item['item_name']) ?></dt>
                                            <dd class="dashboard-chart-track" aria-hidden="true"><span style="width: <?= number_format($width, 2, '.', '') ?>%"></span></dd>
                                            <dd><?= number_format((int)$item['units_sold']) ?></dd>
                                        </div>
                                    <?php endforeach; ?>
                                </dl>
                            <?php endif; ?>
                        </section>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>

</body>
</html>
