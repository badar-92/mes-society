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

// Ensure team_members_all has user_id column
try {
    $check = $db->query("SHOW COLUMNS FROM team_members_all LIKE 'user_id'");
    if ($check->rowCount() == 0) {
        $db->exec("ALTER TABLE team_members_all ADD COLUMN user_id INT NULL, ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL");
    }
} catch(PDOException $e) {
    // Table might not exist yet
}

// Get departments for dropdown
$departments = [];
try {
    $stmt = $db->query("SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name");
    $departments = $stmt->fetchAll();
} catch(PDOException $e) {
    initializeDepartmentsTable();
    $departments = [];
}

// Handle form submissions (add / update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_team_member'])) {
        $user_id = !empty($_POST['user_id']) ? $_POST['user_id'] : null;
        $name = $_POST['name'] ?? '';
        $department = $_POST['department'] ?? '';
        $role = trim($_POST['role'] ?? '');
        $year = $_POST['year'] ?? '';
        $position = $_POST['position'] ?? '';
        $bio = $_POST['bio'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $linkedin = $_POST['linkedin'] ?? '';
        $instagram = $_POST['instagram'] ?? '';
        $github = $_POST['github'] ?? '';
        $display_order = $_POST['display_order'] ?? 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($name) || empty($department) || empty($role) || empty($year)) {
            $error = "Please fill all required fields (Name, Department, Role, Year)";
        } else {
            $profile_picture = 'default-avatar.png';
            if (!$user_id && isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $upload_result = $functions->uploadFile($_FILES['profile_picture'], 'profile_picture');
                if ($upload_result['success']) {
                    $profile_picture = $upload_result['file_name'];
                } else {
                    $error = $upload_result['message'];
                }
            }
            
            if (!$error) {
                $contact_info = json_encode([
                    'email' => $email,
                    'phone' => $phone,
                    'linkedin' => $linkedin,
                    'instagram' => $instagram,
                    'github' => $github
                ]);
                
                $data = [
                    'user_id' => $user_id,
                    'name' => $name,
                    'department' => $department,
                    'role' => $role,
                    'year' => $year,
                    'position' => $position,
                    'bio' => $bio,
                    'profile_picture' => $profile_picture,
                    'contact_info' => $contact_info,
                    'display_order' => $display_order,
                    'is_active' => $is_active
                ];
                
                if (addTeamMemberAll($data)) {
                    $success = "Team member added successfully!";
                } else {
                    $error = "Failed to add team member. Please try again.";
                }
            }
        }
    }
    
    if (isset($_POST['update_team_member'])) {
        $id = $_POST['id'] ?? 0;
        $user_id = !empty($_POST['user_id']) ? $_POST['user_id'] : null;
        $name = $_POST['name'] ?? '';
        $department = $_POST['department'] ?? '';
        $role = trim($_POST['role'] ?? '');
        $year = $_POST['year'] ?? '';
        $position = $_POST['position'] ?? '';
        $bio = $_POST['bio'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $linkedin = $_POST['linkedin'] ?? '';
        $instagram = $_POST['instagram'] ?? '';
        $github = $_POST['github'] ?? '';
        $display_order = $_POST['display_order'] ?? 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $current_picture = $_POST['current_picture'] ?? 'default-avatar.png';
        
        if (empty($name) || empty($department) || empty($role) || empty($year)) {
            $error = "Please fill all required fields (Name, Department, Role, Year)";
        } elseif (!$id) {
            $error = "Invalid team member ID";
        } else {
            $profile_picture = $current_picture;
            if (!$user_id && isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $upload_result = $functions->uploadFile($_FILES['profile_picture'], 'profile_picture');
                if ($upload_result['success']) {
                    $profile_picture = $upload_result['file_name'];
                    if ($current_picture && $current_picture !== 'default-avatar.png') {
                        $old_file_path = '../uploads/profile-pictures/' . $current_picture;
                        if (file_exists($old_file_path) && is_file($old_file_path)) {
                            unlink($old_file_path);
                        }
                    }
                } else {
                    $error = $upload_result['message'];
                }
            }
            
            if (!$error) {
                $contact_info = json_encode([
                    'email' => $email,
                    'phone' => $phone,
                    'linkedin' => $linkedin,
                    'instagram' => $instagram,
                    'github' => $github
                ]);
                
                $data = [
                    'user_id' => $user_id,
                    'name' => $name,
                    'department' => $department,
                    'role' => $role,
                    'year' => $year,
                    'position' => $position,
                    'bio' => $bio,
                    'profile_picture' => $profile_picture,
                    'contact_info' => $contact_info,
                    'display_order' => $display_order,
                    'is_active' => $is_active
                ];
                
                if (updateTeamMemberAll($id, $data)) {
                    $success = "Team member updated successfully!";
                } else {
                    $error = "Failed to update team member. Please try again.";
                }
            }
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $member = getTeamMemberAllById($id);
    if ($member && !$member['user_id']) {
        $profile_picture = $member['profile_picture'];
        if ($profile_picture && $profile_picture !== 'default-avatar.png') {
            $file_path = '../uploads/profile-pictures/' . $profile_picture;
            if (file_exists($file_path) && is_file($file_path)) {
                unlink($file_path);
            }
        }
    }
    if (deleteTeamMemberAll($id)) {
        $success = "Team member deleted successfully!";
    } else {
        $error = "Failed to delete team member. Please try again.";
    }
    header("Location: team-members-all.php");
    exit;
}

// Get filter parameters
$filter_department = $_GET['department'] ?? '';
$filter_role = $_GET['role'] ?? '';
$filter_year = $_GET['year'] ?? '';

// Get all team members (with user data)
$team_members = getTeamMembersAllFiltered($filter_department, $filter_role, $filter_year);

// Get all active users for the dropdown (add/edit)
$users = [];
try {
    $stmt = $db->query("SELECT id, name, email, phone, sap_id FROM users WHERE status = 'active' ORDER BY name");
    $users = $stmt->fetchAll();
} catch(PDOException $e) {
    $users = [];
}

$page_title = "All Team Members Management";
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
            <div class="d-md-none d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                <div>
                    <h4 class="mb-0">All Team Members</h4>
                    <small class="text-muted">Manage members for team page</small>
                </div>
                <button class="btn btn-accent" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileSidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <div class="d-none d-md-flex justify-content-between align-items-center mb-4">
                <h1>All Team Members Management</h1>
                <div>
                    <a href="departments-management.php" class="btn btn-outline-primary me-2">
                        <i class="fas fa-building me-2"></i>Manage Departments
                    </a>
                    <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                        <i class="fas fa-plus me-2"></i>Add Team Member
                    </button>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show"><?php echo htmlspecialchars($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show"><?php echo htmlspecialchars($success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Filter Team Members</h5>
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="department" class="form-label">Department</label>
                            <select class="form-select" id="department" name="department">
                                <option value="">All Departments</option>
                                <?php foreach($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept['department_key']); ?>" <?php echo $filter_department == $dept['department_key'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept['department_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="role" class="form-label">Role (contains)</label>
                            <input type="text" class="form-control" id="role" name="role" value="<?php echo htmlspecialchars($filter_role); ?>" placeholder="e.g., Designer">
                        </div>
                        <div class="col-md-3">
                            <label for="year" class="form-label">Year</label>
                            <select class="form-select" id="year" name="year">
                                <option value="">All Years</option>
                                <option value="1" <?php echo $filter_year == '1' ? 'selected' : ''; ?>>1st Year</option>
                                <option value="2" <?php echo $filter_year == '2' ? 'selected' : ''; ?>>2nd Year</option>
                                <option value="3" <?php echo $filter_year == '3' ? 'selected' : ''; ?>>3rd Year</option>
                                <option value="4" <?php echo $filter_year == '4' ? 'selected' : ''; ?>>4th Year</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Team Members Grid -->
            <div class="row" id="teamMembersGrid">
                <?php foreach($team_members as $member): 
                    $contact_info = json_decode($member['contact_info'], true);
                    $dept_name = $member['department'];
                    foreach($departments as $dept) {
                        if ($dept['department_key'] == $member['department']) {
                            $dept_name = $dept['department_name'];
                            break;
                        }
                    }
                    $display_name = $member['user_name'] ?? $member['name'];
                    $display_email = $member['user_email'] ?? ($contact_info['email'] ?? '');
                    $display_phone = $member['user_phone'] ?? ($contact_info['phone'] ?? '');
                    $display_picture = $member['user_profile_picture'] ?? $member['profile_picture'];
                ?>
                    <div class="col-md-6 col-lg-4 mb-4" data-member-id="<?php echo $member['id']; ?>">
                        <div class="card h-100">
                            <img src="../uploads/profile-pictures/<?php echo htmlspecialchars($display_picture); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($display_name); ?>" style="height: 200px; object-fit: cover;" onerror="this.src='../uploads/profile-pictures/default-avatar.png'">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($display_name); ?></h5>
                                <h6 class="card-subtitle mb-2 text-accent"><?php echo htmlspecialchars($dept_name); ?></h6>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="badge bg-info"><?php echo htmlspecialchars($member['role']); ?></span>
                                    <span class="badge bg-secondary">Year <?php echo htmlspecialchars($member['year']); ?></span>
                                </div>
                                <?php if ($member['position']): ?>
                                    <p class="card-text"><strong><?php echo htmlspecialchars($member['position']); ?></strong></p>
                                <?php endif; ?>
                                <p class="card-text small"><?php echo htmlspecialchars(substr($member['bio'], 0, 100)) . '...'; ?></p>
                                <?php if ($display_email || $display_phone): ?>
                                    <div class="contact-info mt-2">
                                        <?php if($display_email): ?>
                                            <div class="mb-1"><i class="fas fa-envelope me-1 text-muted"></i><small><?php echo htmlspecialchars($display_email); ?></small></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-<?php echo $member['is_active'] ? 'success' : 'secondary'; ?>"><?php echo $member['is_active'] ? 'Active' : 'Inactive'; ?></span>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-outline-primary edit-member" data-member-id="<?php echo $member['id']; ?>" data-bs-toggle="modal" data-bs-target="#editMemberModal"><i class="fas fa-edit"></i></button>
                                        <a href="?delete=<?php echo $member['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this team member? This action cannot be undone.')"><i class="fas fa-trash"></i></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($team_members)): ?>
                <div class="text-center py-5"><i class="fas fa-users fa-3x text-muted mb-3"></i><h4>No Team Members Found</h4><p class="text-muted">Add team members or adjust your filters.</p><a href="team-members-all.php" class="btn btn-primary">Clear Filters</a></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Member Modal -->
<div class="modal fade" id="addMemberModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Add Team Member</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="user_search" class="form-label">Link to Existing User (Optional)</label>
                        <select class="form-select" id="user_search" name="user_id">
                            <option value="">-- Manual Entry (No linked user) --</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['name'] . ' (' . $user['email'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Select a user to auto-fill name, email, phone, and profile picture.</small>
                    </div>
                    <div class="row">
                        <div class="col-md-6"><div class="mb-3"><label for="name" class="form-label">Full Name *</label><input type="text" class="form-control" id="name" name="name" required></div></div>
                        <div class="col-md-6"><div class="mb-3"><label for="department" class="form-label">Department *</label><select class="form-select" id="department" name="department" required><option value="">Select Department</option><?php foreach($departments as $dept): ?><option value="<?php echo htmlspecialchars($dept['department_key']); ?>"><?php echo htmlspecialchars($dept['department_name']); ?></option><?php endforeach; ?></select></div></div>
                    </div>
                    <div class="row">
                        <div class="col-md-4"><div class="mb-3"><label for="role" class="form-label">Role *</label><input type="text" class="form-control" id="role" name="role" placeholder="e.g., Graphic Designer" required><small class="text-muted">Specific role/title in the department</small></div></div>
                        <div class="col-md-4"><div class="mb-3"><label for="year" class="form-label">Year *</label><select class="form-select" id="year" name="year" required><option value="">Select Year</option><option value="1">1st Year</option><option value="2">2nd Year</option><option value="3">3rd Year</option><option value="4">4th Year</option></select></div></div>
                        <div class="col-md-4"><div class="mb-3"><label for="position" class="form-label">Position Title</label><input type="text" class="form-control" id="position" name="position" placeholder="e.g., Lead Designer"><small class="text-muted">Optional: Additional position title</small></div></div>
                    </div>
                    <div class="mb-3"><label for="bio" class="form-label">Bio/Description</label><textarea class="form-control" id="bio" name="bio" rows="3" placeholder="Brief description about the team member..."></textarea></div>
                    <div class="row">
                        <div class="col-md-6"><div class="mb-3"><label for="email" class="form-label">Email</label><input type="email" class="form-control" id="email" name="email" placeholder="member@example.com"></div></div>
                        <div class="col-md-6"><div class="mb-3"><label for="phone" class="form-label">Phone</label><input type="tel" class="form-control" id="phone" name="phone" placeholder="+91 9876543210"></div></div>
                    </div>
                    <div class="row">
                        <div class="col-md-4"><div class="mb-3"><label for="linkedin" class="form-label">LinkedIn</label><input type="url" class="form-control" id="linkedin" name="linkedin" placeholder="https://linkedin.com/in/username"></div></div>
                        <div class="col-md-4"><div class="mb-3"><label for="instagram" class="form-label">Instagram</label><input type="url" class="form-control" id="instagram" name="instagram" placeholder="https://instagram.com/username"></div></div>
                        <div class="col-md-4"><div class="mb-3"><label for="github" class="form-label">GitHub</label><input type="url" class="form-control" id="github" name="github" placeholder="https://github.com/username"></div></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6"><div class="mb-3"><label for="profile_picture" class="form-label">Profile Picture (Manual entry only)</label><input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*"><small class="text-muted">Leave empty to use default. For linked users, picture comes from their profile.</small></div></div>
                        <div class="col-md-6"><div class="mb-3"><label for="display_order" class="form-label">Display Order</label><input type="number" class="form-control" id="display_order" name="display_order" value="0" min="0"><small class="text-muted">Lower numbers display first within department</small></div></div>
                    </div>
                    <div class="mb-3 form-check"><input type="checkbox" class="form-check-input" id="is_active" name="is_active" checked><label class="form-check-label" for="is_active">Active (Visible on website)</label></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-accent" name="add_team_member">Add Member</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Member Modal (populated instantly via JavaScript) -->
<div class="modal fade" id="editMemberModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Edit Team Member</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body" id="editMemberForm"></div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-accent" name="update_team_member">Update Member</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Mobile Floating Action Button -->
<div class="fab-container d-md-none"><button class="fab btn btn-warning rounded-circle shadow" data-bs-toggle="modal" data-bs-target="#addMemberModal"><i class="fas fa-plus"></i></button></div>

<script>
// Preload all team members data as JavaScript array
const teamMembersData = <?php
    $members_json = [];
    foreach ($team_members as $m) {
        $contact = json_decode($m['contact_info'], true);
        $members_json[] = [
            'id' => $m['id'],
            'user_id' => $m['user_id'],
            'name' => $m['user_name'] ?? $m['name'],
            'manual_name' => $m['name'],
            'department' => $m['department'],
            'role' => $m['role'],
            'year' => $m['year'],
            'position' => $m['position'],
            'bio' => $m['bio'],
            'profile_picture' => $m['user_profile_picture'] ?? $m['profile_picture'],
            'manual_picture' => $m['profile_picture'],
            'email' => $m['user_email'] ?? ($contact['email'] ?? ''),
            'phone' => $m['user_phone'] ?? ($contact['phone'] ?? ''),
            'linkedin' => $contact['linkedin'] ?? '',
            'instagram' => $contact['instagram'] ?? '',
            'github' => $contact['github'] ?? '',
            'display_order' => $m['display_order'],
            'is_active' => (bool)$m['is_active']
        ];
    }
    echo json_encode($members_json);
?>;

const departments = <?php echo json_encode($departments); ?>;
const usersList = <?php echo json_encode($users); ?>;

function escapeHtml(unsafe) {
    if (unsafe === null || unsafe === undefined) return '';
    return unsafe.toString().replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    }).replace(/[\uD800-\uDBFF][\uDC00-\uDFFF]/g, function(c) {
        return c;
    });
}

function populateEditModal(memberId) {
    const member = teamMembersData.find(m => m.id == memberId);
    if (!member) {
        document.getElementById('editMemberForm').innerHTML = '<div class="alert alert-danger">Member not found.</div>';
        return;
    }
    
    let departmentOptions = '<option value="">Select Department</option>';
    for (const dept of departments) {
        departmentOptions += `<option value="${escapeHtml(dept.department_key)}" ${member.department === dept.department_key ? 'selected' : ''}>${escapeHtml(dept.department_name)}</option>`;
    }
    
    let userOptions = '<option value="">-- Manual Entry (No linked user) --</option>';
    for (const user of usersList) {
        userOptions += `<option value="${user.id}" ${member.user_id == user.id ? 'selected' : ''}>${escapeHtml(user.name + ' (' + user.email + ')')}</option>`;
    }
    
    const formHtml = `
        <input type="hidden" name="id" value="${member.id}">
        <input type="hidden" name="current_picture" value="${escapeHtml(member.manual_picture || 'default-avatar.png')}">
        
        <div class="mb-3">
            <label for="edit_user_id" class="form-label">Link to Existing User</label>
            <select class="form-select" id="edit_user_id" name="user_id">${userOptions}</select>
            <small class="text-muted">Selecting a user will override name, email, phone, and profile picture.</small>
        </div>
        
        <div class="row">
            <div class="col-md-6"><div class="mb-3"><label for="edit_name" class="form-label">Full Name *</label><input type="text" class="form-control" id="edit_name" name="name" value="${escapeHtml(member.name)}" required></div></div>
            <div class="col-md-6"><div class="mb-3"><label for="edit_department" class="form-label">Department *</label><select class="form-select" id="edit_department" name="department" required>${departmentOptions}</select></div></div>
        </div>
        <div class="row">
            <div class="col-md-4"><div class="mb-3"><label for="edit_role" class="form-label">Role *</label><input type="text" class="form-control" id="edit_role" name="role" value="${escapeHtml(member.role)}" required></div></div>
            <div class="col-md-4"><div class="mb-3"><label for="edit_year" class="form-label">Year *</label><select class="form-select" id="edit_year" name="year" required>
                <option value="">Select Year</option>
                <option value="1" ${member.year == '1' ? 'selected' : ''}>1st Year</option>
                <option value="2" ${member.year == '2' ? 'selected' : ''}>2nd Year</option>
                <option value="3" ${member.year == '3' ? 'selected' : ''}>3rd Year</option>
                <option value="4" ${member.year == '4' ? 'selected' : ''}>4th Year</option>
            </select></div></div>
            <div class="col-md-4"><div class="mb-3"><label for="edit_position" class="form-label">Position Title</label><input type="text" class="form-control" id="edit_position" name="position" value="${escapeHtml(member.position)}"></div></div>
        </div>
        <div class="mb-3"><label for="edit_bio" class="form-label">Bio/Description</label><textarea class="form-control" id="edit_bio" name="bio" rows="3">${escapeHtml(member.bio)}</textarea></div>
        <div class="row">
            <div class="col-md-6"><div class="mb-3"><label for="edit_email" class="form-label">Email</label><input type="email" class="form-control" id="edit_email" name="email" value="${escapeHtml(member.email)}"></div></div>
            <div class="col-md-6"><div class="mb-3"><label for="edit_phone" class="form-label">Phone</label><input type="tel" class="form-control" id="edit_phone" name="phone" value="${escapeHtml(member.phone)}"></div></div>
        </div>
        <div class="row">
            <div class="col-md-4"><div class="mb-3"><label for="edit_linkedin" class="form-label">LinkedIn</label><input type="url" class="form-control" id="edit_linkedin" name="linkedin" value="${escapeHtml(member.linkedin)}"></div></div>
            <div class="col-md-4"><div class="mb-3"><label for="edit_instagram" class="form-label">Instagram</label><input type="url" class="form-control" id="edit_instagram" name="instagram" value="${escapeHtml(member.instagram)}"></div></div>
            <div class="col-md-4"><div class="mb-3"><label for="edit_github" class="form-label">GitHub</label><input type="url" class="form-control" id="edit_github" name="github" value="${escapeHtml(member.github)}"></div></div>
        </div>
        <div class="row">
            <div class="col-md-6"><div class="mb-3"><label for="edit_profile_picture" class="form-label">Profile Picture (Manual only)</label><input type="file" class="form-control" id="edit_profile_picture" name="profile_picture" accept="image/*"><small class="text-muted">Current: ${escapeHtml(member.profile_picture)}</small></div></div>
            <div class="col-md-6"><div class="mb-3"><label for="edit_display_order" class="form-label">Display Order</label><input type="number" class="form-control" id="edit_display_order" name="display_order" value="${member.display_order}" min="0"></div></div>
        </div>
        <div class="mb-3 form-check"><input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active" ${member.is_active ? 'checked' : ''}><label class="form-check-label" for="edit_is_active">Active (Visible on website)</label></div>
    `;
    
    document.getElementById('editMemberForm').innerHTML = formHtml;
    
    // Auto-fill when user selection changes in edit modal
    const editUserSelect = document.getElementById('edit_user_id');
    if (editUserSelect) {
        editUserSelect.addEventListener('change', function() {
            const userId = this.value;
            if (userId) {
                fetch(`get-user-details.php?id=${userId}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data) {
                            document.getElementById('edit_name').value = data.name || '';
                            document.getElementById('edit_email').value = data.email || '';
                            document.getElementById('edit_phone').value = data.phone || '';
                        }
                    })
                    .catch(err => console.error(err));
            } else {
                // Revert to originally stored manual values
                document.getElementById('edit_name').value = member.manual_name || '';
                document.getElementById('edit_email').value = member.email;
                document.getElementById('edit_phone').value = member.phone;
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Add modal: auto-fill when user selected
    const userSearch = document.getElementById('user_search');
    if (userSearch) {
        userSearch.addEventListener('change', function() {
            const userId = this.value;
            if (userId) {
                fetch(`get-user-details.php?id=${userId}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data) {
                            document.getElementById('name').value = data.name || '';
                            document.getElementById('email').value = data.email || '';
                            document.getElementById('phone').value = data.phone || '';
                        }
                    })
                    .catch(err => console.error(err));
            } else {
                document.getElementById('name').value = '';
                document.getElementById('email').value = '';
                document.getElementById('phone').value = '';
            }
        });
    }
    
    // Edit modal: populate instantly from preloaded data
    document.querySelectorAll('.edit-member').forEach(btn => {
        btn.addEventListener('click', function() {
            const memberId = this.getAttribute('data-member-id');
            populateEditModal(memberId);
        });
    });
    
    // Clear add modal on close
    const addModal = document.getElementById('addMemberModal');
    if (addModal) {
        addModal.addEventListener('hidden.bs.modal', function() {
            this.querySelector('form').reset();
        });
    }
});
</script>

<style>
.fab-container { position: fixed; bottom: 20px; right: 20px; z-index: 1030; }
.fab { width: 60px; height: 60px; font-size: 20px; }
.team-card { transition: transform 0.3s; }
.team-card:hover { transform: translateY(-5px); }
</style>

<?php
require_once '../includes/footer.php';

// Helper functions (unchanged but updated to join users)
function initializeDepartmentsTable() {
    global $db;
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS departments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            department_key VARCHAR(100) UNIQUE NOT NULL,
            department_name VARCHAR(255) NOT NULL,
            description TEXT,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $defaults = [
            ['event_planning','Event Planning','Organizing memorable events and workshops'],
            ['media_marketing','Media & Marketing','Creating engaging content and promotions'],
            ['competitions','Competitions','Managing technical competitions and events'],
            ['recruitment','Recruitment','Onboarding new members and talent acquisition'],
            ['technical','Technical Team','Technical development and support'],
            ['design','Design Team','Graphic and UI/UX design work'],
            ['content','Content Writing','Content creation and copywriting'],
            ['logistics','Logistics','Event logistics and management'],
            ['sponsorship','Sponsorship','Sponsorship and partnership management'],
            ['general','General Members','General team members']
        ];
        foreach ($defaults as $d) {
            $stmt = $db->prepare("INSERT IGNORE INTO departments (department_key, department_name, description) VALUES (?,?,?)");
            $stmt->execute($d);
        }
        $db->exec("CREATE TABLE IF NOT EXISTS team_members_all (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            name VARCHAR(255) NOT NULL,
            department VARCHAR(100) NOT NULL,
            role VARCHAR(100) NOT NULL,
            year VARCHAR(10) NOT NULL,
            position VARCHAR(255),
            bio TEXT,
            profile_picture VARCHAR(255) DEFAULT 'default-avatar.png',
            contact_info JSON,
            display_order INT DEFAULT 0,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (department) REFERENCES departments(department_key),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return true;
    } catch(PDOException $e) {
        error_log("Initialize tables error: " . $e->getMessage());
        return false;
    }
}

function getTeamMembersAllFiltered($department = '', $role = '', $year = '') {
    global $db;
    try {
        $sql = "SELECT tm.*, u.name AS user_name, u.email AS user_email, u.phone AS user_phone, u.profile_picture AS user_profile_picture
                FROM team_members_all tm
                LEFT JOIN users u ON tm.user_id = u.id
                WHERE 1=1";
        $params = [];
        if (!empty($department)) { $sql .= " AND tm.department = ?"; $params[] = $department; }
        if (!empty($role)) { $sql .= " AND tm.role LIKE ?"; $params[] = "%$role%"; }
        if (!empty($year)) { $sql .= " AND tm.year = ?"; $params[] = $year; }
        $sql .= " ORDER BY tm.department, tm.display_order, COALESCE(u.name, tm.name)";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Get team members error: " . $e->getMessage());
        return [];
    }
}

function getTeamMemberAllById($id) {
    global $db;
    try {
        $stmt = $db->prepare("SELECT tm.*, u.name AS user_name, u.email AS user_email, u.phone AS user_phone, u.profile_picture AS user_profile_picture
                              FROM team_members_all tm LEFT JOIN users u ON tm.user_id = u.id WHERE tm.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch(PDOException $e) {
        error_log("Get team member by ID error: " . $e->getMessage());
        return false;
    }
}

function addTeamMemberAll($data) {
    global $db;
    try {
        $stmt = $db->prepare("INSERT INTO team_members_all (user_id, name, department, role, year, position, bio, profile_picture, contact_info, display_order, is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        return $stmt->execute([$data['user_id'], $data['name'], $data['department'], $data['role'], $data['year'], $data['position'], $data['bio'], $data['profile_picture'], $data['contact_info'], $data['display_order'], $data['is_active']]);
    } catch(PDOException $e) {
        error_log("Add team member error: " . $e->getMessage());
        return false;
    }
}

function updateTeamMemberAll($id, $data) {
    global $db;
    try {
        $stmt = $db->prepare("UPDATE team_members_all SET user_id=?, name=?, department=?, role=?, year=?, position=?, bio=?, profile_picture=?, contact_info=?, display_order=?, is_active=? WHERE id=?");
        return $stmt->execute([$data['user_id'], $data['name'], $data['department'], $data['role'], $data['year'], $data['position'], $data['bio'], $data['profile_picture'], $data['contact_info'], $data['display_order'], $data['is_active'], $id]);
    } catch(PDOException $e) {
        error_log("Update team member error: " . $e->getMessage());
        return false;
    }
}

function deleteTeamMemberAll($id) {
    global $db;
    try {
        $stmt = $db->prepare("DELETE FROM team_members_all WHERE id = ?");
        return $stmt->execute([$id]);
    } catch(PDOException $e) {
        error_log("Delete team member error: " . $e->getMessage());
        return false;
    }
}
?>