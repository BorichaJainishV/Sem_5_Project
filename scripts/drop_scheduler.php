<?php
declare(strict_types=1);

$rootPath = dirname(__DIR__);
require_once $rootPath . '/core/banner_manager.php';
require_once $rootPath . '/core/drop_promotions.php';

$options = getopt('', ['dry-run', 'force-env']);
$dryRun = array_key_exists('dry-run', $options);
$forceEnv = array_key_exists('force-env', $options);

$environment = getenv('MYSTIC_ENV') ?: getenv('APP_ENV') ?: 'local';
$allowNonProd = strtolower((string) (getenv('DROP_SCHEDULER_ALLOW_ACTIVATE') ?: 'false')) === 'true';
if (!$forceEnv && $environment !== 'production' && !$allowNonProd) {
    scheduler_log("Scheduler blocked in environment '{$environment}'. Set DROP_SCHEDULER_ALLOW_ACTIVATE=true or use --force-env to override.");
    exit(0);
}

function scheduler_log(string $message): void
{
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] $message" . PHP_EOL;
}

function scheduler_format_time(?int $timestamp): string
{
    if ($timestamp === null || $timestamp <= 0) {
        return 'unscheduled';
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function scheduler_load_banner_payload(): ?array
{
    $path = defined('FLASH_BANNER_STORAGE_PATH')
        ? FLASH_BANNER_STORAGE_PATH
        : dirname(__DIR__) . '/storage/flash_banner.json';

    if (!file_exists($path)) {
        return null;
    }

    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return null;
    }

    return $decoded;
}

function scheduler_extract_timestamp($value): ?int
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
        $timestamp = strtotime($value);
        return $timestamp === false ? null : $timestamp;
    }

    return null;
}

$bannerPayload = scheduler_load_banner_payload();
if ($bannerPayload === null) {
    scheduler_log('No flash banner configuration found. Nothing to schedule.');
    exit(0);
}

$mode = $bannerPayload['mode'] ?? 'standard';
if ($mode !== 'drop') {
    scheduler_log('Active banner is not configured for drop mode. Scheduler exiting.');
    exit(0);
}

$dropSlug = trim((string) ($bannerPayload['drop_slug'] ?? ''));
$startTs = scheduler_extract_timestamp($bannerPayload['schedule_start_ts'] ?? ($bannerPayload['schedule_start'] ?? null));
$endTs = scheduler_extract_timestamp($bannerPayload['schedule_end_ts'] ?? ($bannerPayload['schedule_end'] ?? null));

$now = time();
$beforeStart = $startTs !== null && $now < $startTs;
$afterEnd = $endTs !== null && $now > $endTs;

$statusPayload = drop_promotion_current_status();
$currentStatus = $statusPayload['status'] ?? 'idle';
$currentState = is_array($statusPayload['state'] ?? null) ? $statusPayload['state'] : [];
$currentSlug = $currentState['active_slug'] ?? null;

$windowLabel = 'start ' . scheduler_format_time($startTs) . ' / end ' . scheduler_format_time($endTs);
scheduler_log("Evaluating drop scheduler window: $windowLabel");

if ($beforeStart) {
    scheduler_log('Drop has not reached its start time yet.');
    if ($currentStatus === 'active') {
        if ($dryRun) {
            scheduler_log('[dry-run] Would deactivate promotion until the start window opens.');
        } else {
            $result = drop_promotion_deactivate($currentState);
            scheduler_log('Deactivation result: ' . json_encode($result, JSON_UNESCAPED_SLASHES));
        }
    }
    exit(0);
}

if ($afterEnd) {
    scheduler_log('Drop window has passed its end time.');
    if ($currentStatus === 'active') {
        if ($dryRun) {
            scheduler_log('[dry-run] Would deactivate promotion because the window has ended.');
        } else {
            $result = drop_promotion_deactivate($currentState);
            scheduler_log('Deactivation result: ' . json_encode($result, JSON_UNESCAPED_SLASHES));
        }
    } else {
        scheduler_log('Promotion already idle. No action needed.');
    }
    exit(0);
}

if ($startTs !== null) {
    scheduler_log('Drop window is open for activation.');
} else {
    scheduler_log('No explicit start time set; treating drop as immediately eligible.');
}

$alreadyActive = ($currentStatus === 'active') && ($currentSlug === $dropSlug || $dropSlug === '');
if ($alreadyActive) {
    scheduler_log('Drop promotions already active for this slug. Nothing to do.');
    exit(0);
}

if ($dryRun) {
    scheduler_log('[dry-run] Would trigger drop_promotion_sync(true) to activate the drop.');
    exit(0);
}

$result = drop_promotion_sync(true);
$status = $result['status'] ?? 'unknown';
scheduler_log('Activation result: ' . json_encode($result, JSON_UNESCAPED_SLASHES));

if ($status === 'error') {
    exit(1);
}

exit(0);
