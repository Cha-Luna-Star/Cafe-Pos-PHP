<?php

function hasStaffAccountStatus(PDO $connection): bool
{
    static $available;
    return $available ??= (int)$connection->query('EXEC dbo.HasStaffAccountStatus')->fetchColumn() === 1;
}
