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

require_once __DIR__ . '/../config/database.php';

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

    $connection->beginTransaction();

    $stmt = $connection->prepare("
        SELECT stock_qty
        FROM dbo.ingredients WITH (UPDLOCK, HOLDLOCK)
        WHERE id = ?
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

    $tables = [
        'recipe_items',
        'inventory_batches',
        'ingredient_usage'
    ];

    foreach ($tables as $table) {
        $stmt = $connection->prepare("
            SELECT COUNT(*)
            FROM dbo.$table WITH (UPDLOCK, HOLDLOCK)
            WHERE ingredient_id = ?
        ");

        $stmt->execute([$id]);
        $count = $stmt->fetchColumn();
        $stmt->closeCursor();

        if ($count > 0) {
            throw new RuntimeException(
                'Ingredient has existing records and cannot be deleted.'
            );
        }
    }

    $stmt = $connection->prepare("
        SELECT COUNT(*)
        FROM dbo.stock_movements WITH (UPDLOCK, HOLDLOCK)
        WHERE item_type = 'Ingredient' AND item_id = ?
    ");

    $stmt->execute([$id]);
    $count = $stmt->fetchColumn();
    $stmt->closeCursor();

    if ($count > 0) {
        throw new RuntimeException('Ingredient has stock movement history.');
    }

    $stmt = $connection->prepare("
        DELETE FROM dbo.ingredients
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    $connection->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Ingredient deleted successfully.'
    ]);

} catch (Throwable $e) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }

    http_response_code(400);

    if ($e instanceof PDOException) {
        error_log($e->getMessage());
    }

    echo json_encode([
        'success' => false,
        'message' => $e instanceof RuntimeException &&
                     !($e instanceof PDOException)
            ? $e->getMessage()
            : 'Unable to delete ingredient.'
    ]);
}