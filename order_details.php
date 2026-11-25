<?php
require_once __DIR__ . '/includes/auth_session.php';
startAuthSession();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/config/database.php';

$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$orderId || $orderId <= 0) {
    http_response_code(400);
    exit("Invalid order ID.");
}

$stmt = $connection->prepare("
    SELECT
        o.id,
        o.order_type,
        o.status,
        o.total,
        o.created_at,
        o.payment_method,
        o.amount_tendered,
        o.change_amount,
        o.tax,
        o.ref_number,
        u.username
    FROM dbo.orders o
    LEFT JOIN dbo.users u ON o.created_by = u.id
    WHERE o.id = ?
");

$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    http_response_code(404);
    exit("Order not found.");
}

$stmt = $connection->prepare("
    SELECT
        oi.quantity,
        oi.size,
        oi.subtotal,
        m.name
    FROM dbo.order_items oi
    LEFT JOIN dbo.menu_items m ON oi.menu_item_id = m.id
    WHERE oi.order_id = ?
    ORDER BY oi.id
");

$stmt->execute([$orderId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$money = fn($value) => '₱' . number_format((float)$value, 2);

$total = (float)$order['total'];
$tax = (float)$order['tax'];
$paid = (float)$order['amount_tendered'];
$change = (float)$order['change_amount'];

$itemsTotal = array_sum(
    array_map(
        fn($item) => (float)$item['subtotal'],
        $items
    )
);

$discount = max(0, $itemsTotal - $total);

$date = $order['created_at'] instanceof DateTimeInterface
    ? $order['created_at']->format('Y-m-d h:i A')
    : date('Y-m-d h:i A', strtotime((string)$order['created_at']));

function escape($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?= (int)$order['id'] ?> | Cafe POS</title>
    <link rel="stylesheet" href="assets/css/order_details.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/ui.css">
</head>
<body>
<?php
$appPageTitle = 'Order Details';
$appHeaderBack = ['orders.php', 'Back to Orders'];
require __DIR__ . '/includes/sidebar.php';
?>

<main class="ui-page">
    <section class="ui-panel receipt">
        <h2>Cafe POS</h2>
        <p class="receipt-subtitle">Order Receipt</p>

        <hr>

        <p>Order #: <?= (int)$order['id'] ?></p>
        <p>Date: <?= escape($date) ?></p>
        <p>Payment: <?= escape($order['payment_method'] ?? 'N/A') ?></p>

        <hr>

        <div class="receipt-items">
            <?php foreach ($items as $item): ?>
                <p>
                    <?= (int)$item['quantity'] ?>x
                    <?= escape($item['name'] ?? 'Unknown Item') ?>

                    <?php if (!empty($item['size'])): ?>
                        (<?= escape($item['size']) ?>)
                    <?php endif; ?>

                    - <?= $money($item['subtotal']) ?>
                </p>
            <?php endforeach; ?>
        </div>

        <hr>

        <p>Subtotal: <?= $money($itemsTotal) ?></p>
        <p>Discount: <?= $money($discount) ?></p>
        <p>VAT Included: <?= $money($tax) ?></p>

        <h3>Total: <?= $money($total) ?></h3>

        <p>Amount Paid: <?= $money($paid) ?></p>
        <p>Change: <?= $money($change) ?></p>

        <div class="receipt-actions">
            <button data-tone="outline" class="ui-button" type="button" onclick="window.print()">Print</button>
            <a class="ui-button" href="orders.php">Back</a>
        </div>
    </section>
</main>
</body>
</html>