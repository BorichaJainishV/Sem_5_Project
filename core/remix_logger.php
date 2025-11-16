<?php

if (!function_exists('record_remix_entry')) {
    function record_remix_entry(array $payload): void
    {
        $logDir = __DIR__ . '/../storage/logs';
        if (!is_dir($logDir) && !@mkdir($logDir, 0775, true)) {
            return;
        }

        $record = [
            'timestamp' => date('c'),
            'customer_id' => $payload['customer_id'] ?? null,
            'source' => $payload['source'] ?? null,
            'origin' => $payload['origin'] ?? null,
            'variant' => $payload['variant'] ?? null,
            'texture' => $payload['texture'] ?? null,
            'token' => $payload['token'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ];

        $filePath = $logDir . '/remix_activity.log';
        @file_put_contents(
            $filePath,
            json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND
        );
    }
}

