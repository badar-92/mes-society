<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['super_admin', 'department_head', 'media_head']);

$db = Database::getInstance()->getConnection();

// Check and create tables if they don't exist
createGalleryTablesIfNotExist($db);

$albumId = $_GET['album_id'] ?? null;

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
    $_SESSION['error'] = "Album not found or invalid album ID";
    header("Location: gallery.php");
    exit();
}

// Handle photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photos'])) {
    $uploadedCount = 0;
    $errorCount = 0;
    
    // Create uploads directory if it doesn't exist
    $uploadDir = '../uploads/gallery/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            $_SESSION['error'] = "Failed to create upload directory";
            header("Location: gallery-upload.php?album_id=" . $albumId);
            exit();
        }
    }
    
    // Check if files were uploaded
    if (empty($_FILES['photos']['name'][0])) {
        $_SESSION['error'] = "Please select at least one photo to upload";
        header("Location: gallery-upload.php?album_id=" . $albumId);
        exit();
    }
    
    foreach ($_FILES['photos']['tmp_name'] as $key => $tmpName) {
        if ($_FILES['photos']['error'][$key] === UPLOAD_ERR_OK) {
            $originalName = $_FILES['photos']['name'][$key];
            $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $fileName = uniqid() . '_' . time() . '.' . $fileExtension;
            $uploadFile = $uploadDir . $fileName;
            
            // Validate file type
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($fileExtension, $allowedTypes)) {
                // Check file size (max 5MB)
                if ($_FILES['photos']['size'][$key] > 5 * 1024 * 1024) {
                    $errorCount++;
                    continue;
                }
                
                if (move_uploaded_file($tmpName, $uploadFile)) {
                    try {
                        $caption = $_POST['captions'][$key] ?? '';
                        $stmt = $db->prepare("
                            INSERT INTO gallery_photos (album_id, image_path, caption, uploaded_by, created_at)
                            VALUES (?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([$albumId, $fileName, $caption, $_SESSION['user_id']]);
                        $uploadedCount++;
                    } catch(PDOException $e) {
                        error_log("Photo insert error: " . $e->getMessage());
                        $errorCount++;
                        // Delete the uploaded file if database insert fails
                        if (file_exists($uploadFile)) {
                            unlink($uploadFile);
                        }
                    }
                } else {
                    $errorCount++;
                }
            } else {
                $errorCount++;
            }
        } else {
            $errorCount++;
        }
    }
    
    if ($uploadedCount > 0) {
        $_SESSION['success'] = "Successfully uploaded {$uploadedCount} photos to '{$album['title']}'";
    }
    if ($errorCount > 0) {
        $errorMsg = $_SESSION['error'] ?? '';
        $_SESSION['error'] = $errorMsg . ($errorMsg ? '<br>' : '') . "Failed to upload {$errorCount} photos (invalid format, too large, or upload error)";
    }
    
    header("Location: gallery-upload.php?album_id=" . $albumId);
    exit();
}

$page_title = "Upload Photos - " . htmlspecialchars($album['title']);
require_once '../includes/header.php';

// Function to create gallery tables if they don't exist
function createGalleryTablesIfNotExist($db) {
    try {
        // Check if gallery_albums table exists
        $stmt = $db->query("SHOW TABLES LIKE 'gallery_albums'");
        if ($stmt->rowCount() == 0) {
            // Create gallery_albums table
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
            
            error_log("Created gallery_albums table");
        }
        
        // Check if gallery_photos table exists
        $stmt = $db->query("SHOW TABLES LIKE 'gallery_photos'");
        if ($stmt->rowCount() == 0) {
            // Create gallery_photos table
            $db->exec("CREATE TABLE IF NOT EXISTS `gallery_photos` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `album_id` INT NOT NULL,
                `image_path` VARCHAR(255) NOT NULL,
                `caption` TEXT,
                `uploaded_by` INT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            error_log("Created gallery_photos table");
        }
    } catch(PDOException $e) {
        error_log("Table creation error: " . $e->getMessage());
        $_SESSION['error'] = "Database setup error: " . $e->getMessage();
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
                <h1>Upload Photos to: <?php echo htmlspecialchars($album['title']); ?></h1>
                <div>
                    <a href="gallery-view.php?id=<?php echo $albumId; ?>" class="btn btn-secondary me-2">
                        <i class="fas fa-eye me-2"></i>View Album
                    </a>
                    <a href="gallery.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Gallery
                    </a>
                </div>
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

            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-upload me-2"></i>Upload Photos
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" enctype="multipart/form-data" id="uploadForm">
                                <div class="mb-3">
                                    <label for="photos" class="form-label">Select Photos *</label>
                                    <input type="file" class="form-control" id="photos" name="photos[]" 
                                           multiple accept="image/*" required>
                                    <small class="text-muted">
                                        You can select multiple images. Supported formats: JPG, PNG, GIF, WebP. Max file size: 5MB per image.
                                    </small>
                                </div>

                                <div id="captionsContainer" class="mb-3" style="display: none;">
                                    <label class="form-label">Photo Captions (Optional)</label>
                                    <div id="captionsList"></div>
                                </div>

                                <div class="alert alert-info">
                                    <h6><i class="fas fa-info-circle me-2"></i>Upload Tips</h6>
                                    <ul class="mb-0">
                                        <li>Select multiple photos using Ctrl+Click or Shift+Click</li>
                                        <li>Add captions to identify your photos</li>
                                        <li>Photos will be displayed in the order they are uploaded</li>
                                    </ul>
                                </div>

                                <div class="text-end">
                                    <button type="submit" class="btn btn-accent btn-lg">
                                        <i class="fas fa-upload me-2"></i>Upload Photos
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-info-circle me-2"></i>Album Info
                            </h5>
                        </div>
                        <div class="card-body">
                            <h6><?php echo htmlspecialchars($album['title']); ?></h6>
                            <?php if($album['description']): ?>
                                <p class="text-muted"><?php echo htmlspecialchars($album['description']); ?></p>
                            <?php endif; ?>
                            
                            <hr>
                            
                            <div class="mb-2">
                                <small class="text-muted">Status:</small>
                                <span class="badge bg-<?php echo $album['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($album['status']); ?>
                                </span>
                            </div>
                            
                            <div class="mb-2">
                                <small class="text-muted">Created:</small>
                                <br>
                                <small><?php echo date('F j, Y g:i A', strtotime($album['created_at'])); ?></small>
                            </div>
                            
                            <?php
                            // Get photo count for this album
                            try {
                                $stmt = $db->prepare("SELECT COUNT(*) FROM gallery_photos WHERE album_id = ?");
                                $stmt->execute([$albumId]);
                                $photoCount = $stmt->fetchColumn();
                            } catch(PDOException $e) {
                                $photoCount = 0;
                            }
                            ?>
                            
                            <div class="mb-2">
                                <small class="text-muted">Current Photos:</small>
                                <br>
                                <small><strong><?php echo $photoCount; ?></strong> photos in this album</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const photosInput = document.getElementById('photos');
    const captionsContainer = document.getElementById('captionsContainer');
    const captionsList = document.getElementById('captionsList');
    
    photosInput.addEventListener('change', function(e) {
        const files = e.target.files;
        
        if (files.length > 0) {
            captionsContainer.style.display = 'block';
            captionsList.innerHTML = '';
            
            for (let i = 0; i < files.length; i++) {
                const fileName = files[i].name;
                const fileSize = (files[i].size / (1024 * 1024)).toFixed(2); // Convert to MB
                
                const div = document.createElement('div');
                div.className = 'mb-3 p-3 border rounded';
                div.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <strong class="small">${fileName}</strong>
                            <br>
                            <small class="text-muted">${fileSize} MB</small>
                        </div>
                        <span class="badge bg-light text-dark">${i + 1}</span>
                    </div>
                    <input type="text" class="form-control form-control-sm" 
                           name="captions[]" placeholder="Enter caption for this photo (optional)"
                           maxlength="255">
                `;
                captionsList.appendChild(div);
            }
        } else {
            captionsContainer.style.display = 'none';
        }
    });
    
    // Form validation
    document.getElementById('uploadForm').addEventListener('submit', function(e) {
        const files = photosInput.files;
        if (files.length === 0) {
            e.preventDefault();
            alert('Please select at least one photo to upload.');
            return false;
        }
        
        // Check file sizes
        let hasLargeFile = false;
        for (let i = 0; i < files.length; i++) {
            if (files[i].size > 5 * 1024 * 1024) { // 5MB in bytes
                hasLargeFile = true;
                break;
            }
        }
        
        if (hasLargeFile) {
            e.preventDefault();
            alert('One or more files exceed the 5MB size limit. Please select smaller files.');
            return false;
        }
        
        return true;
    });
});
</script>

<style>
.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0, 0, 0, 0.125);
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid rgba(0, 0, 0, 0.125);
}

.btn-accent {
    background-color: var(--accent-color);
    border-color: var(--accent-color);
    color: white;
}

.btn-accent:hover {
    background-color: var(--accent-color-dark);
    border-color: var(--accent-color-dark);
}
</style>

<?php require_once '../includes/footer.php'; ?>