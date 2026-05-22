<?php
// Start session and check if this is first login
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';

$auth = new Auth();

// Redirect if not first login
if (!$auth->isFirstLogin()) {
    header('Location: login.php');
    exit();
}

$page_title = "Set Your Password";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } else {
        // Set the new password
        if ($auth->setInitialPassword($_SESSION['user_id'], $new_password)) {
            // Redirect to appropriate dashboard
            $user_role = $_SESSION['user_role'];
            
            if ($user_role === 'super_admin' || $user_role === 'department_head' || strpos($user_role, 'head') !== false) {
                header('Location: ../admin/dashboard.php');
            } else {
                header('Location: ../member/dashboard.php');
            }
            exit();
        } else {
            $error_message = "Failed to set password. Please try again.";
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h2 class="text-center mb-4">Set Your Password</h2>
                    <p class="text-center text-muted">Welcome to MES Society! Please set your password to continue.</p>

                    <?php if(isset($error_message)): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>

                    <form method="post">
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                            <div class="form-text">Password must be at least 8 characters long.</div>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-accent">Set Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>