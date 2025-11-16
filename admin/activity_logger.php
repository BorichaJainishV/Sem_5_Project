<?php
if (!function_exists('log_admin_activity')) {
    function log_admin_activity(int $adminId, string $action, array $meta = []): void
    {
        $logDir = __DIR__ . '/../storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $record = [
            'timestamp' => time(),
            'admin_id' => $adminId,
            'action' => $action,
            'meta' => $meta,
        ];

        $logFile = $logDir . '/admin_activity.log';
        @file_put_contents($logFile, json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
    }
}

if (!function_exists('get_recent_admin_activity')) {
    function get_recent_admin_activity(int $limit = 10): array
    {
        $logFile = __DIR__ . '/../storage/logs/admin_activity.log';
        if (!is_readable($logFile)) {
            return [];
        }

        $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return [];
        }

        $lines = array_reverse($lines);
        $activities = [];
        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $activities[] = $decoded;
                if (count($activities) >= $limit) {
                    break;
                }
            }
        }

        return $activities;
    }
}
