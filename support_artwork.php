<?php
session_start();
include 'header.php';
?>

<div class="page-header">
    <div class="container">
        <h1>Artwork Support Guide</h1>
        <p>Share your mockups with our print specialists for feedback before we hit the press.</p>
    </div>
</div>

<div class="container max-w-4xl mx-auto space-y-12 py-6">
    <section>
        <h2 class="text-2xl font-semibold mb-3">What we review</h2>
        <ul class="list-disc list-inside text-muted space-y-2">
            <li>File quality (300 DPI, transparent backgrounds, bleed and safe zones).</li>
            <li>Embellishment readiness (embroidery thread counts, specialty inks, foil layers).</li>
            <li>Color management notes—share Pantone references or target hex codes for accuracy.</li>
        </ul>
    </section>

    <section>
        <h2 class="text-2xl font-semibold mb-3">How to request a proof</h2>
        <ol class="list-decimal list-inside text-muted space-y-2">
            <li>Attach your artwork files (PNG, PDF, or AI) and include the design ID if it is saved in the 3D studio.</li>
            <li>Tell us about placement, scale, or any special print finishes you expect.</li>
            <li>Our team replies within one business day with comments or a revised proof.</li>
        </ol>
    </section>

    <section>
        <h2 class="text-2xl font-semibold mb-3">Send your files</h2>
        <p class="text-muted mb-3">Email <a href="mailto:studio@mysticclothing.com" class="text-primary">studio@mysticclothing.com</a> or use the contact form below. Be sure to mention your order number (if placed) so we can match it instantly.</p>
        <a href="contact.php#support" class="btn btn-primary">Open support form</a>
    </section>
</div>

<?php
include 'footer.php';
?>
