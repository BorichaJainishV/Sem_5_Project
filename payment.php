<?php
// ---------------------------------------------------------------------
// payment.php - Step 2: Confirm and Place Order (Definitive Fix v3)
// ---------------------------------------------------------------------
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/session_helpers.php';

// Redirect if critical information is missing
if (!isset($_SESSION['customer_id']) || !isset($_SESSION['shipping_info']) || empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit();
}

include 'db_connection.php';
include 'email_handler.php';
require_once __DIR__ . '/core/drop_promotions.php';
require_once __DIR__ . '/core/custom_reward_wallet.php';
require_once __DIR__ . '/core/cart_snapshot.php';

$cartSnapshot = mystic_cart_snapshot($conn);
$bundleFreebies = isset($cartSnapshot['freebies']) && is_array($cartSnapshot['freebies'])
    ? array_filter(array_map('intval', $cartSnapshot['freebies']), static fn($qty) => $qty > 0)
    : [];

function calculateCostBreakdown(float $orderTotal): array
{
    if ($orderTotal <= 0) {
        return [
            ['label' => 'Materials & Fabric', 'amount' => 0.0, 'description' => 'Premium cotton tees and specialty fabric blanks.'],
            ['label' => 'Print Lab Time', 'amount' => 0.0, 'description' => 'Design preparation, color calibration, and curing.'],
            ['label' => 'Shipping & Handling', 'amount' => 0.0, 'description' => 'Packaging, quality checks, and door-step delivery.'],
        ];
    }

    $materials = round($orderTotal * 0.57, 2);
    $print = round($orderTotal * 0.26, 2);
    $shipping = round($orderTotal - ($materials + $print), 2);

    if ($shipping < 0) {
        $print += $shipping;
        $shipping = 0.0;
    }

    if ($shipping < 49 && $orderTotal >= 400) {
        $shipping = 49.0;
        $print = round($orderTotal - ($materials + $shipping), 2);
    }

    $adjustment = round($orderTotal - ($materials + $print + $shipping), 2);
    if (abs($adjustment) >= 0.01) {
        $shipping = round($shipping + $adjustment, 2);
    }

    if ($shipping < 0) {
        $materials = round($materials + $shipping, 2);
        $shipping = 0.0;
    }

    return [
        [
            'label' => 'Materials & Fabric',
            'amount' => max($materials, 0.0),
            'description' => 'Premium blanks, eco inks, and sustainable packaging supplies.',
        ],
        [
            'label' => 'Print Lab Time',
            'amount' => max($print, 0.0),
            'description' => 'Design setup, calibration, and double-pass curing for lasting color.',
        ],
        [
            'label' => 'Shipping & Handling',
            'amount' => max($shipping, 0.0),
            'description' => 'Protective packing, dispatch coordination, and doorstep delivery.',
        ],
    ];
}

// --- Process the FINAL order placement ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_order') {

    // CSRF validation
    $csrf = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        $_SESSION['checkout_error'] = 'Invalid request.';
        header('Location: payment.php');
        exit();
    }

    $shipping_info   = $_SESSION['shipping_info'];
    $customerId      = $_SESSION['customer_id'];
    $cart            = $_SESSION['cart'];
    $customDesignIds = get_custom_design_ids();

    try {
        $conn->begin_transaction();

        $orderIds = [];
        $emailItems = [];
        $rewardAutoApplied = [
            'applied' => 0.0,
            'entries' => [],
        ];

        // Pre-fetch custom product price if needed
        $customProductPrice = null;
        if (!empty($customDesignIds)) {
            $priceStmt = $conn->prepare("SELECT price FROM inventory WHERE inventory_id = ? AND (is_archived = 0 OR is_archived IS NULL)");
            if (!$priceStmt) {
                throw new Exception("Prepare failed (custom price): " . $conn->error);
            }
            $customId = 4;
            $priceStmt->bind_param("i", $customId);
            if (!$priceStmt->execute()) {
                throw new Exception("Execute failed (custom price): " . $priceStmt->error);
            }
            $priceResult = $priceStmt->get_result();
            $customProductPrice = (float)($priceResult->fetch_assoc()['price'] ?? 0);
            $priceStmt->close();
            if ($customProductPrice < 2000.0) {
                $customProductPrice = 2000.0;
            }
        }

        $rewardContext = drop_promotion_get_active_custom_reward();

        $customDesignLookup = null;

        if (!empty($customDesignIds)) {
            $customDesignLookup = $conn->prepare("SELECT customer_id, product_name, price, front_preview_url, back_preview_url, texture_map_url, design_json, apparel_type, base_color FROM custom_designs WHERE design_id = ?");
            if (!$customDesignLookup) {
                throw new Exception("Prepare failed (custom lookup): " . $conn->error);
            }
        }

    $inventoryInfoStmt = $conn->prepare("SELECT product_name, price, image_url FROM inventory WHERE inventory_id = ? AND (is_archived = 0 OR is_archived IS NULL)");
        if (!$inventoryInfoStmt) {
            throw new Exception("Prepare failed (inventory lookup): " . $conn->error);
        }

        // Loop through each distinct item in the cart
        foreach ($cart as $inventory_id => $quantity) {
            if ($inventory_id == 4) {
                if (empty($customDesignIds)) {
                    continue;
                }

                foreach ($customDesignIds as $designId) {
                    $current_design_id = (int)$designId;
                    if ($current_design_id <= 0) {
                        throw new Exception('Invalid custom design reference encountered.');
                    }

                    if (!$customDesignLookup) {
                        throw new Exception('Custom design statement was not initialised.');
                    }

                    $customDesignLookup->bind_param("i", $current_design_id);
                    if (!$customDesignLookup->execute()) {
                        throw new Exception("Execute failed (custom lookup): " . $customDesignLookup->error);
                    }

                    $customResult = $customDesignLookup->get_result();
                    $customData = $customResult->fetch_assoc();
                    if (!$customData) {
                        throw new Exception('Unable to locate the requested custom design.');
                    }
                    $customResult->free();

                    $designerId = isset($customData['customer_id']) ? (int) $customData['customer_id'] : 0;
                    $frontPreview = $customData['front_preview_url'];
                    $backPreview = $customData['back_preview_url'] ?: $frontPreview;
                    $customProductName = !empty($customData['product_name']) ? $customData['product_name'] : 'Custom Apparel';
                    $customUnitPrice = isset($customData['price']) ? (float)$customData['price'] : $customProductPrice;
                    if ($customUnitPrice <= 0) {
                        $customUnitPrice = $customProductPrice;
                    }

                    $designReferenceId = $current_design_id;

                    $orderStmt = $conn->prepare("INSERT INTO orders (customer_id, design_id, inventory_id, status, order_date) VALUES (?, ?, ?, 'pending', CURDATE())");
                    $orderStmt->bind_param("iii", $customerId, $designReferenceId, $inventory_id);
                    if (!$orderStmt->execute()) {
                        throw new Exception("Execute failed (orders custom): " . $orderStmt->error);
                    }
                    $newOrderId = $orderStmt->insert_id;
                    $orderIds[] = $newOrderId;

                    $orderStmt->close();

                    $updateStmt = $conn->prepare("UPDATE inventory SET stock_qty = stock_qty - 1 WHERE inventory_id = ? AND stock_qty >= 1");
                    $updateStmt->bind_param("i", $inventory_id);
                    if (!$updateStmt->execute()) {
                        throw new Exception("Execute failed (inventory custom): " . $updateStmt->error);
                    }

                    $updateStmt->close();

                    $billStmt = $conn->prepare("INSERT INTO billing (customer_id, order_id, amount, status, billing_date) VALUES (?, ?, ?, 'Paid', CURDATE())");
                    $billStmt->bind_param("iid", $customerId, $newOrderId, $customUnitPrice);
                    if (!$billStmt->execute()) {
                        throw new Exception("Execute failed (billing custom): " . $billStmt->error);
                    }
                    $billStmt->close();

                    if ($rewardContext && $designerId > 0 && $designerId !== $customerId) {
                        custom_reward_grant(
                            $conn,
                            $designerId,
                            $customerId,
                            $newOrderId,
                            $designReferenceId,
                            $rewardContext['discount_value'],
                            $rewardContext['drop_slug']
                        );
                    }

                    $emailItems[] = [
                        'name' => $customProductName,
                        'quantity' => 1,
                        'price' => $customUnitPrice,
                        'preview' => $frontPreview ?: 'image/placeholder.png',
                        'type' => 'custom'
                    ];
                }
                continue;
            }

            // Handle regular catalog items with placeholder designs
            $placeholder_stmt = $conn->prepare("INSERT INTO designs (customer_id, design_file, design_type, created_at) VALUES (?, 'N/A', 'standard_product', CURDATE())");
            if (!$placeholder_stmt) {
                throw new Exception("Prepare failed (placeholder): " . $conn->error);
            }
            $placeholder_stmt->bind_param("i", $customerId);
            if (!$placeholder_stmt->execute()) {
                throw new Exception("Execute failed (placeholder): " . $placeholder_stmt->error);
            }
            $current_design_id = $placeholder_stmt->insert_id;

            $placeholder_stmt->close();

            if (!$current_design_id) {
                throw new Exception("Failed to acquire a Design ID for product #" . $inventory_id);
            }

            $inventoryInfoStmt->bind_param("i", $inventory_id);
            if (!$inventoryInfoStmt->execute()) {
                throw new Exception("Execute failed (inventory lookup): " . $inventoryInfoStmt->error);
            }
            $inventoryInfoResult = $inventoryInfoStmt->get_result();
            $inventoryInfo = $inventoryInfoResult->fetch_assoc();
            $inventoryInfoResult->free();

            if (!$inventoryInfo) {
                throw new Exception('Inventory item not found for checkout.');
            }

            $productName = $inventoryInfo['product_name'] ?? ('Product #' . $inventory_id);
            $price = isset($inventoryInfo['price']) ? (float)$inventoryInfo['price'] : 0.0;
            $productImage = $inventoryInfo['image_url'] ?? 'image/placeholder.png';
            $subtotal = $price * $quantity;

            $orderStmt = $conn->prepare("INSERT INTO orders (customer_id, design_id, inventory_id, status, order_date) VALUES (?, ?, ?, 'pending', CURDATE())");
            $orderStmt->bind_param("iii", $customerId, $current_design_id, $inventory_id);
            if (!$orderStmt->execute()) {
                throw new Exception("Execute failed (orders): " . $orderStmt->error);
            }
            $newOrderId = $orderStmt->insert_id;
            $orderIds[] = $newOrderId;

            $orderStmt->close();

            $updateStmt = $conn->prepare("UPDATE inventory SET stock_qty = stock_qty - ? WHERE inventory_id = ? AND stock_qty >= ?");
            $updateStmt->bind_param("iii", $quantity, $inventory_id, $quantity);
            if (!$updateStmt->execute()) {
                throw new Exception("Execute failed (inventory): " . $updateStmt->error);
            }

            $updateStmt->close();

            $billStmt = $conn->prepare("INSERT INTO billing (customer_id, order_id, amount, status, billing_date) VALUES (?, ?, ?, 'Paid', CURDATE())");
            $billStmt->bind_param("iid", $customerId, $newOrderId, $subtotal);
            if (!$billStmt->execute()) {
                throw new Exception("Execute failed (billing): " . $billStmt->error);
            }
            $billStmt->close();

            $emailItems[] = [
                'name' => $productName,
                'quantity' => $quantity,
                'price' => $price,
                'preview' => $productImage,
                'type' => 'standard'
            ];
        }

        if (!empty($bundleFreebies)) {
            $freePlaceholderStmt = $conn->prepare("INSERT INTO designs (customer_id, design_file, design_type, created_at) VALUES (?, 'N/A', 'standard_product', CURDATE())");
            if (!$freePlaceholderStmt) {
                throw new Exception('Prepare failed (freebie placeholder): ' . $conn->error);
            }
            $freePlaceholderStmt->bind_param('i', $customerId);

            foreach ($bundleFreebies as $freeInventoryId => $freeQuantity) {
                $freeInventoryId = (int) $freeInventoryId;
                $freeQuantity = (int) $freeQuantity;
                if ($freeInventoryId <= 0 || $freeQuantity <= 0) {
                    continue;
                }
                if ($freeInventoryId === 4) {
                    continue;
                }

                if (!$freePlaceholderStmt->execute()) {
                    throw new Exception('Execute failed (freebie placeholder): ' . $freePlaceholderStmt->error);
                }

                $freeDesignId = $freePlaceholderStmt->insert_id;
                if (!$freeDesignId) {
                    throw new Exception('Failed to acquire design id for bundle freebie.');
                }

                $inventoryInfoStmt->bind_param('i', $freeInventoryId);
                if (!$inventoryInfoStmt->execute()) {
                    throw new Exception('Execute failed (inventory lookup freebie): ' . $inventoryInfoStmt->error);
                }
                $inventoryInfoResult = $inventoryInfoStmt->get_result();
                $inventoryInfo = $inventoryInfoResult ? $inventoryInfoResult->fetch_assoc() : null;
                if ($inventoryInfoResult) {
                    $inventoryInfoResult->free();
                }
                if (!$inventoryInfo) {
                    throw new Exception('Inventory item not found for bundle freebie.');
                }

                $productName = ($inventoryInfo['product_name'] ?? ('Product #' . $freeInventoryId)) . ' (Free)';
                $productImage = $inventoryInfo['image_url'] ?? 'image/placeholder.png';
                $referencePrice = isset($inventoryInfo['price']) ? (float) $inventoryInfo['price'] : 0.0;

                $orderStmt = $conn->prepare("INSERT INTO orders (customer_id, design_id, inventory_id, status, order_date) VALUES (?, ?, ?, 'pending', CURDATE())");
                if (!$orderStmt) {
                    throw new Exception('Prepare failed (orders freebie): ' . $conn->error);
                }
                $orderStmt->bind_param('iii', $customerId, $freeDesignId, $freeInventoryId);
                if (!$orderStmt->execute()) {
                    throw new Exception('Execute failed (orders freebie): ' . $orderStmt->error);
                }
                $freeOrderId = $orderStmt->insert_id;
                $orderStmt->close();
                $orderIds[] = $freeOrderId;

                $updateStmt = $conn->prepare('UPDATE inventory SET stock_qty = stock_qty - ? WHERE inventory_id = ? AND stock_qty >= ?');
                if (!$updateStmt) {
                    throw new Exception('Prepare failed (inventory freebie): ' . $conn->error);
                }
                $updateStmt->bind_param('iii', $freeQuantity, $freeInventoryId, $freeQuantity);
                if (!$updateStmt->execute()) {
                    throw new Exception('Execute failed (inventory freebie): ' . $updateStmt->error);
                }
                $updateStmt->close();

                $billStmt = $conn->prepare("INSERT INTO billing (customer_id, order_id, amount, status, billing_date) VALUES (?, ?, 0, 'Bundle Freebie', CURDATE())");
                if (!$billStmt) {
                    throw new Exception('Prepare failed (billing freebie): ' . $conn->error);
                }
                $billStmt->bind_param('ii', $customerId, $freeOrderId);
                if (!$billStmt->execute()) {
                    throw new Exception('Execute failed (billing freebie): ' . $billStmt->error);
                }
                $billStmt->close();

                $emailItems[] = [
                    'name' => $productName,
                    'quantity' => $freeQuantity,
                    'price' => 0.0,
                    'preview' => $productImage,
                    'type' => 'bundle_freebie',
                    'reference_price' => $referencePrice,
                ];
            }

            $freePlaceholderStmt->close();
        }

        if ($customDesignLookup) {
            $customDesignLookup->close();
        }
        if ($inventoryInfoStmt) {
            $inventoryInfoStmt->close();
        }

        $orderSubtotal = 0.0;
        foreach ($emailItems as $item) {
            $itemQuantity = (int) ($item['quantity'] ?? 1);
            $itemPrice = (float) ($item['price'] ?? 0.0);
            $orderSubtotal += $itemQuantity * $itemPrice;
        }

        $rewardAutoApplied = custom_reward_auto_apply($conn, $customerId, $orderSubtotal, $orderIds);
        if ($rewardAutoApplied['applied'] > 0) {
            $appliedCredit = round((float) $rewardAutoApplied['applied'], 2);

            $emailItems[] = [
                'name' => 'Creator reward credit',
                'quantity' => 1,
                'price' => -$appliedCredit,
                'preview' => 'image/placeholder.png',
                'type' => 'reward',
            ];

            if (!empty($orderIds)) {
                $primaryOrderId = (int) $orderIds[0];
                $creditStmt = $conn->prepare("INSERT INTO billing (customer_id, order_id, amount, status, billing_date) VALUES (?, ?, ?, 'Reward Credit', CURDATE())");
                if (!$creditStmt) {
                    throw new Exception('Prepare failed (reward credit billing): ' . $conn->error);
                }
                $negativeAmount = -$appliedCredit;
                $creditStmt->bind_param('iid', $customerId, $primaryOrderId, $negativeAmount);
                if (!$creditStmt->execute()) {
                    throw new Exception('Execute failed (reward credit billing): ' . $creditStmt->error);
                }
                $creditStmt->close();
            }
        }

        $conn->commit();

        $order_id_string = implode(', ', $orderIds);
        $_SESSION['last_order_id'] = $order_id_string;

        $emailTotal = 0.0;
        foreach ($emailItems as $item) {
            $emailTotal += ((float)$item['price']) * (int)($item['quantity'] ?? 1);
        }

        $emailContext = [
            'items' => $emailItems,
            'total' => $emailTotal,
            'shipping' => [
                'full_name' => $shipping_info['full_name'] ?? '',
                'address' => $shipping_info['address'] ?? '',
                'email' => $shipping_info['email'] ?? ''
            ]
        ];
        if ($rewardAutoApplied['applied'] > 0) {
            $emailContext['reward_credit'] = $rewardAutoApplied;
        }

        $orderSummaryItems = array_map(static function (array $item): array {
            $quantity = (int) ($item['quantity'] ?? 1);
            $unitPrice = (float) ($item['price'] ?? 0.0);
            return [
                'name' => $item['name'] ?? 'Custom Item',
                'quantity' => $quantity,
                'subtotal' => $quantity * $unitPrice,
            ];
        }, $emailItems);

        $_SESSION['last_order_meta'] = [
            'total' => $emailTotal,
            'breakdown' => calculateCostBreakdown($emailTotal),
            'items' => $orderSummaryItems,
            'shipping' => [
                'full_name' => $shipping_info['full_name'] ?? '',
                'address' => $shipping_info['address'] ?? '',
                'city' => $shipping_info['city'] ?? '',
                'state' => $shipping_info['state'] ?? '',
                'postal_code' => $shipping_info['postal_code'] ?? '',
                'email' => $shipping_info['email'] ?? '',
                'phone' => $shipping_info['phone'] ?? ''
            ],
            'milestones' => [
                'print_window_start' => date('M j, Y'),
                'print_window_end' => date('M j, Y', strtotime('+2 days')),
                'ship_by' => date('M j, Y', strtotime('+3 days')),
                'expected_delivery' => date('M j, Y', strtotime('+5 days')),
            ],
        ];
        if ($rewardAutoApplied['applied'] > 0) {
            $_SESSION['last_order_meta']['reward_credit'] = $rewardAutoApplied;
        }

        clear_custom_design_ids();
        unset($_SESSION['cart'], $_SESSION['shipping_info']);

        try {
            sendOrderConfirmationEmail($shipping_info['email'], $shipping_info['full_name'], $order_id_string, $emailContext);
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
        }

        header('Location: order_success.php');
        exit();

    } catch (Throwable $e) {
        $conn->rollback();
        error_log("CRITICAL: Order placement failed with error: " . $e->getMessage());
        $_SESSION['checkout_error'] = "Could not place your order. Please try again.";
        $_SESSION['checkout_error_detail'] = $e->getMessage();
        header('Location: payment.php');
        exit();
    }
}

// --- Display the confirmation page (HTML) ---
$cart_items = [];
foreach ($cartSnapshot['lines'] as $line) {
    $cart_items[] = [
        'name' => $line['name'],
        'subtotal' => $line['subtotal'],
        'quantity' => (int) ($line['quantity'] ?? 0),
        'unit_price' => isset($line['unit_price']) ? (float) $line['unit_price'] : (float) ($line['price'] ?? 0.0),
        'preview' => $line['image_url'] ?? $line['thumbnail'] ?? 'image/placeholder.png',
        'type' => !empty($line['is_freebie']) ? 'bundle_freebie' : (!empty($line['is_custom']) ? 'custom' : 'standard'),
        'is_freebie' => !empty($line['is_freebie']),
        'bundle_value' => isset($line['bundle_value']) ? (float) $line['bundle_value'] : 0.0,
        'meta' => $line['meta'] ?? '',
    ];
}
$total_price = isset($cartSnapshot['subtotal']) ? (float) $cartSnapshot['subtotal'] : 0.0;
$bundle_value = isset($cartSnapshot['bundle_value']) ? (float) $cartSnapshot['bundle_value'] : 0.0;
$costBreakdown = calculateCostBreakdown($total_price);

$shipWindowStart = new DateTime('+2 days');
$shipWindowEnd = new DateTime('+5 days');
$estimatedShipWindow = $shipWindowStart->format('M j') . ' – ' . $shipWindowEnd->format('M j, Y');

$displayShippingInfo = $_SESSION['shipping_info'] ?? [];
$orderMetaSession = $_SESSION['last_order_meta'] ?? [];
$customerIdForContact = $_SESSION['customer_id'] ?? null;
$lastOrderReference = $_SESSION['last_order_id'] ?? '';
$customerContactEmail = trim((string)($displayShippingInfo['email'] ?? ''));

if (empty($_SESSION['support_chat_id'])) {
    try {
        $chatSeed = strtoupper(bin2hex(random_bytes(3)));
    } catch (Exception $e) {
        $chatSeed = strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 6));
    }
    $_SESSION['support_chat_id'] = 'CHAT-' . $chatSeed;
}
$chatLogId = $_SESSION['support_chat_id'];

$supportNotes = $_SESSION['support_notes'] ?? [];

$whatsappNumber = '+91 72010 54125';
$whatsappDigits = preg_replace('/\D+/', '', $whatsappNumber);
$whatsappLines = [];

$customerNameForContact = trim((string)($displayShippingInfo['full_name'] ?? ''));
if ($customerNameForContact !== '') {
    $whatsappLines[] = 'Hi Mystic team, this is ' . $customerNameForContact . '.';
} else {
    $whatsappLines[] = 'Hi Mystic team, this is a Mystic Clothing customer.';
}

if (!empty($customerIdForContact)) {
    $whatsappLines[] = 'Customer ID: ' . $customerIdForContact;
}

if (!empty($lastOrderReference)) {
    $whatsappLines[] = 'Order reference: ' . $lastOrderReference;
}

if (!empty($displayShippingInfo['email'])) {
    $whatsappLines[] = 'Email: ' . $displayShippingInfo['email'];
}

if (!empty($displayShippingInfo['phone'])) {
    $whatsappLines[] = 'Phone: ' . $displayShippingInfo['phone'];
}

$addressParts = [];
if (!empty($displayShippingInfo['address'])) {
    $addressParts[] = $displayShippingInfo['address'];
}
if (!empty($displayShippingInfo['city'])) {
    $addressParts[] = $displayShippingInfo['city'];
}
if (!empty($displayShippingInfo['state'])) {
    $addressParts[] = $displayShippingInfo['state'];
}
if (!empty($displayShippingInfo['postal_code'])) {
    $addressParts[] = $displayShippingInfo['postal_code'];
}
if (!empty($addressParts)) {
    $whatsappLines[] = 'Ship to: ' . implode(', ', $addressParts);
}

if (!empty($cart_items)) {
    $itemSummaries = [];
    foreach ($cart_items as $item) {
        if (!empty($item['name'])) {
            $itemSummaries[] = $item['name'];
        }
    }
    if (!empty($itemSummaries)) {
        $itemLine = implode(', ', array_slice($itemSummaries, 0, 3));
        if (count($itemSummaries) > 3) {
            $itemLine .= ', ...';
        }
        $whatsappLines[] = 'Items: ' . $itemLine;
    }
}

if ($total_price > 0) {
    $whatsappLines[] = 'Checkout total: ₹' . number_format($total_price, 2);
}

$whatsappBaseLines = $whatsappLines;
$hasAgentReplies = array_reduce($supportNotes, static function ($carry, $note) {
    return $carry || (($note['role'] ?? '') === 'agent');
}, false);
if ($hasAgentReplies && $chatLogId) {
    $whatsappLines[] = 'Chat log ID: ' . $chatLogId;
}

$whatsappLines[] = 'I need help with my Mystic Clothing order.';

$whatsappMessage = implode("\n", $whatsappLines);
$whatsappBaseMessage = implode("\n", $whatsappBaseLines) . "\n" . 'I need help with my Mystic Clothing order.';
$whatsappLink = $whatsappDigits ? 'https://wa.me/' . $whatsappDigits . '?text=' . rawurlencode($whatsappMessage) : '';

$primarySupportEmail = 'jvb.ombca2023@gmail.com';
$orderMeta = $orderMetaSession;
$orderMeta['chat_log_id'] = $chatLogId;
$supportConfig = [
    'hours' => ['start' => 9, 'end' => 21, 'timezone' => 'Asia/Kolkata'],
    'support_email' => $primarySupportEmail,
    'whatsapp' => [
        'number' => $whatsappNumber,
        'link' => $whatsappLink,
        'message' => $whatsappMessage,
        'base_message' => $whatsappBaseMessage,
        'digits' => $whatsappDigits,
        'chat_log_id' => $chatLogId,
    ],
];

$supportNotesJson = htmlspecialchars(json_encode($supportNotes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
$orderMetaJson = htmlspecialchars(json_encode($orderMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
$supportConfigJson = htmlspecialchars(json_encode($supportConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');

include 'header.php';
?>

<style>
    details.cost-breakdown {
        margin-top: 1rem;
        border: 1px solid var(--color-border);
        border-radius: 0.85rem;
        padding: 1rem 1.15rem;
        background: var(--color-surface-alt);
    }
    details.cost-breakdown summary {
        cursor: pointer;
        font-weight: 600;
        color: var(--color-primary-dark);
        list-style: none;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    details.cost-breakdown summary::-webkit-details-marker {
        display: none;
    }
    .breakdown-list {
        margin-top: 0.9rem;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    .breakdown-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
    }
    .breakdown-row span {
        display: block;
    }
    .breakdown-label {
        font-weight: 600;
        color: var(--color-dark);
    }
    .breakdown-description {
        font-size: 0.75rem;
        color: var(--color-muted);
        margin-top: 0.25rem;
        max-width: 240px;
    }
    .live-chat-card {
        border: 1px solid var(--color-border);
        border-radius: 1rem;
        box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
        background: var(--color-surface-elevated);
        color: var(--color-body);
    }
    .chat-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.25rem 1.5rem 0.75rem;
    }
    .chat-header h3 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--color-dark);
    }
    .chat-header button {
        align-self: flex-start;
    }
    .chat-panel {
        padding: 0 1.5rem 1.5rem;
        color: var(--color-body);
    }
    .chat-history {
        background: var(--color-surface-alt);
        border: 1px solid var(--color-border-light);
        border-radius: 0.85rem;
        padding: 0.75rem;
        max-height: 220px;
        overflow-y: auto;
        display: none;
        margin-bottom: 0.75rem;
    }
    .chat-message {
        display: flex;
        align-items: flex-start;
        gap: 0.6rem;
        font-size: 0.85rem;
        padding: 0.35rem 0.45rem;
        border-radius: 0.6rem;
    }
    .chat-message.agent {
        background: var(--color-primary-light);
        color: var(--color-primary-dark);
    }
    .chat-message.customer {
        background: var(--color-success-light);
        color: var(--color-secondary-hover);
        align-self: flex-end;
    }
    .chat-quick-actions {
        display: none;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
    }
    .chat-quick-actions button {
        padding: 0.35rem 0.7rem;
        border-radius: 999px;
        border: 1px solid rgba(99, 102, 241, 0.35);
        background: rgba(99, 102, 241, 0.12);
        color: var(--color-primary-dark);
        font-size: 0.75rem;
        font-weight: 600;
        transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
    }
    .chat-quick-actions button:hover,
    .chat-quick-actions button:focus-visible {
        background: rgba(79, 70, 229, 0.2);
        border-color: rgba(79, 70, 229, 0.45);
        outline: none;
    }
    .chat-input {
        display: none;
        flex-direction: column;
        gap: 0.6rem;
    }
    .chat-input textarea {
        border: 1px solid var(--color-border);
        border-radius: 0.65rem;
        padding: 0.6rem 0.75rem;
        font-size: 0.9rem;
        resize: vertical;
        min-height: 80px;
        background: var(--color-surface);
        color: var(--color-body);
    }
    .chat-input .btn.btn-outline {
        border-color: rgba(99, 102, 241, 0.35);
        color: var(--color-primary-dark);
    }
    .chat-input .btn.btn-outline:hover,
    .chat-input .btn.btn-outline:focus-visible {
        border-color: rgba(79, 70, 229, 0.55);
        background: rgba(79, 70, 229, 0.12);
    }
    .chat-meta {
        font-size: 0.75rem;
        color: var(--color-muted);
    }
    .chat-status {
        display: none;
        font-size: 0.75rem;
        color: var(--color-success-dark, #047857);
        margin-bottom: 0.6rem;
    }
    .chat-offline {
        display: none;
        border: 1px dashed rgba(220, 38, 38, 0.35);
        background: rgba(254, 226, 226, 0.65);
        color: #7f1d1d;
        font-size: 0.8rem;
        padding: 0.65rem 0.75rem;
        border-radius: 0.75rem;
        margin-bottom: 0.75rem;
    }
    .chat-suggestions {
        display: none;
        flex-direction: column;
        gap: 0.45rem;
        margin-bottom: 0.85rem;
    }
    .chat-suggestions a {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.35rem 0.55rem;
        border-radius: 0.6rem;
        background: rgba(99, 102, 241, 0.12);
        color: var(--color-primary-dark);
        font-size: 0.78rem;
        font-weight: 600;
        text-decoration: none;
        transition: background 0.2s ease, color 0.2s ease;
    }
    .chat-suggestions a:hover,
    .chat-suggestions a:focus-visible {
        background: rgba(79, 70, 229, 0.2);
        color: var(--color-primary-dark);
        outline: none;
    }
    .chat-suggestions a .indicator {
        display: inline-block;
        width: 0.5rem;
        height: 0.5rem;
        border-radius: 999px;
        background: currentColor;
    }
    .chat-message.typing {
        gap: 0.25rem;
    }
    .typing-dots {
        display: inline-flex;
        gap: 0.2rem;
        align-items: center;
    }
    .typing-dots span {
        display: inline-block;
        width: 0.25rem;
        height: 0.25rem;
        border-radius: 999px;
        background: currentColor;
        animation: typing-bounce 1s infinite ease-in-out;
    }
    .typing-dots span:nth-child(2) { animation-delay: 0.15s; }
    .typing-dots span:nth-child(3) { animation-delay: 0.3s; }
    @keyframes typing-bounce {
        0%, 80%, 100% { transform: translateY(0); opacity: 0.3; }
        40% { transform: translateY(-3px); opacity: 1; }
    }
    .chat-resolution {
        margin-top: 1rem;
        border-top: 1px solid var(--color-border-light);
        padding-top: 0.9rem;
        display: none;
        flex-direction: column;
        gap: 0.75rem;
    }
    .chat-resolution h4 {
        margin: 0;
        font-size: 0.85rem;
        color: var(--color-dark);
        font-weight: 700;
    }
    .chat-resolution-list {
        display: grid;
        gap: 0.5rem;
    }
    .chat-resolution-note {
        border: 1px solid var(--color-border-light);
        border-radius: 0.7rem;
        padding: 0.6rem 0.75rem;
        font-size: 0.78rem;
        background: var(--color-surface-alt);
        color: var(--color-body);
    }
    .chat-escalate-form {
        display: none;
        flex-direction: column;
        gap: 0.6rem;
        border: 1px solid var(--color-border-light);
        border-radius: 0.85rem;
        padding: 0.75rem;
        background: var(--color-surface-alt);
        margin-top: 0.75rem;
    }
    .chat-escalate-form label {
        font-size: 0.72rem;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.05em;
        color: var(--color-muted);
    }
    .chat-escalate-form input,
    .chat-escalate-form textarea,
    .chat-escalate-form select {
        border: 1px solid var(--color-border);
        border-radius: 0.6rem;
        padding: 0.55rem 0.65rem;
        font-size: 0.85rem;
        background: var(--color-surface);
        color: var(--color-body);
    }
    .chat-escalate-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        justify-content: flex-end;
    }
    .chat-escalate-copy {
        margin-top: 0.25rem;
        padding: 0.65rem 0.75rem;
        border-radius: 0.75rem;
        border: 1px solid rgba(99, 102, 241, 0.25);
        background: rgba(99, 102, 241, 0.08);
    }
    .chat-escalate-checkbox {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.78rem;
        font-weight: 600;
        color: #312e81;
    }
    .chat-escalate-checkbox input[type="checkbox"] {
        width: 16px;
        height: 16px;
    }
    .chat-escalate-copy .chat-meta {
        margin-top: 0.35rem;
        font-size: 0.7rem;
        color: #4338ca;
    }
    .chat-follow-up {
        display: none;
        border: 1px solid var(--color-border-light);
        border-radius: 0.85rem;
        padding: 0.75rem;
        background: var(--color-surface-alt);
        margin-top: 0.9rem;
    }
    .chat-follow-up strong {
        display: block;
        margin-bottom: 0.5rem;
        font-size: 0.82rem;
        color: var(--color-dark);
    }
    .chat-follow-up .btn-group {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    .chat-follow-up button {
        flex: 1 1 110px;
    }
    #chat-toggle.pulse-ring {
        position: relative;
        color: #fff;
        border-color: transparent;
        background-image: linear-gradient(90deg, #6366f1, #8b5cf6);
        box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.45);
        animation: chat-toggle-pulse 1.8s ease-out infinite;
    }
    @keyframes chat-toggle-pulse {
        0% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.45); }
        70% { box-shadow: 0 0 0 12px rgba(99, 102, 241, 0); }
        100% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0); }
    }
    .chat-whatsapp {
        display: none;
        align-items: center;
        gap: 0.45rem;
        margin-top: 0.6rem;
        font-size: 0.8rem;
        color: #0b8f47;
        font-weight: 600;
    }
    .chat-whatsapp svg {
        width: 18px;
        height: 18px;
    }

    .checkout-knowledge {
        border: 1px solid rgba(199, 210, 254, 0.22);
        background: rgba(255, 255, 255, 0.04);
    }

    .checkout-knowledge .card-body {
        display: flex;
        flex-direction: column;
        gap: 1.35rem;
    }

    .knowledge-header {
        display: flex;
        flex-direction: column;
        gap: 0.85rem;
    }

    .knowledge-intro {
        font-size: 0.9rem;
        color: rgba(148, 163, 184, 0.85);
        margin: 0;
    }

    .knowledge-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .knowledge-tag {
        border: 1px solid rgba(99, 102, 241, 0.35);
        border-radius: 999px;
        padding: 0.35rem 0.8rem;
        font-size: 0.75rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        background: rgba(79, 70, 229, 0.08);
        color: #6366f1;
        transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
    }

    .knowledge-tag[aria-pressed="true"],
    .knowledge-tag:hover {
        background: rgba(79, 70, 229, 0.18);
        color: #312e81;
        border-color: rgba(79, 70, 229, 0.55);
    }

    .knowledge-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1rem;
    }

    .knowledge-card {
        border: 1px solid rgba(148, 163, 184, 0.25);
        border-radius: 1rem;
        padding: 1.1rem 1.2rem;
        background: rgba(255, 255, 255, 0.06);
        display: flex;
        flex-direction: column;
        gap: 0.7rem;
    }

    .knowledge-card h3 {
        margin: 0;
        font-size: 1rem;
        color: #ffffff;
    }

    .knowledge-card ul {
        margin: 0;
        padding-left: 1.1rem;
        display: flex;
        flex-direction: column;
        gap: 0.45rem;
        font-size: 0.85rem;
        color: rgba(226, 232, 240, 0.85);
    }

    .knowledge-card li {
        list-style-type: disc;
    }

    .knowledge-card footer {
        display: flex;
        flex-wrap: wrap;
        gap: 0.6rem;
        font-size: 0.82rem;
    }

    .knowledge-link {
        color: #c7d2fe;
        text-decoration: underline;
    }

    .knowledge-footnote {
        font-size: 0.78rem;
        color: rgba(148, 163, 184, 0.85);
        margin: 0;
    }

    @media (max-width: 768px) {
        .knowledge-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .knowledge-tags {
            width: 100%;
        }
    }

    .text-red-700 { color: #b91c1c; }
    .text-red-600 { color: #dc2626; }
    .bg-red-100 { background-color: #fee2e2; }

    @media (prefers-color-scheme: dark) {
        .live-chat-card {
            box-shadow: 0 20px 40px rgba(2, 6, 23, 0.55);
        }

        .chat-message.customer {
            color: var(--color-dark);
        }

        .chat-quick-actions button {
            background: rgba(79, 70, 229, 0.28);
            border-color: rgba(99, 102, 241, 0.45);
            color: #c7d2fe;
        }

        .chat-suggestions a {
            background: rgba(79, 70, 229, 0.32);
            color: #dbeafe;
        }

        .chat-input .btn.btn-outline {
            border-color: rgba(129, 140, 248, 0.6);
            color: #dbeafe;
        }

        .chat-status {
            color: #34d399;
        }

        .chat-offline {
            border-color: rgba(248, 113, 113, 0.45);
            background: rgba(248, 113, 113, 0.15);
            color: #fecaca;
        }

        .chat-resolution-note,
        .chat-escalate-form,
        .chat-follow-up {
            background: rgba(30, 41, 59, 0.6);
            border-color: rgba(148, 163, 184, 0.35);
            color: #e2e8f0;
        }

        .chat-escalate-form input,
        .chat-escalate-form textarea,
        .chat-escalate-form select {
            background: rgba(15, 23, 42, 0.55);
            border-color: rgba(148, 163, 184, 0.4);
            color: #e2e8f0;
        }

        .chat-whatsapp {
            color: #34d399;
        }

        .checkout-knowledge {
            border-color: rgba(129, 140, 248, 0.35);
            background: rgba(15, 23, 42, 0.6);
        }

        .knowledge-intro,
        .knowledge-footnote {
            color: rgba(203, 213, 225, 0.8);
        }

        .knowledge-tag {
            background: rgba(79, 70, 229, 0.22);
            color: #c7d2fe;
            border-color: rgba(129, 140, 248, 0.45);
        }

        .knowledge-tag[aria-pressed="true"],
        .knowledge-tag:hover {
            background: rgba(79, 70, 229, 0.32);
            color: #e0e7ff;
            border-color: rgba(165, 180, 252, 0.65);
        }

        .knowledge-card {
            background: rgba(30, 41, 59, 0.75);
            border-color: rgba(129, 140, 248, 0.35);
        }

        .knowledge-card h3 {
            color: #e0e7ff;
        }

        .knowledge-card ul {
            color: rgba(226, 232, 240, 0.9);
        }

        .knowledge-link {
            color: #a5b4fc;
        }

        .text-red-700,
        .text-red-600 { color: #fecdd3; }
        .bg-red-100 { background-color: rgba(239, 68, 68, 0.28); }
    }
</style>

<main class="container">
    <h1 class="text-3xl font-bold mb-8">Confirm Your Order</h1>
    
    <?php if (isset($_SESSION['checkout_error'])): ?>
        <div class="bg-red-100 text-red-700 p-3 rounded-md mb-4 text-center">
            <?php echo $_SESSION['checkout_error']; ?>
            <?php if (!empty($_SESSION['checkout_error_detail'])): ?>
                <div class="text-xs text-red-600 mt-2"><?php echo htmlspecialchars($_SESSION['checkout_error_detail']); ?></div>
            <?php endif; ?>
            <p class="text-xs text-red-600 mt-2">Need help? Try refreshing the page and, if the issue persists, contact support with the reference above.</p>
            <?php unset($_SESSION['checkout_error'], $_SESSION['checkout_error_detail']); ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 card">
            <div class="card-body">
                <h2 class="text-2xl font-semibold mb-4">Confirm Details</h2>
                <div class="mb-4">
                    <h3 class="font-semibold">Shipping Address</h3>
                    <p><?php echo htmlspecialchars($_SESSION['shipping_info']['full_name']); ?></p>
                    <p><?php echo htmlspecialchars($_SESSION['shipping_info']['address']); ?></p>
                    <p><?php echo htmlspecialchars($_SESSION['shipping_info']['email']); ?></p>
                </div>
            </div>
        </div>
        <div class="space-y-6">
            <div class="card">
                <div class="card-body">
                    <h2 class="text-2xl font-semibold mb-4">Final Summary</h2>
                    <?php foreach ($cart_items as $item):
                        $preview = $item['preview'] ?? 'image/placeholder.png';
                        $quantity = (int) ($item['quantity'] ?? 1);
                        $unitPrice = isset($item['unit_price']) ? (float) $item['unit_price'] : null;
                        $isFreebie = !empty($item['is_freebie']);
                        $badge = $isFreebie ? 'Bundle Bonus' : (($item['type'] ?? '') === 'custom' ? 'Custom' : 'Catalog');
                        $bundleWorth = isset($item['bundle_value']) ? (float) $item['bundle_value'] : 0.0;
                    ?>
                        <div class="flex items-center gap-3 mb-3">
                            <img src="<?php echo htmlspecialchars($preview); ?>" alt="Item preview" class="w-14 h-14 rounded-lg object-cover border border-white/20">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="font-semibold truncate text-white"><?php echo htmlspecialchars($item['name']); ?></p>
                                    <span class="inline-flex text-[0.65rem] uppercase tracking-wide px-2 py-0.5 rounded-full bg-white/10 text-indigo-100/90"><?php echo htmlspecialchars($badge); ?></span>
                                </div>
                                <?php if (!empty($item['meta'])): ?>
                                    <p class="text-xs text-indigo-100/60 mt-1"><?php echo htmlspecialchars($item['meta']); ?></p>
                                <?php endif; ?>
                                <p class="text-sm text-indigo-100/70">
                                    Qty <?php echo $quantity; ?><?php if ($isFreebie): ?> • <span class="font-semibold text-indigo-100">Free</span><?php elseif ($unitPrice !== null): ?> • ₹<?php echo number_format($unitPrice, 2); ?> each<?php endif; ?>
                                </p>
                                <?php if ($isFreebie && $bundleWorth > 0): ?>
                                    <p class="text-xs text-indigo-100/60">Worth ₹<?php echo number_format($bundleWorth, 2); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="text-right">
                                <?php if ($isFreebie): ?>
                                    <p class="font-semibold text-white">Free</p>
                                <?php else: ?>
                                    <p class="font-semibold text-white">₹<?php echo number_format((float) $item['subtotal'], 2); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="border-t pt-4 mt-4">
                        <?php if (!empty($bundle_value)): ?>
                            <div class="flex justify-between text-indigo-100/80 mb-2"><span>Bundle freebies</span><span>₹<?php echo number_format($bundle_value, 2); ?> value</span></div>
                        <?php endif; ?>
                        <div class="flex justify-between font-bold text-lg"><span>Total</span><span>₹<?php echo number_format($total_price, 2); ?></span></div>
                    </div>

                    <div class="mt-4 p-3 rounded-xl bg-white/5 border border-white/10 text-sm text-indigo-100/80">
                        <p class="font-semibold text-white">Estimated ship & delivery</p>
                        <p class="mt-1">Your order is scheduled to leave the studio between <strong><?php echo htmlspecialchars($estimatedShipWindow); ?></strong>. We will email you the moment it moves to courier.</p>
                    </div>

                    <?php if ($total_price > 0): ?>
                        <details class="cost-breakdown" <?php echo $total_price > 0 ? 'open' : ''; ?>>
                            <summary>
                                Where your investment goes
                                <span class="text-sm font-normal text-indigo-700">Tap to toggle</span>
                            </summary>
                            <div class="breakdown-list">
                                <?php foreach ($costBreakdown as $row): ?>
                                    <div class="breakdown-row">
                                        <div>
                                            <span class="breakdown-label"><?php echo htmlspecialchars($row['label']); ?></span>
                                            <span class="breakdown-description"><?php echo htmlspecialchars($row['description']); ?></span>
                                        </div>
                                        <span class="font-semibold">₹<?php echo number_format($row['amount'], 2); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    <?php endif; ?>

                    <form method="POST" action="payment.php" class="mt-6">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                        <button type="submit" name="action" value="place_order" class="btn btn-primary btn-lg w-full">Place Order</button>
                    </form>
                </div>
            </div>

            <section class="card checkout-knowledge">
                <div class="card-body">
                    <header class="knowledge-header">
                        <div>
                            <h2 class="text-xl font-semibold">Need quick help?</h2>
                            <p class="knowledge-intro">Scan these answers while we prep live support. Most shoppers solve their questions in a minute.</p>
                        </div>
                        <div class="knowledge-tags" role="list">
                            <button type="button" data-filter="shipping" class="knowledge-tag" aria-pressed="false">Shipping</button>
                            <button type="button" data-filter="design" class="knowledge-tag" aria-pressed="false">Design</button>
                            <button type="button" data-filter="billing" class="knowledge-tag" aria-pressed="false">Billing</button>
                        </div>
                    </header>
                    <div class="knowledge-grid" id="checkout-knowledge-grid">
                        <article class="knowledge-card" data-topic="shipping">
                            <h3>Where’s my order?</h3>
                            <ul>
                                <li>Production begins within <strong>24–48 hours</strong> once artwork is approved.</li>
                                <li>A tracking link lands in your inbox the moment the parcel leaves our Pune studio.</li>
                                <li>Expect delivery in <strong>4–6 business days</strong> via Bluedart or Delhivery.</li>
                            </ul>
                            <footer>
                                <a href="orders.php" class="knowledge-link">Track order status</a>
                                <a href="faq.php#shipping" class="knowledge-link">Shipping FAQ</a>
                            </footer>
                        </article>
                        <article class="knowledge-card" data-topic="shipping">
                            <h3>Need to change address or size?</h3>
                            <ul>
                                <li>Updates are free within <strong>12 hours</strong> of checkout before we lock garments.</li>
                                <li>Reply to your confirmation email or message us with the new address or size.</li>
                                <li>Double-check saved details under <strong>My Account → Orders & Addresses</strong>.</li>
                            </ul>
                            <footer>
                                <a href="faq.php#shipping-update" class="knowledge-link">How to update shipping</a>
                                <a href="account.php#addresses" class="knowledge-link">Manage saved addresses</a>
                            </footer>
                        </article>
                        <article class="knowledge-card" data-topic="design">
                            <h3>Finishing design tweaks</h3>
                            <ul>
                                <li>Reopen the 3D studio from <strong>My Account → Saved Designs</strong> to make edits.</li>
                                <li>We need front & back previews plus the print texture before it can hit the press.</li>
                                <li>Share notes like “center logo” or “deepen black” so our print techs can adjust.</li>
                            </ul>
                            <footer>
                                <a href="design3d.php" class="knowledge-link">Launch 3D designer</a>
                                <a href="faq.php#design" class="knowledge-link">Design guidelines</a>
                            </footer>
                        </article>
                        <article class="knowledge-card" data-topic="billing">
                            <h3>Payment & invoices</h3>
                            <ul>
                                <li>We accept UPI, cards (Visa/Master/Amex), and netbanking via Razorpay checkout.</li>
                                <li>Download a GST-compliant invoice from your order receipt email or Account → Orders.</li>
                                <li>Need split payments or PO billing? Our finance desk can set it up in minutes.</li>
                            </ul>
                            <footer>
                                <a href="faq.php#billing" class="knowledge-link">Payment FAQ</a>
                                <a href="contact.php#billing" class="knowledge-link">Billing support desk</a>
                            </footer>
                        </article>
                        <article class="knowledge-card" data-topic="design">
                            <h3>Artwork readiness checklist</h3>
                            <ul>
                                <li>Upload files at <strong>300 DPI</strong> with transparent backgrounds for the cleanest prints.</li>
                                <li>Keep critical elements 0.5 cm inside the safe zone to avoid trimming.</li>
                                <li>To see embroidery proofs, mention the thread count or Pantone refs in your notes.</li>
                            </ul>
                            <footer>
                                <a href="faq.php#artwork" class="knowledge-link">Artwork best practices</a>
                                <a href="support_artwork.php" class="knowledge-link">Submit artwork question</a>
                            </footer>
                        </article>
                        <article class="knowledge-card" data-topic="shipping">
                            <h3>Need it faster?</h3>
                            <ul>
                                <li>Priority production slots add <strong>₹399</strong> and ship within 24 hours.</li>
                                <li>Express courier (Metro cities) reaches in 2–3 days; rural routes vary.</li>
                                <li>Message us with “Urgent delivery” and your target date—we’ll confirm feasibility.</li>
                            </ul>
                            <footer>
                                <a href="faq.php#rush" class="knowledge-link">Rush delivery options</a>
                                <a href="contact.php#support" class="knowledge-link">Talk to logistics</a>
                            </footer>
                        </article>
                    </div>
                    <p class="knowledge-footnote">Still stuck? Open live chat below—your conversation starts with these notes so you don’t repeat yourself.</p>
                </div>
            </section>

            <div class="card">
                <div class="card-body">
                    <h2 class="text-xl font-semibold mb-3">Quick FAQs</h2>
                    <details class="mb-3" open>
                        <summary class="cursor-pointer text-indigo-100 font-medium">Can I tweak my design after checkout?</summary>
                        <p class="mt-2 text-sm text-indigo-100/80">Yes—reply to your confirmation email within 12 hours and our team will pause printing while you make updates.</p>
                    </details>
                    <details class="mb-3">
                        <summary class="cursor-pointer text-indigo-100 font-medium">How soon will it ship?</summary>
                        <p class="mt-2 text-sm text-indigo-100/80">Most orders leave the studio within 48 hours. We’ll email you a tracking link as soon as it’s with our courier.</p>
                    </details>
                    <details>
                        <summary class="cursor-pointer text-indigo-100 font-medium">What if I need support right away?</summary>
                        <p class="mt-2 text-sm text-indigo-100/80">Use the live chat below or tap the WhatsApp button for instant escalation. Our print specialists reply in minutes between 9 AM and 9 PM IST.</p>
                    </details>
                </div>
            </div>

            <aside class="live-chat-card">
                <div class="chat-header">
                    <div>
                        <h3>Need a hand?</h3>
                        <p class="chat-meta">Live specialists reply in under 2 minutes.</p>
                    </div>
                    <button type="button" id="chat-toggle" class="btn btn-outline btn-sm" aria-expanded="false" aria-controls="support-chat-panel">Open chat</button>
                </div>
                <div class="chat-panel" id="support-chat-panel" data-initial-notes="<?php echo $supportNotesJson; ?>" data-order-meta="<?php echo $orderMetaJson; ?>" data-support-config="<?php echo $supportConfigJson; ?>" data-customer-email="<?php echo htmlspecialchars($customerContactEmail, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="chat-history" id="chat-history" role="log" aria-live="polite" aria-relevant="additions text">
                        <div class="chat-message agent">
                            <strong>Riya (Print Expert):</strong>
                            <span>Hey! Need clarity on sizing, print care, or delivery? I’m right here.</span>
                        </div>
                    </div>
                    <div class="chat-quick-actions" id="chat-quick-actions">
                        <button type="button" data-template="Can you update my shipping address?">Update shipping</button>
                        <button type="button" data-template="Can I get an urgent delivery timeline?">Rush delivery</button>
                        <button type="button" data-template="How do I tweak my design before print?">Modify design</button>
                        <button type="button" data-template="Can you help me with payment or billing questions?">Payment support</button>
                    </div>
                    <div class="chat-status" id="chat-status" role="status" aria-live="polite"></div>
                    <div class="chat-offline" id="chat-offline" role="note">Our phone desk is resting right now. Leave a note or tap WhatsApp—we’ll jump back in at 9 AM IST.</div>
                    <div class="chat-suggestions" id="chat-suggestions" aria-label="Suggested help articles"></div>
                    <div class="chat-input" id="chat-input">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-600" for="chat-question">Ask us anything</label>
                        <textarea id="chat-question" placeholder="Eg. Can I rush this order for Friday?" maxlength="240"></textarea>
                        <div class="flex gap-2 flex-wrap">
                            <button type="button" id="chat-send" class="btn btn-primary btn-sm">Send</button>
                            <button type="button" id="chat-escalate" class="btn btn-outline btn-sm">Request a callback</button>
                        </div>
                        <?php $supportMailto = 'mailto:' . $primarySupportEmail; ?>
                        <p class="chat-meta">Prefer email? <a href="<?php echo htmlspecialchars($supportMailto); ?>" class="text-indigo-600 font-medium"><?php echo htmlspecialchars($primarySupportEmail); ?></a></p>
                        <a href="#" id="chat-whatsapp-link" class="chat-whatsapp">
                            <svg viewBox="0 0 32 32" aria-hidden="true"><path fill="currentColor" d="M16 3C9.37 3 4 8.37 4 15c0 2.46.73 4.74 1.98 6.67L4 29l7.54-1.97C13.45 27.28 14.7 27.5 16 27.5c6.63 0 12-5.37 12-12S22.63 3 16 3zm0 22c-1.19 0-2.35-.2-3.44-.6l-.25-.09-4.47 1.17 1.2-4.35-.16-.27A9.93 9.93 0 016 15c0-5.52 4.48-10 10-10s10 4.48 10 10-4.48 10-10 10zm5.3-7.45c-.29-.15-1.72-.85-1.98-.94-.26-.1-.45-.15-.64.15s-.73.94-.9 1.14c-.17.2-.33.22-.62.07-.29-.15-1.23-.45-2.34-1.43-.86-.77-1.44-1.72-1.61-2-.17-.29-.02-.45.13-.6.14-.14.32-.36.48-.54.16-.17.21-.29.32-.48.1-.2.05-.37-.02-.52-.07-.15-.64-1.54-.88-2.11-.23-.55-.47-.48-.64-.49l-.54-.01c-.2 0-.52.07-.8.37-.29.29-1.1 1.08-1.1 2.63 0 1.54 1.12 3.03 1.28 3.24.16.2 2.2 3.36 5.33 4.72.75.32 1.33.51 1.78.66.75.24 1.43.21 1.97.13.6-.09 1.72-.7 1.96-1.38.24-.68.24-1.26.16-1.38-.07-.11-.27-.2-.56-.35z"/></svg>
                            WhatsApp us directly
                        </a>
                    </div>
                    <div class="chat-escalate-form" id="chat-escalate-form" aria-hidden="true">
                        <div>
                            <label for="escalate-summary">What should we tell the specialist?</label>
                            <textarea id="escalate-summary" rows="3" maxlength="400" placeholder="Quick summary for the callback team"></textarea>
                        </div>
                        <div>
                            <label for="escalate-contact">Best way to reach you</label>
                            <select id="escalate-contact">
                                <option value="phone">Phone on file</option>
                                <option value="alternate_phone">Alternate phone (enter below)</option>
                                <option value="email">Email</option>
                                <option value="whatsapp">WhatsApp</option>
                            </select>
                        </div>
                        <div id="escalate-alt-contact" style="display:none;">
                            <input type="text" id="escalate-alt-value" placeholder="Enter alternate phone or contact" />
                        </div>
                        <div>
                            <label for="escalate-time">Preferred callback window</label>
                            <input type="text" id="escalate-time" placeholder="Eg. Today between 4-6 PM" />
                        </div>
                        <div class="chat-escalate-copy">
                            <label class="chat-escalate-checkbox">
                                <input type="checkbox" id="escalate-send-transcript" /> Email me this chat summary
                            </label>
                            <?php if (!empty($customerContactEmail)): ?>
                                <p class="chat-meta">We will send it to <?php echo htmlspecialchars($customerContactEmail); ?>.</p>
                            <?php else: ?>
                                <p class="chat-meta">Add your email in the address book to receive the transcript.</p>
                            <?php endif; ?>
                        </div>
                        <div class="chat-escalate-actions">
                            <button type="button" id="escalate-cancel" class="btn btn-outline btn-sm">Cancel</button>
                            <button type="button" id="escalate-submit" class="btn btn-primary btn-sm">Submit request</button>
                        </div>
                    </div>
                    <div class="chat-follow-up" id="chat-follow-up">
                        <strong>Did that answer your question?</strong>
                        <div class="btn-group">
                            <button type="button" id="followup-resolved" class="btn btn-outline btn-sm">Solved it</button>
                            <button type="button" id="followup-stuck" class="btn btn-primary btn-sm">Still stuck</button>
                        </div>
                    </div>
                </div>
                <div class="chat-resolution" id="chat-resolution">
                    <h4>Latest notes for you</h4>
                    <div class="chat-resolution-list" id="chat-resolution-list"></div>
                </div>
            </aside>
        </div>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const knowledgeGrid = document.getElementById('checkout-knowledge-grid');
        const knowledgeTags = Array.from(document.querySelectorAll('.knowledge-tag'));

        if (knowledgeGrid && knowledgeTags.length) {
            const knowledgeCards = Array.from(knowledgeGrid.querySelectorAll('.knowledge-card'));

            const applyKnowledgeFilter = (topic) => {
                knowledgeCards.forEach((card) => {
                    const matches = !topic || card.dataset.topic === topic;
                    card.style.display = matches ? '' : 'none';
                });
            };

            knowledgeTags.forEach((tag) => {
                tag.addEventListener('click', () => {
                    const isActive = tag.getAttribute('aria-pressed') === 'true';
                    knowledgeTags.forEach((btn) => btn.setAttribute('aria-pressed', 'false'));
                    if (isActive) {
                        applyKnowledgeFilter('');
                        tag.setAttribute('aria-pressed', 'false');
                    } else {
                        tag.setAttribute('aria-pressed', 'true');
                        applyKnowledgeFilter(tag.dataset.filter);
                    }
                });
            });
        }

        const chatToggle = document.getElementById('chat-toggle');
        const chatHistory = document.getElementById('chat-history');
        const chatInput = document.getElementById('chat-input');
        const chatQuickActions = document.getElementById('chat-quick-actions');
        const chatSend = document.getElementById('chat-send');
        const chatQuestion = document.getElementById('chat-question');
        const chatStatus = document.getElementById('chat-status');
        const chatSuggestions = document.getElementById('chat-suggestions');
        const chatEscalate = document.getElementById('chat-escalate');
        const chatOffline = document.getElementById('chat-offline');
        const whatsappLink = document.getElementById('chat-whatsapp-link');
        const supportPanel = document.getElementById('support-chat-panel');
        const chatResolution = document.getElementById('chat-resolution');
        const chatResolutionList = document.getElementById('chat-resolution-list');
        const chatFollowUp = document.getElementById('chat-follow-up');
        const followupResolved = document.getElementById('followup-resolved');
        const followupStuck = document.getElementById('followup-stuck');
        const escalateForm = document.getElementById('chat-escalate-form');
        const escalateSummary = document.getElementById('escalate-summary');
        const escalateContact = document.getElementById('escalate-contact');
        const escalateAltWrapper = document.getElementById('escalate-alt-contact');
        const escalateAltValue = document.getElementById('escalate-alt-value');
        const escalateTime = document.getElementById('escalate-time');
    const escalateSendTranscript = document.getElementById('escalate-send-transcript');
        const escalateSubmit = document.getElementById('escalate-submit');
        const escalateCancel = document.getElementById('escalate-cancel');
    const escalateSubmitDefaultText = escalateSubmit ? escalateSubmit.textContent : 'Submit request';

        const quickActionButtons = Array.from((chatQuickActions || {}).querySelectorAll('button') || []);

        if (!chatToggle || !chatHistory || !chatInput || !chatSend || !chatQuestion || !supportPanel) {
            return;
        }

        const safeParse = (value, fallback) => {
            try {
                return value ? JSON.parse(value) : fallback;
            } catch (error) {
                return fallback;
            }
        };

        let supportNotes = safeParse(supportPanel.dataset.initialNotes, []);
    const orderMeta = safeParse(supportPanel.dataset.orderMeta, {});
        const supportConfig = safeParse(supportPanel.dataset.supportConfig, {});
    const customerEmailOnFile = supportPanel.dataset.customerEmail || orderMeta.shipping?.email || '';

        let chatOpen = false;
        let hasOpenedChat = false;
        let interactionCount = 0;
        let typingIndicator = null;
        let typingTimer = null;
        let followUpShown = false;
    let whatsappClickLogged = false;

        const supportHours = supportConfig.hours || { start: 9, end: 21, timezone: 'Asia/Kolkata' };
        const supportEmail = supportConfig.support_email || 'jvb.ombca2023@gmail.com';

        const orderTimeline = orderMeta.milestones || {};
    const primaryContact = orderMeta.shipping?.phone || '';

        const initialSuggestions = [
            { label: 'Track my order', href: 'orders.php' },
            { label: 'Shipping and delivery FAQ', href: 'faq.php#shipping' },
            { label: 'Email support', href: `mailto:${supportEmail}`, external: true }
        ];

        const responseLibrary = [
            {
                match: (text) => /(update|change).*(address|shipping)/i.test(text) || /(address|shipping).*(update|change)/i.test(text),
                response: () => 'Sure thing. Share the updated address here and I will log it before we print. You can also confirm it under My Account → Orders.',
                suggestions: [
                    { label: 'Change shipping address', href: 'faq.php#shipping-update' },
                    { label: 'View saved addresses', href: 'account.php#addresses' }
                ]
            },
            {
                match: (text) => /(rush|urgent|express|delivery|timeline)/i.test(text),
                response: () => {
                    if (orderTimeline.print_window_start && orderTimeline.expected_delivery) {
                        return `Here is the current plan: print queue opens ${orderTimeline.print_window_start}, ships by ${orderTimeline.ship_by}, and lands with you by ${orderTimeline.expected_delivery}. Need it faster? I can explore rush slots.`;
                    }
                    return 'We can usually rush production within 24 hours if the design is final. Let me confirm a slot and email you the timeline right after this chat.';
                },
                suggestions: [
                    { label: 'Delivery speed options', href: 'shipping_info.php#speeds' },
                    { label: 'Track production status', href: 'orders.php' }
                ]
            },
            {
                match: (text) => /(design|tweak|edit|change).*(before|print)/i.test(text) || /(artwork|mockup)/i.test(text),
                response: () => 'Happy to help with design tweaks. Tell me what should change and I will pause the print queue while our designer sends a revised proof.',
                suggestions: [
                    { label: 'Open design editor', href: 'design3d.php' },
                    { label: 'Upload new artwork', href: 'save_design.php' }
                ]
            },
            {
                match: (text) => /(payment|billing|bill|charge|refund|invoice)/i.test(text),
                response: () => 'I have your invoice open. Let me know if you need GST paperwork, a split payment, or if any charge looks incorrect and I will get it sorted.',
                suggestions: [
                    { label: 'View billing history', href: 'orders.php#billing' },
                    { label: 'Download invoice guide', href: 'faq.php#invoice' }
                ]
            },
            {
                match: (text) => /(call|callback|phone|speak to|human)/i.test(text),
                response: () => `No problem. I am alerting our specialist and they will call the phone number on your shipping info${primaryContact ? ` (${primaryContact})` : ''} within 10 minutes.`,
                suggestions: [
                    { label: 'Update phone number', href: 'account.php#profile' },
                    { label: 'Support hours', href: 'contact.php#hours' }
                ]
            }
        ];

        const fallbackResponses = [
            () => {
                if (orderTimeline.expected_delivery) {
                    return `Everything is locked in. Your package is slated to deliver by ${orderTimeline.expected_delivery}. What else can I double-check for you?`;
                }
                return 'I am here for anything else you need, even if it is a quick double-check. Just type it in.';
            },
            () => 'If you prefer email or WhatsApp, say the word and I will switch channels right away.',
            () => 'Still with you. Share any detail you have and I will take it from there.'
        ];

        const logWhatsAppClick = () => {
            if (whatsappClickLogged) {
                return;
            }
            whatsappClickLogged = true;
            fetch('support_issue_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'whatsapp_click' })
            }).catch(() => {
                // Ignore analytics logging failures.
            });
        };

        const setStatus = (text) => {
            if (!chatStatus) {
                return;
            }
            if (!text) {
                chatStatus.style.display = 'none';
                chatStatus.textContent = '';
                return;
            }
            chatStatus.style.display = 'block';
            chatStatus.textContent = text;
        };

        const showSuggestions = (items = []) => {
            if (!chatSuggestions) {
                return;
            }
            chatSuggestions.innerHTML = '';
            if (!items.length) {
                chatSuggestions.style.display = 'none';
                return;
            }
            chatSuggestions.style.display = 'flex';
            items.slice(0, 4).forEach((item) => {
                const link = document.createElement('a');
                link.href = item.href;
                link.textContent = item.label;
                const indicator = document.createElement('span');
                indicator.className = 'indicator';
                link.prepend(indicator);
                if (item.external) {
                    link.target = '_blank';
                    link.rel = 'noopener';
                }
                chatSuggestions.appendChild(link);
            });
        };

        const removeTypingIndicator = () => {
            if (typingIndicator && typingIndicator.parentNode) {
                typingIndicator.parentNode.removeChild(typingIndicator);
            }
            typingIndicator = null;
        };

        const showTypingIndicator = () => {
            removeTypingIndicator();
            const indicator = document.createElement('div');
            indicator.className = 'chat-message agent typing';
            const label = document.createElement('strong');
            label.textContent = 'Riya (Print Expert):';
            const dots = document.createElement('span');
            dots.className = 'typing-dots';
            for (let i = 0; i < 3; i += 1) {
                const dot = document.createElement('span');
                dots.appendChild(dot);
            }
            indicator.appendChild(label);
            indicator.appendChild(dots);
            chatHistory.appendChild(indicator);
            chatHistory.scrollTop = chatHistory.scrollHeight;
            typingIndicator = indicator;
        };

        const determineSupportOpen = () => {
            try {
                const timezone = supportHours.timezone || 'Asia/Kolkata';
                const formatter = new Intl.DateTimeFormat('en-US', { timeZone: timezone, hour: 'numeric', hour12: false });
                const parts = formatter.formatToParts(new Date());
                const hourPart = parts.find((part) => part.type === 'hour');
                const hour = hourPart ? parseInt(hourPart.value, 10) : new Date().getHours();
                if (Number.isNaN(hour)) {
                    return true;
                }
                return hour >= (supportHours.start ?? 9) && hour < (supportHours.end ?? 21);
            } catch (error) {
                return true;
            }
        };

        const updateAvailability = () => {
            const isOpen = determineSupportOpen();
            if (isOpen) {
                setStatus('We are online right now. Average reply is under 2 minutes.');
                if (chatOffline) {
                    chatOffline.style.display = 'none';
                }
            } else {
                if (chatOffline) {
                    chatOffline.style.display = 'block';
                }
                setStatus('We’ll reply first thing at 9 AM IST. Leave your question and we will email you a solution.');
            }
        };

        const updateWhatsAppLink = () => {
            if (!whatsappLink || !supportConfig.whatsapp) {
                return;
            }
            const digits = supportConfig.whatsapp.digits || '';
            const chatLogId = supportConfig.whatsapp.chat_log_id || orderMeta.chat_log_id || '';
            const hasAgentNote = Array.isArray(supportNotes) && supportNotes.some((note) => (note.role || '') === 'agent');
            const baseMessage = supportConfig.whatsapp.base_message || supportConfig.whatsapp.message || '';
            let message = supportConfig.whatsapp.message || baseMessage;

            if (hasAgentNote && chatLogId) {
                const lines = message.split('\n').filter((line) => line.trim().length);
                const alreadyIncluded = lines.some((line) => line.startsWith('Chat log ID:'));
                if (!alreadyIncluded) {
                    const finalLine = lines.pop() || '';
                    lines.push(`Chat log ID: ${chatLogId}`);
                    if (finalLine) {
                        lines.push(finalLine);
                    }
                    message = lines.join('\n');
                    supportConfig.whatsapp.message = message;
                }
            }

            let link = supportConfig.whatsapp.link || '';
            if (digits) {
                link = `https://wa.me/${digits}?text=${encodeURIComponent(message)}`;
            }

            if (link) {
                whatsappLink.href = link;
                whatsappLink.style.display = 'inline-flex';
                whatsappLink.dataset.prefilled = message;
                whatsappLink.title = 'Open WhatsApp with your order context';
            }
        };

        const renderNotes = () => {
            if (!chatResolution || !chatResolutionList) {
                return;
            }
            chatResolutionList.innerHTML = '';
            if (!Array.isArray(supportNotes) || !supportNotes.length) {
                chatResolution.style.display = 'none';
                return;
            }
            chatResolution.style.display = 'flex';
            const latest = supportNotes.slice(-5).reverse();
            latest.forEach((entry) => {
                const card = document.createElement('div');
                card.className = 'chat-resolution-note';
                const when = entry.timestamp ? new Date(entry.timestamp * 1000) : null;
                const timestamp = when ? when.toLocaleString() : 'earlier';

                const header = document.createElement('div');
                header.style.fontWeight = '600';
                header.textContent = `${entry.role === 'agent' ? 'Riya' : 'You'} · ${timestamp}`;

                const body = document.createElement('div');
                body.style.marginTop = '4px';
                body.textContent = entry.message || '';

                card.appendChild(header);
                card.appendChild(body);
                chatResolutionList.appendChild(card);
            });
        };

        const persistNote = (role, message) => {
            if (!message) {
                return Promise.resolve();
            }
            return fetch('support_issue_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'log', role, message })
            })
                .then((response) => (response.ok ? response.json() : Promise.reject()))
                .then((result) => {
                    if (result && Array.isArray(result.notes)) {
                        supportNotes = result.notes;
                        renderNotes();
                        updateWhatsAppLink();
                    }
                })
                .catch(() => {
                    // swallow logging errors to keep chat responsive
                });
        };

        const showFollowUp = () => {
            if (!chatFollowUp || followUpShown) {
                return;
            }
            followUpShown = true;
            chatFollowUp.style.display = 'block';
            if (chatToggle) {
                chatToggle.classList.add('pulse-ring');
            }
        };

        const hideFollowUp = () => {
            if (chatFollowUp) {
                chatFollowUp.style.display = 'none';
            }
            if (chatToggle) {
                chatToggle.classList.remove('pulse-ring');
            }
        };

        const toggleEscalateForm = (show) => {
            if (!escalateForm) {
                return;
            }
            const shouldShow = typeof show === 'boolean' ? show : escalateForm.style.display !== 'flex';
            escalateForm.style.display = shouldShow ? 'flex' : 'none';
            escalateForm.setAttribute('aria-hidden', shouldShow ? 'false' : 'true');
            if (shouldShow) {
                escalateSummary.focus();
            }
        };

        const setEscalateLoading = (isLoading) => {
            if (!escalateSubmit) {
                return;
            }
            escalateSubmit.disabled = isLoading;
            escalateSubmit.textContent = isLoading ? 'Submitting...' : escalateSubmitDefaultText;
        };

        const appendMessage = (type, text) => {
            const message = document.createElement('div');
            message.className = `chat-message ${type}`;
            const author = document.createElement('strong');
            author.textContent = type === 'customer' ? 'You:' : 'Riya (Print Expert):';
            const body = document.createElement('span');
            body.textContent = text;
            message.appendChild(author);
            message.appendChild(body);
            chatHistory.appendChild(message);
            chatHistory.scrollTop = chatHistory.scrollHeight;
        };

        const resolveResponse = (question) => {
            const text = question.toLowerCase();
            const matched = responseLibrary.find((entry) => entry.match(text));
            if (matched) {
                const reply = typeof matched.response === 'function' ? matched.response(question) : matched.response;
                return {
                    response: reply,
                    suggestions: matched.suggestions || []
                };
            }
            const fallbackBuilder = fallbackResponses[interactionCount % fallbackResponses.length] || (() => 'Let me know how I can help.');
            return { response: fallbackBuilder(question), suggestions: [] };
        };

        const handleAgentResponse = (question) => {
            const match = resolveResponse(question);
            interactionCount += 1;
            showTypingIndicator();
            setStatus('Riya is typing...');
            window.clearTimeout(typingTimer);
            typingTimer = window.setTimeout(() => {
                removeTypingIndicator();
                appendMessage('agent', match.response);
                persistNote('agent', match.response);
                setStatus('Need anything else? I am still here.');
                if (Array.isArray(match.suggestions) && match.suggestions.length) {
                    showSuggestions(match.suggestions);
                } else if (interactionCount >= 2) {
                    showSuggestions([
                        { label: 'View order history', href: 'orders.php' },
                        { label: 'Email support', href: `mailto:${supportEmail}`, external: true }
                    ]);
                }
                if (interactionCount >= 2) {
                    showFollowUp();
                }
            }, 700 + Math.random() * 600);
        };

        const sendQuestion = () => {
            const question = chatQuestion.value.trim();
            if (!question) {
                chatQuestion.focus();
                return;
            }
            appendMessage('customer', question);
            persistNote('customer', question);
            chatQuestion.value = '';
            chatQuestion.focus();
            handleAgentResponse(question);
        };

        chatToggle.addEventListener('click', () => {
            chatOpen = !chatOpen;
            chatHistory.style.display = chatOpen ? 'block' : 'none';
            chatInput.style.display = chatOpen ? 'flex' : 'none';
            if (chatQuickActions) {
                chatQuickActions.style.display = chatOpen ? 'flex' : 'none';
            }
            chatToggle.textContent = chatOpen ? 'Close chat' : 'Open chat';
            chatToggle.setAttribute('aria-expanded', chatOpen ? 'true' : 'false');
            if (chatOpen) {
                chatToggle.classList.remove('pulse-ring');
            }
            if (chatOpen) {
                chatQuestion.focus();
                if (!hasOpenedChat) {
                    showSuggestions(initialSuggestions);
                    hasOpenedChat = true;
                }
                updateAvailability();
            } else {
                setStatus('');
                showSuggestions();
                removeTypingIndicator();
                toggleEscalateForm(false);
            }
        });

        chatToggle.addEventListener('keydown', (event) => {
            if ((event.key === 'Enter' || event.key === ' ') && !event.defaultPrevented) {
                event.preventDefault();
                chatToggle.click();
            }
        });

        quickActionButtons.forEach((button) => {
            button.addEventListener('click', () => {
                if (!chatOpen) {
                    chatToggle.click();
                }
                chatQuestion.value = button.dataset.template || '';
                chatQuestion.focus();
            });
        });

        chatSend.addEventListener('click', sendQuestion);

        chatQuestion.addEventListener('keydown', (event) => {
            if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
                event.preventDefault();
                sendQuestion();
            }
        });

        if (chatEscalate) {
            chatEscalate.addEventListener('click', () => {
                if (chatEscalate.disabled) {
                    return;
                }
                if (!chatOpen) {
                    chatToggle.click();
                }
                toggleEscalateForm(true);
                hideFollowUp();
            });
        }

        if (escalateContact && escalateAltWrapper) {
            escalateContact.addEventListener('change', () => {
                const needsAlternate = escalateContact.value === 'alternate_phone';
                escalateAltWrapper.style.display = needsAlternate ? 'block' : 'none';
                if (!needsAlternate) {
                    escalateAltValue.value = '';
                } else {
                    escalateAltValue.focus();
                }
            });
        }

        if (escalateCancel) {
            escalateCancel.addEventListener('click', () => {
                toggleEscalateForm(false);
            });
        }

        const escalateRequest = () => {
            const issueSummary = (escalateSummary?.value || '').trim();
            const contactChoice = escalateContact?.value || 'phone';
            const altContact = (escalateAltValue?.value || '').trim();
            const preferredWindow = (escalateTime?.value || '').trim();
            const wantsTranscript = Boolean(escalateSendTranscript && escalateSendTranscript.checked);

            if (wantsTranscript && !customerEmailOnFile) {
                setStatus('Add an email address to your account so we can send the transcript.');
                if (escalateSendTranscript) {
                    escalateSendTranscript.focus();
                }
                return;
            }

            const preferredContact = contactChoice === 'alternate_phone' && altContact
                ? `${contactChoice}: ${altContact}`
                : contactChoice;

            const logSummary = issueSummary || 'Callback requested via checkout chat.';
            persistNote('customer', logSummary);

            setEscalateLoading(true);

            fetch('support_issue_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'escalate',
                    issue_summary: issueSummary,
                    preferred_contact: preferredWindow ? `${preferredContact} · ${preferredWindow}` : preferredContact,
                    send_transcript: wantsTranscript
                })
            })
                .then((response) => (response.ok ? response.json() : Promise.reject()))
                .then((result) => {
                    if (result && Array.isArray(result.notes)) {
                        supportNotes = result.notes;
                        renderNotes();
                        updateWhatsAppLink();
                    }
                    appendMessage('agent', 'Got it! I just alerted our senior specialist with your notes. They will reach out shortly.');
                    persistNote('agent', 'Escalation raised to specialist.');
                    let statusMessage = 'Specialist queued. We will reach out shortly.';
                    if (wantsTranscript) {
                        if (result && result.transcriptSent) {
                            appendMessage('agent', 'I also emailed you a copy of this chat for easy reference.');
                            persistNote('agent', 'Transcript emailed to customer.');
                            statusMessage = 'Specialist queued. Transcript emailed to you.';
                        } else {
                            statusMessage = 'Escalation submitted. We will email the transcript manually shortly.';
                        }
                    }
                    setStatus(statusMessage);
                    chatEscalate.disabled = true;
                    chatEscalate.textContent = 'Callback booked';
                    toggleEscalateForm(false);
                    if (escalateSendTranscript) {
                        escalateSendTranscript.checked = false;
                    }
                })
                .catch(() => {
                    setStatus(`Could not reach the specialist desk just now. Try again or email ${supportEmail}.`);
                })
                .finally(() => {
                    setEscalateLoading(false);
                });
        };

        if (escalateSubmit) {
            escalateSubmit.addEventListener('click', escalateRequest);
        }

        if (followupResolved) {
            followupResolved.addEventListener('click', () => {
                appendMessage('customer', 'All set, thank you!');
                persistNote('customer', 'Marked issue as resolved.');
                appendMessage('agent', 'Happy to hear that! I will keep this chat transcript handy if you need it later.');
                persistNote('agent', 'Customer confirmed resolution.');
                hideFollowUp();
            });
        }

        if (followupStuck) {
            followupStuck.addEventListener('click', () => {
                appendMessage('customer', 'Still need help after that, can we look deeper?');
                persistNote('customer', 'Still needs assistance after quick answers.');
                toggleEscalateForm(true);
            });
        }

        if (whatsappLink) {
            whatsappLink.addEventListener('click', () => {
                if (!whatsappClickLogged) {
                    persistNote('customer', 'Requested WhatsApp follow-up from checkout chat.');
                }
                logWhatsAppClick();
            });
        }

        updateWhatsAppLink();
        renderNotes();
        updateAvailability();
        window.setInterval(updateAvailability, 5 * 60 * 1000);
    });
</script>

<?php include 'footer.php'; ?>