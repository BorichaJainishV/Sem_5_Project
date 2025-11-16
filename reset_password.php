<?php
include 'header.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    header("Location: login.php");
    exit();
}

// You can add token validation here later if you want
?>
<main class="bg-gray-100 min-h-screen flex items-center justify-center py-12">
    <div class="bg-white rounded-lg shadow-2xl p-8 max-w-md w-full">
        <h2 class="text-3xl font-bold text-center mb-6 text-dark">Enter New Password</h2>
        
        <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
            <div class="alert alert-success">Your password has been reset successfully! <a href="login.php" class="font-bold">Login now</a>.</div>
        <?php elseif (isset($_GET['status']) && $_GET['status'] == 'error'): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['message']); ?></div>
        <?php endif; ?>

        <form action="update_password.php" method="POST">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            
            <div class="form-group">
                <label for="password" class="form-label">New Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="password_confirm" class="form-label">Confirm New Password</label>
                <input type="password" id="password_confirm" name="password_confirm" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-full">Reset Password</button>
        </form>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        
        if (urlParams.get('status') === 'success') {
            if (typeof toastSuccess === 'function') {
                toastSuccess('Password reset successful! You can now log in.');
            }
        } else if (urlParams.get('status') === 'error') {
            const errorMessage = urlParams.get('message') || 'An error occurred. Please try again.';
            if (typeof toastError === 'function') {
                toastError(errorMessage);
            }
        }
    });
</script>

<?php include 'footer.php'; ?>