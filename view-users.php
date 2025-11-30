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
                try {
                    $db->beginTransaction();
                    
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
                    
                    // First, set user_id to NULL in applications table to break foreign key constraint
                    $stmt = $db->prepare("UPDATE applications SET user_id = NULL WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    
                    // Delete user posts
                    $stmt = $db->prepare("DELETE FROM user_posts WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    
                    // Now delete the user
                    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    
                    $db->commit();
                    $_SESSION['success'] = "User deleted successfully";
                    
                } catch (PDOException $e) {
                    $db->rollBack();
                    $_SESSION['error'] = "Error deleting user: " . $e->getMessage();
                }
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
    
    header("Location: view-users.php");
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
    
    header("Location: view-users.php");
    exit();
}

// Get all users with their posts
$users = [];
try {
    $stmt = $db->query("
        SELECT u.*, GROUP_CONCAT(up.post_name) as user_posts 
        FROM users u 
        LEFT JOIN user_posts up ON u.id = up.user_id 
        GROUP BY u.id 
        ORDER BY u.created_at DESC
    ");
    $users = $stmt->fetchAll();
    
    // Convert user_posts string to array for each user
    foreach ($users as &$user) {
        $user['posts'] = $user['user_posts'] ? explode(',', $user['user_posts']) : [];
    }
    unset($user); // Break the reference
    
} catch(PDOException $e) {
    error_log("Users query error: " . $e->getMessage());
    // Fallback query if user_posts table doesn't exist
    try {
        $stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC");
        $users = $stmt->fetchAll();
        foreach ($users as &$user) {
            $user['posts'] = [];
        }
        unset($user);
    } catch(PDOException $e2) {
        error_log("Fallback users query error: " . $e2->getMessage());
    }
}

// Get available posts
$availablePosts = [
    'member' => 'General Member',
    'media_head' => 'Media Head',
    'event_planner' => 'Event Planner',
    'competition_head' => 'Competition Head',
    'hiring_head' => 'Hiring Head',
    'president' => 'President',
    'department_head' => 'Department Head'
];

$page_title = "View Users - Grid View";
require_once '../includes/header.php';
?>

<div class="container-fluid">
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
                    <h4 class="mb-0">View Users</h4>
                    <small class="text-muted">Grid View</small>
                </div>
                <button class="btn btn-accent" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileSidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <div class="d-none d-md-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3">View Users</h1>
                    <p class="text-muted mb-0">Grid view of all users with comprehensive details</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="users.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
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

            <!-- Users Grid -->
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-users me-2 text-primary"></i>All Users (<?php echo count($users); ?>)
                    </h5>
                    <div class="d-flex gap-2">
                        <div class="input-group input-group-sm" style="width: 250px;">
                            <input type="text" class="form-control" placeholder="Search users..." id="userSearch">
                            <button class="btn btn-outline-secondary" type="button">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                        <button class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-filter me-1"></i>Filter
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if(empty($users)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-users fa-3x mb-3 d-block"></i>
                            <h5>No Users Found</h5>
                            <p class="mb-0">Get started by adding your first user.</p>
                            <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                <i class="fas fa-user-plus me-2"></i>Add New User
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="row" id="usersGrid">
                            <?php foreach($users as $user): ?>
                                <div class="col-xl-3 col-lg-4 col-md-6 mb-4 user-card">
                                    <div class="card h-100 user-grid-card">
                                        <div class="card-body text-center">
                                            <!-- User Avatar -->
                                            <div class="user-avatar mb-3">
                                                <?php if($user['profile_picture']): ?>
                                                    <img src="<?php echo SITE_URL . '/uploads/profile-pictures/' . $user['profile_picture']; ?>" 
                                                         alt="<?php echo $user['name']; ?>" 
                                                         class="avatar-img rounded-circle">
                                                <?php else: ?>
                                                    <div class="avatar-placeholder rounded-circle d-flex align-items-center justify-content-center mx-auto">
                                                        <i class="fas fa-user text-white"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- User Name & Role -->
                                            <h6 class="card-title mb-1"><?php echo htmlspecialchars($user['name']); ?></h6>
                                            <span class="badge bg-<?php 
                                                switch($user['role']) {
                                                    case 'super_admin': echo 'danger'; break;
                                                    case 'department_head': echo 'warning'; break;
                                                    case 'hiring_head': echo 'info'; break;
                                                    case 'event_planner':  echo 'info'; break;
                                                    case 'competition_head': echo 'info'; break;
                                                    case 'media_head': echo 'info'; break;                                                
                                                    case 'member': echo 'success'; break;
                                                    default: echo 'secondary';
                                                }
                                            ?> mb-2">
                                                <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                            </span>
                                            
                                            <!-- User Details -->
                                            <div class="user-details mt-3">
                                                <p class="mb-1 small text-muted">
                                                    <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($user['email']); ?>
                                                </p>
                                                <?php if($user['sap_id']): ?>
                                                <p class="mb-1 small text-muted">
                                                    <i class="fas fa-id-card me-1"></i><?php echo $user['sap_id']; ?>
                                                </p>
                                                <?php endif; ?>
                                                <?php if($user['department']): ?>
                                                <p class="mb-1 small text-muted">
                                                    <i class="fas fa-graduation-cap me-1"></i><?php echo $user['department']; ?>
                                                    <?php if($user['semester']): ?> - Sem <?php echo $user['semester']; ?><?php endif; ?>
                                                </p>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- User Posts -->
                                            <?php if(!empty($user['posts'])): ?>
                                                <div class="user-posts mt-2">
                                                    <?php foreach(array_slice($user['posts'], 0, 3) as $post): ?>
                                                        <span class="badge bg-light text-dark mb-1 d-inline-block"><?php echo htmlspecialchars($availablePosts[$post] ?? $post); ?></span>
                                                    <?php endforeach; ?>
                                                    <?php if(count($user['posts']) > 3): ?>
                                                        <span class="badge bg-light text-dark">+<?php echo count($user['posts']) - 3; ?> more</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Status -->
                                            <div class="user-status mt-2">
                                                <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                    <i class="fas fa-circle me-1" style="font-size: 6px;"></i>
                                                    <?php echo ucfirst($user['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <!-- Card Footer with Actions -->
                                        <div class="card-footer bg-transparent border-top-0 pt-0">
                                            <div class="btn-group w-100" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        onclick="openViewModal(<?php echo $user['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                        onclick="openEditModal(<?php echo $user['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-outline-info dropdown-toggle" data-bs-toggle="dropdown">
                                                        <i class="fas fa-cog"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li>
                                                            <a class="dropdown-item" href="download-confirmation-letter.php?user_id=<?php echo $user['id']; ?>">
                                                                <i class="fas fa-download me-2 text-success"></i>Download Letter
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item reset-password-pdf" href="#" data-user-id="<?php echo $user['id']; ?>" data-user-name="<?php echo htmlspecialchars($user['name']); ?>">
                                                                <i class="fas fa-key me-2 text-warning"></i>Reset Password & Download PDF
                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <?php if($user['status'] === 'active'): ?>
                                                            <li>
                                                                <a class="dropdown-item text-warning" href="?action=deactivate&id=<?php echo $user['id']; ?>" 
                                                                   onclick="return confirm('Deactivate <?php echo $user['name']; ?>?')">
                                                                    <i class="fas fa-pause me-2"></i>Deactivate
                                                                </a>
                                                            </li>
                                                        <?php else: ?>
                                                            <li>
                                                                <a class="dropdown-item text-success" href="?action=activate&id=<?php echo $user['id']; ?>" 
                                                                   onclick="return confirm('Activate <?php echo $user['name']; ?>?')">
                                                                    <i class="fas fa-play me-2"></i>Activate
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="?action=delete&id=<?php echo $user['id']; ?>" 
                                                               onclick="return confirm('Are you sure you want to delete <?php echo $user['name']; ?>? This action cannot be undone.')">
                                                                <i class="fas fa-trash me-2"></i>Delete User
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Single View User Modal (Dynamic content) -->
<div class="modal fade" id="viewUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">User Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewUserModalBody">
                <!-- Dynamic content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="switchToEdit()">
                    <i class="fas fa-edit me-2"></i>Edit User
                </button>
                <a href="#" class="btn btn-success" id="downloadLetterBtn">
                    <i class="fas fa-download me-2"></i>Download Letter
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Single Edit User Modal (Dynamic content) -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="" enctype="multipart/form-data" id="editUserForm">
                <input type="hidden" name="update_user" value="1">
                <input type="hidden" name="user_id" id="editUserId">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="editUserModalBody">
                    <!-- Dynamic content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
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
                                    <?php foreach($availablePosts as $postValue => $postLabel): ?>
                                        <option value="<?php echo $postValue; ?>"><?php echo $postLabel; ?></option>
                                    <?php endforeach; ?>
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
// Store user data for modal operations
const userData = <?php echo json_encode($users); ?>;

// Open view modal
function openViewModal(userId) {
    const user = userData.find(u => u.id == userId);
    if (!user) return;
    
    const modalBody = document.getElementById('viewUserModalBody');
    const downloadBtn = document.getElementById('downloadLetterBtn');
    
    // Set download link
    downloadBtn.href = `download-confirmation-letter.php?user_id=${userId}`;
    
    // Create modal content
    modalBody.innerHTML = `
        <div class="row">
            <div class="col-md-4 text-center border-end">
                ${user.profile_picture ? 
                    `<img src="<?php echo SITE_URL; ?>/uploads/profile-pictures/${user.profile_picture}" 
                         alt="${user.name}" 
                         class="rounded-circle mb-3" 
                         style="width: 120px; height: 120px; object-fit: cover;">` : 
                    `<div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center mx-auto mb-3" 
                         style="width: 120px; height: 120px;">
                        <i class="fas fa-user text-white fa-3x"></i>
                    </div>`
                }
                <h5>${user.name}</h5>
                <span class="badge bg-${getRoleColor(user.role)}">
                    ${user.role.replace('_', ' ')}
                </span>
            </div>
            <div class="col-md-8">
                <table class="table table-borderless">
                    <tr><th width="30%">Email:</th><td>${user.email}</td></tr>
                    <tr><th>SAP ID:</th><td>${user.sap_id || 'N/A'}</td></tr>
                    <tr><th>Department:</th><td>${user.department || 'N/A'}</td></tr>
                    <tr><th>Semester:</th><td>${user.semester || 'N/A'}</td></tr>
                    <tr><th>Phone:</th><td>${user.phone || 'N/A'}</td></tr>
                    <tr><th>Posts:</th><td>${renderPosts(user.posts)}</td></tr>
                    <tr><th>Status:</th><td><span class="badge bg-${user.status === 'active' ? 'success' : 'secondary'}">${user.status}</span></td></tr>
                    <tr><th>Member Since:</th><td>${new Date(user.created_at).toLocaleString()}</td></tr>
                    <tr><th>Last Updated:</th><td>${user.updated_at ? new Date(user.updated_at).toLocaleString() : 'Never'}</td></tr>
                </table>
            </div>
        </div>
    `;
    
    // Store current user ID for edit modal
    window.currentUserId = userId;
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('viewUserModal'));
    modal.show();
}

// Open edit modal
function openEditModal(userId) {
    const user = userData.find(u => u.id == userId);
    if (!user) return;
    
    const modalBody = document.getElementById('editUserModalBody');
    const userIdInput = document.getElementById('editUserId');
    
    // Set user ID
    userIdInput.value = userId;
    
    // Create modal content
    modalBody.innerHTML = `
        <div class="row">
            <div class="col-md-4 text-center">
                <div class="mb-3">
                    ${user.profile_picture ? 
                        `<img src="<?php echo SITE_URL; ?>/uploads/profile-pictures/${user.profile_picture}" 
                             alt="${user.name}" 
                             class="rounded-circle mb-3" 
                             style="width: 120px; height: 120px; object-fit: cover;"
                             id="editProfilePreview">` : 
                        `<div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center mx-auto mb-3" 
                             style="width: 120px; height: 120px;"
                             id="editProfilePreview">
                            <i class="fas fa-user text-white fa-3x"></i>
                        </div>`
                    }
                    <input type="file" class="form-control form-control-sm" name="profile_picture" accept="image/*" 
                           onchange="previewImage(this, 'editProfilePreview')">
                    <small class="text-muted">Max size: 2MB. JPG, PNG, GIF</small>
                </div>
            </div>
            <div class="col-md-8">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" class="form-control" name="name" value="${user.name}" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" value="${user.email}" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">SAP ID</label>
                            <input type="text" class="form-control" name="sap_id" value="${user.sap_id || ''}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone" value="${user.phone || ''}">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Department</label>
                            <input type="text" class="form-control" name="department" value="${user.department || ''}" placeholder="e.g., Mechanical Engineering">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Semester</label>
                            <input type="number" class="form-control" name="semester" value="${user.semester || ''}" min="1" max="8">
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Assign Posts</label>
                    <select class="form-select" name="posts[]" multiple size="4">
                        ${renderPostOptions(user.posts)}
                    </select>
                    <small class="text-muted">Hold Ctrl/Cmd to select multiple posts</small>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">System Role</label>
                            <select class="form-select" name="role" required>
                                ${renderRoleOptions(user.role)}
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                <option value="active" ${user.status === 'active' ? 'selected' : ''}>Active</option>
                                <option value="inactive" ${user.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
}

// Switch from view to edit modal
function switchToEdit() {
    const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewUserModal'));
    viewModal.hide();
    
    setTimeout(() => {
        if (window.currentUserId) {
            openEditModal(window.currentUserId);
        }
    }, 500);
}

// Helper functions
function getRoleColor(role) {
    switch(role) {
        case 'super_admin': return 'danger';
        case 'department_head': return 'warning';
        case 'event_planner': return 'warning';
        case 'competition_head': return 'warning';
        case 'hiring_head': return 'warning';
        case 'media_head': return 'warning';
        case 'member': return 'success';
        default: return 'secondary';
    }
}

function renderPosts(posts) {
    if (!posts || posts.length === 0) return '<span class="text-muted">No posts assigned</span>';
    
    const availablePosts = <?php echo json_encode($availablePosts); ?>;
    return posts.map(post => 
        `<span class="badge bg-info">${availablePosts[post] || post}</span>`
    ).join(' ');
}

function renderPostOptions(userPosts) {
    const availablePosts = <?php echo json_encode($availablePosts); ?>;
    return Object.entries(availablePosts).map(([value, label]) => 
        `<option value="${value}" ${userPosts.includes(value) ? 'selected' : ''}>${label}</option>`
    ).join('');
}

function renderRoleOptions(currentRole) {
    const roles = [
        ['member', 'Member'],
        ['media_head', 'Media Head'],
        ['event_planner', 'Event Planner'],
        ['competition_head', 'Competition Head'],
        ['hiring_head', 'Hiring Head'],
        ['department_head', 'Department Head'],
        ['super_admin', 'Super Admin']
    ];
    
    return roles.map(([value, label]) => 
        `<option value="${value}" ${currentRole === value ? 'selected' : ''}>${label}</option>`
    ).join('');
}

// User search functionality
document.addEventListener('DOMContentLoaded', function() {
    const userSearch = document.getElementById('userSearch');
    if (userSearch) {
        userSearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const userCards = document.querySelectorAll('.user-card');
            
            userCards.forEach(card => {
                const userName = card.querySelector('.card-title').textContent.toLowerCase();
                const userEmail = card.querySelector('.user-details .text-muted').textContent.toLowerCase();
                
                if (userName.includes(searchTerm) || userEmail.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }

    // Password Reset with PDF Download
    const resetPasswordLinks = document.querySelectorAll('.reset-password-pdf');
    
    resetPasswordLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const userId = this.getAttribute('data-user-id');
            const userName = this.getAttribute('data-user-name');
            
            if (confirm(`Reset password for ${userName} and generate PDF?`)) {
                resetPasswordAndDownloadPDF(userId, this);
            }
        });
    });
    
    function resetPasswordAndDownloadPDF(userId, buttonElement) {
        // Show loading state
        const originalText = buttonElement.innerHTML;
        buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating...';
        buttonElement.disabled = true;
        
        // Make AJAX request to generate PDF
        fetch('generate-password-reset-pdf.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'user_id=' + userId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Download the PDF
                window.location.href = data.download_url;
                
                // Show success message
                showAlert('success', `Password reset successfully for ${data.user_name}. PDF downloaded.`);
            } else {
                showAlert('error', data.message || 'Error resetting password.');
            }
        })
        .catch(error => {
            showAlert('error', 'Network error occurred: ' + error.message);
        })
        .finally(() => {
            // Reset button state
            buttonElement.innerHTML = originalText;
            buttonElement.disabled = false;
        });
    }
    
    function showAlert(type, message) {
        // Remove any existing alerts
        const existingAlerts = document.querySelectorAll('.custom-alert');
        existingAlerts.forEach(alert => alert.remove());
        
        // Create new alert
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show custom-alert`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        // Insert at the top of the main content
        const mainContent = document.querySelector('.col-md-9');
        if (mainContent) {
            mainContent.insertBefore(alertDiv, mainContent.firstChild);
        }
    }
});

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
/* Mobile responsive adjustments */
@media (max-width: 767.98px) {
    .container-fluid {
        padding-left: 10px;
        padding-right: 10px;
    }
    
    .card-header .input-group {
        width: 200px !important;
    }
    
    .user-avatar {
        width: 60px;
        height: 60px;
    }
    
    .user-grid-card .card-title {
        font-size: 0.9rem;
    }
    
    .user-details {
        font-size: 0.75rem;
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

/* User Grid Card Styles */
.user-grid-card {
    transition: all 0.3s ease;
    border: 1px solid #e9ecef;
}

.user-grid-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    border-color: var(--accent-color);
}

.user-avatar {
    width: 80px;
    height: 80px;
    margin: 0 auto;
}

.avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-placeholder {
    width: 100%;
    height: 100%;
    background: var(--accent-color);
    color: white;
    font-size: 24px;
}

.user-details {
    font-size: 0.85rem;
}

.user-posts .badge {
    font-size: 0.7rem;
    margin: 1px;
}

/* Responsive grid adjustments */
@media (max-width: 576px) {
    .col-md-6 {
        flex: 0 0 100%;
        max-width: 100%;
    }
}

/* Card footer button group */
.card-footer .btn-group .btn {
    border-radius: 0;
    padding: 0.25rem 0.5rem;
}

.card-footer .btn-group .btn:first-child {
    border-top-left-radius: 6px;
    border-bottom-left-radius: 6px;
}

.card-footer .btn-group .btn:last-child {
    border-top-right-radius: 6px;
    border-bottom-right-radius: 6px;
}

/* Custom alert styles */
.custom-alert {
    margin-bottom: 1rem;
}

/* Ensure back-to-top button doesn't overlap */
.back-to-top {
    bottom: 80px !important;
    right: 20px !important;
}

@media (max-width: 767.98px) {
    .back-to-top {
        bottom: 70px !important;
        right: 15px !important;
    }
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