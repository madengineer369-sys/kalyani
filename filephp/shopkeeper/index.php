<?php
/**
 * SecureShop - Shopkeeper Vendor Dashboard
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
$user_id = $user['id'];

// Shopkeeper product list
$page_num = current_page_number();
$total    = (int)db_fetch_value("SELECT COUNT(*) FROM products WHERE shopkeeper_id=?", 'i', [$user_id]);
$pg       = paginate($total, ITEMS_PER_PAGE, $page_num);

$products = db_fetch_all(
    "SELECT p.*, c.name as category_name
     FROM products p
     LEFT JOIN categories c ON c.id=p.category_id
     WHERE p.shopkeeper_id=?
     ORDER BY p.id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}",
    'i', [$user_id]
);

$page_title = 'Shopkeeper Portal';
$page_desc  = 'Vendor product management.';

log_access();
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-4 py-4 navbar-spacer">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1"><i class="bi bi-shop me-2 text-info"></i> Shopkeeper Portal</h1>
            <p class="text-muted mb-0">Manage products listed under your vendor account.</p>
        </div>
        <a href="../admin/products.php" class="btn btn-info text-dark fw-bold">
            <i class="bi bi-plus-lg me-1"></i> Add / Edit Products
        </a>
    </div>

    <?php render_flash(); ?>

    <div class="card border-secondary bg-dark-subtle rounded-4 overflow-hidden shadow-sm">
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead class="text-muted small border-secondary">
                    <tr>
                        <th scope="col" class="ps-4">Product Name</th>
                        <th scope="col">Category</th>
                        <th scope="col">Price</th>
                        <th scope="col">Stock</th>
                        <th scope="col" class="pe-4 text-end">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No products assigned to your shopkeeper account yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="<?= e(product_image_url($p['image_path'])) ?>" alt="<?= e($p['name']) ?>" class="rounded-2" style="width: 44px; height: 44px; object-fit: cover;">
                                        <div>
                                            <strong class="text-light d-block"><?= e($p['name']) ?></strong>
                                            <span class="text-muted small">SKU: <?= e($p['sku'] ?? 'N/A') ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><?= e($p['category_name'] ?? 'General') ?></td>
                                <td><span class="text-success fw-bold"><?= format_currency($p['sale_price'] ?: $p['price']) ?></span></td>
                                <td><span class="badge bg-secondary"><?= $p['stock_quantity'] ?></span></td>
                                <td class="pe-4 text-end">
                                    <span class="badge bg-<?= $p['is_active'] ? 'success' : 'secondary' ?>"><?= $p['is_active'] ? 'Active' : 'Draft' ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        <?php render_pagination($pg, 'index.php'); ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
