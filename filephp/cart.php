<?php
/**
 * SecureShop - Shopping Cart
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

// Handle POST actions (update qty, remove item, apply coupon, clear cart)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect();
    $action = sanitize_enum($_POST['action'] ?? '', ['update', 'remove', 'apply_discount', 'clear_discount']);

    if ($action === 'update') {
        $cart_id = sanitize_id($_POST['cart_id'] ?? 0);
        $qty     = sanitize_int($_POST['quantity'] ?? 1, 1, 999);
        if ($cart_id && $qty > 0) {
            cart_update_quantity($cart_id, $qty);
            flash('success', 'Cart updated successfully.');
        }
    } elseif ($action === 'remove') {
        $cart_id = sanitize_id($_POST['cart_id'] ?? 0);
        if ($cart_id) {
            cart_remove_item($cart_id);
            flash('info', 'Item removed from your cart.');
        }
    } elseif ($action === 'apply_discount') {
        $code = sanitize_discount_code($_POST['discount_code'] ?? '');
        if ($code) {
            $discount_row = db_fetch_one("SELECT * FROM discount_codes WHERE code=? AND is_active=1 LIMIT 1", 's', [$code]);
            if ($discount_row) {
                $_SESSION['applied_discount'] = $discount_row;
                flash('success', 'Discount code "' . e($code) . '" applied successfully!');
            } else {
                flash('danger', 'Invalid or expired discount code.');
            }
        }
    } elseif ($action === 'clear_discount') {
        unset($_SESSION['applied_discount']);
        flash('info', 'Discount code removed.');
    }

    header('Location: cart.php');
    exit;
}

$cart_items    = cart_get_items();
$applied_code  = $_SESSION['applied_discount'] ?? null;
$totals        = calculate_order_totals($cart_items, $applied_code, DEFAULT_TAX_RATE);

$page_title = 'Shopping Cart';
$page_desc  = 'Review your items and proceed to checkout.';

log_access();
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5 navbar-spacer">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 fw-bold mb-0"><i class="bi bi-cart3 me-2 text-accent"></i> Shopping Cart</h1>
        <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Continue Shopping</a>
    </div>

    <?php render_flash(); ?>

    <?php if (empty($cart_items)): ?>
        <div class="card p-5 text-center my-4 border-secondary bg-dark-subtle rounded-4">
            <div class="mb-3"><i class="bi bi-cart-x fs-1 text-muted"></i></div>
            <h4 class="fw-bold">Your cart is currently empty</h4>
            <p class="text-muted">Discover our collection and add items to your cart.</p>
            <div class="mt-3">
                <a href="index.php" class="btn btn-accent px-4 py-2">Explore Products</a>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <!-- Items Table -->
            <div class="col-lg-8">
                <div class="card border-secondary bg-dark-subtle rounded-4 overflow-hidden shadow-sm">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle mb-0">
                            <thead class="table-dark text-muted small border-secondary">
                                <tr>
                                    <th scope="col" class="ps-4 py-3">Product</th>
                                    <th scope="col" class="py-3 text-center">Price</th>
                                    <th scope="col" class="py-3 text-center">Quantity</th>
                                    <th scope="col" class="py-3 text-end">Subtotal</th>
                                    <th scope="col" class="pe-4 py-3 text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cart_items as $item): ?>
                                    <?php 
                                    $unit_price = (float)($item['sale_price'] ?: $item['price']);
                                    $line_total = $unit_price * (int)$item['quantity'];
                                    ?>
                                    <tr>
                                        <td class="ps-4 py-3">
                                            <div class="d-flex align-items-center gap-3">
                                                <img src="<?= e(product_image_url($item['image_path'])) ?>" alt="<?= e($item['name']) ?>" class="rounded-2" style="width: 54px; height: 54px; object-fit: cover;">
                                                <div>
                                                    <a href="product.php?id=<?= $item['product_id'] ?>" class="fw-semibold text-light text-decoration-none d-block"><?= e($item['name']) ?></a>
                                                    <span class="text-muted small">Stock: <?= $item['stock_quantity'] ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center py-3">
                                            <span class="fw-medium"><?= format_currency($unit_price) ?></span>
                                        </td>
                                        <td class="text-center py-3" style="width: 140px;">
                                            <form method="POST" action="cart.php" class="d-inline-flex align-items-center justify-content-center">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="update">
                                                <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                                <input type="number" name="quantity" value="<?= $item['quantity'] ?>" min="1" max="<?= $item['stock_quantity'] ?>" 
                                                       class="form-control form-control-sm bg-dark text-light border-secondary text-center" 
                                                       onchange="this.form.submit()" style="width: 65px;">
                                            </form>
                                        </td>
                                        <td class="text-end py-3 fw-bold text-accent">
                                            <?= format_currency($line_total) ?>
                                        </td>
                                        <td class="pe-4 py-3 text-end">
                                            <form method="POST" action="cart.php" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="remove">
                                                <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger border-0" title="Remove Item">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Discount Code Input -->
                <div class="card p-4 border-secondary bg-dark-subtle rounded-4 mt-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-ticket-perforated me-2 text-accent"></i> Discount Code / Promo</h6>
                    <?php if ($applied_code): ?>
                        <div class="d-flex align-items-center justify-content-between bg-success-subtle p-3 rounded-3 text-success">
                            <div>
                                <strong><?= e($applied_code['code']) ?></strong> — <?= e($applied_code['description'] ?: 'Discount Applied') ?>
                            </div>
                            <form method="POST" action="cart.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="clear_discount">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="cart.php" class="d-flex gap-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="apply_discount">
                            <input type="text" name="discount_code" class="form-control bg-dark text-light border-secondary" placeholder="Enter coupon code (e.g. WELCOME10, FLAT200)" required>
                            <button type="submit" class="btn btn-accent px-4">Apply</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="col-lg-4">
                <div class="card p-4 border-secondary bg-dark-subtle rounded-4 shadow-sm">
                    <h5 class="fw-bold mb-4 pb-2 border-bottom border-secondary">Order Summary</h5>

                    <div class="d-flex justify-content-between mb-2 text-muted">
                        <span>Items Subtotal</span>
                        <span class="text-light fw-medium"><?= format_currency($totals['subtotal']) ?></span>
                    </div>

                    <?php if ($totals['discount'] > 0): ?>
                        <div class="d-flex justify-content-between mb-2 text-success">
                            <span>Discount</span>
                            <span class="fw-medium">- <?= format_currency($totals['discount']) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between mb-2 text-muted">
                        <span>Estimated Tax (GST <?= $totals['tax_rate'] ?>%)</span>
                        <span class="text-light fw-medium"><?= format_currency($totals['tax']) ?></span>
                    </div>

                    <div class="d-flex justify-content-between mb-3 text-muted">
                        <span>Shipping Fee</span>
                        <span class="text-light fw-medium">
                            <?= $totals['shipping'] == 0 ? '<span class="text-success">FREE</span>' : format_currency($totals['shipping']) ?>
                        </span>
                    </div>

                    <?php if ($totals['subtotal'] < FREE_SHIPPING_ABOVE): ?>
                        <div class="alert alert-info py-2 small mb-3">
                            Add <?= format_currency(FREE_SHIPPING_ABOVE - $totals['subtotal']) ?> more for <strong>FREE Shipping</strong>!
                        </div>
                    <?php endif; ?>

                    <div class="border-top border-secondary pt-3 mt-2 d-flex justify-content-between align-items-center mb-4">
                        <span class="fs-5 fw-bold">Total Amount</span>
                        <span class="fs-4 fw-bold text-accent"><?= format_currency($totals['total']) ?></span>
                    </div>

                    <a href="checkout.php" class="btn btn-accent btn-lg w-100 fw-bold shadow-accent py-3">
                        Proceed to Checkout <i class="bi bi-arrow-right ms-2"></i>
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
