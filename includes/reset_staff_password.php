<?php
require_once __DIR__ . '/mutation_request.php';
require_once __DIR__ . '/audit_event.php';

if (($_SESSION['role'] ?? '') !== 'Admin' || !hasValidCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    return 'Permission denied or the form expired.';
}

$id = requestSqlInt($_POST['user_id'] ?? null);
$password = $_POST['password'] ?? null;
if ($id === false || $id === (int)$_SESSION['user_id'] || !is_string($password) ||
    strlen($password) > 1024 || !preg_match('/\A.{12,}\z/us', $password)) {
    http_response_code(422);
    return 'Select a staff account and use a password with at least 12 characters.';
}

try {
    $hash = password_hash($password, PASSWORD_ARGON2ID);
    $connection->beginTransaction();
    $stmt = $connection->prepare('EXEC dbo.ResetStaffPassword ?, ?');
    $stmt->execute([$hash, $id]);
    $username = $stmt->fetchColumn();
    $stmt->closeCursor();

    if ($username === false) {
        $connection->rollBack();
        http_response_code(404);
        return 'Eligible staff account not found.';
    }

    recordAuditEvent($connection, 'User Password Reset', "Reset password for user #$id ($username).");
    $connection->commit();
    $_SESSION['staff_account_notice'] = 'Password reset. Existing sessions for this account were signed out.';
    header('Location: staff_accounts.php', true, 303);
    exit;
} catch (Throwable $e) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }

    error_log('Staff password reset failed: ' . $e->getMessage());
    http_response_code(500);
    return 'Unable to reset the password. Please try again.';
}
