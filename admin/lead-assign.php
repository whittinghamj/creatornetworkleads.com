<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$db = getDB();
ensureCreatorsLeadTrackingSchema($db);
$id = getInt('id');

if ($id === 0) {
    flash('Invalid lead ID.', 'danger');
    header('Location: /admin/leads.php');
    exit;
}

$stmt = $db->prepare('SELECT * FROM creators WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$lead = $stmt->fetch();

if (!$lead) {
    flash('Lead not found.', 'danger');
    header('Location: /admin/leads.php');
    exit;
}

$customers = $db->query(
    "SELECT id, name, email, company FROM users WHERE role = 'customer' AND status = 'active' ORDER BY name"
)->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action     = postStr('action'); // 'assign' or 'unassign'
    $customerId = (int)postStr('customer_id');

    if ($action === 'unassign') {
        $db->prepare("UPDATE creators SET assigned_customer = NULL, assigned_at = NULL, customer_status = 'new' WHERE id = ?")
            ->execute([$id]);
        flash('Lead @' . ($lead['username'] ?? $id) . ' has been unassigned.', 'success');
        header('Location: /admin/leads.php');
        exit;
    }

    if ($action === 'assign') {
        if ($customerId === 0) {
            $error = 'Please select a customer to assign this lead to.';
        } else {
            // Verify customer exists
            $cStmt = $db->prepare('SELECT id, name FROM users WHERE id = ? AND status = "active" LIMIT 1');
            $cStmt->execute([$customerId]);
            $customer = $cStmt->fetch();
            if (!$customer) {
                $error = 'Selected customer not found or inactive.';
            } else {
                     $db->prepare("UPDATE creators SET assigned_customer = ?, assigned_at = NOW(), customer_status = 'new' WHERE id = ?")
                   ->execute([$customerId, $id]);
                flash('Lead @' . ($lead['username'] ?? $id) . ' assigned to ' . $customer['name'] . '.', 'success');
                header('Location: /admin/leads.php');
                exit;
            }
        }
    }
}

// Current owner
$currentOwner = null;
if ($lead['assigned_customer']) {
    $ownerStmt = $db->prepare('SELECT id, name, email FROM users WHERE id = ? LIMIT 1');
    $ownerStmt->execute([$lead['assigned_customer']]);
    $currentOwner = $ownerStmt->fetch();
}

$pageTitle = 'Assign Lead: @' . ($lead['username'] ?? $id);
$activeNav = 'leads';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="/admin/leads.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Leads
    </a>
    <h5 class="fw-bold mb-0">Assign / Reassign Lead</h5>
</div>

<?php if ($error): ?>
<div class="alert alert-danger small"><i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?></div>
<?php endif; ?>

<div class="row g-4">
    <!-- Lead info -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm" style="border-radius:14px">
            <div class="card-header bg-white border-bottom fw-semibold py-3">Creator Profile</div>
            <div class="card-body p-4 text-center">
                <?= avatarImg($lead['avatar'] ?? '', $lead['display_name'] ?: ($lead['username'] ?? ''), '80') ?>
                <div class="fw-bold mt-3"><?= e($lead['display_name'] ?: '—') ?></div>
                <div class="text-muted small mb-3">@<?= e($lead['username'] ?: '—') ?></div>
                <div class="d-flex flex-wrap gap-2 justify-content-center mb-2">
                    <?= invitationTypeBadge(isset($lead['invitation_type']) ? (int)$lead['invitation_type'] : null) ?>
                    <span class="badge bg-dark"><?= strtoupper(e($lead['backstage_region'] ?? '')) ?></span>
                </div>
                <?= statusBadge($lead['backstage_status'] ?? 'unknown') ?>
                <hr>
                <div class="text-start small">
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Lead ID</span>
                        <span class="fw-semibold">#<?= (int)$lead['id'] ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Region</span>
                        <span class="fw-semibold"><?= e(getRegionName($lead['backstage_region'] ?? '')) ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Current Owner</span>
                        <span class="fw-semibold">
                            <?= $currentOwner ? e($currentOwner['name']) : '<span class="text-warning">Unassigned</span>' ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assignment form -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm" style="border-radius:14px">
            <div class="card-header bg-white border-bottom fw-semibold py-3">
                <?= $currentOwner ? 'Reassign to Another Customer' : 'Assign to Customer' ?>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="/admin/lead-assign.php?id=<?= $id ?>">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="assign">

                    <label class="form-label fw-semibold">Select Customer</label>
                    <select name="customer_id" class="form-select mb-3" required>
                        <option value="0">— Choose a customer —</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"
                                <?= (int)$lead['assigned_customer'] === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= e($c['name']) ?>
                                <?php if ($c['company']): ?> (<?= e($c['company']) ?>)<?php endif; ?>
                                — <?= e($c['email']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if (empty($customers)): ?>
                        <div class="alert alert-warning small">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            No active customers found. <a href="/admin/user-form.php">Add a customer first.</a>
                        </div>
                    <?php else: ?>
                        <button type="submit" class="btn btn-success me-2">
                            <i class="bi bi-person-check me-1"></i><?= $currentOwner ? 'Reassign Lead' : 'Assign Lead' ?>
                        </button>
                    <?php endif; ?>
                    <a href="/admin/leads.php" class="btn btn-outline-secondary">Cancel</a>
                </form>

                <?php if ($currentOwner): ?>
                <hr>
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="fw-semibold small">Unassign this lead</div>
                        <div class="text-muted small">Removes assignment – lead returns to the unassigned pool.</div>
                    </div>
                    <form method="POST" action="/admin/lead-assign.php?id=<?= $id ?>" class="ms-3">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="unassign">
                        <button type="submit" class="btn btn-sm btn-outline-warning"
                                onclick="return confirm('Unassign this lead from <?= e(addslashes($currentOwner['name'])) ?>?')">
                            <i class="bi bi-person-dash me-1"></i>Unassign
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bulk assign tip -->
        <div class="card border-0 shadow-sm mt-3" style="border-radius:14px;background:#f0fdf4;border-color:#bbf7d0!important">
            <div class="card-body p-3">
                <div class="d-flex align-items-start gap-2">
                    <i class="bi bi-lightbulb-fill text-success mt-1"></i>
                    <div class="small text-muted">
                        <strong class="text-dark">Tip:</strong> To bulk-assign multiple leads to a customer, use the
                        <a href="/admin/leads.php?filter=unassigned">Unassigned Leads</a> page and click the
                        assign icon <i class="bi bi-person-check"></i> on each row.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
