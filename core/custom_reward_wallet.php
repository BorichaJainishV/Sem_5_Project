<?php
// ---------------------------------------------------------------------
// core/custom_reward_wallet.php - Designer reward wallet helpers
// ---------------------------------------------------------------------

require_once __DIR__ . '/drop_promotions.php';

if (!function_exists('custom_reward_ensure_table')) {
    function custom_reward_ensure_table(mysqli $conn): bool
    {
        static $ensured = null;
        if ($ensured === true) {
            return true;
        }
        if ($ensured === false) {
            return false;
        }

        $createSql = "CREATE TABLE IF NOT EXISTS designer_reward_wallet (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            designer_id INT UNSIGNED NOT NULL,
            buyer_id INT UNSIGNED NOT NULL,
            order_id INT UNSIGNED NOT NULL,
            design_id INT UNSIGNED NOT NULL,
            drop_slug VARCHAR(120) DEFAULT NULL,
            reward_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status ENUM('available','redeemed','expired') NOT NULL DEFAULT 'available',
            granted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            redeemed_at TIMESTAMP NULL DEFAULT NULL,
            notes VARCHAR(255) DEFAULT NULL,
            UNIQUE KEY uniq_order_design (order_id, design_id),
            KEY idx_designer_status (designer_id, status),
            KEY idx_drop_slug (drop_slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

        try {
            $conn->query($createSql);
        } catch (mysqli_sql_exception $exception) {
            drop_promotion_log('reward_table_failed', [
                'message' => $exception->getMessage(),
            ]);
            $ensured = false;

            return false;
        }

        $ensured = true;

        return true;
    }
}

if (!function_exists('custom_reward_grant')) {
    function custom_reward_grant(
        mysqli $conn,
        int $designerId,
        int $buyerId,
        int $orderId,
        int $designId,
        float $amount,
        string $dropSlug
    ): bool {
        if ($designerId <= 0 || $buyerId <= 0 || $orderId <= 0 || $designId <= 0) {
            return false;
        }

        if ($designerId === $buyerId) {
            return false;
        }

        $amount = round(max(0.0, $amount), 2);
        if ($amount <= 0.0) {
            return false;
        }

        if (!custom_reward_ensure_table($conn)) {
            return false;
        }

        $stmt = $conn->prepare(
            'INSERT INTO designer_reward_wallet (designer_id, buyer_id, order_id, design_id, drop_slug, reward_amount) VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE reward_amount = VALUES(reward_amount), status = "available", notes = NULL'
        );
        if (!$stmt) {
            drop_promotion_log('reward_grant_failed', [
                'designer_id' => $designerId,
                'order_id' => $orderId,
                'design_id' => $designId,
                'reason' => 'statement_prepare',
            ]);

            return false;
        }

        $stmt->bind_param('iiiisd', $designerId, $buyerId, $orderId, $designId, $dropSlug, $amount);
        $success = $stmt->execute();
        if (!$success) {
            drop_promotion_log('reward_grant_failed', [
                'designer_id' => $designerId,
                'order_id' => $orderId,
                'design_id' => $designId,
                'reason' => 'statement_execute',
            ]);
        } else {
            drop_promotion_log('reward_granted', [
                'designer_id' => $designerId,
                'order_id' => $orderId,
                'design_id' => $designId,
                'drop_slug' => $dropSlug,
                'amount' => $amount,
            ]);
        }

        $stmt->close();

        return $success;
    }
}

if (!function_exists('custom_reward_fetch_wallet')) {
    function custom_reward_fetch_wallet(mysqli $conn, int $designerId, int $limit = 10): array
    {
        $summary = [
            'total_available' => 0.0,
            'total_redeemed' => 0.0,
            'total_expired' => 0.0,
            'entries' => [],
        ];

        if ($designerId <= 0) {
            return $summary;
        }

        if (!custom_reward_ensure_table($conn)) {
            return $summary;
        }

        $aggregateStmt = $conn->prepare('SELECT status, SUM(reward_amount) AS total FROM designer_reward_wallet WHERE designer_id = ? GROUP BY status');
        if ($aggregateStmt) {
            $aggregateStmt->bind_param('i', $designerId);
            if ($aggregateStmt->execute()) {
                $aggregateResult = $aggregateStmt->get_result();
                if ($aggregateResult instanceof mysqli_result) {
                    while ($row = $aggregateResult->fetch_assoc()) {
                        $status = strtolower((string) ($row['status'] ?? ''));
                        $total = isset($row['total']) ? (float) $row['total'] : 0.0;
                        if ($status === 'available') {
                            $summary['total_available'] = round($total, 2);
                        } elseif ($status === 'redeemed') {
                            $summary['total_redeemed'] = round($total, 2);
                        } elseif ($status === 'expired') {
                            $summary['total_expired'] = round($total, 2);
                        }
                    }
                    $aggregateResult->free();
                }
            }
            $aggregateStmt->close();
        }

        $limit = max(1, min($limit, 50));
        $listStmt = $conn->prepare('SELECT id, buyer_id, order_id, design_id, drop_slug, reward_amount, status, granted_at, redeemed_at, notes FROM designer_reward_wallet WHERE designer_id = ? ORDER BY granted_at DESC LIMIT ?');
        if ($listStmt) {
            $listStmt->bind_param('ii', $designerId, $limit);
            if ($listStmt->execute()) {
                $listResult = $listStmt->get_result();
                if ($listResult instanceof mysqli_result) {
                    $entries = $listResult->fetch_all(MYSQLI_ASSOC);
                    $summary['entries'] = array_map(static function (array $entry): array {
                        $entry['reward_amount'] = isset($entry['reward_amount']) ? round((float) $entry['reward_amount'], 2) : 0.0;
                        $entry['status'] = strtolower((string) ($entry['status'] ?? 'available'));
                        return $entry;
                    }, $entries);
                    $listResult->free();
                }
            }
            $listStmt->close();
        }

        return $summary;
    }
}

if (!function_exists('custom_reward_auto_apply')) {
    function custom_reward_auto_apply(mysqli $conn, int $designerId, float $orderTotal, array $orderRefs = []): array
    {
        $result = [
            'applied' => 0.0,
            'entries' => [],
        ];

        if ($designerId <= 0 || $orderTotal <= 0.0) {
            return $result;
        }

        if (!custom_reward_ensure_table($conn)) {
            return $result;
        }

        $orderLabel = !empty($orderRefs)
            ? implode(',', array_map(static fn($value) => (string) (int) $value, $orderRefs))
            : 'pending';

        $selectStmt = $conn->prepare('SELECT id, reward_amount FROM designer_reward_wallet WHERE designer_id = ? AND status = "available" ORDER BY granted_at ASC FOR UPDATE');
        if (!$selectStmt) {
            drop_promotion_log('reward_auto_apply_failed', [
                'designer_id' => $designerId,
                'reason' => 'select_prepare',
            ]);

            return $result;
        }

        $selectStmt->bind_param('i', $designerId);
        if (!$selectStmt->execute()) {
            drop_promotion_log('reward_auto_apply_failed', [
                'designer_id' => $designerId,
                'reason' => 'select_execute',
            ]);
            $selectStmt->close();

            return $result;
        }

        $selectResult = $selectStmt->get_result();
        if (!$selectResult instanceof mysqli_result) {
            $selectStmt->close();
            return $result;
        }

        $rows = $selectResult->fetch_all(MYSQLI_ASSOC);
        $selectResult->free();
        $selectStmt->close();

        if (empty($rows)) {
            return $result;
        }

        $remaining = round($orderTotal, 2);

        $fullStmt = $conn->prepare('UPDATE designer_reward_wallet SET status = "redeemed", redeemed_at = CURRENT_TIMESTAMP, notes = CASE WHEN notes IS NULL OR notes = "" THEN ? ELSE CONCAT(?, " | ", notes) END WHERE id = ?');
        $partialStmt = $conn->prepare('UPDATE designer_reward_wallet SET reward_amount = ?, notes = CASE WHEN notes IS NULL OR notes = "" THEN ? ELSE CONCAT(?, " | ", notes) END WHERE id = ?');

        if (!$fullStmt || !$partialStmt) {
            if ($fullStmt) {
                $fullStmt->close();
            }
            if ($partialStmt) {
                $partialStmt->close();
            }
            drop_promotion_log('reward_auto_apply_failed', [
                'designer_id' => $designerId,
                'reason' => 'update_prepare',
            ]);

            return $result;
        }

        foreach ($rows as $row) {
            if ($remaining <= 0.0) {
                break;
            }

            $rowId = (int) ($row['id'] ?? 0);
            $rewardAmount = isset($row['reward_amount']) ? round((float) $row['reward_amount'], 2) : 0.0;
            if ($rowId <= 0 || $rewardAmount <= 0.0) {
                continue;
            }

            $apply = min($remaining, $rewardAmount);
            if ($apply <= 0.0) {
                continue;
            }

            $remaining = round($remaining - $apply, 2);
            $isFullRedemption = $apply >= $rewardAmount - 0.005;
            $note = $isFullRedemption
                ? sprintf('Auto-applied INR %.2f against orders %s', $apply, $orderLabel)
                : sprintf('Auto-applied INR %.2f; INR %.2f remaining for future orders (%s)', $apply, max(0.0, $rewardAmount - $apply), $orderLabel);

            if ($isFullRedemption) {
                $fullStmt->bind_param('ssi', $note, $note, $rowId);
                $fullStmt->execute();
            } else {
                $newAmount = round($rewardAmount - $apply, 2);
                $partialStmt->bind_param('dssi', $newAmount, $note, $note, $rowId);
                $partialStmt->execute();
            }

            $result['applied'] += $apply;
            $result['entries'][] = [
                'id' => $rowId,
                'applied' => round($apply, 2),
                'remaining' => $isFullRedemption ? 0.0 : round($rewardAmount - $apply, 2),
                'status' => $isFullRedemption ? 'redeemed' : 'available',
            ];

            drop_promotion_log('reward_auto_applied', [
                'designer_id' => $designerId,
                'wallet_id' => $rowId,
                'applied' => round($apply, 2),
                'remaining' => $isFullRedemption ? 0.0 : round($rewardAmount - $apply, 2),
                'orders' => $orderLabel,
                'full' => $isFullRedemption,
            ]);

            if ($remaining <= 0.0) {
                break;
            }
        }

        $fullStmt->close();
        $partialStmt->close();

        $result['applied'] = round($result['applied'], 2);

        return $result;
    }
}
?>
