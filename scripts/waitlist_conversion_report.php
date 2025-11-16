<?php
declare(strict_types=1);

$rootPath = dirname(__DIR__);
require_once $rootPath . '/core/drop_waitlist.php';

$options = getopt('', ['slug::', 'format::', 'output::']);
$slugFilter = isset($options['slug']) ? trim((string) $options['slug']) : '';
$format = strtolower((string) ($options['format'] ?? 'table'));
if (!in_array($format, ['table', 'csv', 'json'], true)) {
    $format = 'table';
}
$outputPath = isset($options['output']) ? trim((string) $options['output']) : '';

$entries = export_waitlist_entries($slugFilter !== '' ? $slugFilter : null);
if (empty($entries)) {
    echo $slugFilter !== ''
        ? "No waitlist sign-ups recorded for slug {$slugFilter}." . PHP_EOL
        : "No waitlist sign-ups recorded yet." . PHP_EOL;
    exit(0);
}

$summary = [];
foreach ($entries as $entry) {
    $slug = (string) ($entry['slug'] ?? 'unknown');
    if (!isset($summary[$slug])) {
        $summary[$slug] = [
            'slug' => $slug,
            'total_entries' => 0,
            'unique_emails' => [],
            'sources' => [],
            'first_signup' => null,
            'last_signup' => null,
        ];
    }

    $summary[$slug]['total_entries']++;
    $email = strtolower(trim((string) ($entry['email_normalized'] ?? $entry['email'] ?? '')));
    if ($email !== '') {
        $summary[$slug]['unique_emails'][$email] = true;
    }

    $source = $entry['source'] ?? 'banner';
    $summary[$slug]['sources'][$source] = ($summary[$slug]['sources'][$source] ?? 0) + 1;

    $createdAt = isset($entry['created_at']) ? (int) $entry['created_at'] : null;
    if ($createdAt !== null && $createdAt > 0) {
        if ($summary[$slug]['first_signup'] === null || $createdAt < $summary[$slug]['first_signup']) {
            $summary[$slug]['first_signup'] = $createdAt;
        }
        if ($summary[$slug]['last_signup'] === null || $createdAt > $summary[$slug]['last_signup']) {
            $summary[$slug]['last_signup'] = $createdAt;
        }
    }
}

$rows = [];
foreach ($summary as $slug => $data) {
    $topSource = '';
    $topSourceCount = 0;
    foreach ($data['sources'] as $source => $count) {
        if ($count > $topSourceCount) {
            $topSource = $source;
            $topSourceCount = $count;
        }
    }

    $rows[] = [
        'slug' => $slug,
        'total_entries' => $data['total_entries'],
        'unique_emails' => count($data['unique_emails']),
        'first_signup' => $data['first_signup'] ? date('Y-m-d H:i:s', $data['first_signup']) : null,
        'last_signup' => $data['last_signup'] ? date('Y-m-d H:i:s', $data['last_signup']) : null,
        'top_source' => $topSource,
        'top_source_count' => $topSourceCount,
    ];
}

switch ($format) {
    case 'json':
        echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        break;
    case 'csv':
        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($csv, $row);
        }
        rewind($csv);
        $contents = stream_get_contents($csv);
        fclose($csv);
        if ($outputPath !== '') {
            file_put_contents($outputPath, $contents);
            echo "Report saved to {$outputPath}" . PHP_EOL;
        } else {
            echo $contents;
        }
        break;
    default:
        foreach ($rows as $row) {
            echo sprintf(
                "%-20s total=%-4d unique=%-4d last=%s top_source=%s (%d)%s",
                $row['slug'],
                $row['total_entries'],
                $row['unique_emails'],
                $row['last_signup'] ?? 'n/a',
                $row['top_source'] ?? 'n/a',
                $row['top_source_count'],
                PHP_EOL
            );
        }
        break;
}

exit(0);
