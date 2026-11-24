<?php

function recordAuditEvent(PDO $connection, string $action, string $details): void
{
    $stmt = $connection->prepare("
        INSERT INTO dbo.audit_log (user_id, username, action, details, created_at)
        VALUES (?, ?, ?, ?, SYSDATETIME())
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['username'] ?? '', $action, $details]);
}
