<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole('member');

$page_title = "My Profile";
$hidePublicNavigation = true;
require_once '../includes/header.php';

$user = $session->getCurrentUser();
$db = Database::getInstance()->getConnection();
$auth = new Auth();
$functions = new Functions();

$success_msg = '';
$error_msg = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $phone = $_POST['phone'] ?? '';
    $semester = $_POST['semester'] ?? '';
    $bio = $_POST['bio'] ?? '';
    $skills = $_POST['skills'] ?? '';
    
    try {
        $stmt = $db->prepare("UPDATE users SET phone = ?, semester = ?, bio = ?, skills = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$phone, $semester, $bio, $skills, $user['id']]);
        
        if ($result) {
            $success_msg = "Profile updated successfully!";
            // Refresh user data
            $user = $session->getCurrentUser();
        } else {
            $error_msg = "Failed to update profile. Please try again.";
        }
    } catch(PDOException $e) {
        $error_msg = "Database error: " . $e->getMessage();
    }
}

// Handle profile picture update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    if ($_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_result = $functions->uploadFile($_FILES['profile_picture'], 'profile_picture');
        
        if ($upload_result['success']) {
            // Delete old profile picture if not default
            if ($user['profile_picture'] !== 'default-avatar.png') {
                $old_file = '../uploads/profile-pictures/' . $user['profile_picture'];
                if (file_exists($old_file)) {
                    unlink($old_file);
                }
            }
            
            // Update database
            $stmt = $db->prepare("UPDATE users SET profile_picture = ?, updated_at = NOW() WHERE id = ?");
            $result = $stmt->execute([$upload_result['file_name'], $user['id']]);
            
            if ($result) {
                $success_msg = "Profile picture updated successfully!";
                // Refresh user data
                $user = $session->getCurrentUser();
                
                // Resize to square
                $functions->resizeImageToSquare($upload_result['file_path'], 300);
            } else {
                $error_msg = "Failed to update profile picture in database.";
            }
        } else {
            $error_msg = $upload_result['message'];
        }
    } else {
        $error_msg = "Error uploading file. Please try again.";
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_msg = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error_msg = "New passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error_msg = "New password must be at least 8 characters long.";
    } else {
        if ($auth->changePassword($user['id'], $current_password, $new_password)) {
            $success_msg = "Password changed successfully!";
        } else {
            $error_msg = "Current password is incorrect.";
        }
    }
}
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Desktop Sidebar (visible on larger screens) -->
        <div class="col-md-3 d-none d-md-block">
            <div class="desktop-sidebar">
                <?php include 'sidebar.php'; ?>
            </div>
        </div>

        <!-- Mobile Offcanvas Sidebar (visible on small screens) -->
        <div class="offcanvas offcanvas-start d-md-none" tabindex="-1" id="adminMobileSidebar">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title">Member Menu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body">
                <?php include 'sidebar.php'; ?>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <!-- Mobile Header Bar -->
            <div class="d-md-none d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                <div>
                    <h4 class="mb-0">My Profile</h4>
                    <small class="text-muted">Update your information</small>
                </div>
                <button class="btn btn-accent" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileSidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <div class="d-none d-md-flex justify-content-between align-items-center mb-4">
                <h1>My Profile</h1>
                <span class="badge bg-accent"><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></span>
            </div>

            <!-- Alerts -->
            <?php if ($success_msg): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_msg; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_msg; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Profile Picture Section -->
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-user-circle me-2"></i>Profile Picture
                            </h5>
                        </div>
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <?php
                                $profile_pic = '../uploads/profile-pictures/' . $user['profile_picture'];
                                if (file_exists($profile_pic) && $user['profile_picture'] !== 'default-avatar.png') {
                                    echo '<img src="' . $profile_pic . '" class="rounded-circle" style="width: 150px; height: 150px; object-fit: cover;" alt="Profile Picture">';
                                } else {
                                    echo '<div class="rounded-circle bg-accent text-white d-inline-flex align-items-center justify-content-center" style="width: 150px; height: 150px; font-size: 3rem;">';
                                    echo $functions->getUserInitials($user['name']);
                                    echo '</div>';
                                }
                                ?>
                            </div>
                            
                            <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                                <div class="mb-3">
                                    <input type="file" class="form-control" name="profile_picture" accept="image/jpeg,image/png,image/jpg" required>
                                    <div class="form-text">Max 2MB, JPG/PNG only. Square images work best.</div>
                                </div>
                                <button type="submit" class="btn btn-accent w-100">
                                    <i class="fas fa-upload me-2"></i>Update Picture
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Profile Information -->
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-user-edit me-2"></i>Personal Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="update_profile" value="1">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Full Name</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" readonly>
                                        <div class="form-text">Name cannot be changed</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">SAP ID</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['sap_id']); ?>" readonly>
                                        <div class="form-text">SAP ID cannot be changed</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email Address</label>
                                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                                        <div class="form-text">University email cannot be changed</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Phone Number *</label>
                                        <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                                               pattern="[0-9]{10,15}" title="Enter valid phone number (10-15 digits)" required>
                                        <div class="invalid-feedback">Please enter a valid phone number.</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Department</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['department']); ?>" readonly>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Semester *</label>
                                        <select class="form-select" name="semester" required>
                                            <option value="">Select Semester</option>
                                            <?php for ($i = 1; $i <= 8; $i++): ?>
                                                <option value="<?php echo $i; ?>" <?php echo ($user['semester'] == $i) ? 'selected' : ''; ?>>
                                                    Semester <?php echo $i; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Bio/About Me</label>
                                    <textarea class="form-control" name="bio" rows="3" placeholder="Tell us about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Skills</label>
                                    <textarea class="form-control" name="skills" rows="2" placeholder="List your skills (comma separated)"><?php echo htmlspecialchars($user['skills'] ?? ''); ?></textarea>
                                    <div class="form-text">Separate multiple skills with commas</div>
                                </div>

                                <button type="submit" class="btn btn-accent">
                                    <i class="fas fa-save me-2"></i>Update Profile
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Change Password -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-lock me-2"></i>Change Password
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="needs-validation" novalidate>
                                <input type="hidden" name="change_password" value="1">
                                
                                <div class="mb-3">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" class="form-control" name="current_password" required>
                                    <div class="invalid-feedback">Please enter your current password.</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" class="form-control" name="new_password" minlength="8" required>
                                    <div class="form-text">Minimum 8 characters with letters and numbers.</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" name="confirm_password" minlength="8" required>
                                    <div class="invalid-feedback">Passwords must match.</div>
                                </div>

                                <button type="submit" class="btn btn-accent">
                                    <i class="fas fa-key me-2"></i>Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
document.addEventListener('DOMContentLoaded', function() {
    // Client-side validation for password match
    const form = document.querySelector('form[action*="change_password"]');
    if (form) {
        form.addEventListener('submit', function(e) {
            const newPassword = form.querySelector('input[name="new_password"]');
            const confirmPassword = form.querySelector('input[name="confirm_password"]');
            
            if (newPassword.value !== confirmPassword.value) {
                e.preventDefault();
                confirmPassword.setCustomValidity('Passwords do not match');
                confirmPassword.reportValidity();
            } else {
                confirmPassword.setCustomValidity('');
            }
        });
    }
    
    // Real-time password match check
    const newPassword = document.querySelector('input[name="new_password"]');
    const confirmPassword = document.querySelector('input[name="confirm_password"]');
    
    if (newPassword && confirmPassword) {
        confirmPassword.addEventListener('input', function() {
            if (newPassword.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwords do not match');
            } else {
                confirmPassword.setCustomValidity('');
            }
        });
    }
});
</script>

<style>
/* Mobile responsive adjustments */
@media (max-width: 767.98px) {
    .container-fluid {
        padding-left: 10px;
        padding-right: 10px;
    }
}

/* Offcanvas sidebar styling */
.offcanvas-header {
    background: var(--primary-color);
    color: white;
}

.offcanvas-body {
    padding: 0;
}

.offcanvas-body .nav {
    padding: 1rem;
}
</style>

<?php require_once '../includes/footer.php'; ?>