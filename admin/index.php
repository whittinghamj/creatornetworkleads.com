<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$db = getDB();

// Aggregate stats
$totalUsers      = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
$activeUsers     = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'customer' AND status = 'active'")->fetchColumn();
$pendingUsers    = (int)$db->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
$totalLeads      = (int)$db->query("SELECT COUNT(*) FROM creators")->fetchColumn();
$assignedLeads   = (int)$db->query("SELECT COUNT(*) FROM creators WHERE assigned_customer IS NOT NULL")->fetchColumn();
$unassignedLeads = $totalLeads - $assignedLeads;

// Recent signups
$recentUsers = $db->query(
    "SELECT id, name, email, company, status, role, created_at FROM users ORDER BY created_at DESC LIMIT 8"
)->fetchAll();

// Recent leads
$recentLeads = $db->query(
    "SELECT c.id, c.display_name, c.username, c.backstage_region, c.backstage_status,
            it.name AS type_name, it.badge_color,
            u.name AS customer_name
     FROM creators c
     LEFT JOIN invitation_types it ON it.id = c.invitation_type
     LEFT JOIN users u ON u.id = c.assigned_customer
     ORDER BY c.id DESC LIMIT 8"
)->fetchAll();

// Leads by region (top 6)
$byRegion = $db->query(
    "SELECT backstage_region, COUNT(*) AS cnt FROM creators
     GROUP BY backstage_region ORDER BY cnt DESC LIMIT 6"
)->fetchAll();

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/includes/header.php';
?>

<!-- Stats cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="admin-stat-card">
            <div class="admin-stat-icon" style="background:#fff0f4;color:#ff0050"><i class="bi bi-people-fill"></i></div>
            <div>
                <div class="admin-stat-label">Total Customers</div>
                <div class="admin-stat-value"><?= number_format($totalUsers) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="admin-stat-card">
            <div class="admin-stat-icon" style="background:#d1fae5;color:#059669"><i class="bi bi-person-check-fill"></i></div>
            <div>
                <div class="admin-stat-label">Active Customers</div>
                <div class="admin-stat-value"><?= number_format($activeUsers) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="admin-stat-card">
            <div class="admin-stat-icon" style="background:#fef9c3;color:#ca8a04"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="admin-stat-label">Pending Approval</div>
                <div class="admin-stat-value"><?= number_format($pendingUsers) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="admin-stat-card">
            <div class="admin-stat-icon" style="background:#ede9fe;color:#7c3aed"><i class="bi bi-collection-fill"></i></div>
            <div>
                <div class="admin-stat-label">Total Leads</div>
                <div class="admin-stat-value"><?= number_format($totalLeads) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="admin-stat-card">
            <div class="admin-stat-icon" style="background:#e0f2fe;color:#0284c7"><i class="bi bi-person-lines-fill"></i></div>
            <div>
                <div class="admin-stat-label">Assigned</div>
                <div class="admin-stat-value"><?= number_format($assignedLeads) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="admin-stat-card">
            <div class="admin-stat-icon" style="background:#fce7f3;color:#db2777"><i class="bi bi-inbox-fill"></i></div>
            <div>
                <div class="admin-stat-label">Unassigned</div>
                <div class="admin-stat-value"><?= number_format($unassignedLeads) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Recent Customers -->
    <div class="col-lg-7">
        <div class="table-card">
            <div class="d-flex align-items-center justify-content-between px-3 py-3 border-bottom">
                <h6 class="fw-bold mb-0">Recent Customers</h6>
                <a href="/admin/users.php" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Role</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $u): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($u['name']) ?></div>
                                <?php if ($u['company']): ?>
                                    <div class="text-muted small"><?= e($u['company']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?= e($u['email']) ?></td>
                            <td><?= statusBadge($u['status']) ?></td>
                            <td><span class="badge bg-<?= $u['role'] === 'admin' ? 'dark' : 'secondary' ?>"><?= e(ucfirst($u['role'])) ?></span></td>
                            <td class="text-end">
                                <a href="/admin/user-form.php?id=<?= (int)$u['id'] ?>" class="btn btn-xs btn-outline-primary btn-sm py-0 px-2">Edit</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentUsers)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No users yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Leads by region + quick actions -->
    <div class="col-lg-5">
        <!-- Quick Actions -->
        <div class="card border-0 shadow-sm mb-4" style="border-radius:14px">
            <div class="card-body p-3">
                <h6 class="fw-bold mb-3">Quick Actions</h6>
                <div class="d-flex flex-wrap gap-2">
                    <a href="/admin/user-form.php" class="btn btn-sm btn-danger">
                        <i class="bi bi-person-plus me-1"></i>Add User
                    </a>
                    <a href="/admin/leads.php?filter=unassigned" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-inbox me-1"></i>Unassigned Leads
                    </a>
                    <a href="/admin/leads.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-collection me-1"></i>All Leads
                    </a>
                </div>
            </div>
        </div>

        <!-- Leads by region -->
        <div class="table-card">
            <div class="px-3 py-3 border-bottom">
                <h6 class="fw-bold mb-0">Leads by Region</h6>
            </div>
            <div class="p-3">
                <?php foreach ($byRegion as $r): ?>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-dark" style="min-width:36px"><?= strtoupper(e($r['backstage_region'])) ?></span>
                    <div class="flex-grow-1">
                        <div class="progress" style="height:8px;border-radius:4px">
                            <div class="progress-bar bg-danger" style="width:<?= $totalLeads > 0 ? round($r['cnt']/$totalLeads*100) : 0 ?>%"></div>
                        </div>
                    </div>
                    <span class="text-muted small fw-semibold" style="min-width:40px;text-align:right"><?= number_format($r['cnt']) ?></span>
                </div>
                <?php endforeach; ?>
                <?php if (empty($byRegion)): ?>
                    <p class="text-muted small mb-0">No leads in database.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
