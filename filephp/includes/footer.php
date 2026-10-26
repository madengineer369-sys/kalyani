<?php
/**
 * Black Stone - Shared HTML Footer & Cart Drawer Partial
 */
if (!defined('ECOMMERCE_APP')) { http_response_code(403); exit('Forbidden'); }

$drawer_items = function_exists('cart_get_items') ? cart_get_items() : [];
$drawer_total = function_exists('cart_subtotal') ? cart_subtotal($drawer_items) : 0.00;
?>
</main><!-- /main-content -->

<!-- ===== SLIDE-OUT CART DRAWER ===== -->
<div class="cart-drawer" id="cartDrawer">
    <div class="cart-drawer-overlay" onclick="toggleCart(false)"></div>
    <div class="cart-drawer-content">
        <div class="cart-drawer-header">
            <h5 class="fw-bold mb-0 text-light"><i class="bi bi-bag-check-fill me-2 text-warning"></i>Shopping Bag (<?= count($drawer_items) ?>)</h5>
            <button type="button" class="btn-close btn-close-white" onclick="toggleCart(false)" aria-label="Close"></button>
        </div>
        
        <div class="cart-drawer-items">
            <?php if (empty($drawer_items)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-bag-x fs-1 text-secondary mb-3 d-block"></i>
                    <p class="mb-2">Your shopping bag is currently empty.</p>
                    <button class="btn btn-outline-warning btn-sm" onclick="toggleCart(false)">Continue Shopping</button>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($drawer_items as $item): ?>
                        <div class="d-flex align-items-center gap-3 p-2 bg-dark rounded-3 border border-secondary">
                            <img src="<?= e(product_image_url($item['image_path'])) ?>" alt="<?= e($item['name']) ?>" class="rounded-2" style="width: 56px; height: 56px; object-fit: cover;">
                            <div class="flex-grow-1">
                                <h6 class="mb-0 text-light small fw-bold text-truncate" style="max-width: 180px;"><?= e($item['name']) ?></h6>
                                <span class="text-warning small fw-bold"><?= format_currency($item['sale_price'] ?: $item['price']) ?></span>
                                <span class="text-muted small ms-1">&times; <?= $item['quantity'] ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($drawer_items)): ?>
        <div class="cart-drawer-footer">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-muted">Subtotal</span>
                <span class="fs-5 fw-bold text-warning"><?= format_currency($drawer_total) ?></span>
            </div>
            <p class="text-muted small mb-3"><i class="bi bi-box-seam me-1 text-warning"></i>Every order unlocks a Mystery Box code.</p>
            <div class="d-grid gap-2">
                <a href="<?= e(url('cart.php')) ?>" class="btn btn-outline-warning fw-semibold">View Full Bag</a>
                <a href="<?= e(url('checkout.php')) ?>" class="btn btn-accent py-2 fw-bold shadow-gold">Proceed to Checkout</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== SITE FOOTER ===== -->
<footer class="site-footer mt-5 pt-5 pb-4 border-top border-secondary bg-black">
    <div class="container">
        <div class="row g-4 mb-4">
            <div class="col-lg-4">
                <a href="<?= e(url('index.php')) ?>" class="d-inline-flex align-items-center mb-3 text-light text-decoration-none">
                    <i class="bi bi-gem fs-4 text-warning me-2"></i>
                    <span class="fs-4 fw-bold font-monospace">BLACK <span class="text-warning">STONE</span></span>
                </a>
                <p class="text-muted small">Heavyweight streetwear apparel crafted from premium cottons, hidden reward codes, and a gamified Mystery Box unboxing lounge.</p>
                <div class="d-flex gap-2">
                    <span class="badge bg-dark border border-secondary text-light py-2 px-3"><i class="bi bi-shield-check text-warning me-1"></i> 260+ GSM Cotton</span>
                    <span class="badge bg-dark border border-secondary text-light py-2 px-3"><i class="bi bi-gift text-warning me-1"></i> Reward Mystery Codes</span>
                </div>
            </div>

            <div class="col-lg-2 col-6">
                <h6 class="fw-bold text-light mb-3 text-uppercase letter-spacing-1">Collections</h6>
                <ul class="list-unstyled d-flex flex-column gap-2 small">
                    <li><a href="<?= e(url('t-shirts.php')) ?>" class="text-muted hover-gold">T-Shirts</a></li>
                    <li><a href="<?= e(url('hoodies.php')) ?>" class="text-muted hover-gold">Hoodies</a></li>
                    <li><a href="<?= e(url('trousers.php')) ?>" class="text-muted hover-gold">Trousers</a></li>
                    <li><a href="<?= e(url('shorts.php')) ?>" class="text-muted hover-gold">Shorts</a></li>
                </ul>
            </div>

            <div class="col-lg-2 col-6">
                <h6 class="fw-bold text-light mb-3 text-uppercase letter-spacing-1">Rewards</h6>
                <ul class="list-unstyled d-flex flex-column gap-2 small">
                    <li><a href="<?= e(url('mystery-box.php')) ?>" class="text-muted hover-gold"><i class="bi bi-box-seam me-1 text-warning"></i> Mystery Box</a></li>
                    <li><a href="<?= e(url('code-zone.php')) ?>" class="text-muted hover-gold"><i class="bi bi-ticket-detailed me-1 text-info"></i> Code Zone</a></li>
                    <li><a href="<?= e(url('order-history.php')) ?>" class="text-muted hover-gold">Track Order</a></li>
                </ul>
            </div>

            <div class="col-lg-4">
                <h6 class="fw-bold text-light mb-3 text-uppercase letter-spacing-1">Customer Care</h6>
                <p class="text-muted small mb-2">Have questions regarding fit, fabric weight, or mystery codes? Our team is active 24/7.</p>
                <a href="mailto:support@blackstone.local" class="text-warning small text-decoration-none"><i class="bi bi-envelope-at me-1"></i> care@blackstone.local</a>
            </div>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center pt-4 border-top border-secondary text-muted small">
            <span>&copy; <?= date('Y') ?> Black Stone Streetwear Co. All rights reserved.</span>
            <span>Made for streetwear collectors &bull; Secure Two-Factor OTP Platform</span>
        </div>
    </div>
</footer>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc4s9bIOgUxi8T/jzmKfPMNTHhKFm5wT8BFMlNEomEEE" crossorigin="anonymous"></script>
<!-- App JS -->
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>
