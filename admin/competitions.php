<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['super_admin', 'department_head', 'competition_head']);

$db = Database::getInstance()->getConnection();

// Check and create competitions table if it doesn't exist
try {
    $checkTable = $db->query("SHOW TABLES LIKE 'competitions'");
    if ($checkTable->rowCount() == 0) {
        // Create competitions table
        $createTable = $db->exec("
            CREATE TABLE `competitions` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `title` varchar(255) NOT NULL,
                `description` text,
                `competition_type` enum('individual','team') DEFAULT 'individual',
                `category` varchar(100) DEFAULT NULL,
                `start_date` datetime NOT NULL,
                `end_date` datetime NOT NULL,
                `venue` varchar(255) NOT NULL,
                `max_participants` int(11) DEFAULT NULL,
                `registration_deadline` datetime DEFAULT NULL,
                `prize` text,
                `rules` text,
                `eligibility` text,
                `banner_image` varchar(255) DEFAULT NULL,
                `registration_open` tinyint(1) DEFAULT '1',
                `status` enum('draft','published','cancelled') DEFAULT 'draft',
                `created_by` int(11) DEFAULT NULL,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        
        if ($createTable === false) {
            throw new Exception("Failed to create competitions table. Please check database permissions.");
        }
    } else {
        // Check for required columns and add them if missing
        $requiredColumns = [
            'competition_type' => "ALTER TABLE competitions ADD COLUMN competition_type ENUM('individual','team') DEFAULT 'individual'",
            'category' => "ALTER TABLE competitions ADD COLUMN category VARCHAR(100) DEFAULT NULL",
            'registration_deadline' => "ALTER TABLE competitions ADD COLUMN registration_deadline DATETIME DEFAULT NULL",
            'prize' => "ALTER TABLE competitions ADD COLUMN prize TEXT DEFAULT NULL",
            'rules' => "ALTER TABLE competitions ADD COLUMN rules TEXT DEFAULT NULL",
            'eligibility' => "ALTER TABLE competitions ADD COLUMN eligibility TEXT DEFAULT NULL",
            'banner_image' => "ALTER TABLE competitions ADD COLUMN banner_image VARCHAR(255) DEFAULT NULL",
            'registration_open' => "ALTER TABLE competitions ADD COLUMN registration_open TINYINT(1) DEFAULT 1",
            'status' => "ALTER TABLE competitions ADD COLUMN status ENUM('draft','published','cancelled') DEFAULT 'draft'",
            'created_by' => "ALTER TABLE competitions ADD COLUMN created_by INT(11) DEFAULT NULL",
            'created_at' => "ALTER TABLE competitions ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
            'updated_at' => "ALTER TABLE competitions ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
        ];
        
        foreach ($requiredColumns as $column => $sql) {
            $checkColumn = $db->query("SHOW COLUMNS FROM competitions LIKE '$column'");
            if ($checkColumn->rowCount() == 0) {
                $db->exec($sql);
            }
        }
    }
} catch (PDOException $e) {
    error_log("Competitions table check error: " . $e->getMessage());
    $_SESSION['error'] = "Database configuration error: " . $e->getMessage();
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}

// Handle actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $competitionId = $_GET['id'] ?? null;
    
    switch($action) {
        case 'publish':
            if ($competitionId) {
                $stmt = $db->prepare("UPDATE competitions SET status = 'published' WHERE id = ?");
                $stmt->execute([$competitionId]);
                $_SESSION['success'] = "Competition published successfully";
            }
            break;
            
        case 'unpublish':
            if ($competitionId) {
                $stmt = $db->prepare("UPDATE competitions SET status = 'draft' WHERE id = ?");
                $stmt->execute([$competitionId]);
                $_SESSION['success'] = "Competition unpublished successfully";
            }
            break;
            
        case 'delete':
            if ($competitionId) {
                $stmt = $db->prepare("DELETE FROM competitions WHERE id = ?");
                $stmt->execute([$competitionId]);
                $_SESSION['success'] = "Competition deleted successfully";
            }
            break;
            
        case 'close_registration':
            if ($competitionId) {
                $stmt = $db->prepare("UPDATE competitions SET registration_open = 0 WHERE id = ?");
                $stmt->execute([$competitionId]);
                $_SESSION['success'] = "Registration closed successfully";
            }
            break;
            
        case 'open_registration':
            if ($competitionId) {
                $stmt = $db->prepare("UPDATE competitions SET registration_open = 1 WHERE id = ?");
                $stmt->execute([$competitionId]);
                $_SESSION['success'] = "Registration opened successfully";
            }
            break;
    }
    
    header("Location: competitions.php");
    exit();
}

// Get all competitions
$competitions = [];
try {
    $query = "SELECT c.*, u.name as created_by_name 
              FROM competitions c 
              LEFT JOIN users u ON c.created_by = u.id 
              ORDER BY c.created_at DESC";
    $stmt = $db->query($query);
    $competitions = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Competitions query error: " . $e->getMessage());
    $competitions = [];
}

// Get competition statistics
$compStats = [
    'total' => 0,
    'published' => 0,
    'draft' => 0,
    'active' => 0
];

try {
    $stmt = $db->query("SELECT COUNT(*) FROM competitions");
    $compStats['total'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM competitions WHERE status = 'published'");
    $compStats['published'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM competitions WHERE status = 'draft'");
    $compStats['draft'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM competitions WHERE registration_open = 1 AND status = 'published'");
    $compStats['active'] = $stmt->fetchColumn();
} catch(PDOException $e) {
    error_log("Competition stats error: " . $e->getMessage());
}

$page_title = "Manage Competitions";
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
                    <h4 class="mb-0">Manage Competitions</h4>
                    <small class="text-muted">Competition Management</small>
                </div>
                <button class="btn btn-accent" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileSidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <div class="d-none d-md-flex justify-content-between align-items-center mb-4">
                <h1>Manage Competitions</h1>
                <a href="competitions-create.php" class="btn btn-accent">
                    <i class="fas fa-plus me-2"></i>Create Competition
                </a>
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

            <!-- Competition Stats -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h2 class="card-text"><?php echo $compStats['total']; ?></h2>
                            <h5 class="card-title">Total</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h2 class="card-text"><?php echo $compStats['published']; ?></h2>
                            <h5 class="card-title">Published</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <h2 class="card-text"><?php echo $compStats['draft']; ?></h2>
                            <h5 class="card-title">Drafts</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h2 class="card-text"><?php echo $compStats['active']; ?></h2>
                            <h5 class="card-title">Active</h5>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Competitions Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-trophy me-2"></i>All Competitions
                    </h5>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-filter me-1"></i>Filter
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="?filter=all">All Competitions</a></li>
                            <li><a class="dropdown-item" href="?filter=published">Published</a></li>
                            <li><a class="dropdown-item" href="?filter=draft">Drafts</a></li>
                            <li><a class="dropdown-item" href="?filter=active">Active Registrations</a></li>
                            <li><a class="dropdown-item" href="?filter=closed">Closed Registrations</a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Competition</th>
                                    <th>Type</th>
                                    <th>Dates</th>
                                    <th>Participants</th>
                                    <th>Registration</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($competitions)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            <i class="fas fa-trophy fa-3x mb-3"></i><br>
                                            No competitions found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($competitions as $comp): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if($comp['banner_image']): ?>
                                                        <img src="<?php echo SITE_URL . '/uploads/competitions/' . $comp['banner_image']; ?>" 
                                                             alt="<?php echo $comp['title']; ?>" 
                                                             class="rounded me-2" 
                                                             style="width: 40px; height: 40px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="rounded bg-secondary d-flex align-items-center justify-content-center me-2" 
                                                             style="width: 40px; height: 40px;">
                                                            <i class="fas fa-trophy text-white"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($comp['title']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">Prize: <?php echo $comp['prize'] ?? 'N/A'; ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark">
                                                    <?php echo $comp['competition_type'] === 'team' ? 'Team' : 'Individual'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small>
                                                    <strong>Start:</strong> <?php echo date('M j, Y', strtotime($comp['start_date'])); ?><br>
                                                    <strong>End:</strong> <?php echo date('M j, Y', strtotime($comp['end_date'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php
                                                $participants = 0;
                                                try {
                                                    // Check if competition_registrations table exists
                                                    $checkTable = $db->query("SHOW TABLES LIKE 'competition_registrations'");
                                                    if ($checkTable->rowCount() > 0) {
                                                        $stmt = $db->prepare("SELECT COUNT(*) FROM competition_registrations WHERE competition_id = ?");
                                                        $stmt->execute([$comp['id']]);
                                                        $participants = $stmt->fetchColumn();
                                                    }
                                                } catch(PDOException $e) {
                                                    error_log("Participants count error: " . $e->getMessage());
                                                }
                                                ?>
                                                <small><?php echo $participants; ?>/<?php echo $comp['max_participants'] ?? '∞'; ?></small>
                                            </td>
                                            <td>
                                                <?php if($comp['registration_open']): ?>
                                                    <span class="badge bg-success">Open</span>
                                                    <br>
                                                    <small class="text-muted">
                                                        Until: <?php echo date('M j', strtotime($comp['registration_deadline'])); ?>
                                                    </small>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Closed</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    switch($comp['status']) {
                                                        case 'published': echo 'success'; break;
                                                        case 'draft': echo 'warning'; break;
                                                        case 'cancelled': echo 'danger'; break;
                                                        default: echo 'secondary';
                                                    }
                                                ?>">
                                                    <?php echo ucfirst($comp['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                                                        Actions
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item" href="../public/competition-details.php?id=<?php echo $comp['id']; ?>" target="_blank">
                                                                <i class="fas fa-eye me-2"></i>View Public
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item" href="competitions-edit.php?id=<?php echo $comp['id']; ?>">
                                                                <i class="fas fa-edit me-2"></i>Edit
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item" href="competition-participants.php?id=<?php echo $comp['id']; ?>">
                                                                <i class="fas fa-users me-2"></i>Participants
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item" href="competition-results.php?id=<?php echo $comp['id']; ?>">
                                                                <i class="fas fa-chart-line me-2"></i>Results
                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <?php if($comp['status'] === 'published'): ?>
                                                            <li>
                                                                <a class="dropdown-item text-warning" href="?action=unpublish&id=<?php echo $comp['id']; ?>" 
                                                                   onclick="return confirm('Unpublish <?php echo $comp['title']; ?>?')">
                                                                    <i class="fas fa-eye-slash me-2"></i>Unpublish
                                                                </a>
                                                            </li>
                                                        <?php else: ?>
                                                            <li>
                                                                <a class="dropdown-item text-success" href="?action=publish&id=<?php echo $comp['id']; ?>" 
                                                                   onclick="return confirm('Publish <?php echo $comp['title']; ?>?')">
                                                                    <i class="fas fa-eye me-2"></i>Publish
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                        <?php if($comp['registration_open']): ?>
                                                            <li>
                                                                <a class="dropdown-item text-warning" href="?action=close_registration&id=<?php echo $comp['id']; ?>" 
                                                                   onclick="return confirm('Close registration for <?php echo $comp['title']; ?>?')">
                                                                    <i class="fas fa-lock me-2"></i>Close Registration
                                                                </a>
                                                            </li>
                                                        <?php else: ?>
                                                            <li>
                                                                <a class="dropdown-item text-success" href="?action=open_registration&id=<?php echo $comp['id']; ?>" 
                                                                   onclick="return confirm('Open registration for <?php echo $comp['title']; ?>?')">
                                                                    <i class="fas fa-unlock me-2"></i>Open Registration
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="?action=delete&id=<?php echo $comp['id']; ?>" 
                                                               onclick="return confirm('Are you sure you want to delete <?php echo $comp['title']; ?>? This action cannot be undone.')">
                                                                <i class="fas fa-trash me-2"></i>Delete
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
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

<!-- Mobile Floating Action Button -->
<div class="fab-container d-md-none">
    <a href="competitions-create.php" class="fab btn btn-accent rounded-circle">
        <i class="fas fa-plus"></i>
    </a>
</div>

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
</style>

<?php require_once '../includes/footer.php'; ?>