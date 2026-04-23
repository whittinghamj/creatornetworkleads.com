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
    header('Location: /admin/leads.php');
    exit;
}

if ($id === 0) {
    flash('Invalid lead ID.', 'danger');
    header('Location: /admin/leads.php');
    exit;
}

$db   = getDB();
$stmt = $db->prepare('SELECT id, username FROM creators WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$lead = $stmt->fetch();

if (!$lead) {
    flash('Lead not found.', 'danger');
    header('Location: /admin/leads.php');
    exit;
}

$db->prepare('DELETE FROM creators WHERE id = ?')->execute([$id]);

flash('Creator lead @' . ($lead['username'] ?: $id) . ' has been deleted.', 'success');
header('Location: /admin/leads.php');
exit;
