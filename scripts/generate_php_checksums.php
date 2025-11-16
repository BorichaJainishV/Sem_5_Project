<?php
// generate_php_checksums.php
// Walk the project and generate SHA256 checksums for all PHP source files.

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to determine project root.\n");
    exit(1);
}

$excludeDirs = [
    'vendor',
    '.git',
    'storage\\backups',
    'uploads',
    'node_modules'
];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
$hashes = [];

foreach ($iterator as $file) {
    /** @var SplFileInfo $file */
    if (!$file->isFile()) {
        continue;
    }
    if (strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    $rel = ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
    $relNormalized = str_replace('\\', '/', $rel);

    // skip the checksum file itself if present
    if (stripos($relNormalized, 'docs/php_checksums.txt') === 0 || stripos($relNormalized, 'docs/php_checksums.txt') !== false) {
        continue;
    }

    // exclude known directories
    $skip = false;
    foreach ($excludeDirs as $ex) {
        $exNorm = str_replace('\\', '/', $ex);
        if (stripos($relNormalized, $exNorm . '/') === 0 || stripos($relNormalized, $exNorm) === 0) {
            $skip = true;
            break;
        }
    }
    if ($skip) {
        continue;
    }

    $hash = hash_file('sha256', $path);
    if ($hash === false) {
        fwrite(STDERR, "Failed to hash: {$path}\n");
        continue;
    }
    $hashes[$relNormalized] = $hash;
}

ksort($hashes, SORT_STRING);

$docsDir = $root . DIRECTORY_SEPARATOR . 'docs';
if (!is_dir($docsDir)) {
    @mkdir($docsDir, 0755, true);
}

$outLines = [];
foreach ($hashes as $rel => $h) {
    $outLines[] = $h . '  ' . $rel;
}

$outPath = $docsDir . DIRECTORY_SEPARATOR . 'php_checksums.txt';
file_put_contents($outPath, implode(PHP_EOL, $outLines) . PHP_EOL);

echo "Wrote " . count($outLines) . " checksums to docs/php_checksums.txt\n";
