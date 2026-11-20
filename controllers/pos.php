
<?php
require_once __DIR__ . '/../config/database.php';

$categories = $connection->query("
    SELECT id, name
    FROM dbo.categories
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

$menuItems = $connection->query("
    SELECT id, name, price, category_id,
        available, stock_qty, discount,
        has_temp_option, has_sizes
    FROM dbo.menu_items
    WHERE is_archived = 0
      AND available = 1
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

$sizes = $connection->query("
    SELECT id, name, price_adjustment, menu_item_id
    FROM dbo.size_modifiers
    ORDER BY price_adjustment
")->fetchAll(PDO::FETCH_ASSOC);
?>
