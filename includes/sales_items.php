<?php

function bestSellingMenuItems(PDO $connection, string $from, string $to): array
{
    $stmt = $connection->prepare("EXEC dbo.GetBestSellingMenuItems @From = ?, @To = ?");
    $stmt->execute([$from . 'T00:00:00', $to . 'T23:59:59.9999999']);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    return $items;
}
