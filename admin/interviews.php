<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['super_admin', 'department_head', 'hiring_head']);

$auth = new Auth();
$db = Database::getInstance()->getConnection();

// Handle interview actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $interviewId = $_GET['id'] ?? null;
    $applicationId = $_GET['app_id'] ?? null;
    
    switch($action) {
        case 'approve_after_interview':
            if ($applicationId) {
                // Generate temporary password
                $tempPassword = $auth->generatePassword();
                
                // Create member account with temporary password
                if ($auth->createMemberAccountWithPassword($applicationId, $tempPassword)) {
                    // UPDATE THIS: More robust interview cleanup
                    if ($interviewId) {
                        try {
                            // Option 1: Delete the interview record completely
                            $stmt = $db->prepare("DELETE FROM interviews WHERE id = ?");
                            $stmt->execute([$interviewId]);
                            
                            // Option 2: Or mark as completed (if you want to keep history)
                            // $stmt = $db->prepare("UPDATE interviews SET status = 'completed', outcome = 'approved' WHERE id = ?");
                            // $stmt->execute([$interviewId]);
                        } catch (PDOException $e) {
                            error_log("Error updating interview record: " . $e->getMessage());
                        }
                    }
                    
                    $_SESSION['success'] = "Application approved and member account created successfully. 
                    Temporary password: <strong>" . $tempPassword . "</strong>
                    <br><a href='generate-confirmation-letter.php?type=application&id=" . $applicationId . "' target='_blank' class='btn btn-success btn-sm mt-2' onclick='return generateLetter(this)'>
                    <i class='fas fa-download me-1'></i>Generate Welcome Letter</a>";
                } else {
                    $_SESSION['error'] = "Failed to create member account";
                }
            }
            break;
      case 'reject_after_interview':
    if ($applicationId) {
        $stmt = $db->prepare("UPDATE applications SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$applicationId]);
        
        // UPDATE THIS: Remove interview record for rejected candidates too
        if ($interviewId) {
            try {
                // Delete the interview record completely
                $stmt = $db->prepare("DELETE FROM interviews WHERE id = ?");
                $stmt->execute([$interviewId]);
            } catch (PDOException $e) {
                error_log("Error deleting interview record: " . $e->getMessage());
            }
        }
        
        $_SESSION['success'] = "Application rejected after interview";
    }
    break;
            
case 'cancel_interview':
    if ($interviewId) {
        // DELETE the interview record instead of just updating status
        $stmt = $db->prepare("DELETE FROM interviews WHERE id = ?");
        $stmt->execute([$interviewId]);
        
        // Reset application status back to under_review
        $stmt = $db->prepare("UPDATE applications SET status = 'under_review' WHERE id = (SELECT application_id FROM interviews WHERE id = ?)");
        $stmt->execute([$interviewId]);
        
        $_SESSION['success'] = "Interview cancelled successfully";
    }
    break;
            
        case 'delete_interview':
            if ($interviewId) {
                $stmt = $db->prepare("DELETE FROM interviews WHERE id = ?");
                $stmt->execute([$interviewId]);
                $_SESSION['success'] = "Interview record deleted successfully";
            }
            break;
    }
    
    header("Location: interviews.php");
    exit();
}

// Get interviews with application details
$interviews = [];
try {
    $query = "SELECT i.*, a.id as application_id, a.personal_info, a.academic_info, 
                     u.name as applicant_name, u.email, u.phone, u.profile_picture as user_profile_picture,
                     interviewer.name as interviewer_name
              FROM interviews i 
              LEFT JOIN applications a ON i.application_id = a.id 
              LEFT JOIN users u ON a.user_id = u.id 
              LEFT JOIN users interviewer ON i.created_by = interviewer.id 
              ORDER BY i.scheduled_date DESC";
    
    $stmt = $db->query($query);
    $interviews = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Interviews query error: " . $e->getMessage());
}

// Get interview statistics
$interviewStats = [
    'total' => 0,
    'scheduled' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'approved' => 0,
    'rejected' => 0
];

try {
    $stmt = $db->query("SELECT COUNT(*) FROM interviews");
    $interviewStats['total'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM interviews WHERE status = 'scheduled'");
    $interviewStats['scheduled'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM interviews WHERE status = 'completed'");
    $interviewStats['completed'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM interviews WHERE status = 'cancelled'");
    $interviewStats['cancelled'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM interviews WHERE outcome = 'approved'");
    $interviewStats['approved'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM interviews WHERE outcome = 'rejected'");
    $interviewStats['rejected'] = $stmt->fetchColumn();
} catch(PDOException $e) {
    error_log("Interview stats error: " . $e->getMessage());
}

$page_title = "Manage Interviews";
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

        <!-- Mobile Offcanvas Sidebar -->
        <div class="offcanvas-sidebar d-md-none">
            <div class="mobile-sidebar-header p-3 border-bottom">
                <h5 class="mb-0">Admin Menu</h5>
                <button class="btn-close mobile-sidebar-close"></button>
            </div>
            <?php include 'sidebar.php'; ?>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <!-- Mobile Header Bar -->
            <div class="d-md-none d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                <div>
                    <h4 class="mb-0">Manage Interviews</h4>
                    <small class="text-muted">Interview Management</small>
                </div>
                <button class="btn btn-accent admin-sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <div class="d-none d-md-flex justify-content-between align-items-center mb-4">
                <h1>Manage Interviews</h1>
               
            </div>

            <!-- Success Message -->
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Interview Stats -->
            <div class="row mb-4">
                <div class="col-md-2 mb-3">
                    <div class="card border-primary">
                        <div class="card-body text-center">
                            <h2 class="card-text text-primary"><?php echo $interviewStats['total']; ?></h2>
                            <h6 class="card-title">Total Interviews</h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 mb-3">
                    <div class="card border-warning">
                        <div class="card-body text-center">
                            <h2 class="card-text text-warning"><?php echo $interviewStats['scheduled']; ?></h2>
                            <h6 class="card-title">Scheduled</h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 mb-3">
                    <div class="card border-info">
                        <div class="card-body text-center">
                            <h2 class="card-text text-info"><?php echo $interviewStats['completed']; ?></h2>
                            <h6 class="card-title">Completed</h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 mb-3">
                    <div class="card border-secondary">
                        <div class="card-body text-center">
                            <h2 class="card-text text-secondary"><?php echo $interviewStats['cancelled']; ?></h2>
                            <h6 class="card-title">Cancelled</h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 mb-3">
                    <div class="card border-success">
                        <div class="card-body text-center">
                            <h2 class="card-text text-success"><?php echo $interviewStats['approved']; ?></h2>
                            <h6 class="card-title">Approved</h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 mb-3">
                    <div class="card border-danger">
                        <div class="card-body text-center">
                            <h2 class="card-text text-danger"><?php echo $interviewStats['rejected']; ?></h2>
                            <h6 class="card-title">Rejected</h6>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Interviews Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>Scheduled Interviews
                    </h5>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-filter me-1"></i>Filter
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="?filter=all">All Interviews</a></li>
                            <li><a class="dropdown-item" href="?filter=scheduled">Scheduled</a></li>
                            <li><a class="dropdown-item" href="?filter=completed">Completed</a></li>
                            <li><a class="dropdown-item" href="?filter=cancelled">Cancelled</a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Applicant</th>
                                    <th>Interview Date & Time</th>
                                    <th>Interviewer</th>
                                    <th>Status</th>
                                    <th>Outcome</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($interviews)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <i class="fas fa-calendar-times fa-3x mb-3"></i><br>
                                            No interviews scheduled
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($interviews as $interview): ?>
                                        <?php
                                        // Decode personal info safely
                                        $personalInfo = json_decode($interview['personal_info'] ?? '{}', true) ?? [];
                                        
                                        // Get profile picture
                                        if (!empty($interview['user_profile_picture']) && $interview['user_profile_picture'] !== 'default-avatar.png') {
                                            $profilePicture = $interview['user_profile_picture'];
                                        } else {
                                            $profilePicture = $personalInfo['profile_picture'] ?? 'default-avatar.png';
                                        }
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if($profilePicture && $profilePicture !== 'default-avatar.png'): ?>
                                                        <img src="<?php echo SITE_URL . '/uploads/profile-pictures/' . $profilePicture; ?>" 
                                                             alt="<?php echo htmlspecialchars($personalInfo['name'] ?? $interview['applicant_name'] ?? 'Unknown'); ?>" 
                                                             class="rounded-circle me-2" 
                                                             style="width: 40px; height: 40px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-2" 
                                                             style="width: 40px; height: 40px;">
                                                            <i class="fas fa-user text-white"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($personalInfo['name'] ?? $interview['applicant_name'] ?? 'Unknown'); ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars($personalInfo['sap_id'] ?? 'N/A'); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <small>
                                                    <i class="fas fa-calendar me-1"></i><?php echo date('M j, Y', strtotime($interview['scheduled_date'])); ?><br>
                                                    <i class="fas fa-clock me-1"></i><?php echo date('g:i A', strtotime($interview['scheduled_date'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars($interview['interviewer_name'] ?? 'Not Assigned'); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    switch($interview['status']) {
                                                        case 'scheduled': echo 'warning'; break;
                                                        case 'completed': echo 'success'; break;
                                                        case 'cancelled': echo 'danger'; break;
                                                        default: echo 'secondary';
                                                    }
                                                ?>">
                                                    <?php echo ucfirst($interview['status'] ?? 'unknown'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    // FIXED: Added proper variable and default handling
                                                    $outcome = $interview['outcome'] ?? 'pending';
                                                    switch($outcome) {
                                                        case 'approved': echo 'success'; break;
                                                        case 'rejected': echo 'danger'; break;
                                                        default: echo 'secondary';
                                                    }
                                                ?>">
                                                    <?php echo ucfirst($outcome); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                                                        <i class="fas fa-cog me-1"></i>Actions
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#viewInterviewModal<?php echo $interview['id']; ?>">
                                                                <i class="fas fa-eye me-2"></i>View Details
                                                            </a>
                                                        </li>
                                                        <?php if($interview['status'] === 'scheduled'): ?>
                                                            <li>
                                                                <a class="dropdown-item text-success fw-bold" href="?action=approve_after_interview&id=<?php echo $interview['id']; ?>&app_id=<?php echo $interview['application_id']; ?>" 
                                                                   onclick="return confirm('✅ APPROVE AFTER INTERVIEW\n\nApprove <?php echo htmlspecialchars($personalInfo['name'] ?? 'Applicant'); ?> for membership?\n\nThis will create a member account and generate temporary password.')">
                                                                    <i class="fas fa-check-circle me-2"></i>Approve & Create Account
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item text-danger fw-bold" href="?action=reject_after_interview&id=<?php echo $interview['id']; ?>&app_id=<?php echo $interview['application_id']; ?>" 
                                                                   onclick="return confirm('❌ REJECT AFTER INTERVIEW\n\nReject <?php echo htmlspecialchars($personalInfo['name'] ?? 'Applicant'); ?> application?')">
                                                                    <i class="fas fa-times-circle me-2"></i>Reject Application
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item text-warning" href="?action=cancel_interview&id=<?php echo $interview['id']; ?>" 
                                                                   onclick="return confirm('⚠️ CANCEL INTERVIEW\n\nCancel this interview?')">
                                                                    <i class="fas fa-calendar-times me-2"></i>Cancel Interview
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="?action=delete_interview&id=<?php echo $interview['id']; ?>" 
                                                               onclick="return confirm('⚠️ DELETE INTERVIEW RECORD\n\nAre you sure you want to delete this interview record?')">
                                                                <i class="fas fa-trash me-2"></i>Delete Record
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- View Interview Modal -->
                                        <div class="modal fade" id="viewInterviewModal<?php echo $interview['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Interview Details - <?php echo htmlspecialchars($personalInfo['name'] ?? 'Applicant'); ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <h6>Interview Information</h6>
                                                                <table class="table table-sm table-borderless">
                                                                    <tr><th>Scheduled Date:</th><td><?php echo date('M j, Y', strtotime($interview['scheduled_date'])); ?></td></tr>
                                                                    <tr><th>Scheduled Time:</th><td><?php echo date('g:i A', strtotime($interview['scheduled_date'])); ?></td></tr>
                                                                    <tr><th>Status:</th><td><span class="badge bg-<?php echo $interview['status'] === 'scheduled' ? 'warning' : ($interview['status'] === 'completed' ? 'success' : 'danger'); ?>"><?php echo ucfirst($interview['status']); ?></span></td></tr>
                                                                    <tr><th>Outcome:</th><td>
                                                                        <?php
                                                                        $outcome = $interview['outcome'] ?? 'pending';
                                                                        ?>
                                                                        <span class="badge bg-<?php echo $outcome === 'approved' ? 'success' : ($outcome === 'rejected' ? 'danger' : 'secondary'); ?>"><?php echo ucfirst($outcome); ?></span>
                                                                    </td></tr>
                                                                    <tr><th>Interviewer:</th><td><?php echo htmlspecialchars($interview['interviewer_name'] ?? 'Not Assigned'); ?></td></tr>
                                                                </table>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <h6>Applicant Information</h6>
                                                                <table class="table table-sm table-borderless">
                                                                    <tr><th>Name:</th><td><?php echo htmlspecialchars($personalInfo['name'] ?? 'N/A'); ?></td></tr>
                                                                    <tr><th>SAP ID:</th><td><?php echo htmlspecialchars($personalInfo['sap_id'] ?? 'N/A'); ?></td></tr>
                                                                    <tr><th>Department:</th><td><?php echo htmlspecialchars($personalInfo['department'] ?? 'N/A'); ?></td></tr>
                                                                    <tr><th>Email:</th><td><?php echo htmlspecialchars($personalInfo['email'] ?? 'N/A'); ?></td></tr>
                                                                    <tr><th>Phone:</th><td><?php echo htmlspecialchars($personalInfo['phone'] ?? 'N/A'); ?></td></tr>
                                                                </table>
                                                            </div>
                                                        </div>
                                                        
                                                        <?php if(!empty($interview['notes'])): ?>
                                                            <h6 class="mt-3">Interview Notes</h6>
                                                            <div class="border rounded p-3 bg-light">
                                                                <?php echo nl2br(htmlspecialchars($interview['notes'])); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <?php if($interview['status'] === 'scheduled'): ?>
                                                            <a href="?action=approve_after_interview&id=<?php echo $interview['id']; ?>&app_id=<?php echo $interview['application_id']; ?>" 
                                                               class="btn btn-success"
                                                               onclick="return confirm('Approve this applicant after interview?')">
                                                                <i class="fas fa-check me-2"></i>Approve & Create Account
                                                            </a>
                                                            <a href="?action=reject_after_interview&id=<?php echo $interview['id']; ?>&app_id=<?php echo $interview['application_id']; ?>" 
                                                               class="btn btn-danger"
                                                               onclick="return confirm('Reject this applicant after interview?')">
                                                                <i class="fas fa-times me-2"></i>Reject Application
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Admin Sidebar Overlay -->
<div class="admin-sidebar-overlay"></div>

<script>
// Admin sidebar functionality
document.addEventListener('DOMContentLoaded', function() {
    const adminSidebarToggle = document.querySelector('.admin-sidebar-toggle');
    const offcanvasSidebar = document.querySelector('.offcanvas-sidebar');
    const adminSidebarOverlay = document.querySelector('.admin-sidebar-overlay');
    const mobileSidebarClose = document.querySelector('.mobile-sidebar-close');
    
    if (adminSidebarToggle && offcanvasSidebar) {
        const overlay = document.querySelector('.admin-sidebar-overlay');
        
        // Toggle sidebar
        adminSidebarToggle.addEventListener('click', function() {
            offcanvasSidebar.classList.toggle('show');
            overlay.classList.toggle('show');
            document.body.style.overflow = offcanvasSidebar.classList.contains('show') ? 'hidden' : '';
        });
        
        // Close sidebar when clicking overlay
        overlay.addEventListener('click', function() {
            offcanvasSidebar.classList.remove('show');
            overlay.classList.remove('show');
            document.body.style.overflow = '';
        });
        
        // Close sidebar when clicking close button
        if (mobileSidebarClose) {
            mobileSidebarClose.addEventListener('click', function() {
                offcanvasSidebar.classList.remove('show');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            });
        }
    }
});
</script>

<style>
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

.admin-sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1040;
}

.admin-sidebar-overlay.show {
    display: block;
}

@media (max-width: 767.98px) {
    .offcanvas-sidebar {
        position: fixed;
        top: 0;
        left: -280px;
        width: 280px;
        height: 100vh;
        z-index: 1050;
        background: white;
        transition: left 0.3s ease;
        overflow-y: auto;
        box-shadow: 2px 0 10px rgba(0,0,0,0.1);
    }
    
    .offcanvas-sidebar.show {
        left: 0;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>