<?php
/**
 * SecureShop - User Registration
 * Security: CSRF, rate limiting, whitelist validation, bcrypt hashing, email verification stub
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

if (is_logged_in()) {
    safe_redirect('dashboard.php');
}

$errors = [];
$old    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash('danger', 'Security token expired. Please try again.');
        header('Location: register.php'); exit;
    }

    // ===== COLLECT INPUTS =====
    $first_name      = sanitize_name($_POST['first_name'] ?? '');
    $last_name       = sanitize_name($_POST['last_name']  ?? '');
    $username        = sanitize_username($_POST['username'] ?? '');
    $email           = sanitize_email($_POST['email']     ?? '');
    $phone           = sanitize_phone($_POST['phone']     ?? '');
    $password        = $_POST['password']  ?? '';
    $password_confirm= $_POST['password2'] ?? '';

    $old = compact('first_name', 'last_name', 'username', 'email', 'phone');

    // ===== RATE LIMITING =====
    $ip = get_client_ip();
    if (rate_limit_check('register', $ip, REGISTER_MAX_ATTEMPTS, REGISTER_RATE_WINDOW)) {
        $errors['general'][] = 'Too many registration attempts. Please wait before trying again.';
        log_security('REGISTER_RATE_LIMITED', ['ip' => $ip]);
    } else {
        // ===== VALIDATE =====
        if (!$first_name)  $errors['first_name'][] = 'First name is required and must contain only letters.';
        if (!$last_name)   $errors['last_name'][]  = 'Last name is required and must contain only letters.';
        if (!$username)    $errors['username'][]    = 'Username must be 3–50 alphanumeric characters (- _ allowed).';
        if (!$email)       $errors['email'][]       = 'A valid email address is required.';
        if (!$phone || strlen(preg_replace('/\D/', '', $phone)) < 10) {
            $errors['phone'][] = 'A valid 10-digit phone number is required for OTP verification.';
        }

        // Password strength
        $pwd_check = validate_password($password);
        if (!$pwd_check['valid']) {
            foreach ($pwd_check['errors'] as $err) {
                $errors['password'][] = $err;
            }
        }

        if ($password !== $password_confirm) {
            $errors['password2'][] = 'Passwords do not match.';
        }

        // ===== UNIQUENESS CHECKS =====
        if ($email && !isset($errors['email'])) {
            $exists = db_fetch_value("SELECT COUNT(*) FROM users WHERE email=?", 's', [$email]);
            if ($exists) $errors['email'][] = 'This email is already registered. Try logging in.';
        }
        if ($phone && !isset($errors['phone'])) {
            $exists_phone = db_fetch_value("SELECT COUNT(*) FROM users WHERE phone=?", 's', [$phone]);
            if ($exists_phone) $errors['phone'][] = 'This phone number is already registered.';
        }
        if ($username && !isset($errors['username'])) {
            $exists = db_fetch_value("SELECT COUNT(*) FROM users WHERE username=?", 's', [$username]);
            if ($exists) $errors['username'][] = 'This username is already taken.';
        }

        if (empty($errors)) {
            // ===== DISPATCH OTP & STORE PENDING DATA =====
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            $temp_data = [
                'username'      => $username,
                'email'         => $email,
                'phone'         => $phone,
                'password_hash' => $hash,
                'first_name'    => $first_name,
                'last_name'     => $last_name,
            ];

            // Generate OTP for this registration
            $otp = otp_generate($email, 'register', $temp_data);
            $_SESSION['pending_phone'] = $phone;

            flash('info', "🔐 6-digit OTP sent to <strong>" . e($email) . "</strong> and <strong>" . e($phone) . "</strong>. Demo Code: <strong class='text-warning fs-5'>{$otp}</strong>");
            header('Location: verify-otp.php?identifier=' . urlencode($email) . '&type=register');
            exit;
        }
    }
}

$page_title = 'Create Account';
$page_desc  = 'Register for a free SecureShop account.';
log_access();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>

<div id="session-data" data-logged-in="0" style="display:none"></div>
<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer" style="z-index:1090"></div>

<div class="auth-page">
    <div class="auth-card" style="max-width:520px">

        <div class="auth-logo"><i class="bi bi-person-plus-fill"></i></div>
        <h1 class="auth-title">Create Account</h1>
        <p class="auth-subtitle">Join <?= APP_NAME ?> — secure shopping starts here</p>

        <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($errors['general'][0]) ?></div>
        <?php endif; ?>

        <form method="POST" action="register.php" class="needs-validation" novalidate>
            <?php render_csrf_field(); ?>

            <!-- Name row -->
            <div class="row g-3 mb-3">
                <div class="col-6">
                    <label class="form-label" for="first_name">First Name</label>
                    <input type="text" class="form-control <?= field_class($errors,'first_name') ?>"
                           id="first_name" name="first_name"
                           value="<?= e($old['first_name'] ?? '') ?>"
                           maxlength="100" required autocomplete="given-name">
                    <?php render_field_error($errors, 'first_name'); ?>
                </div>
                <div class="col-6">
                    <label class="form-label" for="last_name">Last Name</label>
                    <input type="text" class="form-control <?= field_class($errors,'last_name') ?>"
                           id="last_name" name="last_name"
                           value="<?= e($old['last_name'] ?? '') ?>"
                           maxlength="100" required autocomplete="family-name">
                    <?php render_field_error($errors, 'last_name'); ?>
                </div>
            </div>

            <!-- Username -->
            <div class="mb-3">
                <label class="form-label" for="username"><i class="bi bi-at me-1"></i>Username</label>
                <input type="text" class="form-control <?= field_class($errors,'username') ?>"
                       id="username" name="username"
                       value="<?= e($old['username'] ?? '') ?>"
                       placeholder="e.g. john_doe" maxlength="50"
                       pattern="[a-zA-Z0-9_\-]{3,50}"
                       required autocomplete="username">
                <div class="form-text">3–50 characters. Letters, numbers, _ and - only.</div>
                <?php render_field_error($errors, 'username'); ?>
            </div>

            <!-- Email -->
            <div class="mb-3">
                <label class="form-label" for="email"><i class="bi bi-envelope me-1 text-warning"></i>Email Address</label>
                <input type="email" class="form-control bg-dark text-light border-secondary <?= field_class($errors,'email') ?>"
                       id="email" name="email"
                       value="<?= e($old['email'] ?? '') ?>"
                       placeholder="you@example.com" maxlength="255"
                       required autocomplete="email">
                <?php render_field_error($errors, 'email'); ?>
            </div>

            <!-- Phone Number -->
            <div class="mb-3">
                <label class="form-label" for="phone"><i class="bi bi-telephone-fill me-1 text-warning"></i>Phone Number (For OTP Verification)</label>
                <input type="tel" class="form-control bg-dark text-light border-secondary <?= field_class($errors,'phone') ?>"
                       id="phone" name="phone"
                       value="<?= e($old['phone'] ?? '') ?>"
                       placeholder="e.g. +91 9876543210" maxlength="20"
                       required autocomplete="tel">
                <div class="form-text text-muted small">We'll send a 6-digit OTP code to verify your phone & email.</div>
                <?php render_field_error($errors, 'phone'); ?>
            </div>

            <!-- Password -->
            <div class="mb-3">
                <label class="form-label" for="password"><i class="bi bi-lock me-1"></i>Password</label>
                <div class="input-group">
                    <input type="password" class="form-control <?= field_class($errors,'password') ?>"
                           id="password" name="password"
                           placeholder="Create a strong password"
                           maxlength="128" required
                           autocomplete="new-password"
                           data-pwd-strength="pwd-meter">
                    <button type="button" class="btn btn-outline-secondary" data-pwd-toggle="password" aria-label="Toggle">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <!-- Strength meter -->
                <div class="pwd-strength-bar mt-2">
                    <div class="pwd-strength-fill" id="pwd-meter-fill"></div>
                </div>
                <div class="pwd-strength-label" id="pwd-meter-label"></div>
                <div class="form-text mt-1">Min 8 chars with uppercase, lowercase, number & special char.</div>
                <?php render_field_error($errors, 'password'); ?>
            </div>

            <!-- Confirm Password -->
            <div class="mb-4">
                <label class="form-label" for="password2"><i class="bi bi-lock-fill me-1"></i>Confirm Password</label>
                <div class="input-group">
                    <input type="password" class="form-control <?= field_class($errors,'password2') ?>"
                           id="password2" name="password2"
                           placeholder="Repeat your password"
                           maxlength="128" required autocomplete="new-password">
                    <button type="button" class="btn btn-outline-secondary" data-pwd-toggle="password2" aria-label="Toggle">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <?php render_field_error($errors, 'password2'); ?>
            </div>

            <button type="submit" class="btn btn-accent w-100 py-2 fw-semibold mb-3">
                <i class="bi bi-person-check me-2"></i>Create Account
            </button>
        </form>

        <p class="text-center text-muted small mb-0">
            Already have an account? <a href="login.php" class="text-accent fw-semibold">Sign in</a>
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
