<?php

function bestSellingMenuItems(PDO $connection, string $from, string $to): array
{
    $stmt = $connection->prepare("
        WITH item_totals AS (
            SELECT oi.menu_item_id, SUM(CAST(oi.quantity AS bigint)) AS units_sold,
                SUM(CAST(oi.subtotal AS decimal(19,2))) AS revenue
            FROM dbo.order_items oi
            JOIN dbo.orders o ON o.id = oi.order_id
            WHERE o.status = 'Completed'
                AND o.created_at >= CONVERT(datetime2(7), ?, 126)
                AND o.created_at <= CONVERT(datetime2(7), ?, 126)
            GROUP BY oi.menu_item_id
        )
        SELECT TOP (10)
            COALESCE(m.name, CONCAT('Item #', t.menu_item_id)) AS item_name,
            t.units_sold, t.revenue
        FROM item_totals t
        LEFT JOIN dbo.menu_items m ON m.id = t.menu_item_id
        ORDER BY t.units_sold DESC, item_name, t.menu_item_id
    ");
    $stmt->execute([$from . 'T00:00:00', $to . 'T23:59:59.9999999']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
