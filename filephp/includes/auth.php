<?php
/**
 * SecureShop - Authentication & Session Management
 *
 * - Secure session start with all flags
 * - Session timeout & regeneration
 * - Role-based access control
 * - Remember-me with long-lived DB tokens
 * - Login / logout lifecycle
 */

if (!defined('ECOMMERCE_APP')) {
    http_response_code(403); exit('Forbidden');
}

// ===== Session Bootstrap =====

/**
 * Start a hardened PHP session.
 * Must be called BEFORE any output on every page.
 */
function session_start_secure(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    session_name(SESSION_NAME);

    session_set_cookie_params([
        'lifetime' => 0,                      // Session cookie (expires on browser close)
        'path'     => '/',
        'domain'   => '',
        'secure'   => SESSION_COOKIE_SECURE,
        'httponly' => SESSION_COOKIE_HTTPONLY,
        'samesite' => SESSION_COOKIE_SAMESITE,
    ]);

    session_start();

    // Regenerate session ID periodically to prevent session fixation
    if (empty($_SESSION['_last_regen'])) {
        $_SESSION['_last_regen'] = time();
    } elseif (time() - $_SESSION['_last_regen'] > SESSION_REGENERATE_EVERY) {
        session_regenerate_id(true);
        $_SESSION['_last_regen'] = time();
    }

    // Session timeout check
    if (!empty($_SESSION['user_id'])) {
        _check_session_timeout();
    }

    // Check remember-me cookie on first load (if not already logged in)
    if (empty($_SESSION['user_id'])) {
        _check_remember_me();
    }
}

/**
 * Check and enforce session idle timeout.
 */
function _check_session_timeout(): void {
    if (empty($_SESSION['_last_activity'])) {
        $_SESSION['_last_activity'] = time();
        return;
    }

    $idle = time() - (int)$_SESSION['_last_activity'];
    if ($idle > SESSION_LIFETIME) {
        $user_id = $_SESSION['user_id'] ?? null;
        session_logout_cleanup();
        log_security('SESSION_TIMEOUT', ['user_id' => $user_id, 'idle_seconds' => $idle]);
        return;
    }

    $_SESSION['_last_activity'] = time();
}

/**
 * Check for a valid remember-me cookie and auto-login.
 */
function _check_remember_me(): void {
    if (empty($_COOKIE['remember_token'])) return;

    $raw_token  = $_COOKIE['remember_token'];
    $token_hash = hash_token($raw_token);

    $row = db_fetch_one(
        "SELECT rt.user_id, rt.expires_at, u.id, u.role, u.is_active, u.must_change_password
         FROM remember_tokens rt
         JOIN users u ON u.id = rt.user_id
         WHERE rt.token_hash = ? AND rt.used_at IS NULL AND rt.expires_at > NOW()
         LIMIT 1",
        's', [$token_hash]
    );

    if (!$row) {
        // Invalid/expired token — clear the cookie
        setcookie('remember_token', '', time() - 3600, '/', '', SESSION_COOKIE_SECURE, true);
        return;
    }

    if (!$row['is_active']) {
        setcookie('remember_token', '', time() - 3600, '/', '', SESSION_COOKIE_SECURE, true);
        return;
    }

    // Mark token as used (single-use) and issue a new one
    db_execute("UPDATE remember_tokens SET used_at=NOW() WHERE token_hash=?", 's', [$token_hash]);

    // Re-login the user
    _create_session($row['user_id'], $row['role'], $row['must_change_password']);
    _issue_remember_me_token($row['user_id']);

    log_security('REMEMBER_ME_LOGIN', ['user_id' => $row['user_id']]);
}

// ===== Login / Logout =====

/**
 * Log a user in: create session, update DB, issue remember-me if requested.
 *
 * @param int    $user_id
 * @param string $role
 * @param bool   $must_change_pwd
 * @param bool   $remember_me
 */
function login_user(int $user_id, string $role, bool $must_change_pwd = false, bool $remember_me = false): void {
    // Rotate session ID on privilege escalation (prevents session fixation)
    session_regenerate_id(true);

    _create_session($user_id, $role, $must_change_pwd);

    // Update last login info
    $ip = get_client_ip();
    db_execute(
        "UPDATE users SET last_login_at=NOW(), last_login_ip=? WHERE id=?",
        'si', [$ip, $user_id]
    );

    // Record in sessions table
    db_execute(
        "INSERT INTO sessions (user_id,session_token,ip_address,user_agent,expires_at)
         VALUES (?,?,?,?,DATE_ADD(NOW(), INTERVAL ? SECOND))
         ON DUPLICATE KEY UPDATE last_activity=NOW()",
        'isssi',
        [$user_id, session_id(), $ip, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500), SESSION_LIFETIME]
    );

    if ($remember_me) {
        _issue_remember_me_token($user_id);
    }

    log_security('LOGIN_SUCCESS', ['user_id' => $user_id, 'role' => $role]);
}

/**
 * Create the in-memory session variables.
 */
function _create_session(int $user_id, string $role, bool $must_change_pwd): void {
    $_SESSION['user_id']          = $user_id;
    $_SESSION['role']             = $role;
    $_SESSION['must_change_pwd']  = $must_change_pwd;
    $_SESSION['_last_activity']   = time();
    $_SESSION['_last_regen']      = time();

    // Ensure CSRF token is fresh for the new session
    csrf_rotate();
}

/**
 * Issue a remember-me cookie (single-use DB token).
 */
function _issue_remember_me_token(int $user_id): void {
    $token      = generate_secure_token(TOKEN_LENGTH);
    $token_hash = hash_token($token);
    $expires    = date('Y-m-d H:i:s', time() + REMEMBER_ME_EXPIRY);

    db_execute(
        "INSERT INTO remember_tokens (user_id,token_hash,expires_at) VALUES (?,?,?)",
        'iss', [$user_id, $token_hash, $expires]
    );

    setcookie(
        'remember_token',
        $token,
        time() + REMEMBER_ME_EXPIRY,
        '/',
        '',
        SESSION_COOKIE_SECURE,
        true // HttpOnly
    );
}

/**
 * Destroy the session and clear cookies.
 */
function logout_user(): void {
    $user_id = $_SESSION['user_id'] ?? null;

    if ($user_id) {
        // Invalidate DB session record
        db_execute("UPDATE sessions SET is_active=0 WHERE session_token=?", 's', [session_id()]);

        // Remove remember-me cookie and its DB record
        if (!empty($_COOKIE['remember_token'])) {
            $hash = hash_token($_COOKIE['remember_token']);
            db_execute("DELETE FROM remember_tokens WHERE token_hash=?", 's', [$hash]);
            setcookie('remember_token', '', time() - 3600, '/', '', SESSION_COOKIE_SECURE, true);
        }

        log_security('LOGOUT', ['user_id' => $user_id]);
    }

    session_logout_cleanup();
}

/**
 * Clean up PHP session data and cookie.
 */
function session_logout_cleanup(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '',
            time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

// ===== Role-Based Access Control =====

/**
 * Check if the current user is logged in.
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * Get the current user's role.
 */
function current_role(): string {
    return $_SESSION['role'] ?? '';
}

/**
 * Get the current user's ID.
 */
function current_user_id(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

/**
 * Check if current user has a specific role.
 */
function has_role(string $role): bool {
    return ($_SESSION['role'] ?? '') === $role;
}

/**
 * Check if user is admin.
 */
function is_admin(): bool { return has_role('admin'); }

/**
 * Check if user is shopkeeper.
 */
function is_shopkeeper(): bool { return has_role('shopkeeper'); }

/**
 * Require authentication. Redirects to login if not logged in.
 *
 * @param string $redirect  URL to return to after login
 */
function require_auth(string $redirect = ''): void {
    if (!is_logged_in()) {
        $back = $redirect ?: $_SERVER['REQUEST_URI'] ?? '';
        $query = $back ? '?next=' . urlencode($back) : '';
        header('Location: login.php' . $query);
        exit;
    }

    // Force password change if flagged
    if ($_SESSION['must_change_pwd'] ?? false) {
        $current = basename($_SERVER['PHP_SELF']);
        if ($current !== 'profile.php' && $current !== 'logout.php') {
            $_SESSION['flash_warning'] = 'You must change your password before continuing.';
            header('Location: profile.php?action=change_password');
            exit;
        }
    }
}

/**
 * Require a specific role. Redirects with 403 message if not matching.
 *
 * @param string|array $role  Required role(s)
 */
function require_role($role): void {
    require_auth();
    $roles = (array)$role;
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        log_security('UNAUTHORIZED_ACCESS', [
            'required_role' => implode('|', $roles),
            'user_role'     => $_SESSION['role'] ?? 'none',
            'uri'           => $_SERVER['REQUEST_URI'] ?? '',
        ]);
        http_response_code(403);
        $_SESSION['flash_error'] = 'You do not have permission to access that page.';
        header('Location: dashboard.php');
        exit;
    }
}

/**
 * Require admin role.
 */
function require_admin(): void { require_role('admin'); }

/**
 * Require shopkeeper or admin role.
 */
function require_shopkeeper(): void { require_role(['admin', 'shopkeeper']); }

// ===== Current User Data =====

/**
 * Fetch the current logged-in user's full record from DB.
 * Cached for the duration of the request.
 */
function get_auth_user(): ?array {
    static $user_cache = null;
    if (!is_logged_in()) return null;
    if ($user_cache !== null) return $user_cache;

    $user_cache = db_fetch_one(
        "SELECT id, username, email, first_name, last_name, role, avatar,
                phone, is_active, email_verified, must_change_password, created_at
         FROM users WHERE id = ? AND is_active = 1 LIMIT 1",
        'i', [current_user_id()]
    );

    if (!$user_cache) {
        // Account deactivated mid-session
        logout_user();
        header('Location: login.php?reason=deactivated');
        exit;
    }

    return $user_cache;
}

/**
 * Get remaining session time in seconds.
 */
function session_remaining_seconds(): int {
    if (empty($_SESSION['_last_activity'])) return 0;
    return max(0, SESSION_LIFETIME - (time() - (int)$_SESSION['_last_activity']));
}

// ===== OTP & Phone / Email Auth Helpers =====

/**
 * Find user by email, phone, or username.
 */
function find_user_by_identifier(string $identifier): ?array {
    $clean = trim($identifier);
    if (!$clean) return null;

    return db_fetch_one(
        "SELECT id, username, email, phone, password_hash, role, is_active,
                first_name, last_name, email_verified, phone_verified, must_change_password
         FROM users
         WHERE (email = ? OR phone = ? OR username = ?) AND is_active = 1
         LIMIT 1",
        'sss', [$clean, $clean, $clean]
    );
}

/**
 * Generate and store a secure 6-digit OTP.
 */
function otp_generate(string $identifier, string $type = 'register', ?array $temp_data = null): string {
    $identifier = trim($identifier);
    $code       = sprintf('%06d', random_int(100000, 999999));
    $json_data  = $temp_data ? json_encode($temp_data, JSON_UNESCAPED_UNICODE) : null;

    // Invalidate existing unused OTPs of this type
    db_execute(
        "UPDATE otps SET is_used = 1 WHERE identifier = ? AND type = ? AND is_used = 0",
        'ss', [$identifier, $type]
    );

    // Insert new OTP with 10 minute expiry
    db_insert(
        "INSERT INTO otps (identifier, type, otp_code, temp_data, expires_at, is_used, created_at)
         VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 0, NOW())",
        'ssss', [$identifier, $type, $code, $json_data]
    );

    // Store in session for easy testing display
    $_SESSION['last_otp_code']       = $code;
    $_SESSION['last_otp_identifier'] = $identifier;
    $_SESSION['last_otp_time']       = time();

    otp_send($identifier, $code, $type);

    return $code;
}

/**
 * Dispatch OTP via Email or SMS simulation.
 */
function otp_send(string $identifier, string $code, string $type = 'register'): void {
    log_security('OTP_GENERATED', ['identifier' => $identifier, 'type' => $type]);

    // Send email if identifier is an email address and mail is enabled
    if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
        $subject = 'Your ' . APP_NAME . ' Verification Code: ' . $code;
        $body    = "<p>Hello,</p><p>Your verification code for <strong>" . APP_NAME . "</strong> is: <strong style='font-size:24px; color:#D4AF37;'>{$code}</strong></p><p>This code expires in 10 minutes. If you did not request this, please disregard.</p>";
        if (function_exists('send_mail')) {
            @send_mail($identifier, $subject, $body);
        }
    }

    // Set flash notice with test OTP so developer/user can seamlessly copy-paste on any machine
    flash('info', "🔐 Verification Code sent to <strong>" . e($identifier) . "</strong>. Demo Code: <strong class='text-warning fs-5'>{$code}</strong>");
}

/**
 * Verify submitted 6-digit OTP. Returns stored temp_data on success, or null on failure.
 */
function otp_verify(string $identifier, string $code, string $type = 'register'): ?array {
    $identifier = trim($identifier);
    $code       = trim($code);

    if (!$identifier || !$code) return null;

    $row = db_fetch_one(
        "SELECT id, otp_code, temp_data, expires_at
         FROM otps
         WHERE identifier = ? AND type = ? AND is_used = 0 AND expires_at > NOW()
         ORDER BY id DESC LIMIT 1",
        'ss', [$identifier, $type]
    );

    if (!$row) {
        log_security('OTP_VERIFY_NOT_FOUND_OR_EXPIRED', ['identifier' => $identifier, 'type' => $type]);
        return null;
    }

    if (!hash_equals($row['otp_code'], $code)) {
        log_security('OTP_VERIFY_MISMATCH', ['identifier' => $identifier, 'type' => $type]);
        return null;
    }

    // Mark OTP as used
    db_execute("UPDATE otps SET is_used = 1 WHERE id = ?", 'i', [$row['id']]);
    log_security('OTP_VERIFIED_SUCCESS', ['identifier' => $identifier, 'type' => $type]);

    $temp_data = $row['temp_data'] ? json_decode($row['temp_data'], true) : [];
    return is_array($temp_data) ? $temp_data : [];
}
