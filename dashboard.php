<?php
require_once __DIR__ . '/includes/auth_session.php';
startAuthSession();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

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
    <link rel="stylesheet" href="assets/css/ui.css">
</head>
<body class="dashboard-page">

<?php
$appPageTitle = 'Dashboard';
$appHeaderDate = $today;
require __DIR__ . '/includes/sidebar.php';
?>

<main class="ui-page dashboard">
    <nav class="dashboard-actions" aria-label="Quick actions">
        <a data-tone="positive" class="ui-button" href="pos.php"><?php $navIcon($navLinks['pos.php'][1]); ?> New Order</a>
        <?php if ($canViewSales): ?>
            <a data-tone="positive" class="ui-button" href="inventory.php?action=stock-in"><?php $navIcon($navLinks['inventory.php'][1]); ?> Stock In</a>
        <?php endif; ?>
        <a data-tone="info" class="ui-button" href="menu_management.php"><?php $navIcon($navLinks['menu_management.php'][1]); ?> Menu Management</a>
        <a data-tone="outline" class="ui-button" href="orders.php"><?php $navIcon($navLinks['orders.php'][1]); ?> Order History</a>
    </nav>

    <?php if ($dashboardError !== null): ?>
        <p class="ui-panel dashboard-empty" role="alert"><?= $escapeDashboard($dashboardError) ?></p>
    <?php else: ?>
        <?php
        $todayOrdersUrl = 'orders.php?date=' . $todayDate;
        $todayCompletedUrl = $todayOrdersUrl . '&status=Completed';
        $todaySalesUrl = $canViewSales ? 'sales_reports.php?from=' . $todayDate . '&to=' . $todayDate : $todayCompletedUrl;
        $cards = [
            ["Today's Sales", $dashboardMoney($todaySales), 'Completed orders', $todaySalesUrl, 'sales'],
            ['Total Orders', number_format($todayOrders), 'All orders today', $todayOrdersUrl, 'orders'],
            ['Average Order Value', $averageOrder === null ? '&mdash;' : $dashboardMoney($averageOrder),
                $averageOrder === null ? 'No completed orders today' : 'Completed orders today', $todayCompletedUrl, 'average'],
            ['Low Stock Items', number_format((int)$stock['low_items']), 'Products and ingredients', 'inventory.php?filter=low-stock', 'stock'],
        ];
        $cardIcons = [
            'sales' => '<path d="M3 6h18v12H3zM6 12h.01M18 12h.01"/><circle cx="12" cy="12" r="2.5"/>',
            'orders' => $navLinks['orders.php'][1],
            'average' => '<path d="M4 19V5m0 14h16M8 14l4-5 3 3 5-6"/>',
            'stock' => $navLinks['inventory.php'][1],
        ];
        $statusTones = ['Completed' => 'positive', 'Pending' => 'warning', 'Cancelled' => 'danger'];
        $statusTotal = array_sum($statusCounts);
        ?>
        <section class="dashboard-stats" aria-label="Today at a glance">
            <?php foreach ($cards as [$label, $value, $hint, $url, $icon]): ?>
                <a class="ui-panel dashboard-stat" href="<?= $escapeDashboard($url) ?>">
                    <span class="dashboard-stat-icon" data-icon="<?= $icon ?>"><?php $navIcon($cardIcons[$icon]); ?></span>
                    <span class="dashboard-stat-label"><?= $escapeDashboard($label) ?></span>
                    <strong><?= $value ?></strong>
                    <small><?= $escapeDashboard($hint) ?></small>
                </a>
            <?php endforeach; ?>
        </section>

        <div class="dashboard-grid">
            <?php if ($canViewSales): ?>
                <section class="ui-panel dashboard-panel dashboard-trends" aria-labelledby="trend-heading">
                    <div class="dashboard-heading">
                        <div>
                            <h2 id="trend-heading">Sales and order trends</h2>
                            <p>Completed sales in PHP and orders placed</p>
                        </div>
                        <?php if ($chartStart !== null): ?>
                            <a class="ui-button ui-button-sm" data-tone="outline" href="sales_reports.php?from=<?= $chartStart->format('Y-m-d') ?>&amp;to=<?= $chartEndDate ?>">Sales Reports</a>
                        <?php endif; ?>
                    </div>
                    <nav class="dashboard-ranges" aria-label="Sales range">
                        <?php foreach ($rangeLabels as $key => $label): ?>
                            <a class="ui-choice" data-dashboard-range href="dashboard.php?range=<?= $key ?>"
                                <?= $range === $key ? 'aria-current="page"' : '' ?>><?= $label ?></a>
                        <?php endforeach; ?>
                    </nav>
                    <form method="get" action="dashboard.php" class="dashboard-date" data-dashboard-date>
                        <input type="hidden" name="range" value="date">
                        <label for="trend-date">Specific date</label>
                        <input class="ui-field" type="date" id="trend-date" name="date"
                            value="<?= $chartEndDate ?>" max="<?= $todayDate ?>" required>
                        <button class="ui-button" data-tone="positive" type="submit">Apply</button>
                    </form>
                    <p class="dashboard-loading" role="status" hidden>Loading dashboard data?</p>
                    <?php if ($rangeError !== null): ?>
                        <p class="dashboard-error" role="alert"><?= $escapeDashboard($rangeError) ?></p>
                    <?php endif; ?>

                    <?php if (!array_sum(array_column($chartPeriods, 'orders'))): ?>
                        <p class="dashboard-empty">No orders in this period.</p>
                    <?php else: ?>
                        <script type="application/json" id="dashboard-chart-data"><?= json_encode($chartPeriods, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
                        <section class="dashboard-chart-section" aria-label="Completed sales">
                            <canvas class="dashboard-chart" data-trend="sales" tabindex="0" aria-label="Sales trend. Use the left and right arrow keys to inspect a period." aria-describedby="dashboard-chart-value"></canvas>
                            <p class="dashboard-chart-value" id="dashboard-chart-value">Select a point to see its sales total.</p>
                        </section>
                        <details class="dashboard-chart-details">
                            <summary>Orders placed chart</summary>
                            <canvas class="dashboard-chart" data-trend="orders" aria-hidden="true"></canvas>
                        </details>
                        <details class="dashboard-chart-details">
                            <summary>View chart data</summary>
                            <div class="dashboard-table">
                                <table>
                                    <thead><tr><th scope="col">Period</th><th scope="col">Sales</th><th scope="col">Orders</th></tr></thead>
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
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="ui-panel dashboard-panel" aria-labelledby="status-heading">
                <div class="dashboard-heading">
                    <div>
                        <h2 id="status-heading">Order status</h2>
                        <p><?= $canViewSales ? $escapeDashboard($rangeLabels[$range] ?? $chartEnd->format('M j, Y')) : 'Today' ?></p>
                    </div>
                </div>
                <?php if ($statusTotal > 0): ?>
                    <?php $completedEnd = 100 * $statusCounts['Completed'] / $statusTotal;
                    $pendingEnd = $completedEnd + 100 * $statusCounts['Pending'] / $statusTotal; ?>
                    <div class="dashboard-doughnut" style="--completed-end: <?= number_format($completedEnd, 4, '.', '') ?>%; --pending-end: <?= number_format($pendingEnd, 4, '.', '') ?>%;" aria-hidden="true">
                        <div><strong><?= number_format($statusTotal) ?></strong><span>Orders</span></div>
                    </div>
                    <ul class="dashboard-list dashboard-status-list" aria-label="<?= number_format($statusTotal) ?> orders by status">
                        <?php foreach ($statusCounts as $status => $count): ?>
                            <li><span class="dashboard-status-dot" data-tone="<?= $statusTones[$status] ?>"></span><?= $status ?><strong><?= number_format($count) ?></strong></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="dashboard-empty">No orders to break down yet.</p>
                <?php endif; ?>
            </section>

            <section class="ui-panel dashboard-panel" aria-labelledby="recent-heading">
                <div class="dashboard-heading">
                    <h2 id="recent-heading">Recent transactions</h2>
                    <a class="ui-button ui-button-sm" data-tone="outline" href="orders.php">View Orders</a>
                </div>
                <?php if (!$recentOrders): ?>
                    <p class="dashboard-empty">No orders yet.</p>
                <?php else: ?>
                    <div class="dashboard-table" role="region" aria-label="Recent transactions table" tabindex="0">
                        <table>
                            <thead><tr><th scope="col">Order</th><th scope="col">Placed</th><th scope="col">Payment</th><th scope="col">Status</th><th scope="col">Total</th></tr></thead>
                            <tbody>
                                <?php foreach ($recentOrders as $order): ?>
                                    <tr>
                                        <th scope="row"><a href="order_details.php?id=<?= (int)$order['id'] ?>">#<?= (int)$order['id'] ?></a></th>
                                        <td><?= $escapeDashboard($order['placed_at'] ?? 'Unavailable') ?></td>
                                        <td><?= $escapeDashboard($order['payment_method'] ?? 'Pending') ?></td>
                                        <td><span class="ui-badge" data-tone="<?= $statusTones[$order['status']] ?? '' ?>"><?= $escapeDashboard($order['status']) ?></span></td>
                                        <td><?= $dashboardMoney($order['total']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="ui-panel dashboard-panel" aria-labelledby="alerts-heading">
                <div class="dashboard-heading"><h2 id="alerts-heading">Inventory alerts</h2></div>
                <ul class="dashboard-list">
                    <?php foreach ([
                        ['low-stock', 'Low-stock items', $stock['low_items'], 'warning'],
                        ['out-of-stock', 'Out-of-stock items', $stock['out_of_stock'], 'danger'],
                        ['expiring-soon', 'Batches expiring within 7 days', $expiringSoonCount, 'warning'],
                        ['expired', 'Expired batches with stock', $expiredCount, 'danger'],
                    ] as [$filter, $label, $count, $tone]): ?>
                        <li><a href="inventory.php?filter=<?= $filter ?>"><?= $label ?><strong class="ui-badge" data-tone="<?= $count ? $tone : '' ?>"><?= number_format((int)$count) ?></strong></a></li>
                    <?php endforeach; ?>
                </ul>
                <div class="dashboard-pending">
                    <h3><a href="orders.php?status=Pending">Pending orders (<?= number_format($pendingCount) ?>)</a></h3>
                    <?php if ($pendingOrders): ?>
                        <ul class="dashboard-list">
                            <?php foreach ($pendingOrders as $order): ?>
                                <li><a href="order_details.php?id=<?= (int)$order['id'] ?>">Order #<?= (int)$order['id'] ?><strong><?= $dashboardMoney($order['total']) ?></strong></a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($canViewSales): ?>
                <section class="ui-panel dashboard-panel dashboard-best-sellers" aria-labelledby="best-heading">
                    <div class="dashboard-heading">
                        <div>
                            <h2 id="best-heading">Best-selling products</h2>
                            <p>Completed orders in the selected period ? Item revenue before order-level discounts</p>
                        </div>
                    </div>
                    <?php if (!$dashboardBestSellers): ?>
                        <p class="dashboard-empty">No completed product sales in this period.</p>
                    <?php else: ?>
                        <?php $topQuantity = max(1, (int)$dashboardBestSellers[0]['units_sold']); ?>
                        <div class="dashboard-table" role="region" aria-label="Best-selling products table" tabindex="0">
                            <table>
                                <thead><tr><th scope="col">Product</th><th scope="col">Quantity sold</th><th scope="col">Revenue</th></tr></thead>
                                <tbody>
                                    <?php foreach ($dashboardBestSellers as $item): ?>
                                        <tr>
                                            <th scope="row"><?= $escapeDashboard($item['item_name']) ?></th>
                                            <td>
                                                <div class="dashboard-quantity">
                                                    <span><?= number_format((int)$item['units_sold']) ?></span>
                                                    <span class="dashboard-quantity-track" aria-hidden="true">
                                                        <span style="width: <?= number_format(100 * (int)$item['units_sold'] / $topQuantity, 2, '.', '') ?>%"></span>
                                                    </span>
                                                </div>
                                            </td>
                                            <td><?= $dashboardMoney($item['revenue']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
