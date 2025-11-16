<?php
// test_email.php

// This will show any errors on the screen, which is useful for debugging.
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include the email handler
include 'email_handler.php';

echo "Attempting to send a test email...<br>";

// Call the function with some test data
$recipient_email = 'jainishv12@gmail.com'; // This can be any email, Mailtrap will catch it.
$recipient_name = 'Test User';
$test_order_id = 'TEST-12345';

$success = sendOrderConfirmationEmail($recipient_email, $recipient_name, $test_order_id);

if ($success) {
    echo "<h2>Email sent successfully!</h2>";
    echo "<p>Please check your Mailtrap inbox at <a href='https://mailtrap.io/inboxes' target='_blank'>mailtrap.io</a>.</p>";
} else {
    echo "<h2>Error: Email could not be sent.</h2>";
    echo "<p>Check your PHP error log for more details.</p>";
}
?>