<?php
/**
 * Black Stone - Admin Dashboard
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

$total_users    = (int)db_fetch_value("SELECT COUNT(*) FROM users");
$total_products = (int)db_fetch_value("SELECT COUNT(*) FROM products");
$total_orders   = (int)db_fetch_value("SELECT COUNT(*) FROM orders");
$total_revenue  = (float)db_fetch_value("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'paid'");

$recent_orders = db_fetch_all("SELECT o.*, u.first_name, u.email FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.id DESC LIMIT 5");

$page_title = 'Admin Command Center';
$page_desc  = 'System statistics, inventory, and operations.';
log_access();
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-2 border-bottom border-secondary">
        <div>
            <h1 class="h3 fw-bold mb-1 text-light"><i class="bi bi-shield-shaded me-2 text-warning"></i> Admin Command Center</h1>
            <p class="text-muted small mb-0">Platform telemetry, store revenue, and manual inventory control.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="add-product.php" class="btn btn-accent fw-bold shadow-gold">
                <i class="bi bi-plus-lg me-1"></i> List Product
            </a>
            <a href="products.php" class="btn btn-outline-secondary">
                <i class="bi bi-boxes me-1"></i> Manage Products
            </a>
        </div>
    </div>

    <?php render_flash(); ?>

    <!-- Stat Cards -->
    <div class="row g-4 mb-5">
        <div class="col-sm-6 col-lg-3">
            <div class="card bg-dark border-secondary p-4 rounded-4 shadow-sm">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">Apparel Listed</span>
                    <i class="bi bi-bag-fill fs-4 text-warning"></i>
                </div>
                <h2 class="display-6 fw-bold text-light mb-0"><?= $total_products ?></h2>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card bg-dark border-secondary p-4 rounded-4 shadow-sm">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">Total Orders</span>
                    <i class="bi bi-cart-check-fill fs-4 text-info"></i>
                </div>
                <h2 class="display-6 fw-bold text-light mb-0"><?= $total_orders ?></h2>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card bg-dark border-secondary p-4 rounded-4 shadow-sm">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">Verified Users</span>
                    <i class="bi bi-people-fill fs-4 text-success"></i>
                </div>
                <h2 class="display-6 fw-bold text-light mb-0"><?= $total_users ?></h2>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card bg-dark border-secondary p-4 rounded-4 shadow-sm">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small text-uppercase fw-semibold">Paid Revenue</span>
                    <i class="bi bi-currency-rupee fs-4 text-warning"></i>
                </div>
                <h2 class="display-6 fw-bold text-warning mb-0"><?= format_currency($total_revenue) ?></h2>
            </div>
        </div>
    </div>

    <!-- Quick Shortcuts & Recent Orders -->
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card bg-dark border-secondary rounded-4 overflow-hidden">
                <div class="card-header bg-black border-secondary py-3 px-4">
                    <h5 class="fw-bold mb-0 text-light"><i class="bi bi-receipt me-2 text-warning"></i> Recent Customer Orders</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0">
                        <thead class="text-muted small border-secondary">
                            <tr>
                                <th class="ps-4">Order #</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th class="pe-4 text-end">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_orders)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">No orders placed yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recent_orders as $ord): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-warning"><?= e($ord['order_number']) ?></td>
                                        <td><?= e($ord['first_name']) ?> <span class="text-muted small">(<?= e($ord['email']) ?>)</span></td>
                                        <td class="fw-bold"><?= format_currency($ord['total_amount']) ?></td>
                                        <td><span class="badge bg-secondary"><?= e(ucfirst($ord['status'])) ?></span></td>
                                        <td class="pe-4 text-end text-muted small"><?= time_ago($ord['created_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card bg-dark border-secondary rounded-4 p-4">
                <h5 class="fw-bold text-light mb-3"><i class="bi bi-gear-fill me-2 text-warning"></i> Quick Operations</h5>
                <div class="d-grid gap-2">
                    <a href="add-product.php" class="btn btn-accent fw-bold text-start py-2">
                        <i class="bi bi-plus-circle me-2"></i> List New Clothing Product
                    </a>
                    <a href="products.php" class="btn btn-outline-light text-start py-2">
                        <i class="bi bi-sliders me-2 text-warning"></i> Edit Existing Inventory
                    </a>
                    <a href="../code-zone.php" class="btn btn-outline-light text-start py-2">
                        <i class="bi bi-ticket-detailed me-2 text-info"></i> Test Mystery Codes
                    </a>
                    <a href="../index.php" class="btn btn-outline-light text-start py-2">
                        <i class="bi bi-eye me-2 text-success"></i> View Live Storefront
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
