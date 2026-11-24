<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Invalid request method.');
}

require_once __DIR__ . '/includes/auth_session.php';
require_once __DIR__ . '/includes/mutation_request.php';
require_once __DIR__ . '/includes/audit_event.php';

startAuthSession();

if (!isset($_SESSION['user_id']) || !hasValidCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid logout request.');
}

session_destroy();
// The request still has the actor's identity after the stored session is removed.
recordAuthenticationAuditEvent('Logout', 'Signed out.');
$_SESSION = [];

$cookie = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires' => 1,
    'path' => $cookie['path'],
    'domain' => $cookie['domain'],
    'secure' => $cookie['secure'],
    'httponly' => $cookie['httponly'],
    'samesite' => $cookie['samesite']
]);

header('Location: login.php', true, 303);
exit;
