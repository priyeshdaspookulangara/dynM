<?php
require_once __DIR__ . '/src/bootstrap.php';
use App\Services\AuthService;

$auth = new AuthService();
$auth->logout();

header('Location: /login.php');
exit;
