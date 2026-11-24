<?php
require_once __DIR__ . '/includes/auth_session.php';
startAuthSession();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$canManageInventory = in_array($_SESSION['role'] ?? '', ['Admin', 'Manager'], true);
require_once __DIR__ . '/controllers/inventory.php';
require_once __DIR__ . '/includes/inventory_dates.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cafe POS | Inventory</title>
<link rel="stylesheet" href="assets/css/inventory.css">
<link rel="stylesheet" href="assets/css/sidebar.css">
<meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
<script src="assets/js/inventory.js" defer></script>
</head>
<body>

<?php require __DIR__ . '/includes/sidebar.php'; ?>

<header class="page-header">
    <h1>Inventory Management</h1>
    <?php if ($canManageInventory): ?>
        <div class="inventory-actions">
            <button type="button" id="stock-in-btn">+ Stock In</button>
            <button type="button" id="adjust-stock-btn">Adjust Stock</button>
            <button type="button" id="add-ingredient-btn">+ Ingredient</button>
        </div>
    <?php endif; ?>
</header>

<main class="inventory-page">
    <?php
    $totalItems = count($ingredients) + count($products);
    $lowStockCount = 0;
    $outOfStockCount = 0;
    $expiringSoonCount = 0;
    $expiredCount = 0;

    foreach ($ingredients as $item) {
        $stock = (float)$item['stock_qty'];
        $threshold = (float)$item['low_stock_threshold'];

        if ($stock <= 0) {
            $outOfStockCount++;
        } elseif ($stock <= $threshold) {
            $lowStockCount++;
        }
    }

    foreach ($products as $item) {
        $stock = (float)$item['stock_qty'];

        if ($stock <= 0) {
            $outOfStockCount++;
        } elseif ($stock <= (int)$item['low_stock_threshold']) {
            $lowStockCount++;
        }
    }

    $today = inventoryToday();
    $expirationLabels = [
        'no-expiration' => 'No Expiration',
        'invalid-date' => 'Invalid Date',
        'expired' => 'Expired',
        'expiring-soon' => 'Expiring Soon',
        'valid' => 'Valid'
    ];

    foreach ($batches as &$batch) {
        $batch['expiration_status'] = batchExpirationStatus($batch['expiration_date'], $today);
        if ((float)$batch['quantity'] > 0) {
            $expiredCount += (int)($batch['expiration_status'] === 'expired');
            $expiringSoonCount += (int)($batch['expiration_status'] === 'expiring-soon');
        }
    }
    unset($batch);

    $formatBatchValue = static function (string $value): string {
        [$whole, $cents] = explode('.', bcadd($value, '0', 2));
        return '&#8369;' . preg_replace('/\B(?=(\d{3})+$)/', ',', $whole) . '.' . $cents;
    };
    ?>

    <section class="inventory-summary">
        <div class="summary-card" data-filter="all" role="button" tabindex="0">
            <span>Total Items</span>
            <strong><?= $totalItems ?></strong>
        </div>

        <div class="summary-card low-stock-card" data-filter="low-stock" role="button" tabindex="0">
            <span>Low Stock</span>
            <strong><?= $lowStockCount ?></strong>
        </div>

        <div class="summary-card out-of-stock-card" data-filter="out-of-stock" role="button" tabindex="0">
            <span>Out of Stock</span>
            <strong><?= $outOfStockCount ?></strong>
        </div>

        <div class="summary-card expiring-card" data-filter="expiring-soon" role="button" tabindex="0">
            <span>Expiring Soon</span>
            <strong><?= $expiringSoonCount ?></strong>
        </div>

        <div class="summary-card expired-card" data-filter="expired" role="button" tabindex="0">
            <span>Expired</span>
            <strong><?= $expiredCount ?></strong>
        </div>
    </section>
    <div class="inventory-tabs">
        <button type="button" class="inventory-tab active" data-target="current-stock-section">Current Stock</button>
        <button type="button" class="inventory-tab" data-target="batch-history-section">Batch History</button>
        <button type="button" class="inventory-tab" data-target="stock-movement-section">Stock Movements</button>
        <?php if ($canManageInventory): ?>
            <button type="button" class="inventory-tab"
                data-target="reconciliation-section">
                Reconciliation (<?= count($discrepancies) ?>)
            </button>
            <button type="button" class="inventory-tab" data-target="reconciliation-history-section">
                Reconciliation History
            </button>
        <?php endif; ?>
        <?php if ($canManageInventory): ?>
            <button type="button" class="inventory-tab"
                data-target="archived-ingredients-section">
                Archived Ingredients
            </button>
        <?php endif; ?>
    </div>
    <section id="current-stock-section" class="inventory-section">
        <div class="inventory-heading">
            <h2>Current Stock</h2>
            <button type="button" class="export-csv-btn" data-table="current-stock-body" data-filename="current_stock.csv">Export CSV</button>
        </div>
        <div class="inventory-filters">
            <input type="search" id="stock-search" placeholder="Search items...">
            <select id="stock-type-filter">
                <option value="">All Types</option>
                <option value="Product">Product</option>
                <option value="Ingredient">Ingredient</option>
            </select>
            <select id="stock-status-filter">
                <option value="">All Stock Statuses</option>
                <option value="in-stock">In Stock</option>
                <option value="low-stock">Low Stock</option>
                <option value="out-of-stock">Out of Stock</option>
            </select>
            <button type="button" id="clear-stock-filters">Clear</button>
        </div>

        <div class="inventory-table">
            <table>
                <thead>
                    <tr>
                        <th><button type="button" class="sort-header" data-table="current-stock-body" data-column="0" data-type="text"><span class="sort-label">Type</span> <span class="sort-indicator" aria-hidden="true">A–Z</span></button></th>
                        <th><button type="button" class="sort-header" data-table="current-stock-body" data-column="1" data-type="text"><span class="sort-label">Item</span> <span class="sort-indicator" aria-hidden="true">A–Z</span></button></th>
                        <th><button type="button" class="sort-header" data-table="current-stock-body" data-column="2" data-type="number"><span class="sort-label">Stock</span> <span class="sort-indicator" aria-hidden="true">⇅</span></button></th>
                        <th><button type="button" class="sort-header" data-table="current-stock-body" data-column="3" data-type="text"><span class="sort-label">Unit</span> <span class="sort-indicator" aria-hidden="true">A–Z</span></button></th>
                        <th><button type="button" class="sort-header" data-table="current-stock-body" data-column="4" data-type="text"><span class="sort-label">Status</span> <span class="sort-indicator" aria-hidden="true">A–Z</span></button></th>
                    </tr>
                </thead>
                <tbody id="current-stock-body">
                    <?php
                    $allItems = [];

                    foreach ($ingredients as $item) {
                        $allItems[] = [
                            'type' => 'Ingredient',
                            'id' => (int)$item['id'],
                            'name' => $item['name'],
                            'stock' => (float)$item['stock_qty'],
                            'unit' => $item['unit'],
                            'threshold' => (float)$item['low_stock_threshold'],
                            'cost' => (float)$item['cost_per_unit']
                        ];
                    }

                    foreach ($products as $item) {
                        $allItems[] = [
                            'type' => 'Product',
                            'id' => (int)$item['id'],
                            'name' => $item['name'],
                            'stock' => (float)$item['stock_qty'],
                            'unit' => 'pcs',
                            'threshold' => (int)$item['low_stock_threshold']
                        ];
                    }

                    foreach ($allItems as $item):
                        $key = $item['type'] . '-' . $item['id'];
                    ?>
                        <tr class="stock-row" data-key="<?= htmlspecialchars($key) ?>">
                            <td><?= htmlspecialchars($item['type']) ?></td>
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td data-number="<?= number_format($item['stock'], 3, '.', '') ?>"><?= number_format($item['stock'], 3) ?></td>
                            <td><?= htmlspecialchars($item['unit']) ?></td>
                            <?php
                            if ($item['stock'] <= 0) {
                                $stockStatus = 'Out of Stock';
                                $statusClass = 'out-of-stock';
                            } elseif ($item['stock'] <= $item['threshold']) {
                                $stockStatus = 'Low Stock';
                                $statusClass = 'low-stock';
                            } else {
                                $stockStatus = 'In Stock';
                                $statusClass = 'in-stock';
                            }
                            ?>

                            <td data-value="<?= $stockStatus ?>">
                                <div class="stock-status-wrapper">
                                    <span class="stock-status <?= $statusClass ?>">
                                        <?= $stockStatus ?>
                                    </span>
                                    <?php if (
                                        $item['type'] === 'Ingredient' &&
                                        in_array($_SESSION['role'] ?? '', ['Admin', 'Manager'], true)
                                    ): ?>
                                        <button type="button" class="edit-ingredient-btn"
                                            data-id="<?= $item['id'] ?>"
                                            data-name="<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>"
                                            data-unit="<?= htmlspecialchars($item['unit'], ENT_QUOTES) ?>"
                                            data-threshold="<?= $item['threshold'] ?>"
                                            data-cost="<?= $item['cost'] ?>">
                                            Edit
                                        </button>
                                        <button type="button" class="delete-ingredient-btn"
                                            data-id="<?= $item['id'] ?>"
                                            data-name="<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>">
                                            Delete
                                        </button>
                                        <button type="button" class="archive-ingredient-btn"
                                            data-id="<?= $item['id'] ?>"
                                            data-name="<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>">
                                            Archive
                                        </button>
                                    <?php endif; ?>
                                    <span class="stock-expand-arrow" aria-hidden="true"></span>
                                </div>
                            </td>
                        </tr>

                        <tr class="stock-details" data-parent="<?= htmlspecialchars($key) ?>" hidden>
                            <td colspan="5">
                                <div class="batch-details">
                                    <strong>Stock Batches</strong>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Batch</th>
                                                <th>Received</th>
                                                <th>Remaining</th>
                                                <th>Expiration</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $matchingBatches = array_filter(
                                                $batches,
                                                fn($batch) =>
                                                    $batch['item_type'] === $item['type'] &&
                                                    (int)$batch['item_id'] === $item['id']
                                            );

                                            foreach ($matchingBatches as $batch):
                                            ?>
                                                <tr>
                                                    <td>#<?= (int)$batch['id'] ?></td>
                                                    <td><?= htmlspecialchars((string)$batch['received_date']) ?></td>
                                                    <td><?= number_format((float)$batch['quantity'], 2) ?></td>
                                                    <td><?= htmlspecialchars((string)($batch['expiration_date'] ?? 'N/A')) ?></td>
                                                </tr>
                                            <?php endforeach; ?>

                                            <?php if (!$matchingBatches): ?>
                                                <tr>
                                                    <td colspan="4">No batch records available.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tbody id="stock-no-results" hidden>
                    <tr>
                        <td colspan="5" class="no-results">No matching items found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="inventory-pagination" data-table="current-stock-body"></div>
    </section>

    <section id="batch-history-section" class="inventory-section" hidden>
        <div class="inventory-heading">
            <h2>Batch History</h2>
            <button type="button" class="export-csv-btn" data-table="batch-history-body" data-filename="batch_history.csv">Export CSV</button>
        </div>

        <section class="inventory-valuation" aria-labelledby="inventory-valuation-heading">
            <h3 id="inventory-valuation-heading">Inventory Valuation</h3>
            <dl>
                <div><dt>Ingredients</dt><dd><?= $formatBatchValue($inventoryValue['Ingredient']) ?></dd></div>
                <div><dt>Products</dt><dd><?= $formatBatchValue($inventoryValue['Product']) ?></dd></div>
                <div><dt>Total at recorded cost</dt><dd><?= $formatBatchValue($totalInventoryValue) ?></dd></div>
            </dl>
            <p>Uses remaining quantities at their batch cost, including expired and archived stock still on hand.</p>
            <?php if ($uncostedBatches > 0): ?>
                <p>Excluded from the total: <?= $uncostedBatches ?> remaining batches without a usable cost.</p>
            <?php endif; ?>
        </section>

        <div class="inventory-filters">
            <input type="search" id="batch-search" placeholder="Search batches...">
            <select id="batch-type-filter">
                <option value="">All Types</option>
                <option value="Product">Product</option>
                <option value="Ingredient">Ingredient</option>
            </select>
            <select id="batch-expiration-filter">
                <option value="">All Expiration Statuses</option>
                <option value="valid">Valid</option>
                <option value="expiring-soon">Expiring Soon</option>
                <option value="expired">Expired</option>
                <option value="no-expiration">No Expiration</option>
                <option value="invalid-date">Invalid Date</option>
            </select>
            <label>
                <input type="checkbox" id="batch-remaining-only">
                Remaining stock only
            </label>
            <button type="button" id="clear-batch-filters">Clear</button>
        </div>

        <div class="inventory-table">
            <table>
            <thead>
                <tr>
                    <th><button type="button" class="sort-header" data-table="batch-history-body" data-column="0" data-type="text"><span class="sort-label">Type</span> <span class="sort-indicator" aria-hidden="true">A–Z</span></button></th>
                    <th><button type="button" class="sort-header" data-table="batch-history-body" data-column="1" data-type="text"><span class="sort-label">Item</span> <span class="sort-indicator" aria-hidden="true">A–Z</span></button></th>
                    <th><button type="button" class="sort-header" data-table="batch-history-body" data-column="2" data-type="number"><span class="sort-label">Remaining</span> <span class="sort-indicator" aria-hidden="true">⇅</span></button></th>
                    <th><button type="button" class="sort-header" data-table="batch-history-body" data-column="3" data-type="number"><span class="sort-label">Cost/Unit</span> <span class="sort-indicator" aria-hidden="true">⇅</span></button></th>
                    <th><button type="button" class="sort-header" data-table="batch-history-body" data-column="4" data-type="date"><span class="sort-label">Received</span> <span class="sort-indicator" aria-hidden="true">Oldest</span></button></th>
                    <th><button type="button" class="sort-header" data-table="batch-history-body" data-column="5" data-type="date"><span class="sort-label">Expiration</span> <span class="sort-indicator" aria-hidden="true">Oldest</span></button></th>
                    <th>Status</th>
                    <th><button type="button" class="sort-header" data-table="batch-history-body" data-column="7" data-type="text"><span class="sort-label">Supplier</span> <span class="sort-indicator" aria-hidden="true">A–Z</span></button></th>
                    <th><button type="button" class="sort-header" data-table="batch-history-body" data-column="8" data-type="number"><span class="sort-label">Remaining Value</span> <span class="sort-indicator" aria-hidden="true">⇅</span></button></th>
                </tr>
            </thead>
                <tbody id="batch-history-body">
                    <?php foreach ($batches as $batch): ?>
                        <tr data-has-stock="<?= (float)$batch['quantity'] > 0 ? 'true' : 'false' ?>">
                            <td><?= htmlspecialchars($batch['item_type']) ?></td>
                            <td><?= htmlspecialchars($batch['item_name']) ?></td>
                            <td data-number="<?= number_format((float)$batch['quantity'], 3, '.', '') ?>"><?= number_format((float)$batch['quantity'], 3) ?></td>
                            <td>₱<?= number_format((float)$batch['cost_per_unit'], 2) ?></td>
                            <td><?= htmlspecialchars((string)$batch['received_date']) ?></td>
                            <td><?= htmlspecialchars((string)($batch['expiration_date'] ?? 'N/A')) ?></td>
                            <td>
                                <span class="expiration-status <?= $batch['expiration_status'] ?>">
                                    <?= $expirationLabels[$batch['expiration_status']] ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars((string)($batch['supplier'] ?? 'N/A')) ?></td>
                            <td><?= $batch['remaining_value'] === null ? 'Unavailable' : $formatBatchValue($batch['remaining_value']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                </tbody>
                <tbody id="batch-no-results" hidden>
                    <tr>
                        <td colspan="9" class="no-results">No matching batches found.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="inventory-pagination" data-table="batch-history-body"></div>
    </section>

    <section id="stock-movement-section" class="inventory-section" hidden>
        <div class="inventory-heading">
            <h2>Stock Movement History</h2>
            <button type="button" class="export-csv-btn" data-table="movement-history-body" data-filename="stock_movements.csv">Export CSV</button>
        </div>

        <div class="inventory-filters">
            <input type="search" id="movement-search" placeholder="Search movements...">

            <select id="movement-type-filter">
                <option value="">All Item Types</option>
                <option value="Product">Product</option>
                <option value="Ingredient">Ingredient</option>
            </select>

            <select id="movement-action-filter">
                <option value="">All Movements</option>
                <option value="Stock In">Stock In</option>
                <option value="Sale">Sale</option>
                <option value="Adjustment">Adjustment</option>
                <option value="Cancellation">Cancellation</option>
                <option value="Expired">Expired</option>
                <option value="Damaged">Damaged</option>
            </select>

            <input type="date" id="movement-date-from" aria-label="Start date">
            <input type="date" id="movement-date-to" aria-label="End date">

            <button type="button" id="clear-movement-filters">Clear</button>
        </div>

        <div class="inventory-table">
            <table>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Item</th>
                        <th>Movement</th>
                        <th>Quantity</th>
                        <th>Batch</th>
                        <th>User</th>
                        <th>Date</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody id="movement-history-body">
                    <?php foreach ($movements as $movement): ?>
                        <tr>
                            <td><?= htmlspecialchars($movement['item_type']) ?></td>
                            <td><?= htmlspecialchars($movement['item_name'] ?? 'Unknown') ?></td>
                            <td>
                                <span class="movement-badge <?= strtolower(str_replace(' ', '-', $movement['movement_type'])) ?>">
                                    <?= htmlspecialchars($movement['movement_type']) ?>
                                </span>
                            </td>
                            <td data-number="<?= number_format((float)$movement['quantity'], 3, '.', '') ?>"><?= number_format((float)$movement['quantity'], 3) ?></td>
                            <td><?= $movement['batch_id'] !== null ? '#' . (int)$movement['batch_id'] : 'N/A' ?></td>
                            <td><?= htmlspecialchars($movement['username'] ?? 'System') ?></td>
                            <td data-date="<?= htmlspecialchars(substr((string)$movement['created_at'], 0, 10)) ?>">
                                <?= htmlspecialchars((string)$movement['created_at']) ?>
                            </td>
                            <td><?= htmlspecialchars($movement['remarks'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>

                </tbody>
                <tbody id="movement-no-results" hidden>
                    <tr>
                        <td colspan="8" class="no-results">No matching stock movements found.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="inventory-pagination" data-table="movement-history-body"></div>
    </section>

    <?php if ($canManageInventory): ?>
    <section id="archived-ingredients-section" class="inventory-section" hidden>
        <h2>Archived Ingredients</h2>

        <table class="inventory-table">
            <thead>
                <tr>
                    <th>Ingredient</th>
                    <th>Unit</th>
                    <th>Low Stock Threshold</th>
                    <th>Cost Per Unit</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="archived-ingredients-body">
                <?php foreach ($archivedIngredients as $ingredient): ?>
                    <tr>
                        <td><?= htmlspecialchars($ingredient['name']) ?></td>
                        <td><?= htmlspecialchars($ingredient['unit']) ?></td>
                        <td><?= $ingredient['low_stock_threshold'] ?></td>
                        <td>₱<?= number_format($ingredient['cost_per_unit'], 2) ?></td>
                        <td>
                            <button type="button" class="restore-ingredient-btn"
                                data-id="<?= $ingredient['id'] ?>"
                                data-name="<?= htmlspecialchars($ingredient['name'], ENT_QUOTES) ?>">
                                Restore
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if (empty($archivedIngredients)): ?>
                <tbody>
                    <tr>
                        <td colspan="5">No archived ingredients.</td>
                    </tr>
                </tbody>
            <?php endif; ?>
        </table>

        <div class="inventory-pagination"
            data-table="archived-ingredients-body"></div>
    </section>
    <?php endif; ?>
    <?php if ($canManageInventory): ?>
    <section id="reconciliation-section" class="inventory-section" hidden>
        <h2>Stock Reconciliation</h2>

        <p class="reconciliation-description">
            Compare recorded stock with remaining batch quantities.
        </p>

        <div class="reconciliation-filters">
            <input type="search" id="reconciliation-search"
                placeholder="Search item...">

            <select id="reconciliation-type-filter">
                <option value="">All Types</option>
                <option value="Ingredient">Ingredient</option>
                <option value="Product">Product</option>
            </select>

            <select id="reconciliation-status-filter">
                <option value="">All Statuses</option>
                <option value="Matched">Matched</option>
                <option value="Mismatch">Mismatch</option>
            </select>

            <button type="button" id="clear-reconciliation-filters">
                Clear Filters
            </button>
        </div>

        <table class="inventory-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Item</th>
                    <th>Recorded Stock</th>
                    <th>Batch Stock</th>
                    <th>Difference</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody id="reconciliation-body">
                <?php foreach ($reconciliation as $item): ?>
                    <?php
                    $difference = (float)$item['difference'];
                    $matched = abs($difference) < 0.001;
                    ?>

                    <tr>
                        <td><?= htmlspecialchars($item['item_type']) ?></td>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td><?= round((float)$item['recorded_stock'], 3) ?></td>
                        <td><?= round((float)$item['batch_stock'], 3) ?></td>
                        <td><?= round($difference, 3) ?></td>
                        <td>
                            <span class="reconciliation-status <?= $matched ? 'matched' : 'mismatch' ?>">
                                <?= $matched ? 'Matched' : 'Mismatch' ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!$matched && $canManageInventory): ?>
                                <button type="button" class="reconcile-btn"
                                    data-id="<?= $item['id'] ?>"
                                    data-type="<?= htmlspecialchars($item['item_type']) ?>"
                                    data-name="<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>"
                                    data-recorded="<?= $item['recorded_stock'] ?>"
                                    data-batch="<?= $item['batch_stock'] ?>">
                                    Resolve
                                </button>
                            <?php elseif ($matched): ?>
                                <span>Matched</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tbody id="reconciliation-no-results" hidden>
                <tr>
                    <td colspan="7">No matching items found.</td>
                </tr>
            </tbody>
        </table>

        <div class="inventory-pagination"
            data-table="reconciliation-body"></div>
    </section>
    <section id="reconciliation-history-section" class="inventory-section" hidden>
        <h2>Reconciliation History</h2>

        <div class="inventory-filters">
            <input type="search" id="correction-search" aria-label="Search corrections by item, user, or reason"
                placeholder="Search item, user, or reason">

            <select id="correction-type-filter" aria-label="Item type">
                <option value="">All Item Types</option>
                <option value="Ingredient">Ingredient</option>
                <option value="Product">Product</option>
            </select>

            <input type="date" id="correction-date-from" aria-label="Correction start date">
            <input type="date" id="correction-date-to" aria-label="Correction end date">
            <button type="button" id="clear-correction-filters">Clear</button>
        </div>

        <div class="inventory-table">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Type</th>
                        <th scope="col">Item</th>
                        <th scope="col">Previous Stock</th>
                        <th scope="col">Corrected Stock</th>
                        <th scope="col">User</th>
                        <th scope="col">Date</th>
                        <th scope="col">Reason</th>
                    </tr>
                </thead>
                <tbody id="reconciliation-history-body">
                    <?php foreach ($reconciliationHistory as $correction): ?>
                        <tr data-type="<?= htmlspecialchars($correction['item_type']) ?>"
                            data-date="<?= htmlspecialchars(substr($correction['corrected_at'] ?? '', 0, 10)) ?>">
                            <td><?= htmlspecialchars($correction['item_type']) ?></td>
                            <td class="correction-search-value"><?= htmlspecialchars($correction['item_name'] ?? 'Item #' . (int)$correction['item_id']) ?></td>
                            <td><?= htmlspecialchars($correction['previous_stock']) ?></td>
                            <td><?= htmlspecialchars($correction['corrected_stock']) ?></td>
                            <td class="correction-search-value"><?= htmlspecialchars($correction['username'] ?? 'User #' . (int)$correction['user_id']) ?></td>
                            <td><?= htmlspecialchars($correction['corrected_at'] ?? 'Unavailable') ?></td>
                            <td class="correction-search-value correction-reason"><?= htmlspecialchars($correction['reason']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tbody id="correction-no-results" <?= $reconciliationHistory ? 'hidden' : '' ?>>
                    <tr>
                        <td colspan="7" class="no-results"><?= $reconciliationHistory
                            ? 'No matching corrections found.'
                            : 'No stock corrections have been recorded.' ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="inventory-pagination" data-table="reconciliation-history-body"></div>
    </section>

    <?php endif; ?>
</main>

<dialog id="stock-dialog">
    <form id="stock-form">
        <h2>Stock In</h2>

        <label for="item-type">Item Type</label>
        <select id="item-type" required>
            <option value="Ingredient">Ingredient</option>
            <option value="Product">Product</option>
        </select>

        <label for="stock-item">Item</label>
        <select id="stock-item" required>
            <?php foreach ($ingredients as $item): ?>
                <option value="<?= (int)$item['id'] ?>" data-type="Ingredient">
                    <?= htmlspecialchars($item['name']) ?>
                </option>
            <?php endforeach; ?>
            <?php foreach ($products as $item): ?>
                <option value="<?= (int)$item['id'] ?>" data-type="Product">
                    <?= htmlspecialchars($item['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="stock-quantity">Quantity</label>
        <input type="number" id="stock-quantity" min="0.001" step="0.001" required>

        <label for="stock-cost">Cost Per Unit (₱)</label>
        <input type="number" id="stock-cost" min="0" step="0.01" required>

        <label for="received-date">Date Received</label>
        <input type="date" id="received-date" required>

        <label for="expiration-date">Expiration Date (Optional)</label>
        <input type="date" id="expiration-date">

        <label for="stock-supplier">Supplier</label>
        <input type="text" id="stock-supplier" maxlength="150">

        <label for="stock-notes">Notes</label>
        <textarea id="stock-notes" maxlength="500"></textarea>

        <p id="stock-message" role="status"></p>

        <div class="dialog-actions">
            <button type="button" id="cancel-stock-btn">Cancel</button>
            <button type="submit" id="save-stock-btn">Save Stock</button>
        </div>
    </form>
</dialog>

<?php if ($canManageInventory): ?>
<dialog id="adjust-stock-dialog">
    <form id="adjust-stock-form">
        <h2>Stock Adjustment</h2>

        <label for="adjust-item-type">Item Type</label>
        <select id="adjust-item-type" required>
            <option value="Ingredient">Ingredient</option>
            <option value="Product">Product</option>
        </select>

        <label for="adjust-item">Item</label>
        <select id="adjust-item" required>
            <?php foreach ($ingredients as $item): ?>
                <option value="<?= (int)$item['id'] ?>" data-type="Ingredient">
                    <?= htmlspecialchars($item['name']) ?>
                </option>
            <?php endforeach; ?>
            <?php foreach ($products as $item): ?>
                <option value="<?= (int)$item['id'] ?>" data-type="Product">
                    <?= htmlspecialchars($item['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="adjust-action">Adjustment Type</label>
        <select id="adjust-action" required>
            <option value="Add">Add Stock</option>
            <option value="Remove">Remove Stock</option>
        </select>
        <div id="adjust-movement-container" hidden>
            <label for="adjust-movement">Removal Reason</label>
            <select id="adjust-movement">
                <option value="Adjustment">Stock Correction</option>
                <option value="Expired">Expired Stock</option>
                <option value="Damaged">Damaged Stock</option>
            </select>
        </div>
        <div id="adjust-batch-container" hidden>
            <label for="adjust-batch">Select Batch</label>
            <select id="adjust-batch">
                <option value="">Select a batch</option>
                <?php foreach ($batches as $batch): ?>
                    <?php if ((float)$batch['quantity'] > 0): ?>
                        <option
                            value="<?= (int)$batch['id'] ?>"
                            data-type="<?= htmlspecialchars($batch['item_type']) ?>"
                            data-item="<?= (int)$batch['item_id'] ?>"
                            data-quantity="<?= (float)$batch['quantity'] ?>"
                            data-expiration-status="<?= $batch['expiration_status'] ?>">
                            #<?= (int)$batch['id'] ?>
                            | Stock: <?= (float)$batch['quantity'] ?>
                            | Exp: <?= htmlspecialchars((string)($batch['expiration_date'] ?: 'N/A')) ?>
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
        <label for="adjust-quantity">Quantity</label>
        <input type="number" id="adjust-quantity" min="0.001" step="0.001" required>

        <label for="adjust-reason">Reason</label>
        <textarea id="adjust-reason" maxlength="500" required
            placeholder="Enter the reason for this adjustment"></textarea>

        <p id="adjust-message" role="status"></p>

        <div class="dialog-actions">
            <button type="button" id="cancel-adjust-btn">Cancel</button>
            <button type="submit" id="save-adjust-btn">Save Adjustment</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<?php if ($canManageInventory): ?>
<dialog id="ingredient-dialog">
    <form id="ingredient-form">
        <h2 id="ingredient-dialog-title">Add Ingredient</h2>

        <input type="hidden" id="ingredient-id">

        <label for="ingredient-name">Ingredient Name</label>
        <input type="text" id="ingredient-name" maxlength="100" required>

        <label for="ingredient-unit">Unit</label>
        <select id="ingredient-unit" required>
            <option value="g">Grams (g)</option>
            <option value="kg">Kilograms (kg)</option>
            <option value="ml">Milliliters (ml)</option>
            <option value="L">Liters (L)</option>
            <option value="pcs">Pieces (pcs)</option>
        </select>

        <label for="ingredient-threshold">Low Stock Threshold</label>
        <input type="number" id="ingredient-threshold" min="0" step="0.001" required>

        <label for="ingredient-cost">Cost Per Unit (₱)</label>
        <input type="number" id="ingredient-cost" min="0" step="0.01" required>

        <p id="ingredient-message" role="status"></p>

        <div class="dialog-actions">
            <button type="button" id="cancel-ingredient-btn">Cancel</button>
            <button type="submit" id="save-ingredient-btn">Save Ingredient</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<?php if ($canManageInventory): ?>
<dialog id="reconcile-dialog">
    <form id="reconcile-form">
        <h2>Resolve Stock Mismatch</h2>

        <input type="hidden" id="reconcile-id">
        <input type="hidden" id="reconcile-type">
        <input type="hidden" id="reconcile-recorded">
        <input type="hidden" id="reconcile-batch">

        <p id="reconcile-item-name"></p>
        <p id="reconcile-stock-info"></p>

        <label for="reconcile-reason">Reason for Correction</label>
        <textarea id="reconcile-reason"
            maxlength="500" rows="4" required></textarea>

        <label>
            <input type="checkbox" id="reconcile-confirm" required>
            I have verified that the batch quantities are correct.
        </label>

        <p id="reconcile-message" role="status"></p>

        <div class="dialog-actions">
            <button type="button" id="cancel-reconcile-btn">Cancel</button>
            <button type="submit" id="save-reconcile-btn">Confirm Correction</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

</body>
</html>
