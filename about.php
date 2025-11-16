<?php
include 'header.php';
include 'db_connection.php';

$metrics = [
    'orders' => 0,
    'first_order' => null,
    'revenue' => 0.0,
    'custom_designs' => 0,
    'customers' => 0,
];

if (isset($conn) && $conn instanceof mysqli) {
    $ordersTable = $conn->query("SHOW TABLES LIKE 'orders'");
    if ($ordersTable && $ordersTable->num_rows > 0) {
        $ordersResult = $conn->query("SELECT COUNT(*) AS total_orders, MIN(order_date) AS first_order FROM orders");
        if ($ordersResult) {
            $row = $ordersResult->fetch_assoc();
            $metrics['orders'] = isset($row['total_orders']) ? (int) $row['total_orders'] : 0;
            $metrics['first_order'] = !empty($row['first_order']) ? $row['first_order'] : null;
            $ordersResult->free();
        }

                $billingResult = $conn->query(
                        "SELECT COALESCE(SUM(b.amount), 0) AS gross_revenue
                         FROM billing b
                         INNER JOIN orders o ON o.order_id = b.order_id
                         WHERE LOWER(b.status) IN ('paid', 'completed')
                             AND LOWER(o.status) IN ('paid', 'completed')"
                );
        if ($billingResult) {
            $row = $billingResult->fetch_assoc();
            $metrics['revenue'] = isset($row['gross_revenue']) ? (float) $row['gross_revenue'] : 0.0;
            $billingResult->free();
        }
    }
    if ($ordersTable) {
        $ordersTable->free();
    }

    $customTable = $conn->query("SHOW TABLES LIKE 'custom_designs'");
    if ($customTable && $customTable->num_rows > 0) {
        $customResult = $conn->query("SELECT COUNT(*) AS total_custom FROM custom_designs");
        if ($customResult) {
            $row = $customResult->fetch_assoc();
            $metrics['custom_designs'] = isset($row['total_custom']) ? (int) $row['total_custom'] : 0;
            $customResult->free();
        }
    }
    if ($customTable) {
        $customTable->free();
    }

    $customerTable = $conn->query("SHOW TABLES LIKE 'customer'");
    if ($customerTable && $customerTable->num_rows > 0) {
        $customerResult = $conn->query("SELECT COUNT(*) AS total_customers FROM customer");
        if ($customerResult) {
            $row = $customerResult->fetch_assoc();
            $metrics['customers'] = isset($row['total_customers']) ? (int) $row['total_customers'] : 0;
            $customerResult->free();
        }
    }
    if ($customerTable) {
        $customerTable->free();
    }
}

$firstLaunchYear = $metrics['first_order'] ? date('Y', strtotime($metrics['first_order'])) : date('Y');
$missionPillars = [
    [
        'title' => 'Empower Every Creator',
        'description' => 'Our platform gives emerging designers the same pro-grade tooling big studios use, no steep learning curve required.',
    ],
    [
        'title' => 'Craft Without Compromise',
        'description' => 'From eco-friendly inks to premium fabrics, every product is built to match the artistry poured into it.',
    ],
    [
        'title' => 'Celebrate Community',
        'description' => 'Spotlights, shared palettes, and remix-friendly saves keep ideas flowing between makers worldwide.',
    ],
];

$timeline = [
    [
        'year' => $firstLaunchYear,
        'title' => 'The first drop ships',
        'body' => 'Mystic goes live with a single tee silhouette and a dozen beta designers. The goal was simple: turn dorm-room sketches into wearable art.',
    ],
    [
        'year' => (string) ((int) $firstLaunchYear + 1),
        'title' => 'Community tools arrive',
        'body' => 'We launched the collaborative moodboard and “Use This Vibe” links so inspiration travels instantly from gallery to canvas.',
    ],
    [
        'year' => 'Today',
        'title' => 'Creators worldwide',
        'body' => sprintf('More than %s bespoke pieces shipped and counting—crafted by a community of %s Mystic makers.',
            number_format(max($metrics['orders'], 1)),
            number_format(max($metrics['customers'], 1))
        ),
    ],
];

$faq = [
    [
        'question' => 'How long does production take?',
        'answer' => 'Most orders move from studio to doorstep within 5–7 business days. We print on demand, so you always receive fresh stock rather than warehoused leftovers.',
    ],
    [
        'question' => 'Can I collaborate with other designers?',
        'answer' => 'Absolutely. Share inspiration textures, co-edit designs, or hand off projects using the “handover link” inside your dashboard.',
    ],
    [
        'question' => 'Do you support bulk or brand orders?',
        'answer' => 'Yes—Mystic Studios offers scaled runs with white-label packaging. Drop us a note through the contact form and we’ll tailor a roadmap.',
    ],
];
?>

<div class="page-header">
    <div class="container">
        <h1>The Mystic Journey</h1>
        <p>Fueled by community creativity, crafted with care in every stitch.</p>
    </div>
</div>

<main class="container space-y-16">
    <section class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <?php foreach ($missionPillars as $pillar): ?>
        <article class="card h-full">
            <div class="card-body space-y-4">
                <span class="badge badge-primary">Mission Pillar</span>
                <h2><?php echo htmlspecialchars($pillar['title']); ?></h2>
                <p class="text-muted"><?php echo htmlspecialchars($pillar['description']); ?></p>
            </div>
        </article>
        <?php endforeach; ?>
    </section>

    <section class="card">
        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 text-center">
                <div>
                    <p class="text-sm uppercase tracking-wide text-muted">Pieces shipped</p>
                    <p class="text-4xl font-extrabold text-primary"><?php echo number_format(max($metrics['orders'], 0)); ?></p>
                </div>
                <div>
                    <p class="text-sm uppercase tracking-wide text-muted">Creators onboard</p>
                    <p class="text-4xl font-extrabold text-primary"><?php echo number_format(max($metrics['customers'], 0)); ?></p>
                </div>
                <div>
                    <p class="text-sm uppercase tracking-wide text-muted">Custom canvases</p>
                    <p class="text-4xl font-extrabold text-primary"><?php echo number_format(max($metrics['custom_designs'], 0)); ?></p>
                </div>
                <div>
                    <p class="text-sm uppercase tracking-wide text-muted">Revenue reinvested</p>
                    <p class="text-4xl font-extrabold text-primary">₹<?php echo number_format(max($metrics['revenue'], 0), 2); ?></p>
                </div>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 md:grid-cols-2 gap-12 items-start">
        <div class="space-y-6">
            <h2>How we grew the coven</h2>
            <p class="text-muted">Mystic started as a rebellious idea to let anyone weaponise imagination. Today we coach creators through every step—from sketching magical runes to shipping out coveted drops.</p>
            <ul class="space-y-4">
                <?php foreach ($timeline as $event): ?>
                <li class="card">
                    <div class="card-body space-y-2">
                        <span class="badge badge-secondary"><?php echo htmlspecialchars($event['year']); ?></span>
                        <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                        <p class="text-muted"><?php echo htmlspecialchars($event['body']); ?></p>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="card h-full">
            <img src="image/placeholder.png" alt="Mystic studio" class="w-full h-64 object-cover rounded-t-lg">
            <div class="card-body space-y-4">
                <h3>Inside Mystic Studios</h3>
                <p class="text-muted">From calibrating print heads at dawn to hosting midnight jam sessions, the team obsesses over every shade and stitch so your ideas land exactly as envisioned.</p>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div class="p-4 bg-surface rounded border border-border">
                        <strong>24/7</strong>
                        <p class="text-muted">support coverage for active launches.</p>
                    </div>
                    <div class="p-4 bg-surface rounded border border-border">
                        <strong>92%</strong>
                        <p class="text-muted">orders delivered ahead of schedule.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section>
        <h2 class="mb-6">Questions from the coven</h2>
        <div class="accordion-mystic">
            <?php foreach ($faq as $index => $item): ?>
            <article class="accordion-item-mystic">
                <button class="accordion-button-mystic<?php echo $index === 0 ? '' : ' collapsed'; ?>" type="button" data-toggle="accordion">
                    <?php echo htmlspecialchars($item['question']); ?>
                    <span class="accordion-icon">▼</span>
                </button>
                <div class="accordion-body-mystic"<?php echo $index === 0 ? '' : ' hidden'; ?>>
                    <p><?php echo htmlspecialchars($item['answer']); ?></p>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.accordion-button-mystic').forEach((button) => {
        button.addEventListener('click', () => {
            const content = button.nextElementSibling;
            const isOpen = !button.classList.contains('collapsed');
            document.querySelectorAll('.accordion-button-mystic').forEach((other) => {
                if (other !== button) {
                    other.classList.add('collapsed');
                    const panel = other.nextElementSibling;
                    if (panel) {
                        panel.classList.add('hidden');
                    }
                }
            });
            if (isOpen) {
                button.classList.add('collapsed');
                content?.classList.add('hidden');
            } else {
                button.classList.remove('collapsed');
                content?.classList.remove('hidden');
            }
        });
    });
});
</script>

<?php include 'footer.php'; ?>