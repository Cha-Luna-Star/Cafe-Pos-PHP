<?php
require_once __DIR__ . '/../includes/auth_session.php';
startAuthSession();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in.']);
    exit;
}

require_once __DIR__ . '/../includes/mutation_request.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Invalid request method.');
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!is_array($data)) {
        throw new RuntimeException('Invalid request.');
    }

    if (!hasValidCsrfToken($data['csrf_token'] ?? null)) {
        throw new RuntimeException('Invalid security token.');
    }

    $orderId = requestSqlInt($data['order_id'] ?? null);
    $action = $data['action'] ?? '';

    if ($orderId === false ||
        !in_array($action, ['Completed', 'Cancelled'], true)) {
        throw new RuntimeException('Invalid order or action.');
    }

    $throwDatabaseConnectionError = true;
    require_once __DIR__ . '/../config/database.php';
    $connection->beginTransaction();

    $stmt = $connection->prepare('EXEC dbo.GetOrderStatusForUpdate @OrderId = ?');
    $stmt->execute([$orderId]);
    $status = $stmt->fetchColumn();
    $stmt->closeCursor();

    if ($status === false) {
        throw new RuntimeException('Order not found.');
    }

    if ($status !== 'Pending') {
        throw new RuntimeException('Only pending orders can be updated.');
    }

    if ($action === 'Cancelled') {
        $stmt = $connection->prepare('EXEC dbo.GetOrderSaleMovements @Reference = ?');
        $stmt->execute(['Order #' . $orderId]);
        $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $connection->prepare('EXEC dbo.GetOrderProductTotals @OrderId = ?');
        $stmt->execute([$orderId]);
        $orderProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $expectedProducts = [];
        foreach ($orderProducts as $product) {
            $expectedProducts[(int)$product['menu_item_id']] = (float)$product['quantity'];
        }

        $stmt = $connection->prepare('EXEC dbo.GetOrderIngredientTotals @OrderId = ?');
        $stmt->execute([$orderId]);
        $orderIngredients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $expectedIngredients = [];
        foreach ($orderIngredients as $ingredient) {
            $expectedIngredients[(int)$ingredient['ingredient_id']] = (float)$ingredient['quantity'];
        }

        $recordedProducts = [];
        $recordedIngredients = [];

        foreach ($movements as $movement) {
            $type = $movement['item_type'];
            $id = (int)$movement['item_id'];
            $quantity = (float)$movement['quantity'];

            if (empty($movement['batch_id']) || $quantity <= 0) {
                throw new RuntimeException('Invalid stock movement history.');
            }

            if ($type === 'Product') {
                $recordedProducts[$id] = ($recordedProducts[$id] ?? 0) + $quantity;
            } elseif ($type === 'Ingredient') {
                $recordedIngredients[$id] = ($recordedIngredients[$id] ?? 0) + $quantity;
            } else {
                throw new RuntimeException('Unknown stock movement type.');
            }
        }

        foreach ($expectedProducts as $id => $quantity) {
            if (abs(($recordedProducts[$id] ?? 0) - $quantity) > 0.000001) {
                throw new RuntimeException('Incomplete product stock history. Cancellation blocked.');
            }
        }

        foreach ($expectedIngredients as $id => $quantity) {
            if (abs(($recordedIngredients[$id] ?? 0) - $quantity) > 0.000001) {
                throw new RuntimeException('Incomplete ingredient stock history. Cancellation blocked.');
            }
        }

        if (count($expectedProducts) !== count($recordedProducts) ||
            count($expectedIngredients) !== count($recordedIngredients)) {
            throw new RuntimeException('Stock movement history does not match the order.');
        }

        ksort($expectedProducts, SORT_NUMERIC);
        ksort($expectedIngredients, SORT_NUMERIC);

        $stmt = $connection->prepare('EXEC dbo.GetProductForOrderRestore @ItemId = ?');
        foreach (array_keys($expectedProducts) as $id) {
            $stmt->execute([$id]);
            $available = $stmt->fetchColumn() !== false;
            $stmt->closeCursor();
            if (!$available) {
                throw new RuntimeException('Unable to restore product stock.');
            }
        }

        $stmt = $connection->prepare('EXEC dbo.GetIngredientForOrderRestore @ItemId = ?');
        foreach (array_keys($expectedIngredients) as $id) {
            $stmt->execute([$id]);
            $ingredient = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            if (!$ingredient || $ingredient['stock_qty'] === null || (float)$ingredient['stock_qty'] < 0) {
                throw new RuntimeException('Unable to restore ingredient stock.');
            }
            if ((int)$ingredient['is_archived'] === 1) {
                throw new RuntimeException('Restore the archived ingredient before cancelling this order.');
            }
        }

        foreach ($movements as $movement) {
            $type = $movement['item_type'];
            $id = (int)$movement['item_id'];
            $batchId = (int)$movement['batch_id'];
            $quantity = (float)$movement['quantity'];

            $stmt = $connection->prepare($type === 'Ingredient' ? 'EXEC dbo.RestoreOrderBatchIngredient ?, ?, ?' : 'EXEC dbo.RestoreOrderBatchProduct ?, ?, ?');
            $stmt->execute([$quantity, $batchId, $id]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Unable to restore batch stock.');
            }

            $stmt = $connection->prepare($type === 'Ingredient' ? 'EXEC dbo.RestoreOrderStockIngredient ?, ?' : 'EXEC dbo.RestoreOrderStockProduct ?, ?');
            $stmt->execute([$quantity, $id]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Unable to restore inventory stock.');
            }

            $stmt = $connection->prepare('EXEC dbo.WriteCancellationMovement ?, ?, ?, ?, ?, ?');
            $stmt->execute([
                $type,
                $id,
                $batchId,
                $quantity,
                'Order #' . $orderId,
                $_SESSION['user_id']
            ]);
        }
    }

    $stmt = $connection->prepare('EXEC dbo.SetPendingOrderStatus ?, ?');
    $stmt->execute([$action, $orderId]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Unable to update order.');
    }

    $stmt = $connection->prepare('EXEC dbo.WriteAuditEvent ?, ?, ?, ?');

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
    $isRequestError = $e instanceof RuntimeException && !($e instanceof PDOException);
    if (isset($connection) && $connection->inTransaction()) {
        $connection->rollBack();
    }

    http_response_code($isRequestError ? 400 : 500);

    echo json_encode([
        'success' => false,
        'message' => $isRequestError
            ? $e->getMessage()
            : 'Unable to update order.'
    ]);

    if (!$isRequestError) {
        error_log($e->getMessage());
    }
}