<?php
session_start();
header("Content-Type: application/json");

if (!isset($_SESSION['user_id']) ||
    !in_array($_SESSION['role'] ?? '', ['Admin', 'Manager'], true)) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Permission denied."]);
    exit;
}

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data) ||
    !hash_equals($_SESSION['csrf_token'] ?? '', $data['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Invalid request token."]);
    exit;
}

$id = (int)($data['id'] ?? 0);
$action = $data['action'] ?? '';

if ($id <= 0 || !in_array($action, ['archive', 'restore'], true)) {
    http_response_code(422);
    echo json_encode(["success" => false, "message" => "Invalid action."]);
    exit;
}

try {
    $stmt = $connection->prepare("
        UPDATE dbo.menu_items
        SET is_archived = ?
        WHERE id = ?
    ");

    $stmt->execute([$action === 'archive' ? 1 : 0, $id]);

    echo json_encode(["success" => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Unable to update item."]);
}