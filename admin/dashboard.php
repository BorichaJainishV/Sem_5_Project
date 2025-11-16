<?php
// ---------------------------------------------------------------------
// /admin/dashboard.php - Admin Dashboard (Upgraded)
// ---------------------------------------------------------------------
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['admin_id'])) { header('Location: index.php'); exit(); }
include '../db_connection.php';
require_once 'activity_logger.php';
require_once __DIR__ . '/../core/banner_manager.php';
require_once __DIR__ . '/../core/support_ticket_queue.php';
require_once __DIR__ . '/../core/drop_promotions.php';

if (!function_exists('dashboard_format_inventory_label')) {
    function dashboard_format_inventory_label(array $row): string
    {
        $inventoryId = isset($row['inventory_id']) ? (int) $row['inventory_id'] : 0;
        $name = trim((string) ($row['product_name'] ?? 'Product #' . $inventoryId));
        if ($name === '') {
            $name = 'Product #' . $inventoryId;
        }

        $label = '#' . $inventoryId . ' · ' . $name;
        if (isset($row['price'])) {
            $label .= ' (₹' . number_format((float) $row['price'], 2) . ')';
        }

        return $label;
    }
}

if (!function_exists('dashboard_fetch_inventory_rows')) {
    function dashboard_fetch_inventory_rows(mysqli $conn, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($value) => $value > 0)));
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("SELECT inventory_id, product_name, price FROM inventory WHERE inventory_id IN ($placeholders)");
        if (!$stmt) {
            return [];
        }

        $types = str_repeat('i', count($ids));
        $stmt->bind_param($types, ...$ids);
        $lookup = [];
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $inventoryId = isset($row['inventory_id']) ? (int) $row['inventory_id'] : 0;
                    if ($inventoryId > 0) {
                        $lookup[$inventoryId] = $row;
                    }
                }
                $result->free();
            }
        }
        $stmt->close();

        return $lookup;
    }
}

// --- Fetch Dashboard Statistics ---

// 1. Total Revenue
$revenueResult = $conn->query(
        "SELECT SUM(b.amount) AS total_revenue
         FROM billing b
         INNER JOIN orders o ON o.order_id = b.order_id
         WHERE LOWER(b.status) IN ('paid', 'completed')
             AND LOWER(o.status) IN ('completed', 'paid')"
);
$totalRevenue = $revenueResult->fetch_assoc()['total_revenue'] ?? 0;

// 2. Total Orders
$ordersResult = $conn->query("SELECT COUNT(order_id) as total_orders FROM orders");
$totalOrders = $ordersResult->fetch_assoc()['total_orders'] ?? 0;

// 3. Total Customers
$customersResult = $conn->query("SELECT COUNT(customer_id) as total_customers FROM customer");
$totalCustomers = $customersResult->fetch_assoc()['total_customers'] ?? 0;

// 4. Total Products
$productsResult = $conn->query("SELECT COUNT(inventory_id) as total_products FROM inventory WHERE is_archived = 0 OR is_archived IS NULL");
$totalProducts = $productsResult->fetch_assoc()['total_products'] ?? 0;

// 5. Recent Orders (Last 5)
$recentOrders = $conn->query("
    SELECT o.order_id, o.order_date, o.status, c.name as customer_name, b.amount 
    FROM orders o 
    JOIN customer c ON o.customer_id = c.customer_id 
    LEFT JOIN billing b ON o.order_id = b.order_id 
    ORDER BY o.order_date DESC 
    LIMIT 5
");
$currentFlashBanner = get_active_flash_banner();
$promotionStatusSnapshot = drop_promotion_current_status();
$promotionStateSnapshot = isset($promotionStatusSnapshot['state']) && is_array($promotionStatusSnapshot['state'])
    ? $promotionStatusSnapshot['state']
    : [];
$promotionDashboardStatus = $promotionStatusSnapshot['status'] ?? 'idle';
$promotionDashboardActive = $promotionDashboardStatus === 'active';
$promotionDashboardSlug = trim((string) ($promotionStateSnapshot['active_slug'] ?? ($currentFlashBanner['drop_slug'] ?? '')));
$promotionDashboardType = trim((string) ($promotionStateSnapshot['promotion_type'] ?? ''));
$promotionDashboardFeatureFlags = isset($promotionStateSnapshot['promotion_features']) && is_array($promotionStateSnapshot['promotion_features'])
    ? array_values(array_unique(array_map('strval', $promotionStateSnapshot['promotion_features'])))
    : [];
if (empty($promotionDashboardFeatureFlags) && !empty($currentFlashBanner['promotion_features']) && is_array($currentFlashBanner['promotion_features'])) {
    foreach ($currentFlashBanner['promotion_features'] as $flagCandidate) {
        $flagCandidate = trim((string) $flagCandidate);
        if ($flagCandidate !== '') {
            $promotionDashboardFeatureFlags[] = $flagCandidate;
        }
    }
    $promotionDashboardFeatureFlags = array_values(array_unique($promotionDashboardFeatureFlags));
}
$promotionDashboardFeatureLabels = array_map(static function ($label) {
    return ucwords(str_replace('_', ' ', (string) $label));
}, $promotionDashboardFeatureFlags);

$promotionDashboardMarkdowns = isset($promotionStateSnapshot['applied_markdowns']) && is_array($promotionStateSnapshot['applied_markdowns'])
    ? $promotionStateSnapshot['applied_markdowns']
    : [];
$promotionDashboardPrimaryMarkdown = $promotionDashboardMarkdowns[0] ?? null;
$promotionDashboardMarkdownSummary = null;
if (is_array($promotionDashboardPrimaryMarkdown)) {
    $promotionDashboardMarkdownSummary = [
        'mode' => $promotionDashboardPrimaryMarkdown['mode'] ?? 'percent',
        'value' => isset($promotionDashboardPrimaryMarkdown['value']) ? (float) $promotionDashboardPrimaryMarkdown['value'] : 0.0,
        'scope' => $promotionDashboardPrimaryMarkdown['scope'] ?? 'all_items',
        'skus' => isset($promotionDashboardPrimaryMarkdown['skus']) && is_array($promotionDashboardPrimaryMarkdown['skus'])
            ? $promotionDashboardPrimaryMarkdown['skus']
            : [],
    ];
}

$promotionDashboardBundleRules = isset($promotionStateSnapshot['bundle_rules']) && is_array($promotionStateSnapshot['bundle_rules'])
    ? $promotionStateSnapshot['bundle_rules']
    : [];
$promotionDashboardBundleCount = count($promotionDashboardBundleRules);
$promotionDashboardClearanceSkus = isset($promotionStateSnapshot['clearance_skus']) && is_array($promotionStateSnapshot['clearance_skus'])
    ? array_values(array_unique(array_map('intval', $promotionStateSnapshot['clearance_skus'])))
    : [];
$promotionDashboardClearanceCount = count($promotionDashboardClearanceSkus);
$promotionDashboardCustomReward = isset($promotionStateSnapshot['custom_reward']) && is_array($promotionStateSnapshot['custom_reward'])
    ? $promotionStateSnapshot['custom_reward']
    : null;
$promotionDashboardRewardValue = $promotionDashboardCustomReward && isset($promotionDashboardCustomReward['discount_value'])
    ? (float) $promotionDashboardCustomReward['discount_value']
    : 0.0;

$promotionDashboardFeaturedInventory = isset($promotionStateSnapshot['featured_inventory']) && is_array($promotionStateSnapshot['featured_inventory'])
    ? array_values(array_unique(array_map('intval', $promotionStateSnapshot['featured_inventory'])))
    : [];
if (empty($promotionDashboardFeaturedInventory) && !empty($currentFlashBanner['promotion_featured_inventory']) && is_array($currentFlashBanner['promotion_featured_inventory'])) {
    foreach ($currentFlashBanner['promotion_featured_inventory'] as $rawId) {
        $id = (int) preg_replace('/\D+/', '', (string) $rawId);
        if ($id > 0) {
            $promotionDashboardFeaturedInventory[] = $id;
        }
    }
    $promotionDashboardFeaturedInventory = array_values(array_unique($promotionDashboardFeaturedInventory));
}
$promotionDashboardFeaturedLabels = [];
if (!empty($promotionDashboardFeaturedInventory)) {
    $featuredRows = dashboard_fetch_inventory_rows($conn, $promotionDashboardFeaturedInventory);
    foreach ($promotionDashboardFeaturedInventory as $inventoryId) {
        if (isset($featuredRows[$inventoryId])) {
            $promotionDashboardFeaturedLabels[] = dashboard_format_inventory_label($featuredRows[$inventoryId]);
        } else {
            $promotionDashboardFeaturedLabels[] = '#' . $inventoryId;
        }
    }
}

$promotionDashboardWarnings = [];
if (in_array('price_markdown', $promotionDashboardFeatureFlags, true)) {
    if (!$promotionDashboardMarkdownSummary || ($promotionDashboardMarkdownSummary['value'] ?? 0.0) <= 0.0) {
        $promotionDashboardWarnings[] = 'Markdown module active without a discount value.';
    }
    if ($promotionDashboardMarkdownSummary && ($promotionDashboardMarkdownSummary['scope'] ?? '') === 'sku_list' && empty($promotionDashboardMarkdownSummary['skus'])) {
        $promotionDashboardWarnings[] = 'Markdown scope targets select SKUs, but none are set.';
    }
}
if (in_array('bundle_bogo', $promotionDashboardFeatureFlags, true) && $promotionDashboardBundleCount === 0) {
    $promotionDashboardWarnings[] = 'Bundle freebies are enabled without any qualifying rules.';
}
if (in_array('clearance', $promotionDashboardFeatureFlags, true) && $promotionDashboardClearanceCount === 0) {
    $promotionDashboardWarnings[] = 'Clearance is toggled on without linked inventory.';
}
if (in_array('custom_design_reward', $promotionDashboardFeatureFlags, true) && $promotionDashboardRewardValue <= 0.0) {
    $promotionDashboardWarnings[] = 'Creator reward is enabled but the payout amount is zero.';
}
$recentAdminActivity = get_recent_admin_activity(5);

$chartRangeDays = 14;
$revenueOrderTrend = $conn->query("\n    SELECT DATE(o.order_date) AS order_day,\n           COUNT(DISTINCT o.order_id) AS order_count,\n           COALESCE(SUM(CASE WHEN LOWER(b.status) IN ('paid', 'completed') THEN b.amount ELSE 0 END), 0) AS total_revenue\n    FROM orders o\n    LEFT JOIN billing b ON o.order_id = b.order_id\n    WHERE o.order_date >= DATE_SUB(CURDATE(), INTERVAL {$chartRangeDays} DAY)\n    GROUP BY order_day\n    ORDER BY order_day ASC\n");

$trendMap = [];
if ($revenueOrderTrend) {
    while ($row = $revenueOrderTrend->fetch_assoc()) {
        $dayKey = $row['order_day'] ?? null;
        if ($dayKey) {
            $trendMap[$dayKey] = [
                'orders' => (int)($row['order_count'] ?? 0),
                'revenue' => (float)($row['total_revenue'] ?? 0.0),
            ];
        }
    }
}

$chartLabels = [];
$chartOrdersSeries = [];
$chartRevenueSeries = [];

$startDate = new DateTime('today');
$startDate->modify('-' . ($chartRangeDays - 1) . ' days');

for ($i = 0; $i < $chartRangeDays; $i++) {
    $currentDate = clone $startDate;
    $currentDate->modify('+' . $i . ' days');
    $dayKey = $currentDate->format('Y-m-d');
    $chartLabels[] = $currentDate->format('M d');
    $chartOrdersSeries[] = $trendMap[$dayKey]['orders'] ?? 0;
    $chartRevenueSeries[] = round($trendMap[$dayKey]['revenue'] ?? 0, 2);
}

$chartPayload = [
    'labels' => $chartLabels,
    'orders' => $chartOrdersSeries,
    'revenue' => $chartRevenueSeries,
];

$lowStockThreshold = 10;
$lowStockItems = [];
$lowStockStmt = $conn->prepare("SELECT inventory_id, product_name, stock_qty FROM inventory WHERE (is_archived = 0 OR is_archived IS NULL) AND stock_qty <= ? ORDER BY stock_qty ASC, product_name ASC LIMIT 8");
if ($lowStockStmt) {
    $lowStockStmt->bind_param('i', $lowStockThreshold);
    if ($lowStockStmt->execute()) {
        $lowStockResult = $lowStockStmt->get_result();
        if ($lowStockResult) {
            while ($row = $lowStockResult->fetch_assoc()) {
                $lowStockItems[] = $row;
            }
        }
    }
    $lowStockStmt->close();
}
$lowStockCount = count($lowStockItems);

$openSupportTicketCount = count_open_support_tickets();
$recentSupportTickets = get_support_tickets('open', 3);
?>
<?php
$ADMIN_TITLE = 'Admin Dashboard';
$ADMIN_BODY_CLASS = 'min-h-screen bg-gradient-to-br from-slate-900 via-indigo-900 to-slate-800 text-slate-50';
require_once __DIR__ . '/_header.php';
?>
<div class="flex-1 p-10">
        <h1 class="text-3xl font-bold mb-6 text-white">Welcome, <?php echo htmlspecialchars($_SESSION['admin_name']); ?>!</h1>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-6 mb-8">
            <div class="bg-white/10 border border-white/10 p-6 rounded-2xl shadow-lg flex items-center backdrop-blur">
                <i class="fas fa-dollar-sign text-4xl text-green-500 mr-4"></i>
                <div>
                    <h2 class="text-indigo-100 text-lg">Total Revenue</h2>
                    <p class="text-2xl font-bold text-white">₹<?php echo number_format($totalRevenue, 2); ?></p>
                </div>
            </div>
            <div class="bg-white/10 border border-white/10 p-6 rounded-2xl shadow-lg flex items-center backdrop-blur">
                <i class="fas fa-box-open text-4xl text-blue-500 mr-4"></i>
                <div>
                    <h2 class="text-indigo-100 text-lg">Total Orders</h2>
                    <p class="text-2xl font-bold text-white"><?php echo $totalOrders; ?></p>
                </div>
            </div>
            <div class="bg-white/10 border border-white/10 p-6 rounded-2xl shadow-lg flex items-center backdrop-blur">
                <i class="fas fa-users text-4xl text-purple-500 mr-4"></i>
                <div>
                    <h2 class="text-indigo-100 text-lg">Total Customers</h2>
                    <p class="text-2xl font-bold text-white"><?php echo $totalCustomers; ?></p>
                </div>
            </div>
             <div class="bg-white/10 border border-white/10 p-6 rounded-2xl shadow-lg flex items-center backdrop-blur">
                <i class="fas fa-warehouse text-4xl text-yellow-500 mr-4"></i>
                <div>
                    <h2 class="text-indigo-100 text-lg">Total Products</h2>
                    <p class="text-2xl font-bold text-white"><?php echo $totalProducts; ?></p>
                </div>
            </div>
            <div class="bg-white/10 border border-white/10 p-6 rounded-2xl shadow-lg flex items-center backdrop-blur">
                <i class="fas fa-headset text-4xl text-rose-400 mr-4"></i>
                <div>
                    <h2 class="text-indigo-100 text-lg">Open Support Tickets</h2>
                    <p class="text-2xl font-bold text-white"><?php echo $openSupportTicketCount; ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white/10 border border-white/10 p-6 rounded-2xl shadow-lg backdrop-blur mb-8">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h2 class="text-2xl font-bold text-white">Revenue vs Orders</h2>
                    <p class="text-sm text-indigo-100/80">Fourteen-day trend of paid revenue alongside order volume.</p>
                </div>
            </div>
            <div class="mt-6">
                <canvas id="revenueOrdersChart" height="220"></canvas>
            </div>
        </div>

        <div class="bg-white/10 border border-white/10 p-6 rounded-2xl shadow-lg backdrop-blur mb-8">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h2 class="text-2xl font-bold text-white">Marketing Snapshot</h2>
                    <p class="text-sm text-indigo-100/80">Preview the flash banner currently configured for the storefront.</p>
                </div>
                <a href="marketing.php" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-md shadow hover:bg-indigo-700 transition">
                    Manage banner
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7l5 5-5 5M6 12h12" /></svg>
                </a>
            </div>

            <?php if ($currentFlashBanner): ?>
                <?php
                $variant = $currentFlashBanner['variant'] ?? 'promo';
                $gradientMap = [
                    'promo' => 'from-indigo-600 via-purple-500 to-pink-500',
                    'info' => 'from-sky-600 via-cyan-500 to-emerald-500',
                    'alert' => 'from-rose-600 via-red-500 to-orange-500',
                ];
                $gradientClass = $gradientMap[$variant] ?? $gradientMap['promo'];
                $variantLabel = [
                    'promo' => 'Promotion',
                    'info' => 'Info',
                    'alert' => 'Alert',
                ][$variant] ?? 'Promotion';
                $timestamp = $currentFlashBanner['updated_at'] ?? $currentFlashBanner['created_at'] ?? time();
                ?>
                <div class="mt-5 rounded-xl border border-white/10 bg-gradient-to-r <?php echo $gradientClass; ?> text-white p-5">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                        <div>
                            <p class="text-sm uppercase tracking-wide opacity-80">Active banner</p>
                            <div class="flex items-center gap-2 flex-wrap">
                                <?php if (!empty($currentFlashBanner['badge'])): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full border border-white/40 text-xs font-semibold uppercase tracking-wider">
                                        <?php echo htmlspecialchars($currentFlashBanner['badge']); ?>
                                    </span>
                                <?php endif; ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-white/20 text-xs font-semibold uppercase tracking-wider">
                                    <?php echo htmlspecialchars($variantLabel); ?>
                                </span>
                            </div>
                            <p class="mt-2 text-xl font-semibold leading-snug"><?php echo htmlspecialchars($currentFlashBanner['message'] ?? ''); ?></p>
                            <?php if (!empty($currentFlashBanner['subtext'])): ?>
                                <p class="mt-1 text-sm text-white/80 max-w-2xl"><?php echo htmlspecialchars($currentFlashBanner['subtext']); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($currentFlashBanner['cta']) && !empty($currentFlashBanner['href'])): ?>
                            <a href="<?php echo htmlspecialchars($currentFlashBanner['href']); ?>" class="inline-flex items-center gap-2 bg-white/20 px-4 py-2 rounded-lg text-sm font-semibold hover:bg-white/30 transition" target="_blank" rel="noopener">
                                <?php echo htmlspecialchars($currentFlashBanner['cta']); ?>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7l5 5-5 5M6 12h12" /></svg>
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-white/80">
                        <span>Last updated <?php echo date('M d, Y · H:i', (int) $timestamp); ?></span>
                        <?php if (!empty($currentFlashBanner['dismissible'])): ?>
                            <span class="inline-flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 14l2-2 2 2m-2-2v6m8-10a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                Dismissible for shoppers
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="mt-5 rounded-xl border border-dashed border-white/20 p-6 text-indigo-100/70 text-sm">
                    No flash banner is currently live. Use the marketing tools to launch a new promo.
                </div>
            <?php endif; ?>

            <?php
            $promotionStatusClass = 'bg-slate-500/20 text-slate-200 border border-slate-400/40';
            if ($promotionDashboardStatus === 'active') {
                $promotionStatusClass = 'bg-emerald-500/20 text-emerald-100 border border-emerald-400/40';
            } elseif ($promotionDashboardStatus === 'syncing') {
                $promotionStatusClass = 'bg-amber-500/20 text-amber-100 border border-amber-400/40';
            } elseif ($promotionDashboardStatus === 'error' || $promotionDashboardStatus === 'failing') {
                $promotionStatusClass = 'bg-rose-500/20 text-rose-100 border border-rose-400/40';
            }
            $promotionStatusLabel = ucwords(str_replace('_', ' ', (string) $promotionDashboardStatus));
            if ($promotionStatusLabel === '') {
                $promotionStatusLabel = 'Idle';
            }
            $promotionTypeLabel = $promotionDashboardType !== '' ? ucwords(str_replace('_', ' ', (string) $promotionDashboardType)) : '—';
            $promotionSlugLabel = $promotionDashboardSlug !== '' ? $promotionDashboardSlug : '—';
            $promotionMarkdownLabel = '—';
            if ($promotionDashboardMarkdownSummary) {
                $modeLabel = strtolower((string) ($promotionDashboardMarkdownSummary['mode'] ?? 'percent')) === 'flat' ? 'Flat' : 'Percent';
                $value = (float) ($promotionDashboardMarkdownSummary['value'] ?? 0.0);
                $formattedValue = $modeLabel === 'Flat'
                    ? '₹' . number_format($value, 2)
                    : number_format($value, 0) . '%';
                $scope = $promotionDashboardMarkdownSummary['scope'] ?? 'all_items';
                $scopeLabel = $scope === 'sku_list' ? 'Select SKUs' : 'Entire Catalog';
                $promotionMarkdownLabel = $formattedValue . ' · ' . $scopeLabel;
            }
            $promotionRewardLabel = $promotionDashboardRewardValue > 0
                ? '₹' . number_format($promotionDashboardRewardValue, 2)
                : '—';
            ?>

            <div class="mt-6 grid gap-5 md:grid-cols-2">
                <div class="bg-white/5 border border-white/10 rounded-2xl p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm text-indigo-100/70">Promotion status</p>
                            <p class="mt-1 text-xl font-semibold text-white">
                                <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-xs font-semibold <?php echo $promotionStatusClass; ?>">
                                    <?php echo htmlspecialchars($promotionStatusLabel); ?>
                                </span>
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-indigo-100/60 uppercase tracking-wide">Drop slug</p>
                            <p class="mt-1 text-sm font-semibold text-white/90"><?php echo htmlspecialchars($promotionSlugLabel); ?></p>
                        </div>
                    </div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2 text-sm text-indigo-100/80">
                        <div class="bg-white/5 border border-white/10 rounded-xl px-4 py-3">
                            <p class="text-xs uppercase tracking-wide text-indigo-100/60">Promotion type</p>
                            <p class="mt-1 text-sm font-semibold text-white/90"><?php echo htmlspecialchars($promotionTypeLabel); ?></p>
                        </div>
                        <div class="bg-white/5 border border-white/10 rounded-xl px-4 py-3">
                            <p class="text-xs uppercase tracking-wide text-indigo-100/60">Featured modules</p>
                            <?php if (!empty($promotionDashboardFeatureLabels)): ?>
                                <div class="mt-1 flex flex-wrap gap-1.5">
                                    <?php foreach ($promotionDashboardFeatureLabels as $featureLabel): ?>
                                        <span class="inline-flex items-center rounded-full border border-white/20 bg-white/10 px-2 py-0.5 text-xs font-semibold text-indigo-100/80"><?php echo htmlspecialchars($featureLabel); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="mt-1 text-sm text-indigo-100/60">No modules toggled.</p>
                            <?php endif; ?>
                        </div>
                        <div class="bg-white/5 border border-white/10 rounded-xl px-4 py-3">
                            <p class="text-xs uppercase tracking-wide text-indigo-100/60">Markdown</p>
                            <p class="mt-1 text-sm font-semibold text-white/90"><?php echo htmlspecialchars($promotionMarkdownLabel); ?></p>
                        </div>
                        <div class="bg-white/5 border border-white/10 rounded-xl px-4 py-3">
                            <p class="text-xs uppercase tracking-wide text-indigo-100/60">Bundle rules</p>
                            <p class="mt-1 text-sm font-semibold text-white/90"><?php echo (int) $promotionDashboardBundleCount; ?></p>
                        </div>
                        <div class="bg-white/5 border border-white/10 rounded-xl px-4 py-3">
                            <p class="text-xs uppercase tracking-wide text-indigo-100/60">Clearance SKUs</p>
                            <p class="mt-1 text-sm font-semibold text-white/90"><?php echo (int) $promotionDashboardClearanceCount; ?></p>
                        </div>
                        <div class="bg-white/5 border border-white/10 rounded-xl px-4 py-3">
                            <p class="text-xs uppercase tracking-wide text-indigo-100/60">Creator reward</p>
                            <p class="mt-1 text-sm font-semibold text-white/90"><?php echo htmlspecialchars($promotionRewardLabel); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white/5 border border-white/10 rounded-2xl p-5 flex flex-col gap-4">
                    <div>
                        <p class="text-sm font-semibold text-white">Featured inventory</p>
                        <?php if (!empty($promotionDashboardFeaturedLabels)): ?>
                            <ul class="mt-2 space-y-2 text-sm text-indigo-100/80">
                                <?php foreach ($promotionDashboardFeaturedLabels as $label): ?>
                                    <li class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-indigo-300"></span><?php echo htmlspecialchars($label); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="mt-2 text-sm text-indigo-100/60">No featured products linked.</p>
                        <?php endif; ?>
                    </div>
                    <div class="border-t border-white/10 pt-4">
                        <p class="text-sm font-semibold text-white">Configuration alerts</p>
                        <?php if (!empty($promotionDashboardWarnings)): ?>
                            <ul class="mt-2 space-y-2 text-sm text-amber-100/90">
                                <?php foreach ($promotionDashboardWarnings as $warning): ?>
                                    <li class="flex items-start gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mt-0.5 text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01M4.93 19.07a10 10 0 1114.14 0 10 10 0 01-14.14 0z" /></svg>
                                        <span><?php echo htmlspecialchars($warning); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="mt-2 text-sm text-emerald-100/80">Promotion configuration is healthy—no warnings detected.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-8 mb-8">
            <div class="bg-white/10 border border-white/10 p-6 rounded-2xl shadow-lg backdrop-blur">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <h2 class="text-2xl font-bold text-white">Low-Stock Alerts</h2>
                        <p class="text-sm text-indigo-100/80">SKUs at or below <?php echo $lowStockThreshold; ?> units ready for restock.</p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-rose-500/20 text-rose-100 border border-rose-300/40"><?php echo $lowStockCount; ?> flagged</span>
                </div>
                <?php if ($lowStockCount > 0): ?>
                    <ul class="mt-6 space-y-3">
                        <?php foreach ($lowStockItems as $item): ?>
                            <li class="flex items-center justify-between gap-3 bg-white/5 border border-white/10 px-4 py-3 rounded-xl">
                                <div>
                                    <p class="text-sm font-semibold text-white"><?php echo htmlspecialchars($item['product_name']); ?></p>
                                    <p class="text-xs text-indigo-100/70">SKU #<?php echo (int)$item['inventory_id']; ?></p>
                                </div>
                                <span class="inline-flex items-center gap-2 text-xs font-semibold text-rose-100">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01M4.93 19.07a10 10 0 1114.14 0 10 10 0 01-14.14 0z" /></svg>
                                    <?php echo (int)$item['stock_qty']; ?> units
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="mt-6 text-sm text-indigo-100/70">Inventory levels look healthy—no low stock alerts right now.</p>
                <?php endif; ?>
                <div class="mt-6">
                    <a href="products.php" class="inline-flex items-center gap-2 text-sm font-semibold text-indigo-200 hover:text-white transition">
                        Restock in product catalog
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7l5 5-5 5M6 12h12" /></svg>
                    </a>
                </div>
            </div>

            <div class="bg-white/10 border border-white/10 p-6 rounded-2xl shadow-lg backdrop-blur">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <h2 class="text-2xl font-bold text-white">Support Queue Preview</h2>
                        <p class="text-sm text-indigo-100/80">Most recent escalations including cart context.</p>
                    </div>
                    <a href="support_queue.php" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-md shadow hover:bg-indigo-700 transition">
                        View queue
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7l5 5-5 5M6 12h12" /></svg>
                    </a>
                </div>
                <?php if (!empty($recentSupportTickets)): ?>
                    <ul class="mt-6 space-y-4">
                        <?php foreach ($recentSupportTickets as $ticket):
                            $ticketCustomer = $ticket['customer']['name'] ?? 'Mystic Customer';
                            $ticketSummary = $ticket['issue_summary'] ?? 'Checkout support request';
                            $submittedAt = !empty($ticket['created_at']) ? date('M d · H:i', (int)$ticket['created_at']) : 'Just now';
                            $estimatedTotal = $ticket['order_context']['estimated_total'] ?? null;
                            ?>
                            <li class="bg-white/5 border border-white/10 rounded-xl px-4 py-3">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-semibold text-white"><?php echo htmlspecialchars($ticketCustomer); ?></p>
                                        <p class="text-xs text-indigo-100/70"><?php echo htmlspecialchars($ticketSummary); ?></p>
                                    </div>
                                    <span class="text-xs uppercase tracking-wide text-indigo-100/60"><?php echo htmlspecialchars($submittedAt); ?></span>
                                </div>
                                <?php if ($estimatedTotal !== null): ?>
                                    <p class="mt-2 text-xs text-indigo-100/80">Cart estimate: ₹<?php echo number_format((float)$estimatedTotal, 2); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($ticket['order_context']['cart_items'])): ?>
                                    <p class="mt-2 text-xs text-indigo-100/60">
                                        <?php
                                        $itemNames = array_map(function ($item) {
                                            return ($item['quantity'] ?? 0) . 'x ' . ($item['name'] ?? 'Item');
                                        }, $ticket['order_context']['cart_items']);
                                        echo htmlspecialchars(implode(', ', $itemNames));
                                        ?>
                                    </p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="mt-6 text-sm text-indigo-100/70">No open support tickets in the queue—great job staying on top of replies!</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-white/10 border border-white/10 p-6 rounded-2xl shadow-lg backdrop-blur">
            <h2 class="text-2xl font-bold mb-4 text-white">Recent Orders</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 border-b text-left">Order ID</th>
                            <th class="px-4 py-2 border-b text-left">Customer</th>
                            <th class="px-4 py-2 border-b text-left">Amount</th>
                            <th class="px-4 py-2 border-b text-left">Status</th>
                            <th class="px-4 py-2 border-b text-left">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $recentOrders->fetch_assoc()): ?>
                        <tr>
                            <td class="px-4 py-2 border-b">#<?php echo $row['order_id']; ?></td>
                            <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($row['customer_name']); ?></td>
                            <td class="px-4 py-2 border-b">₹<?php echo htmlspecialchars($row['amount']); ?></td>
                            <td class="px-4 py-2 border-b">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php 
                                        switch(strtolower($row['status'])) {
                                            case 'completed': echo 'bg-green-100 text-green-800'; break;
                                            case 'shipped': echo 'bg-blue-100 text-blue-800'; break;
                                            case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                            case 'cancelled': echo 'bg-red-100 text-red-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                    ?>">
                                    <?php echo htmlspecialchars(ucfirst($row['status'])); ?>
                                </span>
                            </td>
                            <td class="px-4 py-2 border-b text-sm text-gray-500"><?php echo date("M d, Y", strtotime($row['order_date'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white/10 border border-white/10 p-6 rounded-2xl shadow-lg backdrop-blur mt-8">
            <h2 class="text-2xl font-bold mb-4 text-white">Recent Admin Activity</h2>
            <?php if (empty($recentAdminActivity)): ?>
                <p class="text-indigo-100/80">No admin actions recorded yet. Status changes and key updates will appear here.</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($recentAdminActivity as $activity):
                        $actionLabel = isset($activity['action']) ? ucwords(str_replace('_', ' ', $activity['action'])) : 'Update';
                        $meta = isset($activity['meta']) && is_array($activity['meta']) ? $activity['meta'] : [];
                        $detailsParts = [];
                        if (!empty($meta['order_id'])) {
                            $detailsParts[] = 'Order #' . (int) $meta['order_id'];
                        }
                        if (!empty($meta['inventory_id'])) {
                            $detailsParts[] = 'Product #' . (int) $meta['inventory_id'];
                        }
                        if (!empty($meta['ticket_id'])) {
                            $detailsParts[] = 'Ticket ' . $meta['ticket_id'];
                        }
                        if (!empty($meta['product_name'])) {
                            $detailsParts[] = $meta['product_name'];
                        }
                        if (!empty($meta['new_status'])) {
                            $detailsParts[] = 'Status -> ' . ucfirst($meta['new_status']);
                        }
                        if (!empty($meta['changes']) && is_array($meta['changes'])) {
                            $changeSummaries = [];
                            foreach ($meta['changes'] as $field => $change) {
                                if (is_array($change) && array_key_exists('from', $change) && array_key_exists('to', $change)) {
                                    $label = ucfirst(str_replace('_', ' ', (string)$field));
                                    $changeSummaries[] = $label . ': ' . (string)($change['from'] ?? '') . ' -> ' . (string)($change['to'] ?? '');
                                } elseif (!is_array($change)) {
                                    $label = ucfirst(str_replace('_', ' ', (string)$field));
                                    $changeSummaries[] = $label . ': ' . (string)$change;
                                }
                            }
                            if (!empty($changeSummaries)) {
                                $detailsParts[] = implode(', ', $changeSummaries);
                            }
                        }
                        $details = implode(' • ', $detailsParts);
                        $timestamp = !empty($activity['timestamp']) ? date('M d, Y · H:i', (int) $activity['timestamp']) : 'Just now';
                    ?>
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-white font-semibold"><?php echo htmlspecialchars($actionLabel); ?></p>
                                <?php if ($details !== ''): ?>
                                    <p class="text-sm text-indigo-100/70"><?php echo htmlspecialchars($details); ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="text-xs uppercase tracking-wide text-indigo-200"><?php echo htmlspecialchars($timestamp); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var chartElement = document.getElementById('revenueOrdersChart');
        if (!chartElement || typeof Chart === 'undefined') {
            return;
        }

        var chartData = <?php echo json_encode($chartPayload, JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK); ?>;

        var config = {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [
                    {
                        type: 'bar',
                        label: 'Orders',
                        data: chartData.orders,
                        backgroundColor: 'rgba(129, 140, 248, 0.35)',
                        borderColor: 'rgba(129, 140, 248, 0.6)',
                        borderWidth: 1,
                        yAxisID: 'yOrders',
                        borderRadius: 6,
                    },
                    {
                        type: 'line',
                        label: 'Revenue (₹)',
                        data: chartData.revenue,
                        yAxisID: 'yRevenue',
                        borderColor: '#c084fc',
                        backgroundColor: 'rgba(192, 132, 252, 0.15)',
                        tension: 0.35,
                        fill: true,
                        pointRadius: 3,
                        pointBackgroundColor: '#c084fc',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    yOrders: {
                        beginAtZero: true,
                        position: 'left',
                        ticks: {
                            color: '#cbd5f5',
                            precision: 0,
                        },
                        grid: {
                            color: 'rgba(148, 163, 184, 0.15)',
                        }
                    },
                    yRevenue: {
                        beginAtZero: true,
                        position: 'right',
                        ticks: {
                            color: '#fbcfe8',
                            callback: function (value) {
                                return '₹' + value;
                            }
                        },
                        grid: {
                            drawOnChartArea: false,
                        }
                    },
                    x: {
                        ticks: {
                            color: '#cbd5f5',
                            maxRotation: 0,
                            autoSkip: true,
                        },
                        grid: {
                            color: 'rgba(148, 163, 184, 0.15)',
                        }
                    }
                },
                plugins: {
                    legend: {
                        labels: {
                            color: '#e0e7ff',
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                if (context.dataset.yAxisID === 'yRevenue') {
                                    return context.dataset.label + ': ₹' + context.parsed.y;
                                }
                                return context.dataset.label + ': ' + context.parsed.y;
                            }
                        }
                    }
                }
            }
        };

        new Chart(chartElement, config);
    });
    </script>
    <?php require_once __DIR__ . '/_footer.php'; ?>