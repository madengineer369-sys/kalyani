<?php
/**
 * SecureShop - Shared HTML Footer Partial
 */
if (!defined('ECOMMERCE_APP')) { http_response_code(403); exit('Forbidden'); }
?>
</main><!-- /main-content -->

<!-- ===== FOOTER ===== -->
<footer class="site-footer mt-auto">
    <div class="footer-top">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h5 class="footer-brand">
                        <i class="bi bi-shield-lock-fill me-2 text-accent"></i><?= APP_NAME ?>
                    </h5>
                    <p class="text-muted small"><?= e(APP_TAGLINE) ?>. Built with security-first principles.</p>
                    <div class="d-flex gap-3 mt-3">
                        <span class="footer-badge"><i class="bi bi-lock-fill me-1"></i>SSL Secured</span>
                        <span class="footer-badge"><i class="bi bi-shield-check me-1"></i>OWASP Protected</span>
                    </div>
                </div>
                <div class="col-lg-2 col-6">
                    <h6 class="footer-heading">Shop</h6>
                    <ul class="list-unstyled footer-links">
                        <li><a href="<?= e(url('index.php')) ?>">Products</a></li>
                        <li><a href="<?= e(url('search.php')) ?>">Search</a></li>
                        <li><a href="<?= e(url('cart.php')) ?>">Cart</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-6">
                    <h6 class="footer-heading">Account</h6>
                    <ul class="list-unstyled footer-links">
                        <?php if (is_logged_in()): ?>
                        <li><a href="<?= e(url('dashboard.php')) ?>">Dashboard</a></li>
                        <li><a href="<?= e(url('order-history.php')) ?>">Orders</a></li>
                        <li><a href="<?= e(url('profile.php')) ?>">Profile</a></li>
                        <li><a href="<?= e(url('logout.php')) ?>">Logout</a></li>
                        <?php else: ?>
                        <li><a href="<?= e(url('login.php')) ?>">Login</a></li>
                        <li><a href="<?= e(url('register.php')) ?>">Register</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="col-lg-4">
                    <h6 class="footer-heading">Security Features</h6>
                    <ul class="list-unstyled footer-security-list">
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i>Prepared Statements</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i>CSRF Protection</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i>bcrypt Password Hashing</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i>Rate Limiting & Lockout</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i>Comprehensive Audit Logs</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-center">
                <span class="text-muted small">&copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.</span>
                <span class="text-muted small">v<?= APP_VERSION ?> &bull; <?= APP_ENV === 'development' ? '<span class="text-warning">DEV MODE</span>' : 'Production' ?></span>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc4s9bIOgUxi8T/jzmKfPMNTHhKFm5wT8BFMlNEomEEE" crossorigin="anonymous"></script>
<!-- App JS -->
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>
