<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include 'header.php';
require_once __DIR__ . '/core/remix_logger.php';

if (!isset($_SESSION['customer_id'])) {
    $_SESSION['info_message'] = "You need to log in to access the 3D Designer.";
    header('Location: login.php');
    exit();
}

function resolveInspirationTexture(?string $rawPath): ?string
{
    if ($rawPath === null) {
        return null;
    }

    $rawPath = trim($rawPath);
    if ($rawPath === '') {
        return null;
    }

    if (preg_match('/^[a-z]+:\/\//i', $rawPath)) {
        return null;
    }

    $rawPath = str_replace("\0", '', $rawPath);
    $rawPath = ltrim($rawPath, '/\\');

    $baseDir = realpath(__DIR__);
    if ($baseDir === false) {
        return null;
    }

    $candidatePath = realpath($baseDir . DIRECTORY_SEPARATOR . $rawPath);
    if ($candidatePath === false || !is_file($candidatePath)) {
        return null;
    }

    $allowedRoots = array_filter([
        realpath(__DIR__ . '/uploads'),
        realpath(__DIR__ . '/image'),
    ]);

    $isAllowed = false;
    foreach ($allowedRoots as $root) {
        if ($root !== false && strpos($candidatePath, $root) === 0) {
            $isAllowed = true;
            break;
        }
    }

    if (!$isAllowed) {
        return null;
    }

    $relativePath = substr($candidatePath, strlen($baseDir) + 1);
    return $relativePath ? str_replace('\\', '/', $relativePath) : null;
}

$inspirationTexture = resolveInspirationTexture($_GET['inspiration_texture'] ?? null);

$remixTexture = resolveInspirationTexture($_GET['remix_texture'] ?? null);
$remixVariant = strtolower((string) ($_GET['remix_variant'] ?? ''));
$allowedVariants = ['front', 'back', 'map'];
if (!in_array($remixVariant, $allowedVariants, true)) {
    $remixVariant = null;
}

$remixLabelRaw = trim((string) ($_GET['remix_label'] ?? ''));
$remixLabel = $remixLabelRaw !== '' ? substr($remixLabelRaw, 0, 80) : null;

$remixOriginRaw = trim((string) ($_GET['remix_origin'] ?? ''));
$remixOrigin = $remixOriginRaw !== '' ? substr($remixOriginRaw, 0, 120) : null;

$remixSourceRaw = trim((string) ($_GET['remix_source'] ?? ''));
$remixSource = $remixSourceRaw !== '' ? substr($remixSourceRaw, 0, 120) : null;

$remixTokenRaw = preg_replace('/[^a-f0-9]/i', '', (string) ($_GET['remix_token'] ?? ''));
$remixToken = $remixTokenRaw !== '' ? substr($remixTokenRaw, 0, 24) : null;

$remixPayload = array_filter([
    'texture' => $remixTexture,
    'variant' => $remixVariant,
    'label' => $remixLabel,
    'origin' => $remixOrigin,
    'source' => $remixSource,
    'token' => $remixToken,
], static function ($value) {
    return $value !== null && $value !== '';
});

$remixPayload = !empty($remixPayload) ? $remixPayload : null;

if ($remixPayload) {
    $customerId = $_SESSION['customer_id'] ?? null;
    $dedupeKeySource = $remixPayload['token'] ?? ($remixPayload['texture'] ?? '') . ($remixPayload['variant'] ?? '');
    $dedupeKey = 'remix_logged_' . substr(hash('sha256', (string) $dedupeKeySource), 0, 16);

    if ($customerId && empty($_SESSION[$dedupeKey])) {
        record_remix_entry([
            'customer_id' => $customerId,
            'source' => $remixPayload['source'] ?? null,
            'origin' => $remixPayload['origin'] ?? null,
            'variant' => $remixPayload['variant'] ?? null,
            'texture' => $remixPayload['texture'] ?? null,
            'token' => $remixPayload['token'] ?? null,
        ]);
        $_SESSION[$dedupeKey] = true;
    }
}

$remixHeadline = null;
$remixSubtext = null;

if ($remixPayload) {
    $remixLabel = $remixPayload['label'] ?? '';
    $variantFragment = isset($remixPayload['variant']) && $remixPayload['variant'] !== ''
        ? ucfirst($remixPayload['variant']) . ' view'
        : '';

    $headlineParts = array_filter([$remixLabel, $variantFragment], static function ($value) {
        return is_string($value) && $value !== '';
    });
    $remixHeadline = $headlineParts
        ? implode(' • ', $headlineParts)
        : 'Remix ready to customize';

    $originParts = [];
    if (!empty($remixPayload['origin']) && is_string($remixPayload['origin'])) {
        $originParts[] = $remixPayload['origin'];
    }
    if (!empty($remixPayload['source']) && is_string($remixPayload['source'])) {
        $originParts[] = $remixPayload['source'];
    }
    $originParts = array_values(array_unique(array_filter($originParts, static function ($value) {
        return is_string($value) && trim($value) !== '';
    })));

    if (!empty($originParts)) {
        $remixSubtext = 'Inspired by ' . implode(' · ', $originParts);
    } else {
        $remixSubtext = 'This canvas is preloaded from a community spotlight entry.';
    }
}
?>

<style>
    .design3d-layout {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 2rem;
        padding: 2rem 0;
    }
    #renderCanvas {
        width: 100%;
        height: 70vh;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg);
        outline: none;
        touch-action: none;
        cursor: grab;
    }
    #renderCanvas.drawing-enabled {
        cursor: crosshair;
    }
    .sidebar-controls {
        position: sticky;
        top: 100px;
        height: fit-content;
    }
    .control-section {
        background-color: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        margin-bottom: 1.5rem;
        box-shadow: var(--shadow);
    }
    .control-section-header {
        padding: 1rem 1.5rem;
        font-weight: var(--font-semibold);
        color: var(--color-dark);
        border-bottom: 1px solid var(--color-border);
    }
    .control-section-body {
        padding: 1.5rem;
    }
    #drawToggleBtn.btn-success {
        background-color: var(--color-success);
        color: white;
        border-color: var(--color-success);
    }
    .apparel-btn-group {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .apparel-btn-group button {
        flex: 1 1 45%;
    }
    .apparel-btn-group button.active {
        background-color: var(--color-primary);
        color: #fff;
        border-color: var(--color-primary);
    }
    .checklist {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .checklist-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.5rem 0.75rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        background-color: #f9fafb;
        font-size: 0.9rem;
        transition: background-color 0.2s ease, border-color 0.2s ease;
    }
    .checklist-item.complete {
        border-color: #4ade80;
        background-color: #ecfdf5;
        color: #047857;
    }
    .checklist-item .status {
        font-weight: 600;
    }
    .color-swatches {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.5rem;
        margin-top: 0.75rem;
    }
    .color-swatch {
        border: none;
        border-radius: 0.5rem;
        height: 36px;
        cursor: pointer;
        position: relative;
        box-shadow: inset 0 0 0 1px rgba(255,255,255,0.4);
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .color-swatch::after {
        content: attr(data-label);
        position: absolute;
        inset: auto 0 -1.6rem 0;
        font-size: 0.7rem;
        text-align: center;
        color: #6b7280;
    }
    .color-swatch.active {
        box-shadow: 0 0 0 2px var(--color-primary);
        transform: translateY(-1px);
    }
    .template-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
    }
    .template-card {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        padding: 0.6rem 0.75rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        background: #fff;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .template-card.is-favorite {
        border-color: #f59e0b;
        box-shadow: 0 6px 15px rgba(245, 158, 11, 0.2);
    }
    .template-card:hover {
        border-color: var(--color-primary);
        box-shadow: 0 6px 15px rgba(99, 102, 241, 0.1);
    }
    .template-btn {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.4rem;
        border: none;
        background: transparent;
        padding: 0;
        text-align: left;
        font-size: 0.85rem;
        cursor: pointer;
    }
    .template-preview {
        width: 100%;
        height: 48px;
        border-radius: 0.6rem;
        border: 1px solid rgba(255,255,255,0.4);
    }
    .template-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        gap: 0.5rem;
        margin-top: 0.4rem;
    }
    .template-fav-btn {
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        background: #f9fafb;
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
        cursor: pointer;
        transition: background-color 0.2s ease, border-color 0.2s ease;
    }
    .template-fav-btn:hover {
        background: #fef2c7;
        border-color: #facc15;
    }
    .template-fav-btn.active {
        background: #facc15;
        border-color: #d97706;
        color: #78350f;
    }
    .template-filter-group {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
    }
    .template-filter-btn {
        border: 1px solid #d1d5db;
        border-radius: 9999px;
        background: #fff;
        padding: 0.3rem 0.8rem;
        font-size: 0.75rem;
        cursor: pointer;
        transition: background-color 0.2s ease, border-color 0.2s ease;
    }
    .template-filter-btn.active {
        background: #4f46e5;
        border-color: #4338ca;
        color: #fff;
    }
    .template-empty {
        margin-top: 0.75rem;
        font-size: 0.8rem;
        padding: 0.75rem;
        border: 1px dashed #cbd5f5;
        border-radius: 0.6rem;
        background: #eef2ff;
        color: #4338ca;
        text-align: center;
    }
    .timeline-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        max-height: 240px;
        overflow-y: auto;
    }
    .timeline-entry {
        display: flex;
        gap: 0.75rem;
        align-items: center;
        padding: 0.5rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.6rem;
        background: #fff;
    }
    .timeline-thumb {
        width: 48px;
        height: 48px;
        border-radius: 0.5rem;
        object-fit: cover;
        border: 1px solid #d1d5db;
        background: #f3f4f6;
    }
    .timeline-meta {
        display: flex;
        flex-direction: column;
        font-size: 0.75rem;
        flex: 1;
        color: #4b5563;
    }
    .timeline-actions {
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }
    .timeline-actions button {
        font-size: 0.7rem;
    }
    .preview-carousel {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
    }
    .preview-card {
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        padding: 0.5rem;
        background: #fff;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        align-items: center;
    }
    .preview-card img {
        width: 100%;
        aspect-ratio: 1 / 1;
        object-fit: cover;
        border-radius: 0.6rem;
        border: 1px solid #e5e7eb;
        background: #f3f4f6;
    }
    .preview-meta {
        font-size: 0.75rem;
        color: #6b7280;
        margin-top: 0.5rem;
    }
    .preview-empty {
        grid-column: span 2;
        text-align: center;
        padding: 0.75rem;
        font-size: 0.85rem;
        color: #6b7280;
        border: 1px dashed #d1d5db;
        border-radius: 0.75rem;
        background: #f9fafb;
    }
    .shortcut-overlay {
        position: fixed;
        inset: 0;
        background: rgba(17,24,39,0.7);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        padding: 2rem;
    }
    .shortcut-overlay.visible {
        display: flex;
    }
    .shortcut-panel {
        background: #ffffff;
        border-radius: 1rem;
        padding: 1.5rem;
        max-width: 480px;
        width: 100%;
        box-shadow: 0 30px 60px rgba(30,41,59,0.25);
    }
    .shortcut-panel h3 {
        font-size: 1.25rem;
        margin-bottom: 1rem;
    }
    .shortcut-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    .shortcut-item {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        font-size: 0.9rem;
    }
    .shortcut-key {
        font-family: 'Fira Code', monospace;
        font-size: 0.85rem;
        background: #eef2ff;
        color: #4338ca;
        padding: 0.2rem 0.5rem;
        border-radius: 0.4rem;
    }
    .design-insight {
        font-size: 0.8rem;
        color: #4b5563;
        background: #f9fafb;
        border-radius: 0.75rem;
        padding: 0.75rem;
        border: 1px solid #e5e7eb;
    }
    .remix-callout {
        border: 1px solid rgba(99, 102, 241, 0.25);
        border-radius: 1rem;
        padding: 1rem 1.25rem;
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.08), rgba(14, 165, 233, 0.1));
        margin-bottom: 1.5rem;
        box-shadow: 0 12px 30px rgba(79, 70, 229, 0.12);
        display: flex;
        flex-direction: column;
        gap: 0.6rem;
    }
    .remix-callout__badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-weight: 600;
        color: #4338ca;
        background: rgba(99, 102, 241, 0.14);
        border-radius: 999px;
        padding: 0.25rem 0.75rem;
        align-self: flex-start;
    }
    .remix-callout__title {
        font-size: 1.05rem;
        font-weight: 600;
        color: #1f2937;
        margin: 0;
    }
    .remix-callout__note {
        font-size: 0.85rem;
        color: #4b5563;
        margin: 0;
    }
    .remix-callout__actions {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
        align-items: center;
    }
    .remix-callout__dismiss {
        border: none;
        background: transparent;
        color: #4338ca;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        cursor: pointer;
        padding: 0.25rem 0;
    }
    .remix-callout__dismiss:hover {
        text-decoration: underline;
    }
    .hidden {
        display: none !important;
    }
    @media (max-width: 992px) {
        .design3d-layout {
            grid-template-columns: 1fr;
        }
        .sidebar-controls {
            position: static;
        }
        #renderCanvas {
            height: 60vh;
        }
    }
</style>

<main class="container">
    <div class="page-header">
        <h1>3D Apparel Designer</h1>
        <p><b>How to use:</b> Pick a garment, click the surface to place logos or text, or toggle drawing mode for free-hand art.</p>
    </div>

    <div class="design3d-layout">
        <div class="canvas-container">
            <canvas id="renderCanvas"></canvas>
        </div>

        <div class="sidebar-controls">
            <?php if ($remixPayload): ?>
            <aside class="remix-callout" id="remixCallout">
                <span class="remix-callout__badge">Remix Session</span>
                <?php if (!empty($remixHeadline)): ?>
                <h3 class="remix-callout__title"><?php echo htmlspecialchars($remixHeadline); ?></h3>
                <?php endif; ?>
                <?php if (!empty($remixSubtext)): ?>
                <p class="remix-callout__note"><?php echo htmlspecialchars($remixSubtext); ?></p>
                <?php endif; ?>
                <div class="remix-callout__actions">
                    <button type="button" class="remix-callout__dismiss" data-remix-dismiss>Hide</button>
                </div>
            </aside>
            <?php endif; ?>
            <div class="control-section">
                <div class="control-section-header">Apparel Type</div>
                <div class="control-section-body apparel-btn-group">
                    <button id="modelTshirt" class="btn btn-outline active" data-model="tshirt">T-Shirt</button>
                    <button id="modelHoodie" class="btn btn-outline" data-model="hoodie">Hoodie</button>
                    <button id="modelShirt" class="btn btn-outline" data-model="shirt">Shirt</button>
                </div>
            </div>

            <div class="control-section">
                <div class="control-section-header">Design Checklist</div>
                <div class="control-section-body">
                    <ul class="checklist">
                        <li class="checklist-item" data-check="base-color"><span>Pick a base color</span><span class="status">Pending</span></li>
                        <li class="checklist-item" data-check="artwork"><span>Add artwork (logo, text, or drawing)</span><span class="status">Pending</span></li>
                        <li class="checklist-item" data-check="preview"><span>Capture a front & back preview</span><span class="status">Pending</span></li>
                    </ul>
                </div>
            </div>

            <div class="control-section">
                <div class="control-section-header">Base Color</div>
                <div class="control-section-body">
                    <label for="baseColor" class="form-label">Select Base Color</label>
                    <input type="color" id="baseColor" value="#cccccc" class="form-control" style="height: 48px;">
                    <div class="color-swatches">
                        <button type="button" class="color-swatch" data-swatch="#f8fafc" data-label="Frost"></button>
                        <button type="button" class="color-swatch" data-swatch="#111827" data-label="Midnight"></button>
                        <button type="button" class="color-swatch" data-swatch="#4f46e5" data-label="Indigo"></button>
                        <button type="button" class="color-swatch" data-swatch="#ef4444" data-label="Crimson"></button>
                        <button type="button" class="color-swatch" data-swatch="#38bdf8" data-label="Sky"></button>
                        <button type="button" class="color-swatch" data-swatch="#10b981" data-label="Mint"></button>
                    </div>
                </div>
            </div>

            <div class="control-section">
                <div class="control-section-header">Add Logo</div>
                <div class="control-section-body">
                    <label for="logoUpload" class="form-label">Upload Image</label>
                    <input type="file" id="logoUpload" class="form-control" accept="image/png, image/jpeg">
                    <label for="logoSize" class="form-label mt-3">Logo Size: <span id="logoSizeValue">40</span></label>
                    <input type="range" id="logoSize" min="10" max="80" value="40" class="w-full">
                </div>
            </div>

            <div class="control-section">
                <div class="control-section-header">Quick Templates</div>
                <div class="control-section-body">
                    <div class="template-filter-group">
                        <button type="button" class="template-filter-btn active" data-template-filter="all">All</button>
                        <button type="button" class="template-filter-btn" data-template-filter="gradient">Gradients</button>
                        <button type="button" class="template-filter-btn" data-template-filter="badge">Badges</button>
                        <button type="button" class="template-filter-btn" data-template-filter="pattern">Patterns</button>
                        <button type="button" class="template-filter-btn" data-template-filter="favorite">Favorites</button>
                    </div>
                    <div class="template-grid">
                        <div class="template-card" data-template-key="sunset" data-template-category="gradient">
                            <button type="button" class="template-btn" data-template-apply="sunset">
                                <span class="template-preview" style="background: linear-gradient(135deg, #fb7185 0%, #f97316 100%);"></span>
                                <span class="template-name">Sunset Gradient</span>
                            </button>
                            <div class="template-actions">
                                <span class="text-xs text-gray-500">Gradient</span>
                                <button type="button" class="template-fav-btn" data-template-fav="sunset">Save</button>
                            </div>
                        </div>
                        <div class="template-card" data-template-key="aurora" data-template-category="gradient">
                            <button type="button" class="template-btn" data-template-apply="aurora">
                                <span class="template-preview" style="background: linear-gradient(135deg, #14b8a6 0%, #6366f1 100%);"></span>
                                <span class="template-name">Aurora Fade</span>
                            </button>
                            <div class="template-actions">
                                <span class="text-xs text-gray-500">Gradient</span>
                                <button type="button" class="template-fav-btn" data-template-fav="aurora">Save</button>
                            </div>
                        </div>
                        <div class="template-card" data-template-key="monogram" data-template-category="badge">
                            <button type="button" class="template-btn" data-template-apply="monogram">
                                <span class="template-preview" style="background: radial-gradient(circle at 30% 30%, #facc15, #f97316);"></span>
                                <span class="template-name">Monogram Badge</span>
                            </button>
                            <div class="template-actions">
                                <span class="text-xs text-gray-500">Badge</span>
                                <button type="button" class="template-fav-btn" data-template-fav="monogram">Save</button>
                            </div>
                        </div>
                        <div class="template-card" data-template-key="stripes" data-template-category="pattern">
                            <button type="button" class="template-btn" data-template-apply="stripes">
                                <span class="template-preview" style="background: repeating-linear-gradient(135deg, #0ea5e9 0, #0ea5e9 10px, #1e293b 10px, #1e293b 20px);"></span>
                                <span class="template-name">Sport Stripes</span>
                            </button>
                            <div class="template-actions">
                                <span class="text-xs text-gray-500">Pattern</span>
                                <button type="button" class="template-fav-btn" data-template-fav="stripes">Save</button>
                            </div>
                        </div>
                    </div>
                    <div class="template-empty hidden" id="templateEmptyState">No templates here yet. Try another filter or save a favorite.</div>
                </div>
            </div>

            <div class="control-section">
                <div class="control-section-header">Add Custom Text</div>
                <div class="control-section-body">
                    <label for="customText" class="form-label">Enter Text</label>
                    <input type="text" id="customText" placeholder="Your Text Here" class="form-control mb-3">
                    <label for="fontStyle" class="form-label">Font Style</label>
                    <select id="fontStyle" class="form-control mb-3">
                        <option value="Arial">Arial</option>
                        <option value="Verdana">Verdana</option>
                        <option value="Georgia">Georgia</option>
                        <option value="Impact">Impact</option>
                    </select>
                    <label for="fontSize" class="form-label">Font Size: <span id="fontSizeValue">40</span></label>
                    <input type="range" id="fontSize" min="20" max="80" value="40" class="w-full mb-3">
                    <button id="addTextBtn" class="btn btn-secondary w-full">Apply Text</button>
                </div>
            </div>

            <div class="control-section">
                <div class="control-section-header">Free-Hand Drawing</div>
                <div class="control-section-body">
                    <button id="drawToggleBtn" class="btn btn-outline w-full mb-4">Drawing is OFF</button>
                    <label for="brushColor" class="form-label">Brush Color</label>
                    <input type="color" id="brushColor" value="#ff0000" class="form-control mb-3" style="height: 48px;">
                    <label for="brushSize" class="form-label">Brush Size: <span id="brushSizeValue">5</span></label>
                    <input type="range" id="brushSize" min="1" max="20" value="5" class="w-full">
                </div>
            </div>

            <div class="control-section">
                <div class="control-section-header">Actions</div>
                <div class="control-section-body flex flex-col gap-4">
                    <button id="undoBtn" class="btn btn-outline w-full">Undo</button>
                    <button id="resetBtn" class="btn btn-danger w-full">Reset Design</button>
                    <button id="saveDesignBtn" class="btn btn-primary w-full">Save & Add to Cart</button>
                    <button id="refreshPreviewBtn" class="btn btn-outline w-full">Refresh Preview</button>
                    <button id="toggleShortcutsBtn" class="btn btn-outline w-full">Keyboard Shortcuts</button>
                    <div class="design-insight">
                        <strong>Estimated delivery:</strong> 5-7 business days (custom production).<br>
                        <strong>Materials:</strong> Premium cotton blend with eco-friendly inks.
                    </div>
                </div>
            </div>

            <div class="control-section">
                <div class="control-section-header">Preview Gallery</div>
                <div class="control-section-body">
                    <div class="preview-carousel" id="designPreviewCarousel">
                        <div class="preview-card" data-role="preview-card">
                            <img id="designPreviewFront" alt="Front preview" src="image/placeholder.png">
                            <span class="text-xs text-gray-500">Front</span>
                        </div>
                        <div class="preview-card" data-role="preview-card">
                            <img id="designPreviewBack" alt="Back preview" src="image/placeholder.png">
                            <span class="text-xs text-gray-500">Back</span>
                        </div>
                        <div class="preview-empty" id="designPreviewEmpty">No preview captured yet. Refresh after adjusting your design.</div>
                    </div>
                    <div class="preview-meta" id="designPreviewMeta"></div>
                </div>
            </div>

            <div class="control-section">
                <div class="control-section-header">Design Timeline</div>
                <div class="control-section-body">
                    <ul class="timeline-list" id="designTimeline"></ul>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>

<div id="shortcutOverlay" class="shortcut-overlay" role="dialog" aria-modal="true" aria-labelledby="shortcutOverlayTitle">
    <div class="shortcut-panel">
        <div class="flex items-center justify-between mb-4">
            <h3 id="shortcutOverlayTitle" class="font-semibold text-lg">Keyboard Shortcuts</h3>
            <button type="button" id="closeShortcutOverlay" class="btn btn-outline">Close</button>
        </div>
        <div class="shortcut-list">
            <div class="shortcut-item"><span>Rotate view</span><span class="shortcut-key">Drag mouse</span></div>
            <div class="shortcut-item"><span>Zoom in/out</span><span class="shortcut-key">Mouse wheel</span></div>
            <div class="shortcut-item"><span>Toggle drawing mode</span><span class="shortcut-key">D</span></div>
            <div class="shortcut-item"><span>Undo last change</span><span class="shortcut-key">Ctrl / Cmd + Z</span></div>
            <div class="shortcut-item"><span>Quick preview</span><span class="shortcut-key">P</span></div>
        </div>
    </div>
</div>

<script src="https://cdn.babylonjs.com/babylon.js"></script>
<script src="https://cdn.babylonjs.com/loaders/babylonjs.loaders.min.js"></script>
<script type="module">
    import { applyDesignTexture } from './js/core/designer.js?v=20251011';
    const urlParams = new URLSearchParams(window.location.search);
    const initialDesignId = urlParams.has('design_id') ? parseInt(urlParams.get('design_id'), 10) || null : null;
    const inspirationTexture = <?php echo json_encode($inspirationTexture); ?>;
    const remixPayload = <?php echo json_encode($remixPayload, JSON_UNESCAPED_SLASHES); ?>;
    const canvas = document.getElementById('renderCanvas');
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
    const engine = new BABYLON.Engine(canvas, true);
    const assetVersion = '20251011';

    const remixCallout = document.getElementById('remixCallout');
    if (remixCallout) {
        const dismissKey = 'remixCalloutDismissed';
        let hideCallout = false;
        try {
            hideCallout = sessionStorage.getItem(dismissKey) === '1';
        } catch (error) {
            hideCallout = false;
        }

        if (hideCallout) {
            remixCallout.remove();
        } else {
            const dismissButton = remixCallout.querySelector('[data-remix-dismiss]');
            if (dismissButton) {
                dismissButton.addEventListener('click', () => {
                    remixCallout.remove();
                    try {
                        sessionStorage.setItem(dismissKey, '1');
                    } catch (error) {
                        // Ignore storage errors
                    }
                });
            }
        }
    }

    let scene;
    let model;
    let paintTexture;
    let paintContext;
    let modelLoaded = false;
    let history = [];
    let drawingModeEnabled = false;
    let lastPointerCoordinates = { x: 512, y: 412 };
    let currentModelType = 'tshirt';
    let currentBaseColor = '#cccccc';
    let editingDesignId = initialDesignId;
    const modelButtons = document.querySelectorAll('[data-model]');
    const baseColorInput = document.getElementById('baseColor');
    const availableModels = {
        tshirt: 'tshirt.glb',
        hoodie: 'hoodie.glb',
        shirt: 'shirt.glb',
    };
    const DEFAULT_BASE_COLOR = '#cccccc';
    const checklistElements = {
        base: document.querySelector('[data-check="base-color"]'),
        artwork: document.querySelector('[data-check="artwork"]'),
        preview: document.querySelector('[data-check="preview"]'),
    };
    const checklistState = { base: false, artwork: false, preview: false };
    const previewImages = {
        front: document.getElementById('designPreviewFront'),
        back: document.getElementById('designPreviewBack'),
    };
    const previewEmptyState = document.getElementById('designPreviewEmpty');
    const previewMeta = document.getElementById('designPreviewMeta');
    const refreshPreviewBtn = document.getElementById('refreshPreviewBtn');
    const shortcutOverlay = document.getElementById('shortcutOverlay');
    const toggleShortcutsBtn = document.getElementById('toggleShortcutsBtn');
    const closeShortcutOverlayBtn = document.getElementById('closeShortcutOverlay');
    const colorSwatches = document.querySelectorAll('.color-swatch');
    const templateButtons = document.querySelectorAll('[data-template-apply]');
    const templateCards = document.querySelectorAll('[data-template-key]');
    const templateFilterButtons = document.querySelectorAll('[data-template-filter]');
    const templateFavoriteButtons = document.querySelectorAll('[data-template-fav]');
    const templateEmptyState = document.getElementById('templateEmptyState');
    const DEFAULT_PREVIEW_PLACEHOLDER = 'image/placeholder.png';
    let activeSwatch = null;
    const timelineList = document.getElementById('designTimeline');
    const timelineEntries = [];
    let timelineEntryCounter = 0;
    let initialTimelineCaptured = false;

    function loadFavoriteTemplates() {
        try {
            const stored = localStorage.getItem('mystic-template-favorites');
            if (!stored) {
                return new Set();
            }
            const parsed = JSON.parse(stored);
            if (Array.isArray(parsed)) {
                return new Set(parsed.map(String));
            }
        } catch (error) {
            console.warn('Failed to read template favorites:', error);
        }
        return new Set();
    }

    const favoriteTemplates = loadFavoriteTemplates();

    function setChecklistItem(key, complete) {
        if (!Object.prototype.hasOwnProperty.call(checklistState, key)) {
            return;
        }
        checklistState[key] = !!complete;
        const el = checklistElements[key];
        if (!el) {
            return;
        }
        el.classList.toggle('complete', checklistState[key]);
        const statusEl = el.querySelector('.status');
        if (statusEl) {
            statusEl.textContent = checklistState[key] ? 'Ready' : 'Pending';
        }
    }

    function markArtworkAdded() {
        setChecklistItem('artwork', true);
    }

    function evaluateArtworkState() {
        const hasHistory = history.length > 1;
        setChecklistItem('artwork', hasHistory);
    }

    function clearPreviewCarousel() {
        if (previewImages.front) {
            previewImages.front.src = DEFAULT_PREVIEW_PLACEHOLDER;
        }
        if (previewImages.back) {
            previewImages.back.src = DEFAULT_PREVIEW_PLACEHOLDER;
        }
        if (previewEmptyState) {
            previewEmptyState.classList.remove('hidden');
        }
        if (previewMeta) {
            previewMeta.textContent = '';
        }
        setChecklistItem('preview', false);
    }

    function updatePreviewCarousel(front, back) {
        let hasAny = false;
        if (front && previewImages.front) {
            previewImages.front.src = front;
            hasAny = true;
        }
        if (back && previewImages.back) {
            previewImages.back.src = back;
            hasAny = true;
        }
        if (previewEmptyState) {
            previewEmptyState.classList.toggle('hidden', hasAny);
        }
        if (previewMeta) {
            previewMeta.textContent = hasAny ? `Updated ${new Date().toLocaleTimeString()}` : '';
        }
        setChecklistItem('preview', hasAny);
    }

    function persistFavoriteTemplates() {
        try {
            localStorage.setItem('mystic-template-favorites', JSON.stringify(Array.from(favoriteTemplates.values())));
        } catch (error) {
            console.warn('Failed to persist template favorites:', error);
        }
    }

    function updateTemplateFavoriteUI() {
        templateCards.forEach((card) => {
            const key = card.dataset.templateKey;
            const isFavorite = key && favoriteTemplates.has(key);
            card.classList.toggle('is-favorite', !!isFavorite);
        });
        templateFavoriteButtons.forEach((btn) => {
            const key = btn.dataset.templateFav;
            const isFavorite = key && favoriteTemplates.has(key);
            btn.classList.toggle('active', !!isFavorite);
            btn.textContent = isFavorite ? 'Saved' : 'Save';
        });
    }

    function applyTemplateFilter(filterKey) {
        let visibleCount = 0;
        templateCards.forEach((card) => {
            const key = card.dataset.templateKey;
            const category = card.dataset.templateCategory || 'misc';
            const isFavorite = key && favoriteTemplates.has(key);
            const shouldShow =
                filterKey === 'all' ||
                (filterKey === 'favorite' ? isFavorite : category === filterKey);
            card.classList.toggle('hidden', !shouldShow);
            if (shouldShow) {
                visibleCount += 1;
            }
        });
        if (templateEmptyState) {
            templateEmptyState.classList.toggle('hidden', visibleCount > 0);
        }
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function renderTimeline() {
        if (!timelineList) {
            return;
        }
        if (!timelineEntries.length) {
            timelineList.innerHTML =
                '<li class="text-xs text-gray-500">No timeline entries yet. Start customizing to build your history.</li>';
            return;
        }
        const items = timelineEntries
            .slice()
            .reverse()
            .map((entry) => {
                const timeLabel = entry.timestamp.toLocaleTimeString();
                const escapedLabel = escapeHtml(entry.label);
                return `
                    <li class="timeline-entry" data-timeline-entry="${entry.id}">
                        <img class="timeline-thumb" src="${entry.thumbnail}" alt="Timeline snapshot">
                        <div class="timeline-meta">
                            <span>${escapedLabel}</span>
                            <span>${escapeHtml(timeLabel)}</span>
                        </div>
                        <div class="timeline-actions">
                            <button type="button" class="btn btn-outline" data-timeline-restore="${entry.id}">Restore</button>
                        </div>
                    </li>
                `;
            })
            .join('');
        timelineList.innerHTML = items;
    }

    function captureThumbnailFromImageData(imageData) {
        const tempCanvas = document.createElement('canvas');
        tempCanvas.width = imageData.width;
        tempCanvas.height = imageData.height;
        const tempCtx = tempCanvas.getContext('2d');
        if (!tempCtx) {
            return DEFAULT_PREVIEW_PLACEHOLDER;
        }
        tempCtx.putImageData(imageData, 0, 0);
        const thumbCanvas = document.createElement('canvas');
        thumbCanvas.width = 96;
        thumbCanvas.height = 96;
        const thumbCtx = thumbCanvas.getContext('2d');
        if (!thumbCtx) {
            return DEFAULT_PREVIEW_PLACEHOLDER;
        }
        thumbCtx.drawImage(tempCanvas, 0, 0, thumbCanvas.width, thumbCanvas.height);
        return thumbCanvas.toDataURL('image/png');
    }

    function recordTimelineEntry(label) {
        if (!paintContext) {
            return;
        }
        const lastEntry = timelineEntries[timelineEntries.length - 1];
        const now = new Date();
        if (lastEntry && lastEntry.label === label && now - lastEntry.timestamp < 500) {
            return;
        }
        timelineEntryCounter += 1;
        const imageData = paintContext.getImageData(0, 0, 1024, 1024);
        const entry = {
            id: timelineEntryCounter,
            label,
            timestamp: now,
            imageData,
            thumbnail: captureThumbnailFromImageData(imageData),
        };
        timelineEntries.push(entry);
        if (timelineEntries.length > 12) {
            timelineEntries.shift();
        }
        renderTimeline();
    }

    function restoreTimelineEntry(entryId) {
        const entry = timelineEntries.find((item) => item.id === entryId);
        if (!entry || !paintContext) {
            return;
        }
        paintContext.putImageData(entry.imageData, 0, 0);
        paintTexture.update();
        saveState();
        evaluateArtworkState();
        recordTimelineEntry(`Restored #${entry.id}: ${entry.label}`);
    }

    renderTimeline();
    updateTemplateFavoriteUI();
    applyTemplateFilter('all');

    function syncSwatchSelection(color) {
        const normalized = (color || '').toLowerCase();
        let matched = false;
        colorSwatches.forEach((swatch) => {
            if (!swatch.dataset.swatch) {
                swatch.classList.remove('active');
                return;
            }
            const swatchColor = swatch.dataset.swatch.toLowerCase();
            const isMatch = swatchColor === normalized;
            swatch.classList.toggle('active', isMatch);
            if (isMatch) {
                matched = true;
                activeSwatch = swatch;
            }
        });
        if (!matched) {
            activeSwatch = null;
        }
    }

    function placeImageAtLastPointer(dataURL, sizeMultiplier = 4, timelineLabel = 'Artwork added') {
        if (!dataURL || !paintContext) {
            return;
        }
        const img = new Image();
        img.onload = () => {
            saveState();
            const logoSizeSlider = document.getElementById('logoSize');
            const baseSize = logoSizeSlider ? Number(logoSizeSlider.value) || 40 : 40;
            const logoSize = baseSize * sizeMultiplier;
            const x = (lastPointerCoordinates?.x ?? 512) - logoSize / 2;
            const y = (lastPointerCoordinates?.y ?? 512) - logoSize / 2;
            paintContext.drawImage(img, x, y, logoSize, logoSize);
            paintTexture.update();
            markArtworkAdded();
            recordTimelineEntry(timelineLabel);
        };
        img.onerror = () => {
            console.warn('Failed to load template image');
        };
        img.src = dataURL;
    }

    function generateTemplateDataURL(templateKey) {
        const canvasTemp = document.createElement('canvas');
        canvasTemp.width = 512;
        canvasTemp.height = 512;
        const ctx = canvasTemp.getContext('2d');
        if (!ctx) {
            return null;
        }
        ctx.clearRect(0, 0, canvasTemp.width, canvasTemp.height);
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvasTemp.width, canvasTemp.height);

        switch (templateKey) {
            case 'sunset': {
                const gradient = ctx.createLinearGradient(0, 0, canvasTemp.width, canvasTemp.height);
                gradient.addColorStop(0, '#fb7185');
                gradient.addColorStop(1, '#f97316');
                ctx.fillStyle = gradient;
                ctx.fillRect(0, 0, canvasTemp.width, canvasTemp.height);
                break;
            }
            case 'aurora': {
                const gradient = ctx.createLinearGradient(0, canvasTemp.height, canvasTemp.width, 0);
                gradient.addColorStop(0, '#14b8a6');
                gradient.addColorStop(1, '#6366f1');
                ctx.fillStyle = gradient;
                ctx.fillRect(0, 0, canvasTemp.width, canvasTemp.height);
                ctx.globalAlpha = 0.35;
                ctx.fillStyle = '#0f172a';
                ctx.beginPath();
                ctx.arc(256, 180, 140, 0, Math.PI * 2);
                ctx.fill();
                ctx.globalAlpha = 1;
                break;
            }
            case 'monogram': {
                const gradient = ctx.createLinearGradient(0, 0, 0, canvasTemp.height);
                gradient.addColorStop(0, '#facc15');
                gradient.addColorStop(1, '#f97316');
                ctx.fillStyle = gradient;
                ctx.fillRect(0, 0, canvasTemp.width, canvasTemp.height);
                ctx.fillStyle = 'rgba(0,0,0,0.18)';
                ctx.fillRect(40, 60, 432, 392);
                ctx.fillStyle = '#ffffff';
                ctx.beginPath();
                ctx.arc(256, 256, 150, 0, Math.PI * 2);
                ctx.fill();
                ctx.fillStyle = '#111827';
                ctx.font = 'bold 180px "Poppins", sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText('MC', 256, 256);
                break;
            }
            case 'stripes': {
                ctx.fillStyle = '#0ea5e9';
                ctx.fillRect(0, 0, canvasTemp.width, canvasTemp.height);
                ctx.fillStyle = '#1e293b';
                for (let i = -512; i < 512; i += 40) {
                    ctx.beginPath();
                    ctx.moveTo(i, 0);
                    ctx.lineTo(i + 20, 0);
                    ctx.lineTo(i + 540, 512);
                    ctx.lineTo(i + 520, 512);
                    ctx.closePath();
                    ctx.fill();
                }
                ctx.fillStyle = 'rgba(255,255,255,0.2)';
                ctx.fillRect(40, 120, 432, 64);
                ctx.fillRect(40, 280, 432, 48);
                break;
            }
            default:
                break;
        }

        return canvasTemp.toDataURL('image/png');
    }

    async function captureDesignImages(size = { width: 512, height: 512 }) {
        const camera = scene.activeCamera;
        const front = await capturePreview(camera, size.width, size.height, -Math.PI / 2, Math.PI / 2.5);
        const back = await capturePreview(camera, size.width, size.height, Math.PI / 2, Math.PI / 2.5);
        return { front, back };
    }

    function withButtonState(button, pendingLabel, fn) {
        if (!button || typeof fn !== 'function') {
            return;
        }
        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = pendingLabel;
        return Promise.resolve()
            .then(fn)
            .finally(() => {
                button.disabled = false;
                button.textContent = originalText;
            });
    }

    function toggleShortcutOverlay(visible) {
        if (!shortcutOverlay) {
            return;
        }
        shortcutOverlay.classList.toggle('visible', visible);
    }

    clearPreviewCarousel();
    setChecklistItem('base', false);
    setChecklistItem('artwork', false);

    const createScene = async function () {
        scene = new BABYLON.Scene(engine);
        scene.clearColor = new BABYLON.Color4(0, 0, 0, 0);

        const camera = new BABYLON.ArcRotateCamera(
            'camera',
            -Math.PI / 2,
            Math.PI / 2.5,
            3,
            new BABYLON.Vector3(0, -0.5, 0),
            scene,
        );
        camera.attachControl(canvas, true);
        camera.wheelPrecision = 50;
        camera.lowerRadiusLimit = 2;
        camera.upperRadiusLimit = 5;

        const light = new BABYLON.HemisphericLight('light', new BABYLON.Vector3(0, 1, 0), scene);
        light.intensity = 1.2;
        const light2 = new BABYLON.HemisphericLight('light2', new BABYLON.Vector3(0, 0, 0), scene);
        light2.intensity = 0.5;

        paintTexture = new BABYLON.DynamicTexture('paintTexture', { width: 1024, height: 1024 }, scene, false);
        paintContext = paintTexture.getContext({ willReadFrequently: true });

        await loadModel('tshirt');
        currentModelType = 'tshirt';
        updateBaseColor('#cccccc');
        currentBaseColor = '#cccccc';
        saveState();
        modelLoaded = true;

        if (initialDesignId) {
            await loadExistingDesign(initialDesignId);
        } else if (remixPayload && remixPayload.texture) {
            try {
                const parts = [];
                if (remixPayload.label) {
                    parts.push(remixPayload.label);
                }
                if (remixPayload.origin) {
                    parts.push(`from ${remixPayload.origin}`);
                }
                const timelineMessage = parts.length > 0
                    ? `Remix applied (${parts.join(' ')})`
                    : 'Remix applied';
                await applyTextureFromImage(remixPayload.texture, { timelineMessage });
            } catch (remixError) {
                console.warn('Failed to apply remix texture:', remixError);
            }
        } else if (inspirationTexture) {
            try {
                await applyTextureFromImage(inspirationTexture, { timelineMessage: 'Inspiration applied' });
            } catch (textureError) {
                console.warn('Failed to apply inspiration texture:', textureError);
            }
        }

        return scene;
    };

    async function loadModel(type) {
        const targetType = Object.prototype.hasOwnProperty.call(availableModels, type) ? type : 'tshirt';
        const fileBase = availableModels[targetType] || availableModels.tshirt;
        const file = `${fileBase}?v=${assetVersion}`;
        try {
            if (model) {
                model.getScene()
                    .meshes.slice()
                    .forEach((mesh) => {
                        if (mesh.name && mesh.name.startsWith('importedModel')) {
                            mesh.dispose();
                        }
                    });
                model = null;
            }

            const result = await BABYLON.SceneLoader.ImportMeshAsync('', 'models/', file, scene);
            const root = result.meshes[0];
            root.name = 'importedModelRoot';
            result.meshes.forEach((mesh, index) => {
                if (mesh instanceof BABYLON.Mesh) {
                    mesh.name = `importedModel_${index}`;
                    mesh.material = mesh.material || new BABYLON.PBRMaterial(`mat_${index}`, scene);
                    mesh.material.albedoTexture = paintTexture;
                    mesh.material.metallic = 0.1;
                    mesh.material.roughness = 0.8;
                    mesh.material.backFaceCulling = false;
                }
            });
            model = root;
            model.scaling.scaleInPlace(2.5);
            model.position.y = -4;
            highlightActiveModel(targetType);
            currentModelType = targetType;
        } catch (e) {
            console.error('Error loading model:', e);
        }
    }

    function highlightActiveModel(type) {
        modelButtons.forEach((btn) => {
            btn.classList.toggle('active', btn.dataset.model === type);
        });
    }

    function updateBaseColor(color) {
        if (!paintContext) return;
        const historySizeBefore = history.length;
        saveState();
        paintContext.fillStyle = color;
        paintContext.fillRect(0, 0, 1024, 1024);
        paintTexture.update();
        const normalized = (color || '').toLowerCase();
        const previousColor = (currentBaseColor || '').toLowerCase();
        currentBaseColor = color;
        setChecklistItem('base', normalized !== DEFAULT_BASE_COLOR);
        syncSwatchSelection(normalized);
        if (historySizeBefore === 0 && !initialTimelineCaptured) {
            recordTimelineEntry('Initial canvas');
            initialTimelineCaptured = true;
        } else if (normalized !== previousColor) {
            recordTimelineEntry('Base color updated');
        }
    }

    function saveState() {
        if (!paintContext) return;
        const imageData = paintContext.getImageData(0, 0, 1024, 1024);
        history.push(imageData);
        if (history.length > 20) history.shift();
    }

    function undo() {
        if (!paintContext || history.length <= 1) return;
        history.pop();
        const lastState = history[history.length - 1];
        paintContext.putImageData(lastState, 0, 0);
        paintTexture.update();
        evaluateArtworkState();
        recordTimelineEntry('Undo applied');
    }

    function getPointerCoordinates(pickInfo) {
        if (pickInfo.hit && pickInfo.getTextureCoordinates) {
            const texCoords = pickInfo.getTextureCoordinates();
            if (texCoords) {
                return { x: texCoords.x * 1024, y: (1 - texCoords.y) * 1024 };
            }
        }
        return null;
    }

    async function capturePreview(camera, width, height, alpha, beta) {
        const originalAlpha = camera.alpha;
        const originalBeta = camera.beta;
        const originalRadius = camera.radius;

        camera.alpha = alpha;
        camera.beta = beta;
        camera.radius = 2.8;

        await scene.whenReadyAsync();
        scene.render();

        const previewData = await BABYLON.Tools.CreateScreenshotUsingRenderTargetAsync(engine, camera, {
            width,
            height,
        });

        camera.alpha = originalAlpha;
        camera.beta = originalBeta;
        camera.radius = originalRadius;

        return previewData;
    }

    function setupEventListeners() {
        let isDrawing = false;
        let strokeDrawn = false;

        scene.onPointerDown = (evt, pickInfo) => {
            const coords = getPointerCoordinates(pickInfo);
            if (coords) lastPointerCoordinates = coords;

            if (drawingModeEnabled) {
                isDrawing = true;
                strokeDrawn = false;
                saveState();
                if (coords) drawCircle(coords.x, coords.y);
            }
        };

        scene.onPointerMove = (evt, pickInfo) => {
            if (drawingModeEnabled && isDrawing) {
                const coords = getPointerCoordinates(pickInfo);
                if (coords) drawCircle(coords.x, coords.y);
            }
        };

        scene.onPointerUp = () => {
            if (isDrawing && strokeDrawn) {
                recordTimelineEntry('Brush stroke');
            }
            isDrawing = false;
        };

        function drawCircle(x, y) {
            const brushSize = document.getElementById('brushSize').value;
            const brushColor = document.getElementById('brushColor').value;
            paintContext.fillStyle = brushColor;
            paintContext.beginPath();
            paintContext.arc(x, y, brushSize, 0, 2 * Math.PI);
            paintContext.fill();
            paintTexture.update();
            markArtworkAdded();
            strokeDrawn = true;
        }

        document.getElementById('drawToggleBtn').addEventListener('click', () => {
            drawingModeEnabled = !drawingModeEnabled;
            const btn = document.getElementById('drawToggleBtn');
            btn.textContent = drawingModeEnabled ? 'Drawing is ON' : 'Drawing is OFF';
            btn.classList.toggle('btn-success');
            canvas.classList.toggle('drawing-enabled');
        });

        const logoInputEl = document.getElementById('logoUpload');
        if (logoInputEl) {
            logoInputEl.addEventListener('change', (e) => {
                const file = e.target.files && e.target.files[0];
                if (!file) return;
                const fileName = file.name ? file.name.split(/[/\\]/).pop() : '';
                const truncatedName = fileName && fileName.length > 24 ? `${fileName.slice(0, 24)}...` : fileName;
                const timelineLabel = truncatedName ? `Logo added: ${truncatedName}` : 'Logo upload';
                const reader = new FileReader();
                reader.onload = (event) => {
                    placeImageAtLastPointer(String(event.target?.result || ''), 4, timelineLabel);
                };
                reader.readAsDataURL(file);
                e.target.value = '';
            });
        }

        document.getElementById('addTextBtn').addEventListener('click', () => {
            const text = document.getElementById('customText').value.trim();
            if (!text) return;
            saveState();
            const fontSize = document.getElementById('fontSize').value;
            const fontStyle = document.getElementById('fontStyle').value;
            paintContext.font = `bold ${fontSize}px ${fontStyle}`;
            paintContext.fillStyle = 'black';
            paintContext.textAlign = 'center';
            paintContext.textBaseline = 'middle';
            paintContext.fillText(text, lastPointerCoordinates.x, lastPointerCoordinates.y);
            paintTexture.update();
            document.getElementById('customText').value = '';
            markArtworkAdded();
            const truncated = text.length > 24 ? `${text.slice(0, 24)}...` : text;
            recordTimelineEntry(`Text added: ${truncated}`);
        });

        baseColorInput.addEventListener('input', (e) => {
            if (!modelLoaded) return;
            updateBaseColor(e.target.value);
        });

        colorSwatches.forEach((swatch) => {
            const swatchColor = swatch.dataset.swatch;
            if (!swatchColor) {
                return;
            }
            if (!swatch.style.background) {
                swatch.style.background = swatchColor;
            }
            swatch.addEventListener('click', () => {
                if (!modelLoaded) return;
                baseColorInput.value = swatchColor;
                updateBaseColor(swatchColor);
            });
        });

        templateButtons.forEach((btn) => {
            const templateKey = btn.dataset.templateApply;
            if (!templateKey) {
                return;
            }
            btn.addEventListener('click', () => {
                if (!modelLoaded) return;
                const dataURL = generateTemplateDataURL(templateKey);
                if (dataURL) {
                    const scale = templateKey === 'stripes' ? 5 : 4;
                    const templateLabel = btn.querySelector('.template-name')?.textContent?.trim() || templateKey;
                    placeImageAtLastPointer(dataURL, scale, `Template applied: ${templateLabel}`);
                }
            });
        });

        modelButtons.forEach((btn) => {
            btn.addEventListener('click', async () => {
                const type = btn.dataset.model;
                await loadModel(type);
            });
        });

        document.getElementById('undoBtn').addEventListener('click', undo);

        document.getElementById('resetBtn').addEventListener('click', () => {
            history = [];
            updateBaseColor(DEFAULT_BASE_COLOR);
            setChecklistItem('artwork', false);
            clearPreviewCarousel();
            recordTimelineEntry('Design reset');
        });

        ['logoSize', 'fontSize', 'brushSize'].forEach((id) => {
            const slider = document.getElementById(id);
            const display = document.getElementById(`${id}Value`);
            if (slider && display) {
                slider.addEventListener('input', () => (display.textContent = slider.value));
            }
        });

        if (refreshPreviewBtn) {
            refreshPreviewBtn.addEventListener('click', () =>
                withButtonState(refreshPreviewBtn, 'Refreshing...', async () => {
                    const { front, back } = await captureDesignImages();
                    updatePreviewCarousel(front, back);
                }),
            );
        }

        if (toggleShortcutsBtn) {
            toggleShortcutsBtn.addEventListener('click', () => toggleShortcutOverlay(true));
        }
        if (closeShortcutOverlayBtn) {
            closeShortcutOverlayBtn.addEventListener('click', () => toggleShortcutOverlay(false));
        }
        if (shortcutOverlay) {
            shortcutOverlay.addEventListener('click', (evt) => {
                if (evt.target === shortcutOverlay) {
                    toggleShortcutOverlay(false);
                }
            });
        }

        templateFilterButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                templateFilterButtons.forEach((filterBtn) => filterBtn.classList.remove('active'));
                btn.classList.add('active');
                const filterKey = btn.dataset.templateFilter || 'all';
                applyTemplateFilter(filterKey);
            });
        });

        templateFavoriteButtons.forEach((btn) => {
            btn.addEventListener('click', (event) => {
                event.stopPropagation();
                const key = btn.dataset.templateFav;
                if (!key) {
                    return;
                }
                if (favoriteTemplates.has(key)) {
                    favoriteTemplates.delete(key);
                } else {
                    favoriteTemplates.add(key);
                }
                persistFavoriteTemplates();
                updateTemplateFavoriteUI();
                applyTemplateFilter((document.querySelector('.template-filter-btn.active')?.dataset.templateFilter) || 'all');
            });
        });

        if (timelineList) {
            timelineList.addEventListener('click', (event) => {
                const restoreBtn = event.target.closest('[data-timeline-restore]');
                if (!restoreBtn) {
                    return;
                }
                const entryId = Number(restoreBtn.dataset.timelineRestore);
                if (!Number.isNaN(entryId)) {
                    restoreTimelineEntry(entryId);
                }
            });
        }

        document.addEventListener('keydown', (evt) => {
            const tagName = document.activeElement && document.activeElement.tagName;
            const isTypingContext = tagName === 'INPUT' || tagName === 'TEXTAREA' || document.activeElement?.isContentEditable;
            if (evt.key === 'Escape') {
                toggleShortcutOverlay(false);
                return;
            }
            if (isTypingContext && !evt.metaKey && !evt.ctrlKey) {
                return;
            }
            const key = evt.key.toLowerCase();
            if ((evt.ctrlKey || evt.metaKey) && key === 'z') {
                evt.preventDefault();
                undo();
            } else if (!evt.ctrlKey && !evt.metaKey && key === 'd') {
                evt.preventDefault();
                document.getElementById('drawToggleBtn')?.click();
            } else if (!evt.ctrlKey && !evt.metaKey && key === 'p') {
                evt.preventDefault();
                refreshPreviewBtn?.click();
            } else if (evt.key === '?') {
                evt.preventDefault();
                const isVisible = shortcutOverlay?.classList.contains('visible');
                toggleShortcutOverlay(!isVisible);
            }
        });

        document.getElementById('saveDesignBtn').addEventListener('click', async () => {
            if (!modelLoaded) {
                alert('Model not loaded yet.');
                return;
            }

            const btn = document.getElementById('saveDesignBtn');
            btn.disabled = true;
            btn.textContent = 'Capturing Previews...';

            const previewSize = { width: 512, height: 512 };

            try {
                const { front: frontPreview, back: backPreview } = await captureDesignImages(previewSize);
                updatePreviewCarousel(frontPreview, backPreview);
                const textureMap = paintContext.canvas.toDataURL('image/png');

                btn.textContent = 'Saving to Database...';

                const designData = {
                    designId: editingDesignId,
                    textureMap,
                    images: {
                        front: frontPreview,
                        back: backPreview,
                    },
                    design: {
                        apparelType: currentModelType,
                        baseColor: currentBaseColor,
                    },
                };

                try {
                    const cached = {
                        ...designData,
                        designId: editingDesignId,
                        savedAt: new Date().toISOString(),
                    };
                    localStorage.setItem('mystic-last-design', JSON.stringify(cached));
                } catch (storageError) {
                    console.warn('Unable to cache design locally:', storageError);
                }

                const requestHeaders = {
                    'Content-Type': 'application/json',
                };
                if (csrfToken) {
                    requestHeaders['X-CSRF-Token'] = csrfToken;
                }

                const response = await fetch('save_design.php', {
                    method: 'POST',
                    headers: requestHeaders,
                    body: JSON.stringify(designData),
                });

                const responseBody = await response.text();
                let result;
                try {
                    result = JSON.parse(responseBody);
                } catch (parseError) {
                    throw new Error(`Invalid response (${response.status}): ${responseBody.substring(0, 120)}`);
                }

                if (!response.ok || !result.success) {
                    const message = result?.error || `Request failed with status ${response.status}`;
                    throw new Error(message);
                }

                alert(result.message);
                window.location.href = 'cart.php';
            } catch (error) {
                console.error('Failed to save design:', error);
                alert(`An error occurred while saving: ${error.message}`);
            } finally {
                btn.disabled = false;
                btn.textContent = 'Save & Add to Cart';
            }
        });
    }

    async function loadExistingDesign(designId) {
        try {
            const response = await fetch(`get_design.php?id=${encodeURIComponent(designId)}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const payloadText = await response.text();
            let payload;
            try {
                payload = JSON.parse(payloadText);
            } catch (parseError) {
                throw new Error(`Invalid response when loading design (${response.status})`);
            }

            if (!response.ok || !payload.success) {
                throw new Error(payload?.error || 'Unable to load design');
            }

                editingDesignId = payload.design_id || initialDesignId;
            const designMeta = payload.design_json || {};
            const targetModel = designMeta.apparelType || payload.apparel_type || 'tshirt';
            if (targetModel !== currentModelType) {
                await loadModel(targetModel);
            }

            const effectiveColor = designMeta.baseColor || payload.base_color;
            if (effectiveColor) {
                currentBaseColor = effectiveColor;
                if (baseColorInput) {
                    baseColorInput.value = effectiveColor;
                }
                updateBaseColor(effectiveColor);
            }

            const textureSource = payload.texture_map_url || designMeta.textureMapUrl || null;
            const fallbackPreview = payload.images?.front || null;
            if (textureSource) {
                await applyTextureFromImage(textureSource, { timelineMessage: 'Loaded saved design' });
            } else if (fallbackPreview) {
                await applyTextureFromImage(fallbackPreview, { timelineMessage: 'Loaded saved design' });
            }
        } catch (error) {
            console.error('Failed to load saved design:', error);
        }
    }

    function applyTextureFromImage(imageUrl, options = {}) {
        const {
            timelineMessage = null,
            markArtwork = true,
            bustCache = true,
        } = options;

        return applyDesignTexture({
            textureUrl: imageUrl,
            paintContext,
            paintTexture,
            saveState,
            markArtworkAdded: markArtwork ? markArtworkAdded : null,
            recordTimelineEntry,
            timelineMessage,
            bustCache,
        });
    }

    createScene().then(() => {
        if (scene) {
            setupEventListeners();
            engine.runRenderLoop(() => {
                if (scene.isReady()) scene.render();
            });
        }
    });

    window.addEventListener('resize', () => engine.resize());
</script>