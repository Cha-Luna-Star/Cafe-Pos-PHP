<?php
session_start();
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
        "SELECT COUNT(*) FROM dbo.categories WHERE id = ?"
    );
    $categoryStmt->execute([$categoryId]);

    if (!$categoryStmt->fetchColumn()) {
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

    $params = [$name, (float)$price, $categoryId, $available, $hasTemp, $hasSizes];
    if ($id > 0) {
        $thresholdUpdate = '';
        if ($canSetProductThreshold && $threshold !== null) {
            $thresholdUpdate = ', low_stock_threshold = ?';
            $params[] = $threshold;
        }
        $stmt = $connection->prepare("
            UPDATE dbo.menu_items
            SET name = ?,
                price = ?,
                category_id = ?,
                available = ?,
                has_temp_option = ?,
                has_sizes = ?$thresholdUpdate
            WHERE id = ?
        ");

        $params[] = $id;
        $stmt->execute($params);
        if ($stmt->rowCount() !== 1) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Menu item not found."]);
            exit;
        }

        $message = "Item updated successfully.";
    } else {
        $thresholdColumn = '';
        $thresholdPlaceholder = '';
        if ($canSetProductThreshold && $threshold !== null) {
            $thresholdColumn = ', low_stock_threshold';
            $thresholdPlaceholder = ', ?';
            $params[] = $threshold;
        }
        $stmt = $connection->prepare("
            INSERT INTO dbo.menu_items
            (name, price, category_id, available, stock_qty,
             discount, has_temp_option, has_sizes, is_archived$thresholdColumn)
            VALUES (?, ?, ?, ?, 0, 0, ?, ?, 0$thresholdPlaceholder)
        ");

        $stmt->execute($params);

        $message = "Item added successfully.";
    }

    echo json_encode(["success" => true, "message" => $message]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Unable to save item."]);
}