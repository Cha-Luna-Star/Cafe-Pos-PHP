<?php
require_once __DIR__ . '/../includes/auth_session.php';
startAuthSession();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Please log in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/../includes/mutation_request.php';

if (!hasValidCsrfToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid security token. Refresh the page.'
    ]);
    exit;
}

require_once __DIR__ . '/../includes/inventory_dates.php';

function failCheckout(string $message): void {
    throw new RuntimeException($message);
}

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!is_array($data)) {
        failCheckout('Invalid request.');
    }

    $items = $data['items'] ?? [];
    $orderType = $data['order_type'] ?? '';
    $payment = $data['payment_method'] ?? '';
    $discountType = $data['discount_type'] ?? 'None';
    $tendered = $data['amount_tendered'] ?? 0;
    $reference = $data['ref_number'] ?? '';
    if (!is_string($reference)) {
        failCheckout('Invalid payment reference.');
    }
    $reference = trim($reference);

    if (!is_array($items) || count($items) === 0 || count($items) > 100) {
        failCheckout('Invalid cart.');
    }

    if (!in_array($orderType, ['Dine In', 'Take Out'], true)) {
        failCheckout('Invalid order type.');
    }

    if (!in_array($payment, ['Cash', 'Card', 'E-Wallet'], true)) {
        failCheckout('Invalid payment method.');
    }

    $discounts = [
        'None' => 0,
        'PWD' => 0.20,
        'Student' => 0.10,
        'Senior Citizen' => 0.20
    ];

    if (!is_string($discountType) || !array_key_exists($discountType, $discounts)) {
        failCheckout('Invalid discount.');
    }

    if (!is_numeric($tendered) || !is_finite((float)$tendered) || (float)$tendered < 0) {
        failCheckout('Invalid payment amount.');
    }

    if (strlen($reference) > 100) {
        failCheckout('Payment reference is too long.');
    }

    if ($payment !== 'Cash' && $reference === '') {
        failCheckout('Payment reference is required.');
    }

    $today = inventoryToday()->format('Y-m-d');
    $ingredientBatchAvailable = "(
        NULLIF(LTRIM(RTRIM(expiration_date)), '') IS NULL OR (
            DATALENGTH(expiration_date) = 20
            AND expiration_date = CONVERT(char(10), TRY_CONVERT(date, expiration_date, 23), 23)
            AND TRY_CONVERT(date, expiration_date, 23) >= CONVERT(date, ?, 23)
        )
    )";

    $throwDatabaseConnectionError = true;
    require_once __DIR__ . '/../config/database.php';
    $connection->beginTransaction();

    $validatedItems = [];
    $menuQuantities = [];
    $ingredientNeeds = [];
    $menuStock = [];
    $subtotal = 0;

    $stmt = $connection->query("
        SELECT id, name
        FROM dbo.ingredients
        WHERE name IN ('Cups (Hot)', 'Cups (Cold)', 'Ice')
    ");

    $specialIngredients = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ingredient) {
        $specialIngredients[$ingredient['name']] = (int)$ingredient['id'];
    }

    $hotCupId = $specialIngredients['Cups (Hot)'] ?? null;
    $coldCupId = $specialIngredients['Cups (Cold)'] ?? null;
    $iceId = $specialIngredients['Ice'] ?? null;

    $menuIds = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            failCheckout('Invalid cart item.');
        }
        $id = requestSqlInt($item['id'] ?? null);
        if ($id === false) {
            failCheckout('Invalid item or quantity.');
        }
        $menuIds[$id] = true;
    }

    ksort($menuIds, SORT_NUMERIC);
    $menuItems = [];
    foreach (array_keys($menuIds) as $id) {
        $stmt = $connection->prepare("
            SELECT id, name, price, stock_qty, has_sizes, has_temp_option
            FROM dbo.menu_items WITH (UPDLOCK, HOLDLOCK)
            WHERE id = ?
              AND available = 1
              AND is_archived = 0
        ");
        $stmt->execute([$id]);
        $menu = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        if (!$menu) {
            failCheckout('Menu item is unavailable.');
        }
        $menuItems[$id] = $menu;
    }

    foreach ($items as $item) {
        $id = (int)$item['id'];
        $quantity = requestSqlInt($item['quantity'] ?? null);
        $size = $item['size'] ?? 'Regular';
        $temperature = $item['temperature'] ?? null;

        if (
            $temperature !== null &&
            !in_array($temperature, ['Hot', 'Iced'], true)
        ) {
            failCheckout('Invalid temperature.');
        }

        if ($quantity === false || $quantity > 100) {
            failCheckout('Invalid item or quantity.');
        }

        if (!is_string($size)) {
            failCheckout('Invalid size.');
        }

        $menu = $menuItems[$id];

        if ((int)$menu['has_temp_option'] === 1) {
            if (!in_array($temperature, ['Hot', 'Iced'], true)) {
                failCheckout('Please select a temperature.');
            }
        } elseif ($temperature !== null) {
            failCheckout('Temperature is not available for this item.');
        }

        if (!$menu) {
            failCheckout('A menu item is unavailable.');
        }
        $menuStock[$id] = (int)$menu['stock_qty'];

        $adjustment = 0;

        if ((int)$menu['has_sizes'] === 1 && $size !== 'Regular') {
            $stmt = $connection->prepare("
                SELECT TOP 1 price_adjustment
                FROM dbo.size_modifiers
                WHERE name = ?
                AND (menu_item_id = ? OR menu_item_id IS NULL)
                ORDER BY CASE WHEN menu_item_id = ? THEN 0 ELSE 1 END
            ");
            $stmt->execute([$size, $id, $id]);
            $modifier = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$modifier) {
                failCheckout('Invalid size selection.');
            }

            $adjustment = (float)$modifier['price_adjustment'];
        } elseif ($size !== 'Regular') {
            failCheckout('Invalid item size.');
        }

        $unitPrice = round((float)$menu['price'] + $adjustment, 2);

        if ($unitPrice < 0) {
            failCheckout('Invalid menu price.');
        }

        $menuQuantities[$id] = ($menuQuantities[$id] ?? 0) + $quantity;

        if ($menuQuantities[$id] > $menuStock[$id]) {
            failCheckout($menu['name'] . ' has insufficient stock.');
        }

        $lineTotal = round($unitPrice * $quantity, 2);
        $subtotal += $lineTotal;

        $displaySize = $size;

if ($temperature !== null) {
    $displaySize = $size === 'Regular'
        ? $temperature
        : $size . ' - ' . $temperature;
}

$validatedItems[] = [
    'id' => $id,
    'quantity' => $quantity,
    'size' => $displaySize,
    'subtotal' => $lineTotal
];

        $stmt = $connection->prepare("
            SELECT ingredient_id, quantity_needed
            FROM dbo.recipe_items
            WHERE menu_item_id = ?
        ");
        $stmt->execute([$id]);

        $iceRequired = false;

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $recipe) {
            $ingredientId = (int)$recipe['ingredient_id'];
            $recipeQuantity = (float)$recipe['quantity_needed'];
            $roundedQuantity = round($recipeQuantity, 3);
            if (!is_finite($recipeQuantity) || $roundedQuantity <= 0 ||
                abs($recipeQuantity - $roundedQuantity) > 0.00000001) {
                failCheckout('A recipe has an invalid ingredient quantity.');
            }
            $needed = $roundedQuantity * $quantity;

            if (
                $temperature === 'Iced' &&
                $hotCupId !== null &&
                $coldCupId !== null &&
                $ingredientId === $hotCupId
            ) {
                $ingredientId = $coldCupId;
                $iceRequired = true;
            }

            $ingredientNeeds[$ingredientId] =
                ($ingredientNeeds[$ingredientId] ?? 0) + $needed;
        }

        if ($iceRequired && $iceId !== null) {
            $ingredientNeeds[$iceId] =
                ($ingredientNeeds[$iceId] ?? 0) + (100 * $quantity);
        }
    }


    $ingredientsInOrder = [];
    ksort($ingredientNeeds, SORT_NUMERIC);
    foreach ($ingredientNeeds as $ingredientId => $needed) {
        $needed = round($needed, 3);

        $stmt = $connection->prepare("
            SELECT name, unit, stock_qty
            FROM dbo.ingredients WITH (UPDLOCK, HOLDLOCK)
            WHERE id = ? AND is_archived = 0
        ");
        $stmt->execute([$ingredientId]);

        $ingredient = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$ingredient) {
            failCheckout("Ingredient #$ingredientId is no longer available.");
        }

        $ingredientsInOrder[$ingredientId] = $ingredient['name'];
        $stock = $ingredient['stock_qty'];
        $unit = trim($ingredient['unit'] ?? '');
        $unit = $unit === '' ? '' : " $unit";
        $formatQuantity = static fn(float $value): string => rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
        $requiredQuantity = $formatQuantity($needed) . $unit;

        if ($stock === null || (float)$stock + 0.000001 < $needed) {
            $recordedQuantity = $formatQuantity((float)$stock) . $unit;
            failCheckout("Not enough {$ingredient['name']}. Need $requiredQuantity, with $recordedQuantity recorded in Inventory.");
        }

        $stmt = $connection->prepare("
            SELECT COALESCE(SUM(quantity), 0)
            FROM dbo.inventory_batches WITH (UPDLOCK, HOLDLOCK)
            WHERE ingredient_id = ?
            AND quantity > 0
            AND $ingredientBatchAvailable
        ");
        $stmt->execute([$ingredientId, $today]);

        $batchStock = (float)$stmt->fetchColumn();
        $stmt->closeCursor();

        if ($batchStock + 0.000001 < $needed) {
            $usableQuantity = $formatQuantity($batchStock) . $unit;
            failCheckout("Not enough usable {$ingredient['name']}. Need $requiredQuantity, with $usableQuantity in usable batches. Check Batch History in Inventory.");
        }
    }

    $subtotal = round($subtotal, 2);
    $total = round($subtotal * (1 - $discounts[$discountType]), 2);
    $tax = round($total - ($total / 1.12), 2);

    if ($payment === 'Cash') {
        $tendered = round((float)$tendered, 2);

        if ($tendered < $total) {
            failCheckout('Insufficient payment.');
        }

        $change = round($tendered - $total, 2);
        $reference = '';
    } else {
        $tendered = $total;
        $change = 0;
    }

    $stmt = $connection->prepare("
        INSERT INTO dbo.orders
        (
            order_type, status, total, created_by,
            created_at, payment_method, amount_tendered,
            change_amount, tax, ref_number
        )
        OUTPUT INSERTED.id
        VALUES (?, 'Pending', ?, ?, SYSDATETIME(), ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $orderType,
        $total,
        $_SESSION['user_id'],
        $payment,
        $tendered,
        $change,
        $tax,
        $reference
    ]);

    $orderId = (int)$stmt->fetchColumn();
    $stmt->closeCursor();

    $insertItem = $connection->prepare("
        INSERT INTO dbo.order_items
        (order_id, menu_item_id, quantity, size, subtotal)
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($validatedItems as $item) {
        $insertItem->execute([
            $orderId,
            $item['id'],
            $item['quantity'],
            $item['size'],
            $item['subtotal']
        ]);
    }

    foreach ($menuQuantities as $id => $quantity) {
        $stmt = $connection->prepare("
            UPDATE dbo.menu_items
            SET stock_qty = stock_qty - ?
            WHERE id = ? AND stock_qty >= ?
        ");
        $stmt->execute([$quantity, $id, $quantity]);

        if ($stmt->rowCount() !== 1) {
            failCheckout('Insufficient menu stock.');
        }
        $stmt = $connection->prepare("
            SELECT id, quantity
            FROM dbo.product_batches WITH (UPDLOCK, HOLDLOCK)
            WHERE menu_item_id = ?
            AND quantity > 0
            AND (
                expiration_date IS NULL
                OR expiration_date >= CONVERT(date, ?, 23)
            )
            ORDER BY
                CASE WHEN expiration_date IS NULL THEN 1 ELSE 0 END,
                expiration_date ASC,
                received_date ASC,
                id ASC
        ");

        $stmt->execute([$id, $today]);
        $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $remaining = $quantity;

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $deduct = min((int)$batch['quantity'], $remaining);

            $update = $connection->prepare("
                UPDATE dbo.product_batches
                SET quantity = quantity - ?
                WHERE id = ?
                AND quantity >= ?
            ");

            $update->execute([
                $deduct,
                $batch['id'],
                $deduct
            ]);

            if ($update->rowCount() !== 1) {
                failCheckout('Unable to deduct product batch stock.');
            }

            $movement = $connection->prepare("
                INSERT INTO dbo.stock_movements
                (item_type, item_id, batch_id, movement_type,
                quantity, remarks, user_id)
                VALUES ('Product', ?, ?, 'Sale', ?, ?, ?)
            ");

            $movement->execute([
                $id,
                $batch['id'],
                $deduct,
                'Order #' . $orderId,
                $_SESSION['user_id']
            ]);

            $remaining -= $deduct;
        }

        if ($remaining > 0) {
            failCheckout('Insufficient valid product batch stock.');
        }
    }


    foreach ($ingredientNeeds as $ingredientId => $needed) {
        $needed = round($needed, 3);

        $stmt = $connection->prepare("
            UPDATE dbo.ingredients
            SET stock_qty = CASE WHEN stock_qty <= ? THEN 0 ELSE stock_qty - ? END
            WHERE id = ? AND stock_qty + 0.000001 >= ?
        ");
        $stmt->execute([$needed, $needed, $ingredientId, $needed]);

        if ($stmt->rowCount() !== 1) {
            failCheckout("Not enough {$ingredientsInOrder[$ingredientId]} to complete checkout.");
        }

        $stmt = $connection->prepare("
            INSERT INTO dbo.ingredient_usage
            (ingredient_id, quantity_used, order_id, created_at)
            VALUES (?, ?, ?, SYSDATETIME())
        ");
        $stmt->execute([$ingredientId, $needed, $orderId]);

        $stmt = $connection->prepare("
            SELECT id, quantity
            FROM dbo.inventory_batches WITH (UPDLOCK, HOLDLOCK)
            WHERE ingredient_id = ? AND quantity > 0
                AND $ingredientBatchAvailable
            ORDER BY TRY_CONVERT(date, received_date, 23) ASC, id ASC
        ");
        $stmt->execute([$ingredientId, $today]);
        $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $remaining = $needed;

        foreach ($batches as $batch) {
            if ($remaining <= 0.000001) {
                break;
            }

            $deduct = min((float)$batch['quantity'], $remaining);

            $update = $connection->prepare("
                UPDATE dbo.inventory_batches
                SET quantity = quantity - ?
                WHERE id = ?
            ");
            $update->execute([$deduct, $batch['id']]);

            $movement = $connection->prepare("
                INSERT INTO dbo.stock_movements
                (item_type, item_id, batch_id, movement_type,
                quantity, remarks, user_id)
                VALUES ('Ingredient', ?, ?, 'Sale', ?, ?, ?)
            ");

            $movement->execute([
                $ingredientId,
                $batch['id'],
                $deduct,
                'Order #' . $orderId,
                $_SESSION['user_id']
            ]);

            $remaining -= $deduct;
        }

        if ($remaining > 0.000001) {
            failCheckout("Not enough usable {$ingredientsInOrder[$ingredientId]} to complete checkout. Check Batch History in Inventory.");
        }
    }

    $stmt = $connection->prepare("
        INSERT INTO dbo.audit_log
        (user_id, username, action, details, created_at)
        VALUES (?, ?, ?, ?, SYSDATETIME())
    ");

    $stmt->execute([
        $_SESSION['user_id'],
        $_SESSION['username'] ?? '',
        'CREATE_ORDER',
        'Created order #' . $orderId
    ]);

    $connection->commit();

    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'total' => $total,
        'tax' => $tax,
        'change' => $change
    ]);

} catch (Throwable $e) {
    $isRequestError = $e instanceof RuntimeException && !($e instanceof PDOException);
    if (isset($connection) && $connection->inTransaction()) {
        $connection->rollBack();
    }

    http_response_code($isRequestError ? 400 : 500);

    echo json_encode([
        'success' => false,
        'error' => $isRequestError
            ? $e->getMessage()
            : 'Checkout failed. Check the server logs.'
    ]);

    if (!$isRequestError) {
        error_log($e->getMessage());
    }
}
