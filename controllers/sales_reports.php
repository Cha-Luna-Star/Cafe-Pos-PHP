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

    $stmt = $connection->prepare("
        WITH completed_sales AS (
            SELECT
                CAST(created_at AS date) AS sale_date,
                COALESCE(NULLIF(TRIM(payment_method), ''), 'Unspecified') AS payment_method,
                CAST(total AS decimal(19,2)) AS total
            FROM dbo.orders
            WHERE status = 'Completed'
                AND created_at >= CONVERT(datetime2(7), ?, 126)
                AND created_at <= CONVERT(datetime2(7), ?, 126)
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
    $stmt->execute([
        $start->format('Y-m-d') . 'T00:00:00',
        $end->format('Y-m-d') . 'T23:59:59.9999999'
    ]);

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
} catch (Throwable $e) {
    error_log('Sales report failed: ' . $e->getMessage());
    http_response_code(500);
    $reportError = 'Unable to load the sales report. Please try again.';
}
