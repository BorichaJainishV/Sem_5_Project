<?php
session_start();
include 'header.php';
?>

<div class="page-header">
    <div class="container">
        <h1>Shipping Information</h1>
        <p>From our realm to yours, with speed and care.</p>
    </div>
</div>

<div class="container space-y-12">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-center" id="speeds">
        <div class="card p-8 hover-lift">
            <i data-feather="truck" class="w-12 h-12 text-primary mx-auto mb-4"></i>
            <h3 class="text-2xl mb-3">Domestic Shipping</h3>
            <ul class="text-muted space-y-2 text-sm">
                <li><strong>Standard:</strong> 4–6 business days pan India, ₹99 flat for orders under ₹1,999.</li>
                <li><strong>Express:</strong> 2–3 business days for metro routes, ₹199 upgrade.</li>
                <li>Rural routes may add 1–2 days depending on carrier coverage.</li>
            </ul>
        </div>

        <div class="card p-8 hover-lift">
            <i data-feather="zap" class="w-12 h-12 text-primary mx-auto mb-4"></i>
            <h3 class="text-2xl mb-3">Rush Production Add-on</h3>
            <ul class="text-muted space-y-2 text-sm">
                <li>Priority printing queues your order within 24 hours for ₹399.</li>
                <li>Available for catalogue tees, hoodies, and custom apparel with approved artwork.</li>
                <li>Ask live chat to confirm slot availability before purchasing the upgrade.</li>
            </ul>
        </div>

        <div class="card p-8 hover-lift">
            <i data-feather="globe" class="w-12 h-12 text-primary mx-auto mb-4"></i>
            <h3 class="text-2xl mb-3">International Shipping</h3>
            <ul class="text-muted space-y-2 text-sm">
                <li>Priority air network delivers in 7–12 business days.</li>
                <li>Customs duties/taxes are collected by the carrier upon arrival.</li>
                <li>Tracking is available end-to-end via DHL eCommerce or FedEx IP services.</li>
            </ul>
        </div>
    </div>

    <section id="tracking" class="card p-8">
        <h2 class="text-2xl mb-3">Order Tracking & Notifications</h2>
        <ul class="list-disc list-inside text-muted space-y-2">
            <li>Tracking numbers are emailed the moment the courier scans your parcel.</li>
            <li>Add <strong>hello@mysticclothing.com</strong> to your safe-sender list so alerts don’t land in spam.</li>
            <li>Need help locating a shipment? Forward your order confirmation to <a href="contact.php#support" class="text-primary">support</a> and we will trace it with the carrier.</li>
        </ul>
    </section>
</div>

<?php
include 'footer.php';
?>