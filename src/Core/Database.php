<?php

namespace App\Core;

use PDO;
use PDOException;
use Exception;

class Database {
    private static ?PDO $instance = null;

    public static function resetConnection(): void {
        self::$instance = null;
    }

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $config = require __DIR__ . '/../../config.php';
            $dbConfig = $config['db'];
            $driver = $dbConfig['driver'] ?? 'sqlite';

            try {
                if ($driver === 'sqlite') {
                    $dbPath = $dbConfig['sqlite']['path'];
                    $dir = dirname($dbPath);
                    if (!is_dir($dir)) {
                        mkdir($dir, 0777, true);
                    }
                    self::$instance = new PDO("sqlite:" . $dbPath);
                    self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                    self::$instance->exec("PRAGMA foreign_keys = ON;");
                } elseif ($driver === 'mysql') {
                    $mysql = $dbConfig['mysql'];
                    $dsn = "mysql:host={$mysql['host']};port={$mysql['port']};dbname={$mysql['dbname']};charset={$mysql['charset']}";
                    self::$instance = new PDO($dsn, $mysql['username'], $mysql['password']);
                    self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                } else {
                    throw new Exception("Unsupported database driver: " . $driver);
                }
            } catch (PDOException $e) {
                die("Database Connection Error: " . $e->getMessage());
            }
        }

        return self::$instance;
    }

    public static function initSchema(): void {
        $pdo = self::getConnection();
        $config = require __DIR__ . '/../../config.php';
        $driver = $config['db']['driver'] ?? 'sqlite';

        $schemaFile = __DIR__ . '/../../database/schema_' . $driver . '.sql';
        if (file_exists($schemaFile)) {
            $sql = file_get_contents($schemaFile);
            $pdo->exec($sql);
        }

        self::seedInitialData($pdo);
    }

    private static function seedInitialData(PDO $pdo): void {
        // Check if settings table is empty
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM settings");
        if ((int)$stmt->fetch()['cnt'] === 0) {
            $defaultSettings = [
                ['matrix_width', '3', 'Forced Matrix Width (e.g. 3 for 3xN)'],
                ['matrix_depth', '5', 'Forced Matrix Maximum Depth'],
                ['default_user_wallet_split', '60', 'Default Percentage to User Withdrawable Wallet'],
                ['default_company_wallet_split', '40', 'Default Percentage to Company Wallet'],
                ['company_wallet_user_id', '1', 'User ID representing Company Account'],
                ['min_payout_amount', '100', 'Minimum Withdrawable Amount (INR)'],
                ['max_payout_amount', '50000', 'Maximum Single Payout Request Amount (INR)'],
                ['allow_self_registration', '1', 'Allow direct user registration with ePIN'],
                ['encrypt_passwords', '0', 'Password Encryption Enabled (0 = Disabled/Plaintext, 1 = Enabled/BCrypt)']
            ];

            $insertStmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
            foreach ($defaultSettings as $s) {
                $insertStmt->execute($s);
            }
        }

        // Check if level_commissions table is empty
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM level_commissions");
        if ((int)$stmt->fetch()['cnt'] === 0) {
            $defaultLevels = [
                [1, 10.00], // Level 1: 10%
                [2, 5.00],  // Level 2: 5%
                [3, 3.00],  // Level 3: 3%
                [4, 2.00],  // Level 4: 2%
                [5, 1.00]   // Level 5: 1%
            ];
            $insertLevel = $pdo->prepare("INSERT INTO level_commissions (level_number, commission_percent) VALUES (?, ?)");
            foreach ($defaultLevels as $lvl) {
                $insertLevel->execute($lvl);
            }
        }

        // Check if users table is empty
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM users");
        if ((int)$stmt->fetch()['cnt'] === 0) {
            // Check default encryption setting
            $stmtEnc = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'encrypt_passwords'");
            $encVal = $stmtEnc ? $stmtEnc->fetch() : null;
            $useEncryption = ($encVal && $encVal['setting_value'] === '1');

            $adminPassword = $useEncryption ? password_hash('admin123', PASSWORD_BCRYPT) : 'admin123';

            // 1. Create Super Admin User
            $insertAdmin = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name, role, status) VALUES (?, ?, ?, ?, ?, ?)");
            $insertAdmin->execute(['superadmin', 'superadmin@mlm.com', $adminPassword, 'Super Admin', 'super_admin', 'active']);
            $superAdminId = (int)$pdo->lastInsertId();

            // 2. Create Regular Admin User
            $insertAdmin->execute(['admin', 'admin@mlm.com', $adminPassword, 'System Admin', 'admin', 'active']);
            $adminId = (int)$pdo->lastInsertId();

            // Create initial wallets
            $insertWallet = $pdo->prepare("INSERT INTO wallets (user_id, user_wallet_balance, company_wallet_balance) VALUES (?, 0.00, 0.00)");
            $insertWallet->execute([$superAdminId]);
            $insertWallet->execute([$adminId]);

            // Place Super Admin at top of matrix (root)
            $insertMatrix = $pdo->prepare("INSERT INTO matrix_nodes (user_id, parent_id, sponsor_id, position, depth) VALUES (?, NULL, ?, 1, 0)");
            $insertMatrix->execute([$superAdminId, $superAdminId]);
        }

        // Check if packages table is empty
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM packages");
        if ((int)$stmt->fetch()['cnt'] === 0) {
            $insertPkg = $pdo->prepare("INSERT INTO packages (name, price, direct_referral_percent, direct_referral_type, direct_referral_fixed, wallet_user_split, wallet_company_split, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $insertPkg->execute(['Foundation Tier', 1000.00, 10.00, 'percent', 0.00, 60.00, 40.00, 'active']);
            $insertPkg->execute(['Leadership Tier', 5000.00, 15.00, 'percent', 0.00, 70.00, 30.00, 'active']);
        }
    }
}
