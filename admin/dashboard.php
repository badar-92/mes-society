<?php
require_once '../includes/session.php';
$session = new SessionManager();
$session->requireLogin();
$session->requireRole('super_admin');

$page_title = "Admin Dashboard";
// Hide public navigation for admin area
$hidePublicNavigation = true;
require_once '../includes/header.php';

$user = $session->getCurrentUser();
$db = Database::getInstance()->getConnection();

// Get admin stats
$stats = [
    'total_members' => 0,
    'pending_applications' => 0,
    'upcoming_events' => 0,
    'total_events' => 0,
    'total_galleries' => 0,
    'active_competitions' => 0,
    'active_duties' => 0,
    'pending_notifications' => 0
];

try {
    // Total members
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE role != 'public' AND status = 'active'");
    $stats['total_members'] = $stmt->fetchColumn();

    // Pending applications
    $stmt = $db->query("SELECT COUNT(*) FROM applications WHERE status = 'pending'");
    $stats['pending_applications'] = $stmt->fetchColumn();

    // Upcoming events
    $stmt = $db->query("SELECT COUNT(*) FROM events WHERE start_date >= NOW() AND status = 'published'");
    $stats['upcoming_events'] = $stmt->fetchColumn();

    // Total events
    $stmt = $db->query("SELECT COUNT(*) FROM events");
    $stats['total_events'] = $stmt->fetchColumn();

    // Total galleries
    $stmt = $db->query("SELECT COUNT(*) FROM gallery_albums WHERE status = 'active'");
    $stats['total_galleries'] = $stmt->fetchColumn();

    // Active competitions
    $stmt = $db->query("SELECT COUNT(*) FROM competitions WHERE status IN ('published', 'ongoing')");
    $stats['active_competitions'] = $stmt->fetchColumn();

    // Active duties
    $stmt = $db->query("SELECT COUNT(*) FROM duties WHERE status != 'completed'");
    $stats['active_duties'] = $stmt->fetchColumn();

    // Pending notifications
    $stmt = $db->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0");
    $stats['pending_notifications'] = $stmt->fetchColumn();

} catch(PDOException $e) {
    error_log("Admin stats error: " . $e->getMessage());
}
?>

<div class="container-fluid">
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
                    <h4 class="mb-0">Admin Dashboard</h4>
                    <small class="text-muted">Super Admin</small>
                </div>
                <button class="btn btn-accent" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileSidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <div class="d-none d-md-flex justify-content-between align-items-center mb-4">
                <h1>Admin Dashboard</h1>
                <div>
                    <span class="badge bg-danger">Super Admin</span>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5 class="card-title">Total Members</h5>
                                    <h2 class="card-text"><?php echo $stats['total_members']; ?></h2>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-users fa-2x"></i>
                                </div>
                            </div>
                            <a href="users.php" class="text-white-50 text-decoration-none small">View All →</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-warning text-dark">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5 class="card-title">Pending Applications</h5>
                                    <h2 class="card-text"><?php echo $stats['pending_applications']; ?></h2>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-file-alt fa-2x"></i>
                                </div>
                            </div>
                            <a href="applications.php" class="text-dark text-decoration-none small">Review Now →</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5 class="card-title">Upcoming Events</h5>
                                    <h2 class="card-text"><?php echo $stats['upcoming_events']; ?></h2>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-calendar fa-2x"></i>
                                </div>
                            </div>
                            <a href="events.php" class="text-white-50 text-decoration-none small">Manage Events →</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5 class="card-title">Total Events</h5>
                                    <h2 class="card-text"><?php echo $stats['total_events']; ?></h2>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-calendar-alt fa-2x"></i>
                                </div>
                            </div>
                            <a href="events.php" class="text-white-50 text-decoration-none small">View All →</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6 col-sm-4 col-md-2 mb-3 text-center">
                                    <a href="events.php?action=create" class="btn btn-outline-primary w-100 py-3 h-100 d-flex flex-column justify-content-center">
                                        <i class="fas fa-plus fa-2x mb-2"></i>
                                        <span>Add Event</span>
                                    </a>
                                </div>
                                <div class="col-6 col-sm-4 col-md-2 mb-3 text-center">
                                    <a href="users.php?action=create" class="btn btn-outline-success w-100 py-3 h-100 d-flex flex-column justify-content-center">
                                        <i class="fas fa-user-plus fa-2x mb-2"></i>
                                        <span>Add User</span>
                                    </a>
                                </div>
                                <div class="col-6 col-sm-4 col-md-2 mb-3 text-center">
                                    <a href="gallery.php?action=create" class="btn btn-outline-info w-100 py-3 h-100 d-flex flex-column justify-content-center">
                                        <i class="fas fa-images fa-2x mb-2"></i>
                                        <span>Add Gallery</span>
                                    </a>
                                </div>
                                <div class="col-6 col-sm-4 col-md-2 mb-3 text-center">
                                    <a href="competitions.php?action=create" class="btn btn-outline-warning w-100 py-3 h-100 d-flex flex-column justify-content-center">
                                        <i class="fas fa-trophy fa-2x mb-2"></i>
                                        <span>Add Competition</span>
                                    </a>
                                </div>
                                <div class="col-6 col-sm-4 col-md-2 mb-3 text-center">
                                    <a href="reports.php" class="btn btn-outline-secondary w-100 py-3 h-100 d-flex flex-column justify-content-center">
                                        <i class="fas fa-chart-bar fa-2x mb-2"></i>
                                        <span>Reports</span>
                                    </a>
                                </div>
                                <div class="col-6 col-sm-4 col-md-2 mb-3 text-center">
                                    <a href="settings.php" class="btn btn-outline-dark w-100 py-3 h-100 d-flex flex-column justify-content-center">
                                        <i class="fas fa-cogs fa-2x mb-2"></i>
                                        <span>Settings</span>
                                    </a>
                                </div>
                                <!-- NEW: Enable Push Notifications Button -->
                                <div class="col-6 col-sm-4 col-md-2 mb-3 text-center">
                                    <button onclick="requestNotificationPermission()" class="btn btn-outline-danger w-100 py-3 h-100 d-flex flex-column justify-content-center">
                                        <i class="fas fa-bell fa-2x mb-2"></i>
                                        <span>Enable Notifications</span>
                                    </button>
                                </div>
                                <!-- END NEW BUTTON -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Recent Applications</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $recent_apps = [];
                            try {
                                $stmt = $db->query("SELECT a.*, u.name as applicant_name 
                                                  FROM applications a 
                                                  LEFT JOIN users u ON a.user_id = u.id 
                                                  ORDER BY a.applied_at DESC LIMIT 5");
                                $recent_apps = $stmt->fetchAll();
                            } catch(PDOException $e) {
                                error_log("Recent apps error: " . $e->getMessage());
                            }
                            ?>

                            <?php if(empty($recent_apps)): ?>
                                <p class="text-muted">No recent applications.</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach($recent_apps as $app): ?>
                                        <div class="list-group-item px-0">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1"><?php echo $app['applicant_name'] ?? 'New Applicant'; ?></h6>
                                                <small class="text-muted">
                                                    <?php echo date('M j', strtotime($app['applied_at'])); ?>
                                                </small>
                                            </div>
                                            <p class="mb-1 small text-muted">Applied for membership</p>
                                            <span class="badge bg-<?php 
                                                switch($app['status']) {
                                                    case 'pending': echo 'warning'; break;
                                                    case 'under_review': echo 'info'; break;
                                                    case 'selected': echo 'success'; break;
                                                    case 'rejected': echo 'danger'; break;
                                                    default: echo 'secondary';
                                                }
                                            ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $app['status'])); ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="mt-3">
                                <a href="applications.php" class="btn btn-outline-primary btn-sm">View All Applications</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">System Overview</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <div class="border rounded p-3">
                                        <i class="fas fa-images fa-2x text-info mb-2"></i>
                                        <h5><?php echo $stats['total_galleries']; ?></h5>
                                        <small class="text-muted">Photo Galleries</small>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="border rounded p-3">
                                        <i class="fas fa-trophy fa-2x text-warning mb-2"></i>
                                        <h5><?php echo $stats['active_competitions']; ?></h5>
                                        <small class="text-muted">Active Competitions</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded p-3">
                                        <i class="fas fa-tasks fa-2x text-success mb-2"></i>
                                        <h5><?php echo $stats['active_duties']; ?></h5>
                                        <small class="text-muted">Active Duties</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded p-3">
                                        <i class="fas fa-bell fa-2x text-danger mb-2"></i>
                                        <h5><?php echo $stats['pending_notifications']; ?></h5>
                                        <small class="text-muted">Pending Notifications</small>
                                    </div>
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