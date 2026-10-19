<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Services\AuthService;
use App\Core\Database;

$currentUser = AuthService::requireLogin();
$db = Database::getConnection();

// Direct Referrals
$stmtDir = $db->prepare("
    SELECT u.id, u.username, u.full_name, u.email, u.created_at, m.depth, m.position
    FROM users u
    LEFT JOIN matrix_nodes m ON u.id = m.user_id
    WHERE u.sponsor_id = ?
    ORDER BY u.created_at DESC
");
$stmtDir->execute([$currentUser['id']]);
$directReferrals = $stmtDir->fetchAll();

$pageTitle = "My Referrals & Team";
require_once __DIR__ . '/../templates/layouts/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2 class="fw-bold"><i class="bi bi-people text-primary me-2"></i>Direct Referrals & Team Chain</h2>
        <p class="text-muted">List of all members sponsored directly by you.</p>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-bold">
        <i class="bi bi-person-lines-fill me-2"></i>Directly Sponsored Members (<?= count($directReferrals) ?> Total)
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#ID</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Joined Date</th>
                        <th>Matrix Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($directReferrals)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No direct referrals yet. Share your username to invite members!</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($directReferrals as $ref): ?>
                            <tr>
                                <td>#<?= $ref['id'] ?></td>
                                <td class="fw-bold text-primary"><?= htmlspecialchars($ref['username']) ?></td>
                                <td><?= htmlspecialchars($ref['full_name']) ?></td>
                                <td><?= htmlspecialchars($ref['email']) ?></td>
                                <td><?= date('d M Y', strtotime($ref['created_at'])) ?></td>
                                <td>
                                    <?php if ($ref['depth'] !== null): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Active (Level <?= $ref['depth'] ?>)</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="bi bi-clock me-1"></i>Pending ePIN</span>
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
