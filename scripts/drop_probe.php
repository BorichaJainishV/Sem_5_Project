<?php
declare(strict_types=1);

$options = getopt('', ['url::', 'expect-state::', 'expect-countdown::']);
$url = isset($options['url']) && $options['url'] !== false ? (string) $options['url'] : 'http://localhost/index.php';
$expectState = isset($options['expect-state']) ? (string) $options['expect-state'] : null;
$expectCountdown = isset($options['expect-countdown']) ? (string) $options['expect-countdown'] : null;

function probe_fetch(string $url): string
{
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Unable to initialize cURL');
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'DropProbe/1.0',
    ]);
    $body = curl_exec($ch);
    if ($body === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('cURL error: ' . $error);
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status >= 400) {
        throw new RuntimeException('HTTP status ' . $status);
    }
    return $body;
}

function probe_extract_banner(string $html): ?DOMElement
{
    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    if (!$doc->loadHTML($html)) {
        return null;
    }
    $xpath = new DOMXPath($doc);
    $nodes = $xpath->query('//*[@data-drop-banner]');
    if (!$nodes || $nodes->length === 0) {
        return null;
    }
    return $nodes->item(0);
}

try {
    $html = probe_fetch($url);
} catch (Throwable $fetchError) {
    fwrite(STDERR, 'Fetch failed: ' . $fetchError->getMessage() . PHP_EOL);
    exit(1);
}

$banner = probe_extract_banner($html);
if (!$banner instanceof DOMElement) {
    fwrite(STDERR, 'No drop banner found on page.' . PHP_EOL);
    exit(1);
}

$state = $banner->getAttribute('data-drop-state') ?: 'unknown';
$countdownEnabled = $banner->getAttribute('data-countdown-enabled') === 'true';
$countdownTarget = $banner->getAttribute('data-countdown-target');
$dropSlug = $banner->getAttribute('data-drop-slug');

$summary = [
    'state' => $state,
    'slug' => $dropSlug,
    'countdown_enabled' => $countdownEnabled,
    'countdown_target' => $countdownTarget,
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

$exitCode = 0;
if ($expectState !== null && strtolower($expectState) !== strtolower($state)) {
    fwrite(STDERR, sprintf('Expected state %s but found %s%s', $expectState, $state, PHP_EOL));
    $exitCode = 1;
}

if ($expectCountdown !== null) {
    $expectedBool = strtolower($expectCountdown) === 'true';
    if ($countdownEnabled !== $expectedBool) {
        fwrite(STDERR, sprintf('Expected countdown_enabled=%s but found %s%s', $expectedBool ? 'true' : 'false', $countdownEnabled ? 'true' : 'false', PHP_EOL));
        $exitCode = 1;
    }
}

exit($exitCode);
