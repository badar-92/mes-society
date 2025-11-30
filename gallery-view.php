<?php
$page_title = "Gallery Album";
require_once '../includes/header.php';
require_once '../includes/database.php';

$db = Database::getInstance()->getConnection();

$albumId = $_GET['id'] ?? null;

// Get album details
$album = null;
$photos = [];

if ($albumId) {
    try {
        // Get album info
        $stmt = $db->prepare("SELECT * FROM gallery_albums WHERE id = ? AND status = 'active'");
        $stmt->execute([$albumId]);
        $album = $stmt->fetch();
        
        // Get photos for this album
        if ($album) {
            $stmt = $db->prepare("SELECT * FROM gallery_photos WHERE album_id = ? ORDER BY created_at DESC");
            $stmt->execute([$albumId]);
            $photos = $stmt->fetchAll();
        }
    } catch(PDOException $e) {
        error_log("Album view error: " . $e->getMessage());
    }
}

if (!$album) {
    header("Location: gallery.php");
    exit();
}

$page_title = htmlspecialchars($album['title']) . " - Gallery";
?>

<div class="container py-5">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="gallery.php">Gallery</a></li>
            <li class="breadcrumb-item active"><?php echo htmlspecialchars($album['title']); ?></li>
        </ol>
    </nav>

    <!-- Album Header -->
    <div class="text-center mb-5">
        <h1 class="display-5 fw-bold text-dark mb-3"><?php echo htmlspecialchars($album['title']); ?></h1>
        <?php if($album['description']): ?>
            <p class="lead text-muted max-w-600 mx-auto"><?php echo htmlspecialchars($album['description']); ?></p>
        <?php endif; ?>
        <div class="d-flex justify-content-center align-items-center gap-3 text-muted">
            <small>
                <i class="fas fa-images me-1"></i>
                <?php echo count($photos); ?> photos
            </small>
            <small>•</small>
            <small>
                <i class="fas fa-calendar me-1"></i>
                Created <?php echo date('F j, Y', strtotime($album['created_at'])); ?>
            </small>
        </div>
    </div>

    <?php if(empty($photos)): ?>
        <div class="text-center py-5">
            <div class="alert alert-info mx-auto" style="max-width: 500px;">
                <i class="fas fa-camera fa-3x mb-3 text-muted"></i>
                <h4>No Photos Yet</h4>
                <p class="mb-0">This album doesn't have any photos yet. Please check back later.</p>
            </div>
        </div>
    <?php else: ?>
        <!-- Photo Grid -->
        <div class="row g-4" id="photoGrid">
            <?php foreach($photos as $index => $photo): ?>
                <div class="col-md-6 col-lg-4 col-xl-3">
                    <div class="card h-100 shadow-sm border-0 photo-card">
                        <div class="position-relative overflow-hidden">
                            <img src="../uploads/gallery/<?php echo $photo['image_path']; ?>" 
                                 class="card-img-top" 
                                 alt="<?php echo $photo['caption'] ? htmlspecialchars($photo['caption']) : 'Photo ' . ($index + 1); ?>"
                                 style="height: 250px; object-fit: cover; cursor: pointer;"
                                 data-bs-toggle="modal" 
                                 data-bs-target="#photoModal"
                                 data-photo-src="../uploads/gallery/<?php echo $photo['image_path']; ?>"
                                 data-photo-caption="<?php echo $photo['caption'] ? htmlspecialchars($photo['caption']) : ''; ?>"
                                 data-photo-index="<?php echo $index; ?>">
                            
                            <!-- Download Button -->
                            <div class="position-absolute top-0 end-0 m-2">
                                <a href="../uploads/gallery/<?php echo $photo['image_path']; ?>" 
                                   download="<?php echo 'photo_' . $photo['id'] . '_' . $album['title'] . '.jpg'; ?>"
                                   class="btn btn-light btn-sm shadow-sm download-btn"
                                   onclick="trackDownload(<?php echo $photo['id']; ?>)">
                                    <i class="fas fa-download"></i>
                                </a>
                            </div>
                            
                            <!-- Photo Number -->
                            <div class="position-absolute top-0 start-0 m-2">
                                <span class="badge bg-dark bg-opacity-75 text-white rounded-pill">
                                    <?php echo $index + 1; ?>
                                </span>
                            </div>
                        </div>
                        
                        <?php if($photo['caption']): ?>
                            <div class="card-body">
                                <p class="card-text small text-muted mb-0">
                                    <?php echo htmlspecialchars($photo['caption']); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Quick Stats -->
        <div class="text-center mt-5 pt-4 border-top">
            <div class="row justify-content-center">
                <div class="col-auto">
                    <div class="text-center">
                        <h4 class="text-primary mb-1"><?php echo count($photos); ?></h4>
                        <small class="text-muted">Total Photos</small>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="text-center">
                        <h4 class="text-success mb-1">HD</h4>
                        <small class="text-muted">Quality</small>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="text-center">
                        <h4 class="text-info mb-1">Free</h4>
                        <small class="text-muted">Download</small>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Photo Modal -->
<div class="modal fade" id="photoModal" tabindex="-1" aria-labelledby="photoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-0">
                <img id="modalPhoto" src="" alt="" class="img-fluid" style="max-height: 80vh; object-fit: contain;">
            </div>
            <div class="modal-footer border-0 justify-content-between">
                <div id="modalCaption" class="text-muted"></div>
                <div>
                    <a href="#" id="modalDownload" download class="btn btn-accent me-2">
                        <i class="fas fa-download me-1"></i>Download HD
                    </a>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Photo modal functionality
const photoModal = document.getElementById('photoModal');
if (photoModal) {
    photoModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const photoSrc = button.getAttribute('data-photo-src');
        const photoCaption = button.getAttribute('data-photo-caption');
        const photoIndex = button.getAttribute('data-photo-index');
        
        const modalImage = document.getElementById('modalPhoto');
        const modalCaption = document.getElementById('modalCaption');
        const modalDownload = document.getElementById('modalDownload');
        
        modalImage.src = photoSrc;
        modalDownload.href = photoSrc;
        modalDownload.download = 'photo_' + (parseInt(photoIndex) + 1) + '_<?php echo preg_replace("/[^a-zA-Z0-9]/", "_", $album["title"]); ?>.jpg';
        
        if (photoCaption) {
            modalCaption.textContent = photoCaption;
            modalCaption.style.display = 'block';
        } else {
            modalCaption.style.display = 'none';
        }
    });
}

// Track downloads (you can integrate with analytics later)
function trackDownload(photoId) {
    // Here you can add analytics tracking
    console.log('Downloaded photo ID:', photoId);
    
    // Optional: Show a subtle notification
    const toast = document.createElement('div');
    toast.className = 'position-fixed bottom-0 end-0 m-3 alert alert-success alert-dismissible fade show';
    toast.style.zIndex = '1060';
    toast.innerHTML = `
        <i class="fas fa-check-circle me-2"></i>
        Download started!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(toast);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, 3000);
}

// Add some interactivity
document.addEventListener('DOMContentLoaded', function() {
    const photoCards = document.querySelectorAll('.photo-card');
    
    photoCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.transition = 'transform 0.3s ease';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});
</script>

<style>
.photo-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border-radius: 12px;
    overflow: hidden;
}

.photo-card:hover {
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}

.download-btn {
    opacity: 0;
    transition: opacity 0.3s ease;
    border-radius: 50%;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.photo-card:hover .download-btn {
    opacity: 1;
}

.max-w-600 {
    max-width: 600px;
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

/* Modal enhancements */
.modal-content {
    border-radius: 15px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.modal-header .btn-close {
    background: rgba(0,0,0,0.5);
    border-radius: 50%;
    padding: 8px;
    margin: 0;
}

.modal-header .btn-close:hover {
    background: rgba(0,0,0,0.7);
}
</style>

<?php require_once '../includes/footer.php'; ?>