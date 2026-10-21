<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Services\ApiService;
use App\Services\PackageService;

$pkgService = new PackageService();
$packages = $pkgService->getAllPackages(true);

ApiService::sendJson([
    'success' => true,
    'packages' => $packages
]);
