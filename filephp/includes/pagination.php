<?php
/**
 * SecureShop - Pagination Helper
 */

if (!defined('ECOMMERCE_APP')) {
    http_response_code(403); exit('Forbidden');
}

/**
 * Build a pagination data structure.
 *
 * @param int $total_items  Total number of rows
 * @param int $per_page     Items per page
 * @param int $current_page Current page (1-indexed)
 * @return array  ['offset', 'per_page', 'total', 'total_pages', 'current', 'has_prev', 'has_next']
 */
function paginate(int $total_items, int $per_page, int $current_page): array {
    $per_page     = max(1, $per_page);
    $total_pages  = max(1, (int)ceil($total_items / $per_page));
    $current_page = max(1, min($current_page, $total_pages));
    $offset       = ($current_page - 1) * $per_page;

    return [
        'offset'      => $offset,
        'per_page'    => $per_page,
        'total'       => $total_items,
        'total_pages' => $total_pages,
        'current'     => $current_page,
        'has_prev'    => $current_page > 1,
        'has_next'    => $current_page < $total_pages,
        'prev'        => max(1, $current_page - 1),
        'next'        => min($total_pages, $current_page + 1),
    ];
}

/**
 * Get the current page number from $_GET, sanitised.
 */
function current_page_number(string $param = 'page'): int {
    return max(1, (int)($_GET[$param] ?? 1));
}

/**
 * Build a page URL preserving all current query params except 'page'.
 */
function page_url(int $page, string $param = 'page'): string {
    $params = $_GET;
    $params[$param] = $page;
    return '?' . http_build_query($params);
}

/**
 * Render a Bootstrap 5 pagination nav.
 *
 * @param array  $pg     Result of paginate()
 * @param string $param  GET param name (default: 'page')
 */
function render_pagination(array $pg, string $param = 'page'): void {
    if ($pg['total_pages'] <= 1) return;

    echo '<nav aria-label="Page navigation" class="pagination-wrapper">';
    echo '<ul class="pagination justify-content-center">';

    // Previous
    if ($pg['has_prev']) {
        echo '<li class="page-item"><a class="page-link" href="' . e(page_url($pg['prev'], $param)) . '">'
           . '<i class="bi bi-chevron-left"></i></a></li>';
    } else {
        echo '<li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-left"></i></span></li>';
    }

    // Page numbers (show max 7 around current)
    $start = max(1, $pg['current'] - 3);
    $end   = min($pg['total_pages'], $pg['current'] + 3);

    if ($start > 1) {
        echo '<li class="page-item"><a class="page-link" href="' . e(page_url(1, $param)) . '">1</a></li>';
        if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
    }

    for ($i = $start; $i <= $end; $i++) {
        if ($i === $pg['current']) {
            echo '<li class="page-item active" aria-current="page"><span class="page-link">' . $i . '</span></li>';
        } else {
            echo '<li class="page-item"><a class="page-link" href="' . e(page_url($i, $param)) . '">' . $i . '</a></li>';
        }
    }

    if ($end < $pg['total_pages']) {
        if ($end < $pg['total_pages'] - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
        echo '<li class="page-item"><a class="page-link" href="' . e(page_url($pg['total_pages'], $param)) . '">' . $pg['total_pages'] . '</a></li>';
    }

    // Next
    if ($pg['has_next']) {
        echo '<li class="page-item"><a class="page-link" href="' . e(page_url($pg['next'], $param)) . '">'
           . '<i class="bi bi-chevron-right"></i></a></li>';
    } else {
        echo '<li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-right"></i></span></li>';
    }

    echo '</ul>';
    echo '<p class="pagination-info text-center text-muted small mt-2">Showing page ' . $pg['current'] . ' of ' . $pg['total_pages'] . ' (' . number_format($pg['total']) . ' total)</p>';
    echo '</nav>';
}
