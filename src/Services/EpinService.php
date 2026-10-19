<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class EpinService {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function generateEpins(int $packageId, int $quantity, int $createdById, ?int $assignToUserId = null): array {
        $generated = [];
        $stmt = $this->db->prepare("
            INSERT INTO epins (code, package_id, created_by_user_id, assigned_to_user_id, status)
            VALUES (?, ?, ?, ?, 'unused')
        ");

        for ($i = 0; $i < $quantity; $i++) {
            $code = $this->generateUniqueCode();
            $stmt->execute([$code, $packageId, $createdById, $assignToUserId]);
            $generated[] = [
                'id' => $this->db->lastInsertId(),
                'code' => $code,
                'package_id' => $packageId,
                'assigned_to_user_id' => $assignToUserId
            ];
        }

        return $generated;
    }

    private function generateUniqueCode(): string {
        do {
            $code = 'EPIN-' . strtoupper(bin2hex(random_bytes(4))) . '-' . rand(1000, 9999);
            $stmt = $this->db->prepare("SELECT id FROM epins WHERE code = ?");
            $stmt->execute([$code]);
        } while ($stmt->fetch());

        return $code;
    }

    public function getEpinByCode(string $code): ?array {
        $stmt = $this->db->prepare("
            SELECT e.*, p.name as package_name, p.price as package_price, p.direct_referral_percent,
                   p.direct_referral_type, p.direct_referral_fixed, p.wallet_user_split, p.wallet_company_split
            FROM epins e
            JOIN packages p ON e.package_id = p.id
            WHERE e.code = ?
        ");
        $stmt->execute([$code]);
        return $stmt->fetch() ?: null;
    }

    public function getEpinsForUser(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT e.*, p.name as package_name, p.price as package_price
            FROM epins e
            JOIN packages p ON e.package_id = p.id
            WHERE e.assigned_to_user_id = ? OR e.created_by_user_id = ?
            ORDER BY e.created_at DESC
        ");
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll();
    }

    public function getAllEpins(): array {
        $stmt = $this->db->query("
            SELECT e.*, p.name as package_name, p.price as package_price,
                   creator.username as creator_name,
                   assigned.username as assigned_name,
                   used_user.username as used_by_name
            FROM epins e
            JOIN packages p ON e.package_id = p.id
            JOIN users creator ON e.created_by_user_id = creator.id
            LEFT JOIN users assigned ON e.assigned_to_user_id = assigned.id
            LEFT JOIN users used_user ON e.used_by_user_id = used_user.id
            ORDER BY e.created_at DESC
        ");
        return $stmt->fetchAll();
    }

    public function assignEpin(int $epinId, int $targetUserId): bool {
        $stmt = $this->db->prepare("UPDATE epins SET assigned_to_user_id = ? WHERE id = ? AND status = 'unused'");
        return $stmt->execute([$targetUserId, $epinId]);
    }

    public function revokeEpin(int $epinId): bool {
        $stmt = $this->db->prepare("UPDATE epins SET status = 'revoked' WHERE id = ? AND status = 'unused'");
        return $stmt->execute([$epinId]);
    }

    public function markAsUsed(int $epinId, int $usedByUserId): bool {
        $stmt = $this->db->prepare("
            UPDATE epins
            SET status = 'used', used_by_user_id = ?, used_at = CURRENT_TIMESTAMP
            WHERE id = ? AND status = 'unused'
        ");
        return $stmt->execute([$usedByUserId, $epinId]);
    }
}
