<?php

if (!isset($canViewSales)) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/inventory_dates.php';
require_once __DIR__ . '/../includes/product_stock.php';

$dashboardError = null;
$today = inventoryToday();
$todayDate = $today->format('Y-m-d');
$weekStart = $today->modify('-6 days');
$weeklySales = [];

try {
    $throwDatabaseConnectionError = true;
    require_once __DIR__ . '/../config/database.php';

    $stmt = $connection->prepare("
        SELECT CONVERT(char(10), created_at, 23) AS order_date, COUNT_BIG(*) AS orders,
            SUM(CASE WHEN status = 'Completed' THEN CAST(COALESCE(total, 0) AS decimal(19,2)) ELSE 0 END) AS sales
        FROM dbo.orders
        WHERE created_at >= CONVERT(date, ?, 23) AND created_at < CONVERT(date, ?, 23)
        GROUP BY CONVERT(char(10), created_at, 23)
    ");
    $stmt->execute([
        ($canViewSales ? $weekStart : $today)->format('Y-m-d'),
        $today->modify('+1 day')->format('Y-m-d')
    ]);
    $dailyTotals = $stmt->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    $todaySales = $dailyTotals[$todayDate]['sales'] ?? '0.00';
    $todayOrders = (int)($dailyTotals[$todayDate]['orders'] ?? 0);
    if ($canViewSales) {
        for ($day = $weekStart; $day <= $today; $day = $day->modify('+1 day')) {
            $date = $day->format('Y-m-d');
            $weeklySales[] = ['label' => $day->format('D, M j'), 'sales' => $dailyTotals[$date]['sales'] ?? '0.00'];
        }
    }

    $recentOrders = $connection->query("
        SELECT TOP (6) id, status, total, CONVERT(char(16), created_at, 120) AS placed_at
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
            SELECT 'Ingredient' AS item_type, COALESCE(stock_qty, 0) AS stock_qty, low_stock_threshold AS threshold
            FROM dbo.ingredients WHERE is_archived = 0
            UNION ALL
            SELECT 'Product', COALESCE(stock_qty, 0), $productThreshold
            FROM dbo.menu_items WHERE is_archived = 0
        )
        SELECT
            COUNT_BIG(CASE WHEN item_type = 'Product' THEN 1 END) AS menu_count,
            COUNT_BIG(CASE WHEN item_type = 'Ingredient' AND stock_qty > 0 AND stock_qty <= threshold THEN 1 END) AS low_ingredients,
            COUNT_BIG(CASE WHEN stock_qty > 0 AND stock_qty <= threshold THEN 1 END) AS low_items,
            COUNT_BIG(CASE WHEN stock_qty <= 0 THEN 1 END) AS out_of_stock
        FROM active_stock
    ")->fetch(PDO::FETCH_ASSOC);

    $expirationDates = $connection->query("
        SELECT expiration_date FROM dbo.inventory_batches WHERE quantity > 0 AND expiration_date IS NOT NULL
        UNION ALL
        SELECT CONVERT(nvarchar(30), expiration_date, 23) FROM dbo.product_batches
        WHERE quantity > 0 AND expiration_date IS NOT NULL
    ")->fetchAll(PDO::FETCH_COLUMN);
    $expiredCount = 0;
    foreach ($expirationDates as $expirationDate) {
        $expiredCount += (int)(batchExpirationStatus($expirationDate, $today) === 'expired');
    }
} catch (Throwable $e) {
    error_log('Dashboard failed: ' . $e->getMessage());
    http_response_code(500);
    $dashboardError = 'Unable to load the dashboard. Please try again.';
}
