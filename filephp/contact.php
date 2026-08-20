<?php
/**
 * SecureShop - Contact Us Page
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

$errors = [];
$old    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect();

    $name    = sanitize_name($_POST['name'] ?? '');
    $email   = sanitize_email($_POST['email'] ?? '');
    $subject = sanitize_string($_POST['subject'] ?? '', 200);
    $message = sanitize_textarea($_POST['message'] ?? '', 2000);

    $old = compact('name', 'email', 'subject', 'message');

    // Rate Limiting
    $ip = get_client_ip();
    if (rate_limit_check('contact_form', $ip, 3, 3600)) {
        $errors[] = 'You have sent too many messages. Please wait before submitting again.';
    } else {
        if (!$name)    $errors[] = 'Please enter your name.';
        if (!$email)   $errors[] = 'Please enter a valid email address.';
        if (!$subject) $errors[] = 'Please enter a subject.';
        if (!$message) $errors[] = 'Please enter your message.';

        if (empty($errors)) {
            log_audit('CONTACT_SUBMIT', 'contact', 0, [], ['name' => $name, 'email' => $email, 'subject' => $subject]);
            flash('success', 'Thank you! Your message has been sent. Our team will get back to you shortly.');
            header('Location: contact.php');
            exit;
        }
    }
}

$page_title = 'Contact Us';
$page_desc  = 'Get in touch with Black Stone customer support.';

log_access();
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5 navbar-spacer">
    <div class="row g-5">
        <!-- Contact Form -->
        <div class="col-lg-7">
            <h1 class="h2 fw-bold mb-2">Get in Touch</h1>
            <p class="text-muted mb-4">Have questions about an order, mystery code, or product? Send us a message.</p>

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

            <div class="card p-4 border-secondary bg-dark-subtle rounded-4 shadow-sm">
                <form method="POST" action="contact.php">
                    <?= csrf_field() ?>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Your Name</label>
                            <input type="text" name="name" class="form-control bg-dark text-light border-secondary" value="<?= e($old['name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email Address</label>
                            <input type="email" name="email" class="form-control bg-dark text-light border-secondary" value="<?= e($old['email'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Subject</label>
                        <input type="text" name="subject" class="form-control bg-dark text-light border-secondary" value="<?= e($old['subject'] ?? '') ?>" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Message</label>
                        <textarea name="message" class="form-control bg-dark text-light border-secondary" rows="5" required><?= e($old['message'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-accent btn-lg w-100 fw-bold">Send Message</button>
                </form>
            </div>
        </div>

        <!-- Contact Info Cards -->
        <div class="col-lg-5 d-flex flex-column gap-3">
            <div class="card p-4 border-secondary bg-dark-subtle rounded-4 shadow-sm">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-sm bg-accent text-light"><i class="bi bi-envelope fs-5"></i></div>
                    <div>
                        <h6 class="fw-bold mb-0">Email Support</h6>
                        <span class="text-muted small">support@secureshop.local</span>
                    </div>
                </div>
            </div>
            <div class="card p-4 border-secondary bg-dark-subtle rounded-4 shadow-sm">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-sm bg-success text-light"><i class="bi bi-telephone fs-5"></i></div>
                    <div>
                        <h6 class="fw-bold mb-0">Customer Care</h6>
                        <span class="text-muted small">+91 1800 123 4567 (Mon-Sat, 9am - 7pm)</span>
                    </div>
                </div>
            </div>
            <div class="card p-4 border-secondary bg-dark-subtle rounded-4 shadow-sm">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-sm bg-warning text-dark"><i class="bi bi-geo-alt fs-5"></i></div>
                    <div>
                        <h6 class="fw-bold mb-0">Headquarters</h6>
                        <span class="text-muted small">Black Stone Studio, Tech Park, India</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
