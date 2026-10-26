<?php
/**
 * SecureShop - Order Detail View
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

send_security_headers();
session_start_secure();
require_auth('order-detail.php');

$order_id = sanitize_id($_GET['id'] ?? 0);
$user_id  = current_user_id();

// Fetch Order (ensure it belongs to current user unless admin/shopkeeper)
$sql = "SELECT * FROM orders WHERE id = ?";
$params = [$order_id];
$types  = 'i';

if (!is_admin() && !is_shopkeeper()) {
    $sql .= " AND user_id = ?";
    $params[] = $user_id;
    $types   .= 'i';
}

$order = db_fetch_one($sql, $types, $params);

if (!$order) {
    flash('danger', 'Order not found or access denied.');
    header('Location: order-history.php');
    exit;
}

// Order Items
$items = db_fetch_all(
    "SELECT oi.*, p.image_path, p.slug
     FROM order_items oi
     LEFT JOIN products p ON p.id = oi.product_id
     WHERE oi.order_id = ?",
    'i', [$order['id']]
);

// Status History
$history = db_fetch_all(
    "SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at ASC",
    'i', [$order['id']]
);

// Mystery Box Codes for this order
$mystery_codes = db_fetch_all(
    "SELECT * FROM mystery_codes WHERE order_id = ?",
    'i', [$order['id']]
);

$shipping_info = json_decode($order['shipping_snapshot'] ?? '{}', true);

$page_title = 'Order #' . $order['order_number'];
$page_desc  = 'View details for order #' . $order['order_number'];

log_access();
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5 navbar-spacer">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <a href="order-history.php" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left me-1"></i> Back to Orders</a>
            <h1 class="h3 fw-bold mb-0 mt-1">Order #<?= e($order['order_number']) ?></h1>
            <span class="text-muted small">Placed on <?= format_datetime($order['created_at']) ?></span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?= order_status_badge($order['status']) ?>
            <span class="badge bg-secondary"><?= strtoupper($order['payment_method']) ?></span>
        </div>
    </div>

    <?php render_flash(); ?>

    <?php if (isset($_GET['placed'])): ?>
        <div class="alert alert-success p-4 rounded-4 mb-4 border-0 bg-success-subtle text-success">
            <h5 class="fw-bold mb-2"><i class="bi bi-check-circle-fill me-2"></i> Thank you for your order!</h5>
            <p class="mb-0">Your order has been received and is being processed. You can track its status below.</p>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Main Details -->
        <div class="col-lg-8">
            <!-- Items Card -->
            <div class="card border-secondary bg-dark-subtle rounded-4 overflow-hidden shadow-sm mb-4">
                <div class="card-header bg-dark border-secondary py-3 px-4 fw-bold">
                    <i class="bi bi-bag me-2 text-accent"></i> Order Items
                </div>
                <div class="table-responsive">
                    <table class="table table-dark align-middle mb-0">
                        <thead class="text-muted small border-secondary">
                            <tr>
                                <th scope="col" class="ps-4">Product</th>
                                <th scope="col" class="text-center">Price</th>
                                <th scope="col" class="text-center">Qty</th>
                                <th scope="col" class="pe-4 text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td class="ps-4 py-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <img src="<?= e(product_image_url($item['image_path'] ?? null)) ?>" alt="<?= e($item['product_name']) ?>" class="rounded-2" style="width: 48px; height: 48px; object-fit: cover;">
                                            <span class="fw-semibold text-light"><?= e($item['product_name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="text-center py-3"><?= format_currency($item['unit_price']) ?></td>
                                    <td class="text-center py-3"><?= $item['quantity'] ?></td>
                                    <td class="pe-4 text-end py-3 fw-bold text-accent"><?= format_currency($item['total_price']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mystery Box Codes Unlocked Card -->
            <?php if (!empty($mystery_codes)): ?>
                <div class="card border-accent bg-dark-subtle rounded-4 p-4 mb-4 shadow-accent">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="avatar-lg bg-warning text-dark"><i class="bi bi-gift-fill fs-2"></i></div>
                        <div>
                            <h5 class="fw-bold mb-1 text-warning">Mystery Box Codes Unlocked!</h5>
                            <p class="text-muted small mb-0">You earned exclusive codes with this order. Redeem them in the Code Zone to open your Mystery Boxes.</p>
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-2 mt-2">
                        <?php foreach ($mystery_codes as $mc): ?>
                            <div class="d-flex align-items-center justify-content-between bg-dark p-3 rounded-3 border border-warning">
                                <div>
                                    <code class="fs-5 text-warning fw-bold me-2"><?= e($mc['code']) ?></code>
                                    <span class="badge bg-secondary"><?= e($mc['prize_title']) ?></span>
                                </div>
                                <div>
                                    <?php if ($mc['is_redeemed']): ?>
                                        <span class="badge bg-success"><i class="bi bi-check me-1"></i> Redeemed</span>
                                    <?php else: ?>
                                        <a href="code-zone.php?code=<?= urlencode($mc['code']) ?>" class="btn btn-warning btn-sm fw-bold">Redeem Now</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Timeline Card -->
            <div class="card border-secondary bg-dark-subtle rounded-4 p-4 shadow-sm">
                <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-2 text-accent"></i> Order Progress Timeline</h6>
                <div class="position-relative ps-4 border-start border-secondary ms-2">
                    <?php foreach ($history as $h): ?>
                        <div class="mb-3 position-relative">
                            <div class="position-absolute top-0 start-0 translate-middle bg-accent rounded-circle" style="width: 12px; height: 12px;"></div>
                            <div class="fw-semibold text-light"><?= ucfirst(e($h['new_status'])) ?></div>
                            <div class="text-muted small"><?= format_datetime($h['created_at']) ?> <?= $h['notes'] ? '&bull; ' . e($h['notes']) : '' ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar Summary -->
        <div class="col-lg-4">
            <!-- Price Summary -->
            <div class="card border-secondary bg-dark-subtle rounded-4 p-4 mb-4 shadow-sm">
                <h5 class="fw-bold mb-3 pb-2 border-bottom border-secondary">Payment Summary</h5>
                
                <div class="d-flex justify-content-between mb-2 text-muted small">
                    <span>Subtotal</span>
                    <span class="text-light fw-medium"><?= format_currency($order['subtotal']) ?></span>
                </div>

                <?php if ((float)$order['discount_amount'] > 0): ?>
                    <div class="d-flex justify-content-between mb-2 text-success small">
                        <span>Discount (<?= e($order['discount_code_used']) ?>)</span>
                        <span class="fw-medium">- <?= format_currency($order['discount_amount']) ?></span>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between mb-2 text-muted small">
                    <span>GST Tax</span>
                    <span class="text-light fw-medium"><?= format_currency($order['tax_amount']) ?></span>
                </div>

                <div class="d-flex justify-content-between mb-3 text-muted small">
                    <span>Shipping Fee</span>
                    <span class="text-light fw-medium"><?= (float)$order['shipping_amount'] == 0 ? '<span class="text-success">FREE</span>' : format_currency($order['shipping_amount']) ?></span>
                </div>

                <div class="border-top border-secondary pt-3 mt-2 d-flex justify-content-between align-items-center">
                    <span class="fs-6 fw-bold">Total Amount</span>
                    <span class="fs-4 fw-bold text-accent"><?= format_currency($order['total_amount']) ?></span>
                </div>
            </div>

            <!-- Shipping Info Card -->
            <div class="card border-secondary bg-dark-subtle rounded-4 p-4 shadow-sm">
                <h5 class="fw-bold mb-3 pb-2 border-bottom border-secondary"><i class="bi bi-geo-alt me-2 text-accent"></i> Shipping Address</h5>
                <?php if ($shipping_info): ?>
                    <div class="fw-bold text-light"><?= e($shipping_info['full_name'] ?? '') ?></div>
                    <div class="text-muted small mt-1"><?= e($shipping_info['phone'] ?? '') ?></div>
                    <div class="text-muted small mt-2">
                        <?= e($shipping_info['address_line1'] ?? '') ?><br>
                        <?= e($shipping_info['address_line2'] ?? '') ?><?= !empty($shipping_info['address_line2']) ? '<br>' : '' ?>
                        <?= e($shipping_info['city'] ?? '') ?>, <?= e($shipping_info['state'] ?? '') ?> - <?= e($shipping_info['postal_code'] ?? '') ?><br>
                        <?= e($shipping_info['country'] ?? 'India') ?>
                    </div>
                <?php else: ?>
                    <span class="text-muted small">Shipping address snapshot unavailable.</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
