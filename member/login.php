<?php
$page_title = "Login";
require_once '../includes/header.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-accent text-white">
                    <h4 class="card-title mb-0 text-center">Member Login</h4>
                </div>
                <div class="card-body p-4">
                    <?php if(isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger">
                            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        </div>
                    <?php endif; ?>

                    <form action="../api/auth.php" method="post">
                        <input type="hidden" name="action" value="login">
                        <input type="hidden" name="redirect" value="<?php echo $_SESSION['login_redirect'] ?? 'dashboard.php'; ?>">
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-accent btn-lg">Login</button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-3">
                        <a href="forgot-password.php">Forgot Password?</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
unset($_SESSION['login_redirect']);
require_once '../includes/footer.php'; 
?>