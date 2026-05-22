<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['super_admin', 'department_head']);

$db = Database::getInstance()->getConnection();

$error = '';
$success = '';

// Default departments that can't be deleted
$protected_departments = [
    'event_planning',
    'media_marketing', 
    'competitions',
    'recruitment',
    'technical',
    'design',
    'content',
    'logistics',
    'sponsorship',
    'general'
];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new department
    if (isset($_POST['add_department'])) {
        $department_name = trim($_POST['department_name'] ?? '');
        $department_key = trim($_POST['department_key'] ?? '');
        
        if (!empty($department_name) && !empty($department_key)) {
            // Validate key format
            if (!preg_match('/^[a-z0-9_]+$/', $department_key)) {
                $error = "Department key must contain only lowercase letters, numbers, and underscores";
            } else {
                try {
                    // Check if department key already exists
                    $stmt = $db->prepare("SELECT COUNT(*) FROM departments WHERE department_key = ?");
                    $stmt->execute([$department_key]);
                    if ($stmt->fetchColumn() > 0) {
                        $error = "Department key already exists. Please use a different key.";
                    } else {
                        // Insert new department
                        $sql = "INSERT INTO departments (department_key, department_name, is_active) VALUES (?, ?, 1)";
                        $stmt = $db->prepare($sql);
                        
                        if ($stmt->execute([$department_key, $department_name])) {
                            $success = "New department added successfully!";
                        } else {
                            $error = "Failed to add new department. Please try again.";
                        }
                    }
                } catch(PDOException $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        } else {
            $error = "Please provide both department name and key";
        }
    }
    
    // Update department
    if (isset($_POST['update_department'])) {
        $id = $_POST['id'] ?? 0;
        $department_name = trim($_POST['department_name'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if ($id && !empty($department_name)) {
            try {
                // Get current department key to check if it's protected
                $stmt = $db->prepare("SELECT department_key FROM departments WHERE id = ?");
                $stmt->execute([$id]);
                $dept = $stmt->fetch();
                
                if ($dept && in_array($dept['department_key'], $protected_departments)) {
                    $error = "This is a default department and cannot be modified.";
                } else {
                    $sql = "UPDATE departments SET department_name = ?, is_active = ? WHERE id = ?";
                    $stmt = $db->prepare($sql);
                    
                    if ($stmt->execute([$department_name, $is_active, $id])) {
                        $success = "Department updated successfully!";
                    } else {
                        $error = "Failed to update department. Please try again.";
                    }
                }
            } catch(PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    try {
        // Get department key to check if it's protected
        $stmt = $db->prepare("SELECT department_key FROM departments WHERE id = ?");
        $stmt->execute([$id]);
        $dept = $stmt->fetch();
        
        if ($dept) {
            if (in_array($dept['department_key'], $protected_departments)) {
                $error = "This is a default department and cannot be deleted.";
            } else {
                // Check if department has team members in team_members_all table
                $stmt = $db->prepare("SELECT COUNT(*) FROM team_members_all WHERE department = ?");
                $stmt->execute([$dept['department_key']]);
                $has_members = $stmt->fetchColumn() > 0;
                
                if ($has_members) {
                    $error = "Cannot delete department because it has team members. Please reassign or delete members first.";
                } else {
                    // Delete the department
                    $stmt = $db->prepare("DELETE FROM departments WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        $success = "Department deleted successfully!";
                    } else {
                        $error = "Failed to delete department. Please try again.";
                    }
                }
            }
        }
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
    
    header("Location: departments-management.php");
    exit;
}

// Get all departments
$departments = [];
try {
    $stmt = $db->query("SELECT * FROM departments ORDER BY department_name");
    $departments = $stmt->fetchAll();
} catch(PDOException $e) {
    // Table might not exist, we'll handle it in the view
}

$page_title = "Departments Management";
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
                    <h4 class="mb-0">Departments</h4>
                    <small class="text-muted">Manage team departments</small>
                </div>
                <button class="btn btn-accent" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileSidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <div class="d-none d-md-flex justify-content-between align-items-center mb-4">
                <h1>Departments Management</h1>
                <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                    <i class="fas fa-plus me-2"></i>Add New Department
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

            <!-- Departments Table -->
            <div class="card">
                <div class="card-body">
                    <?php if (empty($departments)): ?>
                        <!-- Initialize departments table if empty -->
                        <?php
                        try {
                            // Create departments table
                            $create_table_sql = "CREATE TABLE IF NOT EXISTS departments (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                department_key VARCHAR(100) UNIQUE NOT NULL,
                                department_name VARCHAR(255) NOT NULL,
                                is_active BOOLEAN DEFAULT TRUE,
                                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                            $db->exec($create_table_sql);
                            
                            // Insert default departments
                            $default_departments = [
                                ['event_planning', 'Event Planning'],
                                ['media_marketing', 'Media & Marketing'],
                                ['competitions', 'Competitions'],
                                ['recruitment', 'Recruitment'],
                                ['technical', 'Technical Team'],
                                ['design', 'Design Team'],
                                ['content', 'Content Writing'],
                                ['logistics', 'Logistics'],
                                ['sponsorship', 'Sponsorship'],
                                ['general', 'General Members']
                            ];
                            
                            foreach ($default_departments as $dept) {
                                $sql = "INSERT IGNORE INTO departments (department_key, department_name) VALUES (?, ?)";
                                $stmt = $db->prepare($sql);
                                $stmt->execute($dept);
                            }
                            
                            // Reload departments
                            $stmt = $db->query("SELECT * FROM departments ORDER BY department_name");
                            $departments = $stmt->fetchAll();
                            
                            $success = "Default departments initialized successfully!";
                        } catch(PDOException $e) {
                            $error = "Failed to initialize departments: " . $e->getMessage();
                        }
                        ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo htmlspecialchars($success); ?>
                            </div>
                            <script>
                                setTimeout(function() {
                                    window.location.reload();
                                }, 2000);
                            </script>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if (!empty($departments)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Department Key</th>
                                        <th>Department Name</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($departments as $dept): 
                                        $is_protected = in_array($dept['department_key'], $protected_departments);
                                    ?>
                                        <tr>
                                            <td><?php echo $dept['id']; ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($dept['department_key']); ?>
                                                <?php if ($is_protected): ?>
                                                    <span class="badge bg-info ms-1">Default</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($dept['department_name']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $dept['is_active'] ? 'success' : 'secondary'; ?>">
                                                    <?php echo $dept['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <button class="btn btn-sm btn-outline-primary edit-dept" 
                                                            data-dept-id="<?php echo $dept['id']; ?>"
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editDepartmentModal">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if (!$is_protected): ?>
                                                        <a href="?delete=<?php echo $dept['id']; ?>" 
                                                           class="btn btn-sm btn-outline-danger"
                                                           onclick="return confirm('Are you sure you want to delete this department? This action cannot be undone.')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-outline-secondary" disabled title="Default department cannot be deleted">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-building fa-3x text-muted mb-3"></i>
                            <h4>No Departments Found</h4>
                            <p class="text-muted">Departments will be initialized automatically.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Department Modal -->
<div class="modal fade" id="addDepartmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="department_name" class="form-label">Department Name *</label>
                        <input type="text" class="form-control" id="department_name" name="department_name" 
                               placeholder="e.g., Social Media Team" required>
                        <small class="text-muted">Display name for the department</small>
                    </div>
                    <div class="mb-3">
                        <label for="department_key" class="form-label">Department Key *</label>
                        <input type="text" class="form-control" id="department_key" name="department_key" 
                               placeholder="e.g., social_media" required>
                        <small class="text-muted">Use lowercase letters, numbers and underscores only (e.g., social_media)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-accent" name="add_department">Add Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Department Modal -->
<div class="modal fade" id="editDepartmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body" id="editDepartmentForm">
                    <!-- Form will be loaded via AJAX -->
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p>Loading department data...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-accent" name="update_department">Update Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Mobile Floating Action Button -->
<div class="fab-container d-md-none">
    <button class="fab btn btn-warning rounded-circle shadow" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
        <i class="fas fa-plus"></i>
    </button>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle edit department clicks
    document.querySelectorAll('.edit-dept').forEach(button => {
        button.addEventListener('click', function() {
            const deptId = this.getAttribute('data-dept-id');
            loadDepartmentData(deptId);
        });
    });

    // Clear form when add modal is closed
    const addModal = document.getElementById('addDepartmentModal');
    if (addModal) {
        addModal.addEventListener('hidden.bs.modal', function () {
            this.querySelector('form').reset();
        });
    }

    // Auto-generate department key from name
    const deptNameInput = document.getElementById('department_name');
    const deptKeyInput = document.getElementById('department_key');
    
    if (deptNameInput && deptKeyInput) {
        deptNameInput.addEventListener('input', function() {
            if (!deptKeyInput.value) {
                const key = this.value
                    .toLowerCase()
                    .replace(/[^a-z0-9\s]/g, '') // Remove special chars
                    .replace(/\s+/g, '_'); // Replace spaces with underscores
                deptKeyInput.value = key;
            }
        });
    }
});

function loadDepartmentData(deptId) {
    const form = document.getElementById('editDepartmentForm');
    form.innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p>Loading department data...</p>
        </div>
    `;

    // Create a simple AJAX request
    const xhr = new XMLHttpRequest();
    xhr.open('GET', `get-department.php?id=${deptId}`, true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                const department = JSON.parse(xhr.responseText);
                if (department && !department.error) {
                    const protectedDepartments = <?php echo json_encode($protected_departments); ?>;
                    const isProtected = protectedDepartments.includes(department.department_key);
                    
                    form.innerHTML = `
                        <input type="hidden" name="id" value="${department.id}">
                        
                        <div class="mb-3">
                            <label for="edit_department_name" class="form-label">Department Name *</label>
                            <input type="text" class="form-control" id="edit_department_name" name="department_name" 
                                   value="${escapeHtml(department.department_name)}" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_department_key" class="form-label">Department Key</label>
                            <input type="text" class="form-control" id="edit_department_key" 
                                   value="${escapeHtml(department.department_key)}" 
                                   ${isProtected ? 'disabled' : ''}>
                            <small class="text-muted">${isProtected ? 'Default department key cannot be changed' : 'Department key cannot be changed after creation'}</small>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active" ${department.is_active ? 'checked' : ''}>
                            <label class="form-check-label" for="edit_is_active">Active</label>
                        </div>
                    `;
                } else {
                    form.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Failed to load department data. Please try again.
                        </div>
                    `;
                }
            } catch (e) {
                console.error('Error parsing JSON:', e);
                form.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error loading department data. Please refresh the page and try again.
                    </div>
                `;
            }
        } else {
            form.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Error loading department data. Please refresh the page and try again.
                </div>
            `;
        }
    };
    xhr.send();
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
.fab-container {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1030;
}

.fab {
    width: 60px;
    height: 60px;
    font-size: 20px;
}
</style>

<?php require_once '../includes/footer.php'; ?>