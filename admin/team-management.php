<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['super_admin', 'department_head']);

$db = Database::getInstance()->getConnection();
$functions = new Functions();

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_team_member'])) {
        $name = $_POST['name'] ?? '';
        $position = $_POST['position'] ?? '';
        $bio = $_POST['bio'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $display_order = $_POST['display_order'] ?? 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Handle file upload
        $profile_picture = null;
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $upload_result = $functions->uploadFile($_FILES['profile_picture'], 'profile_picture');
            if ($upload_result['success']) {
                $profile_picture = $upload_result['file_name'];
            } else {
                $error = $upload_result['message'];
            }
        }
        
        if (!$error) {
            $data = [
                'name' => $name,
                'position' => $position,
                'bio' => $bio,
                'email' => $email,
                'phone' => $phone,
                'profile_picture' => $profile_picture ?: 'default-avatar.png',
                'display_order' => $display_order,
                'is_active' => $is_active
            ];
            
            if ($functions->addTeamMember($data)) {
                $success = "Team member added successfully!";
            } else {
                $error = "Failed to add team member. Please try again.";
            }
        }
    }
    
    if (isset($_POST['update_team_member'])) {
        $id = $_POST['id'] ?? 0;
        $name = $_POST['name'] ?? '';
        $position = $_POST['position'] ?? '';
        $bio = $_POST['bio'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $display_order = $_POST['display_order'] ?? 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $current_picture = $_POST['current_picture'] ?? '';
        
        // Handle file upload
        $profile_picture = $current_picture;
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $upload_result = $functions->uploadFile($_FILES['profile_picture'], 'profile_picture');
            if ($upload_result['success']) {
                $profile_picture = $upload_result['file_name'];
                // Delete old picture if it's not the default - FIXED: Check if file exists
                if ($current_picture && $current_picture !== 'default-avatar.png') {
                    $old_file_path = '../uploads/profile-pictures/' . $current_picture;
                    if (file_exists($old_file_path) && is_file($old_file_path)) {
                        if (unlink($old_file_path)) {
                            // File deleted successfully
                        } else {
                            error_log("Failed to delete old profile picture: " . $old_file_path);
                        }
                    } else {
                        error_log("Old profile picture not found, skipping deletion: " . $old_file_path);
                    }
                }
            } else {
                $error = $upload_result['message'];
            }
        }
        
        if (!$error && $id) {
            $data = [
                'name' => $name,
                'position' => $position,
                'bio' => $bio,
                'email' => $email,
                'phone' => $phone,
                'profile_picture' => $profile_picture,
                'display_order' => $display_order,
                'is_active' => $is_active
            ];
            
            if ($functions->updateTeamMember($id, $data)) {
                $success = "Team member updated successfully!";
            } else {
                $error = "Failed to update team member. Please try again.";
            }
        }
    }
}

// Handle delete - FIXED: Add proper file deletion handling
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Get member data first to delete associated file
    $member = $functions->getTeamMemberById($id);
    if ($member) {
        // Delete profile picture if it exists and is not default
        $profile_picture = $member['profile_picture'];
        if ($profile_picture && $profile_picture !== 'default-avatar.png') {
            $file_path = '../uploads/profile-pictures/' . $profile_picture;
            if (file_exists($file_path) && is_file($file_path)) {
                if (!unlink($file_path)) {
                    error_log("Failed to delete profile picture during member deletion: " . $file_path);
                }
            }
        }
        
        // Delete the team member record
        if ($functions->deleteTeamMember($id)) {
            $success = "Team member deleted successfully!";
        } else {
            $error = "Failed to delete team member. Please try again.";
        }
    } else {
        $error = "Team member not found.";
    }
    
    header("Location: team-management.php");
    exit;
}

// Get all team members
$team_members = $functions->getTeamMembers(false);

$page_title = "Team Management";
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
                    <h4 class="mb-0">Team Management</h4>
                    <small class="text-muted">Manage Team Members</small>
                </div>
                <button class="btn btn-accent" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileSidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <div class="d-none d-md-flex justify-content-between align-items-center mb-4">
                <h1>Team Management</h1>
                <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                    <i class="fas fa-plus me-2"></i>Add Team Member
                </button>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Team Members Grid -->
            <div class="row">
                <?php foreach($team_members as $member): 
                    $contact_info = json_decode($member['contact_info'], true);
                ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100">
                            <img src="../uploads/profile-pictures/<?php echo htmlspecialchars($member['profile_picture']); ?>" 
                                 class="card-img-top" 
                                 alt="<?php echo htmlspecialchars($member['name']); ?>"
                                 style="height: 200px; object-fit: cover;"
                                 onerror="this.src='../uploads/profile-pictures/default-avatar.png'">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($member['name']); ?></h5>
                                <h6 class="card-subtitle mb-2 text-accent"><?php echo htmlspecialchars($member['position']); ?></h6>
                                <p class="card-text"><?php echo htmlspecialchars($member['bio']); ?></p>
                                <?php if ($contact_info): ?>
                                    <div class="contact-info">
                                        <?php if(isset($contact_info['email'])): ?>
                                            <div class="mb-1">
                                                <i class="fas fa-envelope me-2 text-muted"></i>
                                                <?php echo htmlspecialchars($contact_info['email']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if(isset($contact_info['phone'])): ?>
                                            <div>
                                                <i class="fas fa-phone me-2 text-muted"></i>
                                                <?php echo htmlspecialchars($contact_info['phone']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <div class="d-flex justify-content-between">
                                    <span class="badge bg-<?php echo $member['is_active'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $member['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-outline-primary edit-member" 
                                                data-member-id="<?php echo $member['id']; ?>"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editMemberModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?delete=<?php echo $member['id']; ?>" 
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('Are you sure you want to delete this team member? This action cannot be undone.')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($team_members)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h4>No Team Members</h4>
                    <p class="text-muted">Add team members to display them on the homepage.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Member Modal -->
<div class="modal fade" id="addMemberModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Team Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Name *</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="position" class="form-label">Position *</label>
                                <input type="text" class="form-control" id="position" name="position" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="bio" class="form-label">Bio</label>
                        <textarea class="form-control" id="bio" name="bio" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email">
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
                                <label for="profile_picture" class="form-label">Profile Picture</label>
                                <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*">
                                <small class="text-muted">Recommended: Square image, max 2MB</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="display_order" class="form-label">Display Order</label>
                                <input type="number" class="form-control" id="display_order" name="display_order" value="0">
                                <small class="text-muted">Lower numbers display first</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" checked>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-accent" name="add_team_member">Add Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Member Modal -->
<div class="modal fade" id="editMemberModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Team Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body" id="editMemberForm">
                    <!-- Form will be loaded via AJAX -->
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p>Loading team member data...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-accent" name="update_team_member">Update Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Mobile Floating Action Button -->
<div class="fab-container d-md-none">
    <button class="fab btn btn-warning rounded-circle shadow" data-bs-toggle="modal" data-bs-target="#addMemberModal">
        <i class="fas fa-plus"></i>
    </button>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle edit member clicks
    document.querySelectorAll('.edit-member').forEach(button => {
        button.addEventListener('click', function() {
            const memberId = this.getAttribute('data-member-id');
            loadMemberData(memberId);
        });
    });

    // Clear form when add modal is closed
    const addModal = document.getElementById('addMemberModal');
    if (addModal) {
        addModal.addEventListener('hidden.bs.modal', function () {
            this.querySelector('form').reset();
        });
    }
});

function loadMemberData(memberId) {
    const form = document.getElementById('editMemberForm');
    form.innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p>Loading team member data...</p>
        </div>
    `;

    fetch('../api/team.php?action=get_member&id=' + memberId)
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(member => {
        if (member && !member.error) {
            form.innerHTML = `
                <input type="hidden" name="id" value="${member.id}">
                <input type="hidden" name="current_picture" value="${member.profile_picture}">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Name *</label>
                            <input type="text" class="form-control" id="edit_name" name="name" value="${escapeHtml(member.name)}" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="edit_position" class="form-label">Position *</label>
                            <input type="text" class="form-control" id="edit_position" name="position" value="${escapeHtml(member.position)}" required>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="edit_bio" class="form-label">Bio</label>
                    <textarea class="form-control" id="edit_bio" name="bio" rows="3">${escapeHtml(member.bio || '')}</textarea>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email" value="${escapeHtml(member.contact_info?.email || '')}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="edit_phone" class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="edit_phone" name="phone" value="${escapeHtml(member.contact_info?.phone || '')}">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="edit_profile_picture" class="form-label">Profile Picture</label>
                            <input type="file" class="form-control" id="edit_profile_picture" name="profile_picture" accept="image/*">
                            <small class="text-muted">Current: ${escapeHtml(member.profile_picture)}</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="edit_display_order" class="form-label">Display Order</label>
                            <input type="number" class="form-control" id="edit_display_order" name="display_order" value="${member.display_order}">
                        </div>
                    </div>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active" ${member.is_active ? 'checked' : ''}>
                    <label class="form-check-label" for="edit_is_active">Active</label>
                </div>
            `;
        } else {
            form.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Failed to load team member data. Please try again.
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        form.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Error loading team member data. Please refresh the page and try again.
            </div>
        `;
    });
}

// Helper function to escape HTML
function escapeHtml(unsafe) {
    if (unsafe === null || unsafe === undefined) return '';
    return unsafe
        .toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
</script>

<style>
/* Floating Action Button with Orange Background */
.fab-container {
    position: fixed;
    bottom: 80px;
    right: 20px;
    z-index: 1030;
}




</style>  

<?php require_once '../includes/footer.php'; ?>