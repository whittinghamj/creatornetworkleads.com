<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$db = getDB();
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

$errors   = [];
$invTypes = getInvitationTypes();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $displayName  = postStr('display_name');
    $username     = postStr('username');
    $region       = strtolower(postStr('backstage_region'));
    $status       = postStr('backstage_status');
    $checked      = in_array(postStr('backstage_checked'), ['yes','no']) ? postStr('backstage_checked') : 'no';
    $invType      = (int)postStr('invitation_type') ?: null;
    $avatar       = postStr('avatar');

    if ($username === '') {
        $errors[] = 'Username is required.';
    }

    if (empty($errors)) {
        $upStmt = $db->prepare(
            'UPDATE creators
             SET display_name=?, username=?, backstage_region=?, backstage_status=?,
                 backstage_checked=?, invitation_type=?, avatar=?
             WHERE id=?'
        );
        $upStmt->execute([
            $displayName ?: null,
            $username,
            $region,
            $status,
            $checked,
            $invType,
            $avatar ?: null,
            $id,
        ]);
        flash('Lead updated successfully.', 'success');
        header('Location: /admin/leads.php');
        exit;
    }

    // Re-populate for re-display
    $lead = array_merge($lead, [
        'display_name'      => postStr('display_name'),
        'username'          => postStr('username'),
        'backstage_region'  => postStr('backstage_region'),
        'backstage_status'  => postStr('backstage_status'),
        'backstage_checked' => postStr('backstage_checked'),
        'invitation_type'   => (int)postStr('invitation_type') ?: null,
        'avatar'            => postStr('avatar'),
    ]);
}

$statusOptions  = ['unknown','invited','accepted','rejected'];
$checkedOptions = ['yes','no'];

$pageTitle = 'Edit Lead: @' . ($lead['username'] ?? $id);
$activeNav = 'leads';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="/admin/leads.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
    <h5 class="fw-bold mb-0">Edit Creator Lead</h5>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger small">
    <ul class="mb-0 ps-3">
        <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm" style="border-radius:14px">
            <div class="card-header bg-white border-bottom fw-semibold py-3">Creator Details</div>
            <div class="card-body p-4">
                <form method="POST" action="/admin/lead-form.php?id=<?= $id ?>">
                    <?= csrfField() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Display Name</label>
                            <input type="text" name="display_name" class="form-control"
                                   value="<?= e($lead['display_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Username <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">@</span>
                                <input type="text" name="username" class="form-control"
                                       value="<?= e($lead['username'] ?? '') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Region (ISO code)</label>
                            <select name="backstage_region" class="form-select">
                                <?php foreach (REGIONS as $code => $name): ?>
                                    <option value="<?= e($code) ?>" <?= strtolower($lead['backstage_region'] ?? '') === $code ? 'selected' : '' ?>>
                                        <?= e($name) ?> (<?= strtoupper($code) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Invitation Type</label>
                            <select name="invitation_type" class="form-select">
                                <option value="0">— None —</option>
                                <?php foreach ($invTypes as $t): ?>
                                    <option value="<?= (int)$t['id'] ?>"
                                        <?= (int)($lead['invitation_type'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>>
                                        <?= e($t['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Backstage Status</label>
                            <select name="backstage_status" class="form-select">
                                <?php foreach ($statusOptions as $s): ?>
                                    <option value="<?= e($s) ?>" <?= ($lead['backstage_status'] ?? 'unknown') === $s ? 'selected' : '' ?>>
                                        <?= ucfirst(e($s)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Backstage Checked</label>
                            <select name="backstage_checked" class="form-select">
                                <option value="yes" <?= ($lead['backstage_checked'] ?? 'no') === 'yes' ? 'selected' : '' ?>>Yes</option>
                                <option value="no"  <?= ($lead['backstage_checked'] ?? 'no') === 'no'  ? 'selected' : '' ?>>No</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Avatar URL</label>
                            <input type="url" name="avatar" class="form-control"
                                   value="<?= e($lead['avatar'] ?? '') ?>"
                                   placeholder="https://…">
                        </div>
                        <div class="col-12 pt-2">
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-save me-1"></i>Save Changes
                            </button>
                            <a href="/admin/leads.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Preview -->
        <div class="card border-0 shadow-sm mb-3" style="border-radius:14px">
            <div class="card-header bg-white border-bottom fw-semibold py-3">Preview</div>
            <div class="card-body p-3 text-center">
                <?= avatarImg($lead['avatar'] ?? '', $lead['display_name'] ?: ($lead['username'] ?? ''), '72') ?>
                <div class="fw-semibold mt-2"><?= e($lead['display_name'] ?: '—') ?></div>
                <div class="text-muted small">@<?= e($lead['username'] ?: '—') ?></div>
                <div class="mt-2 d-flex flex-wrap gap-1 justify-content-center">
                    <?= invitationTypeBadge(isset($lead['invitation_type']) ? (int)$lead['invitation_type'] : null) ?>
                    <span class="badge bg-dark"><?= strtoupper(e($lead['backstage_region'] ?? '')) ?></span>
                </div>
                <div class="mt-1"><?= statusBadge($lead['backstage_status'] ?? 'unknown') ?></div>
            </div>
        </div>

        <!-- Assignment info -->
        <div class="card border-0 shadow-sm mb-3" style="border-radius:14px">
            <div class="card-header bg-white border-bottom fw-semibold py-3">Assignment</div>
            <div class="card-body p-3">
                <?php if ($lead['assigned_customer']): ?>
                    <?php
                    $ownerStmt = $db->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
                    $ownerStmt->execute([$lead['assigned_customer']]);
                    $owner = $ownerStmt->fetch();
                    ?>
                    <p class="small mb-1"><span class="text-muted">Assigned to:</span></p>
                    <p class="fw-semibold small mb-3"><?= e($owner['name'] ?? 'Unknown') ?><br>
                        <span class="text-muted fw-normal"><?= e($owner['email'] ?? '') ?></span>
                    </p>
                <?php else: ?>
                    <p class="text-muted small mb-3">This lead is currently <strong>unassigned</strong>.</p>
                <?php endif; ?>
                <a href="/admin/lead-assign.php?id=<?= $id ?>" class="btn btn-sm btn-outline-success w-100">
                    <i class="bi bi-person-check me-1"></i><?= $lead['assigned_customer'] ? 'Reassign Lead' : 'Assign Lead' ?>
                </a>
            </div>
        </div>

        <!-- Danger zone -->
        <div class="card border-danger" style="border-radius:14px">
            <div class="card-body p-3">
                <a href="/admin/lead-delete.php?id=<?= $id ?>&csrf=<?= urlencode(csrfToken()) ?>"
                   class="btn btn-sm btn-outline-danger w-100 btn-confirm-delete"
                   data-label="this creator lead">
                    <i class="bi bi-trash me-1"></i>Delete Lead
                </a>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
