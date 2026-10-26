<?php
/**
 * SecureShop - General Utility / Helper Functions
 */

if (!defined('ECOMMERCE_APP')) {
    http_response_code(403); exit('Forbidden');
}

// ===== Currency & Numbers =====

/**
 * Format a number as currency string.
 * e.g. 1234.5 → "₹1,234.50"
 */
function format_currency($amount): string {
    return CURRENCY_SYMBOL . number_format((float)$amount, 2);
}

/**
 * Format a percentage.
 */
function format_percent($val, int $decimals = 1): string {
    return number_format((float)$val, $decimals) . '%';
}

// ===== Dates & Times =====

/**
 * Format a datetime string for human display.
 */
function format_date(string $dt, string $format = 'd M Y'): string {
    if (!$dt) return '—';
    return date($format, strtotime($dt));
}

/**
 * Format datetime with time.
 */
function format_datetime(string $dt): string {
    return format_date($dt, 'd M Y, H:i');
}

/**
 * Return a human-readable "time ago" string.
 */
function time_ago(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return intdiv($diff, 60) . ' min ago';
    if ($diff < 86400)  return intdiv($diff, 3600) . ' hr ago';
    if ($diff < 604800) return intdiv($diff, 86400) . ' days ago';
    return format_date($dt);
}

// ===== Flash Messages =====

/**
 * Set a flash message (stored in session, shown once).
 *
 * @param string $type    Bootstrap alert type: success|danger|warning|info
 * @param string $message Message text
 */
function flash(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Render all queued flash messages and clear them.
 */
function render_flash(): void {
    if (empty($_SESSION['flash'])) return;

    foreach ($_SESSION['flash'] as $msg) {
        $type = htmlspecialchars($msg['type'], ENT_QUOTES);
        $text = htmlspecialchars($msg['message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">'
           . '<i class="bi bi-' . alert_icon($type) . ' me-2"></i>'
           . $text
           . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
           . '</div>';
    }
    unset($_SESSION['flash']);
}

/**
 * Map alert type to Bootstrap Icons icon name.
 */
function alert_icon(string $type): string {
    return match($type) {
        'success' => 'check-circle-fill',
        'danger'  => 'exclamation-triangle-fill',
        'warning' => 'exclamation-circle-fill',
        'info'    => 'info-circle-fill',
        default   => 'bell-fill',
    };
}

// ===== Order Helpers =====

/**
 * Generate a unique order number.
 * Format: ORD-YYYYMMDD-XXXXX (e.g. ORD-20260813-A3F2C)
 */
function generate_order_number(): string {
    do {
        $num = 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    } while (db_fetch_value("SELECT COUNT(*) FROM orders WHERE order_number=?", 's', [$num]));
    return $num;
}

/**
 * Map order status to Bootstrap badge class.
 */
function order_status_badge(string $status): string {
    $map = [
        'pending'    => 'warning',
        'confirmed'  => 'info',
        'processing' => 'primary',
        'shipped'    => 'purple',
        'delivered'  => 'success',
        'cancelled'  => 'danger',
        'refunded'   => 'secondary',
    ];
    $color = $map[$status] ?? 'secondary';
    return '<span class="badge badge-status bg-' . $color . '">' . ucfirst($status) . '</span>';
}

/**
 * Get the allowed next statuses for an order status transition.
 */
function order_next_statuses(string $current): array {
    $transitions = [
        'pending'    => ['confirmed', 'cancelled'],
        'confirmed'  => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped'    => ['delivered'],
        'delivered'  => ['refunded'],
        'cancelled'  => [],
        'refunded'   => [],
    ];
    return $transitions[$current] ?? [];
}

// ===== String Utilities =====

/**
 * Truncate a string to a max length, appending ellipsis.
 */
function truncate(string $str, int $max = 100, string $ellipsis = '…'): string {
    if (mb_strlen($str, 'UTF-8') <= $max) return $str;
    return mb_substr($str, 0, $max, 'UTF-8') . $ellipsis;
}

/**
 * Mask an email for display (e.g. us***@gmail.com).
 */
function mask_email(string $email): string {
    [$local, $domain] = explode('@', $email, 2);
    $len    = strlen($local);
    $show   = max(2, intdiv($len, 3));
    return substr($local, 0, $show) . str_repeat('*', max(0, $len - $show)) . '@' . $domain;
}

/**
 * Pluralise a word based on count.
 */
function pluralise(int $count, string $singular, string $plural = ''): string {
    if (!$plural) $plural = $singular . 's';
    return $count . ' ' . ($count === 1 ? $singular : $plural);
}

// ===== Star Rating =====

/**
 * Render star rating HTML.
 */
function render_stars(float $rating, bool $interactive = false): string {
    $html = '<span class="star-rating">';
    for ($i = 1; $i <= 5; $i++) {
        if ($rating >= $i) {
            $html .= '<i class="bi bi-star-fill text-warning"></i>';
        } elseif ($rating >= $i - 0.5) {
            $html .= '<i class="bi bi-star-half text-warning"></i>';
        } else {
            $html .= '<i class="bi bi-star text-muted"></i>';
        }
    }
    $html .= '</span>';
    return $html;
}

// ===== Arrays & Data =====

/**
 * Safely get a nested array value with a default.
 */
function array_get(array $arr, string $key, $default = null) {
    return $arr[$key] ?? $default;
}

/**
 * Check if an array is associative.
 */
function is_assoc(array $arr): bool {
    return array_keys($arr) !== range(0, count($arr) - 1);
}

// ===== CSV Export =====

/**
 * Stream a CSV file to the browser.
 *
 * @param string   $filename  Download filename
 * @param array    $headers   Column headers
 * @param iterable $rows      Data rows (array of arrays)
 */
function stream_csv(string $filename, array $headers, iterable $rows): void {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // UTF-8 BOM for Excel compatibility
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

// ===== Path & URL =====

/**
 * Build an absolute URL from a relative path.
 */
function url(string $path = ''): string {
    return rtrim(APP_BASE_URL, '/') . '/' . ltrim($path, '/');
}

/**
 * Build the URL for an uploaded product image.
 */
function product_image_url(?string $path): string {
    if (!$path) return url('assets/img/placeholder.png');
    return url('uploads/products/' . $path);
}

/**
 * Check if the current page matches a given filename.
 */
function is_current_page(string $page): bool {
    return basename($_SERVER['PHP_SELF']) === $page;
}

// ===== Breadcrumbs =====

/**
 * Render a Bootstrap breadcrumb trail.
 *
 * @param array $crumbs  [['label' => '...', 'url' => '...'], ...]
 *                       Last item should have no 'url' (it's the current page).
 */
function render_breadcrumbs(array $crumbs): void {
    echo '<nav aria-label="breadcrumb" class="breadcrumb-wrapper">';
    echo '<ol class="breadcrumb">';
    foreach ($crumbs as $i => $crumb) {
        $last = ($i === count($crumbs) - 1);
        if ($last) {
            echo '<li class="breadcrumb-item active" aria-current="page">' . e($crumb['label']) . '</li>';
        } else {
            echo '<li class="breadcrumb-item"><a href="' . e($crumb['url']) . '">' . e($crumb['label']) . '</a></li>';
        }
    }
    echo '</ol></nav>';
}

// ===== Discount Calculation =====

/**
 * Calculate the discount amount for a given code and subtotal.
 *
 * @return float  Discount amount (0 if invalid/not applicable)
 */
function calculate_discount(array $code, float $subtotal): float {
    if (!$code['is_active']) return 0.0;
    if ($code['expires_at'] && strtotime($code['expires_at']) < time()) return 0.0;
    if ($code['max_uses'] && (int)$code['used_count'] >= (int)$code['max_uses']) return 0.0;
    if ((float)$code['min_order_amount'] > $subtotal) return 0.0;

    if ($code['discount_type'] === 'percent') {
        $discount = $subtotal * ((float)$code['discount_value'] / 100);
        if ($code['max_discount']) {
            $discount = min($discount, (float)$code['max_discount']);
        }
    } else {
        $discount = (float)$code['discount_value'];
    }

    return round($discount, 2);
}

/**
 * Calculate order totals: subtotal, discount, tax, shipping, total.
 */
function calculate_order_totals(array $cart_items, ?array $discount_code, float $tax_rate): array {
    $subtotal = 0.0;
    foreach ($cart_items as $item) {
        $price     = (float)($item['sale_price'] ?: $item['price']);
        $subtotal += $price * (int)$item['quantity'];
    }

    $discount = $discount_code ? calculate_discount($discount_code, $subtotal) : 0.0;
    $after_discount = $subtotal - $discount;

    $shipping = ($subtotal >= FREE_SHIPPING_ABOVE) ? 0.0 : SHIPPING_CHARGE;
    $tax      = round($after_discount * ($tax_rate / 100), 2);
    $total    = round($after_discount + $tax + $shipping, 2);

    return compact('subtotal', 'discount', 'tax', 'shipping', 'total', 'tax_rate');
}
