<?php
require_once __DIR__ . '/../includes/mutation_request.php';

if (($_SESSION['role'] ?? '') !== 'Admin' || !hasValidCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    return 'Permission denied or the form expired.';
}

$username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
$role = $_POST['staff_role'] ?? null;
$password = $_POST['password'] ?? null;

if ($username === '' || strlen($username) > 400 || preg_match('/[\x00-\x1f\x7f]/', $username) ||
    !preg_match('//u', $username) || strlen(iconv('UTF-8', 'UTF-16LE', $username)) > 200 ||
    !in_array($role, $creatableStaffRoles, true) || !is_string($password) ||
    strlen($password) > 1024 || !preg_match('/\A.{12,}\z/us', $password)) {
    http_response_code(422);
    return 'Enter a username, choose a staff role, and use a password with at least 12 characters.';
}

try {
    $hash = password_hash($password, PASSWORD_ARGON2ID);
    $throwDatabaseConnectionError = true;
    require_once __DIR__ . '/../config/database.php';

    $connection->beginTransaction();
    $stmt = $connection->prepare("
        INSERT INTO dbo.users (username, password, role)
        OUTPUT INSERTED.id
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$username, $hash, $role]);
    $newUserId = $stmt->fetchColumn();
    $stmt->closeCursor();
    if ($newUserId === false) {
        throw new RuntimeException('The new staff account ID was unavailable.');
    }

    $stmt = $connection->prepare("
        INSERT INTO dbo.audit_log (user_id, username, action, details, created_at)
        VALUES (?, ?, 'User Created', ?, SYSDATETIME())
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $_SESSION['username'] ?? '',
        "Created user #$newUserId ($username) with role $role."
    ]);

    $connection->commit();
    $_SESSION['staff_account_notice'] = 'Staff account created.';
    header('Location: staff_accounts.php', true, 303);
    exit;
} catch (Throwable $e) {
    if (isset($connection) && $connection->inTransaction()) {
        $connection->rollBack();
    }

    if ($e instanceof PDOException && in_array((int)($e->errorInfo[1] ?? 0), [2601, 2627], true)) {
        http_response_code(409);
        return 'That username is already in use.';
    }

    error_log('Staff account creation failed: ' . $e->getMessage());
    http_response_code(500);
    return 'Unable to create the staff account. Please try again.';
}
