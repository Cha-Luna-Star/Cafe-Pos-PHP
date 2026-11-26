<?php
require_once __DIR__ . '/../includes/auth_session.php';
require_once __DIR__ . '/../includes/mutation_request.php';
require_once __DIR__ . '/../includes/inventory_dates.php';
require_once __DIR__ . '/../includes/audit_event.php';

startAuthSession();
header('Content-Type: application/json');

function expirationResponse(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['success' => $status === 200, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    expirationResponse(405, 'Invalid request method.');
}

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Admin', 'Manager'], true) ||
    !hasValidCsrfToken($data['csrf_token'] ?? null)) {
    expirationResponse(403, 'Permission denied or the form expired.');
}

$itemId = requestSqlInt($data['item_id'] ?? null);
$batchId = requestSqlInt($data['batch_id'] ?? null);
$date = $data['expiration_date'] ?? null;
$reason = is_string($data['reason'] ?? null) ? trim($data['reason']) : '';
if ($itemId === false || $batchId === false || !is_string($date) || !parseInventoryDate($date) ||
    $reason === '' || strlen(iconv('UTF-8', 'UTF-16LE', $reason)) > 1000) {
    expirationResponse(422, 'Enter a valid expiration date and a reason of up to 500 characters.');
}

try {
    $connection->beginTransaction();

    $stmt = $connection->prepare('EXEC dbo.GetStockInIngredient @ItemId = ?');
    $stmt->execute([$itemId]);
    $name = $stmt->fetchColumn();
    $stmt->closeCursor();

    $stmt = $connection->prepare('EXEC dbo.GetBatchForExpirationCorrection @BatchId = ?, @IngredientId = ?');
    $stmt->execute([$batchId, $itemId]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if ($name === false || !$batch || batchExpirationStatus($batch['expiration_date'], inventoryToday()) !== 'invalid-date') {
        $connection->rollBack();
        expirationResponse(409, 'This batch is no longer eligible for date correction. Refresh Inventory.');
    }

    if ($batch['received_date'] !== null && $date < $batch['received_date']) {
        $connection->rollBack();
        expirationResponse(422, 'Expiration cannot precede delivery.');
    }

    $stmt = $connection->prepare('UPDATE dbo.inventory_batches SET expiration_date = ? WHERE id = ? AND ingredient_id = ?');
    $stmt->execute([$date, $batchId, $itemId]);
    recordAuditEvent($connection, 'Batch Expiration Corrected',
        "Ingredient #$itemId ($name), batch #$batchId. Previous date: {$batch['expiration_date']}. New date: $date. Reason: $reason");
    $connection->commit();
    expirationResponse(200, 'Expiration date corrected.');
} catch (Throwable $e) {
    if (isset($connection) && $connection->inTransaction()) {
        $connection->rollBack();
    }
    error_log('Batch expiration correction failed: ' . $e->getMessage());
    expirationResponse(500, 'Unable to correct the date. Please try again.');
}
