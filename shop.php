<?php
include 'header.php';
include 'db_connection.php';
require_once __DIR__ . '/session_helpers.php';
require_once __DIR__ . '/core/style_quiz_helpers.php';
require_once __DIR__ . '/core/social_proof.php';
require_once __DIR__ . '/core/drop_promotions.php';

$markdownContext = drop_promotion_get_markdown_context();
$markdownActive = !empty($markdownContext['active']);
$markdownOriginals = $markdownContext['original_prices'] ?? [];

if (!function_exists('build_stock_badge')) {
    function build_stock_badge(int $stockQty): array
    {
        if ($stockQty <= 0) {
            return ['label' => 'Out of stock', 'theme' => 'product-alert-danger'];
        }
        if ($stockQty <= 3) {
            return ['label' => 'Selling fast – only ' . max(0, $stockQty) . ' left!', 'theme' => 'product-alert-urgent'];
        }
        if ($stockQty <= 8) {
            return ['label' => 'Almost gone – reserve yours soon', 'theme' => 'product-alert-warm'];
        }
        return ['label' => 'In stock and ready to ship', 'theme' => 'product-alert-default'];
    }
}

if (!function_exists('compute_delivery_estimate')) {
    function compute_delivery_estimate(?array $shippingInfo, bool $isCustomProduct): array
    {
        $timezone = new DateTimeZone('Asia/Kolkata');
        $today = new DateTime('now', $timezone);
        $minDays = $isCustomProduct ? 5 : 2;
        $maxDays = $isCustomProduct ? 8 : 5;

        if (!empty($shippingInfo['address'])) {
            $minDays = max(1, $minDays - 1);
            $maxDays = max($minDays + 1, $maxDays - 1);
        }

        $shipDate = clone $today;
        $shipDate->modify('+1 day');

        $arrivalStart = clone $today;
        $arrivalStart->modify('+' . $minDays . ' days');
        $arrivalEnd = clone $today;
        $arrivalEnd->modify('+' . $maxDays . ' days');

        $label = 'Est. delivery ' . $arrivalStart->format('M j');
        if ($arrivalEnd->format('M j') !== $arrivalStart->format('M j')) {
            $label .= ' – ' . $arrivalEnd->format('M j');
        }

        $meta = 'Ships ' . $shipDate->format('l');
        if (!empty($shippingInfo['address'])) {
            $destination = strtok((string) $shippingInfo['address'], "\n");
            if (strlen($destination) > 36) {
                $destination = substr($destination, 0, 36) . '...';
            }
            $meta .= ' to ' . $destination;
        }

        return [
            'label' => $label,
            'meta' => $meta,
        ];
    }
}

if (!function_exists('compute_cart_snapshot')) {
    function compute_cart_snapshot(mysqli $conn): array
    {
        global $markdownActive, $markdownOriginals;
        $summary = [
            'items' => 0,
            'subtotal' => 0.0,
            'savings' => 0.0,
            'original_subtotal' => 0.0,
            'lines' => [],
        ];

        if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            return $summary;
        }

        $productStmt = $conn->prepare('SELECT inventory_id, product_name, price, image_url, is_clearance FROM inventory WHERE inventory_id = ? AND (is_archived = 0 OR is_archived IS NULL)');
        $customPreviewStmt = $conn->prepare('SELECT front_preview_url FROM custom_designs WHERE design_id = ? LIMIT 1');

        foreach ($_SESSION['cart'] as $inventoryId => $quantity) {
            $inventoryId = (int) $inventoryId;
            $quantity = (int) $quantity;
            if ($quantity <= 0 || !$productStmt) {
                continue;
            }

            $productStmt->bind_param('i', $inventoryId);
            if (!$productStmt->execute()) {
                continue;
            }
            $productResult = $productStmt->get_result();
            $productRow = $productResult->fetch_assoc();
            $productResult->free();
            if (!$productRow) {
                continue;
            }

            $unitPrice = (float) ($productRow['price'] ?? 0.0);
            $originalUnit = $markdownActive && isset($markdownOriginals[$inventoryId])
                ? (float) $markdownOriginals[$inventoryId]
                : null;
            $thumbnail = $productRow['image_url'] ?? 'image/placeholder.png';
            $lineName = $productRow['product_name'] ?? ('Product #' . $inventoryId);
            $isClearance = !empty($productRow['is_clearance']);
            $meta = $isClearance ? 'Clearance drop pricing' : '';

            if ($inventoryId === 4) {
                $customIds = get_custom_design_ids();
                $quantity = count($customIds) ?: $quantity;
                if (!empty($customIds) && $customPreviewStmt) {
                    $firstCustomId = (int) $customIds[0];
                    $customPreviewStmt->bind_param('i', $firstCustomId);
                    if ($customPreviewStmt->execute()) {
                        $previewResult = $customPreviewStmt->get_result();
                        $previewRow = $previewResult->fetch_assoc();
                        if (!empty($previewRow['front_preview_url'])) {
                            $thumbnail = $previewRow['front_preview_url'];
                        }
                        $previewResult->free();
                    }
                }
                $meta = trim(($meta !== '' ? $meta . ' • ' : '') . 'Saved designs ready to personalize');
            }

            $lineSubtotal = $unitPrice * $quantity;
            $lineSavings = 0.0;
            if ($originalUnit !== null && $originalUnit > $unitPrice) {
                $lineSavings = ($originalUnit - $unitPrice) * $quantity;
            } else {
                $originalUnit = null;
            }
            $summary['items'] += $quantity;
            $summary['subtotal'] += $lineSubtotal;
            $summary['savings'] += $lineSavings;
            $summary['lines'][] = [
                'id' => $inventoryId,
                'name' => $lineName,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $lineSubtotal,
                'thumbnail' => $thumbnail,
                'meta' => $meta,
                'is_clearance' => $isClearance,
                'original_price' => $originalUnit,
                'savings' => $lineSavings,
            ];
        }

        if ($productStmt) {
            $productStmt->close();
        }
        if ($customPreviewStmt) {
            $customPreviewStmt->close();
        }

        $summary['original_subtotal'] = $summary['subtotal'] + $summary['savings'];

        return $summary;
    }
}

$shippingInfo = $_SESSION['shipping_info'] ?? null;
$socialProofEntries = get_recent_compliments_for_social_proof($conn, 8);
$productResult = $conn->query('SELECT * FROM inventory WHERE stock_qty > 0 AND (is_archived = 0 OR is_archived IS NULL) ORDER BY inventory_id DESC');
$cartSnapshot = compute_cart_snapshot($conn);
$inventoryQuizMeta = loadInventoryQuizMetadata($conn);

$personaProfile = [
    'label' => '',
    'style' => '',
    'palette' => '',
    'goal' => '',
];

if (!empty($_SESSION['customer_id'])) {
    $personaStmt = $conn->prepare('SELECT style_choice, palette_choice, goal_choice, persona_label FROM style_quiz_results WHERE customer_id = ? LIMIT 1');
    if ($personaStmt) {
        $personaStmt->bind_param('i', $_SESSION['customer_id']);
        if ($personaStmt->execute()) {
            $personaResult = $personaStmt->get_result();
            $personaRow = $personaResult ? $personaResult->fetch_assoc() : null;
            if ($personaRow) {
                $personaProfile = [
                    'label' => $personaRow['persona_label'] ?? '',
                    'style' => strtolower($personaRow['style_choice'] ?? ''),
                    'palette' => strtolower($personaRow['palette_choice'] ?? ''),
                    'goal' => strtolower($personaRow['goal_choice'] ?? ''),
                ];
            }
            if ($personaResult) {
                $personaResult->free();
            }
        }
        $personaStmt->close();
    }
}

if (empty($personaProfile['label']) && isset($_SESSION['style_quiz_last_result']) && is_array($_SESSION['style_quiz_last_result'])) {
    $sessionPersona = $_SESSION['style_quiz_last_result'];
    $personaProfile = [
        'label' => $sessionPersona['persona_label'] ?? '',
        'style' => strtolower($sessionPersona['style'] ?? ''),
        'palette' => strtolower($sessionPersona['palette'] ?? ''),
        'goal' => strtolower($sessionPersona['goal'] ?? ''),
    ];
}
?>

<style>
    .product-card-content {
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
    }
    .product-alert {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.85rem;
        padding: 0.35rem 0.55rem;
        border-radius: 999px;
        font-weight: 500;
    }
        .product-card-price {
            display: flex;
            align-items: baseline;
            gap: 0.45rem;
        }
        .product-price-original {
            text-decoration: line-through;
            color: #94a3b8;
            font-size: 0.9rem;
        }
        .product-price-current {
            font-size: 1.1rem;
            font-weight: 700;
            color: #0f172a;
        }
        .product-price-savings {
            font-size: 0.75rem;
            font-weight: 600;
            color: #047857;
        }
    .product-alert::before {
        content: '';
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: currentColor;
        opacity: 0.75;
    }
    .product-alert-urgent {
        background: rgba(239, 68, 68, 0.08);
        color: #b91c1c;
    }
    .product-alert-warm {
        background: rgba(249, 115, 22, 0.12);
        color: #c2410c;
    }
    .product-alert-default {
        background: rgba(16, 185, 129, 0.12);
        color: #047857;
    }
    .product-alert-danger {
        background: rgba(107, 114, 128, 0.15);
        color: #374151;
    }
    .product-delivery {
        display: flex;
        flex-direction: column;
        font-size: 0.85rem;
        color: #4b5563;
        gap: 0.15rem;
    }
    .product-delivery span {
        font-size: 0.78rem;
        color: #6b7280;
    }
    .mini-cart {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        width: 320px;
        z-index: 50;
        box-shadow: 0 20px 45px rgba(15, 23, 42, 0.2);
        border-radius: 1rem;
        overflow: hidden;
        background: #ffffff;
        border: 1px solid rgba(99, 102, 241, 0.15);
    }
    .mini-cart.collapsed .mini-cart-panel {
        max-height: 0;
        opacity: 0;
        pointer-events: none;
    }
    .mini-cart.collapsed .mini-cart-toggle::after {
        transform: rotate(0deg);
    }
    .mini-cart-toggle {
        width: 100%;
        padding: 0.85rem 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: #fff;
        font-weight: 600;
        border: none;
        cursor: pointer;
        position: relative;
    }
    .mini-cart-toggle::after {
        content: '\25bc';
        transition: transform 0.2s ease;
    }
    .mini-cart-panel {
        background: #ffffff;
        transition: max-height 0.3s ease, opacity 0.25s ease;
        max-height: 440px;
        overflow-y: auto;
        opacity: 1;
    }
    .mini-cart-body {
        padding: 0.85rem 1rem 1rem 1rem;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    .mini-cart-empty {
        text-align: center;
        font-size: 0.9rem;
        color: #6b7280;
        padding: 1.25rem 0.75rem;
    }
    .mini-cart-line {
        display: grid;
        grid-template-columns: 56px 1fr;
        gap: 0.75rem;
        align-items: center;
    }
    .mini-cart-line.is-freebie {
        border: 1px dashed rgba(99, 102, 241, 0.35);
        border-radius: 0.9rem;
        padding: 0.45rem 0.65rem;
        background: rgba(99, 102, 241, 0.08);
    }
        .mini-price-original {
            text-decoration: line-through;
            color: #94a3b8;
            margin-right: 0.35rem;
            display: inline-block;
        }
        .mini-price-current {
            font-weight: 600;
            color: #0f172a;
        }
        .mini-price-savings {
            color: #047857;
            font-size: 0.75rem;
            font-weight: 600;
        }
    .mini-cart-free-label {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.15rem 0.55rem;
        border-radius: 999px;
        background: rgba(14, 159, 110, 0.14);
        color: #047857;
        font-size: 0.72rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .mini-cart-value-note {
        margin-top: 0.25rem;
        font-size: 0.78rem;
        color: #4338ca;
        font-weight: 600;
    }
    .mini-cart-clearance {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.2rem 0.65rem;
        border-radius: 999px;
        background: rgba(220, 38, 38, 0.12);
        color: #b91c1c;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }
    .mini-cart-meta {
        margin-top: 0.25rem;
        font-size: 0.75rem;
        color: #6b7280;
    }
    .mini-cart-thumb {
        width: 56px;
        height: 56px;
        border-radius: 0.75rem;
        object-fit: cover;
        border: 1px solid #e5e7eb;
        background: #f3f4f6;
    }
    .mini-cart-line h4 {
        font-size: 0.9rem;
        margin: 0;
        color: #111827;
        font-weight: 600;
    }
    .mini-cart-line p {
        margin: 0.1rem 0 0;
        font-size: 0.8rem;
        color: #6b7280;
    }
    .mini-cart-footer {
        padding: 0.75rem 1rem 1rem;
        border-top: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.75rem;
    }
    .mini-cart-footer .btn {
        flex: 1;
    }
    @media (max-width: 768px) {
        .mini-cart {
            right: 1rem;
            left: 1rem;
            width: auto;
            bottom: 1rem;
        }
    }
    .style-quiz-card {
        margin: 3rem 0;
        padding: 2.5rem;
        border-radius: 1.35rem;
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.12), rgba(79, 70, 229, 0.08));
        border: 1px solid rgba(99, 102, 241, 0.18);
        box-shadow: 0 25px 45px rgba(15, 23, 42, 0.08);
        position: relative;
        overflow: hidden;
    }
    .style-quiz-card::after {
        content: '';
        position: absolute;
        right: -120px;
        bottom: -120px;
        width: 260px;
        height: 260px;
        background: radial-gradient(circle at center, rgba(99, 102, 241, 0.25), rgba(99, 102, 241, 0));
        pointer-events: none;
    }
    .style-quiz-header {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        max-width: 540px;
    }
    .style-quiz-steps {
        margin-top: 2.25rem;
        max-width: 640px;
        display: flex;
        flex-direction: column;
        gap: 1.75rem;
    }
    .quiz-step {
        display: none;
        background: rgba(255, 255, 255, 0.92);
        border-radius: 1.1rem;
        border: 1px solid rgba(148, 163, 184, 0.28);
        padding: 1.75rem;
        box-shadow: 0 20px 45px rgba(15, 23, 42, 0.05);
    }
    .quiz-step.active {
        display: block;
    }
    .quiz-step h3 {
        margin: 0 0 1rem 0;
        font-size: 1.35rem;
        color: #1e293b;
    }
    .quiz-options {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 0.9rem;
    }
    .quiz-option {
        border: 1px solid rgba(129, 140, 248, 0.35);
        border-radius: 0.9rem;
        padding: 1rem;
        background: #ffffff;
        font-size: 0.95rem;
        font-weight: 600;
        color: #312e81;
        cursor: pointer;
        transition: transform 0.15s ease, box-shadow 0.2s ease, border 0.2s ease;
        text-align: left;
    }
    .quiz-option:hover,
    .quiz-option:focus {
        transform: translateY(-2px);
        box-shadow: 0 15px 30px rgba(79, 70, 229, 0.15);
    }
    .quiz-option.active {
        border-color: #6366f1;
        background: rgba(129, 140, 248, 0.12);
        color: #1e1b4b;
        box-shadow: 0 20px 40px rgba(79, 70, 229, 0.15);
    }
    .quiz-progress {
        height: 6px;
        width: 100%;
        border-radius: 999px;
        background: rgba(129, 140, 248, 0.25);
        overflow: hidden;
        margin-top: 2rem;
    }
    .quiz-progress-fill {
        height: 100%;
        width: 33%;
        background: linear-gradient(90deg, #6366f1, #8b5cf6);
        transition: width 0.3s ease;
    }
    .quiz-result {
        margin-top: 2rem;
        display: none;
        background: #ffffff;
        border-radius: 1.1rem;
        border: 1px solid rgba(20, 184, 166, 0.2);
        padding: 1.75rem;
        box-shadow: 0 20px 40px rgba(14, 116, 144, 0.15);
    }
    .quiz-result.visible {
        display: block;
    }
    .quiz-recommendations {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-top: 1.5rem;
    }
    .quiz-rec-card {
        border: 1px solid rgba(20, 184, 166, 0.25);
        border-radius: 0.85rem;
        padding: 1rem;
        background: rgba(236, 253, 245, 0.95);
        display: flex;
        flex-direction: column;
        gap: 0.6rem;
    }
    .quiz-rec-card img {
        width: 100%;
        border-radius: 0.75rem;
        object-fit: cover;
        border: 1px solid rgba(20, 184, 166, 0.25);
    }
    .quiz-rec-card h4 {
        margin: 0;
        font-size: 1rem;
        color: #065f46;
    }
    .quiz-rec-card p {
        font-size: 0.85rem;
        color: #0f766e;
        margin: 0;
    }
    .quiz-rec-card .btn {
        align-self: flex-start;
    }
    .quiz-retake {
        margin-top: 1.4rem;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.9rem;
        color: #4338ca;
        cursor: pointer;
        font-weight: 600;
    }
    .persona-banner {
        margin: 2rem 0 1rem;
        padding: 1.25rem 1.5rem;
        border-radius: 1rem;
        background: linear-gradient(135deg, rgba(79, 70, 229, 0.12), rgba(6, 182, 212, 0.12));
        border: 1px solid rgba(79, 70, 229, 0.18);
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 1rem;
        justify-content: space-between;
    }
    .persona-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.6rem 1rem;
        border-radius: 999px;
        background: #1f2937;
        color: #f8fafc;
        font-weight: 600;
        letter-spacing: 0.01em;
    }
    .persona-chip i {
        width: 18px;
        height: 18px;
    }
    .persona-actions {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    .persona-actions button {
        border: 1px solid rgba(79, 70, 229, 0.35);
        padding: 0.45rem 0.9rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.85);
        font-size: 0.85rem;
        font-weight: 600;
        color: #312e81;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .persona-actions button.is-active,
    .persona-actions button:hover {
        background: #4c1d95;
        color: #fff;
        border-color: rgba(76, 29, 149, 0.9);
    }
    .product-card.persona-match,
    .product-card.recommended {
        border: 2px solid rgba(6, 182, 212, 0.55);
        box-shadow: 0 25px 40px rgba(6, 182, 212, 0.18);
        transform: translateY(-6px);
    }
    .product-card.is-clearance {
        border: 2px dashed rgba(220, 38, 38, 0.45);
        box-shadow: 0 20px 40px rgba(220, 38, 38, 0.18);
    }
    .product-card.is-muted {
        opacity: 0.35;
        filter: grayscale(0.5);
        pointer-events: none;
        transition: opacity 0.25s ease;
    }
    .product-clearance-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.35rem 0.6rem;
        border-radius: 999px;
        background: rgba(220, 38, 38, 0.12);
        color: #b91c1c;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }
    .product-card .persona-callout {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.75rem;
        padding: 0.3rem 0.6rem;
        border-radius: 999px;
        background: rgba(6, 182, 212, 0.12);
        color: #0e7490;
        font-weight: 600;
    }
    .product-card .persona-callout i {
        width: 14px;
        height: 14px;
    }
    @media (max-width: 768px) {
        .style-quiz-card {
            padding: 1.8rem;
        }
        .persona-banner {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<div class="page-header">
    <div class="container">
        <h1>Shop</h1>
        <p>Discover our collection of high-quality custom apparel</p>
    </div>
</div>

<?php if (!empty($personaProfile['label'])): ?>
<div class="container">
    <div class="persona-banner" data-persona-bar>
        <span class="persona-chip"><i data-feather="star"></i>You’re a <?php echo htmlspecialchars($personaProfile['label']); ?></span>
        <div class="flex flex-col gap-1 text-sm text-slate-700">
            <span>We’re spotlighting pieces that align with your <?php echo htmlspecialchars(ucfirst($personaProfile['style'])); ?> vibe, <?php echo htmlspecialchars(ucfirst($personaProfile['palette'])); ?> palette, and <?php echo htmlspecialchars(ucfirst($personaProfile['goal'])); ?> goals.</span>
            <a href="shop.php#styleQuiz" class="text-indigo-600 font-semibold inline-flex items-center gap-1">↻ Tune persona</a>
        </div>
        <div class="persona-actions" data-persona-actions>
            <button type="button" class="is-active" data-persona-filter="all">Show all</button>
            <button type="button" data-persona-filter="focus">Only my vibe</button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($socialProofEntries)): ?>
<section class="social-proof-strip">
    <div class="container">
            <div class="social-proof-heading">
                <span class="social-proof-heading__title">Recent compliments</span>
                <span class="social-proof-heading__meta">Powered by 4★+ customer feedback</span>
            </div>
        <div class="social-proof-scroll">
            <?php foreach ($socialProofEntries as $entry):
                $quote = $entry['quote'];
                if (strlen($quote) > 140) {
                    $quote = substr($quote, 0, 137) . '...';
                }
            ?>
                <figure class="social-proof-pill">
                    <figcaption>
                        <span class="social-proof-pill__meta">★ <?php echo (int)$entry['rating']; ?>/5 · <?php echo htmlspecialchars($entry['product']); ?> · <?php echo htmlspecialchars($entry['date']); ?></span>
                        <p class="social-proof-pill__quote">“<?php echo htmlspecialchars($quote); ?>”</p>
                        <span class="social-proof-pill__customer">— <?php echo htmlspecialchars($entry['customer']); ?></span>
                    </figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="container">
    <div class="style-quiz-card" id="styleQuiz" aria-live="polite">
        <div class="style-quiz-header">
            <span class="inline-flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-indigo-900">Style Quiz</span>
            <h2 class="text-3xl font-bold text-slate-900">Find your perfect Mystic kit in under 60 seconds.</h2>
            <p class="text-slate-700 text-base">Answer three quick questions and we’ll curate a bundle that matches your vibe. Saving your picks helps us tailor future drops in your account.</p>
        </div>

        <div class="style-quiz-steps" data-quiz-body>
            <div class="quiz-step active" data-quiz-step="1">
                <h3>What vibe are you channeling?</h3>
                <div class="quiz-options">
                    <button type="button" class="quiz-option" data-question="style" data-answer="street">Street-ready layers</button>
                    <button type="button" class="quiz-option" data-question="style" data-answer="minimal">Clean and minimal</button>
                    <button type="button" class="quiz-option" data-question="style" data-answer="bold">Bold statement pieces</button>
                </div>
            </div>

            <div class="quiz-step" data-quiz-step="2">
                <h3>Pick a palette you love.</h3>
                <div class="quiz-options">
                    <button type="button" class="quiz-option" data-question="palette" data-answer="monochrome">Black, white, and grayscale</button>
                    <button type="button" class="quiz-option" data-question="palette" data-answer="earth">Earthy neutrals &amp; warm tones</button>
                    <button type="button" class="quiz-option" data-question="palette" data-answer="vivid">Vivid pops and gradients</button>
                </div>
            </div>

            <div class="quiz-step" data-quiz-step="3">
                <h3>What’s the main goal for this bundle?</h3>
                <div class="quiz-options">
                    <button type="button" class="quiz-option" data-question="goal" data-answer="everyday">Daily fits I can rotate</button>
                    <button type="button" class="quiz-option" data-question="goal" data-answer="launch">Launch merch for my community</button>
                    <button type="button" class="quiz-option" data-question="goal" data-answer="gift">Gift something unforgettable</button>
                </div>
            </div>
        </div>

        <div class="quiz-progress" aria-hidden="true">
            <div class="quiz-progress-fill" id="quizProgressFill"></div>
        </div>

        <div class="quiz-result" id="quizResult">
            <h3 class="text-2xl font-semibold text-emerald-700" id="quizPersonaLabel">Your bundle is ready</h3>
            <p class="text-sm text-emerald-700 mt-2" id="quizPersonaSummary"></p>
            <div class="quiz-recommendations" id="quizRecommendations"></div>
            <button type="button" class="quiz-retake" id="quizRetake">↻ Try a different vibe</button>
        </div>
    </div>
</section>

<main class="container">
    <div class="product-grid">
        <?php if ($productResult && $productResult->num_rows > 0): while($product = $productResult->fetch_assoc()):
            $stockBadge = build_stock_badge((int) ($product['stock_qty'] ?? 0));
            $estimate = compute_delivery_estimate($shippingInfo, (int) ($product['inventory_id'] ?? 0) === 4);
            $inventoryId = (int) ($product['inventory_id'] ?? 0);
            $meta = $inventoryQuizMeta[$inventoryId] ?? ['style' => [], 'palette' => [], 'goal' => []];
            $personaMatchScore = 0;
            $personaReasons = [];

            if (!empty($personaProfile['style']) && in_array($personaProfile['style'], $meta['style'], true)) {
                $personaMatchScore++;
                $personaReasons[] = ucfirst($personaProfile['style']) . ' fit';
            }
            if (!empty($personaProfile['palette']) && in_array($personaProfile['palette'], $meta['palette'], true)) {
                $personaMatchScore++;
                $personaReasons[] = ucfirst($personaProfile['palette']) . ' palette';
            }
            if (!empty($personaProfile['goal']) && in_array($personaProfile['goal'], $meta['goal'], true)) {
                $personaMatchScore++;
                $personaReasons[] = ucfirst($personaProfile['goal']) . ' goal';
            }

            $personaHighlight = $personaMatchScore >= 2;
            $personaMessage = $personaMatchScore > 0 ? implode(' • ', $personaReasons) : '';
            $isClearance = !empty($product['is_clearance']);
            $currentPrice = isset($product['price']) ? (float) $product['price'] : 0.0;
            $originalPrice = $markdownActive && isset($markdownOriginals[$inventoryId])
                ? (float) $markdownOriginals[$inventoryId]
                : null;
            $priceSavings = 0.0;
            $priceSavingsPercent = 0;
            if ($originalPrice !== null && $originalPrice > $currentPrice) {
                $priceSavings = $originalPrice - $currentPrice;
                if ($originalPrice > 0) {
                    $priceSavingsPercent = round(($priceSavings / $originalPrice) * 100);
                }
            } else {
                $originalPrice = null;
            }
        ?>
            <?php
                $cardClasses = ['product-card'];
                if ($personaHighlight) {
                    $cardClasses[] = 'persona-match';
                }
                if ($isClearance) {
                    $cardClasses[] = 'is-clearance';
                }
            ?>
            <div class="<?php echo implode(' ', $cardClasses); ?>" data-aos="fade-up" data-inventory-id="<?php echo $inventoryId; ?>" data-match-score="<?php echo $personaMatchScore; ?>" data-is-clearance="<?php echo $isClearance ? '1' : '0'; ?>">
                <img src="<?php echo htmlspecialchars($product['image_url'] ?? 'image/placeholder.png'); ?>" 
                     alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                
                <div class="product-card-content">
                    <h3><?php echo htmlspecialchars($product['product_name']); ?></h3>
                    <?php if ($isClearance): ?>
                        <span class="product-clearance-pill" aria-label="Clearance item">Clearance drop</span>
                    <?php endif; ?>
                    <div class="product-card-price">
                        <?php if ($originalPrice !== null): ?>
                            <span class="product-price-original">₹<?php echo number_format($originalPrice, 2); ?></span>
                        <?php endif; ?>
                        <span class="product-price-current">₹<?php echo number_format($currentPrice, 2); ?></span>
                    </div>
                    <?php if ($originalPrice !== null): ?>
                        <span class="product-price-savings">Save ₹<?php echo number_format($priceSavings, 2); ?><?php if ($priceSavingsPercent > 0) { echo ' (' . (int) $priceSavingsPercent . '%)'; } ?></span>
                    <?php endif; ?>
                    <?php if ($personaHighlight && $personaMessage !== ''): ?>
                        <span class="persona-callout"><i data-feather="compass"></i><?php echo htmlspecialchars($personaMessage); ?></span>
                    <?php endif; ?>
                    <div class="product-alert <?php echo htmlspecialchars($stockBadge['theme']); ?>">
                        <?php echo htmlspecialchars($stockBadge['label']); ?>
                    </div>
                    <div class="product-delivery">
                        <span><?php echo htmlspecialchars($estimate['label']); ?></span>
                        <span><?php echo htmlspecialchars($estimate['meta']); ?></span>
                    </div>
                    
                    <div class="flex gap-2 mt-4">
                        <?php if ($product['inventory_id'] == 4): // If it's the Custom 3D Design product ?>
                            <a href="design3d.php" class="btn btn-primary w-full">Start Designing</a>
                        <?php else: // For all other standard products ?>
                                     <a href="cart_handler.php?action=add&id=<?php echo $product['inventory_id']; ?>&redirect=shop.php&csrf_token=<?php echo urlencode($_SESSION['csrf_token'] ?? ''); ?>" 
                               class="btn btn-outline flex-1">Add to Cart</a>
                                     <a href="cart_handler.php?action=add&id=<?php echo $product['inventory_id']; ?>&redirect=cart.php&csrf_token=<?php echo urlencode($_SESSION['csrf_token'] ?? ''); ?>" 
                               class="btn btn-primary flex-1">Buy Now</a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Hover Overlay for Quick Actions -->
                <div class="product-card-overlay">
                    <div class="product-card-overlay-actions">
                        <?php if ($product['inventory_id'] == 4): ?>
                            <a href="design3d.php" class="btn btn-primary btn-sm">Start Designing</a>
                        <?php else: ?>
                            <a href="cart_handler.php?action=add&id=<?php echo $product['inventory_id']; ?>&redirect=shop.php&csrf_token=<?php echo urlencode($_SESSION['csrf_token'] ?? ''); ?>" 
                               class="btn btn-secondary btn-sm" onclick="event.preventDefault(); addToCartQuick(<?php echo $product['inventory_id']; ?>); return false;">
                                <i data-feather="shopping-cart"></i> Add to Cart
                            </a>
                            <a href="cart_handler.php?action=add&id=<?php echo $product['inventory_id']; ?>&redirect=cart.php&csrf_token=<?php echo urlencode($_SESSION['csrf_token'] ?? ''); ?>" 
                               class="btn btn-primary btn-sm">
                                <i data-feather="zap"></i> Buy Now
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endwhile; else: ?>
            <div class="col-span-4 text-center">
                <div class="card p-12">
                    <h3 class="mb-4">No Products Available</h3>
                    <p class="text-muted">We're working hard to add new products.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<aside class="mini-cart collapsed" id="miniCart" aria-live="polite">
    <button class="mini-cart-toggle" id="miniCartToggle" type="button" aria-expanded="false">
        <span><?php echo $cartSnapshot['items']; ?> item<?php echo $cartSnapshot['items'] === 1 ? '' : 's'; ?></span>
        <span>
            <?php if (($cartSnapshot['savings'] ?? 0) > 0): ?>
                <span class="mini-price-original">₹<?php echo number_format($cartSnapshot['original_subtotal'], 2); ?></span>
            <?php endif; ?>
            <span class="mini-price-current">₹<?php echo number_format($cartSnapshot['subtotal'], 2); ?></span>
        </span>
    </button>
    <div class="mini-cart-panel" id="miniCartPanel">
        <div class="mini-cart-body">
            <?php if ($cartSnapshot['items'] === 0): ?>
                <div class="mini-cart-empty">Your cart is empty. Start with a tee or craft a custom design.</div>
            <?php else: ?>
                <?php foreach ($cartSnapshot['lines'] as $line): ?>
                    <?php
                        $isFreebie = !empty($line['is_freebie']);
                        $bundleValue = isset($line['bundle_value']) ? (float) $line['bundle_value'] : 0.0;
                    ?>
                    <div class="mini-cart-line<?php echo $isFreebie ? ' is-freebie' : ''; ?>">
                        <img class="mini-cart-thumb" src="<?php echo htmlspecialchars($line['thumbnail']); ?>" alt="<?php echo htmlspecialchars($line['name']); ?> thumbnail">
                        <div>
                            <h4><?php echo htmlspecialchars($line['name']); ?></h4>
                            <?php if (!empty($line['is_clearance']) && !$isFreebie): ?>
                                <span class="mini-cart-clearance">Clearance</span>
                            <?php endif; ?>
                            <?php if ($isFreebie): ?>
                                <span class="mini-cart-free-label">Bundle Freebie</span>
                                <p>
                                    <span class="mini-price-current">Free</span>
                                    × <?php echo (int) $line['quantity']; ?>
                                    <?php if ($bundleValue > 0): ?>
                                        • <span class="mini-price-savings">Value ₹<?php echo number_format($bundleValue, 2); ?></span>
                                    <?php endif; ?>
                                </p>
                                <?php if (!empty($line['meta'])): ?>
                                    <p class="mini-cart-meta"><?php echo htmlspecialchars($line['meta']); ?></p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p>
                                    <?php if (!empty($line['original_price'])): ?>
                                        <span class="mini-price-original">₹<?php echo number_format($line['original_price'], 2); ?></span>
                                    <?php endif; ?>
                                    <span class="mini-price-current">₹<?php echo number_format($line['unit_price'], 2); ?></span>
                                    × <?php echo (int) $line['quantity']; ?> •
                                    <span class="mini-price-current">₹<?php echo number_format($line['subtotal'], 2); ?></span>
                                </p>
                                <?php if (($line['savings'] ?? 0) > 0): ?>
                                    <p class="mini-price-savings">Saved ₹<?php echo number_format($line['savings'], 2); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($line['meta'])): ?>
                                    <p class="mini-cart-meta"><?php echo htmlspecialchars($line['meta']); ?></p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (($cartSnapshot['savings'] ?? 0) > 0): ?>
                    <div class="mini-price-savings">Cart savings: ₹<?php echo number_format($cartSnapshot['savings'], 2); ?></div>
                <?php endif; ?>
                <?php if (!empty($cartSnapshot['bundle_value'])): ?>
                    <div class="mini-cart-value-note">Bundle freebies worth ₹<?php echo number_format($cartSnapshot['bundle_value'], 2); ?> included</div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="mini-cart-footer">
            <a href="cart.php" class="btn btn-outline">View Cart</a>
            <a href="checkout.php" class="btn btn-primary">Checkout</a>
        </div>
    </div>
</aside>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const miniCart = document.getElementById('miniCart');
        const toggleButton = document.getElementById('miniCartToggle');
        const panel = document.getElementById('miniCartPanel');

        if (miniCart && toggleButton && panel) {
            toggleButton.addEventListener('click', () => {
                const isCollapsed = miniCart.classList.toggle('collapsed');
                toggleButton.setAttribute('aria-expanded', String(!isCollapsed));
                if (!isCollapsed) {
                    panel.scrollTop = 0;
                }
            });

            if (<?php echo (int) $cartSnapshot['items']; ?> > 0) {
                miniCart.classList.remove('collapsed');
                toggleButton.setAttribute('aria-expanded', 'true');
            }
        }

        const quiz = document.getElementById('styleQuiz');
        if (!quiz) {
            return;
        }

        const steps = Array.from(quiz.querySelectorAll('.quiz-step'));
        const progressFill = document.getElementById('quizProgressFill');
        const resultPanel = document.getElementById('quizResult');
        const personaLabel = document.getElementById('quizPersonaLabel');
        const personaSummary = document.getElementById('quizPersonaSummary');
        const recContainer = document.getElementById('quizRecommendations');
        const retakeButton = document.getElementById('quizRetake');
        const productCards = document.querySelectorAll('.product-card[data-inventory-id]');
        const personaBar = document.querySelector('[data-persona-bar]');

        if (personaBar) {
            const actionButtons = personaBar.querySelectorAll('[data-persona-filter]');
            const cards = Array.from(productCards);

            const applyPersonaFilter = (mode) => {
                cards.forEach((card) => {
                    const score = parseInt(card.getAttribute('data-match-score') || '0', 10);
                    const shouldMute = mode === 'focus' && score < 2;
                    card.classList.toggle('is-muted', shouldMute);
                });
            };

            actionButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    actionButtons.forEach((btn) => btn.classList.remove('is-active'));
                    button.classList.add('is-active');
                    applyPersonaFilter(button.getAttribute('data-persona-filter'));
                });
            });

            const defaultButton = personaBar.querySelector('[data-persona-filter].is-active');
            const defaultMode = defaultButton ? defaultButton.getAttribute('data-persona-filter') : 'all';
            applyPersonaFilter(defaultMode || 'all');
        }

        let currentStep = 0;
        const answers = {};

        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

        function updateProgress() {
            if (!progressFill || steps.length === 0) {
                return;
            }
            const percent = Math.round(((currentStep + 1) / steps.length) * 100);
            progressFill.style.width = percent + '%';
        }

        function showStep(index) {
            steps.forEach((step, idx) => {
                step.classList.toggle('active', idx === index);
            });
            currentStep = index;
            updateProgress();
        }

        function resetHighlights() {
            productCards.forEach((card) => card.classList.remove('recommended'));
        }

        function renderRecommendations(recommendations) {
            recContainer.innerHTML = '';

            if (!Array.isArray(recommendations) || recommendations.length === 0) {
                const empty = document.createElement('p');
                empty.className = 'text-sm text-emerald-800';
                empty.textContent = 'We did not spot a perfect match yet, but explore the catalog below to craft your own bundle.';
                recContainer.appendChild(empty);
                resetHighlights();
                return;
            }

            recommendations.forEach((rec) => {
                const card = document.createElement('article');
                card.className = 'quiz-rec-card';

                if (rec.image_url) {
                    const img = document.createElement('img');
                    img.src = rec.image_url;
                    img.alt = rec.name ? rec.name + ' preview' : 'Recommended product preview';
                    card.appendChild(img);
                }

                const title = document.createElement('h4');
                title.textContent = rec.name || 'Recommended pick';
                card.appendChild(title);

                if (rec.reason) {
                    const reason = document.createElement('p');
                    reason.textContent = rec.reason;
                    card.appendChild(reason);
                }

                if (typeof rec.price !== 'undefined' && rec.price !== null) {
                    const parsedPrice = Number(rec.price);
                    if (Number.isFinite(parsedPrice)) {
                        const price = document.createElement('p');
                        price.className = 'font-semibold text-emerald-900';
                        price.textContent = '₹' + parsedPrice.toFixed(2);
                        card.appendChild(price);
                    }
                }

                if (rec.inventory_id) {
                    const link = document.createElement('a');
                    link.className = 'btn btn-primary btn-sm';
                    const queryToken = encodeURIComponent(csrfToken || '');
                    link.href = `cart_handler.php?action=add&id=${encodeURIComponent(rec.inventory_id)}&redirect=shop.php&csrf_token=${queryToken}`;
                    link.textContent = 'Add to cart';
                    card.appendChild(link);
                }

                recContainer.appendChild(card);
            });

            resetHighlights();
            recommendations.forEach((rec) => {
                if (!rec || !rec.inventory_id) {
                    return;
                }
                const card = document.querySelector(`.product-card[data-inventory-id="${rec.inventory_id}"]`);
                if (card) {
                    card.classList.add('recommended');
                }
            });
        }

        function goToNextStep() {
            if (currentStep < steps.length - 1) {
                showStep(currentStep + 1);
                return true;
            }
            return false;
        }

        function submitQuiz() {
            personaLabel.textContent = 'One sec – curating your bundle...';
            personaSummary.textContent = 'We are matching your answers with inventory that fits your vibe.';
            resultPanel.classList.add('visible');
            recContainer.innerHTML = '';
            resetHighlights();

            const payload = Object.assign({}, answers, {
                source: 'shop_quiz'
            });

            fetch('style_quiz_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrfToken || ''
                },
                body: JSON.stringify(payload)
            })
            .then((response) => response.json())
            .then((data) => {
                if (!data || data.success !== true) {
                    personaLabel.textContent = 'We hit a snag matching your style.';
                    personaSummary.textContent = (data && data.message) ? data.message : 'Please try again or chat with our team for a custom concierge.';
                    renderRecommendations([]);
                    return;
                }

                personaLabel.textContent = data.personaLabel || 'Your Mystic bundle';
                personaSummary.textContent = data.personaSummary || '';
                renderRecommendations(data.recommendations || []);

                if (data.accountSynced) {
                    personaSummary.textContent += ' We saved this in your account so future drops stay on-brand.';
                }
            })
            .catch(() => {
                personaLabel.textContent = 'We hit a snag matching your style.';
                personaSummary.textContent = 'Please refresh the page or try again in a moment.';
                renderRecommendations([]);
            });
        }

        steps.forEach((step, stepIndex) => {
            const options = step.querySelectorAll('.quiz-option');
            options.forEach((option) => {
                option.addEventListener('click', () => {
                    const question = option.getAttribute('data-question');
                    const answer = option.getAttribute('data-answer');
                    if (!question || !answer) {
                        return;
                    }

                    step.querySelectorAll('.quiz-option').forEach((btn) => btn.classList.remove('active'));
                    option.classList.add('active');
                    answers[question] = answer;

                    const advanced = goToNextStep();
                    if (!advanced) {
                        submitQuiz();
                    }
                });
            });
        });

        if (retakeButton) {
            retakeButton.addEventListener('click', () => {
                Object.keys(answers).forEach((key) => delete answers[key]);
                steps.forEach((step) => {
                    step.querySelectorAll('.quiz-option').forEach((btn) => btn.classList.remove('active'));
                });
                resultPanel.classList.remove('visible');
                showStep(0);
                personaLabel.textContent = 'Your bundle is ready';
                personaSummary.textContent = '';
                recContainer.innerHTML = '';
                resetHighlights();
            });
        }

        showStep(0);
    });

    // Quick Add to Cart with Toast Notification
    function addToCartQuick(inventoryId) {
        const csrfToken = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
        
        fetch('cart_handler.php?action=add&id=' + inventoryId + '&csrf_token=' + encodeURIComponent(csrfToken), {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.text())
        .then(() => {
            // Show success toast
            if (typeof toastSuccess === 'function') {
                toastSuccess('Item added to cart successfully!');
            }
            
            // Update mini cart if it exists
            const miniCart = document.getElementById('miniCart');
            if (miniCart) {
                // Refresh mini cart content
                location.reload(); // Simple refresh for now, can be improved with AJAX
            }
        })
        .catch(error => {
            console.error('Error adding to cart:', error);
            if (typeof toastError === 'function') {
                toastError('Failed to add item to cart. Please try again.');
            }
        });
    }
</script>

<?php include 'footer.php'; ?>