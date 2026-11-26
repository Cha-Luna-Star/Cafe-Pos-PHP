<?php
require_once __DIR__ . '/includes/auth_session.php';
startAuthSession();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/config/database.php';

$stmt = $connection->query('EXEC dbo.GetOrderHistory');

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

function escape($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cafe POS | Order History</title>

    <link rel="stylesheet" href="assets/css/table_sort.css">

    <link rel="stylesheet" href="assets/css/orders.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">

    <meta name="csrf-token" content="<?= escape($_SESSION['csrf_token']) ?>">

    <script src="assets/js/table_sort.js" defer></script>

    <script src="assets/js/orders.js" defer></script>
    <link rel="stylesheet" href="assets/css/ui.css">
</head>
<body class="table-page">

<?php
$appPageTitle = 'Order History';
$appHeaderBack = ['dashboard.php', 'Back to Dashboard'];
require __DIR__ . '/includes/sidebar.php';
?>

<main class="ui-page">
    <p id="order-update-notice" role="status" hidden></p>

    <div class="order-filters">
        <input class="ui-field"
            type="search"
            id="order-search" aria-label="Search orders"
            placeholder="Search order # or cashier"
        >

        <select class="ui-field" id="status-filter" aria-label="Order status">
            <option value="">All Statuses</option>
            <option value="Pending">Pending</option>
            <option value="Completed">Completed</option>
            <option value="Cancelled">Cancelled</option>
        </select>

        <select class="ui-field" id="payment-filter" aria-label="Payment method">
            <option value="">All Payments</option>
            <option value="Cash">Cash</option>
            <option value="Card">Card</option>
            <option value="E-Wallet">E-Wallet</option>
        </select>

        <input class="ui-field" type="date" id="date-filter" aria-label="Order date">

        <button class="ui-button" type="button" id="clear-filters">
            Clear
        </button>
    </div>

    <div class="ui-panel orders-table-wrapper table-scroll-panel">

        <table class="orders-table" id="orders-table">

            <thead>
                <tr>
                    <th><button type="button" class="sort-header" data-type="number"><span class="sort-label">Order #</span> <span class="sort-indicator" aria-hidden="true">⇅</span></button></th>
                    <th aria-sort="descending"><button type="button" class="sort-header" data-type="date" data-direction="desc"><span class="sort-label">Date</span> <span class="sort-indicator" aria-hidden="true">Newest</span></button></th>
                    <th><button type="button" class="sort-header" data-type="text"><span class="sort-label">Cashier</span> <span class="sort-indicator" aria-hidden="true">A–Z</span></button></th>
                    <th><button type="button" class="sort-header" data-type="text"><span class="sort-label">Order Type</span> <span class="sort-indicator" aria-hidden="true">A–Z</span></button></th>
                    <th><button type="button" class="sort-header" data-type="text"><span class="sort-label">Payment</span> <span class="sort-indicator" aria-hidden="true">A–Z</span></button></th>
                    <th><button type="button" class="sort-header" data-type="number"><span class="sort-label">Total</span> <span class="sort-indicator" aria-hidden="true">⇅</span></button></th>
                    <th><button type="button" class="sort-header" data-type="text"><span class="sort-label">Status</span> <span class="sort-indicator" aria-hidden="true">A–Z</span></button></th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($orders as $order): ?>

                    <?php
                    $status = (string)$order['status'];

                    $createdAt = $order['created_at'];

                    if ($createdAt instanceof DateTimeInterface) {
                        $dateValue = $createdAt->format('Y-m-d H:i:s');
                    } else {
                        $dateValue = (string)$createdAt;
                    }

                    $timestamp = strtotime($dateValue);

                    $displayDate = $timestamp !== false
                        ? date('M d, Y h:i A', $timestamp)
                        : $dateValue;

                    $filterDate = $timestamp !== false
                        ? date('Y-m-d', $timestamp)
                        : '';
                    ?>

                    <tr data-date="<?= escape($filterDate) ?>">

                        <td>
                            <?= (int)$order['id'] ?>
                        </td>

                        <td data-date="<?= escape($filterDate) ?>"
                            data-timestamp="<?= $timestamp !== false ? (int)$timestamp : 0 ?>">
                            <?= escape($displayDate) ?>
                        </td>

                        <td title="<?= escape($order['username'] ?? 'Unknown') ?>">
                            <?= escape($order['username'] ?? 'Unknown') ?>
                        </td>

                        <td>
                            <?= escape($order['order_type']) ?>
                        </td>

                        <td>
                            <?= escape($order['payment_method'] ?? 'N/A') ?>
                        </td>

                        <td>
                            ₱<?= number_format((float)$order['total'], 2) ?>
                        </td>

                        <td>
                            <span class="ui-badge status-badge" data-tone="<?= match ($status) {
                                'Completed' => 'positive', 'Cancelled' => 'danger', 'Pending' => 'warning', default => '',
                            } ?>">
                                <?= escape($status) ?>
                            </span>
                        </td>

                        <td class="order-actions">

                            <div class="order-action-group">

                                <a data-tone="outline" class="ui-button ui-button-sm" href="order_details.php?id=<?= (int)$order['id'] ?>">
                                    View
                                </a>

                                <?php if ($status === 'Pending'): ?>

                                    <button data-tone="positive"
                                        type="button"
                                        class="ui-button ui-button-sm complete-btn"
                                        data-order="<?= (int)$order['id'] ?>"
                                        data-action="Completed"
                                    >
                                        Complete
                                    </button>

                                    <button data-tone="danger"
                                        type="button"
                                        class="ui-button ui-button-sm cancel-btn"
                                        data-order="<?= (int)$order['id'] ?>"
                                        data-action="Cancelled"
                                    >
                                        Cancel
                                    </button>

                                <?php endif; ?>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

                <tr id="orders-empty" <?= $orders ? 'hidden' : '' ?>>
                    <td colspan="8"><?= $orders ? 'No orders match your filters.' : 'No orders yet.' ?></td>
                </tr>

            </tbody>

        </table>

    </div>

</main>

<dialog class="ui-dialog" id="status-dialog" aria-labelledby="status-dialog-title">

    <h2 id="status-dialog-title">Confirm Order Action</h2>

    <p id="status-confirm-message"></p>

    <p id="status-error" role="alert"></p>

    <div class="status-dialog-actions">

        <button class="ui-button"
            type="button"
            id="close-status-dialog"
        >
            Back
        </button>

        <button data-tone="positive" class="ui-button"
            type="button"
            id="confirm-status-action"
        >
            Confirm
        </button>

    </div>

</dialog>

</body>
</html>