<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/core/timezone.php';
$cart_count = (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) ? array_sum($_SESSION['cart']) : 0;
$css_version = "3.0.1"; // Version bump
$topBannerMessage = null;

require_once __DIR__ . '/core/banner_manager.php';
$storedBanner = get_active_flash_banner();

if (!empty($_SESSION['flash_banner']) && is_array($_SESSION['flash_banner'])) {
    $candidateBanner = $_SESSION['flash_banner'];
    $candidateVisibility = [];
    if (isset($candidateBanner['visibility']) && is_array($candidateBanner['visibility'])) {
        $candidateVisibility = array_values(array_unique(array_map('strval', $candidateBanner['visibility'])));
    }

    if (empty($candidateVisibility) || in_array('storefront', $candidateVisibility, true)) {
        $topBannerMessage = $candidateBanner;
    }
}

if (!$topBannerMessage && $storedBanner) {
    $storedVisibility = [];
    if (isset($storedBanner['visibility']) && is_array($storedBanner['visibility'])) {
        $storedVisibility = array_values(array_unique(array_map('strval', $storedBanner['visibility'])));
    }

    if (empty($storedVisibility) || in_array('storefront', $storedVisibility, true)) {
        $topBannerMessage = $storedBanner;
    }
}

$bannerVariant = $topBannerMessage['variant'] ?? 'promo';
$bannerGradients = [
    'promo' => 'from-indigo-600 via-purple-500 to-pink-500',
    'info' => 'from-sky-600 via-cyan-500 to-emerald-500',
    'alert' => 'from-rose-600 via-red-500 to-orange-500',
];
$bannerGradientClass = $bannerGradients[$bannerVariant] ?? $bannerGradients['promo'];
$bannerId = $topBannerMessage['id'] ?? ('banner_' . ($topBannerMessage['created_at'] ?? time()));
$bannerMode = $topBannerMessage['mode'] ?? 'standard';
$variantLabels = ['promo' => 'Promotion', 'info' => 'Info', 'alert' => 'Urgent'];
$bannerVariantLabel = $variantLabels[$bannerVariant] ?? 'Promotion';
$dropBannerHtml = '';
$dismissVersion = null;
$serverNowIso = null;

if ($topBannerMessage) {
    $dismissSeed = $topBannerMessage['updated_at']
        ?? $topBannerMessage['countdown_target_ts']
        ?? $topBannerMessage['schedule_start_ts']
        ?? time();
    $dismissVersion = (string) $dismissSeed . (!empty($topBannerMessage['dismissible']) ? '_dismissible' : '_locked');

    if (function_exists('mystic_banner_iso')) {
        $serverNowIso = mystic_banner_iso(time());
    } else {
        $serverNowIso = date(DateTimeInterface::ATOM);
    }
}

if (!function_exists('render_icon')) {
    function render_icon(string $name, string $class = 'icon'): string
    {
        static $icons = [
            'search' => '<svg class="%s" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>',
            'arrow-right' => '<svg class="%s" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>',
            'shopping-cart' => '<svg class="%s" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 001.98 1.61h9.72a2 2 0 001.98-1.61L23 6H6"></path></svg>',
            'user' => '<svg class="%s" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>',
            'chevron-down' => '<svg class="%s" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>',
            'package' => '<svg class="%s" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16.5 9.4l-9-5.2"></path><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22" x2="12" y2="12"></line></svg>',
            'heart' => '<svg class="%s" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"></path></svg>',
            'log-out' => '<svg class="%s" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>',
            'log-in' => '<svg class="%s" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>',
        ];

        $class = trim($class) === '' ? 'icon' : trim($class);
        return isset($icons[$name]) ? sprintf($icons[$name], htmlspecialchars($class, ENT_QUOTES)) : '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php
    // CSRF token
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    ?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>" />
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Mystic Clothing - Design Your Imagination</title>
    <script src="https://unpkg.com/feather-icons" defer></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;700;800&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo $css_version; ?>">
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
    <meta name="description" content="Create custom t-shirts with Mystic Clothing's 3D designer. High-quality apparel, unique designs, fast printing.">
    <meta name="keywords" content="custom t-shirts, 3d designer, apparel, clothing, printing, design">
    
    <link rel="icon" type="image/png" href="image/magic-cursor.png">
<link rel="stylesheet" href="css/cleaned_style.css">

</head>
<body>

<header class="header" id="main-header">
    <?php if ($topBannerMessage): ?>
        <?php if ($bannerMode === 'drop'): ?>
            <?php
            require_once __DIR__ . '/partials/drop_banner.php';
            $dropBannerHtml = render_drop_banner(
                $topBannerMessage,
                $bannerGradientClass,
                $bannerId,
                $bannerVariantLabel,
                [
                    'dismiss_version' => $dismissVersion,
                    'server_now_iso' => $serverNowIso,
                ]
            );
            ?>
        <?php else: ?>
            <div data-banner-id="<?php echo htmlspecialchars($bannerId); ?>" data-dismiss-version="<?php echo htmlspecialchars($dismissVersion ?? ''); ?>" class="bg-gradient-to-r <?php echo $bannerGradientClass; ?> text-white flash-banner">
                <div class="container flex flex-col gap-3 py-3 text-sm md:flex-row md:items-center md:justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <?php if (!empty($topBannerMessage['badge'])): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full border border-white/40 text-xs font-semibold uppercase tracking-wider">
                                    <?php echo htmlspecialchars($topBannerMessage['badge']); ?>
                                </span>
                            <?php endif; ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-white/20 text-xs font-semibold uppercase tracking-wider">
                                <?php echo htmlspecialchars($bannerVariantLabel); ?>
                            </span>
                        </div>
                        <p class="mt-1 font-semibold text-base md:text-lg"><?php echo htmlspecialchars($topBannerMessage['message'] ?? ''); ?></p>
                        <?php if (!empty($topBannerMessage['subtext'])): ?>
                            <p class="mt-1 text-xs md:text-sm text-white/80 max-w-3xl leading-relaxed"><?php echo htmlspecialchars($topBannerMessage['subtext']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-3">
                        <?php if (!empty($topBannerMessage['cta']) && !empty($topBannerMessage['href'])): ?>
                            <a href="<?php echo htmlspecialchars($topBannerMessage['href']); ?>" class="inline-flex items-center gap-1.5 rounded-lg bg-white/20 px-4 py-2 text-sm font-semibold hover:bg-white/30 transition" target="_blank" rel="noopener">
                                <?php echo htmlspecialchars($topBannerMessage['cta']); ?>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7l5 5-5 5M6 12h12" /></svg>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($topBannerMessage['dismissible'])): ?>
                            <button type="button" data-dismiss-banner class="inline-flex items-center justify-center rounded-full bg-white/10 p-2 text-white hover:bg-white/20 transition" aria-label="Dismiss announcement">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    <div class="container header-content">
    <a href="index.php" class="brand">Mystic Clothing</a>

        <nav class="main-nav" id="main-navigation">
            <a href="shop.php">Shop</a>
            <a href="design3d.php">3D Designer</a>
            <a href="stylist_inbox.php">Stylist Inbox</a>
            <?php if (isset($_SESSION['customer_id'])): ?>
            <a href="orders.php">My Orders</a>
            <?php endif; ?>
            <a href="about.php">About</a>
            <a href="contact.php">Contact</a>
        </nav>

        <div class="actions">
            <!-- Expandable Search -->
            <div class="header-search">
                <button class="header-search-toggle" id="searchToggle" type="button" aria-label="Toggle search">
                    <?php echo render_icon('search'); ?>
                </button>
                <form action="search.php" method="GET" class="header-search-form" id="searchForm">
                    <input 
                        type="text" 
                        name="query" 
                        placeholder="Search products..." 
                        class="header-search-input"
                        id="searchInput"
                        required>
                    <button type="submit" class="header-search-submit" aria-label="Submit search">
                        <?php echo render_icon('arrow-right', 'icon icon-sm'); ?>
                    </button>
                </form>
            </div>

            <a href="cart.php" class="cart-link" aria-label="View Shopping Cart">
                <?php echo render_icon('shopping-cart', 'icon icon-lg'); ?>
                <?php if ($cart_count > 0): ?>
                    <span class="cart-badge"><?php echo $cart_count; ?></span>
                <?php endif; ?>
            </a>
            
            <?php if (isset($_SESSION['customer_id'])): ?>
                <!-- Account Dropdown -->
                <div class="account-dropdown" id="accountDropdown">
                    <button class="account-dropdown-toggle" type="button" aria-expanded="false" aria-haspopup="true">
                        <?php echo render_icon('user'); ?>
                        <span class="account-label">Account</span>
                        <?php echo render_icon('chevron-down', 'icon icon-sm'); ?>
                    </button>
                    <div class="account-dropdown-menu" role="menu">
                        <div class="account-dropdown-header">
                            <strong><?php echo htmlspecialchars($_SESSION['name']); ?></strong>
                            <p><?php echo htmlspecialchars($_SESSION['email']); ?></p>
                        </div>
                        <a href="account.php" class="account-dropdown-item" role="menuitem">
                            <?php echo render_icon('user', 'icon icon-sm'); ?>
                            Account Info
                        </a>
                        <a href="orders.php" class="account-dropdown-item" role="menuitem">
                            <?php echo render_icon('package', 'icon icon-sm'); ?>
                            My Orders
                        </a>
                        <a href="compliments.php" class="account-dropdown-item" role="menuitem">
                            <?php echo render_icon('heart', 'icon icon-sm'); ?>
                            Compliments
                        </a>
                        <div class="account-dropdown-divider"></div>
                        <a href="logout.php" class="account-dropdown-item" role="menuitem">
                            <?php echo render_icon('log-out', 'icon icon-sm'); ?>
                            Logout
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="flex items-center gap-4">
                <a href="login.php" class="btn btn-outline btn-sm btn-outline-light">
                    <?php echo render_icon('log-in', 'icon icon-sm icon-leading'); ?>
                        Login
                    </a>
                    <a href="signup.php" class="btn btn-primary btn-sm">Sign Up</a>   
                </div>
            <?php endif; ?>
        </div>

        <button class="menu-toggle" id="mobile-menu-toggle" aria-label="Toggle Mobile Menu">
            <div class="menu-toggle-icon">
                <span class="menu-toggle-line"></span>
                <span class="menu-toggle-line"></span>
                <span class="menu-toggle-line"></span>
            </div>
        </button>
    </div>
    
    <nav class="mobile-nav hidden" id="mobile-navigation">
        <div class="container">
            <div class="mobile-nav-content">
                <a href="shop.php" class="mobile-nav-link">Shop</a>
                <a href="design3d.php" class="mobile-nav-link">3D Designer</a>
                <a href="stylist_inbox.php" class="mobile-nav-link">Stylist Inbox</a>
                <a href="about.php" class="mobile-nav-link">About</a>
                <a href="contact.php" class="mobile-nav-link">Contact</a>
                
                <div class="mobile-nav-actions">
                    <?php if (isset($_SESSION['customer_id'])): ?>
                        <a href="account.php" class="mobile-nav-link">Account</a>
                        <a href="compliments.php" class="mobile-nav-link">Compliments</a>
                        <a href="orders.php" class="mobile-nav-link">My Orders</a>
                        <a href="logout.php" class="btn btn-outline btn-outline-light w-full">Logout</a>
                    <?php else: ?>
        <a href="login.php" class="btn btn-outline btn-outline-light w-full mb-2">Login</a>
	<a href="signup.php" class="btn btn-primary w-full">Sign Up</a>  
 <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
</header>

<?php if ($dropBannerHtml !== ''): ?>
    <?php echo $dropBannerHtml; ?>
<?php endif; ?>

<?php if ($topBannerMessage && !empty($topBannerMessage['dismissible'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var banner = document.querySelector('[data-banner-id="<?php echo htmlspecialchars($bannerId); ?>"]');
    if (!banner) { return; }
    var bannerId = banner.getAttribute('data-banner-id') || 'banner';
    var dismissVersion = banner.getAttribute('data-dismiss-version') || '';
    var storageKey = 'mysticFlashBannerDismissed_' + bannerId;

    try {
        if (window.localStorage) {
            var stored = localStorage.getItem(storageKey);
            if (stored) {
                var storedVersion = stored;
                try {
                    var parsed = JSON.parse(stored);
                    if (parsed && typeof parsed === 'object' && parsed.version) {
                        storedVersion = parsed.version;
                    }
                } catch (error) {
                    storedVersion = stored;
                }

                if (dismissVersion !== '' && storedVersion === dismissVersion) {
                    banner.remove();
                    return;
                }
            }
        }
    } catch (error) {
        // ignore storage access errors and show banner
    }

    var dismissButton = banner.querySelector('[data-dismiss-banner]');
    if (!dismissButton) { return; }
    dismissButton.addEventListener('click', function () {
        banner.classList.add('hidden');
        banner.style.display = 'none';
        try {
            if (window.localStorage) {
                var payload = JSON.stringify({
                    version: dismissVersion || '1',
                    dismissedAt: Date.now()
                });
                localStorage.setItem(storageKey, payload);
            }
        } catch (error) {
            // ignore storage access errors
        }
    });
});
</script>
<?php endif; ?>