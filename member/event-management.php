<?php
// member/event-management.php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['event_planner', 'department_head', 'super_admin']);

$page_title = "Event Management";
$hidePublicNavigation = true;

$user = $session->getCurrentUser();
$db = Database::getInstance()->getConnection();
$functions = new Functions();

// Handle event creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_event'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $event_type = $_POST['event_type'] ?? '';
    $category = trim($_POST['category'] ?? '');
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $venue = trim($_POST['venue'] ?? '');
    $max_participants = !empty($_POST['max_participants']) ? (int)$_POST['max_participants'] : null;
    $registration_deadline = !empty($_POST['registration_deadline']) ? $_POST['registration_deadline'] : null;
    
    if (!empty($title) && !empty($start_date)) {
        try {
            $stmt = $db->prepare("INSERT INTO events (title, description, event_type, category, start_date, end_date, venue, max_participants, registration_deadline, created_by, status) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')");
            $stmt->execute([$title, $description, $event_type, $category, $start_date, $end_date, $venue, $max_participants, $registration_deadline, $user['id']]);
            $event_id = $db->lastInsertId();
            
            $_SESSION['success_message'] = "Event draft created successfully!";
            header("Location: event-management.php?event_id=" . $event_id);
            exit;
        } catch(PDOException $e) {
            $_SESSION['error_message'] = "Error creating event: " . $e->getMessage();
        }
    }
}

// Handle event update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_event'])) {
    $event_id = $_POST['event_id'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $event_type = $_POST['event_type'] ?? '';
    $category = trim($_POST['category'] ?? '');
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $venue = trim($_POST['venue'] ?? '');
    $max_participants = !empty($_POST['max_participants']) ? (int)$_POST['max_participants'] : null;
    $registration_deadline = !empty($_POST['registration_deadline']) ? $_POST['registration_deadline'] : null;
    
    try {
        $stmt = $db->prepare("UPDATE events SET title = ?, description = ?, event_type = ?, category = ?, start_date = ?, end_date = ?, venue = ?, max_participants = ?, registration_deadline = ? WHERE id = ? AND created_by = ?");
        $stmt->execute([$title, $description, $event_type, $category, $start_date, $end_date, $venue, $max_participants, $registration_deadline, $event_id, $user['id']]);
        
        $_SESSION['success_message'] = "Event updated successfully!";
        header("Location: event-management.php?event_id=" . $event_id);
        exit;
    } catch(PDOException $e) {
        $_SESSION['error_message'] = "Error updating event: " . $e->getMessage();
    }
}

// Handle event submission for approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_for_approval'])) {
    $event_id = $_POST['event_id'] ?? '';
    
    try {
        $stmt = $db->prepare("UPDATE events SET status = 'pending' WHERE id = ? AND created_by = ?");
        $stmt->execute([$event_id, $user['id']]);
        
        $_SESSION['success_message'] = "Event submitted for approval!";
        header("Location: event-management.php");
        exit;
    } catch(PDOException $e) {
        $_SESSION['error_message'] = "Error submitting event: " . $e->getMessage();
    }
}

// Get events created by this user
$events = [];
try {
    $stmt = $db->prepare("SELECT * FROM events WHERE created_by = ? ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);
    $events = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Events query error: " . $e->getMessage());
}

// Get specific event for editing
$current_event = null;
if (isset($_GET['event_id'])) {
    try {
        $stmt = $db->prepare("SELECT * FROM events WHERE id = ? AND created_by = ?");
        $stmt->execute([$_GET['event_id'], $user['id']]);
        $current_event = $stmt->fetch();
    } catch(PDOException $e) {
        error_log("Event query error: " . $e->getMessage());
    }
}

// Include header after all processing and redirects
require_once __DIR__ . '/../includes/header.php';
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
                    <h4 class="mb-0">Event Management</h4>
                    <small class="text-muted">Create and manage events</small>
                </div>
                <button class="btn btn-accent" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileSidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <div class="d-none d-md-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1>Event Management</h1>
                    <p class="text-muted mb-0">Create event drafts and submit for approval</p>
                </div>
                <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#createEventModal">
                    <i class="fas fa-plus me-2"></i>Create Event
                </button>
            </div>

            <!-- Success/Error Messages -->
            <?php if(isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Events List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-calendar me-2"></i>My Events
                    </h5>
                </div>
                <div class="card-body">
                    <?php if(empty($events)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-plus fa-3x text-muted mb-3"></i>
                            <h5>No Events Created</h5>
                            <p class="text-muted">Create your first event to get started.</p>
                            <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#createEventModal">
                                <i class="fas fa-plus me-2"></i>Create First Event
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Type</th>
                                        <th>Date</th>
                                        <th>Venue</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($events as $event): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($event['title']); ?></strong>
                                                <?php if($event['description']): ?>
                                                    <br><small class="text-muted"><?php echo $functions->truncateText($event['description'], 50); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo ucfirst($event['event_type']); ?></span>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php echo $functions->formatDateTime($event['start_date']); ?>
                                                    <?php if($event['end_date']): ?>
                                                        <br>to <?php echo $functions->formatDateTime($event['end_date']); ?>
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                            <td><?php echo $event['venue'] ?: 'N/A'; ?></td>
                                            <td>
                                                <?php echo $functions->getEventStatusBadge($event['status']); ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="event-management.php?event_id=<?php echo $event['id']; ?>" 
                                                       class="btn btn-outline-primary" 
                                                       data-bs-toggle="tooltip" 
                                                       title="Edit Event">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if($event['status'] === 'draft'): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                            <button type="submit" 
                                                                    name="submit_for_approval" 
                                                                    class="btn btn-outline-success"
                                                                    data-bs-toggle="tooltip"
                                                                    title="Submit for Approval"
                                                                    onclick="return confirm('Submit this event for approval?')">
                                                                <i class="fas fa-paper-plane"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <a href="../public/event-details.php?id=<?php echo $event['id']; ?>" 
                                                       class="btn btn-outline-info"
                                                       data-bs-toggle="tooltip"
                                                       title="View Public Page"
                                                       target="_blank">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Event Form (for editing) -->
            <?php if($current_event): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-edit me-2"></i>Edit Event: <?php echo htmlspecialchars($current_event['title']); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="event_id" value="<?php echo $current_event['id']; ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Event Title *</label>
                                        <input type="text" class="form-control" id="title" name="title" 
                                               value="<?php echo htmlspecialchars($current_event['title']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="event_type" class="form-label">Event Type *</label>
                                        <select class="form-select" id="event_type" name="event_type" required>
                                            <option value="seminar" <?php echo $current_event['event_type'] === 'seminar' ? 'selected' : ''; ?>>Seminar</option>
                                            <option value="competition" <?php echo $current_event['event_type'] === 'competition' ? 'selected' : ''; ?>>Competition</option>
                                            <option value="workshop" <?php echo $current_event['event_type'] === 'workshop' ? 'selected' : ''; ?>>Workshop</option>
                                            <option value="social" <?php echo $current_event['event_type'] === 'social' ? 'selected' : ''; ?>>Social</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Event Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($current_event['description']); ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="start_date" class="form-label">Start Date & Time *</label>
                                        <input type="datetime-local" class="form-control" id="start_date" name="start_date" 
                                               value="<?php echo date('Y-m-d\TH:i', strtotime($current_event['start_date'])); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="end_date" class="form-label">End Date & Time</label>
                                        <input type="datetime-local" class="form-control" id="end_date" name="end_date" 
                                               value="<?php echo $current_event['end_date'] ? date('Y-m-d\TH:i', strtotime($current_event['end_date'])) : ''; ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="venue" class="form-label">Venue</label>
                                        <input type="text" class="form-control" id="venue" name="venue" 
                                               value="<?php echo htmlspecialchars($current_event['venue']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="category" class="form-label">Category</label>
                                        <input type="text" class="form-control" id="category" name="category" 
                                               value="<?php echo htmlspecialchars($current_event['category']); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="max_participants" class="form-label">Maximum Participants</label>
                                        <input type="number" class="form-control" id="max_participants" name="max_participants" 
                                               value="<?php echo $current_event['max_participants']; ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="registration_deadline" class="form-label">Registration Deadline</label>
                                        <input type="datetime-local" class="form-control" id="registration_deadline" name="registration_deadline" 
                                               value="<?php echo $current_event['registration_deadline'] ? date('Y-m-d\TH:i', strtotime($current_event['registration_deadline'])) : ''; ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" name="update_event" class="btn btn-accent">Update Event</button>
                                <?php if($current_event['status'] === 'draft'): ?>
                                    <button type="submit" name="submit_for_approval" class="btn btn-success" 
                                            onclick="return confirm('Submit this event for approval?')">
                                        <i class="fas fa-paper-plane me-2"></i>Submit for Approval
                                    </button>
                                <?php endif; ?>
                                <a href="event-management.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Create Event Modal -->
<div class="modal fade" id="createEventModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="new_title" class="form-label">Event Title *</label>
                                <input type="text" class="form-control" id="new_title" name="title" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="new_event_type" class="form-label">Event Type *</label>
                                <select class="form-select" id="new_event_type" name="event_type" required>
                                    <option value="">Select Type</option>
                                    <option value="seminar">Seminar</option>
                                    <option value="competition">Competition</option>
                                    <option value="workshop">Workshop</option>
                                    <option value="social">Social</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="new_description" class="form-label">Event Description</label>
                        <textarea class="form-control" id="new_description" name="description" rows="3"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="new_start_date" class="form-label">Start Date & Time *</label>
                                <input type="datetime-local" class="form-control" id="new_start_date" name="start_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="new_end_date" class="form-label">End Date & Time</label>
                                <input type="datetime-local" class="form-control" id="new_end_date" name="end_date">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="new_venue" class="form-label">Venue</label>
                                <input type="text" class="form-control" id="new_venue" name="venue">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="new_category" class="form-label">Category</label>
                                <input type="text" class="form-control" id="new_category" name="category">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="new_max_participants" class="form-label">Maximum Participants</label>
                                <input type="number" class="form-control" id="new_max_participants" name="max_participants" min="1">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="new_registration_deadline" class="form-label">Registration Deadline</label>
                                <input type="datetime-local" class="form-control" id="new_registration_deadline" name="registration_deadline">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_event" class="btn btn-accent">Create Event Draft</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Set minimum datetime for date inputs to current time
    const now = new Date();
    const localDateTime = now.toISOString().slice(0, 16);
    
    const startDateInput = document.getElementById('new_start_date');
    const endDateInput = document.getElementById('new_end_date');
    const regDeadlineInput = document.getElementById('new_registration_deadline');
    
    if (startDateInput) startDateInput.min = localDateTime;
    if (endDateInput) endDateInput.min = localDateTime;
    if (regDeadlineInput) regDeadlineInput.min = localDateTime;
});
</script>

<style>
.table th {
    border-top: none;
    font-weight: 600;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
}

.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>