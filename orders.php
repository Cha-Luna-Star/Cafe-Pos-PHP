<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/config/database.php';

$stmt = $connection->query("
    SELECT
        o.id,
        o.order_type,
        o.status,
        o.total,
        o.created_at,
        o.payment_method,
        u.username
    FROM dbo.orders o
    LEFT JOIN dbo.users u ON o.created_by = u.id
    ORDER BY o.created_at DESC, o.id DESC
");

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cafe POS | Order History</title>
    <link rel="stylesheet" href="assets/css/orders.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
</head>
<body>
<?php require __DIR__ . '/includes/sidebar.php'; ?>

<header>
    <h1>Order History</h1>
    <a href="dashboard.php">Back to Dashboard</a>
</header>

<main>
    <table>
        <thead>
            <tr>
                <th>Order #</th>
                <th>Date</th>
                <th>Cashier</th>
                <th>Order Type</th>
                <th>Payment</th>
                <th>Total</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?= (int)$order['id'] ?></td>
                    <td><?= htmlspecialchars($order['created_at']) ?></td>
                    <td><?= htmlspecialchars($order['username'] ?? 'Unknown') ?></td>
                    <td><?= htmlspecialchars($order['order_type']) ?></td>
                    <td><?= htmlspecialchars($order['payment_method'] ?? 'N/A') ?></td>
                    <td>₱<?= number_format((float)$order['total'], 2) ?></td>
                    <td><?= htmlspecialchars($order['status']) ?></td>
                    <td>
                        <a href="order_details.php?id=<?= (int)$order['id'] ?>">
                            View
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="8">No orders found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</main>

</body>
</html>