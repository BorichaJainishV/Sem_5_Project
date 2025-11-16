<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/style_quiz_helpers.php';
require_once __DIR__ . '/../email_handler.php';

final class StylistPersonaFlowTest extends TestCase
{
    public function testStaticFallbackProvidesThreeCuratedEntries(): void
    {
        $inventoryIndex = [];
        $fallbacks = buildStaticPersonaFallback(
            $inventoryIndex,
            'street',
            'monochrome',
            'launch',
            []
        );

        $this->assertCount(3, $fallbacks, 'Fallback catalog should surface three entries.');
        foreach ($fallbacks as $entry) {
            $this->assertArrayHasKey('name', $entry);
            $this->assertArrayHasKey('reason', $entry);
            $this->assertNotSame('', trim((string) $entry['name']));
            $this->assertNotSame('', trim((string) $entry['reason']));
        }

        $firstReason = $fallbacks[0]['reason'];
        $this->assertStringContainsString('Palette stays crisp', $firstReason);
        $this->assertStringContainsString('Sized and tagged to support your limited drop', implode(' ', array_column($fallbacks, 'reason')));
    }

    public function testRenderStylistPersonaEmailRendersRecommendations(): void
    {
        $payload = [
            'persona_label' => 'Urban Creator Bundle',
            'persona_summary' => 'We balanced crisp monochrome layers for a limited drop.',
            'source' => 'inbox_flow',
            'captured_at' => '2025-11-07 12:30:00',
            'customer_name' => 'Jordan',
            'recommendations' => [
                [
                    'name' => 'Neon Grid Statement Tee',
                    'price' => 1499,
                    'image_url' => 'image/mock-tee.png',
                    'reason' => 'Front-and-back print zone ready for your limited run graphics.',
                ],
                [
                    'name' => 'Backstage Drop Shoulder Hoodie',
                    'price' => 1999,
                    'image_url' => 'image/mock-hoodie.png',
                    'reason' => 'Oversized canvas that makes your merch table pop.',
                ],
            ],
        ];

        $rendered = renderStylistPersonaEmail($payload);

        $this->assertArrayHasKey('html', $rendered);
        $this->assertArrayHasKey('alt', $rendered);
        $this->assertStringContainsString('Urban Creator Bundle', $rendered['html']);
        $this->assertStringContainsString('Neon Grid Statement Tee', $rendered['html']);
        $this->assertStringContainsString('₹1,499.00', $rendered['html']);
        $this->assertStringContainsString('Review my capsule', $rendered['html']);

    $this->assertStringContainsString('Neon Grid Statement Tee', $rendered['alt']);
    $this->assertStringContainsString('₹1,499.00', $rendered['alt']);
    }
}
