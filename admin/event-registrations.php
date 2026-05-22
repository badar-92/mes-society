<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['super_admin', 'department_head', 'event_planner']);

$db = Database::getInstance()->getConnection();

// Get event ID from URL
$event_id = $_GET['id'] ?? 0;

if (!$event_id) {
    header("Location: events.php");
    exit();
}

// Fetch event details
$event = [];
$registrations = [];
$registered_count = 0;

try {
    $stmt = $db->prepare("
        SELECT e.*, u.name as created_by_name 
        FROM events e 
        LEFT JOIN users u ON e.created_by = u.id 
        WHERE e.id = ?
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        header("Location: events.php");
        exit();
    }

    // Get all registrations - FIXED: Handle both members and non-members
    $stmt = $db->prepare("
        SELECT 
            er.*, 
            COALESCE(u.name, er.name) as display_name,
            COALESCE(u.email, er.email) as display_email,
            COALESCE(u.department, er.department) as display_department,
            COALESCE(u.semester, er.semester) as display_semester,
            COALESCE(u.phone, er.phone) as display_phone,
            COALESCE(u.sap_id, er.sap_id) as display_sap_id,
            CASE 
                WHEN u.id IS NOT NULL THEN 'member'
                ELSE 'guest'
            END as registrant_type
        FROM event_registrations er 
        LEFT JOIN users u ON er.user_id = u.id 
        WHERE er.event_id = ? 
        ORDER BY er.registered_at DESC
    ");
    $stmt->execute([$event_id]);
    $registrations = $stmt->fetchAll();

    $registered_count = count($registrations);

} catch(PDOException $e) {
    error_log("Event registrations error: " . $e->getMessage());
    header("Location: events.php");
    exit();
}

// Handle actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $registration_id = $_GET['registration_id'] ?? null;
    
    switch($action) {
        case 'delete':
            if ($registration_id) {
                try {
                    $stmt = $db->prepare("DELETE FROM event_registrations WHERE id = ?");
                    $stmt->execute([$registration_id]);
                    $_SESSION['success'] = "Registration removed successfully";
                } catch(PDOException $e) {
                    error_log("Registration deletion error: " . $e->getMessage());
                    $_SESSION['error'] = "Failed to remove registration";
                }
            }
            header("Location: event-registrations.php?id=" . $event_id);
            exit();
            
        case 'export_csv':
            exportRegistrationsCSV($registrations, $event);
            exit();
            
        case 'export_excel':
            exportRegistrationsExcel($registrations, $event);
            exit();
    }
}

// Export to CSV function
function exportRegistrationsCSV($registrations, $event) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $event['title'] . ' - Registrations.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
    
    // Headers
    fputcsv($output, [
        'Type', 'SAP ID', 'Name', 'Email', 'Department', 'Semester', 'Phone', 'Registered At'
    ]);
    
    // Data
    foreach ($registrations as $registration) {
        fputcsv($output, [
            $registration['registrant_type'] ?? 'guest',
            $registration['display_sap_id'] ?? 'N/A',
            $registration['display_name'] ?? 'N/A',
            $registration['display_email'] ?? 'N/A',
            $registration['display_department'] ?? 'N/A',
            $registration['display_semester'] ?? 'N/A',
            $registration['display_phone'] ?? 'N/A',
            date('Y-m-d H:i:s', strtotime($registration['registered_at']))
        ]);
    }
    
    fclose($output);
    exit();
}

// Export to Excel function
function exportRegistrationsExcel($registrations, $event) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $event['title'] . ' - Registrations.xls"');
    
    echo "<table border='1'>";
    echo "<tr>";
    echo "<th>Type</th>";
    echo "<th>SAP ID</th>";
    echo "<th>Name</th>";
    echo "<th>Email</th>";
    echo "<th>Department</th>";
    echo "<th>Semester</th>";
    echo "<th>Phone</th>";
    echo "<th>Registered At</th>";
    echo "</tr>";
    
    foreach ($registrations as $registration) {
        echo "<tr>";
        echo "<td>" . ($registration['registrant_type'] ?? 'guest') . "</td>";
        echo "<td>" . ($registration['display_sap_id'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($registration['display_name'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($registration['display_email'] ?? 'N/A') . "</td>";
        echo "<td>" . ($registration['display_department'] ?? 'N/A') . "</td>";
        echo "<td>" . ($registration['display_semester'] ?? 'N/A') . "</td>";
        echo "<td>" . ($registration['display_phone'] ?? 'N/A') . "</td>";
        echo "<td>" . date('Y-m-d H:i:s', strtotime($registration['registered_at'])) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    exit();
}

$page_title = "Event Registrations - " . $event['title'];
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

        <!-- Main Content -->
        <div class="col-md-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Event Registrations</h1>
                <div>
                    <a href="event-details.php?id=<?php echo $event_id; ?>" class="btn btn-info me-2">
                        <i class="fas fa-eye me-2"></i>View Event
                    </a>
                    <a href="events.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Events
                    </a>
                </div>
            </div>

            <!-- Event Info Card -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-calendar me-2"></i><?php echo $event['title']; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Date:</strong> <?php echo date('F j, Y', strtotime($event['start_date'])); ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Time:</strong> <?php echo date('g:i A', strtotime($event['start_date'])); ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Venue:</strong> <?php echo $event['venue']; ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Registrations:</strong> <?php echo $registered_count; ?>
                            <?php if($event['max_participants']): ?>
                                / <?php echo $event['max_participants']; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Success/Error Messages -->
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

            <!-- Registrations Card -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-users me-2"></i>Registered Candidates (<?php echo $registered_count; ?>)
                    </h5>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-download me-1"></i>Export
                        </button>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="event-registrations.php?id=<?php echo $event_id; ?>&action=export_csv">
                                    <i class="fas fa-file-csv me-2"></i>Export as CSV
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="event-registrations.php?id=<?php echo $event_id; ?>&action=export_excel">
                                    <i class="fas fa-file-excel me-2"></i>Export as Excel
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <?php if(empty($registrations)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Registrations Yet</h5>
                            <p class="text-muted">No one has registered for this event yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="registrationsTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>#</th>
                                        <th>Type</th>
                                        <th>SAP ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Department</th>
                                        <th>Semester</th>
                                        <th>Phone</th>
                                        <th>Registered At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($registrations as $index => $registration): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td>
                                                <?php if(($registration['registrant_type'] ?? 'guest') === 'member'): ?>
                                                    <span class="badge bg-success">Member</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info">Guest</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <code><?php echo $registration['display_sap_id'] ?? 'N/A'; ?></code>
                                            </td>
                                            <td>
                                                <!-- FIXED: Added null check for name -->
                                                <strong><?php echo htmlspecialchars($registration['display_name'] ?? 'N/A'); ?></strong>
                                            </td>
                                            <td>
                                                <?php if($registration['display_email']): ?>
                                                    <a href="mailto:<?php echo $registration['display_email']; ?>">
                                                        <?php echo $registration['display_email']; ?>
                                                    </a>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $registration['display_department'] ?? 'N/A'; ?></td>
                                            <td>
                                                <?php if($registration['display_semester']): ?>
                                                    <span class="badge bg-info">Sem <?php echo $registration['display_semester']; ?></span>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if($registration['display_phone']): ?>
                                                    <a href="tel:<?php echo $registration['display_phone']; ?>">
                                                        <?php echo $registration['display_phone']; ?>
                                                    </a>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php echo date('M j, Y', strtotime($registration['registered_at'])); ?><br>
                                                    <span class="text-muted"><?php echo date('g:i A', strtotime($registration['registered_at'])); ?></span>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                                                        Actions
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#viewRegistrationModal<?php echo $registration['id']; ?>">
                                                                <i class="fas fa-eye me-2"></i>View Details
                                                            </a>
                                                        </li>
                                                        <?php if($registration['display_email']): ?>
                                                        <li>
                                                            <a class="dropdown-item" href="mailto:<?php echo $registration['display_email']; ?>">
                                                                <i class="fas fa-envelope me-2"></i>Send Email
                                                            </a>
                                                        </li>
                                                        <?php endif; ?>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <a class="dropdown-item text-danger" 
                                                               href="event-registrations.php?id=<?php echo $event_id; ?>&action=delete&registration_id=<?php echo $registration['id']; ?>" 
                                                               onclick="return confirm('Are you sure you want to remove this registration?')">
                                                                <i class="fas fa-trash me-2"></i>Remove Registration
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- View Registration Modal -->
                                        <div class="modal fade" id="viewRegistrationModal<?php echo $registration['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Registration Details</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <h6>Personal Information</h6>
                                                                <table class="table table-sm table-borderless">
                                                                    <tr>
                                                                        <th width="40%">Type:</th>
                                                                        <td>
                                                                            <?php if(($registration['registrant_type'] ?? 'guest') === 'member'): ?>
                                                                                <span class="badge bg-success">Society Member</span>
                                                                            <?php else: ?>
                                                                                <span class="badge bg-info">Event Guest</span>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <th>Name:</th>
                                                                        <td><?php echo htmlspecialchars($registration['display_name'] ?? 'N/A'); ?></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <th>SAP ID:</th>
                                                                        <td><code><?php echo $registration['display_sap_id'] ?? 'N/A'; ?></code></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <th>Email:</th>
                                                                        <td>
                                                                            <?php if($registration['display_email']): ?>
                                                                                <a href="mailto:<?php echo $registration['display_email']; ?>">
                                                                                    <?php echo $registration['display_email']; ?>
                                                                                </a>
                                                                            <?php else: ?>
                                                                                N/A
                                                                            <?php endif; ?>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <th>Phone:</th>
                                                                        <td>
                                                                            <?php if($registration['display_phone']): ?>
                                                                                <a href="tel:<?php echo $registration['display_phone']; ?>">
                                                                                    <?php echo $registration['display_phone']; ?>
                                                                                </a>
                                                                            <?php else: ?>
                                                                                N/A
                                                                            <?php endif; ?>
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <h6>Academic Information</h6>
                                                                <table class="table table-sm table-borderless">
                                                                    <tr>
                                                                        <th width="40%">Department:</th>
                                                                        <td><?php echo $registration['display_department'] ?? 'N/A'; ?></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <th>Semester:</th>
                                                                        <td>
                                                                            <?php if($registration['display_semester']): ?>
                                                                                <span class="badge bg-info">Semester <?php echo $registration['display_semester']; ?></span>
                                                                            <?php else: ?>
                                                                                N/A
                                                                            <?php endif; ?>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <th>Registered:</th>
                                                                        <td><?php echo date('F j, Y g:i A', strtotime($registration['registered_at'])); ?></td>
                                                                    </tr>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <?php if($registration['display_email']): ?>
                                                        <a href="mailto:<?php echo $registration['display_email']; ?>" class="btn btn-primary">
                                                            <i class="fas fa-envelope me-2"></i>Send Email
                                                        </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Registration Statistics -->
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="card-title mb-0">Registration Statistics</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-md-3">
                                                <h3 class="text-primary"><?php echo $registered_count; ?></h3>
                                                <p class="text-muted">Total Registrations</p>
                                            </div>
                                            <?php if($event['max_participants']): ?>
                                                <div class="col-md-3">
                                                    <h3 class="text-success"><?php echo max(0, $event['max_participants'] - $registered_count); ?></h3>
                                                    <p class="text-muted">Spots Remaining</p>
                                                </div>
                                                <div class="col-md-3">
                                                    <h3 class="text-info"><?php echo round(($registered_count / $event['max_participants']) * 100, 1); ?>%</h3>
                                                    <p class="text-muted">Capacity Filled</p>
                                                </div>
                                            <?php endif; ?>
                                            <div class="col-md-3">
                                                <h3 class="text-warning">
                                                    <?php 
                                                        $today = new DateTime();
                                                        $event_date = new DateTime($event['start_date']);
                                                        $interval = $today->diff($event_date);
                                                        echo $interval->days;
                                                    ?>
                                                </h3>
                                                <p class="text-muted">Days Until Event</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add DataTables for better table functionality -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTables
    $('#registrationsTable').DataTable({
        "pageLength": 25,
        "order": [[8, 'desc']], // Sort by registration date descending
        "language": {
            "search": "Search registrations:",
            "lengthMenu": "Show _MENU_ registrations per page",
            "zeroRecords": "No matching registrations found",
            "info": "Showing _START_ to _END_ of _TOTAL_ registrations",
            "infoEmpty": "No registrations available",
            "infoFiltered": "(filtered from _MAX_ total registrations)"
        }
    });
});
</script>

<style>
.dataTables_wrapper .dataTables_filter {
    float: right;
    margin-bottom: 1rem;
}

.dataTables_wrapper .dataTables_length {
    float: left;
    margin-bottom: 1rem;
}

.table th {
    border-top: none;
    font-weight: 600;
}

.code-sap {
    font-family: 'Courier New', monospace;
    background: #f8f9fa;
    padding: 2px 6px;
    border-radius: 3px;
    font-weight: bold;
}
</style>

<?php require_once '../includes/footer.php'; ?>