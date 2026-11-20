<?php
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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
    <script src="assets/js/orders.js" defer></script>
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<?php require __DIR__ . '/includes/sidebar.php'; ?>

<header>
    <h1>Order History</h1>
    <a href="dashboard.php">Back to Dashboard</a>
</header>

<main>
    <div class="orders-table-wrapper">
        <table class="orders-table">
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
                    <td>
                        <span class="status-badge <?= strtolower(htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8')) ?>">
                            <?= htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td class="order-actions">
                        <div class="order-action-group">
                            <a href="order_details.php?id=<?= (int)$order['id'] ?>">View</a>

                            <?php if ($order['status'] === 'Pending'): ?>
                                <button type="button"
                                        class="complete-btn"
                                        data-order="<?= (int)$order['id'] ?>"
                                        data-action="Completed">
                                    Complete
                                </button>

                                <button type="button"
                                        class="cancel-btn"
                                        data-order="<?= (int)$order['id'] ?>"
                                        data-action="Cancelled">
                                    Cancel
                                </button>
                            <?php endif; ?>
                        </div>
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
    </div>
</main>

<dialog id="status-dialog">
    <h2>Confirm Order Action</h2>
    <p id="status-confirm-message"></p>
    <p id="status-error" role="alert"></p>

    <div class="status-dialog-actions">
        <button type="button" id="close-status-dialog">Back</button>
        <button type="button" id="confirm-status-action">Confirm</button>
    </div>
</dialog>

</body>
</html>