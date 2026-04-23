<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$db    = getDB();
ensurePackagesSchema($db);
$id    = getInt('id');
$isNew = ($id === 0);

$packages = getPackages($db);

// Load existing user
$user = [
    'id'      => 0,
    'name'    => '',
    'email'   => '',
    'company' => '',
    'phone'   => '',
    'role'    => 'customer',
    'status'  => 'active',
    'notes'   => '',
    'package_id' => null,
];

if (!$isNew) {
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        flash('User not found.', 'danger');
        header('Location: /admin/users.php');
        exit;
    }
    $user = array_merge($user, $existing);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action = postStr('action', 'save_user');
    if ($action === 'assign_package_leads' && !$isNew) {
        $assignResult = assignAvailableLeadsForCustomer($db, $id);
        flash(
            'Assigned ' . (int)$assignResult['assigned'] . ' lead(s). ' . $assignResult['reason'],
            'info'
        );
        header('Location: /admin/user-form.php?id=' . $id);
        exit;
    }

    $user['name']    = postStr('name');
    $user['email']   = strtolower(postStr('email'));
    $user['company'] = postStr('company');
    $user['phone']   = postStr('phone');
    $user['role']    = in_array(postStr('role'), ['customer','admin']) ? postStr('role') : 'customer';
    $user['status']  = in_array(postStr('status'), ['active','inactive','pending']) ? postStr('status') : 'active';
    $user['notes']   = postStr('notes');
    $user['package_id'] = (int)postStr('package_id');
    $password        = postStr('password');
    $passConfirm     = postStr('password_confirm');

    if ($user['role'] !== 'customer') {
        $user['package_id'] = 0;
    }

    if ($user['package_id'] > 0) {
        $pkgStmt = $db->prepare('SELECT id FROM packages WHERE id = ? LIMIT 1');
        $pkgStmt->execute([$user['package_id']]);
        if (!$pkgStmt->fetch()) {
            $errors[] = 'Selected package does not exist.';
        }
    }

    if ($user['name'] === '') {
        $errors[] = 'Full name is required.';
    }
    if ($user['email'] === '' || !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email is required.';
    }
    if ($isNew && $password === '') {
        $errors[] = 'Password is required for new users.';
    }
    if ($password !== '' && strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== '' && $password !== $passConfirm) {
        $errors[] = 'Passwords do not match.';
    }

    // Check email uniqueness
    if (empty($errors)) {
        $chkStmt = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
        $chkStmt->execute([$user['email'], $isNew ? 0 : $id]);
        if ($chkStmt->fetch()) {
            $errors[] = 'Another account with that email already exists.';
        }
    }

    if (empty($errors)) {
        if ($isNew) {
            $stmt = $db->prepare(
                'INSERT INTO users (name, email, password, company, phone, role, status, notes, package_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $user['name'],
                $user['email'],
                password_hash($password, PASSWORD_DEFAULT),
                $user['company'] ?: null,
                $user['phone']   ?: null,
                $user['role'],
                $user['status'],
                $user['notes']   ?: null,
                $user['package_id'] > 0 ? $user['package_id'] : null,
            ]);
            flash('User ' . $user['name'] . ' created successfully.', 'success');
        } else {
            if ($password !== '') {
                $stmt = $db->prepare(
                    'UPDATE users SET name=?, email=?, password=?, company=?, phone=?, role=?, status=?, notes=?, package_id=? WHERE id=?'
                );
                $stmt->execute([
                    $user['name'], $user['email'],
                    password_hash($password, PASSWORD_DEFAULT),
                    $user['company'] ?: null, $user['phone'] ?: null,
                    $user['role'], $user['status'], $user['notes'] ?: null,
                    $user['package_id'] > 0 ? $user['package_id'] : null,
                    $id,
                ]);
            } else {
                $stmt = $db->prepare(
                    'UPDATE users SET name=?, email=?, company=?, phone=?, role=?, status=?, notes=?, package_id=? WHERE id=?'
                );
                $stmt->execute([
                    $user['name'], $user['email'],
                    $user['company'] ?: null, $user['phone'] ?: null,
                    $user['role'], $user['status'], $user['notes'] ?: null,
                    $user['package_id'] > 0 ? $user['package_id'] : null,
                    $id,
                ]);
            }
            flash('User updated successfully.', 'success');
        }
        header('Location: /admin/users.php');
        exit;
    }
}

$pageTitle = $isNew ? 'Add User' : 'Edit User: ' . e($user['name']);
$activeNav = $isNew ? 'user-add' : 'users';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="/admin/users.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
    <h5 class="fw-bold mb-0"><?= $isNew ? 'Add New User' : 'Edit User' ?></h5>
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

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm" style="border-radius:14px">
            <div class="card-header bg-white border-bottom fw-semibold py-3">Account Details</div>
            <div class="card-body p-4">
                <form method="POST" action="/admin/user-form.php<?= $isNew ? '' : '?id=' . $id ?>" novalidate>
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="save_user">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control"
                                   value="<?= e($user['name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control"
                                   value="<?= e($user['email']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Company / Agency</label>
                            <input type="text" name="company" class="form-control"
                                   value="<?= e($user['company'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Phone</label>
                            <input type="tel" name="phone" class="form-control"
                                   value="<?= e($user['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Role</label>
                            <select name="role" class="form-select">
                                <option value="customer" <?= $user['role'] === 'customer' ? 'selected' : '' ?>>Customer</option>
                                <option value="admin"    <?= $user['role'] === 'admin'    ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <option value="active"   <?= $user['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                <option value="pending"  <?= $user['status'] === 'pending'  ? 'selected' : '' ?>>Pending</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Package</label>
                            <select name="package_id" class="form-select">
                                <option value="0">No Package</option>
                                <?php foreach ($packages as $package): ?>
                                    <option value="<?= (int)$package['id'] ?>" <?= (int)($user['package_id'] ?? 0) === (int)$package['id'] ? 'selected' : '' ?>>
                                        <?= e($package['name']) ?> (<?= (int)$package['leads_per_day'] ?>/day)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Used only for customer accounts.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">
                                Password <?= $isNew ? '<span class="text-danger">*</span>' : '<span class="text-muted fw-normal">(leave blank to keep current)</span>' ?>
                            </label>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <input type="password" name="password" id="adminPwd" class="form-control"
                                               placeholder="<?= $isNew ? 'Min. 8 characters' : 'New password…' ?>">
                                        <button class="btn btn-outline-secondary" type="button" id="toggleAdminPwd">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <input type="password" name="password_confirm" class="form-control"
                                           placeholder="Confirm password">
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Internal Notes</label>
                            <textarea name="notes" class="form-control" rows="3"
                                      placeholder="Admin notes about this customer…"><?= e($user['notes'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12 pt-2">
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-save me-1"></i><?= $isNew ? 'Create User' : 'Save Changes' ?>
                            </button>
                            <a href="/admin/users.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if (!$isNew): ?>
    <div class="col-lg-4">
        <!-- Assigned leads summary -->
        <div class="card border-0 shadow-sm mb-3" style="border-radius:14px">
            <div class="card-header bg-white border-bottom fw-semibold py-3">Assigned Leads</div>
            <div class="card-body p-3">
                <?php
                $acStmt = $db->prepare('SELECT COUNT(*) FROM creators WHERE assigned_customer = ?');
                $acStmt->execute([$id]);
                $assignedCount = (int)$acStmt->fetchColumn();
                ?>
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted small">Total Assigned</span>
                    <span class="fw-bold fs-4"><?= number_format($assignedCount) ?></span>
                </div>
                <a href="/admin/leads.php?customer=<?= $id ?>" class="btn btn-sm btn-outline-primary w-100 mb-2">
                    <i class="bi bi-eye me-1"></i>View Their Leads
                </a>
                <a href="/admin/leads.php?filter=unassigned" class="btn btn-sm btn-outline-success w-100">
                    <i class="bi bi-plus-circle me-1"></i>Assign More Leads
                </a>
                <?php if ($user['role'] === 'customer' && (int)($user['package_id'] ?? 0) > 0): ?>
                    <form method="POST" action="/admin/user-form.php?id=<?= $id ?>" class="mt-2">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="assign_package_leads">
                        <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                            <i class="bi bi-magic me-1"></i>Assign Package Leads Now
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Danger zone -->
        <?php if ((int)$user['id'] !== (int)$_SESSION['user_id']): ?>
        <div class="card border-danger" style="border-radius:14px">
            <div class="card-header bg-white border-bottom fw-semibold py-3 text-danger">Danger Zone</div>
            <div class="card-body p-3">
                <p class="text-muted small mb-3">Deleting this user will remove their account but will NOT remove their assigned leads – leads will become unassigned.</p>
                <a href="/admin/user-delete.php?id=<?= $id ?>&csrf=<?= urlencode(csrfToken()) ?>"
                   class="btn btn-sm btn-outline-danger w-100 btn-confirm-delete"
                   data-label="<?= e($user['name']) ?>">
                    <i class="bi bi-trash me-1"></i>Delete User
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
$('#toggleAdminPwd').on('click', function(){
    var pwd = $('#adminPwd');
    var icon = $(this).find('i');
    pwd.attr('type', pwd.attr('type') === 'password' ? 'text' : 'password');
    icon.toggleClass('bi-eye bi-eye-slash');
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
