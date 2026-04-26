<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: /dashboard.php');
    exit;
}

$errors = [];
$fields = ['name' => '', 'email' => '', 'company' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $fields['name']     = postStr('name');
    $fields['email']    = strtolower(postStr('email'));
    $fields['company']  = postStr('company');
    $fields['phone']    = postStr('phone');
    $password           = postStr('password');
    $passwordConfirm    = postStr('password_confirm');

    if ($fields['name'] === '') {
        $errors[] = 'Full name is required.';
    }
    if ($fields['email'] === '' || !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $passwordConfirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $db   = getDB();
        ensureUserIpTrackingSchema($db);

        $stmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$fields['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with that email already exists.';
        }
    }

    if (empty($errors)) {
        $db = getDB();
        ensureUserIpTrackingSchema($db);
        ensureUserIpAuditSchema($db);
        $signupIp = getClientIpAddress();

        $stmt = $db->prepare(
            'INSERT INTO users (name, email, password, company, phone, signup_ip, status, role)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $fields['name'],
            $fields['email'],
            password_hash($password, PASSWORD_DEFAULT),
            $fields['company'] ?: null,
            $fields['phone']   ?: null,
            $signupIp !== '' ? $signupIp : null,
            'pending',
            'customer',
        ]);
        logUserIpAudit($db, (int)$db->lastInsertId(), 'signup', $signupIp);

        flash('Your account has been created and is pending approval. We\'ll be in touch shortly!', 'success');
        header('Location: /login.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register – <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="auth-wrapper py-5">
    <div class="container">
        <div class="d-flex justify-content-center">
            <div class="auth-card" style="max-width:500px">
                <!-- Logo -->
                <a href="/" class="auth-logo mb-4 d-flex justify-content-center">
                    <svg width="32" height="32" viewBox="0 0 28 28" fill="none" class="me-2">
                        <rect width="28" height="28" rx="6" fill="#ff0050"/>
                        <path d="M18 8h-3v8.5a2.5 2.5 0 1 1-2.5-2.5c.17 0 .34.02.5.05V11a6 6 0 1 0 5 5.9V12h2.5A2.5 2.5 0 0 1 18 9.5V8z" fill="#fff"/>
                    </svg>
                    <span class="fw-bold fs-5"><?= e(APP_NAME) ?></span>
                </a>

                <h4 class="fw-bold text-center mb-1">Request Access</h4>
                <p class="text-muted text-center mb-4 small">Create your account to access creator leads</p>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger small">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/register.php" novalidate>
                    <?= csrfField() ?>
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="name" class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" class="form-control"
                                   value="<?= e($fields['name']) ?>" placeholder="Jane Smith" required autocomplete="name">
                        </div>
                        <div class="col-12">
                            <label for="email" class="form-label fw-semibold small">Email Address <span class="text-danger">*</span></label>
                            <input type="email" id="email" name="email" class="form-control"
                                   value="<?= e($fields['email']) ?>" placeholder="jane@company.com" required autocomplete="email">
                        </div>
                        <div class="col-12">
                            <label for="company" class="form-label fw-semibold small">Company / Agency</label>
                            <input type="text" id="company" name="company" class="form-control"
                                   value="<?= e($fields['company']) ?>" placeholder="Acme Agency Ltd">
                        </div>
                        <div class="col-12">
                            <label for="phone" class="form-label fw-semibold small">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-control"
                                   value="<?= e($fields['phone']) ?>" placeholder="+44 7700 000000" autocomplete="tel">
                        </div>
                        <div class="col-12">
                            <label for="password" class="form-label fw-semibold small">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" id="password" name="password" class="form-control"
                                       placeholder="Min. 8 characters" required autocomplete="new-password">
                                <button class="btn btn-outline-secondary" type="button" id="togglePwd">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-12">
                            <label for="password_confirm" class="form-label fw-semibold small">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" id="password_confirm" name="password_confirm" class="form-control"
                                   placeholder="Repeat password" required autocomplete="new-password">
                        </div>
                        <div class="col-12 mt-2">
                            <button type="submit" class="btn btn-danger w-100 fw-semibold">
                                <i class="bi bi-person-check me-2"></i>Create Account
                            </button>
                        </div>
                    </div>
                </form>

                <hr class="my-4">
                <p class="text-center text-muted small mb-0">
                    Already have an account? <a href="/login.php" class="text-danger fw-semibold">Log in</a>
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
