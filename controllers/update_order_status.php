<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in.']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Invalid request method.');
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!is_array($data)) {
        throw new RuntimeException('Invalid request.');
    }

    $token = $data['csrf_token'] ?? '';

    if (!is_string($token) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $token)) {
        throw new RuntimeException('Invalid security token.');
    }

    $orderId = filter_var($data['order_id'] ?? null, FILTER_VALIDATE_INT);
    $action = $data['action'] ?? '';

    if (!$orderId || $orderId <= 0 ||
        !in_array($action, ['Completed', 'Cancelled'], true)) {
        throw new RuntimeException('Invalid order or action.');
    }

    $connection->beginTransaction();

    $stmt = $connection->prepare("
        SELECT status
        FROM dbo.orders WITH (UPDLOCK, HOLDLOCK)
        WHERE id = ?
    ");
    $stmt->execute([$orderId]);
    $status = $stmt->fetchColumn();
    $stmt->closeCursor();

    if ($status === false) {
        throw new RuntimeException('Order not found.');
    }

    if ($status !== 'Pending') {
        throw new RuntimeException('Only pending orders can be updated.');
    }

    $stmt = $connection->prepare("
        UPDATE dbo.orders
        SET status = ?
        WHERE id = ? AND status = 'Pending'
    ");
    $stmt->execute([$action, $orderId]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Unable to update order.');
    }

    $stmt = $connection->prepare("
        INSERT INTO dbo.audit_log
        (user_id, username, action, details, created_at)
        VALUES (?, ?, ?, ?, SYSDATETIME())
    ");

    $stmt->execute([
        $_SESSION['user_id'],
        $_SESSION['username'] ?? '',
        'UPDATE_ORDER_STATUS',
        "Order #$orderId changed to $action"
    ]);

    $connection->commit();

    echo json_encode([
        'success' => true,
        'message' => "Order $action successfully."
    ]);

} catch (Throwable $e) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Unable to update order.'
    ]);

    if (!($e instanceof RuntimeException)) {
        error_log($e->getMessage());
    }
}