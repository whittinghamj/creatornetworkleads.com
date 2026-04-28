<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Premium TikTok Creator Leads';
$shareTitle = APP_NAME . ' - ' . APP_TAGLINE;
$shareDescription = 'Premium verified TikTok creator leads for LIVE Backstage. Daily fresh leads with profile photos, usernames, regions and invitation types. Start free.';
$shareUrl = rtrim(APP_URL, '/') . '/';
$shareImage = rtrim(APP_URL, '/') . '/assets/dashboard_1.png';

// Live stats from DB
try {
    $db         = getDB();
    $totalLeads = (int)$db->query('SELECT COUNT(*) FROM creators')->fetchColumn();
    $totalRegions = (int)$db->query('SELECT COUNT(DISTINCT backstage_region) FROM creators WHERE backstage_region IS NOT NULL AND backstage_region != ""')->fetchColumn();
    $pricingPackages = array_values(array_filter(
        getPackages($db),
        static function (array $pkg): bool {
            return trim((string)($pkg['name'] ?? '')) !== 'SocialFlame - Free Package';
        }
    ));
    // Grab up to 5 real creators with avatars for the hero preview
    $heroCreators = $db->query(
        'SELECT display_name, username, backstage_region, invitation_type, avatar
         FROM creators
         WHERE assigned_customer IS NULL
         ORDER BY RAND()
         LIMIT 5'
    )->fetchAll();
} catch (Exception $e) {
    $totalLeads   = 500;
    $totalRegions = 20;
    $heroCreators = [];
    $pricingPackages = [];
}

// Pad with mock data if not enough real rows
$mockFallback = [
    ['display_name' => 'Sarah Chen',     'username' => 'sarahcreates',  'backstage_region' => 'gb', 'invitation_type' => 2, 'avatar' => null],
    ['display_name' => 'Marcus Johnson', 'username' => 'marcusj',       'backstage_region' => 'us', 'invitation_type' => 1, 'avatar' => null],
    ['display_name' => 'Emma Williams',  'username' => 'emmaw_tok',      'backstage_region' => 'au', 'invitation_type' => 3, 'avatar' => null],
    ['display_name' => 'Kai Yamamoto',   'username' => 'kaiyama',        'backstage_region' => 'jp', 'invitation_type' => 1, 'avatar' => null],
    ['display_name' => 'Priya Sharma',   'username' => 'priyacreates',   'backstage_region' => 'in', 'invitation_type' => 2, 'avatar' => null],
];
while (count($heroCreators) < 5) {
    $heroCreators[] = $mockFallback[count($heroCreators)];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> – <?= e(APP_TAGLINE) ?></title>
    <meta name="description" content="Premium verified TikTok creator leads for LIVE Backstage. Daily fresh leads with profile photos, usernames, regions and invitation types. Start free.">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e(APP_NAME) ?>">
    <meta property="og:title" content="<?= e($shareTitle) ?>">
    <meta property="og:description" content="<?= e($shareDescription) ?>">
    <meta property="og:url" content="<?= e($shareUrl) ?>">
    <meta property="og:image" content="<?= e($shareImage) ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="CreatorNetworkLeads dashboard preview showing assigned TikTok creator leads">
    <meta property="og:locale" content="en_GB">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($shareTitle) ?>">
    <meta name="twitter:description" content="<?= e($shareDescription) ?>">
    <meta name="twitter:image" content="<?= e($shareImage) ?>">
    <meta name="twitter:image:alt" content="CreatorNetworkLeads dashboard preview showing assigned TikTok creator leads">
    <link rel="apple-touch-icon" sizes="57x57" href="assets/favicon/apple-icon-57x57.png">
    <link rel="apple-touch-icon" sizes="60x60" href="assets/favicon/apple-icon-60x60.png">
    <link rel="apple-touch-icon" sizes="72x72" href="assets/favicon/apple-icon-72x72.png">
    <link rel="apple-touch-icon" sizes="76x76" href="assets/favicon/apple-icon-76x76.png">
    <link rel="apple-touch-icon" sizes="114x114" href="assets/favicon/apple-icon-114x114.png">
    <link rel="apple-touch-icon" sizes="120x120" href="assets/favicon/apple-icon-120x120.png">
    <link rel="apple-touch-icon" sizes="144x144" href="assets/favicon/apple-icon-144x144.png">
    <link rel="apple-touch-icon" sizes="152x152" href="assets/favicon/apple-icon-152x152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/favicon/apple-icon-180x180.png">
    <link rel="icon" type="image/png" sizes="192x192" href="assets/favicon/android-icon-192x192.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="96x96" href="assets/favicon/favicon-96x96.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/favicon/favicon-16x16.png">
    <link rel="manifest" href="assets/favicon/manifest.json">
    <meta name="msapplication-TileColor" content="#ffffff">
    <meta name="msapplication-TileImage" content="assets/favicon/ms-icon-144x144.png">
    <meta name="theme-color" content="#ffffff">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <script>(function(){var t=localStorage.getItem('cnl-theme')||'light';document.documentElement.setAttribute('data-bs-theme',t);})();</script>
</head>
<body>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="/">
            <img src="/assets/logo/logo.png" alt="<?= e(APP_NAME) ?>" class="cnl-logo-img">
            <span class="fw-bold"><?= e(APP_NAME) ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                <li class="nav-item"><a class="nav-link" href="#how-it-works">How It Works</a></li>
                <li class="nav-item"><a class="nav-link" href="#leads-preview">Lead Profiles</a></li>
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
                    <a href="/register.php" class="btn btn-danger btn-sm">Get Started Free</a>
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
                    <i class="bi bi-lightning-charge-fill me-1"></i> TikTok LIVE Backstage Leads
                </div>
                <h1 class="hero-title mb-4">
                    Fresh Creator Leads<br>
                    <span class="accent">Delivered Daily</span>
                </h1>
                <p class="hero-subtitle mb-4">
                    Get verified TikTok creator profiles – complete with photos, usernames, regions and invitation types –
                    sent to your dashboard every single day. No cold searching, no guesswork.
                </p>
                <div class="d-flex flex-wrap gap-3 mb-4">
                    <a href="/register.php" class="btn btn-danger btn-lg px-4">
                        <i class="bi bi-rocket-takeoff me-2"></i>Start Free
                    </a>
                    <a href="#pricing" class="btn btn-outline-light btn-lg px-4">
                        See Pricing
                    </a>
                </div>
                <div class="d-flex flex-wrap gap-4 text-muted">
                    <div><i class="bi bi-check-circle-fill text-success me-1"></i>Free plan available</div>
                    <div><i class="bi bi-check-circle-fill text-success me-1"></i>No setup fee</div>
                    <div><i class="bi bi-check-circle-fill text-success me-1"></i>Cancel anytime</div>
                </div>
            </div>

            <!-- Hero mockup: live lead dashboard preview -->
            <div class="col-lg-6">
                <div class="hero-mockup">
                    <!-- Mockup header bar -->
                    <div class="d-flex align-items-center gap-2 mb-3 pb-3 border-bottom border-secondary">
                        <div class="rounded-circle bg-danger d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0" style="width:32px;height:32px;font-size:.8rem">
                            <i class="bi bi-speedometer2"></i>
                        </div>
                        <div>
                            <div class="fw-semibold text-white small">My Creator Leads</div>
                            <div class="text-muted" style="font-size:.72rem">Today's fresh leads assigned to your account</div>
                        </div>
                        <span class="badge bg-success ms-auto"><i class="bi bi-circle-fill me-1" style="font-size:.5rem"></i>Live</span>
                    </div>

                    <!-- Mock lead rows -->
                    <?php
                    $avatarColors = ['#ff0050','#6366f1','#059669','#d97706','#0284c7','#7c3aed'];
                    foreach (array_slice($heroCreators, 0, 5) as $i => $c):
                        $name   = $c['display_name'] ?: $c['username'] ?: 'Creator';
                        $uname  = $c['username'] ? '@' . $c['username'] : '—';
                        $region = strtoupper($c['backstage_region'] ?? '??');
                        $typeId = (int)($c['invitation_type'] ?? 1);
                        $typeName  = invitationTypeName($typeId ?: null);
                        $typeColor = ['1'=>'primary','2'=>'warning','3'=>'success'][$typeId] ?? 'secondary';
                        $color  = $avatarColors[$i % count($avatarColors)];
                        $initials = strtoupper(mb_substr(trim($name), 0, 1));
                    ?>
                    <div class="d-flex align-items-center gap-3 py-2 <?= $i < 4 ? 'border-bottom border-secondary' : '' ?>">
                        <?php if (!empty($c['avatar'])): ?>
                            <img src="<?= e($c['avatar']) ?>" alt="<?= e($name) ?>"
                                 class="rounded-circle flex-shrink-0"
                                 width="40" height="40" style="object-fit:cover"
                                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                            <span class="rounded-circle d-none align-items-center justify-content-center text-white fw-bold flex-shrink-0"
                                  style="width:40px;height:40px;background:<?= $color ?>;font-size:.9rem"><?= $initials ?></span>
                        <?php else: ?>
                            <span class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0"
                                  style="width:40px;height:40px;background:<?= $color ?>;font-size:.9rem"><?= $initials ?></span>
                        <?php endif; ?>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="fw-semibold text-white small text-truncate"><?= e($name) ?></div>
                            <div class="text-muted text-truncate" style="font-size:.72rem"><?= e($uname) ?></div>
                        </div>
                        <span class="badge bg-<?= $typeColor ?> flex-shrink-0"><?= e($typeName) ?></span>
                        <span class="badge bg-secondary flex-shrink-0"><?= $region ?></span>
                        <span class="badge bg-outline border border-secondary text-muted flex-shrink-0" style="font-size:.65rem">New</span>
                    </div>
                    <?php endforeach; ?>

                    <div class="text-center text-muted pt-3" style="font-size:.78rem">
                        <i class="bi bi-people-fill me-1 text-danger"></i>
                        <?= number_format($totalLeads) ?>+ verified creator profiles in our database
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
                <div class="stat-label">Creator Profiles</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-number"><?= $totalRegions ?>+</div>
                <div class="stat-label">Regions Covered</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-number">3</div>
                <div class="stat-label">Invitation Types</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-number">Daily</div>
                <div class="stat-label">Fresh Lead Delivery</div>
            </div>
        </div>
    </div>
</div>

<!-- ── Dashboard Screenshot ──────────────────────────────────── -->
<section class="py-5" style="padding:5rem 0!important;background:linear-gradient(180deg,#0d1117 0%,#0a0a0a 100%)">
    <div class="container">
        <div class="text-center mb-5">
            <div class="hero-badge mb-3 mx-auto" style="display:inline-block">
                <i class="bi bi-display me-1"></i> Real Dashboard
            </div>
            <h2 class="fw-bold fs-1 text-white">Your Leads, All in One Place</h2>
            <p class="fs-5 mb-0" style="color:rgba(255,255,255,.55)">A clean, simple dashboard to manage every creator lead you're assigned — track status, export data, and scale your outreach.</p>
        </div>
        <div class="text-center">
            <img src="/assets/dashboard_1.png"
                 alt="Creator Network Leads Dashboard"
                 class="img-fluid rounded-3 shadow"
                 style="max-width:100%;border:1px solid rgba(255,255,255,.08);border-radius:16px!important">
        </div>
        <div class="row g-3 justify-content-center mt-4">
            <div class="col-auto">
                <div class="d-flex align-items-center gap-2 text-white-50 small"><i class="bi bi-check-circle-fill text-success"></i> Track every lead's status</div>
            </div>
            <div class="col-auto">
                <div class="d-flex align-items-center gap-2 text-white-50 small"><i class="bi bi-check-circle-fill text-success"></i> Export to CSV anytime</div>
            </div>
            <div class="col-auto">
                <div class="d-flex align-items-center gap-2 text-white-50 small"><i class="bi bi-check-circle-fill text-success"></i> Fresh leads delivered daily</div>
            </div>
        </div>
    </div>
</section>

<!-- ── Features ───────────────────────────────────────────────── -->
<section id="features" class="py-5" style="padding:5rem 0!important">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold fs-1">Everything You Need to Scale</h2>
            <p class="text-muted fs-5">Real creator data. Daily delivery. One clean dashboard.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-person-bounding-box"></i></div>
                    <h5 class="fw-bold">Full Creator Profiles</h5>
                    <p class="text-muted mb-0">Every lead includes a profile photo, display name, TikTok username and region — everything you need to invite them on Backstage instantly.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-tags-fill"></i></div>
                    <h5 class="fw-bold">Invitation Types</h5>
                    <p class="text-muted mb-0">Leads are tagged as <strong>Regular</strong>, <strong>Premium</strong> or <strong>Elite</strong> — so you always know the tier you're inviting and can prioritise your outreach.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-geo-alt-fill"></i></div>
                    <h5 class="fw-bold">Regional Targeting</h5>
                    <p class="text-muted mb-0">Filter by region – UK, US, AU, and <?= $totalRegions ?>+ more countries. Our admin team assigns leads matched to your target markets.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-calendar2-check-fill"></i></div>
                    <h5 class="fw-bold">Daily Lead Delivery</h5>
                    <p class="text-muted mb-0">New leads arrive in your dashboard every day. Your plan determines how many — from 1 per day on the free tier up to 100 per day on Pro.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-check2-all"></i></div>
                    <h5 class="fw-bold">Status Tracking</h5>
                    <p class="text-muted mb-0">Mark each lead as <strong>Invited</strong>, <strong>Accepted</strong> or <strong>Declined</strong> right in your dashboard. Stay on top of your entire pipeline.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-person-fill-lock"></i></div>
                    <h5 class="fw-bold">Exclusive Assignment</h5>
                    <p class="text-muted mb-0">Leads are assigned exclusively to your account – no sharing, no competition, no duplicates across customers.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── Lead Profile Showcase ─────────────────────────────────── -->
<section id="leads-preview" class="py-5 bg-dark text-white" style="padding:5rem 0!important">
    <div class="container">
        <div class="text-center mb-5">
            <div class="hero-badge mb-3 mx-auto" style="display:inline-block">
                <i class="bi bi-eye me-1"></i> What You'll See in Your Dashboard
            </div>
            <h2 class="fw-bold fs-1">Real Lead Profiles, Ready to Invite</h2>
            <p class="text-muted fs-5">Each lead comes with a full creator profile and your personal tracking status</p>
        </div>

        <!-- Sample lead table -->
        <div class="bg-dark border border-secondary rounded-3 overflow-hidden mb-5">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0" style="font-size:.875rem">
                    <thead style="background:#0d1117">
                        <tr>
                            <th class="py-3 px-3" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.07em;color:#64748b;border-bottom:1px solid #30363d">Creator</th>
                            <th class="py-3 px-3" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.07em;color:#64748b;border-bottom:1px solid #30363d">Region</th>
                            <th class="py-3 px-3" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.07em;color:#64748b;border-bottom:1px solid #30363d">Invitation Type</th>
                            <th class="py-3 px-3" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.07em;color:#64748b;border-bottom:1px solid #30363d">Your Status</th>
                            <th class="py-3 px-3" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.07em;color:#64748b;border-bottom:1px solid #30363d">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $showcaseLeads = [
                        ['name'=>'Sophie Anderson',  'user'=>'sophieanderson',  'region'=>'GB','type'=>2,'status'=>'accepted', 'avatar'=>null, 'color'=>'#ff0050'],
                        ['name'=>'James Mitchell',   'user'=>'jamesmitch',       'region'=>'US','type'=>1,'status'=>'invited',  'avatar'=>null, 'color'=>'#6366f1'],
                        ['name'=>'Yuki Tanaka',      'user'=>'yukiofficial',     'region'=>'JP','type'=>3,'status'=>'new',      'avatar'=>null, 'color'=>'#059669'],
                        ['name'=>'Olivia Nguyen',    'user'=>'olivia_ng',        'region'=>'AU','type'=>2,'status'=>'declined', 'avatar'=>null, 'color'=>'#d97706'],
                    ];
                    $statusColors = ['new'=>'secondary','invited'=>'info','accepted'=>'success','declined'=>'danger'];
                    $typeColors   = ['1'=>'primary','2'=>'warning','3'=>'success'];
                    $typeNames    = ['1'=>'Regular','2'=>'Premium','3'=>'Elite'];
                    $regionNames  = ['GB'=>'United Kingdom','US'=>'United States','JP'=>'Japan','AU'=>'Australia'];
                    foreach ($showcaseLeads as $sl):
                        $sc = $statusColors[$sl['status']] ?? 'secondary';
                        $tc = $typeColors[$sl['type']] ?? 'secondary';
                        $tn = $typeNames[$sl['type']] ?? 'Regular';
                    ?>
                    <tr>
                        <td class="px-3 py-3" style="border-color:#30363d">
                            <div class="d-flex align-items-center gap-2">
                                <span class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0"
                                      style="width:38px;height:38px;background:<?= $sl['color'] ?>;font-size:.9rem">
                                    <?= strtoupper(substr($sl['name'],0,1)) ?>
                                </span>
                                <div>
                                    <div class="fw-semibold small"><?= e($sl['name']) ?></div>
                                    <div class="text-muted" style="font-size:.72rem">@<?= e($sl['user']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-3" style="border-color:#30363d">
                            <div class="small fw-semibold"><?= e($regionNames[$sl['region']] ?? $sl['region']) ?></div>
                            <div class="text-muted" style="font-size:.72rem"><?= $sl['region'] ?></div>
                        </td>
                        <td class="px-3 py-3" style="border-color:#30363d">
                            <span class="badge bg-<?= $tc ?>"><?= $tn ?></span>
                        </td>
                        <td class="px-3 py-3" style="border-color:#30363d">
                            <span class="badge bg-<?= $sc ?>"><?= ucfirst($sl['status']) ?></span>
                        </td>
                        <td class="px-3 py-3" style="border-color:#30363d">
                            <div class="d-flex gap-1">
                                <span class="btn btn-sm btn-outline-info disabled" style="font-size:.72rem;padding:.2rem .5rem">Invited</span>
                                <span class="btn btn-sm btn-outline-success disabled" style="font-size:.72rem;padding:.2rem .5rem">Accepted</span>
                                <span class="btn btn-sm btn-outline-danger disabled" style="font-size:.72rem;padding:.2rem .5rem">Declined</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Invitation type explainer -->
        <div class="row g-4 justify-content-center">
            <div class="col-12">
                <h5 class="text-center fw-bold mb-4">Understanding Invitation Types</h5>
            </div>
            <div class="col-md-4">
                <div class="bg-dark border border-secondary rounded-3 p-4 h-100" style="border-color:#30363d!important">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="badge bg-primary fs-6 px-3 py-2">Regular</span>
                    </div>
                    <p class="text-muted small mb-0">Standard tier creators eligible for TikTok LIVE Backstage. Great for building volume in your programme at scale.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="bg-dark border rounded-3 p-4 h-100" style="border-color:#ca8a04!important">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="badge bg-warning text-dark fs-6 px-3 py-2">Premium</span>
                    </div>
                    <p class="text-muted small mb-0">Higher-tier creators with stronger engagement and reach. Prioritise these for faster growth and better acceptance rates.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="bg-dark border rounded-3 p-4 h-100" style="border-color:#16a34a!important">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="badge bg-success fs-6 px-3 py-2">Elite</span>
                    </div>
                    <p class="text-muted small mb-0">Top-performing creators – high follower counts, verified presence and proven LIVE track record. Maximum value per invitation.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── How It Works ───────────────────────────────────────────── -->
<section id="how-it-works" class="py-5" style="padding:5rem 0!important">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold fs-1">How It Works</h2>
            <p class="text-muted fs-5">Up and running in minutes</p>
        </div>
        <div class="row g-4 justify-content-center">
            <div class="col-md-6 col-lg-3 text-center">
                <div class="step-number">1</div>
                <h6 class="fw-bold">Create Account</h6>
                <p class="text-muted small">Sign up for free. Our team reviews and activates your account – then you're ready to receive leads.</p>
            </div>
            <div class="col-md-6 col-lg-3 text-center">
                <div class="step-number">2</div>
                <h6 class="fw-bold">Choose a Plan</h6>
                <p class="text-muted small">Start free with 1 lead per day or upgrade to receive up to 100 fresh leads delivered to your dashboard daily.</p>
            </div>
            <div class="col-md-6 col-lg-3 text-center">
                <div class="step-number">3</div>
                <h6 class="fw-bold">Receive Daily Leads</h6>
                <p class="text-muted small">Every day, new creator profiles land in your dashboard – with photos, usernames, regions and invitation type tags.</p>
            </div>
            <div class="col-md-6 col-lg-3 text-center">
                <div class="step-number">4</div>
                <h6 class="fw-bold">Track & Invite</h6>
                <p class="text-muted small">Use the creator details to send Backstage invitations, then mark each lead as Invited, Accepted or Declined in one click.</p>
            </div>
        </div>
    </div>
</section>

<!-- ── Pricing ────────────────────────────────────────────────── -->
<section id="pricing" class="py-5" style="padding:5rem 0!important;background:var(--bs-light)">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold fs-1">Simple, Transparent Pricing</h2>
            <p class="text-muted fs-5">Pick a plan and start receiving fresh creator leads every day</p>
        </div>
        <div class="row g-4 justify-content-center align-items-stretch">
            <?php
            $featuredPackageId = 0;
            if (!empty($pricingPackages)) {
                foreach ($pricingPackages as $pkg) {
                    if ((float)($pkg['price_per_month'] ?? 0) > 0) {
                        $featuredPackageId = (int)$pkg['id'];
                        break;
                    }
                }
            }
            ?>

            <?php foreach ($pricingPackages as $package): ?>
                <?php
                $price = (float)($package['price_per_month'] ?? 0);
                $isFree = $price <= 0;
                $isFeatured = !$isFree && (int)$package['id'] === $featuredPackageId;
                ?>
                <div class="col-md-6 col-lg-3">
                    <div class="card pricing-card border <?= $isFeatured ? 'featured' : '' ?> h-100">
                        <div class="card-body p-4 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="fw-bold"><?= e((string)$package['name']) ?></h5>
                                    <p class="text-muted small mb-0"><?= e((string)($package['description'] ?? ($isFree ? 'Try it out with no commitment' : 'Scale your creator pipeline'))) ?></p>
                                </div>
                                <?php if ($isFeatured): ?>
                                    <span class="badge bg-danger">Most Popular</span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex align-items-end gap-1 mb-1">
                                <span class="fw-black" style="font-size:2.6rem;line-height:1"><?= $isFree ? '&pound;0' : '&pound;' . number_format($price, 2) ?></span>
                            </div>
                            <div class="text-muted small mb-4"><?= $isFree ? 'forever free' : 'per month' ?></div>
                            <ul class="list-unstyled mb-4 text-muted small flex-grow-1">
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i><strong><?= (int)$package['leads_per_day'] ?> leads</strong> per day</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Full creator profiles</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>All invitation types</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Status tracking</li>
                                <?php if (!$isFree): ?>
                                    <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>PayPal subscription billing</li>
                                <?php endif; ?>
                            </ul>
                            <a href="/register.php" class="btn <?= $isFeatured ? 'btn-danger' : 'btn-outline-danger' ?> w-100 mt-auto">
                                <?= $isFree ? 'Get Started Free' : 'Get Started' ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($pricingPackages)): ?>
                <div class="col-12 text-center text-muted">Pricing is being updated. Please check back shortly.</div>
            <?php endif; ?>

        </div>

        <!-- Leads per day comparison strip -->
        <div class="mt-5 text-center">
            <p class="text-muted small mb-3">Leads delivered per day across plans</p>
            <div class="d-flex justify-content-center flex-wrap gap-3">
                <?php foreach ($pricingPackages as $package): ?>
                    <div class="px-4 py-2 rounded-3 border" style="min-width:120px">
                        <div class="fw-bold fs-5"><?= (int)$package['leads_per_day'] ?>/day</div>
                        <div class="text-muted small"><?= e((string)$package['name']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- ── CTA Banner ─────────────────────────────────────────────── -->
<section class="py-5" style="background:linear-gradient(135deg,#0a0a0a 0%,#1a0010 100%)">
    <div class="container text-center text-white py-4">
        <h2 class="fw-bold fs-1 mb-3">Start Receiving Creator Leads Today</h2>
        <p class="text-muted fs-5 mb-4">Free plan available – no card required. Upgrade whenever you're ready.</p>
        <a href="/register.php" class="btn btn-danger btn-lg px-5">
            <i class="bi bi-rocket-takeoff me-2"></i>Create Your Free Account
        </a>
    </div>
</section>

<!-- Footer -->
<footer class="bg-dark text-light py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <p class="small mb-1">© <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.</p>
                <p class="small">Not affiliated with TikTok or ByteDance Ltd.</p>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>
