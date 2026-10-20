<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Services\AuthService;
use App\Services\CompensationEngine;

$currentUser = AuthService::requireLogin();
$engine = new CompensationEngine();

// Fetch genealogy tree starting from current user
$treeData = $engine->getMatrixTree($currentUser['id'], 4);

$pageTitle = "Genealogy Tree";
require_once __DIR__ . '/../templates/layouts/header.php';

function renderTreeNode(array $node) {
    echo '<li>';
    echo '<a href="#" class="shadow-sm border-primary text-center">';
    echo '<i class="bi bi-person-circle fs-4 text-primary d-block mb-1"></i>';
    echo '<strong>' . htmlspecialchars($node['username']) . '</strong><br>';
    echo '<small class="text-muted">' . htmlspecialchars($node['full_name']) . '</small>';
    echo '</a>';

    if (!empty($node['children'])) {
        echo '<ul>';
        foreach ($node['children'] as $child) {
            renderTreeNode($child);
        }
        echo '</ul>';
    }
    echo '</li>';
}
?>

<div class="row mb-4">
    <div class="col-md-12 d-flex justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold"><i class="bi bi-diagram-3 text-primary me-2"></i>Genealogy Tree Visualizer</h2>
            <p class="text-muted mb-0">Visual view of your placement tree and downline structure.</p>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body overflow-auto py-5 text-center">
        <?php if (empty($treeData)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i> You are not placed in the matrix yet. Activate your account with an ePIN from the dashboard.
            </div>
        <?php else: ?>
            <div class="tree d-inline-block text-center">
                <ul>
                    <?php renderTreeNode($treeData); ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/layouts/footer.php'; ?>
