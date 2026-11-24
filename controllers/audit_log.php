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

require_once __DIR__ . '/../includes/inventory_dates.php';

$search = $_GET['q'] ?? '';
$action = $_GET['action'] ?? '';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$actions = [];
$auditEntries = [];
$auditError = null;

if (!is_string($search) || strlen($search) > 400 ||
    !is_string($action) || strlen($action) > 400 ||
    !is_string($from) || !is_string($to)) {
    http_response_code(400);
    $auditError = 'Enter valid audit log filters.';
    return;
}

$search = trim($search);
$start = $from === '' ? null : parseInventoryDate($from);
$end = $to === '' ? null : parseInventoryDate($to);
if (($from !== '' && !$start) || ($to !== '' && !$end) || ($start && $end && $start > $end)) {
    http_response_code(400);
    $auditError = 'Enter valid dates. The end date must be on or after the start date.';
    return;
}

$conditions = [];
$params = [];
if ($search !== '') {
    $conditions[] = '(CHARINDEX(?, username) > 0 OR CHARINDEX(?, details) > 0)';
    array_push($params, $search, $search);
}
if ($action !== '') {
    $conditions[] = 'action = ?';
    $params[] = $action;
}
if ($start) {
    $conditions[] = 'created_at >= CONVERT(datetime2(7), ?, 126)';
    $params[] = $start->format('Y-m-d') . 'T00:00:00';
}
if ($end) {
    $conditions[] = 'created_at <= CONVERT(datetime2(7), ?, 126)';
    $params[] = $end->format('Y-m-d') . 'T23:59:59.9999999';
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$pageSize = 25;
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;

try {
    $throwDatabaseConnectionError = true;
    require_once __DIR__ . '/../config/database.php';

    $actions = $connection->query('SELECT DISTINCT action FROM dbo.audit_log ORDER BY action')
        ->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $connection->prepare("SELECT COUNT_BIG(*) FROM dbo.audit_log $where");
    $stmt->execute($params);
    $totalEntries = (int)$stmt->fetchColumn();
    $stmt->closeCursor();
    $pageCount = max(1, intdiv(max(0, $totalEntries - 1), $pageSize) + 1);
    $page = min($page, $pageCount);

    $stmt = $connection->prepare("
        SELECT id, user_id, username, action, details,
            CONVERT(char(19), created_at, 120) AS event_time
        FROM dbo.audit_log
        $where
        ORDER BY created_at DESC, id DESC
        OFFSET CAST(? AS bigint) ROWS FETCH NEXT CAST(? AS int) ROWS ONLY
    ");
    $stmt->execute([...$params, ($page - 1) * $pageSize, $pageSize]);
    $auditEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Audit log failed: ' . $e->getMessage());
    http_response_code(500);
    $auditError = 'Unable to load the audit log. Please try again.';
}
