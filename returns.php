<?php
session_start();
include 'header.php';
?>

<div class="page-header">
    <div class="container">
        <h1>Returns & Exchanges</h1>
        <p>Your satisfaction is our highest spellcraft.</p>
    </div>
</div>

<div class="container">
    <div class="max-w-4xl mx-auto">
        <div class="text-center mb-12">
            <h2 class="text-3xl">Our Promise To You</h2>
            <p class="text-lg text-muted">We want you to be completely satisfied with your purchase from Mystic Clothing. If you're not happy with your order for any reason, we are here to help.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="card p-8">
                <div class="flex items-center gap-4 mb-4">
                    <i data-feather="corner-up-left" class="w-8 h-8 text-primary"></i>
                    <h3 class="text-2xl m-0">Easy Returns</h3>
                </div>
                <p class="text-muted">Return any unworn, unwashed, and undamaged items within <strong>30 days</strong> of delivery for a full refund. To begin, simply email us your order number.</p>
                <a href="mailto:jvb.ombca2023@gmail.com" class="btn btn-primary mt-4">Initiate a Return</a>
            </div>

            <div class="card p-8">
                <div class="flex items-center gap-4 mb-4">
                    <i data-feather="refresh-cw" class="w-8 h-8 text-primary"></i>
                    <h3 class="text-2xl m-0">Quick Exchanges</h3>
                </div>
                <p class="text-muted">Need a different size or color? Contact us within 30 days of receiving your order, and we'll process the exchange once we receive the original item.</p>
                 <a href="mailto:jvb.ombca2023@gmail.com" class="btn btn-outline mt-4">Request an Exchange</a>
            </div>
        </div>
    </div>
</div>

<?php
include 'footer.php';
?>