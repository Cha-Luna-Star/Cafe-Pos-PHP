<?php

if (!isset($start, $end)) {
    http_response_code(404);
    exit;
}

$from = $start->format('Y-m-d');
$to = $end->format('Y-m-d');

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="sales_' . $from . '_to_' . $to . '.csv"');
header('Cache-Control: no-store');

$output = fopen('php://output', 'wb');
fwrite($output, "\xEF\xBB\xBF");

$writeRow = static function (array $values) use ($output): void {
    fputcsv($output, $values, ',', '"', '', "\r\n");
};
$safeText = static function (string $value): string {
    return preg_match('/\A[\s\x00-\x1f\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}\x{FEFF}]*[=+\-@\x{FF1D}\x{FF0B}\x{FF0D}\x{FF20}]/u', $value)
        ? "'" . $value
        : $value;
};

$writeRow(['Section', 'Date', 'Payment Method', 'Completed Orders', 'Sales (PHP)', 'Average Order (PHP)', 'Item', 'Units Sold']);
$writeRow(['Summary', '', '', $completedOrders, $totalSales, number_format($averageSale, 2, '.', ''), '', '']);

foreach ($dailySales as $row) {
    $writeRow(['Daily Sales', $row['sale_date'], '', $row['order_count'], $row['sales'], '', '', '']);
}

foreach ($paymentSales as $row) {
    $writeRow(['Payment Method', '', $safeText($row['payment_method']), $row['order_count'], $row['sales'], '', '', '']);
}

foreach ($bestSellingItems as $row) {
    $writeRow(['Best-Selling Items', '', '', '', '', '', $safeText($row['item_name']), $row['units_sold']]);
}

fclose($output);
