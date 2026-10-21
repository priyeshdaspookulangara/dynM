<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class ApiService {
    public static function sendJson(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function getJsonInput(): array {
        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            return $_POST;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? array_merge($_POST, $data) : $_POST;
    }

    public static function authenticateApiRequest(): array {
        // Support Authorization Bearer Token or X-User-ID / X-API-Token header or session
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        $userIdHeader = $headers['X-User-ID'] ?? $headers['x-user-id'] ?? '';

        $userId = null;

        if (!empty($authHeader) && preg_match('/Bearer\s+(\d+)/i', $authHeader, $matches)) {
            $userId = (int)$matches[1];
        } elseif (!empty($userIdHeader)) {
            $userId = (int)$userIdHeader;
        } elseif (isset($_SESSION['user_id'])) {
            $userId = (int)$_SESSION['user_id'];
        }

        if (!$userId) {
            self::sendJson(['success' => false, 'message' => 'Unauthorized. Please provide a valid Authorization Bearer header or login session.'], 401);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, username, email, full_name, role, sponsor_id, status FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || $user['status'] !== 'active') {
            self::sendJson(['success' => false, 'message' => 'User account invalid or suspended.'], 403);
        }

        return $user;
    }
}
