<?php
require_once __DIR__ . '/../includes/auth_session.php';
startAuthSession();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!in_array($_SESSION['role'] ?? '', ['Admin', 'Manager'], true)) {
    http_response_code(403);
    exit('Permission denied.');
}

require_once __DIR__ . '/../includes/inventory_dates.php';
require_once __DIR__ . '/../includes/sales_items.php';

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
    $stmt = $connection->prepare("EXEC dbo.GetSalesReportTotals @From = ?, @To = ?");
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
    $bestSellingItems = bestSellingMenuItems($connection, $start->format('Y-m-d'), $end->format('Y-m-d'));
} catch (Throwable $e) {
    error_log('Sales report failed: ' . $e->getMessage());
    http_response_code(500);
    $reportError = 'Unable to load the sales report. Please try again.';
}
