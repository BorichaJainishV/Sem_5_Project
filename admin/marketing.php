<?php
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        if (!isset($_SESSION['admin_id'])) {
            header('Location: index.php');
            exit();
        }

        require_once __DIR__ . '/../db_connection.php';
        require_once __DIR__ . '/activity_logger.php';
        require_once __DIR__ . '/../core/banner_manager.php';
        require_once __DIR__ . '/../core/drop_promotions.php';

        if (!function_exists('marketing_format_inventory_label')) {
            function marketing_format_inventory_label(array $row): string
            {
                $inventoryId = isset($row['inventory_id']) ? (int) $row['inventory_id'] : 0;
                $name = trim((string) ($row['product_name'] ?? ''));
                $stockQty = isset($row['stock_qty']) ? (int) $row['stock_qty'] : null;
                $price = isset($row['price']) ? (float) $row['price'] : null;

                $parts = [];

                if ($inventoryId > 0) {
                    $parts[] = '#' . $inventoryId;
                }

                if ($name !== '') {
                    $parts[] = $name;
                } elseif ($inventoryId > 0) {
                    $parts[] = 'Inventory #' . $inventoryId;
                }

                if ($stockQty !== null) {
                    $parts[] = $stockQty . ' in stock';
                }

                if ($price !== null) {
                    $parts[] = 'Rs ' . number_format($price, 2, '.', '');
                }

                return implode(' • ', array_filter($parts, static function ($part) {
                    return $part !== '';
                }));
            }
        }

        if (!function_exists('marketing_collect_inventory_options')) {
            function marketing_collect_inventory_options(mysqli $conn): array
            {
                $options = [];
                $lookup = [];
                $result = null;

                try {
                    $query = 'SELECT inventory_id, product_name, stock_qty, price, is_archived FROM inventory WHERE (is_archived IS NULL OR is_archived = 0) AND stock_qty > 0';
                    $result = $conn->query($query);
                } catch (mysqli_sql_exception $filteredQueryError) {
                    error_log('Marketing inventory lookup failed, falling back to unfiltered query: ' . $filteredQueryError->getMessage());
                    try {
                        $result = $conn->query('SELECT inventory_id, product_name, stock_qty, price FROM inventory');
                    } catch (mysqli_sql_exception $unfilteredQueryError) {
                        error_log('Marketing inventory fallback query failed: ' . $unfilteredQueryError->getMessage());
                        return ['options' => $options, 'lookup' => $lookup];
                    }
                }

                if ($result instanceof mysqli_result) {
                    while ($row = $result->fetch_assoc()) {
                        $id = isset($row['inventory_id']) ? (int) $row['inventory_id'] : 0;
                        if ($id <= 0) {
                            continue;
                        }

                        if (isset($row['is_archived']) && (int) $row['is_archived'] === 1) {
                            continue;
                        }

                        $stockQty = isset($row['stock_qty']) ? (int) $row['stock_qty'] : null;
                        if ($stockQty !== null && $stockQty <= 0) {
                            continue;
                        }

                        $lookup[$id] = $row;
                        $options[] = [
                            'id' => $id,
                            'label' => marketing_format_inventory_label($row),
                        ];
                    }
                    $result->free();
                }

                usort($options, static function ($left, $right) {
                    return strcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
                });

                return ['options' => $options, 'lookup' => $lookup];
            }
        }

        if (!function_exists('marketing_format_datetime_local')) {
            function marketing_format_datetime_local($value): string
            {
                if ($value === null || $value === '') {
                    return '';
                }

                try {
                    if ($value instanceof DateTimeInterface) {
                        $dateTime = $value;
                    } elseif (is_numeric($value)) {
                        $timestamp = (int) $value;
                        if ($timestamp <= 0) {
                            return '';
                        }
                        $dateTime = (new DateTimeImmutable('@' . $timestamp))->setTimezone(mystic_banner_timezone());
                    } elseif (is_string($value)) {
                        $value = trim($value);
                        if ($value === '') {
                            return '';
                        }
                        $dateTime = new DateTimeImmutable($value, mystic_banner_timezone());
                    } else {
                        return '';
                    }

                    return $dateTime->format('Y-m-d\TH:i');
                } catch (Throwable $e) {
                    return '';
                }
            }
        }

        if (!function_exists('marketing_format_decimal_input')) {
            function marketing_format_decimal_input($value, int $precision = 2): string
            {
                if ($value === null) {
                    return '';
                }

                if (is_string($value)) {
                    $value = trim($value);
                }

                if ($value === '') {
                    return '';
                }

                if (!is_numeric($value)) {
                    return '';
                }

                $number = (float) $value;
                return number_format($number, $precision, '.', '');
            }
        }

        if (!function_exists('marketing_highlights_to_text')) {
            function marketing_highlights_to_text($value): string
            {
                if (is_string($value)) {
                    return trim($value);
                }

                if (!is_array($value)) {
                    return '';
                }

                $lines = [];
                foreach ($value as $entry) {
                    $entry = trim((string) $entry);
                    if ($entry !== '') {
                        $lines[] = $entry;
                    }
                }

                return implode(PHP_EOL, $lines);
            }
        }

        if (!function_exists('marketing_text_to_highlights')) {
            function marketing_text_to_highlights(string $text): array
            {
                $text = trim($text);
                if ($text === '') {
                    return [];
                }

                $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
                $highlights = [];
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line !== '') {
                        $highlights[] = mb_substr($line, 0, 220);
                    }
                }

                return $highlights;
            }
        }

        if (!function_exists('marketing_build_free_items_text')) {
            function marketing_build_free_items_text($freeItems): string
            {
                if (!is_array($freeItems)) {
                    return '';
                }

                $lines = [];
                foreach ($freeItems as $key => $value) {
                    $inventoryId = $key;
                    $quantity = $value;

                    if (is_array($value)) {
                        $inventoryId = $value['inventory_id'] ?? $inventoryId;
                        $quantity = $value['quantity'] ?? ($value['qty'] ?? 1);
                    }

                    $inventoryId = (int) $inventoryId;
                    $quantity = max(1, (int) $quantity);

                    if ($inventoryId > 0) {
                        $lines[] = $inventoryId . ':' . $quantity;
                    }
                }

                return implode(PHP_EOL, $lines);
            }
        }

        if (!function_exists('marketing_hydrate_inventory_labels')) {
            function marketing_hydrate_inventory_labels(array $inventoryIds, array $inventoryLookup): array
            {
                $labels = [];

                foreach ($inventoryIds as $rawId) {
                    $inventoryId = (int) $rawId;
                    if ($inventoryId <= 0) {
                        continue;
                    }

                    if (isset($inventoryLookup[$inventoryId])) {
                        $labels[] = marketing_format_inventory_label($inventoryLookup[$inventoryId]);
                        continue;
                    }

                    $labels[] = '#' . $inventoryId;
                }

                return $labels;
            }
        }

        if (!function_exists('marketing_parse_free_items_text')) {
            function marketing_parse_free_items_text(string $text): array
            {
                $lines = preg_split('/\r\n|\r|\n/', trim($text)) ?: [];
                $map = [];

                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }

                    if (strpos($line, ':') !== false) {
                        [$idPart, $qtyPart] = array_map('trim', explode(':', $line, 2));
                    } else {
                        $idPart = $line;
                        $qtyPart = '1';
                    }

                    $inventoryId = (int) $idPart;
                    $quantity = max(1, (int) $qtyPart);

                    if ($inventoryId > 0) {
                        $map[$inventoryId] = $quantity;
                    }
                }

                return $map;
            }
        }

        if (!function_exists('marketing_build_promotion_flash')) {
            function marketing_build_promotion_flash($syncResult): ?array
            {
                if (!is_array($syncResult)) {
                    return null;
                }

                $status = (string) ($syncResult['status'] ?? '');
                $error = $syncResult['error'] ?? null;
                $message = trim((string) ($syncResult['message'] ?? ''));

                if ($status === 'error' || ($error !== null && $error !== '')) {
                    return ['type' => 'error', 'message' => $message !== '' ? $message : (string) $error];
                }

                $warnings = $syncResult['warnings'] ?? [];
                if (is_array($warnings) && !empty($warnings)) {
                    $merged = implode(' ', array_map('trim', $warnings));
                    return ['type' => 'warning', 'message' => trim($merged)];
                }

                if ($message !== '') {
                    return ['type' => 'success', 'message' => $message];
                }

                if ($status === 'success') {
                    return ['type' => 'success', 'message' => 'Promotion synced successfully.'];
                }

                return null;
            }
        }

        $flashMessage = $_SESSION['marketing_flash'] ?? null;
        unset($_SESSION['marketing_flash']);

        $promotionFlash = $_SESSION['promotion_flash'] ?? null;
        unset($_SESSION['promotion_flash']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            $adminId = (int) ($_SESSION['admin_id'] ?? 0);

            if ($action === 'save') {
                $message = trim((string) ($_POST['message'] ?? ''));
                if ($message === '') {
                    $flashMessage = ['type' => 'error', 'message' => 'Banner headline is required before saving.'];
                } else {
                    $isDropModeSelected = !empty($_POST['drop_mode']);

                    $payload = [
                        'message' => $message,
                        'subtext' => trim((string) ($_POST['subtext'] ?? '')),
                        'cta' => trim((string) ($_POST['cta'] ?? '')),
                        'href' => trim((string) ($_POST['href'] ?? '')),
                        'badge' => trim((string) ($_POST['badge'] ?? '')),
                        'variant' => $_POST['variant'] ?? 'promo',
                        'dismissible' => !empty($_POST['dismissible']),
                        'mode' => $isDropModeSelected ? 'drop' : 'standard',
                    ];

                    $validationErrors = [];

                    if ($isDropModeSelected) {
                        $payload['drop_slug'] = trim((string) ($_POST['drop_slug'] ?? ''));
                        $payload['drop_label'] = trim((string) ($_POST['drop_label'] ?? ''));
                        $payload['schedule_start'] = $_POST['schedule_start'] ?? null;
                        $payload['schedule_end'] = $_POST['schedule_end'] ?? null;
                        $payload['countdown_target'] = $_POST['countdown_target'] ?? null;
                        $payload['countdown_enabled'] = !empty($_POST['countdown_enabled']);
                        $payload['countdown_mode'] = $_POST['countdown_mode'] ?? 'manual';
                        $payload['countdown_label'] = trim((string) ($_POST['countdown_label'] ?? ''));
                        $payload['visibility'] = isset($_POST['visibility']) && is_array($_POST['visibility']) ? $_POST['visibility'] : [];

                        $payload['waitlist_enabled'] = !empty($_POST['waitlist_enabled']);
                        $payload['waitlist_slug'] = trim((string) ($_POST['waitlist_slug'] ?? ''));
                        $payload['waitlist_button_label'] = trim((string) ($_POST['waitlist_button_label'] ?? ''));
                        $payload['waitlist_success_copy'] = trim((string) ($_POST['waitlist_success_copy'] ?? ''));

                        $payload['drop_teaser'] = trim((string) ($_POST['drop_teaser'] ?? ''));
                        $payload['drop_story'] = trim((string) ($_POST['drop_story'] ?? ''));
                        $payload['drop_highlights'] = marketing_text_to_highlights((string) ($_POST['drop_highlights'] ?? ''));
                        $payload['drop_access_notes'] = trim((string) ($_POST['drop_access_notes'] ?? ''));
                        $payload['drop_media_url'] = trim((string) ($_POST['drop_media_url'] ?? ''));

                        $promotionFeatures = isset($_POST['promotion_features']) && is_array($_POST['promotion_features'])
                            ? array_values(array_filter(array_map('strval', $_POST['promotion_features'])))
                            : [];

                        $payload['promotion_features'] = $promotionFeatures;
                        $payload['promotion_markdown_mode'] = $_POST['promotion_markdown_mode'] ?? 'percent';
                        $payload['promotion_markdown_value'] = $_POST['promotion_markdown_value'] ?? '';
                        $payload['promotion_markdown_scope'] = $_POST['promotion_markdown_scope'] ?? 'all_items';
                        $payload['promotion_markdown_skus'] = $_POST['promotion_markdown_skus'] ?? '';

                        $payload['promotion_bundle_eligible_skus'] = isset($_POST['promotion_bundle_eligible_skus']) && is_array($_POST['promotion_bundle_eligible_skus'])
                            ? array_values(array_map('intval', $_POST['promotion_bundle_eligible_skus']))
                            : [];
                        $payload['promotion_bundle_free_items'] = marketing_parse_free_items_text((string) ($_POST['promotion_bundle_free_items'] ?? ''));
                        $payload['promotion_bundle_limit'] = (int) ($_POST['promotion_bundle_limit'] ?? 0);

                        $payload['promotion_clearance_skus'] = isset($_POST['promotion_clearance_skus']) && is_array($_POST['promotion_clearance_skus'])
                            ? array_values(array_map('intval', $_POST['promotion_clearance_skus']))
                            : [];

                        $rewardValue = (float) ($_POST['promotion_custom_reward_value'] ?? 0);
                        $payload['promotion_custom_reward_value'] = $rewardValue;
                        $payload['promotion_custom_reward_enabled'] = $rewardValue > 0;

                        $payload['promotion_featured_inventory'] = isset($_POST['promotion_featured_inventory']) && is_array($_POST['promotion_featured_inventory'])
                            ? array_values(array_map('intval', $_POST['promotion_featured_inventory']))
                            : [];

                        if ($payload['drop_slug'] === '') {
                            $validationErrors[] = 'Drop slug is required when drop mode is enabled.';
                        }

                        $scheduleStartValue = trim((string) ($payload['schedule_start'] ?? ''));
                        if ($scheduleStartValue === '') {
                            $validationErrors[] = 'Drop start time must be set before enabling drop mode.';
                        }

                        if (!empty($payload['waitlist_enabled']) && $payload['waitlist_slug'] === '') {
                            $validationErrors[] = 'Waitlist slug is required when the waitlist toggle is on.';
                        }
                    }

                    if (!empty($validationErrors)) {
                        $flashMessage = ['type' => 'error', 'message' => implode(' ', $validationErrors)];
                    } elseif (set_flash_banner($payload)) {
                        log_admin_activity($adminId, 'marketing_banner_saved', [
                            'mode' => $payload['mode'],
                            'drop_slug' => $payload['drop_slug'] ?? null,
                        ]);

                        $syncResult = drop_promotion_sync(true);
                        $promotionFlashMessage = marketing_build_promotion_flash($syncResult);

                        $primaryFlash = ['type' => 'success', 'message' => 'Banner settings saved successfully.'];
                        if ($promotionFlashMessage !== null) {
                            $promotionType = $promotionFlashMessage['type'] ?? 'info';
                            if ($promotionType === 'error') {
                                $primaryFlash = ['type' => 'error', 'message' => 'Banner saved, but promotion sync failed: ' . $promotionFlashMessage['message']];
                            } elseif ($promotionType === 'warning') {
                                $primaryFlash = $promotionFlashMessage;
                            }
                            $_SESSION['promotion_flash'] = $promotionFlashMessage;
                        }

                        $_SESSION['marketing_flash'] = $primaryFlash;

                        header('Location: marketing.php');
                        exit();
                    }

                    if (!isset($_SESSION['marketing_flash'])) {
                        $flashMessage = ['type' => 'error', 'message' => 'Unable to persist banner configuration.'];
                    }
                }
            } elseif ($action === 'clear') {
                clear_flash_banner();
                log_admin_activity($adminId, 'marketing_banner_cleared');

                $syncResult = drop_promotion_sync(true);
                $promotionFlashMessage = marketing_build_promotion_flash($syncResult);

                $primaryFlash = ['type' => 'success', 'message' => 'Banner disabled and storefront reset.'];
                if ($promotionFlashMessage !== null) {
                    $promotionType = $promotionFlashMessage['type'] ?? 'info';
                    if ($promotionType === 'error') {
                        $primaryFlash = ['type' => 'error', 'message' => $promotionFlashMessage['message']];
                    } elseif ($promotionType === 'warning') {
                        $primaryFlash = $promotionFlashMessage;
                    }
                    $_SESSION['promotion_flash'] = $promotionFlashMessage;
                }

                $_SESSION['marketing_flash'] = $primaryFlash;

                header('Location: marketing.php');
                exit();
            } elseif ($action === 'resync') {
                $syncResult = drop_promotion_sync(true);
                $promotionFlashMessage = marketing_build_promotion_flash($syncResult);

                $primaryFlash = ['type' => 'info', 'message' => 'Force resync requested.'];
                if ($promotionFlashMessage !== null) {
                    $promotionType = $promotionFlashMessage['type'] ?? 'info';
                    if ($promotionType === 'error') {
                        $primaryFlash = ['type' => 'error', 'message' => $promotionFlashMessage['message']];
                    } elseif ($promotionType === 'warning') {
                        $primaryFlash = $promotionFlashMessage;
                    }
                    $_SESSION['promotion_flash'] = $promotionFlashMessage;
                }

                $_SESSION['marketing_flash'] = $primaryFlash;

                header('Location: marketing.php');
                exit();
            } else {
                $flashMessage = ['type' => 'error', 'message' => 'Unknown action requested.'];
            }
        }
$currentBanner = get_active_flash_banner();
if (!is_array($currentBanner)) {
    $currentBanner = [];
}

$activePromotion = $currentBanner['promotion'] ?? [];
if (!is_array($activePromotion)) {
    $activePromotion = [];
}

$promotionStatusResult = drop_promotion_current_status();
$promotionState = isset($promotionStatusResult['state']) && is_array($promotionStatusResult['state'])
    ? $promotionStatusResult['state']
    : drop_promotion_default_state();
$promotionStatus = $promotionStatusResult['status'] ?? 'idle';

$isDropMode = ($currentBanner['mode'] ?? '') === 'drop';
$stepTwoStartsOpen = $isDropMode;
$stepThreeStartsOpen = $isDropMode;

$messageValue = $currentBanner['message'] ?? '';
$subtextValue = $currentBanner['subtext'] ?? '';
$ctaValue = $currentBanner['cta'] ?? '';
$hrefValue = $currentBanner['href'] ?? '';
$badgeValue = $currentBanner['badge'] ?? '';
$variantCurrent = $currentBanner['variant'] ?? 'promo';
$dismissibleCurrent = !empty($currentBanner['dismissible']);

$bannerTimezoneName = '';
$bannerTimezoneAbbr = '';
try {
    $bannerTz = mystic_banner_timezone();
    if ($bannerTz instanceof DateTimeZone) {
        $bannerTimezoneName = $bannerTz->getName();
        $bannerTimezoneAbbr = (new DateTimeImmutable('now', $bannerTz))->format('T');
    }
} catch (Throwable $e) {
    $bannerTimezoneName = date_default_timezone_get() ?: 'UTC';
    $bannerTimezoneAbbr = 'UTC';
}

$bannerTimezoneDisplay = $bannerTimezoneName !== ''
    ? trim($bannerTimezoneName . ($bannerTimezoneAbbr !== '' ? ' (' . $bannerTimezoneAbbr . ')' : ''))
    : '';

$dropSlugValue = $currentBanner['drop_slug'] ?? '';
$dropLabelValue = $currentBanner['drop_label'] ?? '';
$countdownLabelValue = $currentBanner['countdown_label'] ?? '';
$startValue = marketing_format_datetime_local($currentBanner['schedule_start_ts'] ?? null);
$endValue = marketing_format_datetime_local($currentBanner['schedule_end_ts'] ?? null);
$countdownValue = marketing_format_datetime_local($currentBanner['countdown_target_ts'] ?? null);
$countdownEnabledCurrent = !empty($currentBanner['countdown_enabled']);
$currentCountdownMode = $currentBanner['countdown_mode'] ?? 'manual';

$visibilityOptions = [
    'storefront' => 'Storefront',
    'account' => 'Account dashboard',
    'checkout' => 'Checkout flow',
    'designer' => 'Designer hub',
];
$visibilityCurrent = isset($currentBanner['visibility']) && is_array($currentBanner['visibility'])
    ? $currentBanner['visibility']
    : [];

$waitlistEnabledCurrent = !empty($currentBanner['waitlist_enabled']);
$waitlistSlugValue = $currentBanner['waitlist_slug'] ?? '';
$waitlistButtonValue = $currentBanner['waitlist_button_label'] ?? '';
$waitlistSuccessValue = $currentBanner['waitlist_success_copy'] ?? '';

$dropTeaserValue = $currentBanner['drop_teaser'] ?? '';
$dropStoryValue = $currentBanner['drop_story'] ?? '';
$dropHighlightsValue = marketing_highlights_to_text($currentBanner['drop_highlights'] ?? []);
$dropAccessNotesValue = $currentBanner['drop_access_notes'] ?? '';
$dropMediaUrlValue = $currentBanner['drop_media_url'] ?? '';

$promotionFeaturesCurrent = $currentBanner['promotion_features'] ?? ($activePromotion['features'] ?? []);
if (!is_array($promotionFeaturesCurrent)) {
    $promotionFeaturesCurrent = [];
}

$promotionMarkdown = $activePromotion['markdown'] ?? [];
$promotionMarkdownModeCurrent = $promotionMarkdown['mode'] ?? 'percent';
$promotionMarkdownValueInput = marketing_format_decimal_input($promotionMarkdown['value'] ?? 0);
$promotionMarkdownScopeCurrent = $promotionMarkdown['scope'] ?? 'all_items';
$promotionMarkdownSkusValue = isset($promotionMarkdown['skus']) && is_array($promotionMarkdown['skus'])
    ? implode(', ', $promotionMarkdown['skus'])
    : '';

$promotionBundle = $activePromotion['bundle'] ?? [];
$promotionBundleEligibleCurrent = isset($promotionBundle['eligible_skus']) && is_array($promotionBundle['eligible_skus'])
    ? array_values(array_map('intval', $promotionBundle['eligible_skus']))
    : [];
$promotionBundleFreeItemsValue = marketing_build_free_items_text($promotionBundle['free_items'] ?? []);
$promotionBundleLimitValue = isset($promotionBundle['limit_per_cart']) ? (int) $promotionBundle['limit_per_cart'] : 0;

$promotionClearanceCurrent = isset($activePromotion['clearance']['skus']) && is_array($activePromotion['clearance']['skus'])
    ? array_values(array_map('intval', $activePromotion['clearance']['skus']))
    : [];

$promotionCustomReward = $activePromotion['custom_design_reward'] ?? [];
$promotionRewardEnabledCurrent = !empty($promotionCustomReward['enabled']);
$promotionRewardValueInput = marketing_format_decimal_input($promotionCustomReward['discount_value'] ?? 0);

$promotionFeaturedInventoryCurrent = isset($activePromotion['featured_inventory']) && is_array($activePromotion['featured_inventory'])
    ? array_values(array_map('intval', $activePromotion['featured_inventory']))
    : (isset($currentBanner['promotion_featured_inventory']) && is_array($currentBanner['promotion_featured_inventory'])
        ? array_values(array_map('intval', $currentBanner['promotion_featured_inventory']))
        : []);

$promotionSlug = $promotionState['active_slug'] ?? ($currentBanner['drop_slug'] ?? '');
$promotionTypeLabel = $promotionState['promotion_type'] ?? ($activePromotion['type'] ?? '');
if ($promotionTypeLabel === '' && !empty($promotionFeaturesCurrent)) {
    $promotionTypeLabel = implode(', ', array_map(static function ($feature) {
        return ucwords(str_replace('_', ' ', (string) $feature));
    }, $promotionFeaturesCurrent));
}
if ($promotionTypeLabel === '') {
    $promotionTypeLabel = 'Standard';
}

$inventoryData = marketing_collect_inventory_options($conn);
$promotionInventoryOptions = $inventoryData['options'];
$promotionInventoryLookup = $inventoryData['lookup'];

$trackedInventoryIds = array_unique(array_filter(array_merge(
    $promotionFeaturedInventoryCurrent,
    $promotionBundleEligibleCurrent,
    $promotionClearanceCurrent
)));

$optionIds = array_column($promotionInventoryOptions, 'id');
$optionSet = array_flip($optionIds);
foreach ($trackedInventoryIds as $trackedId) {
    if (!isset($promotionInventoryLookup[$trackedId])) {
        $promotionInventoryLookup[$trackedId] = [
            'inventory_id' => $trackedId,
            'product_name' => 'Inventory #' . $trackedId,
            'stock_qty' => null,
            'price' => null,
        ];
    }

    if (!isset($optionSet[$trackedId])) {
        $promotionInventoryOptions[] = [
            'id' => $trackedId,
            'label' => marketing_format_inventory_label($promotionInventoryLookup[$trackedId]),
        ];
    }
}

usort($promotionInventoryOptions, static function ($left, $right) {
    return strcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
});

$promotionFeaturedInventoryLabels = marketing_hydrate_inventory_labels($promotionFeaturedInventoryCurrent, $promotionInventoryLookup);
$promotionBundleEligibleLabels = marketing_hydrate_inventory_labels($promotionBundleEligibleCurrent, $promotionInventoryLookup);
$promotionClearanceLabels = marketing_hydrate_inventory_labels($promotionClearanceCurrent, $promotionInventoryLookup);

switch ($promotionStatus) {
    case 'active':
        $promotionStatusBadgeClass = 'bg-emerald-500/20 text-emerald-100';
        $promotionStatusDotClass = 'bg-emerald-400';
        $promotionStatusLabel = 'Automation live';
        break;
    case 'suspended':
        $promotionStatusBadgeClass = 'bg-amber-500/20 text-amber-100';
        $promotionStatusDotClass = 'bg-amber-400';
        $promotionStatusLabel = 'Automation paused';
        break;
    default:
        $promotionStatusBadgeClass = 'bg-slate-500/20 text-slate-100';
        $promotionStatusDotClass = 'bg-slate-400';
        $promotionStatusLabel = 'Automation idle';
        break;
}

$promotionStatusWarnings = [];
$promotionFeatureFlags = isset($promotionState['promotion_features']) && is_array($promotionState['promotion_features'])
    ? $promotionState['promotion_features']
    : [];
$promotionMarkdownSummary = isset($promotionState['applied_markdowns'][0]) ? $promotionState['applied_markdowns'][0] : null;
$promotionBundleRules = isset($promotionState['bundle_rules']) && is_array($promotionState['bundle_rules']) ? $promotionState['bundle_rules'] : [];
$promotionClearanceSkusState = isset($promotionState['clearance_skus']) && is_array($promotionState['clearance_skus']) ? $promotionState['clearance_skus'] : [];
$promotionCustomRewardState = isset($promotionState['custom_reward']) && is_array($promotionState['custom_reward']) ? $promotionState['custom_reward'] : null;

if (!empty($promotionState['manual_suspend_at'])) {
    $promotionStatusWarnings[] = 'Automation is manually paused. Save new drop settings or force a resync to resume.';
}

if (in_array('price_markdown', $promotionFeatureFlags, true)) {
    $markdownValue = $promotionMarkdownSummary['value'] ?? 0.0;
    if ($markdownValue <= 0.0) {
        $promotionStatusWarnings[] = 'Price markdown is enabled but no discount value is configured.';
    }
    if (($promotionMarkdownSummary['scope'] ?? '') === 'sku_list' && empty($promotionMarkdownSummary['skus'] ?? [])) {
        $promotionStatusWarnings[] = 'Markdown scope targets specific SKUs, but none are selected.';
    }
}

if (in_array('bundle_bogo', $promotionFeatureFlags, true) && empty($promotionBundleRules)) {
    $promotionStatusWarnings[] = 'Bundle automation is enabled but bundle rules are missing.';
}

if (in_array('clearance', $promotionFeatureFlags, true) && empty($promotionClearanceSkusState)) {
    $promotionStatusWarnings[] = 'Clearance mode is active without any inventory assigned.';
}

if (in_array('custom_design_reward', $promotionFeatureFlags, true)) {
    $rewardValue = isset($promotionCustomRewardState['discount_value']) ? (float) $promotionCustomRewardState['discount_value'] : 0.0;
    if ($rewardValue <= 0.0) {
        $promotionStatusWarnings[] = 'Creator rewards are enabled but the reward amount is zero.';
    }
}

$manualOverrideStatusMessage = 'All changes saved &middot; overrides ready.';
if (!empty($promotionState['manual_suspend_at'])) {
    $manualOverrideStatusMessage = 'Manual pause active — automation stays off until you force a resync or save new drop settings.';
} elseif ($promotionStatus === 'active') {
    $manualOverrideStatusMessage = 'Drop automation live — updates sync immediately after saving.';
}

?>
<?php $ADMIN_TITLE = 'Marketing Tools'; require_once __DIR__ . '/_header.php'; ?>
<?php $ADMIN_BODY_CLASS = 'min-h-screen bg-gradient-to-br from-slate-900 via-indigo-900 to-slate-800 text-slate-50'; ?>
<?php $ADMIN_TITLE = 'Marketing Tools'; require_once __DIR__ . '/_header.php'; ?>
<main class="flex-1 p-10 space-y-8">
        <div>
            <h1 class="text-3xl font-bold mb-2 text-white">Marketing Tools</h1>
            <p class="text-sm text-indigo-100/80">Control the storefront banner, promotion automation, and drop scheduling.</p>
        </div>

        <nav class="grid gap-2 sm:grid-cols-3 text-xs md:text-sm font-semibold text-indigo-100/80">
            <a href="#step-banner-basics" class="rounded-xl border border-white/10 bg-white/5 px-4 py-2 hover:bg-white/10 transition">1. Banner basics</a>
            <a href="#step-automation" class="rounded-xl border border-white/10 bg-white/5 px-4 py-2 hover:bg-white/10 transition">2. Automation toolkit</a>
            <a href="#step-drop-scheduler" class="rounded-xl border border-white/10 bg-white/5 px-4 py-2 hover:bg-white/10 transition">3. Drop scheduler</a>
        </nav>

        <?php if ($flashMessage): ?>
            <div class="rounded-xl border border-white/10 px-4 py-3 text-sm <?php echo ($flashMessage['type'] ?? '') === 'success' ? 'bg-emerald-500/20 text-emerald-100' : (($flashMessage['type'] ?? '') === 'error' ? 'bg-red-500/20 text-red-100' : 'bg-slate-500/20 text-slate-100'); ?>">
                <?php echo htmlspecialchars($flashMessage['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($promotionFlash): ?>
            <div class="rounded-xl border border-white/10 px-4 py-3 text-sm <?php echo ($promotionFlash['type'] ?? '') === 'success' ? 'bg-emerald-500/20 text-emerald-100' : (($promotionFlash['type'] ?? '') === 'error' ? 'bg-red-500/20 text-red-100' : 'bg-indigo-500/20 text-indigo-100'); ?>">
                <?php echo htmlspecialchars($promotionFlash['message']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-8">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <section id="promotion-status-panel" class="bg-white/10 border border-white/10 rounded-2xl shadow-lg p-6 backdrop-blur space-y-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-white">Promotion status</h2>
                        <p class="text-sm text-indigo-100/80"><?php echo htmlspecialchars(strip_tags($manualOverrideStatusMessage)); ?></p>
                    </div>
                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-semibold <?php echo htmlspecialchars($promotionStatusBadgeClass); ?>">
                        <span class="inline-block h-2.5 w-2.5 rounded-full <?php echo htmlspecialchars($promotionStatusDotClass); ?>"></span>
                        <?php echo htmlspecialchars($promotionStatusLabel); ?>
                    </span>
                </div>

                <?php if (!empty($promotionStatusWarnings)): ?>
                    <div class="bg-amber-500/15 border border-amber-400/30 rounded-xl px-4 py-3 text-sm text-amber-100 space-y-2">
                        <span class="block text-xs uppercase tracking-wide font-semibold">Action recommended</span>
                        <ul class="space-y-1 list-disc list-inside">
                            <?php foreach ($promotionStatusWarnings as $warning): ?>
                                <li><?php echo htmlspecialchars($warning); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="bg-white/5 border border-white/10 rounded-xl p-4 space-y-2 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs uppercase tracking-wide text-indigo-200">Drop slug</span>
                        <span class="font-semibold text-white"><?php echo $promotionSlug !== '' ? htmlspecialchars($promotionSlug) : 'N/A'; ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs uppercase tracking-wide text-indigo-200">Promotion type</span>
                        <span class="font-semibold text-white"><?php echo htmlspecialchars($promotionTypeLabel); ?></span>
                    </div>
                </div>

                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between pt-4 border-t border-white/10">
                    <p class="text-xs text-indigo-100/70">Use quick actions to refresh automation data or pause the current drop without editing banner copy.</p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="px-4 py-2 rounded-full border border-white/20 bg-white/5 text-sm font-semibold text-indigo-100 hover:bg-white/10" onclick="window.location.reload();" title="Reload this dashboard">
                            Refresh
                        </button>
                        <button type="submit" name="action" value="resync" class="px-4 py-2 rounded-full border border-indigo-400/40 bg-indigo-500/20 text-sm font-semibold text-indigo-100 hover:bg-indigo-500/30" title="Force a promotion sync now">
                            Force sync
                        </button>
                        <button type="submit" name="action" value="clear" class="px-4 py-2 rounded-full border border-red-400/40 bg-red-500/10 text-sm font-semibold text-red-100 hover:bg-red-500/20" title="Deactivate automation and clear the banner" onclick="return confirm('Deactivate automation and clear the banner?');">
                            Deactivate
                        </button>
                    </div>
                </div>
            </section>

            <section id="step-banner-basics" class="bg-white/10 border border-white/10 rounded-2xl shadow-lg backdrop-blur">
                <details class="group rounded-2xl" open>
                    <summary class="flex items-start justify-between gap-3 px-5 py-4 cursor-pointer select-none focus:outline-none focus:ring-2 focus:ring-indigo-300/60 rounded-2xl">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-indigo-200 font-semibold">Step 1</p>
                            <h3 class="text-lg font-semibold text-white">Banner basics</h3>
                            <p class="text-sm text-indigo-100/70">Set the headline, supporting copy, and CTA for the storefront banner.</p>
                        </div>
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-white/20 bg-white/10 transition-transform group-open:rotate-180" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white/80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                        </span>
                    </summary>
                    <div class="space-y-5 border-t border-white/10 px-5 pb-5 pt-5">
                        <div class="space-y-3">
                            <label class="block text-xs font-semibold uppercase tracking-wide text-indigo-200 mb-1">Headline message</label>
                            <textarea name="message" rows="2" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded" placeholder="Limited capsule goes live Friday." required><?php echo htmlspecialchars($messageValue); ?></textarea>
                        </div>
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-indigo-200 mb-1">Subtext (optional)</label>
                                <input type="text" name="subtext" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded" value="<?php echo htmlspecialchars($subtextValue); ?>" placeholder="Add a short supporting line." />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-indigo-200 mb-1">Badge text</label>
                                <input type="text" name="badge" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded" value="<?php echo htmlspecialchars($badgeValue); ?>" placeholder="e.g. Limited" />
                            </div>
                        </div>
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-indigo-200 mb-1">CTA label</label>
                                <input type="text" name="cta" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded" value="<?php echo htmlspecialchars($ctaValue); ?>" placeholder="Shop the drop" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-indigo-200 mb-1">CTA link</label>
                                <input type="url" name="href" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded" value="<?php echo htmlspecialchars($hrefValue); ?>" placeholder="https://example.com/drops" />
                            </div>
                        </div>
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-indigo-200 mb-1">Banner style</label>
                                <select name="variant" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded">
                                    <option value="promo" <?php echo $variantCurrent === 'promo' ? 'selected' : ''; ?>>Promotional</option>
                                    <option value="info" <?php echo $variantCurrent === 'info' ? 'selected' : ''; ?>>Informational</option>
                                    <option value="alert" <?php echo $variantCurrent === 'alert' ? 'selected' : ''; ?>>Alert</option>
                                </select>
                            </div>
                            <label class="flex items-center gap-2 text-sm text-indigo-100/80">
                                <input type="checkbox" name="dismissible" value="1" class="h-4 w-4 rounded border-white/30 bg-white/10" <?php echo $dismissibleCurrent ? 'checked' : ''; ?>>
                                Allow shoppers to dismiss the banner in session
                            </label>
                        </div>
                    </div>
                </details>
            </section>

            <section id="step-automation" class="bg-white/10 border border-white/10 rounded-2xl shadow-lg backdrop-blur">
                <details class="group rounded-2xl" <?php echo $stepTwoStartsOpen ? 'open' : ''; ?>>
                    <summary class="flex items-start justify-between gap-3 px-5 py-4 cursor-pointer select-none focus:outline-none focus:ring-2 focus:ring-indigo-300/60 rounded-2xl">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-indigo-200 font-semibold">Step 2</p>
                            <h3 class="text-lg font-semibold text-white">Automation toolkit</h3>
                            <p class="text-sm text-indigo-100/70">Choose which promotion modules run during the drop.</p>
                        </div>
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-white/20 bg-white/10 transition-transform group-open:rotate-180" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white/80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                        </span>
                    </summary>
                    <div class="space-y-5 border-t border-white/10 px-5 pb-5 pt-5">
                        <div class="space-y-3">
                            <span class="text-xs uppercase tracking-wide text-indigo-200">Enable automation</span>
                            <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-3">
                                <label class="flex flex-col gap-1 rounded-xl border border-white/10 bg-white/5 px-3 py-3">
                                    <span class="inline-flex items-center gap-2 text-sm font-semibold text-white">
                                        <input type="checkbox" name="promotion_features[]" value="price_markdown" class="h-4 w-4 rounded border-white/30 bg-white/10" data-promo-toggle="price_markdown" <?php echo in_array('price_markdown', $promotionFeaturesCurrent, true) ? 'checked' : ''; ?>>
                                        Price markdown
                                    </span>
                                    <span class="text-xs text-indigo-100/60">Reduce product prices by a percentage or fixed amount.</span>
                                </label>
                                <label class="flex flex-col gap-1 rounded-xl border border-white/10 bg-white/5 px-3 py-3">
                                    <span class="inline-flex items-center gap-2 text-sm font-semibold text-white">
                                        <input type="checkbox" name="promotion_features[]" value="bundle_bogo" class="h-4 w-4 rounded border-white/30 bg-white/10" data-promo-toggle="bundle_bogo" <?php echo in_array('bundle_bogo', $promotionFeaturesCurrent, true) ? 'checked' : ''; ?>>
                                        Bundle freebies
                                    </span>
                                    <span class="text-xs text-indigo-100/60">Unlock bonus items when qualifying SKUs are in the cart.</span>
                                </label>
                                <label class="flex flex-col gap-1 rounded-xl border border-white/10 bg-white/5 px-3 py-3">
                                    <span class="inline-flex items-center gap-2 text-sm font-semibold text-white">
                                        <input type="checkbox" name="promotion_features[]" value="clearance" class="h-4 w-4 rounded border-white/30 bg-white/10" data-promo-toggle="clearance" <?php echo in_array('clearance', $promotionFeaturesCurrent, true) ? 'checked' : ''; ?>>
                                        Clearance flag
                                    </span>
                                    <span class="text-xs text-indigo-100/60">Temporarily mark inventory as clearance during the drop.</span>
                                </label>
                                <label class="flex flex-col gap-1 rounded-xl border border-white/10 bg-white/5 px-3 py-3">
                                    <span class="inline-flex items-center gap-2 text-sm font-semibold text-white">
                                        <input type="checkbox" name="promotion_features[]" value="custom_design_reward" class="h-4 w-4 rounded border-white/30 bg-white/10" data-promo-toggle="custom_design_reward" <?php echo in_array('custom_design_reward', $promotionFeaturesCurrent, true) ? 'checked' : ''; ?>>
                                        Creator reward
                                    </span>
                                    <span class="text-xs text-indigo-100/60">Credit designers for qualifying orders during the drop.</span>
                                </label>
                            </div>
                        </div>

                        <div class="bg-white/5 border border-white/10 rounded-lg p-4 space-y-3">
                            <div class="flex flex-col gap-1 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <h4 class="text-xs font-semibold uppercase tracking-wide text-indigo-200">Featured inventory</h4>
                                    <p class="text-xs text-indigo-100/60">Spotlight these SKUs in analytics, countdown panels, and drop copy.</p>
                                </div>
                                <span id="promotion_featured_count" class="text-xs text-indigo-100/60"><?php echo count($promotionFeaturedInventoryCurrent); ?> selected</span>
                            </div>
                            <?php if (!empty($promotionInventoryOptions)): ?>
                                <div class="space-y-2">
                                    <input type="search" id="promotion_inventory_search" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded" placeholder="Search inventory to feature">
                                    <select id="promotion_featured_inventory" name="promotion_featured_inventory[]" multiple size="8" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded min-h-[160px]">
                                        <?php foreach ($promotionInventoryOptions as $option): ?>
                                            <option value="<?php echo (int) $option['id']; ?>" <?php echo in_array($option['id'], $promotionFeaturedInventoryCurrent, true) ? 'selected' : ''; ?>><?php echo htmlspecialchars($option['label']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="text-xs text-indigo-100/60">Use Ctrl/Cmd or Shift to select multiple items.</p>
                                </div>
                                <div id="promotion_featured_selected" class="flex flex-wrap gap-2" data-selection-summary>
                                    <?php if (!empty($promotionFeaturedInventoryLabels)): ?>
                                        <?php foreach ($promotionFeaturedInventoryLabels as $label): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full bg-indigo-500/10 border border-indigo-400/20 text-indigo-100/80" data-dynamic-chip><?php echo htmlspecialchars($label); ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-xs text-indigo-100/50" data-empty-hint>No spotlight products yet.</span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-sm text-indigo-100/80">Add products to the catalog to configure featured inventory.</p>
                            <?php endif; ?>
                        </div>

                        <div data-promo-section="price_markdown" class="bg-white/5 border border-white/10 rounded-lg p-4 space-y-3 <?php echo in_array('price_markdown', $promotionFeaturesCurrent, true) ? '' : 'hidden'; ?>">
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-indigo-200">Markdown settings</h4>
                            <div class="grid md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-indigo-200 mb-1">Mode</label>
                                    <select id="promotion_markdown_mode" name="promotion_markdown_mode" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded">
                                        <option value="percent" <?php echo $promotionMarkdownModeCurrent === 'percent' ? 'selected' : ''; ?>>Percent off</option>
                                        <option value="fixed" <?php echo $promotionMarkdownModeCurrent === 'fixed' ? 'selected' : ''; ?>>Fixed amount</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-indigo-200 mb-1">Discount value</label>
                                    <input type="number" step="0.01" min="0" name="promotion_markdown_value" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded" value="<?php echo htmlspecialchars($promotionMarkdownValueInput); ?>" placeholder="e.g. 20">
                                    <p class="mt-1 text-xs text-indigo-100/60">Enter percent or rupee value depending on mode.</p>
                                </div>
                            </div>
                            <div class="grid md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-indigo-200 mb-1">Scope</label>
                                    <select id="promotion_markdown_scope" name="promotion_markdown_scope" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded">
                                        <option value="all_items" <?php echo $promotionMarkdownScopeCurrent === 'all_items' ? 'selected' : ''; ?>>All items in this drop</option>
                                        <option value="sku_list" <?php echo $promotionMarkdownScopeCurrent === 'sku_list' ? 'selected' : ''; ?>>Specific inventory IDs</option>
                                    </select>
                                </div>
                            </div>
                            <div id="promotion_markdown_sku_block" class="space-y-2 <?php echo $promotionMarkdownScopeCurrent === 'sku_list' ? '' : 'hidden'; ?>">
                                <label class="block text-xs font-semibold uppercase tracking-wide text-indigo-200 mb-1">Eligible SKUs</label>
                                <textarea name="promotion_markdown_skus" rows="2" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded" placeholder="Separate with commas or spaces, e.g. 12, 34, 56"><?php echo htmlspecialchars($promotionMarkdownSkusValue); ?></textarea>
                            </div>
                        </div>

                        <div data-promo-section="bundle_bogo" class="bg-white/5 border border-white/10 rounded-lg p-4 space-y-3 <?php echo in_array('bundle_bogo', $promotionFeaturesCurrent, true) ? '' : 'hidden'; ?>">
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-indigo-200">Bundle freebies</h4>
                            <div class="space-y-2">
                                <div class="flex flex-col gap-1 md:flex-row md:items-center md:justify-between">
                                    <label for="promotion_bundle_eligible" class="text-xs font-semibold uppercase tracking-wide text-indigo-200">Eligible products</label>
                                    <span id="promotion_bundle_count" class="text-xs text-indigo-100/60"><?php echo count($promotionBundleEligibleCurrent); ?> selected</span>
                                </div>
                                <?php if (!empty($promotionInventoryOptions)): ?>
                                    <input type="search" id="promotion_bundle_inventory_search" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded" placeholder="Search inventory for bundle eligibility">
                                    <select id="promotion_bundle_eligible" name="promotion_bundle_eligible_skus[]" multiple size="8" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded min-h-[160px]">
                                        <?php foreach ($promotionInventoryOptions as $option): ?>
                                            <option value="<?php echo (int) $option['id']; ?>" <?php echo in_array($option['id'], $promotionBundleEligibleCurrent, true) ? 'selected' : ''; ?>><?php echo htmlspecialchars($option['label']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="text-xs text-indigo-100/60">Use Ctrl/Cmd or Shift to select multiple qualifying items.</p>
                                <?php else: ?>
                                    <p class="text-sm text-indigo-100/80">Add products to the catalog to configure bundle rules.</p>
                                <?php endif; ?>
                                <div id="promotion_bundle_selected" class="flex flex-wrap gap-2" data-selection-summary>
                                    <?php if (!empty($promotionBundleEligibleLabels)): ?>
                                        <?php foreach ($promotionBundleEligibleLabels as $label): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full bg-indigo-500/10 border border-indigo-400/20 text-indigo-100/80" data-dynamic-chip><?php echo htmlspecialchars($label); ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-xs text-indigo-100/50" data-empty-hint>No qualifying products yet.</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-indigo-200 mb-1">Free items configuration</label>
                                <textarea id="promotion_bundle_free_items" name="promotion_bundle_free_items" rows="3" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded" placeholder="One per line: inventory_id:quantity, e.g. 55:1"><?php echo htmlspecialchars($promotionBundleFreeItemsValue); ?></textarea>
                            </div>
                            <div class="grid md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-indigo-200 mb-1">Limit per cart</label>
                                    <input type="number" min="0" id="promotion_bundle_limit" name="promotion_bundle_limit" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded" placeholder="0 = unlimited" value="<?php echo htmlspecialchars((string) $promotionBundleLimitValue); ?>">
                                </div>
                            </div>
                        </div>

                        <div data-promo-section="clearance" class="bg-white/5 border border-white/10 rounded-lg p-4 space-y-3 <?php echo in_array('clearance', $promotionFeaturesCurrent, true) ? '' : 'hidden'; ?>">
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-indigo-200">Clearance inventory</h4>
                            <div class="space-y-2">
                                <div class="flex flex-col gap-1 md:flex-row md:items-center md:justify-between">
                                    <label for="promotion_clearance_inventory" class="text-xs font-semibold uppercase tracking-wide text-indigo-200">Select clearance products</label>
                                    <span id="promotion_clearance_count" class="text-xs text-indigo-100/60"><?php echo count($promotionClearanceCurrent); ?> selected</span>
                                </div>
                                <?php if (!empty($promotionInventoryOptions)): ?>
                                    <input type="search" id="promotion_clearance_search" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded" placeholder="Search inventory for clearance">
                                    <select id="promotion_clearance_inventory" name="promotion_clearance_skus[]" multiple size="8" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded min-h-[160px]">
                                        <?php foreach ($promotionInventoryOptions as $option): ?>
                                            <option value="<?php echo (int) $option['id']; ?>" <?php echo in_array($option['id'], $promotionClearanceCurrent, true) ? 'selected' : ''; ?>><?php echo htmlspecialchars($option['label']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <p class="text-sm text-indigo-100/80">No inventory available to mark as clearance.</p>
                                <?php endif; ?>
                                <div id="promotion_clearance_selected" class="flex flex-wrap gap-2" data-selection-summary>
                                    <?php if (!empty($promotionClearanceLabels)): ?>
                                        <?php foreach ($promotionClearanceLabels as $label): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full bg-rose-500/10 border border-rose-400/20 text-rose-100/80" data-dynamic-chip><?php echo htmlspecialchars($label); ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-xs text-rose-100/60" data-empty-hint>No clearance products yet.</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div data-promo-section="custom_design_reward" class="bg-white/5 border border-white/10 rounded-lg p-4 space-y-3 <?php echo in_array('custom_design_reward', $promotionFeaturesCurrent, true) ? '' : 'hidden'; ?>">
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-indigo-200">Creator reward</h4>
                            <div class="grid md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-indigo-200 mb-1">Reward amount (Rs)</label>
                                    <input type="number" step="0.01" min="0" id="promotion_custom_reward_value" name="promotion_custom_reward_value" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded" value="<?php echo htmlspecialchars($promotionRewardValueInput); ?>">
                                </div>
                                <label class="flex items-center gap-2 text-sm text-indigo-100/80">
                                    <input type="checkbox" name="promotion_custom_reward_enabled" value="1" class="h-4 w-4 rounded border-white/30 bg-white/10" <?php echo $promotionRewardEnabledCurrent ? 'checked' : ''; ?>>
                                    Enable automatic designer wallet credit
                                </label>
                            </div>
                        </div>
                    </div>
                </details>
            </section>

            <section id="step-drop-scheduler" class="bg-white/10 border border-white/10 rounded-2xl shadow-lg backdrop-blur">
                <details class="group rounded-2xl" <?php echo $stepThreeStartsOpen ? 'open' : ''; ?>>
                    <summary class="flex items-start justify-between gap-3 px-5 py-4 cursor-pointer select-none focus:outline-none focus:ring-2 focus:ring-indigo-300/60 rounded-2xl">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-indigo-200 font-semibold">Step 3</p>
                            <h3 class="text-lg font-semibold text-white">Drop scheduler</h3>
                            <p class="text-sm text-indigo-100/70">Coordinate launch timing, countdown behaviour, and waitlist messaging.</p>
                        </div>
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-white/20 bg-white/10 transition-transform group-open:rotate-180" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white/80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                        </span>
                    </summary>
                    <div class="space-y-5 border-t border-white/10 px-5 pb-5 pt-5">
                        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                            <p class="text-sm text-indigo-100/70">Turn on drop mode to schedule the banner, countdown, and waitlist tools.</p>
                            <label class="inline-flex items-center gap-2 text-sm text-indigo-100/80">
                                <input type="checkbox" name="drop_mode" value="1" class="h-4 w-4 rounded border-white/30 bg-white/10" <?php echo $isDropMode ? 'checked' : ''; ?> data-drop-toggle>
                                Enable scheduling &amp; waitlist
                            </label>
                        </div>
                        <?php if ($bannerTimezoneDisplay !== ''): ?>
                            <p class="text-xs text-indigo-100/60">Scheduling uses <?php echo htmlspecialchars($bannerTimezoneDisplay); ?>.</p>
                        <?php endif; ?>

                        <div class="grid md:grid-cols-3 gap-4">
                            <div>
                                <label for="drop_slug" class="block text-xs font-semibold uppercase tracking-wide text-indigo-200 mb-1">Drop identifier</label>
                                <input type="text" id="drop_slug" name="drop_slug" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded" placeholder="aurora-fall-2025" value="<?php echo htmlspecialchars($dropSlugValue); ?>" data-drop-field <?php echo $isDropMode ? '' : 'disabled'; ?>>
                                <p class="mt-1 text-xs text-indigo-100/60">Used to tie countdowns and waitlists together.</p>
                            </div>
                            <div>
                                <label for="drop_label" class="block text-xs font-semibold uppercase tracking-wide text-indigo-200 mb-1">Drop label</label>
                                <input type="text" id="drop_label" name="drop_label" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded" placeholder="Aurora Capsule" value="<?php echo htmlspecialchars($dropLabelValue); ?>" data-drop-field <?php echo $isDropMode ? '' : 'disabled'; ?>>
                                <p class="mt-1 text-xs text-indigo-100/60">Appears in waitlists and admin previews.</p>
                            </div>
                            <div>
                                <label for="countdown_label" class="block text-xs font-semibold uppercase tracking-wide text-indigo-200 mb-1">Countdown label</label>
                                <input type="text" id="countdown_label" name="countdown_label" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded" placeholder="Drop goes live in" value="<?php echo htmlspecialchars($countdownLabelValue); ?>" data-drop-field <?php echo $isDropMode ? '' : 'disabled'; ?>>
                            </div>
                        </div>

                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label for="schedule_start" class="block text-xs font-semibold uppercase tracking-wide text-indigo-200 mb-1">Drop starts</label>
                                <input type="datetime-local" id="schedule_start" name="schedule_start" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded" value="<?php echo htmlspecialchars($startValue); ?>" data-drop-field <?php echo $isDropMode ? '' : 'disabled'; ?>>
                            </div>
                            <div>
                                <label for="schedule_end" class="block text-xs font-semibold uppercase tracking-wide text-indigo-200 mb-1">Drop ends</label>
                                <input type="datetime-local" id="schedule_end" name="schedule_end" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded" value="<?php echo htmlspecialchars($endValue); ?>" data-drop-field <?php echo $isDropMode ? '' : 'disabled'; ?>>
                            </div>
                        </div>

                        <label class="inline-flex items-center gap-2 text-sm text-indigo-100/80">
                            <input type="checkbox" name="countdown_enabled" value="1" class="h-4 w-4 rounded border-white/30 bg-white/10" <?php echo $countdownEnabledCurrent ? 'checked' : ''; ?> data-drop-field <?php echo $isDropMode ? '' : 'disabled'; ?>>
                            Display countdown timer on storefront
                        </label>

                        <div class="grid md:grid-cols-3 gap-4">
                            <div>
                                <label for="countdown_target" class="block text-xs font-semibold uppercase tracking-wide text-indigo-200">Countdown target</label>
                                <input type="datetime-local" id="countdown_target" name="countdown_target" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded" value="<?php echo htmlspecialchars($countdownValue); ?>" data-drop-field <?php echo ($countdownEnabledCurrent && $currentCountdownMode === 'manual' && $isDropMode) ? '' : 'disabled'; ?>>
                                <div class="space-y-1 text-xs text-indigo-100/70" id="countdown_mode_hint"></div>
                            </div>
                            <div class="space-y-2">
                                <label class="flex items-center gap-2 text-sm text-indigo-100/80">
                                    <input type="radio" name="countdown_mode" value="manual" class="h-4 w-4 rounded border-white/30 bg-white/10" <?php echo $currentCountdownMode === 'manual' ? 'checked' : ''; ?> data-drop-field <?php echo ($countdownEnabledCurrent && $isDropMode) ? '' : 'disabled'; ?>>
                                    Set exact time (use field above)
                                </label>
                                <label class="flex items-center gap-2 text-sm text-indigo-100/80">
                                    <input type="radio" name="countdown_mode" value="start" class="h-4 w-4 rounded border-white/30 bg-white/10" <?php echo $currentCountdownMode === 'start' ? 'checked' : ''; ?> data-drop-field <?php echo ($countdownEnabledCurrent && $isDropMode) ? '' : 'disabled'; ?>>
                                    Match drop start
                                </label>
                                <label class="flex items-center gap-2 text-sm text-indigo-100/80">
                                    <input type="radio" name="countdown_mode" value="start_plus5" class="h-4 w-4 rounded border-white/30 bg-white/10" <?php echo $currentCountdownMode === 'start_plus5' ? 'checked' : ''; ?> data-drop-field <?php echo ($countdownEnabledCurrent && $isDropMode) ? '' : 'disabled'; ?>>
                                    5 minutes after drop start
                                </label>
                                <label class="flex items-center gap-2 text-sm text-indigo-100/80">
                                    <input type="radio" name="countdown_mode" value="now" class="h-4 w-4 rounded border-white/30 bg-white/10" <?php echo $currentCountdownMode === 'now' ? 'checked' : ''; ?> data-drop-field <?php echo ($countdownEnabledCurrent && $isDropMode) ? '' : 'disabled'; ?>>
                                    Start countdown from current time
                                </label>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-200">Make visible on</p>
                            <div class="flex flex-wrap gap-3">
                                <?php foreach ($visibilityOptions as $audienceKey => $audienceLabel): ?>
                                    <label class="inline-flex items-center gap-2 text-sm text-indigo-100/80">
                                        <input type="checkbox" name="visibility[]" value="<?php echo htmlspecialchars($audienceKey, ENT_QUOTES); ?>" class="h-4 w-4 rounded border-white/30 bg-white/10" <?php echo in_array($audienceKey, $visibilityCurrent, true) ? 'checked' : ''; ?> data-drop-field <?php echo $isDropMode ? '' : 'disabled'; ?>>
                                        <?php echo htmlspecialchars($audienceLabel); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="text-xs text-indigo-100/60">Leave all unchecked to show everywhere.</p>
                        </div>

                        <div class="border-t border-white/10 pt-4 space-y-3" data-waitlist-fields>
                            <h4 class="text-xs font-semibold text-indigo-200 uppercase tracking-wide">Waitlist micro-form</h4>
                            <label class="inline-flex items-center gap-2 text-sm text-indigo-100/80">
                                <input type="checkbox" name="waitlist_enabled" value="1" class="h-4 w-4 rounded border-white/30 bg-white/10" <?php echo $waitlistEnabledCurrent ? 'checked' : ''; ?> data-waitlist-toggle <?php echo $isDropMode ? '' : 'disabled'; ?>>
                                Allow shoppers to join a waitlist while the drop is closed
                            </label>
                            <p class="text-xs text-indigo-100/60" data-waitlist-hint>
                                <?php
                                if (!$isDropMode) {
                                    echo 'Switch on drop mode first to manage the waitlist copy.';
                                } elseif (!$waitlistEnabledCurrent) {
                                    echo 'Check the box above to unlock waitlist messaging fields.';
                                } else {
                                    echo 'Customize the micro-form copy below while the drop is locked.';
                                }
                                ?>
                            </p>
                            <div class="grid md:grid-cols-3 gap-4">
                                <div>
                                    <label for="waitlist_slug" class="block text-xs font-semibold uppercase tracking-wide text-indigo-200 mb-1">Waitlist ID</label>
                                    <input type="text" id="waitlist_slug" name="waitlist_slug" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded" placeholder="waitlist-aurora" value="<?php echo htmlspecialchars($waitlistSlugValue); ?>" data-waitlist-field <?php echo ($isDropMode && $waitlistEnabledCurrent) ? '' : 'disabled'; ?>>
                                </div>
                                <div>
                                    <label for="waitlist_button_label" class="block text-xs font-semibold uppercase tracking-wide text-indigo-200 mb-1">Button label</label>
                                    <input type="text" id="waitlist_button_label" name="waitlist_button_label" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded" placeholder="Join waitlist" value="<?php echo htmlspecialchars($waitlistButtonValue); ?>" data-waitlist-field <?php echo ($isDropMode && $waitlistEnabledCurrent) ? '' : 'disabled'; ?>>
                                </div>
                                <div>
                                    <label for="waitlist_success_copy" class="block text-xs font-semibold uppercase tracking-wide text-indigo-200 mb-1">Confirmation blurb</label>
                                    <textarea id="waitlist_success_copy" name="waitlist_success_copy" rows="3" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded" placeholder="Thanks for joining the list!" data-waitlist-field <?php echo ($isDropMode && $waitlistEnabledCurrent) ? '' : 'disabled'; ?>><?php echo htmlspecialchars($waitlistSuccessValue); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-white/10 pt-4 space-y-4" data-drop-content-fields>
                            <div>
                                <h4 class="text-xs font-semibold uppercase tracking-wide text-indigo-200">Storytelling &amp; collateral</h4>
                                <p class="text-xs text-indigo-100/60">Populate drop teasers, hero media, and highlights used across the storefront.</p>
                            </div>
                            <div class="grid md:grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <label for="drop_teaser" class="block text-xs font-semibold uppercase tracking-wide text-indigo-200">Teaser headline</label>
                                    <input type="text" id="drop_teaser" name="drop_teaser" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded" placeholder="A new constellation arrives" value="<?php echo htmlspecialchars($dropTeaserValue); ?>" data-drop-field <?php echo $isDropMode ? '' : 'disabled'; ?>>
                                </div>
                                <div class="space-y-2">
                                    <label for="drop_media_url" class="block text-xs font-semibold uppercase tracking-wide text-indigo-200">Hero media URL</label>
                                    <input type="url" id="drop_media_url" name="drop_media_url" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded" placeholder="https://cdn.example.com/drop/hero.jpg" value="<?php echo htmlspecialchars($dropMediaUrlValue); ?>" data-drop-field <?php echo $isDropMode ? '' : 'disabled'; ?>>
                                    <p class="text-xs text-indigo-100/60">Use an absolute image or video URL that is already hosted.</p>
                                </div>
                            </div>
                            <div class="grid md:grid-cols-2 gap-4">
                                <div>
                                    <label for="drop_story" class="block text-xs font-semibold uppercase tracking-wide text-indigo-200 mb-1">Drop story</label>
                                    <textarea id="drop_story" name="drop_story" rows="4" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded" placeholder="Describe the inspiration and craft details." data-drop-field <?php echo $isDropMode ? '' : 'disabled'; ?>><?php echo htmlspecialchars($dropStoryValue); ?></textarea>
                                </div>
                                <div>
                                    <label for="drop_access_notes" class="block text-xs font-semibold uppercase tracking-wide text-indigo-200 mb-1">Access notes</label>
                                    <textarea id="drop_access_notes" name="drop_access_notes" rows="4" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded" placeholder="Who gets early access, shipping windows, etc." data-drop-field <?php echo $isDropMode ? '' : 'disabled'; ?>><?php echo htmlspecialchars($dropAccessNotesValue); ?></textarea>
                                </div>
                            </div>
                            <div>
                                <label for="drop_highlights" class="block text-xs font-semibold uppercase tracking-wide text-indigo-200 mb-1">Highlights (one per line)</label>
                                <textarea id="drop_highlights" name="drop_highlights" rows="3" class="w-full px-3 py-2 border border-white/20 bg-white/10 text-white rounded" placeholder="100% organic cotton&#10;Limited 300 units" data-drop-field <?php echo $isDropMode ? '' : 'disabled'; ?>><?php echo htmlspecialchars($dropHighlightsValue); ?></textarea>
                            </div>
                        </div>
                    </div>
                </details>
            </section>

            <section id="action-toolbar" class="bg-white/5 border border-white/10 rounded-2xl shadow-lg backdrop-blur p-6 space-y-3">
                <div class="flex flex-wrap gap-3">
                    <button type="submit" name="action" value="save" class="px-5 py-3 rounded-xl bg-indigo-500 text-white font-semibold shadow hover:bg-indigo-600 transition">
                        Update banner
                    </button>
                    <button type="submit" name="action" value="clear" class="px-5 py-3 rounded-xl border border-red-400/60 text-red-100 font-semibold bg-red-500/10 hover:bg-red-500/20" onclick="return confirm('Disable the banner and clear storefront messaging?');">
                        Disable banner
                    </button>
                    <button type="submit" name="action" value="resync" class="px-5 py-3 rounded-xl border border-indigo-300/60 text-indigo-100 font-semibold hover:bg-white/10">
                        Force sync
                    </button>
                </div>
                <p class="text-xs text-indigo-100/70">Save to push updates live. Disable removes the banner and pauses automation until a new drop is configured.</p>
            </section>

        </form>
    </main>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const dropToggle = document.querySelector('[data-drop-toggle]');
        const dropFields = Array.from(document.querySelectorAll('[data-drop-field]'));
        const waitlistToggle = document.querySelector('[data-waitlist-toggle]');
        const waitlistFields = Array.from(document.querySelectorAll('[data-waitlist-field]'));
        const waitlistHint = document.querySelector('[data-waitlist-hint]');
        const promoToggles = Array.from(document.querySelectorAll('[data-promo-toggle]'));
        const markdownScope = document.getElementById('promotion_markdown_scope');
        const markdownSkuBlock = document.getElementById('promotion_markdown_sku_block');
        const countdownEnabled = document.querySelector('input[name="countdown_enabled"]');
        const countdownTarget = document.getElementById('countdown_target');
        const countdownModeRadios = Array.from(document.querySelectorAll('input[name="countdown_mode"]'));

        const setDisabledState = (nodes, disabled) => {
            nodes.forEach((el) => {
                el.disabled = !!disabled;
            });
        };

        const updateWaitlistFields = (active) => {
            setDisabledState(waitlistFields, !active);
            if (!waitlistHint) {
                return;
            }

            if (!dropToggle || !dropToggle.checked) {
                waitlistHint.textContent = 'Switch on drop mode first to manage the waitlist copy.';
            } else if (!active) {
                waitlistHint.textContent = 'Check the box above to unlock waitlist messaging fields.';
            } else {
                waitlistHint.textContent = 'Customize the micro-form copy below while the drop is locked.';
            }
        };

        const updateDropMode = () => {
            const enabled = !!(dropToggle && dropToggle.checked);
            setDisabledState(dropFields, !enabled);
            const waitlistActive = enabled && waitlistToggle && waitlistToggle.checked;
            updateWaitlistFields(waitlistActive);
            updateCountdownFields();
        };

        const updatePromoSections = () => {
            promoToggles.forEach((toggle) => {
                const sectionName = toggle.dataset.promoToggle;
                const section = document.querySelector(`[data-promo-section="${sectionName}"]`);
                if (!section) {
                    return;
                }
                section.classList.toggle('hidden', !toggle.checked);
            });
        };

        const updateMarkdownScope = () => {
            if (!markdownScope || !markdownSkuBlock) {
                return;
            }
            markdownSkuBlock.classList.toggle('hidden', markdownScope.value !== 'sku_list');
        };

        const updateCountdownFields = () => {
            if (!countdownEnabled || !countdownTarget) {
                return;
            }
            const dropAllowed = !!(dropToggle && dropToggle.checked);
            const countdownActive = dropAllowed && countdownEnabled.checked;
            const manualMode = Array.from(countdownModeRadios).some((radio) => radio.checked && radio.value === 'manual');
            countdownTarget.disabled = !(countdownActive && manualMode);
            countdownModeRadios.forEach((radio) => {
                radio.disabled = !countdownActive;
            });
        };

        if (dropToggle) {
            dropToggle.addEventListener('change', updateDropMode);
        }
        if (waitlistToggle) {
            waitlistToggle.addEventListener('change', () => updateWaitlistFields(dropToggle && dropToggle.checked && waitlistToggle.checked));
        }
        promoToggles.forEach((toggle) => {
            toggle.addEventListener('change', updatePromoSections);
        });
        if (markdownScope) {
            markdownScope.addEventListener('change', updateMarkdownScope);
        }
        if (countdownEnabled) {
            countdownEnabled.addEventListener('change', updateCountdownFields);
        }
        countdownModeRadios.forEach((radio) => {
            radio.addEventListener('change', updateCountdownFields);
        });

        updateDropMode();
        updatePromoSections();
        updateMarkdownScope();
        updateCountdownFields();
    });
</script>
</body>
</html>