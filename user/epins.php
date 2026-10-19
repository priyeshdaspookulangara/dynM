<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Services\AuthService;
use App\Services\EpinService;
use App\Core\Database;

$currentUser = AuthService::requireLogin();
$epinService = new EpinService();
$db = Database::getConnection();

$message = '';
$error = '';

// Transfer ePIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transfer_epin'])) {
    $epinId = (int)$_POST['epin_id'];
    $targetUsername = trim($_POST['target_username'] ?? '');

    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$targetUsername]);
    $targetUser = $stmt->fetch();

    if (!$targetUser) {
        $error = "Target user '{$targetUsername}' not found.";
    } else {
        if ($epinService->assignEpin($epinId, (int)$targetUser['id'])) {
            $message = "ePIN successfully transferred to user {$targetUsername}!";
        } else {
            $error = "Failed to transfer ePIN. It may already be used or revoked.";
        }
    }
}

$userEpins = $epinService->getEpinsForUser($currentUser['id']);

$pageTitle = "My ePINs";
require_once __DIR__ . '/../templates/layouts/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2 class="fw-bold"><i class="bi bi-key text-primary me-2"></i>My ePIN Keys</h2>
        <p class="text-muted">Manage your package ePIN activation keys or transfer them to downline members.</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-bold">
        <i class="bi bi-ticket-perforated me-2"></i>Assigned & Generated ePINs
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#ID</th>
                        <th>ePIN Code</th>
                        <th>Package</th>
                        <th>Value</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($userEpins)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No ePINs assigned to your account. Contact Admin to issue ePINs.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($userEpins as $epin): ?>
                            <tr>
                                <td>#<?= $epin['id'] ?></td>
                                <td><code class="fs-6 text-primary"><?= htmlspecialchars($epin['code']) ?></code></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($epin['package_name']) ?></span></td>
                                <td class="fw-bold">₹<?= number_format($epin['package_price'], 2) ?></td>
                                <td>
                                    <?php if ($epin['status'] === 'unused'): ?>
                                        <span class="badge bg-success">Unused / Active</span>
                                    <?php elseif ($epin['status'] === 'used'): ?>
                                        <span class="badge bg-danger">Used</span>
                                    <?php else: ?>
                                        <span class="badge bg-dark">Revoked</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d M Y', strtotime($epin['created_at'])) ?></td>
                                <td>
                                    <?php if ($epin['status'] === 'unused'): ?>
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#transferModal<?= $epin['id'] ?>">
                                            <i class="bi bi-send me-1"></i>Transfer
                                        </button>

                                        <!-- Transfer Modal -->
                                        <div class="modal fade" id="transferModal<?= $epin['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="POST" action="epins.php">
                                                        <input type="hidden" name="epin_id" value="<?= $epin['id'] ?>">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Transfer ePIN</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Transfer ePIN: <code><?= htmlspecialchars($epin['code']) ?></code></p>
                                                            <div class="mb-3">
                                                                <label class="form-label">Recipient Username</label>
                                                                <input type="text" name="target_username" class="form-control" required placeholder="Enter username">
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" name="transfer_epin" value="1" class="btn btn-primary">Transfer ePIN</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/layouts/footer.php'; ?>
