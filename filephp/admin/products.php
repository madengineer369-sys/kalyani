<?php
/**
 * SecureShop - Admin Products Management
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
require_admin();

$user = get_auth_user();
$errors = [];

// Handle Actions (Add, Edit, Toggle Active/Featured, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect();
    $action = sanitize_enum($_POST['action'] ?? '', ['add', 'edit', 'toggle_active', 'toggle_featured', 'delete']);

    if ($action === 'add' || $action === 'edit') {
        $name        = sanitize_string($_POST['name'] ?? '', 255);
        $category_id = sanitize_id($_POST['category_id'] ?? 0);
        $sku         = sanitize_string($_POST['sku'] ?? '', 100);
        $price       = sanitize_price($_POST['price'] ?? 0);
        $sale_price  = !empty($_POST['sale_price']) ? sanitize_price($_POST['sale_price']) : null;
        $stock       = sanitize_int($_POST['stock_quantity'] ?? 0, 0, 99999);
        $short_desc  = sanitize_string($_POST['short_desc'] ?? '', 500);
        $description = sanitize_textarea($_POST['description'] ?? '', 5000);
        $slug        = sanitize_slug($_POST['slug'] ?? '') ?: sanitize_slug(strtolower(str_replace(' ', '-', $name)));

        if (!$name)  $errors[] = 'Product name is required.';
        if ($price < 0) $errors[] = 'Price must be a valid positive amount.';
        if (!$slug)  $errors[] = 'Valid product slug is required.';

        // Image file upload handling
        $image_path = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES['image']['tmp_name'];
            $mime     = mime_content_type($tmp_name);
            $ext      = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

            if (in_array($mime, ALLOWED_IMAGE_TYPES, true) && in_array($ext, ALLOWED_IMAGE_EXTENSIONS, true)) {
                $new_name = 'prod_' . bin2hex(random_bytes(8)) . '.' . $ext;
                $dest     = UPLOAD_PATH . DIRECTORY_SEPARATOR . $new_name;

                if (!is_dir(UPLOAD_PATH)) {
                    mkdir(UPLOAD_PATH, 0755, true);
                }

                if (move_uploaded_file($tmp_name, $dest)) {
                    $image_path = $new_name;
                }
            } else {
                $errors[] = 'Invalid image type. Allowed: JPG, PNG, WEBP, GIF.';
            }
        }

        if (empty($errors)) {
            if ($action === 'add') {
                db_insert(
                    "INSERT INTO products (shopkeeper_id, category_id, name, slug, description, short_desc, sku, price, sale_price, stock_quantity, image_path, is_active, is_featured, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, NOW())",
                    'iisssssddis',
                    [$user['id'], $category_id, $name, $slug, $description, $short_desc, $sku, $price, $sale_price, $stock, $image_path]
                );
                flash('success', 'Product "' . e($name) . '" created successfully.');
            } else {
                $pid = sanitize_id($_POST['product_id'] ?? 0);
                if ($image_path) {
                    db_execute(
                        "UPDATE products SET category_id=?, name=?, slug=?, description=?, short_desc=?, sku=?, price=?, sale_price=?, stock_quantity=?, image_path=?, updated_at=NOW() WHERE id=?",
                        'isssssddisi',
                        [$category_id, $name, $slug, $description, $short_desc, $sku, $price, $sale_price, $stock, $image_path, $pid]
                    );
                } else {
                    db_execute(
                        "UPDATE products SET category_id=?, name=?, slug=?, description=?, short_desc=?, sku=?, price=?, sale_price=?, stock_quantity=?, updated_at=NOW() WHERE id=?",
                        'isssssddii',
                        [$category_id, $name, $slug, $description, $short_desc, $sku, $price, $sale_price, $stock, $pid]
                    );
                }
                flash('success', 'Product updated successfully.');
            }
            header('Location: products.php');
            exit;
        }
    } elseif ($action === 'toggle_active') {
        $pid = sanitize_id($_POST['product_id'] ?? 0);
        if ($pid) {
            db_execute("UPDATE products SET is_active = NOT is_active WHERE id=?", 'i', [$pid]);
            flash('info', 'Product active status toggled.');
        }
    } elseif ($action === 'toggle_featured') {
        $pid = sanitize_id($_POST['product_id'] ?? 0);
        if ($pid) {
            db_execute("UPDATE products SET is_featured = NOT is_featured WHERE id=?", 'i', [$pid]);
            flash('info', 'Product featured status toggled.');
        }
    } elseif ($action === 'delete') {
        $pid = sanitize_id($_POST['product_id'] ?? 0);
        if ($pid) {
            db_execute("DELETE FROM products WHERE id=?", 'i', [$pid]);
            flash('warning', 'Product deleted.');
        }
    }
}

// Fetch products & categories
$page_num = current_page_number();
$total    = (int)db_fetch_value("SELECT COUNT(*) FROM products");
$pg       = paginate($total, ADMIN_ITEMS_PER_PAGE, $page_num);

$products = db_fetch_all(
    "SELECT p.*, c.name as category_name
     FROM products p
     LEFT JOIN categories c ON c.id=p.category_id
     ORDER BY p.id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}"
);

$categories = db_fetch_all("SELECT * FROM categories ORDER BY name ASC");

$page_title = 'Manage Products';
$page_desc  = 'Admin product inventory management.';

log_access();
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-4 py-4 navbar-spacer">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 fw-bold mb-1"><i class="bi bi-box-seam me-2 text-warning"></i> Product Management</h1>
            <p class="text-muted mb-0">Total <?= pluralise($total, 'product') ?> in inventory</p>
        </div>
        <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#productModal">
            <i class="bi bi-plus-lg me-1"></i> Add New Product
        </button>
    </div>

    <?php render_flash(); ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card border-secondary bg-dark-subtle rounded-4 overflow-hidden shadow-sm">
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead class="text-muted small border-secondary">
                    <tr>
                        <th scope="col" class="ps-4">Product</th>
                        <th scope="col">Category</th>
                        <th scope="col">Price</th>
                        <th scope="col">Stock</th>
                        <th scope="col">Active</th>
                        <th scope="col">Featured</th>
                        <th scope="col" class="pe-4 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
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
                            <td>
                                <?php if ($p['sale_price']): ?>
                                    <span class="text-success fw-bold"><?= format_currency($p['sale_price']) ?></span>
                                    <span class="text-muted text-decoration-line-through small"><?= format_currency($p['price']) ?></span>
                                <?php else: ?>
                                    <span class="text-light fw-bold"><?= format_currency($p['price']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($p['stock_quantity'] <= $p['low_stock_at']): ?>
                                    <span class="badge bg-danger"><?= $p['stock_quantity'] ?> (LOW)</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?= $p['stock_quantity'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" action="products.php" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $p['is_active'] ? 'btn-success' : 'btn-outline-secondary' ?>">
                                        <?= $p['is_active'] ? 'Active' : 'Disabled' ?>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <form method="POST" action="products.php" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_featured">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $p['is_featured'] ? 'btn-warning text-dark' : 'btn-outline-secondary' ?>">
                                        <?= $p['is_featured'] ? 'Featured' : 'Standard' ?>
                                    </button>
                                </form>
                            </td>
                            <td class="pe-4 text-end">
                                <form method="POST" action="products.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        <?php render_pagination($pg, 'products.php'); ?>
    </div>
</div>

<!-- Add Product Modal -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold">Add New Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="products.php" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label small">Product Name</label>
                            <input type="text" name="name" class="form-control bg-dark text-light border-secondary" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Category</label>
                            <select name="category_id" class="form-select bg-dark text-light border-secondary">
                                <option value="0">General</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">SKU Code</label>
                            <input type="text" name="sku" class="form-control bg-dark text-light border-secondary" placeholder="PRD-101">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Regular Price (₹)</label>
                            <input type="number" step="0.01" name="price" class="form-control bg-dark text-light border-secondary" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Sale Price (Optional)</label>
                            <input type="number" step="0.01" name="sale_price" class="form-control bg-dark text-light border-secondary">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Stock Quantity</label>
                            <input type="number" name="stock_quantity" class="form-control bg-dark text-light border-secondary" value="10" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Product Image File</label>
                            <input type="file" name="image" class="form-control bg-dark text-light border-secondary" accept="image/*">
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Short Description</label>
                            <input type="text" name="short_desc" class="form-control bg-dark text-light border-secondary">
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Full Description</label>
                            <textarea name="description" class="form-control bg-dark text-light border-secondary" rows="4"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-accent fw-bold">Create Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
