<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class CompensationEngine {
    private PDO $db;
    private SettingsService $settingsService;
    private WalletService $walletService;
    private EpinService $epinService;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->settingsService = new SettingsService();
        $this->walletService = new WalletService();
        $this->epinService = new EpinService();
    }

    /**
     * Activate a user using an ePIN and trigger matrix placement and compensation distributions.
     */
    public function activateUserWithEpin(int $userId, string $epinCode): array {
        $this->db->beginTransaction();

        try {
            // 1. Fetch & Validate ePIN
            $epin = $this->epinService->getEpinByCode($epinCode);
            if (!$epin) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Invalid ePIN code.'];
            }

            if ($epin['status'] !== 'unused') {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'ePIN has already been used or revoked.'];
            }

            // Check if user is assigned to this ePIN (if assigned_to_user_id is set, it must match)
            if ($epin['assigned_to_user_id'] && (int)$epin['assigned_to_user_id'] !== $userId) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'This ePIN is assigned to another user.'];
            }

            // Fetch user
            $stmtUser = $this->db->prepare("SELECT * FROM users WHERE id = ?");
            $stmtUser->execute([$userId]);
            $user = $stmtUser->fetch();
            if (!$user) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'User not found.'];
            }

            // Check if user is already placed in matrix
            $stmtMatrix = $this->db->prepare("SELECT id FROM matrix_nodes WHERE user_id = ?");
            $stmtMatrix->execute([$userId]);
            if ($stmtMatrix->fetch()) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'User is already active in matrix.'];
            }

            // Mark ePIN as used
            $this->epinService->markAsUsed($epin['id'], $userId);

            // 2. Forced Matrix Placement via BFS Spillover Logic
            $matrixNode = $this->placeUserInMatrix($userId, $user['sponsor_id']);

            // 3. Process Direct Referral Bonus
            $this->processDirectReferralBonus($user, $epin);

            // 4. Process Multi-Level / Unilevel Payouts
            $this->processMultiLevelPayouts($user, $epin);

            $this->db->commit();
            return ['success' => true, 'message' => 'Account successfully activated!', 'matrix_node' => $matrixNode];
        } catch (\Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Activation Error: ' . $e->getMessage()];
        }
    }

    /**
     * Forced Matrix Placement using Breadth-First Search (BFS) spillover logic
     */
    public function placeUserInMatrix(int $userId, ?int $sponsorId): array {
        $matrixWidth = (int)$this->settingsService->get('matrix_width', 3);
        $maxDepth = (int)$this->settingsService->get('matrix_depth', 5);

        // Determine starting search root: sponsor's matrix node if available, else system root (ID 1)
        $startNodeUserId = $sponsorId;

        if ($startNodeUserId) {
            $stmt = $this->db->prepare("SELECT user_id, depth FROM matrix_nodes WHERE user_id = ?");
            $stmt->execute([$startNodeUserId]);
            $startNode = $stmt->fetch();
            if (!$startNode) {
                $startNodeUserId = $this->getRootMatrixUserId();
            }
        } else {
            $startNodeUserId = $this->getRootMatrixUserId();
        }

        // BFS Queue to find first node with available child slot (< matrixWidth)
        $queue = [$startNodeUserId];
        $targetParentId = null;
        $targetDepth = 1;

        while (!empty($queue)) {
            $currentUserId = array_shift($queue);

            // Get current node info
            $stmtNode = $this->db->prepare("SELECT user_id, depth FROM matrix_nodes WHERE user_id = ?");
            $stmtNode->execute([$currentUserId]);
            $currentNode = $stmtNode->fetch();

            if (!$currentNode) continue;

            // Check how many children current node has
            $stmtChildren = $this->db->prepare("SELECT user_id FROM matrix_nodes WHERE parent_id = ? ORDER BY position ASC");
            $stmtChildren->execute([$currentUserId]);
            $children = $stmtChildren->fetchAll();

            if (count($children) < $matrixWidth) {
                // Found parent node!
                $targetParentId = $currentUserId;
                $targetDepth = $currentNode['depth'] + 1;

                if ($targetDepth > $maxDepth) {
                    throw new \Exception("Matrix maximum depth limit ({$maxDepth}) reached.");
                }
                break;
            } else {
                // Push children to queue for next level BFS search
                foreach ($children as $child) {
                    $queue[] = $child['user_id'];
                }
            }
        }

        if ($targetParentId === null) {
            // Fallback to top root
            $targetParentId = $this->getRootMatrixUserId();
            $targetDepth = 1;
        }

        // Determine child position under parent (1 to matrixWidth)
        $stmtCount = $this->db->prepare("SELECT COUNT(*) as cnt FROM matrix_nodes WHERE parent_id = ?");
        $stmtCount->execute([$targetParentId]);
        $position = ((int)$stmtCount->fetch()['cnt']) + 1;

        // Insert new matrix node
        $stmtInsert = $this->db->prepare("
            INSERT INTO matrix_nodes (user_id, parent_id, sponsor_id, position, depth)
            VALUES (?, ?, ?, ?, ?)
        ");
        $effectiveSponsorId = $sponsorId ?: $targetParentId;
        $stmtInsert->execute([$userId, $targetParentId, $effectiveSponsorId, $position, $targetDepth]);

        return [
            'user_id' => $userId,
            'parent_id' => $targetParentId,
            'sponsor_id' => $effectiveSponsorId,
            'position' => $position,
            'depth' => $targetDepth
        ];
    }

    private function getRootMatrixUserId(): int {
        $stmt = $this->db->query("SELECT user_id FROM matrix_nodes WHERE parent_id IS NULL LIMIT 1");
        $root = $stmt->fetch();
        if ($root) {
            return (int)$root['user_id'];
        }

        // Fallback: get first admin or super admin
        $stmtAdmin = $this->db->query("SELECT id FROM users WHERE role IN ('super_admin', 'admin') ORDER BY id ASC LIMIT 1");
        $admin = $stmtAdmin->fetch();
        return $admin ? (int)$admin['id'] : 1;
    }

    /**
     * Calculate and credit direct referral bonus with dynamic wallet splits
     */
    private function processDirectReferralBonus(array $user, array $epin): void {
        if (empty($user['sponsor_id'])) {
            return; // No sponsor to credit
        }

        $sponsorId = (int)$user['sponsor_id'];
        $packagePrice = (float)$epin['package_price'];

        $refType = $epin['direct_referral_type'] ?? 'percent';
        $refPercent = (float)($epin['direct_referral_percent'] ?? 0.00);
        $refFixed = (float)($epin['direct_referral_fixed'] ?? 0.00);

        $totalBonus = 0.00;
        if ($refType === 'percent') {
            $totalBonus = ($packagePrice * $refPercent) / 100.0;
        } else {
            $totalBonus = $refFixed;
        }

        if ($totalBonus <= 0) return;

        // Get Split Ratios
        $userSplitPercent = (float)($epin['wallet_user_split'] ?? $this->settingsService->get('default_user_wallet_split', 60));
        $companySplitPercent = (float)($epin['wallet_company_split'] ?? $this->settingsService->get('default_company_wallet_split', 40));

        $userShare = round(($totalBonus * $userSplitPercent) / 100.0, 2);
        $companyShare = round(($totalBonus * $companySplitPercent) / 100.0, 2);

        $companyAccountId = (int)$this->settingsService->get('company_wallet_user_id', 1);

        // Credit Sponsor User Wallet
        if ($userShare > 0) {
            $this->walletService->creditWallet(
                $sponsorId,
                $userShare,
                'direct_referral',
                'user',
                "Direct Referral Bonus for user {$user['username']} ({$epin['package_name']})",
                "REF-{$user['id']}"
            );
        }

        // Credit Company Wallet
        if ($companyShare > 0) {
            $this->walletService->creditWallet(
                $companyAccountId,
                $companyShare,
                'company_retention',
                'company',
                "Company retention share from Direct Referral Bonus ({$user['username']})",
                "COMP-REF-{$user['id']}"
            );
        }
    }

    /**
     * Process multi-level / unilevel level-wise payouts dynamically
     */
    private function processMultiLevelPayouts(array $user, array $epin): void {
        $levelCommissions = $this->settingsService->getLevelCommissions();
        if (empty($levelCommissions)) return;

        $packagePrice = (float)$epin['package_price'];
        $userSplitPercent = (float)($epin['wallet_user_split'] ?? $this->settingsService->get('default_user_wallet_split', 60));
        $companySplitPercent = (float)($epin['wallet_company_split'] ?? $this->settingsService->get('default_company_wallet_split', 40));
        $companyAccountId = (int)$this->settingsService->get('company_wallet_user_id', 1);

        $currentUserId = $user['id'];

        foreach ($levelCommissions as $lvl) {
            $levelNum = (int)$lvl['level_number'];
            $commissionPercent = (float)$lvl['commission_percent'];

            if ($commissionPercent <= 0) continue;

            // Fetch Upline Sponsor
            $stmtSponsor = $this->db->prepare("SELECT u.id, u.username, u.sponsor_id FROM users u WHERE u.id = ?");
            $stmtSponsor->execute([$currentUserId]);
            $currentUserObj = $stmtSponsor->fetch();

            if (!$currentUserObj || empty($currentUserObj['sponsor_id'])) {
                break; // Upline chain ends
            }

            $uplineId = (int)$currentUserObj['sponsor_id'];

            // Calculate level payout
            $totalPayout = round(($packagePrice * $commissionPercent) / 100.0, 2);
            $userShare = round(($totalPayout * $userSplitPercent) / 100.0, 2);
            $companyShare = round(($totalPayout * $companySplitPercent) / 100.0, 2);

            if ($userShare > 0) {
                $this->walletService->creditWallet(
                    $uplineId,
                    $userShare,
                    'matrix_level',
                    'user',
                    "Level {$levelNum} Income from registration of {$user['username']}",
                    "LVL{$levelNum}-{$user['id']}"
                );
            }

            if ($companyShare > 0) {
                $this->walletService->creditWallet(
                    $companyAccountId,
                    $companyShare,
                    'company_retention',
                    'company',
                    "Company retention share from Level {$levelNum} Payout ({$user['username']})",
                    "COMP-LVL{$levelNum}-{$user['id']}"
                );
            }

            // Move to next upline level
            $currentUserId = $uplineId;
        }
    }

    /**
     * Get Matrix Tree Data starting from root or specific user for visual tree display
     */
    public function getMatrixTree(?int $rootUserId = null, int $maxDepth = 3): array {
        if (!$rootUserId) {
            $rootUserId = $this->getRootMatrixUserId();
        }

        $stmt = $this->db->prepare("
            SELECT m.*, u.username, u.full_name, u.role, u.status, p.name as package_name
            FROM matrix_nodes m
            JOIN users u ON m.user_id = u.id
            LEFT JOIN epins e ON e.used_by_user_id = u.id
            LEFT JOIN packages p ON e.package_id = p.id
            WHERE m.user_id = ?
        ");
        $stmt->execute([$rootUserId]);
        $rootNode = $stmt->fetch();

        if (!$rootNode) return [];

        return $this->buildTreeRecursive($rootNode, 1, $maxDepth);
    }

    private function buildTreeRecursive(array $node, int $currentDepth, int $maxDepth): array {
        $nodeData = [
            'user_id' => $node['user_id'],
            'username' => $node['username'],
            'full_name' => $node['full_name'],
            'role' => $node['role'],
            'position' => $node['position'],
            'depth' => $node['depth'],
            'children' => []
        ];

        if ($currentDepth < $maxDepth) {
            $stmt = $this->db->prepare("
                SELECT m.*, u.username, u.full_name, u.role, u.status
                FROM matrix_nodes m
                JOIN users u ON m.user_id = u.id
                WHERE m.parent_id = ?
                ORDER BY m.position ASC
            ");
            $stmt->execute([$node['user_id']]);
            $children = $stmt->fetchAll();

            foreach ($children as $child) {
                $nodeData['children'][] = $this->buildTreeRecursive($child, $currentDepth + 1, $maxDepth);
            }
        }

        return $nodeData;
    }
}
