<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['super_admin', 'department_head', 'media_head']);

$db = Database::getInstance()->getConnection();

// Ensure tables and cover column exist
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
        $stmt = $db->query("SHOW TABLES LIKE 'gallery_photos'");
        if ($stmt->rowCount() == 0) {
            $db->exec("CREATE TABLE IF NOT EXISTS `gallery_photos` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `album_id` INT NOT NULL,
                `image_path` VARCHAR(255) NOT NULL,
                `caption` TEXT,
                `uploaded_by` INT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    } catch(PDOException $e) {
        error_log("Table setup error: " . $e->getMessage());
    }
}
ensureGalleryTables($db);

$albumId = $_GET['id'] ?? null;

// Handle actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    switch($action) {
        case 'delete_photo':
            $photoId = $_GET['photo_id'] ?? null;
            if ($photoId && $albumId) {
                $stmt = $db->prepare("SELECT image_path, album_id FROM gallery_photos WHERE id = ?");
                $stmt->execute([$photoId]);
                $photo = $stmt->fetch();
                if ($photo) {
                    $photoPath = '../uploads/gallery/' . $photo['image_path'];
                    if (file_exists($photoPath)) unlink($photoPath);
                    $stmt = $db->prepare("DELETE FROM gallery_photos WHERE id = ?");
                    $stmt->execute([$photoId]);
                    
                    // If deleted photo was cover, clear it
                    $stmt = $db->prepare("SELECT cover_photo FROM gallery_albums WHERE id = ?");
                    $stmt->execute([$photo['album_id']]);
                    $albumCover = $stmt->fetchColumn();
                    if ($albumCover == $photo['image_path']) {
                        $stmt = $db->prepare("UPDATE gallery_albums SET cover_photo = NULL WHERE id = ?");
                        $stmt->execute([$photo['album_id']]);
                    }
                    $_SESSION['success'] = "Photo deleted successfully";
                }
            }
            header("Location: gallery-view.php?id=" . $albumId);
            exit();
            break;
            
        case 'set_cover':
            $photoId = $_GET['photo_id'] ?? null;
            if ($photoId && $albumId) {
                $stmt = $db->prepare("SELECT image_path FROM gallery_photos WHERE id = ? AND album_id = ?");
                $stmt->execute([$photoId, $albumId]);
                $imagePath = $stmt->fetchColumn();
                if ($imagePath) {
                    $stmt = $db->prepare("UPDATE gallery_albums SET cover_photo = ? WHERE id = ?");
                    $stmt->execute([$imagePath, $albumId]);
                    $_SESSION['success'] = "Album cover updated successfully";
                } else {
                    $_SESSION['error'] = "Photo not found in this album";
                }
            }
            header("Location: gallery-view.php?id=" . $albumId);
            exit();
            break;
            
        case 'remove_cover':
            if ($albumId) {
                $stmt = $db->prepare("UPDATE gallery_albums SET cover_photo = NULL WHERE id = ?");
                $stmt->execute([$albumId]);
                $_SESSION['success'] = "Album cover removed";
            }
            header("Location: gallery-view.php?id=" . $albumId);
            exit();
            break;
    }
}

// Get album details
$album = null;
$photos = [];
if ($albumId) {
    try {
        $stmt = $db->prepare("SELECT a.*, u.name as created_by_name FROM gallery_albums a LEFT JOIN users u ON a.created_by = u.id WHERE a.id = ?");
        $stmt->execute([$albumId]);
        $album = $stmt->fetch();
        if ($album) {
            $stmt = $db->prepare("SELECT * FROM gallery_photos WHERE album_id = ? ORDER BY created_at DESC");
            $stmt->execute([$albumId]);
            $photos = $stmt->fetchAll();
        }
    } catch(PDOException $e) {
        error_log("Album view error: " . $e->getMessage());
        $_SESSION['error'] = "Error loading album: " . $e->getMessage();
    }
}

if (!$album) {
    $_SESSION['error'] = "Album not found";
    header("Location: gallery.php");
    exit();
}

$page_title = "View Album - " . htmlspecialchars($album['title']);
require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-3 d-none d-md-block"><div class="desktop-sidebar"><?php include 'sidebar.php'; ?></div></div>
        <div class="col-md-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><?php echo htmlspecialchars($album['title']); ?></h1>
                <div>
                    <a href="gallery-upload.php?album_id=<?php echo $albumId; ?>" class="btn btn-accent me-2"><i class="fas fa-plus me-2"></i>Add Photos</a>
                    <a href="gallery.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Back to Gallery</a>
                </div>
            </div>

            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>

            <!-- Album Info Card -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h5 class="card-title">Album Description</h5>
                            <?php if($album['description']): ?>
                                <p class="card-text"><?php echo htmlspecialchars($album['description']); ?></p>
                            <?php else: ?>
                                <p class="card-text text-muted">No description provided.</p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex flex-column">
                                <small class="text-muted">Status: <span class="badge bg-<?php echo $album['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($album['status']); ?></span></small>
                                <small class="text-muted">Photos: <strong><?php echo count($photos); ?></strong></small>
                                <small class="text-muted">Created: <?php echo date('M j, Y', strtotime($album['created_at'])); ?></small>
                                <?php if($album['created_by_name']): ?>
                                    <small class="text-muted">By: <?php echo $album['created_by_name']; ?></small>
                                <?php endif; ?>
                                <?php if($album['cover_photo']): ?>
                                    <small class="text-muted mt-2"><i class="fas fa-image"></i> Current cover set</small>
                                <?php else: ?>
                                    <small class="text-muted mt-2"><i class="fas fa-image"></i> No cover photo set</small>
                                <?php endif; ?>
                                <?php if($album['cover_photo']): ?>
                                    <a href="?action=remove_cover&id=<?php echo $albumId; ?>" class="btn btn-sm btn-outline-danger mt-2" onclick="return confirm('Remove the current cover?')"><i class="fas fa-times"></i> Remove Cover</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Photos Grid -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-images me-2"></i>Photos (<?php echo count($photos); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if(empty($photos)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-camera fa-4x mb-3"></i>
                            <h4>No Photos Yet</h4>
                            <p class="mb-4">This album doesn't have any photos yet.</p>
                            <a href="gallery-upload.php?album_id=<?php echo $albumId; ?>" class="btn btn-accent"><i class="fas fa-upload me-2"></i>Upload Photos</a>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach($photos as $photo): ?>
                                <div class="col-6 col-md-4 col-lg-3 mb-4">
                                    <div class="card h-100">
                                        <img src="<?php echo SITE_URL . '/uploads/gallery/' . $photo['image_path']; ?>" class="card-img-top" alt="<?php echo $photo['caption'] ? htmlspecialchars($photo['caption']) : 'Photo'; ?>" style="height: 200px; object-fit: cover;">
                                        <div class="card-body">
                                            <?php if($photo['caption']): ?>
                                                <p class="card-text small"><?php echo htmlspecialchars($photo['caption']); ?></p>
                                            <?php else: ?>
                                                <p class="card-text small text-muted">No caption</p>
                                            <?php endif; ?>
                                            <?php if($album['cover_photo'] == $photo['image_path']): ?>
                                                <span class="badge bg-success"><i class="fas fa-star"></i> Cover Photo</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-footer bg-transparent">
                                            <div class="btn-group w-100">
                                                <a href="<?php echo SITE_URL . '/uploads/gallery/' . $photo['image_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-expand"></i></a>
                                                <a href="?action=set_cover&photo_id=<?php echo $photo['id']; ?>&id=<?php echo $albumId; ?>" class="btn btn-sm btn-outline-success" onclick="return confirm('Set this photo as the album cover?')"><i class="fas fa-image"></i> Cover</a>
                                                <a href="?action=delete_photo&photo_id=<?php echo $photo['id']; ?>&id=<?php echo $albumId; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this photo?')"><i class="fas fa-trash"></i></a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>