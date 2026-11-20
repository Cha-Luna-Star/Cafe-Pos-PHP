
<?php
session_start();

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
</head>
<body>
<?php require __DIR__ . '/includes/sidebar.php'; ?>

<header class="page-header">
    <h1>Order Details</h1>
    <a href="orders.php">Back to Orders</a>
</header>

<main>
    <section class="receipt">
        <div class="receipt-header">
            <h2>Cafe POS</h2>
            <p>Order Receipt</p>
        </div>

        <hr>

        <div class="details">
            <p><strong>Order #:</strong> <?= (int)$order['id'] ?></p>
            <p><strong>Date:</strong> <?= escape($date) ?></p>
            <p><strong>Cashier:</strong> <?= escape($order['username'] ?? 'Unknown') ?></p>
            <p><strong>Order Type:</strong> <?= escape($order['order_type']) ?></p>
            <p><strong>Status:</strong> <?= escape($order['status']) ?></p>
            <p><strong>Payment:</strong> <?= escape($order['payment_method'] ?? 'N/A') ?></p>

            <?php if (!empty($order['ref_number'])): ?>
                <p><strong>Reference #:</strong> <?= escape($order['ref_number']) ?></p>
            <?php endif; ?>
        </div>

        <hr>

        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <?= escape($item['name'] ?? 'Unknown Item') ?>

                            <?php if (!empty($item['size'])): ?>
                                <small><?= escape($item['size']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= (int)$item['quantity'] ?></td>
                        <td><?= $money($item['subtotal']) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="3">No items found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <hr>

        <div class="totals">
            <p>
                <span>Subtotal</span>
                <span><?= $money($itemsTotal) ?></span>
            </p>

            <p>
                <span>Discount</span>
                <span><?= $money($discount) ?></span>
            </p>

            <p>
                <span>VAT Included</span>
                <span><?= $money($tax) ?></span>
            </p>

            <p class="grand-total">
                <strong>Total</strong>
                <strong><?= $money($total) ?></strong>
            </p>

            <p>
                <span>Amount Paid</span>
                <span><?= $money($paid) ?></span>
            </p>

            <p>
                <span>Change</span>
                <span><?= $money($change) ?></span>
            </p>
        </div>

        <div class="receipt-actions">
            <button type="button" onclick="window.print()">
                Print Receipt
            </button>

            <a href="orders.php">Back</a>
        </div>
    </section>
</main>

</body>
</html>
