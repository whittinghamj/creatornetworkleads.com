<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$db = getDB();
ensurePackagesSchema($db);

$id = getInt('id');
$isNew = ($id === 0);

$package = [
    'id' => 0,
    'name' => '',
    'description' => '',
    'leads_per_day' => 0,
    'price_per_month' => '0.00',
];

if (!$isNew) {
    $stmt = $db->prepare('SELECT * FROM packages WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        flash('Package not found.', 'danger');
        header('Location: /admin/packages.php');
        exit;
    }
    $package = array_merge($package, $existing);
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $package['name'] = postStr('name');
    $package['description'] = postStr('description');
    $package['leads_per_day'] = max(0, (int)postStr('leads_per_day'));
    $package['price_per_month'] = number_format((float)postStr('price_per_month'), 2, '.', '');

    if ($package['name'] === '') {
        $errors[] = 'Package name is required.';
    }

    if ($package['leads_per_day'] < 0) {
        $errors[] = 'Leads per day must be zero or greater.';
    }

    if ((float)$package['price_per_month'] < 0) {
        $errors[] = 'Price per month must be zero or greater.';
    }

    if (empty($errors)) {
        if ($isNew) {
            $stmt = $db->prepare(
                'INSERT INTO packages (name, description, leads_per_day, price_per_month)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([
                $package['name'],
                $package['description'] ?: null,
                $package['leads_per_day'],
                $package['price_per_month'],
            ]);
            flash('Package created successfully.', 'success');
        } else {
            $stmt = $db->prepare(
                'UPDATE packages
                 SET name = ?, description = ?, leads_per_day = ?, price_per_month = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $package['name'],
                $package['description'] ?: null,
                $package['leads_per_day'],
                $package['price_per_month'],
                $id,
            ]);
            flash('Package updated successfully.', 'success');
        }

        header('Location: /admin/packages.php');
        exit;
    }
}

$pageTitle = $isNew ? 'Add Package' : 'Edit Package';
$activeNav = 'packages';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="/admin/packages.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
    <h5 class="fw-bold mb-0"><?= $isNew ? 'Add Package' : 'Edit Package' ?></h5>
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
    <div class="card-header bg-white border-bottom fw-semibold py-3">Package Details</div>
    <div class="card-body p-4">
        <form method="POST" action="/admin/package-form.php<?= $isNew ? '' : '?id=' . $id ?>">
            <?= csrfField() ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Package Name</label>
                    <input type="text" name="name" class="form-control" value="<?= e($package['name']) ?>" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Leads Per Day</label>
                    <input type="number" name="leads_per_day" min="0" step="1" class="form-control"
                           value="<?= (int)$package['leads_per_day'] ?>" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Price Per Month (GBP)</label>
                    <input type="number" name="price_per_month" min="0" step="0.01" class="form-control"
                           value="<?= e((string)$package['price_per_month']) ?>" required>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-semibold">Description</label>
                    <textarea name="description" class="form-control" rows="4"><?= e((string)$package['description']) ?></textarea>
                </div>

                <div class="col-12 pt-2">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-save me-1"></i><?= $isNew ? 'Create Package' : 'Save Changes' ?>
                    </button>
                    <a href="/admin/packages.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
