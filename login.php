<?php
require_once __DIR__ . '/includes/auth_session.php';
require_once __DIR__ . '/includes/mutation_request.php';
require_once __DIR__ . '/includes/audit_event.php';

startAuthSession();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if (!hasValidCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $error = 'Your sign-in form expired. Please try again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Username and password are required.';
    } else {
        try {
            $throwDatabaseConnectionError = true;
            require_once __DIR__ . '/config/database.php';

            $hasStatus = hasStaffAccountStatus($connection);
            $versionColumn = $hasStatus ? 'session_version' : '0 AS session_version';
            $activeCondition = $hasStatus ? 'AND is_active = 1' : '';
            $stmt = $connection->prepare("
                SELECT id, username, password, role, $versionColumn
                FROM dbo.users
                WHERE username = ? $activeCondition
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            $storedHash = $user['password'] ?? '';
            $isLegacy = preg_match('/\A[0-9a-f]{64}\z/', $storedHash) === 1;
            $verified = $user && ($isLegacy
                ? hash_equals($storedHash, hash('sha256', $password))
                : password_verify($password, $storedHash));

            if (!$user || ($isLegacy && !$verified)) {
                // Give rejected attempts against legacy or unknown accounts comparable work.
                password_verify($password, '$argon2id$v=19$m=65536,t=4,p=1$T2d0dm1mdkd5LktCMmlBLg$/FaA80otfpUBVun/ET88iOuf35viqjHXYNROHH373sc');
            }

            if ($verified && password_needs_rehash($storedHash, PASSWORD_ARGON2ID)) {
                $newHash = password_hash($password, PASSWORD_ARGON2ID);
                $update = $connection->prepare("
                    UPDATE dbo.users
                    SET password = ?
                    WHERE id = ? AND password COLLATE Latin1_General_100_BIN2 = ?
                ");
                $update->execute([$newHash, $user['id'], $storedHash]);

                if ($update->rowCount() === 1) {
                    $storedHash = $newHash;
                } else {
                    $current = $connection->prepare('SELECT password FROM dbo.users WHERE id = ?');
                    $current->execute([$user['id']]);
                    $currentHash = $current->fetchColumn();
                    $verified = is_string($currentHash) && password_verify($password, $currentHash);
                    $storedHash = $currentHash;
                }
            }

            if ($verified) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['account_session_version'] = (string)$user['session_version'];
                $_SESSION['password_fingerprint'] = hash('sha256', $storedHash);
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                session_write_close();
                recordAuthenticationAuditEvent('Login', 'Signed in.', $connection);
                header('Location: dashboard.php');
                exit;
            }

            $error = 'Invalid username or password.';
        } catch (Throwable $e) {
            error_log('Login failed: ' . $e->getMessage());
            $error = 'Unable to process login.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coffee-Up POS | Login</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>

<div class="login-container">
    <div class="login-box">
        <h1>Coffee-Up</h1>
        <p class="subtitle">Point of Sale System</p>

        <?php if ($error): ?>
            <div class="error-message">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(sessionCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <label for="login-username">Username</label>
            <input type="text" id="login-username" name="username" autocomplete="username" required>

            <label for="login-password">Password</label>
            <input type="password" id="login-password" name="password" autocomplete="current-password" required>

            <button type="submit">Login</button>
        </form>
    </div>
</div>

</body>
</html>
