<?php
/**
 * SecureShop - Application Configuration
 *
 * SECURITY: This file must NEVER be served directly by the web server.
 * The .htaccess in includes/ blocks direct access.
 */

// Guard: must be loaded through the app, not directly accessed
if (!defined('ECOMMERCE_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

// ================================================================
// ENVIRONMENT
// ================================================================
define('APP_ENV',      'development');           // 'development' | 'production'
define('APP_DEBUG',    APP_ENV === 'development');
define('APP_NAME',     'SecureShop');
define('APP_TAGLINE',  'Secure E-Commerce Platform');
define('APP_VERSION',  '1.0.0');

// IMPORTANT: Update to your actual base URL (no trailing slash)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
define('APP_BASE_URL', $protocol . '://' . $host);

// ================================================================
// PATHS
// ================================================================
define('APP_ROOT',      dirname(__DIR__));
define('INCLUDES_PATH', APP_ROOT . DIRECTORY_SEPARATOR . 'includes');
define('UPLOAD_PATH',   APP_ROOT . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products');
define('LOG_PATH',      APP_ROOT . DIRECTORY_SEPARATOR . 'logs');

// ================================================================
// TIMEZONE
// ================================================================
define('APP_TIMEZONE', 'Asia/Kolkata');
date_default_timezone_set(APP_TIMEZONE);

// ================================================================
// DATABASE
// ================================================================
define('DB_HOST',    '127.0.0.1');
define('DB_PORT',    3306);
define('DB_NAME',    'ecommerce_db');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

// ================================================================
// SESSION SECURITY
// ================================================================
define('SESSION_NAME',            'SECURESHOP_SID');
define('SESSION_LIFETIME',        1800);    // 30 min idle timeout (seconds)
define('SESSION_WARNING_TIME',    300);     // Warn 5 min before expiry
define('SESSION_COOKIE_SECURE',   false);   // Set TRUE in production (HTTPS)
define('SESSION_COOKIE_HTTPONLY', true);
define('SESSION_COOKIE_SAMESITE', 'Strict');
define('SESSION_REGENERATE_EVERY', 300);   // Regenerate session ID every 5 min

// ================================================================
// PASSWORD & CRYPTO
// ================================================================
define('BCRYPT_COST',         12);
define('CSRF_TOKEN_LENGTH',   32);
define('CSRF_TOKEN_EXPIRY',   3600);
define('TOKEN_LENGTH',        64);
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_MAX_LENGTH', 128);

// ================================================================
// RATE LIMITING
// ================================================================
define('LOGIN_MAX_ATTEMPTS',     5);
define('LOGIN_RATE_WINDOW',      900);    // 15 minutes
define('RESET_MAX_ATTEMPTS',     3);
define('RESET_RATE_WINDOW',      3600);   // 1 hour
define('REGISTER_MAX_ATTEMPTS',  3);
define('REGISTER_RATE_WINDOW',   3600);

// ================================================================
// ACCOUNT LOCKOUT
// ================================================================
define('LOCKOUT_THRESHOLD',       5);     // failures before lock
define('LOCKOUT_BASE_DURATION',   900);   // 15 min base lockout
define('LOCKOUT_PROGRESSIVE',     true);  // doubles each re-lock

// ================================================================
// TOKEN EXPIRY
// ================================================================
define('PASSWORD_RESET_EXPIRY',  3600);        // 1 hour
define('REMEMBER_ME_EXPIRY',     2592000);     // 30 days
define('EMAIL_VERIFY_EXPIRY',    86400);       // 24 hours

// ================================================================
// FILE UPLOAD
// ================================================================
define('MAX_UPLOAD_SIZE',            5 * 1024 * 1024); // 5 MB
define('ALLOWED_IMAGE_TYPES',        ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
define('ALLOWED_IMAGE_EXTENSIONS',   ['jpg', 'jpeg', 'png', 'webp', 'gif']);

// ================================================================
// LOGGING
// ================================================================
define('LOG_LEVEL_DEBUG',    0);
define('LOG_LEVEL_INFO',     1);
define('LOG_LEVEL_WARNING',  2);
define('LOG_LEVEL_ERROR',    3);
define('LOG_LEVEL_CRITICAL', 4);
define('LOG_MIN_LEVEL',      APP_DEBUG ? LOG_LEVEL_DEBUG : LOG_LEVEL_INFO);
define('LOG_MAX_FILE_SIZE',  10 * 1024 * 1024);  // 10 MB per file
define('LOG_RETENTION_DAYS', 30);

// ================================================================
// PAGINATION
// ================================================================
define('ITEMS_PER_PAGE',       12);
define('ADMIN_ITEMS_PER_PAGE', 25);

// ================================================================
// EMAIL
// ================================================================
define('MAIL_FROM',      'noreply@secureshop.local');
define('MAIL_FROM_NAME', APP_NAME);
define('MAIL_REPLY_TO',  'support@secureshop.local');
define('MAIL_ENABLED',   false);   // Set true when mail server is configured

// ================================================================
// E-COMMERCE
// ================================================================
define('CURRENCY_SYMBOL',    '₹');
define('CURRENCY_CODE',      'INR');
define('LOW_STOCK_THRESHOLD', 5);
define('DEFAULT_TAX_RATE',    18.0);    // GST %
define('FREE_SHIPPING_ABOVE', 500.00);  // Free shipping threshold
define('SHIPPING_CHARGE',     60.00);   // Flat shipping below threshold

// ================================================================
// ALLOWED REDIRECT DESTINATIONS  (open-redirect prevention)
// ================================================================
define('ALLOWED_REDIRECTS', [
    '', '/', 'index.php', 'login.php', 'register.php',
    'dashboard.php', 'cart.php', 'checkout.php',
    'order-history.php', 'order-detail.php',
    'profile.php', 'search.php', 'password-reset.php',
    'password-reset-confirm.php',
    'admin/', 'admin/index.php',
    'shopkeeper/', 'shopkeeper/index.php',
]);

// ================================================================
// CONTENT SECURITY POLICY
// ================================================================
define('CSP_NONCE_LENGTH', 16);

// ================================================================
// ERROR HANDLING
// ================================================================
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');   // Never display to user
    ini_set('log_errors',     '1');
    ini_set('error_log',      LOG_PATH . '/php_error.log');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors',     '1');
    ini_set('error_log',      LOG_PATH . '/php_error.log');
}

// Prevent session fixation globally
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
