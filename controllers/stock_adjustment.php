<?php
session_start();
header('Content-Type: application/json');

if (
    !isset($_SESSION['user_id']) ||
    !in_array($_SESSION['role'] ?? '', ['Admin', 'Manager'], true)
) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied.']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/inventory_dates.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Invalid request method.');
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (
        !is_array($data) ||
        !is_string($data['csrf_token'] ?? null) ||
        !hash_equals($_SESSION['csrf_token'] ?? '', $data['csrf_token'])
    ) {
        throw new RuntimeException('Invalid security token.');
    }

    $type = $data['item_type'] ?? '';
    $id = filter_var($data['item_id'] ?? null, FILTER_VALIDATE_INT);
    $action = $data['action'] ?? '';
    $movementType = $action === 'Remove'
        ? ($data['movement_type'] ?? 'Adjustment')
        : 'Adjustment';

    if (!in_array($movementType, ['Adjustment', 'Expired', 'Damaged'], true)) {
        throw new RuntimeException('Invalid movement type.');
    }
    $batchId = filter_var(
        $data['batch_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if ($action === 'Remove' && (!$batchId || $batchId <= 0)) {
        throw new RuntimeException('Please select a valid batch.');
    }
    $quantity = $data['quantity'] ?? null;
    $reason = trim((string)($data['reason'] ?? ''));

    if (
        !in_array($type, ['Ingredient', 'Product'], true) ||
        !$id || $id <= 0 ||
        !in_array($action, ['Add', 'Remove'], true) ||
        !is_numeric($quantity) ||
        !is_finite((float)$quantity) ||
        (float)$quantity <= 0
    ) {
        throw new RuntimeException('Invalid adjustment details.');
    }

    $quantity = (float)$quantity;

    if ($type === 'Product' && (
        $quantity != floor($quantity) ||
        $quantity > 2147483647
    )) {
        throw new RuntimeException('Product quantity must be a valid whole number.');
    }

    if ($type === 'Ingredient' && (
        $quantity < 0.001 ||
        round($quantity, 3) != $quantity
    )) {
        throw new RuntimeException('Ingredient quantity must use at most 3 decimal places.');
    }

    if ($reason === '' || strlen($reason) > 500) {
        throw new RuntimeException('A reason is required (maximum 500 characters).');
    }

    $table = $type === 'Ingredient'
        ? 'dbo.ingredients'
        : 'dbo.menu_items';

    $batchTable = $type === 'Ingredient'
        ? 'dbo.inventory_batches'
        : 'dbo.product_batches';

    $foreignKey = $type === 'Ingredient'
        ? 'ingredient_id'
        : 'menu_item_id';

    $connection->beginTransaction();

    $stmt = $connection->prepare("
        SELECT stock_qty
        FROM $table WITH (UPDLOCK, HOLDLOCK)
        WHERE id = ? AND is_archived = 0
    ");

    $stmt->execute([$id]);
    $currentStock = $stmt->fetchColumn();
    $stmt->closeCursor();

    if ($currentStock === false) {
        throw new RuntimeException('Item not found.');
    }

    if ($action === 'Remove' && $quantity > (float)$currentStock) {
        throw new RuntimeException('Insufficient stock.');
    }

    if ($action === 'Add') {
        $stmt = $connection->prepare("
            INSERT INTO $batchTable
            ($foreignKey, quantity, cost_per_unit, received_date, notes)
            OUTPUT INSERTED.id
            VALUES (?, ?, 0, CAST(GETDATE() AS DATE), ?)
        ");

        $stmt->execute([$id, $quantity, 'Adjustment: ' . $reason]);
        $batchId = (int)$stmt->fetchColumn();
        $stmt->closeCursor();

        $stmt = $connection->prepare("
            UPDATE $table
            SET stock_qty = COALESCE(stock_qty, 0) + ?
            WHERE id = ?
        ");
        $stmt->execute([$quantity, $id]);

        $stmt = $connection->prepare("
            INSERT INTO dbo.stock_movements
            (item_type, item_id, batch_id, movement_type, quantity, remarks, user_id)
            VALUES (?, ?, ?, 'Adjustment', ?, ?, ?)
        ");

        $stmt->execute([
            $type, $id, $batchId, $quantity,
            'Added: ' . $reason, $_SESSION['user_id']
        ]);
    } else {
        $stmt = $connection->prepare("
            SELECT id, quantity, CONVERT(nvarchar(4000), expiration_date, 23) AS expiration_date
            FROM $batchTable WITH (UPDLOCK, HOLDLOCK)
            WHERE $foreignKey = ?
                AND id = ?
                AND quantity > 0
        ");

        $stmt->execute([$id, $batchId]);
        $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        if (!$batches) {
            throw new RuntimeException('Selected batch is unavailable.');
        }

        if ($movementType === 'Expired' &&
            batchExpirationStatus($batches[0]['expiration_date'], inventoryToday()) !== 'expired') {
            throw new RuntimeException('Select a batch with a valid expiration date before today.');
        }

        if ($quantity > (float)$batches[0]['quantity']) {
            throw new RuntimeException('Insufficient stock in the selected batch.');
        }

        $remaining = $quantity;

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $deduct = min($remaining, (float)$batch['quantity']);

            $update = $connection->prepare("
                UPDATE $batchTable
                SET quantity = quantity - ?
                WHERE id = ?
            ");
            $update->execute([$deduct, $batch['id']]);

            $log = $connection->prepare("
                INSERT INTO dbo.stock_movements
                (item_type, item_id, batch_id, movement_type, quantity, remarks, user_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $log->execute([
                $type,
                $id,
                $batch['id'],
                $movementType,
                -$deduct,
                'Removed: ' . $reason,
                $_SESSION['user_id']
            ]);

            $remaining = round($remaining - $deduct, 3);
        }

        if ($remaining > 0) {
            throw new RuntimeException('Insufficient batch stock.');
        }


        $stmt = $connection->prepare("
            UPDATE $table
            SET stock_qty = stock_qty - ?
            WHERE id = ?
        ");
        $stmt->execute([$quantity, $id]);
    }

    $connection->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Stock adjusted successfully.'
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
            : 'Unable to adjust stock.'
    ]);

    if (!($e instanceof RuntimeException)) {
        error_log($e->getMessage());
    }
}