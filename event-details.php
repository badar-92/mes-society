<?php
$page_title = "Event Details";
require_once '../includes/header.php';
require_once '../includes/database.php';

$db = Database::getInstance()->getConnection();

// Get event ID from URL
$event_id = $_GET['id'] ?? 0;

if (!$event_id) {
    header("Location: events.php");
    exit();
}

// Fetch event details
$event = [];
$registered_count = 0;
$is_registered = false;
$registration_open = true;

try {
    $stmt = $db->prepare("
        SELECT e.*, u.name as organizer_name 
        FROM events e 
        LEFT JOIN users u ON e.created_by = u.id 
        WHERE e.id = ? AND e.status = 'published'
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        header("Location: events.php");
        exit();
    }

    // Check if user is registered
    if (isset($_SESSION['user_id'])) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM event_registrations WHERE event_id = ? AND user_id = ?");
        $stmt->execute([$event_id, $_SESSION['user_id']]);
        $is_registered = $stmt->fetchColumn() > 0;
    }

    // Get registration count
    $stmt = $db->prepare("SELECT COUNT(*) FROM event_registrations WHERE event_id = ?");
    $stmt->execute([$event_id]);
    $registered_count = $stmt->fetchColumn();

    // Check if registration is still open
    if ($event['registration_deadline'] && strtotime($event['registration_deadline']) < time()) {
        $registration_open = false;
    }
    if ($event['max_participants'] && $registered_count >= $event['max_participants']) {
        $registration_open = false;
    }

} catch(PDOException $e) {
    error_log("Event details error: " . $e->getMessage());
    header("Location: events.php");
    exit();
}
?>

<div class="container py-5">
    <div class="row">
        <div class="col-12">
            <!-- Back Button -->
            <a href="events.php" class="btn btn-secondary mb-4">
                <i class="fas fa-arrow-left me-2"></i>Back to Events
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Event Details -->
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <?php if($event['banner_image']): ?>
                    <img src="../uploads/event-images/<?php echo $event['banner_image']; ?>" 
                         class="card-img-top" alt="<?php echo $event['title']; ?>" 
                         style="height: 400px; object-fit: cover;">
                <?php else: ?>
                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center" 
                         style="height: 300px;">
                        <i class="fas fa-calendar-alt fa-5x text-muted"></i>
                    </div>
                <?php endif; ?>
                
                <div class="card-body">
                    <h1 class="card-title h2 mb-3"><?php echo $event['title']; ?></h1>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-3">
                                <i class="fas fa-calendar text-accent me-3 fa-lg"></i>
                                <div>
                                    <strong>Date & Time</strong><br>
                                    <?php echo date('F j, Y', strtotime($event['start_date'])); ?><br>
                                    <?php echo date('g:i A', strtotime($event['start_date'])); ?>
                                    <?php if($event['end_date']): ?>
                                        to <?php echo date('g:i A', strtotime($event['end_date'])); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="d-flex align-items-center mb-3">
                                <i class="fas fa-map-marker-alt text-accent me-3 fa-lg"></i>
                                <div>
                                    <strong>Venue</strong><br>
                                    <?php echo $event['venue']; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-3">
                                <i class="fas fa-users text-accent me-3 fa-lg"></i>
                                <div>
                                    <strong>Participants</strong><br>
                                    <?php echo $registered_count; ?>
                                    <?php if($event['max_participants']): ?>
                                        / <?php echo $event['max_participants']; ?> registered
                                    <?php else: ?>
                                        registered
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="d-flex align-items-center mb-3">
                                <i class="fas fa-tag text-accent me-3 fa-lg"></i>
                                <div>
                                    <strong>Event Type</strong><br>
                                    <?php echo ucfirst($event['event_type']); ?>
                                    <?php if($event['category']): ?>
                                        - <?php echo $event['category']; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h4 class="mb-3">About this Event</h4>
                    <div class="event-description">
                        <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                    </div>

                    <?php if($event['registration_deadline']): ?>
                        <div class="alert alert-info mt-4">
                            <i class="fas fa-clock me-2"></i>
                            <strong>Registration Deadline:</strong> 
                            <?php echo date('F j, Y g:i A', strtotime($event['registration_deadline'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Registration Section -->
        <div class="col-lg-4">
            <div class="card shadow-sm sticky-top" style="top: 100px;">
                <div class="card-header bg-accent text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-ticket-alt me-2"></i>Event Registration
                    </h5>
                </div>
                <div class="card-body">
                    <?php if($is_registered): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>You are registered for this event!</strong>
                            <p class="mb-0 mt-2">We look forward to seeing you there.</p>
                        </div>
                        <a href="event-unregister.php?id=<?php echo $event_id; ?>" 
                           class="btn btn-outline-danger w-100"
                           onclick="return confirm('Are you sure you want to cancel your registration?')">
                            <i class="fas fa-times me-2"></i>Cancel Registration
                        </a>
                    <?php elseif(!$registration_open): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Registration Closed</strong>
                            <p class="mb-0 mt-2">
                                <?php if($event['max_participants'] && $registered_count >= $event['max_participants']): ?>
                                    This event has reached maximum capacity.
                                <?php else: ?>
                                    The registration deadline has passed.
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <?php if(isset($_SESSION['user_id'])): ?>
                            <a href="event-register.php?id=<?php echo $event_id; ?>" 
                               class="btn btn-accent btn-lg w-100">
                                <i class="fas fa-user-plus me-2"></i>Register Now
                            </a>
                            <small class="text-muted d-block mt-2 text-center">
                                <?php if($event['max_participants']): ?>
                                    <?php echo ($event['max_participants'] - $registered_count); ?> spots remaining
                                <?php endif; ?>
                            </small>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Please <a href="login.php" class="alert-link">login</a> to register for this event.
                            </div>
                            <a href="login.php" class="btn btn-primary w-100">Login to Register</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Event Share -->
                <div class="card-footer">
                    <h6 class="mb-3">Share this Event</h6>
                    <div class="d-flex justify-content-center gap-3">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(SITE_URL . '/public/event-details.php?id=' . $event_id); ?>" 
                           target="_blank" class="btn btn-outline-primary btn-sm">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode('Check out this event: ' . $event['title']); ?>&url=<?php echo urlencode(SITE_URL . '/public/event-details.php?id=' . $event_id); ?>" 
                           target="_blank" class="btn btn-outline-info btn-sm">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode(SITE_URL . '/public/event-details.php?id=' . $event_id); ?>" 
                           target="_blank" class="btn btn-outline-primary btn-sm">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="mailto:?subject=<?php echo urlencode('Event Invitation: ' . $event['title']); ?>&body=<?php echo urlencode('Check out this event: ' . SITE_URL . '/public/event-details.php?id=' . $event_id); ?>" 
                           class="btn btn-outline-danger btn-sm">
                            <i class="fas fa-envelope"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Organizer Info -->
            <div class="card shadow-sm mt-4">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-user me-2"></i>Event Organizer
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center me-3" 
                             style="width: 50px; height: 50px;">
                            <i class="fas fa-user text-white"></i>
                        </div>
                        <div>
                            <strong><?php echo $event['organizer_name'] ?? 'MES Society'; ?></strong><br>
                            <small class="text-muted">Mechanical Engineering Society</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.sticky-top {
    position: -webkit-sticky;
    position: sticky;
}

.event-description {
    line-height: 1.8;
    font-size: 1.1rem;
}

.card {
    border: none;
    border-radius: 15px;
}

.card-header {
    border-radius: 15px 15px 0 0 !important;
}
</style>

<?php require_once '../includes/footer.php'; ?>