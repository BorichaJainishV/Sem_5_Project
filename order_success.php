<?php
// ---------------------------------------------------------------------
// order_success.php - Order Confirmation Page (Upgraded)
// ---------------------------------------------------------------------
if (session_status() == PHP_SESSION_NONE) { session_start(); }

include 'header.php';

// Get the order ID from the session, then clear it so it doesn't show again.
$order_id = $_SESSION['last_order_id'] ?? 'your';
unset($_SESSION['last_order_id']);
$orderMeta = $_SESSION['last_order_meta'] ?? null;
unset($_SESSION['last_order_meta']);

$orderItems = $orderMeta['items'] ?? [];
$orderBreakdown = $orderMeta['breakdown'] ?? [];
$orderTotal = isset($orderMeta['total']) ? (float) $orderMeta['total'] : null;
$milestones = $orderMeta['milestones'] ?? [];
$shippingInfo = $orderMeta['shipping'] ?? [];
?>
<style>
    .success-hero {
        background: linear-gradient(135deg, rgba(34, 197, 94, 0.14), rgba(99, 102, 241, 0.12));
        border-radius: 1.25rem;
        padding: 2.75rem;
        box-shadow: 0 30px 60px rgba(15, 23, 42, 0.08);
        color: var(--color-body);
    }
    .success-grid {
        display: grid;
        grid-template-columns: minmax(0, 2fr) minmax(0, 1.1fr);
        gap: 2.5rem;
        margin-top: 2.5rem;
    }
    .success-card {
        background: var(--color-surface);
        border-radius: 1.1rem;
        border: 1px solid var(--color-border);
        box-shadow: var(--shadow-lg);
        padding: 2rem;
        color: var(--color-body);
    }
    .success-card.warning {
        border-color: rgba(234, 179, 8, 0.45);
        background: linear-gradient(135deg, rgba(250, 204, 21, 0.12), rgba(253, 224, 71, 0.15));
    }
    .warning-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: rgba(234, 179, 8, 0.22);
        color: #92400e;
        margin-right: 0.75rem;
    }
    .warning-card-body {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
    }
    .timeline {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        position: relative;
        padding-left: 1.5rem;
    }
    .timeline::before {
        content: '';
        position: absolute;
        left: 0.4rem;
        top: 0.4rem;
        bottom: 0.4rem;
        width: 2px;
        background: linear-gradient(to bottom, rgba(34, 197, 94, 0.85), rgba(99, 102, 241, 0.75));
    }
    .timeline-step {
        position: relative;
        padding-left: 1rem;
    }
    .timeline-step::before {
        content: '';
        position: absolute;
        left: -1.15rem;
        top: 0.35rem;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: rgba(34, 197, 94, 0.95);
        box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.25);
    }
    .breakdown-card {
        border: 1px solid rgba(99, 102, 241, 0.22);
        border-radius: 1rem;
        padding: 1.5rem;
        background: rgba(99, 102, 241, 0.08);
    }
    .breakdown-entry {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1.15rem;
        margin-bottom: 1rem;
    }
    .breakdown-entry:last-child {
        margin-bottom: 0;
    }
    .breakdown-entry strong {
        display: block;
        font-size: 1rem;
        color: var(--color-dark);
    }
    .breakdown-entry span {
        font-weight: 600;
        color: var(--color-primary-dark);
    }
    .success-actions {
        margin-top: 2rem;
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }
    .item-summary {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    .item-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        border-bottom: 1px solid rgba(148, 163, 184, 0.3);
        padding-bottom: 0.6rem;
    }
    .item-row:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }
    @media (max-width: 1024px) {
        .success-grid {
            grid-template-columns: 1fr;
        }
    }

    .text-green-700 { color: #047857; }
    .text-indigo-900 { color: #312e81; }
    .text-slate-900 { color: #0f172a; }
    .text-slate-800 { color: #1e293b; }
    .text-slate-700 { color: #334155; }
    .text-slate-600 { color: #475569; }
    .text-slate-500 { color: #64748b; }
    .border-indigo-100 { border-color: #e0e7ff; }
    .bg-slate-50 { background-color: #f8fafc; }
    .border-slate-200 { border-color: #e2e8f0; }

    @media (prefers-color-scheme: dark) {
        .success-hero {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.22), rgba(79, 70, 229, 0.3));
            box-shadow: 0 30px 65px rgba(2, 6, 23, 0.55);
        }
        .success-card {
            box-shadow: 0 20px 55px rgba(2, 6, 23, 0.55);
        }
        .success-card.warning {
            background: linear-gradient(135deg, rgba(250, 204, 21, 0.2), rgba(253, 224, 71, 0.25));
            border-color: rgba(250, 204, 21, 0.45);
        }
        .warning-icon {
            background: rgba(253, 224, 71, 0.32);
            color: #fef9c3;
        }
        .breakdown-card {
            background: rgba(79, 70, 229, 0.22);
            border-color: rgba(99, 102, 241, 0.35);
        }
        .timeline::before {
            background: linear-gradient(to bottom, rgba(34, 197, 94, 0.75), rgba(59, 130, 246, 0.75));
        }
        .timeline-step::before {
            background: rgba(34, 197, 94, 0.9);
        }
        .text-green-700 { color: #6ee7b7; }
    .text-indigo-900 { color: #c7d2fe; }
        .text-slate-900 { color: #f8fafc; }
        .text-slate-800 { color: #e2e8f0; }
        .text-slate-700 { color: #e2e8f0; }
        .text-slate-600 { color: rgba(226, 232, 240, 0.8); }
        .text-slate-500 { color: rgba(226, 232, 240, 0.65); }
        .border-indigo-100 { border-color: rgba(99, 102, 241, 0.35); }
        .bg-slate-50 {
            background-color: rgba(15, 23, 42, 0.75);
            color: rgba(226, 232, 240, 0.85);
        }
        .border-slate-200 { border-color: rgba(99, 102, 241, 0.28); }
    }
    }
</style>

<main class="container mx-auto px-6 py-16">
    <section class="success-hero text-center">
        <h1 class="text-3xl font-bold text-green-700">Order on its way!</h1>
        <p class="text-slate-700 mt-3 text-lg">Thanks for printing with Mystic. We’re prepping everything with care.</p>
        <p class="text-slate-600 mt-2">Order ID <strong>#<?php echo htmlspecialchars($order_id); ?></strong><?php if (!empty($shippingInfo['full_name'])): ?> for <?php echo htmlspecialchars($shippingInfo['full_name']); ?><?php endif; ?></p>
        <div class="success-actions">
            <a href="account.php" class="btn btn-primary">Track in My Account</a>
            <a href="shop.php" class="btn btn-outline">Continue Shopping</a>
        </div>
    </section>

    <div class="success-grid">
        <section class="success-card">
            <h2 class="text-2xl font-semibold text-slate-900 mb-4">What we’re preparing</h2>
            <?php if (!empty($orderItems)): ?>
                <div class="item-summary mb-6">
                    <?php foreach ($orderItems as $item): ?>
                        <div class="item-row">
                            <div>
                                <p class="font-semibold text-slate-800"><?php echo htmlspecialchars($item['name']); ?></p>
                                <p class="text-xs text-slate-500">Qty: <?php echo (int) ($item['quantity'] ?? 1); ?></p>
                            </div>
                            <p class="font-semibold text-slate-800">₹<?php echo number_format($item['subtotal'] ?? 0, 2); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($orderTotal !== null): ?>
                <div class="breakdown-card">
                    <h3 class="text-lg font-semibold text-indigo-900 mb-3">Cost breakdown</h3>
                    <?php foreach ($orderBreakdown as $row): ?>
                        <div class="breakdown-entry">
                            <div>
                                <strong><?php echo htmlspecialchars($row['label']); ?></strong>
                                <p class="text-sm text-slate-600"><?php echo htmlspecialchars($row['description']); ?></p>
                            </div>
                            <span>₹<?php echo number_format($row['amount'], 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div class="flex justify-between font-bold text-slate-900 border-t border-indigo-100 pt-3 mt-3">
                        <span>Total charged</span>
                        <span>₹<?php echo number_format($orderTotal, 2); ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <aside class="success-card">
            <h2 class="text-2xl font-semibold text-slate-900 mb-4">What happens next</h2>
            <div class="timeline">
                <div class="timeline-step">
                    <h3 class="font-semibold text-slate-800">Print prep begins</h3>
                    <p class="text-sm text-slate-600">We’re queuing your design for double-pass curing and color checks.</p>
                    <?php if (!empty($milestones['print_window_start']) && !empty($milestones['print_window_end'])): ?>
                        <p class="text-xs text-slate-500 mt-1">Window: <?php echo htmlspecialchars($milestones['print_window_start']); ?> – <?php echo htmlspecialchars($milestones['print_window_end']); ?></p>
                    <?php endif; ?>
                </div>
                <div class="timeline-step">
                    <h3 class="font-semibold text-slate-800">Packed with care</h3>
                    <p class="text-sm text-slate-600">Each piece gets a lint-free inspection and eco packaging.</p>
                    <?php if (!empty($milestones['ship_by'])): ?>
                        <p class="text-xs text-slate-500 mt-1">Ship by <?php echo htmlspecialchars($milestones['ship_by']); ?></p>
                    <?php endif; ?>
                </div>
                <div class="timeline-step">
                    <h3 class="font-semibold text-slate-800">Out for delivery</h3>
                    <p class="text-sm text-slate-600">We’ll email tracking the moment courier scans your parcel.</p>
                    <?php if (!empty($milestones['expected_delivery'])): ?>
                        <p class="text-xs text-slate-500 mt-1">Estimated arrival <?php echo htmlspecialchars($milestones['expected_delivery']); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-6 p-4 rounded-xl bg-slate-50 border border-slate-200 text-sm text-slate-600">
                <p>Need to tweak your order? Reply to the confirmation email or ping us in live chat within 30 minutes and we’ll do our best to accommodate.</p>
            </div>
        </aside>
    </div>

    <section class="success-card warning mt-10" role="status" aria-live="polite">
        <div class="warning-card-body">
            <div class="warning-icon" aria-hidden="true">!</div>
            <div>
                <h2 class="text-xl font-semibold text-yellow-700 mb-2">Heads up about this page</h2>
                <p class="text-sm text-slate-700">Refreshing this confirmation page will clear the on-screen order reference for privacy. Don’t worry—your order is still logged, and you can always view the full details inside <a href="account.php" class="text-indigo-600 font-medium">My Account</a>.</p>
            </div>
        </div>
    </section>
</main>

<script>
    // Show success toast for order confirmation
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof toastSuccess === 'function') {
            toastSuccess('🎉 Order confirmed! Check your email for details.');
        }
    });
</script>

<?php include 'footer.php'; ?>