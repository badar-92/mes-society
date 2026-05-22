<?php
$page_title = "Gallery";
require_once '../includes/header.php';
require_once '../includes/database.php';

/**
 * Get profile picture URL with fallback
 * @param string|null $profilePicture Filename from users.profile_picture
 * @return string URL to profile image or default avatar (data:image/svg+xml)
 */
function getProfilePictureUrl($profilePicture) {
    if (!empty($profilePicture)) {
        $path = '../uploads/profile-pictures/' . $profilePicture;
        if (file_exists($path)) {
            return SITE_URL . '/uploads/profile-pictures/' . $profilePicture;
        }
    }
    // Return a data URI for a simple gray circle with user icon
    return 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23999"%3E%3Cpath d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/%3E%3C/svg%3E';
}

// Helper function for excerpt formatting (bold/italic only, no links)
function formatExcerpt($text, $length = 100) {
    $plain = htmlspecialchars_decode($text);
    $truncated = mb_substr($plain, 0, $length, 'UTF-8');
    if (mb_strlen($plain) > $length) {
        $truncated .= '...';
    }
    $escaped = htmlspecialchars($truncated, ENT_QUOTES, 'UTF-8');
    $escaped = preg_replace('/\*([^\*]+)\*/', '<strong>$1</strong>', $escaped);
    $escaped = preg_replace('/_([^_]+)_/', '<em>$1</em>', $escaped);
    return $escaped;
}

$db = Database::getInstance()->getConnection();

// Ensure the cover_photo column exists
try {
    $colCheck = $db->query("SHOW COLUMNS FROM `gallery_albums` LIKE 'cover_photo'");
    if ($colCheck->rowCount() == 0) {
        $db->exec("ALTER TABLE `gallery_albums` ADD COLUMN `cover_photo` VARCHAR(255) NULL DEFAULT NULL AFTER `description`");
    }
} catch(PDOException $e) {
    error_log("Public gallery - adding cover_photo column error: " . $e->getMessage());
}

// Get active albums with cover photo, creator info, photo count
$albums = [];
try {
    $query = "SELECT a.*, 
                     u.name as creator_name,
                     u.profile_picture as creator_profile_pic,
                     (SELECT image_path FROM gallery_photos WHERE album_id = a.id ORDER BY id LIMIT 1) as first_photo,
                     (SELECT COUNT(*) FROM gallery_photos WHERE album_id = a.id) as photo_count
              FROM gallery_albums a 
              LEFT JOIN users u ON a.created_by = u.id
              WHERE a.status = 'active' 
              ORDER BY a.created_at DESC";
    $stmt = $db->query($query);
    $albums = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Gallery query error: " . $e->getMessage());
}
?>

<div class="container py-5">
    <div class="text-center mb-5">
        <h1 class="display-4 fw-bold text-primary mb-3">Photo Gallery</h1>
        <p class="lead text-muted">Explore our collection of memorable moments and events</p>
    </div>

    <?php if(empty($albums)): ?>
        <div class="text-center py-5">
            <div class="alert alert-info mx-auto" style="max-width: 500px;">
                <i class="fas fa-images fa-3x mb-3 text-muted"></i>
                <h4>No Galleries Available</h4>
                <p class="mb-0">We're working on adding new photo albums. Please check back soon!</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach($albums as $album): 
                // Use admin-set cover_photo if exists, otherwise use first photo
                $coverImage = !empty($album['cover_photo']) ? $album['cover_photo'] : ($album['first_photo'] ?? null);
                // Get profile picture URL with fallback
                $profilePicUrl = getProfilePictureUrl($album['creator_profile_pic']);
            ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm border-0 gallery-card">
                        <div class="position-relative overflow-hidden">
                            <?php if($coverImage): ?>
                                <img src="../uploads/gallery/<?php echo $coverImage; ?>" 
                                     class="card-img-top" 
                                     alt="<?php echo htmlspecialchars($album['title']); ?>"
                                     style="height: 250px; object-fit: cover; transition: transform 0.3s ease;">
                            <?php else: ?>
                                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" 
                                     style="height: 250px;">
                                    <div class="text-center">
                                        <i class="fas fa-images fa-4x text-muted mb-2"></i>
                                        <p class="text-muted small mb-0">No photos yet</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="position-absolute top-0 end-0 m-3">
                                <span class="badge bg-accent rounded-pill">
                                    <i class="fas fa-camera me-1"></i>
                                    <?php echo $album['photo_count']; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title text-dark"><?php echo htmlspecialchars($album['title']); ?></h5>
                            
                            <?php if($album['description']): ?>
                                <p class="card-text text-muted flex-grow-1">
                                    <?php echo formatExcerpt($album['description'], 100); ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="mt-auto">
                                <!-- Created by info with profile picture - name in bold -->
                                <div class="d-flex align-items-center mb-2">
                                    <img src="<?php echo $profilePicUrl; ?>" 
                                         alt="<?php echo htmlspecialchars($album['creator_name'] ?? 'Unknown'); ?>" 
                                         class="rounded-circle me-2" 
                                         style="width: 24px; height: 24px; object-fit: cover;">
                                    <small class="text-muted">
                                        Created by <strong><?php echo htmlspecialchars($album['creator_name'] ?? 'Unknown'); ?></strong>
                                    </small>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo date('M j, Y', strtotime($album['created_at'])); ?>
                                    </small>
                                    <a href="gallery-view.php?id=<?php echo $album['id']; ?>" 
                                       class="btn btn-accent btn-sm">
                                        <i class="fas fa-eye me-1"></i>View Album
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.gallery-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border-radius: 15px;
    overflow: hidden;
}
.gallery-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
}
.gallery-card:hover .card-img-top {
    transform: scale(1.05);
}
.bg-accent {
    background-color: var(--accent-color) !important;
}
.btn-accent {
    background-color: var(--accent-color);
    border-color: var(--accent-color);
    color: white;
}
.btn-accent:hover {
    background-color: var(--accent-color-dark);
    border-color: var(--accent-color-dark);
    color: white;
}
</style>

<?php require_once '../includes/footer.php'; ?>