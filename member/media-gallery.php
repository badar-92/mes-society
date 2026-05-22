<?php
// member/media-gallery.php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['media_head', 'department_head', 'super_admin']);

$page_title = "Media Gallery Management";
$hidePublicNavigation = true;

$user = $session->getCurrentUser();
$db = Database::getInstance()->getConnection();
$functions = new Functions();

// ------------------------------------------------------------------
// Handle actions (delete album, delete photo)
// ------------------------------------------------------------------
if (isset($_GET['action'])) {
    $action = $_GET['action'];

    // Delete album
    if ($action === 'delete_album' && isset($_GET['id'])) {
        $albumId = (int)$_GET['id'];
        try {
            // First delete all photos (files and database records)
            $stmt = $db->prepare("SELECT image_path FROM gallery_photos WHERE album_id = ?");
            $stmt->execute([$albumId]);
            $photos = $stmt->fetchAll();

            foreach ($photos as $photo) {
                $path = __DIR__ . '/../uploads/gallery/' . $photo['image_path'];
                if (file_exists($path)) {
                    unlink($path);
                }
            }

            $db->prepare("DELETE FROM gallery_photos WHERE album_id = ?")->execute([$albumId]);
            $db->prepare("DELETE FROM gallery_albums WHERE id = ?")->execute([$albumId]);

            $_SESSION['success_message'] = "Album deleted successfully.";
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error deleting album: " . $e->getMessage();
        }
        header("Location: media-gallery.php");
        exit;
    }

    // Delete single photo
    if ($action === 'delete_photo' && isset($_GET['photo_id']) && isset($_GET['album_id'])) {
        $photoId = (int)$_GET['photo_id'];
        $albumId = (int)$_GET['album_id'];
        try {
            // Get photo path
            $stmt = $db->prepare("SELECT image_path FROM gallery_photos WHERE id = ?");
            $stmt->execute([$photoId]);
            $photo = $stmt->fetch();

            if ($photo) {
                $path = __DIR__ . '/../uploads/gallery/' . $photo['image_path'];
                if (file_exists($path)) {
                    unlink($path);
                }
                $db->prepare("DELETE FROM gallery_photos WHERE id = ?")->execute([$photoId]);
                $_SESSION['success_message'] = "Photo deleted successfully.";
            } else {
                $_SESSION['error_message'] = "Photo not found.";
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error deleting photo: " . $e->getMessage();
        }
        header("Location: media-gallery.php?album_id=" . $albumId);
        exit;
    }
}

// ------------------------------------------------------------------
// Handle album creation
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_album'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $event_id = !empty($_POST['event_id']) ? (int)$_POST['event_id'] : null;
    
    if (!empty($title)) {
        try {
            $stmt = $db->prepare("INSERT INTO gallery_albums (title, description, event_id, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $description, $event_id, $user['id']]);
            $album_id = $db->lastInsertId();
            
            $_SESSION['success_message'] = "Album created successfully!";
            header("Location: media-gallery.php?album_id=" . $album_id);
            exit;
        } catch(PDOException $e) {
            $_SESSION['error_message'] = "Error creating album: " . $e->getMessage();
        }
    }
}

// ------------------------------------------------------------------
// Handle photo upload
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_photos'])) {
    $album_id = $_POST['album_id'] ?? '';
    $uploaded_files = $_FILES['photos'] ?? [];
    
    if ($album_id && !empty($uploaded_files['name'][0])) {
        $upload_count = 0;
        
        foreach ($uploaded_files['name'] as $key => $name) {
            if ($uploaded_files['error'][$key] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $uploaded_files['name'][$key],
                    'type' => $uploaded_files['type'][$key],
                    'tmp_name' => $uploaded_files['tmp_name'][$key],
                    'error' => $uploaded_files['error'][$key],
                    'size' => $uploaded_files['size'][$key]
                ];
                
                $result = $functions->uploadFile($file, 'gallery');
                
                if ($result['success']) {
                    try {
                        $stmt = $db->prepare("INSERT INTO gallery_photos (album_id, image_path, caption, uploaded_by) VALUES (?, ?, ?, ?)");
                        $caption = !empty($_POST['captions'][$key]) ? $_POST['captions'][$key] : '';
                        $stmt->execute([$album_id, $result['file_name'], $caption, $user['id']]);
                        $upload_count++;
                    } catch(PDOException $e) {
                        error_log("Photo upload error: " . $e->getMessage());
                    }
                }
            }
        }
        
        $_SESSION['success_message'] = "Successfully uploaded $upload_count photos!";
        header("Location: media-gallery.php?album_id=" . $album_id);
        exit;
    }
}

// ------------------------------------------------------------------
// Determine if we are viewing a single album
// ------------------------------------------------------------------
$current_album_id = isset($_GET['album_id']) ? (int)$_GET['album_id'] : null;
$current_album = null;
$photos = [];

if ($current_album_id) {
    // Fetch the album details
    try {
        $stmt = $db->prepare("SELECT * FROM gallery_albums WHERE id = ?");
        $stmt->execute([$current_album_id]);
        $current_album = $stmt->fetch();
    } catch(PDOException $e) {
        error_log("Album fetch error: " . $e->getMessage());
    }

    // If album exists, fetch its photos
    if ($current_album) {
        try {
            $stmt = $db->prepare("SELECT * FROM gallery_photos WHERE album_id = ? ORDER BY created_at DESC");
            $stmt->execute([$current_album_id]);
            $photos = $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Photos fetch error: " . $e->getMessage());
        }
    } else {
        // Invalid album ID, redirect to main gallery
        $_SESSION['error_message'] = "Album not found.";
        header("Location: media-gallery.php");
        exit;
    }
}

// ------------------------------------------------------------------
// Get all albums (for the main listing)
// ------------------------------------------------------------------
$albums = [];
if (!$current_album_id) {
    try {
        $stmt = $db->prepare("SELECT ga.*, u.name as creator_name, COUNT(gp.id) as photo_count 
                             FROM gallery_albums ga 
                             LEFT JOIN users u ON ga.created_by = u.id 
                             LEFT JOIN gallery_photos gp ON ga.id = gp.album_id 
                             WHERE ga.created_by = ? OR ? IN ('department_head', 'super_admin')
                             GROUP BY ga.id 
                             ORDER BY ga.created_at DESC");
        $stmt->execute([$user['id'], $user['role']]);
        $albums = $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Albums query error: " . $e->getMessage());
    }
}

// ------------------------------------------------------------------
// Get events for dropdown (always needed)
// ------------------------------------------------------------------
$events = [];
try {
    $stmt = $db->prepare("SELECT id, title FROM events WHERE status = 'published' ORDER BY start_date DESC");
    $stmt->execute();
    $events = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Events query error: " . $e->getMessage());
}

// Include header after all processing
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
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
                <h5 class="offcanvas-title">Member Menu</h5>
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
                    <h4 class="mb-0">Media Gallery</h4>
                    <small class="text-muted">
                        <?php echo $current_album ? 'Album: ' . htmlspecialchars($current_album['title']) : 'Manage albums and photos'; ?>
                    </small>
                </div>
                <button class="btn btn-accent" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileSidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <!-- Desktop Header -->
            <div class="d-none d-md-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1><?php echo $current_album ? htmlspecialchars($current_album['title']) : 'Media Gallery Management'; ?></h1>
                    <?php if ($current_album): ?>
                        <p class="text-muted mb-0">
                            <a href="media-gallery.php" class="text-muted"><i class="fas fa-arrow-left me-1"></i>Back to Albums</a>
                        </p>
                    <?php else: ?>
                        <p class="text-muted mb-0">Create albums and upload photos for events</p>
                    <?php endif; ?>
                </div>
                <?php if (!$current_album): ?>
                    <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#createAlbumModal">
                        <i class="fas fa-plus me-2"></i>Create Album
                    </button>
                <?php else: ?>
                    <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#uploadPhotosModal" 
                            data-album-id="<?php echo $current_album['id']; ?>" 
                            data-album-title="<?php echo htmlspecialchars($current_album['title']); ?>">
                        <i class="fas fa-upload me-2"></i>Upload Photos
                    </button>
                <?php endif; ?>
            </div>

            <!-- Success/Error Messages -->
            <?php if(isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Main Content Area -->
            <?php if ($current_album): ?>
                <!-- Album View: Show photos -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-images me-2"></i>Photos (<?php echo count($photos); ?>)
                        </h5>
                        <!-- Mobile upload button (visible only on small screens) -->
                        <button class="btn btn-sm btn-accent d-md-none" data-bs-toggle="modal" data-bs-target="#uploadPhotosModal" 
                                data-album-id="<?php echo $current_album['id']; ?>" 
                                data-album-title="<?php echo htmlspecialchars($current_album['title']); ?>">
                            <i class="fas fa-upload"></i>
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if(empty($photos)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-camera fa-4x mb-3"></i>
                                <h4>No Photos Yet</h4>
                                <p class="mb-4">This album doesn't have any photos yet.</p>
                                <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#uploadPhotosModal" 
                                        data-album-id="<?php echo $current_album['id']; ?>" 
                                        data-album-title="<?php echo htmlspecialchars($current_album['title']); ?>">
                                    <i class="fas fa-upload me-2"></i>Upload Photos
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach($photos as $photo): ?>
                                    <div class="col-6 col-md-4 col-lg-3 mb-4">
                                        <div class="card h-100">
                                            <img src="../uploads/gallery/<?php echo htmlspecialchars($photo['image_path']); ?>" 
                                                 class="card-img-top" 
                                                 alt="<?php echo htmlspecialchars($photo['caption'] ?: 'Photo'); ?>"
                                                 style="height: 180px; object-fit: cover;">
                                            <div class="card-body p-2">
                                                <?php if($photo['caption']): ?>
                                                    <p class="card-text small"><?php echo htmlspecialchars($photo['caption']); ?></p>
                                                <?php else: ?>
                                                    <p class="card-text small text-muted">No caption</p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="card-footer p-2 bg-transparent">
                                                <div class="btn-group w-100">
                                                    <a href="../uploads/gallery/<?php echo htmlspecialchars($photo['image_path']); ?>" 
                                                       target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-expand"></i>
                                                    </a>
                                                    <a href="?action=delete_photo&photo_id=<?php echo $photo['id']; ?>&album_id=<?php echo $current_album['id']; ?>" 
                                                       class="btn btn-sm btn-outline-danger"
                                                       onclick="return confirm('Delete this photo?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>
                <!-- Albums Grid -->
                <div class="row">
                    <?php if(empty($albums)): ?>
                        <div class="col-12">
                            <div class="card text-center py-5">
                                <div class="card-body">
                                    <i class="fas fa-images fa-3x text-muted mb-3"></i>
                                    <h5 class="card-title">No Albums Yet</h5>
                                    <p class="card-text text-muted">Create your first album to start uploading photos.</p>
                                    <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#createAlbumModal">
                                        <i class="fas fa-plus me-2"></i>Create First Album
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach($albums as $album): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100">
                                    <?php
                                    // Get first photo as cover or use default
                                    $cover_photo = null;
                                    try {
                                        $stmt = $db->prepare("SELECT image_path FROM gallery_photos WHERE album_id = ? LIMIT 1");
                                        $stmt->execute([$album['id']]);
                                        $cover_photo = $stmt->fetchColumn();
                                    } catch(PDOException $e) {
                                        error_log("Cover photo error: " . $e->getMessage());
                                    }
                                    ?>
                                    
                                    <div class="card-img-top-container" style="height: 200px; overflow: hidden;">
                                        <?php if($cover_photo): ?>
                                            <img src="../uploads/gallery/<?php echo htmlspecialchars($cover_photo); ?>" 
                                                 class="card-img-top h-100" 
                                                 style="object-fit: cover;" 
                                                 alt="<?php echo htmlspecialchars($album['title']); ?>">
                                        <?php else: ?>
                                            <div class="card-img-top h-100 d-flex align-items-center justify-content-center bg-light">
                                                <i class="fas fa-images fa-3x text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title"><?php echo htmlspecialchars($album['title']); ?></h5>
                                        <?php if($album['description']): ?>
                                            <p class="card-text flex-grow-1"><?php echo $functions->truncateText($album['description'], 100); ?></p>
                                        <?php endif; ?>
                                        
                                        <div class="mt-auto">
                                            <div class="d-flex justify-content-between align-items-center text-muted small mb-2">
                                                <span>
                                                    <i class="fas fa-images me-1"></i>
                                                    <?php echo $album['photo_count']; ?> photos
                                                </span>
                                                <span>
                                                    <i class="fas fa-calendar me-1"></i>
                                                    <?php echo $functions->formatDate($album['created_at']); ?>
                                                </span>
                                            </div>
                                            
                                            <div class="btn-group w-100">
                                                <a href="media-gallery.php?album_id=<?php echo $album['id']; ?>" 
                                                   class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-eye me-1"></i>View
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-outline-secondary btn-sm" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#uploadPhotosModal" 
                                                        data-album-id="<?php echo $album['id']; ?>"
                                                        data-album-title="<?php echo htmlspecialchars($album['title']); ?>">
                                                    <i class="fas fa-upload me-1"></i>Upload
                                                </button>
                                                <!-- Edit button (links to admin edit page) -->
                                                <a href="gallery-edit.php?id=<?php echo $album['id']; ?>" 
                                                   class="btn btn-outline-warning btn-sm">
                                                    <i class="fas fa-edit me-1"></i>Edit
                                                </a>
                                                <!-- Delete button -->
                                                <a href="?action=delete_album&id=<?php echo $album['id']; ?>" 
                                                   class="btn btn-outline-danger btn-sm"
                                                   onclick="return confirm('Are you sure you want to delete this album? All photos will be permanently deleted.')">
                                                    <i class="fas fa-trash me-1"></i>Delete
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Floating Action Button (always visible) -->
<button class="btn btn-accent btn-floating" data-bs-toggle="modal" data-bs-target="#createAlbumModal" title="Create New Album">
    <i class="fas fa-plus"></i>
</button>

<!-- Create Album Modal -->
<div class="modal fade" id="createAlbumModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Album</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="title" class="form-label">Album Title *</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="event_id" class="form-label">Associated Event (Optional)</label>
                        <select class="form-select" id="event_id" name="event_id">
                            <option value="">Select Event</option>
                            <?php foreach($events as $event): ?>
                                <option value="<?php echo $event['id']; ?>"><?php echo htmlspecialchars($event['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_album" class="btn btn-accent">Create Album</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Upload Photos Modal -->
<div class="modal fade" id="uploadPhotosModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Photos to <span id="uploadAlbumTitle"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data" id="uploadForm">
                <div class="modal-body">
                    <input type="hidden" name="album_id" id="uploadAlbumId">
                    <input type="hidden" name="upload_photos" value="1">
                    
                    <div class="mb-3">
                        <label for="photos" class="form-label">Select Photos *</label>
                        <input type="file" class="form-control" id="photos" name="photos[]" multiple accept="image/*" required>
                        <div class="form-text">You can select multiple photos. Maximum file size: 5MB per photo.</div>
                    </div>
                    
                    <div id="captionsContainer" class="mt-3" style="display: none;">
                        <label class="form-label">Photo Captions</label>
                        <div id="captionsList"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-accent">Upload Photos</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Album creation modal focus
    const createAlbumModal = document.getElementById('createAlbumModal');
    if (createAlbumModal) {
        createAlbumModal.addEventListener('show.bs.modal', function() {
            document.getElementById('title').focus();
        });
    }

    // Upload modal data transfer
    const uploadPhotosModal = document.getElementById('uploadPhotosModal');
    const uploadForm = document.getElementById('uploadForm');
    const photosInput = document.getElementById('photos');
    const captionsContainer = document.getElementById('captionsContainer');
    const captionsList = document.getElementById('captionsList');

    if (uploadPhotosModal) {
        uploadPhotosModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const albumId = button.getAttribute('data-album-id');
            const albumTitle = button.getAttribute('data-album-title');
            
            document.getElementById('uploadAlbumId').value = albumId;
            document.getElementById('uploadAlbumTitle').textContent = albumTitle;
            uploadForm.action = 'media-gallery.php?album_id=' + albumId;
            
            // Reset form
            photosInput.value = '';
            captionsContainer.style.display = 'none';
            captionsList.innerHTML = '';
        });
    }

    // Handle file selection for captions
    photosInput.addEventListener('change', function() {
        captionsList.innerHTML = '';
        
        if (this.files.length > 0) {
            captionsContainer.style.display = 'block';
            
            Array.from(this.files).forEach((file, index) => {
                const div = document.createElement('div');
                div.className = 'mb-2';
                div.innerHTML = `
                    <label class="form-label small">${file.name}</label>
                    <input type="text" class="form-control form-control-sm" name="captions[]" placeholder="Enter caption (optional)">
                `;
                captionsList.appendChild(div);
            });
        } else {
            captionsContainer.style.display = 'none';
        }
    });
});
</script>

<style>
.card-img-top-container {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.card {
    transition: transform 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.btn-group .btn {
    flex: 1;
}

/* Floating Action Button */
.btn-floating {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.3);
    z-index: 1050;
    padding: 0;
    transition: transform 0.2s, box-shadow 0.2s;
}

.btn-floating:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 15px rgba(0,0,0,0.4);
}

.btn-floating i {
    margin: 0;
}

/* Mobile responsive adjustments */
@media (max-width: 767.98px) {
    .container-fluid {
        padding-left: 10px;
        padding-right: 10px;
    }
    .btn-floating {
        bottom: 20px;
        right: 20px;
        width: 50px;
        height: 50px;
        font-size: 20px;
    }
}

/* Offcanvas sidebar styling */
.offcanvas-header {
    background: var(--primary-color);
    color: white;
}

.offcanvas-body {
    padding: 0;
}

.offcanvas-body .nav {
    padding: 1rem;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>