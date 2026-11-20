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
    <h2>Current Stock</h2>

    <div class="inventory-table">
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Item</th>
                    <th>Stock</th>
                    <th>Unit</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ingredients as $item): ?>
                    <tr>
                        <td>Ingredient</td>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td><?= number_format((float)$item['stock_qty'], 2) ?></td>
                        <td><?= htmlspecialchars($item['unit']) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php foreach ($products as $item): ?>
                    <tr>
                        <td>Product</td>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td><?= (int)$item['stock_qty'] ?></td>
                        <td>pcs</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2>Batch History</h2>

    <div class="inventory-table">
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Item</th>
                    <th>Remaining</th>
                    <th>Cost/Unit</th>
                    <th>Received</th>
                    <th>Expiration</th>
                    <th>Supplier</th>
                </tr>
            </thead>
            <tbody>
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