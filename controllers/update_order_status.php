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

    if ($action === 'Cancelled') {
        $stmt = $connection->prepare("
            SELECT item_type, item_id, batch_id, quantity
            FROM dbo.stock_movements WITH (UPDLOCK, HOLDLOCK)
            WHERE movement_type = 'Sale'
            AND remarks = ?
            ORDER BY id
        ");
        $stmt->execute(['Order #' . $orderId]);
        $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $connection->prepare("
            SELECT menu_item_id, SUM(quantity) AS quantity
            FROM dbo.order_items
            WHERE order_id = ?
            GROUP BY menu_item_id
        ");
        $stmt->execute([$orderId]);
        $orderProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $expectedProducts = [];
        foreach ($orderProducts as $product) {
            $expectedProducts[(int)$product['menu_item_id']] = (float)$product['quantity'];
        }

        $stmt = $connection->prepare("
            SELECT ingredient_id, SUM(quantity_used) AS quantity
            FROM dbo.ingredient_usage
            WHERE order_id = ?
            GROUP BY ingredient_id
        ");
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

        foreach ($movements as $movement) {
            $type = $movement['item_type'];
            $id = (int)$movement['item_id'];
            $batchId = (int)$movement['batch_id'];
            $quantity = (float)$movement['quantity'];

            if ($type === 'Product') {
                $batchTable = 'dbo.product_batches';
                $stockTable = 'dbo.menu_items';
                $foreignKey = 'menu_item_id';
            } else {
                $batchTable = 'dbo.inventory_batches';
                $stockTable = 'dbo.ingredients';
                $foreignKey = 'ingredient_id';
            }

            $stmt = $connection->prepare("
                UPDATE $batchTable
                SET quantity = quantity + ?
                WHERE id = ? AND $foreignKey = ?
            ");
            $stmt->execute([$quantity, $batchId, $id]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Unable to restore batch stock.');
            }

            $stmt = $connection->prepare("
                UPDATE $stockTable
                SET stock_qty = stock_qty + ?
                WHERE id = ?
            ");
            $stmt->execute([$quantity, $id]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Unable to restore inventory stock.');
            }

            $stmt = $connection->prepare("
                INSERT INTO dbo.stock_movements
                (item_type, item_id, batch_id, movement_type,
                quantity, remarks, user_id)
                VALUES (?, ?, ?, 'Cancellation', ?, ?, ?)
            ");
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