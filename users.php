<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole('super_admin');

$auth = new Auth();
$db = Database::getInstance()->getConnection();

// Handle actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $userId = $_GET['id'] ?? null;
    
    switch($action) {
        case 'activate':
            if ($userId) {
                $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                $stmt->execute([$userId]);
                $_SESSION['success'] = "User activated successfully";
            }
            break;
            
        case 'deactivate':
            if ($userId) {
                $stmt = $db->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
                $stmt->execute([$userId]);
                $_SESSION['success'] = "User deactivated successfully";
            }
            break;
            
        case 'delete':
            if ($userId) {
                // Get user data to delete profile picture
                $stmt = $db->prepare("SELECT profile_picture FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                
                // Delete profile picture if exists
                if ($user && $user['profile_picture']) {
                    $picturePath = '../uploads/profile-pictures/' . $user['profile_picture'];
                    if (file_exists($picturePath)) {
                        unlink($picturePath);
                    }
                }
                
                // Delete user posts
                $stmt = $db->prepare("DELETE FROM user_posts WHERE user_id = ?");
                $stmt->execute([$userId]);
                
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $_SESSION['success'] = "User deleted successfully";
            }
            break;
            
        case 'reset_password':
            if ($userId) {
                $newPassword = generateRandomPassword(12);
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("UPDATE users SET password = ?, temp_password = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $newPassword, $userId]);
                
                $_SESSION['success'] = "Password reset successfully. New password: <strong>" . $newPassword . "</strong>";
            }
            break;
    }
    
    header("Location: users.php");
    exit();
}

// Handle user update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $userId = $_POST['user_id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $sap_id = $_POST['sap_id'];
    $phone = $_POST['phone'];
    $department = $_POST['department'];
    $semester = $_POST['semester'];
    $role = $_POST['role'];
    $status = $_POST['status'];
    $posts = isset($_POST['posts']) ? $_POST['posts'] : [];
    
    try {
        // Handle profile picture upload
        $profilePicture = null;
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/profile-pictures/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileExtension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $fileName = 'user_' . $userId . '_' . time() . '.' . $fileExtension;
            $uploadFile = $uploadDir . $fileName;
            
            // Validate file type
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array(strtolower($fileExtension), $allowedTypes)) {
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $uploadFile)) {
                    $profilePicture = $fileName;
                    
                    // Delete old profile picture if exists
                    $stmt = $db->prepare("SELECT profile_picture FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $oldUser = $stmt->fetch();
                    if ($oldUser && $oldUser['profile_picture'] && file_exists($uploadDir . $oldUser['profile_picture'])) {
                        unlink($uploadDir . $oldUser['profile_picture']);
                    }
                }
            }
        }
        
        // Update user data
        if ($profilePicture) {
            $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, sap_id = ?, phone = ?, department = ?, semester = ?, role = ?, status = ?, profile_picture = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$name, $email, $sap_id, $phone, $department, $semester, $role, $status, $profilePicture, $userId]);
        } else {
            $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, sap_id = ?, phone = ?, department = ?, semester = ?, role = ?, status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$name, $email, $sap_id, $phone, $department, $semester, $role, $status, $userId]);
        }
        
        // Update user posts
        // First, delete existing posts
        $stmt = $db->prepare("DELETE FROM user_posts WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Then insert new posts
        if (!empty($posts)) {
            $stmt = $db->prepare("INSERT INTO user_posts (user_id, post_name) VALUES (?, ?)");
            foreach ($posts as $post) {
                $stmt->execute([$userId, $post]);
            }
        }
        
        $_SESSION['success'] = "User updated successfully";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating user: " . $e->getMessage();
    }
    
    header("Location: users.php");
    exit();
}

// Get user count for display
$userCount = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    $userCount = $result['count'];
} catch(PDOException $e) {
    error_log("User count error: " . $e->getMessage());
}

$page_title = "Manage Users";
require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Desktop Sidebar -->
        <div class="col-md-3 d-none d-md-block">
            <div class="desktop-sidebar">
                <?php include 'sidebar.php'; ?>
            </div>
        </div>

        <!-- Mobile Offcanvas Sidebar -->
        <div class="offcanvas offcanvas-start d-md-none" tabindex="-1" id="adminMobileSidebar">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title">Admin Menu</h5>
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
                    <h4 class="mb-0">Manage Users</h4>
                    <small class="text-muted">User Management</small>
                </div>
                <button class="btn btn-accent" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileSidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <div class="d-none d-md-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Manage Users</h1>
                <div class="d-flex gap-2">
                    <a href="view-users.php" class="btn btn-primary">
                        <i class="fas fa-users me-2"></i>View All Users
                    </a>
                    <a href="export-users.php" class="btn btn-success">
                        <i class="fas fa-file-excel me-2"></i>Export to Excel
                    </a>
                    <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-user-plus me-2"></i>Add New User
                    </button>
                </div>
            </div>

            <!-- Success Message -->
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Error Message -->
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Users Overview Card -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-users me-2 text-primary"></i>Users Overview
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-number text-primary"><?php echo $userCount; ?></div>
                                <div class="stat-label">Total Users</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-number text-success"><?php echo $userCount; ?></div>
                                <div class="stat-label">Active Users</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-number text-warning">0</div>
                                <div class="stat-label">Pending</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card">
                                <div class="stat-number text-info"><?php echo $userCount; ?></div>
                                <div class="stat-label">This Month</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center py-4">
                        <h4>User Management Dashboard</h4>
                        <p class="text-muted mb-4">Manage all users from a comprehensive grid view with advanced filtering and search capabilities.</p>
                        <div class="d-flex justify-content-center gap-3 flex-wrap">
                            <a href="view-users.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-users me-2"></i>View All Users
                            </a>
                            <button class="btn btn-accent btn-lg" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                <i class="fas fa-user-plus me-2"></i>Add New User
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions Card -->
            <div class="card mt-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-bolt me-2 text-warning"></i>Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="quick-action-card text-center p-4 border rounded">
                                <i class="fas fa-users fa-2x text-primary mb-3"></i>
                                <h5>View Users</h5>
                                <p class="text-muted">Browse all users in a responsive grid layout</p>
                                <a href="view-users.php" class="btn btn-outline-primary w-100">Open Users Grid</a>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="quick-action-card text-center p-4 border rounded">
                                <i class="fas fa-user-plus fa-2x text-success mb-3"></i>
                                <h5>Add User</h5>
                                <p class="text-muted">Create a new user account manually</p>
                                <button class="btn btn-outline-success w-100" data-bs-toggle="modal" data-bs-target="#addUserModal">Add User</button>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="quick-action-card text-center p-4 border rounded">
                                <i class="fas fa-file-excel fa-2x text-info mb-3"></i>
                                <h5>Export Data</h5>
                                <p class="text-muted">Export user data for reporting and analysis</p>
                                <a href="export-users.php" class="btn btn-outline-info w-100">
                                    <i class="fas fa-download me-2"></i>Export to Excel
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="users-create.php" enctype="multipart/form-data">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <div class="mb-3">
                                <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center mx-auto mb-3" 
                                     style="width: 120px; height: 120px;"
                                     id="addProfilePreview">
                                    <i class="fas fa-user text-white fa-3x"></i>
                                </div>
                                <input type="file" class="form-control form-control-sm" name="profile_picture" accept="image/*" 
                                       onchange="previewImage(this, 'addProfilePreview')">
                                <small class="text-muted">Max size: 2MB. JPG, PNG, GIF</small>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" id="name" name="name" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="sap_id" class="form-label">SAP ID</label>
                                        <input type="text" class="form-control" id="sap_id" name="sap_id">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone</label>
                                        <input type="tel" class="form-control" id="phone" name="phone">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="department" class="form-label">Department</label>
                                        <input type="text" class="form-control" id="department" name="department" placeholder="e.g., Mechanical Engineering">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="semester" class="form-label">Semester</label>
                                        <input type="number" class="form-control" id="semester" name="semester" min="1" max="8">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="posts" class="form-label">Assign Posts</label>
                                <select class="form-select" id="posts" name="posts[]" multiple size="4">
                                    <option value="member">General Member</option>
                                    <option value="media_head">Media Head</option>
                                    <option value="event_planner">Event Planner</option>
                                    <option value="competition_head">Competition Head</option>
                                    <option value="hiring_head">Hiring Head</option>
                                    <option value="president">President</option>
                                    <option value="department_head">Department Head</option>
                                </select>
                                <small class="text-muted">Hold Ctrl/Cmd to select multiple posts</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="role" class="form-label">System Role</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="member">Member</option>
                                    <option value="media_head">Media Head</option>
                                    <option value="event_planner">Event Planner</option>
                                    <option value="competition_head">Competition Head</option>
                                    <option value="hiring_head">Hiring Head</option>
                                    <option value="department_head">Department Head</option>
                                    <option value="super_admin">Super Admin</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Mobile Floating Action Button -->
<div class="fab-container d-md-none">
    <button class="fab btn btn-primary rounded-circle shadow" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="fas fa-plus"></i>
    </button>
</div>

<script>
// Image preview function
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    const file = input.files[0];
    
    if (file) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            // Create an image element
            const img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'rounded-circle';
            img.style.width = '120px';
            img.style.height = '120px';
            img.style.objectFit = 'cover';
            
            // Replace the preview content with the image
            preview.innerHTML = '';
            preview.appendChild(img);
        }
        
        reader.readAsDataURL(file);
    } else {
        // Reset to default icon if no file selected
        preview.innerHTML = '<i class="fas fa-user text-white fa-3x"></i>';
        preview.className = 'rounded-circle bg-secondary d-flex align-items-center justify-content-center mx-auto mb-3';
        preview.style.width = '120px';
        preview.style.height = '120px';
    }
}

function generateRandomPassword(length = 12) {
    const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    let password = '';
    for (let i = 0; i < length; i++) {
        password += chars[Math.floor(Math.random() * chars.length)];
    }
    return password;
}
</script>

<style>
/* Floating Action Button */
.fab-container {
    position: fixed;
    bottom: 80px;
    right: 20px;
    z-index: 1030;
}

.fab {
    width: 60px;
    height: 60px;
    border: none;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    transition: all 0.3s ease;
}

.fab:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(0,0,0,0.3);
}

@media (max-width: 767.98px) {
    .d-flex.flex-wrap {
        flex-direction: column;
        gap: 10px !important;
    }
    
    .btn-lg {
        width: 100%;
        margin-bottom: 10px;
    }
}

/* Stat Cards */
.stat-card {
    padding: 1rem;
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.stat-label {
    color: var(--dark-gray);
    font-size: 0.9rem;
}

/* Quick Action Cards */
.quick-action-card {
    transition: all 0.3s ease;
    height: 100%;
}

.quick-action-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.quick-action-card h5 {
    margin-bottom: 1rem;
}

.quick-action-card p {
    margin-bottom: 1.5rem;
    min-height: 48px;
}

/* Button improvements */
.btn {
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-2px);
}
</style>

<?php 
function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}
require_once '../includes/footer.php'; 
?>