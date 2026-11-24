<?php
require_once __DIR__ . '/../includes/auth_session.php';
startAuthSession();
header('Content-Type: application/json');

if (
    !isset($_SESSION['user_id']) ||
    !in_array($_SESSION['role'] ?? '', ['Admin', 'Manager'], true)
) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Permission denied.'
    ]);
    exit;
}

require_once __DIR__ . '/../includes/audit_event.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Invalid request method.');
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (
        !is_array($data) ||
        empty($_SESSION['csrf_token']) ||
        !is_string($data['csrf_token'] ?? null) ||
        !hash_equals($_SESSION['csrf_token'], $data['csrf_token'])
    ) {
        throw new RuntimeException('Invalid security token.');
    }

    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);

    if ($id === false || $id <= 0) {
        throw new RuntimeException('Invalid ingredient ID.');
    }

    $throwDatabaseConnectionError = true;
    require_once __DIR__ . '/../config/database.php';
    $connection->beginTransaction();

    $stmt = $connection->prepare("
        SELECT name, stock_qty
        FROM dbo.ingredients WITH (UPDLOCK, HOLDLOCK)
        WHERE id = ? AND is_archived = 0
    ");

    $stmt->execute([$id]);
    $ingredient = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$ingredient) {
        throw new RuntimeException('Ingredient not found.');
    }

    if ((float)$ingredient['stock_qty'] != 0) {
        throw new RuntimeException('Ingredient still has stock.');
    }

    $stmt = $connection->prepare("
        SELECT COUNT(*)
        FROM dbo.inventory_batches WITH (UPDLOCK, HOLDLOCK)
        WHERE ingredient_id = ? AND quantity > 0
    ");

    $stmt->execute([$id]);
    $count = $stmt->fetchColumn();
    $stmt->closeCursor();

    if ($count > 0) {
        throw new RuntimeException('Ingredient still has remaining batches.');
    }

    $stmt = $connection->prepare("
        SELECT COUNT(*)
        FROM dbo.recipe_items WITH (UPDLOCK, HOLDLOCK)
        WHERE ingredient_id = ?
    ");

    $stmt->execute([$id]);
    $count = $stmt->fetchColumn();
    $stmt->closeCursor();

    if ($count > 0) {
        throw new RuntimeException('Ingredient is still used in recipes.');
    }

    $stmt = $connection->prepare("
        SELECT TOP (1) 1
        FROM dbo.ingredient_usage iu
        JOIN dbo.orders o ON o.id = iu.order_id
        WHERE iu.ingredient_id = ? AND o.status = 'Pending'
    ");
    $stmt->execute([$id]);
    $usedByPendingOrder = $stmt->fetchColumn() !== false;
    $stmt->closeCursor();

    if ($usedByPendingOrder) {
        throw new RuntimeException('Ingredient is used by a pending order.');
    }

    $stmt = $connection->prepare("
        UPDATE dbo.ingredients
        SET is_archived = 1
        WHERE id = ?
    ");

    $stmt->execute([$id]);
    recordAuditEvent($connection, 'Inventory Archived', "Archived ingredient #$id ({$ingredient['name']}).");
    $connection->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Ingredient archived successfully.'
    ]);

} catch (Throwable $e) {
    if (isset($connection) && $connection->inTransaction()) {
        $connection->rollBack();
    }

    $isRequestError = $e instanceof RuntimeException && !($e instanceof PDOException);
    http_response_code($isRequestError ? 400 : 500);

    if (!$isRequestError) {
        error_log($e->getMessage());
    }

    echo json_encode([
        'success' => false,
        'message' => $isRequestError
            ? $e->getMessage()
            : 'Unable to archive ingredient.'
    ]);
}