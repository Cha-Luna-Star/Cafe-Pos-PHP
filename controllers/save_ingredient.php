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

    $name = trim((string)($data['name'] ?? ''));
    $unit = $data['unit'] ?? '';
    $threshold = $data['threshold'] ?? null;
    $cost = $data['cost'] ?? null;

    if ($name === '' || strlen($name) > 100) {
        throw new RuntimeException('Invalid ingredient name.');
    }

    if (!in_array($unit, ['g', 'kg', 'ml', 'L', 'pcs'], true)) {
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

    $connection->beginTransaction();

    $stmt = $connection->prepare("
        SELECT id
        FROM dbo.ingredients WITH (UPDLOCK, HOLDLOCK)
        WHERE LOWER(name) = LOWER(?)
    ");

    $stmt->execute([$name]);
    $existing = $stmt->fetchColumn();
    $stmt->closeCursor();

    if ($existing !== false) {
        throw new RuntimeException('Ingredient already exists.');
    }

    $stmt = $connection->prepare("
        INSERT INTO dbo.ingredients
        (
            name,
            unit,
            stock_qty,
            low_stock_threshold,
            cost_per_unit,
            initial_quantity
        )
        VALUES (?, ?, 0, ?, ?, 0)
    ");

    $stmt->execute([
        $name,
        $unit,
        $threshold,
        $cost
    ]);

    $connection->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Ingredient added successfully.'
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
            : 'Unable to save ingredient.'
    ]);
}