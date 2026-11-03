<?php
/**
 * Black Stone - Clothing Category Controller & Dedicated Showcase
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

$nonce = generate_csp_nonce();
send_security_headers($nonce);
session_start_secure();

// Category slug determination
$category_slug = isset($category_slug) ? $category_slug : sanitize_slug($_GET['cat'] ?? $_GET['slug'] ?? 't-shirts');

// Category details
$category = db_fetch_one("SELECT * FROM categories WHERE slug = ? AND is_active = 1 LIMIT 1", 's', [$category_slug]);
if (!$category) {
    // Fallback to first active category if slug not found
    $category = db_fetch_one("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC LIMIT 1");
    $category_slug = $category ? $category['slug'] : 't-shirts';
}

// Handle Add to Bag POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect();
    $action = sanitize_enum($_POST['action'] ?? '', ['add_to_cart']);
    if ($action === 'add_to_cart') {
        $product_id = sanitize_id($_POST['product_id'] ?? 0);
        $quantity   = sanitize_int($_POST['quantity'] ?? 1, 1, 50);
        $size       = sanitize_string($_POST['selected_size'] ?? 'L', 10);

        if ($product_id > 0) {
            $result = cart_add($product_id, $quantity);
            if ($result['ok']) {
                flash('success', 'Added to your bag! Size: ' . e($size));
            } else {
                flash('danger', $result['message']);
            }
        }
    }
    header('Location: ' . basename($_SERVER['PHP_SELF']) . ($category_slug ? '?cat=' . urlencode($category_slug) : ''));
    exit;
}

// Sorting & Filtering
$sort      = sanitize_enum($_GET['sort'] ?? 'newest', ['newest', 'price_asc', 'price_desc', 'rating']);
$min_price = sanitize_price($_GET['min_price'] ?? 0);
$max_price = sanitize_price($_GET['max_price'] ?? 999999);

$where_clauses = ['p.is_active = 1'];
$types  = '';
$params = [];

if ($category) {
    $where_clauses[] = '(p.category_id = ?)';
    $types  .= 'i';
    $params[] = (int)$category['id'];
}

if ($min_price > 0) {
    $where_clauses[] = 'COALESCE(p.sale_price, p.price) >= ?';
    $types .= 'd';
    $params[] = $min_price;
}
if ($max_price > 0 && $max_price < 999999) {
    $where_clauses[] = 'COALESCE(p.sale_price, p.price) <= ?';
    $types .= 'd';
    $params[] = $max_price;
}

$order_sql = match($sort) {
    'price_asc'  => 'effective_price ASC',
    'price_desc' => 'effective_price DESC',
    'rating'     => 'p.avg_rating DESC',
    default      => 'p.is_featured DESC, p.created_at DESC',
};

$where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

// Pagination
$count_sql = "SELECT COUNT(*) FROM products p {$where_sql}";
$total_products = (int)db_fetch_value($count_sql, $types, $params);
$page_num = current_page_number();
$pg = paginate($total_products, ITEMS_PER_PAGE, $page_num);

$products_sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug,
                        COALESCE(p.sale_price, p.price) AS effective_price
                 FROM products p
                 LEFT JOIN categories c ON c.id = p.category_id
                 {$where_sql}
                 ORDER BY {$order_sql}
                 LIMIT {$pg['per_page']} OFFSET {$pg['offset']}";

$products = db_fetch_all($products_sql, $types, $params);

// Category Hero Metadata
$hero_metadata = [
    't-shirts' => [
        'badge' => 'Heavyweight Streetwear',
        'title' => 'Black Stone T-Shirts Collection',
        'desc'  => '240–280GSM pre-shrunk combed cotton with boxy streetwear cuts, custom neck ribbing, and hidden Mystery Box reward codes.',
        'tag'   => 'Hidden Reward Codes Included',
    ],
    'hoodies' => [
        'badge' => 'Winter Drop 450GSM',
        'title' => 'Heavyweight Streetwear Hoodies',
        'desc'  => 'Constructed with impenetrable 450GSM loopback French terry, double-lined hoods, drop-shoulders, and custom embossed hardware.',
        'tag'   => 'Double Lined & French Terry',
    ],
    'trousers' => [
        'badge' => 'Pants & Bottoms',
        'title' => 'Tactical & Tailored Trousers',
        'desc'  => 'High-tensile cotton ripstop cargos with ergonomic pockets and relaxed pleated wide-leg trousers for modern sneaker drapes.',
        'tag'   => 'High Tensile Cotton Ripstop',
    ],
    'shorts' => [
        'badge' => 'Court & Fleece',
        'title' => 'Luxury Streetwear Shorts',
        'desc'  => '380GSM French terry lounge shorts and double-layered heavyweight athletic mesh court shorts equipped with deep zipper pockets.',
        'tag'   => 'Dual Layered & Zipper Pockets',
    ]
];

$curr_meta = $hero_metadata[$category_slug] ?? [
    'badge' => 'Official Drop',
    'title' => ($category['name'] ?? 'Streetwear') . ' Collection',
    'desc'  => ($category['description'] ?? 'Limited edition Black Stone luxury streetwear.'),
    'tag'   => 'Limited Stock Only',
];

$page_title = $category ? $category['name'] . ' Collection' : 'Clothing Collections';
$page_desc  = $curr_meta['desc'];

log_access();
include __DIR__ . '/includes/header.php';
?>

<div class="container py-4">

    <!-- Category Specific Hero Banner -->
    <div class="category-hero shadow-lg">
        <span class="badge bg-warning text-dark px-3 py-2 fw-bold text-uppercase rounded-pill mb-3">
            <i class="bi bi-crown-fill me-1"></i> <?= e($curr_meta['badge']) ?>
        </span>
        <h1 class="display-4 fw-bold mb-3 text-light font-monospace">
            <?= e($curr_meta['title']) ?>
        </h1>
        <p class="text-muted fs-5 max-w-700 mx-auto mb-4" style="max-width: 750px;">
            <?= e($curr_meta['desc']) ?>
        </p>
        <div class="d-flex flex-wrap justify-content-center gap-3">
            <span class="proof-badge"><i class="bi bi-shield-check text-warning"></i> <?= e($curr_meta['tag']) ?></span>
            <span class="proof-badge"><i class="bi bi-gift text-warning"></i> Unlocks Mystery Box</span>
            <span class="proof-badge"><i class="bi bi-truck text-warning"></i> Express Free Shipping</span>
        </div>
    </div>

    <!-- Quick Navigation Chips for all 4 categories -->
    <div class="category-nav-strip mb-4">
        <a href="t-shirts.php" class="category-nav-chip <?= $category_slug === 't-shirts' ? 'active' : '' ?>">
            <i class="bi bi-gem"></i> T-Shirts
        </a>
        <a href="hoodies.php" class="category-nav-chip <?= $category_slug === 'hoodies' ? 'active' : '' ?>">
            <i class="bi bi-fire"></i> Hoodies
        </a>
        <a href="trousers.php" class="category-nav-chip <?= $category_slug === 'trousers' ? 'active' : '' ?>">
            <i class="bi bi-layers"></i> Trousers
        </a>
        <a href="shorts.php" class="category-nav-chip <?= $category_slug === 'shorts' ? 'active' : '' ?>">
            <i class="bi bi-sun"></i> Shorts
        </a>
        <a href="mystery-box.php" class="category-nav-chip border-warning text-warning">
            <i class="bi bi-box-seam-fill"></i> Mystery Box Lounge
        </a>
    </div>

    <!-- Filter & Sort Bar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 p-3 bg-dark rounded-4 border border-secondary mb-4">
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small">Showing <?= count($products) ?> of <?= $total_products ?> items</span>
        </div>

        <form method="GET" action="<?= basename($_SERVER['PHP_SELF']) ?>" class="d-flex align-items-center gap-2">
            <input type="hidden" name="cat" value="<?= e($category_slug) ?>">
            
            <label class="text-muted small mb-0 d-none d-sm-inline">Sort by:</label>
            <select name="sort" class="form-select form-select-sm bg-black text-light border-secondary w-auto" onchange="this.form.submit()">
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest Drops</option>
                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Highest Rated</option>
            </select>
        </form>
    </div>

    <!-- Product Grid -->
    <?php if (empty($products)): ?>
        <div class="card p-5 text-center bg-dark border-secondary rounded-4 my-5">
            <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
            <h4 class="text-light">No pieces listed in this collection yet</h4>
            <p class="text-muted small">Our shopkeeper or admin can list new products anytime.</p>
            <?php if (is_shopkeeper() || is_admin()): ?>
                <a href="<?= e(url('shopkeeper/add-product.php')) ?>" class="btn btn-warning mx-auto fw-bold px-4">
                    <i class="bi bi-plus-lg me-1"></i> Add Product to <?= e($category['name'] ?? 'Collection') ?>
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-3 g-4 mb-5">
            <?php foreach ($products as $p): ?>
                <div class="col">
                    <div class="product-card">
                        <!-- Badge -->
                        <div class="product-badge-corner">
                            <?php if ($p['is_featured']): ?>
                                <span class="badge bg-warning text-dark fw-bold"><i class="bi bi-star-fill me-1"></i> Featured</span>
                            <?php elseif (!empty($p['sale_price'])): ?>
                                <span class="badge bg-danger fw-bold">SALE</span>
                            <?php else: ?>
                                <span class="badge bg-dark border border-secondary text-light">Official Drop</span>
                            <?php endif; ?>
                        </div>

                        <!-- Image -->
                        <div class="product-thumb-wrapper">
                            <a href="<?= e(url('product.php?slug=' . $p['slug'])) ?>">
                                <img src="<?= e(product_image_url($p['image_path'])) ?>" alt="<?= e($p['name']) ?>" class="product-thumb" loading="lazy">
                            </a>
                        </div>

                        <!-- Card Body -->
                        <div class="product-body">
                            <div class="product-category-meta">
                                <?= e($p['category_name'] ?: 'Apparel') ?>
                            </div>

                            <h3 class="product-title">
                                <a href="<?= e(url('product.php?slug=' . $p['slug'])) ?>" class="text-light">
                                    <?= e($p['name']) ?>
                                </a>
                            </h3>

                            <p class="product-description-snippet">
                                <?= e($p['short_desc'] ?: $p['description']) ?>
                            </p>

                            <!-- Size Pill Selectors -->
                            <div class="size-selector-row" title="Select size">
                                <span class="size-pill" data-size="S">S</span>
                                <span class="size-pill" data-size="M">M</span>
                                <span class="size-pill active" data-size="L">L</span>
                                <span class="size-pill" data-size="XL">XL</span>
                                <span class="size-pill" data-size="XXL">XXL</span>
                            </div>

                            <!-- Price Row -->
                            <div class="product-price-row">
                                <span class="effective-price">
                                    <?= format_currency($p['sale_price'] ?: $p['price']) ?>
                                </span>
                                <?php if (!empty($p['sale_price']) && $p['sale_price'] < $p['price']): ?>
                                    <span class="original-price"><?= format_currency($p['price']) ?></span>
                                <?php endif; ?>
                            </div>

                            <!-- Actions -->
                            <div class="product-actions">
                                <form method="POST" action="<?= basename($_SERVER['PHP_SELF']) . '?cat=' . urlencode($category_slug) ?>">
                                    <?= render_csrf_field() ?>
                                    <input type="hidden" name="action" value="add_to_cart">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="quantity" value="1">
                                    <input type="hidden" name="selected_size" value="L">

                                    <button type="submit" class="btn btn-accent w-100 py-2 fw-bold">
                                        <i class="bi bi-bag-plus-fill me-1"></i> Add to Bag
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-4">
            <?php render_pagination($pg, basename($_SERVER['PHP_SELF']) . '?cat=' . urlencode($category_slug)); ?>
        </div>
    <?php endif; ?>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
