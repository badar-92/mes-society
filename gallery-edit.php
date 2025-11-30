<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['super_admin', 'department_head', 'media_head']);

$db = Database::getInstance()->getConnection();

// Check and create tables if they don't exist
createGalleryTablesIfNotExist($db);

$albumId = $_GET['id'] ?? null;

// Get album details
$album = null;
if ($albumId) {
    try {
        $stmt = $db->prepare("SELECT * FROM gallery_albums WHERE id = ?");
        $stmt->execute([$albumId]);
        $album = $stmt->fetch();
    } catch(PDOException $e) {
        error_log("Album fetch error: " . $e->getMessage());
        $_SESSION['error'] = "Error loading album: " . $e->getMessage();
    }
}

if (!$album) {
    $_SESSION['error'] = "Album not found";
    header("Location: gallery.php");
    exit();
}

// Handle album update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $event_id = $_POST['event_id'] ?? null;
    $status = $_POST['status'] ?? 'active';
    
    // Validate input
    if (empty($title)) {
        $_SESSION['error'] = "Album title is required";
        header("Location: gallery-edit.php?id=" . $albumId);
        exit();
    }
    
    try {
        // Update album
        $stmt = $db->prepare("
            UPDATE gallery_albums 
            SET title = ?, description = ?, event_id = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $title,
            $description,
            $event_id,
            $status,
            $albumId
        ]);
        
        $_SESSION['success'] = "Album updated successfully!";
        header("Location: gallery.php");
        exit();
        
    } catch(PDOException $e) {
        error_log("Album update error: " . $e->getMessage());
        $_SESSION['error'] = "Failed to update album. Please try again.";
        header("Location: gallery-edit.php?id=" . $albumId);
        exit();
    }
}

// Get events for association
$events = [];
try {
    $stmt = $db->query("SELECT id, title, start_date FROM events WHERE start_date >= NOW() ORDER BY start_date");
    $events = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Events query error: " . $e->getMessage());
}

$page_title = "Edit Album - " . htmlspecialchars($album['title']);
require_once '../includes/header.php';

function createGalleryTablesIfNotExist($db) {
    try {
        $stmt = $db->query("SHOW TABLES LIKE 'gallery_albums'");
        if ($stmt->rowCount() == 0) {
            $db->exec("CREATE TABLE IF NOT EXISTS `gallery_albums` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `title` VARCHAR(255) NOT NULL,
                `description` TEXT,
                `event_id` INT NULL,
                `status` ENUM('active', 'inactive') DEFAULT 'active',
                `created_by` INT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    } catch(PDOException $e) {
        error_log("Table creation error: " . $e->getMessage());
    }
}
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
                <h1>Edit Album: <?php echo htmlspecialchars($album['title']); ?></h1>
                <a href="gallery.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Gallery
                </a>
            </div>

            <!-- Messages -->
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

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Album Details</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Album Title *</label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($album['title']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="active" <?php echo $album['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $album['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($album['description']); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="event_id" class="form-label">Associated Event (Optional)</label>
                            <select class="form-select" id="event_id" name="event_id">
                                <option value="">No Event Association</option>
                                <?php foreach($events as $event): ?>
                                    <option value="<?php echo $event['id']; ?>" <?php echo $album['event_id'] == $event['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($event['title']); ?> (<?php echo date('M j, Y', strtotime($event['start_date'])); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Link this album to a specific event</small>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-accent btn-lg">
                                <i class="fas fa-save me-2"></i>Update Album
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>