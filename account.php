<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Redirect to login if not logged in
if (!isset($_SESSION['customer_id'])) {
    $_SESSION['info_message'] = "You need to log in to view your account.";
    header('Location: login.php#login-modal');
    exit();
}

include 'header.php';
include 'db_connection.php';
require_once __DIR__ . '/core/style_quiz_helpers.php';
require_once __DIR__ . '/session_helpers.php';
require_once __DIR__ . '/email_handler.php';
require_once __DIR__ . '/core/custom_reward_wallet.php';

ensureStyleQuizResultsTable($conn);

$accountFlash = $_SESSION['account_flash'] ?? null;
$accountFlashType = $_SESSION['account_flash_type'] ?? 'info';
unset($_SESSION['account_flash'], $_SESSION['account_flash_type']);

$customer_id = $_SESSION['customer_id'];

$customerProfile = [];
$profileStmt = $conn->prepare('SELECT name, email, phone, address, created_at FROM customer WHERE customer_id = ? LIMIT 1');
if ($profileStmt) {
    $profileStmt->bind_param('i', $customer_id);
    if ($profileStmt->execute()) {
        $profileResult = $profileStmt->get_result();
        if ($profileResult) {
            $customerProfile = $profileResult->fetch_assoc() ?: [];
            $profileResult->free();
        }
    }
    $profileStmt->close();
}

$feedbackCheck = $conn->query("SHOW TABLES LIKE 'customer_feedback'");
$hasFeedbackTable = $feedbackCheck && $feedbackCheck->num_rows > 0;
if ($feedbackCheck) {
    $feedbackCheck->free();
}

$feedbackSelect = $hasFeedbackTable
    ? "cf.rating AS feedback_rating, cf.feedback_text AS feedback_text, cf.created_at AS feedback_created_at"
    : "NULL AS feedback_rating, NULL AS feedback_text, NULL AS feedback_created_at";

$orderSql = "SELECT o.order_id,
                    o.order_date,
                    o.status,
                    o.inventory_id,
                    o.design_id,
                    i.product_name,
                    i.image_url,
                    b.amount,
                d.design_file,
                d.design_file_back,
                cd.design_id AS custom_design_id,
                cd.front_preview_url AS custom_front_preview,
                cd.back_preview_url AS custom_back_preview,
                cd.texture_map_url AS custom_texture_url,
                cd.design_json AS custom_design_json,
                cd.apparel_type AS custom_apparel_type,
                cd.base_color AS custom_base_color,
                    {$feedbackSelect}
               FROM orders o
               JOIN inventory i ON o.inventory_id = i.inventory_id
               LEFT JOIN billing b ON o.order_id = b.order_id
            LEFT JOIN designs d ON o.design_id = d.design_id
            LEFT JOIN custom_designs cd ON o.design_id = cd.design_id";

if ($hasFeedbackTable) {
    $orderSql .= " LEFT JOIN customer_feedback cf ON cf.order_id = o.order_id";
}

$orderSql .= " WHERE o.customer_id = ?
            ORDER BY o.order_date DESC, o.order_id DESC";

$orderStmt = $conn->prepare($orderSql);
$orderStmt->bind_param('i', $customer_id);
$orderStmt->execute();
$orderResult = $orderStmt->get_result();
$orders = $orderResult->fetch_all(MYSQLI_ASSOC);
$orderStmt->close();
$recentOrderLimit = 5;

$orderedCustomDesignIdMap = [];
foreach ($orders as $orderRow) {
    $linkedCustomDesign = isset($orderRow['custom_design_id']) ? (int) $orderRow['custom_design_id'] : 0;
    if ($linkedCustomDesign > 0) {
        $orderedCustomDesignIdMap[$linkedCustomDesign] = true;
    }
}
$orderedCustomDesignIds = array_keys($orderedCustomDesignIdMap);

$totalOrdersCount = count($orders);
$lifetimeSpend = 0.0;
$lastOrderDateLabel = null;
if ($totalOrdersCount > 0) {
    $firstOrder = $orders[0];
    if (!empty($firstOrder['order_date'])) {
        $lastOrderDateLabel = date('M j, Y', strtotime($firstOrder['order_date']));
    }
    foreach ($orders as $orderSummary) {
        if (isset($orderSummary['amount'])) {
            $lifetimeSpend += (float) $orderSummary['amount'];
        }
    }
}

$profileEmailDisplay = trim((string) ($customerProfile['email'] ?? ($_SESSION['email'] ?? '')));
$profilePhoneDisplay = trim((string) ($customerProfile['phone'] ?? ''));
$profileAddressDisplay = trim((string) ($customerProfile['address'] ?? ''));
$memberSinceLabel = !empty($customerProfile['created_at'])
    ? date('M j, Y', strtotime($customerProfile['created_at']))
    : null;
$lifetimeSpendDisplay = formatPrice($lifetimeSpend);

$rewardWalletSummary = custom_reward_fetch_wallet($conn, $customer_id, 8);
$rewardWalletAvailable = (float) ($rewardWalletSummary['total_available'] ?? 0.0);
$rewardWalletRedeemed = (float) ($rewardWalletSummary['total_redeemed'] ?? 0.0);
$rewardWalletExpired = (float) ($rewardWalletSummary['total_expired'] ?? 0.0);
$rewardWalletEntries = $rewardWalletSummary['entries'] ?? [];
$rewardWalletHasEntries = !empty($rewardWalletEntries);
$rewardWalletRecent = $rewardWalletHasEntries ? $rewardWalletEntries[0] : null;


$customColumnsResult = $conn->query('SHOW COLUMNS FROM custom_designs');
$customColumnNames = [];
if ($customColumnsResult) {
    while ($col = $customColumnsResult->fetch_assoc()) {
        $customColumnNames[] = $col['Field'];
    }
    $customColumnsResult->free();
}

$hasBackPreview = in_array('back_preview_url', $customColumnNames, true);
$hasTagsColumn = in_array('tags', $customColumnNames, true);
$hasPriceColumn = in_array('price', $customColumnNames, true);
$hasTextureColumn = in_array('texture_map_url', $customColumnNames, true);
$hasDesignJsonColumn = in_array('design_json', $customColumnNames, true);
$hasApparelTypeColumn = in_array('apparel_type', $customColumnNames, true);
$hasBaseColorColumn = in_array('base_color', $customColumnNames, true);

$customSelect = "SELECT design_id,
            product_name,
            front_preview_url,
            " . ($hasBackPreview ? 'back_preview_url' : "front_preview_url AS back_preview_url") . ",
            " . ($hasTagsColumn ? 'tags' : "NULL AS tags") . ",
            created_at,
            " . ($hasPriceColumn ? 'price' : "NULL AS price") . ",
            " . ($hasTextureColumn ? 'texture_map_url' : "NULL AS texture_map_url") . ",
            " . ($hasDesignJsonColumn ? 'design_json' : "NULL AS design_json") . ",
            " . ($hasApparelTypeColumn ? 'apparel_type' : "NULL AS apparel_type") . ",
            " . ($hasBaseColorColumn ? 'base_color' : "NULL AS base_color") . "
       FROM custom_designs
      WHERE customer_id = ?
   ORDER BY created_at DESC";

$customStmt = $conn->prepare($customSelect);
$customStmt->bind_param('i', $customer_id);
$customStmt->execute();
$customResult = $customStmt->get_result();
$customDesigns = $customResult->fetch_all(MYSQLI_ASSOC);
$customStmt->close();
$customDesignLookup = [];
foreach ($customDesigns as $designRow) {
    $designLookupId = isset($designRow['design_id']) ? (int) $designRow['design_id'] : 0;
    if ($designLookupId > 0) {
        $customDesignLookup[$designLookupId] = $designRow;
    }
}
$spotlightDesignPicker = array_slice($customDesigns, 0, 6);

$spotlightTableExists = false;
$spotlightCheck = $conn->query("SHOW TABLES LIKE 'design_spotlight_submissions'");
if ($spotlightCheck) {
    $spotlightTableExists = $spotlightCheck->num_rows > 0;
    $spotlightCheck->free();
}

$spotlightSubmissions = [];
if ($spotlightTableExists) {
    $spotlightColumns = [];
    $spotlightColumnResult = $conn->query('SHOW COLUMNS FROM design_spotlight_submissions');
    if ($spotlightColumnResult) {
        while ($col = $spotlightColumnResult->fetch_assoc()) {
            $spotlightColumns[] = $col['Field'];
        }
        $spotlightColumnResult->free();
    }
    $hasHomepageQuoteColumn = in_array('homepage_quote', $spotlightColumns, true);
    if (!$hasHomepageQuoteColumn) {
        if ($conn->query("ALTER TABLE design_spotlight_submissions ADD COLUMN homepage_quote VARCHAR(160) DEFAULT NULL AFTER story")) {
            $hasHomepageQuoteColumn = true;
        }
    }

    $selectFields = 'id, design_id, title, story, ' . ($hasHomepageQuoteColumn ? 'homepage_quote' : "'' AS homepage_quote") . ', inspiration_url, instagram_handle, design_preview, share_gallery, status, created_at';
    $spotlightStmt = $conn->prepare('SELECT ' . $selectFields . ' FROM design_spotlight_submissions WHERE customer_id = ? ORDER BY created_at DESC LIMIT 5');
    if ($spotlightStmt) {
        $spotlightStmt->bind_param('i', $customer_id);
        if ($spotlightStmt->execute()) {
            $spotlightResult = $spotlightStmt->get_result();
            if ($spotlightResult) {
                $spotlightSubmissions = $spotlightResult->fetch_all(MYSQLI_ASSOC);
                $spotlightResult->free();
            }
        }
        $spotlightStmt->close();
    }
}

$topCompliments = $hasFeedbackTable
    ? array_values(array_filter($orders, static function ($order) {
        $ratingValue = $order['feedback_rating'] ?? null;
        $feedbackText = isset($order['feedback_text']) ? trim((string) $order['feedback_text']) : '';

        $hasPositiveRating = $ratingValue !== null && (int) $ratingValue >= 4;
        $hasWrittenFeedback = $feedbackText !== '';

        return $hasPositiveRating || $hasWrittenFeedback;
    }))
    : [];
if (count($topCompliments) > 5) {
    $topCompliments = array_slice($topCompliments, 0, 5);
}

$styleQuizResult = [
    'hasResult' => false,
    'persona_label' => '',
    'persona_summary' => '',
    'style' => '',
    'palette' => '',
    'goal' => '',
    'recommendations' => [],
    'source' => 'none',
    'captured_at' => null,
];

$quizSessionResult = $_SESSION['style_quiz_last_result'] ?? null;
$quizTableExists = false;
$quizCheck = $conn->query("SHOW TABLES LIKE 'style_quiz_results'");
if ($quizCheck) {
    $quizTableExists = $quizCheck->num_rows > 0;
    $quizCheck->free();
}

if ($quizTableExists) {
    $quizStmt = $conn->prepare('SELECT style_choice, palette_choice, goal_choice, persona_label, persona_summary, recommendations_json, source_label, submitted_at FROM style_quiz_results WHERE customer_id = ?');
    if ($quizStmt) {
        $quizStmt->bind_param('i', $customer_id);
        $quizStmt->execute();
        $quizResult = $quizStmt->get_result();
        $quizRow = $quizResult ? $quizResult->fetch_assoc() : null;
        if ($quizRow) {
            $decoded = json_decode($quizRow['recommendations_json'] ?? '[]', true);
            if (is_array($decoded)) {
                $styleQuizResult = [
                    'hasResult' => true,
                    'persona_label' => $quizRow['persona_label'] ?? 'Your Mystic bundle',
                    'persona_summary' => $quizRow['persona_summary'] ?? '',
                    'style' => $quizRow['style_choice'] ?? '',
                    'palette' => $quizRow['palette_choice'] ?? '',
                    'goal' => $quizRow['goal_choice'] ?? '',
                    'recommendations' => $decoded,
                    'source' => $quizRow['source_label'] ?? 'account',
                    'captured_at' => $quizRow['submitted_at'] ?? null,
                ];
            }
        }
        if ($quizResult) {
            $quizResult->free();
        }
        $quizStmt->close();
    }
}

if (!$styleQuizResult['hasResult'] && is_array($quizSessionResult)) {
    $styleQuizResult = [
        'hasResult' => true,
        'persona_label' => $quizSessionResult['persona_label'] ?? 'Your Mystic bundle',
        'persona_summary' => $quizSessionResult['persona_summary'] ?? '',
        'style' => $quizSessionResult['style'] ?? '',
        'palette' => $quizSessionResult['palette'] ?? '',
        'goal' => $quizSessionResult['goal'] ?? '',
        'recommendations' => is_array($quizSessionResult['recommendations'] ?? null) ? $quizSessionResult['recommendations'] : [],
        'source' => $quizSessionResult['source'] ?? 'session',
        'captured_at' => $quizSessionResult['captured_at'] ?? null,
    ];
}

if ($styleQuizResult['hasResult']) {
    $styleQuizResult['recommendations'] = array_values(array_filter(array_map(static function ($rec) {
        if (!is_array($rec)) {
            return null;
        }
        return [
            'inventory_id' => isset($rec['inventory_id']) ? (int) $rec['inventory_id'] : null,
            'name' => $rec['name'] ?? 'Mystic Apparel',
            'price' => isset($rec['price']) ? (float) $rec['price'] : null,
            'image_url' => $rec['image_url'] ?? 'image/placeholder.png',
            'reason' => $rec['reason'] ?? '',
        ];
    }, $styleQuizResult['recommendations'])));
}

$personaSourceLabels = [
    'shop_quiz' => 'Shop quiz',
    'inbox_flow' => 'Stylist Inbox',
    'account' => 'Account dashboard',
    'account_prompt' => 'Account prompt',
    'spotlight' => 'Design spotlight',
    'session' => 'Current session',
];

$styleQuizPersonaMeta = [
    'source' => '',
    'captured_at' => '',
];

if ($styleQuizResult['hasResult']) {
    $sourceKey = strtolower((string) ($styleQuizResult['source'] ?? ''));
    $styleQuizPersonaMeta['source'] = $personaSourceLabels[$sourceKey] ?? 'Mystic stylist';

    $capturedRaw = $styleQuizResult['captured_at'] ?? '';
    if ($capturedRaw) {
        try {
            $capturedTime = new DateTime($capturedRaw);
            $styleQuizPersonaMeta['captured_at'] = $capturedTime->format('M j, Y \a\t g:i A');
        } catch (Exception $e) {
            $styleQuizPersonaMeta['captured_at'] = '';
        }
    }
}

function parseDesignTags(?string $tagString): array
{
    if (!$tagString) {
        return [];
    }
    $decoded = json_decode($tagString, true);
    if (is_array($decoded)) {
        return array_values(array_filter(array_map('trim', $decoded)));
    }
    return array_values(array_filter(array_map('trim', explode(',', $tagString))));
}

function detectDormantDesigns(array $designs): array
{
    $threshold = (new DateTime('-45 days'));
    $dormant = [];
    foreach ($designs as $design) {
        $created = !empty($design['created_at']) ? new DateTime($design['created_at']) : null;
        if ($created && $created < $threshold) {
            $dormant[] = $design;
        }
    }
    return $dormant;
}

function detectAbandonedDesigns(array $designs, array $orderedIds): array
{
    $threshold = new DateTime('-14 days');
    $now = new DateTime();
    $orderedLookup = array_fill_keys($orderedIds, true);
    $abandoned = [];

    foreach ($designs as $design) {
        $designId = isset($design['design_id']) ? (int) $design['design_id'] : 0;
        if ($designId === 0 || isset($orderedLookup[$designId])) {
            continue;
        }

        $createdRaw = $design['created_at'] ?? null;
        $createdAt = $createdRaw ? new DateTime($createdRaw) : null;
        if (!$createdAt || $createdAt > $threshold) {
            continue;
        }

        $hasDesignJson = isset($design['design_json']) && trim((string) $design['design_json']) !== '';
        $hasTexture = isset($design['texture_map_url']) && trim((string) $design['texture_map_url']) !== '';
        $hasBackPreview = isset($design['back_preview_url']) && trim((string) $design['back_preview_url']) !== '';

        if ($hasDesignJson && $hasTexture) {
            continue;
        }

        $abandoned[] = [
            'design_id' => $designId,
            'product_name' => $design['product_name'] ?? 'Custom apparel draft',
            'front_preview_url' => $design['front_preview_url'] ?? 'image/placeholder.png',
            'back_preview_url' => $design['back_preview_url'] ?? '',
            'created_at' => $createdRaw,
            'relative_saved' => formatRelativeDays($createdRaw),
            'has_design_json' => $hasDesignJson,
            'has_texture' => $hasTexture,
            'has_back_preview' => $hasBackPreview,
            'apparel_type' => $design['apparel_type'] ?? '',
            'base_color' => $design['base_color'] ?? null,
            'age_days' => $createdAt ? $createdAt->diff($now)->days : null,
        ];
    }

    usort($abandoned, static function (array $a, array $b) {
        return ($b['age_days'] ?? 0) <=> ($a['age_days'] ?? 0);
    });

    return $abandoned;
}

$dormantDesigns = detectDormantDesigns($customDesigns);
$abandonedDesigns = detectAbandonedDesigns($customDesigns, $orderedCustomDesignIds);
$customDesignCount = count($customDesigns);
$dormantDesignIds = array_map(static fn($design) => (int) ($design['design_id'] ?? 0), $dormantDesigns);
$revisitRecommendations = array_slice($dormantDesigns, 0, 3);
$abandonedDesignHighlights = array_slice($abandonedDesigns, 0, 3);
$lastNudgeTimestamp = (int) ($_SESSION['dormant_nudge_last_sent'] ?? 0);
$nudgedRecently = $lastNudgeTimestamp > 0 && (time() - $lastNudgeTimestamp) < (7 * 24 * 60 * 60);
if (!$nudgedRecently && !empty($dormantDesigns) && !empty($_SESSION['email'])) {
    if (sendDormantDesignNudgeEmail($_SESSION['email'], $_SESSION['name'] ?? 'Mystic Designer', $dormantDesigns)) {
        $_SESSION['dormant_nudge_last_sent'] = time();
        $nudgedRecently = true;
    }
}

function formatPrice(?float $price): string
{
    if ($price === null) {
        return '₹0.00';
    }
    return '₹' . number_format($price, 2);
}

function formatRelativeDays(?string $date): string
{
    if (!$date) {
        return 'Some time ago';
    }
    $dateTime = new DateTime($date);
    $now = new DateTime();
    $diff = $now->diff($dateTime);

    if ($diff->y > 0) {
        return $diff->y === 1 ? '1 year ago' : $diff->y . ' years ago';
    }
    if ($diff->m > 0) {
        return $diff->m === 1 ? '1 month ago' : $diff->m . ' months ago';
    }
    if ($diff->d > 7) {
        $weeks = (int) floor($diff->d / 7);
        return $weeks === 1 ? '1 week ago' : $weeks . ' weeks ago';
    }
    if ($diff->d > 0) {
        return $diff->d === 1 ? 'Yesterday' : $diff->d . ' days ago';
    }
    if ($diff->h > 0) {
        return $diff->h === 1 ? '1 hour ago' : $diff->h . ' hours ago';
    }
    if ($diff->i > 0) {
        return $diff->i === 1 ? '1 minute ago' : $diff->i . ' minutes ago';
    }
    return 'Just now';
}

?>

<style>
    .account-page {
        color: var(--color-body);
    }
    .account-page h1,
    .account-page h2,
    .account-page h3,
    .account-page h4,
    .account-page h5,
    .account-page h6 {
        color: var(--color-dark);
    }
    .account-page a {
        color: var(--color-primary);
    }
    .account-page a:hover {
        color: var(--color-primary-hover);
    }
    .text-gray-400 { color: #94a3b8; }
    .text-gray-500 { color: #6b7280; }
    .text-gray-600 { color: #4b5563; }
    .text-gray-700 { color: #374151; }
    .text-gray-800 { color: #1f2937; }
    .text-emerald-700 { color: #047857; }
    .text-indigo-800 { color: #3730a3; }
    .text-yellow-700 { color: #b45309; }
    .text-indigo-600 { color: #4c1d95; }
    .text-indigo-600:hover { color: var(--color-primary); }
    .bg-gray-100 { background-color: #f8fafc; }
    .bg-green-100 { background-color: #ecfdf5; }
    .bg-blue-100 { background-color: #e0f2fe; }
    .bg-yellow-100 { background-color: #fef9c3; }
    .bg-red-100 { background-color: #fee2e2; }
    .text-green-800 { color: #166534; }
    .text-blue-800 { color: #1e3a8a; }
    .text-yellow-800 { color: #854d0e; }
    .text-red-800 { color: #991b1b; }
    .account-grid {
        display: grid;
        grid-template-columns: minmax(0, 3fr) minmax(0, 2fr);
        gap: 2.5rem;
    }
    .spotlight-submit-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 0.95fr);
        gap: 2rem;
        align-items: start;
    }
    .spotlight-intro h3 {
        font-size: 1.1rem;
        margin-bottom: 0.35rem;
    }
    .spotlight-intro p {
        font-size: 0.9rem;
        color: #475569;
        margin-bottom: 1.25rem;
    }
    .spotlight-picker-heading {
        display: block;
        font-weight: 700;
        margin-bottom: 0.6rem;
        color: #334155;
    }
    .spotlight-design-picker {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 0.85rem;
        margin-bottom: 0.9rem;
    }
    .spotlight-design-option {
        position: relative;
        display: block;
    }
    .spotlight-design-option input[type="radio"] {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    .spotlight-design-tile {
        display: flex;
        gap: 0.9rem;
        align-items: center;
        border: 1px solid rgba(148, 163, 184, 0.35);
        border-radius: 14px;
        padding: 0.85rem 1rem;
        background: #fff;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
        cursor: pointer;
    }
    .spotlight-design-option input[type="radio"]:checked + .spotlight-design-tile {
        border-color: #6366f1;
        box-shadow: 0 12px 24px rgba(99, 102, 241, 0.15);
        transform: translateY(-2px);
    }
    .spotlight-design-thumb {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        object-fit: cover;
        border: 1px solid rgba(148, 163, 184, 0.4);
        background: #f8fafc;
    }
    .spotlight-design-thumb--empty {
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 600;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }
    .spotlight-design-meta strong {
        display: block;
        font-size: 0.95rem;
        color: #0f172a;
        margin-bottom: 0.2rem;
    }
    .spotlight-design-meta span {
        display: block;
        font-size: 0.8rem;
        color: #64748b;
    }
    .spotlight-design-meta .spotlight-design-meta-extra {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        margin-top: 0.35rem;
        font-size: 0.78rem;
        color: #475569;
    }
    .spotlight-design-meta-color-swatch {
        width: 12px;
        height: 12px;
        border-radius: 999px;
        border: 1px solid rgba(15, 23, 42, 0.2);
    }
    .spotlight-form label {
        display: block;
        font-weight: 600;
        margin-bottom: 0.45rem;
        color: #334155;
    }
    .spotlight-form input[type="text"],
    .spotlight-form input[type="url"],
    .spotlight-form select,
    .spotlight-form textarea {
        width: 100%;
        border: 1px solid rgba(148, 163, 184, 0.5);
        border-radius: 12px;
        padding: 0.75rem 0.9rem;
        font-size: 0.95rem;
        color: #1f2937;
        background-color: #fff;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .spotlight-form input:focus,
    .spotlight-form select:focus,
    .spotlight-form textarea:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        outline: none;
    }
    .spotlight-form textarea {
        min-height: 150px;
        resize: vertical;
    }
    .spotlight-form .form-row {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        margin-bottom: 1rem;
    }
    .spotlight-form .checkbox-row {
        display: flex;
        align-items: flex-start;
        gap: 0.6rem;
        margin-top: 0.5rem;
        font-size: 0.85rem;
        color: #475569;
    }
    .spotlight-form .form-footnote {
        font-size: 0.8rem;
        color: #64748b;
        margin-top: -0.25rem;
        margin-bottom: 1rem;
    }
    .spotlight-history h3 {
        font-size: 1rem;
        margin-bottom: 0.4rem;
    }
    .spotlight-history p {
        font-size: 0.85rem;
        color: #64748b;
    }
    .spotlight-history-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        margin-top: 1rem;
    }
    .spotlight-history-card {
        border: 1px solid rgba(148, 163, 184, 0.3);
        border-radius: 14px;
        padding: 1rem;
        background: #f8fafc;
        display: grid;
        grid-template-columns: 72px 1fr;
        gap: 0.85rem;
    }
    .spotlight-history-card img {
        width: 72px;
        height: 72px;
        object-fit: cover;
        border-radius: 12px;
        border: 1px solid rgba(148, 163, 184, 0.4);
    }
    .spotlight-history-card strong {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.95rem;
        color: #0f172a;
        margin-bottom: 0.35rem;
    }
    .spotlight-history-card span {
        display: block;
        font-size: 0.82rem;
        color: #475569;
    }
    .spotlight-history-quote {
        font-style: italic;
        color: #0f172a;
    }
    .spotlight-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        border-radius: 999px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        padding: 0.25rem 0.65rem;
    }
    .spotlight-status-pill.pending { background: rgba(56, 189, 248, 0.18); color: #0369a1; }
    .spotlight-status-pill.approved { background: rgba(34, 197, 94, 0.18); color: #047857; }
    .spotlight-status-pill.rejected { background: rgba(248, 113, 113, 0.18); color: #b91c1c; }
    .spotlight-history-empty {
        border: 1px dashed rgba(148, 163, 184, 0.5);
        border-radius: 14px;
        padding: 1.5rem;
        font-size: 0.85rem;
        color: #64748b;
        background: #f8fafc;
    }
    @media (prefers-color-scheme: dark) {
        .spotlight-intro p,
    .spotlight-form label,
    .spotlight-form .checkbox-row,
    .spotlight-form .form-footnote,
    .spotlight-history p,
    .spotlight-history-card span { color: rgba(226, 232, 240, 0.85); }
    .spotlight-history-quote { color: rgba(255, 255, 255, 0.75); }
        .spotlight-picker-heading { color: #e2e8f0; }
        .spotlight-form input[type="text"],
        .spotlight-form input[type="url"],
        .spotlight-form select,
        .spotlight-form textarea {
            background: #0f172a;
            color: #e2e8f0;
            border-color: rgba(148, 163, 184, 0.35);
        }
        .spotlight-design-tile {
            background: rgba(15, 23, 42, 0.75);
            border-color: rgba(148, 163, 184, 0.35);
        }
        .spotlight-design-option input[type="radio"]:checked + .spotlight-design-tile {
            box-shadow: 0 12px 28px rgba(99, 102, 241, 0.35);
        }
        .spotlight-design-thumb {
            border-color: rgba(148, 163, 184, 0.25);
            background: rgba(15, 23, 42, 0.9);
        }
        .spotlight-design-thumb--empty { color: rgba(226, 232, 240, 0.6); }
        .spotlight-design-meta strong { color: #f8fafc; }
        .spotlight-design-meta span { color: rgba(226, 232, 240, 0.75); }
        .spotlight-design-meta .spotlight-design-meta-extra { color: rgba(226, 232, 240, 0.75); }
        .spotlight-design-meta-color-swatch { border-color: rgba(226, 232, 240, 0.35); }
        .spotlight-history-card {
            background: rgba(15, 23, 42, 0.65);
            border-color: rgba(148, 163, 184, 0.3);
        }
        .spotlight-history-card img {
            border-color: rgba(148, 163, 184, 0.25);
        }
        .spotlight-history-card strong { color: #f8fafc; }
        .spotlight-history-empty {
            background: rgba(15, 23, 42, 0.65);
            border-color: rgba(148, 163, 184, 0.38);
            color: rgba(226, 232, 240, 0.75);
        }
    }
    .account-card {
        position: relative;
        background: linear-gradient(145deg, rgba(255, 255, 255, 0.98), rgba(243, 246, 255, 0.96));
        border-radius: 1rem;
        border: 1px solid rgba(148, 163, 184, 0.18);
        box-shadow: 0 18px 36px rgba(15, 23, 42, 0.08), var(--shadow-lg);
        color: var(--color-body);
    }
    .reward-wallet-summary {
        display: flex;
        flex-wrap: wrap;
        gap: 1.5rem;
        margin-bottom: 1.25rem;
    }
    .reward-wallet-pill {
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
        padding: 1rem 1.2rem;
        border-radius: 0.85rem;
        border: 1px solid rgba(6, 182, 212, 0.28);
        background: rgba(224, 242, 254, 0.75);
        min-width: 180px;
        flex: 1 1 200px;
    }
    .reward-wallet-pill-value {
        font-size: 1.35rem;
        font-weight: 700;
        color: #0f766e;
    }
    .reward-wallet-pill-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #0f172a;
    }
    .reward-wallet-pill-caption {
        font-size: 0.75rem;
        color: #475569;
    }
    .reward-wallet-scroll {
        margin-top: 1.25rem;
        border: 1px solid rgba(148, 163, 184, 0.28);
        border-radius: 0.9rem;
        overflow: hidden;
    }
    .reward-wallet-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 520px;
    }
    .reward-wallet-table th,
    .reward-wallet-table td {
        font-size: 0.85rem;
        padding: 0.75rem 1rem;
        text-align: left;
        border-bottom: 1px solid rgba(148, 163, 184, 0.18);
    }
    .reward-wallet-table tbody tr:last-child td {
        border-bottom: none;
    }
    .reward-wallet-status {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.2rem 0.6rem;
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: capitalize;
    }
    .reward-wallet-status.available {
        background: rgba(16, 185, 129, 0.18);
        color: #047857;
    }
    .reward-wallet-status.redeemed {
        background: rgba(59, 130, 246, 0.18);
        color: #1d4ed8;
    }
    .reward-wallet-status.expired {
        background: rgba(248, 113, 113, 0.2);
        color: #b91c1c;
    }
    .reward-wallet-note {
        font-size: 0.78rem;
        color: #475569;
        max-width: 280px;
    }
    .reward-wallet-empty {
        border: 1px dashed rgba(148, 163, 184, 0.45);
        border-radius: 0.85rem;
        padding: 1.25rem 1.5rem;
        font-size: 0.9rem;
        color: #475569;
        background: rgba(248, 250, 252, 0.85);
    }
    .reward-wallet-tip {
        font-size: 0.8rem;
        color: #0369a1;
        margin: 0;
    }
    .account-card-header {
        padding: 1.5rem 1.75rem 0.5rem;
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
    }
    .account-card-header h2 {
        margin: 0;
        font-size: 1.4rem;
    }
    .account-card-body {
        padding: 1.5rem 1.75rem 1.75rem;
    }
    .style-rec-grid {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .style-rec-item {
        display: grid;
        grid-template-columns: 64px 1fr;
        gap: 0.9rem;
        border: 1px solid rgba(16, 185, 129, 0.25);
        border-radius: 0.85rem;
        padding: 0.85rem;
        background: #ecfdf5;
        color: var(--color-dark);
    }
    .style-rec-thumb {
        width: 64px;
        height: 64px;
        border-radius: 0.75rem;
        object-fit: cover;
    border: 1px solid rgba(16, 185, 129, 0.45);
    background: #f0fdfa;
    }
    .style-rec-body {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
    }
    .style-rec-body strong {
        font-size: 0.95rem;
        color: var(--color-dark);
    }
    .style-rec-meta {
        font-size: 0.8rem;
        color: #047857;
    }
    .style-rec-price {
        font-size: 0.85rem;
        font-weight: 600;
        color: #0f766e;
    }
    .style-rec-actions {
        margin-top: 0.5rem;
        display: flex;
        gap: 0.5rem;
    }
    .quiz-link {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.8rem;
        color: var(--color-secondary);
        font-weight: 600;
        margin-top: 1rem;
    }
    .order-list {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }
    .order-extra {
        display: none;
        margin-top: 1.25rem;
    }
    .order-extra.is-expanded {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }
    .order-view-toggle {
        margin-top: 1rem;
    }
    .order-row {
        display: grid;
        grid-template-columns: 72px 1fr auto;
        gap: 1.25rem;
        align-items: flex-start;
        padding-bottom: 1.25rem;
        border-bottom: 1px solid var(--color-border);
    }
    .order-row:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }
    .order-preview {
        width: 72px;
        height: 72px;
        border-radius: 0.75rem;
        overflow: hidden;
        border: 1px solid rgba(148, 163, 184, 0.3);
        background: rgba(148, 163, 184, 0.1);
        flex-shrink: 0;
    }
    .order-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .order-compliment {
        margin-top: 0.5rem;
        font-size: 0.85rem;
        color: var(--color-secondary);
        background: rgba(99, 102, 241, 0.12);
        padding: 0.55rem 0.75rem;
        border-radius: 0.75rem;
        border-left: 3px solid rgba(99, 102, 241, 0.35);
    }
    .flash-message {
        margin-bottom: 1.5rem;
        padding: 0.85rem 1.1rem;
        border-radius: 0.75rem;
        font-size: 0.9rem;
        font-weight: 500;
    }
    .flash-success {
        background: rgba(34, 197, 94, 0.12);
        color: #0f5132;
        border: 1px solid rgba(34, 197, 94, 0.25);
    }
    .flash-error {
        background: rgba(248, 113, 113, 0.12);
        color: #991b1b;
        border: 1px solid rgba(248, 113, 113, 0.25);
    }
    .flash-info {
        background: rgba(79, 70, 229, 0.12);
        color: #312e81;
        border: 1px solid rgba(99, 102, 241, 0.25);
    }
    details.review-details {
        margin-top: 0.75rem;
        border: 1px solid rgba(99, 102, 241, 0.2);
        border-radius: 0.75rem;
        padding: 0.75rem 1rem;
        background: rgba(237, 233, 254, 0.4);
        transition: background 0.2s ease;
    }
    details.review-details[open] {
        background: rgba(237, 233, 254, 0.7);
    }
    details.review-details summary {
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--color-secondary);
        outline: none;
        list-style: none;
    }
    details.review-details summary::-webkit-details-marker {
        display: none;
    }
    .review-form {
        margin-top: 0.75rem;
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
    }
    .review-form label {
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--color-dark);
    }
    .review-form select,
    .review-form textarea {
        border: 1px solid rgba(148, 163, 184, 0.6);
        border-radius: 0.6rem;
        padding: 0.55rem 0.65rem;
        font-size: 0.9rem;
        font-family: inherit;
        background: var(--color-surface);
        color: var(--color-body);
    }
    .review-form textarea {
        min-height: 90px;
        resize: vertical;
    }
    .review-form .form-help {
        font-size: 0.75rem;
        color: var(--color-light);
    }
    .review-form .btn {
        align-self: flex-start;
    }
    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        border-radius: 999px;
        padding: 0.3rem 0.7rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--color-dark);
        background: rgba(148, 163, 184, 0.18);
        border: 1px solid rgba(148, 163, 184, 0.35);
    }
    .status-pill--completed {
        background: rgba(16, 185, 129, 0.2);
        color: #047857;
        border: 1px solid rgba(16, 185, 129, 0.4);
    }
    .status-pill--processing {
        background: rgba(37, 99, 235, 0.18);
        color: #1e3a8a;
        border: 1px solid rgba(37, 99, 235, 0.45);
    }
    .status-pill--pending {
        background: rgba(250, 204, 21, 0.24);
        color: #92400e;
        border: 1px solid rgba(234, 179, 8, 0.5);
    }
    .status-pill--cancelled {
        background: rgba(239, 68, 68, 0.24);
        color: #7f1d1d;
        border: 1px solid rgba(239, 68, 68, 0.5);
    }
    .status-pill::before {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
    }
    .order-journey {
        margin-top: 0.85rem;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
        gap: 0.6rem;
        position: relative;
    }
    .order-journey__step {
        position: relative;
        padding-top: 1.4rem;
        text-align: center;
        font-size: 0.65rem;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #94a3b8;
    }
    .order-journey__step::before {
        content: '';
        position: absolute;
        top: 0.75rem;
        left: -50%;
        width: calc(100% + 0.6rem);
        height: 2px;
        background: rgba(148, 163, 184, 0.4);
        opacity: 0.6;
    }
    .order-journey__step:first-child::before {
        display: none;
    }
    .order-journey__dot {
        position: absolute;
        top: 0.35rem;
        left: 50%;
        transform: translateX(-50%);
        width: 0.85rem;
        height: 0.85rem;
        border-radius: 50%;
        background: #e2e8f0;
        border: 2px solid #cbd5f5;
        box-shadow: 0 0 0 0 rgba(79, 70, 229, 0.15);
    }
    .order-journey__step--complete {
        color: #1d4ed8;
    }
    .order-journey__step--complete .order-journey__dot {
        background: #4338ca;
        border-color: #4338ca;
        box-shadow: 0 0 0 2px rgba(67, 56, 202, 0.15);
    }
    .order-journey__step--complete::before {
        background: rgba(67, 56, 202, 0.45);
    }
    .order-journey__step--current {
        color: #312e81;
    }
    .order-journey__step--current .order-journey__dot {
        background: #fff;
        border-color: #4f46e5;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
    }
    .order-journey__step--current::before {
        background: rgba(79, 70, 229, 0.45);
    }
    .order-journey__step--upcoming .order-journey__dot {
        background: #f1f5f9;
        border-color: #cbd5f5;
    }
    .order-journey--cancelled .order-journey__step--cancelled {
        color: #9f1239;
    }
    .order-journey--cancelled .order-journey__step--cancelled .order-journey__dot {
        background: #f87171;
        border-color: #dc2626;
        box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.2);
    }
    .order-journey--cancelled .order-journey__step--cancelled::before {
        background: rgba(220, 38, 38, 0.45);
    }
    .order-journey__label {
        display: inline-block;
        width: 100%;
    }
    .order-journey__note {
        margin-top: 0.65rem;
        font-size: 0.78rem;
        color: #475569;
    }
    .design-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 1.5rem;
    }
    .design-card {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        border: 1px solid rgba(148, 163, 209, 0.28);
        border-radius: 1rem;
        padding: 1rem;
        background: rgba(14, 20, 44, 0.9);
        box-shadow: 0 18px 40px rgba(5, 8, 20, 0.4);
        color: rgba(226, 232, 240, 0.92);
    }
    .design-card-hero {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
    }
    .design-card h3 {
        color: #f8fafc;
    }
    .design-preview {
        background: rgba(11, 18, 38, 0.85);
        border-radius: 0.85rem;
        overflow: hidden;
        border: 1px solid rgba(148, 163, 209, 0.35);
        min-height: 160px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .design-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .design-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
    }
    .design-tag {
        font-size: 0.75rem;
        background: rgba(79, 70, 229, 0.22);
        color: #c7d2fe;
        padding: 0.3rem 0.55rem;
        border-radius: 999px;
    }
    .design-meta {
        font-size: 0.8rem;
        color: rgba(226, 232, 240, 0.7);
    }
    .design-actions {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
    }
    .dormant-banner {
        margin-top: 1rem;
        background: linear-gradient(135deg, rgba(250, 204, 21, 0.18), rgba(249, 115, 22, 0.18));
        border-radius: 0.85rem;
        padding: 0.85rem 1rem;
        font-size: 0.85rem;
        color: #fde68a;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }
    .compliment-list {
        display: flex;
        flex-direction: column;
        gap: 0.85rem;
    }
    .compliment-item {
        background: rgba(148, 163, 209, 0.18);
        border-radius: 0.85rem;
        padding: 0.75rem 1rem;
        font-size: 0.85rem;
    }
    .compliment-item strong {
        display: block;
        font-size: 0.8rem;
        color: var(--color-dark);
        margin-bottom: 0.35rem;
    }
    .account-sidebar {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }
    .nudge-card {
        background: linear-gradient(180deg, rgba(99, 102, 241, 0.22), rgba(129, 140, 248, 0.15));
        border-radius: 1rem;
        padding: 1.5rem;
        border: 1px solid rgba(79, 70, 229, 0.4);
        color: var(--color-body);
    }
    .nudge-card h3 {
        margin-bottom: 0.75rem;
        font-size: 1.1rem;
        color: var(--color-light);
    }
    .nudge-card .nudge-lead {
        color: var(--color-light);
    }
    .nudge-list {
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
        font-size: 0.9rem;
        color: var(--color-body);
    }

    .revisit-card {
        margin-bottom: 2.5rem;
        padding: 1.75rem;
        border-radius: 1.1rem;
        border: 1px solid rgba(45, 212, 191, 0.25);
        background: linear-gradient(135deg, rgba(13, 148, 136, 0.18), rgba(45, 212, 191, 0.12));
        box-shadow: 0 18px 45px rgba(8, 47, 73, 0.35);
        color: var(--color-body);
    }

    .revisit-card header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.25rem;
    }

    .revisit-card header h2 {
        margin: 0;
        font-size: 1.25rem;
        color: var(--color-dark);
    }

    .revisit-card header p {
        margin: 0;
        font-size: 0.85rem;
        color: rgba(226, 232, 240, 0.85);
    }

    .revisit-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .revisit-entry {
        display: grid;
        grid-template-columns: 68px 1fr auto;
        gap: 0.85rem;
        align-items: center;
        padding: 0.9rem;
        border-radius: 0.9rem;
        background: rgba(11, 19, 40, 0.85);
        border: 1px solid rgba(45, 212, 191, 0.25);
        color: rgba(226, 232, 240, 0.9);
    }

    .revisit-entry img {
        width: 68px;
        height: 68px;
        border-radius: 0.75rem;
        object-fit: cover;
        border: 1px solid rgba(45, 212, 191, 0.45);
    }

    .revisit-entry strong {
        font-size: 0.95rem;
        color: #f8fafc;
    }

    .revisit-entry p {
        margin: 0.2rem 0 0;
        font-size: 0.78rem;
        color: rgba(226, 232, 240, 0.75);
    }

    .revisit-entry .tagline {
        font-size: 0.75rem;
        color: rgba(94, 234, 212, 0.85);
    }

    .finish-design-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 1.2rem;
    }

    .finish-design-pill {
        display: grid;
        grid-template-columns: 82px 1fr;
        gap: 1rem;
        padding: 1rem;
        border-radius: 1rem;
        border: 1px dashed rgba(99, 102, 241, 0.38);
        background: rgba(237, 233, 254, 0.55);
        position: relative;
        overflow: hidden;
    }

    .finish-design-preview {
        width: 82px;
        height: 82px;
        border-radius: 0.85rem;
        overflow: hidden;
        background: rgba(99, 102, 241, 0.12);
        border: 1px solid rgba(99, 102, 241, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .finish-design-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .finish-design-body {
        display: flex;
        flex-direction: column;
        gap: 0.55rem;
    }

    .finish-design-title {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
    }

    .finish-design-title strong {
        font-size: 1rem;
        color: var(--color-dark);
    }

    .finish-design-badge {
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        background: rgba(79, 70, 229, 0.15);
        color: var(--color-primary-dark, #4c1d95);
        border-radius: 999px;
        padding: 0.2rem 0.55rem;
        border: 1px solid rgba(79, 70, 229, 0.25);
    }

    .finish-design-meta {
        font-size: 0.8rem;
        color: var(--color-muted, #6b7280);
    }

    .finish-design-color {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }

    .finish-design-color-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        border: 1px solid rgba(15, 23, 42, 0.12);
        display: inline-block;
    }

    .finish-design-needs {
        font-size: 0.85rem;
        color: var(--color-secondary, #0f766e);
    }

    .finish-design-actions {
        display: flex;
        gap: 0.6rem;
    }

    .finish-design-more {
        margin-top: 1.25rem;
        font-size: 0.78rem;
        color: var(--color-muted, #6b7280);
    }
    .nudge-actions {
        margin-top: 1rem;
        display: flex;
        flex-wrap: wrap;
        gap: 0.65rem;
    }

    @media (prefers-color-scheme: dark) {
        .account-card {
            background: var(--color-surface);
            border: 1px solid rgba(148, 163, 209, 0.32);
            color: var(--color-body);
            box-shadow: 0 18px 36px rgba(2, 6, 23, 0.55);
        }

        .account-page .text-gray-400 { color: #cbd5f5; }
        .account-page .text-gray-500 { color: #c7d2fe; }
        .account-page .text-gray-600 { color: #a5b4fc; }
        .account-page .text-gray-700 { color: #e2e8f0; }
        .account-page .text-gray-800 { color: #f8fafc; }
        .account-page .text-emerald-700 { color: #6ee7b7; }
        .account-page .text-indigo-800 { color: #c7d2fe; }
        .account-page .text-yellow-700 { color: #fef08a; }
        .account-page .bg-yellow-100 { background-color: rgba(250, 204, 21, 0.28); }
        .account-page .bg-green-100 { background-color: rgba(34, 197, 94, 0.28); }
        .account-page .bg-blue-100 { background-color: rgba(59, 130, 246, 0.28); }
        .account-page .bg-red-100 { background-color: rgba(248, 113, 113, 0.28); }

        .style-rec-item {
            background: rgba(13, 148, 136, 0.22);
            border-color: rgba(45, 212, 191, 0.45);
        }
        .style-rec-thumb {
            background: rgba(15, 118, 110, 0.35);
            border-color: rgba(45, 212, 191, 0.45);
        }
        .style-rec-meta {
            color: rgba(165, 243, 252, 0.85);
        }
        .style-rec-price {
            color: #5eead4;
        }

        .order-compliment {
            background: rgba(99, 102, 241, 0.28);
            color: #e0e7ff;
            border-left-color: rgba(129, 140, 248, 0.55);
        }

        .order-preview {
            border-color: rgba(129, 140, 248, 0.45);
            background: rgba(129, 140, 248, 0.18);
        }

        .flash-success {
            background: rgba(16, 185, 129, 0.25);
            color: #bbf7d0;
            border-color: rgba(16, 185, 129, 0.45);
        }
        .flash-error {
            background: rgba(239, 68, 68, 0.25);
            color: #fecaca;
            border-color: rgba(239, 68, 68, 0.45);
        }
        .flash-info {
            background: rgba(79, 70, 229, 0.25);
            color: #c7d2fe;
            border-color: rgba(99, 102, 241, 0.45);
        }

        details.review-details {
            background: rgba(79, 70, 229, 0.22);
            border-color: rgba(99, 102, 241, 0.35);
        }
        details.review-details[open] {
            background: rgba(99, 102, 241, 0.32);
        }
        .review-form select,
        .review-form textarea {
            background: var(--color-surface);
            color: var(--color-body);
            border-color: rgba(148, 163, 184, 0.45);
        }

        .dormant-banner {
            color: #fef9c3;
            background: linear-gradient(135deg, rgba(253, 230, 138, 0.22), rgba(251, 191, 36, 0.2));
        }

        .compliment-item {
            background: rgba(148, 163, 209, 0.32);
        }
        .compliment-item strong {
            color: rgba(226, 232, 240, 0.9);
        }

        .nudge-card {
            background: linear-gradient(180deg, rgba(99, 102, 241, 0.28), rgba(79, 70, 229, 0.22));
            color: var(--color-body);
            border-color: rgba(99, 102, 241, 0.5);
        }
        .nudge-card .nudge-lead {
            color: var(--color-light);
        }
        .nudge-list {
            color: rgba(226, 232, 240, 0.82);
        }

        .revisit-card {
            background: linear-gradient(135deg, rgba(15, 118, 110, 0.22), rgba(45, 212, 191, 0.2));
            color: var(--color-body);
            box-shadow: 0 18px 45px rgba(2, 44, 34, 0.55);
        }
        .revisit-card header p {
            color: rgba(203, 213, 225, 0.85);
        }
        .revisit-entry {
            background: rgba(7, 16, 32, 0.9);
            border-color: rgba(45, 212, 191, 0.35);
        }
        .revisit-entry .tagline {
            color: rgba(165, 243, 252, 0.75);
        }

        .finish-design-pill {
            border-color: rgba(129, 140, 248, 0.45);
            background: rgba(99, 102, 241, 0.22);
        }
        .finish-design-preview {
            background: rgba(129, 140, 248, 0.18);
            border-color: rgba(129, 140, 248, 0.45);
        }
        .finish-design-title strong {
            color: #e0e7ff;
        }
        .finish-design-badge {
            background: rgba(79, 70, 229, 0.25);
            color: #c7d2fe;
            border-color: rgba(129, 140, 248, 0.45);
        }
        .finish-design-meta {
            color: rgba(203, 213, 225, 0.85);
        }
        .finish-design-needs {
            color: rgba(165, 243, 252, 0.85);
        }
        .finish-design-more {
            color: rgba(203, 213, 225, 0.75);
        }
        .reward-wallet-pill {
            background: rgba(14, 165, 233, 0.22);
            border-color: rgba(14, 165, 233, 0.35);
        }
        .reward-wallet-pill-value {
            color: #bae6fd;
        }
        .reward-wallet-pill-label,
        .reward-wallet-pill-caption {
            color: rgba(226, 232, 240, 0.78);
        }
        .reward-wallet-scroll {
            border-color: rgba(148, 163, 184, 0.35);
            background: rgba(15, 23, 42, 0.75);
        }
        .reward-wallet-table th,
        .reward-wallet-table td {
            color: rgba(226, 232, 240, 0.88);
            border-bottom: 1px solid rgba(148, 163, 184, 0.28);
        }
        .reward-wallet-note {
            color: rgba(203, 213, 225, 0.82);
        }
        .reward-wallet-empty {
            background: rgba(15, 23, 42, 0.78);
            border-color: rgba(148, 163, 184, 0.45);
            color: rgba(226, 232, 240, 0.82);
        }
        .reward-wallet-tip {
            color: rgba(191, 219, 254, 0.9);
        }

        .status-pill {
            color: #f8fafc;
            background: rgba(148, 163, 184, 0.2);
            border-color: rgba(148, 163, 184, 0.45);
        }
        .status-pill--completed {
            background: rgba(16, 185, 129, 0.32);
            color: #bbf7d0;
        }
        .status-pill--processing {
            background: rgba(37, 99, 235, 0.32);
            color: #dbeafe;
        }
        .status-pill--pending {
            background: rgba(250, 204, 21, 0.35);
            color: #fef9c3;
        }
        .status-pill--cancelled {
            background: rgba(239, 68, 68, 0.32);
            color: #fecdd3;
        }
        .profile-info > div {
            background: rgba(15, 23, 42, 0.88);
            border-color: rgba(148, 163, 209, 0.35);
            box-shadow: 0 20px 48px rgba(2, 6, 23, 0.55);
        }
        .profile-info > div:hover {
            box-shadow: 0 24px 60px rgba(30, 64, 175, 0.45);
        }
        .profile-info dt {
            color: rgba(203, 213, 225, 0.78);
        }
        .profile-info dd {
            color: #e2e8f0;
        }
        .profile-muted {
            color: rgba(148, 163, 209, 0.7);
        }
        .profile-stats > div {
            background: linear-gradient(135deg, rgba(76, 29, 149, 0.32), rgba(14, 165, 233, 0.25));
            border-color: rgba(99, 102, 241, 0.35);
            box-shadow: 0 18px 44px rgba(15, 23, 42, 0.55);
        }
        .profile-stat-value {
            color: #e0e7ff;
        }
        .profile-stat-label {
            color: rgba(165, 180, 252, 0.75);
        }
        .profile-form {
            background: rgba(15, 23, 42, 0.94);
            border-color: rgba(148, 163, 209, 0.45);
            box-shadow: 0 24px 55px rgba(2, 6, 23, 0.6);
        }
        .profile-form label {
            color: rgba(203, 213, 225, 0.78);
        }
        .profile-form input,
        .profile-form textarea {
            background: rgba(15, 23, 42, 0.7);
            color: #e2e8f0;
            border-color: rgba(148, 163, 209, 0.45);
            box-shadow: inset 0 1px 2px rgba(2, 6, 23, 0.55);
        }
        .profile-form .form-hint {
            color: rgba(148, 163, 209, 0.75);
        }

    }
        .profile-info {
            display: grid;
            grid-template-columns: repeat(1, minmax(0, 1fr));
            gap: 0.85rem;
        }
        .profile-info-grid {
            display: grid;
            grid-template-columns: repeat(1, minmax(0, 1fr));
            gap: 1.25rem;
        }
        .profile-info dt {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #475569;
            margin-bottom: 0.2rem;
        }
        .profile-info dd {
            font-size: 1rem;
            color: #0f172a;
            font-weight: 600;
            margin: 0;
        }
        .profile-info > div {
            padding: 1rem 1.1rem;
            border-radius: 1.1rem;
            border: 1px solid rgba(148, 163, 184, 0.18);
            background: linear-gradient(140deg, rgba(255, 255, 255, 0.98), rgba(241, 245, 255, 0.94));
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.07);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .profile-info > div:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 46px rgba(79, 70, 229, 0.14);
        }
        .profile-muted {
            color: #64748b;
            font-style: italic;
        }
        .profile-actions {
            margin-top: 1.25rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .profile-stats {
            margin-top: 1.25rem;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.85rem;
        }
        .profile-stats > div {
            padding: 1rem;
            border-radius: 1rem;
            border: 1px solid rgba(99, 102, 241, 0.16);
            background: linear-gradient(140deg, rgba(236, 233, 254, 0.65), rgba(219, 234, 254, 0.6));
            box-shadow: 0 14px 32px rgba(76, 29, 149, 0.12);
        }
        .profile-stat-value {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1e293b;
        }
        .profile-stat-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
        }
        .profile-form {
            margin-top: 1.5rem;
            display: grid;
            gap: 1rem;
            padding: 1.4rem;
            border-radius: 1.1rem;
            border: 1px solid rgba(148, 163, 184, 0.22);
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.97), rgba(243, 246, 255, 0.94));
            box-shadow: 0 20px 44px rgba(15, 23, 42, 0.08);
        }
        .profile-form .form-row {
            display: grid;
            gap: 0.75rem;
        }
        .profile-form .form-row.two-column {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .profile-form label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #475569;
            display: block;
            margin-bottom: 0.35rem;
        }
        .profile-form input,
        .profile-form textarea {
            width: 100%;
            border-radius: 0.75rem;
            border: 1px solid rgba(148, 163, 184, 0.32);
            padding: 0.85rem 1rem;
            background: rgba(255, 255, 255, 0.98);
            color: #0f172a;
            font-size: 0.95rem;
            box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.04);
        }
        .profile-form textarea {
            min-height: 120px;
            resize: vertical;
        }
        .profile-form input:focus,
        .profile-form textarea:focus {
            outline: none;
            border-color: rgba(99, 102, 241, 0.55);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.18);
        }
        .profile-form .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: center;
        }
        .profile-form .form-hint {
            font-size: 0.78rem;
            color: #64748b;
        }

    @media (min-width: 1024px) {
        .profile-info-grid {
            grid-template-columns: minmax(0, 1.75fr) minmax(0, 1fr);
            align-items: start;
        }
        .profile-stats {
            margin-top: 0;
        }
    }

    @media (max-width: 1024px) {
        .account-grid {
            grid-template-columns: 1fr;
        }
        .nudge-card .nudge-lead {
            color: var(--color-dark);
        }
        .order-row > :last-child {
            grid-column: 1 / -1;
            justify-self: flex-start;
        }
        .profile-stats {
            grid-template-columns: 1fr;
        }
        .profile-form .form-row {
            grid-template-columns: 1fr;
        }
        .spotlight-submit-grid {
            grid-template-columns: 1fr;
        }
        .spotlight-history-card {
            grid-template-columns: 56px 1fr;
        }
    }
</style>

<main class="container py-12 account-page">
    <h1 class="text-3xl font-bold mb-8">My Account</h1>
    <?php if (!empty($accountFlash)): ?>
        <div class="flash-message flash-<?php echo htmlspecialchars($accountFlashType); ?>">
            <?php echo htmlspecialchars($accountFlash); ?>
        </div>
    <?php endif; ?>

    <section class="account-card mb-8" id="account-overview">
        <header class="account-card-header">
            <h2>Account Snapshot</h2>
            <p class="text-sm text-gray-500">Keep your contact details current so we can reach you with delivery updates.</p>
        </header>
        <div class="account-card-body">
            <div class="profile-info-grid">
                <dl class="profile-info">
                    <div>
                        <dt>Email</dt>
                        <dd>
                            <?php if ($profileEmailDisplay !== ''): ?>
                                <?php echo htmlspecialchars($profileEmailDisplay); ?>
                            <?php else: ?>
                                <span class="profile-muted">Add your email</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div>
                        <dt>Phone</dt>
                        <dd>
                            <?php if ($profilePhoneDisplay !== ''): ?>
                                <?php echo htmlspecialchars($profilePhoneDisplay); ?>
                            <?php else: ?>
                                <span class="profile-muted">Share a contact number</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div id="addresses">
                        <dt>Shipping Address</dt>
                        <dd>
                            <?php if ($profileAddressDisplay !== ''): ?>
                                <?php echo nl2br(htmlspecialchars($profileAddressDisplay)); ?>
                            <?php else: ?>
                                <span class="profile-muted">Add a default shipping address</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div>
                        <dt>Member Since</dt>
                        <dd>
                            <?php if ($memberSinceLabel): ?>
                                <?php echo htmlspecialchars($memberSinceLabel); ?>
                            <?php else: ?>
                                <span class="profile-muted">We will record your start date on your first order</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div>
                        <dt>Last Order</dt>
                        <dd>
                            <?php if ($lastOrderDateLabel): ?>
                                <?php echo htmlspecialchars($lastOrderDateLabel); ?>
                            <?php else: ?>
                                <span class="profile-muted">Place your first order to see it here</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                </dl>
                <div class="profile-stats">
                    <div>
                        <p class="profile-stat-value"><?php echo htmlspecialchars($lifetimeSpendDisplay); ?></p>
                        <p class="profile-stat-label">Lifetime spend</p>
                    </div>
                    <div>
                        <p class="profile-stat-value"><?php echo number_format($totalOrdersCount); ?></p>
                        <p class="profile-stat-label">Total orders</p>
                    </div>
                </div>
            </div>

            <form method="POST" action="update_profile.php" class="profile-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <div class="form-row two-column">
                    <div>
                        <label for="profile-name">Full name</label>
                        <input type="text" id="profile-name" name="name" value="<?php echo htmlspecialchars($customerProfile['name'] ?? ($_SESSION['name'] ?? '')); ?>" required>
                    </div>
                    <div>
                        <label for="profile-email">Email</label>
                        <input type="email" id="profile-email" name="email" value="<?php echo htmlspecialchars($profileEmailDisplay); ?>" required>
                    </div>
                </div>
                <div class="form-row two-column">
                    <div>
                        <label for="profile-phone">Phone</label>
                        <input type="text" id="profile-phone" name="phone" value="<?php echo htmlspecialchars($profilePhoneDisplay); ?>" placeholder="e.g. +91 98765 43210" pattern="[0-9+\-\s]{6,}" title="Please enter at least 6 digits (numbers, spaces, + or -)">
                    </div>
                    <div>
                        <label for="profile-address">Default shipping address</label>
                        <textarea id="profile-address" name="address" rows="4" placeholder="House number, street, city, state, postal code"><?php echo htmlspecialchars($profileAddressDisplay); ?></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-sm">Save changes</button>
                    <span class="form-hint">We’ll use these details for future deliveries and updates.</span>
                </div>
            </form>
        </div>
    </section>

    <section class="account-card mb-8" id="creator-reward-wallet">
        <header class="account-card-header">
            <h2>Creator Reward Wallet</h2>
            <p class="text-sm text-gray-500">Earned credits from your custom designs auto-apply at checkout.</p>
        </header>
        <div class="account-card-body">
            <?php if ($rewardWalletAvailable <= 0 && !$rewardWalletHasEntries): ?>
                <div class="reward-wallet-empty">
                    <strong>No credits yet.</strong> When other shoppers buy your custom designs during a drop, you'll earn credits that show up here for future orders.
                </div>
            <?php else: ?>
                <div class="reward-wallet-summary">
                    <div class="reward-wallet-pill">
                        <span class="reward-wallet-pill-label">Available to spend</span>
                        <span class="reward-wallet-pill-value"><?php echo htmlspecialchars(formatPrice($rewardWalletAvailable)); ?></span>
                        <?php if ($rewardWalletRecent && !empty($rewardWalletRecent['granted_at'])): ?>
                            <span class="reward-wallet-pill-caption">Last credit: <?php echo htmlspecialchars(date('M j, Y', strtotime($rewardWalletRecent['granted_at']))); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="reward-wallet-pill">
                        <span class="reward-wallet-pill-label">Redeemed so far</span>
                        <span class="reward-wallet-pill-value"><?php echo htmlspecialchars(formatPrice($rewardWalletRedeemed)); ?></span>
                    </div>
                    <?php if ($rewardWalletExpired > 0): ?>
                        <div class="reward-wallet-pill">
                            <span class="reward-wallet-pill-label">Expired</span>
                            <span class="reward-wallet-pill-value"><?php echo htmlspecialchars(formatPrice($rewardWalletExpired)); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <p class="reward-wallet-tip">
                    <?php if ($rewardWalletAvailable > 0): ?>
                        We'll automatically apply your available credits to your next checkout.
                    <?php else: ?>
                        Credits appear here as soon as other shoppers place orders with your designs.
                    <?php endif; ?>
                </p>

                <?php if ($rewardWalletHasEntries): ?>
                    <div class="reward-wallet-scroll">
                        <table class="reward-wallet-table">
                            <thead>
                                <tr>
                                    <th scope="col">Date</th>
                                    <th scope="col">Amount</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Drop</th>
                                    <th scope="col">Order #</th>
                                    <th scope="col">Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rewardWalletEntries as $walletEntry): ?>
                                    <?php
                                        $grantedAtRaw = $walletEntry['granted_at'] ?? '';
                                        $grantedAtLabel = $grantedAtRaw ? date('M j, Y', strtotime($grantedAtRaw)) : '—';
                                        $amountValue = isset($walletEntry['reward_amount']) ? (float) $walletEntry['reward_amount'] : 0.0;
                                        $statusLabel = strtolower((string) ($walletEntry['status'] ?? 'available'));
                                        $dropSlug = trim((string) ($walletEntry['drop_slug'] ?? ''));
                                        $orderId = isset($walletEntry['order_id']) ? (int) $walletEntry['order_id'] : 0;
                                        $note = trim((string) ($walletEntry['notes'] ?? ''));
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($grantedAtLabel); ?></td>
                                        <td><?php echo htmlspecialchars(formatPrice($amountValue)); ?></td>
                                        <td><span class="reward-wallet-status <?php echo htmlspecialchars($statusLabel); ?>"><?php echo htmlspecialchars(ucfirst($statusLabel)); ?></span></td>
                                        <td>
                                            <?php if ($dropSlug !== ''): ?>
                                                <?php echo htmlspecialchars($dropSlug); ?>
                                            <?php else: ?>
                                                <span class="text-gray-500">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $orderId > 0 ? '#' . (int) $orderId : '—'; ?></td>
                                        <td class="reward-wallet-note"><?php echo $note !== '' ? htmlspecialchars($note) : '—'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

        <?php if (!empty($abandonedDesignHighlights)): ?>
        <section class="account-card finish-design-card mb-8">
            <header class="account-card-header">
                <h2>Finish Your Designs</h2>
                <p class="text-sm text-gray-500">You have a few saved drafts without final print files. Jump back in and ship them.</p>
            </header>
            <div class="account-card-body">
                <div class="finish-design-grid">
                    <?php foreach ($abandonedDesignHighlights as $draft):
                        $preview = $draft['front_preview_url'] ?: 'image/placeholder.png';
                        $needs = [];
                        if (!$draft['has_design_json']) {
                            $needs[] = 'designer canvas edits';
                        }
                        if (!$draft['has_texture']) {
                            $needs[] = 'print file export';
                        }
                        if (!$draft['has_back_preview']) {
                            $needs[] = 'back preview';
                        }
                        $needsLabel = !empty($needs)
                            ? 'Needs ' . implode(' · ', $needs)
                            : 'Add finishing touches and send it to print.';
                    ?>
                    <article class="finish-design-pill">
                        <div class="finish-design-preview">
                            <img src="<?php echo htmlspecialchars($preview); ?>" alt="Draft design preview" loading="lazy">
                        </div>
                        <div class="finish-design-body">
                            <div class="finish-design-title">
                                <strong><?php echo htmlspecialchars($draft['product_name'] ?: 'Custom apparel draft'); ?></strong>
                                <?php if (!$draft['has_texture']): ?>
                                    <span class="finish-design-badge">Print file missing</span>
                                <?php endif; ?>
                                <?php if (!$draft['has_design_json']): ?>
                                    <span class="finish-design-badge">Canvas unsaved</span>
                                <?php endif; ?>
                            </div>
                            <p class="finish-design-meta">
                                Saved <?php echo htmlspecialchars($draft['relative_saved']); ?>
                                <?php if (!empty($draft['apparel_type'])): ?>
                                    • <?php echo htmlspecialchars(ucfirst($draft['apparel_type'])); ?>
                                <?php endif; ?>
                                <?php if (!empty($draft['base_color'])): ?>
                                    • <span class="finish-design-color"><span class="finish-design-color-dot" style="background-color: <?php echo htmlspecialchars($draft['base_color']); ?>;"></span><?php echo htmlspecialchars(strtoupper(ltrim($draft['base_color'], '#'))); ?></span>
                                <?php endif; ?>
                            </p>
                            <p class="finish-design-needs"><?php echo htmlspecialchars($needsLabel); ?></p>
                            <div class="finish-design-actions">
                                <a href="design3d.php?design_id=<?php echo (int) $draft['design_id']; ?>" class="btn btn-primary btn-sm">Finish this design</a>
                            </div>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php if (count($abandonedDesigns) > count($abandonedDesignHighlights)): ?>
                    <p class="finish-design-more">You have <?php echo count($abandonedDesigns) - count($abandonedDesignHighlights); ?> more drafts waiting in “Saved Custom Designs”.</p>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

    <div class="account-grid">
        <section class="account-card">
            <header class="account-card-header">
                <h2>Order History</h2>
                <p class="text-sm text-gray-500">Track recent purchases, reorder favorites, and surface your best compliments.</p>
            </header>
            <div class="account-card-body">
                <?php if (empty($orders)): ?>
                    <div class="empty-state orders-empty">
                        <div class="empty-state-icon">
                            <i data-feather="package"></i>
                        </div>
                        <h3 class="empty-state-title">No orders yet</h3>
                        <p class="empty-state-description">
                            You haven't placed any orders yet. Start shopping to see your order history here!
                        </p>
                        <div class="empty-state-actions">
                            <a href="shop.php" class="btn btn-primary btn-lg">
                                <i data-feather="shopping-bag"></i>
                                Browse Products
                            </a>
                            <a href="design3d.php" class="btn btn-outline btn-lg">
                                <i data-feather="edit-3"></i>
                                Design Custom
                            </a>
                        </div>
                        <div class="empty-state-suggestions">
                            <h4>Why Shop With Us?</h4>
                            <ul class="empty-state-list">
                                <li>
                                    <i data-feather="check-circle" class="empty-state-list-icon"></i>
                                    <span>Premium quality materials and printing</span>
                                </li>
                                <li>
                                    <i data-feather="truck" class="empty-state-list-icon"></i>
                                    <span>Fast shipping with tracking</span>
                                </li>
                                <li>
                                    <i data-feather="shield" class="empty-state-list-icon"></i>
                                    <span>100% satisfaction guarantee</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                <?php else: ?>
                    <?php
                        $hasOrderOverflow = count($orders) > $recentOrderLimit;
                        $primaryOrders = $hasOrderOverflow ? array_slice($orders, 0, $recentOrderLimit) : $orders;
                        $overflowOrders = $hasOrderOverflow ? array_slice($orders, $recentOrderLimit) : [];
                        $renderAccountOrderRow = static function (array $orderRow) use ($hasFeedbackTable) {
                            $customDesignId = isset($orderRow['custom_design_id']) ? (int) $orderRow['custom_design_id'] : 0;
                            $customFrontPreview = $orderRow['custom_front_preview'] ?? '';
                            $legacyDesignFile = $orderRow['design_file'] ?? '';
                            $displayImage = $orderRow['image_url'];
                            if ((int) $orderRow['inventory_id'] === 4) {
                                if (!empty($customFrontPreview)) {
                                    $displayImage = $customFrontPreview;
                                } elseif (!empty($legacyDesignFile) && $legacyDesignFile !== 'N/A') {
                                    $displayImage = $legacyDesignFile;
                                }
                            } elseif (!empty($legacyDesignFile) && $legacyDesignFile !== 'N/A') {
                                $displayImage = $legacyDesignFile;
                            }
                            $legacyInspirationLink = '';
                            if ((int) $orderRow['inventory_id'] === 4 && $customDesignId === 0 && !empty($legacyDesignFile) && $legacyDesignFile !== 'N/A') {
                                $legacyInspirationLink = 'design3d.php?inspiration_texture=' . rawurlencode($legacyDesignFile);
                            }
                            $statusClass = 'status-pill--pending';
                            $statusNormalized = strtolower((string) $orderRow['status']);
                            switch ($statusNormalized) {
                                case 'completed':
                                    $statusClass = 'status-pill--completed';
                                    break;
                                case 'shipped':
                                case 'processing':
                                    $statusClass = 'status-pill--processing';
                                    break;
                                case 'pending':
                                    $statusClass = 'status-pill--pending';
                                    break;
                                case 'cancelled':
                                    $statusClass = 'status-pill--cancelled';
                                    break;
                            }
                            $journeyDefinitions = [
                                ['key' => 'placed', 'label' => 'Placed'],
                                ['key' => 'processing', 'label' => 'Processing'],
                                ['key' => 'shipped', 'label' => 'Shipped'],
                                ['key' => 'delivered', 'label' => 'Delivered'],
                            ];
                            $statusToJourneyKey = [
                                'pending' => 'placed',
                                'processing' => 'processing',
                                'shipped' => 'shipped',
                                'completed' => 'delivered',
                                'delivered' => 'delivered',
                            ];
                            $journeyNotes = [
                                'pending' => 'We received your order and will start processing soon.',
                                'processing' => 'Printing and packing in progress.',
                                'shipped' => 'Your order is on the way.',
                                'completed' => 'Delivered - hope you love it!',
                                'delivered' => 'Delivered - hope you love it!',
                                'cancelled' => 'This order was cancelled before fulfillment.',
                            ];
                            $isCancelled = ($statusNormalized === 'cancelled');
                            if ($isCancelled) {
                                $journeyDefinitions = [
                                    ['key' => 'placed', 'label' => 'Placed'],
                                    ['key' => 'cancelled', 'label' => 'Cancelled'],
                                ];
                            }
                            $currentJourneyKey = $statusToJourneyKey[$statusNormalized] ?? 'placed';
                            if ($isCancelled) {
                                $currentJourneyKey = 'cancelled';
                            }
                            $currentJourneyIndex = 0;
                            foreach ($journeyDefinitions as $index => $step) {
                                if ($step['key'] === $currentJourneyKey) {
                                    $currentJourneyIndex = $index;
                                    break;
                                }
                            }
                            $journeyStepStates = [];
                            foreach ($journeyDefinitions as $index => $step) {
                                $state = 'upcoming';
                                if ($isCancelled && $step['key'] === 'cancelled') {
                                    $state = 'cancelled';
                                } elseif ($index < $currentJourneyIndex) {
                                    $state = 'complete';
                                } elseif ($index === $currentJourneyIndex) {
                                    $state = $isCancelled ? 'cancelled' : 'current';
                                }
                                if ($currentJourneyIndex === 0 && $index === 0 && !$isCancelled) {
                                    $state = 'current';
                                }
                                $journeyStepStates[] = $state;
                            }
                            $journeyContainerClasses = 'order-journey';
                            if ($isCancelled) {
                                $journeyContainerClasses .= ' order-journey--cancelled';
                            }
                            $orderJourneyNote = $journeyNotes[$statusNormalized] ?? '';

                            $feedbackRating = isset($orderRow['feedback_rating']) && $orderRow['feedback_rating'] !== null
                                ? (int) $orderRow['feedback_rating']
                                : null;
                            $feedbackText = trim((string) ($orderRow['feedback_text'] ?? ''));
                            $hasFeedback = $hasFeedbackTable && ($feedbackRating !== null || $feedbackText !== '');
                            $feedbackDate = !empty($orderRow['feedback_created_at'])
                                ? date('M j, Y', strtotime($orderRow['feedback_created_at']))
                                : null;
                            $canReview = in_array($statusNormalized, ['completed', 'delivered', 'shipped'], true);
                            $defaultRating = $feedbackRating ?? 5;

                            ob_start();
                            ?>
                            <article class="order-row">
                                <div class="order-preview">
                                    <img src="<?php echo htmlspecialchars($displayImage ?: 'image/placeholder.png'); ?>" alt="<?php echo htmlspecialchars($orderRow['product_name']); ?>">
                                </div>
                                <div>
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="font-semibold text-lg"><?php echo htmlspecialchars($orderRow['product_name'] ?: 'Order #' . $orderRow['order_id']); ?></p>
                                        <span class="status-pill <?php echo $statusClass; ?>"><?php echo htmlspecialchars(ucfirst($orderRow['status'])); ?></span>
                                    </div>
                                    <p class="text-sm text-gray-500">Order #<?php echo (int) $orderRow['order_id']; ?> • <?php echo date('M j, Y', strtotime($orderRow['order_date'])); ?></p>
                                    <p class="text-sm text-gray-500 mt-1">Total Paid: <?php echo formatPrice(isset($orderRow['amount']) ? (float) $orderRow['amount'] : 0.0); ?></p>
                                    <div class="<?php echo $journeyContainerClasses; ?>">
                                        <?php foreach ($journeyDefinitions as $idx => $step):
                                            $state = $journeyStepStates[$idx] ?? 'upcoming';
                                        ?>
                                            <div class="order-journey__step order-journey__step--<?php echo $state; ?>">
                                                <span class="order-journey__dot"></span>
                                                <span class="order-journey__label"><?php echo htmlspecialchars($step['label']); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if ($orderJourneyNote !== ''): ?>
                                        <p class="order-journey__note"><?php echo htmlspecialchars($orderJourneyNote); ?></p>
                                    <?php endif; ?>
                                    <?php if ($hasFeedback): ?>
                                        <div class="order-compliment">
                                            <?php if ($feedbackRating !== null): ?>
                                                <span class="mr-2 text-xs font-semibold uppercase tracking-wide text-yellow-700 bg-yellow-100 px-2 py-1 rounded-full">Rated <?php echo $feedbackRating; ?>/5</span>
                                            <?php endif; ?>
                                            <?php if ($feedbackText !== ''): ?>
                                                “<?php echo htmlspecialchars($feedbackText); ?>”
                                            <?php elseif ($feedbackRating !== null): ?>
                                                <span class="text-sm text-gray-600">Customer left a <?php echo $feedbackRating; ?>/5 rating<?php echo $feedbackDate ? ' on ' . $feedbackDate : ''; ?>.</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($canReview): ?>
                                        <details class="review-details"<?php echo $hasFeedback ? '' : ' open'; ?>>
                                            <summary><?php echo $hasFeedback ? 'Update your review' : 'Leave a review'; ?></summary>
                                            <form method="post" action="submit_feedback.php" class="review-form">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                                <input type="hidden" name="order_id" value="<?php echo (int) $orderRow['order_id']; ?>">
                                                <input type="hidden" name="redirect" value="account.php">
                                                <label for="rating-<?php echo (int) $orderRow['order_id']; ?>">Rating</label>
                                                <select id="rating-<?php echo (int) $orderRow['order_id']; ?>" name="rating" required>
                                                    <?php for ($i = 5; $i >= 1; $i--):
                                                        $isSelected = ($defaultRating === $i);
                                                    ?>
                                                        <option value="<?php echo $i; ?>"<?php echo $isSelected ? ' selected' : ''; ?>><?php echo $i; ?> <?php echo $i === 1 ? 'Star' : 'Stars'; ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                                <label for="feedback-<?php echo (int) $orderRow['order_id']; ?>">Feedback (optional)</label>
                                                <textarea id="feedback-<?php echo (int) $orderRow['order_id']; ?>" name="feedback_text" maxlength="600" placeholder="Tell us what stood out to you."><?php echo htmlspecialchars($feedbackText); ?></textarea>
                                                <p class="form-help">Highlight print quality, fabric feel, or delivery experience.</p>
                                                <button type="submit" class="btn btn-primary btn-sm"><?php echo $hasFeedback ? 'Save updates' : 'Submit review'; ?></button>
                                            </form>
                                        </details>
                                    <?php endif; ?>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <?php
                                        $buyAgainUrl = 'cart_handler.php?action=add&id=' . (int) $orderRow['inventory_id'];
                                        if ((int) $orderRow['inventory_id'] === 4 && $customDesignId > 0) {
                                            $buyAgainUrl .= '&design_id=' . $customDesignId;
                                        }
                                        $buyAgainUrl .= '&redirect=account.php&csrf_token=' . urlencode($_SESSION['csrf_token'] ?? '');
                                    ?>
                                    <a href="<?php echo htmlspecialchars($buyAgainUrl); ?>" class="btn btn-outline">Buy Again</a>
                                    <?php if ((int) $orderRow['inventory_id'] === 4): ?>
                                        <?php if ($customDesignId > 0): ?>
                                            <a href="design3d.php?design_id=<?php echo $customDesignId; ?>" class="btn btn-primary">Customize Again</a>
                                        <?php elseif ($legacyInspirationLink !== ''): ?>
                                            <a href="<?php echo htmlspecialchars($legacyInspirationLink); ?>" class="btn btn-primary">Customize Again</a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </article>
                            <?php
                            return (string) ob_get_clean();
                        };
                    ?>
                    <div class="order-list">
                        <?php foreach ($primaryOrders as $orderRow): ?>
                            <?php echo $renderAccountOrderRow($orderRow); ?>
                        <?php endforeach; ?>
                        <?php if ($hasOrderOverflow): ?>
                            <div class="order-extra" data-order-extra>
                                <?php foreach ($overflowOrders as $orderRow): ?>
                                    <?php echo $renderAccountOrderRow($orderRow); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($hasOrderOverflow): ?>
                        <button type="button" class="btn btn-outline btn-sm order-view-toggle" data-order-toggle aria-expanded="false">View all orders</button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>

        <aside class="account-sidebar">
            <section class="account-card">
                <header class="account-card-header">
                    <h2>Account Snapshot</h2>
                    <p class="text-sm text-gray-500">Keep your contact details current for smooth print updates and deliveries.</p>
                </header>
                <div class="account-card-body">
                    <dl class="profile-info">
                        <div>
                            <dt>Email</dt>
                            <dd>
                                <?php if ($profileEmailDisplay !== ''): ?>
                                    <?php echo htmlspecialchars($profileEmailDisplay); ?>
                                <?php else: ?>
                                    <span class="profile-muted">Add an email to receive confirmations.</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div>
                            <dt>Phone</dt>
                            <dd>
                                <?php if ($profilePhoneDisplay !== ''): ?>
                                    <?php echo htmlspecialchars($profilePhoneDisplay); ?>
                                <?php else: ?>
                                    <span class="profile-muted">Add a phone number for courier handoffs.</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div id="addresses-sidebar">
                            <dt>Primary Address</dt>
                            <dd>
                                <?php if ($profileAddressDisplay !== ''): ?>
                                    <?php echo nl2br(htmlspecialchars($profileAddressDisplay)); ?>
                                <?php else: ?>
                                    <span class="profile-muted">Save your shipping address during checkout.</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div>
                            <dt>Member Since</dt>
                            <dd>
                                <?php if ($memberSinceLabel): ?>
                                    <?php echo htmlspecialchars($memberSinceLabel); ?>
                                <?php else: ?>
                                    <span class="profile-muted">Welcome aboard!</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div>
                            <dt>Last Order</dt>
                            <dd>
                                <?php if ($lastOrderDateLabel): ?>
                                    <?php echo htmlspecialchars($lastOrderDateLabel); ?>
                                <?php else: ?>
                                    <span class="profile-muted">You haven’t placed an order yet.</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                    </dl>
                    <div class="profile-stats">
                        <div>
                            <div class="profile-stat-value"><?php echo number_format($totalOrdersCount); ?></div>
                            <div class="profile-stat-label">Orders placed</div>
                        </div>
                        <div>
                            <div class="profile-stat-value"><?php echo htmlspecialchars($lifetimeSpendDisplay); ?></div>
                            <div class="profile-stat-label">Lifetime spend</div>
                        </div>
                    </div>
                    <div class="profile-actions">
                        <a href="update_password.php" class="btn btn-outline btn-sm">Update password</a>
                        <a href="contact.php#support" class="btn btn-outline btn-sm">Request info change</a>
                    </div>
                </div>
            </section>
            <?php if (!empty($revisitRecommendations)): ?>
            <section class="revisit-card">
                <header>
                    <div>
                        <h2>Revisit Your Designs</h2>
                        <p>Give these crowd-pleasers a fresh print run.</p>
                    </div>
                    <a href="design3d.php" class="btn btn-outline btn-sm">Create new</a>
                </header>
                <div class="revisit-list">
                    <?php foreach ($revisitRecommendations as $revisit):
                        $frontPreview = $revisit['front_preview_url'] ?: 'image/placeholder.png';
                        $relativeSaved = formatRelativeDays($revisit['created_at'] ?? null);
                        $tags = array_slice(parseDesignTags($revisit['tags'] ?? ''), 0, 2);
                        $tagline = !empty($tags)
                            ? '#' . implode(' · #', array_map('htmlspecialchars', $tags))
                            : htmlspecialchars($relativeSaved);
                    ?>
                    <article class="revisit-entry">
                        <img src="<?php echo htmlspecialchars($frontPreview); ?>" alt="Saved design preview">
                        <div>
                            <strong><?php echo htmlspecialchars($revisit['product_name'] ?: 'Custom apparel'); ?></strong>
                            <p><?php echo htmlspecialchars($relativeSaved); ?></p>
                            <p class="tagline"><?php echo $tagline; ?></p>
                        </div>
                        <div class="flex flex-col gap-2">
                            <a class="btn btn-primary btn-sm" href="design3d.php?design_id=<?php echo (int) $revisit['design_id']; ?>">Re-edit</a>
                            <a class="btn btn-outline btn-sm" href="cart_handler.php?action=add&id=4&design_id=<?php echo (int) $revisit['design_id']; ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token'] ?? ''); ?>&redirect=account.php">Quick reorder</a>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
            <section class="account-card persona-bundle-card">
                <header class="account-card-header">
                    <h2>Stylist Capsule</h2>
                    <p class="text-sm text-gray-500">Latest persona bundle curated from your Mystic Style Quiz answers.</p>
                </header>
                <div class="account-card-body">
                    <?php if (!$styleQuizResult['hasResult']): ?>
                        <div class="persona-empty">
                            <p class="text-sm text-gray-500">Answer three lightweight prompts to unlock a personalized capsule and email follow-ups.</p>
                            <div class="persona-empty-actions">
                                <a href="stylist_inbox.php" class="btn btn-primary btn-sm">Launch Stylist Inbox</a>
                                <a href="shop.php#styleQuiz" class="btn btn-outline btn-sm">Take quiz on shop</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="persona-summary">
                            <div class="persona-summary-copy">
                                <span class="persona-chip"><?php echo htmlspecialchars($styleQuizResult['persona_label']); ?></span>
                                <p class="persona-lede"><?php echo htmlspecialchars($styleQuizResult['persona_summary']); ?></p>
                                <?php
                                $metaBits = [];
                                if (!empty($styleQuizPersonaMeta['captured_at'])) {
                                    $metaBits[] = 'Saved on ' . $styleQuizPersonaMeta['captured_at'];
                                }
                                if (!empty($styleQuizPersonaMeta['source'])) {
                                    $metaBits[] = 'Captured via ' . $styleQuizPersonaMeta['source'];
                                }
                                if (!empty($metaBits)):
                                ?>
                                    <p class="persona-meta"><?php echo htmlspecialchars(implode(' • ', $metaBits)); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="persona-summary-actions">
                                <a href="stylist_inbox.php" class="btn btn-primary btn-sm">Open Stylist Inbox</a>
                                <a href="shop.php#styleQuiz" class="btn btn-outline btn-sm">Retake quiz</a>
                            </div>
                        </div>
                        <?php if (!empty($styleQuizResult['recommendations'])): ?>
                            <div class="persona-rec-grid">
                                <?php foreach ($styleQuizResult['recommendations'] as $rec): ?>
                                    <article class="persona-rec-card">
                                        <div class="persona-rec-media">
                                            <img src="<?php echo htmlspecialchars($rec['image_url']); ?>" alt="<?php echo htmlspecialchars($rec['name']); ?> preview">
                                        </div>
                                        <div class="persona-rec-body">
                                            <h4><?php echo htmlspecialchars($rec['name']); ?></h4>
                                            <?php if (!empty($rec['reason'])): ?>
                                                <p class="persona-rec-reason"><?php echo htmlspecialchars($rec['reason']); ?></p>
                                            <?php endif; ?>
                                            <?php if ($rec['price'] !== null && is_numeric($rec['price'])): ?>
                                                <p class="persona-rec-price">₹<?php echo number_format((float) $rec['price'], 2); ?></p>
                                            <?php endif; ?>
                                            <div class="persona-rec-actions">
                                                <?php if (!empty($rec['inventory_id'])): ?>
                                                    <a href="cart_handler.php?action=add&id=<?php echo (int) $rec['inventory_id']; ?>&redirect=account.php&csrf_token=<?php echo urlencode($_SESSION['csrf_token'] ?? ''); ?>" class="btn btn-primary btn-sm">Add to cart</a>
                                                <?php else: ?>
                                                    <a href="stylist_inbox.php" class="btn btn-outline btn-sm">Schedule styling chat</a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="persona-rec-empty">We are refreshing inventory for your vibe. Check back soon or retake the quiz.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </section>
            <section class="account-card">
                <header class="account-card-header">
                    <h2>Top Compliments</h2>
                    <p class="text-sm text-gray-500">What customers loved most about your creations.</p>
                </header>
                <div class="account-card-body">
                    <?php if (empty($topCompliments)): ?>
                        <p class="text-sm text-gray-500">Design something new to start collecting compliments.</p>
                    <?php else: ?>
                        <div class="compliment-list">
                            <?php foreach ($topCompliments as $compliment):
                                $complimentRating = isset($compliment['feedback_rating']) && $compliment['feedback_rating'] !== null
                                    ? (int) $compliment['feedback_rating']
                                    : null;
                                $complimentText = trim((string) ($compliment['feedback_text'] ?? ''));
                                $complimentDate = !empty($compliment['feedback_created_at'])
                                    ? strtotime($compliment['feedback_created_at'])
                                    : (!empty($compliment['order_date']) ? strtotime($compliment['order_date']) : null);
                                $complimentDateLabel = $complimentDate ? date('M j', $complimentDate) : 'Recent';
                            ?>
                                <div class="compliment-item">
                                    <strong>
                                        Order #<?php echo (int) $compliment['order_id']; ?>
                                        • <?php echo htmlspecialchars($complimentDateLabel); ?>
                                        <?php if ($complimentRating !== null): ?>
                                            • <?php echo $complimentRating; ?>/5
                                        <?php endif; ?>
                                    </strong>
                                    <?php if ($complimentText !== ''): ?>
                                        <span>“<?php echo htmlspecialchars($complimentText); ?>”</span>
                                    <?php elseif ($complimentRating !== null): ?>
                                        <span class="text-sm text-gray-600">Customer left a <?php echo $complimentRating; ?>/5 rating.</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="nudge-card">
                <h3>Email Nudges</h3>
                <p class="text-sm nudge-lead">We’ll keep an eye on your saved designs and nudge you when it’s time to reprint.</p>
                <div class="nudge-list">
                    <span>• <?php echo $customDesignCount; ?> designs saved</span>
                    <span>• <?php echo count($dormantDesigns); ?> ready for a refresh</span>
                    <?php if (!empty($abandonedDesigns)): ?>
                        <span>• <?php echo count($abandonedDesigns); ?> drafts need finishing touches</span>
                    <?php endif; ?>
                </div>
                <?php if ($nudgedRecently && !empty($dormantDesigns)): ?>
                    <p class="text-xs text-indigo-800 mt-2">We just sent a reminder email with these designs highlighted.</p>
                <?php endif; ?>
                <div class="nudge-actions">
                    <a href="design3d.php" class="btn btn-primary btn-sm">Start a new design</a>
                    <?php if (!empty($dormantDesigns)): ?>
                        <a href="design3d.php?design_id=<?php echo (int) $dormantDesigns[0]['design_id']; ?>" class="btn btn-outline btn-sm">Refresh oldest</a>
                    <?php endif; ?>
                </div>
            </section>
        </aside>
    </div>

    <section class="account-card mt-10" id="design-spotlight">
        <header class="account-card-header">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div>
                    <h2>Design Spotlight Submission</h2>
                    <p class="text-sm text-gray-500">Share your proudest custom piece and we may feature it on the storefront.</p>
                </div>
                <a href="index.php#design-spotlight" class="btn btn-outline btn-sm">See current spotlight</a>
            </div>
        </header>
        <div class="account-card-body">
            <div class="spotlight-submit-grid">
                <div class="spotlight-intro">
                    <h3>Send your story</h3>
                    <p>Pick a finished design, add the story behind it, and opt-in for gallery features. Our team reviews every submission.</p>
                    <form method="post" action="submit_design_spotlight.php" class="spotlight-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                        <input type="hidden" name="redirect" value="account.php#design-spotlight">
                        <?php if (!empty($spotlightDesignPicker)): ?>
                            <span class="spotlight-picker-heading">Link a saved design</span>
                            <div class="spotlight-design-picker">
                                <label class="spotlight-design-option">
                                    <input type="radio" name="design_id" value="" checked>
                                    <div class="spotlight-design-tile">
                                        <div class="spotlight-design-thumb spotlight-design-thumb--empty"><span>None</span></div>
                                        <div class="spotlight-design-meta">
                                            <strong>No linked design</strong>
                                            <span>Tell your story and include a link.</span>
                                        </div>
                                    </div>
                                </label>
                                <?php foreach ($spotlightDesignPicker as $savedDesign):
                                    $designNumber = (int) ($savedDesign['design_id'] ?? 0);
                                    if ($designNumber <= 0) { continue; }
                                    $designName = $savedDesign['product_name'] ?? 'Custom apparel design';
                                    $designPreview = !empty($savedDesign['front_preview_url']) ? $savedDesign['front_preview_url'] : 'image/placeholder.png';
                                    $relativeSaved = formatRelativeDays($savedDesign['created_at'] ?? null);
                                    $designBaseColor = isset($savedDesign['base_color']) ? trim((string) $savedDesign['base_color']) : '';
                                ?>
                                <label class="spotlight-design-option">
                                    <input type="radio" name="design_id" value="<?php echo $designNumber; ?>">
                                    <div class="spotlight-design-tile">
                                        <img src="<?php echo htmlspecialchars($designPreview); ?>" alt="Preview of design #<?php echo $designNumber; ?>" class="spotlight-design-thumb">
                                        <div class="spotlight-design-meta">
                                            <strong><?php echo htmlspecialchars($designName); ?></strong>
                                            <span>Design #<?php echo $designNumber; ?> • Saved <?php echo htmlspecialchars($relativeSaved); ?></span>
                                            <?php if ($designBaseColor !== ''):
                                                $colorLabel = strtoupper(ltrim($designBaseColor, '#'));
                                            ?>
                                                <div class="spotlight-design-meta-extra">
                                                    <span>Base color</span>
                                                    <span class="spotlight-design-meta-color-swatch" style="background-color: <?php echo htmlspecialchars($designBaseColor); ?>;"></span>
                                                    <span><?php echo htmlspecialchars($colorLabel); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="form-footnote">Preview pulls from your saved design gallery. Leave unlinked if this is a fresh concept. Showing up to six recent designs.</p>
                        <?php else: ?>
                            <p class="form-footnote">Save a custom design to see quick-select options here.</p>
                        <?php endif; ?>
                        <div class="form-row">
                            <div>
                                <label for="spotlight-title">Spotlight title</label>
                                <input type="text" id="spotlight-title" name="title" maxlength="120" placeholder="Eg. Lunar bloom festival jacket" required>
                                <p class="form-footnote">Keep it specific so fans instantly understand the mood.</p>
                            </div>
                        </div>
                        <label for="spotlight-story">Your design story</label>
                        <textarea id="spotlight-story" name="story" maxlength="600" minlength="60" placeholder="Describe the inspiration, fabrics, or moment that gave this design its magic." required></textarea>
                        <label for="spotlight-quote">Homepage quote</label>
                        <textarea id="spotlight-quote" name="homepage_quote" maxlength="160" minlength="20" placeholder="Give us a punchy, quotable line we can feature with your design." required></textarea>
                        <p class="form-footnote">20-160 characters. Think of it as the headline your design deserves.</p>
                        <div class="form-row">
                            <div>
                                <label for="spotlight-link">Inspiration link</label>
                                <input type="url" id="spotlight-link" name="inspiration_url" maxlength="255" placeholder="https://">
                                <p class="form-footnote">Optional moodboard, lookbook, or social post.</p>
                            </div>
                            <div>
                                <label for="spotlight-instagram">Instagram handle</label>
                                <input type="text" id="spotlight-instagram" name="instagram_handle" maxlength="80" placeholder="@MysticMaker">
                                <p class="form-footnote">We’ll credit you if we share it publicly.</p>
                            </div>
                        </div>
                        <label class="checkbox-row">
                            <input type="checkbox" name="share_gallery" value="1">
                            <span>I agree to let Mystic display this design in marketing galleries.</span>
                        </label>
                        <button type="submit" class="btn btn-primary mt-4">Submit to Design Spotlight</button>
                    </form>
                </div>
                <div class="spotlight-history">
                    <h3>Recent submissions</h3>
                    <p>We review within two business days.</p>
                    <?php if (empty($spotlightSubmissions)): ?>
                        <div class="spotlight-history-empty">No spotlight stories submitted yet. Share your first design to kick things off.</div>
                    <?php else: ?>
                        <div class="spotlight-history-list">
                            <?php foreach ($spotlightSubmissions as $submission):
                                $submissionDesignId = isset($submission['design_id']) ? (int) $submission['design_id'] : 0;
                                $linkedDesign = $submissionDesignId > 0 && isset($customDesignLookup[$submissionDesignId]) ? $customDesignLookup[$submissionDesignId] : null;
                                $previewImage = $linkedDesign['front_preview_url'] ?? ($submission['design_preview'] ?? '');
                                if (!$previewImage) {
                                    $previewImage = 'image/placeholder.png';
                                }
                                $submissionStatus = strtolower((string) ($submission['status'] ?? 'pending'));
                                if (!in_array($submissionStatus, ['pending', 'approved', 'rejected'], true)) {
                                    $submissionStatus = 'pending';
                                }
                                $storyFull = trim((string) ($submission['story'] ?? ''));
                                $storyBlurb = $storyFull;
                                $storyLength = function_exists('mb_strlen') ? mb_strlen($storyFull) : strlen($storyFull);
                                if ($storyLength > 160) {
                                    $storyBlurb = function_exists('mb_substr') ? mb_substr($storyFull, 0, 160) : substr($storyFull, 0, 160);
                                    $storyBlurb .= '…';
                                }
                                $quoteCopy = isset($submission['homepage_quote']) ? trim((string) $submission['homepage_quote']) : '';
                                $submittedLabel = !empty($submission['created_at']) ? formatRelativeDays($submission['created_at']) : 'Just now';
                            ?>
                                <article class="spotlight-history-card">
                                    <img src="<?php echo htmlspecialchars($previewImage); ?>" alt="Spotlight design preview">
                                    <div>
                                        <strong>
                                            <?php echo htmlspecialchars($submission['title'] ?? 'Design Spotlight'); ?>
                                            <span class="spotlight-status-pill <?php echo $submissionStatus; ?>"><?php echo ucfirst($submissionStatus); ?></span>
                                        </strong>
                                        <span><?php echo htmlspecialchars($storyBlurb); ?></span>
                                        <?php if ($quoteCopy !== ''): ?>
                                            <span class="spotlight-history-quote">&ldquo;<?php echo htmlspecialchars($quoteCopy); ?>&rdquo;</span>
                                        <?php endif; ?>
                                        <span>Submitted <?php echo htmlspecialchars($submittedLabel); ?></span>
                                        <?php if (!empty($submission['inspiration_url'])): ?>
                                            <span>Link: <a href="<?php echo htmlspecialchars($submission['inspiration_url']); ?>" target="_blank" rel="noopener">View inspiration</a></span>
                                        <?php endif; ?>
                                        <?php if (!empty($submission['instagram_handle'])): ?>
                                            <span>Instagram: <?php echo htmlspecialchars($submission['instagram_handle']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($submission['share_gallery'])): ?>
                                            <span>Gallery opt-in confirmed.</span>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="account-card mt-10" id="saved-designs">
        <header class="account-card-header">
            <div class="flex items-center justify-between">
                <div>
                    <h2>Saved Custom Designs</h2>
                    <p class="text-sm text-gray-500">Tag, reorder, or re-edit your best work.</p>
                </div>
                <a href="design3d.php" class="btn btn-outline">Create New</a>
            </div>
        </header>
        <div class="account-card-body">
            <?php if (empty($customDesigns)): ?>
                <p class="text-gray-600">You have not saved any custom designs yet. Start a new one to see it appear here.</p>
            <?php else: ?>
                <div class="design-grid">
                    <?php foreach ($customDesigns as $design):
                        $tags = parseDesignTags($design['tags'] ?? '') ?? [];
                        $frontPreview = $design['front_preview_url'] ?: 'image/placeholder.png';
                        $backPreview = $design['back_preview_url'] ?: $frontPreview;
                    ?>
                        <article class="design-card">
                            <div class="design-card-hero">
                                <div class="design-preview">
                                    <img src="<?php echo htmlspecialchars($frontPreview); ?>" alt="Front preview of design #<?php echo (int) $design['design_id']; ?>">
                                </div>
                                <div class="design-preview">
                                    <img src="<?php echo htmlspecialchars($backPreview); ?>" alt="Back preview of design #<?php echo (int) $design['design_id']; ?>">
                                </div>
                            </div>
                            <div>
                                <h3 class="font-semibold text-lg truncate"><?php echo htmlspecialchars($design['product_name'] ?: 'Custom Apparel'); ?></h3>
                                <p class="design-meta">Design #<?php echo (int) $design['design_id']; ?> • Saved on <?php echo date('M j, Y', strtotime($design['created_at'] ?? 'now')); ?> • Base price <?php echo formatPrice(isset($design['price']) ? (float) $design['price'] : null); ?></p>
                            </div>
                            <?php if (!empty($tags)): ?>
                                <div class="design-tags">
                                    <?php foreach ($tags as $tag): ?>
                                        <span class="design-tag">#<?php echo htmlspecialchars($tag); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($dormantDesignIds) && in_array((int) $design['design_id'], $dormantDesignIds, true)): ?>
                                <div class="dormant-banner">
                                    <span>It’s been a while since you reprinted this favorite.</span>
                                    <a href="design3d.php?design_id=<?php echo (int) $design['design_id']; ?>" class="btn btn-sm btn-outline">Refresh now</a>
                                </div>
                            <?php endif; ?>
                            <div class="design-actions">
                                <a href="design3d.php?design_id=<?php echo (int) $design['design_id']; ?>" class="btn btn-primary">Re-edit</a>
                                <a href="cart_handler.php?action=add&id=4&design_id=<?php echo (int) $design['design_id']; ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token'] ?? ''); ?>&redirect=account.php" class="btn btn-outline">Quick Reorder</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<noscript>
    <style>
        .order-extra { display: flex !important; flex-direction: column !important; gap: 1.25rem !important; }
        [data-order-toggle] { display: none !important; }
    </style>
</noscript>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Show toast notification for account flash messages
    <?php if (!empty($accountFlash)): ?>
        <?php if ($accountFlashType === 'success'): ?>
            if (typeof toastSuccess === 'function') {
                toastSuccess(<?php echo json_encode($accountFlash); ?>);
            }
        <?php elseif ($accountFlashType === 'error'): ?>
            if (typeof toastError === 'function') {
                toastError(<?php echo json_encode($accountFlash); ?>);
            }
        <?php else: ?>
            if (typeof toastInfo === 'function') {
                toastInfo(<?php echo json_encode($accountFlash); ?>);
            }
        <?php endif; ?>
    <?php endif; ?>

    const toggle = document.querySelector('[data-order-toggle]');
    const extraContainer = document.querySelector('[data-order-extra]');
    if (!toggle || !extraContainer) {
        return;
    }

    extraContainer.classList.remove('is-expanded');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.textContent = 'View all orders';

    toggle.addEventListener('click', function () {
        const currentlyExpanded = toggle.getAttribute('aria-expanded') === 'true';
        const nextExpanded = !currentlyExpanded;
        toggle.setAttribute('aria-expanded', nextExpanded ? 'true' : 'false');
        toggle.textContent = nextExpanded ? 'Show fewer orders' : 'View all orders';
        extraContainer.classList.toggle('is-expanded', nextExpanded);
    });
});
</script>

<?php include 'footer.php'; ?>