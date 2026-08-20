<?php
/**
 * SecureShop - Admin Users Management
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
require_once __DIR__ . '/../includes/pagination.php';

send_security_headers();
session_start_secure();
require_admin();

// Handle User Actions (Role change, Toggle active)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect();
    $action = sanitize_enum($_POST['action'] ?? '', ['change_role', 'toggle_active']);

    if ($action === 'change_role') {
        $uid  = sanitize_id($_POST['user_id'] ?? 0);
        $role = sanitize_enum($_POST['role'] ?? '', ['admin', 'shopkeeper', 'user']);
        if ($uid && $role && $uid !== current_user_id()) {
            db_execute("UPDATE users SET role=? WHERE id=?", 'si', [$role, $uid]);
            log_security('USER_ROLE_CHANGED', ['target_id' => $uid, 'new_role' => $role]);
            flash('success', 'User role updated.');
        }
    } elseif ($action === 'toggle_active') {
        $uid = sanitize_id($_POST['user_id'] ?? 0);
        if ($uid && $uid !== current_user_id()) {
            db_execute("UPDATE users SET is_active = NOT is_active WHERE id=?", 'i', [$uid]);
            flash('info', 'User active status toggled.');
        }
    }
    header('Location: users.php');
    exit;
}

$page_num = current_page_number();
$total    = (int)db_fetch_value("SELECT COUNT(*) FROM users");
$pg       = paginate($total, ADMIN_ITEMS_PER_PAGE, $page_num);

$users = db_fetch_all("SELECT * FROM users ORDER BY id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}");

$page_title = 'Manage Users';
$page_desc  = 'Admin user role and account management.';

log_access();
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-4 py-4 navbar-spacer">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1"><i class="bi bi-people me-2 text-warning"></i> User Accounts</h1>
            <p class="text-muted mb-0">Total <?= pluralise($total, 'registered user') ?></p>
        </div>
    </div>

    <?php render_flash(); ?>

    <div class="card border-secondary bg-dark-subtle rounded-4 overflow-hidden shadow-sm">
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead class="text-muted small border-secondary">
                    <tr>
                        <th scope="col" class="ps-4">User</th>
                        <th scope="col">Username</th>
                        <th scope="col">Registered</th>
                        <th scope="col">Current Role</th>
                        <th scope="col">Change Role</th>
                        <th scope="col" class="pe-4 text-end">Account Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="ps-4 py-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="avatar-sm"><?= strtoupper(substr($u['first_name'], 0, 1)) ?></div>
                                    <div>
                                        <strong class="text-light d-block"><?= e($u['first_name'] . ' ' . $u['last_name']) ?></strong>
                                        <span class="text-muted small"><?= e($u['email']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td><code><?= e($u['username']) ?></code></td>
                            <td class="small text-muted"><?= format_date($u['created_at']) ?></td>
                            <td>
                                <span class="badge bg-<?= role_color($u['role']) ?>"><?= ucfirst($u['role']) ?></span>
                            </td>
                            <td>
                                <?php if ($u['id'] != current_user_id()): ?>
                                    <form method="POST" action="users.php" class="d-flex gap-1">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="change_role">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <select name="role" class="form-select form-select-sm bg-dark text-light border-secondary" onchange="this.form.submit()">
                                            <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                            <option value="shopkeeper" <?= $u['role'] === 'shopkeeper' ? 'selected' : '' ?>>Shopkeeper</option>
                                            <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                        </select>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted small">Current Admin</span>
                                <?php endif; ?>
                            </td>
                            <td class="pe-4 text-end">
                                <?php if ($u['id'] != current_user_id()): ?>
                                    <form method="POST" action="users.php" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-sm <?= $u['is_active'] ? 'btn-success' : 'btn-outline-danger' ?>">
                                            <?= $u['is_active'] ? 'Active' : 'Disabled' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        <?php render_pagination($pg, 'users.php'); ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
