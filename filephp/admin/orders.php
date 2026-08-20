<?php
/**
 * SecureShop - Admin Orders Management
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

// Handle Status Change POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect();
    $action = sanitize_enum($_POST['action'] ?? '', ['update_status']);

    if ($action === 'update_status') {
        $order_id   = sanitize_id($_POST['order_id'] ?? 0);
        $new_status = sanitize_enum($_POST['new_status'] ?? '', ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded']);
        $notes      = sanitize_string($_POST['notes'] ?? '', 250);

        if ($order_id && $new_status) {
            $old_order = db_fetch_one("SELECT status FROM orders WHERE id=? LIMIT 1", 'i', [$order_id]);
            if ($old_order) {
                db_execute("UPDATE orders SET status=?, updated_at=NOW() WHERE id=?", 'si', [$new_status, $order_id]);
                db_insert(
                    "INSERT INTO order_status_history (order_id, old_status, new_status, notes, changed_by, created_at)
                     VALUES (?, ?, ?, ?, ?, NOW())",
                    'isssi', [$order_id, $old_order['status'], $new_status, $notes, $user['id']]
                );
                log_audit('UPDATE_ORDER_STATUS', 'order', $order_id, ['old' => $old_order['status']], ['new' => $new_status]);
                flash('success', 'Order status updated to ' . ucfirst($new_status));
            }
        }
    }
    header('Location: orders.php');
    exit;
}

$status_filter = sanitize_enum($_GET['status'] ?? '', ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded']);
$page_num      = current_page_number();

$where = ['1=1'];
$params = [];
$types  = '';

if ($status_filter) {
    $where[] = 'o.status = ?';
    $params[] = $status_filter;
    $types   .= 's';
}

$where_sql = 'WHERE ' . implode(' AND ', $where);

$total = (int)db_fetch_value("SELECT COUNT(*) FROM orders o {$where_sql}", $types, $params);
$pg    = paginate($total, ADMIN_ITEMS_PER_PAGE, $page_num);

$orders = db_fetch_all(
    "SELECT o.*, u.first_name, u.last_name, u.email
     FROM orders o
     JOIN users u ON u.id = o.user_id
     {$where_sql}
     ORDER BY o.created_at DESC
     LIMIT {$pg['per_page']} OFFSET {$pg['offset']}",
    $types, $params
);

$page_title = 'Manage Orders';
$page_desc  = 'Admin order fulfillment management.';

log_access();
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-4 py-4 navbar-spacer">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 fw-bold mb-1"><i class="bi bi-bag-check me-2 text-warning"></i> Store Orders</h1>
            <p class="text-muted mb-0">Total <?= pluralise($total, 'order') ?></p>
        </div>
        <form method="GET" action="orders.php" class="d-flex gap-2">
            <select name="status" class="form-select bg-dark text-light border-secondary" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                <option value="processing" <?= $status_filter === 'processing' ? 'selected' : '' ?>>Processing</option>
                <option value="shipped" <?= $status_filter === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                <option value="delivered" <?= $status_filter === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </form>
    </div>

    <?php render_flash(); ?>

    <div class="card border-secondary bg-dark-subtle rounded-4 overflow-hidden shadow-sm">
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead class="text-muted small border-secondary">
                    <tr>
                        <th scope="col" class="ps-4">Order #</th>
                        <th scope="col">Customer</th>
                        <th scope="col">Date</th>
                        <th scope="col">Total</th>
                        <th scope="col">Current Status</th>
                        <th scope="col">Update Status</th>
                        <th scope="col" class="pe-4 text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                        <?php $next_opts = order_next_statuses($o['status']); ?>
                        <tr>
                            <td class="ps-4 py-3"><strong class="text-accent">#<?= e($o['order_number']) ?></strong></td>
                            <td>
                                <strong class="text-light d-block"><?= e($o['first_name'] . ' ' . $o['last_name']) ?></strong>
                                <span class="text-muted small"><?= e($o['email']) ?></span>
                            </td>
                            <td class="small text-muted"><?= format_date($o['created_at']) ?></td>
                            <td class="fw-bold text-success"><?= format_currency($o['total_amount']) ?></td>
                            <td><?= order_status_badge($o['status']) ?></td>
                            <td>
                                <?php if (!empty($next_opts)): ?>
                                    <form method="POST" action="orders.php" class="d-flex gap-1">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                        <select name="new_status" class="form-select form-select-sm bg-dark text-light border-secondary">
                                            <?php foreach ($next_opts as $opt): ?>
                                                <option value="<?= $opt ?>"><?= ucfirst($opt) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-accent">Update</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted small">Terminal State</span>
                                <?php endif; ?>
                            </td>
                            <td class="pe-4 text-end">
                                <a href="../order-detail.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-secondary">View Detail</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        <?php render_pagination($pg, 'orders.php?status=' . urlencode($status_filter)); ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
