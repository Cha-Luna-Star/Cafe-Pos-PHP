<?php

if (!isset($canViewSales)) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/inventory_dates.php';
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

try {
    $throwDatabaseConnectionError = true;
    require_once __DIR__ . '/../config/database.php';

    $stmt = $connection->prepare('EXEC dbo.GetDashboardToday ?, ?');
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
        $stmt = $connection->prepare('EXEC dbo.GetDashboardPeriods ?, ?, ?');
        $stmt->execute([
            $chartStop->format('Y-m-d'),
            $isMonthly ? null : $chartStart->format('Y-m-d'),
            $isHourly ? 'hour' : ($isMonthly ? 'month' : 'day'),
        ]);
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

    $recentOrders = $connection->query('EXEC dbo.GetDashboardRecentOrders')->fetchAll(PDO::FETCH_ASSOC);

    $pendingOrders = $connection->query('EXEC dbo.GetDashboardPendingOrders')->fetchAll(PDO::FETCH_ASSOC);
    $pendingCount = (int)($pendingOrders[0]['total_pending'] ?? 0);

    $stock = $connection->query('EXEC dbo.GetDashboardStock')->fetch(PDO::FETCH_ASSOC);

    $expirationGroups = $connection->query('EXEC dbo.GetDashboardExpirationGroups');
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
