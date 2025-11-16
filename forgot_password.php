<?php
include 'header.php';
?>

<main class="bg-gray-100 min-h-screen flex items-center justify-center py-12">
    <div class="bg-white rounded-lg shadow-2xl p-8 max-w-md w-full">
        <h2 class="text-3xl font-bold text-center mb-6 text-dark">Reset Your Password</h2>
        <p class="text-center text-muted mb-6">Enter your email address and we will send you a link to reset your password.</p>
        
        <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
            <div class="alert alert-success">Check your email for a password reset link.</div>
        <?php elseif (isset($_GET['status']) && $_GET['status'] == 'error'): ?>
            <div class="alert alert-danger">No account found with that email address.</div>
        <?php endif; ?>

        <form action="send_reset_link.php" method="POST">
            <div class="form-group">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="you@example.com" required>
            </div>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            <button type="submit" class="btn btn-primary btn-lg w-full">Send Reset Link</button>
        </form>
    </div>
</main>

<?php include 'footer.php'; ?>