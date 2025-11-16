<?php
// ---------------------------------------------------------------------
// logout.php - Destroys the session to log the user out
// (Upgraded for better security)
// ---------------------------------------------------------------------

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Unset all session variables and cart
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
// Note: This will destroy the session, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();

// Redirect to the main page
header("Location: index.php");
exit();
?>