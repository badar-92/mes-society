<?php
$page_title = "Gallery Album";
require_once '../includes/header.php';
require_once '../includes/database.php';

// --- Helper function for formatting (bold, italic, links) ---
function formatDescription($text) {
    // Escape HTML to prevent XSS
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    
    // Convert *bold* to <strong>bold</strong>
    $text = preg_replace('/\*([^\*]+)\*/', '<strong>$1</strong>', $text);
    
    // Convert _italic_ to <em>italic</em>
    $text = preg_replace('/_([^_]+)_/', '<em>$1</em>', $text);
    
    // Use placeholders to avoid double‑processing URLs
    $placeholders = [];
    
    // First, replace all http/https URLs with placeholders
    $text = preg_replace_callback(
        '/(https?:\/\/[^\s<>"{}|\\^`\[\]]+)/i',
        function($matches) use (&$placeholders) {
            $key = '%%URL_' . count($placeholders) . '%%';
            $placeholders[$key] = $matches[1];
            return $key;
        },
        $text
    );
    
    // Then replace all www. links (that are not already part of a URL)
    $text = preg_replace_callback(
        '/(www\.[^\s<>"{}|\\^`\[\]]+)/i',
        function($matches) use (&$placeholders) {
            $key = '%%URL_' . count($placeholders) . '%%';
            $placeholders[$key] = 'https://' . $matches[1];
            return $key;
        },
        $text
    );
    
    // Now replace placeholders with actual anchor tags
    foreach ($placeholders as $key => $url) {
        $link = '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $url . '</a>';
        $text = str_replace($key, $link, $text);
    }
    
    // Convert newlines to <br>
    return nl2br($text);
}
// -------------------------------------

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
            <p class="lead text-muted max-w-600 mx-auto"><?php echo formatDescription($album['description']); ?></p>
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
        <!-- Pinterest-style Masonry Grid -->
        <div class="pinterest-grid">
            <?php foreach($photos as $index => $photo): ?>
                <div class="photo-card">
                    <div class="position-relative overflow-hidden rounded-3">
                        <!-- Main image (click to open modal) -->
                        <img src="../uploads/gallery/<?php echo $photo['image_path']; ?>" 
                             class="card-img-top" 
                             alt="<?php echo $photo['caption'] ? htmlspecialchars($photo['caption']) : 'Photo ' . ($index + 1); ?>"
                             loading="lazy"
                             style="width: 100%; height: auto; display: block; cursor: pointer;"
                             data-bs-toggle="modal" 
                             data-bs-target="#photoModal"
                             data-photo-src="../uploads/gallery/<?php echo $photo['image_path']; ?>"
                             data-photo-caption="<?php echo $photo['caption'] ? htmlspecialchars($photo['caption']) : ''; ?>"
                             data-photo-index="<?php echo $index; ?>">
                        
                        <!-- Download Button -->
                        <div class="position-absolute top-0 end-0 m-2">
                            <a href="../uploads/gallery/<?php echo $photo['image_path']; ?>" 
                               download="<?php echo 'photo_' . $photo['id'] . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $album['title']) . '.jpg'; ?>"
                               class="btn btn-light btn-sm shadow-sm download-btn"
                               onclick="event.stopPropagation(); trackDownload(<?php echo $photo['id']; ?>);">
                                <i class="fas fa-download"></i>
                            </a>
                        </div>
                        
                        <!-- Photo Number Badge -->
                        <div class="position-absolute top-0 start-0 m-2">
                            <span class="badge bg-dark bg-opacity-75 text-white rounded-pill">
                                <?php echo $index + 1; ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php if($photo['caption']): ?>
                        <div class="card-body px-2 pb-2 pt-2">
                            <p class="card-text small text-muted mb-0">
                                <?php echo htmlspecialchars($photo['caption']); ?>
                            </p>
                        </div>
                    <?php endif; ?>
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

<!-- Photo Modal - White Background -->
<div class="modal fade" id="photoModal" tabindex="-1" aria-labelledby="photoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0" style="background: white;">
            <div class="modal-header border-0 pb-0" style="background: white;">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-0" style="background: white;">
                <img id="modalPhoto" src="" alt="" class="img-fluid" style="max-height: 80vh; object-fit: contain;">
            </div>
            <div class="modal-footer border-0 justify-content-between" style="background: white;">
                <div id="modalCaption" class="text-dark"></div>
                <div>
                    <button id="modalDownloadBtn" class="btn btn-accent me-2">
                        <i class="fas fa-download me-1"></i>Download HD
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Pinterest-style Masonry Grid */
.pinterest-grid {
    column-count: 2;
    column-gap: 1.5rem;
}

.photo-card {
    break-inside: avoid;
    margin-bottom: 1.5rem;
    border-radius: 12px;
    background: #fff;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    overflow: hidden;
}

.photo-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 20px rgba(0,0,0,0.12);
}

.photo-card .position-relative {
    border-radius: 12px 12px 0 0;
    overflow: hidden;
}

.card-img-top {
    transition: transform 0.3s ease;
}

.photo-card:hover .card-img-top {
    transform: scale(1.02);
}

.download-btn {
    opacity: 0;
    transition: opacity 0.2s ease;
    border-radius: 50%;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: rgba(255,255,255,0.9);
    backdrop-filter: blur(2px);
}

.photo-card:hover .download-btn {
    opacity: 1;
}

/* Responsive column counts */
@media (min-width: 576px) {
    .pinterest-grid {
        column-count: 2;
    }
}

@media (min-width: 768px) {
    .pinterest-grid {
        column-count: 3;
    }
}

@media (min-width: 992px) {
    .pinterest-grid {
        column-count: 4;
    }
}

@media (min-width: 1400px) {
    .pinterest-grid {
        column-count: 5;
    }
}

.max-w-600 {
    max-width: 600px;
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

/* White modal styles */
.modal-content {
    border-radius: 20px;
    background: white !important;
}

.modal-header .btn-close {
    background-color: rgba(0,0,0,0.1);
    border-radius: 50%;
    padding: 8px;
    opacity: 0.8;
}

.modal-header .btn-close:hover {
    background-color: rgba(0,0,0,0.2);
    opacity: 1;
}

.modal-footer {
    border-top: 1px solid #dee2e6 !important;
}

#modalCaption {
    color: #212529;
}

/* Fix for modal backdrop removal issues */
.modal-backdrop {
    transition: opacity 0.15s linear;
}

body.modal-open {
    overflow: visible !important;
    padding-right: 0 !important;
}

/* FIX: Ensure bold text works on all devices (including mobile) */
.lead strong,
.lead b {
    font-weight: 700 !important;
}
</style>

<script>
// Function to download image via blob (works reliably)
function downloadImage(url, filename) {
    fetch(url)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.blob();
        })
        .then(blob => {
            const blobUrl = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = blobUrl;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(blobUrl);
        })
        .catch(error => {
            console.error('Download failed:', error);
            // Fallback: try opening the link directly
            window.open(url, '_blank');
        });
}

// Modal functionality with robust download handling
document.addEventListener('DOMContentLoaded', function() {
    const photoModal = document.getElementById('photoModal');
    let currentPhotoUrl = '';
    let currentFilename = '';
    
    if (photoModal) {
        // When modal is shown
        photoModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const photoSrc = button.getAttribute('data-photo-src');
            const photoCaption = button.getAttribute('data-photo-caption');
            const photoIndex = button.getAttribute('data-photo-index');
            
            const modalImage = document.getElementById('modalPhoto');
            const modalCaption = document.getElementById('modalCaption');
            
            modalImage.src = photoSrc;
            currentPhotoUrl = photoSrc;
            
            // Sanitize album title for download filename
            const albumTitle = '<?php echo preg_replace("/[^a-zA-Z0-9]/", "_", $album["title"]); ?>';
            currentFilename = 'photo_' + (parseInt(photoIndex) + 1) + '_' + albumTitle + '.jpg';
            
            if (photoCaption && photoCaption.trim() !== '') {
                modalCaption.textContent = photoCaption;
                modalCaption.style.display = 'block';
            } else {
                modalCaption.style.display = 'none';
            }
        });
        
        // Handle modal download button click
        const modalDownloadBtn = document.getElementById('modalDownloadBtn');
        if (modalDownloadBtn) {
            modalDownloadBtn.addEventListener('click', function() {
                if (currentPhotoUrl) {
                    trackDownloadFromModal(currentFilename);
                    downloadImage(currentPhotoUrl, currentFilename);
                }
            });
        }
        
        // Clean up modal properly on close
        photoModal.addEventListener('hidden.bs.modal', function () {
            // Remove any lingering backdrops
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(backdrop => backdrop.remove());
            
            // Remove modal-open class from body and reset overflow
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
            document.body.style.pointerEvents = '';
        });
    }
});

// Track downloads (analytics ready)
function trackDownload(photoId) {
    console.log('Downloaded photo ID (card):', photoId);
    showToast('Download started!');
}

function trackDownloadFromModal(filename) {
    console.log('Downloaded photo from modal:', filename);
    showToast('Download started!');
}

function showToast(message) {
    const toast = document.createElement('div');
    toast.className = 'position-fixed bottom-0 end-0 m-3 alert alert-success alert-dismissible fade show';
    toast.style.zIndex = '9999';
    toast.innerHTML = `
        <i class="fas fa-check-circle me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(toast);
    setTimeout(() => {
        if (toast.parentNode) toast.parentNode.removeChild(toast);
    }, 3000);
}

// Prevent modal image click from closing modal prematurely
document.addEventListener('click', function(e) {
    const modalImg = document.getElementById('modalPhoto');
    if (modalImg && e.target === modalImg) {
        e.stopPropagation();
    }
});

// Optional: Add subtle lazy loading effect for images
if ('IntersectionObserver' in window) {
    const imgObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.classList.add('img-loaded');
                observer.unobserve(img);
            }
        });
    });
    document.querySelectorAll('.photo-card img').forEach(img => {
        imgObserver.observe(img);
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>