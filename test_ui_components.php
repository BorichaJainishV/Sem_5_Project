<?php
session_start();
require_once 'db_connection.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UI Components Test - Mystic Clothing</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://unpkg.com/feather-icons"></script>
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="container py-5">
        <h1 class="mb-4">UI Components Testing Page</h1>

        <!-- Toast Notification Tests -->
        <section class="mb-5">
            <h2 class="mb-3">🔔 Toast Notifications</h2>
            <p class="mb-3">Click the buttons below to test different toast variants:</p>
            <div class="flex gap-3 flex-wrap">
                <button onclick="toastSuccess('Item added to cart successfully!')" class="btn btn-primary">
                    Success Toast
                </button>
                <button onclick="toastError('Failed to process payment. Please try again.')" class="btn" style="background: #ef4444; color: white;">
                    Error Toast
                </button>
                <button onclick="toastWarning('Your session will expire in 5 minutes.')" class="btn" style="background: #f59e0b; color: white;">
                    Warning Toast
                </button>
                <button onclick="toastInfo('New collection launching tomorrow!')" class="btn" style="background: #3b82f6; color: white;">
                    Info Toast
                </button>
                <button onclick="testMultipleToasts()" class="btn btn-secondary">
                    Multiple Toasts
                </button>
            </div>
        </section>

        <!-- Skeleton Loaders Test -->
        <section class="mb-5">
            <h2 class="mb-3">⏳ Skeleton Loaders</h2>
            <p class="mb-3">Toggle between loading state and actual content:</p>
            <button onclick="toggleSkeletons()" class="btn btn-primary mb-4" id="toggleBtn">
                Show Skeletons
            </button>

            <div id="contentArea">
                <!-- Real Product Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4" id="realContent">
                    <div class="product-card">
                        <img src="image/placeholder-product.jpg" alt="Product" style="width: 100%; height: 300px; object-fit: cover; background: #e5e7eb;">
                        <div class="p-4">
                            <h3 class="font-semibold mb-2">Classic T-Shirt</h3>
                            <p class="text-sm text-gray-600 mb-3">Premium cotton blend with custom design</p>
                            <div class="flex justify-between items-center">
                                <span class="font-bold text-lg">$29.99</span>
                                <button class="btn btn-primary btn-sm">Add to Cart</button>
                            </div>
                        </div>
                        <div class="product-card-overlay">
                            <div class="product-card-overlay-actions">
                                <button class="btn btn-primary btn-sm">Quick View</button>
                                <button class="btn btn-secondary btn-sm">Add to Cart</button>
                            </div>
                        </div>
                    </div>

                    <div class="product-card">
                        <img src="image/placeholder-product.jpg" alt="Product" style="width: 100%; height: 300px; object-fit: cover; background: #e5e7eb;">
                        <div class="p-4">
                            <h3 class="font-semibold mb-2">Hoodie Special</h3>
                            <p class="text-sm text-gray-600 mb-3">Comfortable fleece with unique artwork</p>
                            <div class="flex justify-between items-center">
                                <span class="font-bold text-lg">$49.99</span>
                                <button class="btn btn-primary btn-sm">Add to Cart</button>
                            </div>
                        </div>
                        <div class="product-card-overlay">
                            <div class="product-card-overlay-actions">
                                <button class="btn btn-primary btn-sm">Quick View</button>
                                <button class="btn btn-secondary btn-sm">Add to Cart</button>
                            </div>
                        </div>
                    </div>

                    <div class="product-card">
                        <img src="image/placeholder-product.jpg" alt="Product" style="width: 100%; height: 300px; object-fit: cover; background: #e5e7eb;">
                        <div class="p-4">
                            <h3 class="font-semibold mb-2">Custom Jacket</h3>
                            <p class="text-sm text-gray-600 mb-3">Premium denim with personalization</p>
                            <div class="flex justify-between items-center">
                                <span class="font-bold text-lg">$89.99</span>
                                <button class="btn btn-primary btn-sm">Add to Cart</button>
                            </div>
                        </div>
                        <div class="product-card-overlay">
                            <div class="product-card-overlay-actions">
                                <button class="btn btn-primary btn-sm">Quick View</button>
                                <button class="btn btn-secondary btn-sm">Add to Cart</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Skeleton Loaders (hidden by default) -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4" id="skeletonContent" style="display: none;">
                    <div class="skeleton-card">
                        <div class="skeleton-image"></div>
                        <div class="skeleton-content">
                            <div class="skeleton-title"></div>
                            <div class="skeleton-text"></div>
                            <div class="skeleton-text" style="width: 60%;"></div>
                            <div class="skeleton-button"></div>
                        </div>
                    </div>

                    <div class="skeleton-card">
                        <div class="skeleton-image"></div>
                        <div class="skeleton-content">
                            <div class="skeleton-title"></div>
                            <div class="skeleton-text"></div>
                            <div class="skeleton-text" style="width: 60%;"></div>
                            <div class="skeleton-button"></div>
                        </div>
                    </div>

                    <div class="skeleton-card">
                        <div class="skeleton-image"></div>
                        <div class="skeleton-content">
                            <div class="skeleton-title"></div>
                            <div class="skeleton-text"></div>
                            <div class="skeleton-text" style="width: 60%;"></div>
                            <div class="skeleton-button"></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Product Card Hover Test -->
        <section class="mb-5">
            <h2 class="mb-3">✨ Product Card Hover Effects</h2>
            <p class="mb-3">Hover over the product cards above to see:</p>
            <ul class="list-disc pl-6 mb-3">
                <li>Smooth 12px lift animation</li>
                <li>Enhanced shadow effect</li>
                <li>Quick-view overlay with action buttons</li>
            </ul>
        </section>

        <!-- Primary Button Shimmer Test -->
        <section class="mb-5">
            <h2 class="mb-3">🌟 Button Enhancements</h2>
            <p class="mb-3">Hover over the primary button to see the shimmer effect:</p>
            <div class="flex gap-3 flex-wrap">
                <button class="btn btn-primary">Primary Button with Shimmer</button>
                <button class="btn btn-secondary">Secondary Button</button>
                <button class="btn btn-outline">Outline Button</button>
            </div>
        </section>

        <!-- Instructions -->
        <section class="mb-5 p-4" style="background: #f3f4f6; border-radius: 8px;">
            <h3 class="mb-3">📋 Testing Checklist:</h3>
            <ul class="list-disc pl-6 space-y-2">
                <li><strong>Toast Notifications:</strong> Click each button and verify toasts slide in from top-right, auto-dismiss after 5 seconds, and can be manually closed</li>
                <li><strong>Skeleton Loaders:</strong> Toggle to see smooth shimmer animation effect</li>
                <li><strong>Product Cards:</strong> Hover to see lift effect and quick-view overlay</li>
                <li><strong>Buttons:</strong> Hover over primary button to see shimmer effect sweep across</li>
                <li><strong>Dark Mode:</strong> Switch your system to dark mode and test all components</li>
                <li><strong>Mobile:</strong> Resize browser to mobile view and test responsiveness</li>
            </ul>
        </section>
    </main>

    <?php include 'footer.php'; ?>

    <!-- Load Toast JS -->
    <script src="js/toast.js"></script>

    <!-- Test Scripts -->
    <script>
        // Toggle skeleton loaders
        let showingSkeletons = false;
        function toggleSkeletons() {
            const realContent = document.getElementById('realContent');
            const skeletonContent = document.getElementById('skeletonContent');
            const toggleBtn = document.getElementById('toggleBtn');
            
            showingSkeletons = !showingSkeletons;
            
            if (showingSkeletons) {
                realContent.style.display = 'none';
                skeletonContent.style.display = 'grid';
                toggleBtn.textContent = 'Show Real Content';
            } else {
                realContent.style.display = 'grid';
                skeletonContent.style.display = 'none';
                toggleBtn.textContent = 'Show Skeletons';
            }
        }

        // Test multiple toasts
        function testMultipleToasts() {
            toastInfo('Processing your request...');
            setTimeout(() => {
                toastSuccess('Payment received!');
            }, 1000);
            setTimeout(() => {
                toastSuccess('Order confirmed!');
            }, 2000);
            setTimeout(() => {
                toastInfo('Shipping notification sent.');
            }, 3000);
        }

        // Initialize Feather Icons
        feather.replace();
    </script>
</body>
</html>
