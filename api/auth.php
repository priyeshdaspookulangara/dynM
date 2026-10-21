<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Services\AuthService;
use App\Services\ApiService;

$input = ApiService::getJsonInput();
$action = $_GET['action'] ?? 'login';

$auth = new AuthService();

if ($action === 'login') {
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($username) || empty($password)) {
        ApiService::sendJson(['success' => false, 'message' => 'Username and password are required.'], 400);
    }

    $result = $auth->login($username, $password);
    if ($result['success']) {
        $user = $result['user'];
        unset($user['password_hash']);
        ApiService::sendJson([
            'success' => true,
            'message' => 'Login successful',
            'token' => (string)$user['id'], // Bearer token for mobile requests
            'user' => $user
        ]);
    } else {
        ApiService::sendJson(['success' => false, 'message' => $result['message']], 401);
    }
} elseif ($action === 'me') {
    $user = ApiService::authenticateApiRequest();
    ApiService::sendJson(['success' => true, 'user' => $user]);
} else {
    ApiService::sendJson(['success' => false, 'message' => 'Invalid endpoint action.'], 404);
}
