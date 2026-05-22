<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['super_admin', 'department_head', 'event_planner']);

$db = Database::getInstance()->getConnection();

// Get event ID from URL
$event_id = $_GET['id'] ?? 0;

if (!$event_id) {
    header("Location: events.php");
    exit();
}

// Fetch event details
$event = [];
$registrations = [];
$registered_count = 0;

try {
    $stmt = $db->prepare("
        SELECT e.*, u.name as created_by_name 
        FROM events e 
        LEFT JOIN users u ON e.created_by = u.id 
        WHERE e.id = ?
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        header("Location: events.php");
        exit();
    }

    // Get registrations
    $stmt = $db->prepare("
        SELECT er.*, u.name, u.email, u.department 
        FROM event_registrations er 
        LEFT JOIN users u ON er.user_id = u.id 
        WHERE er.event_id = ? 
        ORDER BY er.registered_at DESC
    ");
    $stmt->execute([$event_id]);
    $registrations = $stmt->fetchAll();

    $registered_count = count($registrations);

} catch(PDOException $e) {
    error_log("Event details error: " . $e->getMessage());
    header("Location: events.php");
    exit();
}

$page_title = "Event Details - " . $event['title'];
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

        <!-- Main Content -->
        <div class="col-md-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Event Details</h1>
                <div>
                    <a href="events-edit.php?id=<?php echo $event_id; ?>" class="btn btn-warning me-2">
                        <i class="fas fa-edit me-2"></i>Edit Event
                    </a>
                    <a href="events.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Events
                    </a>
                </div>
            </div>

            <!-- Event Overview -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Event Information</h5>
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
                        </div>
                        <div class="card-body">
                            <?php if($event['banner_image']): ?>
                                <img src="../uploads/event-images/<?php echo $event['banner_image']; ?>" 
                                     class="img-fluid rounded mb-4" alt="<?php echo $event['title']; ?>"
                                     style="max-height: 300px; width: 100%; object-fit: cover;">
                            <?php endif; ?>

                            <h3 class="mb-3"><?php echo $event['title']; ?></h3>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <th width="40%">Event Type:</th>
                                            <td><?php echo ucfirst($event['event_type']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Category:</th>
                                            <td><?php echo $event['category'] ?: 'Not specified'; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Start Date:</th>
                                            <td><?php echo date('F j, Y g:i A', strtotime($event['start_date'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th>End Date:</th>
                                            <td><?php echo $event['end_date'] ? date('F j, Y g:i A', strtotime($event['end_date'])) : 'Not specified'; ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <th width="40%">Venue:</th>
                                            <td><?php echo $event['venue']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Max Participants:</th>
                                            <td><?php echo $event['max_participants'] ?: 'No limit'; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Registration Deadline:</th>
                                            <td><?php echo $event['registration_deadline'] ? date('F j, Y g:i A', strtotime($event['registration_deadline'])) : 'Not specified'; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Created By:</th>
                                            <td><?php echo $event['created_by_name']; ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <h5>Description</h5>
                            <div class="border rounded p-3 bg-light">
                                <?php echo nl2br(htmlspecialchars($event['description'] ?: 'No description provided.')); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Registration Stats</h6>
                        </div>
                        <div class="card-body text-center">
                            <h1 class="display-4 text-primary"><?php echo $registered_count; ?></h1>
                            <p class="text-muted">Total Registrations</p>
                            <?php if($event['max_participants']): ?>
                                <div class="progress mb-3">
                                    <div class="progress-bar bg-success" 
                                         style="width: <?php echo min(100, ($registered_count / $event['max_participants']) * 100); ?>%">
                                    </div>
                                </div>
                                <small class="text-muted">
                                    <?php echo $event['max_participants'] - $registered_count; ?> spots remaining
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Quick Actions</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="event-registrations.php?id=<?php echo $event_id; ?>" 
                                   class="btn btn-outline-primary">
                                    <i class="fas fa-users me-2"></i>View Registrations
                                </a>
                                <a href="events-edit.php?id=<?php echo $event_id; ?>" 
                                   class="btn btn-outline-warning">
                                    <i class="fas fa-edit me-2"></i>Edit Event
                                </a>
                                <?php if($event['status'] === 'published'): ?>
                                    <a href="?action=unpublish&id=<?php echo $event_id; ?>" 
                                       class="btn btn-outline-secondary"
                                       onclick="return confirm('Unpublish this event?')">
                                        <i class="fas fa-eye-slash me-2"></i>Unpublish
                                    </a>
                                <?php else: ?>
                                    <a href="?action=publish&id=<?php echo $event_id; ?>" 
                                       class="btn btn-outline-success"
                                       onclick="return confirm('Publish this event?')">
                                        <i class="fas fa-eye me-2"></i>Publish
                                    </a>
                                <?php endif; ?>
                                <a href="../public/event-details.php?id=<?php echo $event_id; ?>" 
                                   target="_blank" class="btn btn-outline-info">
                                    <i class="fas fa-external-link-alt me-2"></i>View Public Page
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Registrations -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Recent Registrations</h5>
                    <a href="event-registrations.php?id=<?php echo $event_id; ?>" class="btn btn-sm btn-primary">
                        View All
                    </a>
                </div>
                <div class="card-body">
                    <?php if(empty($registrations)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No registrations yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Department</th>
                                        <th>Registered At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach(array_slice($registrations, 0, 5) as $registration): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($registration['name'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($registration['email'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($registration['department'] ?? ''); ?></td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($registration['registered_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>