<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: account.php');
    exit();
}

if (!isset($_SESSION['customer_id'])) {
    $_SESSION['info_message'] = 'You need to log in to leave feedback.';
    header('Location: login.php#login-modal');
    exit();
}

require_once __DIR__ . '/db_connection.php';

$redirectRaw = $_POST['redirect'] ?? 'account.php';
$redirect = basename($redirectRaw);
if ($redirect !== 'account.php') {
    $redirect = 'account.php';
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    $_SESSION['account_flash'] = 'Session expired while submitting feedback. Please try again.';
    $_SESSION['account_flash_type'] = 'error';
    header('Location: ' . $redirect);
    exit();
}

$orderId = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
$rating = isset($_POST['rating']) ? (int) $_POST['rating'] : 0;
$feedbackText = trim((string) ($_POST['feedback_text'] ?? ''));
if (function_exists('mb_substr')) {
    $feedbackText = mb_substr($feedbackText, 0, 600);
} else {
    $feedbackText = substr($feedbackText, 0, 600);
}

if ($orderId <= 0 || $rating < 1 || $rating > 5) {
    $_SESSION['account_flash'] = 'Please choose a rating between 1 and 5 stars.';
    $_SESSION['account_flash_type'] = 'error';
    header('Location: ' . $redirect);
    exit();
}

$customerId = (int) $_SESSION['customer_id'];

try {
    $orderStmt = $conn->prepare('SELECT status FROM orders WHERE order_id = ? AND customer_id = ?');
    $orderStmt->bind_param('ii', $orderId, $customerId);
    $orderStmt->execute();
    $orderResult = $orderStmt->get_result();
    $orderRow = $orderResult ? $orderResult->fetch_assoc() : null;
    $orderStmt->close();

    if (!$orderRow) {
        $_SESSION['account_flash'] = 'We could not find that order. Try refreshing and submitting again.';
        $_SESSION['account_flash_type'] = 'error';
        header('Location: ' . $redirect);
        exit();
    }

    $status = strtolower((string) ($orderRow['status'] ?? ''));
    $reviewableStatuses = ['completed', 'delivered', 'shipped'];
    if (!in_array($status, $reviewableStatuses, true)) {
        $_SESSION['account_flash'] = 'You can review orders once they have been shipped or completed.';
        $_SESSION['account_flash_type'] = 'info';
        header('Location: ' . $redirect);
        exit();
    }

    $tableCheck = $conn->query("SHOW TABLES LIKE 'customer_feedback'");
    $hasFeedbackTable = $tableCheck && $tableCheck->num_rows > 0;
    if ($tableCheck) {
        $tableCheck->free();
    }

    if (!$hasFeedbackTable) {
        $createSql = "CREATE TABLE IF NOT EXISTS customer_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    feedback_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_order (order_id),
    CONSTRAINT fk_feedback_order
        FOREIGN KEY (order_id) REFERENCES orders(order_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $conn->query($createSql);
    }

    $existingStmt = $conn->prepare('SELECT id FROM customer_feedback WHERE order_id = ?');
    $existingStmt->bind_param('i', $orderId);
    $existingStmt->execute();
    $existingResult = $existingStmt->get_result();
    $existingRow = $existingResult ? $existingResult->fetch_assoc() : null;
    $existingStmt->close();

    if ($existingRow) {
        $updateStmt = $conn->prepare('UPDATE customer_feedback SET rating = ?, feedback_text = ?, created_at = NOW() WHERE order_id = ?');
        $updateStmt->bind_param('isi', $rating, $feedbackText, $orderId);
        $updateStmt->execute();
        $updateStmt->close();
        $_SESSION['account_flash'] = 'Your review has been updated.';
    } else {
        $insertStmt = $conn->prepare('INSERT INTO customer_feedback (order_id, rating, feedback_text) VALUES (?, ?, ?)');
        $insertStmt->bind_param('iis', $orderId, $rating, $feedbackText);
        $insertStmt->execute();
        $insertStmt->close();
        $_SESSION['account_flash'] = 'Thanks for sharing your review!';
    }
    $_SESSION['account_flash_type'] = 'success';
} catch (mysqli_sql_exception $exception) {
    error_log('submit_feedback failed: ' . $exception->getMessage());
    $_SESSION['account_flash'] = 'We could not save your review right now. Please try again later.';
    $_SESSION['account_flash_type'] = 'error';
}

header('Location: ' . $redirect);
exit();
