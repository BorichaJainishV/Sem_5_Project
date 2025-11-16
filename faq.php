<?php
session_start();
include 'header.php';
?>

<div class="page-header">
    <div class="container">
        <h1>Ancient Wisdom (FAQ)</h1>
        <p>Answers to the questions we hear most from fellow travelers.</p>
    </div>
</div>

<div class="container">
    <div class="max-w-4xl mx-auto">

        <div class="accordion-mystic" id="faqAccordion">
            <section id="shipping" class="accordion-item-mystic">
                <h2 class="accordion-header" id="headingShipping">
                    <button class="accordion-button-mystic" type="button" data-bs-toggle="collapse" data-bs-target="#collapseShipping" aria-expanded="true">
                        Shipping & Delivery Basics
                        <i data-feather="chevron-down" class="accordion-icon"></i>
                    </button>
                </h2>
                <div id="collapseShipping" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                    <div class="accordion-body-mystic">
                        <p class="mb-3">Production begins within 24–48 hours once your artwork is approved. Parcels leave our Pune print studio via Bluedart or Delhivery and typically arrive within 4–6 business days.</p>
                        <ul class="list-disc list-inside space-y-1 text-muted">
                            <li>Tracking details are emailed to you as soon as the courier scans the package.</li>
                            <li>Express lanes (metro routes) can reach you in 2–3 days—see <a href="#rush" class="text-primary">rush delivery</a> for details.</li>
                            <li>International deliveries take 7–12 business days; customs duties are collected by the carrier.</li>
                        </ul>
                    </div>
                </div>
            </section>

            <section id="shipping-update" class="accordion-item-mystic">
                <h2 class="accordion-header" id="headingShippingUpdate">
                    <button class="accordion-button-mystic collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseShippingUpdate">
                        Updating addresses, sizes, or order details
                        <i data-feather="chevron-down" class="accordion-icon"></i>
                    </button>
                </h2>
                <div id="collapseShippingUpdate" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                    <div class="accordion-body-mystic">
                        <p class="mb-3">Need to tweak your shipping address or garment size? You have a 12-hour window after checkout before we lock garments for printing.</p>
                        <ul class="list-disc list-inside space-y-1 text-muted">
                            <li>Reply to your order confirmation email or send a note via live chat with the updated details.</li>
                            <li>Keep your saved addresses current under <a href="account.php#addresses" class="text-primary">My Account → Orders & Addresses</a>.</li>
                            <li>Once production starts, we can no longer guarantee adjustments, but we will try to accommodate urgent changes.</li>
                        </ul>
                    </div>
                </div>
            </section>

            <section id="design" class="accordion-item-mystic">
                <h2 class="accordion-header" id="headingDesign">
                    <button class="accordion-button-mystic collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDesign">
                        Design changes & artwork preparation
                        <i data-feather="chevron-down" class="accordion-icon"></i>
                    </button>
                </h2>
                <div id="collapseDesign" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                    <div class="accordion-body-mystic">
                        <p class="mb-3">You can reopen saved designs from the 3D studio at any time before print.</p>
                        <ul class="list-disc list-inside space-y-1 text-muted">
                            <li>Revisit drafts under <a href="account.php#saved-designs" class="text-primary">My Account → Saved Designs</a>.</li>
                            <li>Share specific print notes (“center logo”, “deepen black”) in the order comments or via support so our technicians can assist.</li>
                            <li>If you need a designer’s help, open live chat and ask for a revised proof—we pause the print queue until you approve.</li>
                        </ul>
                    </div>
                </div>
            </section>

            <section id="artwork" class="accordion-item-mystic">
                <h2 class="accordion-header" id="headingArtwork">
                    <button class="accordion-button-mystic collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseArtwork">
                        Artwork readiness checklist
                        <i data-feather="chevron-down" class="accordion-icon"></i>
                    </button>
                </h2>
                <div id="collapseArtwork" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                    <div class="accordion-body-mystic">
                        <ul class="list-disc list-inside space-y-1 text-muted">
                            <li>Upload files at 300 DPI or higher with transparent backgrounds for the crispest prints.</li>
                            <li>Keep important elements 0.5 cm inside the safe zone to avoid edge trimming.</li>
                            <li>For embroidery proofs, note desired thread counts or Pantone references when you message us.</li>
                            <li>Need a designer to review your artwork? Visit our <a href="support_artwork.php" class="text-primary">Artwork Support Guide</a> and submit your files.</li>
                        </ul>
                    </div>
                </div>
            </section>

            <section id="billing" class="accordion-item-mystic">
                <h2 class="accordion-header" id="headingBilling">
                    <button class="accordion-button-mystic collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseBilling">
                        Payments, invoices & split billing
                        <i data-feather="chevron-down" class="accordion-icon"></i>
                    </button>
                </h2>
                <div id="collapseBilling" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                    <div class="accordion-body-mystic">
                        <p class="mb-3">We partner with Razorpay to accept UPI, debit/credit cards (Visa, MasterCard, Amex), and netbanking.</p>
                        <ul class="list-disc list-inside space-y-1 text-muted">
                            <li>Download GST invoices from your order confirmation email or the <a href="orders.php#billing" class="text-primary">Order History billing center</a>.</li>
                            <li>Need a split payment or purchase order? Contact <a href="contact.php#billing" class="text-primary">billing support</a> and we will set up a custom payment link.</li>
                            <li>Refunds are processed to the original payment method within 3–5 business days after approval.</li>
                        </ul>
                    </div>
                </div>
            </section>

            <section id="rush" class="accordion-item-mystic">
                <h2 class="accordion-header" id="headingRush">
                    <button class="accordion-button-mystic collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseRush">
                        Rush production & urgent deliveries
                        <i data-feather="chevron-down" class="accordion-icon"></i>
                    </button>
                </h2>
                <div id="collapseRush" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                    <div class="accordion-body-mystic">
                        <ul class="list-disc list-inside space-y-1 text-muted">
                            <li>Priority production adds ₹399 and moves your order to a 24-hour print slot.</li>
                            <li>Express courier for metro cities typically delivers within 2–3 business days.</li>
                            <li>Message us with “Urgent delivery” and your target date—we will confirm feasibility before you pay for the upgrade.</li>
                        </ul>
                    </div>
                </div>
            </section>
        </div>

        <div class="card text-center p-8 mt-16">
            <h3 class="text-2xl">Still Have Questions?</h3>
            <p class="text-muted">Our scribes are ready to assist you. Contact us, and we shall reply with haste.</p>
            <a href="contact.php" class="btn btn-primary mt-4">Contact Us</a>
        </div>
    </div>
</div>

<?php
include 'footer.php';
?>