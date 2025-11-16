<?php include 'header.php'; ?>

<main class="bg-gray-100 min-h-screen flex items-center justify-center py-12">
    <div class="bg-white rounded-lg shadow-2xl p-8 max-w-md w-full">
        <h2 class="text-3xl font-bold text-center mb-6 text-dark">Login to Your Account</h2>

        <?php if (isset($_SESSION['login_error'])): ?>
            <div class="bg-danger-light text-danger p-3 rounded-md mb-4 text-center">
                <?php 
                    echo $_SESSION['login_error']; 
                    unset($_SESSION['login_error']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['registered'])): ?>
            <div class="bg-success-light text-success p-3 rounded-md mb-4 text-center">
                Registration successful! Please log in.
            </div>
        <?php endif; ?>
        
        <form action="login_handler.php" method="POST">
            <div class="form-group">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="you@example.com" required>
            </div>
            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            <button type="submit" class="btn btn-primary btn-lg w-full">Login</button>
        </form>
        <p class="text-center text-muted mt-6">
            Don't have an account? <a href="signup.php" class="text-primary hover:underline font-semibold">Sign Up</a>
        </p>
<p class="text-center text-muted mt-4">
    <a href="forgot_password.php" class="text-sm text-primary hover:underline">Forgot Password?</a>
</p>
    </div>
</main>

<?php include 'footer.php'; ?>