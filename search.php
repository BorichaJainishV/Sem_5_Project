<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_connection.php';
include 'header.php';

$search_query = $_GET['query'] ?? '';
$products = [];
$error_message = '';

if (!empty($search_query)) {
    $search_term = "%" . trim($search_query) . "%";
    $stmt = $conn->prepare("SELECT * FROM inventory WHERE (product_name LIKE ? OR material_type LIKE ?) AND (is_archived = 0 OR is_archived IS NULL)");
    $stmt->bind_param("ss", $search_term, $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    
    if (empty($products)) {
        $error_message = 'No products found for "' . htmlspecialchars($search_query) . '".';
    }
    $stmt->close();
} else {
    $error_message = 'Please enter a search term.';
}
?>

<main class="container py-12">
    <h1 class="text-3xl font-bold mb-6">
        Search Results for "<?php echo htmlspecialchars($search_query); ?>"
    </h1>

    <?php if (!empty($error_message)): ?>
        <div class="empty-state search-empty">
            <div class="empty-state-icon">
                <i data-feather="search"></i>
            </div>
            <h2 class="empty-state-title">No products found</h2>
            <p class="empty-state-description">
                We couldn't find any products matching "<strong><?php echo htmlspecialchars($search_query); ?></strong>". Try adjusting your search or browse our categories.
            </p>
            <div class="empty-state-actions">
                <a href="shop.php" class="btn btn-primary btn-lg">
                    <i data-feather="grid"></i>
                    Browse All Products
                </a>
                <a href="design3d.php" class="btn btn-outline btn-lg">
                    <i data-feather="edit-3"></i>
                    Design Your Own
                </a>
            </div>
            <div class="empty-state-suggestions">
                <h4>Search Tips</h4>
                <ul class="empty-state-list">
                    <li>
                        <i data-feather="check" class="empty-state-list-icon"></i>
                        <span>Check your spelling or try different keywords</span>
                    </li>
                    <li>
                        <i data-feather="check" class="empty-state-list-icon"></i>
                        <span>Use more general terms (e.g., "shirt" instead of "vintage shirt")</span>
                    </li>
                    <li>
                        <i data-feather="check" class="empty-state-list-icon"></i>
                        <span>Browse our categories to discover new products</span>
                    </li>
                </ul>
            </div>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <div class="product-card">
                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                    <div class="product-card-content">
                        <h3><?php echo htmlspecialchars($product['product_name']); ?></h3>
                        <p class="product-card-price">₹<?php echo number_format($product['price'], 2); ?></p>
                        
                        <?php if ($product['inventory_id'] == 4): // If it's the Custom 3D Design product ?>
                            <a href="design3d.php" class="btn btn-primary w-full mt-4">Start Designing</a>
                        <?php else: // For all other standard products ?>
                            <a href="cart_handler.php?action=add&id=<?php echo $product['inventory_id']; ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token'] ?? ''); ?>" class="btn btn-primary w-full mt-4">Add to Cart</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php include 'footer.php'; ?>