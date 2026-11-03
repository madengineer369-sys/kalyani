<?php
/**
 * Black Stone - Shopkeeper Product Inventory Management
 */
define('ECOMMERCE_APP', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/logging.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/pagination.php';

send_security_headers();
session_start_secure();
require_shopkeeper();

$user    = get_auth_user();
$user_id = (int)$user['id'];

// Handle Delete or Toggle Active Status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect();
    $action     = sanitize_enum($_POST['action'] ?? '', ['delete', 'toggle_active']);
    $product_id = sanitize_id($_POST['product_id'] ?? 0);

    if ($product_id > 0) {
        $p = db_fetch_one("SELECT id, name, is_active FROM products WHERE id=? " . (is_admin() ? "" : "AND shopkeeper_id=?"), 'ii', is_admin() ? [$product_id] : [$product_id, $user_id]);
        if ($p) {
            if ($action === 'delete') {
                db_execute("DELETE FROM products WHERE id=?", 'i', [$product_id]);
                log_audit('DELETE_PRODUCT', 'product', $product_id);
                flash('success', "Product \"{$p['name']}\" deleted.");
            } elseif ($action === 'toggle_active') {
                $new_status = $p['is_active'] ? 0 : 1;
                db_execute("UPDATE products SET is_active=? WHERE id=?", 'ii', [$new_status, $product_id]);
                flash('info', "Product status updated to " . ($new_status ? 'Active' : 'Draft'));
            }
        }
    }
    header('Location: products.php');
    exit;
}

$page_num = current_page_number();
$total_query = is_admin() ? "SELECT COUNT(*) FROM products" : "SELECT COUNT(*) FROM products WHERE shopkeeper_id=?";
$total = (int)(is_admin() ? db_fetch_value($total_query) : db_fetch_value($total_query, 'i', [$user_id]));
$pg    = paginate($total, ITEMS_PER_PAGE, $page_num);

$products_query = "SELECT p.*, c.name AS category_name, c.slug AS category_slug
                   FROM products p
                   LEFT JOIN categories c ON c.id = p.category_id " .
                   (is_admin() ? "" : "WHERE p.shopkeeper_id=? ") .
                   "ORDER BY p.id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}";

$products = is_admin() ? db_fetch_all($products_query) : db_fetch_all($products_query, 'i', [$user_id]);

$page_title = 'Product Inventory';
$page_desc  = 'Manage your listed clothing pieces.';
log_access();
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-2 border-bottom border-secondary">
        <div>
            <h1 class="h3 fw-bold mb-1 text-light"><i class="bi bi-boxes me-2 text-warning"></i> Apparel Inventory Management</h1>
            <p class="text-muted small mb-0">Total of <?= $total ?> products currently registered.</p>
        </div>
        <a href="add-product.php" class="btn btn-accent fw-bold shadow-gold">
            <i class="bi bi-plus-lg me-1"></i> Add / List New Piece
        </a>
    </div>

    <?php render_flash(); ?>

    <div class="card bg-dark border-secondary rounded-4 overflow-hidden shadow-sm">
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead class="border-secondary text-muted small text-uppercase">
                    <tr>
                        <th scope="col" class="ps-4">Product Details</th>
                        <th scope="col">Category</th>
                        <th scope="col">Price / MRP</th>
                        <th scope="col">Stock</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="pe-4 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 text-secondary mb-2 d-block"></i>
                                No products listed yet. Click "Add / List New Piece" to upload one.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="<?= e(product_image_url($p['image_path'])) ?>" alt="<?= e($p['name']) ?>" class="rounded-2" style="width: 50px; height: 50px; object-fit: cover;">
                                        <div>
                                            <a href="<?= e(url('product.php?slug=' . $p['slug'])) ?>" class="fw-bold text-light text-decoration-none d-block">
                                                <?= e($p['name']) ?>
                                            </a>
                                            <span class="text-muted small">SKU: <?= e($p['sku'] ?? 'N/A') ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-dark border border-secondary text-warning">
                                        <?= e($p['category_name'] ?: 'General') ?>
                                    </span>
                                </td>
                                <td>
                                    <div>
                                        <strong class="text-warning"><?= format_currency($p['sale_price'] ?: $p['price']) ?></strong>
                                        <?php if (!empty($p['sale_price']) && $p['sale_price'] < $p['price']): ?>
                                            <span class="text-muted small text-decoration-line-through ms-1"><?= format_currency($p['price']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $p['stock_quantity'] > 10 ? 'secondary' : 'danger' ?>">
                                        <?= $p['stock_quantity'] ?> in stock
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" action="products.php" class="d-inline">
                                        <?= render_csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-<?= $p['is_active'] ? 'success' : 'secondary' ?> rounded-pill py-0 px-2" style="font-size:0.75rem;">
                                            <?= $p['is_active'] ? 'Active' : 'Draft' ?>
                                        </button>
                                    </form>
                                </td>
                                <td class="pe-4 text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= e(url('product.php?slug=' . $p['slug'])) ?>" class="btn btn-outline-secondary" title="View product">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <form method="POST" action="products.php" class="d-inline" onsubmit="return confirm('Delete this product permanently?');">
                                            <?= render_csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger" title="Delete product">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        <?php render_pagination($pg, 'products.php'); ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
