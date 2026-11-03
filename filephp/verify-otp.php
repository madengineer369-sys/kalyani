<?php
/**
 * SecureShop - Dedicated 6-Digit OTP Verification Screen
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

$identifier = sanitize_string($_GET['identifier'] ?? $_POST['identifier'] ?? '', 255);
$type       = sanitize_enum($_GET['type'] ?? $_POST['type'] ?? 'register', ['register', 'login', 'reset']);

if (!$identifier) {
    flash('warning', 'Please provide a valid email or phone number to verify.');
    header('Location: ' . ($type === 'login' ? 'login.php' : 'register.php'));
    exit;
}

$errors = [];
$latest_otp_display = ($_SESSION['last_otp_identifier'] ?? '') === $identifier ? ($_SESSION['last_otp_code'] ?? '') : '';

// Handle Resend Request
if (isset($_GET['resend']) && $_GET['resend'] === '1') {
    // Check rate limit
    $ip = get_client_ip();
    if (rate_limit_check('otp_resend', $ip, 5, 300)) {
        flash('danger', 'Too many OTP requests. Please wait a few minutes before resending.');
    } else {
        // Fetch existing temp_data if available
        $existing = db_fetch_one(
            "SELECT temp_data FROM otps WHERE identifier = ? AND type = ? ORDER BY id DESC LIMIT 1",
            'ss', [$identifier, $type]
        );
        $temp_data = $existing && $existing['temp_data'] ? json_decode($existing['temp_data'], true) : [];
        $new_otp = otp_generate($identifier, $type, $temp_data);
        flash('success', 'A new verification code has been generated.');
    }
    header('Location: verify-otp.php?identifier=' . urlencode($identifier) . '&type=' . urlencode($type));
    exit;
}

// Handle OTP Verification Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash('danger', 'Security token expired. Please try again.');
        header('Location: verify-otp.php?identifier=' . urlencode($identifier) . '&type=' . urlencode($type));
        exit;
    }

    $otp_input = trim($_POST['otp'] ?? '');
    // Support digit boxes combining
    if (empty($otp_input)) {
        $digits = '';
        for ($i = 1; $i <= 6; $i++) {
            $digits .= trim($_POST["otp_$i"] ?? '');
        }
        $otp_input = $digits;
    }
    $otp_input = preg_replace('/[^0-9]/', '', $otp_input);

    if (strlen($otp_input) !== 6) {
        $errors[] = 'Please enter all 6 digits of your verification code.';
    } else {
        $verified_data = otp_verify($identifier, $otp_input, $type);

        if ($verified_data === null) {
            $errors[] = 'Invalid or expired verification code. Please check or request a new code.';
            log_security('OTP_INVALID_ATTEMPT', ['identifier' => $identifier, 'ip' => get_client_ip()]);
        } else {
            // ===== SUCCESSFUL VERIFICATION =====
            if ($type === 'register') {
                $username      = $verified_data['username'] ?? ('user_' . bin2hex(random_bytes(4)));
                $email         = $verified_data['email'] ?? $identifier;
                $phone         = $verified_data['phone'] ?? ($_SESSION['pending_phone'] ?? null);
                $password_hash = $verified_data['password_hash'] ?? '';
                $first_name    = $verified_data['first_name'] ?? 'Black Stone';
                $last_name     = $verified_data['last_name'] ?? 'Member';

                // Check if user already got created in race condition
                $existing_user = db_fetch_one("SELECT id FROM users WHERE email=? OR (phone IS NOT NULL AND phone=?) LIMIT 1", 'ss', [$email, $phone]);

                if ($existing_user) {
                    $user_id = (int)$existing_user['id'];
                    db_execute("UPDATE users SET email_verified=1, phone_verified=1 WHERE id=?", 'i', [$user_id]);
                } else {
                    $user_id = db_insert(
                        "INSERT INTO users (username, email, phone, password_hash, role, first_name, last_name, is_active, email_verified, phone_verified, created_at)
                         VALUES (?, ?, ?, ?, 'user', ?, ?, 1, 1, 1, NOW())",
                        'ssssss',
                        [$username, $email, $phone, $password_hash, $first_name, $last_name]
                    );
                }

                // Grant welcome mystery code to new verified members
                $welcome_code = 'WELCOME-' . strtoupper(bin2hex(random_bytes(3)));
                db_execute(
                    "INSERT INTO mystery_codes (code, user_id, box_type, prize_title, prize_value, is_redeemed, created_at)
                     VALUES (?, ?, 'gold', 'Black Stone Welcome Box', '15% OFF Next Drop', 0, NOW())",
                    'si', [$welcome_code, $user_id]
                );

                login_user($user_id, 'user', false, false);
                log_audit('REGISTER_OTP_VERIFIED', 'user', $user_id);
                flash('success', '🎉 Welcome to Black Stone! Your phone & email are verified. You unlocked a Welcome Mystery Box in your lounge!');
                safe_redirect('index.php');
                exit;

            } elseif ($type === 'login') {
                $user = find_user_by_identifier($identifier);
                if ($user) {
                    login_user((int)$user['id'], $user['role'], (bool)$user['must_change_password'], false);
                    cart_merge_on_login((int)$user['id']);
                    log_audit('LOGIN_OTP', 'user', (int)$user['id']);
                    flash('success', 'Welcome back, ' . e($user['first_name']) . '! Signed in via OTP.');
                    safe_redirect('dashboard.php');
                    exit;
                } else {
                    $errors[] = 'Account not found for this identifier.';
                }
            }
        }
    }
}

$page_title = 'Verify Verification Code';
$page_desc  = 'Enter your 6-digit OTP code to verify your account.';
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
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>

<div class="auth-page">
    <div class="auth-card text-center" style="max-width: 500px;">
        <div class="auth-logo-icon">
            <i class="bi bi-shield-lock-fill"></i>
        </div>

        <h2 class="fw-bold mb-2">Two-Factor OTP Verification</h2>
        <p class="text-muted small mb-4">
            We sent a secure 6-digit verification code to<br>
            <strong class="text-warning"><?= e($identifier) ?></strong>
        </p>

        <?php if (!empty($latest_otp_display)): ?>
        <div class="alert alert-warning border-warning bg-dark-subtle text-start p-3 mb-4 rounded-3 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="badge bg-warning text-dark fw-bold mb-1">LOCAL DEV SIMULATOR</span>
                    <div class="small text-light">Demo OTP Code: <strong class="fs-5 text-warning font-monospace"><?= e($latest_otp_display) ?></strong></div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-warning" onclick="fillDemoOtp('<?= e($latest_otp_display) ?>')">
                    <i class="bi bi-magic me-1"></i> Auto-Fill
                </button>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger text-start">
            <?php foreach ($errors as $err): ?>
                <div><i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($err) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="verify-otp.php" id="otpVerifyForm">
            <?= render_csrf_field(); ?>
            <input type="hidden" name="identifier" value="<?= e($identifier) ?>">
            <input type="hidden" name="type" value="<?= e($type) ?>">
            <input type="hidden" name="otp" id="full_otp_input" value="">

            <div class="otp-box-grid">
                <input type="text" name="otp_1" class="otp-single-box" maxlength="1" inputmode="numeric" autocomplete="one-time-code" required autofocus>
                <input type="text" name="otp_2" class="otp-single-box" maxlength="1" inputmode="numeric" required>
                <input type="text" name="otp_3" class="otp-single-box" maxlength="1" inputmode="numeric" required>
                <input type="text" name="otp_4" class="otp-single-box" maxlength="1" inputmode="numeric" required>
                <input type="text" name="otp_5" class="otp-single-box" maxlength="1" inputmode="numeric" required>
                <input type="text" name="otp_6" class="otp-single-box" maxlength="1" inputmode="numeric" required>
            </div>

            <button type="submit" class="btn btn-accent w-100 py-3 fw-bold mb-3 shadow-gold">
                <i class="bi bi-check2-circle me-2"></i> Verify Code & Continue
            </button>
        </form>

        <div class="d-flex align-items-center justify-content-between mt-4 pt-3 border-top border-secondary">
            <span class="text-muted small">Didn't receive the code?</span>
            <a href="verify-otp.php?identifier=<?= urlencode($identifier) ?>&type=<?= urlencode($type) ?>&resend=1" 
               class="btn btn-sm btn-outline-secondary" id="resendBtn">
                <i class="bi bi-arrow-repeat me-1"></i> Resend Code <span id="resendCountdown" class="text-warning"></span>
            </a>
        </div>

        <div class="mt-4">
            <a href="<?= $type === 'login' ? 'login.php' : 'register.php' ?>" class="text-muted small text-decoration-underline">
                <i class="bi bi-arrow-left me-1"></i> Return to <?= $type === 'login' ? 'Login' : 'Registration' ?>
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
<script>
function fillDemoOtp(code) {
    if (!code || code.length !== 6) return;
    const boxes = document.querySelectorAll('.otp-single-box');
    code.split('').forEach((char, i) => {
        if (boxes[i]) boxes[i].value = char;
    });
    const fullInput = document.getElementById('full_otp_input');
    if (fullInput) fullInput.value = code;
    boxes[boxes.length - 1].focus();
}
</script>
</body>
</html>
