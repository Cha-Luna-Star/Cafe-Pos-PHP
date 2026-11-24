<?php
require __DIR__ . '/controllers/staff_accounts.php';

$staffEscape = static fn($value): string => htmlspecialchars(
    is_string($value) ? $value : '',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cafe POS | Staff Accounts</title>
    <link rel="stylesheet" href="assets/css/reports.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
</head>
<body>

<?php require __DIR__ . '/includes/sidebar.php'; ?>

<header class="report-header">
    <h1>Staff Accounts</h1>
</header>

<main class="report-content staff-accounts">
    <?php if ($staffNotice !== null): ?>
        <p role="status"><?= $staffEscape($staffNotice) ?></p>
    <?php endif; ?>

    <?php if (($_SESSION['role'] ?? '') === 'Admin'): ?>
        <section class="report-section" aria-labelledby="staff-create-heading">
            <h2 id="staff-create-heading">Add Staff Account</h2>
            <form method="post" action="staff_accounts.php" class="report-filters">
                <input type="hidden" name="csrf_token" value="<?= $staffEscape(sessionCsrfToken()) ?>">
                <div>
                    <label for="new-staff-username">Username</label>
                    <input id="new-staff-username" name="username" maxlength="100"
                        value="<?= $staffEscape($_POST['username'] ?? '') ?>" required>
                </div>
                <div>
                    <label for="new-staff-role">Role</label>
                    <select id="new-staff-role" name="staff_role" required>
                        <?php foreach ($creatableStaffRoles as $roleOption): ?>
                            <option value="<?= $staffEscape($roleOption) ?>" <?= ($_POST['staff_role'] ?? '') === $roleOption ? 'selected' : '' ?>>
                                <?= $staffEscape($roleOption) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="new-staff-password">Initial Password</label>
                    <input type="password" id="new-staff-password" name="password"
                        autocomplete="new-password" minlength="12" required>
                </div>
                <button type="submit">Create Account</button>
            </form>
            <?php if ($staffCreateError !== null): ?>
                <p class="report-error" role="alert"><?= $staffEscape($staffCreateError) ?></p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <form method="get" class="report-filters">
        <div>
            <label for="staff-search">Username</label>
            <input type="search" id="staff-search" name="q" maxlength="100" value="<?= $staffEscape($staffSearch) ?>">
        </div>
        <div>
            <label for="staff-role">Role</label>
            <select id="staff-role" name="role">
                <option value="">All roles</option>
                <?php foreach ($staffRoles as $roleOption): ?>
                    <option value="<?= $staffEscape($roleOption) ?>" <?= $staffRole === $roleOption ? 'selected' : '' ?>>
                        <?= $staffEscape($roleOption) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit">Apply</button>
        <a href="staff_accounts.php">Reset</a>
    </form>

    <?php if ($staffError !== null): ?>
        <p class="report-error" role="alert"><?= $staffEscape($staffError) ?></p>
    <?php elseif (!$staffAccounts): ?>
        <p class="report-empty">No staff accounts match these filters.</p>
    <?php else: ?>
        <section class="report-section staff-results" aria-label="Matching staff accounts">
            <div class="report-table">
                <table aria-label="Staff accounts">
                    <thead>
                        <tr>
                            <th scope="col">Username</th>
                            <th scope="col">Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staffAccounts as $staffAccount): ?>
                            <tr>
                                <th scope="row"><?= $staffEscape($staffAccount['username']) ?></th>
                                <td><?= $staffEscape($staffAccount['role'] ?? 'Unassigned') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</main>

</body>
</html>
