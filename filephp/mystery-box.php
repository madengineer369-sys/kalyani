<?php
/**
 * Black Stone - Separated Luxury Mystery Box Lounge
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

$nonce = generate_csp_nonce();
send_security_headers($nonce);
session_start_secure();
require_auth('mystery-box.php');

$user    = get_auth_user();
$user_id = (int)$user['id'];
$recently_won = null;

// Handle Open Box POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect();
    $box_id = sanitize_id($_POST['box_id'] ?? 0);

    if ($box_id) {
        $box = db_fetch_one("SELECT * FROM mystery_boxes WHERE id=? AND user_id=? AND is_opened=0 LIMIT 1", 'ii', [$box_id, $user_id]);
        if ($box) {
            db_execute("UPDATE mystery_boxes SET is_opened=1, opened_at=NOW() WHERE id=?", 'i', [$box_id]);
            log_audit('OPEN_MYSTERY_BOX', 'mystery_box', $box_id);
            $_SESSION['recently_unboxed'] = $box;
            flash('success', '🎉 Unboxed! You won: ' . e($box['prize_name']));
        }
    }
    header('Location: mystery-box.php');
    exit;
}

if (!empty($_SESSION['recently_unboxed'])) {
    $recently_won = $_SESSION['recently_unboxed'];
    unset($_SESSION['recently_unboxed']);
}

// User Mystery Boxes & Milestone Tracker
$unopened_boxes = db_fetch_all("SELECT * FROM mystery_boxes WHERE user_id=? AND is_opened=0 ORDER BY created_at DESC", 'i', [$user_id]);
$opened_boxes   = db_fetch_all("SELECT * FROM mystery_boxes WHERE user_id=? AND is_opened=1 ORDER BY opened_at DESC", 'i', [$user_id]);
$redeemed_tag_count = (int)db_fetch_value("SELECT COUNT(*) FROM mystery_codes WHERE user_id=? AND is_redeemed=1", 'i', [$user_id]);
$current_cycle = ($redeemed_tag_count % 5 === 0 && $redeemed_tag_count > 0) ? 5 : ($redeemed_tag_count % 5);

$page_title = 'Mystery Box Lounge';
$page_desc  = 'Unlock luxury prizes, secret discount codes, and streetwear drops.';

log_access();
include __DIR__ . '/includes/header.php';
?>

<div class="container py-4">

    <!-- Mystery Lounge Hero -->
    <div class="mystery-lounge-hero mb-4 shadow-lg">
        <span class="badge bg-warning text-dark px-3 py-2 fw-bold text-uppercase rounded-pill mb-3">
            <i class="bi bi-gift-fill me-1"></i> VIP Gamified Rewards
        </span>
        <h1 class="display-4 fw-bold mb-3 text-light font-monospace">
            The Black Stone <span class="text-warning">Mystery Lounge</span>
        </h1>
        <p class="text-muted fs-5 max-w-700 mx-auto mb-4" style="max-width: 700px;">
            Every apparel purchase contains a unique label code. Redeem your codes in the Code Zone to unlock chests containing vouchers, limited merch, and secret streetwear drops.
        </p>
        <div class="d-flex flex-wrap justify-content-center gap-3">
            <a href="code-zone.php" class="btn btn-accent px-4 py-2 fw-bold shadow-gold">
                <i class="bi bi-ticket-perforated me-1"></i> Redeem Label Code
            </a>
            <a href="t-shirts.php" class="btn btn-outline-warning px-4 py-2 fw-bold">
                <i class="bi bi-bag-plus me-1"></i> Shop Collection for Codes
            </a>
        </div>
    </div>

    <!-- 5-T-Shirt Milestone Progress Widget -->
    <div class="card p-4 border-warning bg-dark-subtle rounded-4 mb-5 shadow-sm" style="border-width: 1px;">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold text-light mb-1"><i class="bi bi-gem text-warning me-2"></i> Grand Vault Milestone Progress</h4>
                <p class="text-muted small mb-0">Collect 5 verified Black Stone T-shirt tags to automatically unlock a Legendary Grand Vault Master Chest.</p>
            </div>
            <span class="badge bg-warning text-dark fs-6 px-3 py-2 fw-bold rounded-pill"><?= $current_cycle ?> / 5 TAGS</span>
        </div>
        <div class="row row-cols-5 g-2 text-center mt-2">
            <?php for ($i = 1; $i <= 5; $i++): 
                $is_unlocked = ($i <= $current_cycle);
            ?>
                <div class="col">
                    <div class="p-3 rounded-3 border <?= $is_unlocked ? 'border-warning bg-warning bg-opacity-10 shadow-sm' : 'border-secondary bg-dark' ?>">
                        <i class="bi <?= $is_unlocked ? 'bi-shield-fill-check text-warning' : 'bi-lock-fill text-muted' ?> fs-3 d-block mb-1"></i>
                        <span class="small fw-bold <?= $is_unlocked ? 'text-warning' : 'text-muted' ?>">Tag #<?= $i ?></span>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>

    <?php render_flash(); ?>

    <!-- Recently Unboxed Winner Modal / Banner -->
    <?php if ($recently_won): ?>
        <div class="card bg-dark border-warning p-4 p-md-5 rounded-4 text-center mb-5 shadow-gold" style="border-width: 2px;">
            <div class="avatar-lg mx-auto mb-3 fs-1 text-warning">
                <i class="bi bi-trophy-fill"></i>
            </div>
            <span class="badge bg-warning text-dark px-3 py-1 fw-bold rounded-pill mx-auto mb-2 text-uppercase">
                <?= e($recently_won['rarity'] ?: 'LEGENDARY') ?> REVEAL
            </span>
            <h2 class="display-6 fw-bold text-light mb-2"><?= e($recently_won['title']) ?></h2>
            <div class="display-5 fw-bold text-warning font-monospace my-3">
                <?= e($recently_won['prize_name']) ?>
            </div>
            <p class="text-muted small mb-4"><?= e($recently_won['prize_desc'] ?: 'Redeemable on all upcoming Black Stone collections.') ?></p>
            <div class="d-flex justify-content-center gap-3">
                <a href="index.php" class="btn btn-accent px-4 fw-bold shadow-gold">Shop with Reward</a>
                <a href="code-zone.php" class="btn btn-outline-warning px-4 fw-bold">Redeem Another Code</a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Unopened Boxes Section -->
    <div class="mb-5">
        <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom border-secondary">
            <div>
                <h3 class="fw-bold mb-1 text-light"><i class="bi bi-box2-heart-fill me-2 text-warning"></i> Ready to Unbox (<?= count($unopened_boxes) ?>)</h3>
                <p class="text-muted small mb-0">Chests available in your inventory waiting to be opened.</p>
            </div>
            <a href="code-zone.php" class="btn btn-sm btn-outline-warning">
                <i class="bi bi-plus-lg me-1"></i> Enter Another Code
            </a>
        </div>

        <?php if (empty($unopened_boxes)): ?>
            <div class="card p-5 text-center bg-dark border-secondary rounded-4 my-3">
                <i class="bi bi-box-seam fs-1 text-muted mb-3"></i>
                <h5 class="text-light">No unopened mystery boxes</h5>
                <p class="text-muted small">You don't have any pending boxes. Redeem a demo code in the Code Zone to unlock one instantly!</p>
                <div class="d-flex justify-content-center gap-2">
                    <a href="code-zone.php?code=BLACKSTONE-DEMO1" class="btn btn-warning px-4 fw-bold text-dark">
                        <i class="bi bi-unlock-fill me-1"></i> Redeem Demo Code
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($unopened_boxes as $box): 
                    $rarity = strtolower($box['rarity'] ?: 'legendary');
                ?>
                    <div class="col">
                        <div class="chest-card <?= e($rarity) ?>">
                            <div class="chest-icon-wrapper">
                                <i class="bi bi-box-seam-fill"></i>
                            </div>
                            <span class="badge bg-warning text-dark fw-bold rounded-pill text-uppercase mb-2">
                                <?= e($rarity) ?> RARITY
                            </span>
                            <h4 class="fw-bold text-light mb-2"><?= e($box['title']) ?></h4>
                            <p class="text-muted small mb-4">
                                <?= e($box['prize_desc'] ?: 'Contains verified reward code & perks') ?>
                            </p>
                            
                            <form method="POST" action="mystery-box.php" id="unboxForm_<?= $box['id'] ?>">
                                <?php render_csrf_field(); ?>
                                <input type="hidden" name="box_id" value="<?= $box['id'] ?>">
                                <button type="button" class="btn btn-accent w-100 py-3 fw-bold shadow-gold" onclick="triggerPhpUnbox(<?= $box['id'] ?>, '<?= e($rarity) ?>')">
                                    <i class="bi bi-unlock-fill me-2"></i> Open Mystery Box
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tier Explanation Cards -->
    <div class="mb-5">
        <h4 class="fw-bold text-light mb-3"><i class="bi bi-stars me-2 text-warning"></i> Mystery Rarity Tiers</h4>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card p-4 bg-dark border-warning rounded-4 h-100 shadow-sm">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-warning text-dark fw-bold">LEGENDARY</span>
                        <span class="text-warning fw-bold">Tier 1</span>
                    </div>
                    <h5 class="fw-bold text-light">Heavyweight Hoodies & ₹1,000 Vouchers</h5>
                    <p class="text-muted small mb-0">Found inside limited drop signature t-shirts and high-value orders.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-4 bg-dark rounded-4 h-100 shadow-sm" style="border: 1px solid #a855f7;">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge text-dark fw-bold" style="background:#a855f7;">EPIC</span>
                        <span class="fw-bold" style="color:#a855f7;">Tier 2</span>
                    </div>
                    <h5 class="fw-bold text-light">Signature Silver Rings & 50% OFF</h5>
                    <p class="text-muted small mb-0">Sterling silver engraved community signet rings and massive site-wide discounts.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-4 bg-dark rounded-4 h-100 shadow-sm" style="border: 1px solid #06b6d4;">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge text-dark fw-bold" style="background:#06b6d4;">RARE</span>
                        <span class="fw-bold" style="color:#06b6d4;">Tier 3</span>
                    </div>
                    <h5 class="fw-bold text-light">25% Coupons & Free Merch Packs</h5>
                    <p class="text-muted small mb-0">Guaranteed rewards included on all verified Black Stone t-shirt labels.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Opened Boxes History -->
    <div>
        <h4 class="fw-bold text-light mb-3"><i class="bi bi-clock-history text-muted me-2"></i> Unboxed Rewards History (<?= count($opened_boxes) ?>)</h4>
        <?php if (empty($opened_boxes)): ?>
            <p class="text-muted small">No unboxed rewards history yet.</p>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
                <?php foreach ($opened_boxes as $ob): ?>
                    <div class="col">
                        <div class="card p-3 bg-dark border-secondary rounded-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Claimed</span>
                                <span class="text-muted small"><?= time_ago($ob['opened_at']) ?></span>
                            </div>
                            <h6 class="fw-bold text-light mb-1"><?= e($ob['title']) ?></h6>
                            <div class="text-warning fw-bold fs-6 mt-1">
                                <i class="bi bi-trophy-fill me-1"></i> <?= e($ob['prize_name']) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
