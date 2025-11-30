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

// Handle album creation
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

// Handle photo upload
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

// Get albums
$albums = [];
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

// Get events for dropdown
$events = [];
try {
    $stmt = $db->prepare("SELECT id, title FROM events WHERE status = 'published' ORDER BY start_date DESC");
    $stmt->execute();
    $events = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Events query error: " . $e->getMessage());
}

// Include header after all processing and redirects
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Desktop Sidebar (visible on larger screens) -->
        <div class="col-md-3 d-none d-md-block">
            <div class="desktop-sidebar">
                <?php include 'sidebar.php'; ?>
            </div>
        </div>

        <!-- Mobile Offcanvas Sidebar (visible on small screens) -->
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
                    <small class="text-muted">Manage albums and photos</small>
                </div>
                <button class="btn btn-accent" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileSidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <div class="d-none d-md-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1>Media Gallery Management</h1>
                    <p class="text-muted mb-0">Create albums and upload photos for events</p>
                </div>
                <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#createAlbumModal">
                    <i class="fas fa-plus me-2"></i>Create Album
                </button>
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
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

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
    // Album creation
    const createAlbumModal = document.getElementById('createAlbumModal');
    if (createAlbumModal) {
        createAlbumModal.addEventListener('show.bs.modal', function() {
            document.getElementById('title').focus();
        });
    }

    // Photo upload
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

/* Mobile responsive adjustments */
@media (max-width: 767.98px) {
    .container-fluid {
        padding-left: 10px;
        padding-right: 10px;
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