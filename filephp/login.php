<?php
/**
 * SecureShop - Login Page
 * Security: Rate limiting, account lockout, CSRF, bcrypt verify, session regeneration
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
require_once __DIR__ . '/includes/email.php';

send_security_headers();
session_start_secure();

// Redirect if already logged in
if (is_logged_in()) {
    safe_redirect($_GET['next'] ?? 'dashboard.php');
}

$errors = [];
$old    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ===== CSRF =====
    if (!csrf_verify()) {
        flash('danger', 'Security token expired. Please try again.');
        header('Location: login.php');
        exit;
    }

    $action      = sanitize_enum($_POST['action'] ?? 'password_login', ['password_login', 'send_otp']);
    $identifier  = sanitize_string($_POST['identifier'] ?? '', 255);
    $old['identifier'] = $identifier;

    if (!$identifier) {
        $errors['identifier'][] = 'Email, phone number, or username is required.';
    }

    $ip = get_client_ip();

    // ===== OTP LOGIN ACTION =====
    if ($action === 'send_otp' && empty($errors)) {
        if (rate_limit_check('login_otp', $ip, 5, 300)) {
            $errors['general'][] = 'Too many OTP requests. Please wait a few minutes.';
        } else {
            $user = find_user_by_identifier($identifier);
            if (!$user) {
                $errors['identifier'][] = 'No account found matching that email or phone number. Please register.';
            } else {
                $target_id = $user['email'] ?: $user['phone'];
                $otp = otp_generate($target_id, 'login');
                flash('info', "🔐 Login OTP sent to <strong>" . e($target_id) . "</strong>. Demo Code: <strong class='text-warning fs-5'>{$otp}</strong>");
                header('Location: verify-otp.php?identifier=' . urlencode($target_id) . '&type=login');
                exit;
            }
        }
    }

    // ===== PASSWORD LOGIN ACTION =====
    if ($action === 'password_login' && empty($errors)) {
        $password    = $_POST['password'] ?? '';
        $remember_me = isset($_POST['remember_me']);

        if (!$password) $errors['password'][] = 'Password is required.';

        if (empty($errors)) {
            // Rate limiting
            if (rate_limit_check('login', $ip, LOGIN_MAX_ATTEMPTS, LOGIN_RATE_WINDOW)) {
                $errors['general'][] = 'Too many login attempts. Please wait 15 minutes before trying again.';
                log_security('LOGIN_RATE_LIMITED', ['ip' => $ip, 'identifier' => $identifier]);
            }
            // Account lockout
            elseif (account_is_locked($identifier)) {
                $remaining = account_lock_remaining($identifier);
                $mins = max(1, ceil($remaining / 60));
                $errors['general'][] = "Account is temporarily locked. Please try again in {$mins} minute(s).";
                log_security('LOGIN_ACCOUNT_LOCKED', ['identifier' => $identifier]);
            }
            else {
                // Fetch user by email, phone, or username
                $user = find_user_by_identifier($identifier);

                if (!$user || !password_verify($password, $user['password_hash'])) {
                    $errors['general'][] = 'Invalid credentials. Please check your email/phone and password.';
                    record_failed_login($identifier, $ip);
                    log_security('LOGIN_FAILED', ['identifier' => $identifier, 'ip' => $ip]);
                } else {
                    rate_limit_reset('login', $ip);
                    reset_failed_logins((int)$user['id']);

                    if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST])) {
                        $new_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
                        db_execute("UPDATE users SET password_hash=? WHERE id=?", 'si', [$new_hash, $user['id']]);
                    }

                    login_user((int)$user['id'], $user['role'], (bool)$user['must_change_password'], $remember_me);
                    cart_merge_on_login((int)$user['id']);

                    log_audit('LOGIN', 'user', (int)$user['id']);
                    flash('success', 'Welcome back, ' . e($user['first_name']) . '!');

                    $next = validate_redirect_url($_GET['next'] ?? '');
                    safe_redirect($next ?: 'dashboard.php');
                }
            }
        }
    }
}

$page_title = 'Login';
$page_desc  = 'Sign in to your SecureShop account.';
log_access();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> — <?= APP_NAME ?></title>
    <meta name="description" content="<?= e($page_desc) ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>

<div id="session-data" data-logged-in="0" style="display:none"></div>
<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer" style="z-index:1090"></div>

<div class="auth-page">
    <div class="auth-card">

        <!-- Logo -->
        <div class="auth-logo">
            <i class="bi bi-shield-lock-fill"></i>
        </div>
        <h1 class="auth-title">Member Sign In</h1>
        <p class="auth-subtitle text-muted">Access your Black Stone rewards, collection, and orders</p>

        <!-- Errors -->
        <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2 flex-shrink-0"></i>
            <div><?= e($errors['general'][0]) ?></div>
        </div>
        <?php endif; ?>

        <!-- Flash messages -->
        <?php if (!empty($_SESSION['flash'])) { render_flash(); } ?>

        <!-- Dual Login Tabs -->
        <ul class="nav nav-pills nav-fill mb-4 p-1 bg-dark rounded-pill border border-secondary" id="loginTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active rounded-pill fw-semibold py-2" id="password-tab" data-bs-toggle="pill" data-bs-target="#tab-password" type="button" role="tab">
                    <i class="bi bi-key-fill me-1 text-warning"></i> Password
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill fw-semibold py-2" id="otp-tab" data-bs-toggle="pill" data-bs-target="#tab-otp" type="button" role="tab">
                    <i class="bi bi-phone-vibrate-fill me-1 text-warning"></i> Phone / Email OTP
                </button>
            </li>
        </ul>

        <div class="tab-content" id="loginTabContent">
            <!-- Tab 1: Password Login -->
            <div class="tab-pane fade show active" id="tab-password" role="tabpanel">
                <form method="POST" action="login.php<?= !empty($_GET['next']) ? '?next='.urlencode($_GET['next']) : '' ?>">
                    <?php render_csrf_field(); ?>
                    <input type="hidden" name="action" value="password_login">

                    <div class="mb-3 text-start">
                        <label class="form-label" for="identifier">
                            <i class="bi bi-person-fill me-1 text-warning"></i>Email, Phone, or Username
                        </label>
                        <input type="text"
                               class="form-control bg-dark text-light border-secondary <?= field_class($errors, 'identifier') ?>"
                               id="identifier" name="identifier"
                               value="<?= e($old['identifier'] ?? '') ?>"
                               placeholder="e.g. user@example.com or +91 9876543210"
                               autocomplete="username" maxlength="255" required>
                        <?php render_field_error($errors, 'identifier'); ?>
                    </div>

                    <div class="mb-4 text-start">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label mb-0" for="password">
                                <i class="bi bi-lock-fill me-1 text-warning"></i>Password
                            </label>
                            <a href="password-reset.php" class="small text-accent">Forgot password?</a>
                        </div>
                        <div class="input-group">
                            <input type="password"
                                   class="form-control bg-dark text-light border-secondary <?= field_class($errors, 'password') ?>"
                                   id="password" name="password"
                                   placeholder="Enter password"
                                   autocomplete="current-password" maxlength="128" required>
                            <button type="button" class="btn btn-outline-secondary" data-pwd-toggle="password" tabindex="-1">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <?php render_field_error($errors, 'password'); ?>
                    </div>

                    <div class="mb-4 text-start">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                            <label class="form-check-label small text-muted" for="remember_me">
                                Keep me signed in for 30 days
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-accent w-100 py-3 fw-bold mb-3 shadow-gold">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In with Password
                    </button>
                </form>
            </div>

            <!-- Tab 2: OTP Login -->
            <div class="tab-pane fade" id="tab-otp" role="tabpanel">
                <form method="POST" action="login.php<?= !empty($_GET['next']) ? '?next='.urlencode($_GET['next']) : '' ?>">
                    <?php render_csrf_field(); ?>
                    <input type="hidden" name="action" value="send_otp">

                    <p class="text-muted small text-start mb-3">
                        Enter your registered phone number or email address. We will send you an instantaneous 6-digit one-time password (OTP).
                    </p>

                    <div class="mb-4 text-start">
                        <label class="form-label" for="otp_identifier">
                            <i class="bi bi-telephone-fill me-1 text-warning"></i>Registered Phone or Email
                        </label>
                        <input type="text"
                               class="form-control bg-dark text-light border-secondary <?= field_class($errors, 'identifier') ?>"
                               id="otp_identifier" name="identifier"
                               value="<?= e($old['identifier'] ?? '') ?>"
                               placeholder="e.g. +91 9876543210 or user@example.com"
                               maxlength="255" required>
                        <?php render_field_error($errors, 'identifier'); ?>
                    </div>

                    <button type="submit" class="btn btn-warning w-100 py-3 fw-bold mb-3 shadow-sm text-dark">
                        <i class="bi bi-send-fill me-2"></i>Send Verification Code (OTP)
                    </button>
                </form>
            </div>
        </div>

        <p class="text-center text-muted small mt-2 mb-0">
            Don't have an account?
            <a href="register.php<?= !empty($_GET['next']) ? '?next='.urlencode($_GET['next']) : '' ?>" class="text-accent fw-semibold">Create account</a>
        </p>

        <!-- Security note -->
        <div class="text-center mt-4 pt-3 border-top border-secondary">
            <small class="text-muted">
                <i class="bi bi-shield-check me-1 text-warning"></i>
                Secured by Black Stone Two-Factor & Encrypted Auth
            </small>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
