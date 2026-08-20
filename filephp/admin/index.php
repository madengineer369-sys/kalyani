<?php
/**
 * SecureShop - Admin Dashboard Overview
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

$total_revenue = (float)db_fetch_value("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status != 'cancelled'");
$total_orders  = (int)db_fetch_value("SELECT COUNT(*) FROM orders");
$total_users   = (int)db_fetch_value("SELECT COUNT(*) FROM users WHERE role='user'");
$total_products= (int)db_fetch_value("SELECT COUNT(*) FROM products");
$low_stock     = db_fetch_all("SELECT * FROM products WHERE stock_quantity <= low_stock_at AND is_active=1 LIMIT 5");

$recent_orders = db_fetch_all("SELECT o.*, u.first_name, u.last_name FROM orders o JOIN users u ON u.id=o.user_id ORDER BY o.created_at DESC LIMIT 5");

$page_title = 'Admin Panel';
$page_desc  = 'Store management overview.';

log_access();
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-4 py-4 navbar-spacer">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 fw-bold mb-1"><i class="bi bi-shield-fill-check me-2 text-warning"></i> Admin Panel</h1>
            <p class="text-muted mb-0">Overview of store metrics, performance, and management.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="products.php" class="btn btn-accent btn-sm"><i class="bi bi-box-seam me-1"></i> Manage Products</a>
            <a href="orders.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-bag-check me-1"></i> Manage Orders</a>
            <a href="discounts.php" class="btn btn-outline-warning btn-sm"><i class="bi bi-ticket-perforated me-1"></i> Discounts</a>
            <a href="users.php" class="btn btn-outline-info btn-sm"><i class="bi bi-people me-1"></i> Manage Users</a>
        </div>
    </div>

    <?php render_flash(); ?>

    <!-- Metrics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card p-4 border-secondary bg-dark-subtle rounded-4 shadow-sm">
                <span class="text-muted small fw-semibold">TOTAL REVENUE</span>
                <h3 class="fw-bold text-success my-2"><?= format_currency($total_revenue) ?></h3>
                <span class="text-muted small">From non-cancelled orders</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-4 border-secondary bg-dark-subtle rounded-4 shadow-sm">
                <span class="text-muted small fw-semibold">TOTAL ORDERS</span>
                <h3 class="fw-bold text-accent my-2"><?= $total_orders ?></h3>
                <a href="orders.php" class="text-muted small text-decoration-none">View all orders &rarr;</a>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-4 border-secondary bg-dark-subtle rounded-4 shadow-sm">
                <span class="text-muted small fw-semibold">ACTIVE PRODUCTS</span>
                <h3 class="fw-bold text-warning my-2"><?= $total_products ?></h3>
                <a href="products.php" class="text-muted small text-decoration-none">View catalog &rarr;</a>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-4 border-secondary bg-dark-subtle rounded-4 shadow-sm">
                <span class="text-muted small fw-semibold">REGISTERED CUSTOMERS</span>
                <h3 class="fw-bold text-info my-2"><?= $total_users ?></h3>
                <a href="users.php" class="text-muted small text-decoration-none">Manage users &rarr;</a>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Recent Orders Table -->
        <div class="col-lg-8">
            <div class="card border-secondary bg-dark-subtle rounded-4 p-4 shadow-sm">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-secondary">
                    <h5 class="fw-bold mb-0">Recent Orders</h5>
                    <a href="orders.php" class="btn btn-outline-accent btn-sm">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0">
                        <thead class="text-muted small border-secondary">
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $ro): ?>
                                <tr>
                                    <td><strong class="text-accent">#<?= e($ro['order_number']) ?></strong></td>
                                    <td><?= e($ro['first_name'] . ' ' . $ro['last_name']) ?></td>
                                    <td class="small text-muted"><?= format_date($ro['created_at']) ?></td>
                                    <td class="fw-bold text-success"><?= format_currency($ro['total_amount']) ?></td>
                                    <td><?= order_status_badge($ro['status']) ?></td>
                                    <td class="text-end">
                                        <a href="../order-detail.php?id=<?= $ro['id'] ?>" class="btn btn-sm btn-outline-secondary">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Low Stock Alerts -->
        <div class="col-lg-4">
            <div class="card border-danger bg-dark-subtle rounded-4 p-4 shadow-sm">
                <h5 class="fw-bold text-danger mb-3"><i class="bi bi-exclamation-triangle me-2"></i> Low Stock Alerts</h5>
                <?php if (empty($low_stock)): ?>
                    <p class="text-muted small mb-0">All products have sufficient stock.</p>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($low_stock as $ls): ?>
                            <div class="bg-dark p-3 rounded-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="text-light d-block small"><?= e($ls['name']) ?></strong>
                                    <span class="text-danger small">Only <?= $ls['stock_quantity'] ?> left in stock</span>
                                </div>
                                <a href="products.php?edit=<?= $ls['id'] ?>" class="btn btn-outline-warning btn-sm">Restock</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
