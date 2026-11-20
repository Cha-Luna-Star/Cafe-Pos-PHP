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
        <button type="button" id="stock-in-btn">+ Stock In</button>
    <?php endif; ?>
</header>

<main class="inventory-page">
    <div class="inventory-heading">
        <h2>Current Stock</h2>
        <select id="current-stock-sort">
            <option value="name-asc">Name (A-Z)</option>
            <option value="name-desc">Name (Z-A)</option>
            <option value="stock-asc">Stock (Lowest First)</option>
            <option value="stock-desc">Stock (Highest First)</option>
            <option value="type">Item Type</option>
        </select>
    </div>

    <div class="inventory-table">
        <table>
            <thead>
                <tr>
                    <th><button type="button" class="sort-header" data-table="current-stock-body" data-column="0" data-type="text">Type <span>⇅</span></button></th>
                    <th><button type="button" class="sort-header" data-table="current-stock-body" data-column="1" data-type="text">Item <span>⇅</span></button></th>
                    <th><button type="button" class="sort-header" data-table="current-stock-body" data-column="2" data-type="number">Stock <span>⇅</span></button></th>
                    <th><button type="button" class="sort-header" data-table="current-stock-body" data-column="3" data-type="text">Unit <span>⇅</span></button></th>
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
                        'unit' => $item['unit']
                    ];
                }

                foreach ($products as $item) {
                    $allItems[] = [
                        'type' => 'Product',
                        'id' => (int)$item['id'],
                        'name' => $item['name'],
                        'stock' => (float)$item['stock_qty'],
                        'unit' => 'pcs'
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
                    </tr>

                    <tr class="stock-details" data-parent="<?= htmlspecialchars($key) ?>" hidden>
                        <td colspan="4">
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
        </table>
    </div>

    <div class="inventory-heading">
        <h2>Batch History</h2>
        <select id="batch-sort">
            <option value="newest">Newest Delivery</option>
            <option value="oldest">Oldest Delivery</option>
            <option value="name-asc">Name (A-Z)</option>
            <option value="name-desc">Name (Z-A)</option>
            <option value="quantity-asc">Quantity (Lowest First)</option>
            <option value="quantity-desc">Quantity (Highest First)</option>
            <option value="expiration">Expiration (Nearest First)</option>
        </select>
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
                <th><button type="button" class="sort-header" data-table="batch-history-body" data-column="6" data-type="text">Supplier <span>⇅</span></button></th>
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
                        <td><?= htmlspecialchars((string)($batch['supplier'] ?? 'N/A')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
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

</body>
</html>