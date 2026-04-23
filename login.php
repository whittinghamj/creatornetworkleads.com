<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header(isAdmin() ? 'Location: /admin/' : 'Location: /dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email    = postStr('email');
    $password = postStr('password');

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $result = loginUser($email, $password);
        if ($result === '') {
            flash('Welcome back, ' . $_SESSION['user_name'] . '!');
            header(isAdmin() ? 'Location: /admin/' : 'Location: /dashboard.php');
            exit;
        }
        $error = $result;
    }
}
$pageTitle = 'Log In';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In – <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="auth-wrapper">
    <div class="container">
        <div class="d-flex justify-content-center">
            <div class="auth-card">
                <!-- Logo -->
                <a href="/" class="auth-logo mb-4 d-flex justify-content-center">
                    <svg width="32" height="32" viewBox="0 0 28 28" fill="none" class="me-2">
                        <rect width="28" height="28" rx="6" fill="#ff0050"/>
                        <path d="M18 8h-3v8.5a2.5 2.5 0 1 1-2.5-2.5c.17 0 .34.02.5.05V11a6 6 0 1 0 5 5.9V12h2.5A2.5 2.5 0 0 1 18 9.5V8z" fill="#fff"/>
                    </svg>
                    <span class="fw-bold fs-5"><?= e(APP_NAME) ?></span>
                </a>

                <h4 class="fw-bold text-center mb-1">Welcome back</h4>
                <p class="text-muted text-center mb-4 small">Log in to access your creator leads</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger small"><i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?></div>
                <?php endif; ?>
                <?= flashHtml() ?>

                <form method="POST" action="/login.php" novalidate>
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold small">Email address</label>
                        <input type="email" id="email" name="email" class="form-control"
                               value="<?= e($_POST['email'] ?? '') ?>"
                               placeholder="you@example.com" required autofocus autocomplete="email">
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label fw-semibold small">Password</label>
                        <div class="input-group">
                            <input type="password" id="password" name="password" class="form-control"
                                   placeholder="••••••••" required autocomplete="current-password">
                            <button class="btn btn-outline-secondary" type="button" id="togglePwd" title="Show/hide password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-danger w-100 fw-semibold">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Log In
                    </button>
                </form>

                <hr class="my-4">
                <p class="text-center text-muted small mb-0">
                    Don't have an account? <a href="/register.php" class="text-danger fw-semibold">Request access</a>
                </p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$('#togglePwd').on('click', function(){
    var pwd = $('#password');
    var icon = $(this).find('i');
    if (pwd.attr('type') === 'password') {
        pwd.attr('type', 'text');
        icon.removeClass('bi-eye').addClass('bi-eye-slash');
    } else {
        pwd.attr('type', 'password');
        icon.removeClass('bi-eye-slash').addClass('bi-eye');
    }
});
</script>
</body>
</html>
