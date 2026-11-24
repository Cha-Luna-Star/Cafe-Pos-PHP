<?php
require_once __DIR__ . '/../includes/auth_session.php';
startAuthSession();
header('Content-Type: application/json');

function reconciliationResponse(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['success' => $status === 200, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    reconciliationResponse(405, 'Invalid request method.');
}

$userId = $_SESSION['user_id'] ?? null;
if (!$userId || !in_array($_SESSION['role'] ?? '', ['Admin', 'Manager'], true)) {
    reconciliationResponse(403, 'Permission denied.');
}

$token = $_SESSION['csrf_token'] ?? '';
session_write_close();

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    reconciliationResponse(400, 'Invalid request data.');
}

if (!is_string($token) || $token === '' ||
    !is_string($data['csrf_token'] ?? null) || !hash_equals($token, $data['csrf_token'])) {
    reconciliationResponse(403, 'Invalid security token.');
}

$type = $data['item_type'] ?? null;
$id = $data['item_id'] ?? null;
$reason = is_string($data['reason'] ?? null) ? trim($data['reason']) : '';
$recorded = $data['expected_recorded_stock'] ?? null;
$batch = $data['expected_batch_stock'] ?? null;

if (!in_array($type, ['Ingredient', 'Product'], true) ||
    !is_int($id) || $id < 1 || $id > 2147483647 ||
    ($data['confirmed'] ?? null) !== true) {
    reconciliationResponse(400, 'Select a valid item and confirm that the batches are accurate.');
}

if ($reason === '' || strlen(iconv('UTF-8', 'UTF-16LE', $reason)) > 1000) {
    reconciliationResponse(400, 'A reason is required and must fit within 500 characters.');
}

foreach ([$recorded, $batch] as $quantity) {
    if (!is_string($quantity) || !preg_match('/\A(?:[0-9]{1,15}(?:\.[0-9]{1,3})?|\.[0-9]{1,3})\z/', $quantity)) {
        reconciliationResponse(400, 'Invalid stock quantities. Refresh the page and try again.');
    }
}

[$table, $batchTable, $foreignKey] = $type === 'Ingredient'
    ? ['dbo.ingredients', 'dbo.inventory_batches', 'ingredient_id']
    : ['dbo.menu_items', 'dbo.product_batches', 'menu_item_id'];

try {
    $throwDatabaseConnectionError = true;
    require __DIR__ . '/../config/database.php';

    $connection->beginTransaction();

    $stmt = $connection->prepare("
        SELECT TRY_CONVERT(decimal(18,3), stock_qty) AS recorded_stock, is_archived,
            CASE WHEN stock_qty >= 0 THEN 1 ELSE 0 END AS valid_stock
        FROM $table WITH (UPDLOCK, HOLDLOCK)
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$item || (string)$item['is_archived'] !== '0') {
        $connection->rollBack();
        reconciliationResponse(409, 'This item is no longer available for reconciliation.');
    }

    $stmt = $connection->prepare("
        SELECT TRY_CONVERT(decimal(18,3), COALESCE(SUM(TRY_CONVERT(decimal(18,3), quantity)), 0)) AS batch_stock,
            COUNT_BIG(*) - COUNT_BIG(CASE WHEN quantity >= 0
                AND TRY_CONVERT(decimal(18,3), quantity) IS NOT NULL
                AND ABS(quantity - ROUND(quantity, 3)) <= 0.00000001 THEN 1 END) AS invalid_batches
        FROM $batchTable WITH (UPDLOCK, HOLDLOCK)
        WHERE $foreignKey = ?
    ");
    $stmt->execute([$id]);
    $batches = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    $previousStock = $item['recorded_stock'];
    $correctedStock = $batches['batch_stock'];

    if (!(int)$item['valid_stock'] || $previousStock === null || $correctedStock === null ||
        (int)$batches['invalid_batches'] !== 0 ||
        ($type === 'Product' && bccomp($correctedStock, '2147483647', 3) > 0)) {
        $connection->rollBack();
        reconciliationResponse(409, 'This item has invalid stock or batch quantities.');
    }

    if (bccomp($previousStock, $recorded, 3) !== 0 || bccomp($correctedStock, $batch, 3) !== 0) {
        $connection->rollBack();
        reconciliationResponse(409, 'Stock changed since this page was loaded. Refresh and review the new totals.');
    }

    if (bccomp($previousStock, $correctedStock, 3) === 0) {
        $connection->rollBack();
        reconciliationResponse(409, 'Recorded stock already matches the batch total.');
    }

    $stmt = $connection->prepare("
        UPDATE $table
        SET stock_qty = CAST(? AS decimal(18,3))
        WHERE id = ?
    ");
    $stmt->execute([$correctedStock, $id]);

    $stmt = $connection->prepare("
        INSERT INTO dbo.stock_reconciliations
        (item_type, item_id, previous_stock, corrected_stock, reason, user_id)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$type, $id, $previousStock, $correctedStock, $reason, $userId]);

    $connection->commit();
    reconciliationResponse(200, 'Stock corrected successfully.');
} catch (Throwable $e) {
    if (isset($connection) && $connection->inTransaction()) {
        $connection->rollBack();
    }

    error_log('Stock reconciliation failed: ' . $e->getMessage());
    reconciliationResponse(500, 'Unable to correct stock. Please try again.');
}
