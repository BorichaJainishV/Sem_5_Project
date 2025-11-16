<?php
// ---------------------------------------------------------------------
// login_handler.php - Processes login for both Admins and Customers
// ---------------------------------------------------------------------

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include 'db_connection.php';
require_once __DIR__ . '/session_helpers.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF check
    if (empty($_SESSION['csrf_token']) || empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['login_error'] = 'Invalid request. Please try again.';
        header("Location: login.php");
        exit();
    }
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $_SESSION['login_error'] = 'Please fill in all fields.';
        header("Location: login.php");
        exit();
    }

    // --- 1. Check if the user is an Admin ---
    $stmt = $conn->prepare("SELECT admin_id, name, password FROM admin WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $admin = $result->fetch_assoc();
        if (password_verify($password, $admin['password'])) {
            // Admin login successful
            session_regenerate_id(true);
            // Clear any existing customer session data
            clear_custom_design_ids();
            unset($_SESSION['customer_id'], $_SESSION['name'], $_SESSION['email'], $_SESSION['cart']);
            
            $_SESSION['admin_id'] = $admin['admin_id'];
            $_SESSION['admin_name'] = $admin['name'];
            header('Location: admin/dashboard.php');
            exit();
        }
    }
    $stmt->close();

    // --- 2. If not an admin, check if the user is a Customer ---
    $stmt = $conn->prepare("SELECT customer_id, name, email, password FROM customer WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $customer = $result->fetch_assoc();
        if (password_verify($password, $customer['password'])) {
            // Customer login successful
            session_regenerate_id(true);
            // Clear any existing admin session data
            unset($_SESSION['admin_id'], $_SESSION['admin_name']);

            clear_custom_design_ids();
            unset($_SESSION['cart']);

            $_SESSION['customer_id'] = $customer['customer_id'];
            $_SESSION['name'] = $customer['name'];
            $_SESSION['email'] = $customer['email'];
            header("Location: index.php?login=success");
            exit();
        }
    }
    $stmt->close();

    // --- 3. If both checks fail, show an error ---
    $_SESSION['login_error'] = 'Invalid email or password.';
    header("Location: login.php");
    exit();

} else {
    header("Location: index.php");
    exit();
}
$conn->close();
?>