<?php
/**
 * SecureShop - User Dashboard
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
require_once __DIR__ . '/includes/cart_helper.php';

send_security_headers();
session_start_secure();
require_auth('dashboard.php');

$user = get_auth_user();

// Dashboard Stats
$user_id = $user['id'];
$order_count = (int)db_fetch_value("SELECT COUNT(*) FROM orders WHERE user_id=?", 'i', [$user_id]);
$total_spent = (float)db_fetch_value("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE user_id=? AND status != 'cancelled'", 'i', [$user_id]);
$cart_count  = cart_count();
$code_count  = (int)db_fetch_value("SELECT COUNT(*) FROM mystery_codes WHERE user_id=? AND is_redeemed=0", 'i', [$user_id]);

// Recent 5 Orders
$recent_orders = db_fetch_all("SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC LIMIT 5", 'i', [$user_id]);

// Available Mystery Codes
$recent_codes = db_fetch_all("SELECT * FROM mystery_codes WHERE user_id=? AND is_redeemed=0 ORDER BY created_at DESC LIMIT 3", 'i', [$user_id]);

$page_title = 'User Dashboard';
$page_desc  = 'Welcome to your Black Stone account dashboard.';

log_access();
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5 navbar-spacer">
    <!-- Welcome Header -->
    <div class="card border-secondary bg-dark-subtle rounded-4 p-4 mb-4 shadow-sm">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="avatar-lg fs-3 fw-bold">
                    <?= strtoupper(substr($user['first_name'], 0, 1)) ?>
                </div>
                <div>
                    <h2 class="h4 fw-bold mb-1">Welcome back, <?= e($user['first_name'] . ' ' . $user['last_name']) ?>!</h2>
                    <p class="text-muted small mb-0">Member since <?= format_date($user['created_at']) ?> &bull; <?= e($user['email']) ?></p>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="profile.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-gear me-1"></i> Edit Profile</a>
                <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
            </div>
        </div>
    </div>

    <?php render_flash(); ?>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card p-4 border-secondary bg-dark-subtle rounded-4 shadow-sm text-center">
                <span class="text-muted small fw-semibold">TOTAL ORDERS</span>
                <h3 class="fw-bold text-accent my-2"><?= $order_count ?></h3>
                <a href="order-history.php" class="text-muted small text-decoration-none">View order history &rarr;</a>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-4 border-secondary bg-dark-subtle rounded-4 shadow-sm text-center">
                <span class="text-muted small fw-semibold">TOTAL SPENT</span>
                <h3 class="fw-bold text-success my-2"><?= format_currency($total_spent) ?></h3>
                <span class="text-muted small">Successful purchases</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-4 border-secondary bg-dark-subtle rounded-4 shadow-sm text-center">
                <span class="text-muted small fw-semibold">CART ITEMS</span>
                <h3 class="fw-bold text-warning my-2"><?= $cart_count ?></h3>
                <a href="cart.php" class="text-muted small text-decoration-none">View shopping cart &rarr;</a>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-4 border-secondary bg-dark-subtle rounded-4 shadow-sm text-center">
                <span class="text-muted small fw-semibold">MYSTERY CODES</span>
                <h3 class="fw-bold text-info my-2"><?= $code_count ?></h3>
                <a href="code-zone.php" class="text-muted small text-decoration-none">Redeem in Code Zone &rarr;</a>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Recent Orders -->
        <div class="col-lg-8">
            <div class="card border-secondary bg-dark-subtle rounded-4 p-4 shadow-sm">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-secondary">
                    <h5 class="fw-bold mb-0"><i class="bi bi-bag-check me-2 text-accent"></i> Recent Orders</h5>
                    <a href="order-history.php" class="btn btn-outline-accent btn-sm">View All</a>
                </div>

                <?php if (empty($recent_orders)): ?>
                    <p class="text-muted py-3 mb-0">You haven't placed any orders yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle mb-0">
                            <thead class="text-muted small border-secondary">
                                <tr>
                                    <th>Order #</th>
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
                                        <td class="small text-muted"><?= format_date($ro['created_at']) ?></td>
                                        <td class="fw-bold text-success"><?= format_currency($ro['total_amount']) ?></td>
                                        <td><?= order_status_badge($ro['status']) ?></td>
                                        <td class="text-end">
                                            <a href="order-detail.php?id=<?= $ro['id'] ?>" class="btn btn-sm btn-outline-secondary">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions & Mystery Codes -->
        <div class="col-lg-4">
            <!-- Unredeemed Mystery Codes -->
            <div class="card border-warning bg-dark-subtle rounded-4 p-4 mb-4 shadow-sm">
                <h6 class="fw-bold mb-3 text-warning"><i class="bi bi-ticket-perforated me-2"></i> Active Mystery Codes</h6>
                <?php if (empty($recent_codes)): ?>
                    <p class="text-muted small mb-0">No active mystery codes available. Place an order to earn new codes!</p>
                <?php else: ?>
                    <div class="d-flex flex-column gap-2 mb-3">
                        <?php foreach ($recent_codes as $rc): ?>
                            <div class="bg-dark p-2 px-3 rounded-3 d-flex justify-content-between align-items-center">
                                <code class="text-warning fw-bold"><?= e($rc['code']) ?></code>
                                <a href="code-zone.php?code=<?= urlencode($rc['code']) ?>" class="btn btn-warning btn-sm py-0">Use</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <a href="code-zone.php" class="btn btn-outline-warning w-100 btn-sm mt-2">Go to Code Zone</a>
            </div>

            <!-- Navigation Links -->
            <div class="card border-secondary bg-dark-subtle rounded-4 p-4 shadow-sm">
                <h6 class="fw-bold mb-3"><i class="bi bi-grid-fill me-2 text-accent"></i> Quick Menu</h6>
                <div class="list-group list-group-flush bg-transparent">
                    <a href="index.php" class="list-group-item bg-transparent text-light border-secondary px-0 py-2"><i class="bi bi-shop me-2 text-accent"></i> Shop Catalog</a>
                    <a href="cart.php" class="list-group-item bg-transparent text-light border-secondary px-0 py-2"><i class="bi bi-cart3 me-2 text-warning"></i> View Shopping Cart</a>
                    <a href="mystery-box.php" class="list-group-item bg-transparent text-light border-secondary px-0 py-2"><i class="bi bi-box-seam me-2 text-info"></i> Mystery Boxes</a>
                    <a href="profile.php" class="list-group-item bg-transparent text-light border-secondary px-0 py-2"><i class="bi bi-person me-2 text-success"></i> Profile Settings</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
