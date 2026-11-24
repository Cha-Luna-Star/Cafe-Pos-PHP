<?php

function startAuthSession(bool $validateUser = true): void
{
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    $cookie = session_get_cookie_params();
    $https = isset($_SERVER['HTTPS']) && in_array(strtolower($_SERVER['HTTPS']), ['on', '1'], true);
    $cookie['secure'] = $cookie['secure'] || $https;
    $cookie['httponly'] = true;
    $cookie['samesite'] = 'Lax';
    session_set_cookie_params($cookie);
    session_start();
    if ($validateUser) {
        validateAuthSession();
    }
}

function validateAuthSession(): void
{
    global $connection;

    if (!isset($_SESSION['user_id'])) {
        return;
    }

    $sessionFingerprint = $_SESSION['password_fingerprint'] ?? null;
    if (is_string($sessionFingerprint)) {
        try {
            $throwDatabaseConnectionError = true;
            require_once __DIR__ . '/../config/database.php';
            $stmt = $connection->prepare('SELECT password FROM dbo.users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $hash = $stmt->fetchColumn();
            $stmt->closeCursor();

            if (is_string($hash) && hash_equals($sessionFingerprint, hash('sha256', $hash))) {
                return;
            }
        } catch (Throwable $e) {
            error_log('Session validation failed: ' . $e->getMessage());
            http_response_code(503);
            exit('Unable to verify the session. Please try again.');
        }
    }

    $_SESSION = [];
    session_regenerate_id(true);
}
