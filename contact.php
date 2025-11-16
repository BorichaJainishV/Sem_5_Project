<?php include 'header.php'; ?>

<div class="page-header">
    <div class="container">
        <h1>Contact Us</h1>
        <p>We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
    </div>
</div>

<main class="container">
    <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
        <div class="alert alert-success">
            <h4>Thank you for your message!</h4>
            <p>We'll get back to you soon.</p>
        </div>
    <?php elseif (isset($_GET['status']) && $_GET['status'] == 'error'): ?>
        <div class="alert alert-danger">
            <h4>Error</h4>
            <p>Please fill all fields with a valid email.</p>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-2 gap-12">
        <!-- Contact Form -->
        <div id="support">
            <form action="contact_handler.php" method="POST" class="form-container">
                <h2 class="mb-6">Send us a Message</h2>
                
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input id="name" name="name" type="text" class="form-control" placeholder="Enter your full name" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input id="email" name="email" type="email" class="form-control" placeholder="Enter your email address" required>
                </div>

                <div class="form-group">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" rows="6" class="form-control" placeholder="Enter your message..." required></textarea>
                </div>

                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <button type="submit" class="btn btn-primary btn-lg w-full">Send Message</button>
            </form>
        </div>

        <!-- Contact Information -->
        <div id="billing">
            <div class="card">
                <div class="card-body">
                    <h2 class="mb-6">Get in Touch</h2>
                    
                    <div class="space-y-6">
                        <div class="flex items-start gap-4">
                            <div class="bg-primary-light p-3 rounded-lg">
                                <i data-feather="map-pin" class="text-primary"></i>
                            </div>
                            <div>
                                <h4>Address</h4>
                                <p class="text-muted">Rajkot, Gujarat, India</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-4">
                            <div class="bg-primary-light p-3 rounded-lg">
                                <i data-feather="phone" class="text-primary"></i>
                            </div>
                            <div>
                                <h4>Phone</h4>
                                <p class="text-muted">+91 XXXXXXXXXX</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-4">
                            <div class="bg-primary-light p-3 rounded-lg">
                                <i data-feather="mail" class="text-primary"></i>
                            </div>
                            <div>
                                <h4>Email</h4>
                                <p class="text-muted">contact@mysticclothing.com<br><span class="text-sm">Billing queries: finance@mysticclothing.com</span></p>
                            </div>
                        </div>

                        <div class="flex items-start gap-4">
                            <div class="bg-primary-light p-3 rounded-lg">
                                <i data-feather="clock" class="text-primary"></i>
                            </div>
                            <div>
                                <h4>Business Hours</h4>
                                <p class="text-muted">Mon - Fri: 9:00 AM - 6:00 PM<br>Sat - Sun: 10:00 AM - 4:00 PM</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
