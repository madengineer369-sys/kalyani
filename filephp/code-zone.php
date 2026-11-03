<?php
/**
 * SecureShop - Code Zone (Mystery Box Code Redemption)
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
require_auth('code-zone.php');

$user = get_auth_user();
$prefilled_code = sanitize_string($_GET['code'] ?? '', 50);
$result_box = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect();
    $code_input = strtoupper(sanitize_string($_POST['code_input'] ?? '', 50));

    if (!$code_input) {
        flash('danger', 'Please enter a valid Mystery Box Code.');
    } else {
        // Rate limiting check
        $ip = get_client_ip();
        if (rate_limit_check('code_redeem', $ip, 5, 300)) {
            flash('danger', 'Too many attempts. Please wait 5 minutes before trying another code.');
        } else {
            // Check code in mystery_codes database table
            $code_row = db_fetch_one("SELECT * FROM mystery_codes WHERE code=? LIMIT 1", 's', [$code_input]);

            if (!$code_row) {
                flash('danger', 'Invalid Mystery Code. Check your t-shirt label or order details.');
                log_security('MYSTERY_CODE_INVALID', ['code' => $code_input, 'user_id' => $user['id']]);
            } elseif ($code_row['is_redeemed']) {
                flash('warning', 'This Mystery Code has already been redeemed.');
            } else {
                // Determine proper rarity from code box_type
                $box_type = strtolower($code_row['box_type'] ?? 'rare');
                $rarity_map = [
                    'gold'      => 'legendary',
                    'legendary' => 'legendary',
                    'epic'      => 'epic',
                    'silver'    => 'epic',
                    'rare'      => 'rare',
                    'common'    => 'rare'
                ];
                $box_rarity = $rarity_map[$box_type] ?? 'rare';

                // Redeem Code & Issue Mystery Box
                db_execute("UPDATE mystery_codes SET is_redeemed=1, user_id=?, redeemed_at=NOW() WHERE id=?", 'ii', [$user['id'], $code_row['id']]);

                $box_id = db_insert(
                    "INSERT INTO mystery_boxes (user_id, title, rarity, prize_name, prize_desc, is_opened, created_at)
                     VALUES (?, ?, ?, ?, ?, 0, NOW())",
                    'issss',
                    [
                        $user['id'],
                        $code_row['prize_title'],
                        $box_rarity,
                        $code_row['prize_value'],
                        'Unlocked from order code ' . $code_row['code']
                    ]
                );

                log_audit('REDEEM_CODE', 'mystery_code', $code_row['id']);

                // Check 5-T-Shirt Milestone
                $redeemed_count = (int)db_fetch_value("SELECT COUNT(*) FROM mystery_codes WHERE user_id=? AND is_redeemed=1", 'i', [$user['id']]);
                if ($redeemed_count > 0 && $redeemed_count % 5 === 0) {
                    db_insert(
                        "INSERT INTO mystery_boxes (user_id, title, rarity, prize_name, prize_desc, is_opened, created_at)
                         VALUES (?, 'Grand Vault Master Chest', 'legendary', 'Signature Heavyweight Hoodie + ₹2,000 Voucher', 'Milestone Award for collecting 5 verified Black Stone T-Shirts', 0, NOW())",
                        'i', [$user['id']]
                    );
                    flash('success', '🎉 Code verified! PLUS you completed your 5 T-Shirt Milestone and unlocked a bonus GRAND VAULT MASTER CHEST!');
                } else {
                    $remaining = 5 - ($redeemed_count % 5);
                    flash('success', "Code redeemed successfully! Your {$box_rarity} Mystery Box is ready. Only {$remaining} more T-shirt(s) to unlock the Grand Vault!");
                }

                header('Location: mystery-box.php?opened=' . $box_id);
                exit;
            }
        }
    }
}

// User's past redeemed codes & milestone progress
$redeemed_history = db_fetch_all("SELECT * FROM mystery_codes WHERE user_id=? AND is_redeemed=1 ORDER BY redeemed_at DESC", 'i', [$user['id']]);
$total_redeemed   = count($redeemed_history);
$current_cycle    = ($total_redeemed % 5 === 0 && $total_redeemed > 0) ? 5 : ($total_redeemed % 5);

$page_title = 'Code Zone';
$page_desc  = 'Redeem Black Stone t-shirt label codes to unlock mystery boxes.';

log_access();
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5 navbar-spacer">
    <div class="text-center max-w-700 mx-auto mb-5" style="max-width: 700px;">
        <span class="badge bg-warning text-dark fs-6 px-3 py-1 mb-2"><i class="bi bi-ticket-detailed me-1"></i> CODE REDEMPTION ZONE</span>
        <h1 class="display-5 fw-bold mb-3">Unlock Your <span class="text-warning">Mystery Box</span></h1>
        <p class="text-muted">Enter the unique code printed on your Black Stone t-shirt tag or provided in your order confirmation to reveal exclusive rewards, discounts, and secret items.</p>
    </div>

    <?php render_flash(); ?>

    <!-- 5-T-Shirt Milestone Tracker -->
    <div class="card p-4 border-warning bg-dark-subtle rounded-4 mb-5 shadow-sm" style="border-width: 1px;">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold text-light mb-1"><i class="bi bi-gem text-warning me-2"></i> Grand Vault Milestone Progress</h4>
                <p class="text-muted small mb-0">Collect 5 verified Black Stone T-shirt tags to trigger a Legendary Grand Vault Master Chest.</p>
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

    <div class="row justify-content-center g-4 mb-5">
        <div class="col-lg-6">
            <div class="card p-5 border-warning bg-dark-subtle rounded-4 shadow-accent text-center">
                <i class="bi bi-shield-lock-fill fs-1 text-warning mb-3"></i>
                <h4 class="fw-bold mb-3">Enter Mystery Code</h4>

                <form method="POST" action="code-zone.php">
                    <?php render_csrf_field(); ?>
                    <div class="mb-4">
                        <input type="text" name="code_input" class="form-control form-control-lg bg-dark text-warning border-warning text-center fw-bold fs-4 text-uppercase" 
                               placeholder="BLACKSTONE-XXXX" value="<?= e($prefilled_code) ?>" autocomplete="off" required>
                        <div class="form-text text-muted small mt-2">Example format: <code>BLACKSTONE-DEMO1</code></div>
                    </div>

                    <button type="submit" class="btn btn-warning btn-lg w-100 fw-bold py-3 shadow-sm">
                        <i class="bi bi-unlock-fill me-2"></i> Redeem Code & Claim Box
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Instructions & Demo Codes -->
    <div class="row g-4 mb-5">
        <div class="col-md-6">
            <div class="card p-4 border-secondary bg-dark-subtle rounded-4 h-100">
                <h5 class="fw-bold mb-3 text-light"><i class="bi bi-info-circle me-2 text-accent"></i> Where to find your code?</h5>
                <ul class="text-muted small ps-3 mb-0">
                    <li class="mb-2">On the physical label attached inside your Black Stone T-Shirt.</li>
                    <li class="mb-2">In your digital invoice / order details page under "Mystery Box Codes Unlocked".</li>
                    <li>During promotional events sent to your registered email.</li>
                </ul>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card p-4 border-secondary bg-dark-subtle rounded-4 h-100">
                <h5 class="fw-bold mb-3 text-warning"><i class="bi bi-lightbulb me-2"></i> Try Demo Codes</h5>
                <p class="text-muted small mb-2">Test the Mystery Box experience right now using these sample codes:</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="code-zone.php?code=BLACKSTONE-DEMO1" class="btn btn-sm btn-outline-warning">BLACKSTONE-DEMO1</a>
                    <a href="code-zone.php?code=BLACKSTONE-DEMO2" class="btn btn-sm btn-outline-warning">BLACKSTONE-DEMO2</a>
                    <a href="code-zone.php?code=BLACKSTONE-CODE99" class="btn btn-sm btn-outline-warning">BLACKSTONE-CODE99</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
