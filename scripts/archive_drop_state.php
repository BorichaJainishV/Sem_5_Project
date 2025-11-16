<?php
declare(strict_types=1);

$rootPath = dirname(__DIR__);
$storagePath = $rootPath . '/storage';
$bannerPath = $storagePath . '/flash_banner.json';
$promoStatePath = $storagePath . '/drop_promotions_state.json';

$options = getopt('', ['label::']);
$label = isset($options['label']) ? preg_replace('/[^a-zA-Z0-9_-]+/', '', (string) $options['label']) : '';
$timestamp = date('Ymd_His');
$archiveDir = $storagePath . '/archives/drop_state_' . $timestamp . ($label !== '' ? '_' . $label : '');

if (!is_dir($archiveDir) && !@mkdir($archiveDir, 0775, true) && !is_dir($archiveDir)) {
    fwrite(STDERR, 'Unable to create archive directory: ' . $archiveDir . PHP_EOL);
    exit(1);
}

$copied = [];
$missing = [];

function archive_copy(string $source, string $destination, array &$copied, array &$missing): void
{
    if (!file_exists($source)) {
        $missing[] = basename($destination);
        return;
    }
    $dir = dirname($destination);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        fwrite(STDERR, 'Unable to create directory: ' . $dir . PHP_EOL);
        exit(1);
    }
    if (!copy($source, $destination)) {
        fwrite(STDERR, 'Failed to copy ' . $source . PHP_EOL);
        exit(1);
    }
    $copied[] = $destination;
}

$primaryFiles = [
    'flash_banner.json' => $bannerPath,
    'drop_promotions_state.json' => $promoStatePath,
];

foreach ($primaryFiles as $filename => $source) {
    archive_copy($source, $archiveDir . '/' . $filename, $copied, $missing);
}

$optionalFiles = [
    'drop_waitlists.json' => $storagePath . '/drop_waitlists.json',
    'logs/drop_scheduler.log' => $storagePath . '/logs/drop_scheduler.log',
    'logs/drop_scheduler_dryrun.log' => $storagePath . '/logs/drop_scheduler_dryrun.log',
    'logs/drop_promotions.log' => $storagePath . '/logs/drop_promotions.log',
];

foreach ($optionalFiles as $relative => $source) {
    if (!file_exists($source)) {
        continue;
    }
    archive_copy($source, $archiveDir . '/' . $relative, $copied, $missing);
}

 $zipCreated = null;
 $zipWarning = null;

if (class_exists('ZipArchive')) {
    $zipPath = $archiveDir . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($archiveDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $fileInfo) {
            $localName = substr($fileInfo->getPathname(), strlen($archiveDir) + 1);
            if ($fileInfo->isDir()) {
                $zip->addEmptyDir($localName);
            } else {
                $zip->addFile($fileInfo->getPathname(), $localName);
            }
        }
        $zip->close();
        $zipCreated = $zipPath;
    } else {
        $zipWarning = 'Unable to create zip archive at ' . $zipPath;
    }
} else {
    $zipWarning = 'ZipArchive extension not available; skipping bundled zip.';
}

echo 'Archive written to ' . $archiveDir . PHP_EOL;
if (!empty($copied)) {
    echo 'Files:' . PHP_EOL;
    foreach ($copied as $file) {
        echo ' - ' . $file . PHP_EOL;
    }
}
if (!empty($missing)) {
    echo 'Missing files (skipped): ' . implode(', ', $missing) . PHP_EOL;
}
if ($zipCreated !== null) {
    echo 'Zip bundle: ' . $zipCreated . PHP_EOL;
} elseif ($zipWarning !== null) {
    fwrite(STDERR, $zipWarning . PHP_EOL);
}

exit(0);
