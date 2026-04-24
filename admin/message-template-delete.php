<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$db = getDB();
ensureMessageTemplatesSchema($db);

$id = getInt('id');
$csrf = getStr('csrf');

if ($id <= 0) {
    flash('Invalid template ID.', 'danger');
    header('Location: /admin/message-templates.php');
    exit;
}

if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    flash('Invalid security token.', 'danger');
    header('Location: /admin/message-templates.php');
    exit;
}

$stmt = $db->prepare('SELECT id, title FROM message_templates WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$template = $stmt->fetch();

if (!$template) {
    flash('Message template not found.', 'danger');
    header('Location: /admin/message-templates.php');
    exit;
}

$db->prepare('DELETE FROM message_templates WHERE id = ?')->execute([$id]);
flash('Message template "' . $template['title'] . '" deleted.', 'success');

header('Location: /admin/message-templates.php');
exit;
