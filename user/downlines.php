<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Services\AuthService;
use App\Core\Database;

$currentUser = AuthService::requireLogin();
$db = Database::getConnection();

// Recursive BFS/DFS to get all downline nodes in matrix under current user
function getDownlines(PDO $db, int $parentUserId, int $currentDepth = 1, int $maxDepth = 10): array {
    $results = [];
    if ($currentDepth > $maxDepth) return $results;

    $stmt = $db->prepare("
        SELECT m.user_id, m.position, m.depth, u.username, u.full_name, u.email, u.created_at, s.username as sponsor_name
        FROM matrix_nodes m
        JOIN users u ON m.user_id = u.id
        LEFT JOIN users s ON m.sponsor_id = s.id
        WHERE m.parent_id = ?
        ORDER BY m.position ASC
    ");
    $stmt->execute([$parentUserId]);
    $children = $stmt->fetchAll();

    foreach ($children as $child) {
        $child['level_relative'] = $currentDepth;
        $results[] = $child;
        $subDownlines = getDownlines($db, $child['user_id'], $currentDepth + 1, $maxDepth);
        $results = array_merge($results, $subDownlines);
    }

    return $results;
}

$downlineMembers = getDownlines($db, $currentUser['id']);

$pageTitle = "My Downlines";
require_once __DIR__ . '/../templates/layouts/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2 class="fw-bold"><i class="bi bi-node-plus text-primary me-2"></i>My Downlines</h2>
        <p class="text-muted">Complete multi-level team downline list placed under you in the matrix structure.</p>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-diagram-2 me-2"></i>Matrix Downline Members (<?= count($downlineMembers) ?> Total)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Matrix Level</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Sponsor</th>
                        <th>Slot Position</th>
                        <th>Joined Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($downlineMembers)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No downline members in your matrix tree yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($downlineMembers as $d): ?>
                            <tr>
                                <td><span class="badge bg-primary">Level <?= $d['level_relative'] ?></span></td>
                                <td class="fw-bold text-primary"><?= htmlspecialchars($d['username']) ?></td>
                                <td><?= htmlspecialchars($d['full_name']) ?></td>
                                <td><?= htmlspecialchars($d['sponsor_name'] ?? 'System') ?></td>
                                <td>Position #<?= $d['position'] ?></td>
                                <td><?= date('d M Y', strtotime($d['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/layouts/footer.php'; ?>
