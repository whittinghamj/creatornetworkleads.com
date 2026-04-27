<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$db      = getDB();
ensureCreatorsLeadTrackingSchema($db);
$perPage = 25;
$page    = getInt('page', 1);
$search  = getStr('search');
$region  = getStr('region');
$typeF   = getInt('type');
$statusF = getStr('status');
$customerStatusF = getStr('customer_status');
$custF   = getInt('customer');
$filter  = getStr('filter'); // 'unassigned', 'assigned', or 'scraped'

// Build WHERE
$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = '(c.username LIKE ? OR c.display_name LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($region !== '') {
    $where[]  = 'c.backstage_region = ?';
    $params[] = $region;
}
if ($typeF > 0) {
    $where[]  = 'c.invitation_type = ?';
    $params[] = $typeF;
}
if ($statusF !== '') {
    $where[]  = 'c.backstage_status = ?';
    $params[] = $statusF;
}
if ($customerStatusF !== '') {
    $where[]  = 'c.customer_status = ?';
    $params[] = $customerStatusF;
}
if ($custF > 0) {
    $where[]  = 'c.assigned_customer = ?';
    $params[] = $custF;
} elseif ($filter === 'unassigned') {
    $where[] = 'c.assigned_customer IS NULL';
} elseif ($filter === 'assigned') {
    $where[] = 'c.assigned_customer IS NOT NULL';
} elseif ($filter === 'scraped') {
    $where[] = "LOWER(COALESCE(c.backstage_checked, '')) = 'yes'";
}

$whereStr = 'WHERE ' . implode(' AND ', $where);

$cntStmt = $db->prepare("SELECT COUNT(*) FROM creators c $whereStr");
$cntStmt->execute($params);
$total = (int)$cntStmt->fetchColumn();

$baseUrl = '/admin/leads.php?search=' . urlencode($search)
    . '&region=' . urlencode($region)
    . '&type=' . $typeF
    . '&status=' . urlencode($statusF)
    . '&customer_status=' . urlencode($customerStatusF)
    . '&customer=' . $custF
    . '&filter=' . urlencode($filter);

$pag    = buildPagination($total, $perPage, $page, $baseUrl);
$offset = $pag['offset'];

$stmtParams   = $params;
$stmtParams[] = $perPage;
$stmtParams[] = $offset;

$stmt = $db->prepare(
    "SELECT c.*, u.name AS customer_name, u.email AS customer_email
     FROM creators c
     LEFT JOIN users u ON u.id = c.assigned_customer
     $whereStr
     ORDER BY c.id DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute($stmtParams);
$leads = $stmt->fetchAll();

$statsStmt = $db->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN LOWER(COALESCE(c.backstage_status, '')) = 'available' THEN 1 ELSE 0 END) AS available_count,
        SUM(CASE WHEN c.customer_status = 'contacted' THEN 1 ELSE 0 END) AS contacted_count,
        SUM(CASE WHEN c.customer_status = 'invited'  THEN 1 ELSE 0 END) AS invited_count,
        SUM(CASE WHEN c.customer_status = 'accepted' THEN 1 ELSE 0 END) AS accepted_count,
        SUM(CASE WHEN c.customer_status = 'declined' THEN 1 ELSE 0 END) AS declined_count
     FROM creators c
     $whereStr"
);
$statsStmt->execute($params);
$quickStats = $statsStmt->fetch() ?: [
    'total' => 0,
    'available_count' => 0,
    'contacted_count' => 0,
    'invited_count' => 0,
    'accepted_count' => 0,
    'declined_count' => 0,
];

// Filter options
$regionRows   = $db->query('SELECT DISTINCT backstage_region FROM creators ORDER BY backstage_region')->fetchAll(PDO::FETCH_COLUMN);
$statusRows   = $db->query('SELECT DISTINCT backstage_status FROM creators ORDER BY backstage_status')->fetchAll(PDO::FETCH_COLUMN);
$customerStatusRows = ['new', 'contacted', 'invited', 'accepted', 'declined'];
$invTypes     = getInvitationTypes();
$customers    = $db->query("SELECT id, name, email FROM users WHERE role='customer' AND status='active' ORDER BY name")->fetchAll();

// Header label
$filterLabel = match(true) {
    $filter === 'unassigned' => 'Unassigned Leads',
    $filter === 'assigned'   => 'Assigned Leads',
    $filter === 'scraped'    => 'Scraped Leads',
    $custF > 0               => 'Leads for Customer',
    default                  => 'All Creator Leads',
};

$pageTitle = $filterLabel;
$activeNav = ($filter === 'unassigned') ? 'leads-unassigned' : 'leads';
require __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h5 class="fw-bold mb-0"><?= e($filterLabel) ?></h5>
        <p class="text-muted small mb-0"><?= number_format($total) ?> creator lead(s)</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/leads.php" class="btn btn-sm btn-outline-secondary <?= $filter === '' && $custF === 0 ? 'active' : '' ?>">All</a>
        <a href="/admin/leads.php?filter=unassigned" class="btn btn-sm btn-outline-primary <?= $filter === 'unassigned' ? 'active' : '' ?>">Unassigned</a>
        <a href="/admin/leads.php?filter=assigned"   class="btn btn-sm btn-outline-success <?= $filter === 'assigned'   ? 'active' : '' ?>">Assigned</a>
        <a href="/admin/leads.php?filter=scraped"    class="btn btn-sm btn-outline-info <?= $filter === 'scraped'    ? 'active' : '' ?>">Scraped</a>
    </div>
</div>

<!-- Quick Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3 col-lg-2">
        <div class="card border-0 shadow-sm text-center py-3" style="border-radius:12px">
            <div class="text-muted small">Total</div>
            <div class="fw-bold fs-4"><?= number_format((int)$quickStats['total']) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <div class="card border-0 shadow-sm text-center py-3" style="border-radius:12px">
            <div class="text-muted small">Available</div>
            <div class="fw-bold fs-4 text-success"><?= number_format((int)$quickStats['available_count']) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <div class="card border-0 shadow-sm text-center py-3" style="border-radius:12px">
            <div class="text-muted small">Contacted</div>
            <div class="fw-bold fs-4 text-primary"><?= number_format((int)$quickStats['contacted_count']) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <div class="card border-0 shadow-sm text-center py-3" style="border-radius:12px">
            <div class="text-muted small">Invited</div>
            <div class="fw-bold fs-4 text-info"><?= number_format((int)$quickStats['invited_count']) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <div class="card border-0 shadow-sm text-center py-3" style="border-radius:12px">
            <div class="text-muted small">Accepted</div>
            <div class="fw-bold fs-4 text-success"><?= number_format((int)$quickStats['accepted_count']) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <div class="card border-0 shadow-sm text-center py-3" style="border-radius:12px">
            <div class="text-muted small">Declined</div>
            <div class="fw-bold fs-4 text-danger"><?= number_format((int)$quickStats['declined_count']) ?></div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px">
    <div class="card-body py-3">
        <form method="GET" action="/admin/leads.php" class="row g-2 align-items-end">
            <input type="hidden" name="filter" value="<?= e($filter) ?>">
            <div class="col-12 col-md-3">
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Username or display name…" value="<?= e($search) ?>">
            </div>
            <div class="col-6 col-md-2">
                <select name="region" class="form-select form-select-sm auto-submit">
                    <option value="">All Regions</option>
                    <?php foreach ($regionRows as $r): ?>
                        <option value="<?= e($r) ?>" <?= $region === $r ? 'selected' : '' ?>>
                            <?= strtoupper(e($r)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="type" class="form-select form-select-sm auto-submit">
                    <option value="0">All Types</option>
                    <?php foreach ($invTypes as $t): ?>
                        <option value="<?= (int)$t['id'] ?>" <?= $typeF === (int)$t['id'] ? 'selected' : '' ?>>
                            <?= e($t['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="status" class="form-select form-select-sm auto-submit">
                    <option value="">All Statuses</option>
                    <?php foreach ($statusRows as $s): ?>
                        <option value="<?= e($s) ?>" <?= $statusF === $s ? 'selected' : '' ?>>
                            <?= ucfirst(e($s)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="customer_status" class="form-select form-select-sm auto-submit">
                    <option value="">All Customer Statuses</option>
                    <?php foreach ($customerStatusRows as $cs): ?>
                        <option value="<?= e($cs) ?>" <?= $customerStatusF === $cs ? 'selected' : '' ?>>
                            <?= ucfirst(e($cs)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="customer" class="form-select form-select-sm auto-submit">
                    <option value="0">All Customers</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $custF === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?> (<?= e($c['email']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-danger flex-fill"><i class="bi bi-search"></i></button>
                <a href="/admin/leads.php<?= $filter ? '?filter='.$filter : '' ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
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
                    <th style="width:40px">#</th>
                    <th>Creator</th>
                    <th>Username</th>
                    <th>Region</th>
                    <th>Invitation Type</th>
                    <th>Backstage Status</th>
                    <th>Customer Status</th>
                    <th>Checked</th>
                    <th>Assigned To</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leads as $lead): ?>
                <tr>
                    <td class="text-muted small"><?= (int)$lead['id'] ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?= avatarImg($lead['avatar'], $lead['display_name'] ?: $lead['username'] ?: '?', '36') ?>
                            <span class="fw-semibold small"><?= e($lead['display_name'] ?: '—') ?></span>
                        </div>
                    </td>
                    <td class="text-muted small">@<?= e($lead['username'] ?: '—') ?></td>
                    <td>
                        <span class="badge bg-dark"><?= strtoupper(e($lead['backstage_region'] ?? '')) ?></span>
                        <div class="text-muted small"><?= e(getRegionName($lead['backstage_region'] ?? '')) ?></div>
                    </td>
                    <td><?= invitationTypeBadge(isset($lead['invitation_type']) ? (int)$lead['invitation_type'] : null) ?></td>
                    <td><?= statusBadge($lead['backstage_status'] ?? 'unknown') ?></td>
                    <td><?= statusBadge($lead['customer_status'] ?? 'new') ?></td>
                    <td><?= statusBadge($lead['backstage_checked'] ?? 'no') ?></td>
                    <td>
                        <?php if ($lead['assigned_customer']): ?>
                            <div class="small fw-semibold"><?= e($lead['customer_name'] ?? 'Unknown') ?></div>
                            <div class="text-muted" style="font-size:.72rem"><?= e($lead['customer_email'] ?? '') ?></div>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Unassigned</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="/admin/lead-form.php?id=<?= (int)$lead['id'] ?>"
                               class="btn btn-sm btn-outline-primary py-0 px-2" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="/admin/lead-assign.php?id=<?= (int)$lead['id'] ?>"
                               class="btn btn-sm btn-outline-success py-0 px-2" title="Assign">
                                <i class="bi bi-person-check"></i>
                            </a>
                            <a href="/admin/lead-delete.php?id=<?= (int)$lead['id'] ?>&csrf=<?= urlencode(csrfToken()) ?>"
                               class="btn btn-sm btn-outline-danger py-0 px-2 btn-confirm-delete"
                               data-label="creator #<?= (int)$lead['id'] ?>" title="Delete">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($leads)): ?>
                <tr><td colspan="10" class="text-center py-4 text-muted">No leads found.</td></tr>
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
