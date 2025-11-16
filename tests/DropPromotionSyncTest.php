<?php

use PHPUnit\Framework\TestCase;

$dropPromotionTestRoot = sys_get_temp_dir() . '/drop_promotion_test_' . uniqid('', true);
if (!defined('DROP_PROMOTION_TEST_ROOT')) {
    define('DROP_PROMOTION_TEST_ROOT', $dropPromotionTestRoot);
}
if (!defined('DROP_PROMOTION_STATE_PATH')) {
    define('DROP_PROMOTION_STATE_PATH', DROP_PROMOTION_TEST_ROOT . '/state.json');
}
if (!defined('DROP_PROMOTION_LOG_PATH')) {
    define('DROP_PROMOTION_LOG_PATH', DROP_PROMOTION_TEST_ROOT . '/logs/drop_promotions.log');
}
if (!defined('DROP_PROMOTION_LOCK_PATH')) {
    define('DROP_PROMOTION_LOCK_PATH', DROP_PROMOTION_TEST_ROOT . '/drop_promotions.lock');
}
if (!defined('DROP_PROMOTION_BUNDLE_PATH')) {
    define('DROP_PROMOTION_BUNDLE_PATH', DROP_PROMOTION_TEST_ROOT . '/bundle_rules.json');
}

require_once __DIR__ . '/../core/drop_promotions.php';

final class DropPromotionSyncTest extends TestCase
{
    protected function setUp(): void
    {
        if (!is_dir(DROP_PROMOTION_TEST_ROOT)) {
            mkdir(DROP_PROMOTION_TEST_ROOT, 0775, true);
        }

        $this->purgeTestArtifacts();
    }

    protected function tearDown(): void
    {
        $currentState = drop_promotion_load_state();
        if (!empty($currentState['active_slug'])) {
            drop_promotion_deactivate($currentState);
        }

        $this->purgeTestArtifacts();
    }

    private function purgeTestArtifacts(): void
    {
        $paths = [
            DROP_PROMOTION_STATE_PATH,
            DROP_PROMOTION_LOCK_PATH,
            DROP_PROMOTION_BUNDLE_PATH,
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }

        $logDir = dirname(DROP_PROMOTION_LOG_PATH);
        if (is_dir($logDir)) {
            $files = glob($logDir . '/*');
            if ($files !== false) {
                foreach ($files as $file) {
                    @unlink($file);
                }
            }
        }
    }

    public function testSyncSkipsWhenConfigUnchanged(): void
    {
        $config = $this->samplePromotionConfig(150.0);

        $firstResult = drop_promotion_sync(false, $config);
        $this->assertSame('activated', $firstResult['status'] ?? null, 'First sync should activate the promotion.');

        $secondResult = drop_promotion_sync(false, $config);
        $this->assertSame('already_active', $secondResult['status'] ?? null, 'Second sync should skip because nothing changed.');

        $modifiedConfig = $this->samplePromotionConfig(200.0);
        $thirdResult = drop_promotion_sync(false, $modifiedConfig);
        $this->assertSame('activated', $thirdResult['status'] ?? null, 'Config change should trigger a fresh activation.');
    }

    public function testSyncDeactivatesWhenBannerMissing(): void
    {
        $state = array_merge(drop_promotion_default_state(), [
            'active_slug' => 'orphaned-drop',
            'promotion_type' => 'custom_design_reward',
            'last_config_hash' => 'deadbeef',
        ]);

        $this->assertTrue(drop_promotion_save_state($state), 'Should seed promotion state for deactivation test.');

        $result = drop_promotion_sync(false);

        $this->assertSame('deactivated', $result['status'] ?? null, 'Missing banner should trigger deactivation.');

        $postState = drop_promotion_load_state();
        $this->assertEmpty($postState['active_slug'] ?? null);
        $this->assertNull($postState['promotion_type'] ?? null);
    }

    private function samplePromotionConfig(float $discountValue): array
    {
        $now = time();
        return [
            'drop_slug' => 'unit-test-drop',
            'schedule_start_ts' => $now - 60,
            'schedule_end_ts' => $now + 3600,
            'promotion' => [
                'type' => 'custom_design_reward',
                'markdown' => [
                    'mode' => 'percent',
                    'value' => 0.0,
                    'scope' => 'all_items',
                    'skus' => [],
                ],
                'bundle' => [
                    'eligible_skus' => [],
                    'free_items' => [],
                    'limit_per_cart' => 0,
                ],
                'clearance' => [
                    'skus' => [],
                ],
                'custom_design_reward' => [
                    'enabled' => true,
                    'discount_value' => $discountValue,
                ],
            ],
        ];
    }
}
