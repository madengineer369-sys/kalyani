<?php
/**
 * SecureShop - Product Catalog & File Showcase
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

// Handle Add-to-Cart POST Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect();
    $action = sanitize_enum($_POST['action'] ?? '', ['add_to_cart']);
    if ($action === 'add_to_cart') {
        $product_id = sanitize_id($_POST['product_id'] ?? 0);
        $quantity   = sanitize_int($_POST['quantity'] ?? 1, 1, 99);
        if ($product_id && $quantity > 0) {
            $result = cart_add($product_id, $quantity);
            flash($result['ok'] ? 'success' : 'danger', $result['message']);
        }
    }
    header('Location: file.php');
    exit;
}

// Filtering & Pagination Parameters
$cat_id   = sanitize_id($_GET['category'] ?? 0);
$search   = sanitize_search($_GET['q'] ?? '');
$page_num = current_page_number();

$where_clauses = ['p.is_active = 1'];
$params = [];
$types  = '';

if ($cat_id) {
    $where_clauses[] = 'p.category_id = ?';
    $params[] = $cat_id;
    $types   .= 'i';
}

if (!empty($search)) {
    $where_clauses[] = '(p.name LIKE ? OR p.description LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}

$where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

// Count Total Products for Pagination
$count_sql = "SELECT COUNT(*) FROM products p {$where_sql}";
$total     = (int)db_fetch_value($count_sql, $types, $params);
$pg        = paginate($total, ITEMS_PER_PAGE, $page_num);

// Fetch Products Page
$sql = "SELECT p.*, c.name as category_name, COALESCE(p.sale_price, p.price) as effective_price
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        {$where_sql} 
        ORDER BY p.id DESC 
        LIMIT {$pg['per_page']} OFFSET {$pg['offset']}";

$products   = db_fetch_all($sql, $types, $params);
$categories = db_fetch_all("SELECT * FROM categories WHERE is_active=1 ORDER BY name ASC");

$page_title = 'Product Catalog';
$page_desc  = 'Browse products in the Black Stone store.';

log_access();
include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-4 py-4 navbar-spacer">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">Product Catalog</h1>
            <p class="text-muted mb-0">Showing <?= pluralise($total, 'product') ?></p>
        </div>
        <form class="d-flex gap-2" method="GET" action="file.php">
            <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All Categories</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $cat_id === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= e($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php render_flash(); ?>

    <?php if (empty($products)): ?>
        <div class="card p-5 text-center my-4">
            <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
            <h5>No products found</h5>
            <p class="text-muted">Try adjusting your category or search filter.</p>
            <a href="file.php" class="btn btn-outline-accent mx-auto">Reset Filters</a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($products as $p): ?>
                <div class="col-md-6 col-lg-4 col-xl-3">
                    <div class="card h-100 product-card">
                        <div class="card-img-top-wrapper position-relative overflow-hidden">
                            <img src="<?= e(product_image_url($p['image_path'])) ?>" class="card-img-top" alt="<?= e($p['name']) ?>" style="height:220px; object-fit:cover;">
                            <?php if ($p['sale_price']): ?>
                                <span class="badge bg-danger position-absolute top-0 end-0 m-2">SALE</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <span class="text-accent small mb-1"><?= e($p['category_name'] ?? 'General') ?></span>
                            <h5 class="card-title h6 mb-2">
                                <a href="product.php?id=<?= $p['id'] ?>" class="text-reset text-decoration-none"><?= e($p['name']) ?></a>
                            </h5>
                            <p class="card-text text-muted small mb-3 flex-grow-1">
                                <?= e(truncate($p['short_desc'] ?: $p['description'], 80)) ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center mt-auto">
                                <div>
                                    <?php if ($p['sale_price']): ?>
                                        <span class="fw-bold text-success me-1"><?= format_currency($p['sale_price']) ?></span>
                                        <span class="text-muted text-decoration-line-through small"><?= format_currency($p['price']) ?></span>
                                    <?php else: ?>
                                        <span class="fw-bold text-light"><?= format_currency($p['price']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <form method="POST" action="file.php">
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
            <?php render_pagination($pg, 'file.php?category=' . $cat_id . '&q=' . urlencode($search)); ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>