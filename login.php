<?php
require_once __DIR__ . '/includes/auth_session.php';
require_once __DIR__ . '/includes/mutation_request.php';
require_once __DIR__ . '/includes/audit_event.php';

startAuthSession();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = $username = $usernameError = $passwordError = '';
$invalidCredentials = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if (!hasValidCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $error = 'Your sign-in form expired. Please try again.';
    } elseif ($username === '' || $password === '') {
        $usernameError = $username === '' ? 'Enter your username.' : '';
        $passwordError = $password === '' ? 'Enter your password.' : '';
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

            $invalidCredentials = true;
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
    <title>Coffee-Up POS | Sign In</title>
    <link rel="stylesheet" href="assets/css/ui.css">
    <link rel="stylesheet" href="assets/css/login.css">
    <script src="assets/js/login.js" defer></script>
</head>
<body class="login-page">
    <main class="login-layout" aria-labelledby="login-heading">
        <div class="login-brand">
            <div class="login-brand-label">
                <img src="assets/images/coffee-up.png" alt="Coffee-Up" width="536" height="114">
                <p>Point of Sale</p>
            </div>
        </div>

        <section class="login-content">
            <div class="login-box">
                <h1 id="login-heading">Welcome back</h1>
                <p>Sign in with your staff account to open Coffee-Up POS.</p>

                <form method="post" action="login.php" id="login-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(sessionCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">

                    <div class="login-field">
                        <label for="login-username">Username</label>
                        <input class="ui-field" type="text" id="login-username" name="username" autocomplete="username"
                            autocapitalize="none" spellcheck="false" value="<?= htmlspecialchars($username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                            aria-describedby="login-username-error<?= $invalidCredentials ? ' login-error' : '' ?>"
                            <?= $usernameError || $invalidCredentials ? 'aria-invalid="true"' : '' ?> required>
                        <p id="login-username-error" class="login-error" aria-live="polite" <?= $usernameError ? '' : 'hidden' ?>><?= $usernameError ?></p>
                    </div>

                    <div class="login-field">
                        <label for="login-password">Password</label>
                        <div class="login-password">
                            <input class="ui-field" type="password" id="login-password" name="password" autocomplete="current-password"
                                aria-describedby="login-password-error<?= $invalidCredentials ? ' login-error' : '' ?>"
                                <?= $passwordError || $invalidCredentials ? 'aria-invalid="true"' : '' ?> required>
                            <button type="button" class="login-password-toggle" id="login-password-toggle" aria-controls="login-password"
                                aria-label="Show password" hidden>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                    <path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6-10-6-10-6Z"/>
                                    <circle cx="12" cy="12" r="2.5"/>
                                    <path class="login-eye-slash" d="m4 3 16 18"/>
                                </svg>
                            </button>
                        </div>
                        <p id="login-password-error" class="login-error" aria-live="polite" <?= $passwordError ? '' : 'hidden' ?>><?= $passwordError ?></p>
                        <?php if ($invalidCredentials): ?>
                            <p id="login-error" class="login-error" role="alert"><?= htmlspecialchars($error) ?></p>
                        <?php endif; ?>
                    </div>

                    <?php if ($error && !$invalidCredentials): ?>
                        <p class="login-error login-form-error" role="alert"><?= htmlspecialchars($error) ?></p>
                    <?php endif; ?>

                    <button data-tone="positive" class="ui-button login-submit" type="submit" id="login-submit">Sign In</button>
                    <span class="login-status" role="status" id="login-status"></span>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
