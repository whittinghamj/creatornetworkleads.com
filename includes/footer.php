    <!-- Footer -->
    <footer class="bg-dark text-light py-5 mt-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <svg width="28" height="28" viewBox="0 0 28 28" fill="none">
                            <rect width="28" height="28" rx="6" fill="#ff0050"/>
                            <path d="M18 8h-3v8.5a2.5 2.5 0 1 1-2.5-2.5c.17 0 .34.02.5.05V11a6 6 0 1 0 5 5.9V12h2.5A2.5 2.5 0 0 1 18 9.5V8z" fill="#fff"/>
                        </svg>
                        <span class="fw-bold fs-5"><?= e(APP_NAME) ?></span>
                    </div>
                    <p class="text-muted small">Premium TikTok creator leads for LIVE Backstage. Helping agencies and businesses connect with the right creators.</p>
                </div>
                <div class="col-lg-2 col-6">
                    <h6 class="fw-semibold mb-3">Platform</h6>
                    <ul class="list-unstyled small text-muted">
                        <li class="mb-1"><a href="/#features" class="text-muted text-decoration-none">Features</a></li>
                        <li class="mb-1"><a href="/#how-it-works" class="text-muted text-decoration-none">How It Works</a></li>
                        <li class="mb-1"><a href="/#pricing" class="text-muted text-decoration-none">Pricing</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-6">
                    <h6 class="fw-semibold mb-3">Account</h6>
                    <ul class="list-unstyled small text-muted">
                        <li class="mb-1"><a href="/login.php" class="text-muted text-decoration-none">Log In</a></li>
                        <li class="mb-1"><a href="/register.php" class="text-muted text-decoration-none">Register</a></li>
                        <?php if (isLoggedIn()): ?>
                        <li class="mb-1"><a href="/dashboard.php" class="text-muted text-decoration-none">Dashboard</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="col-lg-4">
                    <h6 class="fw-semibold mb-3">About</h6>
                    <p class="text-muted small">© <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.</p>
                    <p class="text-muted small">Not affiliated with TikTok or ByteDance Ltd.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Custom JS -->
    <script src="/assets/js/app.js"></script>
</body>
</html>
