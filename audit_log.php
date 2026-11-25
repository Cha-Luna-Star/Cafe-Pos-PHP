<?php
require __DIR__ . '/controllers/audit_log.php';

$auditEscape = static fn($value): string => htmlspecialchars(
    is_string($value) ? $value : '',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$auditPageUrl = static function (int $number) use ($search, $action, $from, $to): string {
    return 'audit_log.php?' . http_build_query(array_filter([
        'q' => $search,
        'action' => $action,
        'from' => $from,
        'to' => $to,
        'page' => $number
    ], static fn($value): bool => $value !== ''));
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cafe POS | Audit Log</title>
    <link rel="stylesheet" href="assets/css/reports.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
</head>
<body>

<?php
$appPageTitle = 'Audit Log';
require __DIR__ . '/includes/sidebar.php';
?>

<main class="report-content audit-log">
    <form method="get" class="report-filters">
        <div>
            <label for="audit-search">Search by user or details</label>
            <input type="search" id="audit-search" name="q" maxlength="100" value="<?= $auditEscape($search) ?>">
        </div>
        <div>
            <label for="audit-action">Action</label>
            <select id="audit-action" name="action">
                <option value="">All actions</option>
                <?php if (is_string($action) && $action !== '' && !in_array($action, $actions, true)): ?>
                    <option value="<?= $auditEscape($action) ?>" selected><?= $auditEscape($action) ?></option>
                <?php endif; ?>
                <?php foreach ($actions as $option): ?>
                    <option value="<?= $auditEscape($option) ?>" <?= $option === $action ? 'selected' : '' ?>>
                        <?= $auditEscape($option) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="audit-from">From</label>
            <input type="date" id="audit-from" name="from" value="<?= $auditEscape($from) ?>">
        </div>
        <div>
            <label for="audit-to">To</label>
            <input type="date" id="audit-to" name="to" value="<?= $auditEscape($to) ?>">
        </div>
        <button type="submit">Apply</button>
        <a href="audit_log.php">Reset</a>
    </form>

    <?php if ($auditError !== null): ?>
        <p class="report-error" role="alert"><?= $auditEscape($auditError) ?></p>
    <?php elseif (!$auditEntries): ?>
        <p class="report-empty">No audit entries match these filters.</p>
    <?php else: ?>
        <section class="report-section audit-entries" aria-label="Audit entries">
            <div class="report-table">
                <table aria-label="Audit events">
                    <thead>
                        <tr>
                            <th scope="col">Date and Time</th>
                            <th scope="col">User</th>
                            <th scope="col">Action</th>
                            <th scope="col">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auditEntries as $entry): ?>
                            <?php
                            $username = trim($entry['username'] ?? '');
                            if ($username === '') {
                                $username = $entry['user_id'] === null ? 'System' : 'User #' . (int)$entry['user_id'];
                            }
                            ?>
                            <tr>
                                <td><?= $auditEscape($entry['event_time'] ?? 'Unavailable') ?></td>
                                <td><?= $auditEscape($username) ?></td>
                                <td><?= $auditEscape($entry['action']) ?></td>
                                <td class="audit-details"><?= $auditEscape($entry['details']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <nav class="audit-pages" aria-label="Audit log pages">
                <?php if ($page > 1): ?>
                    <a rel="prev" href="<?= $auditEscape($auditPageUrl($page - 1)) ?>">Previous</a>
                <?php endif; ?>
                <span>Page <?= number_format($page) ?> of <?= number_format($pageCount) ?> · <?= number_format($totalEntries) ?> entries</span>
                <?php if ($page < $pageCount): ?>
                    <a rel="next" href="<?= $auditEscape($auditPageUrl($page + 1)) ?>">Next</a>
                <?php endif; ?>
            </nav>
        </section>
    <?php endif; ?>
</main>

</body>
</html>
