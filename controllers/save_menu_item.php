<?php
require_once __DIR__ . '/../includes/auth_session.php';
startAuthSession();
header("Content-Type: application/json");

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized."]);
    exit;
}

if (!in_array($_SESSION['role'] ?? '', ['Admin', 'Manager'], true)) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Permission denied."]);
    exit;
}

require_once __DIR__ . '/../includes/mutation_request.php';
require_once __DIR__ . '/../includes/product_stock.php';
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

$id = requestSqlInt($data['id'] ?? 0, 0);
$name = is_string($data['name'] ?? null) ? trim($data['name']) : '';
$price = $data['price'] ?? null;
$categoryId = requestSqlInt($data['category_id'] ?? null);

if ($id === false || $name === '' || strlen($name) > 150 ||
    !is_numeric($price) || !is_finite((float)$price) ||
    (float)$price < 0 || $categoryId === false) {
    http_response_code(422);
    echo json_encode(["success" => false, "message" => "Invalid item details."]);
    exit;
}

$threshold = null;
if (array_key_exists('low_stock_threshold', $data)) {
    $threshold = $data['low_stock_threshold'];
    if (!is_int($threshold) || $threshold < 0 || $threshold > 2147483647) {
        http_response_code(422);
        echo json_encode(["success" => false, "message" => "Low stock threshold must be a nonnegative whole number."]);
        exit;
    }
}

$isDrink = in_array($categoryId, [1, 2], true);

$available = !empty($data['available']) ? 1 : 0;
$hasTemp = $isDrink && !empty($data['has_temp_option']) ? 1 : 0;
$hasSizes = $isDrink ? 1 : 0;

try {
    $throwDatabaseConnectionError = true;
    require_once __DIR__ . '/../config/database.php';
    $categoryStmt = $connection->prepare(
        'EXEC dbo.CountMenuCategory @CategoryId = ?'
    );
    $categoryStmt->execute([$categoryId]);

    $categoryExists = (int)$categoryStmt->fetchColumn() > 0;
    $categoryStmt->closeCursor();
    if (!$categoryExists) {
        http_response_code(422);
        echo json_encode(["success" => false, "message" => "Invalid category."]);
        exit;
    }

    $canSetProductThreshold = hasProductLowStockThreshold($connection);
    if ($threshold !== null && !$canSetProductThreshold) {
        http_response_code(409);
        echo json_encode(["success" => false, "message" => "Apply the product threshold database migration first."]);
        exit;
    }

    $connection->beginTransaction();
    $useThreshold = $canSetProductThreshold && $threshold !== null;
    $params = [$name, (float)$price, $categoryId, $available, $hasTemp, $hasSizes];
    if ($id > 0) {
        if ($useThreshold) {
            $params[] = $threshold;
        }
        $stmt = $connection->prepare($useThreshold
            ? 'EXEC dbo.UpdateMenuItemWithThreshold ?, ?, ?, ?, ?, ?, ?, ?'
            : 'EXEC dbo.UpdateMenuItem ?, ?, ?, ?, ?, ?, ?');

        $params[] = $id;
        $stmt->execute($params);
        if ($stmt->rowCount() !== 1) {
            $connection->rollBack();
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Menu item not found."]);
            exit;
        }

        $auditAction = "Menu Item Updated";
        $message = "Item updated successfully.";
    } else {
        if ($useThreshold) {
            $params[] = $threshold;
        }
        $stmt = $connection->prepare($useThreshold
            ? 'EXEC dbo.CreateMenuItemWithThreshold ?, ?, ?, ?, ?, ?, ?'
            : 'EXEC dbo.CreateMenuItem ?, ?, ?, ?, ?, ?');

        $stmt->execute($params);

        $id = $stmt->fetchColumn();
        $stmt->closeCursor();
        if ($id === false) {
            throw new RuntimeException('The new menu item ID was unavailable.');
        }

        $auditAction = "Menu Item Created";
        $message = "Item added successfully.";
    }

    $amount = number_format((float)$price, 2, '.', '');
    $availability = $available ? 'Available' : 'Unavailable';
    $details = "Menu item #$id ($name). Price: $amount PHP. Category #$categoryId. $availability.";
    if ($threshold !== null) {
        $details .= " Low stock threshold: $threshold.";
    }
    recordAuditEvent($connection, $auditAction, $details);
    $connection->commit();

    echo json_encode(["success" => true, "message" => $message]);
} catch (Throwable $e) {
    if (isset($connection) && $connection->inTransaction()) {
        $connection->rollBack();
    }

    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Unable to save item."]);
}