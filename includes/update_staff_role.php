<?php
require_once __DIR__ . '/mutation_request.php';
require_once __DIR__ . '/audit_event.php';

if (($_SESSION['role'] ?? '') !== 'Admin' || !hasValidCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    return 'Permission denied or the form expired.';
}

$id = requestSqlInt($_POST['user_id'] ?? null);
$currentRole = $_POST['current_role'] ?? null;
$newRole = $_POST['new_role'] ?? null;
if ($id === false || $id === (int)$_SESSION['user_id'] ||
    !in_array($currentRole, $creatableStaffRoles, true) ||
    !in_array($newRole, $creatableStaffRoles, true) || $currentRole === $newRole) {
    http_response_code(422);
    return 'Select an eligible staff account and a different role.';
}

try {
    if (!hasStaffAccountStatus($connection)) {
        http_response_code(409);
        return 'Apply the staff account status migration before changing roles.';
    }

    $connection->beginTransaction();
    $stmt = $connection->prepare("
        UPDATE dbo.users
        SET role = ?, session_version = session_version + 1
        OUTPUT INSERTED.username, DELETED.role AS previous_role
        WHERE id = ? AND role = ? AND role IN ('Cashier', 'Manager')
    ");
    $stmt->execute([$newRole, $id, $currentRole]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$user) {
        $connection->rollBack();
        http_response_code(409);
        return 'The account is ineligible or its role changed. Reload the page and try again.';
    }

    recordAuditEvent(
        $connection,
        'User Role Changed',
        "Changed user #$id ({$user['username']}) from {$user['previous_role']} to $newRole."
    );
    $connection->commit();

    $_SESSION['staff_account_notice'] = 'Staff role updated. Existing sessions for this account were signed out.';
    header('Location: staff_accounts.php', true, 303);
    exit;
} catch (Throwable $e) {
    if (isset($connection) && $connection->inTransaction()) {
        $connection->rollBack();
    }

    error_log('Staff role update failed: ' . $e->getMessage());
    http_response_code(500);
    return 'Unable to change the staff role. Please try again.';
}
