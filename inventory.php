<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/controllers/inventory.php';
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
    <?php if (in_array($_SESSION['role'] ?? '', ['Admin', 'Manager'], true)): ?>
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
        } elseif ($stock <= 10) {
            $lowStockCount++;
        }
    }

    $today = new DateTimeImmutable('today');

    foreach ($batches as $batch) {
        if ((float)$batch['quantity'] <= 0) {
            continue;
        }

        $expiration = $batch['expiration_date'] ?? null;

        if (empty($expiration)) {
            continue;
        }

        try {
            $expiryDate = new DateTimeImmutable((string)$expiration);
            $daysRemaining = (int)$today->diff($expiryDate)->format('%r%a');

            if ($daysRemaining < 0) {
                $expiredCount++;
            } elseif ($daysRemaining <= 7) {
                $expiringSoonCount++;
            }
        } catch (Exception $e) {
            continue;
        }
    }
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
                        <th><button type="button" class="sort-header" data-table="current-stock-body" data-column="0" data-type="text">Type <span>⇅</span></button></th>
                        <th><button type="button" class="sort-header" data-table="current-stock-body" data-column="1" data-type="text">Item <span>⇅</span></button></th>
                        <th><button type="button" class="sort-header" data-table="current-stock-body" data-column="2" data-type="number">Stock <span>⇅</span></button></th>
                        <th><button type="button" class="sort-header" data-table="current-stock-body" data-column="3" data-type="text">Unit <span>⇅</span></button></th>
                        <th><button type="button" class="sort-header" data-table="current-stock-body" data-column="4" data-type="text">Status <span>⇅</span></button></th>
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
                            'threshold' => 10
                        ];
                    }

                    foreach ($allItems as $item):
                        $key = $item['type'] . '-' . $item['id'];
                    ?>
                        <tr class="stock-row" data-key="<?= htmlspecialchars($key) ?>">
                            <td><?= htmlspecialchars($item['type']) ?></td>
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td><?= number_format($item['stock'], 2) ?></td>
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

                            <td>
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
            <button type="button" id="clear-batch-filters">Clear</button>
        </div>

        <div class="inventory-table">
            <table>
            <thead>
                <tr>
                    <th><button type="button" class="sort-header" data-table="batch-history-body" data-column="0" data-type="text">Type <span>⇅</span></button></th>
                    <th><button type="button" class="sort-header" data-table="batch-history-body" data-column="1" data-type="text">Item <span>⇅</span></button></th>
                    <th><button type="button" class="sort-header" data-table="batch-history-body" data-column="2" data-type="number">Remaining <span>⇅</span></button></th>
                    <th><button type="button" class="sort-header" data-table="batch-history-body" data-column="3" data-type="number">Cost/Unit <span>⇅</span></button></th>
                    <th><button type="button" class="sort-header" data-table="batch-history-body" data-column="4" data-type="date">Received <span>⇅</span></button></th>
                    <th><button type="button" class="sort-header" data-table="batch-history-body" data-column="5" data-type="date">Expiration <span>⇅</span></button></th>
                    <th>Status</th>
                    <th><button type="button" class="sort-header" data-table="batch-history-body" data-column="7" data-type="text">Supplier <span>⇅</span></button></th>
                </tr>
            </thead>
                <tbody id="batch-history-body">
                    <?php foreach ($batches as $batch): ?>
                        <tr>
                            <td><?= htmlspecialchars($batch['item_type']) ?></td>
                            <td><?= htmlspecialchars($batch['item_name']) ?></td>
                            <td><?= number_format((float)$batch['quantity'], 2) ?></td>
                            <td>₱<?= number_format((float)$batch['cost_per_unit'], 2) ?></td>
                            <td><?= htmlspecialchars((string)$batch['received_date']) ?></td>
                            <td><?= htmlspecialchars((string)($batch['expiration_date'] ?? 'N/A')) ?></td>
                            <?php
                            $expiration = $batch['expiration_date'] ?? null;
                            $today = new DateTimeImmutable('today');

                            $expirationStatus = 'No Expiration';
                            $expirationClass = 'no-expiration';

                            if (!empty($expiration)) {
                                try {
                                    $expiryDate = new DateTimeImmutable((string)$expiration);
                                    $daysRemaining = (int)$today->diff($expiryDate)->format('%r%a');

                                    if ($daysRemaining < 0) {
                                        $expirationStatus = 'Expired';
                                        $expirationClass = 'expired';
                                    } elseif ($daysRemaining <= 7) {
                                        $expirationStatus = 'Expiring Soon';
                                        $expirationClass = 'expiring-soon';
                                    } else {
                                        $expirationStatus = 'Valid';
                                        $expirationClass = 'valid';
                                    }
                                } catch (Exception $e) {
                                    $expirationStatus = 'Invalid Date';
                                    $expirationClass = 'invalid-date';
                                }
                            }
                            ?>

                            <td>
                                <span class="expiration-status <?= $expirationClass ?>">
                                    <?= $expirationStatus ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars((string)($batch['supplier'] ?? 'N/A')) ?></td>
                            </tr>
                            <?php endforeach; ?>
                </tbody>
                <tbody id="batch-no-results" hidden>
                    <tr>
                        <td colspan="8" class="no-results">No matching batches found.</td>
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
                            <td><?= number_format((float)$movement['quantity'], 2) ?></td>
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

<?php if (in_array($_SESSION['role'] ?? '', ['Admin', 'Manager'], true)): ?>
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
                            data-quantity="<?= (float)$batch['quantity'] ?>">
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

        <div id="adjust-movement-container" hidden>
            <label for="adjust-movement">Removal Reason</label>
            <select id="adjust-movement">
                <option value="Adjustment">Stock Correction</option>
                <option value="Expired">Expired Stock</option>
                <option value="Damaged">Damaged Stock</option>
            </select>
        </div>
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

<?php if (in_array($_SESSION['role'] ?? '', ['Admin', 'Manager'], true)): ?>
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
</body>
</html>