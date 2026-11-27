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

    $stmt = $connection->prepare('EXEC dbo.GetIngredientForDeletion @ItemId = ?');

    $stmt->execute([$id]);
    $ingredient = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$ingredient) {
        throw new RuntimeException('Ingredient not found.');
    }

    if ((float)$ingredient['stock_qty'] != 0) {
        throw new RuntimeException('Ingredient still has stock.');
    }

    $checks = [
        'EXEC dbo.CountIngredientRecipeReferences @ItemId = ?',
        'EXEC dbo.CountIngredientBatchReferences @ItemId = ?',
        'EXEC dbo.CountIngredientUsageReferences @ItemId = ?',
    ];

    foreach ($checks as $procedure) {
        $stmt = $connection->prepare($procedure);

        $stmt->execute([$id]);
        $count = $stmt->fetchColumn();
        $stmt->closeCursor();

        if ($count > 0) {
            throw new RuntimeException(
                'Ingredient has existing records and cannot be deleted.'
            );
        }
    }

    $stmt = $connection->prepare('EXEC dbo.CountIngredientMovementReferences @ItemId = ?');

    $stmt->execute([$id]);
    $count = $stmt->fetchColumn();
    $stmt->closeCursor();

    if ($count > 0) {
        throw new RuntimeException('Ingredient has stock movement history.');
    }

    $stmt = $connection->prepare('EXEC dbo.DeleteIngredient ?');

    $stmt->execute([$id]);

    recordAuditEvent($connection, 'Inventory Deleted', "Deleted ingredient #$id ({$ingredient['name']}).");
    $connection->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Ingredient deleted successfully.'
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
            : 'Unable to delete ingredient.'
    ]);
}