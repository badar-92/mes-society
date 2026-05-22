<?php
require_once '../includes/session.php';
require_once '../includes/database.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['super_admin', 'department_head', 'competition_head']);

$db = Database::getInstance()->getConnection();

$competition_id = $_GET['id'] ?? 0;

// Get competition details
$competition = null;
try {
    $stmt = $db->prepare("SELECT * FROM competitions WHERE id = ?");
    $stmt->execute([$competition_id]);
    $competition = $stmt->fetch();
} catch(PDOException $e) {
    error_log("Competition participants error: " . $e->getMessage());
}

if (!$competition) {
    header("Location: competitions.php");
    exit();
}

// Get participants
$participants = [];
try {
    $stmt = $db->prepare("SELECT * FROM competition_registrations WHERE competition_id = ? ORDER BY registration_date DESC");
    $stmt->execute([$competition_id]);
    $participants = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Participants query error: " . $e->getMessage());
}

// Export to Excel function
function exportParticipantsExcel($participants, $competition) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $competition['title'] . ' - Participants.xls"');
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .header { background-color: #4CAF50; color: white; }
            .summary { background-color: #e7f3fe; }
        </style>
    </head>
    <body>";
    
    echo "<h2>" . htmlspecialchars($competition['title']) . " - Participants</h2>";
    echo "<p><strong>Generated on:</strong> " . date('F j, Y g:i A') . "</p>";
    echo "<p><strong>Total Participants:</strong> " . count($participants) . "</p>";
    
    echo "<table>
        <thead>
            <tr class='header'>
                <th>#</th>
                <th>Registration ID</th>
                <th>Type</th>
                <th>Team/Individual Name</th>
                <th>Team Leader</th>
                <th>Leader SAP ID</th>
                <th>Leader Email</th>
                <th>Leader Phone</th>
                <th>Leader Department</th>
                <th>University</th>
                <th>Team Members Count</th>
                <th>Registration Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>";
    
    foreach ($participants as $index => $participant) {
        $team_members = [];
        $team_members_count = 0;
        if (!empty($participant['team_members'])) {
            $decoded_members = json_decode($participant['team_members'], true);
            $team_members = is_array($decoded_members) ? $decoded_members : [];
            $team_members_count = count($team_members);
        }
        
        $participant_type = ($competition['competition_type'] === 'team') ? 'Team' : 'Individual';
        $team_individual_name = ($competition['competition_type'] === 'team') ? 
            ($participant['team_name'] ?: 'Unnamed Team') : 'Individual Participant';
        
        echo "<tr>
                <td>" . ($index + 1) . "</td>
                <td>#" . $participant['id'] . "</td>
                <td>" . $participant_type . "</td>
                <td>" . htmlspecialchars($team_individual_name) . "</td>
                <td>" . htmlspecialchars($participant['team_leader_name']) . "</td>
                <td>" . ($participant['team_leader_sapid'] ?: 'N/A') . "</td>
                <td>" . htmlspecialchars($participant['team_leader_email']) . "</td>
                <td>" . ($participant['team_leader_phone'] ?: 'N/A') . "</td>
                <td>" . htmlspecialchars($participant['team_leader_department'] ?: 'N/A') . "</td>
                <td>" . htmlspecialchars($participant['university'] ?: 'N/A') . "</td>
                <td>" . ($team_members_count + 1) . " (Leader + " . $team_members_count . " members)</td>
                <td>" . date('Y-m-d H:i:s', strtotime($participant['registration_date'])) . "</td>
                <td>" . ucfirst($participant['status']) . "</td>
             </tr>";
        
        // If team competition and has members, add detailed team members in subsequent rows
        if ($competition['competition_type'] === 'team' && !empty($team_members)) {
            foreach ($team_members as $member_index => $member) {
                echo "<tr style='background-color: #f9f9f9;'>
                        <td></td>
                        <td></td>
                        <td>Team Member</td>
                        <td></td>
                        <td>" . htmlspecialchars($member['name'] ?? 'N/A') . "</td>
                        <td>" . ($member['sapid'] ?? 'N/A') . "</td>
                        <td>" . htmlspecialchars($member['email'] ?? 'N/A') . "</td>
                        <td>" . ($member['phone'] ?? 'N/A') . "</td>
                        <td>" . htmlspecialchars($member['department'] ?? 'N/A') . "</td>
                        <td></td>
                        <td>Member " . ($member_index + 1) . "</td>
                        <td></td>
                        <td></td>
                     </tr>";
            }
        }
    }
    
    echo "</tbody>
    </table>";
    
    // Add summary statistics
    $approved = array_filter($participants, function($p) { return $p['status'] === 'approved'; });
    $pending = array_filter($participants, function($p) { return $p['status'] === 'pending'; });
    $rejected = array_filter($participants, function($p) { return $p['status'] === 'rejected'; });
    
    echo "<br><br>
    <table style='width: 50%;' class='summary'>
        <tr class='header'>
            <th colspan='2'>Summary Statistics</th>
        </tr>
        <tr>
            <td><strong>Total Registrations:</strong></td>
            <td>" . count($participants) . "</td>
        </tr>
        <tr>
            <td><strong>Approved:</strong></td>
            <td>" . count($approved) . "</td>
        </tr>
        <tr>
            <td><strong>Pending:</strong></td>
            <td>" . count($pending) . "</td>
        </tr>
        <tr>
            <td><strong>Rejected:</strong></td>
            <td>" . count($rejected) . "</td>
        </tr>
    </table>";
    
    echo "</body></html>";
    exit();
}

// ==================== ACTION HANDLING ====================
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $reg_id = isset($_GET['reg_id']) ? (int)$_GET['reg_id'] : 0;

    if ($action === 'export_excel') {
        exportParticipantsExcel($participants, $competition);
        exit();
    }

    if ($reg_id > 0 && in_array($action, ['approve', 'reject', 'delete'])) {
        // Verify that this registration belongs to the current competition
        try {
            $checkStmt = $db->prepare("SELECT id FROM competition_registrations WHERE id = ? AND competition_id = ?");
            $checkStmt->execute([$reg_id, $competition_id]);
            if ($checkStmt->rowCount() == 0) {
                $_SESSION['error'] = "Registration ID $reg_id does not belong to this competition.";
                header("Location: competition-participants.php?id=" . $competition_id);
                exit();
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database error during verification: " . $e->getMessage();
            header("Location: competition-participants.php?id=" . $competition_id);
            exit();
        }

        // Perform the action
        try {
            switch($action) {
                case 'approve':
                    $sql = "UPDATE competition_registrations SET status = 'approved' WHERE id = ?";
                    break;
                case 'reject':
                    $sql = "UPDATE competition_registrations SET status = 'rejected' WHERE id = ?";
                    break;
                case 'delete':
                    $sql = "DELETE FROM competition_registrations WHERE id = ?";
                    break;
            }
            
            $stmt = $db->prepare($sql);
            $success = $stmt->execute([$reg_id]);
            
            if ($success && $stmt->rowCount() > 0) {
                $_SESSION['success'] = "Registration " . ($action === 'delete' ? 'deleted' : $action . 'ed') . " successfully.";
            } else {
                $errorInfo = $stmt->errorInfo();
                if ($errorInfo[2]) {
                    $_SESSION['error'] = "Database error: " . $errorInfo[2];
                } else {
                    $_SESSION['error'] = "No rows affected. The registration may have already been modified.";
                }
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Exception: " . $e->getMessage();
        }

        // Redirect back to the participants page
        header("Location: competition-participants.php?id=" . $competition_id);
        exit();
    }
}

require_once '../includes/header.php';
$page_title = "Participants - " . $competition['title'];
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 d-none d-md-block">
            <div class="desktop-sidebar">
                <?php include 'sidebar.php'; ?>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Participants - <?php echo htmlspecialchars($competition['title']); ?></h1>
                <div>
                    <a href="competitions-edit.php?id=<?php echo $competition_id; ?>" class="btn btn-outline-primary me-2">
                        <i class="fas fa-edit me-2"></i>Edit Competition
                    </a>
                    <a href="competitions.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Competitions
                    </a>
                </div>
            </div>

            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Error!</strong> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h3 class="card-text"><?php echo count($participants); ?></h3>
                            <h6 class="card-title">Total Registrations</h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h3 class="card-text">
                                <?php 
                                $approved = array_filter($participants, function($p) { 
                                    return $p['status'] === 'approved'; 
                                });
                                echo count($approved);
                                ?>
                            </h3>
                            <h6 class="card-title">Approved</h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <h3 class="card-text">
                                <?php 
                                $pending = array_filter($participants, function($p) { 
                                    return $p['status'] === 'pending'; 
                                });
                                echo count($pending);
                                ?>
                            </h3>
                            <h6 class="card-title">Pending</h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body text-center">
                            <h3 class="card-text">
                                <?php 
                                $rejected = array_filter($participants, function($p) { 
                                    return $p['status'] === 'rejected'; 
                                });
                                echo count($rejected);
                                ?>
                            </h3>
                            <h6 class="card-title">Rejected</h6>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-users me-2"></i>Registered Participants
                    </h5>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-download me-1"></i>Export
                        </button>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="competition-participants.php?id=<?php echo $competition_id; ?>&amp;action=export_excel">
                                    <i class="fas fa-file-excel me-2"></i>Export to Excel
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Team/Individual</th>
                                    <th>Team Leader</th>
                                    <th>Contact</th>
                                    <th>Department</th>
                                    <th>University</th>
                                    <th>Registration Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($participants)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">
                                            <i class="fas fa-users fa-3x mb-3"></i><br>
                                            No participants found for this competition
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($participants as $participant): 
                                        $team_members = [];
                                        if (!empty($participant['team_members'])) {
                                            $decoded_members = json_decode($participant['team_members'], true);
                                            $team_members = is_array($decoded_members) ? $decoded_members : [];
                                        }
                                    ?>
                                        <tr>
                                            <td>#<?php echo $participant['id']; ?></td>
                                            <td>
                                                <?php if($competition['competition_type'] === 'team'): ?>
                                                    <div>
                                                        <strong class="text-primary"><?php echo htmlspecialchars($participant['team_name'] ?: 'Unnamed Team'); ?></strong>
                                                        <?php if (count($team_members) > 0): ?>
                                                            <br>
                                                            <small class="text-muted">
                                                                <i class="fas fa-users me-1"></i>
                                                                <?php echo count($team_members) + 1; ?> members (including leader)
                                                            </small>
                                                        <?php else: ?>
                                                            <br>
                                                            <small class="text-muted">Team Leader only</small>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="badge bg-info">Individual</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($participant['team_leader_name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        <i class="fas fa-id-card me-1"></i>
                                                        SAP: <?php echo $participant['team_leader_sapid'] ?: 'N/A'; ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <small>
                                                    <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($participant['team_leader_email']); ?><br>
                                                    <i class="fas fa-phone me-1"></i><?php echo $participant['team_leader_phone'] ?: 'N/A'; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($participant['team_leader_department'] ?: 'N/A'); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($participant['university'] ?: 'N/A'); ?>
                                            </td>
                                            <td>
                                                <small>
                                                    <i class="fas fa-calendar me-1"></i>
                                                    <?php echo date('M j, Y', strtotime($participant['registration_date'])); ?><br>
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo date('g:i A', strtotime($participant['registration_date'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    switch($participant['status']) {
                                                        case 'approved': echo 'success'; break;
                                                        case 'rejected': echo 'danger'; break;
                                                        default: echo 'warning';
                                                    }
                                                ?>">
                                                    <i class="fas fa-<?php 
                                                        switch($participant['status']) {
                                                            case 'approved': echo 'check'; break;
                                                            case 'rejected': echo 'times'; break;
                                                            default: echo 'clock';
                                                        }
                                                    ?> me-1"></i>
                                                    <?php echo ucfirst($participant['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                                                        <i class="fas fa-cog"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#viewParticipantModal<?php echo $participant['id']; ?>">
                                                                <i class="fas fa-eye me-2"></i>View Details
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item" href="competition-participants-edit.php?id=<?php echo $participant['id']; ?>&competition_id=<?php echo $competition_id; ?>">
                                                                <i class="fas fa-edit me-2"></i>Edit
                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <?php if($participant['status'] !== 'approved'): ?>
                                                            <li>
                                                                <a class="dropdown-item text-success" href="?id=<?php echo $competition_id; ?>&amp;action=approve&amp;reg_id=<?php echo $participant['id']; ?>" 
                                                                   onclick="return confirm('Approve registration for <?php echo htmlspecialchars($participant['team_leader_name']); ?>?')">
                                                                    <i class="fas fa-check me-2"></i>Approve
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                        <?php if($participant['status'] !== 'rejected'): ?>
                                                            <li>
                                                                <a class="dropdown-item text-warning" href="?id=<?php echo $competition_id; ?>&amp;action=reject&amp;reg_id=<?php echo $participant['id']; ?>" 
                                                                   onclick="return confirm('Reject registration for <?php echo htmlspecialchars($participant['team_leader_name']); ?>?')">
                                                                    <i class="fas fa-times me-2"></i>Reject
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="?id=<?php echo $competition_id; ?>&amp;action=delete&amp;reg_id=<?php echo $participant['id']; ?>" 
                                                               onclick="return confirm('Are you sure you want to delete registration for <?php echo htmlspecialchars($participant['team_leader_name']); ?>?')">
                                                                <i class="fas fa-trash me-2"></i>Delete
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- View Participant Modal -->
                                        <div class="modal fade" id="viewParticipantModal<?php echo $participant['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">
                                                            <i class="fas fa-user me-2"></i>
                                                            Participant Details - <?php echo htmlspecialchars($participant['team_leader_name']); ?>
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <h6>Team Leader Information</h6>
                                                                <div class="border p-3 rounded">
                                                                    <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($participant['team_leader_name']); ?></p>
                                                                    <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($participant['team_leader_email']); ?></p>
                                                                    <p class="mb-1"><strong>Phone:</strong> <?php echo $participant['team_leader_phone'] ?: 'N/A'; ?></p>
                                                                    <p class="mb-1"><strong>SAP ID:</strong> <?php echo $participant['team_leader_sapid'] ?: 'N/A'; ?></p>
                                                                    <p class="mb-1"><strong>Department:</strong> <?php echo htmlspecialchars($participant['team_leader_department'] ?: 'N/A'); ?></p>
                                                                    <p class="mb-0"><strong>University:</strong> <?php echo htmlspecialchars($participant['university'] ?: 'N/A'); ?></p>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <h6>Registration Details</h6>
                                                                <div class="border p-3 rounded">
                                                                    <p class="mb-1"><strong>Registration ID:</strong> #<?php echo $participant['id']; ?></p>
                                                                    <p class="mb-1"><strong>Date:</strong> <?php echo date('F j, Y g:i A', strtotime($participant['registration_date'])); ?></p>
                                                                    <p class="mb-1"><strong>Status:</strong> 
                                                                        <span class="badge bg-<?php 
                                                                            switch($participant['status']) {
                                                                                case 'approved': echo 'success'; break;
                                                                                case 'rejected': echo 'danger'; break;
                                                                                default: echo 'warning';
                                                                            }
                                                                        ?>">
                                                                            <?php echo ucfirst($participant['status']); ?>
                                                                        </span>
                                                                    </p>
                                                                    <?php if($competition['competition_type'] === 'team' && $participant['team_name']): ?>
                                                                        <p class="mb-0"><strong>Team Name:</strong> <?php echo htmlspecialchars($participant['team_name']); ?></p>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <?php if($competition['competition_type'] === 'team' && !empty($team_members)): ?>
                                                            <h6 class="mt-4">Team Members</h6>
                                                            <div class="row">
                                                                <?php foreach($team_members as $index => $member): ?>
                                                                    <div class="col-md-6 mb-3">
                                                                        <div class="border p-3 rounded">
                                                                            <h6 class="mb-2">Member <?php echo $index + 1; ?></h6>
                                                                            <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($member['name'] ?? 'N/A'); ?></p>
                                                                            <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($member['email'] ?? 'N/A'); ?></p>
                                                                            <p class="mb-1"><strong>Phone:</strong> <?php echo $member['phone'] ?? 'N/A'; ?></p>
                                                                            <p class="mb-1"><strong>SAP ID:</strong> <?php echo $member['sapid'] ?? 'N/A'; ?></p>
                                                                            <p class="mb-0"><strong>Department:</strong> <?php echo htmlspecialchars($member['department'] ?? 'N/A'); ?></p>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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

<?php require_once '../includes/footer.php'; ?>