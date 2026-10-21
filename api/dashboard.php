<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Services\ApiService;
use App\Services\WalletService;
use App\Core\Database;

$user = ApiService::authenticateApiRequest();
$db = Database::getConnection();

$walletService = new WalletService();
$wallet = $walletService->getWallet($user['id']);
$recentTransactions = array_slice($walletService->getTransactions($user['id']), 0, 5);

// Matrix node status
$stmtM = $db->prepare("SELECT * FROM matrix_nodes WHERE user_id = ?");
$stmtM->execute([$user['id']]);
$matrixNode = $stmtM->fetch() ?: null;

// Direct referrals count
$stmtRef = $db->prepare("SELECT COUNT(*) as cnt FROM users WHERE sponsor_id = ?");
$stmtRef->execute([$user['id']]);
$directCount = (int)$stmtRef->fetch()['cnt'];

ApiService::sendJson([
    'success' => true,
    'user' => $user,
    'wallet_balance' => (float)$wallet['user_wallet_balance'],
    'direct_referrals_count' => $directCount,
    'matrix_node' => $matrixNode,
    'is_active_in_matrix' => ($matrixNode !== null),
    'recent_transactions' => $recentTransactions
]);
