<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$db      = getDB();
ensurePackagesSchema($db);
ensureUserIpTrackingSchema($db);
$perPage = 20;
$page    = getInt('page', 1);
$search  = getStr('search');
$role    = getStr('role');
$status  = getStr('status');
$export  = getStr('export');

$exportWindows = [
    'today' => [
        'label' => "Today's Leads",
        'from' => date('Y-m-d'),
        'to' => date('Y-m-d'),
        'filename' => 'todays-leads',
    ],
    'week' => [
        'label' => "This Week's Leads",
        'from' => date('Y-m-d', strtotime('monday this week')),
        'to' => date('Y-m-d'),
        'filename' => 'this-weeks-leads',
    ],
    'month' => [
        'label' => "This Month's Leads",
        'from' => date('Y-m-01'),
        'to' => date('Y-m-d'),
        'filename' => 'this-months-leads',
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && postStr('action') === 'set_package') {
    verifyCsrf();

    $targetUserId = (int)postStr('user_id');
    $packageId    = (int)postStr('package_id');

    $usrStmt = $db->prepare('SELECT id, role, name FROM users WHERE id = ? LIMIT 1');
    $usrStmt->execute([$targetUserId]);
    $targetUser = $usrStmt->fetch();

    if (!$targetUser) {
        flash('User not found.', 'danger');
    } elseif ($targetUser['role'] !== 'customer') {
        flash('Packages can only be assigned to customer accounts.', 'danger');
    } else {
        if ($packageId > 0) {
            $pkgStmt = $db->prepare('SELECT id, name FROM packages WHERE id = ? LIMIT 1');
            $pkgStmt->execute([$packageId]);
            $package = $pkgStmt->fetch();
            if (!$package) {
                flash('Selected package does not exist.', 'danger');
            } else {
                $db->prepare('UPDATE users SET package_id = ? WHERE id = ?')->execute([$packageId, $targetUserId]);
                flash('Package updated for ' . $targetUser['name'] . '.', 'success');
            }
        } else {
            $db->prepare('UPDATE users SET package_id = NULL WHERE id = ?')->execute([$targetUserId]);
            flash('Package removed for ' . $targetUser['name'] . '.', 'success');
        }
    }

    header('Location: ' . ($_SERVER['REQUEST_URI'] ?? '/admin/users.php'));
    exit;
}

$packages = getPackages($db);

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

if ($export !== '' && isset($exportWindows[$export])) {
    $window = $exportWindows[$export];

    $exportStmt = $db->prepare(
        "SELECT u.*, p.name AS package_name
         FROM users u
         LEFT JOIN packages p ON p.id = u.package_id
         $whereStr
         ORDER BY u.created_at DESC, u.id DESC"
    );
    $exportStmt->execute($params);
    $exportUsers = $exportStmt->fetchAll();

    $currentLeadStmt = $db->query(
        'SELECT assigned_customer, COUNT(*) AS cnt
         FROM creators
         WHERE assigned_customer IS NOT NULL
         GROUP BY assigned_customer'
    );
    $currentLeadCounts = [];
    foreach ($currentLeadStmt->fetchAll() as $leadRow) {
        $currentLeadCounts[(int)$leadRow['assigned_customer']] = (int)$leadRow['cnt'];
    }

    $periodLeadStmt = $db->prepare(
        'SELECT user_id, SUM(assigned_count) AS cnt
         FROM customer_daily_lead_assignments
         WHERE assign_date >= ? AND assign_date <= ?
         GROUP BY user_id'
    );
    $periodLeadStmt->execute([$window['from'], $window['to']]);
    $periodLeadCounts = [];
    foreach ($periodLeadStmt->fetchAll() as $periodRow) {
        $periodLeadCounts[(int)$periodRow['user_id']] = (int)$periodRow['cnt'];
    }

    $filename = 'users-' . $window['filename'] . '-' . date('Ymd-His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'User ID',
        'Name',
        'Email',
        'Company',
        'Package',
        'Role',
        'Status',
        'Current Assigned Leads',
        $window['label'],
        'Joined',
        'Signup IP',
        'Last Login',
        'Last Login IP',
    ]);

    foreach ($exportUsers as $exportUser) {
        $userId = (int)$exportUser['id'];
        fputcsv($out, [
            $userId,
            (string)$exportUser['name'],
            (string)$exportUser['email'],
            (string)($exportUser['company'] ?? ''),
            (string)($exportUser['package_name'] ?? 'None'),
            (string)$exportUser['role'],
            (string)$exportUser['status'],
            $currentLeadCounts[$userId] ?? 0,
            $periodLeadCounts[$userId] ?? 0,
            !empty($exportUser['created_at']) ? date('Y-m-d', strtotime((string)$exportUser['created_at'])) : '',
            (string)($exportUser['signup_ip'] ?? ''),
            !empty($exportUser['last_login']) ? date('Y-m-d H:i:s', strtotime((string)$exportUser['last_login'])) : '',
            (string)($exportUser['last_login_ip'] ?? ''),
        ]);
    }

    fclose($out);
    exit;
}

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
    <div class="d-flex flex-wrap gap-2">
        <div class="dropdown">
            <button class="btn btn-sm export-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-download me-1"></i>Export CSV
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item" href="/admin/users.php?search=<?= urlencode($search) ?>&role=<?= urlencode($role) ?>&status=<?= urlencode($status) ?>&export=today">
                        Today's Leads
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="/admin/users.php?search=<?= urlencode($search) ?>&role=<?= urlencode($role) ?>&status=<?= urlencode($status) ?>&export=week">
                        This Week's Leads
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="/admin/users.php?search=<?= urlencode($search) ?>&role=<?= urlencode($role) ?>&status=<?= urlencode($status) ?>&export=month">
                        This Month's Leads
                    </a>
                </li>
            </ul>
        </div>
        <a href="/admin/user-form.php" class="btn btn-danger btn-sm">
            <i class="bi bi-person-plus me-1"></i>Add User
        </a>
    </div>
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
                    <th>Package</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Leads</th>
                    <th>Joined</th>
                    <th>Signup IP</th>
                    <th>Last Login</th>
                    <th>Last Login IP</th>
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
                    <td>
                        <?php if ($u['role'] === 'customer'): ?>
                            <form method="POST" action="/admin/users.php?search=<?= urlencode($search) ?>&role=<?= urlencode($role) ?>&status=<?= urlencode($status) ?>&page=<?= (int)$page ?>" class="d-flex gap-1 align-items-center">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="set_package">
                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                <select name="package_id" class="form-select form-select-sm" style="min-width:160px">
                                    <option value="0">No Package</option>
                                    <?php foreach ($packages as $package): ?>
                                        <option value="<?= (int)$package['id'] ?>" <?= (int)($u['package_id'] ?? 0) === (int)$package['id'] ? 'selected' : '' ?>>
                                            <?= e($package['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-sm btn-outline-primary py-0 px-2" title="Save package">
                                    <i class="bi bi-check2"></i>
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="text-muted small">—</span>
                        <?php endif; ?>
                    </td>
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
                    <td class="small text-muted"><?= !empty($u['signup_ip']) ? e((string)$u['signup_ip']) : '—' ?></td>
                    <td class="small text-muted"><?= $u['last_login'] ? date('d M Y', strtotime($u['last_login'])) : 'Never' ?></td>
                    <td class="small text-muted"><?= !empty($u['last_login_ip']) ? e((string)$u['last_login_ip']) : '—' ?></td>
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
                <tr><td colspan="13" class="text-center py-4 text-muted">No users found.</td></tr>
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
