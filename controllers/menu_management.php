<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/product_stock.php';

$categories = $connection->query('EXEC dbo.GetMenuCategories')->fetchAll(PDO::FETCH_ASSOC);

$canSetProductThreshold = hasProductLowStockThreshold($connection);

$menuItems = $connection->query('EXEC dbo.GetManagedMenuItems')->fetchAll(PDO::FETCH_ASSOC);