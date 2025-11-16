<?php
// scripts/force_deactivate.php
// Usage: php scripts/force_deactivate.php --token=XYZ [--reason="..."] [--dry-run]
// Safety: Requires env var DROP_FORCE_DEACTIVATE_TOKEN to be set and match --token value.

$opts = [];
foreach ($argv as $a) {
    if (strpos($a, '--') === 0) {
        $parts = explode('=', $a, 2);
        $key = substr($parts[0], 2);
        $val = isset($parts[1]) ? $parts[1] : true;
        $opts[$key] = $val;
    }
}

$envToken = getenv('DROP_FORCE_DEACTIVATE_TOKEN');
if (empty($envToken)) {
    fwrite(STDERR, "ERROR: Environment variable DROP_FORCE_DEACTIVATE_TOKEN not set. Aborting.\n");
    exit(2);
}

$provided = isset($opts['token']) ? $opts['token'] : null;
if (!$provided || $provided !== $envToken) {
    fwrite(STDERR, "ERROR: Missing or invalid --token. Aborting.\n");
    exit(3);
}

$dryRun = isset($opts['dry-run']);
$reason = isset($opts['reason']) ? $opts['reason'] : 'manual force deactivate';

$workspace = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
$storageFile = realpath($workspace) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'drop_promotions_state.json';
$logsDir = realpath($workspace) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';

if (!file_exists($storageFile)) {
    fwrite(STDERR, "ERROR: storage file not found: $storageFile\n");
    exit(4);
}

$now = date('c');
$backupFile = $storageFile . '.bak.' . preg_replace('/[^0-9T]/','', $now);

$raw = file_get_contents($storageFile);
$data = json_decode($raw, true);
if ($data === null) {
    fwrite(STDERR, "ERROR: Failed to decode JSON in $storageFile\n");
    exit(5);
}

// Create a backup
if (!$dryRun) {
    if (false === file_put_contents($backupFile, $raw)) {
        fwrite(STDERR, "ERROR: Failed to write backup file $backupFile\n");
        exit(6);
    }
}

$changed = false;
// Mark a top-level force_deactivated flag and timestamp
if (!isset($data['force_deactivated']) || $data['force_deactivated'] !== true) {
    $data['force_deactivated'] = true;
    $data['force_deactivated_at'] = $now;
    $data['force_deactivated_reason'] = $reason;
    $changed = true;
}

// If promotions list exists, deactivate each active promotion
if (isset($data['promotions']) && is_array($data['promotions'])) {
    foreach ($data['promotions'] as &$promo) {
        if (isset($promo['active']) && $promo['active'] === true) {
            $promo['active'] = false;
            $promo['status'] = 'deactivated_by_force';
            $promo['deactivated_at'] = $now;
            $changed = true;
        }
    }
    unset($promo);
}

$out = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($out === false) {
    fwrite(STDERR, "ERROR: Failed to encode JSON for writing.\n");
    exit(7);
}

if ($dryRun) {
    echo "DRY RUN: Would write updated state to $storageFile (backup to $backupFile).\n";
    echo "DRY RUN: Changes detected: " . ($changed ? 'yes' : 'no') . "\n";
    exit(0);
}

// Ensure logs dir exists
if (!is_dir($logsDir)) {
    @mkdir($logsDir, 0755, true);
}

// Write the updated state
if (false === file_put_contents($storageFile, $out)) {
    fwrite(STDERR, "ERROR: Failed to write updated state to $storageFile\n");
    exit(8);
}

$logEntry = json_encode([
    'at' => $now,
    'by' => get_current_user() ?: php_uname('n'),
    'reason' => $reason,
    'backup' => basename($backupFile),
    'changed' => $changed,
], JSON_UNESCAPED_SLASHES) . PHP_EOL;

$logFile = $logsDir . DIRECTORY_SEPARATOR . 'force_deactivate.log';
file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

echo "Force-deactivate completed. Backup: $backupFile\n";
echo "Audit log updated: $logFile\n";
exit(0);
