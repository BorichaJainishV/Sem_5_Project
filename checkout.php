<?php
// ---------------------------------------------------------------------
// checkout.php - Step 1: Collect Shipping Information
// ---------------------------------------------------------------------
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// Redirect if not logged in or cart is empty
if (!isset($_SESSION['customer_id']) || empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit();
}

include 'db_connection.php';
require_once __DIR__ . '/core/drop_promotions.php';
require_once __DIR__ . '/core/cart_snapshot.php';

$cartSnapshot = mystic_cart_snapshot($conn);
$cart_items = $cartSnapshot['lines'];
$total_price = $cartSnapshot['subtotal'];
$total_savings = $cartSnapshot['savings'];
$bundle_value = $cartSnapshot['bundle_value'];
$original_total = $cartSnapshot['original_subtotal'];
$total_savings_percent = ($total_savings > 0 && $original_total > 0) ? round(($total_savings / $original_total) * 100) : 0;

// --- Handle Shipping Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    $csrf = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        header('Location: cart.php');
        exit();
    }
    // Save shipping info to session and redirect to the payment page
    $_SESSION['shipping_info'] = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'email'     => trim($_POST['email'] ?? ''),
        'address'   => trim($_POST['address'] ?? '')
    ];
    header('Location: payment.php');
    exit();
}


include 'header.php';

$timezone = new DateTimeZone('Asia/Kolkata');
$now = new DateTime('now', $timezone);
$prepDate = (clone $now)->modify('+1 day');
$productionDate = (clone $now)->modify('+3 days');
$deliveryWindowStart = (clone $now)->modify('+4 days');
$deliveryWindowEnd = (clone $now)->modify('+6 days');

$deliveryTimeline = [
    [
        'title' => 'Lock in your order',
        'eta' => $now->format('D, M j'),
        'description' => 'We verify artwork and prep garments the same day.',
        'status' => 'current',
    ],
    [
        'title' => 'Ink & stitch',
        'eta' => $prepDate->format('D, M j'),
        'description' => 'Production kicks off in our Pune studio within 24 hours.',
        'status' => 'upcoming',
    ],
    [
        'title' => 'Out for delivery',
        'eta' => $productionDate->format('D, M j'),
        'description' => 'Tracking number drops the moment your bundle ships.',
        'status' => 'upcoming',
    ],
    [
        'title' => 'Arrives at your door',
        'eta' => $deliveryWindowStart->format('M j') . ' – ' . $deliveryWindowEnd->format('M j'),
        'description' => 'Expect hand-off via Bluedart or Delhivery with SMS updates.',
        'status' => 'upcoming',
    ],
];
?>

<style>
    .price-original {
        text-decoration: line-through;
        color: #94a3b8;
        font-size: 0.82rem;
        margin-right: 0.35rem;
        display: inline-block;
    }
    .price-current {
        font-weight: 600;
        color: #0f172a;
    }
    .price-savings {
        color: #047857;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
        margin-top: 0.1rem;
    }
    .checkout-roadmap {
        margin-top: 1.5rem;
        border-radius: 1rem;
        border: 1px solid rgba(99, 102, 241, 0.18);
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.08), rgba(199, 210, 254, 0.12));
        padding: 1.6rem;
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
        color: var(--color-body);
    }
    .checkout-roadmap h3 {
        margin: 0;
        font-size: 1.2rem;
        color: var(--color-primary-dark);
        display: flex;
        align-items: center;
        gap: 0.6rem;
    }
    .checkout-roadmap h3 i {
        width: 20px;
        height: 20px;
    }
    .roadmap-steps {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.1rem;
    }
    .roadmap-step {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 0.85rem;
        align-items: flex-start;
    }
    .roadmap-marker {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.8rem;
        background: var(--color-primary-dark);
        color: #ffffff;
        position: relative;
    }
    .roadmap-marker::after {
        content: '';
        position: absolute;
        width: 2px;
        height: 80px;
        background: rgba(79, 70, 229, 0.2);
        left: 50%;
        transform: translateX(-50%);
        top: 100%;
    }
    .roadmap-step:last-child .roadmap-marker::after {
        display: none;
    }
    .roadmap-step h4 {
        margin: 0;
        font-size: 1rem;
        color: var(--color-dark);
    }
    .roadmap-meta {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--color-primary);
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-top: 0.1rem;
    }
    .roadmap-copy {
        margin: 0.2rem 0 0;
        font-size: 0.85rem;
        color: var(--color-muted);
    }
    .text-slate-600 { color: var(--color-muted); }
    .text-red-700 { color: #b91c1c; }
    .text-red-600 { color: #dc2626; }
    .bg-red-100 { background-color: #fee2e2; }

    @media (prefers-color-scheme: dark) {
        .price-original {
            color: rgba(226, 232, 240, 0.65);
        }
        .price-current {
            color: #e0f2fe;
        }
        .price-savings {
            color: #34d399;
        }
        .checkout-roadmap {
            border-color: rgba(99, 102, 241, 0.35);
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.32), rgba(37, 99, 235, 0.18));
            color: var(--color-body);
        }
        .checkout-roadmap h3 {
            color: #c7d2fe;
        }
        .roadmap-marker {
            background: rgba(79, 70, 229, 0.85);
            color: #e0e7ff;
        }
        .roadmap-marker::after {
            background: rgba(79, 70, 229, 0.35);
        }
        .roadmap-meta {
            color: #c7d2fe;
        }
        .roadmap-copy {
            color: rgba(226, 232, 240, 0.8);
        }
        .text-slate-600 { color: rgba(226, 232, 240, 0.8); }
        .text-red-700,
        .text-red-600 { color: #fecdd3; }
        .bg-red-100 { background-color: rgba(239, 68, 68, 0.28); }
    }
    @media (max-width: 768px) {
        .checkout-roadmap {
            padding: 1.25rem;
        }
    }
</style>

<main class="container">
    <h1 class="text-3xl font-bold mb-8">Checkout</h1>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 card">
            <div class="card-body">
                <h2 class="text-2xl font-semibold mb-4">Shipping Information</h2>
                <form method="POST" action="checkout.php" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group md:col-span-2">
                        <label class="form-label" for="full_name">Full Name</label>
                        <input id="full_name" name="full_name" type="text" class="form-control" required>
                    </div>
                    <div class="form-group md:col-span-2">
                        <label class="form-label" for="email">Email</label>
                        <input id="email" name="email" type="email" class="form-control" required>
                    </div>
                    <div class="form-group md:col-span-2">
                        <label class="form-label" for="address">Address</label>
                        <textarea id="address" name="address" rows="3" class="form-control" required></textarea>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                    <div class="md:col-span-2">
                        <button type="submit" class="btn btn-primary btn-lg w-full">Continue to Payment</button>
                    </div>
                </form>
            </div>
        </div>
        <div>
            <div class="card">
                <div class="card-body">
                    <h2 class="text-2xl font-semibold mb-4">Order Summary</h2>
                    <?php foreach ($cart_items as $item): ?>
                        <?php
                            $isFreebie = !empty($item['is_freebie']);
                            $bundleWorth = isset($item['bundle_value']) ? (float) $item['bundle_value'] : 0.0;
                            $quantity = (int) ($item['quantity'] ?? 0);
                        ?>
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <p class="font-semibold truncate"><?php echo htmlspecialchars($item['name']); ?></p>
                                <?php if (!empty($item['meta'])): ?>
                                    <p class="text-xs text-slate-500 mb-1"><?php echo htmlspecialchars($item['meta']); ?></p>
                                <?php endif; ?>
                                <p class="text-sm text-slate-600">
                                    <?php if ($isFreebie): ?>
                                        <span class="price-current">Free</span>
                                        <span class="text-xs text-slate-500">× <?php echo $quantity; ?></span>
                                        <?php if ($bundleWorth > 0): ?>
                                            <span class="price-savings">Worth ₹<?php echo number_format($bundleWorth, 2); ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if (!empty($item['original_price'])): ?>
                                            <span class="price-original">₹<?php echo number_format($item['original_price'], 2); ?></span>
                                        <?php endif; ?>
                                        <span class="price-current">₹<?php echo number_format($item['price'], 2); ?></span>
                                        <span class="text-xs text-slate-500">× <?php echo $quantity; ?></span>
                                        <?php if (($item['savings'] ?? 0) > 0): ?>
                                            <span class="price-savings">Saved ₹<?php echo number_format($item['savings'], 2); ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="text-right">
                                <?php if ($isFreebie): ?>
                                    <span class="price-current">Free</span>
                                <?php else: ?>
                                    <?php if (!empty($item['original_price'])): ?>
                                        <span class="price-original">₹<?php echo number_format($item['original_price'] * $item['quantity'], 2); ?></span>
                                    <?php endif; ?>
                                    <span class="price-current">₹<?php echo number_format($item['subtotal'], 2); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="border-t pt-4 mt-4">
                        <?php if ($total_savings > 0): ?>
                            <div class="flex justify-between items-center mb-2">
                                <span>Original total</span>
                                <span class="price-original">₹<?php echo number_format($original_total, 2); ?></span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span>You saved</span>
                                <span class="price-savings">₹<?php echo number_format($total_savings, 2); ?><?php if ($total_savings_percent > 0) { echo ' (' . (int)$total_savings_percent . '%)'; } ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($bundle_value)): ?>
                            <div class="flex justify-between items-center mb-2">
                                <span>Bundle freebies</span>
                                <span class="price-savings">₹<?php echo number_format($bundle_value, 2); ?> value</span>
                            </div>
                        <?php endif; ?>
                        <div class="flex justify-between font-bold text-lg"><span>Total</span><span>₹<?php echo number_format($total_price, 2); ?></span></div>
                    </div>
                </div>
            </div>
            <div class="checkout-roadmap">
                <h3><i data-feather="map"></i>Delivery Roadmap</h3>
                <p class="text-sm text-slate-600">We’ll keep you in the loop at every milestone from print setup to doorstep drop-off.</p>
                <div class="roadmap-steps">
                    <?php foreach ($deliveryTimeline as $index => $step): ?>
                    <div class="roadmap-step">
                        <span class="roadmap-marker"><?php echo $index + 1; ?></span>
                        <div>
                            <h4><?php echo htmlspecialchars($step['title']); ?></h4>
                            <div class="roadmap-meta">ETA <?php echo htmlspecialchars($step['eta']); ?></div>
                            <p class="roadmap-copy"><?php echo htmlspecialchars($step['description']); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>