<?php
/**
 * SecureShop - Admin Categories Management
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
require_admin();

$errors = [];

// Handle Actions (Add, Toggle Active, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect();
    $action = sanitize_enum($_POST['action'] ?? '', ['add', 'toggle_active', 'delete']);

    if ($action === 'add') {
        $name        = sanitize_string($_POST['name'] ?? '', 100);
        $slug        = sanitize_slug($_POST['slug'] ?? '') ?: sanitize_slug(strtolower(str_replace(' ', '-', $name)));
        $description = sanitize_textarea($_POST['description'] ?? '', 500);

        if (!$name) $errors[] = 'Category name is required.';
        if (!$slug) $errors[] = 'Category slug is required.';

        if (empty($errors)) {
            $exists = db_fetch_value("SELECT COUNT(*) FROM categories WHERE slug=?", 's', [$slug]);
            if ($exists) {
                $errors[] = 'Slug already exists. Please choose another.';
            } else {
                db_insert(
                    "INSERT INTO categories (name, slug, description, is_active, created_at) VALUES (?, ?, ?, 1, NOW())",
                    'sss', [$name, $slug, $description]
                );
                flash('success', 'Category "' . e($name) . '" created.');
                header('Location: categories.php');
                exit;
            }
        }
    } elseif ($action === 'toggle_active') {
        $cid = sanitize_id($_POST['category_id'] ?? 0);
        if ($cid) {
            db_execute("UPDATE categories SET is_active = NOT is_active WHERE id=?", 'i', [$cid]);
            flash('info', 'Category status toggled.');
        }
        header('Location: categories.php');
        exit;
    } elseif ($action === 'delete') {
        $cid = sanitize_id($_POST['category_id'] ?? 0);
        if ($cid) {
            db_execute("DELETE FROM categories WHERE id=?", 'i', [$cid]);
            flash('warning', 'Category deleted.');
        }
        header('Location: categories.php');
        exit;
    }
}

$categories = db_fetch_all("SELECT c.*, COUNT(p.id) as product_count FROM categories c LEFT JOIN products p ON p.category_id=c.id GROUP BY c.id ORDER BY c.sort_order ASC, c.name ASC");

$page_title = 'Manage Categories';
$page_desc  = 'Store category management.';

log_access();
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-4 py-4 navbar-spacer">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 fw-bold mb-1"><i class="bi bi-grid-3x3-gap me-2 text-warning"></i> Categories</h1>
            <p class="text-muted mb-0">Organize your store product taxonomy.</p>
        </div>
        <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#catModal">
            <i class="bi bi-plus-lg me-1"></i> Add Category
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
                        <th scope="col" class="ps-4">Category Name</th>
                        <th scope="col">Slug</th>
                        <th scope="col">Product Count</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="pe-4 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $c): ?>
                        <tr>
                            <td class="ps-4 py-3"><strong class="text-light"><?= e($c['name']) ?></strong></td>
                            <td><code><?= e($c['slug']) ?></code></td>
                            <td><span class="badge bg-secondary"><?= $c['product_count'] ?> products</span></td>
                            <td>
                                <form method="POST" action="categories.php" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="category_id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $c['is_active'] ? 'btn-success' : 'btn-outline-secondary' ?>">
                                        <?= $c['is_active'] ? 'Active' : 'Disabled' ?>
                                    </button>
                                </form>
                            </td>
                            <td class="pe-4 text-end">
                                <form method="POST" action="categories.php" class="d-inline" onsubmit="return confirm('Delete category?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="category_id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="catModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold">Add Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="categories.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small">Name</label>
                        <input type="text" name="name" class="form-control bg-dark text-light border-secondary" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Slug (Optional)</label>
                        <input type="text" name="slug" class="form-control bg-dark text-light border-secondary" placeholder="auto-generated-if-empty">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Description</label>
                        <textarea name="description" class="form-control bg-dark text-light border-secondary" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-accent fw-bold">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
