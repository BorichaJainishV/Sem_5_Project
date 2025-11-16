<?php
// ---------------------------------------------------------------------
// core/banner_manager.php - Utilities to persist and retrieve flash banners
// ---------------------------------------------------------------------

require_once __DIR__ . '/timezone.php';

if (!defined('FLASH_BANNER_STORAGE_PATH')) {
    define('FLASH_BANNER_STORAGE_PATH', __DIR__ . '/../storage/flash_banner.json');
}

if (!function_exists('mystic_banner_timezone')) {
    function mystic_banner_timezone(): DateTimeZone
    {
        if (function_exists('mystic_app_timezone')) {
            return mystic_app_timezone();
        }

        $defaultTz = date_default_timezone_get() ?: 'UTC';
        try {
            return new DateTimeZone($defaultTz);
        } catch (Throwable $e) {
            return new DateTimeZone('UTC');
        }
    }
}

if (!function_exists('mystic_banner_iso')) {
    function mystic_banner_iso(?int $timestamp): ?string
    {
        if ($timestamp === null) {
            return null;
        }
        try {
            $dt = (new DateTimeImmutable('@' . $timestamp))->setTimezone(mystic_banner_timezone());
            return $dt->format(DateTimeInterface::ATOM);
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('mystic_banner_parse_schedule')) {
    function mystic_banner_parse_schedule($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            $intValue = (int) $value;
            return $intValue > 0 ? $intValue : null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }

            $formats = ['Y-m-d\TH:i', DateTimeInterface::ATOM, DATE_RFC3339, DATE_ISO8601];
            foreach ($formats as $format) {
                $dateTime = DateTime::createFromFormat($format, $value, mystic_banner_timezone());
                if ($dateTime instanceof DateTime) {
                    return $dateTime->getTimestamp();
                }
            }

            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                return $timestamp;
            }
        }

        return null;
    }
}

if (!function_exists('mystic_banner_normalize_list')) {
    function mystic_banner_normalize_list($value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_string($value)) {
            $parts = preg_split('/[\r\n,]+/', $value) ?: [];
        } elseif (is_array($value)) {
            $parts = $value;
        } else {
            return [];
        }

        $normalized = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }
            $normalized[] = $part;
        }

        return array_values(array_unique($normalized));
    }
}

if (!function_exists('mystic_banner_normalize_quantity_map')) {
    function mystic_banner_normalize_quantity_map($value): array
    {
        if ($value === null) {
            return [];
        }

        $entries = [];

        if (is_string($value)) {
            $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];
            foreach ($lines as $line) {
                $entries[] = $line;
            }
        } elseif (is_array($value)) {
            $entries = $value;
        }

        $result = [];
        foreach ($entries as $entry) {
            if (is_array($entry)) {
                $sku = trim((string) ($entry['sku'] ?? ''));
                if ($sku === '' && isset($entry['inventory_id'])) {
                    $sku = trim((string) $entry['inventory_id']);
                }
                $qty = isset($entry['quantity']) ? (int) $entry['quantity'] : 0;
            } else {
                $raw = trim((string) $entry);
                if ($raw === '') {
                    continue;
                }
                $parts = preg_split('/[:|,\-]/', $raw, 2);
                $sku = trim($parts[0] ?? '');
                $qty = isset($parts[1]) ? (int) trim($parts[1]) : 1;
            }

            if ($sku === '') {
                continue;
            }

            if ($qty < 1) {
                $qty = 1;
            }

            $result[] = [
                'sku' => $sku,
                'quantity' => $qty,
            ];
        }

        return $result;
    }
}

if (!function_exists('mystic_banner_normalize_visibility')) {
    function mystic_banner_normalize_visibility($value): array
    {
        $allowed = ['storefront', 'account', 'checkout', 'designer'];

        if ($value === null) {
            return [];
        }

        if (!is_array($value)) {
            $value = preg_split('/[\s,]+/', (string) $value) ?: [];
        }

        $normalized = [];
        foreach ($value as $candidate) {
            $candidate = strtolower(trim((string) $candidate));
            if ($candidate === '') {
                continue;
            }

            if (!in_array($candidate, $allowed, true)) {
                continue;
            }

            if (!in_array($candidate, $normalized, true)) {
                $normalized[] = $candidate;
            }
        }

        return $normalized;
    }
}

/**
 * Load the active flash banner configuration if present.
 */
function get_active_flash_banner(): ?array
{
    $path = FLASH_BANNER_STORAGE_PATH;

    if (!file_exists($path)) {
        return null;
    }

    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return null;
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return null;
    }

    if (empty($data['message'])) {
        return null;
    }

    $mode = isset($data['mode']) && in_array($data['mode'], ['standard', 'drop'], true)
        ? $data['mode']
        : 'standard';
    $data['mode'] = $mode;

    $scheduleStart = isset($data['schedule_start_ts']) ? (int) $data['schedule_start_ts'] : null;
    $scheduleEnd = isset($data['schedule_end_ts']) ? (int) $data['schedule_end_ts'] : null;
    $countdownTarget = isset($data['countdown_target_ts']) ? (int) $data['countdown_target_ts'] : null;

    $now = time();
    $state = 'live';
    if ($mode === 'drop') {
        if ($scheduleStart !== null && $now < $scheduleStart) {
            $state = 'upcoming';
        } elseif ($scheduleEnd !== null && $now > $scheduleEnd) {
            $state = 'ended';
        }
    }

    if (!isset($data['timezone']) || !is_string($data['timezone']) || $data['timezone'] === '') {
        $data['timezone'] = mystic_banner_timezone()->getName();
    }

    $data['schedule_start_ts'] = $scheduleStart;
    $data['schedule_start_iso'] = $data['schedule_start_iso'] ?? mystic_banner_iso($scheduleStart);
    $data['schedule_end_ts'] = $scheduleEnd;
    $data['schedule_end_iso'] = $data['schedule_end_iso'] ?? mystic_banner_iso($scheduleEnd);
    $data['countdown_target_ts'] = $countdownTarget;
    $data['countdown_target_iso'] = $data['countdown_target_iso'] ?? mystic_banner_iso($countdownTarget);
    $data['state'] = $state;
    $data['seconds_to_countdown'] = $countdownTarget !== null ? max(0, $countdownTarget - $now) : null;
    $data['start_at_ts'] = $scheduleStart;
    $data['start_at_iso'] = $data['schedule_start_iso'];
    $data['end_at_ts'] = $scheduleEnd;
    $data['end_at_iso'] = $data['schedule_end_iso'];

    $dropLabel = isset($data['drop_label']) ? trim((string) $data['drop_label']) : '';
    if ($dropLabel === '') {
        $dropSlug = isset($data['drop_slug']) ? trim((string) $data['drop_slug']) : '';
        if ($dropSlug !== '') {
            $dropLabel = $dropSlug;
        } elseif (!empty($data['message'])) {
            $dropLabel = trim((string) $data['message']);
        }
    }
    $data['drop_label'] = $dropLabel;

    $visibility = mystic_banner_normalize_visibility($data['visibility'] ?? []);
    if ($mode === 'drop' && empty($visibility)) {
        $visibility = ['storefront'];
    }
    $data['visibility'] = $visibility;

    $countdownEnabled = array_key_exists('countdown_enabled', $data)
        ? !empty($data['countdown_enabled'])
        : ($countdownTarget !== null);
    $data['countdown_enabled'] = $mode === 'drop' ? $countdownEnabled : false;

    $promotionConfig = isset($data['promotion']) && is_array($data['promotion']) ? $data['promotion'] : [];
    $featuredInventory = [];
    if (isset($data['promotion_featured_inventory']) && is_array($data['promotion_featured_inventory'])) {
        $featuredInventory = array_values(array_unique(array_map('strval', $data['promotion_featured_inventory'])));
    } elseif (isset($promotionConfig['featured_inventory']) && is_array($promotionConfig['featured_inventory'])) {
        $featuredInventory = array_values(array_unique(array_map('strval', $promotionConfig['featured_inventory'])));
    }
    $data['promotion_featured_inventory'] = $featuredInventory;
    $promotionType = isset($promotionConfig['type']) && in_array($promotionConfig['type'], ['price_markdown', 'bundle_bogo', 'clearance', 'custom_design_reward'], true)
        ? $promotionConfig['type']
        : '';

    $data['promotion'] = [
        'type' => $promotionType,
        'markdown' => [
            'mode' => in_array($promotionConfig['markdown']['mode'] ?? '', ['percent', 'fixed'], true)
                ? $promotionConfig['markdown']['mode']
                : 'percent',
            'value' => isset($promotionConfig['markdown']['value']) ? (float) $promotionConfig['markdown']['value'] : 0.0,
            'scope' => in_array($promotionConfig['markdown']['scope'] ?? '', ['all_items', 'sku_list'], true)
                ? $promotionConfig['markdown']['scope']
                : 'all_items',
            'skus' => isset($promotionConfig['markdown']['skus']) && is_array($promotionConfig['markdown']['skus'])
                ? array_values(array_unique(array_map('strval', $promotionConfig['markdown']['skus'])))
                : [],
        ],
        'bundle' => [
            'eligible_skus' => isset($promotionConfig['bundle']['eligible_skus']) && is_array($promotionConfig['bundle']['eligible_skus'])
                ? array_values(array_unique(array_map('strval', $promotionConfig['bundle']['eligible_skus'])))
                : [],
            'free_items' => isset($promotionConfig['bundle']['free_items']) && is_array($promotionConfig['bundle']['free_items'])
                ? array_values(array_filter(array_map(static function ($item) {
                    if (!is_array($item)) {
                        return null;
                    }
                    $sku = isset($item['sku']) ? (string) $item['sku'] : '';
                    if ($sku === '') {
                        return null;
                    }
                    $qty = isset($item['quantity']) ? (int) $item['quantity'] : 1;
                    if ($qty < 1) {
                        $qty = 1;
                    }
                    return ['sku' => $sku, 'quantity' => $qty];
                }, $promotionConfig['bundle']['free_items']), static function ($item) {
                    return $item !== null;
                }))
                : [],
            'limit_per_cart' => isset($promotionConfig['bundle']['limit_per_cart']) ? max(0, (int) $promotionConfig['bundle']['limit_per_cart']) : 0,
        ],
        'clearance' => [
            'skus' => isset($promotionConfig['clearance']['skus']) && is_array($promotionConfig['clearance']['skus'])
                ? array_values(array_unique(array_map('strval', $promotionConfig['clearance']['skus'])))
                : [],
        ],
        'custom_design_reward' => [
            'enabled' => !empty($promotionConfig['custom_design_reward']['enabled']),
            'discount_value' => isset($promotionConfig['custom_design_reward']['discount_value'])
                ? max(0.0, (float) $promotionConfig['custom_design_reward']['discount_value'])
                : 0.0,
        ],
        'featured_inventory' => $featuredInventory,
    ];

    if ($mode === 'drop') {
        mystic_banner_lazy_promotion_sync($data);
    }

    if ($mode === 'drop' && $state === 'ended') {
        return null;
    }

    return $data;
}

if (!function_exists('mystic_banner_lazy_promotion_sync')) {
    function mystic_banner_lazy_promotion_sync(array $banner): void
    {
        static $hasSynced = false;

        if ($hasSynced) {
            return;
        }

        $mode = $banner['mode'] ?? 'standard';
        if ($mode !== 'drop') {
            return;
        }

        if (!function_exists('drop_promotion_sync')) {
            $promotionPath = __DIR__ . '/drop_promotions.php';
            if (file_exists($promotionPath)) {
                @require_once $promotionPath;
            }
        }

        if (!function_exists('drop_promotion_sync')) {
            return;
        }

        $config = [
            'drop_slug' => $banner['drop_slug'] ?? '',
            'schedule_start_ts' => $banner['schedule_start_ts'] ?? null,
            'schedule_end_ts' => $banner['schedule_end_ts'] ?? null,
            'promotion' => $banner['promotion'] ?? [],
        ];

        drop_promotion_sync(false, $config);
        $hasSynced = true;
    }
}

/**
 * Persist a new flash banner payload to disk.
 */
function set_flash_banner(array $banner): bool
{
    $message = trim($banner['message'] ?? '');
    if ($message === '') {
        return false;
    }

    $payload = [
        'id' => $banner['id'] ?? ('banner_' . time()),
        'message' => $message,
        'subtext' => trim($banner['subtext'] ?? ''),
        'cta' => trim($banner['cta'] ?? ''),
        'href' => trim($banner['href'] ?? ''),
        'badge' => trim($banner['badge'] ?? ''),
        'variant' => in_array($banner['variant'] ?? '', ['promo', 'info', 'alert'], true) ? $banner['variant'] : 'promo',
        'dismissible' => !empty($banner['dismissible']),
        'created_at' => $banner['created_at'] ?? time(),
        'updated_at' => time(),
    ];

    $mode = isset($banner['mode']) && in_array($banner['mode'], ['standard', 'drop'], true)
        ? $banner['mode']
        : 'standard';
    $payload['mode'] = $mode;
    $payload['timezone'] = mystic_banner_timezone()->getName();

    $payload['drop_label'] = $mode === 'drop' ? trim((string) ($banner['drop_label'] ?? '')) : '';
    $visibility = $mode === 'drop' ? mystic_banner_normalize_visibility($banner['visibility'] ?? []) : [];
    if ($mode === 'drop' && empty($visibility)) {
        $visibility = ['storefront'];
    }
    $payload['visibility'] = $visibility;

    $scheduleStartTs = mystic_banner_parse_schedule($banner['schedule_start'] ?? null);
    $scheduleEndTs = mystic_banner_parse_schedule($banner['schedule_end'] ?? null);
    $countdownTargetTs = mystic_banner_parse_schedule($banner['countdown_target'] ?? null);

    if ($mode === 'drop' && $scheduleStartTs !== null && $countdownTargetTs === null) {
        $countdownTargetTs = $scheduleStartTs;
    }

    if ($scheduleEndTs !== null && $scheduleStartTs !== null && $scheduleEndTs < $scheduleStartTs) {
        $scheduleEndTs = $scheduleStartTs;
    }

    $payload['drop_slug'] = $mode === 'drop' ? trim((string) ($banner['drop_slug'] ?? '')) : '';
    $payload['schedule_start_ts'] = $scheduleStartTs;
    $payload['schedule_start_iso'] = mystic_banner_iso($scheduleStartTs);
    $payload['schedule_end_ts'] = $scheduleEndTs;
    $payload['schedule_end_iso'] = mystic_banner_iso($scheduleEndTs);
    $countdownEnabled = $mode === 'drop' && !empty($banner['countdown_enabled']);
    $payload['countdown_enabled'] = $countdownEnabled;
    $payload['countdown_target_ts'] = $countdownEnabled ? $countdownTargetTs : null;
    $payload['countdown_target_iso'] = $countdownEnabled ? mystic_banner_iso($countdownTargetTs) : null;
    $payload['countdown_label'] = trim((string) ($banner['countdown_label'] ?? ''));
    $payload['countdown_mode'] = in_array($banner['countdown_mode'] ?? '', ['manual', 'start', 'start_plus5', 'now'], true)
        ? $banner['countdown_mode']
        : 'manual';
    if (!$countdownEnabled) {
        $payload['countdown_mode'] = 'manual';
    }
    $payload['waitlist_enabled'] = !empty($banner['waitlist_enabled']) && $mode === 'drop';
    $payload['waitlist_slug'] = $payload['waitlist_enabled'] ? trim((string) ($banner['waitlist_slug'] ?? '')) : '';
    $payload['waitlist_button_label'] = $payload['waitlist_enabled'] ? trim((string) ($banner['waitlist_button_label'] ?? '')) : '';
    $payload['waitlist_success_copy'] = $payload['waitlist_enabled'] ? trim((string) ($banner['waitlist_success_copy'] ?? '')) : '';

    if ($mode === 'drop') {
        $story = trim((string) ($banner['drop_story'] ?? ''));
        $payload['drop_story'] = $story;
        $payload['drop_teaser'] = trim((string) ($banner['drop_teaser'] ?? ''));

        $rawHighlights = $banner['drop_highlights'] ?? [];
        if (is_string($rawHighlights)) {
            $rawHighlights = preg_split('/\r\n|\r|\n/', $rawHighlights) ?: [];
        }
        if (!is_array($rawHighlights)) {
            $rawHighlights = [];
        }
        $highlights = [];
        foreach ($rawHighlights as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $highlights[] = mb_substr($item, 0, 220);
            }
        }
        $payload['drop_highlights'] = $highlights;

        $payload['drop_access_notes'] = trim((string) ($banner['drop_access_notes'] ?? ''));
        $payload['drop_media_url'] = trim((string) ($banner['drop_media_url'] ?? ''));

        $allowedPromotionTypes = ['', 'price_markdown', 'bundle_bogo', 'clearance', 'custom_design_reward', 'hybrid'];
        $promotionType = isset($banner['promotion_type']) && in_array($banner['promotion_type'], $allowedPromotionTypes, true)
            ? $banner['promotion_type']
            : '';

        $markdownMode = in_array($banner['promotion_markdown_mode'] ?? '', ['percent', 'fixed'], true)
            ? $banner['promotion_markdown_mode']
            : 'percent';
        $markdownScope = in_array($banner['promotion_markdown_scope'] ?? '', ['all_items', 'sku_list'], true)
            ? $banner['promotion_markdown_scope']
            : 'all_items';
        $markdownValue = isset($banner['promotion_markdown_value']) ? max(0.0, (float) $banner['promotion_markdown_value']) : 0.0;
        $markdownSkus = $markdownScope === 'sku_list'
            ? mystic_banner_normalize_list($banner['promotion_markdown_skus'] ?? [])
            : [];

        $bundleEligibleRaw = $banner['promotion_bundle_eligible_skus']
            ?? $banner['promotion_bundle_eligible']
            ?? ($banner['promotion']['bundle']['eligible_skus'] ?? []);
        $bundleEligible = mystic_banner_normalize_list($bundleEligibleRaw);
        $bundleFreeItems = mystic_banner_normalize_quantity_map($banner['promotion_bundle_free_items'] ?? []);
        $bundleLimitRaw = $banner['promotion_bundle_limit_per_cart']
            ?? $banner['promotion_bundle_limit']
            ?? ($banner['promotion']['bundle']['limit_per_cart'] ?? 0);
        $bundleLimit = max(0, (int) $bundleLimitRaw);

        $clearanceSkus = mystic_banner_normalize_list($banner['promotion_clearance_skus'] ?? []);
        $featuredInventory = mystic_banner_normalize_list($banner['promotion_featured_inventory'] ?? []);

        $customRewardEnabled = !empty($banner['promotion_custom_reward_enabled'])
            || !empty($banner['promotion']['custom_design_reward']['enabled']);
        $customRewardDiscountRaw = $banner['promotion_custom_reward_discount']
            ?? $banner['promotion_custom_reward_value']
            ?? ($banner['promotion']['custom_design_reward']['discount_value'] ?? 0.0);
        $customRewardDiscount = max(0.0, (float) $customRewardDiscountRaw);

        $rawFeatureList = $banner['promotion_features'] ?? ($banner['promotion']['features'] ?? []);
        if (!is_array($rawFeatureList)) {
            $rawFeatureList = [];
        }

        $promotionFeatures = [];
        foreach ($rawFeatureList as $featureCandidate) {
            $featureCandidate = trim((string) $featureCandidate);
            if ($featureCandidate !== '') {
                $promotionFeatures[] = $featureCandidate;
            }
        }

        if ($customRewardEnabled) {
            $promotionFeatures[] = 'custom_design_reward';
        }

        $allowedFeatureValues = ['price_markdown', 'bundle_bogo', 'clearance', 'custom_design_reward'];
        $promotionFeatures = array_values(array_filter(array_unique($promotionFeatures), static function ($value) use ($allowedFeatureValues) {
            return in_array($value, $allowedFeatureValues, true);
        }));

        if (empty($promotionFeatures)) {
            if ($markdownValue > 0.0) {
                $promotionFeatures[] = 'price_markdown';
            }
            if (!empty($bundleEligible) || !empty($bundleFreeItems)) {
                $promotionFeatures[] = 'bundle_bogo';
            }
            if (!empty($clearanceSkus)) {
                $promotionFeatures[] = 'clearance';
            }
            if ($customRewardEnabled) {
                $promotionFeatures[] = 'custom_design_reward';
            }

            $promotionFeatures = array_values(array_filter(array_unique($promotionFeatures), static function ($value) use ($allowedFeatureValues) {
                return in_array($value, $allowedFeatureValues, true);
            }));
        }

        if (count($promotionFeatures) > 1) {
            $promotionType = 'hybrid';
        } elseif (count($promotionFeatures) === 1) {
            $singleFeature = $promotionFeatures[0];
            if ($promotionType === '' || $promotionType === 'hybrid') {
                $promotionType = $singleFeature;
            }
        } elseif ($promotionType === 'hybrid') {
            $promotionType = '';
        }

        $payload['promotion'] = [
            'type' => $promotionType,
            'features' => $promotionFeatures,
            'markdown' => [
                'mode' => $markdownMode,
                'value' => $markdownValue,
                'scope' => $markdownScope,
                'skus' => $markdownSkus,
            ],
            'bundle' => [
                'eligible_skus' => $bundleEligible,
                'free_items' => $bundleFreeItems,
                'limit_per_cart' => $bundleLimit,
            ],
            'clearance' => [
                'skus' => $clearanceSkus,
            ],
            'custom_design_reward' => [
                'enabled' => $customRewardEnabled,
                'discount_value' => $customRewardDiscount,
            ],
            'featured_inventory' => $featuredInventory,
        ];
        $payload['promotion_features'] = $promotionFeatures;
        $payload['promotion_featured_inventory'] = $featuredInventory;
    } else {
        $payload['drop_story'] = '';
        $payload['drop_teaser'] = '';
        $payload['drop_highlights'] = [];
        $payload['drop_access_notes'] = '';
        $payload['drop_media_url'] = '';
        $payload['promotion'] = [
            'type' => '',
            'markdown' => [
                'mode' => 'percent',
                'value' => 0.0,
                'scope' => 'all_items',
                'skus' => [],
            ],
            'bundle' => [
                'eligible_skus' => [],
                'free_items' => [],
                'limit_per_cart' => 0,
            ],
            'clearance' => [
                'skus' => [],
            ],
            'custom_design_reward' => [
                'enabled' => false,
                'discount_value' => 0.0,
            ],
            'featured_inventory' => [],
        ];
        $payload['promotion_features'] = [];
        $payload['promotion_featured_inventory'] = [];
    }

    $dir = dirname(FLASH_BANNER_STORAGE_PATH);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
    }

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    return file_put_contents(FLASH_BANNER_STORAGE_PATH, $json) !== false;
}

/**
 * Remove the current flash banner config.
 */
function clear_flash_banner(): void
{
    $path = FLASH_BANNER_STORAGE_PATH;
    if (file_exists($path)) {
        @unlink($path);
    }
}
