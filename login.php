<?php
// Process login BEFORE any output - FIXED SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';

$page_title = "Login";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = new Auth();

    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($auth->login($email, $password)) {
        // Check if this is first login (requires password setup)
        if ($auth->isFirstLogin()) {
            header('Location: first-login.php');
            exit();
        }
        
        // Get user role for redirection - FIXED VARIABLE NAME
        $user_role = $_SESSION['user_role'] ?? '';
        
        // Debug: Check what role we have
        error_log("Login successful. User role: " . $user_role);
        
        // Redirect based on role - FIXED LOGIC
        $admin_roles = ['super_admin'];
        if (in_array($user_role, $admin_roles)) {
            header('Location: ../admin/dashboard.php');
        } else {
            header('Location: ../member/dashboard.php');
        }
        exit();
    } else {
        $error_message = "Invalid email or password.";
    }
}

// Now include header after potential redirects
require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h2 class="text-center mb-4">Member Login</h2>

                    <?php if(isset($error_message) && !empty($error_message)): ?>
                        <div class="alert alert-danger">
                            <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Login Instructions -->
                    <div class="alert alert-info">
                        <strong>Login Instructions:</strong><br>
                        <small>
                            • Use your registered email<br>
                            • For first-time login, use any password<br>
                            • You'll be prompted to set your password
                        </small>
                    </div>

                    <form method="post">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" required 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <div class="form-text">For first login, enter any password</div>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-accent">Login</button>
                        </div>

                        <div class="text-center mt-3">
                            <a href="#" class="text-decoration-none" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal">Forgot your password?</a>
                        </div>
                    </form>

                    <hr class="my-4">

                    <div class="text-center">
                        <p class="mb-0">Not a member yet?</p>
                        <a href="apply.php" class="btn btn-outline-primary btn-sm mt-2">Apply for Membership</a>
                    </div>
                </div>
            </div>
            
            <!-- Debug Session Info -->
            <div class="card mt-3">
                <div class="card-body">
                    <h6>Session Debug Info:</h6>
                    <small class="text-muted">
                        Session Status: <?php echo session_status(); ?><br>
                        Session ID: <?php echo session_id(); ?><br>
                        Logged In: <?php echo isset($_SESSION['user_id']) ? 'Yes' : 'No'; ?><br>
                        User Role: <?php echo $_SESSION['user_role'] ?? 'Not set'; ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Forgot Password Modal -->
<div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-labelledby="forgotPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="forgotPasswordModalLabel">
                    <i class="fas fa-key me-2"></i>Password Reset
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div class="mb-4">
                    <i class="fas fa-lock fa-3x text-warning mb-3"></i>
                    <h4>Password Reset Required</h4>
                </div>
                
                <p class="mb-3">
                    For security reasons, password resets must be processed manually by the administration.
                </p>
                
                <div class="alert alert-info">
                    <strong>Please contact the MES Society administration to reset your password.</strong>
                </div>
                
                <p class="text-muted small mb-4">
                    This ensures the security of your account and prevents unauthorized access.
                </p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="contact.php" class="btn btn-accent">
                    <i class="fas fa-envelope me-2"></i>Contact Administration
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>