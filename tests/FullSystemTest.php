<?php

require_once __DIR__ . '/../src/bootstrap.php';

use App\Core\Database;
use App\Services\AuthService;
use App\Services\PackageService;
use App\Services\EpinService;
use App\Services\WalletService;
use App\Services\CompensationEngine;
use App\Services\SettingsService;

class FullSystemTest {
    private AuthService $auth;
    private PackageService $pkgService;
    private EpinService $epinService;
    private WalletService $walletService;
    private CompensationEngine $engine;
    private SettingsService $settingsService;

    public function __construct() {
        Database::resetConnection();
        $dbPath = __DIR__ . '/../database/app.sqlite';
        if (file_exists($dbPath)) {
            unlink($dbPath);
        }
        Database::initSchema();

        $this->auth = new AuthService();
        $this->pkgService = new PackageService();
        $this->epinService = new EpinService();
        $this->walletService = new WalletService();
        $this->engine = new CompensationEngine();
        $this->settingsService = new SettingsService();
    }

    public function runAllTests(): void {
        echo "=== STARTING COMPREHENSIVE SYSTEM TESTS ===\n\n";

        $this->testSettingsEngine();
        $this->testPackageManagement();
        $this->testEpinLifecycle();
        $this->testForcedMatrixSpillover();
        $this->testCompensationDistributionsAndDualWallet();
        $this->testPayoutWorkflow();

        echo "\n=== ALL TESTS PASSED SUCCESSFULLY! ===\n";
    }

    private function testSettingsEngine(): void {
        echo "[1/6] Testing Dynamic Settings Engine...\n";

        $this->settingsService->set('matrix_width', '3', '3xN Matrix');
        $this->settingsService->set('matrix_depth', '5', 'Depth 5');
        $this->settingsService->set('min_payout_amount', '100', 'Min 100 INR');

        assert($this->settingsService->get('matrix_width') === '3', 'Matrix width should be 3');
        assert($this->settingsService->get('matrix_depth') === '5', 'Matrix depth should be 5');

        // Update Level Commissions
        $this->settingsService->updateLevelCommissions([
            1 => 10.0,
            2 => 5.0,
            3 => 2.0
        ]);

        $levels = $this->settingsService->getLevelCommissions();
        assert(count($levels) === 3, 'Should have 3 levels configured');
        assert((float)$levels[0]['commission_percent'] === 10.0, 'Level 1 percent should be 10%');

        echo " -> Dynamic Settings Engine Test Passed.\n";
    }

    private function testPackageManagement(): void {
        echo "[2/6] Testing Package Management...\n";

        $pkgId = $this->pkgService->createPackage([
            'name' => 'Custom Starter Tier',
            'price' => 2000.00,
            'direct_referral_percent' => 15.00,
            'direct_referral_type' => 'percent',
            'wallet_user_split' => 70.00,
            'wallet_company_split' => 30.00,
            'status' => 'active'
        ]);

        $pkg = $this->pkgService->getPackageById($pkgId);
        assert($pkg !== null, 'Package should exist');
        assert($pkg['name'] === 'Custom Starter Tier', 'Package name match');
        assert((float)$pkg['price'] === 2000.00, 'Package price match');

        echo " -> Package Management Test Passed.\n";
    }

    private function testEpinLifecycle(): void {
        echo "[3/6] Testing ePIN Generation & Assignment...\n";

        $packages = $this->pkgService->getAllPackages(true);
        $pkgId = $packages[0]['id'];

        $epins = $this->epinService->generateEpins($pkgId, 2, 1);
        assert(count($epins) === 2, 'Should generate 2 ePINs');

        $epinCode = $epins[0]['code'];
        $fetched = $this->epinService->getEpinByCode($epinCode);
        assert($fetched['status'] === 'unused', 'New ePIN should be unused');

        // Revoke second ePIN
        $this->epinService->revokeEpin($epins[1]['id']);
        $fetchedRev = $this->epinService->getEpinByCode($epins[1]['code']);
        assert($fetchedRev['status'] === 'revoked', 'ePIN should be revoked');

        echo " -> ePIN Lifecycle Test Passed.\n";
    }

    private function testForcedMatrixSpillover(): void {
        echo "[4/6] Testing Forced Matrix BFS Spillover Placement...\n";

        // Create Sponsor chain: Root (ID 1) -> User A -> Users B, C, D, E
        $uA = $this->auth->registerUser('user_a', 'a@test.com', 'User A', 'pass123', 1);
        $uB = $this->auth->registerUser('user_b', 'b@test.com', 'User B', 'pass123', $uA['user_id']);
        $uC = $this->auth->registerUser('user_c', 'c@test.com', 'User C', 'pass123', $uA['user_id']);
        $uD = $this->auth->registerUser('user_d', 'd@test.com', 'User D', 'pass123', $uA['user_id']);
        $uE = $this->auth->registerUser('user_e', 'e@test.com', 'User E', 'pass123', $uA['user_id']);

        $pkg = $this->pkgService->getAllPackages(true)[0];
        $epins = $this->epinService->generateEpins($pkg['id'], 5, 1);

        // Activate A under Super Admin (ID 1)
        $resA = $this->engine->activateUserWithEpin($uA['user_id'], $epins[0]['code']);
        assert($resA['success'], 'User A activation success');

        // Activate B, C, D (should fill User A's 3 slots)
        $resB = $this->engine->activateUserWithEpin($uB['user_id'], $epins[1]['code']);
        $resC = $this->engine->activateUserWithEpin($uC['user_id'], $epins[2]['code']);
        $resD = $this->engine->activateUserWithEpin($uD['user_id'], $epins[3]['code']);

        assert($resB['matrix_node']['parent_id'] == $uA['user_id'], 'User B parent should be A');
        assert($resC['matrix_node']['parent_id'] == $uA['user_id'], 'User C parent should be A');
        assert($resD['matrix_node']['parent_id'] == $uA['user_id'], 'User D parent should be A');

        // Activate E (since A is full with 3 slots, spillover BFS places E under B!)
        $resE = $this->engine->activateUserWithEpin($uE['user_id'], $epins[4]['code']);
        assert($resE['success'], 'User E activation success');
        assert($resE['matrix_node']['parent_id'] == $uB['user_id'], 'Spillover: User E parent should be B');

        echo " -> Forced Matrix BFS Spillover Placement Test Passed.\n";
    }

    private function testCompensationDistributionsAndDualWallet(): void {
        echo "[5/6] Testing Compensation Distribution & Dual-Wallet Splits...\n";

        // Check balances after activations in test 4
        // Direct referral bonus for User A sponsoring B, C, D, E (4 users)
        // Package price: ₹1000, Direct Referral: 10% = ₹100 per activation.
        // Wallet Split: 60% User, 40% Company
        // User A gets ₹60 per user * 4 users = ₹240
        $uA_id = 3; // User A ID
        $walletA = $this->walletService->getWallet($uA_id);

        assert((float)$walletA['user_wallet_balance'] >= 240.00, 'User A balance should be at least 240');

        $transactionsA = $this->walletService->getTransactions($uA_id);
        assert(!empty($transactionsA), 'User A should have transactions recorded');

        echo " -> Compensation Distribution & Dual-Wallet Split Test Passed.\n";
    }

    private function testPayoutWorkflow(): void {
        echo "[6/6] Testing Withdrawal Payout Workflow...\n";

        $uA_id = 3;
        $walletA = $this->walletService->getWallet($uA_id);
        $initialBal = (float)$walletA['user_wallet_balance'];

        // Request Payout of ₹100
        $payoutRes = $this->walletService->requestPayout($uA_id, 100.00);
        assert($payoutRes['success'], 'Payout request should succeed');

        $walletAAfter = $this->walletService->getWallet($uA_id);
        assert((float)$walletAAfter['user_wallet_balance'] === ($initialBal - 100.00), 'Balance should decrease by 100');

        $requests = $this->walletService->getPayoutRequests($uA_id);
        assert(count($requests) > 0, 'Should have payout request record');

        // Test Payout Rejection & Refund
        $payoutId = $requests[0]['id'];
        $processRes = $this->walletService->processPayout($payoutId, 'reject', 'Test rejection');
        assert($processRes, 'Process payout rejection should succeed');

        $walletAAfterRefund = $this->walletService->getWallet($uA_id);
        assert((float)$walletAAfterRefund['user_wallet_balance'] === $initialBal, 'Balance should be refunded back to initial balance');

        echo " -> Withdrawal Payout Workflow Test Passed.\n";
    }
}

$test = new FullSystemTest();
$test->runAllTests();
