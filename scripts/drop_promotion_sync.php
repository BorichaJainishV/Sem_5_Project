<?php
// ---------------------------------------------------------------------
// scripts/drop_promotion_sync.php - CLI helper to reconcile drop promotions
// ---------------------------------------------------------------------

$root = dirname(__DIR__);
require_once $root . '/core/drop_promotions.php';

$argv = $_SERVER['argv'] ?? [];
$force = in_array('--force', $argv, true) || in_array('-f', $argv, true);
$jsonOutput = in_array('--json', $argv, true);

$result = drop_promotion_sync($force);

if ($jsonOutput) {
    echo json_encode([
        'timestamp' => date(DateTimeInterface::ATOM),
        'status' => $result['status'] ?? 'unknown',
        'message' => $result['message'] ?? null,
        'errors' => $result['errors'] ?? null,
        'results' => $result['results'] ?? null,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
    $status = strtoupper((string) ($result['status'] ?? 'unknown'));
    echo '[' . date('Y-m-d H:i:s') . "] Drop promotion sync => $status" . PHP_EOL;
    if (!empty($result['message'])) {
        echo '  message: ' . $result['message'] . PHP_EOL;
    }
    if (!empty($result['errors']) && is_array($result['errors'])) {
        foreach ($result['errors'] as $step => $errorMessage) {
            echo '  ' . $step . ': ' . $errorMessage . PHP_EOL;
        }
    }
}

exit(($result['status'] ?? '') === 'error' ? 1 : 0);
