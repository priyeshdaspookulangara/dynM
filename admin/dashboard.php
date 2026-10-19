<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Services\AuthService;
use App\Core\Database;

$currentUser = AuthService::requireAdmin();
$db = Database::getConnection();

// System stats
$totalUsers = $db->query("SELECT COUNT(*) as cnt FROM users")->fetch()['cnt'];
$activeMatrixUsers = $db->query("SELECT COUNT(*) as cnt FROM matrix_nodes")->fetch()['cnt'];
$totalEpins = $db->query("SELECT COUNT(*) as cnt FROM epins")->fetch()['cnt'];
$usedEpins = $db->query("SELECT COUNT(*) as cnt FROM epins WHERE status = 'used'")->fetch()['cnt'];

// Company Wallet Total Accumulation
$stmtComp = $db->query("SELECT SUM(company_wallet_balance) as total FROM wallets");
$companyBalance = $stmtComp->fetch()['total'] ?? 0.00;

// Pending Payout Requests
$stmtPay = $db->query("SELECT COUNT(*) as cnt, SUM(amount) as total FROM payouts WHERE status = 'pending'");
$payData = $stmtPay->fetch();
$pendingPayoutsCount = $payData['cnt'] ?? 0;
$pendingPayoutsSum = $payData['total'] ?? 0.00;

// Recent System Transactions
$stmtRecent = $db->query("
    SELECT t.*, u.username
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    ORDER BY t.created_at DESC LIMIT 6
");
$recentTx = $stmtRecent->fetchAll();

$pageTitle = "Admin Dashboard";
require_once __DIR__ . '/../templates/layouts/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2 class="fw-bold text-dark"><i class="bi bi-shield-lock text-primary me-2"></i>Admin Overview Panel</h2>
        <p class="text-muted">System metrics, financial overview, and administrative quick links.</p>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body">
                <h6 class="text-white-50"><i class="bi bi-people me-2"></i>Total Users</h6>
                <h2 class="fw-bold my-2"><?= $totalUsers ?></h2>
                <small><?= $activeMatrixUsers ?> Active in Matrix</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card bg-success text-white">
            <div class="card-body">
                <h6 class="text-white-50"><i class="bi bi-building me-2"></i>Company Wallet Retention</h6>
                <h2 class="fw-bold my-2">₹<?= number_format($companyBalance, 2) ?></h2>
                <small>Company Split Accumulation</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card bg-warning text-dark">
            <div class="card-body">
                <h6 class="text-dark-50"><i class="bi bi-cash-stack me-2"></i>Pending Payouts</h6>
                <h2 class="fw-bold my-2"><?= $pendingPayoutsCount ?> Requests</h2>
                <small>Totaling ₹<?= number_format($pendingPayoutsSum, 2) ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card bg-info text-white">
            <div class="card-body">
                <h6 class="text-white-50"><i class="bi bi-key me-2"></i>Total ePINs</h6>
                <h2 class="fw-bold my-2"><?= $totalEpins ?></h2>
                <small><?= $usedEpins ?> Used / Activated</small>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card h-100 shadow-sm border-0 bg-light">
            <div class="card-body d-flex flex-column justify-content-between text-center p-4">
                <div>
                    <i class="bi bi-gear-fill fs-1 text-primary d-block mb-3"></i>
                    <h5 class="fw-bold">Dynamic Settings Engine</h5>
                    <p class="text-muted small">Configure matrix width/depth, referral %, multi-level payouts, and split ratios on the fly.</p>
                </div>
                <a href="/admin/settings.php" class="btn btn-primary w-100 mt-3">Configure Settings</a>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="card h-100 shadow-sm border-0 bg-light">
            <div class="card-body d-flex flex-column justify-content-between text-center p-4">
                <div>
                    <i class="bi bi-ticket-perforated-fill fs-1 text-success d-block mb-3"></i>
                    <h5 class="fw-bold">ePIN Management</h5>
                    <p class="text-muted small">Generate bulk ePINs for packages, assign to users, and track activations.</p>
                </div>
                <a href="/admin/epins.php" class="btn btn-success w-100 mt-3">Manage ePINs</a>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="card h-100 shadow-sm border-0 bg-light">
            <div class="card-body d-flex flex-column justify-content-between text-center p-4">
                <div>
                    <i class="bi bi-cash-coin fs-1 text-warning d-block mb-3"></i>
                    <h5 class="fw-bold">Payout Approval Requests</h5>
                    <p class="text-muted small">Approve or reject member withdrawal requests with ledger updates.</p>
                </div>
                <a href="/admin/payouts.php" class="btn btn-warning w-100 mt-3">Manage Payouts</a>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-bold">
        <i class="bi bi-activity me-2"></i>Recent System Ledger Activity
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date & Time</th>
                        <th>User</th>
                        <th>Type</th>
                        <th>Wallet</th>
                        <th>Category</th>
                        <th>Amount</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentTx)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No activity logged yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentTx as $tx): ?>
                            <tr>
                                <td><?= date('d M Y, h:i A', strtotime($tx['created_at'])) ?></td>
                                <td><strong><?= htmlspecialchars($tx['username']) ?></strong></td>
                                <td><span class="badge bg-<?= $tx['type'] === 'credit' ? 'success' : 'danger' ?>"><?= strtoupper($tx['type']) ?></span></td>
                                <td><span class="badge bg-<?= $tx['wallet_type'] === 'company' ? 'dark' : 'info' ?>"><?= strtoupper($tx['wallet_type']) ?></span></td>
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

<?php require_once __DIR__ . '/../templates/layouts/footer.php'; ?>
