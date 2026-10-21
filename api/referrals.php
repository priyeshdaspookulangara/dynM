<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Services\ApiService;
use App\Core\Database;

$user = ApiService::authenticateApiRequest();
$db = Database::getConnection();

// Direct Referrals
$stmtDir = $db->prepare("
    SELECT u.id, u.username, u.full_name, u.email, u.created_at, m.depth, m.position
    FROM users u
    LEFT JOIN matrix_nodes m ON u.id = m.user_id
    WHERE u.sponsor_id = ?
    ORDER BY u.created_at DESC
");
$stmtDir->execute([$user['id']]);
$directReferrals = $stmtDir->fetchAll();

// Multi-level Matrix Downlines
function getDownlines(PDO $db, int $parentUserId, int $currentDepth = 1, int $maxDepth = 10): array {
    $results = [];
    if ($currentDepth > $maxDepth) return $results;

    $stmt = $db->prepare("
        SELECT m.user_id, m.position, m.depth, u.username, u.full_name, u.email, u.created_at, s.username as sponsor_name
        FROM matrix_nodes m
        JOIN users u ON m.user_id = u.id
        LEFT JOIN users s ON m.sponsor_id = s.id
        WHERE m.parent_id = ?
        ORDER BY m.position ASC
    ");
    $stmt->execute([$parentUserId]);
    $children = $stmt->fetchAll();

    foreach ($children as $child) {
        $child['level_relative'] = $currentDepth;
        $results[] = $child;
        $subDownlines = getDownlines($db, $child['user_id'], $currentDepth + 1, $maxDepth);
        $results = array_merge($results, $subDownlines);
    }

    return $results;
}

$downlines = getDownlines($db, $user['id']);

ApiService::sendJson([
    'success' => true,
    'direct_referrals' => $directReferrals,
    'matrix_downlines' => $downlines
]);
