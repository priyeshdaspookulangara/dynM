<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Services\AuthService;
use App\Services\CompensationEngine;
use App\Services\ApiService;

$input = ApiService::getJsonInput();

$username = trim($input['username'] ?? '');
$email = trim($input['email'] ?? '');
$fullName = trim($input['full_name'] ?? '');
$password = $input['password'] ?? '';
$sponsorId = !empty($input['sponsor_id']) ? (int)$input['sponsor_id'] : null;
$epinCode = trim($input['epin_code'] ?? '');

if (empty($username) || empty($email) || empty($password) || empty($fullName)) {
    ApiService::sendJson(['success' => false, 'message' => 'Username, Email, Full Name, and Password are required.'], 400);
}

$auth = new AuthService();
$result = $auth->registerUser($username, $email, $fullName, $password, $sponsorId);

if (!$result['success']) {
    ApiService::sendJson(['success' => false, 'message' => $result['message']], 400);
}

$newUserId = $result['user_id'];
$activationStatus = null;

if (!empty($epinCode)) {
    $engine = new CompensationEngine();
    $actRes = $engine->activateUserWithEpin($newUserId, $epinCode);
    $activationStatus = $actRes;
}

ApiService::sendJson([
    'success' => true,
    'message' => 'User registered successfully!',
    'user_id' => $newUserId,
    'activation' => $activationStatus
], 201);
