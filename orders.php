<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['customer_id'])) { header('Location: index.php'); exit(); }
include 'db_connection.php';

$customerId = $_SESSION['customer_id'];
$stmt = $conn->prepare("SELECT o.order_id, o.order_date, o.status, COUNT(*) as item_count, SUM(i.price) as total_amount FROM orders o JOIN inventory i ON o.inventory_id = i.inventory_id WHERE o.customer_id = ? GROUP BY o.order_id, o.order_date, o.status ORDER BY o.order_date DESC, o.order_id DESC");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$orderResult = $stmt->get_result();
$orderRows = $orderResult ? $orderResult->fetch_all(MYSQLI_ASSOC) : [];
if ($orderResult) {
    $orderResult->free();
}
include 'header.php';
?>
<style>
    .order-dashboard {
        max-width: 1100px;
        margin: 0 auto;
        padding: 3rem 1rem;
        color: var(--color-body);
    }
    .order-dashboard h1 {
        font-size: 2.4rem;
        margin-bottom: 1.5rem;
        color: var(--color-dark);
    }
    .order-empty {
        background: rgba(99, 102, 241, 0.08);
        border: 1px dashed rgba(79, 70, 229, 0.35);
        border-radius: 1.25rem;
        padding: 2.5rem;
        text-align: center;
        color: var(--color-muted, #6b7280);
    }
    .order-empty a {
        margin-top: 1.25rem;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    .order-grid {
        display: flex;
        flex-direction: column;
        gap: 1.75rem;
    }
    .order-card {
        background: var(--color-surface, #ffffff);
        border-radius: 1.25rem;
        border: 1px solid rgba(148, 163, 184, 0.18);
        box-shadow: 0 30px 60px rgba(15, 23, 42, 0.08);
        padding: 1.75rem;
        display: flex;
        flex-direction: column;
        gap: 1.4rem;
        position: relative;
        overflow: hidden;
    }
    .order-card::after {
        content: '';
        position: absolute;
        top: -40px;
        right: -40px;
        width: 160px;
        height: 160px;
        background: radial-gradient(circle at center, rgba(99, 102, 241, 0.18), transparent 70%);
        pointer-events: none;
    }
    .order-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        position: relative;
        z-index: 1;
    }
    .order-title {
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--color-dark);
    }
    .order-subtitle {
        font-size: 0.9rem;
        color: var(--color-muted, #6b7280);
        margin-top: 0.25rem;
    }
    .order-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        border-radius: 999px;
        padding: 0.4rem 0.85rem;
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.09em;
        border: 1px solid rgba(99, 102, 241, 0.35);
        color: var(--color-primary, #4c1d95);
        background: rgba(129, 140, 248, 0.15);
    }
    .order-status-pill span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: currentColor;
    }
    .order-status-pill.is-completed {
        background: rgba(16, 185, 129, 0.18);
        color: #047857;
        border-color: rgba(16, 185, 129, 0.45);
    }
    .order-meta-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 1.1rem;
        position: relative;
        z-index: 1;
    }
    .order-meta-card {
        background: rgba(99, 102, 241, 0.08);
        border-radius: 1rem;
        padding: 1rem 1.1rem;
    }
    .order-meta-card strong {
        display: block;
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--color-muted, #6b7280);
        margin-bottom: 0.4rem;
    }
    .order-meta-card span {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--color-dark);
    }
    .order-progress {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.85rem;
        position: relative;
        z-index: 1;
    }
    .order-progress-step {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        align-items: flex-start;
    }
    .progress-indicator {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border-radius: 999px;
        border: 2px solid rgba(148, 163, 184, 0.45);
        font-size: 0.85rem;
        font-weight: 600;
        color: rgba(100, 116, 139, 0.9);
        background: rgba(255, 255, 255, 0.65);
    }
    .order-progress-step.is-complete .progress-indicator {
        border-color: rgba(37, 99, 235, 0.65);
        background: rgba(37, 99, 235, 0.15);
        color: #1e3a8a;
    }
    .order-progress-step.is-current .progress-indicator {
        border-color: rgba(16, 185, 129, 0.65);
        background: rgba(16, 185, 129, 0.12);
        color: #047857;
    }
    .progress-title {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--color-dark);
    }
    .progress-hint {
        font-size: 0.78rem;
        color: var(--color-muted, #6b7280);
    }
    .order-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-top: 0.5rem;
        position: relative;
        z-index: 1;
    }
    .order-actions .btn {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
    }
    .billing-card {
        margin-top: 2.5rem;
        background: linear-gradient(130deg, rgba(79, 70, 229, 0.2), rgba(129, 140, 248, 0.18));
        border-radius: 1.25rem;
        padding: 2rem;
        color: var(--color-dark);
        border: 1px solid rgba(79, 70, 229, 0.25);
    }
    .billing-card h2 {
        font-size: 1.6rem;
        margin-bottom: 0.9rem;
    }
    .billing-card ul {
        margin: 0;
        padding-left: 1.1rem;
        color: rgba(51, 65, 85, 0.85);
        font-size: 0.9rem;
        display: grid;
        gap: 0.55rem;
    }

    @media (max-width: 768px) {
        .order-progress {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .order-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .order-card {
            padding: 1.35rem;
        }
    }

    @media (prefers-color-scheme: dark) {
        .order-card {
            background: rgba(13, 19, 42, 0.92);
            border-color: rgba(99, 102, 241, 0.25);
            box-shadow: 0 30px 60px rgba(2, 6, 23, 0.65);
        }
        .order-meta-card {
            background: rgba(79, 70, 229, 0.18);
        }
        .progress-indicator {
            background: rgba(15, 23, 42, 0.45);
            border-color: rgba(148, 163, 184, 0.35);
            color: rgba(209, 213, 219, 0.85);
        }
        .order-progress-step.is-complete .progress-indicator {
            background: rgba(37, 99, 235, 0.32);
            color: #dbeafe;
        }
        .order-progress-step.is-current .progress-indicator {
            background: rgba(16, 185, 129, 0.32);
            color: #a7f3d0;
        }
        .progress-hint,
        .order-subtitle {
            color: rgba(203, 213, 225, 0.75);
        }
        .billing-card {
            background: linear-gradient(130deg, rgba(37, 99, 235, 0.28), rgba(129, 140, 248, 0.25));
            color: #e0e7ff;
            border-color: rgba(99, 102, 241, 0.45);
        }
        .billing-card ul {
            color: rgba(226, 232, 240, 0.85);
        }
    }
</style>

<main class="order-dashboard container">
    <h1>My Orders</h1>
    <?php if (count($orderRows) === 0): ?>
        <div class="order-empty">
            <p>You haven’t placed an order yet. Your future masterpieces will appear here once you check out.</p>
            <a href="shop.php" class="btn btn-primary">
                <i data-feather="shopping-bag"></i>
                Browse the shop
            </a>
        </div>
    <?php else:
        $progressBlueprint = [
            ['title' => 'Order placed', 'hint' => 'We’ve logged your order and locked in pricing.'],
            ['title' => 'In production', 'hint' => 'Print technicians are prepping garments and inks.'],
            ['title' => 'Shipped', 'hint' => 'Courier has your parcel and will share live updates.'],
            ['title' => 'Delivered', 'hint' => 'Handed off at your doorstep. Enjoy the reveal!'],
        ];
        ?>
        <div class="order-grid">
            <?php foreach ($orderRows as $row):
                $orderId = (int)$row['order_id'];
                $trackingNumber = $_SESSION['tracking_numbers'][$orderId] ?? null;
                $trackingLink = $trackingNumber ? 'https://track.aftership.com/' . urlencode($trackingNumber) : null;
                $statusRaw = strtolower(trim((string) $row['status']));
                $statusStep = 0;
                switch ($statusRaw) {
                    case 'completed':
                    case 'delivered':
                        $statusStep = 3;
                        break;
                    case 'shipped':
                        $statusStep = 2;
                        break;
                    case 'processing':
                        $statusStep = 1;
                        break;
                    default:
                        $statusStep = 0;
                        break;
                }
                $statusLabel = ucfirst($statusRaw ?: 'Pending');
                $placedDate = !empty($row['order_date']) ? date('M j, Y', strtotime($row['order_date'])) : 'Recently';
            ?>
            <article class="order-card">
                <header class="order-header">
                    <div>
                        <div class="order-title">Order #<?php echo $orderId; ?></div>
                        <div class="order-subtitle">Placed on <?php echo htmlspecialchars($placedDate); ?></div>
                    </div>
                    <span class="order-status-pill <?php echo $statusStep === 3 ? 'is-completed' : ''; ?>">
                        <span></span><?php echo htmlspecialchars($statusLabel); ?>
                    </span>
                </header>

                <div class="order-meta-grid">
                    <div class="order-meta-card">
                        <strong>Items</strong>
                        <span><?php echo (int)$row['item_count']; ?></span>
                    </div>
                    <div class="order-meta-card">
                        <strong>Total Paid</strong>
                        <span>₹<?php echo number_format((float)$row['total_amount'], 2); ?></span>
                    </div>
                    <div class="order-meta-card">
                        <strong>Tracking</strong>
                        <span>
                            <?php if ($trackingLink): ?>
                                <a href="<?php echo htmlspecialchars($trackingLink); ?>" target="_blank" rel="noopener" class="text-primary">Track package</a>
                            <?php else: ?>
                                Awaiting courier hand-off
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <div class="order-progress">
                    <?php foreach ($progressBlueprint as $index => $step):
                        $isComplete = $index < $statusStep;
                        $isCurrent = $index === $statusStep;
                        $stepClass = $isCurrent ? 'is-current' : ($isComplete ? 'is-complete' : '');
                    ?>
                    <div class="order-progress-step <?php echo $stepClass; ?>">
                        <span class="progress-indicator"><?php echo $index + 1; ?></span>
                        <span class="progress-title"><?php echo htmlspecialchars($step['title']); ?></span>
                        <span class="progress-hint"><?php echo htmlspecialchars($step['hint']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="order-actions">
                    <a href="shop.php" class="btn btn-outline btn-sm">
                        <i data-feather="shopping-bag"></i>
                        Continue shopping
                    </a>
                    <?php if ($trackingLink): ?>
                    <a href="<?php echo htmlspecialchars($trackingLink); ?>" target="_blank" rel="noopener" class="btn btn-primary btn-sm">
                        <i data-feather="map"></i>
                        Live tracking
                    </a>
                    <?php endif; ?>
                    <a href="contact.php#support" class="btn btn-outline btn-sm">
                        <i data-feather="help-circle"></i>
                        Need support
                    </a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <section class="billing-card" id="billing">
            <h2>Billing & Invoices</h2>
            <p class="order-subtitle">Keep your paperwork tidy with quick-access invoice support.</p>
            <ul id="orders-help">
                <li>Each confirmation email carries a PDF invoice—search your inbox for “Mystic Clothing Invoice”.</li>
                <li>Need another copy or GST breakdown? <a href="contact.php#billing" class="text-primary">Ping our billing desk</a> with the order number and we’ll resend it within the hour.</li>
                <li>Planning a corporate run? Ask about split payments or purchase order billing before the parcel ships.</li>
            </ul>
        </section>
    <?php endif; ?>
</main>
<?php include 'footer.php'; ?>


