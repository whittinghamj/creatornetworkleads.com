<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$db = getDB();
ensureBackstageAccountsSchema($db);

$id = getInt('id');
$isNew = ($id === 0);

$account = [
    'id' => 0,
    'label' => '',
    'email' => '',
    'password' => '',
    'is_active' => 1,
];

if (!$isNew) {
    $stmt = $db->prepare('SELECT * FROM backstage_accounts WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        flash('Backstage account not found.', 'danger');
        header('Location: /admin/backstage-accounts.php');
        exit;
    }
    $account = array_merge($account, $existing);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $account['label'] = postStr('label');
    $account['email'] = strtolower(postStr('email'));
    $account['password'] = postStr('password');
    $account['is_active'] = postStr('is_active') === '1' ? 1 : 0;

    if ($account['email'] === '' || !filter_var($account['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email is required.';
    }

    if ($isNew && $account['password'] === '') {
        $errors[] = 'Password is required for new backstage accounts.';
    }

    if (!$isNew && $account['password'] === '') {
        $stmt = $db->prepare('SELECT password FROM backstage_accounts WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $currentPassword = $stmt->fetchColumn();
        $account['password'] = is_string($currentPassword) ? $currentPassword : '';
    }

    if ($account['password'] === '') {
        $errors[] = 'Password cannot be empty.';
    }

    if (empty($errors)) {
        $chkStmt = $db->prepare('SELECT id FROM backstage_accounts WHERE email = ? AND id != ? LIMIT 1');
        $chkStmt->execute([$account['email'], $isNew ? 0 : $id]);
        if ($chkStmt->fetch()) {
            $errors[] = 'Another backstage account with that email already exists.';
        }
    }

    if (empty($errors)) {
        if ($isNew) {
            $stmt = $db->prepare(
                'INSERT INTO backstage_accounts (email, password, label, is_active)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([
                $account['email'],
                $account['password'],
                $account['label'] ?: null,
                (int)$account['is_active'],
            ]);
            flash('Backstage account created successfully.', 'success');
        } else {
            $stmt = $db->prepare(
                'UPDATE backstage_accounts
                 SET email = ?, password = ?, label = ?, is_active = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $account['email'],
                $account['password'],
                $account['label'] ?: null,
                (int)$account['is_active'],
                $id,
            ]);
            flash('Backstage account updated successfully.', 'success');
        }

        header('Location: /admin/backstage-accounts.php');
        exit;
    }
}

$pageTitle = $isNew ? 'Add Backstage Account' : 'Edit Backstage Account';
$activeNav = $isNew ? 'backstage-account-add' : 'backstage-accounts';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="/admin/backstage-accounts.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
    <h5 class="fw-bold mb-0"><?= $isNew ? 'Add Backstage Account' : 'Edit Backstage Account' ?></h5>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0 ps-3 small">
        <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm" style="border-radius:14px">
    <div class="card-header bg-white border-bottom fw-semibold py-3">Account Details</div>
    <div class="card-body p-4">
        <form method="POST" action="/admin/backstage-account-form.php<?= $isNew ? '' : '?id=' . $id ?>">
            <?= csrfField() ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Label</label>
                    <input type="text" name="label" class="form-control" value="<?= e((string)$account['label']) ?>"
                           placeholder="Optional internal name (e.g. UK Creator Team)">
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Status</label>
                    <select name="is_active" class="form-select">
                        <option value="1" <?= (int)$account['is_active'] === 1 ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= (int)$account['is_active'] === 0 ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" value="<?= e((string)$account['email']) ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Password <?= $isNew ? '<span class="text-danger">*</span>' : '<span class="text-muted fw-normal">(leave blank to keep current)</span>' ?></label>
                    <input type="text" name="password" class="form-control" value="<?= $isNew ? '' : '' ?>"
                           placeholder="<?= $isNew ? 'Backstage password' : 'Set a new password' ?>">
                </div>

                <?php if (!$isNew): ?>
                <div class="col-12">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold mb-1">Last Used</label>
                            <div class="form-control-plaintext small border rounded px-3 py-2 bg-body-tertiary">
                                <?= !empty($account['last_used_at']) ? e(date('d M Y H:i', strtotime((string)$account['last_used_at']))) : '—' ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold mb-1">Last Success</label>
                            <div class="form-control-plaintext small border rounded px-3 py-2 bg-body-tertiary">
                                <?= !empty($account['last_success_at']) ? e(date('d M Y H:i', strtotime((string)$account['last_success_at']))) : '—' ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold mb-1">Last Failure</label>
                            <div class="form-control-plaintext small border rounded px-3 py-2 bg-body-tertiary">
                                <?= !empty($account['last_failure_at']) ? e(date('d M Y H:i', strtotime((string)$account['last_failure_at']))) : '—' ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="col-12">
                    <div class="alert alert-warning mb-0 small" role="alert">
                        Passwords are currently stored in plain text because automation needs direct login credentials.
                    </div>
                </div>

                <div class="col-12 pt-2">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-save me-1"></i><?= $isNew ? 'Create Account' : 'Save Changes' ?>
                    </button>
                    <a href="/admin/backstage-accounts.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>