<?php
require_once __DIR__ . '/mutation_request.php';
require_once __DIR__ . '/audit_event.php';

if (($_SESSION['role'] ?? '') !== 'Admin' || !hasValidCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    return 'Permission denied or the form expired.';
}

$id = requestSqlInt($_POST['user_id'] ?? null);
$action = $_POST['account_status'] ?? null;
if ($id === false || $id === (int)$_SESSION['user_id'] || !in_array($action, ['deactivate', 'reactivate'], true)) {
    http_response_code(422);
    return 'Select a staff account and a valid status change.';
}

try {
    if (!hasStaffAccountStatus($connection)) {
        http_response_code(409);
        return 'Apply the staff account status database migration first.';
    }

    $active = $action === 'reactivate' ? 1 : 0;
    $connection->beginTransaction();
    $stmt = $connection->prepare('EXEC dbo.SetStaffAccountStatus ?, ?, ?');
    $stmt->execute([$active, $id, $active]);
    $username = $stmt->fetchColumn();
    $stmt->closeCursor();

    if ($username === false) {
        $connection->rollBack();
        http_response_code(409);
        return 'The account is ineligible or already has that status.';
    }

    $event = $active ? 'User Reactivated' : 'User Deactivated';
    recordAuditEvent($connection, $event, "$event #$id ($username).");
    $connection->commit();

    $_SESSION['staff_account_notice'] = $active ? 'Staff account reactivated.' : 'Staff account deactivated.';
    header('Location: staff_accounts.php', true, 303);
    exit;
} catch (Throwable $e) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }

    error_log('Staff account status update failed: ' . $e->getMessage());
    http_response_code(500);
    return 'Unable to update the account status. Please try again.';
}
