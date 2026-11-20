
<?php
session_start();

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
    <script src="assets/js/pos.js" defer></script>
</head>
<body>

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
                        $size['menu_item_id'] == $item['id']
                ));

                $itemData = [
                    'id' => (int)$item['id'],
                    'name' => $item['name'],
                    'price' => (float)$item['price'],
                    'discount' => (float)$item['discount'],
                    'has_sizes' => (int)$item['has_sizes'],
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

        <button type="button" id="checkout-btn" disabled>
            Checkout
        </button>
    </aside>
</main>

</body>
</html>
