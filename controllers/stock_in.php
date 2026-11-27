<?php
require_once __DIR__ . '/../includes/auth_session.php';
startAuthSession();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) ||
    !in_array($_SESSION['role'] ?? '', ['Admin', 'Manager'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied.']);
    exit;
}

require_once __DIR__ . '/../includes/mutation_request.php';
require_once __DIR__ . '/../includes/inventory_dates.php';
require_once __DIR__ . '/../includes/audit_event.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Invalid request method.');
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!is_array($data) || !hasValidCsrfToken($data['csrf_token'] ?? null)) {
        throw new RuntimeException('Invalid security token.');
    }

    $type = $data['item_type'] ?? '';
    $id = requestSqlInt($data['item_id'] ?? null);
    $quantity = $data['quantity'] ?? null;
    $cost = $data['cost_per_unit'] ?? null;
    $received = $data['received_date'] ?? '';
    $expiration = $data['expiration_date'] ?? null;
    $supplier = $data['supplier'] ?? '';
    $notes = $data['notes'] ?? '';

    if (!in_array($type, ['Ingredient', 'Product'], true) ||
        $id === false || !is_string($supplier) || !is_string($notes) ||
        !is_numeric($quantity) ||
        !is_finite((float)$quantity) ||
        (float)$quantity <= 0 ||
        !is_numeric($cost) ||
        !is_finite((float)$cost) ||
        (float)$cost < 0) {
        throw new RuntimeException('Invalid stock details.');
    }

    if ($type === 'Product' &&
        ((float)$quantity != (int)$quantity ||
         (float)$quantity > 2147483647)) {
        throw new RuntimeException('Product quantity must be a whole number.');
    }

    if ($type === 'Ingredient' && ((float)$quantity < 0.001 || round((float)$quantity, 3) !== (float)$quantity)) {
        throw new RuntimeException('Ingredient quantity must use at most 3 decimal places.');
    }

    $supplier = trim($supplier);
    $notes = trim($notes);
    if (strlen($supplier) > 150 || strlen($notes) > 500) {
        throw new RuntimeException('Supplier or notes are too long.');
    }

    $parseDate = function ($value) {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value) || parseInventoryDate($value) === null) {
            throw new RuntimeException('Invalid date.');
        }

        return $value;
    };

    $received = $parseDate($received);
    $expiration = $parseDate($expiration);

    if (!$received) {
        throw new RuntimeException('Received date is required.');
    }

    if ($received > inventoryToday()->format('Y-m-d')) {
        throw new RuntimeException('Received date cannot be in the future.');
    }

    if ($expiration && $expiration < $received) {
        throw new RuntimeException('Expiration cannot precede delivery.');
    }

    $throwDatabaseConnectionError = true;
    require_once __DIR__ . '/../config/database.php';
    $connection->beginTransaction();

    if ($type === 'Ingredient') {
        $stmt = $connection->prepare('EXEC dbo.GetStockInIngredient @ItemId = ?');
    } else {
        $stmt = $connection->prepare('EXEC dbo.GetStockInProduct @ItemId = ?');
    }

    $stmt->execute([$id]);

    $itemName = $stmt->fetchColumn();
    $stmt->closeCursor();
    if ($itemName === false) {
        throw new RuntimeException('Item not found.');
    }

    if ($type === 'Ingredient') {
        $stmt = $connection->prepare('EXEC dbo.CreateIngredientDelivery ?, ?, ?, ?, ?, ?, ?');

        $stmt->execute([
            $id, $quantity, $cost, $received,
            $expiration, $supplier, $notes
        ]);
    } else {
        $stmt = $connection->prepare('EXEC dbo.CreateProductDelivery ?, ?, ?, ?, ?, ?, ?');

        $stmt->execute([
            $id, $quantity, $cost, $received,
            $expiration, $supplier, $notes
        ]);
    }

    $batchId = (int)$stmt->fetchColumn();
    $stmt->closeCursor();

    $stmt = $connection->prepare($type === 'Ingredient' ? 'EXEC dbo.AddRecordedStockIngredient ?, ?' : 'EXEC dbo.AddRecordedStockProduct ?, ?');

    $stmt->execute([$quantity, $id]);

    $stmt = $connection->prepare('EXEC dbo.WriteDeliveryMovement ?, ?, ?, ?, ?, ?');

    $stmt->execute([
        $type,
        $id,
        $batchId,
        $quantity,
        $notes,
        $_SESSION['user_id']
    ]);

    $amount = number_format((float)$quantity, $type === 'Product' ? 0 : 3, '.', '');
    recordAuditEvent(
        $connection,
        'Inventory Restock',
        "Received $amount for $type #$id ($itemName), batch #$batchId."
    );

    $connection->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Stock added successfully.'
    ]);

} catch (Throwable $e) {
    $isRequestError = $e instanceof RuntimeException && !($e instanceof PDOException);
    if (isset($connection) && $connection->inTransaction()) {
        $connection->rollBack();
    }

    http_response_code($isRequestError ? 400 : 500);

    echo json_encode([
        'success' => false,
        'message' => $isRequestError
            ? $e->getMessage()
            : 'Unable to add stock.'
    ]);

    if (!$isRequestError) {
        error_log($e->getMessage());
    }
}