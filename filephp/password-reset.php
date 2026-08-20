<?php
/**
 * SecureShop - Forgot Password Request
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
require_once __DIR__ . '/includes/email.php';

send_security_headers();
session_start_secure();

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect();
    $email = sanitize_email($_POST['email'] ?? '');

    $ip = get_client_ip();
    if (rate_limit_check('pwd_reset', $ip, RESET_MAX_ATTEMPTS, RESET_RATE_WINDOW)) {
        $errors[] = 'Too many password reset requests. Please wait 1 hour.';
    } else {
        if (!$email) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            $user = db_fetch_one("SELECT id, first_name, email FROM users WHERE email=? AND is_active=1 LIMIT 1", 's', [$email]);
            if ($user) {
                $raw_token  = generate_secure_token(TOKEN_LENGTH);
                $token_hash = hash_token($raw_token);
                $expires    = date('Y-m-d H:i:s', time() + PASSWORD_RESET_EXPIRY);

                db_insert(
                    "INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, created_at, ip_address)
                     VALUES (?, ?, ?, NOW(), ?)",
                    'isss', [$user['id'], $token_hash, $expires, $ip]
                );

                send_password_reset_email($email, $user['first_name'], $raw_token);
                log_security('PASSWORD_RESET_REQUESTED', ['user_id' => $user['id']]);
            }

            // Always show generic message to prevent email enumeration
            flash('info', 'If that email address exists in our system, a password reset link has been sent to it.');
            header('Location: login.php');
            exit;
        }
    }
}

$page_title = 'Reset Password';
$page_desc  = 'Request a password reset link.';

log_access();
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5 navbar-spacer">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card p-4 border-secondary bg-dark-subtle rounded-4 shadow-sm">
                <h4 class="fw-bold mb-2 text-center">Forgot Password?</h4>
                <p class="text-muted small text-center mb-4">Enter your registered email address and we'll send you a password reset link.</p>

                <?php render_flash(); ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="password-reset.php">
                    <?= csrf_field() ?>
                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Email Address</label>
                        <input type="email" name="email" class="form-control bg-dark text-light border-secondary" placeholder="user@example.com" required>
                    </div>
                    <button type="submit" class="btn btn-accent w-100 fw-bold py-2 mb-3">Send Reset Link</button>
                    <div class="text-center">
                        <a href="login.php" class="text-muted small text-decoration-none">&larr; Back to Login</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
