<?php
$page_title = "Event Details";
require_once '../includes/header.php';
require_once '../includes/database.php';

// --- Helper function for formatting (bold, italic, links) ---
function formatDescription($text) {
    // Escape HTML to prevent XSS
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    
    // Convert *bold* to <strong>bold</strong>
    $text = preg_replace('/\*([^\*]+)\*/', '<strong>$1</strong>', $text);
    
    // Convert _italic_ to <em>italic</em>
    $text = preg_replace('/_([^_]+)_/', '<em>$1</em>', $text);
    
    // Use placeholders to avoid double‑processing URLs
    $placeholders = [];
    
    // First, replace all http/https URLs with placeholders
    $text = preg_replace_callback(
        '/(https?:\/\/[^\s<>"{}|\\^`\[\]]+)/i',
        function($matches) use (&$placeholders) {
            $key = '%%URL_' . count($placeholders) . '%%';
            $placeholders[$key] = $matches[1];
            return $key;
        },
        $text
    );
    
    // Then replace all www. links (that are not already part of a URL)
    $text = preg_replace_callback(
        '/(www\.[^\s<>"{}|\\^`\[\]]+)/i',
        function($matches) use (&$placeholders) {
            $key = '%%URL_' . count($placeholders) . '%%';
            // Add protocol for the actual link
            $placeholders[$key] = 'https://' . $matches[1];
            return $key;
        },
        $text
    );
    
    // Now replace placeholders with actual anchor tags
    foreach ($placeholders as $key => $url) {
        $link = '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $url . '</a>';
        $text = str_replace($key, $link, $text);
    }
    
    // Convert newlines to <br>
    return nl2br($text);
}
// -------------------------------------

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
$organizer_profile_pic = '';

try {
    $stmt = $db->prepare("
        SELECT e.*, u.name as organizer_name, u.profile_picture as organizer_profile_picture
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

    // DEBUG: See raw description (optional – remove or comment out in production)
    // echo '<!-- RAW: ' . htmlspecialchars($event['description'] ?? '') . ' -->';

    // Set organizer profile picture path
    if ($event['organizer_profile_picture'] && file_exists('../uploads/profile-pictures/' . $event['organizer_profile_picture'])) {
        $organizer_profile_pic = '../uploads/profile-pictures/' . $event['organizer_profile_picture'];
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

    // --- Determine if registration is still open ---
    $registration_open = true;
    
    // 1. Deadline passed?
    if ($event['registration_deadline'] && strtotime($event['registration_deadline']) < time()) {
        $registration_open = false;
    }
    // 2. Max participants reached?
    if ($event['max_participants'] && $registered_count >= $event['max_participants']) {
        $registration_open = false;
    }
    // 3. Event already started?
    if (strtotime($event['start_date']) < time()) {
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
                    <?php
                    $image_path = "../uploads/event-images/" . $event['banner_image'];
                    $has_image = file_exists($image_path);
                    
                    // Get image dimensions for exact display
                    $image_info = null;
                    if ($has_image) {
                        $image_info = @getimagesize($image_path);
                    }
                    ?>
                    <div class="text-center" style="background-color: #f8f9fa; overflow: hidden;">
                        <?php if($has_image && $image_info): ?>
                            <img src="<?php echo $image_path; ?>" 
                                 class="img-fluid natural-image" 
                                 alt="<?php echo $event['title']; ?>"
                                 width="<?php echo $image_info[0]; ?>"
                                 height="<?php echo $image_info[1]; ?>"
                                 style="max-width: 100%; height: auto; display: block; margin: 0 auto;">
                        <?php else: ?>
                            <div class="bg-light d-flex align-items-center justify-content-center" 
                                 style="height: 300px;">
                                <i class="fas fa-calendar-alt fa-5x text-muted"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-light d-flex align-items-center justify-content-center" 
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
                        <?php echo formatDescription($event['description']); ?>
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
            <div class="sticky-top" style="top: 100px;">
                <!-- Registration Card -->
                <div class="card shadow-sm">
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
                                    <?php 
                                    if (strtotime($event['start_date']) < time()) {
                                        echo "This event has already started.";
                                    } elseif ($event['max_participants'] && $registered_count >= $event['max_participants']) {
                                        echo "This event has reached maximum capacity.";
                                    } else {
                                        echo "The registration deadline has passed.";
                                    }
                                    ?>
                                </p>
                            </div>

                        <?php else: ?>
                            <?php if(isset($_SESSION['user_id'])): ?>
                                <a href="event-register.php?id=<?php echo $event_id; ?>" 
                                   class="btn btn-accent btn-lg w-100">
                                    <i class="fas fa-user-plus me-2"></i>Register Now
                                </a>
                                <?php if($event['max_participants']): ?>
                                    <small class="text-muted d-block mt-2 text-center">
                                        <?php echo ($event['max_participants'] - $registered_count); ?> spots remaining
                                    </small>
                                <?php endif; ?>

                            <?php else: ?>
                                <div class="alert alert-info mb-4">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Not a member? You can still participate as a guest.
                                </div>

                                <div class="d-grid gap-3">
                                    <a href="event-register.php?id=<?php echo $event_id; ?>" 
                                       class="btn btn-accent btn-lg w-100">
                                        <i class="fas fa-user-plus me-2"></i>Register as Guest
                                    </a>
                                    <a href="event-unregister.php?id=<?php echo $event_id; ?>" 
                                       class="btn btn-outline-danger btn-lg w-100">
                                        <i class="fas fa-user-minus me-2"></i>Unregister as Guest
                                    </a>
                                    <div class="position-relative my-3">
                                        <hr>
                                        <span class="position-absolute top-50 start-50 translate-middle bg-white px-3 text-muted small">
                                            or
                                        </span>
                                    </div>
                                    <a href="login.php?redirect=event-details.php?id=<?php echo $event_id; ?>" 
                                       class="btn btn-outline-primary btn-lg w-100">
                                        <i class="fas fa-sign-in-alt me-2"></i>Login (Society Member)
                                    </a>
                                </div>

                                <?php if($event['max_participants']): ?>
                                    <small class="text-muted d-block mt-3 text-center">
                                        <?php echo ($event['max_participants'] - $registered_count); ?> spots remaining
                                    </small>
                                <?php endif; ?>
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

                <!-- Organizer Info Card -->
                <div class="card shadow-sm mt-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-user me-2"></i>Event Organizer
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle overflow-hidden me-3" 
                                 style="width: 50px; height: 50px;">
                                <?php if($organizer_profile_pic): ?>
                                    <img src="<?php echo $organizer_profile_pic; ?>" 
                                         style="width: 100%; height: 100%; object-fit: cover;" 
                                         alt="<?php echo $event['organizer_name'] ?? 'MES Society'; ?>">
                                <?php else: ?>
                                    <?php
                                    $initials = '';
                                    if (!empty($event['organizer_name'])) {
                                        $name_parts = explode(' ', $event['organizer_name']);
                                        foreach ($name_parts as $part) {
                                            $initials .= strtoupper(substr($part, 0, 1));
                                            if (strlen($initials) >= 2) break;
                                        }
                                    } else {
                                        $initials = 'MS';
                                    }
                                    ?>
                                    <div style="width: 100%; height: 100%; background: #FF6600; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1rem;">
                                        <?php echo $initials; ?>
                                    </div>
                                <?php endif; ?>
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
.natural-image {
    width: auto !important;
    max-width: 100% !important;
    height: auto !important;
    display: block;
    margin: 0 auto;
}
.text-center {
    text-align: center;
    padding: 0;
    margin: 0;
}
@media (max-width: 768px) {
    .natural-image {
        max-height: none !important;
    }
}
@media (min-width: 769px) and (max-width: 1024px) {
    .natural-image {
        max-height: none !important;
    }
}
@media (min-width: 1025px) {
    .natural-image {
        max-height: none !important;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>