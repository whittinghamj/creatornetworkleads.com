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
