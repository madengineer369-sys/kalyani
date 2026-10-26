<?php
/**
 * Black Stone - Manual Product Listing (Shopkeeper / Vendor)
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

send_security_headers();
session_start_secure();
require_shopkeeper();

$user    = get_auth_user();
$user_id = (int)$user['id'];
$errors  = [];
$old     = [];

// Fetch categories for dropdown
$categories = db_fetch_all("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect();

    $name        = sanitize_string($_POST['name'] ?? '', 255);
    $cat_id      = sanitize_id($_POST['category_id'] ?? 0);
    $custom_cat  = sanitize_string($_POST['custom_category'] ?? '', 100);
    $price       = sanitize_price($_POST['price'] ?? 0);
    $sale_price  = !empty($_POST['sale_price']) ? sanitize_price($_POST['sale_price']) : null;
    $stock       = sanitize_int($_POST['stock_quantity'] ?? 50, 0, 999999);
    $sku         = sanitize_string($_POST['sku'] ?? ('BS-' . strtoupper(bin2hex(random_bytes(3)))), 100);
    $short_desc  = sanitize_string($_POST['short_desc'] ?? '', 500);
    $description = sanitize_textarea($_POST['description'] ?? '');
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;

    $old = compact('name', 'cat_id', 'custom_cat', 'price', 'sale_price', 'stock', 'sku', 'short_desc', 'description');

    // Validation
    if (!$name) $errors['name'][] = 'Product title is required.';
    if ($price <= 0) $errors['price'][] = 'Please fix a valid selling price.';
    if ($sale_price !== null && $sale_price >= $price) {
        $errors['sale_price'][] = 'Sale price must be lower than the regular price.';
    }

    // Handle Custom Category creation if typed
    if (!empty($custom_cat)) {
        $custom_slug = unique_slug(generate_slug($custom_cat), 'categories');
        $cat_id = db_insert(
            "INSERT INTO categories (name, slug, description, is_active, sort_order, created_at)
             VALUES (?, ?, 'Custom apparel category', 1, 10, NOW())",
            'ss', [$custom_cat, $custom_slug]
        );
    }

    if (!$cat_id) {
        $errors['category_id'][] = 'Please select or create a category for this apparel.';
    }

    // Image Upload Handling
    $image_path = null;
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $upload_res = validate_file_upload(
            $_FILES['product_image'],
            ALLOWED_IMAGE_TYPES,
            ALLOWED_IMAGE_EXTENSIONS,
            MAX_UPLOAD_SIZE
        );

        if (!$upload_res['ok']) {
            $errors['product_image'][] = $upload_res['error'];
        } else {
            $filename = generate_upload_filename($upload_res['ext']);
            $destination = UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename;
            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $destination)) {
                $image_path = $filename;
            } else {
                $errors['product_image'][] = 'Failed to save uploaded image. Please check server permissions.';
            }
        }
    }

    if (empty($errors)) {
        $slug = unique_slug(generate_slug($name), 'products');

        $product_id = db_insert(
            "INSERT INTO products (shopkeeper_id, category_id, name, slug, description, short_desc, sku, price, sale_price, stock_quantity, image_path, is_active, is_featured, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())",
            'iisssssddisi',
            [
                $user_id,
                $cat_id,
                $name,
                $slug,
                $description,
                $short_desc,
                $sku,
                $price,
                $sale_price,
                $stock,
                $image_path,
                $is_featured
            ]
        );

        log_audit('ADD_PRODUCT', 'product', $product_id);
        flash('success', "Product \"{$name}\" successfully listed!");
        header('Location: products.php');
        exit;
    }
}

$page_title = 'List New Product';
$page_desc  = 'Upload images, set prices, and write apparel descriptions.';
log_access();
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom border-secondary">
        <div>
            <h1 class="h3 fw-bold mb-1 text-light"><i class="bi bi-tag-fill me-2 text-warning"></i> List New Clothing Product</h1>
            <p class="text-muted small mb-0">Manually upload photos, fix selling price, and define streetwear specifications.</p>
        </div>
        <a href="products.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Inventory
        </a>
    </div>

    <?php render_flash(); ?>

    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card bg-dark border-secondary rounded-4 p-4 p-md-5 shadow-lg">
                <form method="POST" action="add-product.php" enctype="multipart/form-data">
                    <?= render_csrf_field(); ?>

                    <!-- Title & SKU -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-8">
                            <label class="form-label text-light fw-semibold" for="name">Product Title / Name *</label>
                            <input type="text" name="name" id="name" class="form-control bg-black text-light border-secondary <?= field_class($errors, 'name') ?>"
                                   placeholder="e.g. Black Stone Obsidian Heavyweight Hoodie" value="<?= e($old['name'] ?? '') ?>" required>
                            <?php render_field_error($errors, 'name'); ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-light fw-semibold" for="sku">SKU Code</label>
                            <input type="text" name="sku" id="sku" class="form-control bg-black text-light border-secondary"
                                   placeholder="BS-XXXX" value="<?= e($old['sku'] ?? '') ?>">
                            <div class="form-text text-muted small">Auto-generated if left blank.</div>
                        </div>
                    </div>

                    <!-- Category selection -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label text-light fw-semibold" for="category_id">Collection / Category *</label>
                            <select name="category_id" id="category_id" class="form-select bg-black text-light border-secondary <?= field_class($errors, 'category_id') ?>">
                                <option value="">-- Choose Category --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= ((int)($old['cat_id'] ?? 0) === (int)$cat['id']) ? 'selected' : '' ?>>
                                        <?= e($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php render_field_error($errors, 'category_id'); ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-light fw-semibold" for="custom_category">Or Create New Category</label>
                            <input type="text" name="custom_category" id="custom_category" class="form-control bg-black text-light border-secondary"
                                   placeholder="e.g. Denim Jackets, Caps" value="<?= e($old['custom_cat'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Price & Stock -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label text-light fw-semibold" for="price">Fix Selling Price (₹) *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-black border-secondary text-warning fw-bold">₹</span>
                                <input type="number" step="0.01" min="1" name="price" id="price" class="form-control bg-black text-light border-secondary <?= field_class($errors, 'price') ?>"
                                       placeholder="2499.00" value="<?= e($old['price'] ?? '') ?>" required>
                            </div>
                            <?php render_field_error($errors, 'price'); ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-light fw-semibold" for="sale_price">Sale Price (Optional ₹)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-black border-secondary text-danger fw-bold">₹</span>
                                <input type="number" step="0.01" min="1" name="sale_price" id="sale_price" class="form-control bg-black text-light border-secondary <?= field_class($errors, 'sale_price') ?>"
                                       placeholder="1999.00" value="<?= e($old['sale_price'] ?? '') ?>">
                            </div>
                            <?php render_field_error($errors, 'sale_price'); ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-light fw-semibold" for="stock_quantity">Available Inventory Stock *</label>
                            <input type="number" min="0" name="stock_quantity" id="stock_quantity" class="form-control bg-black text-light border-secondary"
                                   value="<?= e($old['stock'] ?? '50') ?>" required>
                        </div>
                    </div>

                    <!-- Short Description -->
                    <div class="mb-4">
                        <label class="form-label text-light fw-semibold" for="short_desc">Quick Highlight Catchphrase</label>
                        <input type="text" name="short_desc" id="short_desc" class="form-control bg-black text-light border-secondary"
                               placeholder="e.g. 260GSM combed cotton, oversized streetwear drop-shoulder fit." value="<?= e($old['short_desc'] ?? '') ?>" maxlength="500">
                    </div>

                    <!-- Detailed Description -->
                    <div class="mb-4">
                        <label class="form-label text-light fw-semibold" for="description">Detailed Description (Fabric, GSM, Fit & Care)</label>
                        <textarea name="description" id="description" rows="5" class="form-control bg-black text-light border-secondary"
                                  placeholder="Describe the fabric weight, feel, ribbing collar, wash instructions, and mystery box code inclusion..."><?= e($old['description'] ?? '') ?></textarea>
                    </div>

                    <!-- Image Upload -->
                    <div class="mb-4">
                        <label class="form-label text-light fw-semibold">Upload Product Image</label>
                        <div class="image-upload-dropzone" onclick="document.getElementById('productImageInput').click();">
                            <i class="bi bi-cloud-arrow-up-fill fs-1 text-warning mb-2 d-block"></i>
                            <span class="text-light fw-semibold d-block">Click or Drag & Drop Image Here</span>
                            <span class="text-muted small">Supports JPG, PNG, WEBP (Max 5MB)</span>
                            <input type="file" name="product_image" id="productImageInput" class="d-none" accept="image/jpeg,image/png,image/webp">
                            <img id="productImagePreview" class="image-preview-target" alt="Preview">
                        </div>
                        <?php render_field_error($errors, 'product_image'); ?>
                    </div>

                    <!-- Featured Checkbox -->
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" name="is_featured" id="is_featured" <?= !empty($old['is_featured']) ? 'checked' : '' ?>>
                        <label class="form-check-label text-light" for="is_featured">
                            Feature this piece on the homepage showcase
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <div class="d-flex gap-3">
                        <button type="submit" class="btn btn-accent py-3 px-5 fw-bold shadow-gold">
                            <i class="bi bi-check2-circle me-2"></i> Publish Clothing Product
                        </button>
                        <a href="products.php" class="btn btn-outline-secondary py-3 px-4">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
