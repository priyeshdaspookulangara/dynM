<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Services\AuthService;
use App\Services\SettingsService;

AuthService::requireAdmin();
$settingsService = new SettingsService();

$message = '';
$error = '';

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_general_settings'])) {
        $settingsService->set('matrix_width', (string)(int)$_POST['matrix_width'], 'Forced Matrix Width');
        $settingsService->set('matrix_depth', (string)(int)$_POST['matrix_depth'], 'Forced Matrix Maximum Depth');
        $settingsService->set('default_user_wallet_split', (string)(float)$_POST['default_user_wallet_split'], 'Default Percentage to User Wallet');
        $settingsService->set('default_company_wallet_split', (string)(float)$_POST['default_company_wallet_split'], 'Default Percentage to Company Wallet');
        $settingsService->set('min_payout_amount', (string)(float)$_POST['min_payout_amount'], 'Minimum Payout Amount');
        $settingsService->set('max_payout_amount', (string)(float)$_POST['max_payout_amount'], 'Maximum Single Payout Request Amount');
        $settingsService->set('company_wallet_user_id', (string)(int)$_POST['company_wallet_user_id'], 'Company Account User ID');

        $message = "General system parameters updated successfully!";
    } elseif (isset($_POST['update_level_commissions'])) {
        $levels = $_POST['levels'] ?? []; // array of level_number => percent
        $cleanLevels = [];
        foreach ($levels as $lvlNum => $percent) {
            $p = (float)$percent;
            if ($p > 0) {
                $cleanLevels[(int)$lvlNum] = $p;
            }
        }
        ksort($cleanLevels);
        $settingsService->updateLevelCommissions($cleanLevels);
        $message = "Multi-level payout distribution percentages updated successfully!";
    }
}

$allSettings = $settingsService->getAllSettings();
$levelCommissions = $settingsService->getLevelCommissions();

$pageTitle = "Dynamic Settings Engine";
require_once __DIR__ . '/../templates/layouts/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2 class="fw-bold text-dark"><i class="bi bi-gear-fill text-primary me-2"></i>Dynamic System Settings Engine</h2>
        <p class="text-muted">Manage all architectural and compensation rules dynamically without modifying codebase or hardcoding values.</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="row">
    <!-- General Settings -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-sliders me-2 text-primary"></i>Core Matrix & Wallet Configuration
            </div>
            <div class="card-body">
                <form method="POST" action="settings.php">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Forced Matrix Width</label>
                        <input type="number" name="matrix_width" class="form-control" value="<?= htmlspecialchars($allSettings['matrix_width']['value'] ?? 3) ?>" min="1" max="10" required>
                        <small class="text-muted">Number of max direct children slots per node in matrix (e.g. 3 for 3xN matrix).</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Forced Matrix Max Depth</label>
                        <input type="number" name="matrix_depth" class="form-control" value="<?= htmlspecialchars($allSettings['matrix_depth']['value'] ?? 5) ?>" min="1" max="20" required>
                        <small class="text-muted">Maximum depth level supported in spillover matrix tree.</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Default User Split (%)</label>
                            <input type="number" step="0.01" name="default_user_wallet_split" id="default_user_split" class="form-control" value="<?= htmlspecialchars($allSettings['default_user_wallet_split']['value'] ?? 60) ?>" required oninput="updateDefaultCompSplit()">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Default Company Split (%)</label>
                            <input type="number" step="0.01" name="default_company_wallet_split" id="default_company_split" class="form-control" value="<?= htmlspecialchars($allSettings['default_company_wallet_split']['value'] ?? 40) ?>" readonly>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Min Withdrawal (INR)</label>
                            <input type="number" step="0.01" name="min_payout_amount" class="form-control" value="<?= htmlspecialchars($allSettings['min_payout_amount']['value'] ?? 100) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Max Withdrawal (INR)</label>
                            <input type="number" step="0.01" name="max_payout_amount" class="form-control" value="<?= htmlspecialchars($allSettings['max_payout_amount']['value'] ?? 50000) ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Company Wallet User ID</label>
                        <input type="number" name="company_wallet_user_id" class="form-control" value="<?= htmlspecialchars($allSettings['company_wallet_user_id']['value'] ?? 1) ?>" required>
                        <small class="text-muted">User ID assigned to store company wallet retention funds (Default: 1).</small>
                    </div>

                    <button type="submit" name="update_general_settings" value="1" class="btn btn-primary w-100 mt-2">
                        <i class="bi bi-save me-1"></i>Save Core Parameters
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Multi-Level Incentive Distribution Rules -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-diagram-3 me-2 text-primary"></i>Multi-Level Commission Payout Rules</span>
                <button type="button" class="btn btn-sm btn-outline-success" onclick="addLevelRow()"><i class="bi bi-plus me-1"></i>Add Level</button>
            </div>
            <div class="card-body">
                <p class="text-muted small">Configure dynamic level-wise percentage payouts distributed to upline sponsors upon ePIN activations.</p>

                <form method="POST" action="settings.php">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle" id="levelsTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 30%;">Level Number</th>
                                    <th>Commission Payout (%)</th>
                                    <th style="width: 15%;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="levelsBody">
                                <?php foreach ($levelCommissions as $lvl): ?>
                                    <tr>
                                        <td>
                                            <input type="number" class="form-control fw-bold text-center" name="levels_num[]" value="<?= $lvl['level_number'] ?>" readonly>
                                        </td>
                                        <td>
                                            <div class="input-group">
                                                <input type="number" step="0.01" class="form-control" name="levels[<?= $lvl['level_number'] ?>]" value="<?= $lvl['commission_percent'] ?>" required>
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)"><i class="bi bi-trash"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <button type="submit" name="update_level_commissions" value="1" class="btn btn-primary w-100 mt-2">
                        <i class="bi bi-save me-1"></i>Save Level Payout Percentages
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function updateDefaultCompSplit() {
    const userSplit = parseFloat(document.getElementById('default_user_split').value) || 0;
    const compSplit = Math.max(0, 100 - userSplit);
    document.getElementById('default_company_split').value = compSplit.toFixed(2);
}

function addLevelRow() {
    const body = document.getElementById('levelsBody');
    const rowCount = body.rows.length + 1;
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <input type="number" class="form-control fw-bold text-center" name="levels_num[]" value="${rowCount}" readonly>
        </td>
        <td>
            <div class="input-group">
                <input type="number" step="0.01" class="form-control" name="levels[${rowCount}]" value="1.00" required>
                <span class="input-group-text">%</span>
            </div>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)"><i class="bi bi-trash"></i></button>
        </td>
    `;
    body.appendChild(tr);
}

function removeRow(btn) {
    const tr = btn.closest('tr');
    tr.remove();
}
</script>

<?php require_once __DIR__ . '/../templates/layouts/footer.php'; ?>
