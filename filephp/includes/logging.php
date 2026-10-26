<?php
/**
 * SecureShop - Centralized Logging System
 *
 * Levels: DEBUG, INFO, WARNING, ERROR, CRITICAL
 * Log files: access, error, security, query, audit
 *
 * SECURITY: Sensitive data (passwords, tokens, card numbers) is NEVER logged.
 */

if (!defined('ECOMMERCE_APP')) {
    http_response_code(403); exit('Forbidden');
}

// ===== Core Log Writer =====

/**
 * Write a log entry to the specified log file.
 *
 * @param string $level   Log level: DEBUG|INFO|WARNING|ERROR|CRITICAL
 * @param string $message Human-readable message
 * @param array  $context Additional structured context
 * @param string $logfile Base name of log file (access|error|security|query|audit)
 */
function log_write(string $level, string $message, array $context = [], string $logfile = 'error'): void {
    static $level_map = [
        'DEBUG'    => LOG_LEVEL_DEBUG,
        'INFO'     => LOG_LEVEL_INFO,
        'WARNING'  => LOG_LEVEL_WARNING,
        'ERROR'    => LOG_LEVEL_ERROR,
        'CRITICAL' => LOG_LEVEL_CRITICAL,
    ];

    $numeric = $level_map[strtoupper($level)] ?? LOG_LEVEL_INFO;
    if ($numeric < LOG_MIN_LEVEL && $logfile !== 'security' && $logfile !== 'audit') {
        return; // Security and audit logs always written
    }

    // Sanitize: strip sensitive keys before logging
    $context = _log_redact($context);

    $entry = json_encode([
        'ts'         => date('Y-m-d\TH:i:s'),
        'level'      => strtoupper($level),
        'rid'        => _request_id(),
        'uid'        => $_SESSION['user_id'] ?? null,
        'ip'         => get_client_ip(),
        'msg'        => $message,
        'ctx'        => $context,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $path = _log_path($logfile);
    _rotate_if_needed($path);

    $h = @fopen($path, 'a');
    if ($h) {
        flock($h, LOCK_EX);
        fwrite($h, $entry . PHP_EOL);
        flock($h, LOCK_UN);
        fclose($h);
    }

    // Mirror critical errors to PHP error log
    if ($numeric >= LOG_LEVEL_ERROR) {
        error_log("[SECURESHOP][{$level}] {$message}");
    }
}

// ===== Convenience Functions =====

function log_debug(string $msg, array $ctx = [], string $file = 'error'): void    { log_write('DEBUG',    $msg, $ctx, $file); }
function log_info(string $msg, array $ctx = [], string $file = 'access'): void    { log_write('INFO',     $msg, $ctx, $file); }
function log_warning(string $msg, array $ctx = [], string $file = 'error'): void  { log_write('WARNING',  $msg, $ctx, $file); }
function log_error(string $msg, array $ctx = [], string $file = 'error'): void    { log_write('ERROR',    $msg, $ctx, $file); }
function log_critical(string $msg, array $ctx = [], string $file = 'error'): void { log_write('CRITICAL', $msg, $ctx, $file); }

// ===== Specialised Loggers =====

/**
 * Log a security event (also persisted to security_log DB table if DB is ready).
 */
function log_security(string $event_type, array $details = []): void {
    $ctx = array_merge($details, [
        'event'  => $event_type,
        'uri'    => $_SERVER['REQUEST_URI'] ?? '',
        'ua'     => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
    ]);
    log_write('WARNING', "SECURITY_EVENT:{$event_type}", $ctx, 'security');

    // Persist to DB (best effort)
    if (function_exists('db_query')) {
        try {
            $uid     = $_SESSION['user_id'] ?? null;
            $ip      = get_client_ip();
            $ua      = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
            $detail  = json_encode($details, JSON_UNESCAPED_UNICODE);
            db_query(
                "INSERT INTO security_log (event_type,user_id,ip_address,user_agent,details,created_at) VALUES (?,?,?,?,?,NOW())",
                'sssss', [$event_type, $uid, $ip, $ua, $detail]
            );
        } catch (Throwable $e) { /* silent */ }
    }
}

/**
 * Log a database query (sanitised SQL only, never raw values).
 */
function log_query(string $sql, array $params = [], float $exec_time = 0): void {
    log_write('DEBUG', 'DB_QUERY', [
        'sql'      => preg_replace('/\s+/', ' ', trim($sql)),
        'params'   => count($params),
        'ms'       => round($exec_time * 1000, 2),
    ], 'query');
}

/**
 * Log an audit event for important data changes.
 */
function log_audit(string $action, string $entity, ?int $entity_id, $old = null, $new = null): void {
    $ctx = ['action' => $action, 'entity' => $entity, 'id' => $entity_id];
    log_write('INFO', "AUDIT:{$action}:{$entity}#{$entity_id}", $ctx, 'audit');

    if (function_exists('db_query')) {
        try {
            $uid = $_SESSION['user_id'] ?? null;
            $ip  = get_client_ip();
            $ua  = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
            $old_json = $old ? json_encode(_log_redact((array)$old)) : null;
            $new_json = $new ? json_encode(_log_redact((array)$new)) : null;
            db_query(
                "INSERT INTO audit_log (user_id,action,entity_type,entity_id,old_values,new_values,ip_address,user_agent,created_at)
                 VALUES (?,?,?,?,?,?,?,?,NOW())",
                'ississss',
                [$uid, $action, $entity, $entity_id, $old_json, $new_json, $ip, $ua]
            );
        } catch (Throwable $e) { /* silent */ }
    }
}

/**
 * Log an HTTP access.
 */
function log_access(int $status = 200): void {
    log_write('INFO', 'HTTP', [
        'method'  => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        'uri'     => substr($_SERVER['REQUEST_URI'] ?? '/', 0, 500),
        'status'  => $status,
        'referer' => substr($_SERVER['HTTP_REFERER'] ?? '', 0, 300),
        'ua'      => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
    ], 'access');
}

// ===== Helpers =====

/**
 * Get the real client IP, trusting only known proxies.
 */
function get_client_ip(): string {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $trusted = ['127.0.0.1', '::1'];

    if (in_array($remote, $trusted, true)) {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP'] as $hdr) {
            if (!empty($_SERVER[$hdr])) {
                $ip = trim(explode(',', $_SERVER[$hdr])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
    }
    return $remote;
}

/**
 * Consistent request ID for correlating all log lines from one HTTP request.
 */
function _request_id(): string {
    static $rid = null;
    if (!$rid) { $rid = bin2hex(random_bytes(6)); }
    return $rid;
}

/**
 * Resolve daily-rotated log file path.
 */
function _log_path(string $name): string {
    return LOG_PATH . DIRECTORY_SEPARATOR . $name . '_' . date('Y-m-d') . '.log';
}

/**
 * Rotate log file to archive if it exceeds size limit.
 */
function _rotate_if_needed(string $path): void {
    if (!file_exists($path) || filesize($path) < LOG_MAX_FILE_SIZE) return;
    $archive = $path . '.' . time() . '.bak';
    rename($path, $archive);
}

/**
 * Recursively redact sensitive keys from a context array before logging.
 */
function _log_redact(array $ctx): array {
    static $sensitive = [
        'password','password_hash','pass','passwd','secret','token',
        'api_key','credit_card','card_number','cvv','ssn','pin',
        'auth_token','reset_token','remember_token','csrf_token',
        'authorization','card','otp',
    ];

    array_walk_recursive($ctx, function (&$val, $key) use ($sensitive) {
        foreach ($sensitive as $s) {
            if (stripos((string)$key, $s) !== false) {
                $val = '[REDACTED]';
                return;
            }
        }
        // Truncate very long values
        if (is_string($val) && strlen($val) > 1000) {
            $val = substr($val, 0, 200) . '...[TRUNCATED]';
        }
    });
    return $ctx;
}

// ===== Init: Ensure log directory exists with .htaccess protection =====

(function () {
    if (!is_dir(LOG_PATH)) {
        @mkdir(LOG_PATH, 0750, true);
    }
    $ht = LOG_PATH . DIRECTORY_SEPARATOR . '.htaccess';
    if (!file_exists($ht)) {
        @file_put_contents($ht, "Require all denied\n");
    }
})();
