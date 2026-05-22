<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/push_helper.php'; // NEW: push helper

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['super_admin', 'department_head']);

$db = Database::getInstance()->getConnection();

// Handle actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $dutyId = $_GET['id'] ?? null;
    
    switch($action) {
        case 'delete':
            if ($dutyId) {
                $stmt = $db->prepare("DELETE FROM duties WHERE id = ?");
                $stmt->execute([$dutyId]);
                $_SESSION['success'] = "Duty deleted successfully";
            }
            break;
            
        case 'complete':
            if ($dutyId) {
                $stmt = $db->prepare("UPDATE duties SET status = 'completed', completed_at = NOW() WHERE id = ?");
                $stmt->execute([$dutyId]);
                $_SESSION['success'] = "Duty marked as completed";
            }
            break;
            
        case 'reopen':
            if ($dutyId) {
                $stmt = $db->prepare("UPDATE duties SET status = 'assigned', completed_at = NULL WHERE id = ?");
                $stmt->execute([$dutyId]);
                $_SESSION['success'] = "Duty reopened successfully";
            }
            break;
    }
    
    header("Location: duties.php");
    exit();
}

// Handle duty assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_duty'])) {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $eventId = !empty($_POST['event_id']) ? $_POST['event_id'] : NULL;
    $assignedTo = $_POST['assigned_to'] ?? '';
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $priority = $_POST['priority'] ?? 'medium';
    
    // Validate required fields
    if (empty($title) || empty($assignedTo) || empty($startDate) || empty($endDate)) {
        $_SESSION['error'] = "Please fill all required fields";
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO duties (title, description, event_id, assigned_to, start_date, end_date, priority, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'assigned', ?)
            ");
            $stmt->execute([$title, $description, $eventId, $assignedTo, $startDate, $endDate, $priority, $_SESSION['user_id']]);
            
            $dutyId = $db->lastInsertId();
            
            // Create in-app notification
            $notificationMsg = "You have been assigned a new duty: " . $title;
            if ($eventId) {
                $eventStmt = $db->prepare("SELECT title FROM events WHERE id = ?");
                $eventStmt->execute([$eventId]);
                $event = $eventStmt->fetch();
                if ($event) {
                    $notificationMsg .= " for event: " . $event['title'];
                }
            }
            
            $notifStmt = $db->prepare("
                INSERT INTO notifications (user_id, title, message, type, related_id, related_type)
                VALUES (?, 'New Duty Assigned', ?, 'duty_assigned', ?, 'duty')
            ");
            $notifStmt->execute([$assignedTo, $notificationMsg, $dutyId]);
            
            // Send push notification (if user has enabled it)
            sendPushNotification($assignedTo, "New Duty Assigned", $notificationMsg, "/member/duties.php");
            
            $_SESSION['success'] = "Duty assigned successfully and notification sent";
        } catch(PDOException $e) {
            error_log("Duty assignment error: " . $e->getMessage());
            $_SESSION['error'] = "Failed to assign duty: " . $e->getMessage();
        }
    }
    
    header("Location: duties.php");
    exit();
}

// Get all duties
$duties = [];
try {
    $query = "SELECT d.*, u.name as assigned_to_name, e.title as event_title, creator.name as created_by_name 
              FROM duties d 
              LEFT JOIN users u ON d.assigned_to = u.id 
              LEFT JOIN events e ON d.event_id = e.id 
              LEFT JOIN users creator ON d.created_by = creator.id 
              ORDER BY d.created_at DESC";
    $stmt = $db->query($query);
    $duties = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Duties query error: " . $e->getMessage());
}

// Get ALL active members for assignment (no role restrictions)
$members = [];
try {
    $stmt = $db->query("SELECT id, name, email, role FROM users WHERE status = 'active' ORDER BY name");
    $members = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Members query error: " . $e->getMessage());
}

// Get upcoming events for duty assignment
$events = [];
try {
    $stmt = $db->query("SELECT id, title, start_date FROM events WHERE start_date >= NOW() ORDER BY start_date");
    $events = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Events query error: " . $e->getMessage());
}

// Get duty statistics
$dutyStats = [
    'total' => 0,
    'assigned' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'overdue' => 0
];

try {
    $stmt = $db->query("SELECT COUNT(*) FROM duties");
    $dutyStats['total'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM duties WHERE status = 'assigned'");
    $dutyStats['assigned'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM duties WHERE status = 'in_progress'");
    $dutyStats['in_progress'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM duties WHERE status = 'completed'");
    $dutyStats['completed'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM duties WHERE status != 'completed' AND end_date < NOW()");
    $dutyStats['overdue'] = $stmt->fetchColumn();
} catch(PDOException $e) {
    error_log("Duty stats error: " . $e->getMessage());
}

$page_title = "Manage Duties";
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
                    <h4 class="mb-0">Manage Duties</h4>
                    <small class="text-muted">Duty Management</small>
                </div>
                <button class="btn btn-accent" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileSidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <div class="d-none d-md-flex justify-content-between align-items-center mb-4">
                <h1>Manage Duties</h1>
                <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#assignDutyModal">
                    <i class="fas fa-plus me-2"></i>Assign New Duty
                </button>
            </div>

            <!-- Success Message -->
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Duty Stats -->
            <div class="row mb-4">
                <div class="col-md-2 mb-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h2 class="card-text"><?php echo $dutyStats['total']; ?></h2>
                            <h6 class="card-title">Total</h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 mb-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <h2 class="card-text"><?php echo $dutyStats['assigned']; ?></h2>
                            <h6 class="card-title">Assigned</h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 mb-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h2 class="card-text"><?php echo $dutyStats['in_progress']; ?></h2>
                            <h6 class="card-title">In Progress</h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 mb-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h2 class="card-text"><?php echo $dutyStats['completed']; ?></h2>
                            <h6 class="card-title">Completed</h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 mb-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body text-center">
                            <h2 class="card-text"><?php echo $dutyStats['overdue']; ?></h2>
                            <h6 class="card-title">Overdue</h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 mb-3">
                    <div class="card bg-secondary text-white">
                        <div class="card-body text-center">
                            <h2 class="card-text"><?php echo count($members); ?></h2>
                            <h6 class="card-title">Active Members</h6>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Duties Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-tasks me-2"></i>All Duties
                    </h5>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-filter me-1"></i>Filter
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="?filter=all">All Duties</a></li>
                            <li><a class="dropdown-item" href="?filter=assigned">Assigned</a></li>
                            <li><a class="dropdown-item" href="?filter=in_progress">In Progress</a></li>
                            <li><a class="dropdown-item" href="?filter=completed">Completed</a></li>
                            <li><a class="dropdown-item" href="?filter=overdue">Overdue</a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Duty Title</th>
                                    <th>Assigned To</th>
                                    <th>Event</th>
                                    <th>Timeline</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($duties)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            <i class="fas fa-tasks fa-3x mb-3"></i><br>
                                            No duties assigned yet
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($duties as $duty): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($duty['title']); ?></strong>
                                                <?php if($duty['description']): ?>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars(substr($duty['description'], 0, 50)); ?>...</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center me-2" 
                                                         style="width: 32px; height: 32px;">
                                                        <i class="fas fa-user text-white"></i>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo $duty['assigned_to_name']; ?></strong>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if($duty['event_title']): ?>
                                                    <span class="badge bg-info"><?php echo $duty['event_title']; ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">General Duty</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small>
                                                    <strong>Start:</strong> <?php echo date('M j, Y', strtotime($duty['start_date'])); ?><br>
                                                    <strong>End:</strong> <?php echo date('M j, Y', strtotime($duty['end_date'])); ?>
                                                    <?php if($duty['end_date'] < date('Y-m-d') && $duty['status'] !== 'completed'): ?>
                                                        <br><span class="badge bg-danger">Overdue</span>
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    switch($duty['priority']) {
                                                        case 'high': echo 'danger'; break;
                                                        case 'medium': echo 'warning'; break;
                                                        case 'low': echo 'success'; break;
                                                        default: echo 'secondary';
                                                    }
                                                ?>">
                                                    <?php echo ucfirst($duty['priority']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    switch($duty['status']) {
                                                        case 'assigned': echo 'warning'; break;
                                                        case 'in_progress': echo 'info'; break;
                                                        case 'completed': echo 'success'; break;
                                                        default: echo 'secondary';
                                                    }
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $duty['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                                                        Actions
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#viewDutyModal<?php echo $duty['id']; ?>">
                                                                <i class="fas fa-eye me-2"></i>View Details
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item" href="duties-edit.php?id=<?php echo $duty['id']; ?>">
                                                                <i class="fas fa-edit me-2"></i>Edit
                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <?php if($duty['status'] === 'completed'): ?>
                                                            <li>
                                                                <a class="dropdown-item text-warning" href="?action=reopen&id=<?php echo $duty['id']; ?>" 
                                                                   onclick="return confirm('Reopen this duty?')">
                                                                    <i class="fas fa-redo me-2"></i>Reopen
                                                                </a>
                                                            </li>
                                                        <?php else: ?>
                                                            <li>
                                                                <a class="dropdown-item text-success" href="?action=complete&id=<?php echo $duty['id']; ?>" 
                                                                   onclick="return confirm('Mark this duty as completed?')">
                                                                    <i class="fas fa-check me-2"></i>Mark Complete
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="?action=delete&id=<?php echo $duty['id']; ?>" 
                                                               onclick="return confirm('Are you sure you want to delete this duty?')">
                                                                <i class="fas fa-trash me-2"></i>Delete
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- View Duty Modal -->
                                        <div class="modal fade" id="viewDutyModal<?php echo $duty['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Duty Details</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <h6>Basic Information</h6>
                                                                <table class="table table-sm table-borderless">
                                                                    <tr><th>Title:</th><td><?php echo $duty['title']; ?></td></tr>
                                                                    <tr><th>Assigned To:</th><td><?php echo $duty['assigned_to_name']; ?></td></tr>
                                                                    <tr><th>Event:</th><td><?php echo $duty['event_title'] ?: 'General Duty'; ?></td></tr>
                                                                    <tr><th>Priority:</th><td>
                                                                        <span class="badge bg-<?php 
                                                                            switch($duty['priority']) {
                                                                                case 'high': echo 'danger'; break;
                                                                                case 'medium': echo 'warning'; break;
                                                                                case 'low': echo 'success'; break;
                                                                                default: echo 'secondary';
                                                                            }
                                                                        ?>">
                                                                            <?php echo ucfirst($duty['priority']); ?>
                                                                        </span>
                                                                    </td></tr>
                                                                    <tr><th>Status:</th><td>
                                                                        <span class="badge bg-<?php 
                                                                            switch($duty['status']) {
                                                                                case 'assigned': echo 'warning'; break;
                                                                                case 'in_progress': echo 'info'; break;
                                                                                case 'completed': echo 'success'; break;
                                                                                default: echo 'secondary';
                                                                            }
                                                                        ?>">
                                                                            <?php echo ucfirst(str_replace('_', ' ', $duty['status'])); ?>
                                                                        </span>
                                                                    </td></tr>
                                                                </table>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <h6>Timeline</h6>
                                                                <table class="table table-sm table-borderless">
                                                                    <tr><th>Start Date:</th><td><?php echo date('M j, Y g:i A', strtotime($duty['start_date'])); ?></td></tr>
                                                                    <tr><th>End Date:</th><td><?php echo date('M j, Y g:i A', strtotime($duty['end_date'])); ?></td></tr>
                                                                    <tr><th>Created:</th><td><?php echo date('M j, Y g:i A', strtotime($duty['created_at'])); ?></td></tr>
                                                                    <?php if($duty['completed_at']): ?>
                                                                        <tr><th>Completed:</th><td><?php echo date('M j, Y g:i A', strtotime($duty['completed_at'])); ?></td></tr>
                                                                    <?php endif; ?>
                                                                </table>
                                                            </div>
                                                        </div>
                                                        
                                                        <h6 class="mt-3">Description</h6>
                                                        <p><?php echo nl2br(htmlspecialchars($duty['description'] ?: 'No description provided')); ?></p>
                                                        
                                                        <h6 class="mt-3">Assigned By</h6>
                                                        <p><?php echo $duty['created_by_name']; ?> on <?php echo date('M j, Y', strtotime($duty['created_at'])); ?></p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <a href="duties-edit.php?id=<?php echo $duty['id']; ?>" class="btn btn-primary">
                                                            <i class="fas fa-edit me-2"></i>Edit Duty
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Assign Duty Modal -->
<div class="modal fade" id="assignDutyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title">Assign New Duty</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="title" class="form-label">Duty Title *</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="assigned_to" class="form-label">Assign To *</label>
                                <select class="form-select" id="assigned_to" name="assigned_to" required>
                                    <option value="">Select Member</option>
                                    <?php foreach($members as $member): ?>
                                        <option value="<?php echo $member['id']; ?>"><?php echo htmlspecialchars($member['name']); ?> (<?php echo $member['email']; ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Describe the duty responsibilities..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="event_id" class="form-label">Related Event (Optional)</label>
                                <select class="form-select" id="event_id" name="event_id">
                                    <option value="">Select Event</option>
                                    <?php foreach($events as $event): ?>
                                        <option value="<?php echo $event['id']; ?>"><?php echo htmlspecialchars($event['title']); ?> (<?php echo date('M j, Y', strtotime($event['start_date'])); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="priority" class="form-label">Priority *</label>
                                <select class="form-select" id="priority" name="priority" required>
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Start Date *</label>
                                <input type="datetime-local" class="form-control" id="start_date" name="start_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="end_date" class="form-label">End Date *</label>
                                <input type="datetime-local" class="form-control" id="end_date" name="end_date" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="assign_duty" class="btn btn-accent">Assign Duty</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Mobile Floating Action Button -->
<div class="fab-container d-md-none">
    <button class="fab btn btn-accent rounded-circle" data-bs-toggle="modal" data-bs-target="#assignDutyModal">
        <i class="fas fa-plus"></i>
    </button>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set default dates for duty assignment
    const now = new Date();
    const startDate = now.toISOString().slice(0, 16);
    const endDate = new Date(now.getTime() + 24 * 60 * 60 * 1000).toISOString().slice(0, 16);
    
    document.getElementById('start_date').value = startDate;
    document.getElementById('end_date').value = endDate;
});
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