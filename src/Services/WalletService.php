<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class WalletService {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function getWallet(int $userId): array {
        $stmt = $this->db->prepare("SELECT * FROM wallets WHERE user_id = ?");
        $stmt->execute([$userId]);
        $wallet = $stmt->fetch();

        if (!$wallet) {
            $this->db->prepare("INSERT INTO wallets (user_id, user_wallet_balance, company_wallet_balance) VALUES (?, 0.00, 0.00)")
                ->execute([$userId]);
            return [
                'user_id' => $userId,
                'user_wallet_balance' => 0.00,
                'company_wallet_balance' => 0.00
            ];
        }

        return $wallet;
    }

    public function creditWallet(int $userId, float $amount, string $category, string $walletType = 'user', ?string $description = null, ?string $referenceId = null): bool {
        if ($amount <= 0) return false;

        $column = ($walletType === 'company') ? 'company_wallet_balance' : 'user_wallet_balance';

        $stmt = $this->db->prepare("UPDATE wallets SET {$column} = {$column} + ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
        $stmt->execute([$amount, $userId]);

        $stmtTx = $this->db->prepare("
            INSERT INTO transactions (user_id, amount, type, category, wallet_type, description, reference_id)
            VALUES (?, ?, 'credit', ?, ?, ?, ?)
        ");
        return $stmtTx->execute([$userId, $amount, $category, $walletType, $description, $referenceId]);
    }

    public function debitWallet(int $userId, float $amount, string $category, string $walletType = 'user', ?string $description = null, ?string $referenceId = null): bool {
        if ($amount <= 0) return false;

        $wallet = $this->getWallet($userId);
        $currentBalance = ($walletType === 'company') ? (float)$wallet['company_wallet_balance'] : (float)$wallet['user_wallet_balance'];

        if ($currentBalance < $amount) {
            return false; // Insufficient funds
        }

        $column = ($walletType === 'company') ? 'company_wallet_balance' : 'user_wallet_balance';

        $stmt = $this->db->prepare("UPDATE wallets SET {$column} = {$column} - ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
        $stmt->execute([$amount, $userId]);

        $stmtTx = $this->db->prepare("
            INSERT INTO transactions (user_id, amount, type, category, wallet_type, description, reference_id)
            VALUES (?, ?, 'debit', ?, ?, ?, ?)
        ");
        return $stmtTx->execute([$userId, $amount, $category, $walletType, $description, $referenceId]);
    }

    public function getTransactions(int $userId, ?string $walletType = null): array {
        $sql = "SELECT * FROM transactions WHERE user_id = ?";
        $params = [$userId];

        if ($walletType) {
            $sql .= " AND wallet_type = ?";
            $params[] = $walletType;
        }

        $sql .= " ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getAllTransactions(): array {
        $stmt = $this->db->query("
            SELECT t.*, u.username, u.full_name
            FROM transactions t
            JOIN users u ON t.user_id = u.id
            ORDER BY t.created_at DESC
        ");
        return $stmt->fetchAll();
    }

    public function requestPayout(int $userId, float $amount): array {
        $settingsService = new SettingsService();
        $minPayout = (float)$settingsService->get('min_payout_amount', 100);
        $maxPayout = (float)$settingsService->get('max_payout_amount', 50000);

        if ($amount < $minPayout) {
            return ['success' => false, 'message' => "Minimum payout amount is ₹{$minPayout}"];
        }

        if ($amount > $maxPayout) {
            return ['success' => false, 'message' => "Maximum payout amount per request is ₹{$maxPayout}"];
        }

        $wallet = $this->getWallet($userId);
        if ((float)$wallet['user_wallet_balance'] < $amount) {
            return ['success' => false, 'message' => "Insufficient balance in withdrawable user wallet."];
        }

        $this->db->beginTransaction();
        try {
            // Debit user wallet
            $debitSuccess = $this->debitWallet($userId, $amount, 'payout_request', 'user', "Payout Request Created", null);
            if (!$debitSuccess) {
                $this->db->rollBack();
                return ['success' => false, 'message' => "Failed to debit user wallet."];
            }

            // Create payout record
            $stmt = $this->db->prepare("INSERT INTO payouts (user_id, amount, status) VALUES (?, ?, 'pending')");
            $stmt->execute([$userId, $amount]);

            $this->db->commit();
            return ['success' => true, 'message' => "Payout request submitted successfully."];
        } catch (\Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => "Error submitting payout: " . $e->getMessage()];
        }
    }

    public function processPayout(int $payoutId, string $action, ?string $notes = null): bool {
        $stmt = $this->db->prepare("SELECT * FROM payouts WHERE id = ? AND status = 'pending'");
        $stmt->execute([$payoutId]);
        $payout = $stmt->fetch();

        if (!$payout) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            if ($action === 'approve') {
                $stmtUp = $this->db->prepare("UPDATE payouts SET status = 'approved', notes = ?, processed_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmtUp->execute([$notes, $payoutId]);
            } elseif ($action === 'reject') {
                // Refund money back to user's wallet
                $this->creditWallet($payout['user_id'], (float)$payout['amount'], 'payout_refund', 'user', "Payout Request Rejected / Refunded: " . $notes, "PAYOUT-{$payoutId}");
                $stmtUp = $this->db->prepare("UPDATE payouts SET status = 'rejected', notes = ?, processed_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmtUp->execute([$notes, $payoutId]);
            }
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function getPayoutRequests(?int $userId = null): array {
        $sql = "SELECT p.*, u.username, u.full_name, u.email FROM payouts p JOIN users u ON p.user_id = u.id";
        $params = [];

        if ($userId) {
            $sql .= " WHERE p.user_id = ?";
            $params[] = $userId;
        }

        $sql .= " ORDER BY p.requested_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
