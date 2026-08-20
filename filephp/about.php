<?php
/**
 * SecureShop - About Us Page
 */
define('ECOMMERCE_APP', true);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/logging.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/cart_helper.php';

send_security_headers();
session_start_secure();

$page_title = 'About Us';
$page_desc  = 'Learn about Black Stone - Premium E-Commerce & Interactive Tag Experience.';

log_access();
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5 navbar-spacer">
    <div class="row align-items-center g-5 mb-5">
        <div class="col-lg-6">
            <span class="badge bg-accent mb-2 px-3 py-1">OUR STORY</span>
            <h1 class="display-5 fw-bold mb-3">Redefining Premium Apparel & <span class="text-accent">Security-First E-Commerce</span></h1>
            <p class="text-muted lead">Black Stone merges high-quality streetwear with interactive mystery box unboxings, creating an unmatched online shopping experience.</p>
            <p class="text-muted">Every item in our collection is crafted with attention to detail, using pre-shrunk premium cotton, modern fits, and embedded tag codes that unlock mystery rewards right here on our site.</p>
        </div>
        <div class="col-lg-6">
            <div class="card p-5 border-secondary bg-dark-subtle rounded-4 text-center shadow-accent">
                <i class="bi bi-shield-lock-fill display-1 text-accent mb-3"></i>
                <h3 class="fw-bold text-light">Built on Trust & Security</h3>
                <p class="text-muted small">Our custom PHP platform implements OWASP Top 10 security standards including prepared statements, anti-CSRF protections, rate limiting, and password hashing.</p>
            </div>
        </div>
    </div>

    <!-- Values Grid -->
    <div class="row g-4 mt-4">
        <div class="col-md-4">
            <div class="card p-4 border-secondary bg-dark-subtle rounded-4 h-100">
                <div class="avatar-lg bg-accent text-light mb-3"><i class="bi bi-gem fs-2"></i></div>
                <h5 class="fw-bold">Premium Quality</h5>
                <p class="text-muted small mb-0">Crafted from top-tier materials designed for comfort, durability, and bold aesthetic appeal.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-4 border-secondary bg-dark-subtle rounded-4 h-100">
                <div class="avatar-lg bg-warning text-dark mb-3"><i class="bi bi-box-seam-fill fs-2"></i></div>
                <h5 class="fw-bold">Code Zone Experience</h5>
                <p class="text-muted small mb-0">Every t-shirt tag contains a unique code. Enter it in the Code Zone to reveal mystery boxes and prizes.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-4 border-secondary bg-dark-subtle rounded-4 h-100">
                <div class="avatar-lg bg-success text-light mb-3"><i class="bi bi-truck fs-2"></i></div>
                <h5 class="fw-bold">Fast Delivery</h5>
                <p class="text-muted small mb-0">Orders are processed quickly with real-time status tracking and hassle-free returns.</p>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
