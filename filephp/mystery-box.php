<?php
/**
 * SecureShop - Mystery Box Unboxing Experience
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
require_auth('mystery-box.php');

$user    = get_auth_user();
$user_id = $user['id'];

// Handle Open Box POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect();
    $box_id = sanitize_id($_POST['box_id'] ?? 0);

    if ($box_id) {
        $box = db_fetch_one("SELECT * FROM mystery_boxes WHERE id=? AND user_id=? AND is_opened=0 LIMIT 1", 'ii', [$box_id, $user_id]);
        if ($box) {
            db_execute("UPDATE mystery_boxes SET is_opened=1, opened_at=NOW() WHERE id=?", 'i', [$box_id]);
            log_audit('OPEN_MYSTERY_BOX', 'mystery_box', $box_id);
            flash('success', 'Box Unboxed! You won: ' . $box['prize_name']);
        }
    }
    header('Location: mystery-box.php');
    exit;
}

// User Mystery Boxes
$unopened_boxes = db_fetch_all("SELECT * FROM mystery_boxes WHERE user_id=? AND is_opened=0 ORDER BY created_at DESC", 'i', [$user_id]);
$opened_boxes   = db_fetch_all("SELECT * FROM mystery_boxes WHERE user_id=? AND is_opened=1 ORDER BY opened_at DESC", 'i', [$user_id]);

$page_title = 'Mystery Boxes';
$page_desc  = 'Open your unlocked Black Stone mystery boxes.';

log_access();
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5 navbar-spacer">
    <div class="text-center mb-5">
        <span class="badge bg-warning text-dark fs-6 px-3 py-1 mb-2"><i class="bi bi-box-seam-fill me-1"></i> UNBOXING LOUNGE</span>
        <h1 class="display-5 fw-bold">My <span class="text-warning">Mystery Boxes</span></h1>
        <p class="text-muted">Unbox your rewards and collect exclusive coupons, vouchers, and gifts.</p>
    </div>

    <?php render_flash(); ?>

    <!-- Unopened Boxes -->
    <div class="mb-5">
        <h4 class="fw-bold mb-3"><i class="bi bi-gift text-warning me-2"></i> Ready to Open (<?= count($unopened_boxes) ?>)</h4>

        <?php if (empty($unopened_boxes)): ?>
            <div class="card p-5 text-center border-secondary bg-dark-subtle rounded-4">
                <i class="bi bi-box-seam fs-1 text-muted mb-3"></i>
                <h5>No unopened mystery boxes</h5>
                <p class="text-muted">Redeem a code in the Code Zone to unlock your next mystery box!</p>
                <a href="code-zone.php" class="btn btn-warning mx-auto px-4 fw-bold">Redeem Code</a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($unopened_boxes as $box): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card border-warning bg-dark p-4 rounded-4 text-center shadow-accent position-relative">
                            <div class="avatar-lg bg-warning text-dark mx-auto mb-3 fs-1 fw-bold">
                                <i class="bi bi-box-seam-fill"></i>
                            </div>
                            <h5 class="fw-bold text-light mb-1"><?= e($box['title']) ?></h5>
                            <span class="badge bg-warning text-dark align-self-center mb-3 text-uppercase"><?= e($box['rarity']) ?> RARITY</span>
                            <p class="text-muted small mb-4"><?= e($box['prize_desc'] ?: 'Contains mystery rewards') ?></p>
                            
                            <form method="POST" action="mystery-box.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="box_id" value="<?= $box['id'] ?>">
                                <button type="submit" class="btn btn-warning w-100 fw-bold py-2">
                                    <i class="bi bi-unlock me-1"></i> Open Box Now
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Opened Boxes History -->
    <div>
        <h4 class="fw-bold mb-3"><i class="bi bi-clock-history text-muted me-2"></i> Opened Boxes History (<?= count($opened_boxes) ?>)</h4>
        <?php if (empty($opened_boxes)): ?>
            <p class="text-muted small">No opened box history yet.</p>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($opened_boxes as $ob): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card p-3 border-secondary bg-dark-subtle rounded-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Opened</span>
                                <span class="text-muted small"><?= time_ago($ob['opened_at']) ?></span>
                            </div>
                            <h6 class="fw-bold text-light mb-1"><?= e($ob['title']) ?></h6>
                            <div class="text-warning fw-bold fs-6 mt-1"><i class="bi bi-trophy-fill me-1"></i> <?= e($ob['prize_name']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
