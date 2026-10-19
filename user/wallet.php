<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Services\AuthService;
use App\Services\WalletService;
use App\Services\SettingsService;

$currentUser = AuthService::requireLogin();
$walletService = new WalletService();
$settingsService = new SettingsService();

$wallet = $walletService->getWallet($currentUser['id']);
$transactions = $walletService->getTransactions($currentUser['id']);
$payoutRequests = $walletService->getPayoutRequests($currentUser['id']);

$minPayout = (float)$settingsService->get('min_payout_amount', 100);
$maxPayout = (float)$settingsService->get('max_payout_amount', 50000);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_payout'])) {
    $amount = (float)($_POST['payout_amount'] ?? 0);
    $result = $walletService->requestPayout($currentUser['id'], $amount);

    if ($result['success']) {
        $message = $result['message'];
        // Refresh wallet balance
        $wallet = $walletService->getWallet($currentUser['id']);
        $transactions = $walletService->getTransactions($currentUser['id']);
        $payoutRequests = $walletService->getPayoutRequests($currentUser['id']);
    } else {
        $error = $result['message'];
    }
}

$pageTitle = "Wallet & Payouts";
require_once __DIR__ . '/../templates/layouts/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2 class="fw-bold"><i class="bi bi-wallet2 text-primary me-2"></i>My Earnings Wallet & Payouts</h2>
        <p class="text-muted">Track your earnings, view full financial ledger, and request payout withdrawals.</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-5">
        <div class="card shadow-sm h-100 border-primary">
            <div class="card-header bg-primary text-white fw-bold">
                <i class="bi bi-cash-coin me-2"></i>Withdrawable Wallet Balance
            </div>
            <div class="card-body text-center d-flex flex-column justify-content-center">
                <p class="text-muted mb-1">Available Funds</p>
                <h1 class="display-4 fw-bold text-success">₹<?= number_format($wallet['user_wallet_balance'], 2) ?></h1>
                <small class="text-muted mt-2">Min Payout: ₹<?= number_format($minPayout, 2) ?> | Max Payout: ₹<?= number_format($maxPayout, 2) ?></small>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-send-plus me-2"></i>Request Withdrawal Payout
            </div>
            <div class="card-body">
                <form method="POST" action="wallet.php">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Payout Amount (INR)</label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" step="0.01" min="<?= $minPayout ?>" max="<?= $maxPayout ?>" name="payout_amount" class="form-control form-control-lg" required placeholder="0.00">
                        </div>
                        <small class="text-muted">Funds will be debited from your withdrawable wallet upon request.</small>
                    </div>
                    <button type="submit" name="request_payout" value="1" class="btn btn-primary btn-lg w-100">
                        <i class="bi bi-arrow-up-right-circle me-2"></i>Submit Payout Request
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Tabs for Ledger and Payout Requests -->
<ul class="nav nav-tabs nav-fill mb-3" id="walletTab" role="tablist">
    <li class="nav-item">
        <button class="nav-link active fw-bold" id="ledger-tab" data-bs-toggle="tab" data-bs-target="#ledger" type="button">
            <i class="bi bi-list-columns-reverse me-2"></i>Transaction Ledger
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link fw-bold" id="payouts-tab" data-bs-toggle="tab" data-bs-target="#payouts" type="button">
            <i class="bi bi-clock-history me-2"></i>Payout Requests History
        </button>
    </li>
</ul>

<div class="tab-content" id="walletTabContent">
    <!-- Transaction Ledger Tab -->
    <div class="tab-pane fade show active" id="ledger" role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#ID</th>
                                <th>Date & Time</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Amount</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No transactions found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $tx): ?>
                                    <tr>
                                        <td>#<?= $tx['id'] ?></td>
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

    <!-- Payout Requests Tab -->
    <div class="tab-pane fade" id="payouts" role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#ID</th>
                                <th>Requested Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Processed Date</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payoutRequests)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No payout requests submitted yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($payoutRequests as $p): ?>
                                    <tr>
                                        <td>#<?= $p['id'] ?></td>
                                        <td><?= date('d M Y, h:i A', strtotime($p['requested_at'])) ?></td>
                                        <td class="fw-bold">₹<?= number_format($p['amount'], 2) ?></td>
                                        <td>
                                            <?php if ($p['status'] === 'pending'): ?>
                                                <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Pending</span>
                                            <?php elseif ($p['status'] === 'approved'): ?>
                                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Approved</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Rejected (Refunded)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $p['processed_at'] ? date('d M Y, h:i A', strtotime($p['processed_at'])) : '-' ?></td>
                                        <td><?= htmlspecialchars($p['notes'] ?? '-') ?></td>
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
