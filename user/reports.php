<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Services\AuthService;
use App\Core\Database;

$currentUser = AuthService::requireLogin();
$db = Database::getConnection();

// Earnings by Category
$stmtCat = $db->prepare("
    SELECT category, SUM(amount) as total_amount, COUNT(*) as tx_count
    FROM transactions
    WHERE user_id = ? AND type = 'credit'
    GROUP BY category
");
$stmtCat->execute([$currentUser['id']]);
$earningsByCategory = $stmtCat->fetchAll();

// Total Credited
$stmtTotCredit = $db->prepare("SELECT SUM(amount) as total FROM transactions WHERE user_id = ? AND type = 'credit'");
$stmtTotCredit->execute([$currentUser['id']]);
$totalEarnings = $stmtTotCredit->fetch()['total'] ?? 0.00;

// Total Debited (Payouts)
$stmtTotDebit = $db->prepare("SELECT SUM(amount) as total FROM transactions WHERE user_id = ? AND type = 'debit'");
$stmtTotDebit->execute([$currentUser['id']]);
$totalDebits = $stmtTotDebit->fetch()['total'] ?? 0.00;

// Direct Referrals Count
$stmtDirCount = $db->prepare("SELECT COUNT(*) as cnt FROM users WHERE sponsor_id = ?");
$stmtDirCount->execute([$currentUser['id']]);
$totalDirects = $stmtDirCount->fetch()['cnt'];

$pageTitle = "My Reports";
require_once __DIR__ . '/../templates/layouts/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2 class="fw-bold"><i class="bi bi-file-earmark-bar-graph text-primary me-2"></i>Income & Financial Reports</h2>
        <p class="text-muted">Summary analytics of earnings, direct bonuses, level-wise payouts, and withdrawals.</p>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card stat-card bg-success text-white">
            <div class="card-body">
                <h6 class="text-white-50"><i class="bi bi-cash-stack me-2"></i>Total Earned</h6>
                <h2 class="fw-bold my-2">₹<?= number_format($totalEarnings, 2) ?></h2>
                <small>Gross Income Earned</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card bg-danger text-white">
            <div class="card-body">
                <h6 class="text-white-50"><i class="bi bi-arrow-up-right-circle me-2"></i>Total Debited / Payouts</h6>
                <h2 class="fw-bold my-2">₹<?= number_format($totalDebits, 2) ?></h2>
                <small>Total Withdrawals / Expenses</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body">
                <h6 class="text-white-50"><i class="bi bi-people me-2"></i>Sponsored Members</h6>
                <h2 class="fw-bold my-2"><?= $totalDirects ?></h2>
                <small>Direct Referrals Count</small>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-bold">
        <i class="bi bi-pie-chart me-2"></i>Earnings Breakdown by Category
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Income Category</th>
                        <th>Transactions Count</th>
                        <th>Total Amount Earned</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($earningsByCategory)): ?>
                        <tr>
                            <td colspan="3" class="text-center py-4 text-muted">No income records available yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($earningsByCategory as $cat): ?>
                            <tr>
                                <td><strong class="text-primary"><?= htmlspecialchars(str_replace('_', ' ', strtoupper($cat['category']))) ?></strong></td>
                                <td><?= $cat['tx_count'] ?> Transaction(s)</td>
                                <td class="fw-bold text-success">₹<?= number_format($cat['total_amount'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/layouts/footer.php'; ?>
