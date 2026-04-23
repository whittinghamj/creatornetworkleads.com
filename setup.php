<?php
/**
 * setup.php – First-run admin account creator
 *
 * DELETE THIS FILE after creating your admin account.
 * Accessible at: https://yourdomain.com/setup.php
 */

// Block if any admin already exists
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

try {
    $db           = getDB();
    $adminExists  = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
} catch (Exception $e) {
    die('<pre style="color:red">Database error: ' . htmlspecialchars($e->getMessage()) . '</pre>');
}

$done   = false;
$error  = '';
$fields = ['name' => 'Admin', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields['name']  = trim($_POST['name']  ?? '');
    $fields['email'] = strtolower(trim($_POST['email'] ?? ''));
    $password        = $_POST['password']         ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if ($adminExists > 0) {
        $error = 'An admin account already exists. Delete this file.';
    } elseif ($fields['name'] === '') {
        $error = 'Name is required.';
    } elseif (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'A valid email is required.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $db->prepare(
            "INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, 'admin', 'active')"
        );
        $stmt->execute([
            $fields['name'],
            $fields['email'],
            password_hash($password, PASSWORD_DEFAULT),
        ]);
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup – <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { background: #f8fafc; }
        .setup-card { max-width: 460px; margin: 80px auto; background: #fff; border-radius: 16px; padding: 2.5rem; box-shadow: 0 10px 40px rgba(0,0,0,.1); }
    </style>
</head>
<body>
<div class="setup-card">
    <h4 class="fw-bold mb-1">Initial Setup</h4>
    <p class="text-muted small mb-4">Create the first administrator account. <strong>Delete this file afterwards.</strong></p>

    <?php if ($done): ?>
        <div class="alert alert-success">
            <strong>Admin account created!</strong><br>
            You can now <a href="/login.php">log in</a> with your credentials.<br><br>
            <span class="text-danger fw-semibold">⚠ Delete <code>setup.php</code> from your server immediately.</span>
        </div>
    <?php elseif ($adminExists > 0): ?>
        <div class="alert alert-warning">
            An administrator account already exists.<br>
            <a href="/login.php" class="btn btn-sm btn-danger mt-2">Go to Login</a>
        </div>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="alert alert-danger small"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label small fw-semibold">Full Name</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($fields['name']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Email Address</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($fields['email']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Min. 8 characters" required>
            </div>
            <div class="mb-4">
                <label class="form-label small fw-semibold">Confirm Password</label>
                <input type="password" name="password_confirm" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-danger w-100 fw-semibold">Create Admin Account</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
