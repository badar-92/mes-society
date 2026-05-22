<?php
    ob_start();
require_once '../includes/session.php';
require_once '../includes/functions.php';  // <-- ADDED

$session = new SessionManager();
$session->requireLogin();
// If you need multiple roles, check each separately or modify the method.
// For now, we use the original role check for super_admin only.
$session->requireRole('super_admin');

$page_title = "Manage Certificates";
$hidePublicNavigation = true;
require_once '../includes/header.php';

$db = Database::getInstance()->getConnection();
$functions = new Functions();

// Handle delete request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        // Get file paths before deletion
        $stmt = $db->prepare("SELECT file_path, thumbnail_path FROM certificates WHERE id = ?");
        $stmt->execute([$id]);
        $cert = $stmt->fetch();
        if ($cert) {
            // Delete files
            if (file_exists($cert['file_path'])) unlink($cert['file_path']);
            if ($cert['thumbnail_path'] && file_exists($cert['thumbnail_path'])) unlink($cert['thumbnail_path']);
            
            // Delete record
            $stmt = $db->prepare("DELETE FROM certificates WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = "Certificate deleted successfully.";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting certificate: " . $e->getMessage();
    }
    header("Location: certificates.php");
    exit;
}

// Build search query
$search = $_GET['search'] ?? '';
$filter_event = $_GET['event'] ?? '';
$filter_competition = $_GET['competition'] ?? '';

$sql = "SELECT c.*, e.title as event_title, comp.title as competition_title 
        FROM certificates c
        LEFT JOIN events e ON c.event_id = e.id
        LEFT JOIN competitions comp ON c.competition_id = comp.id
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (c.name LIKE ? OR c.sap_id LIKE ? OR c.serial_number LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}
if (!empty($filter_event)) {
    $sql .= " AND c.event_id = ?";
    $params[] = $filter_event;
}
if (!empty($filter_competition)) {
    $sql .= " AND c.competition_id = ?";
    $params[] = $filter_competition;
}

$sql .= " ORDER BY c.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$certificates = $stmt->fetchAll();

// Get events and competitions for filter dropdowns
$events = $db->query("SELECT id, title FROM events WHERE status = 'published' ORDER BY start_date DESC")->fetchAll();
$competitions = $db->query("SELECT id, title FROM competitions WHERE status = 'published' ORDER BY start_date DESC")->fetchAll();
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
            <!-- Mobile Header -->
            <div class="d-md-none d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                <h4 class="mb-0">Manage Certificates</h4>
                <button class="btn btn-accent" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileSidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Certificates</h1>
                <a href="certificates-add.php" class="btn btn-accent">
                    <i class="fas fa-plus me-2"></i>Upload New Certificate
                </a>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Search and Filter Form -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <div class="col-md-4">
                            <input type="text" class="form-control" name="search" placeholder="Search by name, SAP ID, or serial number" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="event">
                                <option value="">All Events</option>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?php echo $event['id']; ?>" <?php echo $filter_event == $event['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($event['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="competition">
                                <option value="">All Competitions</option>
                                <?php foreach ($competitions as $comp): ?>
                                    <option value="<?php echo $comp['id']; ?>" <?php echo $filter_competition == $comp['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($comp['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Certificates Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Serial #</th>
                                    <th>Name</th>
                                    <th>SAP ID</th>
                                    <th>Event/Competition</th>
                                    <th>Uploaded</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($certificates)): ?>
                                    <tr><td colspan="6" class="text-center">No certificates found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($certificates as $cert): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($cert['serial_number']); ?></td>
                                            <td><?php echo htmlspecialchars($cert['name']); ?></td>
                                            <td><?php echo htmlspecialchars($cert['sap_id'] ?: '-'); ?></td>
                                            <td>
                                                <?php 
                                                if ($cert['event_title']) {
                                                    echo '<span class="badge bg-info">Event: ' . htmlspecialchars($cert['event_title']) . '</span>';
                                                } elseif ($cert['competition_title']) {
                                                    echo '<span class="badge bg-warning">Competition: ' . htmlspecialchars($cert['competition_title']) . '</span>';
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($cert['created_at'])); ?></td>
                                            <td>
                                                <a href="<?php echo SITE_URL . '/' . $cert['file_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="certificates-edit.php?id=<?php echo $cert['id']; ?>" class="btn btn-sm btn-outline-success" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?delete=<?php echo $cert['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this certificate?');" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
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

<?php require_once '../includes/footer.php'; ?> <!-- FIXED: removed parentheses -->