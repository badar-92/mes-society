<?php
require_once '../includes/session.php';
$session = new SessionManager();
$session->requireLogin();
$session->requireRole('member');

$page_title = "My Duties";
$hidePublicNavigation = true;
require_once '../includes/header.php';

$user = $session->getCurrentUser();
$db = Database::getInstance()->getConnection();

// Get duties assigned to user
$duties = [];
try {
    $stmt = $db->prepare("SELECT d.*, e.title as event_title 
                         FROM duties d 
                         LEFT JOIN events e ON d.event_id = e.id 
                         WHERE d.assigned_to = ? 
                         ORDER BY d.start_date ASC");
    $stmt->execute([$user['id']]);
    $duties = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Duties query error: " . $e->getMessage());
}

// Handle duty status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_duty_status'])) {
    $duty_id = intval($_POST['duty_id']);
    $status = $_POST['status'];
    
    try {
        $update_stmt = $db->prepare("UPDATE duties SET status = ?, updated_at = NOW() WHERE id = ? AND assigned_to = ?");
        $update_stmt->execute([$status, $duty_id, $user['id']]);
        
        if ($update_stmt->rowCount() > 0) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                Duty status updated successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
            
            // Refresh duties
            $stmt->execute([$user['id']]);
            $duties = $stmt->fetchAll();
        }
    } catch(PDOException $e) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            Error updating duty status: ' . $e->getMessage() . '
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
                    <h4 class="mb-0">My Duties</h4>
                    <small class="text-muted">Manage your assigned responsibilities</small>
                </div>
                <button class="btn btn-accent" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileSidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <div class="d-none d-md-flex justify-content-between align-items-center mb-4">
                <h1>My Duties & Responsibilities</h1>
                <span class="badge bg-accent"><?php echo count($duties); ?> Duties</span>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h5 class="card-title">Assigned</h5>
                            <h2 class="card-text">
                                <?php echo count(array_filter($duties, function($duty) { return $duty['status'] === 'assigned'; })); ?>
                            </h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-warning text-dark">
                        <div class="card-body text-center">
                            <h5 class="card-title">In Progress</h5>
                            <h2 class="card-text">
                                <?php echo count(array_filter($duties, function($duty) { return $duty['status'] === 'in_progress'; })); ?>
                            </h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h5 class="card-title">Completed</h5>
                            <h2 class="card-text">
                                <?php echo count(array_filter($duties, function($duty) { return $duty['status'] === 'completed'; })); ?>
                            </h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h5 class="card-title">Total</h5>
                            <h2 class="card-text"><?php echo count($duties); ?></h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Duties List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-tasks me-2"></i>Assigned Duties
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($duties)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                            <h4>No Duties Assigned</h4>
                            <p class="text-muted">You don't have any duties assigned at the moment.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Duty Title</th>
                                        <th>Event</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($duties as $duty): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($duty['title']); ?></strong>
                                                <?php if ($duty['description']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($duty['description']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($duty['event_title']): ?>
                                                    <?php echo htmlspecialchars($duty['event_title']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">General Duty</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?php echo date('M j, Y g:i A', strtotime($duty['start_date'])); ?></small>
                                            </td>
                                            <td>
                                                <small><?php echo date('M j, Y g:i A', strtotime($duty['end_date'])); ?></small>
                                            </td>
                                            <td>
                                                <?php
                                                $status_badge = [
                                                    'assigned' => 'badge bg-primary',
                                                    'in_progress' => 'badge bg-warning',
                                                    'completed' => 'badge bg-success',
                                                    'cancelled' => 'badge bg-danger'
                                                ];
                                                $status_class = $status_badge[$duty['status']] ?? 'badge bg-secondary';
                                                ?>
                                                <span class="<?php echo $status_class; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $duty['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($duty['status'] !== 'completed' && $duty['status'] !== 'cancelled'): ?>
                                                    <div class="btn-group">
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="duty_id" value="<?php echo $duty['id']; ?>">
                                                            <?php if ($duty['status'] === 'assigned'): ?>
                                                                <input type="hidden" name="status" value="in_progress">
                                                                <button type="submit" name="update_duty_status" class="btn btn-warning btn-sm">
                                                                    <i class="fas fa-play me-1"></i>Start
                                                                </button>
                                                            <?php elseif ($duty['status'] === 'in_progress'): ?>
                                                                <input type="hidden" name="status" value="completed">
                                                                <button type="submit" name="update_duty_status" class="btn btn-success btn-sm">
                                                                    <i class="fas fa-check me-1"></i>Complete
                                                                </button>
                                                            <?php endif; ?>
                                                        </form>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted small">No actions available</span>
                                                <?php endif; ?>
                                            </td>
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

<style>
.table th {
    border-top: none;
    font-weight: 600;
    color: #495057;
    background-color: #f8f9fa;
}

.btn-group .btn {
    margin-right: 5px;
}

.btn-group .btn:last-child {
    margin-right: 0;
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