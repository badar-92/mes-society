<?php
// Get stats for sidebar if not already available
if (!isset($stats)) {
    $db = Database::getInstance()->getConnection();
    $user = (new SessionManager())->getCurrentUser();
    $stats = [
        'pending_applications' => 0,
        'duties_assigned' => 0
    ];
    
    try {
        // Fixed: Check duties for this user
        $stmt = $db->prepare("SELECT COUNT(*) FROM duties WHERE assigned_to = ? AND status != 'completed'");
        $stmt->execute([$user['id']]);
        $stats['duties_assigned'] = $stmt->fetchColumn();
    } catch(PDOException $e) {
        error_log("Member sidebar stats error: " . $e->getMessage());
    }
}

// Get user's assigned posts for role-based access
$user_posts = [];
try {
    $stmt = $db->prepare("SELECT post_name FROM user_posts WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $user_posts = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {
    error_log("User posts error: " . $e->getMessage());
}

// Check if user has specific roles
$is_media_head = in_array('media_head', $user_posts);
$is_event_planner = in_array('event_planner', $user_posts);
$is_competition_head = in_array('competition_head', $user_posts);
$is_hiring_head = in_array('hiring_head', $user_posts);
?>

<nav class="nav flex-column">
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
        </a>
    </li>
    
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>" href="profile.php">
            <i class="fas fa-user me-2"></i>My Profile
        </a>
    </li>
    
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'id-card.php' ? 'active' : ''; ?>" href="id-card.php">
            <i class="fas fa-id-card me-2"></i>Digital ID Card
        </a>
    </li>
    
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'events.php' ? 'active' : ''; ?>" href="events.php">
            <i class="fas fa-calendar me-2"></i>Events
        </a>
    </li>
    
    <!-- Role-based navigation items -->
    <?php if($is_media_head): ?>
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'media-gallery.php' ? 'active' : ''; ?>" href="media-gallery.php">
            <i class="fas fa-images me-2"></i>Media Gallery
        </a>
    </li>
    <?php endif; ?>
    
    <?php if($is_event_planner): ?>
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'event-management.php' ? 'active' : ''; ?>" href="event-management.php">
            <i class="fas fa-calendar-plus me-2"></i>Event Management
        </a>
    </li>
    <?php endif; ?>
    
    <?php if($is_competition_head): ?>
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'competition-management.php' ? 'active' : ''; ?>" href="competition-management.php">
            <i class="fas fa-trophy me-2"></i>Competition Management
        </a>
    </li>
    <?php endif; ?>
    
    <?php if($is_hiring_head): ?>
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'applications-review.php' ? 'active' : ''; ?>" href="applications-review.php">
            <i class="fas fa-file-alt me-2"></i>Review Applications
        </a>
    </li>
    <?php endif; ?>
    
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'duties.php' ? 'active' : ''; ?>" href="duties.php">
            <i class="fas fa-tasks me-2"></i>My Duties
            <?php if($stats['duties_assigned'] > 0): ?> 
                <span class="badge bg-info float-end"><?php echo $stats['duties_assigned']; ?></span>
            <?php endif; ?>
        </a>
    </li>
    
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : ''; ?>" href="notifications.php">
            <i class="fas fa-bell me-2"></i>Notifications
        </a>
    </li>
    
    <hr class="my-2">
    
    <li class="nav-item">
        <a class="nav-link text-danger" href="../includes/logout.php">
            <i class="fas fa-sign-out-alt me-2"></i>Logout
        </a>
    </li>
</nav>