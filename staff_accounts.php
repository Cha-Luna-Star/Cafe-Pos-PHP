<?php
require __DIR__ . '/controllers/staff_accounts.php';

$staffEscape = static fn($value): string => htmlspecialchars(
    is_string($value) ? $value : '',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
$retryResetId = $staffResetError !== null ? requestSqlInt($_POST['user_id'] ?? null) : false;
$retryAccountVisible = $retryResetId !== false && array_any($staffAccounts, static fn(array $account): bool =>
    (int)$account['id'] === $retryResetId && in_array($account['role'], $creatableStaffRoles, true)
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
    <link rel="stylesheet" href="assets/css/table_sort.css">
    <script src="assets/js/staff_accounts.js" defer></script>
    <link rel="stylesheet" href="assets/css/ui.css">
</head>
<body class="table-page">

<?php
$appPageTitle = 'Staff Accounts';
require __DIR__ . '/includes/sidebar.php';
?>

<main class="ui-page report-content staff-accounts">
    <?php if ($staffNotice !== null): ?>
        <p role="status"><?= $staffEscape($staffNotice) ?></p>
    <?php endif; ?>

    <?php if (($_SESSION['role'] ?? '') === 'Admin'): ?>
        <section class="ui-panel report-section" aria-labelledby="staff-create-heading">
            <h2 id="staff-create-heading">Add Staff Account</h2>
            <form method="post" action="staff_accounts.php" class="report-filters">
                <input type="hidden" name="staff_action" value="create">
                <input type="hidden" name="csrf_token" value="<?= $staffEscape(sessionCsrfToken()) ?>">
                <div>
                    <label for="new-staff-username">Username</label>
                    <input class="ui-field" id="new-staff-username" name="username" maxlength="100"
                        value="<?= $staffEscape($_POST['username'] ?? '') ?>" required>
                </div>
                <div>
                    <label for="new-staff-role">Role</label>
                    <select class="ui-field" id="new-staff-role" name="staff_role" required>
                        <?php foreach ($creatableStaffRoles as $roleOption): ?>
                            <option value="<?= $staffEscape($roleOption) ?>" <?= ($_POST['staff_role'] ?? '') === $roleOption ? 'selected' : '' ?>>
                                <?= $staffEscape($roleOption) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="new-staff-password">Initial Password</label>
                    <input class="ui-field" type="password" id="new-staff-password" name="password"
                        autocomplete="new-password" minlength="12" required>
                </div>
                <button data-tone="positive" class="ui-button" type="submit">Create Account</button>
            </form>
            <?php if ($staffCreateError !== null): ?>
                <p class="report-error" role="alert"><?= $staffEscape($staffCreateError) ?></p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <form method="get" class="ui-filter-bar report-filters" aria-label="Filter staff accounts">
        <div>
            <label for="staff-search">Username</label>
            <input class="ui-field" type="search" id="staff-search" name="q" maxlength="100" value="<?= $staffEscape($staffSearch) ?>">
        </div>
        <div>
            <label for="staff-role">Role</label>
            <select class="ui-field" id="staff-role" name="role">
                <option value="">All roles</option>
                <?php foreach ($staffRoles as $roleOption): ?>
                    <option value="<?= $staffEscape($roleOption) ?>" <?= $staffRole === $roleOption ? 'selected' : '' ?>>
                        <?= $staffEscape($roleOption) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button data-tone="positive" class="ui-button" type="submit">Apply</button>
        <button class="ui-button" type="submit" form="clear-staff-filters">Reset Filters</button>
    </form>
    <form id="clear-staff-filters" method="get" action="staff_accounts.php" hidden></form>

    <?php if ($staffRoleError !== null): ?>
        <p class="report-error" role="alert"><?= $staffEscape($staffRoleError) ?></p>
    <?php endif; ?>

    <?php if ($staffStatusError !== null): ?>
        <p class="report-error" role="alert"><?= $staffEscape($staffStatusError) ?></p>
    <?php elseif ($staffError === null && !$staffStatusAvailable && ($_SESSION['role'] ?? '') === 'Admin'): ?>
        <p class="report-note">Apply the staff account status database migration to enable deactivation and role changes.</p>
    <?php endif; ?>

    <?php if ($staffResetError !== null && !$retryAccountVisible): ?>
        <p class="report-error" role="alert"><?= $staffEscape($staffResetError) ?></p>
    <?php endif; ?>

    <?php if ($staffError !== null): ?>
        <p class="report-error" role="alert"><?= $staffEscape($staffError) ?></p>
    <?php elseif (!$staffAccounts): ?>
        <p class="ui-panel report-empty">No staff accounts match these filters.</p>
    <?php else: ?>
        <section class="ui-panel report-section report-table staff-results table-scroll-panel" aria-label="Matching staff accounts" tabindex="0">
            <table aria-label="Staff accounts">
                <thead>
                    <tr>
                        <th scope="col">Username</th>
                        <th scope="col">Role</th>
                        <?php if ($staffStatusAvailable): ?>
                            <th scope="col">Account Status</th>
                        <?php endif; ?>
                        <?php if (($_SESSION['role'] ?? '') === 'Admin'): ?>
                            <th scope="col" class="staff-actions-column">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staffAccounts as $staffAccount): ?>
                        <?php $isActive = $staffStatusAvailable && (int)$staffAccount['is_active'] === 1; ?>
                        <tr>
                            <th scope="row"><?= $staffEscape($staffAccount['username']) ?></th>
                            <td><?= $staffEscape($staffAccount['role'] ?? 'Unassigned') ?></td>
                            <?php if ($staffStatusAvailable): ?>
                                <td>
                                    <span class="ui-badge" data-tone="<?= $isActive ? 'positive' : '' ?>"><?= $isActive ? 'Active' : 'Inactive' ?></span>
                                </td>
                            <?php endif; ?>
                            <?php if (($_SESSION['role'] ?? '') === 'Admin'): ?>
                                <td>
                                    <?php if (in_array($staffAccount['role'], $creatableStaffRoles, true)): ?>
                                        <div class="staff-row-actions">
                                            <button data-tone="danger" type="button" class="ui-button ui-button-sm" data-reset-staff
                                                data-staff-id="<?= (int)$staffAccount['id'] ?>"
                                                data-staff-name="<?= $staffEscape($staffAccount['username']) ?>">
                                                Reset Password
                                            </button>
                                            <?php if ($staffStatusAvailable): ?>
                                                <?php
                                                $statusAction = $isActive ? 'Deactivate' : 'Reactivate';
                                                $statusTone = $isActive ? 'warning' : 'positive';
                                                ?>
                                                <?php $nextRole = $staffAccount['role'] === 'Cashier' ? 'Manager' : 'Cashier'; ?>
                                                <details>
                                                    <summary data-tone="info" class="ui-button ui-button-sm">Change Role</summary>
                                                    <form method="post" action="staff_accounts.php" class="report-filters">
                                                        <input type="hidden" name="staff_action" value="change_role">
                                                        <input type="hidden" name="user_id" value="<?= (int)$staffAccount['id'] ?>">
                                                        <input type="hidden" name="csrf_token" value="<?= $staffEscape(sessionCsrfToken()) ?>">
                                                        <input type="hidden" name="current_role" value="<?= $staffEscape($staffAccount['role']) ?>">
                                                        <input type="hidden" name="new_role" value="<?= $staffEscape($nextRole) ?>">
                                                        <button data-tone="info" class="ui-button" type="submit">
                                                            Change <?= $staffEscape($staffAccount['username']) ?> to <?= $staffEscape($nextRole) ?>
                                                        </button>
                                                    </form>
                                                </details>
                                                <details>
                                                    <summary data-tone="<?= $statusTone ?>" class="ui-button ui-button-sm"><?= $statusAction ?> Account</summary>
                                                    <form method="post" action="staff_accounts.php" class="report-filters">
                                                        <input type="hidden" name="staff_action" value="change_status">
                                                        <input type="hidden" name="user_id" value="<?= (int)$staffAccount['id'] ?>">
                                                        <input type="hidden" name="csrf_token" value="<?= $staffEscape(sessionCsrfToken()) ?>">
                                                        <input type="hidden" name="account_status" value="<?= strtolower($statusAction) ?>">
                                                        <button data-tone="<?= $statusTone ?>" class="ui-button" type="submit"><?= $statusAction ?> <?= $staffEscape($staffAccount['username']) ?></button>
                                                    </form>
                                                </details>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>
</main>

<?php if (($_SESSION['role'] ?? '') === 'Admin'): ?>
    <dialog id="staff-reset-dialog" class="ui-dialog staff-action-dialog" aria-labelledby="reset-staff-heading"
        data-retry-id="<?= (int)$retryResetId ?>">
        <form method="post" action="staff_accounts.php">
            <h2 id="reset-staff-heading">Reset Password</h2>
            <p>Account <strong id="reset-staff-name"></strong></p>
            <input type="hidden" name="staff_action" value="reset_password">
            <input type="hidden" name="user_id" id="reset-staff-id">
            <input type="hidden" name="csrf_token" value="<?= $staffEscape(sessionCsrfToken()) ?>">
            <label for="reset-staff-password">New password</label>
            <input class="ui-field" type="password" id="reset-staff-password" name="password" autocomplete="new-password"
                minlength="12" aria-describedby="reset-staff-help" required>
            <small id="reset-staff-help">Use at least 12 characters. Saving signs this person out of existing sessions.</small>
            <p id="reset-staff-error" class="report-error" role="alert" hidden><?= $staffEscape($staffResetError) ?></p>
            <div class="staff-dialog-buttons">
                <button data-tone="danger" class="ui-button" type="button" id="cancel-staff-reset">Cancel</button>
                <button data-tone="danger" class="ui-button" type="submit">Reset Password</button>
            </div>
        </form>
    </dialog>
<?php endif; ?>

</body>
</html>
