<?php
// ---------------------------------------------------------------------
// /admin/spotlight.php - Design Spotlight moderation queue
// ---------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
if (!isset($_SESSION['admin_id'])) { header('Location: index.php'); exit(); }

include '../db_connection.php';
require_once 'activity_logger.php';

$schemaError = null;

function ensure_spotlight_moderation_schema(mysqli $conn): ?string
{
    $createSql = "CREATE TABLE IF NOT EXISTS design_spotlight_submissions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        customer_id INT UNSIGNED NOT NULL,
        design_id INT UNSIGNED NULL,
        title VARCHAR(120) NOT NULL,
        story TEXT NOT NULL,
        homepage_quote VARCHAR(160) DEFAULT NULL,
        inspiration_url VARCHAR(255) DEFAULT NULL,
        instagram_handle VARCHAR(80) DEFAULT NULL,
        design_preview VARCHAR(255) DEFAULT NULL,
        share_gallery TINYINT(1) NOT NULL DEFAULT 0,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        moderated_at DATETIME NULL DEFAULT NULL,
        moderated_by INT UNSIGNED NULL,
        INDEX idx_customer (customer_id),
        INDEX idx_design (design_id),
        INDEX idx_status (status),
        INDEX idx_spotlight_status_created (status, created_at),
        CONSTRAINT fk_spotlight_customer FOREIGN KEY (customer_id) REFERENCES customer(customer_id) ON DELETE CASCADE,
        CONSTRAINT fk_spotlight_design FOREIGN KEY (design_id) REFERENCES custom_designs(design_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!$conn->query($createSql)) {
        return 'Unable to ensure spotlight submissions table exists. ' . $conn->error;
    }

    $columnChecks = [
        'moderated_at' => "ALTER TABLE design_spotlight_submissions ADD COLUMN moderated_at DATETIME NULL DEFAULT NULL AFTER created_at",
        'moderated_by' => "ALTER TABLE design_spotlight_submissions ADD COLUMN moderated_by INT UNSIGNED NULL AFTER moderated_at",
        'homepage_quote' => "ALTER TABLE design_spotlight_submissions ADD COLUMN homepage_quote VARCHAR(160) DEFAULT NULL AFTER story",
    ];

    foreach ($columnChecks as $column => $alterSql) {
        $columnResult = $conn->query("SHOW COLUMNS FROM design_spotlight_submissions LIKE '" . $conn->real_escape_string($column) . "'");
        if ($columnResult instanceof mysqli_result) {
            $exists = $columnResult->num_rows > 0;
            $columnResult->free();
            if (!$exists) {
                if (!$conn->query($alterSql)) {
                    return 'Unable to add column ' . $column . '. ' . $conn->error;
                }
            }
        } else {
            return 'Unable to inspect columns for spotlight submissions. ' . $conn->error;
        }
    }

    $indexResult = $conn->query("SHOW INDEX FROM design_spotlight_submissions WHERE Key_name = 'idx_spotlight_status_created'");
    if ($indexResult instanceof mysqli_result) {
        $hasIndex = $indexResult->num_rows > 0;
        $indexResult->free();
        if (!$hasIndex) {
            if (!$conn->query("ALTER TABLE design_spotlight_submissions ADD INDEX idx_spotlight_status_created (status, created_at)")) {
                return 'Unable to add spotlight status index. ' . $conn->error;
            }
        }
    } else {
        return 'Unable to inspect indexes for spotlight submissions. ' . $conn->error;
    }

    return null;
}

$schemaError = ensure_spotlight_moderation_schema($conn);

$adminId = (int) $_SESSION['admin_id'];
$flashMessage = $_SESSION['admin_flash'] ?? null;
$flashType = $_SESSION['admin_flash_type'] ?? 'info';
unset($_SESSION['admin_flash'], $_SESSION['admin_flash_type']);

if ($schemaError !== null) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $_SESSION['admin_flash'] = $schemaError;
        $_SESSION['admin_flash_type'] = 'error';
        header('Location: spotlight.php');
        exit();
    }
    $flashMessage = $flashMessage ? $flashMessage . ' ' . $schemaError : $schemaError;
    $flashType = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        $_SESSION['admin_flash'] = 'Session expired while updating the spotlight queue. Please try again.';
        $_SESSION['admin_flash_type'] = 'error';
        header('Location: spotlight.php');
        exit();
    }

    $submissionId = isset($_POST['submission_id']) ? (int) $_POST['submission_id'] : 0;
    $action = $_POST['action'] ?? '';
    $statusMap = [
        'approve' => 'approved',
        'reject' => 'rejected',
        'pending' => 'pending',
    ];

    if ($submissionId <= 0 || !isset($statusMap[$action])) {
        $_SESSION['admin_flash'] = 'We could not find that submission. Refresh the page and try again.';
        $_SESSION['admin_flash_type'] = 'error';
        header('Location: spotlight.php');
        exit();
    }

    $targetStatus = $statusMap[$action];

    $lookupStmt = $conn->prepare('SELECT title, status FROM design_spotlight_submissions WHERE id = ?');
    if ($lookupStmt) {
        $lookupStmt->bind_param('i', $submissionId);
        $lookupStmt->execute();
        $lookupResult = $lookupStmt->get_result();
        $submissionRow = $lookupResult ? $lookupResult->fetch_assoc() : null;
        if ($lookupResult) { $lookupResult->free(); }
        $lookupStmt->close();
    } else {
        $submissionRow = null;
    }

    if (!$submissionRow) {
        $_SESSION['admin_flash'] = 'That submission no longer exists.';
        $_SESSION['admin_flash_type'] = 'error';
        header('Location: spotlight.php');
        exit();
    }

    if ($submissionRow['status'] === $targetStatus) {
        $_SESSION['admin_flash'] = 'No change needed - this submission is already marked as ' . $targetStatus . '.';
        $_SESSION['admin_flash_type'] = 'info';
        header('Location: spotlight.php');
        exit();
    }

    if ($targetStatus === 'pending') {
        $updateStmt = $conn->prepare('UPDATE design_spotlight_submissions SET status = ?, moderated_at = NULL, moderated_by = NULL WHERE id = ?');
    } else {
        $updateStmt = $conn->prepare('UPDATE design_spotlight_submissions SET status = ?, moderated_at = NOW(), moderated_by = ? WHERE id = ?');
    }

    if ($updateStmt) {
        if ($targetStatus === 'pending') {
            $updateStmt->bind_param('si', $targetStatus, $submissionId);
        } else {
            $updateStmt->bind_param('sii', $targetStatus, $adminId, $submissionId);
        }
        $updateStmt->execute();
        $updateStmt->close();

        log_admin_activity($adminId, 'spotlight_status_update', [
            'submission_id' => $submissionId,
            'title' => $submissionRow['title'] ?? '',
            'new_status' => $targetStatus,
        ]);

        $statusLabels = [
            'approved' => 'approved',
            'rejected' => 'rejected',
            'pending' => 'moved back to pending',
        ];

    $_SESSION['admin_flash'] = 'Submission "' . htmlspecialchars($submissionRow['title'], ENT_QUOTES, 'UTF-8') . '" marked as ' . ($statusLabels[$targetStatus] ?? $targetStatus) . '.';
        $_SESSION['admin_flash_type'] = 'success';
    } else {
        $_SESSION['admin_flash'] = 'We could not update that submission. Please try again later.';
        $_SESSION['admin_flash_type'] = 'error';
    }

    header('Location: spotlight.php');
    exit();
}

$statusBuckets = [
    'pending' => [],
    'approved' => [],
    'rejected' => [],
];

$counts = [
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
];

$submissionsStmt = $conn->query("SELECT s.*, c.name AS customer_name, c.email AS customer_email FROM design_spotlight_submissions s LEFT JOIN customer c ON s.customer_id = c.customer_id ORDER BY s.created_at DESC");
if ($submissionsStmt) {
    while ($row = $submissionsStmt->fetch_assoc()) {
        $status = strtolower((string) ($row['status'] ?? 'pending'));
        if (!isset($statusBuckets[$status])) {
            $status = 'pending';
        }
        $statusBuckets[$status][] = $row;
        $counts[$status]++;
    }
    $submissionsStmt->free();
}

$totalSubmissions = array_sum($counts);

function truncate_story(?string $story, int $limit = 220): string
{
    $story = trim((string) $story);
    if ($story === '') {
        return 'No story provided yet.';
    }
    $length = function_exists('mb_strlen') ? mb_strlen($story) : strlen($story);
    if ($length <= $limit) {
        return $story;
    }
    $snippet = function_exists('mb_substr') ? mb_substr($story, 0, $limit) : substr($story, 0, $limit);
    return rtrim($snippet) . '...';
}

function format_homepage_quote(?string $quote): string
{
    $quote = trim((string) $quote);
    if ($quote === '') {
        return 'No quote submitted yet.';
    }
    $length = function_exists('mb_strlen') ? mb_strlen($quote) : strlen($quote);
    if ($length <= 160) {
        return $quote;
    }
    $snippet = function_exists('mb_substr') ? mb_substr($quote, 0, 160) : substr($quote, 0, 160);
    return rtrim($snippet) . '...';
}

function format_relative_time(?string $timestamp): string
{
    if (!$timestamp) {
        return 'Pending review';
    }
    $time = strtotime($timestamp);
    if (!$time) {
        return 'Pending review';
    }
    return date('M j, Y \a\t H:i', $time);
}

function spotlight_status_badge(string $status): string
{
    $map = [
        'pending' => 'bg-amber-500/20 text-amber-200 border border-amber-400/30',
        'approved' => 'bg-emerald-500/20 text-emerald-200 border border-emerald-400/30',
        'rejected' => 'bg-rose-500/20 text-rose-200 border border-rose-400/30',
    ];
    $label = ucfirst($status);
    $classes = $map[$status] ?? 'bg-slate-500/20 text-slate-200 border border-slate-300/30';
    return '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold uppercase tracking-wide ' . $classes . '">' . htmlspecialchars($label) . '</span>';
}

function resolve_spotlight_preview(?string $path): string
{
    $path = trim((string) $path);
    if ($path === '') {
        return '../image/placeholder.png';
    }
    if (preg_match('#^https?://#i', $path) || strpos($path, 'data:') === 0) {
        return $path;
    }
    if (strpos($path, '../') === 0) {
        return $path;
    }
    if (strpos($path, '/') === 0) {
        return '..' . $path;
    }
    return '../' . $path;
}
?>
<?php $ADMIN_TITLE = 'Design Spotlight Queue'; require_once __DIR__ . '/_header.php'; ?>
<?php $ADMIN_BODY_CLASS = 'min-h-screen bg-gradient-to-br from-slate-900 via-indigo-900 to-slate-800 text-slate-50'; ?>
<?php $ADMIN_TITLE = 'Design Spotlight Queue'; require_once __DIR__ . '/_header.php'; ?>
<div class="flex-1 p-10">
        <div class="flex items-center justify-between gap-4 flex-wrap mb-6">
            <div>
                <h1 class="text-3xl font-bold text-white">Design Spotlight Queue</h1>
                <p class="text-indigo-100/80 text-sm">Approve the most inspiring custom creations before they hit the storefront spotlight.</p>
            </div>
            <div class="bg-white/10 border border-white/10 rounded-xl px-4 py-3 text-sm text-indigo-100">
                <span class="font-semibold text-white text-lg mr-1"><?php echo (int) $counts['pending']; ?></span> pending &middot;
                <span class="font-semibold text-emerald-200 text-lg mx-1"><?php echo (int) $counts['approved']; ?></span> approved &middot;
                <span class="font-semibold text-rose-200 text-lg ml-1"><?php echo (int) $counts['rejected']; ?></span> rejected &middot;
                <span class="text-xs text-indigo-100/70 ml-2">Total <?php echo (int) $totalSubmissions; ?></span>
            </div>
        </div>

        <?php if ($flashMessage): ?>
            <?php
                $flashClasses = [
                    'success' => 'bg-emerald-500/15 border border-emerald-400/40 text-emerald-100',
                    'error' => 'bg-rose-500/15 border border-rose-400/40 text-rose-100',
                    'info' => 'bg-indigo-500/15 border border-indigo-400/40 text-indigo-100',
                ][$flashType] ?? 'bg-indigo-500/15 border border-indigo-400/40 text-indigo-100';
            ?>
            <div class="mb-6 px-4 py-3 rounded-xl <?php echo $flashClasses; ?>">
                <?php echo htmlspecialchars($flashMessage); ?>
            </div>
        <?php endif; ?>

        <?php
            $sections = [
                'pending' => 'Awaiting review',
                'approved' => 'Approved designs',
                'rejected' => 'Rejected or needs more info',
            ];
        ?>

        <?php foreach ($sections as $statusKey => $heading): ?>
            <section class="mb-12">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <h2 class="text-2xl font-semibold text-white flex items-center gap-3">
                        <?php echo htmlspecialchars($heading); ?>
                        <?php echo spotlight_status_badge($statusKey); ?>
                        <span class="text-indigo-200 text-sm font-medium">(<?php echo (int) $counts[$statusKey]; ?>)</span>
                    </h2>
                </div>
                <?php if (empty($statusBuckets[$statusKey])): ?>
                    <div class="border border-dashed border-white/20 rounded-xl px-6 py-8 text-sm text-indigo-100/70">
                        <?php if ($statusKey === 'pending'): ?>
                            No submissions need moderation right now. Encourage customers to share their designs!
                        <?php elseif ($statusKey === 'approved'): ?>
                            Nothing approved yet. Approve a submission to feature it on the storefront spotlight feed.
                        <?php else: ?>
                            No rejected submissions. Everything is either pending review or already approved.
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                        <?php foreach ($statusBuckets[$statusKey] as $submission):
                            $preview = resolve_spotlight_preview($submission['design_preview'] ?? '');
                            $customerName = $submission['customer_name'] ?? 'Mystic customer';
                            $customerEmail = $submission['customer_email'] ?? '';
                            $moderatedLabel = format_relative_time($submission['moderated_at'] ?? null);
                            $story = truncate_story($submission['story'] ?? '');
                            $rawHomepageQuote = isset($submission['homepage_quote']) ? trim((string) $submission['homepage_quote']) : '';
                            $homepageQuoteDisplay = $rawHomepageQuote !== '' ? format_homepage_quote($rawHomepageQuote) : null;
                            $shareGallery = !empty($submission['share_gallery']);
                            $createdAtLabel = date('M j, Y \a\t H:i', strtotime($submission['created_at'] ?? 'now'));
                        ?>
                        <article class="bg-white/10 border border-white/10 rounded-2xl overflow-hidden shadow-lg backdrop-blur">
                            <div class="h-44 bg-slate-900/40 flex items-center justify-center overflow-hidden">
                                <img src="<?php echo htmlspecialchars($preview); ?>" alt="<?php echo htmlspecialchars($submission['title']); ?> preview" class="h-full w-full object-cover">
                            </div>
                            <div class="p-5 space-y-3 text-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="text-lg font-semibold text-white leading-tight"><?php echo htmlspecialchars($submission['title']); ?></h3>
                                        <p class="text-xs text-indigo-100/70">Submitted <?php echo htmlspecialchars($createdAtLabel); ?></p>
                                    </div>
                                    <?php echo spotlight_status_badge($statusKey); ?>
                                </div>
                                <p class="text-indigo-100/80 leading-relaxed"><?php echo htmlspecialchars($story); ?></p>
                                <?php if ($homepageQuoteDisplay !== null): ?>
                                    <div class="bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-xs text-indigo-100/90 italic">
                                        &ldquo;<?php echo htmlspecialchars($homepageQuoteDisplay); ?>&rdquo;
                                    </div>
                                <?php endif; ?>
                                <div class="text-xs text-indigo-100/60 space-y-1">
                                    <p>Customer: <?php echo htmlspecialchars($customerName); ?><?php if ($customerEmail): ?> &middot; <a class="underline" href="mailto:<?php echo htmlspecialchars($customerEmail); ?>">Email</a><?php endif; ?></p>
                                    <p>Last action: <?php echo htmlspecialchars($moderatedLabel); ?></p>
                                    <?php if (!empty($submission['instagram_handle'])): ?>
                                        <p>Instagram: <?php echo htmlspecialchars($submission['instagram_handle']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($submission['inspiration_url'])): ?>
                                        <p><a href="<?php echo htmlspecialchars($submission['inspiration_url']); ?>" target="_blank" rel="noopener" class="text-indigo-200 underline">View inspiration link</a></p>
                                    <?php endif; ?>
                                    <p><?php echo $shareGallery ? 'Opted in for gallery sharing' : 'Gallery sharing not yet approved'; ?></p>
                                </div>
                                <div class="flex flex-wrap gap-2 pt-2 border-t border-white/10">
                                    <?php if ($statusKey !== 'approved'): ?>
                                        <form method="post" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <input type="hidden" name="submission_id" value="<?php echo (int) $submission['id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="px-3 py-2 text-sm font-semibold rounded-lg bg-emerald-500/80 text-white hover:bg-emerald-500 transition">Approve</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($statusKey !== 'pending'): ?>
                                        <form method="post" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <input type="hidden" name="submission_id" value="<?php echo (int) $submission['id']; ?>">
                                            <input type="hidden" name="action" value="pending">
                                            <button type="submit" class="px-3 py-2 text-sm font-semibold rounded-lg bg-indigo-500/40 text-indigo-100 hover:bg-indigo-500/60 transition">Move to pending</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($statusKey !== 'rejected'): ?>
                                        <form method="post" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <input type="hidden" name="submission_id" value="<?php echo (int) $submission['id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="px-3 py-2 text-sm font-semibold rounded-lg bg-rose-500/70 text-white hover:bg-rose-500 transition">Reject</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>
