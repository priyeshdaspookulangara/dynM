<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class PackageService {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function getAllPackages(bool $activeOnly = false): array {
        $sql = "SELECT * FROM packages";
        if ($activeOnly) {
            $sql .= " WHERE status = 'active'";
        }
        $sql .= " ORDER BY price ASC";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    public function getPackageById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM packages WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function createPackage(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO packages (name, price, direct_referral_percent, direct_referral_type, direct_referral_fixed, wallet_user_split, wallet_company_split, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['name'],
            $data['price'],
            $data['direct_referral_percent'] ?? 0.00,
            $data['direct_referral_type'] ?? 'percent',
            $data['direct_referral_fixed'] ?? 0.00,
            $data['wallet_user_split'] ?? 60.00,
            $data['wallet_company_split'] ?? 40.00,
            $data['status'] ?? 'active'
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updatePackage(int $id, array $data): bool {
        $stmt = $this->db->prepare("
            UPDATE packages
            SET name = ?, price = ?, direct_referral_percent = ?, direct_referral_type = ?, direct_referral_fixed = ?, wallet_user_split = ?, wallet_company_split = ?, status = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['name'],
            $data['price'],
            $data['direct_referral_percent'],
            $data['direct_referral_type'],
            $data['direct_referral_fixed'],
            $data['wallet_user_split'],
            $data['wallet_company_split'],
            $data['status'],
            $id
        ]);
    }

    public function deletePackage(int $id): bool {
        // Soft delete or status change to inactive
        $stmt = $this->db->prepare("UPDATE packages SET status = 'inactive' WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
