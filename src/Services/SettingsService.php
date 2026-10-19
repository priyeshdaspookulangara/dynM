<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class SettingsService {
    private PDO $db;
    private static ?array $cache = null;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function getAllSettings(): array {
        $stmt = $this->db->query("SELECT setting_key, setting_value, description FROM settings");
        $results = $stmt->fetchAll();
        $settings = [];
        foreach ($results as $row) {
            $settings[$row['setting_key']] = [
                'value' => $row['setting_value'],
                'description' => $row['description']
            ];
        }
        return $settings;
    }

    public function get(string $key, $default = null) {
        if (self::$cache === null) {
            $stmt = $this->db->query("SELECT setting_key, setting_value FROM settings");
            self::$cache = [];
            while ($row = $stmt->fetch()) {
                self::$cache[$row['setting_key']] = $row['setting_value'];
            }
        }
        return self::$cache[$key] ?? $default;
    }

    public function set(string $key, string $value, ?string $description = null): void {
        $stmt = $this->db->prepare("
            INSERT INTO settings (setting_key, setting_value, description)
            VALUES (?, ?, ?)
            ON CONFLICT(setting_key) DO UPDATE SET
                setting_value = EXCLUDED.setting_value,
                description = COALESCE(EXCLUDED.description, settings.description),
                updated_at = CURRENT_TIMESTAMP
        ");

        // Note: For MySQL compatibility if switching driver:
        $config = require __DIR__ . '/../../config.php';
        if (($config['db']['driver'] ?? 'sqlite') === 'mysql') {
            $stmt = $this->db->prepare("
                INSERT INTO settings (setting_key, setting_value, description)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    setting_value = VALUES(setting_value),
                    description = COALESCE(VALUES(description), description)
            ");
        }

        $stmt->execute([$key, $value, $description]);
        self::$cache = null; // Clear cache
    }

    public function getLevelCommissions(): array {
        $stmt = $this->db->query("SELECT level_number, commission_percent FROM level_commissions ORDER BY level_number ASC");
        return $stmt->fetchAll();
    }

    public function updateLevelCommissions(array $levelCommissions): void {
        $this->db->beginTransaction();
        try {
            $this->db->exec("DELETE FROM level_commissions");
            $stmt = $this->db->prepare("INSERT INTO level_commissions (level_number, commission_percent) VALUES (?, ?)");
            foreach ($levelCommissions as $level => $percent) {
                $stmt->execute([(int)$level, (float)$percent]);
            }
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
