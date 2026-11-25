<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$navRole = $_SESSION['role'] ?? 'Cashier';
$navUsername = $_SESSION['username'] ?? 'User';
$navEscape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$navLinks = [
    'dashboard.php' => 'Dashboard',
    'pos.php' => 'New Order',
    'orders.php' => 'Order History',
    'menu_management.php' => 'Menu Management',
    'inventory.php' => 'Inventory',
];
if (in_array($navRole, ['Admin', 'Manager'], true)) {
    $navLinks += [
        'sales_reports.php' => 'Sales Reports',
        'audit_log.php' => 'Audit Log',
        'staff_accounts.php' => 'Staff Accounts',
    ];
}
$navActivePage = $currentPage === 'order_details.php' ? 'orders.php' : $currentPage;
?>

<div class="app-navigation">
    <header class="app-header">
        <div class="app-header-heading">
            <button type="button" id="sidebar-toggle" class="sidebar-toggle" aria-controls="app-sidebar" aria-expanded="true" aria-label="Hide sidebar" hidden>
                <span aria-hidden="true"></span>
            </button>
            <h1><?= $navEscape($appPageTitle) ?></h1>
        </div>
        <?php if (isset($appHeaderBack) || !empty($appHeaderActions)): ?>
            <div class="app-header-actions">
                <?php if (isset($appHeaderBack)): ?>
                    <a href="<?= $navEscape($appHeaderBack[0]) ?>"><?= $navEscape($appHeaderBack[1]) ?></a>
                <?php endif; ?>
                <?php foreach ($appHeaderActions ?? [] as $actionId => $label): ?>
                    <button type="button" id="<?= $navEscape($actionId) ?>"><?= $navEscape($label) ?></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="app-header-account">
            <strong><?= $navEscape($navUsername) ?></strong>
            <span><?= $navEscape($navRole) ?></span>
        </div>
    </header>

    <aside class="sidebar" id="app-sidebar">
        <h2>Cafe POS</h2>
        <nav aria-label="Main navigation">
            <?php foreach ($navLinks as $href => $label): ?>
                <?php $isActive = $href === $navActivePage; ?>
                <a href="<?= $navEscape($href) ?>"<?= $isActive ? ' class="active" aria-current="page"' : '' ?>>
                    <?= $navEscape($label) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <form action="logout.php" method="post" class="logout">
            <?php require_once __DIR__ . '/mutation_request.php'; ?>
            <input type="hidden" name="csrf_token" value="<?= $navEscape(sessionCsrfToken()) ?>">
            <button type="submit">Logout</button>
        </form>
    </aside>
</div>
<script src="assets/js/sidebar.js" defer></script>
