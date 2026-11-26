<?php
require_once __DIR__ . '/../includes/auth_session.php';
startAuthSession();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!in_array($_SESSION['role'] ?? '', ['Admin', 'Manager'], true)) {
    http_response_code(403);
    exit('Permission denied.');
}

$creatableStaffRoles = ['Cashier', 'Manager'];
$staffRoles = ['Admin', 'Manager', 'Cashier'];
$staffCreateError = null;
$staffResetError = null;
$staffStatusError = null;
$staffRoleError = null;
$staffStatusAvailable = false;
$staffNotice = $_SESSION['staff_account_notice'] ?? null;
unset($_SESSION['staff_account_notice']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_SESSION['role'] ?? '') !== 'Admin') {
        http_response_code(403);
        exit('Permission denied.');
    }

    $staffAction = $_POST['staff_action'] ?? 'create';
    if ($staffAction === 'create') {
        $staffCreateError = require __DIR__ . '/../includes/create_staff_account.php';
    } elseif ($staffAction === 'reset_password') {
        $staffResetError = require __DIR__ . '/../includes/reset_staff_password.php';
    } elseif ($staffAction === 'change_status') {
        $staffStatusError = require __DIR__ . '/../includes/update_staff_account_status.php';
    } elseif ($staffAction === 'change_role') {
        $staffRoleError = require __DIR__ . '/../includes/update_staff_role.php';
    } else {
        http_response_code(400);
        exit('Invalid staff account action.');
    }
}
$staffSearch = $_GET['q'] ?? '';
$staffRole = $_GET['role'] ?? '';
$staffAccounts = [];
$staffError = null;

if (!is_string($staffSearch) || strlen($staffSearch) > 400 ||
    !is_string($staffRole) || ($staffRole !== '' && !in_array($staffRole, $staffRoles, true))) {
    http_response_code(400);
    $staffError = 'Enter valid staff account filters.';
    return;
}

$staffSearch = trim($staffSearch);

try {
    $throwDatabaseConnectionError = true;
    require_once __DIR__ . '/../config/database.php';

    $staffStatusAvailable = hasStaffAccountStatus($connection);
    $stmt = $connection->prepare('EXEC dbo.GetStaffAccounts @Search = ?, @Role = ?');
    $stmt->execute([$staffSearch, $staffRole]);
    $staffAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
} catch (Throwable $e) {
    error_log('Staff accounts failed: ' . $e->getMessage());
    http_response_code(500);
    $staffError = 'Unable to load staff accounts. Please try again.';
}
