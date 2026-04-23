<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$db = getDB();
ensurePackagesSchema($db);

$id = getInt('id');
$csrf = getStr('csrf');

if ($id <= 0) {
    flash('Invalid package ID.', 'danger');
    header('Location: /admin/packages.php');
    exit;
}

if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    flash('Invalid security token.', 'danger');
    header('Location: /admin/packages.php');
    exit;
}

$stmt = $db->prepare('SELECT id, name FROM packages WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$package = $stmt->fetch();

if (!$package) {
    flash('Package not found.', 'danger');
    header('Location: /admin/packages.php');
    exit;
}

$db->beginTransaction();
try {
    $db->prepare('UPDATE users SET package_id = NULL WHERE package_id = ?')->execute([$id]);
    $db->prepare('DELETE FROM packages WHERE id = ?')->execute([$id]);
    $db->commit();
    flash('Package "' . $package['name'] . '" deleted.', 'success');
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    flash('Failed to delete package: ' . $e->getMessage(), 'danger');
}

header('Location: /admin/packages.php');
exit;
