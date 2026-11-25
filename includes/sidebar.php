<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$navRole = $_SESSION['role'] ?? 'Cashier';
$navUsername = $_SESSION['username'] ?? 'User';
$navEscape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$navLinks = [
    'dashboard.php' => ['Dashboard', '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>'],
    'pos.php' => ['New Order', '<path d="M3 4h2l2 11h12l2-8H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/>'],
    'orders.php' => ['Order History', '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'],
    'menu_management.php' => ['Menu Management', '<path d="M4 5h16v14H4zM8 9h8m-8 4h8"/>'],
    'inventory.php' => ['Inventory', '<path d="M3 7l9-4 9 4v10l-9 4-9-4zM3 7l9 4 9-4m-9 4v10"/>'],
];
if (in_array($navRole, ['Admin', 'Manager'], true)) {
    $navLinks += [
        'sales_reports.php' => ['Sales Reports', '<path d="M4 4v16h16M8 16v-4m5 4V8m5 8V5"/>'],
        'audit_log.php' => ['Audit Log', '<path d="M6 3h9l4 4v14H6zM9 11h7m-7 4h7"/>'],
        'staff_accounts.php' => ['Staff Accounts', '<circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M16 5a3 3 0 0 1 0 6m2 3a5 5 0 0 1 3 4v2"/>'],
    ];
}
$navIcon = static function (string $shape): void {
    ?>
    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><?= $shape ?></svg>
    <?php
};
$navActivePage = $currentPage === 'order_details.php' ? 'orders.php' : $currentPage;
?>

<div class="app-navigation">
    <header class="app-header">
        <div class="app-header-heading">
            <button type="button" id="sidebar-menu-toggle" class="sidebar-toggle sidebar-toggle-mobile" aria-controls="app-sidebar" aria-expanded="false" aria-label="Show sidebar" hidden>
                <span class="sidebar-menu-icon" aria-hidden="true"></span>
            </button>
            <h1>
                <?php $navIcon($navLinks[$navActivePage][1]); ?>
                <?= $navEscape($appPageTitle) ?>
            </h1>
        </div>
        <?php if (isset($appHeaderBack) || !empty($appHeaderActions)): ?>
            <div class="app-header-actions">
                <?php if (isset($appHeaderBack)): ?>
                    <a class="ui-button" href="<?= $navEscape($appHeaderBack[0]) ?>"><?= $navEscape($appHeaderBack[1]) ?></a>
                <?php endif; ?>
                <?php foreach ($appHeaderActions ?? [] as $actionId => $label): ?>
                    <button class="ui-button" data-tone="positive" type="button" id="<?= $navEscape($actionId) ?>"><?= $navEscape($label) ?></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="app-header-account">
            <strong><?= $navEscape($navUsername) ?></strong>
            <span><?= $navEscape($navRole) ?></span>
        </div>
    </header>

    <aside class="sidebar" id="app-sidebar">
        <div class="sidebar-heading">
            <h2 aria-label="Cafe POS">
                <?php $navIcon('<path d="M4 8h13v6a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4zm13 1h1a2 2 0 0 1 0 4h-1M3 21h17M8 3v2m4-2v2"/>'); ?>
                <span class="sidebar-label">Cafe POS</span>
            </h2>
            <button type="button" id="sidebar-collapse" class="sidebar-toggle sidebar-toggle-desktop" aria-controls="app-sidebar" aria-expanded="true" aria-label="Collapse sidebar" hidden>
                <?php $navIcon('<path d="m14 6-6 6 6 6"/>'); ?>
            </button>
        </div>

        <nav aria-label="Main navigation">
            <?php foreach ($navLinks as $href => [$label, $icon]): ?>
                <?php $isActive = $href === $navActivePage; ?>
                <a href="<?= $navEscape($href) ?>" aria-label="<?= $navEscape($label) ?>" title="<?= $navEscape($label) ?>"<?= $isActive ? ' class="active" aria-current="page"' : '' ?>>
                    <?php $navIcon($icon); ?>
                    <span class="sidebar-label"><?= $navEscape($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <form action="logout.php" method="post" class="logout">
            <?php require_once __DIR__ . '/mutation_request.php'; ?>
            <input type="hidden" name="csrf_token" value="<?= $navEscape(sessionCsrfToken()) ?>">
            <button type="submit" aria-label="Logout" title="Logout">
                <?php $navIcon('<path d="M10 4H4v16h6m4-12 4 4-4 4m-6-4h10"/>'); ?>
                <span class="sidebar-label">Logout</span>
            </button>
        </form>
    </aside>
</div>
<script src="assets/js/sidebar.js" defer></script>
