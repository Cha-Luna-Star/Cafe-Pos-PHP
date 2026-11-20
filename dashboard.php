
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/config/database.php';

$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'Cashier';

try {
    $salesQuery = $connection->query("
        SELECT COALESCE(SUM(total), 0)
        FROM dbo.orders
        WHERE [status] = 'Completed'
        AND CAST(created_at AS DATE) = CAST(GETDATE() AS DATE)
    ");
    $todaySales = $salesQuery->fetchColumn();

    $ordersQuery = $connection->query("
        SELECT COUNT(*)
        FROM dbo.orders
        WHERE CAST(created_at AS DATE) = CAST(GETDATE() AS DATE)
    ");
    $todayOrders = $ordersQuery->fetchColumn();

    $menuQuery = $connection->query("
        SELECT COUNT(*)
        FROM dbo.menu_items
        WHERE is_archived = 0
    ");
    $menuCount = $menuQuery->fetchColumn();

    $stockQuery = $connection->query("
        SELECT COUNT(*)
        FROM dbo.ingredients
        WHERE stock_qty <= low_stock_threshold
    ");
    $lowStockCount = $stockQuery->fetchColumn();

} catch (PDOException $e) {
    die("Dashboard error: " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cafe POS | Dashboard</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>

<div class="sidebar">
    <h2>Cafe POS</h2>

    <nav>
        <a href="dashboard.php" class="active">Dashboard</a>
        <a href="pos.php">New Order</a>
        <a href="orders.php">Orders</a>
        <a href="#">Menu Management</a>
        <a href="#">Inventory</a>
        <a href="#">Sales Reports</a>

        <?php if ($role === 'Admin' || $role === 'Manager'): ?>
            <a href="#">User Management</a>
        <?php endif; ?>
    </nav>

    <a href="logout.php" class="logout">Logout</a>
</div>

<div class="main-content">
    <header>
        <div>
            <h1>Dashboard</h1>
            <p>Welcome, <?= htmlspecialchars($username) ?>.</p>
        </div>

        <div class="user-info">
            <?= htmlspecialchars($role) ?>
        </div>
    </header>

    <section class="stats">
        <div class="stat-card">
            <p>Today's Sales</p>
            <h2>₱<?= number_format((float)$todaySales, 2) ?></h2>
        </div>

        <div class="stat-card">
            <p>Today's Orders</p>
            <h2><?= number_format((int)$todayOrders) ?></h2>
        </div>

        <div class="stat-card">
            <p>Active Menu Items</p>
            <h2><?= number_format((int)$menuCount) ?></h2>
        </div>

        <div class="stat-card">
            <p>Low Stock Ingredients</p>
            <h2><?= number_format((int)$lowStockCount) ?></h2>
        </div>
    </section>
</div>

</body>
</html>
