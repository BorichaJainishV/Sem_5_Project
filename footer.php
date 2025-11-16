<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <h3>Mystic Clothing</h3>
                <p class="mb-4">Design, Print & Wear Your Imagination. Creating unique custom apparel since 2024.</p>
                <div class="footer-social">
                    <a href="#" aria-label="Facebook">
                        <i data-feather="facebook"></i> </a>
                    <a href="#" aria-label="Instagram">
                        <i data-feather="instagram"></i>
                    </a>
                    <a href="#" aria-label="Twitter">
                        <i data-feather="twitter"></i>
                    </a>
                </div>
            </div>
            <div>
                <h3>Navigate</h3> <ul>
                    <li><a href="shop.php">Shop</a></li>
                    <li><a href="design3d.php">3D Designer</a></li>
                    <li><a href="stylist_inbox.php">Stylist Inbox</a></li>
                    <li><a href="about.php">About Us</a></li>
                    <li><a href="contact.php">Contact</a></li>
                </ul>
            </div>
            <div>
                <h3>Information</h3>
                <ul>
                    <li><a href="shipping_info.php">Shipping Info</a></li>
                    <li><a href="size_guide.php">Size Guide</a></li>
                    <li><a href="returns.php">Returns & Exchanges</a></li>
                    <li><a href="faq.php">FAQ</a></li>
                    <li><a href="terms.php">Terms &amp; Conditions</a></li>
                    <li><a href="privacy.php">Privacy Policy</a></li>
                </ul>
            </div>
            <div>
                <h3>Support & Guides</h3>
                <ul>
                    <li><a href="support_artwork.php">Artwork Support Guide</a></li>
                    <li><a href="contact.php#support">Production Support Queue</a></li>
                    <li><a href="size_guide.php#catalog-sizing">Catalog Size Chart</a></li>
                    <li><a href="size_guide.php#custom-sizing">Custom Fit Size Chart</a></li>
                    <li><a href="compliments.php">Compliments Dashboard</a></li>
                </ul>
            </div>
            <div>
                <h3>Newsletter</h3>
                 <p class="text-gray-400 mb-4">Get updates on new products and exclusive offers.</p>
                <form class="newsletter-form" id="newsletterForm" method="POST">
                    <div class="form-group">
                        <input type="email" id="newsletterEmail" name="email" placeholder="Enter your email" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-full">Subscribe</button>
                </form>
            </div>
        </div>
        <div class="footer-bottom">
            <div>
                <p>&copy; <?php echo date('Y'); ?> Mystic Clothing. All Rights Reserved.</p>
            </div>
            <div class="flex gap-6 text-sm">
               <a href="privacy.php" class="text-gray-400 hover:text-white">Privacy Policy</a>
	       <a href="terms.php" class="text-gray-400 hover:text-white">Terms of Service</a>
            </div>
        </div>
    </div>
</footer>

<div class="fixed inset-0 z-50 hidden items-center justify-center bg-black bg-opacity-60 px-4" data-waitlist-modal aria-hidden="true" style="display:none;">
    <div class="absolute inset-0" data-waitlist-close></div>
    <div class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl transition-transform duration-200" data-waitlist-dialog>
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-widest text-indigo-500 font-semibold" data-waitlist-heading>Drop Waitlist</p>
                <h3 class="mt-1 text-2xl font-bold text-gray-900" data-waitlist-title>Reserve your spot</h3>
                <p class="mt-2 text-sm text-gray-600" data-waitlist-subtitle>Jump on the list and we’ll ping you the moment this drop goes live.</p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-600" data-waitlist-close aria-label="Close waitlist modal">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg>
            </button>
        </div>
        <div class="mt-4 hidden rounded-md px-3 py-2 text-sm" data-waitlist-message></div>
        <form class="mt-4 flex flex-col gap-4" data-waitlist-form novalidate>
            <div class="form-group">
                <label for="waitlist-name" class="form-label">Name <span class="text-gray-400">(optional)</span></label>
                <input type="text" id="waitlist-name" name="name" class="form-control" autocomplete="name" placeholder="Your name" />
            </div>
            <div class="form-group">
                <label for="waitlist-email" class="form-label">Email</label>
                <input type="email" id="waitlist-email" name="email" class="form-control" autocomplete="email" placeholder="you@example.com" required />
            </div>
            <button type="submit" class="btn btn-primary w-full" data-waitlist-cta>Notify Me</button>
            <p class="text-xs text-gray-400">No spam—just one drop alert and early access links.</p>
        </form>
    </div>
</div>

<?php if (!isset($_SESSION['customer_id'])): ?>
<div id="login-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 hidden opacity-0 transition-opacity duration-300">
    <div class="bg-surface rounded-lg shadow-2xl p-8 max-w-sm w-full transform transition-all scale-95" id="login-modal-content">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-dark">Welcome Back!</h2>
            <button onclick="toggleModal()" class="text-muted hover:text-dark">
                <i data-feather="x" class="w-6 h-6"></i>
            </button>
        </div>
        <div id="login-message-div" class="hidden text-center p-3 rounded-md mb-4"></div>
        <form action="login_handler.php" method="POST">
            <div class="form-group">
                <label for="modal-email" class="form-label">Email Address</label>
                <input type="email" id="modal-email" name="email" class="form-control" placeholder="you@example.com" required>
            </div>
            <div class="form-group">
                <label for="modal-password" class="form-label">Password</label>
                <input type="password" id="modal-password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-full">Login</button>
        </form>
        <p class="text-center text-muted mt-6">
            Don't have an account? <a href="signup.php" class="text-primary hover:underline font-semibold">Sign Up</a>
        </p>
    </div>
</div>
<?php endif; ?> 
<!-- Toast Notification System -->
<script src="js/toast.js"></script>

<script type="module" src="js/core/dropScheduler.js"></script>

<script src="https://unpkg.com/aos@next/dist/aos.js" defer></script>

<script>
    // --- Consolidated JavaScript in one place ---

    // Placed in global scope to be accessible by onclick attributes
    function toggleModal() {
        const loginModal = document.getElementById('login-modal');
        const loginModalContent = document.getElementById('login-modal-content');
        if (!loginModal || !loginModalContent) return;

        if (loginModal.classList.contains('hidden')) {
            loginModal.classList.remove('hidden');
            setTimeout(() => {
                loginModal.classList.remove('opacity-0');
                loginModalContent.classList.remove('scale-95');
            }, 10);
        } else {
            loginModal.classList.add('opacity-0');
            loginModalContent.classList.add('scale-95');
            setTimeout(() => {
                loginModal.classList.add('hidden');
            }, 300);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // --- Initialize Animate on Scroll ---
        if (window.AOS && typeof AOS.init === 'function') {
            AOS.init({
                duration: 800,
                once: true,
            });
        }

        // --- Initialize Feather Icons ---
        if (typeof feather !== 'undefined') {
          feather.replace();
        }

        // --- Sticky Header Scroll Effect ---
        const header = document.getElementById('main-header');
        let lastScrollY = window.scrollY;

        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
            lastScrollY = window.scrollY;
        });

        // --- Expandable Search ---
        const searchToggle = document.getElementById('searchToggle');
        const searchForm = document.getElementById('searchForm');
        const searchInput = document.getElementById('searchInput');

        if (searchToggle && searchForm) {
            searchToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                searchForm.classList.toggle('active');
                if (searchForm.classList.contains('active') && searchInput) {
                    setTimeout(() => searchInput.focus(), 300);
                }
            });

            // Close search when clicking outside
            document.addEventListener('click', function(e) {
                if (!searchForm.contains(e.target) && !searchToggle.contains(e.target)) {
                    searchForm.classList.remove('active');
                }
            });

            // Prevent closing when clicking inside search form
            searchForm.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }

        // --- Account Dropdown ---
        const accountDropdown = document.getElementById('accountDropdown');
        if (accountDropdown) {
            const dropdownToggle = accountDropdown.querySelector('.account-dropdown-toggle');

            if (dropdownToggle) {
                dropdownToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                accountDropdown.classList.toggle('active');
                const isExpanded = accountDropdown.classList.contains('active');
                dropdownToggle.setAttribute('aria-expanded', isExpanded);
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (!accountDropdown.contains(e.target)) {
                        accountDropdown.classList.remove('active');
                        dropdownToggle.setAttribute('aria-expanded', 'false');
                    }
                });
            }

        }

        // --- Newsletter Form Handler ---
        const newsletterForm = document.getElementById('newsletterForm');
        if (newsletterForm) {
            newsletterForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const emailInput = document.getElementById('newsletterEmail');
                const email = emailInput.value.trim();
                
                if (email) {
                    // Simulate newsletter subscription (you can add actual backend later)
                    if (typeof toastSuccess === 'function') {
                        toastSuccess('Thanks for subscribing! Check your email for confirmation.');
                    }
                    emailInput.value = '';
                } else {
                    if (typeof toastError === 'function') {
                        toastError('Please enter a valid email address.');
                    }
                }
            });
        }

        // --- Mobile Menu Toggle with Animation ---
        const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
        const mobileNavigation = document.getElementById('mobile-navigation');
        if (mobileMenuToggle && mobileNavigation) {
            mobileMenuToggle.addEventListener('click', () => {
                mobileNavigation.classList.toggle('hidden');
                mobileMenuToggle.classList.toggle('active');
                
                // Update aria-expanded for accessibility
                const isExpanded = !mobileNavigation.classList.contains('hidden');
                mobileMenuToggle.setAttribute('aria-expanded', isExpanded);
            });

            // Close mobile menu when clicking outside
            document.addEventListener('click', function(e) {
                if (!mobileMenuToggle.contains(e.target) && !mobileNavigation.contains(e.target)) {
                    mobileNavigation.classList.add('hidden');
                    mobileMenuToggle.classList.remove('active');
                    mobileMenuToggle.setAttribute('aria-expanded', 'false');
                }
            });
        }

        // --- Login Modal Logic ---
        const messageDiv = document.getElementById('login-message-div');
        const loginError = "<?php echo isset($_SESSION['login_error']) ? addslashes($_SESSION['login_error']) : '' ?>";
        const infoMessage = "<?php echo isset($_SESSION['info_message']) ? addslashes($_SESSION['info_message']) : '' ?>";
        const shouldOpenModal = window.location.hash === '#login-modal';

        if (shouldOpenModal && messageDiv) {
            if (loginError) {
                messageDiv.textContent = loginError;
                messageDiv.className = 'block text-center bg-danger-light text-danger p-3 rounded-md mb-4';
                <?php unset($_SESSION['login_error']); ?>
            } else if (infoMessage) {
                messageDiv.textContent = infoMessage;
                messageDiv.className = 'block text-center bg-info-light text-info p-3 rounded-md mb-4';
                <?php unset($_SESSION['info_message']); ?>
            }
            toggleModal();
            history.pushState("", document.title, window.location.pathname + window.location.search);
        }
        
        const loginModal = document.getElementById('login-modal');
        if (loginModal) {
            loginModal.addEventListener('click', (e) => {
                if (e.target === loginModal) toggleModal();
            });
        }
    });
</script>

</body>
</html>