<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['super_admin', 'department_head', 'event_planner']);

$db = Database::getInstance()->getConnection();

// Handle actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $eventId = $_GET['id'] ?? null;
    
    switch($action) {
        case 'publish':
            if ($eventId) {
                $stmt = $db->prepare("UPDATE events SET status = 'published' WHERE id = ?");
                $stmt->execute([$eventId]);
                $_SESSION['success'] = "Event published successfully";
            }
            break;
            
        case 'unpublish':
            if ($eventId) {
                $stmt = $db->prepare("UPDATE events SET status = 'draft' WHERE id = ?");
                $stmt->execute([$eventId]);
                $_SESSION['success'] = "Event unpublished successfully";
            }
            break;
            
        case 'delete':
            if ($eventId) {
                try {
                    $db->beginTransaction();
                    
                    // First delete related registrations
                    $stmt = $db->prepare("DELETE FROM event_registrations WHERE event_id = ?");
                    $stmt->execute([$eventId]);
                    
                    // Then delete the event
                    $stmt = $db->prepare("DELETE FROM events WHERE id = ?");
                    $stmt->execute([$eventId]);
                    
                    $db->commit();
                    $_SESSION['success'] = "Event deleted successfully";
                } catch(PDOException $e) {
                    $db->rollBack();
                    error_log("Event deletion error: " . $e->getMessage());
                    $_SESSION['error'] = "Failed to delete event. Please try again.";
                }
            }
            break;
            
        case 'duplicate':
            if ($eventId) {
                // Get original event
                $stmt = $db->prepare("SELECT * FROM events WHERE id = ?");
                $stmt->execute([$eventId]);
                $event = $stmt->fetch();
                
                if ($event) {
                    // Insert duplicate
                    $insertStmt = $db->prepare("
                        INSERT INTO events (title, description, event_type, category, start_date, end_date, venue, max_participants, banner_image, status, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?)
                    ");
                    $insertStmt->execute([
                        $event['title'] . ' (Copy)',
                        $event['description'],
                        $event['event_type'],
                        $event['category'],
                        $event['start_date'],
                        $event['end_date'],
                        $event['venue'],
                        $event['max_participants'],
                        $event['banner_image'],
                        $_SESSION['user_id']
                    ]);
                    $_SESSION['success'] = "Event duplicated successfully";
                }
            }
            break;
    }
    
    header("Location: events.php");
    exit();
}

// Get all events
$events = [];
try {
    $query = "SELECT e.*, u.name as created_by_name 
              FROM events e 
              LEFT JOIN users u ON e.created_by = u.id 
              ORDER BY e.created_at DESC";
    $stmt = $db->query($query);
    $events = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Events query error: " . $e->getMessage());
}

// Get event statistics
$eventStats = [
    'total' => 0,
    'published' => 0,
    'draft' => 0,
    'upcoming' => 0
];

try {
    $stmt = $db->query("SELECT COUNT(*) FROM events");
    $eventStats['total'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM events WHERE status = 'published'");
    $eventStats['published'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM events WHERE status = 'draft'");
    $eventStats['draft'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM events WHERE start_date >= NOW() AND status = 'published'");
    $eventStats['upcoming'] = $stmt->fetchColumn();
} catch(PDOException $e) {
    error_log("Event stats error: " . $e->getMessage());
}

$page_title = "Manage Events";
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
                    <h4 class="mb-0">Manage Events</h4>
                    <small class="text-muted">Event Management</small>
                </div>
                <button class="btn btn-accent" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileSidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <div class="d-none d-md-flex justify-content-between align-items-center mb-4">
                <h1>Manage Events</h1>
                <a href="events-create.php" class="btn btn-accent">
                    <i class="fas fa-plus me-2"></i>Create New Event
                </a>
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

            <!-- Event Stats -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h2 class="card-text"><?php echo $eventStats['total']; ?></h2>
                            <h5 class="card-title">Total Events</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h2 class="card-text"><?php echo $eventStats['published']; ?></h2>
                            <h5 class="card-title">Published</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <h2 class="card-text"><?php echo $eventStats['draft']; ?></h2>
                            <h5 class="card-title">Drafts</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h2 class="card-text"><?php echo $eventStats['upcoming']; ?></h2>
                            <h5 class="card-title">Upcoming</h5>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Events Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-calendar me-2"></i>All Events
                    </h5>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-filter me-1"></i>Filter
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="?filter=all">All Events</a></li>
                            <li><a class="dropdown-item" href="?filter=published">Published</a></li>
                            <li><a class="dropdown-item" href="?filter=draft">Drafts</a></li>
                            <li><a class="dropdown-item" href="?filter=upcoming">Upcoming</a></li>
                            <li><a class="dropdown-item" href="?filter=past">Past Events</a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Type</th>
                                    <th>Date & Time</th>
                                    <th>Venue</th>
                                    <th>Participants</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($events)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            <i class="fas fa-calendar fa-3x mb-3"></i><br>
                                            No events found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($events as $event): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if($event['banner_image']): ?>
                                                        <img src="<?php echo SITE_URL . '/uploads/event-images/' . $event['banner_image']; ?>" 
                                                             alt="<?php echo $event['title']; ?>" 
                                                             class="rounded me-2" 
                                                             style="width: 40px; height: 40px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="rounded bg-secondary d-flex align-items-center justify-content-center me-2" 
                                                             style="width: 40px; height: 40px;">
                                                            <i class="fas fa-calendar text-white"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($event['title']); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo $event['category']; ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark">
                                                    <?php echo ucfirst($event['event_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small>
                                                    <strong><?php echo date('M j, Y', strtotime($event['start_date'])); ?></strong><br>
                                                    <?php echo date('g:i A', strtotime($event['start_date'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars($event['venue']); ?></small>
                                            </td>
                                            <td>
                                                <?php
                                                $registered = 0;
                                                try {
                                                    $stmt = $db->prepare("SELECT COUNT(*) FROM event_registrations WHERE event_id = ?");
                                                    $stmt->execute([$event['id']]);
                                                    $registered = $stmt->fetchColumn();
                                                } catch(PDOException $e) {
                                                    error_log("Registration count error: " . $e->getMessage());
                                                }
                                                ?>
                                                <small><?php echo $registered; ?>/<?php echo $event['max_participants'] ?? '∞'; ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    switch($event['status']) {
                                                        case 'published': echo 'success'; break;
                                                        case 'draft': echo 'warning'; break;
                                                        case 'cancelled': echo 'danger'; break;
                                                        default: echo 'secondary';
                                                    }
                                                ?>">
                                                    <?php echo ucfirst($event['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small><?php echo $event['created_by_name'] ?? 'System'; ?></small>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                                                        Actions
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item" href="../public/event-details.php?id=<?php echo $event['id']; ?>" target="_blank">
                                                                <i class="fas fa-eye me-2"></i>View Public
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item" href="events-edit.php?id=<?php echo $event['id']; ?>">
                                                                <i class="fas fa-edit me-2"></i>Edit
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item" href="event-registrations.php?id=<?php echo $event['id']; ?>">
                                                                <i class="fas fa-users me-2"></i>Registrations
                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <?php if($event['status'] === 'published'): ?>
                                                            <li>
                                                                <a class="dropdown-item text-warning" href="?action=unpublish&id=<?php echo $event['id']; ?>" 
                                                                   onclick="return confirm('Unpublish <?php echo $event['title']; ?>?')">
                                                                    <i class="fas fa-eye-slash me-2"></i>Unpublish
                                                                </a>
                                                            </li>
                                                        <?php else: ?>
                                                            <li>
                                                                <a class="dropdown-item text-success" href="?action=publish&id=<?php echo $event['id']; ?>" 
                                                                   onclick="return confirm('Publish <?php echo $event['title']; ?>?')">
                                                                    <i class="fas fa-eye me-2"></i>Publish
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                        <li>
                                                            <a class="dropdown-item" href="?action=duplicate&id=<?php echo $event['id']; ?>" 
                                                               onclick="return confirm('Duplicate <?php echo $event['title']; ?>?')">
                                                                <i class="fas fa-copy me-2"></i>Duplicate
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="?action=delete&id=<?php echo $event['id']; ?>" 
                                                               onclick="return confirm('Are you sure you want to delete <?php echo $event['title']; ?>? This action cannot be undone.')">
                                                                <i class="fas fa-trash me-2"></i>Delete
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
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

<!-- Mobile Floating Action Button -->
<div class="fab-container d-md-none">
    <a href="events-create.php" class="fab btn btn-accent rounded-circle">
        <i class="fas fa-plus"></i>
    </a>
</div>

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