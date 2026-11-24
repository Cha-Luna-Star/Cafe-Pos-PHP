<?php
session_start();
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
        UPDATE dbo.ingredients
        SET is_archived = 0
        OUTPUT INSERTED.name
        WHERE id = ? AND is_archived = 1
    ");
    $stmt->execute([$id]);
    $name = $stmt->fetchColumn();
    $stmt->closeCursor();

    if ($name === false) {
        throw new RuntimeException('Archived ingredient not found.');
    }

    recordAuditEvent($connection, 'Inventory Restored', "Restored ingredient #$id ($name).");
    $connection->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Ingredient restored successfully.'
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
            : 'Unable to restore ingredient.'
    ]);
}