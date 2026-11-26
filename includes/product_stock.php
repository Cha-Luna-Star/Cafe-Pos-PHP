<?php

const DEFAULT_PRODUCT_LOW_STOCK_THRESHOLD = 10;

function hasProductLowStockThreshold(PDO $connection): bool
{
    return (int)$connection->query('EXEC dbo.HasProductLowStockThreshold')->fetchColumn() === 1;
}
