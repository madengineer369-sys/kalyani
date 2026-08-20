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
