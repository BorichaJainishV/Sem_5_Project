<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include '../db_connection.php';

// ROBUST FIX: Prevent execution if an admin already exists.
$checkStmt = $conn->query("SELECT COUNT(*) FROM admin");
$adminCount = $checkStmt->fetch_row()[0] ?? 0;
if ($adminCount > 0) {
    // Silently exit or show a generic error message.
    // This prevents attackers from knowing the script exists.
    header("HTTP/1.1 404 Not Found");
    exit("Setup has already been completed and this script is disabled.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? 'Admin');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO admin (name, email, role, password) VALUES (?, ?, 'superadmin', ?)");
        $stmt->bind_param("sss", $name, $email, $hash);
        $stmt->execute();
        // IMPORTANT: After setup, this file should be deleted from the server.
        header('Location: index.php?setup=complete');
        exit();
    }
}
?>
<?php $ADMIN_TITLE = 'Admin Setup'; require_once __DIR__ . '/_header.php'; ?>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>body{font-family:'Inter',sans-serif}</style>
<body class="bg-gradient-to-br from-slate-900 via-indigo-900 to-slate-800 min-h-screen text-slate-50">
<div class="min-h-screen flex items-center justify-center px-6 py-12">
    <div class="grid w-full max-w-4xl gap-8 md:grid-cols-2">
        <div class="bg-white/10 border border-white/10 rounded-3xl p-8 backdrop-blur shadow-[0_20px_60px_rgba(15,23,42,0.55)] flex flex-col justify-between">
            <div>
                <span class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.3em] text-indigo-300">Mystic Clothing</span>
                <h1 class="mt-6 text-3xl font-bold leading-tight">Initial Admin Setup</h1>
                <p class="mt-4 text-sm text-slate-300 leading-relaxed">
                    This one-time screen provisions the first super admin. After you create the account, the setup script automatically locks itself so it can’t be reused.
                </p>
            </div>
            <div class="mt-8 text-sm text-slate-300">
                <p class="mb-3">Already configured? <a class="inline-flex items-center gap-1 underline decoration-dotted" href="index.php"><span>Return to login</span></a>.</p>
                <p class="text-xs text-slate-400">Tip: delete <code>admin/setup.php</code> once setup is complete.</p>
            </div>
        </div>
        <div class="bg-white rounded-3xl shadow-xl p-8 text-slate-900">
            <h2 class="text-2xl font-bold mb-2">Create Super Admin</h2>
            <p class="text-sm text-slate-500 mb-6">We recommend using a strong password and an email address you can recover.</p>
            <form method="POST" action="setup.php" class="space-y-5">
                <div>
                    <label for="name" class="block text-sm font-semibold text-slate-600 mb-2">Name</label>
                    <input type="text" id="name" name="name" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-400" placeholder="Admin" required>
                </div>
                <div>
                    <label for="email" class="block text-sm font-semibold text-slate-600 mb-2">Email</label>
                    <input type="email" id="email" name="email" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-400" required>
                </div>
                <div>
                    <label for="password" class="block text-sm font-semibold text-slate-600 mb-2">Password</label>
                    <input type="password" id="password" name="password" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-400" required>
                </div>
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 transition text-white py-3 rounded-xl font-semibold">Create Admin</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>