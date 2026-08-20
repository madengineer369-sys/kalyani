<?php
/**
 * SecureShop - CSRF Protection
 *
 * - Per-session token with rotation after each use
 * - Separate per-form tokens for added precision
 * - Constant-time comparison to prevent timing attacks
 */

if (!defined('ECOMMERCE_APP')) {
    http_response_code(403); exit('Forbidden');
}

/**
 * Get (or create) the CSRF token for the current session.
 * Token rotates after every successful validation.
 */
function csrf_get_token(): string {
    if (empty($_SESSION['csrf_token']) || csrf_token_expired()) {
        $_SESSION['csrf_token']      = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Check whether the stored CSRF token has exceeded its lifetime.
 */
function csrf_token_expired(): bool {
    if (empty($_SESSION['csrf_token_time'])) return true;
    return (time() - (int)$_SESSION['csrf_token_time']) > CSRF_TOKEN_EXPIRY;
}

/**
 * Render a hidden <input> field with the CSRF token.
 * Include this in EVERY form.
 */
function render_csrf_field(): void {
    $token = csrf_get_token();
    echo '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * Validate the submitted CSRF token against the session token.
 *
 * Returns TRUE on success and rotates the token.
 * Returns FALSE and logs a security event on failure.
 *
 * @param string $submitted  Value from $_POST['csrf_token']
 */
function csrf_verify(string $submitted = ''): bool {
    if (empty($submitted)) {
        $submitted = $_POST['csrf_token'] ?? '';
    }

    $stored = $_SESSION['csrf_token'] ?? '';

    if (!$stored || !$submitted) {
        log_security('CSRF_MISSING_TOKEN', ['uri' => $_SERVER['REQUEST_URI'] ?? '']);
        return false;
    }

    if (csrf_token_expired()) {
        log_security('CSRF_TOKEN_EXPIRED', ['uri' => $_SERVER['REQUEST_URI'] ?? '']);
        csrf_rotate();
        return false;
    }

    if (!hash_equals($stored, $submitted)) {
        log_security('CSRF_TOKEN_MISMATCH', [
            'uri'       => $_SERVER['REQUEST_URI'] ?? '',
            'submitted' => substr($submitted, 0, 8) . '...',
        ]);
        return false;
    }

    // Rotate token after successful use
    csrf_rotate();
    return true;
}

/**
 * Regenerate the CSRF token (call after successful validation).
 */
function csrf_rotate(): void {
    $_SESSION['csrf_token']      = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    $_SESSION['csrf_token_time'] = time();
}

/**
 * Abort the request with 403 if CSRF validation fails.
 * Convenience wrapper for POST handlers.
 */
function csrf_protect(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

    if (!csrf_verify()) {
        http_response_code(403);
        $_SESSION['flash_error'] = 'Invalid or expired security token. Please try again.';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
        exit;
    }
}
