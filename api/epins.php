<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Services\ApiService;
use App\Services\EpinService;
use App\Core\Database;

$user = ApiService::authenticateApiRequest();
$epinService = new EpinService();
$db = Database::getConnection();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $epins = $epinService->getEpinsForUser($user['id']);
    ApiService::sendJson([
        'success' => true,
        'epins' => $epins
    ]);
} elseif ($method === 'POST') {
    $input = ApiService::getJsonInput();
    $action = $input['action'] ?? 'transfer';

    if ($action === 'transfer') {
        $epinId = (int)($input['epin_id'] ?? 0);
        $targetUsername = trim($input['target_username'] ?? '');

        if (!$epinId || empty($targetUsername)) {
            ApiService::sendJson(['success' => false, 'message' => 'ePIN ID and target username are required.'], 400);
        }

        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$targetUsername]);
        $targetUser = $stmt->fetch();

        if (!$targetUser) {
            ApiService::sendJson(['success' => false, 'message' => "Recipient username '{$targetUsername}' not found."], 404);
        }

        if ($epinService->assignEpin($epinId, (int)$targetUser['id'])) {
            ApiService::sendJson(['success' => true, 'message' => "ePIN successfully transferred to {$targetUsername}!"]);
        } else {
            ApiService::sendJson(['success' => false, 'message' => 'Failed to transfer ePIN. It may be used or revoked.'], 400);
        }
    } else {
        ApiService::sendJson(['success' => false, 'message' => 'Invalid action.'], 400);
    }
}
