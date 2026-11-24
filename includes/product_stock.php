<?php

const DEFAULT_PRODUCT_LOW_STOCK_THRESHOLD = 10;

function hasProductLowStockThreshold(PDO $connection): bool
{
    return (int)$connection->query("
        SELECT CASE WHEN COL_LENGTH('dbo.menu_items', 'low_stock_threshold') IS NULL
            THEN 0 ELSE 1 END
    ")->fetchColumn() === 1;
}
