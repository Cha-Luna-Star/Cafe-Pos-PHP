
<?php
require_once __DIR__ . '/../includes/inventory_dates.php';
require_once __DIR__ . '/../config/database.php';

$categories = $connection->query('EXEC dbo.GetMenuCategories')->fetchAll(PDO::FETCH_ASSOC);

$stmt = $connection->prepare('EXEC dbo.GetPosMenu ?');
$stmt->execute([inventoryToday()->format('Y-m-d')]);
$menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt->closeCursor();

$sizes = $connection->query('EXEC dbo.GetSizeModifiers')->fetchAll(PDO::FETCH_ASSOC);
?>
