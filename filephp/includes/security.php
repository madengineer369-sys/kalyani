<?php
/**
 * SecureShop - Security Module
 *
 * - Secure HTTP headers (CSP, HSTS, X-Frame-Options, etc.)
 * - Rate limiting (DB-backed sliding window)
 * - Account lockout (progressive delays)
 * - Safe redirect (whitelist-based)
 * - File upload validation
 * - XSS output escaping
 */

if (!defined('ECOMMERCE_APP')) {
    http_response_code(403); exit('Forbidden');
}

// ===== HTTP Security Headers =====

/**
 * Send all security-related HTTP headers.
 * Call this BEFORE any output.
 *
 * @param string $nonce  CSP nonce (generated per request for inline scripts/styles)
 */
function send_security_headers(string $nonce = ''): void {
    // Content-Security-Policy
    $csp = "default-src 'self'; "
         . "script-src 'self' https://cdn.jsdelivr.net" . ($nonce ? " 'nonce-{$nonce}'" : '') . "; "
         . "style-src 'self' https://cdn.jsdelivr.net https://fonts.googleapis.com" . ($nonce ? " 'nonce-{$nonce}'" : '') . "; "
         . "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; "
         . "img-src 'self' data: blob:; "
         . "connect-src 'self'; "
         . "form-action 'self'; "
         . "frame-ancestors 'none'; "
         . "base-uri 'self'; "
         . "object-src 'none'";

    header("Content-Security-Policy: {$csp}");
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('X-Permitted-Cross-Domain-Policies: none');

    // HSTS (only when HTTPS is actually in use)
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }

    // Remove server fingerprinting headers
    header_remove('X-Powered-By');
    header_remove('Server');

    // Cache control for sensitive pages
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');
}

/**
 * Generate a cryptographically secure CSP nonce for this request.
 */
function generate_csp_nonce(): string {
    static $nonce = null;
    if (!$nonce) {
        $nonce = base64_encode(random_bytes(CSP_NONCE_LENGTH));
    }
    return $nonce;
}

// ===== Rate Limiting =====

/**
 * Check whether an action is rate-limited for an identifier (IP or user_id).
 *
 * @param string $action    Slug like 'login', 'password_reset', 'register'
 * @param string $id        IP address or user email
 * @param int    $max       Max attempts allowed
 * @param int    $window    Time window in seconds
 * @return bool             TRUE = rate limited (block the action)
 */
function rate_limit_check(string $action, string $id, int $max, int $window): bool {
    $id = substr($id, 0, 100);

    // Get or create rate-limit record
    $row = db_fetch_one(
        "SELECT id, attempts, window_start FROM rate_limits WHERE action=? AND identifier=? LIMIT 1",
        'ss', [$action, $id]
    );

    $now = time();

    if (!$row) {
        // First attempt
        db_execute(
            "INSERT INTO rate_limits (action,identifier,attempts,window_start,last_attempt) VALUES (?,?,1,NOW(),NOW())
             ON DUPLICATE KEY UPDATE attempts=1, window_start=NOW(), last_attempt=NOW()",
            'ss', [$action, $id]
        );
        return false;
    }

    $window_start = strtotime($row['window_start']);

    // Window expired — reset
    if (($now - $window_start) > $window) {
        db_execute(
            "UPDATE rate_limits SET attempts=1, window_start=NOW(), last_attempt=NOW() WHERE id=?",
            'i', [$row['id']]
        );
        return false;
    }

    // Within window — increment
    db_execute(
        "UPDATE rate_limits SET attempts=attempts+1, last_attempt=NOW() WHERE id=?",
        'i', [$row['id']]
    );

    if ((int)$row['attempts'] + 1 > $max) {
        log_security('RATE_LIMIT_EXCEEDED', ['action' => $action, 'id' => $id]);
        return true; // blocked
    }

    return false;
}

/**
 * Reset rate-limit counter (call after successful login, etc.).
 */
function rate_limit_reset(string $action, string $id): void {
    db_execute(
        "DELETE FROM rate_limits WHERE action=? AND identifier=?",
        'ss', [$action, $id]
    );
}

// ===== Account Lockout =====

/**
 * Check if an account is currently locked.
 *
 * @param string $email  User email or username
 * @return bool          TRUE = account is locked
 */
function account_is_locked(string $email): bool {
    $user = db_fetch_one(
        "SELECT locked_until FROM users WHERE email=? OR username=? LIMIT 1",
        'ss', [$email, $email]
    );

    if (!$user || !$user['locked_until']) return false;
    return strtotime($user['locked_until']) > time();
}

/**
 * Get seconds remaining until account unlock.
 */
function account_lock_remaining(string $email): int {
    $user = db_fetch_one(
        "SELECT locked_until FROM users WHERE email=? OR username=? LIMIT 1",
        'ss', [$email, $email]
    );
    if (!$user || !$user['locked_until']) return 0;
    return max(0, (int)(strtotime($user['locked_until']) - time()));
}

/**
 * Record a failed login attempt and apply lockout if threshold reached.
 *
 * @param string $email     Attempted identifier
 * @param string $ip        Client IP
 */
function record_failed_login(string $email, string $ip): void {
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

    // Log to failed_logins table
    db_execute(
        "INSERT INTO failed_logins (identifier,ip_address,user_agent,attempted_at) VALUES (?,?,?,NOW())",
        'sss', [$email, $ip, $ua]
    );

    // Update user's failed attempt counter
    $user = db_fetch_one(
        "SELECT id, failed_login_attempts, locked_until FROM users WHERE email=? OR username=? LIMIT 1",
        'ss', [$email, $email]
    );

    if (!$user) return;

    $attempts = (int)$user['failed_login_attempts'] + 1;

    if ($attempts >= LOCKOUT_THRESHOLD) {
        // Progressive lockout: each lock doubles the duration
        $lock_count = intdiv($attempts, LOCKOUT_THRESHOLD);
        $duration   = LOCKOUT_BASE_DURATION;
        if (LOCKOUT_PROGRESSIVE && $lock_count > 1) {
            $duration = min($duration * (2 ** ($lock_count - 1)), 86400); // max 24h
        }
        $locked_until = date('Y-m-d H:i:s', time() + $duration);

        db_execute(
            "UPDATE users SET failed_login_attempts=?, locked_until=? WHERE id=?",
            'isi', [$attempts, $locked_until, $user['id']]
        );

        log_security('ACCOUNT_LOCKED', ['user_id' => $user['id'], 'attempts' => $attempts, 'duration' => $duration]);
    } else {
        db_execute(
            "UPDATE users SET failed_login_attempts=? WHERE id=?",
            'ii', [$attempts, $user['id']]
        );
    }
}

/**
 * Reset failed login counter on successful login.
 */
function reset_failed_logins(int $user_id): void {
    db_execute(
        "UPDATE users SET failed_login_attempts=0, locked_until=NULL WHERE id=?",
        'i', [$user_id]
    );
}

// ===== Safe Redirect =====

/**
 * Redirect to a URL only if it's in the allowed whitelist.
 * Prevents open-redirect attacks.
 *
 * @param string $url       Destination (relative path)
 * @param string $fallback  Safe fallback URL
 */
function safe_redirect(string $url, string $fallback = 'index.php'): void {
    $url = trim($url);

    // Strip scheme/host to get relative path
    $parsed  = parse_url($url);
    $path    = $parsed['path'] ?? '';
    $relpath = ltrim($path, '/');

    // Remove query and fragment for whitelist check
    $check = $relpath;

    $allowed = false;
    foreach (ALLOWED_REDIRECTS as $allowed_path) {
        if ($check === $allowed_path || strpos($check, $allowed_path) === 0) {
            $allowed = true;
            break;
        }
    }

    if (!$allowed) {
        log_security('OPEN_REDIRECT_ATTEMPT', ['url' => $url]);
        $url = $fallback;
    }

    // Rebuild with query string if same-origin
    $query = !empty($parsed['query']) ? '?' . $parsed['query'] : '';
    header('Location: ' . $url . $query);
    exit;
}

// ===== File Upload Validation =====

/**
 * Validate an uploaded file's type, size, and extension.
 *
 * @param array  $file          $_FILES['field'] entry
 * @param array  $allowed_types Allowed MIME types
 * @param array  $allowed_exts  Allowed extensions
 * @param int    $max_size      Max file size in bytes
 * @return array ['ok' => bool, 'error' => string, 'ext' => string, 'mime' => string]
 */
function validate_file_upload(array $file, array $allowed_types, array $allowed_exts, int $max_size): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server size limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'Upload blocked by extension.',
        ];
        return ['ok' => false, 'error' => $errors[$file['error']] ?? 'Unknown upload error.'];
    }

    // Check actual file size
    if ($file['size'] > $max_size) {
        return ['ok' => false, 'error' => 'File too large. Max ' . round($max_size / 1048576, 1) . 'MB.'];
    }

    // Validate MIME type using finfo (not just the browser-supplied type)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowed_types, true)) {
        log_security('INVALID_UPLOAD_MIME', ['mime' => $mime, 'file' => $file['name']]);
        return ['ok' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $allowed_exts)];
    }

    // Validate extension (from original filename — after mime check)
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_exts, true)) {
        log_security('INVALID_UPLOAD_EXT', ['ext' => $ext, 'file' => $file['name']]);
        return ['ok' => false, 'error' => 'Invalid file extension.'];
    }

    return ['ok' => true, 'error' => '', 'ext' => $ext, 'mime' => $mime];
}

/**
 * Generate a secure, sanitised filename for an uploaded file.
 */
function generate_upload_filename(string $ext): string {
    return bin2hex(random_bytes(16)) . '.' . $ext;
}

// ===== XSS Output Escaping =====

/**
 * Escape value for safe HTML output (primary XSS defence).
 * Use this for EVERY variable you echo into HTML.
 */
function e($val): string {
    return htmlspecialchars((string)$val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Escape value for use inside a JavaScript string literal.
 */
function js_escape($val): string {
    return json_encode((string)$val, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
}

// ===== Miscellaneous Security Utilities =====

/**
 * Constant-time string comparison to prevent timing attacks.
 */
function secure_compare(string $a, string $b): bool {
    return hash_equals($a, $b);
}

/**
 * Generate a cryptographically secure random token.
 */
function generate_secure_token(int $bytes = 32): string {
    return bin2hex(random_bytes($bytes));
}

/**
 * Hash a token for storage (never store raw tokens in DB).
 */
function hash_token(string $token): string {
    return hash('sha256', $token);
}
