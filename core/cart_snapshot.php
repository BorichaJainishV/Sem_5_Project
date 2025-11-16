<?php
require_once __DIR__ . '/../session_helpers.php';
require_once __DIR__ . '/drop_promotions.php';

if (!function_exists('mystic_cart_fetch_inventory_rows')) {
    function mystic_cart_fetch_inventory_rows(mysqli $conn, array $inventoryIds): array
    {
        $inventoryIds = array_values(array_unique(array_filter(array_map('intval', $inventoryIds), static fn($id) => $id > 0)));
        if (empty($inventoryIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($inventoryIds), '?'));
        $types = str_repeat('i', count($inventoryIds));
        $stmt = $conn->prepare("SELECT inventory_id, product_name, price, image_url FROM inventory WHERE inventory_id IN ($placeholders) AND (is_archived = 0 OR is_archived IS NULL)");
        if (!$stmt) {
            return [];
        }

        $params = array_merge([$types], $inventoryIds);
        $refs = [];
        foreach ($params as $index => $value) {
            $refs[$index] = &$params[$index];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);

        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }

        $rows = [];
        $result = $stmt->get_result();
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $inventoryId = isset($row['inventory_id']) ? (int) $row['inventory_id'] : 0;
                if ($inventoryId <= 0) {
                    continue;
                }
                $rows[$inventoryId] = $row;
            }
            $result->free();
        }

        $stmt->close();

        return $rows;
    }
}

if (!function_exists('mystic_cart_fetch_custom_designs')) {
    function mystic_cart_fetch_custom_designs(mysqli $conn, array $designIds): array
    {
        $designIds = array_values(array_unique(array_filter(array_map('intval', $designIds), static fn($id) => $id > 0)));
        if (empty($designIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($designIds), '?'));
        $types = str_repeat('i', count($designIds));
        $stmt = $conn->prepare("SELECT design_id, product_name, price, front_preview_url FROM custom_designs WHERE design_id IN ($placeholders)");
        if (!$stmt) {
            return [];
        }

        $params = array_merge([$types], $designIds);
        $refs = [];
        foreach ($params as $index => $value) {
            $refs[$index] = &$params[$index];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);

        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }

        $designs = [];
        $result = $stmt->get_result();
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $designId = isset($row['design_id']) ? (int) $row['design_id'] : 0;
                if ($designId <= 0) {
                    continue;
                }
                $designs[$designId] = $row;
            }
            $result->free();
        }

        $stmt->close();

        return $designs;
    }
}

if (!function_exists('mystic_cart_snapshot')) {
    function mystic_cart_snapshot(mysqli $conn): array
    {
        ensure_session_started();

        $sessionCart = $_SESSION['cart'] ?? [];
        if (!is_array($sessionCart)) {
            $sessionCart = [];
        }

        $baseCart = [];
        foreach ($sessionCart as $inventoryId => $quantity) {
            $inventoryId = (int) $inventoryId;
            $quantity = (int) $quantity;
            if ($inventoryId <= 0 || $quantity <= 0) {
                continue;
            }
            $baseCart[$inventoryId] = $quantity;
        }

        $customDesignIds = get_custom_design_ids();

        $markdownContext = drop_promotion_get_markdown_context();
        $markdownActive = !empty($markdownContext['active']);
        $markdownOriginals = $markdownContext['original_prices'] ?? [];

        $bundleEvaluation = drop_promotion_calculate_bundle_freebies($baseCart);
        $freebies = isset($bundleEvaluation['grants']) && is_array($bundleEvaluation['grants'])
            ? array_filter(array_map('intval', $bundleEvaluation['grants']), static fn($qty) => $qty > 0)
            : [];

        $inventoryKeys = array_keys($baseCart);
        $inventoryLookup = mystic_cart_fetch_inventory_rows($conn, $inventoryKeys);

        $snapshot = [
            'lines' => [],
            'items' => 0,
            'paid_items' => 0,
            'subtotal' => 0.0,
            'savings' => 0.0,
            'bundle_value' => 0.0,
            'original_subtotal' => 0.0,
            'freebies' => $freebies,
            'bundle_slug' => $bundleEvaluation['slug'] ?? null,
            'bundle_rules' => $bundleEvaluation['applied'] ?? [],
            'markdown_active' => $markdownActive,
        ];

        foreach ($baseCart as $inventoryId => $quantity) {
            if ($inventoryId === 4) {
                continue;
            }

            $row = $inventoryLookup[$inventoryId] ?? null;
            if (!$row) {
                continue;
            }

            $unitPrice = isset($row['price']) ? (float) $row['price'] : 0.0;
            $lineSubtotal = $unitPrice * $quantity;

            $originalUnit = $markdownActive && isset($markdownOriginals[$inventoryId])
                ? (float) $markdownOriginals[$inventoryId]
                : null;
            $lineSavings = 0.0;
            if ($originalUnit !== null && $originalUnit > $unitPrice) {
                $lineSavings = ($originalUnit - $unitPrice) * $quantity;
            } else {
                $originalUnit = null;
            }

            $snapshot['lines'][] = [
                'id' => $inventoryId,
                'inventory_id' => $inventoryId,
                'key' => (string) $inventoryId,
                'name' => $row['product_name'] ?? ('Product #' . $inventoryId),
                'price' => $unitPrice,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'subtotal' => $lineSubtotal,
                'original_price' => $originalUnit,
                'savings' => $lineSavings,
                'savings_percent' => ($originalUnit !== null && $originalUnit > 0)
                    ? (int) round((($originalUnit - $unitPrice) / $originalUnit) * 100)
                    : 0,
                'thumbnail' => !empty($row['image_url']) ? $row['image_url'] : 'image/placeholder.png',
                'image_url' => !empty($row['image_url']) ? $row['image_url'] : 'image/placeholder.png',
                'meta' => '',
                'is_custom' => false,
                'custom_design_id' => null,
                'is_freebie' => false,
                'bundle_value' => 0.0,
            ];

            $snapshot['items'] += $quantity;
            $snapshot['paid_items'] += $quantity;
            $snapshot['subtotal'] += $lineSubtotal;
            $snapshot['savings'] += $lineSavings;
        }

        if (!empty($baseCart[4]) && !empty($customDesignIds)) {
            $customInventory = $inventoryLookup[4] ?? null;
            if (!$customInventory) {
                $customInventory = mystic_cart_fetch_inventory_rows($conn, [4])[4] ?? null;
            }

            $customUnitPrice = $customInventory ? (float) ($customInventory['price'] ?? 0.0) : 0.0;
            $customOriginal = $markdownActive && isset($markdownOriginals[4])
                ? (float) $markdownOriginals[4]
                : null;

            $designRows = mystic_cart_fetch_custom_designs($conn, $customDesignIds);

            foreach ($customDesignIds as $designId) {
                $designId = (int) $designId;
                if ($designId <= 0) {
                    continue;
                }

                $designRow = $designRows[$designId] ?? [];
                $designName = trim((string) ($designRow['product_name'] ?? ''));
                if ($designName === '') {
                    $designName = 'Custom design #' . $designId;
                } elseif (stripos($designName, '#' . $designId) === false) {
                    $designName .= ' #' . $designId;
                }

                $thumbnail = !empty($designRow['front_preview_url'])
                    ? $designRow['front_preview_url']
                    : (!empty($customInventory['image_url']) ? $customInventory['image_url'] : 'image/placeholder.png');

                $lineOriginal = $customOriginal;
                $lineSavings = 0.0;
                if ($lineOriginal !== null && $lineOriginal > $customUnitPrice) {
                    $lineSavings = $lineOriginal - $customUnitPrice;
                } else {
                    $lineOriginal = null;
                }

                $snapshot['lines'][] = [
                    'id' => 4,
                    'inventory_id' => 4,
                    'key' => 'custom-' . $designId,
                    'name' => $designName,
                    'price' => $customUnitPrice,
                    'unit_price' => $customUnitPrice,
                    'quantity' => 1,
                    'subtotal' => $customUnitPrice,
                    'original_price' => $lineOriginal,
                    'savings' => $lineSavings,
                    'savings_percent' => ($lineOriginal !== null && $lineOriginal > 0)
                        ? (int) round((($lineOriginal - $customUnitPrice) / $lineOriginal) * 100)
                        : 0,
                    'thumbnail' => $thumbnail,
                    'image_url' => $thumbnail,
                    'meta' => 'Saved design ready to personalize',
                    'is_custom' => true,
                    'custom_design_id' => $designId,
                    'is_freebie' => false,
                    'bundle_value' => 0.0,
                ];

                $snapshot['items'] += 1;
                $snapshot['paid_items'] += 1;
                $snapshot['subtotal'] += $customUnitPrice;
                $snapshot['savings'] += $lineSavings;
            }
        }

        if (!empty($freebies)) {
            $freebieIds = array_keys($freebies);
            $missingFreebieIds = array_diff($freebieIds, array_keys($inventoryLookup));
            if (!empty($missingFreebieIds)) {
                $inventoryLookup += mystic_cart_fetch_inventory_rows($conn, $missingFreebieIds);
            }

            $bundleLabel = 'Drop bundle bonus';
            if (!empty($bundleEvaluation['slug'])) {
                $bundleLabel .= ' · ' . $bundleEvaluation['slug'];
            }

            foreach ($freebies as $inventoryId => $quantity) {
                $inventoryId = (int) $inventoryId;
                $quantity = (int) $quantity;
                if ($inventoryId <= 0 || $quantity <= 0) {
                    continue;
                }

                $row = $inventoryLookup[$inventoryId] ?? null;
                if (!$row) {
                    continue;
                }

                $referencePrice = isset($row['price']) ? (float) $row['price'] : 0.0;
                $bundleWorth = $referencePrice * $quantity;

                $snapshot['lines'][] = [
                    'id' => $inventoryId,
                    'inventory_id' => $inventoryId,
                    'key' => 'freebie-' . $inventoryId,
                    'name' => ($row['product_name'] ?? ('Product #' . $inventoryId)) . ' (Free)',
                    'price' => 0.0,
                    'unit_price' => 0.0,
                    'quantity' => $quantity,
                    'subtotal' => 0.0,
                    'original_price' => null,
                    'savings' => 0.0,
                    'savings_percent' => 0,
                    'thumbnail' => !empty($row['image_url']) ? $row['image_url'] : 'image/placeholder.png',
                    'image_url' => !empty($row['image_url']) ? $row['image_url'] : 'image/placeholder.png',
                    'meta' => $bundleLabel,
                    'is_custom' => false,
                    'custom_design_id' => null,
                    'is_freebie' => true,
                    'bundle_value' => $bundleWorth,
                    'reference_price' => $referencePrice,
                ];

                $snapshot['items'] += $quantity;
                $snapshot['bundle_value'] += $bundleWorth;
            }
        }

        $snapshot['original_subtotal'] = $snapshot['subtotal'] + $snapshot['savings'];

        return $snapshot;
    }
}
