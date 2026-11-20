<?php
require_once __DIR__ . '/../config/database.php';

$ingredients = $connection->query("
    SELECT id, name, unit, stock_qty, low_stock_threshold
    FROM dbo.ingredients
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

$products = $connection->query("
    SELECT id, name, stock_qty
    FROM dbo.menu_items
    WHERE is_archived = 0
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

$batches = $connection->query("
    SELECT
        'Ingredient' AS item_type,
        b.ingredient_id AS item_id,
        b.id,
        i.name AS item_name,
        b.quantity,
        b.cost_per_unit,
        b.received_date,
        b.expiration_date,
        b.supplier
    FROM dbo.inventory_batches b
    JOIN dbo.ingredients i ON b.ingredient_id = i.id

    UNION ALL

    SELECT
        'Product' AS item_type,
        b.menu_item_id AS item_id,
        b.id,
        m.name AS item_name,
        b.quantity,
        b.cost_per_unit,
        CONVERT(NVARCHAR(30), b.received_date, 23),
        CONVERT(NVARCHAR(30), b.expiration_date, 23),
        b.supplier
    FROM dbo.product_batches b
    JOIN dbo.menu_items m ON b.menu_item_id = m.id
")->fetchAll(PDO::FETCH_ASSOC);

$movements = $connection->query("
    SELECT
        sm.id,
        sm.item_type,
        CASE
            WHEN sm.item_type = 'Ingredient' THEN i.name
            WHEN sm.item_type = 'Product' THEN m.name
        END AS item_name,
        sm.movement_type,
        sm.quantity,
        sm.batch_id,
        sm.remarks,
        sm.created_at,
        u.username
    FROM dbo.stock_movements sm
    LEFT JOIN dbo.ingredients i
        ON sm.item_type = 'Ingredient' AND sm.item_id = i.id
    LEFT JOIN dbo.menu_items m
        ON sm.item_type = 'Product' AND sm.item_id = m.id
    LEFT JOIN dbo.users u
        ON sm.user_id = u.id
    ORDER BY sm.created_at DESC, sm.id DESC
")->fetchAll(PDO::FETCH_ASSOC);