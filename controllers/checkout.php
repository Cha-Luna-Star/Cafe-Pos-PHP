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
    $throwDatabaseConnectionError = true;
    require_once __DIR__ . '/../config/database.php';
    $connection->beginTransaction();

    $validatedItems = [];
    $menuQuantities = [];
    $ingredientNeeds = [];
    $menuStock = [];
    $subtotal = 0;

    $stmt = $connection->query('EXEC dbo.GetCheckoutSupplies');

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
        $stmt = $connection->prepare('EXEC dbo.GetCheckoutMenuItem ?');
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
            $stmt = $connection->prepare('EXEC dbo.GetCheckoutSizeModifier ?, ?, ?');
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

        $stmt = $connection->prepare('EXEC dbo.GetCheckoutRecipe ?');
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

        $stmt = $connection->prepare('EXEC dbo.GetCheckoutIngredient ?');
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

        $stmt = $connection->prepare('EXEC dbo.GetCheckoutIngredientStock ?, ?');
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

    $stmt = $connection->prepare('EXEC dbo.CreateCheckoutOrder ?, ?, ?, ?, ?, ?, ?, ?');

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

    $insertItem = $connection->prepare('EXEC dbo.CreateCheckoutOrderItem ?, ?, ?, ?, ?');

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
        $stmt = $connection->prepare('EXEC dbo.DeductCheckoutProduct ?, ?, ?');
        $stmt->execute([$quantity, $id, $quantity]);

        if ($stmt->rowCount() !== 1) {
            failCheckout('Insufficient menu stock.');
        }
        $stmt = $connection->prepare('EXEC dbo.GetCheckoutProductBatches ?, ?');

        $stmt->execute([$id, $today]);
        $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $remaining = $quantity;

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $deduct = min((int)$batch['quantity'], $remaining);

            $update = $connection->prepare('EXEC dbo.DeductCheckoutProductBatch ?, ?, ?');

            $update->execute([
                $deduct,
                $batch['id'],
                $deduct
            ]);

            if ($update->rowCount() !== 1) {
                failCheckout('Unable to deduct product batch stock.');
            }

            $movement = $connection->prepare('EXEC dbo.WriteProductSaleMovement ?, ?, ?, ?, ?');

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

        $stmt = $connection->prepare('EXEC dbo.DeductCheckoutIngredient ?, ?, ?, ?');
        $stmt->execute([$needed, $needed, $ingredientId, $needed]);

        if ($stmt->rowCount() !== 1) {
            failCheckout("Not enough {$ingredientsInOrder[$ingredientId]} to complete checkout.");
        }

        $stmt = $connection->prepare('EXEC dbo.WriteCheckoutIngredientUsage ?, ?, ?');
        $stmt->execute([$ingredientId, $needed, $orderId]);

        $stmt = $connection->prepare('EXEC dbo.GetCheckoutIngredientBatches ?, ?');
        $stmt->execute([$ingredientId, $today]);
        $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $remaining = $needed;

        foreach ($batches as $batch) {
            if ($remaining <= 0.000001) {
                break;
            }

            $deduct = min((float)$batch['quantity'], $remaining);

            $update = $connection->prepare('EXEC dbo.DeductCheckoutIngredientBatch ?, ?');
            $update->execute([$deduct, $batch['id']]);

            $movement = $connection->prepare('EXEC dbo.WriteIngredientSaleMovement ?, ?, ?, ?, ?');

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

    $stmt = $connection->prepare('EXEC dbo.WriteAuditEvent ?, ?, ?, ?');

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
