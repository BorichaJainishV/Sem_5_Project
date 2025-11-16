<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['customer_id'])) {
    $_SESSION['info_message'] = 'Please log in to update your profile.';
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: account.php');
    exit();
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    $_SESSION['account_flash'] = 'We could not verify your request. Please try again.';
    $_SESSION['account_flash_type'] = 'error';
    header('Location: account.php');
    exit();
}

include 'db_connection.php';

$customerId = (int) $_SESSION['customer_id'];
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');

$errors = [];

if ($name === '') {
    $errors[] = 'Name is required.';
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
}

if ($phone !== '' && !preg_match('/^[0-9+\-\s]{6,}$/', $phone)) {
    $errors[] = 'Please provide a valid phone number (minimum 6 characters).';
}

if (!empty($errors)) {
    $_SESSION['account_flash'] = implode(' ', $errors);
    $_SESSION['account_flash_type'] = 'error';
    header('Location: account.php');
    exit();
}

$emailCheckStmt = $conn->prepare('SELECT customer_id FROM customer WHERE email = ? AND customer_id <> ? LIMIT 1');
if ($emailCheckStmt) {
    $emailCheckStmt->bind_param('si', $email, $customerId);
    $emailCheckStmt->execute();
    $emailCheckStmt->store_result();
    if ($emailCheckStmt->num_rows > 0) {
        $emailCheckStmt->close();
        $_SESSION['account_flash'] = 'This email address is already associated with another account.';
        $_SESSION['account_flash_type'] = 'error';
        header('Location: account.php');
        exit();
    }
    $emailCheckStmt->close();
}

$updateStmt = $conn->prepare('UPDATE customer SET name = ?, email = ?, phone = ?, address = ? WHERE customer_id = ?');
if (!$updateStmt) {
    $_SESSION['account_flash'] = 'Unable to update your profile right now. Please try again later.';
    $_SESSION['account_flash_type'] = 'error';
    header('Location: account.php');
    exit();
}

$phoneParam = $phone !== '' ? $phone : null;
$addressParam = $address !== '' ? $address : null;
$updateStmt->bind_param('ssssi', $name, $email, $phoneParam, $addressParam, $customerId);

if ($updateStmt->execute()) {
    $_SESSION['name'] = $name;
    $_SESSION['email'] = $email;
    $_SESSION['account_flash'] = 'Profile updated successfully.';
    $_SESSION['account_flash_type'] = 'success';
} else {
    $_SESSION['account_flash'] = 'We could not save your changes. Please try again.';
    $_SESSION['account_flash_type'] = 'error';
}

$updateStmt->close();
$conn->close();

header('Location: account.php');
exit();
