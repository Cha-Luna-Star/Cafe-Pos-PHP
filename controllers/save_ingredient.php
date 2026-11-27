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

    $id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);

    if ($id === false || $id < 0) {
        throw new RuntimeException('Invalid ingredient ID.');
    }
    $name = trim((string)($data['name'] ?? ''));
    $unit = $data['unit'] ?? '';
    $threshold = $data['threshold'] ?? null;
    $cost = $data['cost'] ?? null;

    if ($name === '' || strlen($name) > 100) {
        throw new RuntimeException('Invalid ingredient name.');
    }

    if ($id === 0 && !in_array($unit, ['g', 'kg', 'ml', 'L', 'pcs'], true)) {
        throw new RuntimeException('Invalid unit.');
    }

    if (
        !is_numeric($threshold) ||
        !is_finite((float)$threshold) ||
        (float)$threshold < 0 ||
        !is_numeric($cost) ||
        !is_finite((float)$cost) ||
        (float)$cost < 0
    ) {
        throw new RuntimeException('Invalid threshold or cost.');
    }

    $throwDatabaseConnectionError = true;
    require_once __DIR__ . '/../config/database.php';
    $connection->beginTransaction();

    $stmt = $connection->prepare('EXEC dbo.FindDuplicateIngredient @Name = ?, @ItemId = ?');

    $stmt->execute([$name, $id]);
    $existing = $stmt->fetchColumn();
    $stmt->closeCursor();

    if ($existing !== false) {
        throw new RuntimeException('Ingredient already exists.');
    }

    if ($id > 0) {
        $stmt = $connection->prepare('EXEC dbo.FindIngredientForEdit @ItemId = ?');

        $stmt->execute([$id]);
        $ingredientExists = $stmt->fetchColumn();
        $stmt->closeCursor();

        if ($ingredientExists === false) {
            throw new RuntimeException('Ingredient not found.');
        }

        $stmt = $connection->prepare('EXEC dbo.UpdateIngredient ?, ?, ?, ?');

        $stmt->execute([
            $name,
            $threshold,
            $cost,
            $id
        ]);

        $auditAction = 'Inventory Ingredient Updated';
        $message = 'Ingredient updated successfully.';
    } else {
        $stmt = $connection->prepare('EXEC dbo.CreateIngredient ?, ?, ?, ?');

        $stmt->execute([
            $name,
            $unit,
            $threshold,
            $cost
        ]);

        $id = $stmt->fetchColumn();
        $stmt->closeCursor();
        if ($id === false) {
            throw new RuntimeException('The new ingredient ID was unavailable.');
        }

        $auditAction = 'Inventory Add';
        $message = 'Ingredient added successfully.';
    }

    recordAuditEvent($connection, $auditAction, "Ingredient #$id ($name). Low stock threshold: $threshold. Unit cost: $cost PHP.");
    $connection->commit();

    echo json_encode([
        'success' => true,
        'message' => $message
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
            : 'Unable to save ingredient.'
    ]);
}