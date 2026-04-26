<?php
/**
 * Authentication helpers
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function isAdmin(): bool
{
    return isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'admin';
}

function requireLogin(string $redirect = '/login.php'): void
{
    if (!isLoggedIn()) {
        header('Location: ' . $redirect);
        exit;
    }
}

function requireAdmin(): void
{
    if (!isAdmin()) {
        header(isLoggedIn() ? 'Location: /dashboard.php' : 'Location: /login.php');
        exit;
    }
}

function getCurrentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }
    $stmt = getDB()->prepare(
        'SELECT id, name, email, company, phone, role, status FROM users WHERE id = ? AND status = "active" LIMIT 1'
    );
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function loginUser(string $email, string $password): string
{
    $db   = getDB();
    ensureUserIpTrackingSchema($db);
    ensureUserIpAuditSchema($db);

    $stmt = $db->prepare('SELECT id, name, email, password, role, status FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([strtolower(trim($email))]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return 'Invalid email or password.';
    }
    if ($user['status'] === 'pending') {
        return 'Your account is pending approval. Please contact support.';
    }
    if ($user['status'] !== 'active') {
        return 'Your account has been disabled. Please contact support.';
    }

    // Prevent session fixation
    session_regenerate_id(true);

    $_SESSION['user_id']    = (int)$user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $user['role'];

    $ipAddress = getClientIpAddress();
    $db->prepare('UPDATE users SET last_login = NOW(), last_login_ip = ? WHERE id = ?')->execute([$ipAddress ?: null, $user['id']]);
    logUserIpAudit($db, (int)$user['id'], 'login', $ipAddress);

    return '';
}

function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
