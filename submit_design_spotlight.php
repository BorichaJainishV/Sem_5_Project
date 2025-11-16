<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: account.php#design-spotlight');
    exit();
}

if (!isset($_SESSION['customer_id'])) {
    $_SESSION['info_message'] = 'Log in to share your Design Spotlight story.';
    header('Location: login.php#login-modal');
    exit();
}

require_once __DIR__ . '/db_connection.php';

$redirectRaw = $_POST['redirect'] ?? 'account.php';
$redirectBase = 'account.php';
$redirectAnchor = '';
if (!empty($redirectRaw)) {
    $parts = explode('#', $redirectRaw, 2);
    $candidate = basename($parts[0]);
    if ($candidate === 'account.php') {
        $redirectBase = $candidate;
        if (!empty($parts[1]) && preg_match('/^[a-z0-9\-]+$/i', $parts[1])) {
            $redirectAnchor = '#' . $parts[1];
        }
    }
}
$redirect = $redirectBase . $redirectAnchor;

$csrfToken = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    $_SESSION['account_flash'] = 'Session expired while sending your spotlight. Please try again.';
    $_SESSION['account_flash_type'] = 'error';
    header('Location: ' . $redirect);
    exit();
}

$customerId = (int) $_SESSION['customer_id'];
$designId = isset($_POST['design_id']) ? (int) $_POST['design_id'] : 0;
$title = trim((string) ($_POST['title'] ?? ''));
$story = trim((string) ($_POST['story'] ?? ''));
$homepageQuote = trim((string) ($_POST['homepage_quote'] ?? ''));
$inspirationUrl = trim((string) ($_POST['inspiration_url'] ?? ''));
$instagramHandle = trim((string) ($_POST['instagram_handle'] ?? ''));
$shareGallery = isset($_POST['share_gallery']) ? 1 : 0;

if (function_exists('mb_substr')) {
    $title = mb_substr($title, 0, 120);
    $story = mb_substr($story, 0, 600);
    $homepageQuote = mb_substr($homepageQuote, 0, 160);
    $inspirationUrl = mb_substr($inspirationUrl, 0, 255);
    $instagramHandle = mb_substr($instagramHandle, 0, 80);
} else {
    $title = substr($title, 0, 120);
    $story = substr($story, 0, 600);
    $homepageQuote = substr($homepageQuote, 0, 160);
    $inspirationUrl = substr($inspirationUrl, 0, 255);
    $instagramHandle = substr($instagramHandle, 0, 80);
}

$errors = [];
if ($title === '') {
    $errors[] = 'Give your spotlight entry a title.';
}
$storyLength = function_exists('mb_strlen') ? mb_strlen($story) : strlen($story);
if ($storyLength < 60) {
    $errors[] = 'Share at least 60 characters about the inspiration behind your design.';
}
$quoteLength = function_exists('mb_strlen') ? mb_strlen($homepageQuote) : strlen($homepageQuote);
if ($quoteLength < 20) {
    $errors[] = 'Add a short quote we can feature on the homepage (at least 20 characters).';
}
if ($inspirationUrl !== '' && !filter_var($inspirationUrl, FILTER_VALIDATE_URL)) {
    $errors[] = 'Please provide a valid inspiration link (or leave it blank).';
}
if ($instagramHandle !== '' && $instagramHandle[0] !== '@') {
    $instagramHandle = '@' . ltrim($instagramHandle);
    if (function_exists('mb_substr')) {
        $instagramHandle = mb_substr($instagramHandle, 0, 80);
    } else {
        $instagramHandle = substr($instagramHandle, 0, 80);
    }
}

$designPreview = null;
if ($designId > 0) {
    $designStmt = $conn->prepare('SELECT front_preview_url FROM custom_designs WHERE design_id = ? AND customer_id = ? LIMIT 1');
    if ($designStmt) {
        $designStmt->bind_param('ii', $designId, $customerId);
        $designStmt->execute();
        $designResult = $designStmt->get_result();
        $designRow = $designResult ? $designResult->fetch_assoc() : null;
        if (!$designRow) {
            $errors[] = 'We could not find that design. Pick another from your list.';
        } else {
            $designPreview = $designRow['front_preview_url'] ?? null;
        }
        if ($designResult) {
            $designResult->free();
        }
        $designStmt->close();
    } else {
        $errors[] = 'Unable to verify the selected design right now.';
    }
}

if (!empty($errors)) {
    $_SESSION['account_flash'] = $errors[0];
    $_SESSION['account_flash_type'] = 'error';
    header('Location: ' . $redirect);
    exit();
}

$tableSql = "CREATE TABLE IF NOT EXISTS design_spotlight_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    design_id INT NULL,
    title VARCHAR(120) NOT NULL,
    story TEXT NOT NULL,
    homepage_quote VARCHAR(160) DEFAULT NULL,
    inspiration_url VARCHAR(255) DEFAULT NULL,
    instagram_handle VARCHAR(80) DEFAULT NULL,
    design_preview VARCHAR(255) DEFAULT NULL,
    share_gallery TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    moderated_at TIMESTAMP NULL DEFAULT NULL,
    moderated_by INT NULL,
    INDEX idx_customer (customer_id),
    INDEX idx_design (design_id),
    INDEX idx_status (status),
    CONSTRAINT fk_spotlight_customer FOREIGN KEY (customer_id) REFERENCES customer(customer_id) ON DELETE CASCADE,
    CONSTRAINT fk_spotlight_design FOREIGN KEY (design_id) REFERENCES custom_designs(design_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (!$conn->query($tableSql)) {
    error_log('design_spotlight_submissions table create failed: ' . $conn->error);
    $_SESSION['account_flash'] = 'We could not save your spotlight right now. Please try again later.';
    $_SESSION['account_flash_type'] = 'error';
    header('Location: ' . $redirect);
    exit();
}

$columnCheckResult = $conn->query("SHOW COLUMNS FROM design_spotlight_submissions");
$existingColumns = [];
if ($columnCheckResult) {
    while ($col = $columnCheckResult->fetch_assoc()) {
        $existingColumns[] = $col['Field'];
    }
    $columnCheckResult->free();
}

$alterStatements = [];
if (!in_array('moderated_at', $existingColumns, true)) {
    $alterStatements[] = "ADD COLUMN moderated_at TIMESTAMP NULL DEFAULT NULL AFTER created_at";
}
if (!in_array('moderated_by', $existingColumns, true)) {
    $alterStatements[] = "ADD COLUMN moderated_by INT NULL AFTER moderated_at";
}
if (!in_array('homepage_quote', $existingColumns, true)) {
    $alterStatements[] = "ADD COLUMN homepage_quote VARCHAR(160) DEFAULT NULL AFTER story";
}
if (!in_array('status', $existingColumns, true)) {
    $alterStatements[] = "ADD COLUMN status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending' AFTER share_gallery";
}

if (!empty($alterStatements)) {
    $alterSql = 'ALTER TABLE design_spotlight_submissions ' . implode(', ', $alterStatements);
    if (!$conn->query($alterSql)) {
        error_log('design_spotlight_submissions alter failed: ' . $conn->error);
    }
}

$indexCheck = $conn->query("SHOW INDEX FROM design_spotlight_submissions WHERE Key_name = 'idx_design_spotlight_status'");
$hasStatusIndex = $indexCheck && $indexCheck->num_rows > 0;
if ($indexCheck) { $indexCheck->free(); }
if (!$hasStatusIndex) {
    if (!$conn->query("CREATE INDEX idx_design_spotlight_status ON design_spotlight_submissions (status)")) {
        error_log('design_spotlight_submissions index create failed: ' . $conn->error);
    }
}

$insertSql = 'INSERT INTO design_spotlight_submissions (customer_id, design_id, title, story, homepage_quote, inspiration_url, instagram_handle, design_preview, share_gallery) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
$insertStmt = $conn->prepare($insertSql);
if (!$insertStmt) {
    error_log('prepare submit_design_spotlight failed: ' . $conn->error);
    $_SESSION['account_flash'] = 'We could not submit your spotlight right now. Please try again later.';
    $_SESSION['account_flash_type'] = 'error';
    header('Location: ' . $redirect);
    exit();
}

$designIdParam = $designId > 0 ? $designId : null;
$inspirationParam = $inspirationUrl !== '' ? $inspirationUrl : null;
$instagramParam = $instagramHandle !== '' ? $instagramHandle : null;
$previewParam = $designPreview !== '' ? $designPreview : null;

$quoteParam = $homepageQuote !== '' ? $homepageQuote : null;

$insertStmt->bind_param(
    'iissssssi',
    $customerId,
    $designIdParam,
    $title,
    $story,
    $quoteParam,
    $inspirationParam,
    $instagramParam,
    $previewParam,
    $shareGallery
);

if ($insertStmt->execute()) {
    $_SESSION['account_flash'] = 'Thanks! Your Design Spotlight submission is now pending review.';
    $_SESSION['account_flash_type'] = 'success';
} else {
    error_log('execute submit_design_spotlight failed: ' . $insertStmt->error);
    $_SESSION['account_flash'] = 'We could not save your spotlight right now. Please try again later.';
    $_SESSION['account_flash_type'] = 'error';
}

$insertStmt->close();

header('Location: ' . $redirect);
exit();
