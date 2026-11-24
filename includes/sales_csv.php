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

fputcsv($output, ['Section', 'Date', 'Payment Method', 'Completed Orders', 'Sales (PHP)', 'Average Order (PHP)'], ',', '"', '', "\r\n");
fputcsv($output, ['Summary', '', '', $completedOrders, $totalSales, number_format($averageSale, 2, '.', '')], ',', '"', '', "\r\n");

foreach ($dailySales as $row) {
    fputcsv($output, ['Daily Sales', $row['sale_date'], '', $row['order_count'], $row['sales'], ''], ',', '"', '', "\r\n");
}

foreach ($paymentSales as $row) {
    $method = $row['payment_method'];
    if (preg_match('/\A[\s\x00-\x1f\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}\x{FEFF}]*[=+\-@＝＋－＠]/u', $method)) {
        $method = "'" . $method;
    }
    fputcsv($output, ['Payment Method', '', $method, $row['order_count'], $row['sales'], ''], ',', '"', '', "\r\n");
}

fclose($output);
