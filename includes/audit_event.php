<?php

function recordAuditEvent(PDO $connection, string $action, string $details): void
{
    $stmt = $connection->prepare('EXEC dbo.WriteAuditEvent ?, ?, ?, ?');
    $stmt->execute([$_SESSION['user_id'], $_SESSION['username'] ?? '', $action, $details]);
}

function recordAuthenticationAuditEvent(string $action, string $details, ?PDO $connection = null): void
{
    try {
        if ($connection === null) {
            $throwDatabaseConnectionError = true;
            require __DIR__ . '/../config/database.php';
        }

        recordAuditEvent($connection, $action, $details);
    } catch (Throwable $e) {
        error_log('Authentication audit failed: ' . $e->getMessage());
    }
}
