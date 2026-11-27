<?php
require_once __DIR__ . '/../includes/auth_session.php';
startAuthSession();
header("Content-Type: application/json");

if (!isset($_SESSION['user_id']) ||
    !in_array($_SESSION['role'] ?? '', ['Admin', 'Manager'], true)) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Permission denied."]);
    exit;
}

require_once __DIR__ . '/../includes/mutation_request.php';
require_once __DIR__ . '/../includes/audit_event.php';

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
    $connection->beginTransaction();

    $archived = $action === 'archive' ? 1 : 0;
    $stmt = $connection->prepare('EXEC dbo.SetMenuItemArchiveStatus ?, ?');

    $stmt->execute([$archived, $id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$item) {
        $connection->rollBack();
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Menu item not found."]);
        exit;
    }

    if ($item['previous_archived'] === null || (int)$item['previous_archived'] !== $archived) {
        $auditAction = $archived ? 'Menu Item Archived' : 'Menu Item Restored';
        recordAuditEvent($connection, $auditAction, "Menu item #$id ({$item['name']}).");
    }
    $connection->commit();

    echo json_encode(["success" => true]);
} catch (Throwable $e) {
    if (isset($connection) && $connection->inTransaction()) {
        $connection->rollBack();
    }

    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Unable to update item."]);
}