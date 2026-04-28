<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$db = getDB();
ensurePackagesSchema($db);

$packages = getPackages($db);

$assignedCountsStmt = $db->query(
    'SELECT package_id, COUNT(*) AS cnt
     FROM users
     WHERE role = "customer" AND package_id IS NOT NULL
     GROUP BY package_id'
);
$assignedCounts = [];
foreach ($assignedCountsStmt->fetchAll() as $row) {
    $assignedCounts[(int)$row['package_id']] = (int)$row['cnt'];
}

$pageTitle = 'Packages';
$activeNav = 'packages';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h5 class="fw-bold mb-0">Lead Packages</h5>
        <p class="text-muted small mb-0"><?= number_format(count($packages)) ?> package(s)</p>
    </div>
    <a href="/admin/package-form.php" class="btn btn-danger btn-sm">
        <i class="bi bi-plus-circle me-1"></i>Add Package
    </a>
</div>

<div class="table-card mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>PayPal Plan ID</th>
                    <th>Leads / Day</th>
                    <th>Price / Month</th>
                    <th>Customers</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($packages as $package): ?>
                <tr>
                    <td class="text-muted small"><?= (int)$package['id'] ?></td>
                    <td class="fw-semibold"><?= e($package['name']) ?></td>
                    <td class="small text-muted"><?= e((string)($package['description'] ?? '')) ?></td>
                    <td class="small text-break"><?= !empty($package['paypal_plan_id']) ? e((string)$package['paypal_plan_id']) : '<span class="text-muted">Not set</span>' ?></td>
                    <td><span class="badge bg-primary"><?= (int)$package['leads_per_day'] ?></span></td>
                    <td class="fw-semibold">£<?= number_format((float)$package['price_per_month'], 2) ?></td>
                    <td>
                        <span class="badge bg-secondary"><?= (int)($assignedCounts[(int)$package['id']] ?? 0) ?></span>
                    </td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="/admin/package-form.php?id=<?= (int)$package['id'] ?>"
                               class="btn btn-sm btn-outline-primary py-0 px-2" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="/admin/package-delete.php?id=<?= (int)$package['id'] ?>&csrf=<?= urlencode(csrfToken()) ?>"
                               class="btn btn-sm btn-outline-danger py-0 px-2 btn-confirm-delete"
                               data-label="<?= e($package['name']) ?>" title="Delete">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($packages)): ?>
                <tr><td colspan="8" class="text-center py-4 text-muted">No packages created yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
