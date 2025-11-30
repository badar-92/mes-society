<?php
require_once '../includes/session.php';
$session = new SessionManager();
$session->requireLogin();
$session->requireRole('member');

$page_title = "Notifications";
$hidePublicNavigation = true;
require_once '../includes/header.php';

$user = $session->getCurrentUser();
$db = Database::getInstance()->getConnection();

// Get user notifications
$notifications = [];
try {
    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);
    $notifications = $stmt->fetchAll();
    
    // Mark all as read
    $update_stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $update_stmt->execute([$user['id']]);
} catch(PDOException $e) {
    error_log("Notifications query error: " . $e->getMessage());
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
                    <h4 class="mb-0">Notifications</h4>
                    <small class="text-muted">Stay updated with society activities</small>
                </div>
                <button class="btn btn-accent" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileSidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <div class="d-none d-md-flex justify-content-between align-items-center mb-4">
                <h1>Notifications</h1>
                <div>
                    <span class="badge bg-accent"><?php echo count($notifications); ?> Total</span>
                    <?php
                    $unread_count = count(array_filter($notifications, function($notification) { return !$notification['is_read']; }));
                    if ($unread_count > 0): ?>
                        <span class="badge bg-warning ms-2"><?php echo $unread_count; ?> New</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Notifications List -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-bell me-2"></i>All Notifications
                    </h5>
                    <?php if (!empty($notifications)): ?>
                        <button class="btn btn-outline-danger btn-sm" id="clearAllNotifications">
                            <i class="fas fa-trash me-1"></i>Clear All
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($notifications)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                            <h4>No Notifications</h4>
                            <p class="text-muted">You don't have any notifications at the moment.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach($notifications as $notification): ?>
                                <div class="list-group-item <?php echo !$notification['is_read'] ? 'bg-light' : ''; ?>">
                                    <div class="d-flex w-100 justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <?php if (!$notification['is_read']): ?>
                                                    <span class="badge bg-warning me-2">New</span>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($notification['title']); ?>
                                            </h6>
                                            <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                            </small>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                    type="button" 
                                                    data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="#" 
                                                       onclick="markAsRead(<?php echo $notification['id']; ?>)">
                                                        <i class="fas fa-check me-2"></i>Mark as Read
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item text-danger" href="#" 
                                                       onclick="deleteNotification(<?php echo $notification['id']; ?>)">
                                                        <i class="fas fa-trash me-2"></i>Delete
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($notification['action_url'])): ?>
                                        <div class="mt-2">
                                            <a href="<?php echo htmlspecialchars($notification['action_url']); ?>" 
                                               class="btn btn-accent btn-sm">
                                                <i class="fas fa-external-link-alt me-1"></i>
                                                Take Action
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Notification Types Summary -->
            <div class="row mt-4">
                <div class="col-md-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <i class="fas fa-calendar fa-2x mb-2"></i>
                            <h5>Event Updates</h5>
                            <h3>
                                <?php echo count(array_filter($notifications, function($n) { 
                                    return strpos(strtolower($n['title']), 'event') !== false; 
                                })); ?>
                            </h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <i class="fas fa-tasks fa-2x mb-2"></i>
                            <h5>Duty Alerts</h5>
                            <h3>
                                <?php echo count(array_filter($notifications, function($n) { 
                                    return strpos(strtolower($n['title']), 'duty') !== false || 
                                           strpos(strtolower($n['title']), 'task') !== false; 
                                })); ?>
                            </h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <i class="fas fa-bullhorn fa-2x mb-2"></i>
                            <h5>Announcements</h5>
                            <h3>
                                <?php echo count(array_filter($notifications, function($n) { 
                                    return strpos(strtolower($n['title']), 'announcement') !== false || 
                                           strpos(strtolower($n['title']), 'news') !== false; 
                                })); ?>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function markAsRead(notificationId) {
    fetch('../api/notifications.php?action=mark_read&id=' + notificationId)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error marking notification as read.');
    });
}

function deleteNotification(notificationId) {
    if (confirm('Are you sure you want to delete this notification?')) {
        fetch('../api/notifications.php?action=delete&id=' + notificationId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting notification.');
        });
    }
}

document.getElementById('clearAllNotifications')?.addEventListener('click', function() {
    if (confirm('Are you sure you want to clear all notifications? This action cannot be undone.')) {
        fetch('../api/notifications.php?action=clear_all')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error clearing notifications.');
        });
    }
});
</script>

<style>
.list-group-item {
    border: none;
    border-bottom: 1px solid #e9ecef;
    transition: background-color 0.2s ease;
}

.list-group-item:hover {
    background-color: #f8f9fa !important;
}

.list-group-item:last-child {
    border-bottom: none;
}

.dropdown-toggle::after {
    display: none;
}

.card .card-body h3 {
    margin: 0;
    font-weight: bold;
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