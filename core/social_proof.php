<?php
// ---------------------------------------------------------------------
// core/social_proof.php - Helpers for storefront social proof surfaces
// ---------------------------------------------------------------------

if (!function_exists('get_recent_compliments_for_social_proof')) {
    /**
     * Fetch recent positive compliments to display on storefront surfaces.
     */
    function get_recent_compliments_for_social_proof(mysqli $conn, int $limit = 6): array
    {
        static $cache = [];
        $limit = max(1, $limit);

        if (isset($cache[$limit])) {
            return $cache[$limit];
        }

        $tableCheck = $conn->query("SHOW TABLES LIKE 'customer_feedback'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            if ($tableCheck) {
                $tableCheck->free();
            }
            $cache[$limit] = [];
            return $cache[$limit];
        }
        $tableCheck->free();

        $sql = "
            SELECT
                cf.feedback_text,
                cf.rating,
                cf.created_at,
                COALESCE(NULLIF(c.name, ''), 'Mystic Customer') AS customer_name,
                COALESCE(NULLIF(i.product_name, ''), 'Custom Apparel') AS product_name
            FROM customer_feedback cf
            INNER JOIN orders o ON o.order_id = cf.order_id
            LEFT JOIN customer c ON c.customer_id = o.customer_id
            LEFT JOIN inventory i ON i.inventory_id = o.inventory_id
            WHERE cf.rating >= 4
            ORDER BY cf.created_at DESC
            LIMIT ?
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $cache[$limit] = [];
            return $cache[$limit];
        }

        $stmt->bind_param('i', $limit);
        if (!$stmt->execute()) {
            $stmt->close();
            $cache[$limit] = [];
            return $cache[$limit];
        }

        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        if ($result) {
            $result->free();
        }
        $stmt->close();

        $formatted = [];
        foreach ($rows as $row) {
            $text = trim((string)($row['feedback_text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $formatted[] = [
                'quote' => $text,
                'rating' => (int)($row['rating'] ?? 5),
                'customer' => $row['customer_name'] ?? 'Mystic Customer',
                'product' => $row['product_name'] ?? 'Custom Apparel',
                'date' => !empty($row['created_at']) ? date('M j', strtotime($row['created_at'])) : date('M j'),
            ];
        }

        $cache[$limit] = array_slice($formatted, 0, $limit);
        return $cache[$limit];
    }
}