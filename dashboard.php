<?php
require_once '../includes/session.php';
$session = new SessionManager();
$session->requireLogin();
$session->requireRole('member');

$page_title = "Member Dashboard";
$hidePublicNavigation = true;
require_once '../includes/header.php';

$user = $session->getCurrentUser();
$db = Database::getInstance()->getConnection();

// Get member stats
$stats = [
    'events_attended' => 0,
    'duties_assigned' => 0
];

try {
    // Get events attended count
    $stmt = $db->prepare("SELECT COUNT(*) FROM event_registrations WHERE user_id = ? AND status = 'attended'");
    $stmt->execute([$user['id']]);
    $stats['events_attended'] = $stmt->fetchColumn();

    // Get duties assigned count
    $stmt = $db->prepare("SELECT COUNT(*) FROM duties WHERE assigned_to = ?");
    $stmt->execute([$user['id']]);
    $stats['duties_assigned'] = $stmt->fetchColumn();

} catch(PDOException $e) {
    error_log("Stats query error: " . $e->getMessage());
}

// Get user's assigned posts for role display
$user_posts = [];
try {
    $stmt = $db->prepare("SELECT post_name FROM user_posts WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $user_posts = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {
    error_log("User posts error: " . $e->getMessage());
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
                    <h4 class="mb-0">Member Dashboard</h4>
                    <small class="text-muted">Welcome, <?php echo $user['name']; ?></small>
                </div>
                <button class="btn btn-accent" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileSidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <div class="d-none d-md-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1>Welcome, <?php echo $user['name']; ?>!</h1>
                    <?php if(!empty($user_posts)): ?>
                        <div class="mt-1">
                            <?php foreach($user_posts as $post): ?>
                                <span class="badge bg-accent me-1"><?php echo ucfirst(str_replace('_', ' ', $post)); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <span class="badge bg-accent"><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></span>
            </div>

            <!-- Quick Stats -->
            <div class="row mb-5">
                <div class="col-md-6 mb-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5 class="card-title">Events Attended</h5>
                                    <h2 class="card-text"><?php echo $stats['events_attended']; ?></h2>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-calendar-check fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="card bg-accent text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5 class="card-title">Duties Assigned</h5>
                                    <h2 class="card-text"><?php echo $stats['duties_assigned']; ?></h2>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-tasks fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Upcoming Duties -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-tasks me-2"></i>Upcoming Duties
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $upcoming_duties = [];
                            try {
                                $stmt = $db->prepare("SELECT d.*, e.title as event_title 
                                                    FROM duties d 
                                                    LEFT JOIN events e ON d.event_id = e.id 
                                                    WHERE d.assigned_to = ? AND d.start_date >= NOW() 
                                                    ORDER BY d.start_date LIMIT 5");
                                $stmt->execute([$user['id']]);
                                $upcoming_duties = $stmt->fetchAll();
                            } catch(PDOException $e) {
                                error_log("Duties query error: " . $e->getMessage());
                            }
                            ?>

                            <?php if(empty($upcoming_duties)): ?>
                                <p class="text-muted">No upcoming duties assigned.</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach($upcoming_duties as $duty): ?>
                                        <div class="list-group-item px-0">
                                            <h6 class="mb-1"><?php echo $duty['title']; ?></h6>
                                            <small class="text-muted">
                                                <?php if($duty['event_title']): ?>
                                                    Event: <?php echo $duty['event_title']; ?><br>
                                                <?php endif; ?>
                                                Date: <?php echo date('M j, Y g:i A', strtotime($duty['start_date'])); ?>
                                            </small>
                                            <span class="badge bg-<?php 
                                                switch($duty['status']) {
                                                    case 'assigned': echo 'warning'; break;
                                                    case 'in_progress': echo 'info'; break;
                                                    case 'completed': echo 'success'; break;
                                                    default: echo 'secondary';
                                                }
                                            ?> float-end mt-1">
                                                <?php echo ucfirst(str_replace('_', ' ', $duty['status'])); ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="mt-3">
                                <a href="duties.php" class="btn btn-outline-primary btn-sm">View All Duties</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Notifications -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-bell me-2"></i>Recent Notifications
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $notifications = [];
                            try {
                                $stmt = $db->prepare("SELECT * FROM notifications 
                                                    WHERE user_id = ? 
                                                    ORDER BY created_at DESC LIMIT 5");
                                $stmt->execute([$user['id']]);
                                $notifications = $stmt->fetchAll();
                            } catch(PDOException $e) {
                                error_log("Notifications query error: " . $e->getMessage());
                            }
                            ?>

                            <?php if(empty($notifications)): ?>
                                <p class="text-muted">No new notifications.</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach($notifications as $notification): ?>
                                        <div class="list-group-item px-0 <?php echo !$notification['is_read'] ? 'bg-light' : ''; ?>">
                                            <h6 class="mb-1"><?php echo $notification['title']; ?></h6>
                                            <p class="mb-1 small"><?php echo $notification['message']; ?></p>
                                            <small class="text-muted">
                                                <?php echo date('M j, g:i A', strtotime($notification['created_at'])); ?>
                                            </small>
                                            <?php if(!$notification['is_read']): ?>
                                                <span class="badge bg-danger float-end mt-1">New</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="mt-3">
                                <a href="notifications.php" class="btn btn-outline-primary btn-sm">View All Notifications</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6 col-sm-3 mb-3">
                                    <a href="profile.php" class="btn btn-outline-primary w-100 py-3 h-100 d-flex flex-column justify-content-center">
                                        <i class="fas fa-user fa-2x mb-2"></i>
                                        <span>Update Profile</span>
                                    </a>
                                </div>
                                <div class="col-6 col-sm-3 mb-3">
                                    <a href="id-card.php" class="btn btn-outline-success w-100 py-3 h-100 d-flex flex-column justify-content-center">
                                        <i class="fas fa-id-card fa-2x mb-2"></i>
                                        <span>ID Card</span>
                                    </a>
                                </div>
                                <div class="col-6 col-sm-3 mb-3">
                                    <a href="events.php" class="btn btn-outline-info w-100 py-3 h-100 d-flex flex-column justify-content-center">
                                        <i class="fas fa-calendar fa-2x mb-2"></i>
                                        <span>View Events</span>
                                    </a>
                                </div>
                                <div class="col-6 col-sm-3 mb-3">
                                    <a href="duties.php" class="btn btn-outline-warning w-100 py-3 h-100 d-flex flex-column justify-content-center">
                                        <i class="fas fa-tasks fa-2x mb-2"></i>
                                        <span>My Duties</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Admin sidebar specific styles */
.admin-sidebar-toggle {
    border: none;
    background: var(--accent-color);
    color: white;
    border-radius: 6px;
    padding: 0.5rem 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Mobile responsive adjustments */
@media (max-width: 767.98px) {
    .container-fluid {
        padding-left: 10px;
        padding-right: 10px;
    }
    
    /* Adjust button sizes for mobile */
    .btn {
        min-height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
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