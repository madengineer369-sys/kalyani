<?php
/**
 * SecureShop - Advanced Product Search & Filters
 */
define('ECOMMERCE_APP', true);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/logging.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/cart_helper.php';
require_once __DIR__ . '/includes/pagination.php';

send_security_headers();
session_start_secure();

// Handle Add to Cart POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect();
    $action = sanitize_enum($_POST['action'] ?? '', ['add_to_cart']);
    if ($action === 'add_to_cart') {
        $pid = sanitize_id($_POST['product_id'] ?? 0);
        $qty = sanitize_int($_POST['quantity'] ?? 1, 1, 99);
        if ($pid && $qty > 0) {
            $res = cart_add($pid, $qty);
            flash($res['ok'] ? 'success' : 'danger', $res['message']);
        }
    }
    header('Location: search.php?' . $_SERVER['QUERY_STRING']);
    exit;
}

// Search Inputs
$q         = sanitize_search($_GET['q'] ?? '');
$cat_id    = sanitize_id($_GET['category'] ?? 0);
$min_price = sanitize_price($_GET['min_price'] ?? -1);
$max_price = sanitize_price($_GET['max_price'] ?? -1);
$sort      = sanitize_enum($_GET['sort'] ?? 'newest', ['newest', 'price_asc', 'price_desc', 'rating', 'featured']);
$page_num  = current_page_number();

$where = ['p.is_active = 1'];
$params = [];
$types  = '';

if ($q) {
    $where[] = '(p.name LIKE ? OR p.description LIKE ? OR p.short_desc LIKE ?)';
    $like = '%' . $q . '%';
    $params = array_merge($params, [$like, $like, $like]);
    $types .= 'sss';
}

if ($cat_id) {
    $where[] = 'p.category_id = ?';
    $params[] = $cat_id;
    $types .= 'i';
}

if ($min_price >= 0) {
    $where[] = 'COALESCE(p.sale_price, p.price) >= ?';
    $params[] = $min_price;
    $types .= 'd';
}

if ($max_price >= 0 && $max_price > $min_price) {
    $where[] = 'COALESCE(p.sale_price, p.price) <= ?';
    $params[] = $max_price;
    $types .= 'd';
}

$where_sql = 'WHERE ' . implode(' AND ', $where);

$order_map = [
    'newest'     => 'p.created_at DESC',
    'price_asc'  => 'effective_price ASC',
    'price_desc' => 'effective_price DESC',
    'rating'     => 'p.avg_rating DESC',
    'featured'   => 'p.is_featured DESC, p.created_at DESC',
];
$order_sql = $order_map[$sort] ?? 'p.created_at DESC';

$total = (int)db_fetch_value("SELECT COUNT(*) FROM products p {$where_sql}", $types, $params);
$pg    = paginate($total, ITEMS_PER_PAGE, $page_num);

$sql = "SELECT p.*, c.name AS category_name, COALESCE(p.sale_price, p.price) AS effective_price
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        {$where_sql}
        ORDER BY {$order_sql}
        LIMIT {$pg['per_page']} OFFSET {$pg['offset']}";

$products   = db_fetch_all($sql, $types, $params);
$categories = db_fetch_all("SELECT * FROM categories WHERE is_active=1 ORDER BY sort_order ASC");

$page_title = $q ? 'Search: ' . $q : 'Explore Catalog';
$page_desc  = 'Find products in our secure e-commerce catalog.';

log_access();
include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-4 py-4 navbar-spacer">
    <div class="row g-4">
        <!-- Sidebar Filters -->
        <div class="col-lg-3">
            <div class="card p-4 border-secondary bg-dark-subtle rounded-4 shadow-sm sticky-top" style="top: 90px;">
                <h5 class="fw-bold mb-4 pb-2 border-bottom border-secondary"><i class="bi bi-funnel me-2 text-accent"></i> Filter Products</h5>
                <form method="GET" action="search.php">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Keyword Search</label>
                        <input type="text" name="q" class="form-control bg-dark text-light border-secondary" placeholder="Search title or tags..." value="<?= e($q) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Category</label>
                        <select name="category" class="form-select bg-dark text-light border-secondary">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $cat_id === (int)$cat['id'] ? 'selected' : '' ?>>
                                    <?= e($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Price Range (₹)</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="number" name="min_price" class="form-control bg-dark text-light border-secondary" placeholder="Min" value="<?= $min_price >= 0 ? $min_price : '' ?>">
                            </div>
                            <div class="col-6">
                                <input type="number" name="max_price" class="form-control bg-dark text-light border-secondary" placeholder="Max" value="<?= $max_price >= 0 ? $max_price : '' ?>">
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Sort Order</label>
                        <select name="sort" class="form-select bg-dark text-light border-secondary">
                            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest Arrivals</option>
                            <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                            <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                            <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Top Rated</option>
                            <option value="featured" <?= $sort === 'featured' ? 'selected' : '' ?>>Featured First</option>
                        </select>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-accent w-100 fw-bold">Apply Filters</button>
                        <a href="search.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Main Results Grid -->
        <div class="col-lg-9">
            <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                <div>
                    <h1 class="h4 fw-bold mb-1"><?= $q ? 'Results for "' . e($q) . '"' : 'All Products' ?></h1>
                    <span class="text-muted small">Showing <?= pluralise($total, 'product') ?></span>
                </div>
            </div>

            <?php render_flash(); ?>

            <?php if (empty($products)): ?>
                <div class="card p-5 text-center my-4 border-secondary bg-dark-subtle rounded-4">
                    <i class="bi bi-search fs-1 text-muted mb-3"></i>
                    <h5>No products match your criteria</h5>
                    <p class="text-muted">Try clearing some of your search filters.</p>
                    <a href="search.php" class="btn btn-outline-accent mx-auto px-4">Clear All Filters</a>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($products as $p): ?>
                        <div class="col-md-6 col-xl-4">
                            <div class="card h-100 product-card border-secondary bg-dark-subtle rounded-4 overflow-hidden shadow-sm">
                                <div class="card-img-top-wrapper position-relative overflow-hidden">
                                    <img src="<?= e(product_image_url($p['image_path'])) ?>" class="card-img-top" alt="<?= e($p['name']) ?>" style="height: 220px; object-fit: cover;">
                                    <?php if ($p['sale_price']): ?>
                                        <span class="badge bg-danger position-absolute top-0 end-0 m-3">SALE</span>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body d-flex flex-column p-4">
                                    <span class="text-accent small mb-1"><?= e($p['category_name'] ?? 'General') ?></span>
                                    <h5 class="card-title h6 fw-bold mb-2">
                                        <a href="product.php?id=<?= $p['id'] ?>" class="text-light text-decoration-none"><?= e($p['name']) ?></a>
                                    </h5>
                                    <p class="card-text text-muted small mb-3 flex-grow-1">
                                        <?= e(truncate($p['short_desc'] ?: $p['description'], 90)) ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center mt-auto pt-3 border-top border-secondary">
                                        <div>
                                            <?php if ($p['sale_price']): ?>
                                                <span class="fw-bold text-success fs-6 me-1"><?= format_currency($p['sale_price']) ?></span>
                                                <span class="text-muted text-decoration-line-through small"><?= format_currency($p['price']) ?></span>
                                            <?php else: ?>
                                                <span class="fw-bold text-light fs-6"><?= format_currency($p['price']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <form method="POST" action="search.php?<?= e($_SERVER['QUERY_STRING']) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="add_to_cart">
                                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-accent" <?= $p['stock_quantity'] < 1 ? 'disabled' : '' ?>>
                                                <i class="bi bi-cart-plus me-1"></i> Add
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-4">
                    <?php render_pagination($pg, 'search.php?q=' . urlencode($q) . '&category=' . $cat_id . '&min_price=' . $min_price . '&max_price=' . $max_price . '&sort=' . urlencode($sort)); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
