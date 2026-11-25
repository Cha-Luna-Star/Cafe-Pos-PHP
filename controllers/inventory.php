<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/product_stock.php';

$ingredients = $connection->query("
    SELECT id, name, unit, stock_qty,
           low_stock_threshold, cost_per_unit
    FROM dbo.ingredients
    WHERE is_archived = 0
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

$archivedIngredients = $connection->query("
    SELECT id, name, unit, low_stock_threshold, cost_per_unit
    FROM dbo.ingredients
    WHERE is_archived = 1
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

$productThresholdColumn = hasProductLowStockThreshold($connection)
    ? 'low_stock_threshold'
    : DEFAULT_PRODUCT_LOW_STOCK_THRESHOLD;

$products = $connection->query("
    SELECT id, name, stock_qty, $productThresholdColumn AS low_stock_threshold
    FROM dbo.menu_items
    WHERE is_archived = 0
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

$batchValueExpression = "
    CASE
        WHEN b.quantity = 0 THEN 0
        WHEN TRY_CONVERT(decimal(18,3), b.quantity) > 0
            AND TRY_CONVERT(decimal(18,6), b.cost_per_unit) > 0
        THEN ROUND(TRY_CONVERT(decimal(18,3), b.quantity) *
            TRY_CONVERT(decimal(18,6), b.cost_per_unit), 2)
    END AS remaining_value
";

$batches = $connection->query("
    SELECT
        'Ingredient' AS item_type,
        b.ingredient_id AS item_id,
        b.id,
        i.name AS item_name,
        i.is_archived,
        b.quantity,
        b.cost_per_unit,
        $batchValueExpression,
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
        m.is_archived,
        b.quantity,
        b.cost_per_unit,
        $batchValueExpression,
        CONVERT(NVARCHAR(30), b.received_date, 23),
        CONVERT(NVARCHAR(30), b.expiration_date, 23),
        b.supplier
    FROM dbo.product_batches b
    JOIN dbo.menu_items m ON b.menu_item_id = m.id
")->fetchAll(PDO::FETCH_ASSOC);

$inventoryValue = ['Ingredient' => '0.00', 'Product' => '0.00'];
$uncostedBatches = 0;
foreach ($batches as $batch) {
    if ($batch['remaining_value'] === null && (float)$batch['quantity'] > 0) {
        $uncostedBatches++;
    } elseif ($batch['remaining_value'] !== null) {
        $type = $batch['item_type'];
        $inventoryValue[$type] = bcadd($inventoryValue[$type], $batch['remaining_value'], 2);
    }
}
$totalInventoryValue = bcadd($inventoryValue['Ingredient'], $inventoryValue['Product'], 2);

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
        CONVERT(varchar(27), sm.created_at, 126) AS sort_timestamp,
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

$reconciliation = $connection->query("
    SELECT
        'Ingredient' AS item_type,
        i.id,
        i.name,
        TRY_CONVERT(decimal(18,3), i.stock_qty) AS recorded_stock,
        TRY_CONVERT(decimal(18,3), COALESCE(b.batch_stock, 0)) AS batch_stock,
        TRY_CONVERT(decimal(18,3), i.stock_qty) - TRY_CONVERT(decimal(18,3), COALESCE(b.batch_stock, 0)) AS difference
    FROM dbo.ingredients i
    LEFT JOIN (
        SELECT ingredient_id, SUM(TRY_CONVERT(decimal(18,3), quantity)) AS batch_stock
        FROM dbo.inventory_batches
        GROUP BY ingredient_id
    ) b ON i.id = b.ingredient_id
    WHERE i.is_archived = 0

    UNION ALL

    SELECT
        'Product' AS item_type,
        m.id,
        m.name,
        TRY_CONVERT(decimal(18,3), m.stock_qty) AS recorded_stock,
        TRY_CONVERT(decimal(18,3), COALESCE(b.batch_stock, 0)) AS batch_stock,
        TRY_CONVERT(decimal(18,3), m.stock_qty) - TRY_CONVERT(decimal(18,3), COALESCE(b.batch_stock, 0)) AS difference
    FROM dbo.menu_items m
    LEFT JOIN (
        SELECT menu_item_id, SUM(TRY_CONVERT(decimal(18,3), quantity)) AS batch_stock
        FROM dbo.product_batches
        GROUP BY menu_item_id
    ) b ON m.id = b.menu_item_id
    WHERE m.is_archived = 0

    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

$discrepancies = array_filter(
    $reconciliation,
    fn($item) => abs((float)$item['difference']) >= 0.001
);

if (!empty($canManageInventory)) {
    $reconciliationHistory = $connection->query("
        SELECT
            r.item_type,
            r.item_id,
            COALESCE(i.name, m.name) AS item_name,
            r.previous_stock,
            r.corrected_stock,
            r.reason,
            r.user_id,
            u.username,
            CONVERT(varchar(19), r.created_at, 120) AS corrected_at
        FROM dbo.stock_reconciliations r
        LEFT JOIN dbo.ingredients i
            ON r.item_type = 'Ingredient' AND r.item_id = i.id
        LEFT JOIN dbo.menu_items m
            ON r.item_type = 'Product' AND r.item_id = m.id
        LEFT JOIN dbo.users u ON r.user_id = u.id
        ORDER BY r.created_at DESC, r.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
}
