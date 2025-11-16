<?php
// ---------------------------------------------------------------------
// /admin/index.php - Admin Login Page
// ---------------------------------------------------------------------
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (isset($_SESSION['admin_id'])) { header('Location: dashboard.php'); exit(); }
include '../db_connection.php';
$message = '';

// Ensure CSRF token exists for the admin login form
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// If no admins exist, redirect to setup
$countRes = $conn->query("SELECT COUNT(*) AS c FROM admin");
if ($countRes && ($row = $countRes->fetch_assoc()) && (int)$row['c'] === 0) {
    header('Location: setup.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF validation
    if (empty($_SESSION['csrf_token']) || empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = 'Invalid request. Please try again.';
    } else {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $stmt = $conn->prepare("SELECT admin_id, name, password FROM admin WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['admin_id'] = $user['admin_id'];
            $_SESSION['admin_name'] = $user['name'];
            header('Location: dashboard.php');
            exit();
        }
    }
    $message = 'Invalid email or password.';
    }
}
?>
<?php $ADMIN_TITLE = 'Admin Login'; require_once __DIR__ . '/_header.php'; ?>
<body class="bg-gradient-to-br from-slate-900 via-indigo-900 to-slate-800 min-h-screen text-slate-50">
<div class="min-h-screen flex items-center justify-center px-6 py-12">
    <div class="grid w-full max-w-4xl gap-8 md:grid-cols-2">
        <div class="bg-white/10 border border-white/10 rounded-3xl p-8 backdrop-blur shadow-[0_20px_60px_rgba(15,23,42,0.55)] flex flex-col justify-between">
            <div>
                <span class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.3em] text-indigo-300">Mystic Clothing</span>
                <h1 class="mt-6 text-3xl font-bold leading-tight">Admin Control Center</h1>
                <p class="mt-4 text-sm text-slate-300 leading-relaxed">
                    Manage products, track customer orders, and keep the storefront in sync. Your session is independent from shoppers, so you can hop back to the main website without logging out users.
                </p>
            </div>
            <div class="mt-8 flex flex-wrap items-center gap-3 text-sm text-slate-300">
                <a class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-indigo-500/20 text-indigo-100 hover:bg-indigo-500/30 transition" href="../index.php">
                    <span>View Storefront</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5l7 7-7 7" /></svg>
                </a>
                <span class="text-xs text-slate-400">Need a new admin? <a class="underline decoration-dotted" href="setup.php">Run initial setup</a>.</span>
            </div>
        </div>
        <div class="bg-white rounded-3xl shadow-xl p-8 text-slate-900">
            <h2 class="text-2xl font-bold mb-2">Sign in</h2>
            <p class="text-sm text-slate-500 mb-6">Use your admin credentials to access the control panel.</p>
            <?php if ($message): ?><p class="text-red-600 text-sm font-semibold mb-4"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
            <form method="POST" action="index.php" class="space-y-5">
                <div>
                    <label for="email" class="block text-sm font-semibold text-slate-600 mb-2">Email</label>
                    <input type="email" id="email" name="email" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-400" required>
                </div>
                <div>
                    <label for="password" class="block text-sm font-semibold text-slate-600 mb-2">Password</label>
                    <input type="password" id="password" name="password" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-400" required>
                </div>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 transition text-white py-3 rounded-xl font-semibold">Log In</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>