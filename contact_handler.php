<?php
// ---------------------------------------------------------------------
// contact_handler.php - Processes the contact form
// ---------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    $csrf = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        header('Location: contact.php?status=error');
        exit();
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // Basic validation
    if (!empty($name) && !empty($email) && !empty($message) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // mail() or queue handling could be added here.
        header('Location: contact.php?status=success');
        exit();
    } else {
        header('Location: contact.php?status=error');
        exit();
    }
}
?>
