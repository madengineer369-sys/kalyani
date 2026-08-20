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

    $identifier  = sanitize_string($_POST['identifier'] ?? '', 255);
    $password    = $_POST['password'] ?? ''; // never sanitize passwords
    $remember_me = isset($_POST['remember_me']);
    $old['identifier'] = $identifier;

    if (!$identifier) $errors['identifier'][] = 'Email or username is required.';
    if (!$password)   $errors['password'][]   = 'Password is required.';

    if (empty($errors)) {
        $ip = get_client_ip();

        // ===== RATE LIMITING =====
        if (rate_limit_check('login', $ip, LOGIN_MAX_ATTEMPTS, LOGIN_RATE_WINDOW)) {
            $errors['general'][] = 'Too many login attempts. Please wait 15 minutes before trying again.';
            log_security('LOGIN_RATE_LIMITED', ['ip' => $ip, 'identifier' => $identifier]);
        }

        // ===== ACCOUNT LOCKOUT =====
        elseif (account_is_locked($identifier)) {
            $remaining = account_lock_remaining($identifier);
            $mins = max(1, ceil($remaining / 60));
            $errors['general'][] = "Account is temporarily locked. Please try again in {$mins} minute(s).";
            log_security('LOGIN_ACCOUNT_LOCKED', ['identifier' => $identifier]);
        }

        else {
            // ===== FETCH USER (by email OR username) =====
            $user = db_fetch_one(
                "SELECT id, email, username, password_hash, role, is_active, must_change_password,
                        first_name, last_name
                 FROM users
                 WHERE (email = ? OR username = ?) AND is_active = 1
                 LIMIT 1",
                'ss', [$identifier, $identifier]
            );

            if (!$user || !password_verify($password, $user['password_hash'])) {
                // Generic message — don't reveal if email/username exists
                $errors['general'][] = 'Invalid credentials. Please check and try again.';
                record_failed_login($identifier, $ip);
                log_security('LOGIN_FAILED', ['identifier' => $identifier, 'ip' => $ip]);
            } else {
                // ===== SUCCESS =====
                rate_limit_reset('login', $ip);
                reset_failed_logins((int)$user['id']);

                // Re-hash if cost factor upgraded
                if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST])) {
                    $new_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
                    db_execute("UPDATE users SET password_hash=? WHERE id=?", 'si', [$new_hash, $user['id']]);
                }

                login_user((int)$user['id'], $user['role'], (bool)$user['must_change_password'], $remember_me);

                // Merge guest cart
                cart_merge_on_login((int)$user['id']);

                // Login alert email
                send_login_alert_email(
                    $user['email'],
                    $user['first_name'],
                    $ip,
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                );

                log_audit('LOGIN', 'user', (int)$user['id']);
                flash('success', 'Welcome back, ' . $user['first_name'] . '!');

                $next = validate_redirect_url($_GET['next'] ?? '');
                safe_redirect($next ?: 'dashboard.php');
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
        <h1 class="auth-title">Welcome Back</h1>
        <p class="auth-subtitle">Sign in to your <?= APP_NAME ?> account</p>

        <!-- Errors -->
        <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2 flex-shrink-0"></i>
            <div><?= e($errors['general'][0]) ?></div>
        </div>
        <?php endif; ?>

        <!-- Flash messages -->
        <?php
        if (!empty($_SESSION['flash'])) { render_flash(); }
        ?>

        <!-- Login Form -->
        <form method="POST" action="login.php<?= !empty($_GET['next']) ? '?next='.urlencode($_GET['next']) : '' ?>"
              class="needs-validation" novalidate id="login-form">
            <?php render_csrf_field(); ?>

            <!-- Identifier -->
            <div class="mb-3">
                <label class="form-label" for="identifier">
                    <i class="bi bi-person me-1"></i>Email or Username
                </label>
                <input type="text"
                       class="form-control <?= field_class($errors, 'identifier') ?>"
                       id="identifier" name="identifier"
                       value="<?= e($old['identifier'] ?? '') ?>"
                       placeholder="you@example.com"
                       autocomplete="username"
                       maxlength="255"
                       required
                       aria-describedby="identifier-help">
                <?php render_field_error($errors, 'identifier'); ?>
            </div>

            <!-- Password -->
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label mb-0" for="password">
                        <i class="bi bi-lock me-1"></i>Password
                    </label>
                    <a href="password-reset.php" class="small text-accent">Forgot password?</a>
                </div>
                <div class="input-group">
                    <input type="password"
                           class="form-control <?= field_class($errors, 'password') ?>"
                           id="password" name="password"
                           placeholder="Enter your password"
                           autocomplete="current-password"
                           maxlength="128"
                           required>
                    <button type="button" class="btn btn-outline-secondary" data-pwd-toggle="password" tabindex="-1" aria-label="Toggle password visibility">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <?php render_field_error($errors, 'password'); ?>
            </div>

            <!-- Remember Me -->
            <div class="mb-4">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                    <label class="form-check-label small" for="remember_me">
                        Keep me signed in for 30 days
                    </label>
                </div>
            </div>

            <!-- Submit -->
            <button type="submit" class="btn btn-accent w-100 py-2 mb-3 fw-semibold" id="login-btn">
                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
            </button>

        </form>

        <p class="text-center text-muted small mb-0">
            Don't have an account?
            <a href="register.php<?= !empty($_GET['next']) ? '?next='.urlencode($_GET['next']) : '' ?>" class="text-accent fw-semibold">Create one</a>
        </p>

        <!-- Security note -->
        <div class="text-center mt-4 pt-3 border-top border-secondary">
            <small class="text-muted">
                <i class="bi bi-shield-check me-1 text-success"></i>
                Secured with bcrypt + CSRF protection
            </small>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
