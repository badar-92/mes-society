<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['super_admin', 'department_head', 'media_head']);

$db = Database::getInstance()->getConnection();

// Helper function to truncate description (same as before)
function truncateDescription($text, $length = 100) {
    if (empty($text)) return '';
    $plainText = strip_tags($text);
    if (mb_strlen($plainText, 'UTF-8') > $length) {
        $truncated = mb_substr($plainText, 0, $length, 'UTF-8') . '...';
    } else {
        $truncated = $plainText;
    }
    return htmlspecialchars($truncated, ENT_QUOTES, 'UTF-8');
}

// Ensure tables and column exist
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        } else {
            // Check if cover_photo column exists, add if not
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }
    } catch(PDOException $e) {
        error_log("Table setup error: " . $e->getMessage());
    }
}
ensureGalleryTables($db);

// Handle actions (delete album / delete photo)
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $albumId = $_GET['id'] ?? null;
    
    switch($action) {
        case 'delete_album':
            if ($albumId) {
                // Get all photos to delete files
                $stmt = $db->prepare("SELECT image_path FROM gallery_photos WHERE album_id = ?");
                $stmt->execute([$albumId]);
                $photos = $stmt->fetchAll();
                foreach ($photos as $photo) {
                    $photoPath = '../uploads/gallery/' . $photo['image_path'];
                    if (file_exists($photoPath)) unlink($photoPath);
                }
                $stmt = $db->prepare("DELETE FROM gallery_photos WHERE album_id = ?");
                $stmt->execute([$albumId]);
                $stmt = $db->prepare("DELETE FROM gallery_albums WHERE id = ?");
                $stmt->execute([$albumId]);
                $_SESSION['success'] = "Album and all photos deleted successfully";
            }
            break;
            
        case 'delete_photo':
            $photoId = $_GET['photo_id'] ?? null;
            if ($photoId) {
                $stmt = $db->prepare("SELECT image_path, album_id FROM gallery_photos WHERE id = ?");
                $stmt->execute([$photoId]);
                $photo = $stmt->fetch();
                if ($photo) {
                    $photoPath = '../uploads/gallery/' . $photo['image_path'];
                    if (file_exists($photoPath)) unlink($photoPath);
                    $stmt = $db->prepare("DELETE FROM gallery_photos WHERE id = ?");
                    $stmt->execute([$photoId]);
                    
                    // If deleted photo was the album cover, clear cover_photo
                    $stmt = $db->prepare("SELECT cover_photo FROM gallery_albums WHERE id = ?");
                    $stmt->execute([$photo['album_id']]);
                    $album = $stmt->fetch();
                    if ($album && $album['cover_photo'] == $photo['image_path']) {
                        $stmt = $db->prepare("UPDATE gallery_albums SET cover_photo = NULL WHERE id = ?");
                        $stmt->execute([$photo['album_id']]);
                    }
                    $_SESSION['success'] = "Photo deleted successfully";
                }
            }
            break;
    }
    header("Location: gallery.php");
    exit();
}

// Get all albums with photo counts and cover info
$albums = [];
try {
    $query = "SELECT a.*, COUNT(p.id) as photo_count, u.name as created_by_name 
              FROM gallery_albums a 
              LEFT JOIN gallery_photos p ON a.id = p.album_id 
              LEFT JOIN users u ON a.created_by = u.id 
              GROUP BY a.id 
              ORDER BY a.created_at DESC";
    $stmt = $db->query($query);
    $albums = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Albums query error: " . $e->getMessage());
    $_SESSION['error'] = "Error loading albums: " . $e->getMessage();
}

// Gallery stats
$galleryStats = ['total_albums' => 0, 'total_photos' => 0, 'recent_albums' => 0];
try {
    $stmt = $db->query("SELECT COUNT(*) FROM gallery_albums");
    $galleryStats['total_albums'] = $stmt->fetchColumn();
    $stmt = $db->query("SELECT COUNT(*) FROM gallery_photos");
    $galleryStats['total_photos'] = $stmt->fetchColumn();
    $stmt = $db->query("SELECT COUNT(*) FROM gallery_albums WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $galleryStats['recent_albums'] = $stmt->fetchColumn();
} catch(PDOException $e) {
    error_log("Gallery stats error: " . $e->getMessage());
}

$page_title = "Manage Gallery";
require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar same as before -->
        <div class="col-md-3 d-none d-md-block">
            <div class="desktop-sidebar"><?php include 'sidebar.php'; ?></div>
        </div>
        <div class="offcanvas offcanvas-start d-md-none" tabindex="-1" id="adminMobileSidebar">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title">Admin Menu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body"><?php include 'sidebar.php'; ?></div>
        </div>

        <div class="col-md-9">
            <!-- Mobile header etc. (unchanged) -->
            <div class="d-md-none d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                <div><h4 class="mb-0">Manage Gallery</h4><small class="text-muted">Gallery Management</small></div>
                <button class="btn btn-accent" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileSidebar"><i class="fas fa-bars"></i></button>
            </div>
            <div class="d-none d-md-flex justify-content-between align-items-center mb-4">
                <h1>Manage Gallery</h1>
                <div>
                    <a href="gallery-create.php" class="btn btn-accent me-2"><i class="fas fa-plus me-2"></i>Create Album</a>
                    <a href="gallery-upload.php" class="btn btn-outline-accent"><i class="fas fa-upload me-2"></i>Upload Photos</a>
                </div>
            </div>

            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>

            <!-- Stats cards (unchanged) -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3"><div class="card bg-primary text-white"><div class="card-body text-center"><h2><?php echo $galleryStats['total_albums']; ?></h2><h5>Total Albums</h5></div></div></div>
                <div class="col-md-4 mb-3"><div class="card bg-success text-white"><div class="card-body text-center"><h2><?php echo $galleryStats['total_photos']; ?></h2><h5>Total Photos</h5></div></div></div>
                <div class="col-md-4 mb-3"><div class="card bg-info text-white"><div class="card-body text-center"><h2><?php echo $galleryStats['recent_albums']; ?></h2><h5>Recent Albums</h5></div></div></div>
            </div>

            <!-- Albums Grid -->
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0"><i class="fas fa-images me-2"></i>Photo Albums</h5></div>
                <div class="card-body">
                    <?php if(empty($albums)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-images fa-4x mb-3"></i><br><h4>No albums found</h4>
                            <p class="mb-4">Create your first album to get started</p>
                            <a href="gallery-create.php" class="btn btn-accent"><i class="fas fa-plus me-2"></i>Create First Album</a>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach($albums as $album): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card h-100">
                                        <?php
                                        // Use stored cover_photo if exists, else fetch first photo
                                        $coverImage = null;
                                        if (!empty($album['cover_photo'])) {
                                            $coverImage = $album['cover_photo'];
                                        } else {
                                            try {
                                                $stmt = $db->prepare("SELECT image_path FROM gallery_photos WHERE album_id = ? ORDER BY id LIMIT 1");
                                                $stmt->execute([$album['id']]);
                                                $coverImage = $stmt->fetchColumn();
                                            } catch(PDOException $e) {}
                                        }
                                        ?>
                                        <?php if($coverImage): ?>
                                            <img src="<?php echo SITE_URL . '/uploads/gallery/' . $coverImage; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($album['title']); ?>" style="height: 200px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;"><i class="fas fa-images fa-3x text-muted"></i></div>
                                        <?php endif; ?>
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo htmlspecialchars($album['title']); ?></h5>
                                            <p class="card-text text-muted small"><?php echo !empty($album['description']) ? truncateDescription($album['description'], 100) : '<span class="text-muted fst-italic">No description</span>'; ?></p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted"><i class="fas fa-images me-1"></i><?php echo $album['photo_count']; ?> photos</small>
                                                <small class="text-muted"><?php echo date('M j, Y', strtotime($album['created_at'])); ?></small>
                                            </div>
                                            <?php if($album['created_by_name']): ?>
                                                <small class="text-muted d-block mt-1">Created by: <?php echo htmlspecialchars($album['created_by_name']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-footer bg-transparent">
                                            <div class="btn-group w-100">
                                                <a href="gallery-view.php?id=<?php echo $album['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                                                <a href="gallery-edit.php?id=<?php echo $album['id']; ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i></a>
                                                <a href="gallery-upload.php?album_id=<?php echo $album['id']; ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-plus"></i></a>
                                                <a href="?action=delete_album&id=<?php echo $album['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this album? All photos will be permanently deleted.')"><i class="fas fa-trash"></i></a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Photos section (unchanged) -->
            <div class="card mt-4">
                <div class="card-header"><h5 class="card-title mb-0"><i class="fas fa-clock me-2"></i>Recently Added Photos</h5></div>
                <div class="card-body">
                    <?php
                    $recentPhotos = [];
                    try {
                        $query = "SELECT p.*, a.title as album_title FROM gallery_photos p LEFT JOIN gallery_albums a ON p.album_id = a.id ORDER BY p.created_at DESC LIMIT 12";
                        $stmt = $db->query($query);
                        $recentPhotos = $stmt->fetchAll();
                    } catch(PDOException $e) {}
                    ?>
                    <?php if(empty($recentPhotos)): ?>
                        <p class="text-muted text-center">No photos uploaded yet.</p>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach($recentPhotos as $photo): ?>
                                <div class="col-6 col-md-3 col-lg-2 mb-3">
                                    <div class="position-relative">
                                        <img src="<?php echo SITE_URL . '/uploads/gallery/' . $photo['image_path']; ?>" alt="<?php echo htmlspecialchars($photo['caption'] ?? 'Photo'); ?>" class="img-fluid rounded" style="height: 100px; width: 100%; object-fit: cover;">
                                        <div class="position-absolute top-0 end-0 m-1">
                                            <a href="?action=delete_photo&photo_id=<?php echo $photo['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this photo?')"><i class="fas fa-times"></i></a>
                                        </div>
                                        <small class="text-muted d-block mt-1 text-truncate"><?php echo htmlspecialchars($photo['album_title']); ?></small>
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

<!-- Mobile FAB (unchanged) -->
<div class="fab-container d-md-none">
    <a href="gallery-create.php" class="fab btn btn-accent rounded-circle"><i class="fas fa-plus"></i></a>
</div>

<style>
.fab-container { position: fixed; bottom: 80px; right: 20px; z-index: 1030; }
.fab { width: 60px; height: 60px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.2); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; transition: all 0.3s ease; }
.fab:hover { transform: scale(1.1); box-shadow: 0 6px 20px rgba(0,0,0,0.3); }
.offcanvas-header { background: var(--primary-color); color: white; }
.offcanvas-body { padding: 0; }
.offcanvas-body .nav { padding: 1rem; }
</style>

<?php require_once '../includes/footer.php'; ?>