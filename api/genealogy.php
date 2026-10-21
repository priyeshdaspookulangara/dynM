<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Services\ApiService;
use App\Services\CompensationEngine;

$user = ApiService::authenticateApiRequest();
$engine = new CompensationEngine();

$depth = isset($_GET['depth']) ? (int)$_GET['depth'] : 4;
$tree = $engine->getMatrixTree($user['id'], $depth);

ApiService::sendJson([
    'success' => true,
    'genealogy_tree' => $tree
]);
