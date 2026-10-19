<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Services\AuthService;
use App\Services\WalletService;
use App\Services\CompensationEngine;
use App\Services\EpinService;
use App\Core\Database;

$currentUser = AuthService::requireLogin();
$db = Database::getConnection();

$walletService = new WalletService();
$wallet = $walletService->getWallet($currentUser['id']);
$recentTransactions = array_slice($walletService->getTransactions($currentUser['id']), 0, 5);

// Check matrix status
$stmtM = $db->prepare("SELECT * FROM matrix_nodes WHERE user_id = ?");
$stmtM->execute([$currentUser['id']]);
$matrixNode = $stmtM->fetch();

// Direct referrals count
$stmtRef = $db->prepare("SELECT COUNT(*) as cnt FROM users WHERE sponsor_id = ?");
$stmtRef->execute([$currentUser['id']]);
$directCount = $stmtRef->fetch()['cnt'];

// Handle ePIN activation post
$activationError = '';
$activationSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_epin'])) {
    $code = trim($_POST['epin_code'] ?? '');
    $engine = new CompensationEngine();
    $res = $engine->activateUserWithEpin($currentUser['id'], $code);
    if ($res['success']) {
        $activationSuccess = 'Account activated successfully!';
        // Refresh matrix node status
        $stmtM->execute([$currentUser['id']]);
        $matrixNode = $stmtM->fetch();
    } else {
        $activationError = $res['message'];
    }
}

$pageTitle = "User Dashboard";
require_once __DIR__ . '/../templates/layouts/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2 class="fw-bold"><i class="bi bi-speedometer2 text-primary me-2"></i>Welcome, <?= htmlspecialchars($currentUser['full_name']) ?>!</h2>
        <p class="text-muted">Username: <strong><?= htmlspecialchars($currentUser['username']) ?></strong> | Role: <span class="badge bg-secondary"><?= strtoupper($currentUser['role']) ?></span></p>
    </div>
</div>

<?php if (!$matrixNode): ?>
    <div class="card border-warning mb-4 shadow-sm">
        <div class="card-header bg-warning text-dark fw-bold">
            <i class="bi bg-warning text-dark bi-exclamation-triangle-fill me-2"></i>Account Inactive in Matrix
        </div>
        <div class="card-body">
            <p>Your account is registered but not yet activated in the forced matrix. Activate now using an ePIN to start earning commissions!</p>
            <?php if ($activationError): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($activationError) ?></div>
            <?php endif; ?>
            <?php if ($activationSuccess): ?>
                <div class="alert alert-success"><?= htmlspecialchars($activationSuccess) ?></div>
            <?php endif; ?>
            <form method="POST" action="dashboard.php" class="row g-2 align-items-center">
                <div class="col-md-6">
                    <input type="text" name="epin_code" class="form-control" required placeholder="Enter ePIN Code (e.g. EPIN-XXXX)">
                </div>
                <div class="col-md-3">
                    <button type="submit" name="activate_epin" value="1" class="btn btn-primary w-100"><i class="bi bi-check-circle me-1"></i>Activate Account</button>
                </div>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-success mb-4"><i class="bi bi-check-circle-fill me-2"></i>Account Active in Matrix (Depth Level <?= $matrixNode['depth'] ?>, Slot <?= $matrixNode['position'] ?>)</div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body">
                <h6 class="text-white-50"><i class="bi bi-wallet2 me-2"></i>Withdrawable Balance</h6>
                <h2 class="fw-bold my-2">₹<?= number_format($wallet['user_wallet_balance'], 2) ?></h2>
                <a href="/user/wallet.php" class="btn btn-sm btn-light text-primary mt-2 fw-bold">Request Payout &rarr;</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card bg-success text-white">
            <div class="card-body">
                <h6 class="text-white-50"><i class="bi bi-people me-2"></i>Direct Referrals</h6>
                <h2 class="fw-bold my-2"><?= $directCount ?> Members</h2>
                <a href="/user/referrals.php" class="btn btn-sm btn-light text-success mt-2 fw-bold">View Downline &rarr;</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card bg-info text-white">
            <div class="card-body">
                <h6 class="text-white-50"><i class="bi bi-diagram-3 me-2"></i>Matrix Position</h6>
                <h2 class="fw-bold my-2"><?= $matrixNode ? 'Level ' . $matrixNode['depth'] : 'Pending' ?></h2>
                <a href="/user/matrix.php" class="btn btn-sm btn-light text-info mt-2 fw-bold">View Matrix Tree &rarr;</a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2"></i>Recent Wallet Transactions</h5>
                <a href="/user/wallet.php" class="btn btn-sm btn-outline-primary">View All Transactions</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Amount</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentTransactions)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No transactions recorded yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentTransactions as $tx): ?>
                                    <tr>
                                        <td><?= date('d M Y, h:i A', strtotime($tx['created_at'])) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $tx['type'] === 'credit' ? 'success' : 'danger' ?>">
                                                <?= strtoupper($tx['type']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars(str_replace('_', ' ', $tx['category'])) ?></td>
                                        <td class="fw-bold text-<?= $tx['type'] === 'credit' ? 'success' : 'danger' ?>">
                                            <?= $tx['type'] === 'credit' ? '+' : '-' ?>₹<?= number_format($tx['amount'], 2) ?>
                                        </td>
                                        <td><?= htmlspecialchars($tx['description']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/layouts/footer.php'; ?>
