<?php
// ---------------------------------------------------------------------
// signup.php - Handles new user registration
// (Upgraded with better error handling and feedback)
// ---------------------------------------------------------------------

// Start the session to manage user login state
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Ensure CSRF token exists for form submissions
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
include 'db_connection.php'; // Include database connection

$message = ''; // To store success or error messages

// Check if the form has been submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF validation
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = "Invalid request. Please try again.";
    } else {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // --- Basic Validation ---
    if (empty($name) || empty($email) || empty($password)) {
        $message = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
    } else {
        // --- IMPORTANT: Hash the password for security ---
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // Prepare an SQL statement to prevent SQL injection
        $stmt = $conn->prepare("INSERT INTO customer (name, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $email, $hashed_password);

        // Execute the statement and check for success
        try {
            if ($stmt->execute()) {
                // Automatically log the user in after successful registration
                $_SESSION['customer_id'] = $stmt->insert_id; // Get the new customer ID
                $_SESSION['name'] = $name;
                
                // Redirect to the main page
                header("Location: index.php");
                exit();
            }
        } catch (mysqli_sql_exception $e) {
            // Check for duplicate email error (error code 1062)
            if ($e->getCode() == 1062) {
                $message = "Error: This email address is already registered.";
            } else {
                $message = "An unexpected error occurred. Please try again.";
                error_log("Signup Error: " . $e->getMessage());
            }
        }
        $stmt->close();
    }
    }
}
$conn->close();
?>

<!-- The HTML part is mostly the same as your signup.html -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Mystic Clothing</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <header class="bg-white shadow-md">
        <nav class="container mx-auto px-6 py-4 flex justify-between items-center">
            <a href="index.php" class="text-2xl font-bold text-indigo-600">Mystic Clothing</a>
            <a href="index.php" class="text-gray-600 hover:text-indigo-600">Back to Home</a>
        </nav>
    </header>

    <main class="bg-gray-100 min-h-screen flex items-center justify-center py-12">
        <div class="bg-white rounded-lg shadow-2xl p-8 max-w-md w-full">
            <h2 class="text-3xl font-bold text-center mb-6">Create an Account</h2>
            
            <?php if(!empty($message)): ?>
                <div class="text-center bg-red-100 text-red-700 p-3 rounded-md mb-4"><?php echo $message; ?></div>
            <?php endif; ?>

            <!-- The form now posts to itself -->
            <form action="signup.php" method="POST" novalidate>
                <div class="mb-4">
                    <label for="name" class="block text-gray-700 mb-2">Full Name</label>
                    <input type="text" id="name" name="name" class="w-full px-4 py-2 border rounded-md" required>
                </div>
                <div class="mb-4">
                    <label for="email" class="block text-gray-700 mb-2">Email Address</label>
                    <input type="email" id="email" name="email" class="w-full px-4 py-2 border rounded-md" required>
                </div>
                <div class="mb-6">
                    <label for="password" class="block text-gray-700 mb-2">Password</label>
                    <input type="password" id="password" name="password" class="w-full px-4 py-2 border rounded-md" required>
                </div>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <button type="submit" class="w-full bg-indigo-600 text-white py-3 rounded-md font-bold">Sign Up</button>
            </form>
            <p class="text-center text-gray-600 mt-6">
                Already have an account? 
                <a href="index.php" class="text-indigo-600 hover:underline font-semibold">Log in</a>
            </p>
        </div>
    </main>
</body>
</html>