<?php
require_once '../includes/session.php';
$session = new SessionManager();
$session->requireLogin();
$session->requireRole('member');

$page_title = "Events";
$hidePublicNavigation = true;
require_once '../includes/header.php';

$user = $session->getCurrentUser();
$db = Database::getInstance()->getConnection();

// Get events
$events = [];
try {
    $stmt = $db->prepare("SELECT e.*, 
                         (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id AND user_id = ?) as is_registered,
                         (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) as total_registrations
                         FROM events e 
                         WHERE e.status = 'published' AND e.start_date >= NOW() 
                         ORDER BY e.start_date ASC");
    $stmt->execute([$user['id']]);
    $events = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Events query error: " . $e->getMessage());
}

// Handle event registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_event'])) {
    $event_id = intval($_POST['event_id']);
    
    try {
        // Check if already registered
        $check_stmt = $db->prepare("SELECT COUNT(*) FROM event_registrations WHERE event_id = ? AND user_id = ?");
        $check_stmt->execute([$event_id, $user['id']]);
        $already_registered = $check_stmt->fetchColumn();
        
        if (!$already_registered) {
            $register_stmt = $db->prepare("INSERT INTO event_registrations (event_id, user_id, status, registered_at) VALUES (?, ?, 'registered', NOW())");
            $register_stmt->execute([$event_id, $user['id']]);
            
            if ($register_stmt->rowCount() > 0) {
                echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    Successfully registered for the event!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>';
                
                // Refresh events to update registration status
                $stmt->execute([$user['id']]);
                $events = $stmt->fetchAll();
            }
        } else {
            echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                You are already registered for this event.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
        }
    } catch(PDOException $e) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            Error registering for event: ' . $e->getMessage() . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
    }
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
                    <h4 class="mb-0">Events</h4>
                    <small class="text-muted">Browse and register for events</small>
                </div>
                <button class="btn btn-accent" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileSidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <div class="d-none d-md-flex justify-content-between align-items-center mb-4">
                <h1>Upcoming Events</h1>
                <span class="badge bg-accent"><?php echo count($events); ?> Events</span>
            </div>

            <!-- Events Grid -->
            <div class="row">
                <?php if (empty($events)): ?>
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <h4>No Upcoming Events</h4>
                                <p class="text-muted">Check back later for new events and activities.</p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach($events as $event): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 event-card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="card-title mb-0"><?php echo htmlspecialchars($event['title']); ?></h5>
                                </div>
                                <div class="card-body">
                                    <p class="card-text"><?php echo htmlspecialchars($event['description']); ?></p>
                                    
                                    <div class="event-details mb-3">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-calendar text-accent me-2"></i>
                                            <small><?php echo date('M j, Y', strtotime($event['start_date'])); ?></small>
                                        </div>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-clock text-accent me-2"></i>
                                            <small><?php echo date('g:i A', strtotime($event['start_date'])); ?></small>
                                        </div>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-map-marker-alt text-accent me-2"></i>
                                            <small><?php echo htmlspecialchars($event['venue']); ?></small>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-users text-accent me-2"></i>
                                            <small><?php echo $event['total_registrations']; ?> Registered</small>
                                        </div>
                                    </div>
                                    
                                    <?php if ($event['max_participants'] > 0): ?>
                                        <div class="progress mb-3" style="height: 8px;">
                                            <?php
                                            $percentage = min(100, ($event['total_registrations'] / $event['max_participants']) * 100);
                                            ?>
                                            <div class="progress-bar bg-accent" role="progressbar" 
                                                 style="width: <?php echo $percentage; ?>%"
                                                 aria-valuenow="<?php echo $percentage; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                            </div>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo $event['total_registrations']; ?> / <?php echo $event['max_participants']; ?> spots filled
                                        </small>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer">
                                    <?php if ($event['is_registered']): ?>
                                        <button class="btn btn-success w-100" disabled>
                                            <i class="fas fa-check me-2"></i>Registered
                                        </button>
                                    <?php else: ?>
                                        <form method="POST" class="d-inline w-100">
                                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                            <button type="submit" name="register_event" class="btn btn-accent w-100">
                                                <i class="fas fa-user-plus me-2"></i>Register Now
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.event-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.event-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.progress {
    background-color: #e9ecef;
    border-radius: 4px;
}

.progress-bar {
    border-radius: 4px;
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

<?php require_once '../includes/footer.php'; ?>