<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$db      = getDB();
$perPage = 20;
$page    = getInt('page', 1);
$search  = getStr('search');
$role    = getStr('role');
$status  = getStr('status');

// Build query
$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = '(name LIKE ? OR email LIKE ? OR company LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($role !== '') {
    $where[]  = 'role = ?';
    $params[] = $role;
}
if ($status !== '') {
    $where[]  = 'status = ?';
    $params[] = $status;
}

$whereStr = 'WHERE ' . implode(' AND ', $where);

$total    = (int)$db->prepare("SELECT COUNT(*) FROM users $whereStr")->execute($params) ? (int)$db->prepare("SELECT COUNT(*) FROM users $whereStr")->execute($params) : 0;
// Re-execute for count
$cntStmt = $db->prepare("SELECT COUNT(*) FROM users $whereStr");
$cntStmt->execute($params);
$total = (int)$cntStmt->fetchColumn();

$pag    = buildPagination($total, $perPage, $page, '/admin/users.php?search=' . urlencode($search) . '&role=' . urlencode($role) . '&status=' . urlencode($status));
$offset = $pag['offset'];

$stmt = $db->prepare("SELECT * FROM users $whereStr ORDER BY created_at DESC LIMIT ? OFFSET ?");
$params[] = $perPage;
$params[] = $offset;
$stmt->execute($params);
$users = $stmt->fetchAll();

// Lead counts per user
$leadCountStmt = $db->query('SELECT assigned_customer, COUNT(*) AS cnt FROM creators WHERE assigned_customer IS NOT NULL GROUP BY assigned_customer');
$leadCounts    = [];
foreach ($leadCountStmt->fetchAll() as $lc) {
    $leadCounts[(int)$lc['assigned_customer']] = (int)$lc['cnt'];
}

$pageTitle = 'Manage Users';
$activeNav = 'users';
require __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h5 class="fw-bold mb-0">Manage Users</h5>
        <p class="text-muted small mb-0"><?= number_format($total) ?> user(s) found</p>
    </div>
    <a href="/admin/user-form.php" class="btn btn-danger btn-sm">
        <i class="bi bi-person-plus me-1"></i>Add User
    </a>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px">
    <div class="card-body py-3">
        <form method="GET" action="/admin/users.php" class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Search name, email or company…" value="<?= e($search) ?>">
            </div>
            <div class="col-6 col-md-2">
                <select name="role" class="form-select form-select-sm auto-submit">
                    <option value="">All Roles</option>
                    <option value="customer" <?= $role === 'customer' ? 'selected' : '' ?>>Customer</option>
                    <option value="admin"    <?= $role === 'admin'    ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="status" class="form-select form-select-sm auto-submit">
                    <option value="">All Statuses</option>
                    <option value="active"   <?= $status === 'active'   ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="pending"  <?= $status === 'pending'  ? 'selected' : '' ?>>Pending</option>
                </select>
            </div>
            <div class="col-12 col-md-auto d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-danger">
                    <i class="bi bi-search me-1"></i>Search
                </button>
                <a href="/admin/users.php" class="btn btn-sm btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="table-card mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Company</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Leads</th>
                    <th>Joined</th>
                    <th>Last Login</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td class="text-muted small"><?= (int)$u['id'] ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0"
                                 style="width:32px;height:32px;background:#6366f1;font-size:.8rem">
                                <?= strtoupper(substr($u['name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="fw-semibold small"><?= e($u['name']) ?></div>
                                <?php if ($u['phone']): ?>
                                    <div class="text-muted" style="font-size:.72rem"><?= e($u['phone']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="small text-muted"><?= e($u['email']) ?></td>
                    <td class="small"><?= $u['company'] ? e($u['company']) : '<span class="text-muted">—</span>' ?></td>
                    <td><span class="badge bg-<?= $u['role'] === 'admin' ? 'dark' : 'secondary' ?>"><?= ucfirst(e($u['role'])) ?></span></td>
                    <td><?= statusBadge($u['status']) ?></td>
                    <td>
                        <?php $lc = $leadCounts[(int)$u['id']] ?? 0; ?>
                        <?php if ($lc > 0): ?>
                            <a href="/admin/leads.php?customer=<?= (int)$u['id'] ?>" class="badge bg-primary text-decoration-none">
                                <?= number_format($lc) ?>
                            </a>
                        <?php else: ?>
                            <span class="text-muted small">0</span>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted"><?= $u['created_at'] ? date('d M Y', strtotime($u['created_at'])) : '—' ?></td>
                    <td class="small text-muted"><?= $u['last_login'] ? date('d M Y', strtotime($u['last_login'])) : 'Never' ?></td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="/admin/user-form.php?id=<?= (int)$u['id'] ?>"
                               class="btn btn-sm btn-outline-primary py-0 px-2" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                            <a href="/admin/user-delete.php?id=<?= (int)$u['id'] ?>&csrf=<?= urlencode(csrfToken()) ?>"
                               class="btn btn-sm btn-outline-danger py-0 px-2 btn-confirm-delete"
                               data-label="<?= e($u['name']) ?>" title="Delete">
                                <i class="bi bi-trash"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                <tr><td colspan="10" class="text-center py-4 text-muted">No users found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
<div class="d-flex justify-content-between align-items-center">
    <p class="text-muted small mb-0">
        Showing <?= number_format(min($offset + 1, $total)) ?>–<?= number_format(min($offset + $perPage, $total)) ?> of <?= number_format($total) ?>
    </p>
    <?= paginationHtml($pag) ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
