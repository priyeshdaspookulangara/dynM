<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Services\AuthService;
use App\Services\EpinService;
use App\Services\PackageService;
use App\Core\Database;

$adminUser = AuthService::requireAdmin();
$epinService = new EpinService();
$pkgService = new PackageService();
$db = Database::getConnection();

$message = '';
$error = '';

// Handle ePIN Generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_epins'])) {
    $packageId = (int)$_POST['package_id'];
    $quantity = max(1, min(50, (int)$_POST['quantity']));
    $assignUsername = trim($_POST['assign_username'] ?? '');

    $assignUserId = null;
    if (!empty($assignUsername)) {
        $stmtUser = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmtUser->execute([$assignUsername]);
        $uObj = $stmtUser->fetch();
        if ($uObj) {
            $assignUserId = (int)$uObj['id'];
        } else {
            $error = "User '{$assignUsername}' not found. ePINs generated without pre-assignment.";
        }
    }

    $generated = $epinService->generateEpins($packageId, $quantity, $adminUser['id'], $assignUserId);
    $message = "Successfully generated {$quantity} ePIN(s)!";
}

// Handle Revoke ePIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke_epin'])) {
    $epinId = (int)$_POST['epin_id'];
    if ($epinService->revokeEpin($epinId)) {
        $message = "ePIN revoked successfully!";
    } else {
        $error = "Unable to revoke ePIN. It may already be used or revoked.";
    }
}

$epins = $epinService->getAllEpins();
$packages = $pkgService->getAllPackages(true);

$pageTitle = "ePIN Manager";
require_once __DIR__ . '/../templates/layouts/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12 d-flex justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold"><i class="bi bi-ticket-perforated text-primary me-2"></i>ePIN Management</h2>
            <p class="text-muted mb-0">Generate single or bulk activation ePINs, track status, assign or revoke keys.</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateModal">
            <i class="bi bi-plus-circle me-1"></i>Generate ePINs
        </button>
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
                        <th>ePIN Code</th>
                        <th>Package</th>
                        <th>Price</th>
                        <th>Creator</th>
                        <th>Assigned To</th>
                        <th>Used By</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($epins)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">No ePINs generated yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($epins as $e): ?>
                            <tr>
                                <td>#<?= $e['id'] ?></td>
                                <td><code class="fs-6 text-primary"><?= htmlspecialchars($e['code']) ?></code></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($e['package_name']) ?></span></td>
                                <td class="fw-bold">₹<?= number_format($e['package_price'], 2) ?></td>
                                <td><?= htmlspecialchars($e['creator_name']) ?></td>
                                <td><?= htmlspecialchars($e['assigned_name'] ?? 'Unassigned') ?></td>
                                <td><?= htmlspecialchars($e['used_by_name'] ?? '-') ?></td>
                                <td>
                                    <?php if ($e['status'] === 'unused'): ?>
                                        <span class="badge bg-success">Unused</span>
                                    <?php elseif ($e['status'] === 'used'): ?>
                                        <span class="badge bg-danger">Used</span>
                                    <?php else: ?>
                                        <span class="badge bg-dark">Revoked</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($e['status'] === 'unused'): ?>
                                        <form method="POST" action="epins.php" style="display:inline;" onsubmit="return confirm('Revoke this ePIN?');">
                                            <input type="hidden" name="epin_id" value="<?= $e['id'] ?>">
                                            <button type="submit" name="revoke_epin" value="1" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-x-circle me-1"></i>Revoke
                                            </button>
                                        </form>
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

<!-- Generate ePIN Modal -->
<div class="modal fade" id="generateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="epins.php">
                <div class="modal-header">
                    <h5 class="modal-title">Generate New Package ePINs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Package</label>
                        <select name="package_id" class="form-select" required>
                            <?php foreach ($packages as $pkg): ?>
                                <option value="<?= $pkg['id'] ?>">
                                    <?= htmlspecialchars($pkg['name']) ?> (₹<?= number_format($pkg['price'], 2) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Quantity to Generate</label>
                        <input type="number" name="quantity" class="form-control" value="1" min="1" max="50" required>
                        <small class="text-muted">Generate up to 50 single-use ePINs at a time.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Assign To Username (Optional)</label>
                        <input type="text" name="assign_username" class="form-control" placeholder="Enter username (leave blank if unassigned)">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="generate_epins" value="1" class="btn btn-primary">Generate ePINs</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/layouts/footer.php'; ?>
