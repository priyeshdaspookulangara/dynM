<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Services\ApiService;
use App\Services\WalletService;
use App\Services\SettingsService;

$user = ApiService::authenticateApiRequest();
$walletService = new WalletService();
$settingsService = new SettingsService();

$wallet = $walletService->getWallet($user['id']);
$transactions = $walletService->getTransactions($user['id']);
$payoutRequests = $walletService->getPayoutRequests($user['id']);

$minPayout = (float)$settingsService->get('min_payout_amount', 100);
$maxPayout = (float)$settingsService->get('max_payout_amount', 50000);

ApiService::sendJson([
    'success' => true,
    'wallet_balance' => (float)$wallet['user_wallet_balance'],
    'min_payout' => $minPayout,
    'max_payout' => $maxPayout,
    'transactions' => $transactions,
    'payout_requests' => $payoutRequests
]);
