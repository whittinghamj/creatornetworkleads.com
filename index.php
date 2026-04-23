<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Premium TikTok Creator Leads';

// Fetch a live stat count for the hero
try {
    $totalLeads = (int)getDB()->query('SELECT COUNT(*) FROM creators')->fetchColumn();
} catch (Exception $e) {
    $totalLeads = 500;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> – <?= e(APP_TAGLINE) ?></title>
    <meta name="description" content="Premium verified TikTok creator leads for LIVE Backstage. Save time prospecting – get instant access to ready-to-invite creators.">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="/">
            <svg width="28" height="28" viewBox="0 0 28 28" fill="none">
                <rect width="28" height="28" rx="6" fill="#ff0050"/>
                <path d="M18 8h-3v8.5a2.5 2.5 0 1 1-2.5-2.5c.17 0 .34.02.5.05V11a6 6 0 1 0 5 5.9V12h2.5A2.5 2.5 0 0 1 18 9.5V8z" fill="#fff"/>
            </svg>
            <span class="fw-bold"><?= e(APP_NAME) ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                <li class="nav-item"><a class="nav-link" href="#how-it-works">How It Works</a></li>
                <li class="nav-item"><a class="nav-link" href="#pricing">Pricing</a></li>
            </ul>
            <div class="d-flex gap-2">
                <?php if (isLoggedIn()): ?>
                    <?php if (isAdmin()): ?>
                        <a href="/admin/" class="btn btn-outline-warning btn-sm"><i class="bi bi-shield-lock me-1"></i>Admin</a>
                    <?php endif; ?>
                    <a href="/dashboard.php" class="btn btn-outline-light btn-sm"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
                    <a href="/logout.php" class="btn btn-danger btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
                <?php else: ?>
                    <a href="/login.php" class="btn btn-outline-light btn-sm">Log In</a>
                    <a href="/register.php" class="btn btn-danger btn-sm">Get Access</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- ── Hero ───────────────────────────────────────────────────── -->
<section class="hero-section text-white">
    <div class="container py-5">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <div class="hero-badge mb-3">
                    <i class="bi bi-lightning-charge-fill me-1"></i> TikTok LIVE Backstage
                </div>
                <h1 class="hero-title mb-4">
                    Find <span class="accent">Verified</span> TikTok<br>
                    Creator Leads<br>Instantly
                </h1>
                <p class="hero-subtitle mb-4">
                    Access pre-vetted TikTok creators ready for LIVE Backstage invitations.
                    Regional targeting, multiple invitation types – everything you need to grow your LIVE programme.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="/register.php" class="btn btn-danger btn-lg px-4">
                        <i class="bi bi-rocket-takeoff me-2"></i>Get Started Free
                    </a>
                    <a href="#how-it-works" class="btn btn-outline-light btn-lg px-4">
                        How It Works
                    </a>
                </div>
                <div class="mt-4 d-flex gap-4 text-muted">
                    <div><i class="bi bi-check-circle-fill text-success me-1"></i>No setup fee</div>
                    <div><i class="bi bi-check-circle-fill text-success me-1"></i>Instant access</div>
                    <div><i class="bi bi-check-circle-fill text-success me-1"></i>Dedicated support</div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="hero-mockup">
                    <div class="d-flex align-items-center gap-2 mb-3 pb-3 border-bottom border-secondary">
                        <div class="rounded-circle bg-danger d-flex align-items-center justify-content-center text-white fw-bold" style="width:36px;height:36px;font-size:.9rem">C</div>
                        <div>
                            <div class="fw-semibold text-white small">Creator Lead Dashboard</div>
                            <div class="text-muted" style="font-size:.75rem">Your assigned leads</div>
                        </div>
                        <span class="badge bg-success ms-auto">Live</span>
                    </div>
                    <?php
                    $mockCreators = [
                        ['name' => 'Sarah Chen',     'user' => '@sarahcreates',  'region' => 'UK', 'type' => 'LIVE Host',  'color' => 'danger'],
                        ['name' => 'Marcus Johnson', 'user' => '@marcusj',       'region' => 'US', 'type' => 'Creator',    'color' => 'primary'],
                        ['name' => 'Emma Williams',  'user' => '@emmaw_tok',     'region' => 'AU', 'type' => 'Affiliate',  'color' => 'success'],
                        ['name' => 'Kai Yamamoto',   'user' => '@kaiyama',       'region' => 'JP', 'type' => 'LIVE Host',  'color' => 'danger'],
                    ];
                    foreach ($mockCreators as $mc):
                    ?>
                    <div class="d-flex align-items-center gap-3 py-2 border-bottom border-secondary">
                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0"
                             style="width:38px;height:38px;background:#6366f1;font-size:.9rem">
                            <?= strtoupper(substr($mc['name'], 0, 1)) ?>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold text-white small"><?= e($mc['name']) ?></div>
                            <div class="text-muted" style="font-size:.75rem"><?= e($mc['user']) ?></div>
                        </div>
                        <span class="badge bg-<?= $mc['color'] ?> flex-shrink-0"><?= e($mc['type']) ?></span>
                        <span class="badge bg-secondary flex-shrink-0"><?= e($mc['region']) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <div class="text-center text-muted pt-3" style="font-size:.78rem">
                        <i class="bi bi-arrow-down-circle me-1"></i>
                        <?= number_format($totalLeads) ?>+ creator leads available
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── Stats Strip ────────────────────────────────────────────── -->
<div class="stats-strip text-white py-4">
    <div class="container">
        <div class="row g-3 text-center">
            <div class="col-6 col-md-3">
                <div class="stat-number"><?= number_format($totalLeads) ?>+</div>
                <div class="stat-label">Creator Leads</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-number">20+</div>
                <div class="stat-label">Regions</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-number">5</div>
                <div class="stat-label">Invitation Types</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-number">24/7</div>
                <div class="stat-label">Platform Access</div>
            </div>
        </div>
    </div>
</div>

<!-- ── Features ───────────────────────────────────────────────── -->
<section id="features" class="py-6 bg-light" style="padding:5rem 0">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold fs-1">Everything You Need to Scale</h2>
            <p class="text-muted fs-5">Powerful tools to find and manage your TikTok creator pipeline</p>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-patch-check-fill"></i></div>
                    <h5 class="fw-bold">Verified Profiles</h5>
                    <p class="text-muted mb-0">Every creator has been checked against TikTok Backstage. No wasted invitations on ineligible accounts.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-geo-alt-fill"></i></div>
                    <h5 class="fw-bold">Regional Targeting</h5>
                    <p class="text-muted mb-0">Filter creators by region – UK, US, AU, and 20+ more countries. Reach the right audience in the right market.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-send-fill"></i></div>
                    <h5 class="fw-bold">Invitation Types</h5>
                    <p class="text-muted mb-0">LIVE Host, Creator, Affiliate, Shop Seller and more. Leads matched to the exact invitation type you need.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-speedometer2"></i></div>
                    <h5 class="fw-bold">Instant Dashboard</h5>
                    <p class="text-muted mb-0">All your assigned leads in one clean dashboard. Photos, usernames, regions and statuses at a glance.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-person-fill-lock"></i></div>
                    <h5 class="fw-bold">Exclusive Assignment</h5>
                    <p class="text-muted mb-0">Leads are assigned exclusively to your account – no sharing, no competition, no duplicates.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-graph-up-arrow"></i></div>
                    <h5 class="fw-bold">Continuously Updated</h5>
                    <p class="text-muted mb-0">Our database is regularly refreshed so your leads have current status information from TikTok Backstage.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── How It Works ───────────────────────────────────────────── -->
<section id="how-it-works" class="py-6" style="padding:5rem 0">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold fs-1">How It Works</h2>
            <p class="text-muted fs-5">Up and running in minutes</p>
        </div>
        <div class="row g-4 justify-content-center">
            <div class="col-md-6 col-lg-3 text-center">
                <div class="step-number">1</div>
                <h6 class="fw-bold">Create Account</h6>
                <p class="text-muted small">Register your account and our team will review and activate it promptly.</p>
            </div>
            <div class="col-md-6 col-lg-3 text-center">
                <div class="step-number">2</div>
                <h6 class="fw-bold">Receive Leads</h6>
                <p class="text-muted small">We assign a curated batch of creator leads matching your region and invitation type preferences.</p>
            </div>
            <div class="col-md-6 col-lg-3 text-center">
                <div class="step-number">3</div>
                <h6 class="fw-bold">Access Dashboard</h6>
                <p class="text-muted small">Log in to see all your assigned leads with full profile data – avatar, username, region, and status.</p>
            </div>
            <div class="col-md-6 col-lg-3 text-center">
                <div class="step-number">4</div>
                <h6 class="fw-bold">Send Invitations</h6>
                <p class="text-muted small">Use the creator details to send invitations through TikTok Backstage and grow your LIVE community.</p>
            </div>
        </div>
    </div>
</section>

<!-- ── Pricing ────────────────────────────────────────────────── -->
<section id="pricing" class="py-6 bg-light" style="padding:5rem 0">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold fs-1">Simple Pricing</h2>
            <p class="text-muted fs-5">All plans include full dashboard access and dedicated support</p>
        </div>
        <div class="row g-4 justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card pricing-card border h-100">
                    <div class="card-body p-4">
                        <h5 class="fw-bold">Starter</h5>
                        <p class="text-muted small">Perfect for getting started with creator outreach</p>
                        <div class="d-flex align-items-end gap-1 mb-4">
                            <span class="fs-1 fw-black">Contact</span>
                        </div>
                        <ul class="list-unstyled mb-4 text-muted small">
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Up to 50 creator leads</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>1 region</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>All invitation types</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Dashboard access</li>
                        </ul>
                        <a href="/register.php" class="btn btn-outline-danger w-100">Get Started</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card pricing-card border featured h-100">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <h5 class="fw-bold">Professional</h5>
                            <span class="badge bg-danger">Popular</span>
                        </div>
                        <p class="text-muted small">For agencies and serious LIVE managers</p>
                        <div class="d-flex align-items-end gap-1 mb-4">
                            <span class="fs-1 fw-black">Contact</span>
                        </div>
                        <ul class="list-unstyled mb-4 text-muted small">
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Up to 200 creator leads</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>5 regions</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>All invitation types</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Priority support</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Weekly lead refresh</li>
                        </ul>
                        <a href="/register.php" class="btn btn-danger w-100">Get Started</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card pricing-card border h-100">
                    <div class="card-body p-4">
                        <h5 class="fw-bold">Enterprise</h5>
                        <p class="text-muted small">Bespoke solutions for large-scale programmes</p>
                        <div class="d-flex align-items-end gap-1 mb-4">
                            <span class="fs-1 fw-black">Custom</span>
                        </div>
                        <ul class="list-unstyled mb-4 text-muted small">
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Unlimited leads</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>All regions</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Dedicated account manager</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Custom filtering & exports</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>SLA guarantee</li>
                        </ul>
                        <a href="/register.php" class="btn btn-outline-danger w-100">Contact Us</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── CTA Banner ─────────────────────────────────────────────── -->
<section class="py-5" style="background:linear-gradient(135deg,#0a0a0a 0%,#1a0010 100%)">
    <div class="container text-center text-white py-4">
        <h2 class="fw-bold fs-1 mb-3">Ready to Scale Your TikTok LIVE Programme?</h2>
        <p class="text-muted fs-5 mb-4">Join agencies and talent managers already using <?= e(APP_NAME) ?></p>
        <a href="/register.php" class="btn btn-danger btn-lg px-5">
            <i class="bi bi-rocket-takeoff me-2"></i>Create Your Account
        </a>
    </div>
</section>

<!-- Footer -->
<footer class="bg-dark text-light py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <svg width="26" height="26" viewBox="0 0 28 28" fill="none"><rect width="28" height="28" rx="6" fill="#ff0050"/><path d="M18 8h-3v8.5a2.5 2.5 0 1 1-2.5-2.5c.17 0 .34.02.5.05V11a6 6 0 1 0 5 5.9V12h2.5A2.5 2.5 0 0 1 18 9.5V8z" fill="#fff"/></svg>
                    <span class="fw-bold"><?= e(APP_NAME) ?></span>
                </div>
                <p class="text-muted small">Premium TikTok creator leads for LIVE Backstage agencies and talent managers.</p>
            </div>
            <div class="col-lg-2 col-6">
                <h6 class="fw-semibold mb-3">Platform</h6>
                <ul class="list-unstyled small text-muted">
                    <li class="mb-1"><a href="#features" class="text-muted text-decoration-none">Features</a></li>
                    <li class="mb-1"><a href="#how-it-works" class="text-muted text-decoration-none">How It Works</a></li>
                    <li class="mb-1"><a href="#pricing" class="text-muted text-decoration-none">Pricing</a></li>
                </ul>
            </div>
            <div class="col-lg-2 col-6">
                <h6 class="fw-semibold mb-3">Account</h6>
                <ul class="list-unstyled small text-muted">
                    <li class="mb-1"><a href="/login.php" class="text-muted text-decoration-none">Log In</a></li>
                    <li class="mb-1"><a href="/register.php" class="text-muted text-decoration-none">Register</a></li>
                </ul>
            </div>
            <div class="col-lg-4">
                <p class="text-muted small mb-1">© <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.</p>
                <p class="text-muted small">Not affiliated with TikTok or ByteDance Ltd.</p>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>
