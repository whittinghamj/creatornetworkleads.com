<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$db = getDB();
ensureBackstageAccountsSchema($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    $token = (string)($_POST['csrf_token'] ?? '');
    $csrfOk = isset($_SESSION['csrf_token']) && hash_equals((string)$_SESSION['csrf_token'], $token);

    if (!$csrfOk) {
        if ($isAjax) {
            http_response_code(403);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'ok' => false,
                'message' => 'Invalid security token. Refresh the page and try again.',
                'account_id' => null,
                'is_active' => null,
            ]);
            exit;
        }

        verifyCsrf();
    }

    $action = postStr('action');
    $accountId = (int)postStr('account_id');
    $response = [
        'ok' => false,
        'message' => 'Invalid status update request.',
        'account_id' => $accountId,
        'is_active' => null,
    ];

    if ($action === 'toggle_active' && $accountId > 0) {
        $stmt = $db->prepare(
            'UPDATE backstage_accounts
             SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END
             WHERE id = ?
             LIMIT 1'
        );
        $stmt->execute([$accountId]);

        if ($stmt->rowCount() > 0) {
            $stateStmt = $db->prepare('SELECT is_active FROM backstage_accounts WHERE id = ? LIMIT 1');
            $stateStmt->execute([$accountId]);
            $isActive = $stateStmt->fetchColumn();
            $isActiveInt = ((int)$isActive === 1) ? 1 : 0;

            $response['ok'] = true;
            $response['message'] = 'Backstage account status updated.';
            $response['is_active'] = $isActiveInt;
            flash($response['message'], 'success');
        } else {
            $response['message'] = 'Backstage account not found.';
            flash($response['message'], 'danger');
        }
    } else {
        flash($response['message'], 'danger');
    }

    if ($isAjax) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($response);
        exit;
    }

    header('Location: /admin/backstage-accounts.php');
    exit;
}

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
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge <?= (int)$account['is_active'] === 1 ? 'bg-success' : 'bg-secondary' ?> js-backstage-status-badge"
                                  data-account-id="<?= (int)$account['id'] ?>">
                                <?= (int)$account['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                            </span>
                            <form method="POST"
                                  action="/admin/backstage-accounts.php"
                                  class="d-inline js-backstage-toggle-form"
                                  data-account-id="<?= (int)$account['id'] ?>">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="account_id" value="<?= (int)$account['id'] ?>">
                                <button type="submit"
                                        class="btn btn-sm <?= (int)$account['is_active'] === 1 ? 'btn-outline-warning' : 'btn-outline-success' ?> py-0 px-2 js-backstage-toggle-btn"
                                        data-account-id="<?= (int)$account['id'] ?>"
                                        data-is-active="<?= (int)$account['is_active'] === 1 ? '1' : '0' ?>"
                                        title="<?= (int)$account['is_active'] === 1 ? 'Disable account' : 'Enable account' ?>">
                                    <?= (int)$account['is_active'] === 1 ? 'Disable' : 'Enable' ?>
                                </button>
                            </form>
                        </div>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('.js-backstage-toggle-form');

    for (const form of forms) {
        form.addEventListener('submit', async function (event) {
            event.preventDefault();

            const accountId = form.getAttribute('data-account-id') || '';
            const button = form.querySelector('.js-backstage-toggle-btn');
            const badge = document.querySelector('.js-backstage-status-badge[data-account-id="' + accountId + '"]');

            if (!button || !badge) {
                form.submit();
                return;
            }

            button.disabled = true;
            const originalText = button.textContent;
            button.textContent = 'Saving...';

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: new FormData(form),
                    credentials: 'same-origin',
                });

                const responseText = await response.text();
                let payload = null;

                try {
                    payload = JSON.parse(responseText);
                } catch {
                    payload = null;
                }

                if (!response.ok || !payload || payload.ok !== true) {
                    throw new Error((payload && payload.message) ? payload.message : 'Status update failed.');
                }

                const isActive = Number(payload.is_active) === 1;
                badge.classList.toggle('bg-success', isActive);
                badge.classList.toggle('bg-secondary', !isActive);
                badge.textContent = isActive ? 'Active' : 'Inactive';

                button.classList.toggle('btn-outline-warning', isActive);
                button.classList.toggle('btn-outline-success', !isActive);
                button.setAttribute('data-is-active', isActive ? '1' : '0');
                button.title = isActive ? 'Disable account' : 'Enable account';
                button.textContent = isActive ? 'Disable' : 'Enable';
            } catch (error) {
                button.textContent = originalText;
                window.alert(error && error.message ? error.message : 'Could not update account status.');
            } finally {
                button.disabled = false;
            }
        });
    }
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>