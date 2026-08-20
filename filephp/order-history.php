<?php
/**
 * SecureShop - Order History
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
require_once __DIR__ . '/includes/pagination.php';

send_security_headers();
session_start_secure();
require_auth('order-history.php');

$user_id  = current_user_id();
$page_num = current_page_number();
$status   = sanitize_enum($_GET['status'] ?? '', ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded']);

$where = ['user_id = ?'];
$params = [$user_id];
$types = 'i';

if ($status) {
    $where[] = 'status = ?';
    $params[] = $status;
    $types .= 's';
}

$where_sql = 'WHERE ' . implode(' AND ', $where);

$total = (int)db_fetch_value("SELECT COUNT(*) FROM orders {$where_sql}", $types, $params);
$pg    = paginate($total, ITEMS_PER_PAGE, $page_num);

$sql = "SELECT o.*, 
               (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
        FROM orders o
        {$where_sql}
        ORDER BY o.created_at DESC
        LIMIT {$pg['per_page']} OFFSET {$pg['offset']}";

$orders = db_fetch_all($sql, $types, $params);

$page_title = 'My Orders';
$page_desc  = 'View your complete order history.';

log_access();
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5 navbar-spacer">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 fw-bold mb-1"><i class="bi bi-bag-check me-2 text-accent"></i> My Orders</h1>
            <p class="text-muted mb-0">Total <?= pluralise($total, 'order') ?></p>
        </div>
        <form method="GET" action="order-history.php" class="d-flex align-items-center gap-2">
            <select name="status" class="form-select form-select-sm bg-dark text-light border-secondary" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="confirmed" <?= $status === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                <option value="processing" <?= $status === 'processing' ? 'selected' : '' ?>>Processing</option>
                <option value="shipped" <?= $status === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                <option value="delivered" <?= $status === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </form>
    </div>

    <?php render_flash(); ?>

    <?php if (empty($orders)): ?>
        <div class="card p-5 text-center my-4 border-secondary bg-dark-subtle rounded-4">
            <i class="bi bi-box-seam fs-1 text-muted mb-3"></i>
            <h5>No orders found</h5>
            <p class="text-muted">You haven't placed any orders matching this criteria yet.</p>
            <a href="index.php" class="btn btn-accent mx-auto px-4">Start Shopping</a>
        </div>
    <?php else: ?>
        <div class="d-flex flex-column gap-3">
            <?php foreach ($orders as $o): ?>
                <div class="card border-secondary bg-dark-subtle rounded-4 overflow-hidden shadow-sm p-4">
                    <div class="row align-items-center g-3">
                        <div class="col-md-3">
                            <span class="text-muted small d-block">Order Number</span>
                            <strong class="text-accent fs-6">#<?= e($o['order_number']) ?></strong>
                            <span class="text-muted small d-block mt-1"><?= format_datetime($o['created_at']) ?></span>
                        </div>
                        <div class="col-md-2">
                            <span class="text-muted small d-block">Total Items</span>
                            <span class="fw-semibold text-light"><?= pluralise($o['item_count'], 'item') ?></span>
                        </div>
                        <div class="col-md-2">
                            <span class="text-muted small d-block">Total Price</span>
                            <span class="fw-bold text-success fs-6"><?= format_currency($o['total_amount']) ?></span>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted small d-block mb-1">Status</span>
                            <?= order_status_badge($o['status']) ?>
                            <span class="badge bg-secondary ms-1"><?= strtoupper($o['payment_method']) ?></span>
                        </div>
                        <div class="col-md-2 text-md-end">
                            <a href="order-detail.php?id=<?= $o['id'] ?>" class="btn btn-outline-accent btn-sm">
                                View Details <i class="bi bi-chevron-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-4">
            <?php render_pagination($pg, 'order-history.php?status=' . urlencode($status)); ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
