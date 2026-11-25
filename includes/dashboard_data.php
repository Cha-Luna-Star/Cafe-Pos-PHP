<?php

if (!isset($canViewSales)) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/inventory_dates.php';
require_once __DIR__ . '/../includes/product_stock.php';
require_once __DIR__ . '/../includes/sales_items.php';

$dashboardError = null;
$today = inventoryToday();
$todayDate = $today->format('Y-m-d');
$tomorrowDate = $today->modify('+1 day')->format('Y-m-d');
$rangeLabels = ['daily' => 'Daily', '7-days' => 'Weekly', '1-month' => 'Monthly', 'all-time' => 'All Time'];
$requestedRange = $_GET['range'] ?? '';
$requestedDate = $_GET['date'] ?? '';
$selectedDate = is_string($requestedDate) ? parseInventoryDate($requestedDate) : null;
$rangeError = null;
$range = is_string($requestedRange) && isset($rangeLabels[$requestedRange]) ? $requestedRange : '7-days';
if ($requestedRange === 'date') {
    if ($selectedDate && $selectedDate <= $today) {
        $range = 'date';
    } else {
        $rangeError = 'Choose a valid date on or before today.';
    }
}

$isMonthly = $range === 'all-time';
$isHourly = in_array($range, ['daily', 'date'], true);
$chartEnd = $range === 'date' ? $selectedDate : $today;
$chartEndDate = $chartEnd->format('Y-m-d');
$chartStop = $chartEnd->modify('+1 day');
$chartStart = match ($range) {
    'all-time' => null,
    'date', 'daily' => $chartEnd,
    '1-month' => $today->modify('-29 days'),
    default => $today->modify('-6 days')
};
$chartPeriods = [];
$dashboardBestSellers = [];
$statusColumns = ['Completed' => 'completed', 'Pending' => 'pending', 'Cancelled' => 'cancelled'];
$statusCounts = array_fill_keys(array_keys($statusColumns), 0);
$statusSelect = implode(', ', array_map(
    static fn(string $status, string $column): string => "COUNT_BIG(CASE WHEN status = '$status' THEN 1 END) AS $column",
    array_keys($statusColumns),
    $statusColumns
));

try {
    $throwDatabaseConnectionError = true;
    require_once __DIR__ . '/../config/database.php';

    $stmt = $connection->prepare("
        SELECT COUNT_BIG(*) AS orders, $statusSelect,
            SUM(CASE WHEN status = 'Completed' THEN CAST(COALESCE(total, 0) AS decimal(19,2)) ELSE 0 END) AS sales
        FROM dbo.orders
        WHERE created_at >= CONVERT(date, ?, 23) AND created_at < CONVERT(date, ?, 23)
    ");
    $stmt->execute([$todayDate, $tomorrowDate]);
    $totals = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    $todaySales = $totals['sales'] ?? '0.00';
    $todayOrders = (int)$totals['orders'];
    $completedToday = (int)$totals['completed'];
    $averageOrder = $completedToday > 0 ? (float)$todaySales / $completedToday : null;
    foreach ($statusColumns as $label => $column) {
        $statusCounts[$label] = (int)$totals[$column];
    }

    if ($canViewSales) {
        $periodExpression = match ($range) {
            'date', 'daily' => 'DATEPART(hour, created_at)',
            'all-time' => 'CONVERT(char(7), created_at, 23)',
            default => 'CONVERT(char(10), created_at, 23)'
        };
        $startCondition = $isMonthly ? '' : 'AND created_at >= CONVERT(date, ?, 23)';
        $stmt = $connection->prepare("
            SELECT $periodExpression AS period, COUNT_BIG(*) AS orders, $statusSelect,
                SUM(CASE WHEN status = 'Completed' THEN CAST(COALESCE(total, 0) AS decimal(19,2)) ELSE 0 END) AS sales
            FROM dbo.orders
            WHERE created_at < CONVERT(date, ?, 23) $startCondition
            GROUP BY $periodExpression
            ORDER BY period
        ");
        $params = [$chartStop->format('Y-m-d')];
        if (!$isMonthly) {
            $params[] = $chartStart->format('Y-m-d');
        }
        $stmt->execute($params);
        $periodTotals = $stmt->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        foreach ($statusColumns as $label => $column) {
            $statusCounts[$label] = array_sum(array_column($periodTotals, $column));
        }

        if ($isMonthly && $periodTotals) {
            $chartStart = parseInventoryDate(array_key_first($periodTotals) . '-01');
        }

        $step = $isHourly ? '+1 hour' : ($isMonthly ? '+1 month' : '+1 day');
        $periodFormat = $isHourly ? 'G' : ($isMonthly ? 'Y-m' : 'Y-m-d');
        $labelFormat = $isHourly ? 'g A' : ($isMonthly ? 'M Y' : 'M j');
        for ($date = $chartStart; $date !== null && $date < $chartStop; $date = $date->modify($step)) {
            $period = $date->format($periodFormat);
            $chartPeriods[] = [
                'label' => $date->format($labelFormat),
                'sales' => (float)($periodTotals[$period]['sales'] ?? 0),
                'orders' => (int)($periodTotals[$period]['orders'] ?? 0)
            ];
        }
    }

    if ($canViewSales && $chartStart !== null) {
        $dashboardBestSellers = bestSellingMenuItems($connection, $chartStart->format('Y-m-d'), $chartEndDate);
    }

    $recentOrders = $connection->query("
        SELECT TOP (6) id, status, total, payment_method, CONVERT(char(16), created_at, 120) AS placed_at
        FROM dbo.orders
        ORDER BY created_at DESC, id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $pendingOrders = $connection->query("
        SELECT TOP (3) id, total, COUNT_BIG(*) OVER () AS total_pending
        FROM dbo.orders
        WHERE status = 'Pending'
        ORDER BY created_at ASC, id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    $pendingCount = (int)($pendingOrders[0]['total_pending'] ?? 0);

    $productThreshold = hasProductLowStockThreshold($connection)
        ? 'low_stock_threshold'
        : DEFAULT_PRODUCT_LOW_STOCK_THRESHOLD;
    $stock = $connection->query("
        WITH active_stock AS (
            SELECT COALESCE(stock_qty, 0) AS stock_qty, low_stock_threshold AS threshold
            FROM dbo.ingredients WHERE is_archived = 0
            UNION ALL
            SELECT COALESCE(stock_qty, 0), $productThreshold
            FROM dbo.menu_items WHERE is_archived = 0
        )
        SELECT
            COUNT_BIG(CASE WHEN stock_qty > 0 AND stock_qty <= threshold THEN 1 END) AS low_items,
            COUNT_BIG(CASE WHEN stock_qty <= 0 THEN 1 END) AS out_of_stock
        FROM active_stock
    ")->fetch(PDO::FETCH_ASSOC);

    $expirationGroups = $connection->query("
        SELECT expiration_date, COUNT_BIG(*) AS batch_count
        FROM (
            SELECT expiration_date FROM dbo.inventory_batches WHERE quantity > 0 AND expiration_date IS NOT NULL
            UNION ALL
            SELECT CONVERT(nvarchar(30), expiration_date, 23) FROM dbo.product_batches
            WHERE quantity > 0 AND expiration_date IS NOT NULL
        ) AS remaining_batches
        GROUP BY expiration_date
    ");
    $expiredCount = $expiringSoonCount = 0;
    while ($batch = $expirationGroups->fetch(PDO::FETCH_ASSOC)) {
        $expirationStatus = batchExpirationStatus($batch['expiration_date'], $today);
        $expiredCount += $expirationStatus === 'expired' ? (int)$batch['batch_count'] : 0;
        $expiringSoonCount += $expirationStatus === 'expiring-soon' ? (int)$batch['batch_count'] : 0;
    }
    $expirationGroups->closeCursor();
} catch (Throwable $e) {
    error_log('Dashboard failed: ' . $e->getMessage());
    http_response_code(500);
    $dashboardError = 'Unable to load the dashboard. Please try again.';
}
