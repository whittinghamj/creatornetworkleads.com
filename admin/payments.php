<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/paypal.php';

requireAdmin();

$db = getDB();
ensureBillingSchema($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = postStr('action');

    if ($action === 'sync_subscription') {
        $subscriptionId = postStr('subscription_id');
        $sync = paypalSyncSubscriptionById($db, $subscriptionId, null, 'admin_sync');

        if (!empty($sync['ok'])) {
            flash('Subscription synced successfully. Status: ' . strtoupper((string)$sync['status']), 'success');
        } else {
            flash('Failed to sync subscription: ' . (string)($sync['error'] ?? 'Unknown error'), 'danger');
        }

        header('Location: /admin/payments.php');
        exit;
    }
}

$search = getStr('search');
$status = strtolower(getStr('status'));

$where = ['u.role = "customer"'];
$params = [];

if ($search !== '') {
    $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.paypal_subscription_id LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($status !== '') {
    $where[] = 'u.subscription_status = ?';
    $params[] = $status;
}

$subscriptionStmt = $db->prepare(
    'SELECT
        u.id,
        u.name,
        u.email,
        u.status AS account_status,
        u.subscription_status,
        u.subscription_updated_at,
        u.paypal_subscription_id,
        p.name AS package_name,
        p.price_per_month,
        p.leads_per_day,
        ps.next_billing_time
     FROM users u
     LEFT JOIN packages p ON p.id = u.subscription_package_id
     LEFT JOIN paypal_subscriptions ps ON ps.paypal_subscription_id = u.paypal_subscription_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY u.subscription_updated_at DESC, u.id DESC'
);
$subscriptionStmt->execute($params);
$subscriptions = $subscriptionStmt->fetchAll();

$payments = $db->query(
    'SELECT pp.*, u.name AS customer_name, u.email AS customer_email
     FROM paypal_payments pp
     LEFT JOIN users u ON u.id = pp.user_id
     ORDER BY COALESCE(pp.paid_at, pp.created_at) DESC, pp.id DESC
     LIMIT 250'
)->fetchAll();

$activeSubCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'customer' AND subscription_status = 'active'")->fetchColumn();
$suspendedSubCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'customer' AND subscription_status IN ('suspended', 'cancelled', 'expired', 'payment_failed')")->fetchColumn();
$failedPayments30d = (int)$db->query("SELECT COUNT(*) FROM paypal_payments WHERE status IN ('failed', 'denied') AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

function adminBillingBadge(string $status): string
{
    $status = strtolower($status);
    $map = [
        'active' => 'success',
        'pending' => 'warning',
        'suspended' => 'danger',
        'cancelled' => 'secondary',
        'expired' => 'secondary',
        'payment_failed' => 'danger',
        'failed' => 'danger',
        'denied' => 'danger',
        'completed' => 'success',
        'none' => 'secondary',
    ];
    $color = $map[$status] ?? 'secondary';
    return '<span class="badge bg-' . $color . '">' . e(ucfirst(str_replace('_', ' ', $status))) . '</span>';
}

$pageTitle = 'Payments and Subscriptions';
$activeNav = 'payments';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h5 class="fw-bold mb-0">Payments and Subscriptions</h5>
        <p class="text-muted small mb-0">Monitor active paid subscriptions, failures, and billing history.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm" style="border-radius:12px">
            <div class="card-body">
                <div class="text-muted small">Active Paid Subscriptions</div>
                <div class="fs-4 fw-bold"><?= number_format($activeSubCount) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm" style="border-radius:12px">
            <div class="card-body">
                <div class="text-muted small">Suspended/Cancelled/Expired</div>
                <div class="fs-4 fw-bold text-danger"><?= number_format($suspendedSubCount) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm" style="border-radius:12px">
            <div class="card-body">
                <div class="text-muted small">Failed Payments (30 days)</div>
                <div class="fs-4 fw-bold text-warning"><?= number_format($failedPayments30d) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4" style="border-radius:12px">
    <div class="card-body py-3">
        <form method="GET" action="/admin/payments.php" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-semibold mb-1">Search</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Customer, email, subscription ID" value="<?= e($search) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Subscription Status</label>
                <select name="status" class="form-select form-select-sm auto-submit">
                    <option value="">All</option>
                    <?php foreach (['active','pending','suspended','cancelled','expired','none'] as $s): ?>
                        <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-auto d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-search me-1"></i>Search</button>
                <a href="/admin/payments.php" class="btn btn-sm btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="table-card mb-4">
    <div class="px-3 py-3 border-bottom">
        <h6 class="fw-bold mb-0">Customer Subscription Status</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Package</th>
                    <th>Subscription</th>
                    <th>Status</th>
                    <th>Account</th>
                    <th>Next Billing</th>
                    <th>Updated</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subscriptions as $sub): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold small"><?= e((string)$sub['name']) ?></div>
                            <div class="text-muted" style="font-size:.75rem"><?= e((string)$sub['email']) ?></div>
                        </td>
                        <td class="small">
                            <?php if (!empty($sub['package_name'])): ?>
                                <?= e((string)$sub['package_name']) ?>
                                <div class="text-muted" style="font-size:.72rem">GBP <?= number_format((float)($sub['price_per_month'] ?? 0), 2) ?> · <?= (int)($sub['leads_per_day'] ?? 0) ?>/day</div>
                            <?php else: ?>
                                <span class="text-muted">None</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-break"><?= e((string)($sub['paypal_subscription_id'] ?? '')) ?></td>
                        <td><?= adminBillingBadge((string)($sub['subscription_status'] ?? 'none')) ?></td>
                        <td><?= statusBadge((string)($sub['account_status'] ?? 'inactive')) ?></td>
                        <td class="small text-muted"><?= !empty($sub['next_billing_time']) ? e(date('d M Y H:i', strtotime((string)$sub['next_billing_time']))) : '—' ?></td>
                        <td class="small text-muted"><?= !empty($sub['subscription_updated_at']) ? e(date('d M Y H:i', strtotime((string)$sub['subscription_updated_at']))) : '—' ?></td>
                        <td class="text-end">
                            <?php if (!empty($sub['paypal_subscription_id'])): ?>
                                <form method="POST" action="/admin/payments.php" class="d-inline-block">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="sync_subscription">
                                    <input type="hidden" name="subscription_id" value="<?= e((string)$sub['paypal_subscription_id']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-primary py-0 px-2" title="Sync from PayPal">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <a href="/admin/user-form.php?id=<?= (int)$sub['id'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Open user">
                                <i class="bi bi-person"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($subscriptions)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No customer subscriptions found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="table-card">
    <div class="px-3 py-3 border-bottom">
        <h6 class="fw-bold mb-0">Payment History</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Subscription ID</th>
                    <th>Transaction ID</th>
                    <th>Status</th>
                    <th>Amount</th>
                    <th>Currency</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td class="small text-muted"><?= !empty($payment['paid_at']) ? e(date('d M Y H:i', strtotime((string)$payment['paid_at']))) : e(date('d M Y H:i', strtotime((string)$payment['created_at']))) ?></td>
                        <td>
                            <div class="fw-semibold small"><?= e((string)($payment['customer_name'] ?? 'Unknown')) ?></div>
                            <div class="text-muted" style="font-size:.75rem"><?= e((string)($payment['customer_email'] ?? '')) ?></div>
                        </td>
                        <td class="small text-break"><?= e((string)($payment['paypal_subscription_id'] ?? '')) ?></td>
                        <td class="small text-break"><?= e((string)$payment['paypal_transaction_id']) ?></td>
                        <td><?= adminBillingBadge((string)$payment['status']) ?></td>
                        <td class="fw-semibold"><?= number_format((float)($payment['amount'] ?? 0), 2) ?></td>
                        <td class="small text-muted"><?= e((string)($payment['currency_code'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($payments)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No PayPal payment records yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
