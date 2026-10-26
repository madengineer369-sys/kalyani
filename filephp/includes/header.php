<?php
/**
 * SecureShop - Shared HTML Header Partial
 *
 * Usage: include at top of every page AFTER setting $page_title, $page_description, $extra_css
 * Security headers and session are already started before this is included.
 */
if (!defined('ECOMMERCE_APP')) { http_response_code(403); exit('Forbidden'); }

$nonce       = generate_csp_nonce();
$page_title  = isset($page_title)  ? e($page_title) . ' — ' . APP_NAME : APP_NAME;
$page_desc   = isset($page_desc)   ? e($page_desc)  : e(APP_TAGLINE);
$cart_count  = is_logged_in() ? cart_count() : 0;
$current_usr = is_logged_in() ? get_auth_user() : null;
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?= $page_title ?></title>
    <meta name="description" content="<?= $page_desc ?>">
    <meta name="robots" content="noindex, nofollow"><!-- Remove in production for public pages -->

    <!-- Bootstrap 5.3 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
    <?php if (isset($extra_css)) echo $extra_css; ?>
</head>
<body>

<!-- Session Timeout Data -->
<div id="session-data"
     data-timeout="<?= SESSION_LIFETIME ?>"
     data-warning="<?= SESSION_WARNING_TIME ?>"
     data-remaining="<?= session_remaining_seconds() ?>"
     data-logged-in="<?= is_logged_in() ? '1' : '0' ?>"
     style="display:none"></div>

<!-- ===== NAVBAR ===== -->
<nav class="navbar navbar-expand-lg navbar-dark fixed-top" id="mainNav">
    <div class="container-fluid px-4">

        <!-- Brand -->
        <a class="navbar-brand fw-bold" href="<?= e(url('index.php')) ?>">
            <i class="bi bi-gem me-2 text-accent"></i>BLACK <span class="gold-text ms-1">STONE</span>
        </a>

        <!-- Mobile Toggle -->
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navContent"
                aria-controls="navContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navContent">

            <!-- Primary Clothing Collections Links -->
            <ul class="navbar-nav me-auto ms-lg-3 mb-2 mb-lg-0 gap-1">
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>" href="<?= e(url('index.php')) ?>">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 't-shirts.php' ? 'active' : '' ?>" href="<?= e(url('t-shirts.php')) ?>">T-Shirts</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'hoodies.php' ? 'active' : '' ?>" href="<?= e(url('hoodies.php')) ?>">Hoodies</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'trousers.php' ? 'active' : '' ?>" href="<?= e(url('trousers.php')) ?>">Trousers</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'shorts.php' ? 'active' : '' ?>" href="<?= e(url('shorts.php')) ?>">Shorts</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'mystery-box.php' ? 'active' : '' ?>" href="<?= e(url('mystery-box.php')) ?>">
                        <i class="bi bi-gift-fill me-1 text-warning"></i>Mystery Box
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'code-zone.php' ? 'active' : '' ?>" href="<?= e(url('code-zone.php')) ?>">
                        <i class="bi bi-ticket-perforated me-1 text-info"></i>Code Zone
                    </a>
                </li>
            </ul>

            <!-- Search Bar -->
            <form class="d-flex me-3 nav-search my-2 my-lg-0" action="<?= e(url('search.php')) ?>" method="GET" role="search">
                <div class="input-group">
                    <input class="form-control search-input" type="search" name="q"
                           placeholder="Search apparel…"
                           value="<?= e($_GET['q'] ?? '') ?>"
                           maxlength="200" autocomplete="off"
                           aria-label="Search products">
                    <button class="btn btn-accent" type="submit" aria-label="Search">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>

            <!-- Right Nav -->
            <ul class="navbar-nav align-items-center gap-2">

                <!-- Cart -->
                <li class="nav-item">
                    <a class="nav-link position-relative cart-link" href="<?= e(url('cart.php')) ?>">
                        <i class="bi bi-bag-fill fs-5 text-accent"></i>
                        <?php if ($cart_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-accent cart-badge">
                            <?= $cart_count < 100 ? $cart_count : '99+' ?>
                        </span>
                        <?php endif; ?>
                    </a>
                </li>

                <?php if (is_logged_in()): ?>

                <!-- User Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown">
                        <div class="avatar-sm">
                            <?= strtoupper(substr($current_usr['first_name'] ?? 'U', 0, 1)) ?>
                        </div>
                        <span class="d-none d-lg-inline"><?= e($current_usr['first_name'] ?? 'User') ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
                        <li class="px-3 py-2 border-bottom border-secondary">
                            <div class="fw-semibold small"><?= e(($current_usr['first_name'] ?? '') . ' ' . ($current_usr['last_name'] ?? '')) ?></div>
                            <div class="text-muted small"><?= e(mask_email($current_usr['email'] ?? '')) ?></div>
                            <span class="badge badge-role bg-<?= role_color(current_role()) ?> mt-1"><?= ucfirst(current_role()) ?></span>
                        </li>
                        <li><a class="dropdown-item" href="<?= e(url('dashboard.php')) ?>">
                            <i class="bi bi-speedometer2 me-2"></i>Dashboard
                        </a></li>
                        <li><a class="dropdown-item" href="<?= e(url('order-history.php')) ?>">
                            <i class="bi bi-bag-check me-2"></i>My Orders
                        </a></li>
                        <li><a class="dropdown-item" href="<?= e(url('profile.php')) ?>">
                            <i class="bi bi-person-gear me-2"></i>Profile
                        </a></li>
                        <?php if (is_admin()): ?>
                        <li><hr class="dropdown-divider border-secondary"></li>
                        <li><a class="dropdown-item text-warning" href="<?= e(url('admin/index.php')) ?>">
                            <i class="bi bi-shield-fill-check me-2"></i>Admin Panel
                        </a></li>
                        <?php elseif (is_shopkeeper()): ?>
                        <li><hr class="dropdown-divider border-secondary"></li>
                        <li><a class="dropdown-item text-info" href="<?= e(url('shopkeeper/index.php')) ?>">
                            <i class="bi bi-shop me-2"></i>Shopkeeper Panel
                        </a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider border-secondary"></li>
                        <li><a class="dropdown-item text-danger" href="<?= e(url('logout.php')) ?>">
                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                        </a></li>
                    </ul>
                </li>

                <?php else: ?>

                <!-- Guest Links -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= e(url('login.php')) ?>">
                        <i class="bi bi-person me-1"></i>Login
                    </a>
                </li>
                <li class="nav-item">
                    <a class="btn btn-accent btn-sm ms-2" href="<?= e(url('register.php')) ?>">
                        <i class="bi bi-person-plus me-1"></i>Register
                    </a>
                </li>

                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Navbar Spacer -->
<div class="navbar-spacer"></div>

<!-- ===== FLASH MESSAGES ===== -->
<div class="container-fluid px-4 pt-3" id="flash-container">
    <?php render_flash(); ?>
</div>

<!-- ===== SESSION TIMEOUT MODAL ===== -->
<div class="modal fade" id="sessionTimeoutModal" tabindex="-1" aria-labelledby="sessionModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border border-warning">
            <div class="modal-header border-warning">
                <h5 class="modal-title text-warning" id="sessionModalLabel">
                    <i class="bi bi-clock-history me-2"></i>Session Expiring Soon
                </h5>
            </div>
            <div class="modal-body">
                <p>Your session will expire in <strong id="countdown" class="text-warning">5:00</strong>.</p>
                <p class="text-muted small">Click "Stay Logged In" to continue your session.</p>
            </div>
            <div class="modal-footer border-warning">
                <a href="<?= e(url('logout.php')) ?>" class="btn btn-outline-danger">
                    <i class="bi bi-box-arrow-right me-1"></i>Logout Now
                </a>
                <button type="button" class="btn btn-warning" id="extendSession">
                    <i class="bi bi-arrow-repeat me-1"></i>Stay Logged In
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ===== CONFIRM MODAL ===== -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border border-secondary">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmModalLabel">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="confirmModalBody">Are you sure?</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmModalOk">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer" style="z-index:1090"></div>

<!-- Main Content Start -->
<main class="main-content">
<?php

/**
 * Helper: return Bootstrap color for role badge.
 */
function role_color(string $role): string {
    return match($role) { 'admin' => 'danger', 'shopkeeper' => 'warning', default => 'primary' };
}
