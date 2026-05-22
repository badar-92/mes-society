<?php
// Get stats for sidebar if not already available
if (!isset($stats)) {
    $db = Database::getInstance()->getConnection();
    $stats = [
        'pending_applications' => 0
    ];
    
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM applications WHERE status = 'pending'");
        $stats['pending_applications'] = $stmt->fetchColumn();
    } catch(PDOException $e) {
        error_log("Sidebar stats error: " . $e->getMessage());
    }
}
?>

<nav class="nav flex-column">
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
        </a>
    </li>
    
    <!-- Team Management Link -->
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'team-management.php' ? 'active' : ''; ?>" href="team-management.php">
            <i class="fas fa-users me-2"></i>Team Management
        </a>
    </li>
    
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" href="users.php">
            <i class="fas fa-users me-2"></i>Manage Users
        </a>
    </li>
    
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'applications.php' ? 'active' : ''; ?>" href="applications.php">
            <i class="fas fa-file-alt me-2"></i>Applications
            <?php if($stats['pending_applications'] > 0): ?>
                <span class="badge bg-warning float-end"><?php echo $stats['pending_applications']; ?></span>
            <?php endif; ?>
        </a>
    </li>
    
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'events.php' ? 'active' : ''; ?>" href="events.php">
            <i class="fas fa-calendar me-2"></i>Events
        </a>
    </li>
    
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'competitions.php' ? 'active' : ''; ?>" href="competitions.php">
            <i class="fas fa-trophy me-2"></i>Competitions
        </a>
    </li>
    
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'gallery.php' ? 'active' : ''; ?>" href="gallery.php">
            <i class="fas fa-images me-2"></i>Gallery
        </a>
    </li>
    <li class="nav-item">
    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'certificates.php' ? 'active' : ''; ?>" href="certificates.php">
        <i class="fas fa-certificate me-2"></i>Certificates
    </a>
</li>
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'duties.php' ? 'active' : ''; ?>" href="duties.php">
            <i class="fas fa-tasks me-2"></i>Duties
        </a>
    </li>
<li class="nav-item">
    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'team-members-all.php' ? 'active' : ''; ?>" href="team-members-all.php">
        <i class="fas fa-users-cog me-2"></i>All Team Members
    </a>
</li>
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
            <i class="fas fa-chart-bar me-2"></i>Reports
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : ''; ?>" href="notifications.php">
            <i class="fas fa-bell me-2"></i>Notifications
            <?php if($unreadNotifications > 0): ?>
                <span class="badge bg-warning float-end"><?php echo $unreadNotifications; ?></span>
            <?php endif; ?>
        </a>
    </li>
    
    <!-- NEW: Send Push Notifications -->
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'send-push.php' ? 'active' : ''; ?>" href="send-push.php">
            <i class="fas fa-broadcast-tower me-2"></i>Send Push
        </a>
    </li>
    
    <!-- NEW: APK Manager (Upload & Manage App Versions) -->
    <li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'apk-manager.php' ? 'active' : ''; ?>" href="apk-manager.php">
            <i class="fa-brands fa-android"></i>APK Manager
        </a>
    </li>
    
<!-- Add this to the navigation menu -->
<li class="nav-item">
    <a class="nav-link" href="contact-messages.php">
        <i class="fas fa-envelope me-2"></i>
        Contact Messages
        <?php
        // Show unread count badge
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'unread'");
            $unread_count = $stmt->fetchColumn();
            if ($unread_count > 0) {
                echo '<span class="badge bg-danger float-end">' . $unread_count . '</span>';
            }
        } catch (PDOException $e) {
            // Ignore error for sidebar
        }
        ?>
    </a>
</li>

<li class="nav-item">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
            <i class="fas fa-cogs me-2"></i>Settings
        </a>
    </li>
    
    <hr class="my-2">
    
    <li class="nav-item">
        <a class="nav-link text-danger" href="../includes/logout.php">
            <i class="fas fa-sign-out-alt me-2"></i>Logout
        </a>
    </li>
</nav>