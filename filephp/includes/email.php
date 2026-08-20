<?php
/**
 * SecureShop - Email Module
 *
 * Wraps PHP mail() with templated messages.
 * Set MAIL_ENABLED=true and configure your SMTP relay for production.
 *
 * SECURITY: Never include raw passwords, tokens, or card data in email bodies.
 */

if (!defined('ECOMMERCE_APP')) {
    http_response_code(403); exit('Forbidden');
}

/**
 * Low-level email sender.
 *
 * @param string $to       Recipient email
 * @param string $subject  Email subject
 * @param string $html     HTML body
 * @param string $plain    Plain-text fallback
 * @return bool
 */
function send_email(string $to, string $subject, string $html, string $plain = ''): bool {
    if (!MAIL_ENABLED) {
        // In development, log the email instead of sending
        log_info('EMAIL_SUPPRESSED (MAIL_ENABLED=false)', [
            'to'      => $to,
            'subject' => $subject,
        ], 'access');
        return true;
    }

    $to      = filter_var($to, FILTER_VALIDATE_EMAIL);
    $subject = mb_encode_mimeheader($subject, 'UTF-8', 'B');
    if (!$to) return false;

    $boundary = 'SECURESHOP_' . bin2hex(random_bytes(8));
    $from     = MAIL_FROM_NAME . ' <' . MAIL_FROM . '>';
    $plain    = $plain ?: strip_tags($html);

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $headers .= "From: {$from}\r\n";
    $headers .= "Reply-To: " . MAIL_REPLY_TO . "\r\n";
    $headers .= "X-Mailer: SecureShop/1.0\r\n";

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($plain)) . "\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($html)) . "\r\n";
    $body .= "--{$boundary}--";

    $result = mail($to, $subject, $body, $headers);
    if (!$result) {
        log_error('EMAIL_SEND_FAILED', ['to' => $to, 'subject' => $subject]);
    }
    return $result;
}

// ===== Email Template =====

/**
 * Wrap content in the standard HTML email template.
 */
function email_template(string $title, string $content): string {
    return '<!DOCTYPE html><html><head><meta charset="UTF-8">
    <style>
        body{font-family:Arial,sans-serif;background:#f4f6f9;margin:0;padding:0}
        .wrap{max-width:600px;margin:30px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.1)}
        .header{background:linear-gradient(135deg,#6c63ff,#4ecdc4);padding:30px;text-align:center;color:#fff}
        .header h1{margin:0;font-size:24px;letter-spacing:1px}
        .body{padding:30px}
        .body p{color:#555;line-height:1.7;margin:0 0 15px}
        .btn{display:inline-block;background:#6c63ff;color:#fff;padding:13px 28px;border-radius:6px;text-decoration:none;font-weight:bold;margin:10px 0}
        .footer{background:#f4f6f9;padding:20px;text-align:center;font-size:12px;color:#999}
        .divider{border:none;border-top:1px solid #eee;margin:20px 0}
        .info-box{background:#f8f9ff;border-left:4px solid #6c63ff;padding:12px 18px;border-radius:4px;margin:15px 0}
    </style></head><body>
    <div class="wrap">
        <div class="header"><h1>' . APP_NAME . '</h1><p style="margin:5px 0 0;opacity:.8">' . e($title) . '</p></div>
        <div class="body">' . $content . '</div>
        <div class="footer">
            &copy; ' . date('Y') . ' ' . APP_NAME . '. This is an automated message — please do not reply directly.<br>
            If you did not request this email, please ignore it.
        </div>
    </div></body></html>';
}

// ===== Notification Emails =====

/**
 * Send welcome email on new account registration.
 */
function send_welcome_email(string $email, string $name): void {
    $html = email_template('Welcome to ' . APP_NAME, '
        <p>Hi <strong>' . e($name) . '</strong>,</p>
        <p>Welcome to <strong>' . APP_NAME . '</strong>! Your account has been successfully created.</p>
        <p>You can now browse our products, place orders, and track deliveries — all securely.</p>
        <a href="' . APP_BASE_URL . '/login.php" class="btn">Login to Your Account</a>
        <hr class="divider">
        <p style="font-size:13px;color:#888">If you didn\'t create this account, please contact support immediately.</p>
    ');
    send_email($email, 'Welcome to ' . APP_NAME . '!', $html);
}

/**
 * Send password reset email with secure link.
 * NOTE: token is the raw token — hash is stored in DB, raw is in the link.
 */
function send_password_reset_email(string $email, string $name, string $raw_token): void {
    $link = APP_BASE_URL . '/password-reset-confirm.php?token=' . urlencode($raw_token);
    $html = email_template('Password Reset Request', '
        <p>Hi <strong>' . e($name) . '</strong>,</p>
        <p>We received a request to reset the password for your account.</p>
        <p>Click the button below to set a new password. This link expires in <strong>1 hour</strong>.</p>
        <a href="' . e($link) . '" class="btn">Reset My Password</a>
        <div class="info-box">
            <strong>Security notice:</strong> This link can only be used once and will expire in 1 hour.
        </div>
        <p>If you did not request a password reset, your account may be at risk. Please contact support immediately.</p>
    ');
    send_email($email, 'Password Reset — ' . APP_NAME, $html);
}

/**
 * Send login alert email (new device/location).
 */
function send_login_alert_email(string $email, string $name, string $ip, string $ua): void {
    $html = email_template('New Login Detected', '
        <p>Hi <strong>' . e($name) . '</strong>,</p>
        <p>We detected a new login to your account:</p>
        <div class="info-box">
            <strong>Time:</strong> ' . e(date('d M Y, H:i:s')) . '<br>
            <strong>IP Address:</strong> ' . e($ip) . '<br>
            <strong>Device:</strong> ' . e(substr($ua, 0, 100)) . '
        </div>
        <p>If this was you, no action is needed.</p>
        <p>If you did not log in, please <a href="' . APP_BASE_URL . '/password-reset.php">reset your password</a> immediately and contact support.</p>
    ');
    send_email($email, 'New Login to Your ' . APP_NAME . ' Account', $html);
}

/**
 * Send order confirmation email.
 */
function send_order_confirmation_email(string $email, string $name, array $order): void {
    $html = email_template('Order Confirmed', '
        <p>Hi <strong>' . e($name) . '</strong>,</p>
        <p>Thank you for your order! We\'ve received it and it\'s being processed.</p>
        <div class="info-box">
            <strong>Order #:</strong> ' . e($order['order_number']) . '<br>
            <strong>Total:</strong> ' . CURRENCY_SYMBOL . ' ' . e(number_format($order['total_amount'], 2)) . '<br>
            <strong>Status:</strong> ' . e(ucfirst($order['status'])) . '
        </div>
        <a href="' . APP_BASE_URL . '/order-detail.php?id=' . (int)$order['id'] . '" class="btn">View Order</a>
        <p>We\'ll send you another email when your order is shipped.</p>
    ');
    send_email($email, 'Order Confirmed — #' . $order['order_number'], $html);
}

/**
 * Send account created by admin notification (bulk user creation).
 */
function send_bulk_account_email(string $email, string $name, string $temp_password): void {
    $html = email_template('Your Account Has Been Created', '
        <p>Hi <strong>' . e($name) . '</strong>,</p>
        <p>An account has been created for you on <strong>' . APP_NAME . '</strong>.</p>
        <div class="info-box">
            <strong>Email:</strong> ' . e($email) . '<br>
            <strong>Temporary Password:</strong> <code>' . e($temp_password) . '</code>
        </div>
        <p><strong>You must change your password on first login.</strong></p>
        <a href="' . APP_BASE_URL . '/login.php" class="btn">Login Now</a>
        <p style="font-size:13px;color:#888">Please keep your credentials safe and do not share them.</p>
    ');
    send_email($email, 'Your ' . APP_NAME . ' Account Details', $html);
}

/**
 * Send password change notification.
 */
function send_password_changed_email(string $email, string $name): void {
    $html = email_template('Password Changed', '
        <p>Hi <strong>' . e($name) . '</strong>,</p>
        <p>Your password was successfully changed on <strong>' . e(date('d M Y, H:i')) . '</strong>.</p>
        <p>If you did not make this change, please contact support immediately.</p>
        <a href="' . APP_BASE_URL . '/password-reset.php" class="btn">Reset Password Now</a>
    ');
    send_email($email, 'Password Changed — ' . APP_NAME, $html);
}

/**
 * Send order status update email.
 */
function send_order_status_email(string $email, string $name, array $order, string $new_status): void {
    $status_messages = [
        'confirmed'  => 'Your order has been confirmed and will be processed soon.',
        'processing' => 'Your order is being prepared for shipment.',
        'shipped'    => 'Great news — your order is on its way!',
        'delivered'  => 'Your order has been delivered. Enjoy!',
        'cancelled'  => 'Your order has been cancelled. If this is unexpected, please contact support.',
    ];
    $msg = $status_messages[$new_status] ?? 'Your order status has been updated.';

    $html = email_template('Order Update — #' . $order['order_number'], '
        <p>Hi <strong>' . e($name) . '</strong>,</p>
        <p>' . e($msg) . '</p>
        <div class="info-box">
            <strong>Order #:</strong> ' . e($order['order_number']) . '<br>
            <strong>New Status:</strong> ' . e(ucfirst($new_status)) . '
        </div>
        <a href="' . APP_BASE_URL . '/order-detail.php?id=' . (int)$order['id'] . '" class="btn">Track Order</a>
    ');
    send_email($email, 'Order Update — #' . $order['order_number'], $html);
}
