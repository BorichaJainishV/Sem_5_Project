<?php
require_once __DIR__ . '/../core/drop_promotions.php';

if (PHP_SAPI !== 'cli') {
    if (function_exists('http_response_code')) {
        http_response_code(403);
    }
    echo "This script is intended for CLI use only." . PHP_EOL;
    exit(1);
}

function reset_clearance_flags(): int
{
    $lockHandle = drop_promotion_acquire_lock();
    if ($lockHandle === false) {
        fwrite(STDERR, "Promotion system is currently locked. Try again later.\n");
        return 1;
    }

    try {
        $conn = drop_promotion_get_connection();
        if (!$conn) {
            fwrite(STDERR, "Unable to connect to database.\n");
            return 1;
        }

        $sql = "SELECT inventory_id, product_name FROM inventory WHERE is_clearance = 1 ORDER BY inventory_id ASC";
        $result = $conn->query($sql);
        if ($result === false) {
            fwrite(STDERR, "Failed to fetch clearance flags: " . $conn->error . "\n");
            return 1;
        }

        $flagged = [];
        while ($row = $result->fetch_assoc()) {
            $flagged[] = [
                'inventory_id' => (int) ($row['inventory_id'] ?? 0),
                'product_name' => (string) ($row['product_name'] ?? ''),
            ];
        }
        $result->free();

        if (empty($flagged)) {
            echo "No clearance flags detected.\n";
            return 0;
        }

        echo "Clearance flags detected for the following inventory: \n";
        foreach ($flagged as $entry) {
            echo '  #' . $entry['inventory_id'] . ' · ' . ($entry['product_name'] !== '' ? $entry['product_name'] : 'Unnamed product') . PHP_EOL;
        }

        echo "\nResetting clearance flags...\n";
        $inventoryIds = array_column($flagged, 'inventory_id');
        $resetResult = drop_promotion_force_clearance_reset($inventoryIds);

        $status = (string) ($resetResult['status'] ?? 'unknown');
        $updated = (int) ($resetResult['updated'] ?? 0);

        echo 'Status: ' . $status . PHP_EOL;
        if ($updated > 0) {
            echo 'Rows updated: ' . $updated . PHP_EOL;
        }

        if ($status !== 'reset') {
            $message = (string) ($resetResult['message'] ?? 'Unable to reset clearance flags.');
            fwrite(STDERR, $message . "\n");
            return 1;
        }

        drop_promotion_log('clearance_force_reset_cli', [
            'inventory_ids' => $inventoryIds,
            'updated' => $updated,
        ]);

        echo "Done.\n";

        return 0;
    } finally {
        drop_promotion_release_lock($lockHandle);
    }
}

exit(reset_clearance_flags());
