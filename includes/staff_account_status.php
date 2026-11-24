<?php

function hasStaffAccountStatus(PDO $connection): bool
{
    static $available;
    return $available ??= (int)$connection->query("
        SELECT CASE WHEN COL_LENGTH('dbo.users', 'is_active') IS NOT NULL
            AND COL_LENGTH('dbo.users', 'session_version') IS NOT NULL THEN 1 ELSE 0 END
    ")->fetchColumn() === 1;
}
