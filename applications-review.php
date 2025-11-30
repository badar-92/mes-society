<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['super_admin', 'department_head', 'hiring_head']);

$auth = new Auth();
$db = Database::getInstance()->getConnection();

// Handle actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $applicationId = $_GET['id'] ?? null;
    
    switch($action) {
        case 'approve':
            if ($applicationId) {
                // Generate temporary password
                $tempPassword = $auth->generatePassword();
                
                // Create member account with temporary password
                if ($auth->createMemberAccountWithPassword($applicationId, $tempPassword)) {
                    $_SESSION['success'] = "Application approved and member account created successfully. 
                    Temporary password: <strong>" . $tempPassword . "</strong>
                    <br><a href='generate-confirmation-letter.php?type=application&id=" . $applicationId . "' target='_blank' class='btn btn-success btn-sm mt-2' onclick='return generateLetter(this)'>Generate Welcome Letter</a>";
                } else {
                    $_SESSION['error'] = "Failed to create member account";
                }
            }
            break;
            
        case 'reject':
            if ($applicationId) {
                $stmt = $db->prepare("UPDATE applications SET status = 'rejected' WHERE id = ?");
                $stmt->execute([$applicationId]);
                $_SESSION['success'] = "Application rejected successfully";
            }
            break;
            
        case 'schedule_interview':
            if ($applicationId) {
                $interviewDate = $_POST['interview_date'] ?? null;
                $interviewTime = $_POST['interview_time'] ?? null;
                $notes = $_POST['notes'] ?? '';
                
                if ($interviewDate && $interviewTime) {
                    $interviewDateTime = $interviewDate . ' ' . $interviewTime;
                    
                    try {
                        // Check if interviews table exists and insert
                        $stmt = $db->prepare("INSERT INTO interviews (application_id, scheduled_date, notes, status, created_by) VALUES (?, ?, ?, 'scheduled', ?)");
                        $stmt->execute([$applicationId, $interviewDateTime, $notes, $_SESSION['user_id']]);
                        
                        // Update application status
                        $auth->updateApplicationStatus($applicationId, 'interview_scheduled');
                        $_SESSION['success'] = "Interview scheduled successfully";
                    } catch (PDOException $e) {
                        // If interviews table doesn't exist, just update application status
                        $auth->updateApplicationStatus($applicationId, 'interview_scheduled');
                        $_SESSION['success'] = "Application marked for interview (interview scheduling feature not available)";
                    }
                }
            }
            break;
            
        case 'delete':
            if ($applicationId) {
                $stmt = $db->prepare("DELETE FROM applications WHERE id = ?");
                $stmt->execute([$applicationId]);
                $_SESSION['success'] = "Application deleted successfully";
            }
            break;
    }
    
    header("Location: applications.php");
    exit();
}

// Get filter status
$filter = $_GET['filter'] ?? 'pending';

// Get applications based on filter
$applications = [];
try {
    $query = "SELECT a.*, u.name as applicant_name, u.email, u.phone, u.profile_picture as user_profile_picture 
              FROM applications a 
              LEFT JOIN users u ON a.user_id = u.id 
              WHERE a.applied_for = 'membership'";
    
    switch($filter) {
        case 'pending':
            $query .= " AND a.status = 'pending'";
            break;
        case 'under_review':
            $query .= " AND a.status = 'under_review'";
            break;
        case 'interview_scheduled':
            $query .= " AND a.status = 'interview_scheduled'";
            break;
        case 'selected':
            $query .= " AND a.status = 'selected'";
            break;
        case 'rejected':
            $query .= " AND a.status = 'rejected'";
            break;
    }
    
    $query .= " ORDER BY a.applied_at DESC";
    $stmt = $db->query($query);
    $applications = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Applications query error: " . $e->getMessage());
}

// Get application statistics
$appStats = [
    'total' => 0,
    'pending' => 0,
    'under_review' => 0,
    'interview_scheduled' => 0,
    'selected' => 0,
    'rejected' => 0
];

try {
    $stmt = $db->query("SELECT COUNT(*) FROM applications WHERE applied_for = 'membership'");
    $appStats['total'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM applications WHERE applied_for = 'membership' AND status = 'pending'");
    $appStats['pending'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM applications WHERE applied_for = 'membership' AND status = 'under_review'");
    $appStats['under_review'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM applications WHERE applied_for = 'membership' AND status = 'interview_scheduled'");
    $appStats['interview_scheduled'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM applications WHERE applied_for = 'membership' AND status = 'selected'");
    $appStats['selected'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM applications WHERE applied_for = 'membership' AND status = 'rejected'");
    $appStats['rejected'] = $stmt->fetchColumn();
} catch(PDOException $e) {
    error_log("Application stats error: " . $e->getMessage());
}

$page_title = "Manage Applications";
require_once '../includes/header.php';
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
                    <h4 class="mb-0">Manage Applications</h4>
                    <small class="text-muted">Application Management</small>
                </div>
                <button class="btn btn-accent" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileSidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <div class="d-none d-md-flex justify-content-between align-items-center mb-4">
                <h1>Manage Applications</h1>
                <div class="btn-group">
                    <a href="interviews.php" class="btn btn-primary">
                        <i class="fas fa-calendar-alt me-2"></i>Manage Interviews
                    </a>
                    <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-download me-2"></i>Export
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="export-applications.php?type=excel">Export as Excel</a></li>
                    </ul>
                </div>
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

            <!-- Application Stats -->
            <div class="row mb-4">
                <div class="col-md-2 mb-3">
                    <div class="card <?php echo $filter === 'all' ? 'border-accent' : ''; ?>">
                        <div class="card-body text-center">
                            <h2 class="card-text text-primary"><?php echo $appStats['total']; ?></h2>
                            <h6 class="card-title">Total</h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 mb-3">
                    <div class="card <?php echo $filter === 'pending' ? 'border-accent' : ''; ?>">
                        <div class="card-body text-center">
                            <h2 class="card-text text-warning"><?php echo $appStats['pending']; ?></h2>
                            <h6 class="card-title">Pending</h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 mb-3">
                    <div class="card <?php echo $filter === 'under_review' ? 'border-accent' : ''; ?>">
                        <div class="card-body text-center">
                            <h2 class="card-text text-info"><?php echo $appStats['under_review']; ?></h2>
                            <h6 class="card-title">Under Review</h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 mb-3">
                    <div class="card <?php echo $filter === 'interview_scheduled' ? 'border-accent' : ''; ?>">
                        <div class="card-body text-center">
                            <h2 class="card-text text-primary"><?php echo $appStats['interview_scheduled']; ?></h2>
                            <h6 class="card-title">Interview</h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 mb-3">
                    <div class="card <?php echo $filter === 'selected' ? 'border-accent' : ''; ?>">
                        <div class="card-body text-center">
                            <h2 class="card-text text-success"><?php echo $appStats['selected']; ?></h2>
                            <h6 class="card-title">Selected</h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 mb-3">
                    <div class="card <?php echo $filter === 'rejected' ? 'border-accent' : ''; ?>">
                        <div class="card-body text-center">
                            <h2 class="card-text text-danger"><?php echo $appStats['rejected']; ?></h2>
                            <h6 class="card-title">Rejected</h6>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Applications Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-file-alt me-2"></i>Membership Applications
                    </h5>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-filter me-1"></i><?php echo ucfirst(str_replace('_', ' ', $filter)); ?>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item <?php echo $filter === 'all' ? 'active' : ''; ?>" href="?filter=all">All Applications</a></li>
                            <li><a class="dropdown-item <?php echo $filter === 'pending' ? 'active' : ''; ?>" href="?filter=pending">Pending</a></li>
                            <li><a class="dropdown-item <?php echo $filter === 'under_review' ? 'active' : ''; ?>" href="?filter=under_review">Under Review</a></li>
                            <li><a class="dropdown-item <?php echo $filter === 'interview_scheduled' ? 'active' : ''; ?>" href="?filter=interview_scheduled">Interview Scheduled</a></li>
                            <li><a class="dropdown-item <?php echo $filter === 'selected' ? 'active' : ''; ?>" href="?filter=selected">Selected</a></li>
                            <li><a class="dropdown-item <?php echo $filter === 'rejected' ? 'active' : ''; ?>" href="?filter=rejected">Rejected</a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Applicant</th>
                                    <th>Contact</th>
                                    <th>Department</th>
                                    <th>Applied On</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($applications)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <i class="fas fa-file-alt fa-3x mb-3"></i><br>
                                            No applications found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($applications as $app): ?>
                                        <?php
                                        // Decode personal info safely
                                        $personalInfo = json_decode($app['personal_info'] ?? '{}', true) ?? [];
                                        $academicInfo = json_decode($app['academic_info'] ?? '{}', true) ?? [];
                                        
                                        // Get profile picture
                                        if (!empty($app['user_profile_picture']) && $app['user_profile_picture'] !== 'default-avatar.png') {
                                            $profilePicture = $app['user_profile_picture'];
                                        } else {
                                            $profilePicture = $personalInfo['profile_picture'] ?? 'default-avatar.png';
                                        }
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if($profilePicture && $profilePicture !== 'default-avatar.png'): ?>
                                                        <img src="<?php echo SITE_URL . '/uploads/profile-pictures/' . $profilePicture; ?>" 
                                                             alt="<?php echo htmlspecialchars($personalInfo['name'] ?? $app['applicant_name'] ?? 'Unknown'); ?>" 
                                                             class="rounded-circle me-2" 
                                                             style="width: 40px; height: 40px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-2" 
                                                             style="width: 40px; height: 40px;">
                                                            <i class="fas fa-user text-white"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($personalInfo['name'] ?? $app['applicant_name'] ?? 'Unknown'); ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars($personalInfo['sap_id'] ?? 'N/A'); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <small>
                                                    <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($personalInfo['email'] ?? $app['email'] ?? 'N/A'); ?><br>
                                                    <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($personalInfo['phone'] ?? $app['phone'] ?? 'N/A'); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php echo htmlspecialchars($personalInfo['department'] ?? 'N/A'); ?><br>
                                                    Sem: <?php echo htmlspecialchars($personalInfo['semester'] ?? 'N/A'); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <small><?php echo date('M j, Y', strtotime($app['applied_at'] ?? 'now')); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    switch($app['status']) {
                                                        case 'pending': echo 'warning'; break;
                                                        case 'under_review': echo 'info'; break;
                                                        case 'interview_scheduled': echo 'primary'; break;
                                                        case 'selected': echo 'success'; break;
                                                        case 'rejected': echo 'danger'; break;
                                                        default: echo 'secondary';
                                                    }
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $app['status'] ?? 'unknown')); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                                                        <i class="fas fa-cog me-1"></i>Actions
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#viewApplicationModal<?php echo $app['id']; ?>">
                                                                <i class="fas fa-eye me-2"></i>View Details
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#scheduleInterviewModal<?php echo $app['id']; ?>">
                                                                <i class="fas fa-calendar me-2"></i>Schedule Interview
                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        
                                                        <!-- APPROVE BUTTON - Clear Green Button -->
                                                        <?php if($app['status'] !== 'selected' && $app['status'] !== 'rejected'): ?>
                                                            <li>
                                                                <a class="dropdown-item text-success fw-bold" href="?action=approve&id=<?php echo $app['id']; ?>" 
                                                                   onclick="return confirm('✅ APPROVE APPLICATION\n\nApprove <?php echo htmlspecialchars($personalInfo['name'] ?? 'Applicant'); ?> for membership?\n\nThis will:\n• Create a member account\n• Generate temporary password\n• Send welcome letter')">
                                                                    <i class="fas fa-check-circle me-2"></i>APPROVE & Create Account
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                        
                                                        <!-- REJECT BUTTON - Clear Red Button -->
                                                        <?php if($app['status'] !== 'rejected'): ?>
                                                            <li>
                                                                <a class="dropdown-item text-danger fw-bold" href="?action=reject&id=<?php echo $app['id']; ?>" 
                                                                   onclick="return confirm('❌ REJECT APPLICATION\n\nReject <?php echo htmlspecialchars($personalInfo['name'] ?? 'Applicant'); ?> application?\n\nThis action cannot be undone.')">
                                                                    <i class="fas fa-times-circle me-2"></i>REJECT Application
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                        
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="?action=delete&id=<?php echo $app['id']; ?>" 
                                                               onclick="return confirm('⚠️ DELETE APPLICATION\n\nAre you sure you want to delete this application?\n\nThis action cannot be undone.')">
                                                                <i class="fas fa-trash me-2"></i>Delete Application
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                                
                                                <!-- Quick Action Buttons (Visible on larger screens) -->
                                                <div class="btn-group d-none d-md-flex ms-2">
                                                    <?php if($app['status'] !== 'selected' && $app['status'] !== 'rejected'): ?>
                                                        <a class="btn btn-success btn-sm" href="?action=approve&id=<?php echo $app['id']; ?>" 
                                                           onclick="return confirm('✅ APPROVE APPLICATION\n\nApprove <?php echo htmlspecialchars($personalInfo['name'] ?? 'Applicant'); ?> for membership?')"
                                                           data-bs-toggle="tooltip" title="Approve & Create Account">
                                                            <i class="fas fa-check"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if($app['status'] !== 'rejected'): ?>
                                                        <a class="btn btn-danger btn-sm" href="?action=reject&id=<?php echo $app['id']; ?>" 
                                                           onclick="return confirm('❌ REJECT APPLICATION\n\nReject <?php echo htmlspecialchars($personalInfo['name'] ?? 'Applicant'); ?> application?')"
                                                           data-bs-toggle="tooltip" title="Reject Application">
                                                            <i class="fas fa-times"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- View Application Modal -->
                                        <div class="modal fade" id="viewApplicationModal<?php echo $app['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Application Details - <?php echo htmlspecialchars($personalInfo['name'] ?? 'Applicant'); ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row">
                                                            <div class="col-md-4 text-center">
                                                                <!-- Applicant Photo -->
                                                                <?php if($profilePicture && $profilePicture !== 'default-avatar.png'): ?>
                                                                    <img src="<?php echo SITE_URL . '/uploads/profile-pictures/' . $profilePicture; ?>" 
                                                                         alt="<?php echo htmlspecialchars($personalInfo['name'] ?? 'Applicant'); ?>" 
                                                                         class="rounded mb-3" 
                                                                         style="width: 150px; height: 150px; object-fit: cover;">
                                                                <?php else: ?>
                                                                    <div class="rounded bg-secondary d-flex align-items-center justify-content-center mb-3 mx-auto" 
                                                                         style="width: 150px; height: 150px;">
                                                                        <i class="fas fa-user text-white fa-3x"></i>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="col-md-8">
                                                                <h6>Personal Information</h6>
                                                                <table class="table table-sm table-borderless">
                                                                    <tr><th>Name:</th><td><?php echo htmlspecialchars($personalInfo['name'] ?? 'N/A'); ?></td></tr>
                                                                    <tr><th>SAP ID:</th><td><?php echo htmlspecialchars($personalInfo['sap_id'] ?? 'N/A'); ?></td></tr>
                                                                    <tr><th>Department:</th><td><?php echo htmlspecialchars($personalInfo['department'] ?? 'N/A'); ?></td></tr>
                                                                    <tr><th>Semester:</th><td><?php echo htmlspecialchars($personalInfo['semester'] ?? 'N/A'); ?></td></tr>
                                                                    <tr><th>Email:</th><td><?php echo htmlspecialchars($personalInfo['email'] ?? 'N/A'); ?></td></tr>
                                                                    <tr><th>Phone:</th><td><?php echo htmlspecialchars($personalInfo['phone'] ?? 'N/A'); ?></td></tr>
                                                                </table>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="row mt-3">
                                                            <div class="col-md-6">
                                                                <h6>Academic Information</h6>
                                                                <table class="table table-sm table-borderless">
                                                                    <tr><th>CGPA:</th><td><?php echo htmlspecialchars($academicInfo['cgpa'] ?? 'N/A'); ?></td></tr>
                                                                    <tr><th>Current Courses:</th><td><?php echo htmlspecialchars($academicInfo['current_courses'] ?? 'N/A'); ?></td></tr>
                                                                </table>
                                                            </div>
                                                        </div>
                                                        
                                                        <h6 class="mt-3">Skills & Experience</h6>
                                                        <p><?php echo nl2br(htmlspecialchars($app['skills_experience'] ?? 'No information provided')); ?></p>
                                                        
                                                        <h6 class="mt-3">Motivation Statement</h6>
                                                        <p><?php echo nl2br(htmlspecialchars($app['motivation_statement'] ?? 'No statement provided')); ?></p>
                                                        
                                                        <?php if(!empty($app['portfolio_links'])): ?>
                                                            <h6 class="mt-3">Portfolio Links</h6>
                                                            <p><?php echo nl2br(htmlspecialchars($app['portfolio_links'])); ?></p>
                                                        <?php endif; ?>
                                                        
                                                        <?php if(!empty($app['resume_path'])): ?>
                                                            <h6 class="mt-3">Resume</h6>
                                                            <a href="<?php echo SITE_URL . '/uploads/resumes/' . $app['resume_path']; ?>" 
                                                               class="btn btn-sm btn-outline-primary" target="_blank">
                                                                <i class="fas fa-download me-2"></i>Download Resume
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <?php if($app['status'] !== 'selected'): ?>
                                                            <a href="?action=approve&id=<?php echo $app['id']; ?>" 
                                                               class="btn btn-success"
                                                               onclick="return confirm('✅ APPROVE APPLICATION\n\nApprove this application and generate temporary password?')">
                                                                <i class="fas fa-check me-2"></i>APPROVE & Create Account
                                                            </a>
                                                        <?php endif; ?>
                                                        <?php if($app['status'] !== 'rejected'): ?>
                                                            <a href="?action=reject&id=<?php echo $app['id']; ?>" 
                                                               class="btn btn-danger"
                                                               onclick="return confirm('❌ REJECT APPLICATION\n\nReject this application?')">
                                                                <i class="fas fa-times me-2"></i>REJECT Application
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Schedule Interview Modal -->
                                        <div class="modal fade" id="scheduleInterviewModal<?php echo $app['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="POST" action="?action=schedule_interview&id=<?php echo $app['id']; ?>">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Schedule Interview - <?php echo htmlspecialchars($personalInfo['name'] ?? 'Applicant'); ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label for="interview_date" class="form-label">Interview Date *</label>
                                                                <input type="date" class="form-control" id="interview_date" name="interview_date" required 
                                                                       min="<?php echo date('Y-m-d'); ?>">
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="interview_time" class="form-label">Interview Time *</label>
                                                                <input type="time" class="form-control" id="interview_time" name="interview_time" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="notes" class="form-label">Notes (Optional)</label>
                                                                <textarea class="form-control" id="notes" name="notes" rows="3" 
                                                                          placeholder="Any special instructions or notes for the candidate..."></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-accent">Schedule Interview</button>
                                                        </div>
                                                    </form>
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

<!-- Mobile Floating Action Button -->
<div class="fab-container d-md-none">
    <button class="fab btn btn-accent rounded-circle" onclick="window.location.href='../public/apply.php'">
        <i class="fas fa-plus"></i>
    </button>
</div>

<script>
// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
});

function generateLetter(element) {
    event.preventDefault();
    const url = element.href;
    
    // Show loading
    element.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Generating...';
    element.disabled = true;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message with download link
                const downloadUrl = 'download-confirmation-letter.php?file=' + encodeURIComponent(data.filepath);
                let message = `Letter generated successfully! `;
                if (data.temp_password) {
                    message += `Temporary password: <strong>${data.temp_password}</strong><br>`;
                }
                message += `<a href="${downloadUrl}" class="btn btn-success btn-sm mt-2" download>
                    <i class="fas fa-download me-1"></i>Download Letter
                </a>`;
                
                // Show alert or update message area
                alert(message);
            } else {
                alert('Error: ' . data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('There was an error generating the letter.');
        })
        .finally(() => {
            // Reset button
            element.innerHTML = '<i class="fas fa-download me-1"></i>Generate Welcome Letter';
            element.disabled = false;
        });
    
    return false;
}
</script>

<style>
/* Floating Action Button */
.fab-container {
    position: fixed;
    bottom: 80px;
    right: 20px;
    z-index: 1030;
}

.fab {
    width: 60px;
    height: 60px;
    border: none;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    transition: all 0.3s ease;
}

.fab:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(0,0,0,0.3);
}

/* Improved action buttons */
.dropdown-item.text-success {
    background-color: rgba(25, 135, 84, 0.1);
}

.dropdown-item.text-danger {
    background-color: rgba(220, 53, 69, 0.1);
}

.dropdown-item.text-success:hover {
    background-color: rgba(25, 135, 84, 0.2);
}

.dropdown-item.text-danger:hover {
    background-color: rgba(220, 53, 69, 0.2);
}

/* Mobile responsive adjustments */
@media (max-width: 767.98px) {
    .container-fluid {
        padding-left: 10px;
        padding-right: 10px;
    }
    
    .btn-group.d-md-flex {
        display: none !important;
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