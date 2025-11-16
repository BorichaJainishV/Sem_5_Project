<?php
require 'vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Stop script if not a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

include 'db_connection.php';

// CSRF validation
$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    header('Location: forgot_password.php?status=error');
    exit();
}

// Validate email input
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
if (!$email) {
    header('Location: forgot_password.php?status=error');
    exit();
}

// Find user in the database
$stmt = $conn->prepare("SELECT customer_id, name, email FROM customer WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Always respond with success to avoid user enumeration, but only send email if user exists
if ($user) {
    // Generate token and save hash + expiry
    $token = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $token);
    $expires_at = date('Y-m-d H:i:s', time() + 3600);

    $update_stmt = $conn->prepare("UPDATE customer SET reset_token_hash = ?, reset_token_expires_at = ? WHERE customer_id = ?");
    $update_stmt->bind_param("ssi", $token_hash, $expires_at, $user['customer_id']);
    $update_stmt->execute();

    // Build absolute URL with https when available
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    $scheme = $isHttps ? 'https' : 'http';
    $base = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    $reset_link = $scheme . '://' . $_SERVER['HTTP_HOST'] . $base . '/reset_password.php?token=' . $token;

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = getenv('SMTP_USERNAME') ?: '';
        $mail->Password = getenv('SMTP_PASSWORD') ?: '';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = getenv('SMTP_PORT') ?: 587;

        $fromEmail = getenv('SMTP_FROM') ?: 'no-reply@mysticclothing.com';
        $fromName  = getenv('SMTP_FROM_NAME') ?: 'Mystic Clothing';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($user['email'], $user['name']);

        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request';
        $safeName = htmlspecialchars($user['name']);
        $body = "<h1>Password Reset Request</h1>"
              . "<p>Hi {$safeName},</p>"
              . "<p>Please click the link below to reset your password. This link is valid for 1 hour.</p>"
              . "<p><a href='{$reset_link}'>{$reset_link}</a></p>"
              . "<p>If you did not request a password reset, please ignore this email.</p>";
        $mail->Body = $body;

        // Only attempt to send if credentials are present
        if (!empty($mail->Username) && !empty($mail->Password)) {
            $mail->send();
        } else {
            error_log('Password reset email not sent: SMTP credentials not configured.');
        }
    } catch (Exception $e) {
        error_log("Mailer error: " . $e->getMessage());
    }
}

// Always redirect to success to avoid user enumeration
header('Location: forgot_password.php?status=success');
exit();
?>
