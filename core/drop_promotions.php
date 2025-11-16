<?php
// ---------------------------------------------------------------------
// core/drop_promotions.php - Promotion engine plumbing for drop campaigns
// ---------------------------------------------------------------------

if (!function_exists('get_active_flash_banner')) {
    require_once __DIR__ . '/banner_manager.php';
}

if (!defined('DROP_PROMOTION_STATE_PATH')) {
    define('DROP_PROMOTION_STATE_PATH', __DIR__ . '/../storage/drop_promotions_state.json');
}

if (!defined('DROP_PROMOTION_LOG_PATH')) {
    define('DROP_PROMOTION_LOG_PATH', __DIR__ . '/../storage/logs/drop_promotions.log');
}

if (!defined('DROP_PROMOTION_LOCK_PATH')) {
    define('DROP_PROMOTION_LOCK_PATH', __DIR__ . '/../storage/drop_promotions.lock');
}

if (!defined('DROP_PROMOTION_BUNDLE_PATH')) {
    define('DROP_PROMOTION_BUNDLE_PATH', __DIR__ . '/../storage/drop_bundle_rules.json');
}

if (!function_exists('drop_promotion_ensure_directory')) {
    function drop_promotion_ensure_directory(string $path): void
    {
        $dir = dirname($path);
        if (is_dir($dir)) {
            return;
        }

        @mkdir($dir, 0775, true);
    }
}

if (!function_exists('drop_promotion_default_state')) {
    function drop_promotion_default_state(): array
    {
        return [
            'active_slug' => null,
            'promotion_type' => null,
            'promotion_features' => [],
            'featured_inventory' => [],
            'activated_at' => null,
            'applied_markdowns' => [],
            'pricing_snapshot' => [],
            'bundle_rules' => [],
            'clearance_skus' => [],
            'clearance_snapshot' => [],
            'custom_reward' => null,
            'last_config_hash' => null,
            'manual_suspend_hash' => null,
            'manual_suspend_slug' => null,
            'manual_suspend_at' => null,
            'log' => [],
        ];
    }
}

if (!function_exists('drop_promotion_load_state')) {
    function drop_promotion_load_state(): array
    {
        $path = DROP_PROMOTION_STATE_PATH;
        if (!file_exists($path)) {
            return drop_promotion_default_state();
        }

        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return drop_promotion_default_state();
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return drop_promotion_default_state();
        }

        return array_merge(drop_promotion_default_state(), $decoded);
    }
}

if (!function_exists('drop_promotion_save_state')) {
    function drop_promotion_save_state(array $state): bool
    {
        drop_promotion_ensure_directory(DROP_PROMOTION_STATE_PATH);
        $payload = array_merge(drop_promotion_default_state(), $state);
        $payload['updated_at'] = time();

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        return file_put_contents(DROP_PROMOTION_STATE_PATH, $json, LOCK_EX) !== false;
    }
}

if (!function_exists('drop_promotion_allowed_features')) {
    function drop_promotion_allowed_features(): array
    {
        return ['price_markdown', 'bundle_bogo', 'clearance', 'custom_design_reward'];
    }
}

if (!function_exists('drop_promotion_collect_features')) {
    function drop_promotion_collect_features(array $promotion): array
    {
        $features = [];

        if (isset($promotion['features']) && is_array($promotion['features'])) {
            foreach ($promotion['features'] as $feature) {
                $feature = trim((string) $feature);
                if ($feature !== '') {
                    $features[] = $feature;
                }
            }
        }

        $type = trim((string) ($promotion['type'] ?? ''));
        if ($type !== '' && $type !== 'hybrid') {
            $features[] = $type;
        }

        if (!empty($promotion['custom_design_reward']['enabled'])) {
            $features[] = 'custom_design_reward';
        }

        if (!in_array('price_markdown', $features, true)) {
            $markdown = $promotion['markdown'] ?? [];
            $value = isset($markdown['value']) ? (float) $markdown['value'] : 0.0;
            if ($value > 0.0) {
                $features[] = 'price_markdown';
            }
        }

        if (!in_array('bundle_bogo', $features, true)) {
            $bundle = $promotion['bundle'] ?? [];
            if (!empty($bundle['eligible_skus']) || !empty($bundle['free_items'])) {
                $features[] = 'bundle_bogo';
            }
        }

        if (!in_array('clearance', $features, true)) {
            $clearance = $promotion['clearance'] ?? [];
            if (!empty($clearance['skus'])) {
                $features[] = 'clearance';
            }
        }

        $allowed = drop_promotion_allowed_features();
        $deduped = [];
        foreach ($features as $feature) {
            if (in_array($feature, $allowed, true) && !in_array($feature, $deduped, true)) {
                $deduped[] = $feature;
            }
        }

        return $deduped;
    }
}

if (!function_exists('drop_promotion_feature_enabled')) {
    function drop_promotion_feature_enabled(array $promotion, string $feature): bool
    {
        $feature = trim($feature);
        if ($feature === '') {
            return false;
        }

        $features = drop_promotion_collect_features($promotion);
        return in_array($feature, $features, true);
    }
}

if (!function_exists('drop_promotion_log')) {
    function drop_promotion_log(string $event, array $context = []): void
    {
        drop_promotion_ensure_directory(DROP_PROMOTION_LOG_PATH);

        $entry = [
            'timestamp' => date(DateTimeInterface::ATOM),
            'event' => $event,
            'context' => $context,
        ];

        $line = json_encode($entry, JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            return;
        }

        @file_put_contents(DROP_PROMOTION_LOG_PATH, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('drop_promotion_get_active_custom_reward')) {
    function drop_promotion_get_active_custom_reward(): ?array
    {
        $state = drop_promotion_load_state();
        $slug = isset($state['active_slug']) ? (string) $state['active_slug'] : '';
        if ($slug === '') {
            return null;
        }

        $reward = $state['custom_reward'] ?? null;
        if (!is_array($reward) || empty($reward['discount_value'])) {
            return null;
        }

        $amount = max(0.0, (float) $reward['discount_value']);
        if ($amount <= 0.0) {
            return null;
        }

        return [
            'drop_slug' => $slug,
            'promotion_type' => $state['promotion_type'] ?? null,
            'discount_value' => $amount,
        ];
    }
}

if (!function_exists('drop_promotion_acquire_lock')) {
    function drop_promotion_acquire_lock()
    {
        drop_promotion_ensure_directory(DROP_PROMOTION_LOCK_PATH);
        $handle = @fopen(DROP_PROMOTION_LOCK_PATH, 'c');
        if ($handle === false) {
            return false;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return false;
        }

        return $handle;
    }
}

if (!function_exists('drop_promotion_release_lock')) {
    function drop_promotion_release_lock($handle): void
    {
        if (!is_resource($handle)) {
            return;
        }

        @flock($handle, LOCK_UN);
        @fclose($handle);
    }
}

if (!function_exists('drop_promotion_store_bundle_rules')) {
    function drop_promotion_store_bundle_rules(array $rules, ?string $dropSlug = null): array
    {
        if (empty($rules)) {
            if (file_exists(DROP_PROMOTION_BUNDLE_PATH)) {
                if (!@unlink(DROP_PROMOTION_BUNDLE_PATH)) {
                    drop_promotion_log('bundle_rules_failed', [
                        'slug' => $dropSlug,
                        'reason' => 'unlink_failed',
                    ]);

                    return ['status' => 'error', 'message' => 'Unable to clear bundle rule store.'];
                }
            }

            drop_promotion_log('bundle_rules_cleared', [
                'slug' => $dropSlug,
            ]);

            return ['status' => 'cleared'];
        }

        drop_promotion_ensure_directory(DROP_PROMOTION_BUNDLE_PATH);

        $payload = [
            'slug' => $dropSlug,
            'updated_at' => time(),
            'rules' => array_values($rules),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            drop_promotion_log('bundle_rules_failed', [
                'slug' => $dropSlug,
                'reason' => 'json_encode',
            ]);

            return ['status' => 'error', 'message' => 'Unable to encode bundle rules.'];
        }

        if (file_put_contents(DROP_PROMOTION_BUNDLE_PATH, $json, LOCK_EX) === false) {
            drop_promotion_log('bundle_rules_failed', [
                'slug' => $dropSlug,
                'reason' => 'write_failed',
            ]);

            return ['status' => 'error', 'message' => 'Unable to persist bundle rules.'];
        }

        drop_promotion_log('bundle_rules_saved', [
            'slug' => $dropSlug,
            'count' => count($rules),
        ]);

        return ['status' => 'saved', 'count' => count($rules)];
    }
}

if (!function_exists('drop_promotion_load_bundle_rules')) {
    function drop_promotion_load_bundle_rules(): array
    {
        if (!file_exists(DROP_PROMOTION_BUNDLE_PATH)) {
            return [];
        }

        $raw = @file_get_contents(DROP_PROMOTION_BUNDLE_PATH);
        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || empty($decoded['rules']) || !is_array($decoded['rules'])) {
            return [];
        }

        $normalizedRules = [];
        foreach ($decoded['rules'] as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $eligible = [];
            if (isset($rule['eligible_inventory_ids']) && is_array($rule['eligible_inventory_ids'])) {
                foreach ($rule['eligible_inventory_ids'] as $candidate) {
                    $candidateId = (int) $candidate;
                    if ($candidateId > 0) {
                        $eligible[] = $candidateId;
                    }
                }
            }

            $eligible = array_values(array_unique($eligible));

            $freeItems = [];
            if (isset($rule['free_items']) && is_array($rule['free_items'])) {
                foreach ($rule['free_items'] as $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $inventoryId = isset($item['inventory_id']) ? (int) $item['inventory_id'] : 0;
                    if ($inventoryId <= 0 && isset($item['sku'])) {
                        $inventoryId = (int) $item['sku'];
                    }
                    if ($inventoryId <= 0) {
                        continue;
                    }

                    $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 1;
                    if ($quantity <= 0) {
                        $quantity = 1;
                    }

                    $freeItems[] = [
                        'inventory_id' => $inventoryId,
                        'quantity' => $quantity,
                    ];
                }
            }

            $limit = isset($rule['limit_per_cart']) ? (int) $rule['limit_per_cart'] : 0;
            if ($limit < 0) {
                $limit = 0;
            }

            if (empty($eligible) || empty($freeItems)) {
                continue;
            }

            $normalizedRules[] = [
                'eligible_inventory_ids' => $eligible,
                'free_items' => $freeItems,
                'limit_per_cart' => $limit,
            ];
        }

        if (empty($normalizedRules)) {
            return [];
        }

        return [
            'slug' => isset($decoded['slug']) ? (string) $decoded['slug'] : null,
            'updated_at' => isset($decoded['updated_at']) ? (int) $decoded['updated_at'] : null,
            'rules' => $normalizedRules,
        ];
    }
}

if (!function_exists('drop_promotion_calculate_bundle_freebies')) {
    function drop_promotion_calculate_bundle_freebies(array $cartQuantities): array
    {
        $payload = drop_promotion_load_bundle_rules();
        $rules = isset($payload['rules']) && is_array($payload['rules']) ? $payload['rules'] : [];

        $normalizedCart = [];
        foreach ($cartQuantities as $inventoryId => $quantity) {
            $inventoryId = (int) $inventoryId;
            $quantity = (int) $quantity;
            if ($inventoryId <= 0 || $quantity <= 0) {
                continue;
            }
            $normalizedCart[$inventoryId] = ($normalizedCart[$inventoryId] ?? 0) + $quantity;
        }

        if (empty($rules) || empty($normalizedCart)) {
            return [
                'grants' => [],
                'applied' => [],
                'slug' => $payload['slug'] ?? null,
            ];
        }

        $grants = [];
        $applied = [];

        foreach ($rules as $index => $rule) {
            $eligibleIds = isset($rule['eligible_inventory_ids']) && is_array($rule['eligible_inventory_ids'])
                ? $rule['eligible_inventory_ids']
                : [];
            $freeItems = isset($rule['free_items']) && is_array($rule['free_items'])
                ? $rule['free_items']
                : [];

            if (empty($eligibleIds) || empty($freeItems)) {
                continue;
            }

            $eligibleUnitCount = 0;
            foreach ($eligibleIds as $eligibleId) {
                $eligibleId = (int) $eligibleId;
                if ($eligibleId <= 0) {
                    continue;
                }
                $eligibleUnitCount += $normalizedCart[$eligibleId] ?? 0;
            }

            if ($eligibleUnitCount <= 0) {
                continue;
            }

            $limitPerCart = isset($rule['limit_per_cart']) ? (int) $rule['limit_per_cart'] : 0;
            if ($limitPerCart > 0) {
                $eligibleUnitCount = min($eligibleUnitCount, $limitPerCart);
            }

            if ($eligibleUnitCount <= 0) {
                continue;
            }

            foreach ($freeItems as $freeItem) {
                if (!is_array($freeItem)) {
                    continue;
                }

                $freeInventory = isset($freeItem['inventory_id']) ? (int) $freeItem['inventory_id'] : 0;
                if ($freeInventory <= 0 && isset($freeItem['sku'])) {
                    $freeInventory = (int) $freeItem['sku'];
                }
                $freeQuantity = isset($freeItem['quantity']) ? (int) $freeItem['quantity'] : 1;
                if ($freeInventory <= 0 || $freeQuantity <= 0) {
                    continue;
                }

                $grantQuantity = $eligibleUnitCount * $freeQuantity;
                if ($grantQuantity <= 0) {
                    continue;
                }

                $grants[$freeInventory] = ($grants[$freeInventory] ?? 0) + $grantQuantity;
            }

            $applied[] = [
                'rule_index' => $index,
                'eligible_units' => $eligibleUnitCount,
                'limit_per_cart' => $rule['limit_per_cart'] ?? 0,
            ];
        }

        return [
            'grants' => $grants,
            'applied' => $applied,
            'slug' => $payload['slug'] ?? null,
        ];
    }
}

if (!function_exists('drop_promotion_get_connection')) {
    function drop_promotion_get_connection(): ?mysqli
    {
        static $cached = null;

        if ($cached instanceof mysqli) {
            if (@$cached->ping()) {
                return $cached;
            }

            try {
                @$cached->close();
            } catch (Throwable $e) {
            }

            $cached = null;
        }

        $dbPath = __DIR__ . '/../db_connection.php';
        if (!file_exists($dbPath)) {
            drop_promotion_log('db_connection_missing', ['path' => $dbPath]);
            return null;
        }

        $conn = null;
        try {
            /** @var mysqli|null $conn */
            require $dbPath;
        } catch (Throwable $e) {
            drop_promotion_log('db_connection_exception', ['message' => $e->getMessage()]);
            return null;
        }

        if (!isset($conn) || !($conn instanceof mysqli)) {
            drop_promotion_log('db_connection_unavailable', []);
            return null;
        }

        $cached = $conn;

        return $cached;
    }
}

if (!function_exists('drop_promotion_extract_inventory_ids')) {
    function drop_promotion_extract_inventory_ids($rawSkus): array
    {
        if (!is_array($rawSkus)) {
            $rawSkus = [$rawSkus];
        }

        $ids = [];
        foreach ($rawSkus as $sku) {
            if ($sku === null) {
                continue;
            }

            if (is_int($sku)) {
                $candidate = $sku;
            } elseif (is_numeric($sku)) {
                $candidate = (int) $sku;
            } elseif (is_string($sku) && preg_match('/(\d+)/', $sku, $matches)) {
                $candidate = (int) $matches[1];
            } else {
                continue;
            }

            if ($candidate > 0) {
                $ids[] = $candidate;
            }
        }

        return array_values(array_unique($ids));
    }
}

if (!function_exists('drop_promotion_fetch_inventory_prices')) {
    function drop_promotion_fetch_inventory_prices(mysqli $conn, ?array $inventoryIds = null): array
    {
        $prices = [];

        if ($inventoryIds === null) {
            $sql = 'SELECT inventory_id, price FROM inventory WHERE is_archived = 0 OR is_archived IS NULL';
            $result = $conn->query($sql);
            if ($result instanceof mysqli_result) {
                while ($row = $result->fetch_assoc()) {
                    $inventoryId = isset($row['inventory_id']) ? (int) $row['inventory_id'] : 0;
                    if ($inventoryId <= 0) {
                        continue;
                    }
                    $prices[$inventoryId] = isset($row['price']) ? (float) $row['price'] : 0.0;
                }
                $result->free();
            }

            return $prices;
        }

        if (empty($inventoryIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($inventoryIds), '?'));
        $sql = "SELECT inventory_id, price FROM inventory WHERE (is_archived = 0 OR is_archived IS NULL) AND inventory_id IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $types = str_repeat('i', count($inventoryIds));
        $stmt->bind_param($types, ...$inventoryIds);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $inventoryId = isset($row['inventory_id']) ? (int) $row['inventory_id'] : 0;
                if ($inventoryId <= 0) {
                    continue;
                }
                $prices[$inventoryId] = isset($row['price']) ? (float) $row['price'] : 0.0;
            }
            $result->free();
        }

        $stmt->close();

        return $prices;
    }
}

if (!function_exists('drop_promotion_calculate_markdown')) {
    function drop_promotion_calculate_markdown(float $price, string $mode, float $value): float
    {
        if ($value <= 0.0) {
            return round($price, 2);
        }

        if ($mode === 'fixed') {
            $newPrice = $price - $value;
        } else {
            $newPrice = $price * (1.0 - ($value / 100.0));
        }

        if ($newPrice < 0.0) {
            $newPrice = 0.0;
        }

        return round($newPrice, 2);
    }
}

if (!function_exists('drop_promotion_apply_markdowns')) {
    function drop_promotion_apply_markdowns(array $config, array $state): array
    {
        $promotion = $config['promotion'] ?? [];

        $result = [
            'status' => 'skipped',
            'state' => $state,
        ];

        if (!drop_promotion_feature_enabled($promotion, 'price_markdown')) {
            return $result;
        }

        $markdown = $promotion['markdown'] ?? [];
        $value = isset($markdown['value']) ? (float) $markdown['value'] : 0.0;
        $mode = isset($markdown['mode']) ? (string) $markdown['mode'] : 'percent';
        $scope = isset($markdown['scope']) ? (string) $markdown['scope'] : 'all_items';

        if ($value <= 0.0) {
            drop_promotion_log('markdown_skipped', [
                'slug' => $config['drop_slug'] ?? '',
                'reason' => 'non_positive_value',
            ]);

            return $result;
        }

        $conn = drop_promotion_get_connection();
        if (!$conn) {
            drop_promotion_log('markdown_failed', [
                'slug' => $config['drop_slug'] ?? '',
                'reason' => 'db_connection',
            ]);

            return [
                'status' => 'error',
                'message' => 'Unable to establish database connection.',
                'state' => $state,
            ];
        }

        $inventoryIds = null;
        if ($scope === 'sku_list') {
            $inventoryIds = drop_promotion_extract_inventory_ids($markdown['skus'] ?? []);
            if (empty($inventoryIds)) {
                drop_promotion_log('markdown_skipped', [
                    'slug' => $config['drop_slug'] ?? '',
                    'reason' => 'empty_sku_list',
                ]);

                return $result;
            }
        }

        try {
            $conn->begin_transaction();

            $currentPrices = drop_promotion_fetch_inventory_prices($conn, $inventoryIds);
            if (empty($currentPrices)) {
                $conn->rollback();
                drop_promotion_log('markdown_skipped', [
                    'slug' => $config['drop_slug'] ?? '',
                    'reason' => 'no_matching_inventory',
                ]);

                return $result;
            }

            $updates = [];
            $snapshot = [];
            foreach ($currentPrices as $inventoryId => $originalPrice) {
                $newPrice = drop_promotion_calculate_markdown($originalPrice, $mode, $value);
                if (abs($newPrice - $originalPrice) < 0.005) {
                    continue;
                }

                $updates[$inventoryId] = $newPrice;
                $snapshot[$inventoryId] = round($originalPrice, 2);
            }

            if (empty($updates)) {
                $conn->rollback();
                drop_promotion_log('markdown_skipped', [
                    'slug' => $config['drop_slug'] ?? '',
                    'reason' => 'no_price_delta',
                ]);

                return $result;
            }

            $stmt = $conn->prepare('UPDATE inventory SET price = ? WHERE inventory_id = ?');
            if (!$stmt) {
                $conn->rollback();
                drop_promotion_log('markdown_failed', [
                    'slug' => $config['drop_slug'] ?? '',
                    'reason' => 'statement_prepare',
                ]);

                return [
                    'status' => 'error',
                    'message' => 'Unable to prepare update statement.',
                    'state' => $state,
                ];
            }

            $priceParam = 0.0;
            $idParam = 0;
            $stmt->bind_param('di', $priceParam, $idParam);

            foreach ($updates as $inventoryId => $newPrice) {
                $priceParam = $newPrice;
                $idParam = (int) $inventoryId;
                $stmt->execute();
            }

            $stmt->close();
            $conn->commit();

            $state['pricing_snapshot'] = $snapshot;
            $state['applied_markdowns'] = drop_promotion_prepare_markdowns($promotion);

            drop_promotion_log('markdown_applied', [
                'slug' => $config['drop_slug'] ?? '',
                'mode' => $mode,
                'value' => $value,
                'updated' => count($updates),
            ]);

            return [
                'status' => 'applied',
                'state' => $state,
                'updated' => count($updates),
            ];
        } catch (mysqli_sql_exception $exception) {
            try {
                $conn->rollback();
            } catch (Throwable $rollbackException) {
            }

            drop_promotion_log('markdown_failed', [
                'slug' => $config['drop_slug'] ?? '',
                'reason' => 'exception',
                'message' => $exception->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Exception while applying markdowns.',
                'state' => $state,
            ];
        }
    }
}

if (!function_exists('drop_promotion_revert_markdowns')) {
    function drop_promotion_revert_markdowns(array $state): array
    {
        $snapshot = $state['pricing_snapshot'] ?? [];
        if (empty($snapshot)) {
            return ['status' => 'skipped'];
        }

        $conn = drop_promotion_get_connection();
        if (!$conn) {
            drop_promotion_log('markdown_restore_failed', [
                'reason' => 'db_connection',
            ]);

            return [
                'status' => 'error',
                'message' => 'Unable to establish database connection.',
            ];
        }

        try {
            $conn->begin_transaction();
            $stmt = $conn->prepare('UPDATE inventory SET price = ? WHERE inventory_id = ?');
            if (!$stmt) {
                $conn->rollback();
                drop_promotion_log('markdown_restore_failed', [
                    'reason' => 'statement_prepare',
                ]);

                return [
                    'status' => 'error',
                    'message' => 'Unable to prepare restore statement.',
                ];
            }

            $priceParam = 0.0;
            $idParam = 0;
            $stmt->bind_param('di', $priceParam, $idParam);

            foreach ($snapshot as $inventoryId => $originalPrice) {
                $priceParam = (float) $originalPrice;
                $idParam = (int) $inventoryId;
                $stmt->execute();
            }

            $stmt->close();
            $conn->commit();

            drop_promotion_log('markdown_restored', [
                'restored' => count($snapshot),
            ]);

            return [
                'status' => 'restored',
                'restored' => count($snapshot),
            ];
        } catch (mysqli_sql_exception $exception) {
            try {
                $conn->rollback();
            } catch (Throwable $rollbackException) {
            }

            drop_promotion_log('markdown_restore_failed', [
                'reason' => 'exception',
                'message' => $exception->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Exception while restoring markdown prices.',
            ];
        }
    }
}

if (!function_exists('drop_promotion_ensure_clearance_column')) {
    function drop_promotion_ensure_clearance_column(mysqli $conn): bool
    {
        static $ensured = null;

        if ($ensured === true) {
            return true;
        }

        if ($ensured === false) {
            return false;
        }

        try {
            $columnCheck = $conn->query("SHOW COLUMNS FROM inventory LIKE 'is_clearance'");
            if ($columnCheck && $columnCheck->num_rows === 0) {
                $conn->query("ALTER TABLE inventory ADD COLUMN is_clearance TINYINT(1) NOT NULL DEFAULT 0");
            }
            if ($columnCheck instanceof mysqli_result) {
                $columnCheck->free();
            }
        } catch (mysqli_sql_exception $exception) {
            drop_promotion_log('clearance_column_failed', [
                'message' => $exception->getMessage(),
            ]);

            $ensured = false;

            return false;
        }

        $ensured = true;

        return true;
    }
}

if (!function_exists('drop_promotion_apply_clearance')) {
    function drop_promotion_apply_clearance(array $config, array $state): array
    {
        $inventoryIds = isset($state['clearance_skus']) && is_array($state['clearance_skus'])
            ? array_values(array_unique(array_map('intval', $state['clearance_skus'])))
            : [];

        if (empty($inventoryIds)) {
            return ['status' => 'skipped', 'state' => $state];
        }

        $conn = drop_promotion_get_connection();
        if (!$conn) {
            drop_promotion_log('clearance_failed', [
                'slug' => $config['drop_slug'] ?? '',
                'reason' => 'db_connection',
            ]);

            return [
                'status' => 'error',
                'message' => 'Unable to establish database connection for clearance update.',
                'state' => $state,
            ];
        }

        if (!drop_promotion_ensure_clearance_column($conn)) {
            return [
                'status' => 'error',
                'message' => 'Unable to ensure clearance column exists.',
                'state' => $state,
            ];
        }

        $placeholders = implode(',', array_fill(0, count($inventoryIds), '?'));
        $types = str_repeat('i', count($inventoryIds));

        try {
            $conn->begin_transaction();

            $selectSql = "SELECT inventory_id, is_clearance FROM inventory WHERE inventory_id IN ($placeholders)";
            $selectStmt = $conn->prepare($selectSql);
            if (!$selectStmt) {
                $conn->rollback();
                drop_promotion_log('clearance_failed', [
                    'slug' => $config['drop_slug'] ?? '',
                    'reason' => 'select_prepare',
                ]);

                return [
                    'status' => 'error',
                    'message' => 'Unable to prepare clearance snapshot statement.',
                    'state' => $state,
                ];
            }

            $selectStmt->bind_param($types, ...$inventoryIds);
            $selectStmt->execute();
            $result = $selectStmt->get_result();
            $snapshot = [];
            if ($result instanceof mysqli_result) {
                while ($row = $result->fetch_assoc()) {
                    $inventoryId = isset($row['inventory_id']) ? (int) $row['inventory_id'] : 0;
                    if ($inventoryId <= 0) {
                        continue;
                    }
                    $snapshot[$inventoryId] = isset($row['is_clearance']) ? (int) $row['is_clearance'] : 0;
                }
                $result->free();
            }
            $selectStmt->close();

            if (empty($snapshot)) {
                $conn->rollback();
                drop_promotion_log('clearance_skipped', [
                    'slug' => $config['drop_slug'] ?? '',
                    'reason' => 'no_matching_inventory',
                ]);

                return ['status' => 'skipped', 'state' => $state];
            }

            $updateSql = "UPDATE inventory SET is_clearance = 1 WHERE inventory_id IN ($placeholders)";
            $updateStmt = $conn->prepare($updateSql);
            if (!$updateStmt) {
                $conn->rollback();
                drop_promotion_log('clearance_failed', [
                    'slug' => $config['drop_slug'] ?? '',
                    'reason' => 'update_prepare',
                ]);

                return [
                    'status' => 'error',
                    'message' => 'Unable to prepare clearance update statement.',
                    'state' => $state,
                ];
            }

            $updateStmt->bind_param($types, ...$inventoryIds);
            $updateStmt->execute();
            $affected = max(0, $updateStmt->affected_rows);
            $updateStmt->close();

            $conn->commit();

            $state['clearance_snapshot'] = $snapshot;

            drop_promotion_log('clearance_applied', [
                'slug' => $config['drop_slug'] ?? '',
                'updated' => $affected,
            ]);

            return [
                'status' => 'applied',
                'state' => $state,
                'updated' => $affected,
            ];
        } catch (mysqli_sql_exception $exception) {
            try {
                $conn->rollback();
            } catch (Throwable $rollbackException) {
            }

            drop_promotion_log('clearance_failed', [
                'slug' => $config['drop_slug'] ?? '',
                'reason' => 'exception',
                'message' => $exception->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Exception while applying clearance flags.',
                'state' => $state,
            ];
        }
    }
}

if (!function_exists('drop_promotion_revert_clearance')) {
    function drop_promotion_revert_clearance(array $state): array
    {
        $snapshot = $state['clearance_snapshot'] ?? [];
        if (empty($snapshot)) {
            return ['status' => 'skipped'];
        }

        $conn = drop_promotion_get_connection();
        if (!$conn) {
            drop_promotion_log('clearance_restore_failed', [
                'reason' => 'db_connection',
            ]);

            return [
                'status' => 'error',
                'message' => 'Unable to establish database connection for clearance restore.',
            ];
        }

        if (!drop_promotion_ensure_clearance_column($conn)) {
            return [
                'status' => 'error',
                'message' => 'Unable to ensure clearance column exists.',
            ];
        }

        try {
            $conn->begin_transaction();
            $stmt = $conn->prepare('UPDATE inventory SET is_clearance = ? WHERE inventory_id = ?');
            if (!$stmt) {
                $conn->rollback();
                drop_promotion_log('clearance_restore_failed', [
                    'reason' => 'statement_prepare',
                ]);

                return [
                    'status' => 'error',
                    'message' => 'Unable to prepare clearance restore statement.',
                ];
            }

            $flagParam = 0;
            $idParam = 0;
            $stmt->bind_param('ii', $flagParam, $idParam);

            foreach ($snapshot as $inventoryId => $flagValue) {
                $flagParam = (int) $flagValue;
                if ($flagParam !== 0) {
                    $flagParam = 1;
                }
                $idParam = (int) $inventoryId;
                $stmt->execute();
            }

            $stmt->close();
            $conn->commit();

            drop_promotion_log('clearance_restored', [
                'restored' => count($snapshot),
            ]);

            return [
                'status' => 'restored',
                'restored' => count($snapshot),
            ];
        } catch (mysqli_sql_exception $exception) {
            try {
                $conn->rollback();
            } catch (Throwable $rollbackException) {
            }

            drop_promotion_log('clearance_restore_failed', [
                'reason' => 'exception',
                'message' => $exception->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Exception while restoring clearance flags.',
            ];
        }
    }
}

if (!function_exists('drop_promotion_force_clearance_reset')) {
    function drop_promotion_force_clearance_reset(array $inventoryIds): array
    {
        $inventoryIds = array_values(array_unique(array_map('intval', $inventoryIds)));
        $inventoryIds = array_filter($inventoryIds, static function ($id) {
            return $id > 0;
        });

        if (empty($inventoryIds)) {
            return ['status' => 'skipped'];
        }

        $conn = drop_promotion_get_connection();
        if (!$conn) {
            drop_promotion_log('clearance_force_reset_failed', [
                'reason' => 'db_connection',
                'inventory_ids' => $inventoryIds,
            ]);

            return [
                'status' => 'error',
                'message' => 'Unable to establish database connection for clearance reset.',
            ];
        }

        if (!drop_promotion_ensure_clearance_column($conn)) {
            return [
                'status' => 'error',
                'message' => 'Unable to ensure clearance column exists.',
            ];
        }

        $placeholders = implode(',', array_fill(0, count($inventoryIds), '?'));
        $types = str_repeat('i', count($inventoryIds));

        try {
            $stmt = $conn->prepare("UPDATE inventory SET is_clearance = 0 WHERE inventory_id IN ($placeholders)");
            if (!$stmt) {
                drop_promotion_log('clearance_force_reset_failed', [
                    'reason' => 'statement_prepare',
                    'inventory_ids' => $inventoryIds,
                ]);

                return [
                    'status' => 'error',
                    'message' => 'Unable to prepare clearance reset statement.',
                ];
            }

            $stmt->bind_param($types, ...$inventoryIds);
            $stmt->execute();
            $affected = max(0, $stmt->affected_rows);
            $stmt->close();

            drop_promotion_log('clearance_force_reset', [
                'inventory_ids' => $inventoryIds,
                'updated' => $affected,
            ]);

            return [
                'status' => 'reset',
                'updated' => $affected,
            ];
        } catch (mysqli_sql_exception $exception) {
            drop_promotion_log('clearance_force_reset_failed', [
                'reason' => 'exception',
                'message' => $exception->getMessage(),
                'inventory_ids' => $inventoryIds,
            ]);

            return [
                'status' => 'error',
                'message' => 'Exception while resetting clearance flags.',
            ];
        }
    }
}

if (!function_exists('drop_promotion_prepare_markdowns')) {
    function drop_promotion_prepare_markdowns(array $promotion): array
    {
        if (!drop_promotion_feature_enabled($promotion, 'price_markdown')) {
            return [];
        }

        $markdown = $promotion['markdown'] ?? [];

        return [[
            'mode' => $markdown['mode'] ?? 'percent',
            'value' => isset($markdown['value']) ? (float) $markdown['value'] : 0.0,
            'scope' => $markdown['scope'] ?? 'all_items',
            'skus' => isset($markdown['skus']) && is_array($markdown['skus']) ? array_values($markdown['skus']) : [],
        ]];
    }
}

if (!function_exists('drop_promotion_prepare_bundle_rules')) {
    function drop_promotion_prepare_bundle_rules(array $promotion): array
    {
        if (!drop_promotion_feature_enabled($promotion, 'bundle_bogo')) {
            return [];
        }

        $bundle = $promotion['bundle'] ?? [];

        $eligibleInventoryIds = drop_promotion_extract_inventory_ids($bundle['eligible_skus'] ?? []);

        $freeItems = [];
        if (isset($bundle['free_items']) && is_array($bundle['free_items'])) {
            foreach ($bundle['free_items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $candidate = $item['inventory_id'] ?? $item['sku'] ?? null;
                $ids = drop_promotion_extract_inventory_ids([$candidate]);
                $inventoryId = $ids[0] ?? null;
                if ($inventoryId === null) {
                    continue;
                }

                $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 1;
                if ($quantity < 1) {
                    $quantity = 1;
                }

                $freeItems[] = [
                    'inventory_id' => $inventoryId,
                    'quantity' => $quantity,
                ];
            }
        }

        $limitPerCart = isset($bundle['limit_per_cart']) ? (int) $bundle['limit_per_cart'] : 0;
        if ($limitPerCart < 0) {
            $limitPerCart = 0;
        }

        if (empty($eligibleInventoryIds) && empty($freeItems)) {
            return [];
        }

        return [[
            'eligible_inventory_ids' => $eligibleInventoryIds,
            'free_items' => $freeItems,
            'limit_per_cart' => $limitPerCart,
        ]];
    }
}

if (!function_exists('drop_promotion_prepare_clearance')) {
    function drop_promotion_prepare_clearance(array $promotion): array
    {
        if (!drop_promotion_feature_enabled($promotion, 'clearance')) {
            return [];
        }

        $clearance = $promotion['clearance'] ?? [];

        return drop_promotion_extract_inventory_ids($clearance['skus'] ?? []);
    }
}

if (!function_exists('drop_promotion_prepare_custom_reward')) {
    function drop_promotion_prepare_custom_reward(array $promotion): ?array
    {
        $reward = $promotion['custom_design_reward'] ?? [];
        if (empty($reward['enabled'])) {
            return null;
        }

        return [
            'discount_value' => isset($reward['discount_value']) ? (float) $reward['discount_value'] : 0.0,
        ];
    }
}

if (!function_exists('drop_promotion_config_hash')) {
    function drop_promotion_config_hash(array $config): string
    {
        $normalized = [
            'drop_slug' => $config['drop_slug'] ?? '',
            'promotion' => $config['promotion'] ?? [],
            'schedule_start_ts' => $config['schedule_start_ts'] ?? null,
            'schedule_end_ts' => $config['schedule_end_ts'] ?? null,
        ];

        $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $json = serialize($normalized);
        }

        return hash('sha256', $json);
    }
}

if (!function_exists('drop_promotion_get_markdown_context')) {
    function drop_promotion_get_markdown_context(): array
    {
        $state = drop_promotion_load_state();
        $markdowns = isset($state['applied_markdowns']) && is_array($state['applied_markdowns'])
            ? $state['applied_markdowns']
            : [];

        if (empty($state['active_slug']) || empty($markdowns)) {
            return [
                'active' => false,
                'original_prices' => [],
            ];
        }

        $primary = $markdowns[0] ?? [];
        $originals = [];
        if (isset($state['pricing_snapshot']) && is_array($state['pricing_snapshot'])) {
            foreach ($state['pricing_snapshot'] as $inventoryId => $price) {
                $inventoryId = (int) $inventoryId;
                if ($inventoryId <= 0) {
                    continue;
                }
                $originals[$inventoryId] = (float) $price;
            }
        }

        return [
            'active' => true,
            'slug' => $state['active_slug'],
            'mode' => $primary['mode'] ?? 'percent',
            'value' => isset($primary['value']) ? (float) $primary['value'] : 0.0,
            'scope' => $primary['scope'] ?? 'all_items',
            'skus' => isset($primary['skus']) && is_array($primary['skus']) ? array_values($primary['skus']) : [],
            'original_prices' => $originals,
            'activated_at' => $state['activated_at'] ?? null,
        ];
    }
}

if (!function_exists('drop_promotion_original_price')) {
    function drop_promotion_original_price(int $inventoryId): ?float
    {
        $context = drop_promotion_get_markdown_context();
        if (empty($context['active'])) {
            return null;
        }

        $inventoryId = (int) $inventoryId;
        $originals = $context['original_prices'] ?? [];
        if (!isset($originals[$inventoryId])) {
            return null;
        }

        return (float) $originals[$inventoryId];
    }
}

/**
 * Determine if there is an active drop promotion configuration.
 */
function drop_promotion_get_active_config(): ?array
{
    $banner = get_active_flash_banner();
    if (!$banner || ($banner['mode'] ?? '') !== 'drop') {
        return null;
    }

    $promotion = $banner['promotion'] ?? [];
    if (!is_array($promotion)) {
        return null;
    }

    $type = $promotion['type'] ?? '';
    if ($type === '') {
        $features = drop_promotion_collect_features($promotion);
        if (empty($features)) {
            return null;
        }
    }

    return [
        'drop_slug' => $banner['drop_slug'] ?? '',
        'schedule_start_ts' => $banner['schedule_start_ts'] ?? null,
        'schedule_end_ts' => $banner['schedule_end_ts'] ?? null,
        'promotion' => $promotion,
    ];
}

if (!function_exists('drop_promotion_activate')) {
    function drop_promotion_activate(array $config, array $state): array
    {
        $promotion = $config['promotion'];

        $previousClearanceSkus = isset($state['clearance_skus']) && is_array($state['clearance_skus'])
            ? array_values(array_unique(array_map('intval', $state['clearance_skus'])))
            : [];

        if (!empty($state['clearance_snapshot'])) {
            $restoreResult = drop_promotion_revert_clearance($state);
            if (($restoreResult['status'] ?? '') === 'error') {
                return [
                    'status' => 'error',
                    'message' => $restoreResult['message'] ?? 'Failed to reset previous clearance flags.',
                ];
            }

            $state['clearance_snapshot'] = [];
            $state['clearance_skus'] = [];
        } elseif (!empty($previousClearanceSkus)) {
            $resetResult = drop_promotion_force_clearance_reset($previousClearanceSkus);
            if (($resetResult['status'] ?? '') === 'error') {
                return [
                    'status' => 'error',
                    'message' => $resetResult['message'] ?? 'Failed to clear stale clearance flags.',
                ];
            }

            $state['clearance_skus'] = [];
        }

        $newState = array_merge(drop_promotion_default_state(), $state, [
            'active_slug' => $config['drop_slug'],
            'promotion_type' => $promotion['type'],
            'promotion_features' => drop_promotion_collect_features($promotion),
            'featured_inventory' => drop_promotion_extract_inventory_ids($promotion['featured_inventory'] ?? []),
            'activated_at' => time(),
            'applied_markdowns' => drop_promotion_prepare_markdowns($promotion),
            'pricing_snapshot' => [],
            'bundle_rules' => drop_promotion_prepare_bundle_rules($promotion),
            'clearance_skus' => drop_promotion_prepare_clearance($promotion),
            'clearance_snapshot' => [],
            'custom_reward' => drop_promotion_prepare_custom_reward($promotion),
            'last_config_hash' => drop_promotion_config_hash($config),
            'manual_suspend_hash' => null,
            'manual_suspend_slug' => null,
            'manual_suspend_at' => null,
        ]);

        $pricingResult = drop_promotion_apply_markdowns($config, $newState);
        if (($pricingResult['status'] ?? '') === 'error') {
            return [
                'status' => 'error',
                'message' => $pricingResult['message'] ?? 'Failed to apply price markdowns.',
            ];
        }

        $newState = $pricingResult['state'] ?? $newState;

        $bundleResult = drop_promotion_store_bundle_rules($newState['bundle_rules'] ?? [], $config['drop_slug'] ?? '');
        if (($bundleResult['status'] ?? '') === 'error') {
            drop_promotion_revert_markdowns($newState);

            return [
                'status' => 'error',
                'message' => $bundleResult['message'] ?? 'Failed to persist bundle rules.',
            ];
        }

        $clearanceResult = drop_promotion_apply_clearance($config, $newState);
        if (($clearanceResult['status'] ?? '') === 'error') {
            drop_promotion_revert_markdowns($newState);
            if (($bundleResult['status'] ?? '') === 'saved') {
                drop_promotion_store_bundle_rules([], $config['drop_slug'] ?? '');
            }

            return [
                'status' => 'error',
                'message' => $clearanceResult['message'] ?? 'Failed to apply clearance flags.',
            ];
        }

        $newState = $clearanceResult['state'] ?? $newState;

        if (!drop_promotion_save_state($newState)) {
            drop_promotion_log('activation_failed', [
                'slug' => $config['drop_slug'],
                'type' => $promotion['type'],
                'reason' => 'state_persist',
            ]);

            return ['status' => 'error', 'message' => 'Unable to persist promotion state.'];
        }

        drop_promotion_log('activated', [
            'slug' => $config['drop_slug'],
            'type' => $promotion['type'],
        ]);

        return ['status' => 'activated', 'state' => $newState];
    }
}

if (!function_exists('drop_promotion_deactivate')) {
    function drop_promotion_deactivate(array $state): array
    {
        $previousSlug = $state['active_slug'] ?? null;
        $previousType = $state['promotion_type'] ?? null;

        $results = [
            'markdowns' => drop_promotion_revert_markdowns($state),
            'clearance' => drop_promotion_revert_clearance($state),
            'bundles' => drop_promotion_store_bundle_rules([], $previousSlug),
        ];

        $errors = [];
        foreach ($results as $key => $result) {
            if (($result['status'] ?? '') === 'error') {
                $errors[$key] = $result['message'] ?? 'Unknown error';
            }
        }

        if (!empty($errors)) {
            drop_promotion_log('deactivation_partial_failure', [
                'slug' => $previousSlug,
                'type' => $previousType,
                'errors' => $errors,
            ]);

            return [
                'status' => 'error',
                'message' => 'Failed to fully deactivate promotion.',
                'errors' => $errors,
                'results' => $results,
            ];
        }

        $resetState = drop_promotion_default_state();
        $resetState['manual_suspend_hash'] = $state['last_config_hash'] ?? null;
        $resetState['manual_suspend_slug'] = $state['active_slug'] ?? null;
        $resetState['manual_suspend_at'] = time();

        if (!drop_promotion_save_state($resetState)) {
            drop_promotion_log('deactivation_failed', [
                'slug' => $previousSlug,
                'type' => $previousType,
                'reason' => 'state_persist',
            ]);

            return ['status' => 'error', 'message' => 'Unable to persist promotion state.', 'results' => $results];
        }

        drop_promotion_log('deactivated', [
            'slug' => $previousSlug,
            'type' => $previousType,
            'restored_markdowns' => $results['markdowns']['restored'] ?? 0,
            'restored_clearance' => $results['clearance']['restored'] ?? 0,
            'bundle_status' => $results['bundles']['status'] ?? null,
        ]);

        return ['status' => 'deactivated', 'state' => $resetState, 'results' => $results];
    }
}

if (!function_exists('drop_promotion_sync')) {
    function drop_promotion_sync(bool $force = false, ?array $overrideConfig = null): array
    {
        $lock = drop_promotion_acquire_lock();
        if ($lock === false) {
            return ['status' => 'locked'];
        }

        try {
            $state = drop_promotion_load_state();
            $config = $overrideConfig ?? drop_promotion_get_active_config();

            if ($config === null) {
                if (!empty($state['active_slug'])) {
                    return drop_promotion_deactivate($state);
                }

                return ['status' => 'idle'];
            }

            $currentSlug = $state['active_slug'] ?? null;
            $incomingSlug = $config['drop_slug'] ?? '';
            $incomingType = $config['promotion']['type'] ?? '';
            $incomingHash = drop_promotion_config_hash($config);
            $previousHash = $state['last_config_hash'] ?? null;

            $manualSuspendHash = $state['manual_suspend_hash'] ?? null;
            if (!$force && $manualSuspendHash !== null) {
                if ($manualSuspendHash === $incomingHash) {
                    return ['status' => 'suspended', 'state' => $state];
                }
            }

            if (!$force && $currentSlug === $incomingSlug && ($state['promotion_type'] ?? null) === $incomingType && $previousHash === $incomingHash) {
                return ['status' => 'already_active', 'state' => $state];
            }

            return drop_promotion_activate($config, $state);
        } finally {
            drop_promotion_release_lock($lock);
        }
    }
}

if (!function_exists('drop_promotion_current_status')) {
    function drop_promotion_current_status(): array
    {
        $state = drop_promotion_load_state();
        $status = empty($state['active_slug']) ? 'idle' : 'active';
        if ($status === 'idle' && !empty($state['manual_suspend_hash'])) {
            $status = 'suspended';
        }

        return [
            'status' => $status,
            'state' => $state,
        ];
    }
}
