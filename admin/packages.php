<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Services\AuthService;
use App\Services\PackageService;

AuthService::requireAdmin();
$pkgService = new PackageService();

$message = '';
$error = '';

// Handle Create / Update Package
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
    $name = trim($_POST['name'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $directReferralType = $_POST['direct_referral_type'] ?? 'percent';
    $directReferralPercent = (float)($_POST['direct_referral_percent'] ?? 0);
    $directReferralFixed = (float)($_POST['direct_referral_fixed'] ?? 0);
    $walletUserSplit = (float)($_POST['wallet_user_split'] ?? 60);
    $walletCompanySplit = (float)($_POST['wallet_company_split'] ?? 40);
    $status = $_POST['status'] ?? 'active';

    if (empty($name) || $price < 0) {
        $error = 'Package name and a valid price are required.';
    } else {
        $data = [
            'name' => $name,
            'price' => $price,
            'direct_referral_type' => $directReferralType,
            'direct_referral_percent' => $directReferralPercent,
            'direct_referral_fixed' => $directReferralFixed,
            'wallet_user_split' => $walletUserSplit,
            'wallet_company_split' => $walletCompanySplit,
            'status' => $status
        ];

        if ($id) {
            $pkgService->updatePackage($id, $data);
            $message = 'Package updated successfully!';
        } else {
            $pkgService->createPackage($data);
            $message = 'New package created successfully!';
        }
    }
}

$packages = $pkgService->getAllPackages(false);

$pageTitle = "Package Management";
require_once __DIR__ . '/../templates/layouts/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12 d-flex justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold"><i class="bi bi-box-seam text-primary me-2"></i>Package & Tier Management</h2>
            <p class="text-muted mb-0">Configure dynamic joining packages, price in INR, referral payouts, and wallet splits.</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#packageModal" onclick="resetForm()">
            <i class="bi bi-plus-circle me-1"></i>Create New Package
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
                        <th>Package Name</th>
                        <th>Price (INR)</th>
                        <th>Direct Referral Bonus</th>
                        <th>Wallet Split (User/Company)</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($packages as $pkg): ?>
                        <tr>
                            <td>#<?= $pkg['id'] ?></td>
                            <td><strong class="text-primary"><?= htmlspecialchars($pkg['name']) ?></strong></td>
                            <td class="fw-bold">₹<?= number_format($pkg['price'], 2) ?></td>
                            <td>
                                <?= $pkg['direct_referral_type'] === 'percent' ? $pkg['direct_referral_percent'] . '% Percentage' : '₹' . number_format($pkg['direct_referral_fixed'], 2) . ' Fixed' ?>
                            </td>
                            <td>
                                <span class="badge bg-success"><?= $pkg['wallet_user_split'] ?>% User</span> /
                                <span class="badge bg-dark"><?= $pkg['wallet_company_split'] ?>% Company</span>
                            </td>
                            <td>
                                <span class="badge bg-<?= $pkg['status'] === 'active' ? 'success' : 'secondary' ?>">
                                    <?= strtoupper($pkg['status']) ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick='editPackage(<?= json_encode($pkg) ?>)'>
                                    <i class="bi bi-pencil me-1"></i>Edit
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create / Edit Package Modal -->
<div class="modal fade" id="packageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="packages.php" id="pkgForm">
                <input type="hidden" name="id" id="pkg_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Create New Package</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Package Name</label>
                            <input type="text" name="name" id="pkg_name" class="form-control" required placeholder="e.g. Leadership Tier">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Price (INR)</label>
                            <input type="number" step="0.01" name="price" id="pkg_price" class="form-control" required placeholder="1000.00">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Direct Referral Type</label>
                            <select name="direct_referral_type" id="pkg_ref_type" class="form-select" onchange="toggleRefType()">
                                <option value="percent">Percentage (%)</option>
                                <option value="fixed">Fixed Amount (₹)</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3" id="ref_percent_group">
                            <label class="form-label">Referral Percentage (%)</label>
                            <input type="number" step="0.01" name="direct_referral_percent" id="pkg_ref_percent" class="form-control" value="10.00">
                        </div>
                        <div class="col-md-4 mb-3" id="ref_fixed_group" style="display:none;">
                            <label class="form-label">Referral Fixed Amount (INR)</label>
                            <input type="number" step="0.01" name="direct_referral_fixed" id="pkg_ref_fixed" class="form-control" value="0.00">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">User Wallet Split (%)</label>
                            <input type="number" step="0.01" name="wallet_user_split" id="pkg_user_split" class="form-control" value="60.00" oninput="updateCompanySplit()">
                            <small class="text-muted">Percentage sent to member withdrawable wallet.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Company Wallet Split (%)</label>
                            <input type="number" step="0.01" name="wallet_company_split" id="pkg_company_split" class="form-control" value="40.00" readonly>
                            <small class="text-muted">Auto-calculated company retention share.</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="pkg_status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Package</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleRefType() {
    const type = document.getElementById('pkg_ref_type').value;
    if (type === 'percent') {
        document.getElementById('ref_percent_group').style.display = 'block';
        document.getElementById('ref_fixed_group').style.display = 'none';
    } else {
        document.getElementById('ref_percent_group').style.display = 'none';
        document.getElementById('ref_fixed_group').style.display = 'block';
    }
}

function updateCompanySplit() {
    const userSplit = parseFloat(document.getElementById('pkg_user_split').value) || 0;
    const companySplit = Math.max(0, 100 - userSplit);
    document.getElementById('pkg_company_split').value = companySplit.toFixed(2);
}

function resetForm() {
    document.getElementById('modalTitle').innerText = 'Create New Package';
    document.getElementById('pkg_id').value = '';
    document.getElementById('pkgForm').reset();
    toggleRefType();
    updateCompanySplit();
}

function editPackage(pkg) {
    document.getElementById('modalTitle').innerText = 'Edit Package';
    document.getElementById('pkg_id').value = pkg.id;
    document.getElementById('pkg_name').value = pkg.name;
    document.getElementById('pkg_price').value = pkg.price;
    document.getElementById('pkg_ref_type').value = pkg.direct_referral_type;
    document.getElementById('pkg_ref_percent').value = pkg.direct_referral_percent;
    document.getElementById('pkg_ref_fixed').value = pkg.direct_referral_fixed;
    document.getElementById('pkg_user_split').value = pkg.wallet_user_split;
    document.getElementById('pkg_company_split').value = pkg.wallet_company_split;
    document.getElementById('pkg_status').value = pkg.status;
    toggleRefType();

    const modal = new bootstrap.Modal(document.getElementById('packageModal'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/../templates/layouts/footer.php'; ?>
