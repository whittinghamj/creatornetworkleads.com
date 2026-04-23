<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$id   = getInt('id');
$csrf = getStr('csrf');

if (!hash_equals(csrfToken(), $csrf)) {
    flash('Invalid security token.', 'danger');
    header('Location: /admin/users.php');
    exit;
}

if ($id === (int)$_SESSION['user_id']) {
    flash('You cannot delete your own account.', 'danger');
    header('Location: /admin/users.php');
    exit;
}

$db   = getDB();
$stmt = $db->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    flash('User not found.', 'danger');
    header('Location: /admin/users.php');
    exit;
}

// Unassign their leads before deleting
$db->prepare('UPDATE creators SET assigned_customer = NULL WHERE assigned_customer = ?')->execute([$id]);

// Delete user
$db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);

flash('User "' . $user['name'] . '" has been deleted. Their leads are now unassigned.', 'success');
header('Location: /admin/users.php');
exit;
