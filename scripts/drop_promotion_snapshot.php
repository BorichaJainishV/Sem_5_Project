<?php
declare(strict_types=1);

$rootPath = dirname(__DIR__);
require_once $rootPath . '/core/drop_promotions.php';

$options = getopt('', ['expect-slug::', 'expect-status::']);
$expectSlug = isset($options['expect-slug']) ? trim((string) $options['expect-slug']) : null;
$expectStatus = isset($options['expect-status']) ? trim((string) $options['expect-status']) : null;

$status = drop_promotion_current_status();
$currentStatus = $status['status'] ?? 'unknown';
$state = isset($status['state']) && is_array($status['state']) ? $status['state'] : [];

$report = [
    'status' => $currentStatus,
    'slug' => $state['active_slug'] ?? null,
    'type' => $state['promotion_type'] ?? null,
    'features' => $state['promotion_features'] ?? [],
    'activated_at' => $state['activated_at'] ?? null,
    'manual_suspend_hash' => $state['manual_suspend_hash'] ?? null,
    'updated_at' => $state['updated_at'] ?? null,
];

$pretty = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($pretty !== false) {
    echo $pretty . PHP_EOL;
}

$exitCode = 0;
if ($expectStatus !== null && strtolower($expectStatus) !== strtolower((string) $currentStatus)) {
    fwrite(STDERR, sprintf("Expected status %s but found %s%s", $expectStatus, $currentStatus, PHP_EOL));
    $exitCode = 1;
}

if ($expectSlug !== null) {
    $actualSlug = (string) ($report['slug'] ?? '');
    if ($actualSlug !== $expectSlug) {
        fwrite(STDERR, sprintf("Expected slug %s but found %s%s", $expectSlug, $actualSlug, PHP_EOL));
        $exitCode = 1;
    }
}

exit($exitCode);
