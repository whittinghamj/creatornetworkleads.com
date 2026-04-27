<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$db = getDB();
ensureBackstageAccountsSchema($db);

$id = getInt('id');
$csrf = getStr('csrf');

if ($id <= 0) {
    flash('Invalid backstage account ID.', 'danger');
    header('Location: /admin/backstage-accounts.php');
    exit;
}

if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    flash('Invalid security token.', 'danger');
    header('Location: /admin/backstage-accounts.php');
    exit;
}

$stmt = $db->prepare('SELECT id, email FROM backstage_accounts WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$account = $stmt->fetch();

if (!$account) {
    flash('Backstage account not found.', 'danger');
    header('Location: /admin/backstage-accounts.php');
    exit;
}

$db->prepare('DELETE FROM backstage_accounts WHERE id = ?')->execute([$id]);
flash('Backstage account "' . $account['email'] . '" deleted.', 'success');

header('Location: /admin/backstage-accounts.php');
exit;