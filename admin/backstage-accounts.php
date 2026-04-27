<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$db = getDB();
ensureBackstageAccountsSchema($db);

$accounts = $db->query(
    'SELECT id, created_at, updated_at, email, password, label, is_active,
            last_used_at, last_success_at, last_failure_at
     FROM backstage_accounts
     ORDER BY is_active DESC, id ASC'
)->fetchAll();

$activeCount = 0;
foreach ($accounts as $account) {
    if ((int)$account['is_active'] === 1) {
        $activeCount++;
    }
}

$pageTitle = 'Backstage Accounts';
$activeNav = 'backstage-accounts';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h5 class="fw-bold mb-0">Backstage Accounts</h5>
        <p class="text-muted small mb-0"><?= number_format(count($accounts)) ?> account(s), <?= number_format($activeCount) ?> active</p>
    </div>
    <a href="/admin/backstage-account-form.php" class="btn btn-danger btn-sm">
        <i class="bi bi-plus-circle me-1"></i>Add Account
    </a>
</div>

<div class="card border-0 shadow-sm mb-3" style="border-radius:12px">
    <div class="card-body py-3">
        <p class="mb-0 small text-muted">
            Active accounts are used by <strong>run-bulk.sh</strong> for random rotation. Inactive rows are ignored.
        </p>
    </div>
</div>

<div class="table-card mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Label</th>
                    <th>Email</th>
                    <th>Password</th>
                    <th>Status</th>
                    <th>Last Used</th>
                    <th>Last Success</th>
                    <th>Last Failure</th>
                    <th>Updated</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($accounts as $account): ?>
                <tr>
                    <td class="text-muted small"><?= (int)$account['id'] ?></td>
                    <td class="fw-semibold"><?= e((string)($account['label'] ?: '—')) ?></td>
                    <td><?= e((string)$account['email']) ?></td>
                    <td>
                        <code class="small"><?= e((string)$account['password']) ?></code>
                    </td>
                    <td>
                        <?= (int)$account['is_active'] === 1
                            ? '<span class="badge bg-success">Active</span>'
                            : '<span class="badge bg-secondary">Inactive</span>' ?>
                    </td>
                    <td class="small text-muted">
                        <?= !empty($account['last_used_at']) ? e(date('d M Y H:i', strtotime((string)$account['last_used_at']))) : '—' ?>
                    </td>
                    <td class="small text-muted">
                        <?= !empty($account['last_success_at']) ? e(date('d M Y H:i', strtotime((string)$account['last_success_at']))) : '—' ?>
                    </td>
                    <td class="small text-muted">
                        <?= !empty($account['last_failure_at']) ? e(date('d M Y H:i', strtotime((string)$account['last_failure_at']))) : '—' ?>
                    </td>
                    <td class="small text-muted">
                        <?= !empty($account['updated_at']) ? e(date('d M Y H:i', strtotime((string)$account['updated_at']))) : '—' ?>
                    </td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="/admin/backstage-account-form.php?id=<?= (int)$account['id'] ?>"
                               class="btn btn-sm btn-outline-primary py-0 px-2" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="/admin/backstage-account-delete.php?id=<?= (int)$account['id'] ?>&csrf=<?= urlencode(csrfToken()) ?>"
                               class="btn btn-sm btn-outline-danger py-0 px-2 btn-confirm-delete"
                               data-label="<?= e((string)$account['email']) ?>" title="Delete">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($accounts)): ?>
                <tr><td colspan="10" class="text-center py-4 text-muted">No backstage accounts configured yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>