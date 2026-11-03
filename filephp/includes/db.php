<?php
/**
 * SecureShop - Database Layer (MySQLi, Procedural)
 *
 * ALL queries use prepared statements.
 * ALL queries are logged to query.log.
 * DB connection errors are handled silently (no details leaked to browser).
 */

if (!defined('ECOMMERCE_APP')) {
    http_response_code(403); exit('Forbidden');
}

/**
 * Return (or create) the singleton MySQLi connection.
 */
function db_connect(): mysqli {
    static $conn = null;

    if ($conn !== null) {
        // Reconnect if connection dropped
        if (!@$conn->ping()) { $conn = null; }
        else { return $conn; }
    }

    // Suppress PHP warnings — we handle errors ourselves
    $conn = @new mysqli(DB_HOST . ':' . DB_PORT, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_errno) {
        log_error('DB_CONNECT_FAILED', ['errno' => $conn->connect_errno]);
        log_security('DB_CONNECTION_FAILURE');
        // NEVER reveal MySQL error details to the browser
        http_response_code(503);
        die('<!DOCTYPE html><html><head><title>Service Unavailable</title></head><body>
            <h1>Service Temporarily Unavailable</h1>
            <p>We\'re experiencing technical difficulties. Please try again later.</p>
        </body></html>');
    }

    if (!$conn->set_charset(DB_CHARSET)) {
        log_warning('DB_CHARSET_FAILED', ['charset' => DB_CHARSET]);
    }

    // Strict SQL mode: prevents silent data truncation / bad inserts
    $conn->query("SET SESSION sql_mode='STRICT_ALL_TABLES,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
    $conn->query("SET SESSION time_zone='+05:30'");

    static $schema_checked = false;
    if (!$schema_checked) {
        db_ensure_schema($conn);
        $schema_checked = true;
    }

    return $conn;
}

/**
 * Execute a prepared statement.
 *
 * @param string $sql    SQL with ? placeholders
 * @param string $types  Bind types: i=int, d=float, s=string, b=blob
 * @param array  $params Values to bind (count must match $types length)
 * @return mysqli_stmt
 * @throws RuntimeException on failure
 */
function db_query(string $sql, string $types = '', array $params = []): mysqli_stmt {
    $conn  = db_connect();
    $start = microtime(true);

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        log_error('DB_PREPARE_FAILED', ['sql' => _safe_sql($sql), 'err' => $conn->error]);
        throw new RuntimeException('Query preparation failed');
    }

    if ($types !== '' && $params !== []) {
        if (strlen($types) !== count($params)) {
            log_error('DB_PARAM_MISMATCH', ['sql' => _safe_sql($sql), 'types' => $types, 'got' => count($params)]);
            throw new InvalidArgumentException('Param/type count mismatch');
        }
        // bind_param requires references
        $refs = [$types];
        foreach ($params as &$p) { $refs[] = &$p; }
        unset($p);
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }

    if (!$stmt->execute()) {
        log_error('DB_EXECUTE_FAILED', ['sql' => _safe_sql($sql), 'err' => $stmt->error, 'errno' => $stmt->errno]);
        throw new RuntimeException('Query execution failed: ' . $stmt->error);
    }

    log_query($sql, $params, microtime(true) - $start);

    return $stmt;
}

/**
 * Fetch a single associative row (or null).
 */
function db_fetch_one(string $sql, string $types = '', array $params = []): ?array {
    $stmt   = db_query($sql, $types, $params);
    $result = $stmt->get_result();
    $row    = $result->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Fetch all rows as array of associative arrays.
 */
function db_fetch_all(string $sql, string $types = '', array $params = []): array {
    $stmt   = db_query($sql, $types, $params);
    $result = $stmt->get_result();
    $rows   = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
}

/**
 * Fetch a single scalar value from first column of first row.
 */
function db_fetch_value(string $sql, string $types = '', array $params = [], int $col = 0) {
    $stmt   = db_query($sql, $types, $params);
    $result = $stmt->get_result();
    $row    = $result->fetch_row();
    $stmt->close();
    return $row ? $row[$col] : null;
}

/**
 * Execute INSERT and return the new auto-increment ID.
 */
function db_insert(string $sql, string $types = '', array $params = []): int {
    $stmt = db_query($sql, $types, $params);
    $id   = db_connect()->insert_id;
    $stmt->close();
    return $id;
}

/**
 * Execute UPDATE / DELETE and return affected row count.
 */
function db_execute(string $sql, string $types = '', array $params = []): int {
    $stmt    = db_query($sql, $types, $params);
    $aff     = $stmt->affected_rows;
    $stmt->close();
    return $aff;
}

/**
 * Count rows matching optional WHERE condition.
 *
 * @param string $table  Table name (must be safe — caller responsible)
 * @param string $where  Optional WHERE clause (e.g. "status = ? AND is_active = ?")
 */
function db_count(string $table, string $where = '', string $types = '', array $params = []): int {
    $sql = "SELECT COUNT(*) FROM `{$table}`" . ($where ? " WHERE {$where}" : '');
    return (int) db_fetch_value($sql, $types, $params);
}

// ===== Transactions =====

function db_begin(): void    { db_connect()->begin_transaction(); }
function db_commit(): void   { db_connect()->commit(); }
function db_rollback(): void { db_connect()->rollback(); }
function db_last_id(): int   { return (int) db_connect()->insert_id; }

// ===== Internal helpers =====

/** Sanitise SQL for safe logging (strip extra whitespace). */
function _safe_sql(string $sql): string {
    return preg_replace('/\s+/', ' ', trim($sql));
}

/**
 * Self-healing schema migration: Ensures tables, columns, categories & upload directories exist.
 */
function db_ensure_schema(mysqli $conn): void {
    // 1. Ensure upload directory exists
    if (defined('UPLOAD_PATH') && !is_dir(UPLOAD_PATH)) {
        @mkdir(UPLOAD_PATH, 0755, true);
    }

    // 2. Ensure otps table exists
    $conn->query("CREATE TABLE IF NOT EXISTS `otps` (
        `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `identifier`   VARCHAR(255) NOT NULL,
        `type`         ENUM('register','login','reset') NOT NULL DEFAULT 'register',
        `otp_code`     VARCHAR(10)  NOT NULL,
        `temp_data`    JSON                  DEFAULT NULL,
        `expires_at`   DATETIME     NOT NULL,
        `is_used`      TINYINT(1)   NOT NULL DEFAULT 0,
        `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_identifier` (`identifier`),
        INDEX `idx_expires_at` (`expires_at`),
        INDEX `idx_is_used`    (`is_used`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 3. Ensure phone_verified column in users table
    $col_check = $conn->query("SHOW COLUMNS FROM `users` LIKE 'phone_verified'");
    if ($col_check && $col_check->num_rows === 0) {
        $conn->query("ALTER TABLE `users` ADD COLUMN `phone_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `email_verified`");
    }

    // 4. Ensure clothing categories exist
    $cat_count = 0;
    $res = $conn->query("SELECT COUNT(*) FROM `categories` WHERE `slug` IN ('t-shirts', 'hoodies', 'trousers', 'shorts')");
    if ($res) {
        $row = $res->fetch_row();
        $cat_count = (int)($row[0] ?? 0);
    }
    if ($cat_count < 4) {
        $conn->query("INSERT IGNORE INTO `categories` (`name`, `slug`, `description`, `is_active`, `sort_order`) VALUES
            ('T-Shirts', 't-shirts', 'Heavyweight 240-280GSM luxury streetwear tees with custom neck ribbing and signature graphics.', 1, 1),
            ('Hoodies', 'hoodies', 'Heavyweight 450GSM French terry hoodies with drop shoulders and custom metallic hardware.', 1, 2),
            ('Trousers', 'trousers', 'Relaxed utilitarian cargo pants, wide-leg trousers, and tailored streetwear essentials.', 1, 3),
            ('Shorts', 'shorts', 'Heavyweight fleece shorts and luxury double-layer breathable court mesh shorts.', 1, 4)");
    }
}
