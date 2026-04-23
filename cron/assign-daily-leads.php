<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDB();
ensurePackagesSchema($db);

$cronKey = defined('CRON_ASSIGN_KEY') ? (string)CRON_ASSIGN_KEY : '';
$isCli = (PHP_SAPI === 'cli');

if (!$isCli && $cronKey !== '') {
    $provided = (string)($_GET['key'] ?? '');
    if (!hash_equals($cronKey, $provided)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid key'], JSON_PRETTY_PRINT);
        exit;
    }
}

$result = assignAvailableLeadsForAllCustomers($db);

if (!$isCli) {
    header('Content-Type: application/json');
}
echo json_encode([
    'success' => true,
    'ran_at' => date('c'),
    'summary' => $result,
], JSON_PRETTY_PRINT);
