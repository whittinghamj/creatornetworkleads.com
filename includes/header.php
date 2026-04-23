<?php
/**
 * Public / customer site header
 * Variables expected: $pageTitle (string), $bodyClass (string, optional)
 */
if (!isset($pageTitle)) {
    $pageTitle = APP_NAME;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> – <?= e(APP_NAME) ?></title>
    <meta name="description" content="Premium TikTok Creator Leads for LIVE Backstage. Find verified creators ready for invitation.">
    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom styles -->
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="<?= isset($bodyClass) ? e($bodyClass) : '' ?>">

<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="/">
            <span class="brand-logo">
                <svg width="28" height="28" viewBox="0 0 28 28" fill="none">
                    <rect width="28" height="28" rx="6" fill="#ff0050"/>
                    <path d="M18 8h-3v8.5a2.5 2.5 0 1 1-2.5-2.5c.17 0 .34.02.5.05V11a6 6 0 1 0 5 5.9V12h2.5A2.5 2.5 0 0 1 18 9.5V8z" fill="#fff"/>
                </svg>
            </span>
            <span class="fw-bold"><?= e(APP_NAME) ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="/#features">Features</a></li>
                <li class="nav-item"><a class="nav-link" href="/#how-it-works">How It Works</a></li>
                <li class="nav-item"><a class="nav-link" href="/#pricing">Pricing</a></li>
            </ul>
            <div class="d-flex gap-2">
                <?php if (isLoggedIn()): ?>
                    <?php if (isAdmin()): ?>
                        <a href="/admin/" class="btn btn-outline-warning btn-sm">
                            <i class="bi bi-shield-lock me-1"></i>Admin Panel
                        </a>
                    <?php endif; ?>
                    <a href="/dashboard.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-speedometer2 me-1"></i>Dashboard
                    </a>
                    <a href="/logout.php" class="btn btn-danger btn-sm">
                        <i class="bi bi-box-arrow-right me-1"></i>Logout
                    </a>
                <?php else: ?>
                    <a href="/login.php" class="btn btn-outline-light btn-sm">Log In</a>
                    <a href="/register.php" class="btn btn-danger btn-sm">Get Access</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
