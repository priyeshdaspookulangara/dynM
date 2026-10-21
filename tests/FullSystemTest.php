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
        $this->testPasswordEncryptionToggle();
        $this->testPackageManagement();
        $this->testEpinLifecycle();
        $this->testForcedMatrixSpillover();
        $this->testCompensationDistributionsAndDualWallet();
        $this->testPayoutWorkflow();
        $this->testAdminMemberActivation();

        echo "\n=== ALL TESTS PASSED SUCCESSFULLY! ===\n";
    }

    private function testSettingsEngine(): void {
        echo "[1/8] Testing Dynamic Settings Engine...\n";

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

    private function testPasswordEncryptionToggle(): void {
        echo "[2/8] Testing Password Encryption Strategy (Plaintext vs BCrypt)...\n";

        // 1. By default, encrypt_passwords is 0 (Plaintext)
        $this->settingsService->set('encrypt_passwords', '0');
        $uPlain = $this->auth->registerUser('plain_user', 'plain@test.com', 'Plain User', 'secret123', 1);

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$uPlain['user_id']]);
        $storedPlain = $stmt->fetch()['password_hash'];
        assert($storedPlain === 'secret123', 'Password should be stored in plaintext when encryption is disabled');

        $loginPlain = $this->auth->login('plain_user', 'secret123');
        assert($loginPlain['success'], 'Plaintext login should succeed');

        // 2. Enable Encryption (encrypt_passwords = 1)
        $this->settingsService->set('encrypt_passwords', '1');
        $uEnc = $this->auth->registerUser('enc_user', 'enc@test.com', 'Encrypted User', 'secret123', 1);

        $stmt->execute([$uEnc['user_id']]);
        $storedEnc = $stmt->fetch()['password_hash'];
        assert(strpos($storedEnc, '$2y$') === 0, 'Password should be BCrypt hashed when encryption is enabled');

        $loginEnc = $this->auth->login('enc_user', 'secret123');
        assert($loginEnc['success'], 'Encrypted password login should succeed');

        // Reset to default
        $this->settingsService->set('encrypt_passwords', '0');

        echo " -> Password Encryption Toggle Test Passed.\n";
    }

    private function testPackageManagement(): void {
        echo "[3/8] Testing Package Management...\n";

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
        echo "[4/8] Testing ePIN Generation & Assignment...\n";

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
        echo "[5/8] Testing Forced Matrix BFS Spillover Placement...\n";

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
        echo "[6/8] Testing Compensation Distribution & Dual-Wallet Splits...\n";

        // Check balances after activations
        $db = Database::getConnection();
        $stmtUA = $db->prepare("SELECT id FROM users WHERE username = 'user_a'");
        $stmtUA->execute();
        $uA_id = $stmtUA->fetch()['id'];

        $walletA = $this->walletService->getWallet($uA_id);
        assert((float)$walletA['user_wallet_balance'] >= 240.00, 'User A balance should be at least 240');

        $transactionsA = $this->walletService->getTransactions($uA_id);
        assert(!empty($transactionsA), 'User A should have transactions recorded');

        echo " -> Compensation Distribution & Dual-Wallet Split Test Passed.\n";
    }

    private function testPayoutWorkflow(): void {
        echo "[7/8] Testing Withdrawal Payout Workflow...\n";

        $db = Database::getConnection();
        $stmtUA = $db->prepare("SELECT id FROM users WHERE username = 'user_a'");
        $stmtUA->execute();
        $uA_id = $stmtUA->fetch()['id'];

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

    private function testAdminMemberActivation(): void {
        echo "[8/8] Testing Admin Direct Member Activation...\n";

        // Register pending user
        $pendingUser = $this->auth->registerUser('pending_user', 'pending@test.com', 'Pending User', 'pass123', 1);
        $userId = $pendingUser['user_id'];

        // Auto-generate ePIN and activate
        $packages = $this->pkgService->getAllPackages(true);
        $generated = $this->epinService->generateEpins($packages[0]['id'], 1, 1, $userId);
        $epinCode = $generated[0]['code'];

        $actRes = $this->engine->activateUserWithEpin($userId, $epinCode);
        assert($actRes['success'], 'Admin direct member activation should succeed');

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT depth FROM matrix_nodes WHERE user_id = ?");
        $stmt->execute([$userId]);
        $node = $stmt->fetch();
        assert($node !== false, 'Activated member must have entry in matrix_nodes');

        echo " -> Admin Direct Member Activation Test Passed.\n";
    }
}

$test = new FullSystemTest();
$test->runAllTests();
