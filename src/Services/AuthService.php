<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class AuthService {
    private PDO $db;
    private SettingsService $settingsService;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->settingsService = new SettingsService();
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
    }

    public function registerUser(string $username, string $email, string $fullName, string $password, ?int $sponsorId = null): array {
        // Validate duplicates
        $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Username or Email already exists.'];
        }

        $encryptSetting = $this->settingsService->get('encrypt_passwords', '0');
        $storedPassword = ($encryptSetting === '1') ? password_hash($password, PASSWORD_BCRYPT) : $password;

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, password_hash, full_name, role, sponsor_id, status)
                VALUES (?, ?, ?, ?, 'user', ?, 'active')
            ");
            $stmt->execute([$username, $email, $storedPassword, $fullName, $sponsorId]);
            $userId = (int)$this->db->lastInsertId();

            // Initialize user wallet
            $stmtWallet = $this->db->prepare("INSERT INTO wallets (user_id, user_wallet_balance, company_wallet_balance) VALUES (?, 0.00, 0.00)");
            $stmtWallet->execute([$userId]);

            $this->db->commit();
            return ['success' => true, 'user_id' => $userId];
        } catch (\Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
        }
    }

    public function login(string $username, string $password): array {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }

        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => 'Account is suspended or inactive.'];
        }

        $encryptSetting = $this->settingsService->get('encrypt_passwords', '0');
        $isValid = false;

        if ($encryptSetting === '1') {
            $isValid = password_verify($password, $user['password_hash']) || ($password === $user['password_hash']);
        } else {
            // No encryption mode
            $isValid = ($password === $user['password_hash']) || password_verify($password, $user['password_hash']);
        }

        if (!$isValid) {
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];

        return ['success' => true, 'user' => $user];
    }

    public function logout(): void {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['full_name'], $_SESSION['role']);
        session_destroy();
    }

    public static function getCurrentUser(): ?array {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, username, email, full_name, role, sponsor_id, status, created_at FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    }

    public static function requireLogin(): array {
        $user = self::getCurrentUser();
        if (!$user) {
            header("Location: /login.php");
            exit;
        }
        return $user;
    }

    public static function requireAdmin(): array {
        $user = self::requireLogin();
        if (!in_array($user['role'], ['admin', 'super_admin'])) {
            header("Location: /user/dashboard.php");
            exit;
        }
        return $user;
    }

    public static function requireSuperAdmin(): array {
        $user = self::requireLogin();
        if ($user['role'] !== 'super_admin') {
            header("Location: /admin/dashboard.php");
            exit;
        }
        return $user;
    }
}
