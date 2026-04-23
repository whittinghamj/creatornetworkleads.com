<?php
/**
 * Database connection – reads credentials from .env
 */

function parseEnvFile(string $path): array
{
    $env = [];
    if (!file_exists($path)) {
        return $env;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $env[trim($key)] = trim($value);
        }
    }
    return $env;
}

// Look for web-root .env first, then fall back to automation .env
$_envCandidates = [
    __DIR__ . '/../.env',
    __DIR__ . '/../automation/tiktok-backstage/.env',
];
$_env = [];
foreach ($_envCandidates as $_candidate) {
    if (file_exists($_candidate)) {
        $_env = parseEnvFile($_candidate);
        break;
    }
}
unset($_envCandidates, $_candidate);

if (!defined('DB_HOST')) {
    define('DB_HOST', $_env['DB_HOST']     ?? '10.254.6.110');
    define('DB_PORT', $_env['DB_PORT']     ?? '3306');
    define('DB_NAME', $_env['DB_NAME']     ?? 'tiktokcreatorleads');
    define('DB_USER', $_env['DB_USER']     ?? 'stiliam');
    define('DB_PASS', $_env['DB_PASSWORD'] ?? '');
}
unset($_env);

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            DB_HOST, DB_PORT, DB_NAME
        );
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}
