<?php
// Test script for backend models and compensation engine

require_once __DIR__ . '/src/Core/Database.php';
require_once __DIR__ . '/src/Services/SettingsService.php';
require_once __DIR__ . '/src/Services/AuthService.php';
require_once __DIR__ . '/src/Services/PackageService.php';
require_once __DIR__ . '/src/Services/EpinService.php';
require_once __DIR__ . '/src/Services/WalletService.php';
require_once __DIR__ . '/src/Services/CompensationEngine.php';

use App\Core\Database;
use App\Services\AuthService;
use App\Services\PackageService;
use App\Services\EpinService;
use App\Services\WalletService;
use App\Services\CompensationEngine;
use App\Services\SettingsService;

echo "--- Initializing Schema ---\n";
Database::initSchema();
echo "Schema initialized successfully.\n";

$auth = new AuthService();
$pkgService = new PackageService();
$epinService = new EpinService();
$walletService = new WalletService();
$engine = new CompensationEngine();
$settings = new SettingsService();

echo "--- Creating Test Users ---\n";
$user1 = $auth->registerUser('user1', 'user1@test.com', 'User One', 'pass123', 1); // Sponsored by superadmin (ID 1)
$u1_id = $user1['user_id'];
echo "Registered User 1 ID: {$u1_id}\n";

$user2 = $auth->registerUser('user2', 'user2@test.com', 'User Two', 'pass123', $u1_id); // Sponsored by User 1
$u2_id = $user2['user_id'];
echo "Registered User 2 ID: {$u2_id}\n";

$user3 = $auth->registerUser('user3', 'user3@test.com', 'User Three', 'pass123', $u1_id); // Sponsored by User 1
$u3_id = $user3['user_id'];
echo "Registered User 3 ID: {$u3_id}\n";

$user4 = $auth->registerUser('user4', 'user4@test.com', 'User Four', 'pass123', $u1_id); // Sponsored by User 1
$u4_id = $user4['user_id'];
echo "Registered User 4 ID: {$u4_id}\n";

echo "--- Generating ePINs ---\n";
$packages = $pkgService->getAllPackages(true);
$pkg = $packages[0]; // Foundation Tier: ₹1000, 10% direct referral, 60/40 split

$epins = $epinService->generateEpins($pkg['id'], 4, 1);
echo "Generated " . count($epins) . " ePINs.\n";

echo "--- Activating Users ---\n";
$res1 = $engine->activateUserWithEpin($u1_id, $epins[0]['code']);
echo "User 1 Activation: " . ($res1['success'] ? 'SUCCESS' : 'FAILED: ' . $res1['message']) . "\n";

$res2 = $engine->activateUserWithEpin($u2_id, $epins[1]['code']);
echo "User 2 Activation: " . ($res2['success'] ? 'SUCCESS' : 'FAILED: ' . $res2['message']) . "\n";

$res3 = $engine->activateUserWithEpin($u3_id, $epins[2]['code']);
echo "User 3 Activation: " . ($res3['success'] ? 'SUCCESS' : 'FAILED: ' . $res3['message']) . "\n";

$res4 = $engine->activateUserWithEpin($u4_id, $epins[3]['code']);
echo "User 4 Activation: " . ($res4['success'] ? 'SUCCESS' : 'FAILED: ' . $res4['message']) . "\n";

echo "--- Checking Balances & Matrix ---\n";
$u1_wallet = $walletService->getWallet($u1_id);
echo "User 1 Wallet Balance: ₹{$u1_wallet['user_wallet_balance']}\n";

$company_wallet = $walletService->getWallet(1);
echo "Company Account Wallet Balance (User Share): ₹{$company_wallet['user_wallet_balance']}, Company Share: ₹{$company_wallet['company_wallet_balance']}\n";

$tree = $engine->getMatrixTree(1, 4);
echo "Matrix Tree structure generated successfully.\n";
print_r($tree);

echo "Backend services test completed!\n";
