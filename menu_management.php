<?php
require_once __DIR__ . '/includes/auth_session.php';
startAuthSession();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/controllers/menu_management.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cafe POS | Menu Management</title>
    <link rel="stylesheet" href="assets/css/table_sort.css">
    <link rel="stylesheet" href="assets/css/menu_management.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
    <script src="assets/js/table_sort.js" defer></script>
    <script src="assets/js/menu_management.js" defer></script>
    <link rel="stylesheet" href="assets/css/ui.css">
</head>
<body class="table-page">

<?php
$appPageTitle = 'Menu Management';
$appHeaderActions = ['add-item-btn' => '+ Add Item'];
require __DIR__ . '/includes/sidebar.php';
?>

<main class="ui-page menu-management">
    <div class="toolbar">
        <input class="ui-field" type="search" id="menu-search" aria-label="Search menu items" placeholder="Search menu items...">

        <select class="ui-field" id="category-filter" aria-label="Menu category">
            <option value="all">All Categories</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= (int)$category['id'] ?>">
                    <?= htmlspecialchars($category['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select class="ui-field" id="status-filter" aria-label="Menu item status">
            <option value="all">All Items</option>
            <option value="available">Available</option>
            <option value="unavailable">Unavailable</option>
            <option value="archived">Archived</option>
        </select>
    </div>

    <div class="ui-panel table-wrapper table-scroll-panel">
        <table>
            <thead>
                <tr>
                    <th><button type="button" class="sort-header" data-type="text"><span class="sort-label">Item</span> <span class="sort-indicator" aria-hidden="true">A–Z</span></button></th>
                    <th><button type="button" class="sort-header" data-type="text"><span class="sort-label">Category</span> <span class="sort-indicator" aria-hidden="true">A–Z</span></button></th>
                    <th><button type="button" class="sort-header" data-type="number"><span class="sort-label">Price</span> <span class="sort-indicator" aria-hidden="true">⇅</span></button></th>
                    <th><button type="button" class="sort-header" data-type="number"><span class="sort-label">Stock</span> <span class="sort-indicator" aria-hidden="true">⇅</span></button></th>
                    <th><button type="button" class="sort-header" data-type="number"><span class="sort-label">Low Stock Threshold</span> <span class="sort-indicator" aria-hidden="true">⇅</span></button></th>
                    <th><button type="button" class="sort-header" data-type="text"><span class="sort-label">Status</span> <span class="sort-indicator" aria-hidden="true">A–Z</span></button></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="menu-table">
                <?php foreach ($menuItems as $item): ?>
                    <?php
                    $status = (int)$item['is_archived'] === 1
                        ? 'Archived'
                        : ((int)$item['available'] === 1 ? 'Available' : 'Unavailable');
                    ?>
                    <tr
                        data-name="<?= htmlspecialchars(strtolower($item['name']), ENT_QUOTES, 'UTF-8') ?>"
                        data-category="<?= (int)$item['category_id'] ?>"
                        data-status="<?= strtolower($status) ?>"
                    >
                        <td title="<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>"><?= htmlspecialchars($item['name']) ?></td>
                        <td><?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?></td>
                        <td data-number="<?= (float)$item['price'] ?>">₱<?= number_format((float)$item['price'], 2) ?></td>
                        <td><?= (int)$item['stock_qty'] ?></td>
                        <td><?= (int)$item['low_stock_threshold'] ?></td>
                        <td>
                            <span class="ui-badge" data-tone="<?= match ($status) {
                                    'Available' => 'positive', 'Unavailable' => 'warning', default => '',
                                } ?>">
                                <?= $status ?>
                            </span>
                        </td>
                        <td>
                            <button data-tone="info"
                                type="button"
                                class="ui-button ui-button-sm edit-btn"
                                data-item="<?= htmlspecialchars(json_encode([
                                    'id' => (int)$item['id'],
                                    'name' => $item['name'],
                                    'price' => (float)$item['price'],
                                    'category_id' => (int)$item['category_id'],
                                    'low_stock_threshold' => (int)$item['low_stock_threshold'],
                                    'available' => (int)$item['available'],
                                    'has_temp_option' => (int)$item['has_temp_option']
                                ]), ENT_QUOTES, 'UTF-8') ?>"
                            >
                                Edit
                            </button>

                            <button data-tone="<?= (int)$item['is_archived'] === 1 ? 'positive' : 'warning' ?>"
                                type="button"
                                class="ui-button ui-button-sm archive-btn"
                                data-id="<?= (int)$item['id'] ?>"
                                data-action="<?= (int)$item['is_archived'] === 1 ? 'restore' : 'archive' ?>"
                            >
                                <?= (int)$item['is_archived'] === 1 ? 'Restore' : 'Archive' ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr id="menu-empty" <?= $menuItems ? 'hidden' : '' ?>>
                    <td colspan="7"><?= $menuItems ? 'No menu items match your filters.' : 'No menu items yet.' ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</main>

<dialog class="ui-dialog" id="confirm-dialog" aria-labelledby="confirm-title">
    <div class="confirm-content">
        <div class="confirm-icon">!</div>
        <h2 id="confirm-title">Archive Item</h2>
        <p id="confirm-description"></p>

        <div class="confirm-actions">
            <button data-tone="danger" class="ui-button" type="button" id="confirm-cancel">Cancel</button>
            <button data-tone="positive" class="ui-button" type="button" id="confirm-submit">Confirm</button>
        </div>
    </div>
</dialog>

<dialog class="ui-dialog" id="item-dialog" aria-labelledby="dialog-title">
    <form id="item-form">
        <h2 id="dialog-title">Add Item</h2>

        <input type="hidden" id="item-id">

        <label for="item-name">Item Name</label>
        <input class="ui-field" type="text" id="item-name" maxlength="150" required>

        <label for="item-price">Price (₱)</label>
        <input class="ui-field" type="number" id="item-price" min="0" step="0.01" required>

        <label for="item-low-stock-threshold">Low Stock Threshold</label>
        <input class="ui-field" type="number" id="item-low-stock-threshold" min="0" max="2147483647" step="1"
            value="<?= DEFAULT_PRODUCT_LOW_STOCK_THRESHOLD ?>" <?= $canSetProductThreshold ? 'required' : 'disabled' ?>>
        <?php if (!$canSetProductThreshold): ?>
            <small>Apply the product threshold database migration to edit this value.</small>
        <?php endif; ?>

        <label for="item-category">Category</label>
        <select class="ui-field" id="item-category" required>
            <?php foreach ($categories as $category): ?>
                <option value="<?= (int)$category['id'] ?>">
                    <?= htmlspecialchars($category['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label class="checkbox-label">
            <input type="checkbox" id="item-available" checked>
            Available
        </label>

        <label class="checkbox-label" id="temp-option-row">
            <input type="checkbox" id="item-temperature">
            Allow Hot/Iced Selection
        </label>

        <p id="item-message" role="status"></p>

        <div class="dialog-actions">
            <button data-tone="danger" class="ui-button" type="button" id="cancel-item-btn">Cancel</button>
            <button data-tone="positive" class="ui-button" type="submit" id="save-item-btn">Save Item</button>
        </div>
    </form>
</dialog>



</body>
</html>