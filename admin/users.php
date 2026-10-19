<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Services\AuthService;
use App\Core\Database;

$currentUser = AuthService::requireAdmin();
$db = Database::getConnection();

$message = '';
$error = '';

// Handle Role/Status Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $userId = (int)$_POST['user_id'];
    $role = $_POST['role'];
    $status = $_POST['status'];

    // Super Admin check for changing to super_admin or editing super_admin
    if ($role === 'super_admin' && $currentUser['role'] !== 'super_admin') {
        $error = "Only Super Admins can assign the Super Admin role.";
    } else {
        $stmt = $db->prepare("UPDATE users SET role = ?, status = ? WHERE id = ?");
        $stmt->execute([$role, $status, $userId]);
        $message = "User status and role updated successfully.";
    }
}

$stmtUsers = $db->query("
    SELECT u.*, s.username as sponsor_name, w.user_wallet_balance, m.depth
    FROM users u
    LEFT JOIN users s ON u.sponsor_id = s.id
    LEFT JOIN wallets w ON u.id = w.user_id
    LEFT JOIN matrix_nodes m ON u.id = m.user_id
    ORDER BY u.id ASC
");
$users = $stmtUsers->fetchAll();

$pageTitle = "User Management";
require_once __DIR__ . '/../templates/layouts/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2 class="fw-bold"><i class="bi bi-people-fill text-primary me-2"></i>User Management</h2>
        <p class="text-muted">Manage registered users, view wallet balances, sponsor tree placement, and manage roles/permissions.</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#ID</th>
                        <th>User</th>
                        <th>Email</th>
                        <th>Sponsor</th>
                        <th>Role</th>
                        <th>Wallet Balance</th>
                        <th>Matrix Status</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>#<?= $u['id'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($u['full_name']) ?></strong><br>
                                <small class="text-primary">@<?= htmlspecialchars($u['username']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= htmlspecialchars($u['sponsor_name'] ?? 'System Direct') ?></td>
                            <td>
                                <span class="badge bg-<?= $u['role'] === 'super_admin' ? 'danger' : ($u['role'] === 'admin' ? 'warning' : 'info') ?>">
                                    <?= strtoupper(str_replace('_', ' ', $u['role'])) ?>
                                </span>
                            </td>
                            <td class="fw-bold text-success">₹<?= number_format($u['user_wallet_balance'] ?? 0, 2) ?></td>
                            <td>
                                <?php if ($u['depth'] !== null): ?>
                                    <span class="badge bg-success">Active (Depth <?= $u['depth'] ?>)</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= $u['status'] === 'active' ? 'success' : 'danger' ?>">
                                    <?= strtoupper($u['status']) ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $u['id'] ?>">
                                    <i class="bi bi-pencil me-1"></i>Manage
                                </button>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editUserModal<?= $u['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST" action="users.php">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Manage User: <?= htmlspecialchars($u['username']) ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Role</label>
                                                        <select name="role" class="form-select">
                                                            <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                                            <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                            <?php if ($currentUser['role'] === 'super_admin'): ?>
                                                                <option value="super_admin" <?= $u['role'] === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                                                            <?php endif; ?>
                                                        </select>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Account Status</label>
                                                        <select name="status" class="form-select">
                                                            <option value="active" <?= $u['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                            <option value="inactive" <?= $u['status'] === 'inactive' ? 'selected' : '' ?>>Inactive / Suspended</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="update_user" value="1" class="btn btn-primary">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/layouts/footer.php'; ?>
