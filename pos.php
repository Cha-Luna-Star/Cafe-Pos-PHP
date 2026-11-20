
<?php
session_start();

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
</head>
<body>
<?php require __DIR__ . '/includes/sidebar.php'; ?>

<header>
    <h1>New Order</h1>
    <a href="dashboard.php">Back to Dashboard</a>
</header>

<main>
    <section class="menu-section">
        <h2>Menu</h2>

        <div class="categories">
            <button type="button" onclick="filterCategory('all')">
                All
            </button>

            <?php foreach ($categories as $category): ?>
                <button type="button"
                    onclick="filterCategory(<?= (int)$category['id'] ?>)">
                    <?= htmlspecialchars($category['name']) ?>
                </button>
            <?php endforeach; ?>
        </div>

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

                <div class="menu-card"
                     data-category="<?= (int)$item['category_id'] ?>">

                    <h3><?= htmlspecialchars($item['name']) ?></h3>

                    <p>
                        ₱<?= number_format((float)$item['price'], 2) ?>
                    </p>

                    <button type="button"
                            data-item="<?= $json ?>"
                            onclick="addToCart(JSON.parse(this.dataset.item))">
                        Add to Cart
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <aside class="cart-section">
        <h2>Current Order</h2>

        <div id="cart-items">
            <p>No items added.</p>
        </div>

        <div class="cart-total">
            <strong>Total:</strong>
            <strong id="cart-total">₱0.00</strong>
        </div>

        <form id="checkout-form">
            <label for="order-type">Order Type</label>
            <select id="order-type" required>
                <option value="Dine In">Dine In</option>
                <option value="Take Out">Take Out</option>
            </select>

            <label for="discount-type">Discount</label>
            <select id="discount-type">
                <option value="None">None</option>
                <option value="PWD">PWD (20%)</option>
                <option value="Student">Student (10%)</option>
                <option value="Senior Citizen">Senior Citizen (20%)</option>
            </select>

            <label for="payment-method">Payment Method</label>
            <select id="payment-method">
                <option value="Cash">Cash</option>
                <option value="Card">Card</option>
                <option value="E-Wallet">E-Wallet</option>
            </select>

            <div id="cash-fields">
                <label for="amount-tendered">Amount Tendered</label>
                <input type="number" id="amount-tendered" min="0" step="0.01">
            </div>

            <div id="reference-fields" hidden>
                <label for="ref-number">Reference Number</label>
                <input type="text" id="ref-number" maxlength="100">
            </div>

            <p id="checkout-message" role="status"></p>

            <button type="submit" id="checkout-btn" disabled>
                Checkout
            </button>
        </form>
    </aside>
</main>

<dialog id="receipt-modal">
    <div class="receipt">
        <h2>Cafe POS</h2>
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
            <button type="button" onclick="window.print()">Print</button>
            <button type="button" onclick="closeReceipt()">Close</button>
        </div>
    </div>
</dialog>

</body>
</html>
