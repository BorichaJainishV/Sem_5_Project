<?php
// ---------------------------------------------------------------------
// cart_handler.php - Simplified and Corrected
// ---------------------------------------------------------------------
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/session_helpers.php';
require_once __DIR__ . '/core/drop_promotions.php';
include __DIR__ . '/db_connection.php';

if (!function_exists('mystic_cart_bundle_rules_with_limits')) {
function mystic_cart_bundle_rules_with_limits(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $payload = drop_promotion_load_bundle_rules();
    $rules = [];

    if (!empty($payload['rules']) && is_array($payload['rules'])) {
        foreach ($payload['rules'] as $index => $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $limit = isset($rule['limit_per_cart']) ? (int) $rule['limit_per_cart'] : 0;
            if ($limit <= 0) {
                continue;
            }

            $eligible = [];
            if (!empty($rule['eligible_inventory_ids']) && is_array($rule['eligible_inventory_ids'])) {
                foreach ($rule['eligible_inventory_ids'] as $inventoryId) {
                    $candidate = (int) $inventoryId;
                    if ($candidate > 0) {
                        $eligible[] = $candidate;
                    }
                }
            }

            $eligible = array_values(array_unique($eligible));
            if (empty($eligible)) {
                continue;
            }

            $rules[] = [
                'index' => (int) $index,
                'limit' => $limit,
                'eligible' => $eligible,
            ];
        }
    }

    $cached = [
        'slug' => $payload['slug'] ?? null,
        'rules' => $rules,
    ];

    return $cached;
}
}

if (!function_exists('mystic_cart_apply_bundle_limits')) {
function mystic_cart_apply_bundle_limits(array &$cart, int $touchedInventoryId = 0): void
{
    $bundleData = mystic_cart_bundle_rules_with_limits();
    $rules = $bundleData['rules'] ?? [];

    if (empty($rules) || empty($cart)) {
        return;
    }

    $touchedInventoryId = (int) $touchedInventoryId;
    $limitApplied = null;

    foreach ($rules as $rule) {
        $eligible = $rule['eligible'];
        $limit = (int) $rule['limit'];
        if ($limit <= 0) {
            continue;
        }

        $containsTouched = $touchedInventoryId > 0 && in_array($touchedInventoryId, $eligible, true);
        if (!$containsTouched && $touchedInventoryId > 0) {
            $containsRuleItem = false;
            foreach ($eligible as $eligibleId) {
                if (!empty($cart[$eligibleId])) {
                    $containsRuleItem = true;
                    break;
                }
            }
            if (!$containsRuleItem) {
                continue;
            }
        }

        $totalEligibleUnits = 0;
        foreach ($eligible as $eligibleId) {
            $totalEligibleUnits += (int) ($cart[$eligibleId] ?? 0);
        }

        if ($totalEligibleUnits <= $limit) {
            continue;
        }

        $overflow = $totalEligibleUnits - $limit;
        $adjustmentTargets = [];

        if ($containsTouched) {
            $adjustmentTargets[] = $touchedInventoryId;
        }

        foreach ($eligible as $eligibleId) {
            if (!in_array($eligibleId, $adjustmentTargets, true) && !empty($cart[$eligibleId])) {
                $adjustmentTargets[] = $eligibleId;
            }
        }

        foreach ($adjustmentTargets as $targetId) {
            if ($overflow <= 0) {
                break;
            }

            $currentQty = (int) ($cart[$targetId] ?? 0);
            if ($currentQty <= 0) {
                continue;
            }

            $reduceBy = min($currentQty, $overflow);
            $newQty = $currentQty - $reduceBy;

            if ($newQty <= 0) {
                unset($cart[$targetId]);
            } else {
                $cart[$targetId] = $newQty;
            }

            $overflow -= $reduceBy;
            $limitApplied = [
                'limit' => $limit,
                'rule_index' => $rule['index'],
                'inventory_id' => $targetId,
            ];
        }
    }

    if ($limitApplied !== null) {
        $_SESSION['cart_limit_notice'] = [
            'limited_to' => $limitApplied['limit'],
            'rule_index' => $limitApplied['rule_index'],
            'inventory_id' => $limitApplied['inventory_id'],
            'bundle_slug' => $bundleData['slug'] ?? null,
        ];
    }
}
}

// Only one cart is needed.
if (!isset($_SESSION['cart'])) { $_SESSION['cart'] = []; }

$action = $_GET['action'] ?? 'view';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$designId = isset($_GET['design_id']) ? (int)$_GET['design_id'] : null;
// For state-changing actions, require a valid CSRF token (preferably these should be POST)
$stateChanging = in_array($action, ['add','remove','update','clear','add_bundle'], true);
if ($stateChanging) {
    $csrf = $_GET['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        header('Location: cart.php');
        exit();
    }
}

switch ($action) {
    case 'add':
        if ($id) {
            if ($id === 4 && $designId) {
                add_custom_design_id($designId);
            } else {
                $availabilityStmt = $conn->prepare('SELECT stock_qty FROM inventory WHERE inventory_id = ? AND stock_qty > 0 AND (is_archived = 0 OR is_archived IS NULL)');
                if ($availabilityStmt) {
                    $availabilityStmt->bind_param('i', $id);
                    if ($availabilityStmt->execute()) {
                        $availabilityResult = $availabilityStmt->get_result();
                        if ($availabilityResult && $availabilityResult->num_rows > 0) {
                            $_SESSION['cart'][$id] = ($_SESSION['cart'][$id] ?? 0) + 1;
                            mystic_cart_apply_bundle_limits($_SESSION['cart'], $id);
                        }
                        if ($availabilityResult) { $availabilityResult->free(); }
                    }
                    $availabilityStmt->close();
                }
            }
        }
        // Redirect back to the previous page or the shop.
        header('Location: ' . ($_GET['redirect'] ?? 'shop.php'));
        exit();

    case 'remove':
        if ($id && isset($_SESSION['cart'][$id])) {
            if ($id === 4) {
                if ($designId) {
                    remove_custom_design_id($designId);
                } else {
                    clear_custom_design_ids();
                }
            } else {
                unset($_SESSION['cart'][$id]);
            }
        }
        header('Location: cart.php');
        exit();

    case 'update':
        if ($id && isset($_GET['qty'])) {
            $qty = max(0, (int)$_GET['qty']);
            if ($id === 4) {
                // Custom designs are managed per unique design; keep cart in sync with helper
                sync_custom_design_cart_quantity();
            } else {
                if ($qty === 0) {
                    unset($_SESSION['cart'][$id]);
                } else {
                    $stockStmt = $conn->prepare('SELECT stock_qty FROM inventory WHERE inventory_id = ? AND stock_qty >= ? AND (is_archived = 0 OR is_archived IS NULL)');
                    if ($stockStmt) {
                        $stockStmt->bind_param('ii', $id, $qty);
                        if ($stockStmt->execute()) {
                            $stockResult = $stockStmt->get_result();
                            if ($stockResult && $stockResult->num_rows > 0) {
                                $_SESSION['cart'][$id] = $qty;
                                mystic_cart_apply_bundle_limits($_SESSION['cart'], $id);
                            } else {
                                unset($_SESSION['cart'][$id]);
                            }
                            if ($stockResult) { $stockResult->free(); }
                        }
                        $stockStmt->close();
                    }
                }
            }
        }
        header('Location: cart.php');
        exit();

    case 'clear':
        $_SESSION['cart'] = [];
        clear_custom_design_ids();
        header('Location: cart.php');
        exit();

    case 'add_bundle':
        $bundleRaw = $_GET['bundle_ids'] ?? '';
        if (!empty($bundleRaw)) {
            $bundleIds = array_unique(array_filter(array_map('intval', explode(',', $bundleRaw)), static fn($value) => $value > 0 && $value !== 4));
            if (!empty($bundleIds)) {
                $availabilityStmt = $conn->prepare('SELECT stock_qty FROM inventory WHERE inventory_id = ? AND stock_qty > 0 AND (is_archived = 0 OR is_archived IS NULL)');
                if ($availabilityStmt) {
                    foreach ($bundleIds as $bundleId) {
                        $availabilityStmt->bind_param('i', $bundleId);
                        if ($availabilityStmt->execute()) {
                            $availabilityResult = $availabilityStmt->get_result();
                            if ($availabilityResult && $availabilityResult->num_rows > 0) {
                                $_SESSION['cart'][$bundleId] = ($_SESSION['cart'][$bundleId] ?? 0) + 1;
                                mystic_cart_apply_bundle_limits($_SESSION['cart'], $bundleId);
                            }
                            if ($availabilityResult) { $availabilityResult->free(); }
                        }
                    }
                    $availabilityStmt->close();
                }
            }
        }
        header('Location: ' . ($_GET['redirect'] ?? 'cart.php'));
        exit();
}
exit();
?>