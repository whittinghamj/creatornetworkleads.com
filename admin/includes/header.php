<?php
/**
 * Admin shared header + sidebar
 * Variables expected from the calling page:
 *   $pageTitle  (string)
 *   $activeNav  (string) – matches sidebar link identifiers
 */
if (!isset($pageTitle))  { $pageTitle  = 'Admin'; }
if (!isset($activeNav))  { $activeNav  = ''; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> – Admin · <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="admin-wrapper">

    <!-- ── Sidebar ──────────────────────────────────────────────── -->
    <aside class="sidebar" id="adminSidebar">
        <a href="/admin/" class="sidebar-brand">
            <svg width="30" height="30" viewBox="0 0 28 28" fill="none">
                <rect width="28" height="28" rx="6" fill="#ff0050"/>
                <path d="M18 8h-3v8.5a2.5 2.5 0 1 1-2.5-2.5c.17 0 .34.02.5.05V11a6 6 0 1 0 5 5.9V12h2.5A2.5 2.5 0 0 1 18 9.5V8z" fill="#fff"/>
            </svg>
            <div>
                <div class="brand-text"><?= e(APP_NAME) ?></div>
                <div class="brand-sub">Admin Panel</div>
            </div>
        </a>

        <nav class="sidebar-nav">
            <div class="nav-section">Overview</div>
            <a href="/admin/" class="nav-link <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>

            <div class="nav-section mt-2">Customers</div>
            <a href="/admin/users.php" class="nav-link <?= $activeNav === 'users' ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i> All Users
            </a>
            <a href="/admin/user-form.php" class="nav-link <?= $activeNav === 'user-add' ? 'active' : '' ?>">
                <i class="bi bi-person-plus-fill"></i> Add User
            </a>

            <div class="nav-section mt-2">Creator Leads</div>
            <a href="/admin/leads.php" class="nav-link <?= $activeNav === 'leads' ? 'active' : '' ?>">
                <i class="bi bi-collection-fill"></i> All Leads
            </a>
            <a href="/admin/leads.php?filter=unassigned" class="nav-link <?= $activeNav === 'leads-unassigned' ? 'active' : '' ?>">
                <i class="bi bi-inbox-fill"></i> Unassigned Leads
            </a>

            <div class="nav-section mt-2">Settings</div>
            <a href="/dashboard.php" class="nav-link">
                <i class="bi bi-house-fill"></i> Customer View
            </a>
            <a href="/logout.php" class="nav-link text-danger">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </nav>

        <div class="px-3 py-3 border-top border-secondary" style="border-color:rgba(255,255,255,.07)!important">
            <div class="d-flex align-items-center gap-2">
                <div class="rounded-circle bg-danger d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0"
                     style="width:32px;height:32px;font-size:.8rem">
                    <?= strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)) ?>
                </div>
                <div class="overflow-hidden">
                    <div class="text-white small fw-semibold text-truncate"><?= e($_SESSION['user_name'] ?? '') ?></div>
                    <div class="text-muted" style="font-size:.7rem">Administrator</div>
                </div>
            </div>
        </div>
    </aside>

    <!-- ── Main content ─────────────────────────────────────────── -->
    <div class="admin-main">
        <!-- Topbar -->
        <div class="admin-topbar">
            <button class="btn btn-link text-muted p-0 me-3 d-lg-none" id="sidebarToggle">
                <i class="bi bi-list fs-4"></i>
            </button>
            <span class="fw-semibold text-dark"><?= e($pageTitle) ?></span>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <span class="text-muted small d-none d-md-inline"><?= e($_SESSION['user_email'] ?? '') ?></span>
                <a href="/logout.php" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-box-arrow-right me-1"></i>Logout
                </a>
            </div>
        </div>

        <!-- Content area -->
        <div class="admin-content">
            <?= flashHtml() ?>
