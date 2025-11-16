<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

include 'db_connection.php';

$token = $_POST['token'] ?? '';
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';

if (empty($token) || empty($password) || $password !== $password_confirm) {
    header("Location: reset_password.php?token=" . urlencode($token) . "&status=error&message=Passwords do not match or fields are empty.");
    exit();
}

$token_hash = hash('sha256', $token);

$stmt = $conn->prepare("SELECT customer_id, reset_token_expires_at FROM customer WHERE reset_token_hash = ?");
$stmt->bind_param("s", $token_hash);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || strtotime($user['reset_token_expires_at']) < time()) {
    header("Location: reset_password.php?token=" . urlencode($token) . "&status=error&message=Invalid or expired token.");
    exit();
}

// Token is valid, update the password
$hashed_password = password_hash($password, PASSWORD_BCRYPT);

$update_stmt = $conn->prepare("UPDATE customer SET password = ?, reset_token_hash = NULL, reset_token_expires_at = NULL WHERE customer_id = ?");
$update_stmt->bind_param("si", $hashed_password, $user['customer_id']);
$update_stmt->execute();

header("Location: reset_password.php?status=success");
exit();
?>