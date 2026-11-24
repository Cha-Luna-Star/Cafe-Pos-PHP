<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!in_array($_SESSION['role'] ?? '', ['Admin', 'Manager'], true)) {
    http_response_code(403);
    exit('Permission denied.');
}

require_once __DIR__ . '/../includes/inventory_dates.php';

$today = inventoryToday();
$startValue = $_GET['from'] ?? $today->modify('-6 days')->format('Y-m-d');
$endValue = $_GET['to'] ?? $today->format('Y-m-d');
$start = is_string($startValue) ? parseInventoryDate($startValue) : null;
$end = is_string($endValue) ? parseInventoryDate($endValue) : null;
$reportError = null;
$dailySales = [];
$paymentSales = [];
$bestSellingItems = [];
$completedOrders = 0;
$totalSales = '0.00';

if (!$start || !$end || $start > $end) {
    http_response_code(400);
    $reportError = 'Enter a valid start and end date. The end date must be on or after the start date.';
    return;
}

try {
    $throwDatabaseConnectionError = true;
    require_once __DIR__ . '/../config/database.php';

    $reportDates = [
        $start->format('Y-m-d') . 'T00:00:00',
        $end->format('Y-m-d') . 'T23:59:59.9999999'
    ];
    $completedInRange = "o.status = 'Completed'
        AND o.created_at >= CONVERT(datetime2(7), ?, 126)
        AND o.created_at <= CONVERT(datetime2(7), ?, 126)";

    $stmt = $connection->prepare("
        WITH completed_sales AS (
            SELECT
                CAST(created_at AS date) AS sale_date,
                COALESCE(NULLIF(TRIM(payment_method), ''), 'Unspecified') AS payment_method,
                CAST(total AS decimal(19,2)) AS total
            FROM dbo.orders o
            WHERE $completedInRange
        )
        SELECT
            CONVERT(char(10), sale_date, 23) AS sale_date,
            payment_method,
            COUNT_BIG(*) AS order_count,
            COALESCE(SUM(total), 0) AS sales
        FROM completed_sales
        GROUP BY GROUPING SETS ((), (sale_date), (payment_method))
        ORDER BY sale_date DESC, sales DESC, payment_method
    ");
    $stmt->execute($reportDates);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ($row['sale_date'] !== null) {
            $dailySales[] = $row;
        } elseif ($row['payment_method'] !== null) {
            $paymentSales[] = $row;
        } else {
            $totalSales = $row['sales'];
            $completedOrders = (int)$row['order_count'];
        }
    }

    $stmt->closeCursor();
    $stmt = $connection->prepare("
        WITH item_totals AS (
            SELECT oi.menu_item_id, SUM(CAST(oi.quantity AS bigint)) AS units_sold
            FROM dbo.order_items oi
            JOIN dbo.orders o ON o.id = oi.order_id
            WHERE $completedInRange
            GROUP BY oi.menu_item_id
        )
        SELECT TOP (10)
            COALESCE(m.name, CONCAT('Item #', t.menu_item_id)) AS item_name,
            t.units_sold
        FROM item_totals t
        LEFT JOIN dbo.menu_items m ON m.id = t.menu_item_id
        ORDER BY t.units_sold DESC, item_name, t.menu_item_id
    ");
    $stmt->execute($reportDates);
    $bestSellingItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Sales report failed: ' . $e->getMessage());
    http_response_code(500);
    $reportError = 'Unable to load the sales report. Please try again.';
}
