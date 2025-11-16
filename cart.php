<?php
include 'header.php'; 
include 'db_connection.php';
require_once __DIR__ . '/session_helpers.php';
require_once __DIR__ . '/core/drop_promotions.php';
require_once __DIR__ . '/core/cart_snapshot.php';

$cartSnapshot = mystic_cart_snapshot($conn);
$cart_items = $cartSnapshot['lines'];
$total_price = $cartSnapshot['subtotal'];
$total_savings = $cartSnapshot['savings'];
$bundle_value = $cartSnapshot['bundle_value'];
$original_total = $cartSnapshot['original_subtotal'];
$total_savings_percent = ($total_savings > 0 && $original_total > 0) ? round(($total_savings / $original_total) * 100) : 0;
$bundleLimitNotice = null;
if (!empty($_SESSION['cart_limit_notice']) && is_array($_SESSION['cart_limit_notice'])) {
    $limitNotice = $_SESSION['cart_limit_notice'];
    $limitCount = isset($limitNotice['limited_to']) ? (int) $limitNotice['limited_to'] : 0;
    if ($limitCount > 0) {
        $bundleLimitNotice = [
            'limit' => $limitCount,
            'slug' => isset($limitNotice['bundle_slug']) ? trim((string) $limitNotice['bundle_slug']) : '',
        ];
    }
    unset($_SESSION['cart_limit_notice']);
}

// Initialize main cart in session if it doesn't exist
if (!isset($_SESSION['cart'])) { $_SESSION['cart'] = []; }

$crossSellBundles = [];

// Build high-stock bundle suggestions so shoppers can add two in-stock items at once.
$crossSellThreshold = 25;
$bundleCandidates = [];
$secondaryCandidates = [];
$inCartIds = array_map('intval', array_keys($_SESSION['cart'] ?? []));
$bundleStmt = $conn->prepare('SELECT inventory_id, product_name, price, image_url, stock_qty FROM inventory WHERE stock_qty >= ? AND inventory_id <> 4 AND (is_archived = 0 OR is_archived IS NULL) ORDER BY stock_qty DESC, product_name LIMIT 12');
if ($bundleStmt) {
    $bundleStmt->bind_param('i', $crossSellThreshold);
    if ($bundleStmt->execute()) {
        $candidateResult = $bundleStmt->get_result();
        if ($candidateResult) {
            while ($row = $candidateResult->fetch_assoc()) {
                $candidate = [
                    'inventory_id' => (int)$row['inventory_id'],
                    'product_name' => $row['product_name'],
                    'price' => (float)$row['price'],
                    'image_url' => !empty($row['image_url']) ? $row['image_url'] : 'image/placeholder.png',
                    'stock_qty' => (int)$row['stock_qty']
                ];
                if (in_array($candidate['inventory_id'], $inCartIds, true)) {
                    $secondaryCandidates[] = $candidate;
                } else {
                    $bundleCandidates[] = $candidate;
                }
            }
            $candidateResult->free();
        }
    }
    $bundleStmt->close();
}

if (count($bundleCandidates) < 2) {
    $bundleCandidates = array_merge($bundleCandidates, $secondaryCandidates);
}

$bundleCount = count($bundleCandidates);
$maxBundles = 3;
$usedInventoryIds = [];
for ($i = 0; $i < $bundleCount && count($crossSellBundles) < $maxBundles; $i++) {
    $first = $bundleCandidates[$i];
    if (in_array($first['inventory_id'], $usedInventoryIds, true)) {
        continue;
    }

    for ($j = $i + 1; $j < $bundleCount; $j++) {
        $second = $bundleCandidates[$j];
        if (in_array($second['inventory_id'], $usedInventoryIds, true)) {
            continue;
        }

        $crossSellBundles[] = [
            'title' => $first['product_name'] . ' + ' . $second['product_name'],
            'items' => [$first, $second],
            'combined_price' => $first['price'] + $second['price'],
            'bundle_ids' => $first['inventory_id'] . ',' . $second['inventory_id'],
            'stock_total' => $first['stock_qty'] + $second['stock_qty']
        ];

        $usedInventoryIds[] = $first['inventory_id'];
        $usedInventoryIds[] = $second['inventory_id'];
        break;
    }
}
?>

<style>
    .cart-price-original {
        text-decoration: line-through;
        color: #94a3b8;
        font-size: 0.85rem;
        margin-right: 0.4rem;
        display: inline-block;
    }
    .cart-price-current {
        font-weight: 600;
        color: #0f172a;
    }
    .cart-price-savings {
        color: #047857;
        font-size: 0.78rem;
        font-weight: 600;
        margin-left: 0.5rem;
    }
    .cart-summary-savings {
        color: #047857;
        font-weight: 600;
    }
    @media (prefers-color-scheme: dark) {
        .cart-price-original {
            color: rgba(226, 232, 240, 0.65);
        }
        .cart-price-current {
            color: #e0f2fe;
        }
        .cart-price-savings,
        .cart-summary-savings {
            color: #34d399;
        }
    }
</style>

<div class="page-header">
    <div class="container">
        <h1>Your Shopping Cart</h1>
        <p>Review your items before checkout</p>
    </div>
</div>

<main class="container">
    <?php if ($bundleLimitNotice): ?>
        <div class="alert alert-warning mb-6" role="status">
            <strong>Bundle limit reached<?php if (!empty($bundleLimitNotice['slug'])) { echo ' for ' . htmlspecialchars($bundleLimitNotice['slug']); } ?>.</strong>
            We capped eligible items at <?php echo (int) $bundleLimitNotice['limit']; ?> per cart so everyone gets a fair shot at the freebies.
        </div>
    <?php endif; ?>
    <?php if (empty($cart_items)): ?>
        <div class="empty-state cart-empty">
            <div class="empty-state-icon">
                <i data-feather="shopping-cart"></i>
            </div>
            <h2 class="empty-state-title">Your cart is empty</h2>
            <p class="empty-state-description">
                Looks like you haven't added any items to your cart yet. Start exploring our collection and find something you love!
            </p>
            <div class="empty-state-actions">
                <a href="shop.php" class="btn btn-primary btn-lg">
                    <i data-feather="shopping-bag"></i>
                    Browse Products
                </a>
                <a href="design3d.php" class="btn btn-outline btn-lg">
                    <i data-feather="edit-3"></i>
                    Create Custom Design
                </a>
            </div>
            <div class="empty-state-suggestions">
                <h4>Popular Categories</h4>
                <ul class="empty-state-list">
                    <li>
                        <i data-feather="star" class="empty-state-list-icon"></i>
                        <span>Featured Collections</span>
                    </li>
                    <li>
                        <i data-feather="trending-up" class="empty-state-list-icon"></i>
                        <span>Trending Designs</span>
                    </li>
                    <li>
                        <i data-feather="zap" class="empty-state-list-icon"></i>
                        <span>Best Sellers</span>
                    </li>
                </ul>
            </div>
        </div>
    <?php else: ?>
        <div class="card mb-8">
            <div class="card-body">
                <table class="content-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Total</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_items as $item): ?>
                            <?php
                                $isFreebie = !empty($item['is_freebie']);
                                $isCustom = !empty($item['is_custom']);
                                $bundleWorth = isset($item['bundle_value']) ? (float) $item['bundle_value'] : 0.0;
                                $quantity = (int) ($item['quantity'] ?? 0);
                            ?>
                            <tr>
                                <td>
                                    <div class="flex items-center gap-4">
                                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                        <div>
                                            <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                            <?php if ($isCustom): ?>
                                                <span class="badge badge-info">Custom Design</span>
                                            <?php endif; ?>
                                            <?php if (!empty($item['meta'])): ?>
                                                <p class="text-sm text-muted mt-1"><?php echo htmlspecialchars($item['meta']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($isFreebie): ?>
                                        <span class="cart-price-current">Free</span>
                                        <?php if ($bundleWorth > 0): ?>
                                            <span class="cart-price-savings">Worth ₹<?php echo number_format($bundleWorth, 2); ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if (!empty($item['original_price'])): ?>
                                            <span class="cart-price-original">₹<?php echo number_format($item['original_price'], 2); ?></span>
                                        <?php endif; ?>
                                        <span class="cart-price-current">₹<?php echo number_format($item['price'], 2); ?></span>
                                        <?php if (($item['savings'] ?? 0) > 0): ?>
                                            <span class="cart-price-savings">Save ₹<?php echo number_format($item['savings'], 2); ?><?php if (($item['savings_percent'] ?? 0) > 0) { echo ' (' . (int)$item['savings_percent'] . '%)'; } ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isCustom): ?>
                                        <span class="px-4 py-1 bg-background rounded text-center min-w-12">1</span>
                                    <?php elseif ($isFreebie): ?>
                                        <span class="px-4 py-1 bg-background rounded text-center min-w-12"><?php echo $quantity; ?></span>
                                    <?php else: ?>
                                    <div class="flex items-center gap-2">
                                        <a class="btn btn-sm btn-ghost" href="cart_handler.php?action=update&id=<?php echo $item['id']; ?>&qty=<?php echo max(0, $item['quantity'] - 1); ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token'] ?? ''); ?>">-</a>
                                        <span class="px-4 py-1 bg-background rounded text-center min-w-12"><?php echo $item['quantity']; ?></span>
                                        <a class="btn btn-sm btn-ghost" href="cart_handler.php?action=update&id=<?php echo $item['id']; ?>&qty=<?php echo $item['quantity'] + 1; ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token'] ?? ''); ?>">+</a>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td class="font-semibold">
                                    <?php if ($isFreebie): ?>
                                        <span class="cart-price-current">Free</span>
                                        <?php if ($bundleWorth > 0): ?>
                                            <span class="cart-price-savings">Value ₹<?php echo number_format($bundleWorth, 2); ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if (!empty($item['original_price'])): ?>
                                            <span class="cart-price-original">₹<?php echo number_format($item['original_price'] * $item['quantity'], 2); ?></span>
                                        <?php endif; ?>
                                        <span class="cart-price-current">₹<?php echo number_format($item['subtotal'], 2); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isFreebie): ?>
                                        <span class="text-sm text-muted">Bundle applied</span>
                                    <?php else: ?>
                                        <?php
                                            $removeUrl = 'cart_handler.php?action=remove&id=' . $item['id'];
                                            if ($isCustom && !empty($item['custom_design_id'])) {
                                                $removeUrl .= '&design_id=' . (int) $item['custom_design_id'];
                                            }
                                            $removeUrl .= '&csrf_token=' . urlencode($_SESSION['csrf_token'] ?? '');
                                        ?>
                                        <?php if ($isCustom && !empty($item['custom_design_id'])): ?>
                                            <a href="design3d.php?design_id=<?php echo (int) $item['custom_design_id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                                        <?php endif; ?>
                                        <a href="<?php echo $removeUrl; ?>" class="btn btn-sm btn-danger">Remove</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex justify-between items-center flex-wrap gap-6">
            <a href="shop.php" class="btn btn-outline">← Continue Shopping</a>
            
            <div class="text-right">
                <div class="card p-6">
                    <h3 class="mb-2">Order Summary</h3>
                    <?php if ($total_savings > 0): ?>
                        <div class="flex justify-between items-center mb-2">
                            <span>Original total:</span>
                            <span class="cart-price-original">₹<?php echo number_format($original_total, 2); ?></span>
                        </div>
                        <div class="flex justify-between items-center mb-2">
                            <span>You saved</span>
                            <span class="cart-summary-savings">₹<?php echo number_format($total_savings, 2); ?><?php if ($total_savings_percent > 0) { echo ' (' . (int)$total_savings_percent . '%)'; } ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($bundle_value)): ?>
                        <div class="flex justify-between items-center mb-2">
                            <span>Bundle freebies</span>
                            <span class="cart-summary-savings">₹<?php echo number_format($bundle_value, 2); ?> value</span>
                        </div>
                    <?php endif; ?>
                    <div class="flex justify-between items-center mb-2">
                        <span>Subtotal:</span>
                        <span class="text-2xl font-bold text-primary">₹<?php echo number_format($total_price, 2); ?></span>
                    </div>
                    <p class="text-muted text-sm mb-6">Taxes and shipping calculated at checkout</p>
                    
                    <div class="flex gap-3">
                        <a href="checkout.php" class="btn btn-primary btn-lg">Proceed to Checkout</a>
                        <a href="cart_handler.php?action=clear&csrf_token=<?php echo urlencode($_SESSION['csrf_token'] ?? ''); ?>" class="btn btn-danger btn-lg">Clear Cart</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($crossSellBundles)): ?>
        <style>
            .cross-sell-section { margin-top: 3rem; }
            .cross-sell-section .section-eyebrow { font-size: 0.75rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #6366f1; margin-bottom: 0.35rem; }
            .cross-sell-section .bundle-grid { display: grid; gap: 1.5rem; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); }
            .cross-sell-section .bundle-card { border: 1px solid rgba(15, 23, 42, 0.08); border-radius: 16px; padding: 1.5rem; background: #fff; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08); display: flex; flex-direction: column; gap: 1.25rem; }
            .cross-sell-section .bundle-card h3 { font-size: 1.05rem; margin: 0; color: #0f172a; }
            .cross-sell-section .bundle-meta { display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: #475569; }
            .cross-sell-section .bundle-items { display: flex; flex-direction: column; gap: 1rem; }
            .cross-sell-section .bundle-item { display: flex; gap: 0.85rem; align-items: center; }
            .cross-sell-section .bundle-item img { width: 64px; height: 64px; object-fit: cover; border-radius: 12px; border: 1px solid rgba(148, 163, 184, 0.4); }
            .cross-sell-section .bundle-item-title { font-size: 0.95rem; font-weight: 600; color: #1e293b; margin: 0 0 0.2rem 0; }
            .cross-sell-section .bundle-item-price { font-size: 0.85rem; color: #64748b; }
            .cross-sell-section .bundle-footer { display: flex; flex-direction: column; gap: 0.75rem; }
            .cross-sell-section .bundle-total { display: flex; justify-content: space-between; align-items: baseline; }
            .cross-sell-section .bundle-total span { font-size: 0.85rem; color: #475569; }
            .cross-sell-section .bundle-total strong { font-size: 1.15rem; color: #0f172a; }
            @media (prefers-color-scheme: dark) {
                .cross-sell-section .bundle-card { background: #0f172a; border-color: rgba(148, 163, 184, 0.2); box-shadow: none; }
                .cross-sell-section .bundle-card h3 { color: #f8fafc; }
                .cross-sell-section .bundle-meta { color: #cbd5f5; }
                .cross-sell-section .bundle-item-title { color: #e2e8f0; }
                .cross-sell-section .bundle-item-price { color: rgba(226, 232, 240, 0.75); }
                .cross-sell-section .bundle-item img { border-color: rgba(226, 232, 240, 0.2); }
                .cross-sell-section .bundle-total span { color: rgba(226, 232, 240, 0.7); }
                .cross-sell-section .bundle-total strong { color: #f8fafc; }
            }
        </style>

        <section class="cross-sell-section">
            <div class="card p-8">
                <div class="flex justify-between items-start flex-wrap gap-4 mb-6">
                    <div>
                        <p class="section-eyebrow">Quick add mini bundles</p>
                        <h2 class="text-2xl font-semibold mb-1">Finish your look in two clicks</h2>
                        <p class="text-muted text-sm">Pairs of in-stock essentials that ship immediately.</p>
                    </div>
                    <div class="text-sm text-muted">All picks have <?php echo (int)$crossSellThreshold; ?>+ units ready.</div>
                </div>

                <div class="bundle-grid">
                    <?php foreach ($crossSellBundles as $bundle): ?>
                        <div class="bundle-card">
                            <div>
                                <h3><?php echo htmlspecialchars($bundle['title']); ?></h3>
                                <div class="bundle-meta">Ships now · <?php echo (int)$bundle['stock_total']; ?> units available</div>
                            </div>

                            <div class="bundle-items">
                                <?php foreach ($bundle['items'] as $bundleItem): ?>
                                    <div class="bundle-item">
                                        <img src="<?php echo htmlspecialchars($bundleItem['image_url']); ?>" alt="<?php echo htmlspecialchars($bundleItem['product_name']); ?>">
                                        <div>
                                            <div class="bundle-item-title"><?php echo htmlspecialchars($bundleItem['product_name']); ?></div>
                                            <div class="bundle-item-price">₹<?php echo number_format($bundleItem['price'], 2); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="bundle-footer">
                                <div class="bundle-total">
                                    <span>Bundle total</span>
                                    <strong>₹<?php echo number_format($bundle['combined_price'], 2); ?></strong>
                                </div>
                                <a href="cart_handler.php?action=add_bundle&amp;bundle_ids=<?php echo urlencode($bundle['bundle_ids']); ?>&amp;redirect=cart.php&amp;csrf_token=<?php echo urlencode($_SESSION['csrf_token'] ?? ''); ?>" class="btn btn-primary w-full justify-center">Add Both to Cart</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
</main>

<?php include 'footer.php'; ?>

