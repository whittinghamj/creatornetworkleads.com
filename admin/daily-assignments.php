<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$db = getDB();
ensurePackagesSchema($db);

$summary = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $summary = assignAvailableLeadsForAllCustomers($db);
}

$pageTitle = 'Daily Lead Assignments';
$activeNav = 'daily-assignments';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h5 class="fw-bold mb-0">Daily Lead Assignment</h5>
        <p class="text-muted small mb-0">Assigns only Available creators, based on each customer's package leads/day limit.</p>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4" style="border-radius:14px">
    <div class="card-body p-4">
        <form method="POST" action="/admin/daily-assignments.php">
            <?= csrfField() ?>
            <button type="submit" class="btn btn-danger">
                <i class="bi bi-play-circle me-1"></i>Run Daily Assignment Now
            </button>
        </form>
    </div>
</div>

<?php if ($summary !== null): ?>
<div class="card border-0 shadow-sm" style="border-radius:14px">
    <div class="card-header bg-white border-bottom fw-semibold py-3">Run Results</div>
    <div class="card-body p-3">
        <div class="d-flex flex-wrap gap-4 mb-3">
            <div><strong>Total Assigned:</strong> <?= number_format((int)$summary['total_assigned']) ?></div>
            <div><strong>Customers Processed:</strong> <?= number_format((int)$summary['customer_count']) ?></div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Assigned</th>
                        <th>Remaining Today</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($summary['results'] as $result): ?>
                    <tr>
                        <td class="fw-semibold"><?= e((string)$result['name']) ?> <span class="text-muted small">(#<?= (int)$result['user_id'] ?>)</span></td>
                        <td><span class="badge bg-primary"><?= (int)$result['assigned'] ?></span></td>
                        <td><?= (int)$result['remaining'] ?></td>
                        <td class="text-muted small"><?= e((string)$result['reason']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($summary['results'])): ?>
                    <tr><td colspan="4" class="text-muted text-center py-3">No package customers found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
