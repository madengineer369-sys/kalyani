<?php
/**
 * SecureShop - Homepage / Product Catalog
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
require_once __DIR__ . '/includes/email.php';

$nonce = generate_csp_nonce();
send_security_headers($nonce);
session_start_secure();

// Handle cart actions
$cart_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect();
    $action = sanitize_enum($_POST['action'] ?? '', ['add_to_cart']);
    if ($action === 'add_to_cart' && is_logged_in()) {
        $product_id = sanitize_id($_POST['product_id'] ?? 0);
        $quantity   = sanitize_int($_POST['quantity'] ?? 1, 1, 100);
        if ($product_id && $quantity > 0) {
            $result = cart_add($product_id, $quantity);
            flash($result['ok'] ? 'success' : 'danger', $result['message']);
        }
    } elseif ($action === 'add_to_cart' && !is_logged_in()) {
        flash('warning', 'Please log in to add items to your cart.');
    }
    header('Location: index.php');
    exit;
}

// ===== Filters =====
$search    = sanitize_search($_GET['q']        ?? '');
$cat_id    = sanitize_id($_GET['category']     ?? 0);
$sort      = sanitize_enum($_GET['sort']       ?? 'newest', ['newest','price_asc','price_desc','rating','featured']);
$featured  = isset($_GET['featured']) ? 1 : 0;
$page_num  = current_page_number();

// ===== Build Query =====
$where_clauses = ['p.is_active = 1'];
$types  = '';
$params = [];

if ($search) {
    $where_clauses[] = '(p.name LIKE ? OR p.description LIKE ? OR p.short_desc LIKE ?)';
    $like = '%' . $search . '%';
    $types  .= 'sss';
    $params  = array_merge($params, [$like, $like, $like]);
}
if ($cat_id) {
    $where_clauses[] = '(p.category_id = ? OR c2.parent_id = ?)';
    $types  .= 'ii';
    $params  = array_merge($params, [$cat_id, $cat_id]);
}
if ($featured) {
    $where_clauses[] = 'p.is_featured = 1';
}

$where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

$order_map = [
    'newest'     => 'p.created_at DESC',
    'price_asc'  => 'effective_price ASC',
    'price_desc' => 'effective_price DESC',
    'rating'     => 'p.avg_rating DESC',
    'featured'   => 'p.is_featured DESC, p.created_at DESC',
];
$order_sql = $order_map[$sort] ?? 'p.created_at DESC';

$count_sql = "SELECT COUNT(DISTINCT p.id) FROM products p
              LEFT JOIN categories c2 ON c2.id = p.category_id
              {$where_sql}";

$total = (int)db_fetch_value($count_sql, $types, $params);
$pg    = paginate($total, ITEMS_PER_PAGE, $page_num);

$products_sql = "SELECT p.*, c.name AS category_name,
                        COALESCE(p.sale_price, p.price) AS effective_price
                 FROM products p
                 LEFT JOIN categories c  ON c.id  = p.category_id
                 LEFT JOIN categories c2 ON c2.id = p.category_id
                 {$where_sql}
                 GROUP BY p.id
                 ORDER BY {$order_sql}
                 LIMIT {$pg['per_page']} OFFSET {$pg['offset']}";

$products = db_fetch_all($products_sql, $types, $params);

// ===== Categories for sidebar =====
$categories = db_fetch_all(
    "SELECT c.*, COUNT(p.id) AS product_count
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
     WHERE c.parent_id IS NULL AND c.is_active = 1
     GROUP BY c.id ORDER BY c.sort_order"
);

// ===== Featured products for hero =====
$featured_products = db_fetch_all(
    "SELECT * FROM products WHERE is_featured=1 AND is_active=1 ORDER BY RAND() LIMIT 3"
);

$page_title = $search ? 'Search: ' . $search : 'Shop';
$page_desc  = 'Browse our curated product catalog — quality products at great prices.';

log_access();
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="container-fluid px-4 py-4">

    <!-- Hero Banner (when no search) -->
    <?php if (!$search && !$cat_id && !$featured && $page_num === 1): ?>
    <div class="hero-banner mb-5 p-5 rounded-4 position-relative overflow-hidden shadow-lg">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <span class="badge bg-warning text-dark mb-3 px-3 py-2 fw-bold text-uppercase rounded-pill">
                    <i class="bi bi-crown-fill me-1"></i> Heavyweight Unisex Streetwear
                </span>
                <h1 class="display-4 fw-bold mb-3 font-monospace">
                    BLACK <span class="gold-text glow-text">STONE</span><br>
                    <span class="fs-2 text-light">Luxury Apparel & Mystery Box Drops</span>
                </h1>
                <p class="hero-tagline mb-4">
                    Heavyweight 260GSM combed cotton tees, 450GSM French terry hoodies, structured tactical cargo trousers, and luxury court shorts. Every piece contains secret claim codes for our interactive Mystery Box lounge.
                </p>
                <div class="d-flex gap-3 flex-wrap mb-4">
                    <a href="t-shirts.php" class="btn btn-accent px-4 py-2 fw-bold shadow-gold">
                        <i class="bi bi-gem me-2"></i>Shop T-Shirts
                    </a>
                    <a href="hoodies.php" class="btn btn-outline-warning px-4 py-2 fw-bold">
                        <i class="bi bi-fire me-2"></i>Explore Hoodies
                    </a>
                    <a href="mystery-box.php" class="btn btn-dark border-secondary px-4 py-2 fw-bold text-light">
                        <i class="bi bi-gift-fill text-warning me-2"></i>Mystery Box Lounge
                    </a>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="proof-badge"><i class="bi bi-check-circle-fill text-warning"></i> 260–450 GSM Cotton</span>
                    <span class="proof-badge"><i class="bi bi-check-circle-fill text-warning"></i> Hidden Tag Codes</span>
                    <span class="proof-badge"><i class="bi bi-check-circle-fill text-warning"></i> Limited Street Drops</span>
                </div>
            </div>
            <div class="col-lg-4 d-none d-lg-block text-center">
                <div class="position-relative">
                    <div class="avatar-lg bg-warning text-dark mx-auto fs-1 fw-bold mb-3 shadow-gold" style="width:120px; height:120px; border-radius:50%; display:flex; align-items:center; justify-content:center;">
                        <i class="bi bi-box-seam-fill"></i>
                    </div>
                    <h5 class="fw-bold text-light">Mystery Box Included</h5>
                    <p class="text-muted small">Receive claim codes with every verified apparel order.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Quick Chips Strip -->
    <div class="category-nav-strip mb-4">
        <a href="t-shirts.php" class="category-nav-chip">
            <i class="bi bi-gem text-warning"></i> T-Shirts Collection
        </a>
        <a href="hoodies.php" class="category-nav-chip">
            <i class="bi bi-fire text-warning"></i> Hoodies Drop
        </a>
        <a href="trousers.php" class="category-nav-chip">
            <i class="bi bi-layers text-warning"></i> Trousers & Pants
        </a>
        <a href="shorts.php" class="category-nav-chip">
            <i class="bi bi-sun text-warning"></i> Shorts Collection
        </a>
        <a href="mystery-box.php" class="category-nav-chip border-warning text-warning">
            <i class="bi bi-box-seam-fill"></i> Open Mystery Box
        </a>
    </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- Sidebar Filters -->
        <div class="col-lg-3 col-md-4">
            <div class="card p-3 mb-3">
                <h6 class="fw-bold mb-3"><i class="bi bi-funnel me-2"></i>Filter Products</h6>

                <!-- Category Filter -->
                <div class="mb-4">
                    <div class="small fw-semibold text-muted mb-2 text-uppercase">Category</div>
                    <div class="d-flex flex-column gap-1">
                        <a href="index.php<?= $search ? '?q='.urlencode($search) : '' ?>"
                           class="sidebar-filter-link <?= !$cat_id ? 'active' : '' ?>">
                            <i class="bi bi-house me-2"></i>All Categories
                        </a>
                        <?php foreach ($categories as $cat): ?>
                        <a href="?category=<?= (int)$cat['id'] ?><?= $search ? '&q='.urlencode($search) : '' ?>"
                           class="sidebar-filter-link <?= $cat_id === (int)$cat['id'] ? 'active' : '' ?>">
                            <i class="bi bi-tag me-2"></i><?= e($cat['name']) ?>
                            <span class="ms-auto badge bg-secondary badge-sm"><?= (int)$cat['product_count'] ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Sort -->
                <div class="mb-3">
                    <div class="small fw-semibold text-muted mb-2 text-uppercase">Sort By</div>
                    <form method="GET">
                        <?php if ($search): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>
                        <?php if ($cat_id): ?><input type="hidden" name="category" value="<?= (int)$cat_id ?>"><?php endif; ?>
                        <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="newest"     <?= $sort==='newest'     ?'selected':''?>>Newest First</option>
                            <option value="price_asc"  <?= $sort==='price_asc'  ?'selected':''?>>Price: Low to High</option>
                            <option value="price_desc" <?= $sort==='price_desc' ?'selected':''?>>Price: High to Low</option>
                            <option value="rating"     <?= $sort==='rating'     ?'selected':''?>>Top Rated</option>
                            <option value="featured"   <?= $sort==='featured'   ?'selected':''?>>Featured First</option>
                        </select>
                    </form>
                </div>

                <!-- Featured Toggle -->
                <a href="<?= $featured ? 'index.php' : 'index.php?featured=1' ?>"
                   class="btn btn-sm <?= $featured ? 'btn-accent' : 'btn-outline-accent' ?> w-100">
                    <i class="bi bi-star me-1"></i><?= $featured ? 'Showing Featured' : 'Show Featured Only' ?>
                </a>
            </div>
        </div>

        <!-- Product Grid -->
        <div class="col-lg-9 col-md-8">

            <!-- Breadcrumb & Search Context -->
            <?php
            $crumbs = [['label' => 'Home', 'url' => 'index.php'], ['label' => $search ? 'Search: '.truncate($search,30) : ($cat_id ? ($categories[array_search($cat_id, array_column($categories,'id'))]['name'] ?? 'Category') : 'Products')]];
            render_breadcrumbs($crumbs);
            ?>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2 class="section-title mb-0">
                        <?= $search ? 'Results for "' . e($search) . '"' : ($cat_id ? e($categories[array_search($cat_id, array_column($categories,'id'))]['name'] ?? 'Products') : 'All Products') ?>
                    </h2>
                    <p class="text-muted small mb-0"><?= number_format($total) ?> product<?= $total !== 1 ? 's' : '' ?> found</p>
                </div>
                <?php if ($search): ?>
                <a href="index.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x me-1"></i>Clear Search
                </a>
                <?php endif; ?>
            </div>

            <?php if (empty($products)): ?>
            <div class="text-center py-5">
                <i class="bi bi-search display-1 text-muted opacity-25"></i>
                <h3 class="mt-3">No products found</h3>
                <p class="text-muted">Try adjusting your search or browse all categories.</p>
                <a href="index.php" class="btn btn-accent mt-2">Browse All Products</a>
            </div>
            <?php else: ?>

            <div class="row g-4">
                <?php foreach ($products as $prod): ?>
                <div class="col-xl-4 col-md-6">
                    <div class="card product-card h-100">
                        <!-- Image -->
                        <a href="search.php?product=<?= (int)$prod['id'] ?>" class="product-img-wrapper">
                            <?php if ($prod['is_featured']): ?>
                            <span class="product-badge featured"><i class="bi bi-star-fill me-1"></i>Featured</span>
                            <?php elseif ($prod['sale_price']): ?>
                            <span class="product-badge sale">
                                <?= round((1 - $prod['sale_price'] / $prod['price']) * 100) ?>% OFF
                            </span>
                            <?php endif; ?>
                            <img src="<?= e(product_image_url($prod['image_path'])) ?>"
                                 alt="<?= e($prod['name']) ?>"
                                 loading="lazy">
                        </a>

                        <div class="product-card-body d-flex flex-column">
                            <div class="small text-muted mb-1"><?= e($prod['category_name'] ?? 'Uncategorized') ?></div>
                            <h6 class="product-name mb-2"><?= e($prod['name']) ?></h6>

                            <!-- Rating -->
                            <?php if ($prod['avg_rating']): ?>
                            <div class="d-flex align-items-center gap-1 mb-2">
                                <?= render_stars((float)$prod['avg_rating']) ?>
                                <span class="small text-muted">(<?= (int)$prod['review_count'] ?>)</span>
                            </div>
                            <?php endif; ?>

                            <!-- Price -->
                            <div class="d-flex align-items-baseline gap-2 mb-2">
                                <span class="product-price">
                                    <?= format_currency($prod['sale_price'] ?: $prod['price']) ?>
                                </span>
                                <?php if ($prod['sale_price']): ?>
                                <span class="product-price-original"><?= format_currency($prod['price']) ?></span>
                                <?php endif; ?>
                            </div>

                            <!-- Stock -->
                            <div class="product-stock <?= (int)$prod['stock_quantity'] <= LOW_STOCK_THRESHOLD ? 'low' : '' ?> mb-3">
                                <?php if ((int)$prod['stock_quantity'] === 0): ?>
                                <i class="bi bi-x-circle me-1"></i>Out of Stock
                                <?php elseif ((int)$prod['stock_quantity'] <= LOW_STOCK_THRESHOLD): ?>
                                <i class="bi bi-exclamation-circle me-1"></i>Only <?= (int)$prod['stock_quantity'] ?> left!
                                <?php else: ?>
                                <i class="bi bi-check-circle me-1"></i>In Stock
                                <?php endif; ?>
                            </div>

                            <!-- Add to Cart -->
                            <div class="mt-auto">
                                <?php if ((int)$prod['stock_quantity'] > 0): ?>
                                <form method="POST" action="index.php">
                                    <?php render_csrf_field(); ?>
                                    <input type="hidden" name="action"     value="add_to_cart">
                                    <input type="hidden" name="product_id" value="<?= (int)$prod['id'] ?>">
                                    <div class="input-group">
                                        <input type="number" name="quantity" value="1" min="1"
                                               max="<?= (int)$prod['stock_quantity'] ?>"
                                               class="form-control qty-input" aria-label="Quantity">
                                        <button class="btn btn-accent" type="submit">
                                            <i class="bi bi-cart-plus me-1"></i>Add
                                        </button>
                                    </div>
                                </form>
                                <?php else: ?>
                                <button class="btn btn-secondary w-100" disabled>Out of Stock</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php render_pagination($pg); ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style nonce="<?= e($nonce) ?>">
.sidebar-filter-link {
    display: flex; align-items: center;
    padding: 0.4rem 0.6rem;
    border-radius: 6px;
    color: var(--text-muted);
    font-size: 0.88rem;
    transition: all 0.2s;
    text-decoration: none;
}
.sidebar-filter-link:hover, .sidebar-filter-link.active {
    background: rgba(108,99,255,0.1);
    color: var(--accent-light);
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
