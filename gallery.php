<?php
$page_title = "Gallery";
require_once '../includes/header.php';
require_once '../includes/database.php';

$db = Database::getInstance()->getConnection();

// Get active albums with their first photo as cover
$albums = [];
try {
    $query = "SELECT a.*, 
                     (SELECT image_path FROM gallery_photos WHERE album_id = a.id ORDER BY id LIMIT 1) as cover_image,
                     (SELECT COUNT(*) FROM gallery_photos WHERE album_id = a.id) as photo_count
              FROM gallery_albums a 
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
            <?php foreach($albums as $album): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm border-0 gallery-card">
                        <div class="position-relative overflow-hidden">
                            <?php if($album['cover_image']): ?>
                                <img src="../uploads/gallery/<?php echo $album['cover_image']; ?>" 
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
                                    <?php echo htmlspecialchars($album['description']); ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="mt-auto">
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