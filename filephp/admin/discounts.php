<?php
/**
 * SecureShop - Admin Discount Codes Management
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

$user   = get_auth_user();
$errors = [];

// Handle Actions (Add code, Toggle active, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect();
    $action = sanitize_enum($_POST['action'] ?? '', ['add', 'toggle_active', 'delete']);

    if ($action === 'add') {
        $code             = sanitize_discount_code($_POST['code'] ?? '');
        $description      = sanitize_string($_POST['description'] ?? '', 500);
        $discount_type    = sanitize_enum($_POST['discount_type'] ?? 'percent', ['percent', 'fixed']);
        $discount_value   = sanitize_price($_POST['discount_value'] ?? 0);
        $min_order_amount = sanitize_price($_POST['min_order_amount'] ?? 0);
        $max_discount     = !empty($_POST['max_discount']) ? sanitize_price($_POST['max_discount']) : null;
        $max_uses         = !empty($_POST['max_uses']) ? sanitize_int($_POST['max_uses']) : null;

        if (!$code) $errors[] = 'Valid discount code (uppercase letters/numbers) is required.';
        if ($discount_value <= 0) $errors[] = 'Discount value must be greater than 0.';

        if (empty($errors)) {
            $exists = db_fetch_value("SELECT COUNT(*) FROM discount_codes WHERE code=?", 's', [$code]);
            if ($exists) {
                $errors[] = 'Discount code "' . e($code) . '" already exists.';
            } else {
                db_insert(
                    "INSERT INTO discount_codes (code, description, discount_type, discount_value, min_order_amount, max_discount, max_uses, is_active, created_by, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())",
                    'sssdddii', [$code, $description, $discount_type, $discount_value, $min_order_amount, $max_discount, $max_uses, $user['id']]
                );
                flash('success', 'Discount code "' . e($code) . '" created.');
                header('Location: discounts.php');
                exit;
            }
        }
    } elseif ($action === 'toggle_active') {
        $did = sanitize_id($_POST['discount_id'] ?? 0);
        if ($did) {
            db_execute("UPDATE discount_codes SET is_active = NOT is_active WHERE id=?", 'i', [$did]);
            flash('info', 'Discount code status updated.');
        }
        header('Location: discounts.php');
        exit;
    } elseif ($action === 'delete') {
        $did = sanitize_id($_POST['discount_id'] ?? 0);
        if ($did) {
            db_execute("DELETE FROM discount_codes WHERE id=?", 'i', [$did]);
            flash('warning', 'Discount code deleted.');
        }
        header('Location: discounts.php');
        exit;
    }
}

$discounts = db_fetch_all("SELECT * FROM discount_codes ORDER BY created_at DESC");

$page_title = 'Manage Discounts';
$page_desc  = 'Manage promotional coupon codes.';

log_access();
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-4 py-4 navbar-spacer">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 fw-bold mb-1"><i class="bi bi-ticket-perforated me-2 text-warning"></i> Discount Codes</h1>
            <p class="text-muted mb-0">Promotions and coupon management.</p>
        </div>
        <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#discountModal">
            <i class="bi bi-plus-lg me-1"></i> Add Discount Code
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
                        <th scope="col" class="ps-4">Code</th>
                        <th scope="col">Type & Value</th>
                        <th scope="col">Min Order</th>
                        <th scope="col">Uses</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="pe-4 text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($discounts as $d): ?>
                        <tr>
                            <td class="ps-4 py-3">
                                <strong class="text-warning fs-6"><code><?= e($d['code']) ?></code></strong>
                                <span class="text-muted small d-block"><?= e($d['description']) ?></span>
                            </td>
                            <td>
                                <?php if ($d['discount_type'] === 'percent'): ?>
                                    <span class="badge bg-info text-dark fw-bold"><?= format_percent($d['discount_value']) ?> OFF</span>
                                <?php else: ?>
                                    <span class="badge bg-success fw-bold"><?= format_currency($d['discount_value']) ?> FLAT OFF</span>
                                <?php endif; ?>
                            </td>
                            <td><?= format_currency($d['min_order_amount']) ?></td>
                            <td><?= $d['used_count'] ?> / <?= $d['max_uses'] ?? '&infin;' ?></td>
                            <td>
                                <form method="POST" action="discounts.php" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="discount_id" value="<?= $d['id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $d['is_active'] ? 'btn-success' : 'btn-outline-secondary' ?>">
                                        <?= $d['is_active'] ? 'Active' : 'Disabled' ?>
                                    </button>
                                </form>
                            </td>
                            <td class="pe-4 text-end">
                                <form method="POST" action="discounts.php" class="d-inline" onsubmit="return confirm('Delete code?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="discount_id" value="<?= $d['id'] ?>">
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
<div class="modal fade" id="discountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold">Add Discount Code</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="discounts.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small">Code Name (Uppercase)</label>
                        <input type="text" name="code" class="form-control bg-dark text-light border-secondary text-uppercase" placeholder="SUMMER20" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Description</label>
                        <input type="text" name="description" class="form-control bg-dark text-light border-secondary" placeholder="20% off on all items">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small">Type</label>
                            <select name="discount_type" class="form-select bg-dark text-light border-secondary">
                                <option value="percent">Percentage (%)</option>
                                <option value="fixed">Fixed Amount (₹)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Discount Value</label>
                            <input type="number" step="0.01" name="discount_value" class="form-control bg-dark text-light border-secondary" placeholder="10 or 100" required>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small">Min Order Amount (₹)</label>
                            <input type="number" step="0.01" name="min_order_amount" class="form-control bg-dark text-light border-secondary" value="500.00">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Max Uses Limit</label>
                            <input type="number" name="max_uses" class="form-control bg-dark text-light border-secondary" placeholder="Leave empty for unlimited">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-accent fw-bold">Create Coupon</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
