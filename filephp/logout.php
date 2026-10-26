<?php
/**
 * SecureShop - Logout Handler
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

session_start_secure();
logout_user();

$reason = sanitize_enum($_GET['reason'] ?? '', ['timeout', 'deactivated']);
$messages = [
    'timeout'     => 'Your session expired due to inactivity.',
    'deactivated' => 'Your account has been deactivated. Contact support.',
];

if ($reason && isset($messages[$reason])) {
    // Store flash in a new session briefly
    session_start();
    flash('warning', $messages[$reason]);
    session_write_close();
}

header('Location: login.php');
exit;
