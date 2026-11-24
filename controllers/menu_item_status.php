<?php
session_start();
header("Content-Type: application/json");

if (!isset($_SESSION['user_id']) ||
    !in_array($_SESSION['role'] ?? '', ['Admin', 'Manager'], true)) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Permission denied."]);
    exit;
}

require_once __DIR__ . '/../includes/mutation_request.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data) || !hasValidCsrfToken($data['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Invalid request token."]);
    exit;
}

$id = requestSqlInt($data['id'] ?? null);
$action = $data['action'] ?? '';

if ($id === false || !in_array($action, ['archive', 'restore'], true)) {
    http_response_code(422);
    echo json_encode(["success" => false, "message" => "Invalid action."]);
    exit;
}

try {
    $throwDatabaseConnectionError = true;
    require_once __DIR__ . '/../config/database.php';
    $stmt = $connection->prepare("
        UPDATE dbo.menu_items
        SET is_archived = ?
        WHERE id = ?
    ");

    $stmt->execute([$action === 'archive' ? 1 : 0, $id]);

    if ($stmt->rowCount() !== 1) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Menu item not found."]);
        exit;
    }

    echo json_encode(["success" => true]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Unable to update item."]);
}