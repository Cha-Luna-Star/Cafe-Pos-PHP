<?php
require_once __DIR__ . '/../config/database.php';

$ingredients = $connection->query('EXEC dbo.GetActiveIngredients')->fetchAll(PDO::FETCH_ASSOC);

$archivedIngredients = $connection->query('EXEC dbo.GetArchivedIngredients')->fetchAll(PDO::FETCH_ASSOC);

$products = $connection->query('EXEC dbo.GetInventoryProducts')->fetchAll(PDO::FETCH_ASSOC);

$batches = $connection->query('EXEC dbo.GetInventoryBatches')->fetchAll(PDO::FETCH_ASSOC);

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

$movements = $connection->query('EXEC dbo.GetInventoryMovements')->fetchAll(PDO::FETCH_ASSOC);

$reconciliation = $connection->query('EXEC dbo.GetStockReconciliation')->fetchAll(PDO::FETCH_ASSOC);

$discrepancies = array_filter(
    $reconciliation,
    fn($item) => abs((float)$item['difference']) >= 0.001
);

if (!empty($canManageInventory)) {
    $reconciliationHistory = $connection->query('EXEC dbo.GetReconciliationHistory')->fetchAll(PDO::FETCH_ASSOC);
}
