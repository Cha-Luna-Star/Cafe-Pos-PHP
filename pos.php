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

require_once __DIR__ . '/controllers/pos.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cafe POS | New Order</title>
    <link rel="stylesheet" href="assets/css/pos.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <script src="assets/js/pos.js" defer></script>
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="assets/css/ui.css">
</head>
<body>
<?php
$appPageTitle = 'New Order';
$appHeaderBack = ['dashboard.php', 'Back to Dashboard'];
require __DIR__ . '/includes/sidebar.php';
?>

<main class="ui-page">
    <section class="ui-panel menu-section">
        <div class="pos-section-heading">
            <h2>Menu</h2>
            <a href="#current-order" id="cart-shortcut">View cart (0)</a>
        </div>

        <div class="pos-menu-search">
            <label for="pos-menu-search">Find an item</label>
            <div>
                <input class="ui-field" type="search" id="pos-menu-search" placeholder="Search menu items" autocomplete="off">
                <button class="ui-button" type="button" id="clear-pos-menu-search" disabled>Clear Search</button>
            </div>
        </div>

        <div class="categories" role="group" aria-label="Menu categories">
            <button class="ui-choice" type="button" data-menu-category="all" aria-pressed="true">
                All
            </button>

            <?php foreach ($categories as $category): ?>
                <button class="ui-choice" type="button"
                    data-menu-category="<?= (int)$category['id'] ?>" aria-pressed="false">
                    <?= htmlspecialchars($category['name']) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <p id="pos-menu-result-count" class="pos-menu-result-count" role="status"></p>
        <p id="pos-menu-empty" class="pos-menu-empty" hidden>No menu items match your search and category.</p>
        <div class="menu-grid">
            <?php foreach ($menuItems as $item): ?>
                <?php
                $itemSizes = array_values(array_filter(
                    $sizes,
                    fn($size) =>
                        $item['has_sizes'] == 1 &&
                        (
                            $size['menu_item_id'] === null ||
                            $size['menu_item_id'] == $item['id']
                        )
                ));

                $itemData = [
                    'id' => (int)$item['id'],
                    'name' => $item['name'],
                    'price' => (float)$item['price'],
                    'discount' => (float)$item['discount'],
                    'has_sizes' => (int)$item['has_sizes'],
                    'has_temp_option' => (int)$item['has_temp_option'],
                    'is_addon' => (int)$item['category_id'] === 4,
                    'sizes' => $itemSizes
                ];

                $json = htmlspecialchars(
                    json_encode($itemData, JSON_HEX_APOS | JSON_HEX_QUOT),
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>

                <div class="ui-panel menu-card"
                     data-category="<?= (int)$item['category_id'] ?>"
                     data-menu-name="<?= htmlspecialchars($item['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

                    <h3><?= htmlspecialchars($item['name']) ?></h3>

                    <p>
                        ₱<?= number_format((float)$item['price'], 2) ?>
                    </p>

                    <button data-tone="positive" class="ui-button" type="button"
                            data-item="<?= $json ?>"
                            data-menu-id="<?= (int)$item['id'] ?>"
                            data-stock="<?= (int)$item['sellable_quantity'] ?>"
                            <?= (int)$item['sellable_quantity'] === 0 ? 'disabled' : '' ?>
                            onclick="addToCart(JSON.parse(this.dataset.item))">
                        <?= (int)$item['sellable_quantity'] > 0 ? 'Add to Cart' : 'Out of Stock' ?>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <aside class="cart-section" id="current-order" aria-labelledby="current-order-heading">
        <h2 id="current-order-heading">Current Order</h2>

        <div id="cart-items">
            <p>No items added.</p>
        </div>

        <div class="cart-total">
            <strong>Total:</strong>
            <strong id="cart-total">₱0.00</strong>
        </div>

        <form id="checkout-form">
            <label for="order-type">Order Type</label>
            <select class="ui-field" id="order-type" required>
                <option value="Dine In">Dine In</option>
                <option value="Take Out">Take Out</option>
            </select>

            <label for="discount-type">Discount</label>
            <select class="ui-field" id="discount-type">
                <option value="None">None</option>
                <option value="PWD">PWD (20%)</option>
                <option value="Student">Student (10%)</option>
                <option value="Senior Citizen">Senior Citizen (20%)</option>
            </select>

            <label for="payment-method">Payment Method</label>
            <select class="ui-field" id="payment-method">
                <option value="Cash">Cash</option>
                <option value="Card">Card</option>
                <option value="E-Wallet">E-Wallet</option>
            </select>

            <div id="cash-fields">
                <label for="amount-tendered">Amount Tendered</label>
                <input class="ui-field" type="number" id="amount-tendered" min="0" step="0.01" inputmode="decimal" aria-describedby="cash-summary">
                <p id="cash-summary" aria-live="polite"></p>
            </div>

            <div id="reference-fields" hidden>
                <label for="ref-number">Reference Number</label>
                <input class="ui-field" type="text" id="ref-number" maxlength="100">
            </div>

            <p id="checkout-message" role="status" aria-live="polite"></p>

            <button data-tone="positive" class="ui-button" type="submit" id="checkout-btn" disabled>
                Place Order
            </button>
        </form>
    </aside>
</main>

<dialog class="ui-dialog" id="receipt-modal" aria-labelledby="receipt-dialog-title">
    <div class="receipt">
        <h2 id="receipt-dialog-title">Cafe POS</h2>
        <p>Order Receipt</p>
        <hr>

        <p>Order #: <span id="receipt-order"></span></p>
        <p>Date: <span id="receipt-date"></span></p>
        <p>Payment: <span id="receipt-payment"></span></p>

        <hr>

        <div id="receipt-items"></div>

        <hr>

        <p>Subtotal: <span id="receipt-subtotal"></span></p>
        <p>Discount: <span id="receipt-discount"></span></p>
        <p>VAT Included: <span id="receipt-tax"></span></p>

        <h3>Total: <span id="receipt-total"></span></h3>

        <p>Amount Paid: <span id="receipt-paid"></span></p>
        <p>Change: <span id="receipt-change"></span></p>

        <div class="receipt-actions">
            <button data-tone="outline" class="ui-button" type="button" onclick="window.print()">Print</button>
            <button class="ui-button" type="button" id="close-receipt-btn" onclick="closeReceipt()">Close</button>
        </div>
    </div>
</dialog>

</body>
</html>
