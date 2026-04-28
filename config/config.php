<?php
/**
 * Application configuration – loaded once before any output
 */

if (!defined('APP_NAME')) {
    define('APP_NAME',    'CreatorNetworkLeads');
    define('APP_TAGLINE', 'Premium TikTok Creator Leads for LIVE Backstage');
    define('APP_URL',     'https://creatornetworkleads.com');
    define('PER_PAGE',    25);
    define('CRON_ASSIGN_KEY', '');
}

if (!function_exists('configEnvValues')) {
    function configEnvValues(): array
    {
        static $env = null;
        if ($env !== null) {
            return $env;
        }

        $env = [];
        $candidates = [
            __DIR__ . '/../.env',
            __DIR__ . '/../automation/tiktok-backstage/.env',
        ];

        foreach ($candidates as $file) {
            if (!is_file($file)) {
                continue;
            }

            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $env[trim($k)] = trim($v);
            }
            break;
        }

        return $env;
    }
}

$configEnv = configEnvValues();

if (!defined('PAYPAL_MODE')) {
    define('PAYPAL_MODE', strtolower((string)($configEnv['PAYPAL_MODE'] ?? 'sandbox')) === 'live' ? 'live' : 'sandbox');
    define('PAYPAL_CLIENT_ID', (string)($configEnv['PAYPAL_CLIENT_ID'] ?? ''));
    define('PAYPAL_CLIENT_SECRET', (string)($configEnv['PAYPAL_CLIENT_SECRET'] ?? ''));
    define('PAYPAL_WEBHOOK_ID', (string)($configEnv['PAYPAL_WEBHOOK_ID'] ?? ''));
    define('PAYPAL_BILLING_EMAIL', (string)($configEnv['PAYPAL_BILLING_EMAIL'] ?? 'billing@genexnet.com'));
    define('PAYPAL_RETURN_URL', (string)($configEnv['PAYPAL_RETURN_URL'] ?? rtrim(APP_URL, '/') . '/billing.php?paypal=success'));
    define('PAYPAL_CANCEL_URL', (string)($configEnv['PAYPAL_CANCEL_URL'] ?? rtrim(APP_URL, '/') . '/billing.php?paypal=cancel'));
}
unset($configEnv);

// Timezone
date_default_timezone_set('UTC');

// Secure session settings (must be set before session_start)
ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Lax');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}

if (session_status() === PHP_SESSION_NONE) {
    session_name('cnl_session');
    session_start();
}

// Region lookup (ISO 3166-1 alpha-2 → display name)
define('REGIONS', [
    'uk' => 'United Kingdom',
    'us' => 'United States',
    'ca' => 'Canada',
    'au' => 'Australia',
    'de' => 'Germany',
    'fr' => 'France',
    'it' => 'Italy',
    'es' => 'Spain',
    'nl' => 'Netherlands',
    'se' => 'Sweden',
    'no' => 'Norway',
    'dk' => 'Denmark',
    'fi' => 'Finland',
    'pl' => 'Poland',
    'sg' => 'Singapore',
    'my' => 'Malaysia',
    'id' => 'Indonesia',
    'th' => 'Thailand',
    'ph' => 'Philippines',
    'vn' => 'Vietnam',
    'ae' => 'UAE',
    'sa' => 'Saudi Arabia',
    'br' => 'Brazil',
    'mx' => 'Mexico',
    'jp' => 'Japan',
    'kr' => 'South Korea',
]);
