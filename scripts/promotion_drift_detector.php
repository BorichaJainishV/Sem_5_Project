<?php
declare(strict_types=1);

$rootPath = dirname(__DIR__);
require_once $rootPath . '/db_connection.php';
require_once $rootPath . '/core/drop_promotions.php';

$options = getopt('', ['tolerance::']);
$tolerance = isset($options['tolerance']) ? max(0.0, (float) $options['tolerance']) : 0.05;

$status = drop_promotion_current_status();
$currentStatus = $status['status'] ?? 'idle';
$state = isset($status['state']) && is_array($status['state']) ? $status['state'] : [];

if ($currentStatus !== 'active') {
    echo "No active drop promotion detected. Status: {$currentStatus}" . PHP_EOL;
    exit(0);
}

$slug = (string) ($state['active_slug'] ?? '');
$markdowns = isset($state['applied_markdowns']) && is_array($state['applied_markdowns']) ? $state['applied_markdowns'] : [];
$pricingSnapshot = isset($state['pricing_snapshot']) && is_array($state['pricing_snapshot']) ? $state['pricing_snapshot'] : [];

if (empty($markdowns) || empty($pricingSnapshot)) {
    echo "Active drop lacks markdown metadata or pricing snapshot." . PHP_EOL;
    exit(0);
}

$primary = $markdowns[0];
$mode = in_array($primary['mode'] ?? '', ['percent', 'fixed'], true) ? $primary['mode'] : 'percent';
$value = isset($primary['value']) ? (float) $primary['value'] : 0.0;
$scope = in_array($primary['scope'] ?? '', ['all_items', 'sku_list'], true) ? $primary['scope'] : 'all_items';
$targetSkus = isset($primary['skus']) && is_array($primary['skus']) ? array_map('strval', $primary['skus']) : [];

$inventoryIds = array_keys($pricingSnapshot);
$inventoryIds = array_values(array_filter(array_map('intval', $inventoryIds), static function ($id) {
    return $id > 0;
}));

if (empty($inventoryIds)) {
    echo "Pricing snapshot missing inventory ids." . PHP_EOL;
    exit(0);
}

$placeholders = implode(',', array_fill(0, count($inventoryIds), '?'));
$query = "SELECT inventory_id, price FROM inventory WHERE inventory_id IN ($placeholders)";
$stmt = $conn->prepare($query);
$types = str_repeat('i', count($inventoryIds));
$stmt->bind_param($types, ...$inventoryIds);
$stmt->execute();
$result = $stmt->get_result();

$currentPrices = [];
while ($row = $result->fetch_assoc()) {
    $id = (int) $row['inventory_id'];
    $currentPrices[$id] = isset($row['price']) ? (float) $row['price'] : null;
}
$result->free();
$stmt->close();

$drift = [];
foreach ($pricingSnapshot as $inventoryId => $originalPrice) {
    $inventoryId = (int) $inventoryId;
    if ($inventoryId <= 0) {
        continue;
    }

    $original = (float) $originalPrice;
    $current = $currentPrices[$inventoryId] ?? null;
    if ($current === null) {
        $drift[] = [
            'inventory_id' => $inventoryId,
            'reason' => 'missing_current_price',
        ];
        continue;
    }

    $applies = ($scope === 'all_items')
        || ($scope === 'sku_list' && in_array((string) $inventoryId, $targetSkus, true));

    if (!$applies) {
        continue;
    }

    if ($mode === 'fixed') {
        $expected = max(0.0, $original - $value);
    } else {
        $expected = $original * (1 - ($value / 100));
    }
    $expected = round($expected, 2);

    if (abs($current - $expected) > $tolerance) {
        $drift[] = [
            'inventory_id' => $inventoryId,
            'original' => $original,
            'expected' => $expected,
            'actual' => $current,
            'difference' => round($current - $expected, 2),
        ];
    }
}

if (empty($drift)) {
    echo "Promotion $slug: no pricing drift detected." . PHP_EOL;
    exit(0);
}

echo json_encode([
    'slug' => $slug,
    'drift_count' => count($drift),
    'items' => $drift,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit(1);
