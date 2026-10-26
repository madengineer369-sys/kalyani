<?php
/**
 * SecureShop - Password Reset Confirmation
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

send_security_headers();
session_start_secure();

$raw_token  = sanitize_string($_GET['token'] ?? '', 128);
$token_hash = hash_token($raw_token);

$token_row = db_fetch_one(
    "SELECT * FROM password_reset_tokens WHERE token_hash=? AND used=0 AND expires_at > NOW() LIMIT 1",
    's', [$token_hash]
);

if (!$token_row) {
    flash('danger', 'Invalid or expired password reset link.');
    header('Location: password-reset.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect();
    $new_pwd     = $_POST['new_password'] ?? '';
    $confirm_pwd = $_POST['confirm_password'] ?? '';

    $pwd_check = validate_password($new_pwd);
    if (!$pwd_check['valid']) {
        foreach ($pwd_check['errors'] as $e) {
            $errors[] = $e;
        }
    }

    if ($new_pwd !== $confirm_pwd) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $new_hash = password_hash($new_pwd, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        
        // Update User Password
        db_execute(
            "UPDATE users SET password_hash=?, password_changed_at=NOW(), must_change_password=0 WHERE id=?",
            'si', [$new_hash, $token_row['user_id']]
        );

        // Mark Token as Used
        db_execute("UPDATE password_reset_tokens SET used=1 WHERE id=?", 'i', [$token_row['id']]);

        log_security('PASSWORD_RESET_SUCCESS', ['user_id' => $token_row['user_id']]);
        flash('success', 'Your password has been reset successfully! You can now log in.');
        header('Location: login.php');
        exit;
    }
}

$page_title = 'Set New Password';
$page_desc  = 'Set your new account password.';

log_access();
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5 navbar-spacer">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card p-4 border-secondary bg-dark-subtle rounded-4 shadow-sm">
                <h4 class="fw-bold mb-2 text-center">Set New Password</h4>
                <p class="text-muted small text-center mb-4">Please choose a strong password for your account.</p>

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

                <form method="POST" action="password-reset-confirm.php?token=<?= urlencode($raw_token) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">New Password</label>
                        <input type="password" name="new_password" class="form-control bg-dark text-light border-secondary" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control bg-dark text-light border-secondary" required>
                    </div>
                    <button type="submit" class="btn btn-accent w-100 fw-bold py-2">Reset Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
