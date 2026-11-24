<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/product_stock.php';

$categories = $connection->query("
    SELECT id, name
    FROM dbo.categories
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

$canSetProductThreshold = hasProductLowStockThreshold($connection);
$productThresholdColumn = $canSetProductThreshold ? 'm.low_stock_threshold' : DEFAULT_PRODUCT_LOW_STOCK_THRESHOLD;

$menuItems = $connection->query("
    SELECT
        m.id,
        m.name,
        m.price,
        m.category_id,
        c.name AS category_name,
        m.available,
        m.stock_qty,
        $productThresholdColumn AS low_stock_threshold,
        m.discount,
        m.has_temp_option,
        m.has_sizes,
        m.is_archived
    FROM dbo.menu_items m
    LEFT JOIN dbo.categories c
        ON m.category_id = c.id
    ORDER BY m.is_archived, m.name
")->fetchAll(PDO::FETCH_ASSOC);