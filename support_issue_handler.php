<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
    exit();
}

$action = $data['action'] ?? '';
$response = ['status' => 'ok'];

require_once __DIR__ . '/email_handler.php';
require_once __DIR__ . '/session_helpers.php';
require_once __DIR__ . '/core/support_ticket_queue.php';

if (!function_exists('build_support_order_context')) {
    function build_support_order_context(): array
    {
        $context = [
            'cart_items' => [],
            'custom_designs' => [],
            'estimated_total' => null,
            'cart_count' => 0,
            'shipping' => [],
            'customer_id' => $_SESSION['customer_id'] ?? null,
        ];

        $cart = $_SESSION['cart'] ?? [];
        if (empty($cart) || !is_array($cart)) {
            return $context;
        }

        $inventoryIds = [];
        foreach ($cart as $inventoryId => $quantity) {
            $inventoryIds[] = (int)$inventoryId;
            $context['cart_count'] += max(1, (int)$quantity);
        }

    require_once __DIR__ . '/db_connection.php';
    global $conn;
    $lookup = [];
        $connInstance = isset($conn) && ($conn instanceof mysqli) ? $conn : null;

        if ($connInstance && !empty($inventoryIds)) {
            $uniqueIds = array_unique(array_map('intval', $inventoryIds));
            $idList = implode(',', $uniqueIds);
            if ($idList !== '') {
                $result = $connInstance->query("SELECT inventory_id, product_name, price FROM inventory WHERE inventory_id IN ($idList)");
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $lookup[(int)$row['inventory_id']] = $row;
                    }
                }
            }
        }

        $customDesignIds = function_exists('get_custom_design_ids') ? get_custom_design_ids() : [];
        if (is_array($customDesignIds) && !empty($customDesignIds)) {
            $context['custom_designs'] = array_values(array_map('intval', $customDesignIds));
        }

        $estimatedTotal = 0.0;
        foreach ($cart as $inventoryId => $quantity) {
            $inventoryId = (int)$inventoryId;
            $quantity = max(1, (int)$quantity);
            $catalog = $lookup[$inventoryId] ?? null;
            $name = $catalog['product_name'] ?? ($inventoryId === 4 ? 'Custom Apparel' : 'Catalog Item #' . $inventoryId);
            $unitPrice = isset($catalog['price']) ? (float)$catalog['price'] : 0.0;
            $lineTotal = $unitPrice * $quantity;
            $estimatedTotal += $lineTotal;

            $context['cart_items'][] = [
                'inventory_id' => $inventoryId,
                'name' => $name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ];
        }

        if ($estimatedTotal > 0) {
            $context['estimated_total'] = round($estimatedTotal, 2);
        }

        $shippingInfo = $_SESSION['shipping_info'] ?? [];
        if (is_array($shippingInfo) && !empty($shippingInfo)) {
            $context['shipping'] = [
                'name' => $shippingInfo['full_name'] ?? '',
                'email' => $shippingInfo['email'] ?? '',
                'city' => $shippingInfo['city'] ?? '',
                'state' => $shippingInfo['state'] ?? '',
                'phone' => $shippingInfo['phone'] ?? '',
                'postal_code' => $shippingInfo['postal_code'] ?? '',
            ];
        }

        return $context;
    }
}

if (!isset($_SESSION['support_notes']) || !is_array($_SESSION['support_notes'])) {
    $_SESSION['support_notes'] = [];
}

if (empty($_SESSION['support_chat_id'])) {
    try {
        $seed = strtoupper(bin2hex(random_bytes(3)));
    } catch (Exception $e) {
        $seed = strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 6));
    }
    $_SESSION['support_chat_id'] = 'CHAT-' . $seed;
}

$chatLogId = $_SESSION['support_chat_id'];

$customerName = $_SESSION['shipping_info']['full_name'] ?? ($_SESSION['customer_name'] ?? 'Mystic Customer');
$customerEmail = $_SESSION['shipping_info']['email'] ?? ($_SESSION['customer_email'] ?? '');

switch ($action) {
    case 'log':
        $entry = [
            'timestamp' => time(),
            'role' => in_array($data['role'] ?? 'customer', ['agent', 'customer'], true) ? $data['role'] : 'customer',
            'message' => trim((string)($data['message'] ?? '')),
        ];
        if ($entry['message'] !== '') {
            $_SESSION['support_notes'][] = $entry;
        }
        $response['notes'] = $_SESSION['support_notes'];
        break;

    case 'escalate':
        $issueSummary = trim((string)($data['issue_summary'] ?? ''));
        $preferredContact = trim((string)($data['preferred_contact'] ?? ''));
        $conversation = array_slice($_SESSION['support_notes'], -25);
        $sendTranscript = !empty($data['send_transcript']);
        $whatsappHandoff = !empty($_SESSION['support_whatsapp_clicked']);

        $emailSent = sendSupportAlertEmail([
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'issue_summary' => $issueSummary,
            'preferred_contact' => $preferredContact,
            'conversation' => $conversation,
            'subject' => 'Checkout support escalation request',
            'chat_log_id' => $chatLogId,
            'channels' => ['whatsapp' => $whatsappHandoff]
        ]);

        $response['notes'] = $conversation;
        $response['emailSent'] = $emailSent;
        $orderContext = build_support_order_context();
        $ticketQueued = enqueue_support_ticket([
            'issue_summary' => $issueSummary,
            'preferred_contact' => $preferredContact,
            'conversation' => $conversation,
            'chat_log_id' => $chatLogId,
            'customer' => [
                'id' => $_SESSION['customer_id'] ?? null,
                'name' => $customerName,
                'email' => $customerEmail,
            ],
            'order_context' => $orderContext,
            'channels' => ['email' => (bool)$emailSent, 'whatsapp' => $whatsappHandoff],
            'source' => 'checkout_support',
        ]);
        $response['ticketQueued'] = $ticketQueued;
        if ($sendTranscript && $customerEmail) {
            $response['transcriptSent'] = sendSupportTranscriptToCustomer($customerEmail, $customerName, $conversation, $chatLogId);
        }
        break;

    case 'clear':
        $_SESSION['support_notes'] = [];
        $_SESSION['support_whatsapp_clicked'] = false;
        $response['notes'] = [];
        break;

    case 'whatsapp_click':
        $_SESSION['support_whatsapp_clicked'] = true;
        $response['chat_log_id'] = $chatLogId;
        break;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
        exit();
}

echo json_encode($response);
