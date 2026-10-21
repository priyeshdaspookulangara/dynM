<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Services\ApiService;
use App\Services\WalletService;

$user = ApiService::authenticateApiRequest();
$input = ApiService::getJsonInput();

$amount = (float)($input['amount'] ?? 0);
if ($amount <= 0) {
    ApiService::sendJson(['success' => false, 'message' => 'Valid payout amount is required.'], 400);
}

$walletService = new WalletService();
$result = $walletService->requestPayout($user['id'], $amount);

if ($result['success']) {
    ApiService::sendJson(['success' => true, 'message' => $result['message']]);
} else {
    ApiService::sendJson(['success' => false, 'message' => $result['message']], 400);
}
