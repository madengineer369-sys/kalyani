<?php
/**
 * SecureShop - Input Validation & Sanitisation (Whitelist Approach)
 *
 * Server-side validation for ALL user inputs.
 * Client-side validation is UX-only and NEVER trusted.
 *
 * Rule: Reject anything that doesn't explicitly match the whitelist.
 */

if (!defined('ECOMMERCE_APP')) {
    http_response_code(403); exit('Forbidden');
}

// ===== Core Sanitisation =====

/**
 * Trim and limit string length. Returns empty string if null/non-scalar.
 */
function sanitize_string($val, int $max_len = 255): string {
    if (!is_scalar($val)) return '';
    return mb_substr(trim((string)$val), 0, $max_len, 'UTF-8');
}

/**
 * Sanitise and validate an email address.
 * Returns lowercase email or empty string on failure.
 */
function sanitize_email($val): string {
    $val = strtolower(sanitize_string($val, 255));
    return filter_var($val, FILTER_VALIDATE_EMAIL) ? $val : '';
}

/**
 * Sanitise a username: alphanumeric + underscore + hyphen, 3–50 chars.
 */
function sanitize_username($val): string {
    $val = sanitize_string($val, 50);
    return preg_match('/^[a-zA-Z0-9_\-]{3,50}$/', $val) ? $val : '';
}

/**
 * Sanitise a name field (first name, last name, etc.).
 * Allows letters, spaces, hyphens, apostrophes.
 */
function sanitize_name($val, int $max = 100): string {
    $val = sanitize_string($val, $max);
    return preg_match('/^[\p{L}\s\'\-\.]{1,100}$/u', $val) ? $val : '';
}

/**
 * Sanitise a phone number: digits, +, -, spaces, parens. 7–20 chars.
 */
function sanitize_phone($val): string {
    $val = sanitize_string($val, 20);
    return preg_match('/^[\+\d\s\-\(\)]{7,20}$/', $val) ? $val : '';
}

/**
 * Sanitise a positive decimal/float (price, weight, rate).
 * Returns float or -1 on failure.
 */
function sanitize_price($val): float {
    $val = filter_var($val, FILTER_VALIDATE_FLOAT);
    return ($val !== false && $val >= 0 && $val < 9999999) ? round((float)$val, 2) : -1.0;
}

/**
 * Sanitise a positive integer (quantity, count, ID).
 * Returns int or -1 on failure.
 */
function sanitize_int($val, int $min = 0, int $max = PHP_INT_MAX): int {
    $val = filter_var($val, FILTER_VALIDATE_INT);
    if ($val === false) return -1;
    return ($val >= $min && $val <= $max) ? (int)$val : -1;
}

/**
 * Sanitise an ID (positive integer, no zero).
 */
function sanitize_id($val): int {
    $id = sanitize_int($val, 1, PHP_INT_MAX);
    return $id > 0 ? $id : 0;
}

/**
 * Sanitise a slug: lowercase letters, numbers, hyphens.
 */
function sanitize_slug($val): string {
    $val = sanitize_string($val, 280);
    return preg_match('/^[a-z0-9\-]{1,280}$/', $val) ? $val : '';
}

/**
 * Sanitise a ENUM-like value against a whitelist of allowed values.
 * Returns the value if allowed, or empty string.
 */
function sanitize_enum($val, array $allowed): string {
    $val = (string)$val;
    return in_array($val, $allowed, true) ? $val : '';
}

/**
 * Sanitise a sort direction.
 */
function sanitize_sort_dir($val): string {
    return sanitize_enum(strtoupper((string)$val), ['ASC', 'DESC']) ?: 'ASC';
}

/**
 * Sanitise a search query: strip dangerous characters, limit length.
 */
function sanitize_search($val): string {
    $val = sanitize_string($val, 200);
    // Remove SQL-dangerous special chars (they won't affect prepared stmts, but clean for display)
    return preg_replace('/[<>{}|\\\\^~\[\]`]/', '', $val);
}

/**
 * Sanitise a postal code (Indian PIN: 6 digits; adjust for international).
 */
function sanitize_postal_code($val): string {
    $val = sanitize_string($val, 20);
    // Allow alphanumeric and hyphen/space for international support
    return preg_match('/^[A-Z0-9\s\-]{3,10}$/i', $val) ? $val : '';
}

/**
 * Sanitise a text area (multi-line, strips HTML tags).
 */
function sanitize_textarea($val, int $max = 5000): string {
    $val = sanitize_string($val, $max);
    return strip_tags($val);
}

/**
 * Sanitise a discount code: uppercase alphanumeric.
 */
function sanitize_discount_code($val): string {
    $val = strtoupper(sanitize_string($val, 50));
    return preg_match('/^[A-Z0-9]{3,50}$/', $val) ? $val : '';
}

// ===== Password Validation =====

/**
 * Validate password strength.
 *
 * @return array ['valid' => bool, 'errors' => string[]]
 */
function validate_password(string $password): array {
    $errors = [];

    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'At least ' . PASSWORD_MIN_LENGTH . ' characters required.';
    }
    if (strlen($password) > PASSWORD_MAX_LENGTH) {
        $errors[] = 'Password too long (max ' . PASSWORD_MAX_LENGTH . ' chars).';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'At least one uppercase letter required.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'At least one lowercase letter required.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'At least one number required.';
    }
    if (!preg_match('/[\W_]/', $password)) {
        $errors[] = 'At least one special character required (!@#$%^&* etc.).';
    }

    return ['valid' => empty($errors), 'errors' => $errors];
}

/**
 * Calculate password strength score (0–4).
 * 0=very weak, 1=weak, 2=fair, 3=strong, 4=very strong
 */
function password_strength_score(string $password): int {
    $score = 0;
    if (strlen($password) >= 8)  $score++;
    if (strlen($password) >= 12) $score++;
    if (preg_match('/[A-Z]/', $password) && preg_match('/[a-z]/', $password)) $score++;
    if (preg_match('/[0-9]/', $password) && preg_match('/[\W_]/', $password)) $score++;
    return $score;
}

// ===== Form Error Collection =====

/**
 * Collect and display form validation errors.
 */
function validation_error(array &$errors, string $field, string $message): void {
    $errors[$field][] = $message;
}

/**
 * Get the first error for a field (for inline display).
 */
function field_error(array $errors, string $field): string {
    return $errors[$field][0] ?? '';
}

/**
 * Check whether any field has errors.
 */
function has_errors(array $errors): bool {
    return !empty($errors);
}

/**
 * Render Bootstrap validation feedback div for a field.
 */
function render_field_error(array $errors, string $field): void {
    $msg = field_error($errors, $field);
    if ($msg) {
        echo '<div class="invalid-feedback d-block">' . e($msg) . '</div>';
    }
}

/**
 * Return Bootstrap 'is-invalid' class if field has error.
 */
function field_class(array $errors, string $field): string {
    return isset($errors[$field]) ? 'is-invalid' : '';
}

// ===== URL / Redirect Validation =====

/**
 * Validate that a redirect 'next' parameter is a safe relative URL.
 */
function validate_redirect_url(string $url): string {
    $url = trim($url);
    // Must be relative (no scheme or host)
    if (preg_match('#^(https?:)?//#i', $url)) return '';
    // Only allow safe characters
    if (!preg_match('#^[a-zA-Z0-9/_\-\.?=&]+$#', $url)) return '';
    return $url;
}

// ===== Slug Generation =====

/**
 * Generate a URL slug from a string.
 */
function generate_slug(string $str): string {
    $str = strtolower(trim($str));
    $str = preg_replace('/[^a-z0-9\s\-]/', '', $str);
    $str = preg_replace('/[\s\-]+/', '-', $str);
    return trim($str, '-');
}

/**
 * Ensure a slug is unique in the given table.
 * Appends -1, -2, etc. if needed.
 */
function unique_slug(string $base_slug, string $table, int $exclude_id = 0): string {
    $slug  = $base_slug;
    $count = 1;
    while (true) {
        $where = 'slug = ?' . ($exclude_id ? ' AND id != ?' : '');
        $types = $exclude_id ? 'si' : 's';
        $params = $exclude_id ? [$slug, $exclude_id] : [$slug];
        $exists = db_fetch_value("SELECT COUNT(*) FROM `{$table}` WHERE {$where}", $types, $params);
        if (!$exists) break;
        $slug = $base_slug . '-' . $count++;
    }
    return $slug;
}
