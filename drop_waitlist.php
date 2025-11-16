<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/core/banner_manager.php';
require_once __DIR__ . '/core/drop_waitlist.php';

$requestedSlug = strtolower(trim((string) ($_GET['drop'] ?? $_GET['slug'] ?? '')));
$activeBanner = get_active_flash_banner();
$effectiveSlug = $requestedSlug;
$activeDrop = null;

if ($activeBanner && ($activeBanner['mode'] ?? '') === 'drop') {
    $bannerSlug = strtolower(trim((string) ($activeBanner['waitlist_slug'] ?? $activeBanner['drop_slug'] ?? '')));
    if ($bannerSlug !== '') {
        if ($effectiveSlug === '' || $effectiveSlug === $bannerSlug) {
            $effectiveSlug = $bannerSlug;
            $activeDrop = $activeBanner;
        }
    }
}

$formState = [
    'status' => null,
    'code' => null,
    'message' => null,
];
$formErrors = [];
$formValues = [
    'name' => '',
    'email' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string) ($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        $formErrors[] = 'Your session expired. Please refresh the page and try again.';
    }

    $postedSlug = strtolower(trim((string) ($_POST['drop_slug'] ?? '')));
    if ($postedSlug !== '') {
        $effectiveSlug = $postedSlug;
        if ($activeBanner && ($activeBanner['mode'] ?? '') === 'drop') {
            $bannerSlug = strtolower(trim((string) ($activeBanner['waitlist_slug'] ?? $activeBanner['drop_slug'] ?? '')));
            if ($bannerSlug === $postedSlug) {
                $activeDrop = $activeBanner;
            }
        }
    }

    $formValues['name'] = trim((string) ($_POST['name'] ?? ''));
    $formValues['email'] = trim((string) ($_POST['email'] ?? ''));

    if ($effectiveSlug === '') {
        $formErrors[] = 'We could not identify which drop this waitlist belongs to.';
    }

    if ($formValues['email'] === '') {
        $formErrors[] = 'Please provide an email address so we can send your invite.';
    } elseif (!filter_var($formValues['email'], FILTER_VALIDATE_EMAIL)) {
        $formErrors[] = 'Enter a valid email address to continue.';
    }

    if (empty($formErrors)) {
        $result = record_waitlist_signup($effectiveSlug, [
            'email' => $formValues['email'],
            'name' => $formValues['name'],
            'source' => 'waitlist_page',
            'context' => [
                'path' => $_SERVER['REQUEST_URI'] ?? '',
                'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            ],
        ]);

        $status = $result['status'] ?? 'error';
        switch ($status) {
            case 'stored':
                $successCopy = $activeDrop['waitlist_success_copy'] ?? 'You are confirmed for this drop. We will send you early access before it unlocks.';
                $formState = [
                    'status' => 'success',
                    'code' => 'stored',
                    'message' => $successCopy,
                ];
                $formValues = ['name' => '', 'email' => ''];
                break;
            case 'exists':
                $formState = [
                    'status' => 'success',
                    'code' => 'exists',
                    'message' => $result['message'] ?? 'You are already on this waitlist. We will keep you posted.',
                ];
                break;
            case 'rate_limited':
                $formState = [
                    'status' => 'error',
                    'code' => 'rate_limited',
                    'message' => $result['message'] ?? 'We have a few too many attempts from this device. Please try again shortly.',
                ];
                break;
            case 'invalid':
                $formState = [
                    'status' => 'error',
                    'code' => 'invalid',
                    'message' => $result['message'] ?? 'Please double-check the details and try again.',
                ];
                break;
            default:
                $formState = [
                    'status' => 'error',
                    'code' => 'error',
                    'message' => $result['message'] ?? 'We could not save your request just now. Please try again later.',
                ];
                break;
        }
    } else {
        $formState = [
            'status' => 'error',
            'code' => 'validation',
            'message' => 'We need a little more information before we can add you to the waitlist.',
        ];
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$dropTitle = $activeDrop['message'] ?? 'Join the Mystic Drop Waitlist';
$dropLabel = trim((string) ($activeDrop['drop_label'] ?? ''));
$dropSubtext = $activeDrop['subtext'] ?? 'Secure first access to our next limited release by joining the list below.';
$dropBadge = $activeDrop['badge'] ?? '';
$dropHighlights = [];
if ($activeDrop && !empty($activeDrop['drop_highlights']) && is_array($activeDrop['drop_highlights'])) {
    $dropHighlights = array_values(array_filter($activeDrop['drop_highlights'], static fn($item) => trim((string) $item) !== ''));
}
$dropStory = $activeDrop['drop_story'] ?? '';
$dropAccessNotes = $activeDrop['drop_access_notes'] ?? '';
$dropMediaUrl = $activeDrop['drop_media_url'] ?? '';
$scheduleStart = isset($activeDrop['schedule_start_ts']) ? (int) $activeDrop['schedule_start_ts'] : null;
$scheduleEnd = isset($activeDrop['schedule_end_ts']) ? (int) $activeDrop['schedule_end_ts'] : null;
$dropSlugDisplay = $effectiveSlug !== '' ? strtoupper($effectiveSlug) : null;

$formDisabled = ($formState['status'] === 'success' && in_array($formState['code'], ['stored', 'exists'], true));

include 'header.php';
?>

<div class="page-header">
    <div class="container space-y-4">
        <?php if ($dropBadge !== ''): ?>
            <span class="inline-flex items-center px-3 py-1 rounded-full bg-primary-light text-primary font-semibold tracking-wide uppercase text-xs">
                <?php echo htmlspecialchars($dropBadge, ENT_QUOTES); ?>
            </span>
        <?php endif; ?>
        <?php if ($dropLabel !== ''): ?>
            <p class="text-xs uppercase tracking-[0.3em] text-muted font-semibold">Drop · <?php echo htmlspecialchars($dropLabel, ENT_QUOTES); ?></p>
        <?php endif; ?>
        <h1 class="text-4xl font-bold leading-tight max-w-3xl">Join the waitlist for <?php echo htmlspecialchars($dropTitle, ENT_QUOTES); ?></h1>
        <p class="text-lg text-muted max-w-3xl"><?php echo htmlspecialchars($dropSubtext, ENT_QUOTES); ?></p>
    </div>
</div>

<main class="container py-12">
    <?php if ($formState['status'] === 'success'): ?>
        <div class="alert alert-success mb-8">
            <?php echo htmlspecialchars($formState['message'] ?? 'You are confirmed for this drop.'); ?>
        </div>
    <?php elseif ($formState['status'] === 'error'): ?>
        <div class="alert alert-danger mb-8">
            <p class="font-semibold mb-2">We hit a small snag.</p>
            <p><?php echo htmlspecialchars($formState['message'] ?? 'Please review the form and try again.'); ?></p>
            <?php if (!empty($formErrors)): ?>
                <ul class="mt-3 space-y-1">
                    <?php foreach ($formErrors as $error): ?>
                        <li>• <?php echo htmlspecialchars($error, ENT_QUOTES); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
        <section class="card p-8 shadow-xl">
            <h2 class="text-2xl font-semibold mb-2">Reserve your early access slot</h2>
            <p class="text-muted mb-6">Drop your email (and name if you like). We will send a private unlock link as soon as the collection opens.</p>

            <form method="POST" action="drop_waitlist.php<?php echo $effectiveSlug !== '' ? '?drop=' . rawurlencode($effectiveSlug) : ''; ?>" class="space-y-5">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES); ?>">
                <input type="hidden" name="drop_slug" value="<?php echo htmlspecialchars($effectiveSlug, ENT_QUOTES); ?>">

                <div class="form-group">
                    <label for="waitlist-name" class="form-label">Name <span class="text-muted text-sm">(optional)</span></label>
                    <input
                        id="waitlist-name"
                        name="name"
                        type="text"
                        class="form-control"
                        placeholder="Your name"
                        value="<?php echo htmlspecialchars($formValues['name'], ENT_QUOTES); ?>"
                        <?php echo $formDisabled ? 'disabled' : ''; ?>
                    >
                </div>

                <div class="form-group">
                    <label for="waitlist-email" class="form-label">Email address</label>
                    <input
                        id="waitlist-email"
                        name="email"
                        type="email"
                        class="form-control"
                        placeholder="you@example.com"
                        value="<?php echo htmlspecialchars($formValues['email'], ENT_QUOTES); ?>"
                        required
                        <?php echo $formDisabled ? 'disabled' : ''; ?>
                    >
                </div>

                <button type="submit" class="btn btn-primary w-full" <?php echo $formDisabled ? 'disabled' : ''; ?>>
                    <?php echo $formDisabled ? 'You are on the list' : 'Join the Waitlist'; ?>
                </button>
                <p class="text-xs text-muted">We only use your email to send secure drop access—no spam, ever.</p>
            </form>
        </section>

        <aside class="space-y-6">
            <?php if ($dropMediaUrl !== ''): ?>
                <div class="rounded-2xl overflow-hidden shadow-lg border border-white/10">
                    <img src="<?php echo htmlspecialchars($dropMediaUrl, ENT_QUOTES); ?>" alt="Drop preview" class="w-full h-64 object-cover">
                </div>
            <?php endif; ?>

            <div class="card p-6">
                <div class="space-y-4">
                    <?php if ($dropSlugDisplay): ?>
                        <div>
                            <p class="text-xs tracking-[0.3em] uppercase text-muted">Drop code</p>
                            <p class="mt-1 text-lg font-semibold text-dark"><?php echo htmlspecialchars($dropSlugDisplay, ENT_QUOTES); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($scheduleStart): ?>
                        <div>
                            <p class="text-xs tracking-[0.3em] uppercase text-muted">Opens</p>
                            <p class="mt-1 text-lg font-semibold text-dark"><?php echo htmlspecialchars(date('l · M j · g:ia T', $scheduleStart), ENT_QUOTES); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($scheduleEnd): ?>
                        <div>
                            <p class="text-xs tracking-[0.3em] uppercase text-muted">Closes</p>
                            <p class="mt-1 text-lg font-semibold text-dark"><?php echo htmlspecialchars(date('l · M j · g:ia T', $scheduleEnd), ENT_QUOTES); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($dropStory !== ''): ?>
                        <div>
                            <h3 class="text-sm tracking-[0.2em] uppercase text-muted">Drop story</h3>
                            <p class="mt-2 text-body leading-relaxed"><?php echo nl2br(htmlspecialchars($dropStory, ENT_QUOTES)); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($dropHighlights)): ?>
                        <div>
                            <h3 class="text-sm tracking-[0.2em] uppercase text-muted">Highlights</h3>
                            <ul class="mt-2 space-y-2 text-body">
                                <?php foreach ($dropHighlights as $highlight): ?>
                                    <li class="flex gap-2">
                                        <span class="mt-1 h-2 w-2 rounded-full bg-primary"></span>
                                        <span><?php echo htmlspecialchars($highlight, ENT_QUOTES); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($dropAccessNotes !== ''): ?>
                        <div>
                            <h3 class="text-sm tracking-[0.2em] uppercase text-muted">Access notes</h3>
                            <p class="mt-2 text-body leading-relaxed"><?php echo nl2br(htmlspecialchars($dropAccessNotes, ENT_QUOTES)); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
    </div>
</main>

<?php include 'footer.php'; ?>
