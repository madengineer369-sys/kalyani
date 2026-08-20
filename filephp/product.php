<?php
/**
 * SecureShop - Single Product Details & Reviews
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

send_security_headers();
session_start_secure();

$product_id = sanitize_id($_GET['id'] ?? 0);
if (!$product_id) {
    flash('danger', 'Product not specified.');
    header('Location: index.php');
    exit;
}

// Fetch Product
$product = db_fetch_one(
    "SELECT p.*, c.name AS category_name, c.slug AS category_slug, u.username AS shopkeeper_name
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     LEFT JOIN users u ON u.id = p.shopkeeper_id
     WHERE p.id = ? AND p.is_active = 1 LIMIT 1",
    'i', [$product_id]
);

if (!$product) {
    flash('danger', 'Product not found or unavailable.');
    header('Location: index.php');
    exit;
}

// Handle Actions (Add to Cart / Submit Review)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect();
    $action = sanitize_enum($_POST['action'] ?? '', ['add_to_cart', 'submit_review']);

    if ($action === 'add_to_cart') {
        $qty = sanitize_int($_POST['quantity'] ?? 1, 1, 99);
        $res = cart_add($product['id'], $qty);
        flash($res['ok'] ? 'success' : 'danger', $res['message']);
        header("Location: product.php?id={$product['id']}");
        exit;
    } elseif ($action === 'submit_review' && is_logged_in()) {
        $rating = sanitize_int($_POST['rating'] ?? 5, 1, 5);
        $title  = sanitize_string($_POST['title'] ?? '', 200);
        $body   = sanitize_textarea($_POST['body'] ?? '', 1000);

        if ($rating >= 1 && $rating <= 5) {
            $user_id = current_user_id();
            $exists = db_fetch_value(
                "SELECT COUNT(*) FROM reviews WHERE product_id=? AND user_id=?",
                'ii', [$product['id'], $user_id]
            );
            if ($exists) {
                flash('warning', 'You have already reviewed this product.');
            } else {
                db_insert(
                    "INSERT INTO reviews (product_id, user_id, rating, title, body, is_approved, created_at)
                     VALUES (?, ?, ?, ?, ?, 1, NOW())",
                    'iiiss', [$product['id'], $user_id, $rating, $title, $body]
                );
                
                // Recalculate average rating
                $stats = db_fetch_one(
                    "SELECT AVG(rating) as avg_r, COUNT(*) as cnt FROM reviews WHERE product_id=? AND is_approved=1",
                    'i', [$product['id']]
                );
                db_execute(
                    "UPDATE products SET avg_rating=?, review_count=? WHERE id=?",
                    'dii', [$stats['avg_r'] ?? 5, $stats['cnt'] ?? 0, $product['id']]
                );

                flash('success', 'Thank you! Your review has been published.');
            }
        }
        header("Location: product.php?id={$product['id']}#reviews");
        exit;
    }
}

// Fetch Reviews
$reviews = db_fetch_all(
    "SELECT r.*, u.first_name, u.last_name, u.avatar
     FROM reviews r
     JOIN users u ON u.id = r.user_id
     WHERE r.product_id = ? AND r.is_approved = 1
     ORDER BY r.created_at DESC",
    'i', [$product['id']]
);

// Related Products
$related = db_fetch_all(
    "SELECT * FROM products 
     WHERE category_id = ? AND id != ? AND is_active = 1 
     ORDER BY RAND() LIMIT 4",
    'ii', [$product['category_id'], $product['id']]
);

$page_title = $product['name'];
$page_desc  = $product['short_desc'] ?: truncate($product['description'], 160);

log_access();
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5 navbar-spacer">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="file.php?category=<?= $product['category_id'] ?>"><?= e($product['category_name'] ?? 'Shop') ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($product['name']) ?></li>
        </ol>
    </nav>

    <?php render_flash(); ?>

    <div class="row g-5">
        <!-- Product Image -->
        <div class="col-lg-6">
            <div class="card p-3 border-0 bg-dark-subtle rounded-4 shadow-sm text-center">
                <img src="<?= e(product_image_url($product['image_path'])) ?>" 
                     alt="<?= e($product['name']) ?>" 
                     class="img-fluid rounded-3" 
                     style="max-height: 480px; object-fit: contain; width: 100%;">
            </div>
        </div>

        <!-- Product Summary -->
        <div class="col-lg-6 d-flex flex-column">
            <span class="badge bg-accent-subtle text-accent mb-2 align-self-start fs-6 px-3 py-1">
                <?= e($product['category_name'] ?? 'Product') ?>
            </span>
            <h1 class="display-6 fw-bold mb-3"><?= e($product['name']) ?></h1>

            <div class="d-flex align-items-center gap-3 mb-3">
                <div>
                    <?= render_stars((float)($product['avg_rating'] ?? 5)) ?>
                    <span class="text-muted ms-1 small">(<?= (int)$product['review_count'] ?> reviews)</span>
                </div>
                <span class="text-muted">&bull;</span>
                <span class="small text-muted">SKU: <?= e($product['sku'] ?? 'N/A') ?></span>
            </div>

            <div class="mb-4">
                <?php if ($product['sale_price']): ?>
                    <span class="display-6 fw-bold text-success me-2"><?= format_currency($product['sale_price']) ?></span>
                    <span class="fs-4 text-muted text-decoration-line-through me-2"><?= format_currency($product['price']) ?></span>
                    <span class="badge bg-danger">Save <?= format_currency($product['price'] - $product['sale_price']) ?></span>
                <?php else: ?>
                    <span class="display-6 fw-bold text-light"><?= format_currency($product['price']) ?></span>
                <?php endif; ?>
            </div>

            <p class="text-muted mb-4"><?= e($product['short_desc'] ?: $product['description']) ?></p>

            <div class="mb-4">
                <span class="fw-semibold me-2">Availability:</span>
                <?php if ($product['stock_quantity'] > 0): ?>
                    <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle me-1"></i>In Stock (<?= $product['stock_quantity'] ?> left)</span>
                <?php else: ?>
                    <span class="badge bg-danger-subtle text-danger"><i class="bi bi-x-circle me-1"></i>Out of Stock</span>
                <?php endif; ?>
            </div>

            <!-- Add to Cart Form -->
            <form method="POST" action="product.php?id=<?= $product['id'] ?>" class="mt-auto">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_to_cart">
                <div class="row g-3 align-items-center mb-4">
                    <div class="col-auto">
                        <label for="quantity" class="form-label mb-0 fw-semibold">Quantity:</label>
                    </div>
                    <div class="col-auto">
                        <input type="number" id="quantity" name="quantity" class="form-control form-control-lg bg-dark text-light border-secondary text-center" 
                               value="1" min="1" max="<?= max(1, $product['stock_quantity']) ?>" style="width: 100px;" <?= $product['stock_quantity'] < 1 ? 'disabled' : '' ?>>
                    </div>
                    <div class="col">
                        <button type="submit" class="btn btn-accent btn-lg w-100 fw-bold shadow-accent" <?= $product['stock_quantity'] < 1 ? 'disabled' : '' ?>>
                            <i class="bi bi-cart-plus-fill me-2"></i> Add to Shopping Cart
                        </button>
                    </div>
                </div>
            </form>

            <div class="border-top border-secondary pt-3 mt-3 d-flex gap-4 text-muted small">
                <div><i class="bi bi-shield-check me-1 text-accent"></i> 100% Authentic Guarantee</div>
                <div><i class="bi bi-truck me-1 text-accent"></i> Fast Express Shipping</div>
            </div>
        </div>
    </div>

    <!-- Description & Reviews Tabs -->
    <div class="mt-5 pt-4">
        <ul class="nav nav-tabs border-secondary mb-4" id="productTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="desc-tab" data-bs-toggle="tab" data-bs-target="#desc-pane" type="button" role="tab">Full Overview</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews-pane" type="button" role="tab">Customer Reviews (<?= count($reviews) ?>)</button>
            </li>
        </ul>

        <div class="tab-content text-light" id="productTabsContent">
            <!-- Overview -->
            <div class="tab-pane fade show active" id="desc-pane" role="tabpanel">
                <div class="card p-4 border-secondary bg-dark-subtle rounded-4">
                    <h5 class="fw-bold mb-3">Product Specifications & Details</h5>
                    <p style="white-space: pre-line;"><?= e($product['description']) ?></p>
                </div>
            </div>

            <!-- Reviews -->
            <div class="tab-pane fade" id="reviews-pane" role="tabpanel">
                <div class="row g-4">
                    <div class="col-lg-7">
                        <?php if (empty($reviews)): ?>
                            <div class="card p-4 border-secondary text-center text-muted">
                                <i class="bi bi-chat-square-text fs-2 mb-2"></i>
                                <p>No reviews yet for this product. Be the first to share your feedback!</p>
                            </div>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-3">
                                <?php foreach ($reviews as $rev): ?>
                                    <div class="card p-3 border-secondary bg-dark-subtle rounded-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="avatar-sm">
                                                    <?= strtoupper(substr($rev['first_name'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0 fw-bold"><?= e($rev['first_name'] . ' ' . $rev['last_name']) ?></h6>
                                                    <span class="text-muted small"><?= time_ago($rev['created_at']) ?></span>
                                                </div>
                                            </div>
                                            <div><?= render_stars((float)$rev['rating']) ?></div>
                                        </div>
                                        <?php if ($rev['title']): ?>
                                            <h6 class="fw-bold text-accent mb-1"><?= e($rev['title']) ?></h6>
                                        <?php endif; ?>
                                        <p class="mb-0 text-muted small"><?= e($rev['body']) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-lg-5">
                        <div class="card p-4 border-secondary bg-dark-subtle rounded-4">
                            <h5 class="fw-bold mb-3">Write a Review</h5>
                            <?php if (is_logged_in()): ?>
                                <form method="POST" action="product.php?id=<?= $product['id'] ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="submit_review">
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Rating</label>
                                        <select name="rating" class="form-select bg-dark text-light border-secondary">
                                            <option value="5">★★★★★ (5/5 Excellent)</option>
                                            <option value="4">★★★★☆ (4/5 Good)</option>
                                            <option value="3">★★★☆☆ (3/5 Average)</option>
                                            <option value="2">★★☆☆☆ (2/5 Below Average)</option>
                                            <option value="1">★☆☆☆☆ (1/5 Poor)</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Review Title</label>
                                        <input type="text" name="title" class="form-control bg-dark text-light border-secondary" placeholder="Summarize your opinion">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Your Review</label>
                                        <textarea name="body" class="form-control bg-dark text-light border-secondary" rows="4" placeholder="What did you like or dislike?"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-accent w-100">Submit Review</button>
                                </form>
                            <?php else: ?>
                                <p class="text-muted small">Please log in to leave a product review.</p>
                                <a href="login.php?next=<?= urlencode("product.php?id={$product['id']}") ?>" class="btn btn-outline-accent w-100">Log In to Review</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
