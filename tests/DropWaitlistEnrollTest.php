<?php

use PHPUnit\Framework\TestCase;

if (!defined('DROP_WAITLIST_STORAGE_PATH')) {
    define('DROP_WAITLIST_STORAGE_PATH', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'drop_waitlist_test.json');
}

if (!defined('DROP_WAITLIST_FALLBACK_PATH')) {
    define('DROP_WAITLIST_FALLBACK_PATH', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'drop_waitlist_fallback.json');
}

require_once __DIR__ . '/../core/drop_waitlist.php';

final class DropWaitlistEnrollTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (file_exists(DROP_WAITLIST_STORAGE_PATH)) {
            @unlink(DROP_WAITLIST_STORAGE_PATH);
        }

        if (file_exists(DROP_WAITLIST_FALLBACK_PATH)) {
            @unlink(DROP_WAITLIST_FALLBACK_PATH);
        }

        $_SERVER['REMOTE_ADDR'] = '203.0.113.99';
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit-waitlist-agent';
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);

        if (file_exists(DROP_WAITLIST_STORAGE_PATH)) {
            @unlink(DROP_WAITLIST_STORAGE_PATH);
        }
    }

    public function testStoresNewEntry(): void
    {
        $result = record_waitlist_signup('capsule-one', [
            'email' => 'reader@example.com',
            'name' => 'Reader',
            'context' => ['page' => 'home', 'cta' => 'banner'],
        ]);

        $this->assertSame('stored', $result['status']);
        $this->assertFileExists(DROP_WAITLIST_STORAGE_PATH);

        $payload = json_decode((string) file_get_contents(DROP_WAITLIST_STORAGE_PATH), true);
        $this->assertIsArray($payload);
        $entries = $payload['entries'] ?? [];
        $this->assertCount(1, $entries);

        $entry = $entries[0];
        $this->assertSame('capsule-one', $entry['slug']);
        $this->assertSame('reader@example.com', $entry['email']);
        $this->assertArrayHasKey('ip_hash', $entry);
        $this->assertArrayNotHasKey('ip', $entry);
        $this->assertSame(['page' => 'home', 'cta' => 'banner'], $entry['context']);
    }

    public function testDuplicateEmailReturnsExists(): void
    {
        record_waitlist_signup('capsule-two', [
            'email' => 'dupe@example.com',
        ]);

        $result = record_waitlist_signup('capsule-two', [
            'email' => 'dupe@example.com',
        ]);

        $this->assertSame('exists', $result['status']);
        $this->assertArrayHasKey('entry', $result);
    }

    public function testRateLimitTriggersForRapidRequests(): void
    {
        $options = [
            'rate_limit_window' => 600,
            'rate_limit_max' => 1,
        ];

        record_waitlist_signup('capsule-three', [
            'email' => 'first@example.com',
        ], $options);

        $result = record_waitlist_signup('capsule-three', [
            'email' => 'second@example.com',
        ], $options);

        $this->assertSame('rate_limited', $result['status']);
        $this->assertGreaterThanOrEqual(0, $result['retry_after']);
    }
}
