<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$user = getCurrentUser();
if (!$user) {
    logoutUser();
    header('Location: /login.php');
    exit;
}

$db        = getDB();
$userId    = (int)$_SESSION['user_id'];
$perPage   = 24;
$page      = getInt('page', 1);
$search    = getStr('search');
$region    = getStr('region');
$typeFilter = getInt('type');

ensureCreatorsLeadTrackingSchema($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && postStr('action') === 'update_customer_status') {
    verifyCsrf();

    $leadId = (int)postStr('lead_id');
    $newStatus = strtolower(postStr('customer_status'));
    $allowedStatuses = ['invited', 'accepted', 'declined'];

    if ($leadId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
        flash('Invalid lead status update.', 'danger');
    } else {
        $upStmt = $db->prepare(
            'UPDATE creators
             SET customer_status = ?
             WHERE id = ? AND assigned_customer = ?
             LIMIT 1'
        );
        $upStmt->execute([$newStatus, $leadId, $userId]);

        if ($upStmt->rowCount() > 0) {
            flash('Lead status updated.', 'success');
        } else {
            flash('Lead not found or not assigned to your account.', 'danger');
        }
    }

    header('Location: ' . ($_SERVER['REQUEST_URI'] ?? '/dashboard.php'));
    exit;
}

// Build WHERE clause
$where  = ['c.assigned_customer = ?'];
$params = [$userId];

if ($search !== '') {
    $where[]  = '(c.username LIKE ? OR c.display_name LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($region !== '') {
    $where[]  = 'c.backstage_region = ?';
    $params[] = $region;
}
if ($typeFilter > 0) {
    $where[]  = 'c.invitation_type = ?';
    $params[] = $typeFilter;
}

$whereStr = 'WHERE ' . implode(' AND ', $where);

// Count
$countStmt = $db->prepare("SELECT COUNT(*) FROM creators c $whereStr");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$pag    = buildPagination($total, $perPage, $page, '/dashboard.php?search=' . urlencode($search) . '&region=' . urlencode($region) . '&type=' . $typeFilter);
$offset = $pag['offset'];

// Fetch leads
$stmt = $db->prepare(
    "SELECT c.*
     FROM creators c
     $whereStr
    ORDER BY COALESCE(c.assigned_at, '1970-01-01 00:00:00') DESC, c.id DESC
     LIMIT ? OFFSET ?"
);
$params[] = $perPage;
$params[] = $offset;
$stmt->execute($params);
$leads = $stmt->fetchAll();

// Stats for sidebar
$statsStmt = $db->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN customer_status = 'invited'  THEN 1 ELSE 0 END) AS invited,
        SUM(CASE WHEN customer_status = 'accepted' THEN 1 ELSE 0 END) AS accepted,
        SUM(CASE WHEN customer_status = 'declined' THEN 1 ELSE 0 END) AS declined
     FROM creators WHERE assigned_customer = ?"
);
$statsStmt->execute([$userId]);
$stats = $statsStmt->fetch();

// Regions assigned to this user
$regionStmt = $db->prepare(
    'SELECT DISTINCT backstage_region FROM creators WHERE assigned_customer = ? ORDER BY backstage_region'
);
$regionStmt->execute([$userId]);
$myRegions = $regionStmt->fetchAll(PDO::FETCH_COLUMN);

$invTypes = getInvitationTypes();
$pageTitle = 'My Leads';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-light">

<!-- Topbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm sticky-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center gap-2" href="/dashboard.php">
            <svg width="26" height="26" viewBox="0 0 28 28" fill="none">
                <rect width="28" height="28" rx="6" fill="#ff0050"/>
                <path d="M18 8h-3v8.5a2.5 2.5 0 1 1-2.5-2.5c.17 0 .34.02.5.05V11a6 6 0 1 0 5 5.9V12h2.5A2.5 2.5 0 0 1 18 9.5V8z" fill="#fff"/>
            </svg>
            <span class="fw-bold"><?= e(APP_NAME) ?></span>
        </a>
        <div class="ms-auto d-flex align-items-center gap-3">
            <span class="text-muted small d-none d-md-inline">
                <i class="bi bi-person-circle me-1"></i><?= e($user['name']) ?>
                <?php if ($user['company']): ?> · <?= e($user['company']) ?><?php endif; ?>
            </span>
            <?php if (isAdmin()): ?>
                <a href="/admin/" class="btn btn-outline-warning btn-sm"><i class="bi bi-shield-lock me-1"></i>Admin</a>
            <?php endif; ?>
            <a href="/logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 py-4">

    <?= flashHtml() ?>

    <!-- Header row -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-0">My Creator Leads</h4>
            <p class="text-muted small mb-0">TikTok creators assigned to your account</p>
        </div>
        <div class="d-flex gap-2 align-items-center text-muted small">
            <span><i class="bi bi-people-fill me-1 text-danger"></i><strong><?= number_format($stats['total']) ?></strong> leads</span>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="dash-stat-card card border-0 shadow-sm p-3 text-center">
                <div class="dash-stat-icon mx-auto mb-2" style="background:#fff0f4;color:#ff0050">
                    <i class="bi bi-people-fill fs-4"></i>
                </div>
                <div class="fw-bold fs-3"><?= number_format($stats['total']) ?></div>
                <div class="text-muted small">Total Leads</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="dash-stat-card card border-0 shadow-sm p-3 text-center">
                <div class="dash-stat-icon mx-auto mb-2" style="background:#e0f2fe;color:#0284c7">
                    <i class="bi bi-send-fill fs-4"></i>
                </div>
                <div class="fw-bold fs-3"><?= number_format($stats['invited']) ?></div>
                <div class="text-muted small">Invited</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="dash-stat-card card border-0 shadow-sm p-3 text-center">
                <div class="dash-stat-icon mx-auto mb-2" style="background:#d1fae5;color:#059669">
                    <i class="bi bi-check2-circle fs-4"></i>
                </div>
                <div class="fw-bold fs-3"><?= number_format($stats['accepted']) ?></div>
                <div class="text-muted small">Accepted</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="dash-stat-card card border-0 shadow-sm p-3 text-center">
                <div class="dash-stat-icon mx-auto mb-2" style="background:#f3e8ff;color:#7c3aed">
                    <i class="bi bi-x-circle fs-4"></i>
                </div>
                <div class="fw-bold fs-3\"><?= number_format((int)$stats['declined']) ?></div>
                <div class="text-muted small">Declined</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" action="/dashboard.php" class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold mb-1">Search</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control"
                               placeholder="Name or username…" value="<?= e($search) ?>">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-semibold mb-1">Region</label>
                    <select name="region" class="form-select form-select-sm auto-submit">
                        <option value="">All Regions</option>
                        <?php foreach ($myRegions as $r): ?>
                            <option value="<?= e($r) ?>" <?= $region === $r ? 'selected' : '' ?>>
                                <?= e(getRegionName($r)) ?> (<?= strtoupper(e($r)) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-semibold mb-1">Invitation Type</label>
                    <select name="type" class="form-select form-select-sm auto-submit">
                        <option value="0">All Types</option>
                        <?php foreach ($invTypes as $t): ?>
                            <option value="<?= (int)$t['id'] ?>" <?= $typeFilter === (int)$t['id'] ? 'selected' : '' ?>>
                                <?= e($t['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-danger flex-fill">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    <a href="/dashboard.php" class="btn btn-sm btn-outline-secondary" title="Clear filters">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Results info -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted small mb-0">
            Showing <?= number_format(min($offset + 1, $total)) ?>–<?= number_format(min($offset + $perPage, $total)) ?> of <?= number_format($total) ?> leads
        </p>
        <?= paginationHtml($pag) ?>
    </div>

    <!-- Leads Table -->
    <?php if (empty($leads)): ?>
        <div class="text-center py-5">
            <i class="bi bi-inbox fs-1 text-muted"></i>
            <h5 class="mt-3 text-muted">No leads found</h5>
            <p class="text-muted small">
                <?= ($search || $region || $typeFilter) ? 'Try adjusting your filters.' : 'No creator leads have been assigned to your account yet. Please contact support.' ?>
            </p>
        </div>
    <?php else: ?>
        <div class="table-card mb-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Creator</th>
                            <th>Region</th>
                            <th>Invitation Type</th>
                            <th>Backstage Status</th>
                            <th>Your Status</th>
                            <th>Assigned</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leads as $lead): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?= avatarImg($lead['avatar'], $lead['display_name'] ?: $lead['username'] ?: '?', '38') ?>
                                    <div>
                                        <div class="fw-semibold small"><?= e($lead['display_name'] ?: '—') ?></div>
                                        <div class="text-muted" style="font-size:.75rem">@<?= e($lead['username'] ?: '—') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="small fw-semibold"><?= e(getRegionName($lead['backstage_region'] ?? '')) ?></div>
                                <div class="text-muted" style="font-size:.72rem"><?= strtoupper(e($lead['backstage_region'] ?? '')) ?></div>
                            </td>
                            <td><?= invitationTypeBadge(isset($lead['invitation_type']) ? (int)$lead['invitation_type'] : null) ?></td>
                            <td><?= statusBadge((string)($lead['backstage_status'] ?? 'unknown')) ?></td>
                            <td><?= statusBadge((string)($lead['customer_status'] ?? 'new')) ?></td>
                            <td class="small text-muted">
                                <?php if (!empty($lead['assigned_at'])): ?>
                                    <?= e(date('d M Y H:i', strtotime((string)$lead['assigned_at']))) ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <form method="POST" action="/dashboard.php?search=<?= urlencode($search) ?>&region=<?= urlencode($region) ?>&type=<?= (int)$typeFilter ?>&page=<?= (int)$page ?>" class="d-inline-flex gap-1">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="update_customer_status">
                                    <input type="hidden" name="lead_id" value="<?= (int)$lead['id'] ?>">
                                    <button type="submit" name="customer_status" value="invited" class="btn btn-sm <?= ($lead['customer_status'] ?? 'new') === 'invited' ? 'btn-info text-white' : 'btn-outline-info' ?>">Invited</button>
                                    <button type="submit" name="customer_status" value="accepted" class="btn btn-sm <?= ($lead['customer_status'] ?? '') === 'accepted' ? 'btn-success' : 'btn-outline-success' ?>">Accepted</button>
                                    <button type="submit" name="customer_status" value="declined" class="btn btn-sm <?= ($lead['customer_status'] ?? '') === 'declined' ? 'btn-danger' : 'btn-outline-danger' ?>">Declined</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- Bottom pagination -->
        <div class="d-flex justify-content-center mb-2">
            <?= paginationHtml($pag) ?>
        </div>
    <?php endif; ?>

</div>

<!-- Footer -->
<footer class="text-center text-muted py-3 border-top bg-white" style="font-size:.8rem">
    © <?= date('Y') ?> <?= e(APP_NAME) ?> &nbsp;·&nbsp;
    <a href="/" class="text-muted text-decoration-none">Home</a> &nbsp;·&nbsp;
    <a href="/logout.php" class="text-muted text-decoration-none">Logout</a>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>
