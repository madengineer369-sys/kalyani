<?php
/**
 * SecureShop - Profile Management & Settings
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
require_auth('profile.php');

$user    = get_auth_user();
$user_id = $user['id'];
$errors  = [];

// Handle Profile Updates & Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect();
    $action = sanitize_enum($_POST['action'] ?? '', ['update_info', 'change_password', 'add_address', 'delete_address']);

    if ($action === 'update_info') {
        $first_name = sanitize_name($_POST['first_name'] ?? '');
        $last_name  = sanitize_name($_POST['last_name'] ?? '');
        $phone      = sanitize_phone($_POST['phone'] ?? '');

        if (!$first_name) $errors[] = 'First name is required.';
        if (!$last_name)  $errors[] = 'Last name is required.';

        if (empty($errors)) {
            db_execute(
                "UPDATE users SET first_name=?, last_name=?, phone=?, updated_at=NOW() WHERE id=?",
                'sssi', [$first_name, $last_name, $phone, $user_id]
            );
            log_audit('UPDATE_PROFILE', 'user', $user_id);
            flash('success', 'Profile information updated successfully.');
            header('Location: profile.php');
            exit;
        }
    } elseif ($action === 'change_password') {
        $current_pwd = $_POST['current_password'] ?? '';
        $new_pwd     = $_POST['new_password'] ?? '';
        $confirm_pwd = $_POST['confirm_password'] ?? '';

        // Verify current password
        $user_pwd_hash = db_fetch_value("SELECT password_hash FROM users WHERE id=?", 'i', [$user_id]);
        if (!password_verify($current_pwd, $user_pwd_hash)) {
            $errors[] = 'Current password is incorrect.';
        } else {
            $pwd_check = validate_password($new_pwd);
            if (!$pwd_check['valid']) {
                foreach ($pwd_check['errors'] as $e_msg) {
                    $errors[] = $e_msg;
                }
            }
            if ($new_pwd !== $confirm_pwd) {
                $errors[] = 'New passwords do not match.';
            }

            if (empty($errors)) {
                $new_hash = password_hash($new_pwd, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
                db_execute(
                    "UPDATE users SET password_hash=?, must_change_password=0, password_changed_at=NOW() WHERE id=?",
                    'si', [$new_hash, $user_id]
                );
                $_SESSION['must_change_pwd'] = false;
                log_security('PASSWORD_CHANGED', ['user_id' => $user_id]);
                flash('success', 'Password updated successfully!');
                header('Location: profile.php?tab=password');
                exit;
            }
        }
    } elseif ($action === 'add_address') {
        $label         = sanitize_string($_POST['label'] ?? 'Home', 50);
        $full_name     = sanitize_name($_POST['full_name'] ?? '');
        $phone         = sanitize_phone($_POST['phone'] ?? '');
        $address_line1 = sanitize_string($_POST['address_line1'] ?? '', 300);
        $address_line2 = sanitize_string($_POST['address_line2'] ?? '', 300);
        $city          = sanitize_string($_POST['city'] ?? '', 100);
        $state         = sanitize_string($_POST['state'] ?? '', 100);
        $postal_code   = sanitize_postal_code($_POST['postal_code'] ?? '');

        if (!$full_name)     $errors[] = 'Full name is required.';
        if (!$address_line1) $errors[] = 'Address line 1 is required.';
        if (!$city)          $errors[] = 'City is required.';
        if (!$state)         $errors[] = 'State is required.';
        if (!$postal_code)   $errors[] = 'Postal code is required.';

        if (empty($errors)) {
            db_insert(
                "INSERT INTO shipping_addresses (user_id, label, full_name, phone, address_line1, address_line2, city, state, postal_code, country, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'India', NOW())",
                'issssssss', [$user_id, $label, $full_name, $phone, $address_line1, $address_line2, $city, $state, $postal_code]
            );
            flash('success', 'New shipping address added.');
            header('Location: profile.php?tab=addresses');
            exit;
        }
    } elseif ($action === 'delete_address') {
        $addr_id = sanitize_id($_POST['address_id'] ?? 0);
        if ($addr_id) {
            db_execute("DELETE FROM shipping_addresses WHERE id=? AND user_id=?", 'ii', [$addr_id, $user_id]);
            flash('info', 'Shipping address deleted.');
            header('Location: profile.php?tab=addresses');
            exit;
        }
    }
}

$addresses = db_fetch_all("SELECT * FROM shipping_addresses WHERE user_id=? ORDER BY id DESC", 'i', [$user_id]);
$active_tab = $_GET['tab'] ?? 'info';

$page_title = 'Account Settings';
$page_desc  = 'Manage your account profile and addresses.';

log_access();
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5 navbar-spacer">
    <h1 class="h3 fw-bold mb-4"><i class="bi bi-person-gear me-2 text-accent"></i> Account Settings</h1>

    <?php render_flash(); ?>

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

    <div class="row g-4">
        <!-- Tabs Menu -->
        <div class="col-lg-3">
            <div class="card border-secondary bg-dark-subtle rounded-4 p-3 shadow-sm">
                <div class="nav flex-column nav-pills" id="profileTabs" role="tablist">
                    <button class="nav-link text-start <?= $active_tab === 'info' ? 'active' : '' ?>" id="info-tab" data-bs-toggle="pill" data-bs-target="#info-pane" type="button" role="tab">
                        <i class="bi bi-person me-2"></i> Personal Info
                    </button>
                    <button class="nav-link text-start <?= $active_tab === 'password' ? 'active' : '' ?>" id="password-tab" data-bs-toggle="pill" data-bs-target="#password-pane" type="button" role="tab">
                        <i class="bi bi-key me-2"></i> Change Password
                    </button>
                    <button class="nav-link text-start <?= $active_tab === 'addresses' ? 'active' : '' ?>" id="addresses-tab" data-bs-toggle="pill" data-bs-target="#addresses-pane" type="button" role="tab">
                        <i class="bi bi-geo-alt me-2"></i> Shipping Addresses
                    </button>
                </div>
            </div>
        </div>

        <!-- Tab Content -->
        <div class="col-lg-9">
            <div class="tab-content" id="profileTabsContent">
                <!-- Info Tab -->
                <div class="tab-pane fade <?= $active_tab === 'info' ? 'show active' : '' ?>" id="info-pane" role="tabpanel">
                    <div class="card p-4 border-secondary bg-dark-subtle rounded-4 shadow-sm">
                        <h5 class="fw-bold mb-4 pb-2 border-bottom border-secondary">Personal Information</h5>
                        <form method="POST" action="profile.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update_info">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">First Name</label>
                                    <input type="text" name="first_name" class="form-control bg-dark text-light border-secondary" value="<?= e($user['first_name']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Last Name</label>
                                    <input type="text" name="last_name" class="form-control bg-dark text-light border-secondary" value="<?= e($user['last_name']) ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Email Address (Read-Only)</label>
                                <input type="email" class="form-control bg-dark text-muted border-secondary" value="<?= e($user['email']) ?>" readonly>
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-semibold">Phone Number</label>
                                <input type="text" name="phone" class="form-control bg-dark text-light border-secondary" value="<?= e($user['phone'] ?? '') ?>" placeholder="+91 9876543210">
                            </div>
                            <button type="submit" class="btn btn-accent px-4">Save Changes</button>
                        </form>
                    </div>
                </div>

                <!-- Password Tab -->
                <div class="tab-pane fade <?= $active_tab === 'password' ? 'show active' : '' ?>" id="password-pane" role="tabpanel">
                    <div class="card p-4 border-secondary bg-dark-subtle rounded-4 shadow-sm">
                        <h5 class="fw-bold mb-4 pb-2 border-bottom border-secondary">Change Password</h5>
                        <form method="POST" action="profile.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="change_password">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Current Password</label>
                                <input type="password" name="current_password" class="form-control bg-dark text-light border-secondary" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">New Password</label>
                                <input type="password" name="new_password" class="form-control bg-dark text-light border-secondary" required>
                                <div class="form-text text-muted small">Must be at least 8 characters with numbers, uppercase, and special characters.</div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-semibold">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control bg-dark text-light border-secondary" required>
                            </div>
                            <button type="submit" class="btn btn-accent px-4">Update Password</button>
                        </form>
                    </div>
                </div>

                <!-- Addresses Tab -->
                <div class="tab-pane fade <?= $active_tab === 'addresses' ? 'show active' : '' ?>" id="addresses-pane" role="tabpanel">
                    <div class="card p-4 border-secondary bg-dark-subtle rounded-4 mb-4 shadow-sm">
                        <h5 class="fw-bold mb-4 pb-2 border-bottom border-secondary">Add New Address</h5>
                        <form method="POST" action="profile.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add_address">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small">Label (e.g. Home, Work)</label>
                                    <input type="text" name="label" class="form-control bg-dark text-light border-secondary" value="Home" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">Full Name</label>
                                    <input type="text" name="full_name" class="form-control bg-dark text-light border-secondary" value="<?= e($user['first_name'] . ' ' . $user['last_name']) ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">Phone</label>
                                    <input type="text" name="phone" class="form-control bg-dark text-light border-secondary" value="<?= e($user['phone'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small">Address Line 1</label>
                                    <input type="text" name="address_line1" class="form-control bg-dark text-light border-secondary" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small">Address Line 2 (Optional)</label>
                                    <input type="text" name="address_line2" class="form-control bg-dark text-light border-secondary">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">City</label>
                                    <input type="text" name="city" class="form-control bg-dark text-light border-secondary" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">State</label>
                                    <input type="text" name="state" class="form-control bg-dark text-light border-secondary" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">PIN / Postal Code</label>
                                    <input type="text" name="postal_code" class="form-control bg-dark text-light border-secondary" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-accent mt-3 px-4">Add Address</button>
                        </form>
                    </div>

                    <!-- Saved Addresses -->
                    <h6 class="fw-bold mb-3">Saved Addresses (<?= count($addresses) ?>)</h6>
                    <div class="row g-3">
                        <?php foreach ($addresses as $a): ?>
                            <div class="col-md-6">
                                <div class="card p-3 border-secondary bg-dark rounded-3 h-100 position-relative">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <span class="badge bg-secondary"><?= e($a['label']) ?></span>
                                        <form method="POST" action="profile.php">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_address">
                                            <input type="hidden" name="address_id" value="<?= $a['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger border-0 p-0" title="Delete Address"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                    <strong class="text-light"><?= e($a['full_name']) ?></strong>
                                    <span class="text-muted small"><?= e($a['phone']) ?></span>
                                    <p class="text-muted small mb-0 mt-2">
                                        <?= e($a['address_line1']) ?>, <?= e($a['address_line2']) ?><br>
                                        <?= e($a['city']) ?>, <?= e($a['state']) ?> - <?= e($a['postal_code']) ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
