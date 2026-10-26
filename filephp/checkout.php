<?php
/**
 * SecureShop - Checkout & Order Processing
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
require_auth('checkout.php');

$user       = get_auth_user();
$cart_items = cart_get_items();

if (empty($cart_items)) {
    flash('warning', 'Your cart is empty. Please add items before checking out.');
    header('Location: cart.php');
    exit;
}

// User Addresses
$addresses = db_fetch_all("SELECT * FROM shipping_addresses WHERE user_id=? ORDER BY is_default DESC, id DESC", 'i', [$user['id']]);

$applied_code = $_SESSION['applied_discount'] ?? null;
$totals       = calculate_order_totals($cart_items, $applied_code, DEFAULT_TAX_RATE);
$errors       = [];

// Handle Place Order
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect();

    $address_id     = sanitize_id($_POST['address_id'] ?? 0);
    $payment_method = sanitize_enum($_POST['payment_method'] ?? 'cod', ['cod', 'card', 'upi']);
    $notes          = sanitize_textarea($_POST['notes'] ?? '', 500);

    // New Address handling if selected
    if ($address_id === 0) {
        $full_name     = sanitize_name($_POST['full_name'] ?? '');
        $phone         = sanitize_phone($_POST['phone'] ?? '');
        $address_line1 = sanitize_string($_POST['address_line1'] ?? '', 300);
        $address_line2 = sanitize_string($_POST['address_line2'] ?? '', 300);
        $city          = sanitize_string($_POST['city'] ?? '', 100);
        $state         = sanitize_string($_POST['state'] ?? '', 100);
        $postal_code   = sanitize_postal_code($_POST['postal_code'] ?? '');

        if (!$full_name)     $errors[] = 'Full name is required.';
        if (!$phone)         $errors[] = 'Valid phone number is required.';
        if (!$address_line1) $errors[] = 'Address Line 1 is required.';
        if (!$city)          $errors[] = 'City is required.';
        if (!$state)         $errors[] = 'State is required.';
        if (!$postal_code)   $errors[] = 'Postal PIN code is required.';

        if (empty($errors)) {
            $address_id = db_insert(
                "INSERT INTO shipping_addresses (user_id, full_name, phone, address_line1, address_line2, city, state, postal_code, country, is_default, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'India', 1, NOW())",
                'isssssss', [$user['id'], $full_name, $phone, $address_line1, $address_line2, $city, $state, $postal_code]
            );
        }
    }

    if ($address_id <= 0) {
        $errors[] = 'Please select or add a valid shipping address.';
    }

    if (empty($errors)) {
        // Fetch full address for snapshot
        $address = db_fetch_one("SELECT * FROM shipping_addresses WHERE id=? AND user_id=? LIMIT 1", 'ii', [$address_id, $user['id']]);
        
        if (!$address) {
            $errors[] = 'Selected shipping address not found.';
        } else {
            // Begin Order Creation Transaction
            $order_number = generate_order_number();
            $address_snapshot = json_encode($address, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $discount_id   = $applied_code['id'] ?? null;
            $discount_code = $applied_code['code'] ?? null;
            $ip            = get_client_ip();

            $order_id = db_insert(
                "INSERT INTO orders 
                 (user_id, order_number, status, subtotal, discount_amount, tax_amount, shipping_amount, total_amount, 
                  discount_code_id, discount_code_used, shipping_address_id, shipping_snapshot, payment_method, payment_status, notes, tax_rate, ip_address, created_at)
                 VALUES (?, ?, 'confirmed', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?, ?, ?, NOW())",
                'isdddddisisssds',
                [
                    $user['id'], $order_number, $totals['subtotal'], $totals['discount'], $totals['tax'], $totals['shipping'], $totals['total'],
                    $discount_id, $discount_code, $address_id, $address_snapshot, $payment_method, $notes, $totals['tax_rate'], $ip
                ]
            );

            // Insert Order Items & Deduct Stock
            foreach ($cart_items as $item) {
                $unit_price = (float)($item['sale_price'] ?: $item['price']);
                $item_total = $unit_price * (int)$item['quantity'];

                db_insert(
                    "INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, total_price)
                     VALUES (?, ?, ?, ?, ?, ?)",
                    'iisidd', [$order_id, $item['product_id'], $item['name'], $item['quantity'], $unit_price, $item_total]
                );

                // Deduct product stock
                db_execute("UPDATE products SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id=?", 'ii', [$item['quantity'], $item['product_id']]);
            }

            // Record initial status history
            db_insert(
                "INSERT INTO order_status_history (order_id, old_status, new_status, notes, changed_by, created_at)
                 VALUES (?, NULL, 'confirmed', 'Order placed successfully', ?, NOW())",
                'ii', [$order_id, $user['id']]
            );

            // Generate Mystery Box Code for Black Stone purchase!
            $mystery_code = 'BLACKSTONE-' . strtoupper(bin2hex(random_bytes(4)));
            db_insert(
                "INSERT INTO mystery_codes (code, order_id, user_id, box_type, prize_title, prize_value, is_redeemed, created_at)
                 VALUES (?, ?, ?, 'legendary', 'Black Stone Mystery Box', '25% OFF Next Order + Mystery Gift', 0, NOW())",
                'sii', [$mystery_code, $order_id, $user['id']]
            );

            // Clear Cart & Applied Discount
            cart_clear();
            unset($_SESSION['applied_discount']);

            log_audit('PLACE_ORDER', 'order', $order_id);
            log_security('ORDER_CREATED', ['order_id' => $order_id, 'order_number' => $order_number]);

            flash('success', 'Order #' . $order_number . ' placed successfully! Your Mystery Box Code is: ' . $mystery_code);
            header("Location: order-detail.php?id={$order_id}&placed=1");
            exit;
        }
    }
}

$page_title = 'Checkout';
$page_desc  = 'Complete your order securely.';

log_access();
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5 navbar-spacer">
    <h1 class="h3 fw-bold mb-4"><i class="bi bi-shield-check me-2 text-accent"></i> Checkout</h1>

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

    <form method="POST" action="checkout.php">
        <?= csrf_field() ?>
        <div class="row g-4">
            <!-- Left Column: Address & Payment -->
            <div class="col-lg-7">
                <!-- Shipping Address -->
                <div class="card p-4 border-secondary bg-dark-subtle rounded-4 mb-4 shadow-sm">
                    <h5 class="fw-bold mb-3"><i class="bi bi-geo-alt me-2 text-accent"></i> 1. Shipping Address</h5>

                    <?php if (!empty($addresses)): ?>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Select Saved Address:</label>
                            <?php foreach ($addresses as $addr): ?>
                                <div class="form-check p-3 border border-secondary rounded-3 mb-2 bg-dark">
                                    <input class="form-check-input" type="radio" name="address_id" id="addr_<?= $addr['id'] ?>" value="<?= $addr['id'] ?>" <?= $addr['is_default'] ? 'checked' : '' ?>>
                                    <label class="form-check-input-label w-100 ms-2 text-light" for="addr_<?= $addr['id'] ?>">
                                        <strong class="d-block"><?= e($addr['full_name']) ?> (<?= e($addr['label']) ?>) — <?= e($addr['phone']) ?></strong>
                                        <span class="text-muted small">
                                            <?= e($addr['address_line1']) ?>, <?= e($addr['address_line2']) ? $addr['address_line2'] . ', ' : '' ?><?= e($addr['city']) ?>, <?= e($addr['state']) ?> - <?= e($addr['postal_code']) ?>
                                        </span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                            <div class="form-check p-3 border border-secondary rounded-3 mb-2 bg-dark">
                                <input class="form-check-input" type="radio" name="address_id" id="addr_new" value="0">
                                <label class="form-check-label ms-2 fw-semibold text-accent" for="addr_new">
                                    + Add New Shipping Address
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- New Address Fields (hidden unless 0 selected if addresses exist) -->
                    <div id="newAddressFields" class="<?= empty($addresses) ? '' : 'd-none' ?> border-top border-secondary pt-3 mt-3">
                        <h6 class="fw-semibold text-light mb-3">Enter New Address Details</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small">Full Name</label>
                                <input type="text" name="full_name" class="form-control bg-dark text-light border-secondary" value="<?= e($user['first_name'] . ' ' . $user['last_name']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Phone Number</label>
                                <input type="text" name="phone" class="form-control bg-dark text-light border-secondary" placeholder="+91 9876543210" value="<?= e($user['phone'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label small">Address Line 1</label>
                                <input type="text" name="address_line1" class="form-control bg-dark text-light border-secondary" placeholder="Flat / House No. / Street">
                            </div>
                            <div class="col-12">
                                <label class="form-label small">Address Line 2 (Optional)</label>
                                <input type="text" name="address_line2" class="form-control bg-dark text-light border-secondary" placeholder="Landmark / Area">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">City</label>
                                <input type="text" name="city" class="form-control bg-dark text-light border-secondary">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">State</label>
                                <input type="text" name="state" class="form-control bg-dark text-light border-secondary">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">PIN / Postal Code</label>
                                <input type="text" name="postal_code" class="form-control bg-dark text-light border-secondary">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Method -->
                <div class="card p-4 border-secondary bg-dark-subtle rounded-4 mb-4 shadow-sm">
                    <h5 class="fw-bold mb-3"><i class="bi bi-credit-card me-2 text-accent"></i> 2. Payment Method</h5>
                    <div class="d-flex flex-column gap-2">
                        <div class="form-check p-3 border border-secondary rounded-3 bg-dark">
                            <input class="form-check-input" type="radio" name="payment_method" id="pay_cod" value="cod" checked>
                            <label class="form-check-label ms-2 text-light" for="pay_cod">
                                <strong>Cash on Delivery (COD)</strong>
                                <span class="d-block text-muted small">Pay with cash when your package is delivered to your door.</span>
                            </label>
                        </div>
                        <div class="form-check p-3 border border-secondary rounded-3 bg-dark">
                            <input class="form-check-input" type="radio" name="payment_method" id="pay_upi" value="upi">
                            <label class="form-check-label ms-2 text-light" for="pay_upi">
                                <strong>UPI / GPay / PhonePe / Paytm</strong>
                                <span class="d-block text-muted small">Instant payment via UPI QR or VPA.</span>
                            </label>
                        </div>
                        <div class="form-check p-3 border border-secondary rounded-3 bg-dark">
                            <input class="form-check-input" type="radio" name="payment_method" id="pay_card" value="card">
                            <label class="form-check-label ms-2 text-light" for="pay_card">
                                <strong>Credit / Debit Card</strong>
                                <span class="d-block text-muted small">Visa, MasterCard, RuPay cards accepted.</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Order Notes -->
                <div class="card p-4 border-secondary bg-dark-subtle rounded-4 shadow-sm">
                    <h5 class="fw-bold mb-2"><i class="bi bi-journal-text me-2 text-accent"></i> Order Notes (Optional)</h5>
                    <textarea name="notes" class="form-control bg-dark text-light border-secondary" rows="3" placeholder="Special instructions for delivery..."></textarea>
                </div>
            </div>

            <!-- Right Column: Order Summary & Place Order Button -->
            <div class="col-lg-5">
                <div class="card p-4 border-secondary bg-dark-subtle rounded-4 shadow-sm sticky-top" style="top: 90px;">
                    <h5 class="fw-bold mb-4 pb-2 border-bottom border-secondary">Order Summary</h5>

                    <div class="mb-4" style="max-height: 250px; overflow-y: auto;">
                        <?php foreach ($cart_items as $item): ?>
                            <?php $price = (float)($item['sale_price'] ?: $item['price']); ?>
                            <div class="d-flex align-items-center justify-content-between mb-3 me-2">
                                <div class="d-flex align-items-center gap-3">
                                    <img src="<?= e(product_image_url($item['image_path'])) ?>" alt="<?= e($item['name']) ?>" class="rounded-2" style="width: 44px; height: 44px; object-fit: cover;">
                                    <div>
                                        <span class="fw-semibold text-light d-block small"><?= e($item['name']) ?></span>
                                        <span class="text-muted small">Qty: <?= $item['quantity'] ?> &times; <?= format_currency($price) ?></span>
                                    </div>
                                </div>
                                <span class="fw-bold text-accent small"><?= format_currency($price * $item['quantity']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex justify-content-between mb-2 text-muted small">
                        <span>Subtotal</span>
                        <span class="text-light fw-medium"><?= format_currency($totals['subtotal']) ?></span>
                    </div>

                    <?php if ($totals['discount'] > 0): ?>
                        <div class="d-flex justify-content-between mb-2 text-success small">
                            <span>Discount Applied</span>
                            <span class="fw-medium">- <?= format_currency($totals['discount']) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between mb-2 text-muted small">
                        <span>GST (<?= $totals['tax_rate'] ?>%)</span>
                        <span class="text-light fw-medium"><?= format_currency($totals['tax']) ?></span>
                    </div>

                    <div class="d-flex justify-content-between mb-3 text-muted small">
                        <span>Shipping</span>
                        <span class="text-light fw-medium"><?= $totals['shipping'] == 0 ? '<span class="text-success">FREE</span>' : format_currency($totals['shipping']) ?></span>
                    </div>

                    <div class="alert alert-warning py-2 mb-3 small d-flex align-items-center gap-2">
                        <i class="bi bi-gift-fill fs-5 text-warning"></i>
                        <div><strong>Mystery Box Bonus!</strong> Placing this order unlocks an exclusive Mystery Box Code.</div>
                    </div>

                    <div class="border-top border-secondary pt-3 mt-2 d-flex justify-content-between align-items-center mb-4">
                        <span class="fs-5 fw-bold">Total to Pay</span>
                        <span class="fs-3 fw-bold text-accent"><?= format_currency($totals['total']) ?></span>
                    </div>

                    <button type="submit" class="btn btn-accent btn-lg w-100 fw-bold shadow-accent py-3">
                        <i class="bi bi-check-circle-fill me-2"></i> Confirm & Place Order
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.querySelectorAll('input[name="address_id"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const newFields = document.getElementById('newAddressFields');
        if (this.value === '0') {
            newFields.classList.remove('d-none');
        } else {
            newFields.classList.add('d-none');
        }
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
