<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['customer_id'])) {
    $_SESSION['info_message'] = 'You need to log in to view compliments.';
    header('Location: login.php#login-modal');
    exit();
}

include 'header.php';
include 'db_connection.php';

$customerId = (int) $_SESSION['customer_id'];

$feedbackTableCheck = $conn->query("SHOW TABLES LIKE 'customer_feedback'");
$hasFeedbackTable = $feedbackTableCheck && $feedbackTableCheck->num_rows > 0;
if ($feedbackTableCheck) {
    $feedbackTableCheck->free();
}

$compliments = [];
if ($hasFeedbackTable) {
    $sql = "SELECT cf.id,
                   cf.order_id,
                   cf.rating,
                   cf.feedback_text,
                   cf.created_at,
                   o.order_date,
                   i.product_name,
                   i.image_url,
                   d.design_file
              FROM customer_feedback cf
              JOIN orders o ON cf.order_id = o.order_id AND o.customer_id = ?
              LEFT JOIN inventory i ON o.inventory_id = i.inventory_id
              LEFT JOIN designs d ON o.design_id = d.design_id
             WHERE cf.rating >= 4
          ORDER BY cf.created_at DESC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        $result = $stmt->get_result();
        $compliments = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }
}
?>

<style>
    .compliment-layout {
        display: grid;
        grid-template-columns: minmax(0, 3fr) minmax(0, 1fr);
        gap: 2rem;
    }
    .compliment-list {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }
    .compliment-card {
        display: grid;
        grid-template-columns: 72px 1fr;
        gap: 1.25rem;
        padding: 1.25rem;
        border-radius: 1rem;
        border: 1px solid rgba(15, 23, 42, 0.08);
        background: #ffffff;
        box-shadow: 0 15px 35px rgba(15, 23, 42, 0.08);
    }
    .compliment-thumb {
        width: 72px;
        height: 72px;
        border-radius: 0.85rem;
        object-fit: cover;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
    }
    .compliment-meta {
        font-size: 0.8rem;
        color: #64748b;
        margin-bottom: 0.35rem;
    }
    .compliment-rating {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.25rem 0.65rem;
        background: rgba(250, 204, 21, 0.15);
        color: #b45309;
        font-size: 0.75rem;
        border-radius: 999px;
        font-weight: 600;
    }
    .compliment-sidebar {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }
    .compliment-sidebar .card {
        border-radius: 1rem;
        border: 1px solid rgba(99, 102, 241, 0.15);
        background: linear-gradient(180deg, rgba(99, 102, 241, 0.08), rgba(129, 140, 248, 0.04));
    }
    @media (max-width: 1024px) {
        .compliment-layout {
            grid-template-columns: 1fr;
        }
    }
</style>

<main class="container py-12">
    <h1 class="text-3xl font-bold mb-6">Compliments & Feedback</h1>
    <p class="text-gray-600 mb-10">See what customers highlighted about your recent orders.</p>

    <?php if (!$hasFeedbackTable): ?>
        <div class="card p-8">
            <h2 class="text-xl font-semibold mb-3">Compliments not available yet</h2>
            <p class="text-gray-600">The customer feedback feature has not been enabled on this store. Once enabled, compliments and high ratings will appear here automatically.</p>
        </div>
    <?php else: ?>
        <div class="compliment-layout">
            <section>
                <?php if (empty($compliments)): ?>
                    <div class="card p-10 text-center">
                        <h2 class="text-xl font-semibold mb-3">No compliments yet</h2>
                        <p class="text-gray-600 mb-4">Once customers start leaving positive feedback (rating 4 or higher), you will see it here.</p>
                        <a href="shop.php" class="btn btn-primary">Create a design to delight</a>
                    </div>
                <?php else: ?>
                    <div class="compliment-list">
                        <?php foreach ($compliments as $compliment):
                            $image = $compliment['design_file'] && $compliment['design_file'] !== 'N/A'
                                ? $compliment['design_file']
                                : ($compliment['image_url'] ?: 'image/placeholder.png');
                            $dateLabel = !empty($compliment['created_at'])
                                ? date('M j, Y', strtotime($compliment['created_at']))
                                : date('M j, Y');
                        ?>
                            <article class="compliment-card">
                                <img class="compliment-thumb" src="<?php echo htmlspecialchars($image); ?>" alt="Order preview">
                                <div>
                                    <div class="compliment-meta">
                                        Order #<?php echo (int) $compliment['order_id']; ?> • <?php echo htmlspecialchars($compliment['product_name'] ?: 'Custom Apparel'); ?> • <?php echo $dateLabel; ?>
                                    </div>
                                    <div class="compliment-rating">Rating: <?php echo (int) $compliment['rating']; ?>/5</div>
                                    <p class="mt-3 text-lg text-slate-800">“<?php echo htmlspecialchars($compliment['feedback_text']); ?>”</p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <aside class="compliment-sidebar">
                <div class="card p-6">
                    <h2 class="text-xl font-semibold mb-3">How compliments work</h2>
                    <p class="text-sm text-indigo-900">Whenever a customer rates an order 4 stars or higher and leaves feedback, it will be captured automatically. Use these insights to refine your designs or reprint bestsellers.</p>
                    <div class="mt-4 space-y-2 text-sm text-indigo-900">
                        <p>• Ratings &gt;= 4 are considered compliments.</p>
                        <p>• Compliments include the product name, order date, and rating.</p>
                        <p>• You can export this data directly from your database.</p>
                    </div>
                </div>
                <div class="card p-6">
                    <h2 class="text-xl font-semibold mb-3">Need the SQL?</h2>
                    <p class="text-sm text-indigo-900">Use the statement below to create the <code>customer_feedback</code> table if it is missing.</p>
                    <pre class="bg-white border border-indigo-100 rounded-lg text-xs p-4 overflow-auto">CREATE TABLE customer_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    feedback_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_feedback_order
        FOREIGN KEY (order_id) REFERENCES orders(order_id)
        ON DELETE CASCADE
);
</pre>
                </div>
            </aside>
        </div>
    <?php endif; ?>
</main>

<?php include 'footer.php'; ?>
