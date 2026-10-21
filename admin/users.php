<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Services\AuthService;
use App\Services\SettingsService;
use App\Services\EpinService;
use App\Services\PackageService;
use App\Services\CompensationEngine;
use App\Core\Database;

$currentUser = AuthService::requireAdmin();
$settingsService = new SettingsService();
$epinService = new EpinService();
$pkgService = new PackageService();
$engine = new CompensationEngine();
$db = Database::getConnection();

$message = '';
$error = '';

// Fetch all possible sponsors for dropdown selection
$allSponsors = $db->query("SELECT id, username, full_name FROM users ORDER BY username ASC")->fetchAll();

// Fetch all unused ePINs for admin activation selection
$unusedEpins = $db->query("
    SELECT e.id, e.code, p.name as package_name, p.price as package_price
    FROM epins e
    JOIN packages p ON e.package_id = p.id
    WHERE e.status = 'unused'
    ORDER BY e.id DESC
")->fetchAll();

// Fetch active packages for administrative auto-ePIN generation
$activePackages = $pkgService->getAllPackages(true);

// Handle Admin Direct Member Activation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_activate_user'])) {
    $targetUserId = (int)$_POST['target_user_id'];
    $epinCode = trim($_POST['epin_code'] ?? '');
    $selectedPkgId = !empty($_POST['package_id']) ? (int)$_POST['package_id'] : null;

    if (empty($epinCode) && $selectedPkgId) {
        // Auto-generate ePIN for the selected package
        $generated = $epinService->generateEpins($selectedPkgId, 1, $currentUser['id'], $targetUserId);
        if (!empty($generated)) {
            $epinCode = $generated[0]['code'];
        }
    }

    if (empty($epinCode)) {
        $error = "Please select an existing unused ePIN or a package to auto-generate an ePIN.";
    } else {
        $actRes = $engine->activateUserWithEpin($targetUserId, $epinCode);
        if ($actRes['success']) {
            $message = "Member successfully activated in matrix with ePIN '{$epinCode}'!";
        } else {
            $error = "Activation failed: " . $actRes['message'];
        }
    }
}

// Handle User Details Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $userId = (int)$_POST['user_id'];
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $sponsorId = !empty($_POST['sponsor_id']) ? (int)$_POST['sponsor_id'] : null;
    $role = $_POST['role'];
    $status = $_POST['status'];

    // Check username/email duplicate for other users
    $stmtCheck = $db->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
    $stmtCheck->execute([$username, $email, $userId]);
    if ($stmtCheck->fetch()) {
        $error = "Username or Email address is already in use by another account.";
    } elseif ($role === 'super_admin' && $currentUser['role'] !== 'super_admin') {
        $error = "Only Super Admins can assign the Super Admin role.";
    } else {
        // Prevent setting sponsor_id to self
        if ($sponsorId === $userId) {
            $sponsorId = null;
        }

        $encryptSetting = $settingsService->get('encrypt_passwords', '0');

        if (!empty($newPassword)) {
            $storedPassword = ($encryptSetting === '1') ? password_hash($newPassword, PASSWORD_BCRYPT) : $newPassword;
            $stmtUp = $db->prepare("
                UPDATE users
                SET full_name = ?, username = ?, email = ?, password_hash = ?, sponsor_id = ?, role = ?, status = ?
                WHERE id = ?
            ");
            $stmtUp->execute([$fullName, $username, $email, $storedPassword, $sponsorId, $role, $status, $userId]);
        } else {
            $stmtUp = $db->prepare("
                UPDATE users
                SET full_name = ?, username = ?, email = ?, sponsor_id = ?, role = ?, status = ?
                WHERE id = ?
            ");
            $stmtUp->execute([$fullName, $username, $email, $sponsorId, $role, $status, $userId]);
        }

        $message = "User details updated successfully!";
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
        <p class="text-muted">Manage registered users, edit personal profiles, set passwords, sponsor tree placement, and activate pending members in matrix.</p>
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
                                    <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Pending Activation</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= $u['status'] === 'active' ? 'success' : 'danger' ?>">
                                    <?= strtoupper($u['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $u['id'] ?>">
                                        <i class="bi bi-pencil me-1"></i>Edit
                                    </button>

                                    <?php if ($u['depth'] === null): ?>
                                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#activateUserModal<?= $u['id'] ?>">
                                            <i class="bi bi-check-circle me-1"></i>Activate
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <?php if ($u['depth'] === null): ?>
                                    <!-- Admin Activate Member Modal -->
                                    <div class="modal fade" id="activateUserModal<?= $u['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="users.php">
                                                    <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                                    <div class="modal-header bg-success text-white">
                                                        <h5 class="modal-title"><i class="bi bi-person-check-fill me-2"></i>Activate Pending Member: <?= htmlspecialchars($u['username']) ?></h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Activate <strong><?= htmlspecialchars($u['full_name']) ?> (@<?= htmlspecialchars($u['username']) ?>)</strong> into the forced matrix.</p>

                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Select Existing Unused ePIN</label>
                                                            <select name="epin_code" class="form-select">
                                                                <option value="">-- Choose Unused ePIN Code --</option>
                                                                <?php foreach ($unusedEpins as $ep): ?>
                                                                    <option value="<?= htmlspecialchars($ep['code']) ?>">
                                                                        <?= htmlspecialchars($ep['code']) ?> (<?= htmlspecialchars($ep['package_name']) ?> - ₹<?= number_format($ep['package_price'], 2) ?>)
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>

                                                        <div class="text-center text-muted fw-bold my-2">- OR AUTO-GENERATE ePIN -</div>

                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Select Joining Package</label>
                                                            <select name="package_id" class="form-select">
                                                                <option value="">-- Select Package to Auto-Generate ePIN --</option>
                                                                <?php foreach ($activePackages as $ap): ?>
                                                                    <option value="<?= $ap['id'] ?>">
                                                                        <?= htmlspecialchars($ap['name']) ?> (₹<?= number_format($ap['price'], 2) ?>)
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <small class="text-muted">If no existing ePIN is selected, an ePIN for this package will be automatically generated and applied.</small>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="admin_activate_user" value="1" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Activate Member Now</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editUserModal<?= $u['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <form method="POST" action="users.php">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit User Account: <?= htmlspecialchars($u['username']) ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label fw-bold">Full Name</label>
                                                            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($u['full_name']) ?>" required>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label fw-bold">Username</label>
                                                            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($u['username']) ?>" required>
                                                        </div>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label fw-bold">Email Address</label>
                                                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($u['email']) ?>" required>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label fw-bold">New Password (Optional)</label>
                                                            <input type="text" name="new_password" class="form-control" placeholder="Leave blank to keep unchanged">
                                                        </div>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label fw-bold">Sponsor / Referrer</label>
                                                            <select name="sponsor_id" class="form-select">
                                                                <option value="">-- Direct System Sponsor --</option>
                                                                <?php foreach ($allSponsors as $sp): ?>
                                                                    <?php if ($sp['id'] !== $u['id']): ?>
                                                                        <option value="<?= $sp['id'] ?>" <?= $u['sponsor_id'] == $sp['id'] ? 'selected' : '' ?>>
                                                                            <?= htmlspecialchars($sp['username']) ?> (<?= htmlspecialchars($sp['full_name']) ?>)
                                                                        </option>
                                                                    <?php endif; ?>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label fw-bold">Role</label>
                                                            <select name="role" class="form-select">
                                                                <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                                                <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                                <?php if ($currentUser['role'] === 'super_admin'): ?>
                                                                    <option value="super_admin" <?= $u['role'] === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                                                                <?php endif; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label fw-bold">Account Status</label>
                                                            <select name="status" class="form-select">
                                                                <option value="active" <?= $u['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                                <option value="inactive" <?= $u['status'] === 'inactive' ? 'selected' : '' ?>>Inactive / Suspended</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="update_user" value="1" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save User Changes</button>
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
