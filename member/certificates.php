<?php
require_once '../includes/session.php';
require_once '../includes/functions.php';  // <-- ADDED
$session = new SessionManager();
$session->requireLogin();
$session->requireRole('member');

$page_title = "My Certificates";
$hidePublicNavigation = true;
require_once '../includes/header.php';

$user = $session->getCurrentUser();
$db = Database::getInstance()->getConnection();
$functions = new Functions();

// Fetch certificates where sap_id matches logged-in user's sap_id
$stmt = $db->prepare("SELECT c.*, e.title as event_title, comp.title as competition_title 
                      FROM certificates c
                      LEFT JOIN events e ON c.event_id = e.id
                      LEFT JOIN competitions comp ON c.competition_id = comp.id
                      WHERE c.sap_id = ?
                      ORDER BY c.created_at DESC");
$stmt->execute([$user['sap_id']]);
$certificates = $stmt->fetchAll();
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Desktop Sidebar -->
        <div class="col-md-3 d-none d-md-block">
            <div class="desktop-sidebar">
                <?php include 'sidebar.php'; ?>
            </div>
        </div>

        <!-- Mobile Offcanvas -->
        <div class="offcanvas offcanvas-start d-md-none" tabindex="-1" id="memberMobileSidebar">
            <div class="offcanvas-header bg-primary text-white">
                <h5 class="offcanvas-title">Member Menu</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
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
                    <h4 class="mb-0">My Certificates</h4>
                    <small class="text-muted"><?php echo $user['name']; ?></small>
                </div>
                <button class="btn btn-accent" type="button" data-bs-toggle="offcanvas" data-bs-target="#memberMobileSidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <div class="d-none d-md-flex justify-content-between align-items-center mb-4">
                <h1>My Certificates</h1>
            </div>

            <?php if (empty($certificates)): ?>
                <div class="alert alert-info">No certificates found for your SAP ID.</div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($certificates as $cert): ?>
                        <div class="col-md-4 col-sm-6 mb-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-img-top text-center p-3" style="height: 180px; overflow: hidden;">
                                    <?php if ($cert['thumbnail_path']): ?>
                                        <img src="<?php echo SITE_URL . '/' . $cert['thumbnail_path']; ?>" alt="Certificate Thumbnail" class="img-fluid" style="max-height: 100%;">
                                    <?php else: ?>
                                        <i class="fas fa-file-pdf fa-4x text-danger"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($cert['name']); ?></h5>
                                    <p class="card-text">
                                        <small class="text-muted">Serial: <?php echo htmlspecialchars($cert['serial_number']); ?></small><br>
                                        <?php if ($cert['event_title']): ?>
                                            <span class="badge bg-info">Event: <?php echo htmlspecialchars($cert['event_title']); ?></span>
                                        <?php elseif ($cert['competition_title']): ?>
                                            <span class="badge bg-warning">Competition: <?php echo htmlspecialchars($cert['competition_title']); ?></span>
                                        <?php endif; ?>
                                    </p>
                                    <a href="<?php echo SITE_URL . '/' . $cert['file_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary w-100">Download</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>