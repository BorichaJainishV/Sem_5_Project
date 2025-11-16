<?php
if (!function_exists('ensureStyleQuizResultsTable')) {
    function ensureStyleQuizResultsTable(mysqli $conn): void
    {
        $createSql = "CREATE TABLE IF NOT EXISTS style_quiz_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    style_choice VARCHAR(50) NOT NULL,
    palette_choice VARCHAR(50) NOT NULL,
    goal_choice VARCHAR(50) NOT NULL,
    persona_label VARCHAR(120) NOT NULL,
    persona_summary VARCHAR(255) NOT NULL,
    recommendations_json TEXT NOT NULL,
    source_label VARCHAR(60) NOT NULL DEFAULT 'shop_quiz',
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_customer (customer_id),
    CONSTRAINT fk_quiz_customer
        FOREIGN KEY (customer_id) REFERENCES customer(customer_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $conn->query($createSql);

        $existingColumns = [];
        $columnResult = $conn->query('SHOW COLUMNS FROM style_quiz_results');
        if ($columnResult) {
            while ($column = $columnResult->fetch_assoc()) {
                $existingColumns[] = $column['Field'];
            }
            $columnResult->free();
        }

        if (!in_array('source_label', $existingColumns, true)) {
            $conn->query("ALTER TABLE style_quiz_results ADD COLUMN source_label VARCHAR(60) NOT NULL DEFAULT 'shop_quiz' AFTER recommendations_json");
        }

        if (!in_array('submitted_at', $existingColumns, true)) {
            $conn->query("ALTER TABLE style_quiz_results ADD COLUMN submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER source_label");
        }
    }
}

if (!function_exists('ensureInventoryQuizMetadataTable')) {
    function ensureInventoryQuizMetadataTable(mysqli $conn): void
    {
        $createSql = "CREATE TABLE IF NOT EXISTS inventory_quiz_tags (
    inventory_id INT NOT NULL PRIMARY KEY,
    style_tags VARCHAR(255) NOT NULL,
    palette_tags VARCHAR(255) NOT NULL,
    goal_tags VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_quiz_inventory
        FOREIGN KEY (inventory_id) REFERENCES inventory(inventory_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $conn->query($createSql);
    }
}

if (!function_exists('seedInventoryQuizMetadata')) {
    function seedInventoryQuizMetadata(mysqli $conn): void
    {
        ensureInventoryQuizMetadataTable($conn);

        $seedData = [
            1 => [
                'style_tags' => 'street,minimal',
                'palette_tags' => 'monochrome,earth',
                'goal_tags' => 'everyday,launch',
            ],
            2 => [
                'style_tags' => 'street,bold',
                'palette_tags' => 'vivid,monochrome',
                'goal_tags' => 'launch,everyday',
            ],
            3 => [
                'style_tags' => 'street',
                'palette_tags' => 'earth,monochrome',
                'goal_tags' => 'everyday',
            ],
            4 => [
                'style_tags' => 'street,minimal,bold',
                'palette_tags' => 'monochrome,earth,vivid',
                'goal_tags' => 'everyday,launch,gift',
            ],
        ];

        $stmt = $conn->prepare('INSERT INTO inventory_quiz_tags (inventory_id, style_tags, palette_tags, goal_tags) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE style_tags = VALUES(style_tags), palette_tags = VALUES(palette_tags), goal_tags = VALUES(goal_tags)');
        if ($stmt) {
            foreach ($seedData as $inventoryId => $tags) {
                $stmt->bind_param(
                    'isss',
                    $inventoryId,
                    $tags['style_tags'],
                    $tags['palette_tags'],
                    $tags['goal_tags']
                );
                $stmt->execute();
            }
            $stmt->close();
        }

    $inventoryResult = $conn->query('SELECT inventory_id FROM inventory WHERE is_archived = 0 OR is_archived IS NULL');
        if ($inventoryResult) {
            $defaultStyle = 'street,minimal,bold';
            $defaultPalette = 'monochrome,earth,vivid';
            $defaultGoal = 'everyday,launch,gift';

            $checkStmt = $conn->prepare('SELECT 1 FROM inventory_quiz_tags WHERE inventory_id = ? LIMIT 1');
            $insertStmt = $conn->prepare('INSERT INTO inventory_quiz_tags (inventory_id, style_tags, palette_tags, goal_tags) VALUES (?, ?, ?, ?)');

            if ($checkStmt && $insertStmt) {
                while ($row = $inventoryResult->fetch_assoc()) {
                    $inventoryId = (int) ($row['inventory_id'] ?? 0);
                    if ($inventoryId <= 0) {
                        continue;
                    }

                    $checkStmt->bind_param('i', $inventoryId);
                    $checkStmt->execute();
                    $checkResult = $checkStmt->get_result();
                    $exists = $checkResult && $checkResult->num_rows > 0;
                    if ($checkResult) {
                        $checkResult->free();
                    }

                    if ($exists) {
                        continue;
                    }

                    $insertStmt->bind_param('isss', $inventoryId, $defaultStyle, $defaultPalette, $defaultGoal);
                    $insertStmt->execute();
                }
            }

            if ($checkStmt) {
                $checkStmt->close();
            }
            if ($insertStmt) {
                $insertStmt->close();
            }
            $inventoryResult->free();
        }
    }
}

if (!function_exists('loadInventoryQuizMetadata')) {
    function loadInventoryQuizMetadata(mysqli $conn): array
    {
        $metadata = [];
        $result = $conn->query('SELECT inventory_id, style_tags, palette_tags, goal_tags FROM inventory_quiz_tags');
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $inventoryId = (int) ($row['inventory_id'] ?? 0);
                if ($inventoryId <= 0) {
                    continue;
                }
                $metadata[$inventoryId] = [
                    'style' => parseQuizTagList($row['style_tags'] ?? ''),
                    'palette' => parseQuizTagList($row['palette_tags'] ?? ''),
                    'goal' => parseQuizTagList($row['goal_tags'] ?? ''),
                ];
            }
            $result->free();
        }
        return $metadata;
    }
}

if (!function_exists('parseQuizTagList')) {
    function parseQuizTagList(?string $tagString): array
    {
        if (!$tagString) {
            return [];
        }
        $parts = preg_split('/[,|]/', $tagString);
        if (!is_array($parts)) {
            return [];
        }
        return array_values(array_filter(array_map(static function ($value) {
            return strtolower(trim((string) $value));
        }, $parts)));
    }
}

if (!function_exists('derivePersonaLabel')) {
    function derivePersonaLabel(string $style, string $palette, string $goal): array
    {
        $styleLabels = [
            'street' => 'Urban Creator',
            'minimal' => 'Clean Classic',
            'bold' => 'Statement Maker',
        ];

        $goalDescriptors = [
            'everyday' => 'everyday rotation that never misses',
            'launch' => 'merch drop that sparks buzz',
            'gift' => 'gift kit that feels premium',
        ];

        $paletteDescriptors = [
            'monochrome' => 'crisp monochrome layers',
            'earth' => 'earth-tone textures',
            'vivid' => 'vivid gradients and high-contrast inks',
        ];

        $persona = ($styleLabels[$style] ?? 'Mystic Original') . ' Bundle';
        $summary = 'We balanced ' . ($paletteDescriptors[$palette] ?? 'versatile tones') . ' for a ' . ($goalDescriptors[$goal] ?? 'fit you will love') . '.';

        return [$persona, ucfirst($summary)];
    }
}

if (!function_exists('fetchInventoryForQuiz')) {
    function fetchInventoryForQuiz(mysqli $conn): array
    {
        $items = [];
    $result = $conn->query('SELECT inventory_id, product_name, price, image_url, stock_qty FROM inventory WHERE stock_qty > 0 AND (is_archived = 0 OR is_archived IS NULL) ORDER BY inventory_id DESC LIMIT 50');
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
            $result->free();
        }
        return $items;
    }
}

if (!function_exists('scoreInventoryItem')) {
    function scoreInventoryItem(array $item, string $style, string $palette, string $goal, array $metadata): array
    {
        $name = strtolower((string) ($item['product_name'] ?? ''));
        $itemId = (int) ($item['inventory_id'] ?? 0);
        $score = 0;
        $reasons = [];

        $meta = $metadata[$itemId] ?? null;

        $styleLabels = [
            'street' => 'Street-ready layers',
            'minimal' => 'Minimal staples',
            'bold' => 'Bold statements',
        ];
        $paletteLabels = [
            'monochrome' => 'Monochrome palette',
            'earth' => 'Earthy tone mix',
            'vivid' => 'Vivid gradients',
        ];
        $goalLabels = [
            'everyday' => 'Daily rotation',
            'launch' => 'Launch-ready merch',
            'gift' => 'Giftable presentation',
        ];

        if ($meta) {
            if (!empty($meta['style']) && in_array($style, $meta['style'], true)) {
                $score += 8;
                $reasons[] = ($styleLabels[$style] ?? 'Style match') . ' flagged by merch team';
            }
            if (!empty($meta['palette']) && in_array($palette, $meta['palette'], true)) {
                $score += 4;
                $reasons[] = ($paletteLabels[$palette] ?? 'Palette match') . ' aligns with your picks';
            }
            if (!empty($meta['goal']) && in_array($goal, $meta['goal'], true)) {
                $score += 4;
                $reasons[] = ($goalLabels[$goal] ?? 'Goal match') . ' suited to your plan';
            }
        }

        $styleKeywords = [
            'street' => ['hoodie', 'oversized', 'jogger', 'street', 'drop', 'graphic'],
            'minimal' => ['minimal', 'classic', 'clean', 'plain', 'essential', 'crew'],
            'bold' => ['print', 'neon', 'bold', 'statement', 'vibrant', 'limited'],
        ];

        $paletteKeywords = [
            'monochrome' => ['black', 'white', 'charcoal', 'slate'],
            'earth' => ['sand', 'olive', 'rust', 'earth', 'taupe', 'clay'],
            'vivid' => ['sunset', 'aurora', 'gradient', 'coral', 'cobalt', 'electric'],
        ];

        $goalBoost = [
            'everyday' => ['tee', 'tshirt', 'shirt', 'hoodie', 'crew'],
            'launch' => ['limited', 'drop', 'custom', 'logo'],
            'gift' => ['premium', 'box', 'bundle', 'gift'],
        ];

        foreach ($styleKeywords[$style] as $keyword) {
            if (strpos($name, $keyword) !== false) {
                $score += 2;
                $reasons[] = 'Product title leans ' . $style;
                break;
            }
        }

        foreach ($paletteKeywords[$palette] as $keyword) {
            if (strpos($name, $keyword) !== false) {
                $score += 1;
                $reasons[] = 'Palette hint in the product name';
                break;
            }
        }

        foreach ($goalBoost[$goal] as $keyword) {
            if (strpos($name, $keyword) !== false) {
                $score += 1;
                $reasons[] = 'Ideal for your ' . $goal . ' goal';
                break;
            }
        }

        if ($itemId === 4) {
            $score += 3;
            $reasons[] = 'Custom designer adapts to any outcome';
        }

        if ((float) ($item['price'] ?? 0) >= 699) {
            $score += 1;
        }

        return [$score, array_values(array_unique($reasons))];
    }
}

if (!function_exists('buildRecommendations')) {
    function buildRecommendations(mysqli $conn, string $style, string $palette, string $goal, array $metadata): array
    {
        $inventory = fetchInventoryForQuiz($conn);
        $inventoryIndex = [];
        foreach ($inventory as $item) {
            $inventoryId = isset($item['inventory_id']) ? (int) $item['inventory_id'] : 0;
            if ($inventoryId > 0) {
                $inventoryIndex[$inventoryId] = $item;
            }
        }

        $scored = [];
        foreach ($inventory as $item) {
            [$score, $reasons] = scoreInventoryItem($item, $style, $palette, $goal, $metadata);
            $scored[] = [
                'inventory_id' => (int) ($item['inventory_id'] ?? 0),
                'name' => $item['product_name'] ?? 'Mystic Apparel',
                'price' => (float) ($item['price'] ?? 0),
                'image_url' => $item['image_url'] ?? 'image/placeholder.png',
                'score' => $score,
                'reason' => implode(' • ', array_slice($reasons, 0, 2)),
            ];
        }

        usort($scored, static function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        $topCandidates = array_values(array_filter($scored, static function ($item) {
            return $item['score'] > 0;
        }));
        $topCandidates = count($topCandidates) < 3 ? array_slice($scored, 0, 3) : array_slice($topCandidates, 0, 3);

        $recommendations = array_map(static function ($item) {
            unset($item['score']);
            return $item;
        }, $topCandidates);

        $presentIds = [];
        foreach ($recommendations as $rec) {
            $inventoryId = isset($rec['inventory_id']) ? (int) $rec['inventory_id'] : 0;
            if ($inventoryId > 0) {
                $presentIds[$inventoryId] = true;
            }
        }

        $fallbacks = [];
        if (count($recommendations) < 3) {
            $fallbacks = buildStaticPersonaFallback($inventoryIndex, $style, $palette, $goal, $presentIds);
            foreach ($fallbacks as $fallback) {
                $recommendations[] = $fallback;
                if (count($recommendations) >= 3) {
                    break;
                }
            }
        }

        if (count($recommendations) === 0 && !empty($fallbacks)) {
            $recommendations = array_slice($fallbacks, 0, 3);
        }

        if (count($recommendations) === 0) {
            $recommendations[] = [
                'inventory_id' => null,
                'name' => 'Mystic Essentials Bundle',
                'price' => null,
                'image_url' => 'image/placeholder.png',
                'reason' => 'We are lining up fresh inventory for your vibe. Check back shortly for curated picks.',
            ];
        }

        return array_slice($recommendations, 0, 3);
    }
}

if (!function_exists('buildStaticPersonaFallback')) {
    function buildStaticPersonaFallback(array $inventoryIndex, string $style, string $palette, string $goal, array $skipInventoryIds = []): array
    {
        $catalog = staticPersonaCatalog();
        $paletteNotes = [
            'monochrome' => 'Palette stays crisp with blacks, whites, and charcoals for easy layering.',
            'earth' => 'Grounded earth tones keep the capsule soft and wearable.',
            'vivid' => 'Bold color pops make the capsule stand out in any crowd.',
        ];
        $goalNotes = [
            'everyday' => 'Built for repeat wear without losing shape.',
            'launch' => 'Sized and tagged to support your limited drop.',
            'gift' => 'Packaged details make this feel special right out of the box.',
        ];

        $key = $style . '|' . $goal;
        $entries = $catalog[$key] ?? $catalog['default'];

        $results = [];
        foreach ($entries as $entry) {
            $inventoryId = isset($entry['inventory_id']) ? (int) $entry['inventory_id'] : 0;
            if ($inventoryId > 0 && isset($skipInventoryIds[$inventoryId])) {
                continue;
            }

            $matchedInventory = ($inventoryId > 0 && isset($inventoryIndex[$inventoryId])) ? $inventoryIndex[$inventoryId] : null;

            $name = $matchedInventory['product_name'] ?? ($entry['fallback_name'] ?? 'Mystic Apparel');
            $price = isset($matchedInventory['price']) ? (float) $matchedInventory['price'] : ($entry['fallback_price'] ?? null);
            $image = $matchedInventory['image_url'] ?? ($entry['fallback_image'] ?? 'image/placeholder.png');

            if ($matchedInventory === null && $inventoryId > 0) {
                $inventoryId = null;
            }

            $reasonTemplate = $entry['reason'] ?? 'Curated by our stylist squad.';
            $reason = str_replace(
                ['%PALETTE_NOTE%', '%GOAL_NOTE%'],
                [$paletteNotes[$palette] ?? '', $goalNotes[$goal] ?? ''],
                $reasonTemplate
            );
            $reason = trim(preg_replace('/\s+/', ' ', $reason));

            $results[] = [
                'inventory_id' => $inventoryId ?: null,
                'name' => $name,
                'price' => $price !== null ? (float) $price : null,
                'image_url' => $image,
                'reason' => $reason,
            ];
        }

        return $results;
    }
}

if (!function_exists('staticPersonaCatalog')) {
    function staticPersonaCatalog(): array
    {
        return [
            'street|everyday' => [
                [
                    'inventory_id' => 4,
                    'fallback_name' => 'Custom Remix Designer Kit',
                    'fallback_price' => 1299,
                    'fallback_image' => 'image/placeholder.png',
                    'reason' => 'Lock in a customizable base that keeps your street-ready edits flexible. %GOAL_NOTE% %PALETTE_NOTE%',
                ],
                [
                    'fallback_name' => 'Circuit Breaker Cargo Joggers',
                    'fallback_price' => 1499,
                    'fallback_image' => 'image/placeholder.png',
                    'reason' => 'Relaxed structure pairs with graphic tees for repeat wear. %GOAL_NOTE%',
                ],
                [
                    'fallback_name' => 'Skyline Shield Windbreaker',
                    'fallback_price' => 1899,
                    'fallback_image' => 'image/placeholder.png',
                    'reason' => 'Lightweight shell that handles late-night rides without sacrificing polish. %PALETTE_NOTE%',
                ],
            ],
            'street|launch' => [
                [
                    'inventory_id' => 4,
                    'fallback_name' => 'Custom Drop Designer Kit',
                    'fallback_price' => 1299,
                    'fallback_image' => 'image/placeholder.png',
                    'reason' => 'Stamp your launch art on a flexible base and keep production agile. %GOAL_NOTE% %PALETTE_NOTE%',
                ],
                [
                    'fallback_name' => 'Neon Grid Statement Tee',
                    'fallback_price' => 999,
                    'fallback_image' => 'image/placeholder.png',
                    'reason' => 'Front-and-back print zone ready for your limited run graphics. %GOAL_NOTE% %PALETTE_NOTE%',
                ],
                [
                    'fallback_name' => 'Backstage Drop Shoulder Hoodie',
                    'fallback_price' => 1999,
                    'fallback_image' => 'image/placeholder.png',
                    'reason' => 'Oversized canvas that makes your merch table pop. %GOAL_NOTE%',
                ],
            ],
            'street|gift' => [
                [
                    'fallback_name' => 'City Pulse Gift Pack',
                    'fallback_price' => 2199,
                    'fallback_image' => 'image/placeholder.png',
                    'reason' => 'Bundle includes a premium tee and cap ready for gifting. %GOAL_NOTE% %PALETTE_NOTE%',
                ],
                [
                    'inventory_id' => 4,
                    'fallback_name' => 'Custom Remix Designer Kit',
                    'fallback_price' => 1299,
                    'fallback_image' => 'image/placeholder.png',
                    'reason' => 'Let them co-create the final look with our designer. %GOAL_NOTE%',
                ],
                [
                    'fallback_name' => 'Night Run Bomber',
                    'fallback_price' => 2499,
                    'fallback_image' => 'image/placeholder.png',
                    'reason' => 'Gift-worthy bomber with luxe lining that still reads street. %PALETTE_NOTE%',
                ],
            ],
            'minimal|everyday' => [
                [
                    'fallback_name' => 'Luxe Pima Crew Pack',
                    'fallback_price' => 1199,
                    'fallback_image' => 'image/placeholder.png',
                    'reason' => 'Soft-touch basics you can rotate all week. %GOAL_NOTE% %PALETTE_NOTE%',
                ],
                [
                    'fallback_name' => 'Contour Seam Joggers',
                    'fallback_price' => 1399,
                    'fallback_image' => 'image/placeholder.png',
                    'reason' => 'Tailored but relaxed fit keeps things clean during long days. %GOAL_NOTE%',
                ],
                [
                    'inventory_id' => 4,
                    'fallback_name' => 'Custom Designer Essentials Kit',
                    'fallback_price' => 1299,
                    'fallback_image' => 'image/placeholder.png',
                    'reason' => 'Personalize tonal prints without overpowering your capsule. %PALETTE_NOTE%',
                ],
            ],
            'minimal|launch' => [
                [
                    'inventory_id' => 4,
                    'fallback_name' => 'Monogram Launch Designer Kit',
                    'fallback_price' => 1299,
                    'fallback_image' => 'image/placeholder.png',
                    'reason' => 'Dial in subtle monograms or embossing for your drop. %GOAL_NOTE% %PALETTE_NOTE%',
                ],
                [
                    'fallback_name' => 'Gallery Mockneck Tee',
                    'fallback_price' => 1199,
                    'fallback_image' => 'image/placeholder.png',
                    'reason' => 'Minimal silhouette with premium drape that carries your logo. %GOAL_NOTE%',
                ],
                [
                    'fallback_name' => 'Studio Tailored Overshirt',
                    'fallback_price' => 2299,
                    'fallback_image' => 'image/placeholder.png',
                    'reason' => 'Layer over merch tees for a cohesive launch look. %PALETTE_NOTE%',
                ],
            ],
            'minimal|gift' => [
                [
                    'fallback_name' => 'Calm Mornings Gift Set',
                    'fallback_price' => 2099,
                    'fallback_image' => 'image/placeholder.png',
                    'reason' => 'Includes a luxe crew and matching beanie in a tactile gift box. %GOAL_NOTE% %PALETTE_NOTE%',
                ],
                [
                    'fallback_name' => 'Heritage Ribbed Cardigan',
                    'fallback_price' => 2499,
                    'fallback_image' => 'image/placeholder.png',
                    'reason' => 'Elevated layering piece that feels instantly thoughtful. %GOAL_NOTE%',
                ],
                [
                    'inventory_id' => 4,
                    'fallback_name' => 'Custom Designer Essentials Kit',
                    'fallback_price' => 1299,
                    'fallback_image' => 'image/placeholder.png',
                    'reason' => 'Invite them to imprint initials or a date for a personal touch. %PALETTE_NOTE%',
                ],
            ],
            'bold|everyday' => [
                [
                    'fallback_name' => 'Aurora Fade Hoodie',
                    'fallback_price' => 1999,
                    'fallback_image' => 'image/placeholder.png',
                    'reason' => 'Gradient print keeps daily fits feeling fresh. %GOAL_NOTE% %PALETTE_NOTE%',
                ],
                [
                    'inventory_id' => 4,
                    'fallback_name' => 'Custom Remix Designer Kit',
                    'fallback_price' => 1299,
                    'fallback_image' => 'image/placeholder.png',
                    'reason' => 'Spin up bold artwork without waiting on a studio slot. %PALETTE_NOTE%',
                ],
                [
                    'fallback_name' => 'Prism Clash Utility Vest',
                    'fallback_price' => 1699,
                    'fallback_image' => 'image/placeholder.png',
                    'reason' => 'Layer over basics to inject attitude instantly. %GOAL_NOTE%',
                ],
            ],
            'bold|launch' => [
                [
                    'inventory_id' => 4,
                    'fallback_name' => 'Launch Holograph Designer Kit',
                    'fallback_price' => 1299,
                    'fallback_image' => 'image/placeholder.png',
                    'reason' => 'Prep foil and neon treatments for your drop without bottlenecks. %GOAL_NOTE% %PALETTE_NOTE%',
                ],
                [
                    'fallback_name' => 'Spotlight Stage Jacket',
                    'fallback_price' => 2599,
                    'fallback_image' => 'image/placeholder.png',
                    'reason' => 'High-shine outerwear that photographs flawlessly. %GOAL_NOTE%',
                ],
                [
                    'fallback_name' => 'Fireline Mesh Tee Duo',
                    'fallback_price' => 1499,
                    'fallback_image' => 'image/placeholder.png',
                    'reason' => 'Bundle of two statement tees with breathable mesh panelling. %PALETTE_NOTE%',
                ],
            ],
            'bold|gift' => [
                [
                    'fallback_name' => 'Spectrum Gift Crate',
                    'fallback_price' => 2399,
                    'fallback_image' => 'image/placeholder.png',
                    'reason' => 'Packed with a vivid tee, enamel pin set, and note card. %GOAL_NOTE% %PALETTE_NOTE%',
                ],
                [
                    'fallback_name' => 'Glowline Travel Hoodie',
                    'fallback_price' => 2199,
                    'fallback_image' => 'image/placeholder.png',
                    'reason' => 'Glow-ink piping feels premium and playful in equal parts. %GOAL_NOTE%',
                ],
                [
                    'inventory_id' => 4,
                    'fallback_name' => 'Custom Remix Designer Kit',
                    'fallback_price' => 1299,
                    'fallback_image' => 'image/placeholder.png',
                    'reason' => 'Gift them a design session so they can push the colors further. %PALETTE_NOTE%',
                ],
            ],
            'default' => [
                [
                    'inventory_id' => 4,
                    'fallback_name' => 'Custom Remix Designer Kit',
                    'fallback_price' => 1299,
                    'fallback_image' => 'image/placeholder.png',
                    'reason' => 'A flexible designer base that adapts to your mood. %GOAL_NOTE% %PALETTE_NOTE%',
                ],
                [
                    'fallback_name' => 'Mystic Core Tee',
                    'fallback_price' => 899,
                    'fallback_image' => 'image/placeholder.png',
                    'reason' => 'Easy staple to pair with any capsule build.',
                ],
                [
                    'fallback_name' => 'Everyday Travel Tote',
                    'fallback_price' => 699,
                    'fallback_image' => 'image/placeholder.png',
                    'reason' => 'Carry-all that keeps your kit organized on the go.',
                ],
            ],
        ];
    }
}
