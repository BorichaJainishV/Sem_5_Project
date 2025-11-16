<?php
// ---------------------------------------------------------------------
// core/drop_waitlist.php - Persist waitlist sign-ups for timed drops
// ---------------------------------------------------------------------

if (!defined('DROP_WAITLIST_STORAGE_PATH')) {
    define('DROP_WAITLIST_STORAGE_PATH', __DIR__ . '/../storage/drop_waitlists.json');
}

if (!defined('DROP_WAITLIST_FALLBACK_PATH')) {
    define('DROP_WAITLIST_FALLBACK_PATH', __DIR__ . '/../storage/waitlist_requests.json');
}

if (!defined('DROP_WAITLIST_RATE_LIMIT_WINDOW')) {
    define('DROP_WAITLIST_RATE_LIMIT_WINDOW', 300); // seconds
}

if (!defined('DROP_WAITLIST_RATE_LIMIT_MAX')) {
    define('DROP_WAITLIST_RATE_LIMIT_MAX', 3); // entries allowed per window per slug/ip
}

if (!function_exists('drop_waitlist_ip_hash')) {
    function drop_waitlist_ip_hash(?string $ip): ?string
    {
        $ip = $ip ? trim($ip) : '';
        if ($ip === '') {
            return null;
        }

        return hash('sha256', $ip . '|drop_waitlist');
    }
}

if (!function_exists('load_waitlist_storage')) {
    function load_waitlist_storage(): array
    {
        $path = DROP_WAITLIST_STORAGE_PATH;
        if (!file_exists($path)) {
            return ['entries' => [], 'updated_at' => null];
        }

        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            if (DROP_WAITLIST_FALLBACK_PATH !== $path && file_exists(DROP_WAITLIST_FALLBACK_PATH)) {
                $raw = @file_get_contents(DROP_WAITLIST_FALLBACK_PATH);
            }
            if ($raw === false || $raw === '') {
                return ['entries' => [], 'updated_at' => null];
            }
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['entries' => [], 'updated_at' => null];
        }

        if (!isset($decoded['entries']) || !is_array($decoded['entries'])) {
            $decoded['entries'] = [];
        }

        if (!isset($decoded['updated_at'])) {
            $decoded['updated_at'] = null;
        }

        return $decoded;
    }
}

if (!function_exists('save_waitlist_storage')) {
    function save_waitlist_storage(array $payload): bool
    {
        $dir = dirname(DROP_WAITLIST_STORAGE_PATH);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                return false;
            }
        }

        $payload['updated_at'] = time();
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        return file_put_contents(DROP_WAITLIST_STORAGE_PATH, $json, LOCK_EX) !== false;
    }
}

if (!function_exists('record_waitlist_signup')) {
    function record_waitlist_signup(string $slug, array $data, array $options = []): array
    {
        $slug = strtolower(trim($slug));
        if ($slug === '') {
            return ['status' => 'invalid', 'message' => 'Missing waitlist slug.'];
        }

        $emailRaw = trim((string) ($data['email'] ?? ''));
        $emailNormalized = strtolower($emailRaw);
        if (!filter_var($emailRaw, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'invalid', 'message' => 'Enter a valid email.'];
        }

        $rateLimitWindow = isset($options['rate_limit_window']) ? (int) $options['rate_limit_window'] : DROP_WAITLIST_RATE_LIMIT_WINDOW;
        $rateLimitMax = isset($options['rate_limit_max']) ? (int) $options['rate_limit_max'] : DROP_WAITLIST_RATE_LIMIT_MAX;
        if ($rateLimitWindow < 0) {
            $rateLimitWindow = 0;
        }
        if ($rateLimitMax < 0) {
            $rateLimitMax = 0;
        }

        $now = time();
        $ipHash = drop_waitlist_ip_hash($_SERVER['REMOTE_ADDR'] ?? null);
        $userAgentHash = isset($_SERVER['HTTP_USER_AGENT']) && $_SERVER['HTTP_USER_AGENT'] !== ''
            ? hash('sha256', trim((string) $_SERVER['HTTP_USER_AGENT']) . '|drop_waitlist')
            : null;

        $storage = load_waitlist_storage();
        $entries = $storage['entries'] ?? [];
        foreach ($entries as $entry) {
            if (($entry['slug'] ?? '') === $slug && ($entry['email_normalized'] ?? '') === $emailNormalized) {
                return [
                    'status' => 'exists',
                    'entry' => $entry,
                ];
            }
        }

        if ($ipHash !== null && $rateLimitWindow > 0 && $rateLimitMax > 0) {
            $recent = [];
            $windowFloor = $now - $rateLimitWindow;
            foreach ($entries as $entry) {
                if (($entry['slug'] ?? '') !== $slug) {
                    continue;
                }
                if (($entry['ip_hash'] ?? null) !== $ipHash) {
                    continue;
                }
                $createdAt = isset($entry['created_at']) ? (int) $entry['created_at'] : 0;
                if ($createdAt >= $windowFloor) {
                    $recent[] = $createdAt;
                }
            }

            if (count($recent) >= $rateLimitMax) {
                $oldest = min($recent);
                $retryAfter = max(0, ($oldest + $rateLimitWindow) - $now);

                return [
                    'status' => 'rate_limited',
                    'retry_after' => $retryAfter,
                    'message' => 'Too many waitlist attempts. Please try again shortly.',
                ];
            }
        }

        $source = trim((string) ($data['source'] ?? 'banner'));
        if ($source === '') {
            $source = 'banner';
        }

        $context = $data['context'] ?? [];
        if (!is_array($context)) {
            $context = [];
        }

        $context = array_filter(
            array_map(static function ($value) {
                if (is_scalar($value)) {
                    $value = trim((string) $value);
                    return $value === '' ? null : $value;
                }
                return null;
            }, $context)
        );

        $entry = [
            'slug' => $slug,
            'email' => $emailRaw,
            'email_normalized' => $emailNormalized,
            'name' => trim((string) ($data['name'] ?? '')),
            'source' => $source,
            'context' => $context,
            'ip_hash' => $ipHash,
            'ua_hash' => $userAgentHash,
            'created_at' => $now,
        ];
        $entries[] = $entry;
        $storage['entries'] = $entries;

        if (!save_waitlist_storage($storage)) {
            return ['status' => 'error', 'message' => 'Unable to save waitlist request.'];
        }

        return ['status' => 'stored', 'entry' => $entry];
    }
}

if (!function_exists('export_waitlist_entries')) {
    function export_waitlist_entries(?string $slug = null): array
    {
        $storage = load_waitlist_storage();
        $entries = $storage['entries'] ?? [];
        if ($slug === null || $slug === '') {
            return $entries;
        }

        $slug = strtolower(trim($slug));
        return array_values(array_filter($entries, static function (array $entry) use ($slug) {
            return ($entry['slug'] ?? '') === $slug;
        }));
    }
}
