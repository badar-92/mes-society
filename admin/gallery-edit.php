<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['super_admin', 'department_head', 'media_head']);

$db = Database::getInstance()->getConnection();

function ensureGalleryTables($db) {
    try {
        $stmt = $db->query("SHOW TABLES LIKE 'gallery_albums'");
        if ($stmt->rowCount() == 0) {
            $db->exec("CREATE TABLE IF NOT EXISTS `gallery_albums` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `title` VARCHAR(255) NOT NULL,
                `description` TEXT,
                `cover_photo` VARCHAR(255) NULL,
                `event_id` INT NULL,
                `status` ENUM('active', 'inactive') DEFAULT 'active',
                `created_by` INT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } else {
            $colCheck = $db->query("SHOW COLUMNS FROM `gallery_albums` LIKE 'cover_photo'");
            if ($colCheck->rowCount() == 0) {
                $db->exec("ALTER TABLE `gallery_albums` ADD COLUMN `cover_photo` VARCHAR(255) NULL DEFAULT NULL AFTER `description`");
            }
        }
    } catch(PDOException $e) {
        error_log("Table creation error: " . $e->getMessage());
    }
}
ensureGalleryTables($db);

$albumId = $_GET['id'] ?? null;
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

// Handle remove cover
if (isset($_GET['remove_cover']) && $albumId) {
    $stmt = $db->prepare("UPDATE gallery_albums SET cover_photo = NULL WHERE id = ?");
    $stmt->execute([$albumId]);
    $_SESSION['success'] = "Album cover removed";
    header("Location: gallery-edit.php?id=" . $albumId);
    exit();
}

// Handle album update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $event_id = $_POST['event_id'] ?? null;
    $status = $_POST['status'] ?? 'active';
    
    if (empty($title)) {
        $_SESSION['error'] = "Album title is required";
        header("Location: gallery-edit.php?id=" . $albumId);
        exit();
    }
    
    try {
        $stmt = $db->prepare("UPDATE gallery_albums SET title = ?, description = ?, event_id = ?, status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$title, $description, $event_id, $status, $albumId]);
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
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-3 d-none d-md-block"><div class="desktop-sidebar"><?php include 'sidebar.php'; ?></div></div>
        <div class="col-md-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Edit Album: <?php echo htmlspecialchars($album['title']); ?></h1>
                <a href="gallery.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Back to Gallery</a>
            </div>

            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Album Details</h5></div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3"><label for="title" class="form-label">Album Title *</label><input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($album['title']); ?>" required></div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3"><label for="status" class="form-label">Status</label><select class="form-select" id="status" name="status"><option value="active" <?php echo $album['status'] === 'active' ? 'selected' : ''; ?>>Active</option><option value="inactive" <?php echo $album['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option></select></div>
                            </div>
                        </div>
                        <div class="mb-3"><label for="description" class="form-label">Description</label><textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($album['description']); ?></textarea></div>
                        
                        <!-- Current Cover Photo Section -->
                        <div class="mb-3">
                            <label class="form-label">Current Cover Photo</label>
                            <?php if($album['cover_photo']): ?>
                                <div class="d-flex align-items-center gap-3">
                                    <img src="<?php echo SITE_URL . '/uploads/gallery/' . $album['cover_photo']; ?>" alt="Cover" style="height: 80px; width: auto; object-fit: cover; border-radius: 4px;">
                                    <a href="gallery-view.php?id=<?php echo $albumId; ?>" class="btn btn-sm btn-outline-secondary">Change in Album View</a>
                                    <a href="?remove_cover=1" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove current cover?')">Remove Cover</a>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No cover photo set. <a href="gallery-view.php?id=<?php echo $albumId; ?>">Set one from album view</a></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="event_id" class="form-label">Associated Event (Optional)</label>
                            <select class="form-select" id="event_id" name="event_id">
                                <option value="">No Event Association</option>
                                <?php foreach($events as $event): ?>
                                    <option value="<?php echo $event['id']; ?>" <?php echo $album['event_id'] == $event['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($event['title']); ?> (<?php echo date('M j, Y', strtotime($event['start_date'])); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Link this album to a specific event</small>
                        </div>
                        <div class="text-end"><button type="submit" class="btn btn-accent btn-lg"><i class="fas fa-save me-2"></i>Update Album</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>