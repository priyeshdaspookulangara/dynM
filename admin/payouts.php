<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Services\AuthService;
use App\Services\WalletService;

AuthService::requireAdmin();
$walletService = new WalletService();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_payout'])) {
    $payoutId = (int)$_POST['payout_id'];
    $action = $_POST['action_payout']; // 'approve' or 'reject'
    $notes = trim($_POST['notes'] ?? '');

    if ($walletService->processPayout($payoutId, $action, $notes)) {
        $message = "Payout request successfully " . ($action === 'approve' ? 'approved' : 'rejected and refunded') . ".";
    } else {
        $error = "Failed to process payout request.";
    }
}

$payouts = $walletService->getPayoutRequests();

$pageTitle = "Payout Requests";
require_once __DIR__ . '/../templates/layouts/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2 class="fw-bold"><i class="bi bi-cash-stack text-primary me-2"></i>Withdrawal Payout Requests</h2>
        <p class="text-muted">Review member payout requests, approve disbursements, or reject with automatic wallet refunds.</p>
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
                        <th>Amount</th>
                        <th>Requested Date</th>
                        <th>Status</th>
                        <th>Processed Date</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payouts)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No payout requests found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payouts as $p): ?>
                            <tr>
                                <td>#<?= $p['id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($p['full_name']) ?></strong><br>
                                    <small class="text-primary">@<?= htmlspecialchars($p['username']) ?></small>
                                </td>
                                <td class="fw-bold fs-6">₹<?= number_format($p['amount'], 2) ?></td>
                                <td><?= date('d M Y, h:i A', strtotime($p['requested_at'])) ?></td>
                                <td>
                                    <?php if ($p['status'] === 'pending'): ?>
                                        <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Pending</span>
                                    <?php elseif ($p['status'] === 'approved'): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Approved</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Rejected</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $p['processed_at'] ? date('d M Y, h:i A', strtotime($p['processed_at'])) : '-' ?></td>
                                <td><?= htmlspecialchars($p['notes'] ?? '-') ?></td>
                                <td>
                                    <?php if ($p['status'] === 'pending'): ?>
                                        <button class="btn btn-sm btn-success me-1" data-bs-toggle="modal" data-bs-target="#actionModal<?= $p['id'] ?>_approve">
                                            Approve
                                        </button>
                                        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#actionModal<?= $p['id'] ?>_reject">
                                            Reject
                                        </button>

                                        <!-- Approve Modal -->
                                        <div class="modal fade" id="actionModal<?= $p['id'] ?>_approve" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="POST" action="payouts.php">
                                                        <input type="hidden" name="payout_id" value="<?= $p['id'] ?>">
                                                        <input type="hidden" name="action_payout" value="approve">
                                                        <div class="modal-header bg-success text-white">
                                                            <h5 class="modal-title">Approve Payout Request #<?= $p['id'] ?></h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Confirm payout of <strong>₹<?= number_format($p['amount'], 2) ?></strong> to <strong><?= htmlspecialchars($p['username']) ?></strong>?</p>
                                                            <div class="mb-3">
                                                                <label class="form-label">Notes / Transaction Reference</label>
                                                                <input type="text" name="notes" class="form-control" placeholder="e.g. Bank Transfer Ref #123456">
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-success">Confirm Approval</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Reject Modal -->
                                        <div class="modal fade" id="actionModal<?= $p['id'] ?>_reject" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="POST" action="payouts.php">
                                                        <input type="hidden" name="payout_id" value="<?= $p['id'] ?>">
                                                        <input type="hidden" name="action_payout" value="reject">
                                                        <div class="modal-header bg-danger text-white">
                                                            <h5 class="modal-title">Reject Payout Request #<?= $p['id'] ?></h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Reject payout of <strong>₹<?= number_format($p['amount'], 2) ?></strong>? Funds will be refunded to user's wallet.</p>
                                                            <div class="mb-3">
                                                                <label class="form-label">Reason for Rejection</label>
                                                                <input type="text" name="notes" class="form-control" required placeholder="e.g. Invalid bank details">
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-danger">Confirm Rejection & Refund</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Done</span>
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
