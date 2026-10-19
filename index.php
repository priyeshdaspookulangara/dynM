<?php
require_once __DIR__ . '/src/bootstrap.php';
use App\Services\PackageService;

$pageTitle = "Home - Dynamic Direct Selling App";
require_once __DIR__ . '/templates/layouts/header.php';

$pkgService = new PackageService();
$packages = $pkgService->getAllPackages(true);
?>

<div class="row align-items-center mb-5 py-4 bg-white rounded shadow-sm p-4">
    <div class="col-lg-7">
        <h1 class="display-5 fw-bold text-dark mb-3">Multi-Tier Direct Selling Compensation Engine</h1>
        <p class="lead text-secondary">
            Fully configurable forced matrix compensation system with ePIN activations, multi-level incentive distribution, dual-wallet management, and zero hardcoded rules.
        </p>
        <div class="d-grid gap-2 d-md-flex justify-content-md-start mt-4">
            <a href="/register.php" class="btn btn-primary btn-lg px-4 me-md-2">Get Started</a>
            <a href="/login.php" class="btn btn-outline-secondary btn-lg px-4">Member Login</a>
        </div>
    </div>
    <div class="col-lg-5 text-center mt-4 mt-lg-0">
        <img src="https://images.unsplash.com/photo-1551836022-d5d88e9218df?auto=format&fit=crop&w=600&q=80" alt="MLM Concept" class="img-fluid rounded shadow" style="max-height: 280px; object-fit: cover;">
    </div>
</div>

<h3 class="mb-4 text-center fw-bold"><i class="bi bi-box-seam text-primary me-2"></i>Available Packages & Tiers</h3>
<div class="row">
    <?php foreach ($packages as $pkg): ?>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100 shadow-sm border-primary">
                <div class="card-header bg-primary text-white text-center py-3">
                    <h4 class="my-0 font-weight-normal"><?= htmlspecialchars($pkg['name']) ?></h4>
                </div>
                <div class="card-body text-center d-flex flex-column">
                    <h1 class="card-title pricing-card-title my-3">₹<?= number_format($pkg['price'], 2) ?></h1>
                    <ul class="list-unstyled mt-3 mb-4 text-start">
                        <li class="py-1"><i class="bi bi-check-circle-fill text-success me-2"></i><strong>Direct Referral Bonus:</strong>
                            <?= $pkg['direct_referral_type'] === 'percent' ? $pkg['direct_referral_percent'] . '%' : '₹' . $pkg['direct_referral_fixed'] ?>
                        </li>
                        <li class="py-1"><i class="bi bi-check-circle-fill text-success me-2"></i><strong>User Wallet Split:</strong> <?= $pkg['wallet_user_split'] ?>%</li>
                        <li class="py-1"><i class="bi bi-check-circle-fill text-success me-2"></i><strong>Company Wallet Split:</strong> <?= $pkg['wallet_company_split'] ?>%</li>
                        <li class="py-1"><i class="bi bi-check-circle-fill text-success me-2"></i><strong>Multi-Level Commission Eligible</strong></li>
                    </ul>
                    <a href="/register.php" class="w-100 btn btn-lg btn-outline-primary mt-auto">Register with ePIN</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/templates/layouts/footer.php'; ?>
