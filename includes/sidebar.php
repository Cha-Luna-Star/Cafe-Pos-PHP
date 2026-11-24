<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? 'Cashier';
?>

<aside class="sidebar">
    <h2>Cafe POS</h2>

    <nav>
        <a href="dashboard.php" class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
            Dashboard
        </a>

        <a href="pos.php" class="<?= $currentPage === 'pos.php' ? 'active' : '' ?>">
            New Order
        </a>

        <a href="orders.php" class="<?= in_array($currentPage, ['orders.php', 'order_details.php']) ? 'active' : '' ?>">
            Order History
        </a>

        <a href="menu_management.php" class="<?= $currentPage === 'menu_management.php' ? 'active' : '' ?>">Menu Management</a>
        <a href="inventory.php" class="<?= $currentPage === 'inventory.php' ? 'active' : '' ?>">Inventory</a>
        <?php if (in_array($role, ['Admin', 'Manager'], true)): ?>
            <a href="sales_reports.php" class="<?= $currentPage === 'sales_reports.php' ? 'active' : '' ?>">Sales Reports</a>
            <a href="#">User Management</a>
        <?php endif; ?>
    </nav>

    <a href="logout.php" class="logout">Logout</a>
</aside>