<?php
// email_handler.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'vendor/PHPMailer/Exception.php';
require 'vendor/PHPMailer/PHPMailer.php';
require 'vendor/PHPMailer/SMTP.php';

function logMailFailure(string $context, string $message, array $meta = []): void
{
    $logDir = __DIR__ . '/storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    $entry = '[' . date('Y-m-d H:i:s') . "] {$context}: {$message}";
    if (!empty($meta)) {
        $entry .= ' | ' . json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    file_put_contents($logDir . '/email.log', $entry . PHP_EOL, FILE_APPEND);
}

function configureMysticMailer(): PHPMailer
{
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'jvb.ombca2023@gmail.com';
    $mail->Password   = preg_replace('/\s+/', '', 'sfuo tanm yofg zncm');
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;
    $mail->setFrom('jvb.ombca2023@gmail.com', 'Mystic Clothing Support');
    return $mail;
}

function sendOrderConfirmationEmail($to, $name, $order_id, array $context = []) {
    $mail = configureMysticMailer();

    try {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid recipient email provided.');
        }

        $mail->addAddress($to, $name); // The recipient's email address

        $items = $context['items'] ?? [];
        $shipping = $context['shipping'] ?? [];
        $total = isset($context['total']) ? (float)$context['total'] : 0.0;

        $itemRows = '';
        if (!empty($items)) {
            foreach ($items as $item) {
                $itemName = htmlspecialchars($item['name'] ?? 'Item');
                $itemQty = (int)($item['quantity'] ?? 1);
                $itemPrice = number_format((float)($item['price'] ?? 0), 2);
                $previewPath = !empty($item['preview']) ? htmlspecialchars($item['preview']) : '';
                $previewCell = $previewPath ? "<img src=\"{$previewPath}\" alt=\"{$itemName}\" style=\"width:60px;height:60px;border-radius:8px;border:1px solid #ddd;object-fit:cover;\" />" : '';
                $itemRows .= "<tr>";
                $itemRows .= "<td style=\"padding:8px 12px;border-bottom:1px solid #eee;\"><strong>{$itemName}</strong><div style=\"font-size:12px;color:#666;\">Qty: {$itemQty}</div></td>";
                $itemRows .= "<td style=\"padding:8px 12px;border-bottom:1px solid #eee;text-align:center;\">{$previewCell}</td>";
                $itemRows .= "<td style=\"padding:8px 12px;border-bottom:1px solid #eee;text-align:right;\">₹{$itemPrice}</td>";
                $itemRows .= "</tr>";
            }
        }

        $shippingBlock = '';
        if (!empty($shipping)) {
            $addressParts = array_filter([
                $shipping['full_name'] ?? '',
                $shipping['address'] ?? ''
            ]);
            if (!empty($addressParts)) {
                $shippingBlock = '<h3 style="margin-top:24px;margin-bottom:8px;">Shipping To</h3><p style="margin:0;color:#444;">' . nl2br(htmlspecialchars(implode("\n", $addressParts))) . '</p>';
            }
        }

        $orderTotalRow = $total > 0 ? '<tr><td colspan="3" style="padding:12px 12px 0;text-align:right;font-weight:bold;font-size:16px;">Order Total: ₹' . number_format($total, 2) . '</td></tr>' : '';
        $itemsTable = $itemRows ? "<table style='width:100%;border-collapse:collapse;margin-top:20px;'><thead><tr><th style='text-align:left;padding:8px 12px;border-bottom:2px solid #333;'>Item</th><th style='text-align:center;padding:8px 12px;border-bottom:2px solid #333;'>Preview</th><th style='text-align:right;padding:8px 12px;border-bottom:2px solid #333;'>Price</th></tr></thead><tbody>{$itemRows}</tbody>{$orderTotalRow}</table>" : '';

        // --- Content ---
        $mail->isHTML(true);
        $mail->Subject = 'Your Mystic Clothing Order Confirmation #' . $order_id;
        $mail->Body    = "
            <div style='font-family:Arial,sans-serif;color:#222;'>
                <h1 style='color:#1a3d7c;'>Thank you for your order, " . htmlspecialchars($name) . "!</h1>
                <p>Your order with ID <strong>#" . htmlspecialchars($order_id) . "</strong> has been placed successfully.</p>
                <p>We will send another update as soon as it ships.</p>
                {$itemsTable}
                {$shippingBlock}
                <p style='margin-top:24px;'>Need a change or spot an issue? Reply to this email and our crew will help right away.</p>
                <p style='margin-top:16px;'>Thank you for shopping with Mystic Clothing!</p>
            </div>
        ";

        $altLines = [
            'Thank you for your order!',
            'Order ID: #' . $order_id
        ];
        if (!empty($items)) {
            foreach ($items as $item) {
                $altLines[] = ($item['quantity'] ?? 1) . ' x ' . ($item['name'] ?? 'Item');
            }
        }
        if ($total > 0) {
            $altLines[] = 'Total: ₹' . number_format($total, 2);
        }
        if (!empty($shipping['address'] ?? '')) {
            $altLines[] = 'Ship to: ' . $shipping['address'];
        }
        $mail->AltBody = implode("\n", $altLines);

        $mail->send();
        return true;
    } catch (Exception $e) {
        $errorMessage = $mail->ErrorInfo ?: $e->getMessage();
        logMailFailure('order_confirmation', $errorMessage, ['to' => $to, 'order' => $order_id]);
        error_log("Message could not be sent. Mailer Error: {$errorMessage}");
        return false;
    }
}

function sendOrderStatusUpdateEmail(string $to, string $name, int $orderId, string $newStatus, array $context = []): bool
{
    $status = strtolower(trim($newStatus));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || $status === '') {
        return false;
    }

    $statusMessages = [
        'pending' => [
            'headline' => 'We have your order and it’s queued up!',
            'body' => 'Our print crew is reviewing the artwork. We will move it to production shortly.',
        ],
        'processing' => [
            'headline' => 'Production has started on your Mystic Apparel.',
            'body' => 'Our team is prepping the fabric, colors, and cure cycle for a perfect finish.',
        ],
        'shipped' => [
            'headline' => 'Your order has left the studio!',
            'body' => 'It’s now with our delivery partner. We will monitor it until it reaches your doorstep.',
        ],
        'completed' => [
            'headline' => 'Delivery confirmed.',
            'body' => 'Thanks for designing with Mystic Clothing. We hope you love the final piece!',
        ],
        'cancelled' => [
            'headline' => 'Order update: marked as cancelled.',
            'body' => 'We have closed out this order. If this was unexpected, reply to this email and we will jump in.',
        ],
    ];

    $copy = $statusMessages[$status] ?? [
        'headline' => 'Order update from Mystic Clothing',
        'body' => 'We wanted to let you know the latest status on your custom apparel.',
    ];

    $mail = configureMysticMailer();

    try {
        $mail->addAddress($to, $name ?: 'Mystic customer');
        $mail->isHTML(true);

        $subjectStatus = ucfirst($status);
        $mail->Subject = "Mystic Clothing order #{$orderId}: {$subjectStatus}";

        $estimatedShip = $context['estimated_ship'] ?? null;
        $trackingUrl = isset($context['tracking_url']) && filter_var($context['tracking_url'], FILTER_VALIDATE_URL)
            ? $context['tracking_url']
            : null;
        $amount = isset($context['amount']) ? (float) $context['amount'] : null;

        $extraLines = '';
        if ($estimatedShip) {
            $extraLines .= '<p style="margin:0 0 16px;color:#4b5563;">Estimated ship / delivery window: <strong>' . htmlspecialchars($estimatedShip) . '</strong></p>';
        }
        if ($trackingUrl) {
            $extraLines .= '<p style="margin:0 0 20px;"><a href="' . htmlspecialchars($trackingUrl) . '" style="display:inline-block;background:#4f46e5;color:#fff;padding:10px 18px;border-radius:999px;text-decoration:none;">Track my package</a></p>';
        }

        $amountLine = $amount !== null ? '<p style="margin:0 0 12px;color:#4b5563;">Order total: ₹' . number_format($amount, 2) . '</p>' : '';

        $mail->Body = '<div style="font-family:Inter,Arial,sans-serif;color:#111827;">'
            . '<h2 style="color:#4338ca;margin-bottom:12px;">' . htmlspecialchars($copy['headline']) . '</h2>'
            . '<p style="margin:0 0 16px;">' . htmlspecialchars($copy['body']) . '</p>'
            . '<p style="margin:0 0 12px;color:#4b5563;">Order reference: <strong>#' . htmlspecialchars((string)$orderId) . '</strong></p>'
            . $amountLine
            . $extraLines
            . '<p style="margin:24px 0 0;color:#64748b;font-size:13px;">Have a question? Reply to this email and our print experts will help right away.</p>'
            . '</div>';

        $altLines = [
            'Order update from Mystic Clothing',
            'Order ID #' . $orderId,
            'Status: ' . ucfirst($status),
        ];
        if ($amount !== null) {
            $altLines[] = 'Total: ₹' . number_format($amount, 2);
        }
        if ($estimatedShip) {
            $altLines[] = 'Estimated ship/delivery: ' . $estimatedShip;
        }
        if ($trackingUrl) {
            $altLines[] = 'Tracking: ' . $trackingUrl;
        }
        $mail->AltBody = implode("\n", $altLines);

        $mail->send();
        return true;
    } catch (Exception $e) {
        $errorMessage = $mail->ErrorInfo ?: $e->getMessage();
        logMailFailure('order_status_update', $errorMessage, [
            'to' => $to,
            'order' => $orderId,
            'status' => $status,
        ]);
        error_log('Order status email failed: ' . $errorMessage);
        return false;
    }
}

function sendDormantDesignNudgeEmail(string $to, string $name, array $designs): bool
{
    if (empty($designs)) {
        return false;
    }

    $mail = configureMysticMailer();

    try {
        $mail->addAddress($to, $name ?: 'Mystic Designer');
        $mail->isHTML(true);

        $subject = 'Your saved designs are ready for a reprint';
        $mail->Subject = $subject;

        $rows = '';
        $designs = array_slice($designs, 0, 5);
        foreach ($designs as $design) {
            $title = htmlspecialchars($design['product_name'] ?? 'Custom Apparel');
            $savedDate = !empty($design['created_at']) ? date('M j, Y', strtotime($design['created_at'])) : 'earlier this season';
            $preview = htmlspecialchars($design['front_preview_url'] ?? 'image/placeholder.png');
            $rows .= "<tr><td style='padding:10px 0;border-bottom:1px solid #eee;'>"
                . "<div style='display:flex;align-items:center;gap:12px;'>"
                . "<img src='{$preview}' style='width:56px;height:56px;border-radius:8px;border:1px solid #e5e7eb;object-fit:cover;' alt='Design preview'>"
                . "<div><strong>{$title}</strong><div style='font-size:12px;color:#475569;'>Saved {$savedDate}</div></div>"
                . '</div></td></tr>';
        }

        $designList = "<table style='width:100%;border-collapse:collapse;margin-top:18px;'>" . $rows . '</table>';

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'mystic-clothing.local';
        $dashboardUrl = $scheme . '://' . $host . '/account.php';

        $mail->Body = "<div style='font-family:Inter,Arial,sans-serif;color:#111827;'>"
            . "<h2 style='color:#4f46e5;'>We saved your favorites for the perfect moment.</h2>"
            . "<p style='margin-bottom:16px;'>A few of your recent designs are trending again. Reprint or tweak them before they disappear from your inspiration board.</p>"
            . $designList
            . "<div style='margin-top:20px;'>"
            . "<a href='" . htmlspecialchars($dashboardUrl) . "' style='display:inline-block;background:#4f46e5;color:#fff;padding:12px 20px;border-radius:999px;text-decoration:none;'>View my designs</a>"
            . '</div>'
            . '</div>';

        $altLines = [
            'We saved your favorite designs.',
            'Log in to Mystic Clothing to reprint them.',
        ];
        foreach ($designs as $design) {
            $altLines[] = ($design['product_name'] ?? 'Custom Apparel') . ' saved ' . (!empty($design['created_at']) ? date('M j, Y', strtotime($design['created_at'])) : 'recently');
        }
        $mail->AltBody = implode("\n", $altLines);

        $mail->send();
        return true;
    } catch (Exception $e) {
        $errorMessage = $mail->ErrorInfo ?: $e->getMessage();
        logMailFailure('dormant_nudge', $errorMessage, ['to' => $to]);
        error_log("Dormant nudge could not be sent. Mailer Error: {$errorMessage}");
        return false;
    }
}

function sendSupportAlertEmail(array $payload): bool
{
    $mail = configureMysticMailer();

    $adminAddress = 'jvb.ombca2023@gmail.com';
    $adminName = 'Mystic Support Desk';

    $customerName = $payload['customer_name'] ?? 'Mystic Customer';
    $customerEmail = $payload['customer_email'] ?? 'unknown';
    $subject = $payload['subject'] ?? 'Customer support request logged';
    $conversation = $payload['conversation'] ?? [];
    $issueSummary = trim((string)($payload['issue_summary'] ?? ''));
    $preferredContact = trim((string)($payload['preferred_contact'] ?? ''));
    $chatLogId = trim((string)($payload['chat_log_id'] ?? ''));
    $channels = is_array($payload['channels'] ?? null) ? $payload['channels'] : [];
    $whatsappHandoff = !empty($channels['whatsapp']);

    try {
        $mail->clearAddresses();
        $mail->addAddress($adminAddress, $adminName);
        $mail->isHTML(true);
        $mail->Subject = $subject;

        $conversationRows = '';
        if (!empty($conversation) && is_array($conversation)) {
            foreach ($conversation as $entry) {
                $timestamp = !empty($entry['timestamp']) ? date('M j, Y H:i', (int)$entry['timestamp']) : 'recently';
                $speaker = htmlspecialchars($entry['role'] ?? 'customer');
                $message = nl2br(htmlspecialchars($entry['message'] ?? ''));
                $conversationRows .= '<tr>'
                    . '<td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;white-space:nowrap;font-weight:600;text-transform:capitalize;">' . $speaker . '</td>'
                    . '<td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;color:#1f2937;">' . $message . '<div style="font-size:11px;color:#6b7280;margin-top:4px;">' . $timestamp . '</div></td>'
                    . '</tr>';
            }
        }

        $conversationTable = $conversationRows
            ? '<table style="width:100%;border-collapse:collapse;margin-top:16px;">'
                . '<thead><tr><th style="text-align:left;padding:8px 12px;border-bottom:2px solid #111827;">Speaker</th>'
                . '<th style="text-align:left;padding:8px 12px;border-bottom:2px solid #111827;">Message</th></tr></thead>'
                . '<tbody>' . $conversationRows . '</tbody></table>'
            : '<p style="margin-top:16px;color:#4b5563;">No transcript captured.</p>';

        $mail->Body = '<div style="font-family:Inter,Arial,sans-serif;color:#111827;">'
            . '<h2 style="color:#4338ca;margin-bottom:12px;">New support request</h2>'
            . '<p style="margin:0 0 12px;">A customer just asked for help via the checkout chat.</p>'
            . '<p style="margin:0 0 12px;font-size:15px;">'
            . '<strong>Name:</strong> ' . htmlspecialchars($customerName) . '<br>'
            . '<strong>Email:</strong> ' . htmlspecialchars($customerEmail) . '<br>'
            . ($preferredContact ? '<strong>Preferred contact:</strong> ' . htmlspecialchars($preferredContact) . '<br>' : '')
            . ($chatLogId ? '<strong>Chat log ID:</strong> ' . htmlspecialchars($chatLogId) . '<br>' : '')
            . '</p>'
            . ($issueSummary ? '<p style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin:0 0 16px;">'
                . '<strong>Issue summary:</strong><br>' . nl2br(htmlspecialchars($issueSummary)) . '</p>' : '')
            . ($whatsappHandoff ? '<p style="margin:0 0 16px;color:#047857;"><strong>Heads up:</strong> customer tapped the WhatsApp handoff. Expect a follow-up there.</p>' : '')
            . $conversationTable
            . '</div>';

        $altLines = [
            'New support request captured via payment chat.',
            'Customer: ' . $customerName,
            'Email: ' . $customerEmail,
        ];
        if ($preferredContact) {
            $altLines[] = 'Preferred contact: ' . $preferredContact;
        }
        if ($chatLogId) {
            $altLines[] = 'Chat log ID: ' . $chatLogId;
        }
        if ($issueSummary) {
            $altLines[] = 'Summary: ' . $issueSummary;
        }
        if ($whatsappHandoff) {
            $altLines[] = 'Customer requested WhatsApp follow-up.';
        }
        $mail->AltBody = implode("\n", $altLines);

        $mail->send();
        return true;
    } catch (Exception $e) {
        $errorMessage = $mail->ErrorInfo ?: $e->getMessage();
        logMailFailure('support_alert', $errorMessage, ['customer_email' => $customerEmail]);
        error_log('Support alert email failed: ' . $errorMessage);
        return false;
    }
}

function sendSupportTranscriptToCustomer(string $to, string $name, array $conversation, string $chatLogId = ''): bool
{
    if (empty($to) || empty($conversation)) {
        return false;
    }

    $mail = configureMysticMailer();

    try {
        $mail->clearAddresses();
        $mail->addAddress($to, $name ?: 'Mystic Clothing customer');
        $mail->isHTML(true);

        $subjectSuffix = $chatLogId ? ' - ' . $chatLogId : '';
        $mail->Subject = 'Your Mystic Clothing chat summary' . $subjectSuffix;

        $rows = '';
        foreach ($conversation as $entry) {
            $timestamp = !empty($entry['timestamp']) ? date('M j, Y H:i', (int)$entry['timestamp']) : 'recently';
            $speaker = htmlspecialchars($entry['role'] === 'agent' ? 'Riya (Print Expert)' : 'You');
            $message = nl2br(htmlspecialchars($entry['message'] ?? ''));
            $rows .= '<tr>'
                . '<td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;white-space:nowrap;font-weight:600;">' . $speaker . '</td>'
                . '<td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;color:#111827;">' . $message
                . '<div style="font-size:11px;color:#6b7280;margin-top:4px;">' . $timestamp . '</div>'
                . '</td>'
                . '</tr>';
        }

        $mail->Body = '<div style="font-family:Inter,Arial,sans-serif;color:#111827;">'
            . '<h2 style="color:#4338ca;margin-bottom:12px;">Here is a copy of our chat</h2>'
            . ($chatLogId ? '<p style="margin:0 0 12px;color:#4b5563;">Chat reference: <strong>' . htmlspecialchars($chatLogId) . '</strong></p>' : '')
            . '<p style="margin:0 0 16px;">We saved the key points from your conversation so you can refer back anytime.</p>'
            . '<table style="width:100%;border-collapse:collapse;margin-top:8px;">'
            . '<thead><tr><th style="text-align:left;padding:8px 12px;border-bottom:2px solid #111827;">Speaker</th>'
            . '<th style="text-align:left;padding:8px 12px;border-bottom:2px solid #111827;">Message</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table>'
            . '<p style="margin-top:20px;color:#4b5563;">Need anything else? Just reply to this email and we will jump back in.</p>'
            . '</div>';

        $altLines = ['Chat transcript from Mystic Clothing'];
        if ($chatLogId) {
            $altLines[] = 'Chat reference: ' . $chatLogId;
        }
        foreach ($conversation as $entry) {
            $altLines[] = strtoupper($entry['role'] ?? 'agent') . ': ' . ($entry['message'] ?? '');
        }
        $mail->AltBody = implode("\n", $altLines);

        $mail->send();
        return true;
    } catch (Exception $e) {
        $errorMessage = $mail->ErrorInfo ?: $e->getMessage();
        logMailFailure('support_transcript', $errorMessage, ['to' => $to, 'chatLogId' => $chatLogId]);
        error_log('Customer transcript email failed: ' . $errorMessage);
        return false;
    }
}

function renderStylistPersonaEmail(array $payload): array
{
    $templatePath = __DIR__ . '/emails/stylist_persona_template.html';
    $template = '';
    if (is_readable($templatePath)) {
        $template = (string) file_get_contents($templatePath);
    }

    $personaLabel = trim((string) ($payload['persona_label'] ?? 'Your Mystic bundle'));
    $personaSummary = trim((string) ($payload['persona_summary'] ?? 'We lined up a capsule that mirrors your picks.'));

    $sourceLabels = [
        'shop_quiz' => 'Shop quiz',
        'inbox_flow' => 'Stylist Inbox',
        'account' => 'Account dashboard',
        'account_prompt' => 'Account prompt',
        'spotlight' => 'Design spotlight',
        'session' => 'Mystic stylist',
    ];

    $rawSource = strtolower((string) ($payload['source'] ?? 'inbox_flow'));
    $sourceLabel = $sourceLabels[$rawSource] ?? 'Mystic stylist';

    $capturedAtFormatted = 'Just now';
    $capturedRaw = $payload['captured_at'] ?? '';
    if ($capturedRaw) {
        try {
            $capturedDt = new DateTime($capturedRaw);
            $capturedAtFormatted = $capturedDt->format('M j, Y \a\t g:i A');
        } catch (\Throwable $e) {
            $capturedAtFormatted = 'Recently';
        }
    }

    $customerName = trim((string) ($payload['customer_name'] ?? 'there'));

    $recommendations = is_array($payload['recommendations'] ?? null)
        ? array_slice(array_values($payload['recommendations']), 0, 3)
        : [];

    $recommendationsHtml = '';
    $altLines = [];
    if (!empty($recommendations)) {
        $recRows = '';
        foreach ($recommendations as $rec) {
            $name = htmlspecialchars((string) ($rec['name'] ?? 'Mystic Apparel'), ENT_QUOTES, 'UTF-8');
            $image = htmlspecialchars((string) ($rec['image_url'] ?? 'image/placeholder.png'), ENT_QUOTES, 'UTF-8');
            $reason = htmlspecialchars((string) ($rec['reason'] ?? ''), ENT_QUOTES, 'UTF-8');
            $price = null;
            if (isset($rec['price']) && is_numeric($rec['price'])) {
                $price = '₹' . number_format((float) $rec['price'], 2);
            }

            $priceBlock = $price ? '<div style="font-weight:600;color:#111827;margin-top:6px;">' . $price . '</div>' : '';
            $reasonBlock = $reason !== ''
                ? '<p style="margin:8px 0 0;font-size:13px;color:#475569;line-height:1.45;">' . $reason . '</p>'
                : '';

            $recRows .= '<tr>'
                . '<td style="padding:16px 0;border-bottom:1px solid #e5e7eb;">'
                . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0">'
                . '<tr>'
                . '<td width="88" valign="top" style="padding-right:16px;">'
                . '<img src="' . $image . '" alt="' . $name . ' preview" style="width:88px;height:88px;border-radius:14px;object-fit:cover;border:1px solid #e5e7eb;">'
                . '</td>'
                . '<td valign="top" style="font-size:14px;color:#111827;">'
                . '<strong style="display:block;font-size:15px;">' . $name . '</strong>'
                . $reasonBlock
                . $priceBlock
                . '</td>'
                . '</tr>'
                . '</table>'
                . '</td>'
                . '</tr>';

            $altLine = $name;
            if ($price) {
                $altLine .= ' – ' . $price;
            }
            if ($reason !== '') {
                $altSource = strip_tags(html_entity_decode((string) ($rec['reason'] ?? ''), ENT_QUOTES, 'UTF-8'));
                if ($altSource !== '') {
                    $altLine .= ' (' . $altSource . ')';
                }
            }
            $altLines[] = $altLine;
        }

        $recommendationsHtml = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">'
            . $recRows
            . '</table>';
    } else {
        $recommendationsHtml = '<p style="margin:0 0 24px;font-size:14px;color:#475569;background:#f1f5f9;border-radius:12px;padding:16px;">We are refreshing inventory for your palette. Tap the Stylist Inbox button and we will keep you posted.</p>';
        $altLines[] = 'Stylist is refreshing inventory for your capsule.';
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'mystic-clothing.local';
    $defaultBase = $scheme . '://' . $host;

    $ctaUrl = $payload['cta_url'] ?? ($defaultBase . '/stylist_inbox.php');
    $ctaCopy = $payload['cta_copy'] ?? 'Review my capsule';
    $ctaNote = $payload['cta_note'] ?? 'We will keep updating this bundle as new drops go live.';
    $supportUrl = $payload['support_url'] ?? ($defaultBase . '/support_artwork.php');
    $footerNote = $payload['footer_note'] ?? 'You are receiving this because you asked for Mystic stylist recommendations.';

    if ($template === '') {
        $plainHtml = '<div style="font-family:Arial,sans-serif;color:#111827;">'
            . '<h2>' . htmlspecialchars($personaLabel, ENT_QUOTES, 'UTF-8') . '</h2>'
            . '<p>' . htmlspecialchars($personaSummary, ENT_QUOTES, 'UTF-8') . '</p>'
            . $recommendationsHtml
            . '<p><a href="' . htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8') . '">Open Stylist Inbox</a></p>'
            . '</div>';

        return [
            'html' => $plainHtml,
            'alt' => implode("\n", array_merge([
                $personaLabel,
                $personaSummary,
            ], $altLines)),
        ];
    }

    $replacements = [
        '{{PERSONA_LABEL}}' => htmlspecialchars($personaLabel, ENT_QUOTES, 'UTF-8'),
        '{{PERSONA_SUMMARY}}' => htmlspecialchars($personaSummary, ENT_QUOTES, 'UTF-8'),
        '{{SOURCE_LABEL}}' => htmlspecialchars($sourceLabel, ENT_QUOTES, 'UTF-8'),
        '{{CAPTURED_AT}}' => htmlspecialchars($capturedAtFormatted, ENT_QUOTES, 'UTF-8'),
        '{{CUSTOMER_NAME}}' => htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8'),
        '{{RECOMMENDATIONS}}' => $recommendationsHtml,
        '{{CTA_URL}}' => htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8'),
        '{{CTA_COPY}}' => htmlspecialchars($ctaCopy, ENT_QUOTES, 'UTF-8'),
        '{{CTA_NOTE}}' => htmlspecialchars($ctaNote, ENT_QUOTES, 'UTF-8'),
        '{{SUPPORT_URL}}' => htmlspecialchars($supportUrl, ENT_QUOTES, 'UTF-8'),
        '{{FOOTER_NOTE}}' => htmlspecialchars($footerNote, ENT_QUOTES, 'UTF-8'),
    ];

    $htmlBody = strtr($template, $replacements);

    $altTextLines = array_merge([
        $personaLabel,
        $personaSummary,
    ], $altLines);

    return [
        'html' => $htmlBody,
        'alt' => implode("\n", $altTextLines),
    ];
}

function sendStylistPersonaEmail(string $to, string $name, array $personaPayload, array $context = []): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $mail = configureMysticMailer();

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'mystic-clothing.local';
    $defaultAccountUrl = $scheme . '://' . $host . '/account.php';

    $payload = $personaPayload;
    $payload['customer_name'] = $name ?: 'there';
    $payload['cta_url'] = $context['cta_url'] ?? ($context['deep_link'] ?? $defaultAccountUrl);
    $payload['cta_copy'] = $context['cta_copy'] ?? 'Review capsule in my account';
    $payload['cta_note'] = $context['cta_note'] ?? 'We will refresh matches as new drops go live.';
    $payload['support_url'] = $context['support_url'] ?? ($scheme . '://' . $host . '/support_artwork.php');
    $payload['footer_note'] = $context['footer_note'] ?? 'To opt-out of stylist emails, update preferences inside your Mystic account.';

    if (empty($payload['captured_at']) && !empty($personaPayload['captured_at'])) {
        $payload['captured_at'] = $personaPayload['captured_at'];
    }

    try {
        $mail->addAddress($to, $name ?: 'Mystic creator');
        $mail->isHTML(true);

        $rendered = renderStylistPersonaEmail($payload);

        $subject = $context['subject'] ?? ('Your ' . ($personaPayload['persona_label'] ?? 'Mystic bundle') . ' is ready');
        $mail->Subject = $subject;
        $mail->Body = $rendered['html'];
        $mail->AltBody = $rendered['alt'];

        $mail->send();
        return true;
    } catch (Exception $e) {
        $errorMessage = $mail->ErrorInfo ?: $e->getMessage();
        logMailFailure('stylist_persona', $errorMessage, ['to' => $to]);
        error_log('Stylist persona email failed: ' . $errorMessage);
        return false;
    }
}
?>