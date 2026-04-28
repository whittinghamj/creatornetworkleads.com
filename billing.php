<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/paypal.php';

requireLogin();

$db = getDB();
ensureBillingSchema($db);

$userId = (int)($_SESSION['user_id'] ?? 0);
$userStmt = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$userStmt->execute([$userId]);
$user = $userStmt->fetch();

if (!$user) {
    logoutUser();
    header('Location: /login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = postStr('action');

    if ($action === 'start_subscription') {
        $packageId = max(0, (int)postStr('package_id'));
        $result = paypalCreateSubscriptionForUser($db, $user, $packageId);
        if (!empty($result['ok'])) {
            header('Location: ' . $result['approve_url']);
            exit;
        }

        flash((string)($result['error'] ?? 'Unable to create PayPal subscription.'), 'danger');
        header('Location: /billing.php');
        exit;
    }

    flash('Unknown billing action.', 'danger');
    header('Location: /billing.php');
    exit;
}

$paypalFlow = getStr('paypal');
$paypalSubscriptionId = getStr('subscription_id', getStr('token'));

if ($paypalFlow === 'success' && $paypalSubscriptionId !== '') {
    $sync = paypalSyncSubscriptionById($db, $paypalSubscriptionId, $userId, 'return');
    if (!empty($sync['ok'])) {
        flash('Your PayPal subscription is now connected and active.', 'success');
    } else {
        flash('We could not verify your PayPal subscription yet: ' . (string)($sync['error'] ?? 'Unknown error'), 'warning');
    }

    header('Location: /billing.php');
    exit;
}

if ($paypalFlow === 'cancel') {
    flash('PayPal checkout was canceled. No changes were made.', 'info');
    header('Location: /billing.php');
    exit;
}

$packages = array_values(array_filter(
    getPackages($db),
    static function (array $package): bool {
        return trim((string)($package['paypal_plan_id'] ?? '')) !== '';
    }
));

$currentSubStmt = $db->prepare(
    'SELECT ps.*, p.name AS package_name, p.leads_per_day, p.price_per_month
     FROM paypal_subscriptions ps
     LEFT JOIN packages p ON p.id = ps.package_id
     WHERE ps.user_id = ?
     ORDER BY ps.updated_at DESC, ps.id DESC
     LIMIT 1'
);
$currentSubStmt->execute([$userId]);
$currentSubscription = $currentSubStmt->fetch();

$paymentsStmt = $db->prepare(
    'SELECT *
     FROM paypal_payments
     WHERE user_id = ?
     ORDER BY COALESCE(paid_at, created_at) DESC, id DESC
     LIMIT 100'
);
$paymentsStmt->execute([$userId]);
$payments = $paymentsStmt->fetchAll();

$subscriptionStatus = strtolower((string)($user['subscription_status'] ?? 'none'));

function billingStatusBadge(string $status): string
{
    $status = strtolower($status);
    $map = [
        'active' => 'success',
        'pending' => 'warning',
        'suspended' => 'danger',
        'cancelled' => 'secondary',
        'expired' => 'secondary',
        'none' => 'secondary',
    ];
    $color = $map[$status] ?? 'secondary';
    return '<span class="badge bg-' . $color . '">' . e(ucfirst($status)) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <script>(function(){var t=localStorage.getItem('cnl-theme')||'light';document.documentElement.setAttribute('data-bs-theme',t);})();</script>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm sticky-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center gap-2" href="/dashboard.php">
            <img src="/assets/logo/logo.png" alt="<?= e(APP_NAME) ?>" class="cnl-logo-img">
            <span class="fw-bold"><?= e(APP_NAME) ?></span>
        </a>
        <div class="ms-auto d-flex align-items-center gap-2">
            <a href="/dashboard.php" class="btn btn-outline-light btn-sm"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
            <a href="/logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 py-4">
    <?= flashHtml() ?>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
        <div>
            <h4 class="fw-bold mb-0">Billing and Subscription</h4>
            <p class="text-muted small mb-0">Manage your package, PayPal subscription, and payment history.</p>
        </div>
        <div class="text-end">
            <div class="small text-muted">Subscription Status</div>
            <div><?= billingStatusBadge($subscriptionStatus) ?></div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4" style="border-radius:14px">
        <div class="card-header bg-white border-bottom fw-semibold py-3">Current Subscription</div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="text-muted small">Package</div>
                    <div class="fw-semibold"><?= e((string)($currentSubscription['package_name'] ?? 'None')) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Leads per Day</div>
                    <div class="fw-semibold"><?= (int)($currentSubscription['leads_per_day'] ?? 0) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Monthly Price</div>
                    <div class="fw-semibold">&pound;<?= number_format((float)($currentSubscription['price_per_month'] ?? 0), 2) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">PayPal Subscription ID</div>
                    <div class="fw-semibold small text-break"><?= e((string)($user['paypal_subscription_id'] ?? 'Not connected')) ?></div>
                </div>
            </div>

            <?php if ($subscriptionStatus !== 'active'): ?>
                <div class="alert alert-warning mt-3 mb-0">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Your account only receives new daily leads while subscription status is <strong>Active</strong>.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4" style="border-radius:14px">
        <div class="card-header bg-white border-bottom fw-semibold py-3">Choose or Upgrade Package</div>
        <div class="card-body p-4">
            <div class="row g-3">
                <?php foreach ($packages as $package): ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="border rounded-3 h-100 p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="fw-semibold"><?= e((string)$package['name']) ?></div>
                                <?php if ((int)($user['subscription_package_id'] ?? 0) === (int)$package['id'] && $subscriptionStatus === 'active'): ?>
                                    <span class="badge bg-success">Current</span>
                                <?php endif; ?>
                            </div>
                            <p class="text-muted small mb-2"><?= e((string)($package['description'] ?? '')) ?></p>
                            <div class="small mb-1"><strong><?= (int)$package['leads_per_day'] ?></strong> leads/day</div>
                            <div class="h5 mb-3">&pound;<?= number_format((float)$package['price_per_month'], 2) ?><span class="text-muted fs-6">/month</span></div>

                            <form method="POST" action="/billing.php">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="start_subscription">
                                <input type="hidden" name="package_id" value="<?= (int)$package['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm w-100">
                                    <i class="bi bi-paypal me-1"></i><?= $subscriptionStatus === 'active' ? 'Switch to This Package' : 'Subscribe with PayPal' ?>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($packages)): ?>
                    <div class="col-12 text-muted small">No packages are available yet.</div>
                <?php endif; ?>
            </div>

            <p class="text-muted small mt-3 mb-0">
                Billing destination account: <strong><?= e(PAYPAL_BILLING_EMAIL) ?></strong>
            </p>
        </div>
    </div>

    <div class="card border-0 shadow-sm" style="border-radius:14px">
        <div class="card-header bg-white border-bottom fw-semibold py-3">Payment History</div>
        <div class="card-body p-0">
            <?php if (empty($payments)): ?>
                <p class="text-muted small p-3 mb-0">No PayPal payment records yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
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
                                    <td class="small text-break"><?= e((string)$payment['paypal_transaction_id']) ?></td>
                                    <td><?= billingStatusBadge((string)$payment['status']) ?></td>
                                    <td class="fw-semibold"><?= number_format((float)($payment['amount'] ?? 0), 2) ?></td>
                                    <td class="small text-muted"><?= e((string)($payment['currency_code'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>
