
<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['super_admin', 'department_head', 'media_head']);

$db = Database::getInstance()->getConnection();
$functions = new Functions();

// Check and create tables if they don't exist
createGalleryTablesIfNotExist($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $event_id = $_POST['event_id'] ?? null;
    $status = $_POST['status'] ?? 'active';
    
    // Validate input
    if (empty($title)) {
        $_SESSION['error'] = "Album title is required";
        header("Location: gallery-create.php");
        exit();
    }
    
    // Validate event_id - set to NULL if empty or 0
    if (empty($event_id) || $event_id == 0) {
        $event_id = null;
    } else {
        // Check if the event exists
        try {
            $stmt = $db->prepare("SELECT id FROM events WHERE id = ?");
            $stmt->execute([$event_id]);
            if (!$stmt->fetch()) {
                // Event doesn't exist, set to null
                $event_id = null;
            }
        } catch (PDOException $e) {
            // If events table doesn't exist or other error, set to null
            $event_id = null;
        }
    }
    
    try {
        // Insert album with proper NULL handling
        $stmt = $db->prepare("
            INSERT INTO gallery_albums (title, description, event_id, status, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $title,
            $description,
            $event_id,  // This can be NULL now
            $status,
            $_SESSION['user_id']
        ]);
        
        $albumId = $db->lastInsertId();
        
      
        
        $_SESSION['success'] = "Album created successfully!";
        header("Location: gallery-upload.php?album_id=" . $albumId);
        exit();
        
    } catch(PDOException $e) {
        error_log("Album creation error: " . $e->getMessage());
        
        // More specific error message
        if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
            $_SESSION['error'] = "The selected event does not exist. Please choose a valid event or leave it unassociated.";
        } else {
            $_SESSION['error'] = "Failed to create album. Please try again.";
        }
        
        header("Location: gallery-create.php");
        exit();
    }
}

// Get events for association - with error handling
$events = [];
try {
    // Check if events table exists first
    $tableCheck = $db->query("SHOW TABLES LIKE 'events'");
    if ($tableCheck->rowCount() > 0) {
        $stmt = $db->query("SELECT id, title, start_date FROM events WHERE start_date >= NOW() ORDER BY start_date");
        $events = $stmt->fetchAll();
    }
} catch(PDOException $e) {
    error_log("Events query error: " . $e->getMessage());
    // Continue without events - it's optional
}

$page_title = "Create New Album";
require_once '../includes/header.php';

// Function to create gallery tables if they don't exist
function createGalleryTablesIfNotExist($db) {
    try {
        // Check if gallery_albums table exists
        $stmt = $db->query("SHOW TABLES LIKE 'gallery_albums'");
        if ($stmt->rowCount() == 0) {
            // Create gallery_albums table WITHOUT foreign key constraints initially
            $db->exec("CREATE TABLE IF NOT EXISTS `gallery_albums` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `title` VARCHAR(255) NOT NULL,
                `description` TEXT,
                `event_id` INT NULL,
                `status` ENUM('active', 'inactive') DEFAULT 'active',
                `created_by` INT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }
    } catch(PDOException $e) {
        error_log("Table creation error: " . $e->getMessage());
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
                <h1>Create New Album</h1>
                <a href="gallery.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Gallery
                </a>
            </div>

            <!-- Error Message -->
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Error Creating Album</h5>
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Album Details</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="albumForm">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Album Title *</label>
                                    <input type="text" class="form-control" id="title" name="title" required 
                                           placeholder="Enter album title" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                                    <div class="invalid-feedback">Please provide a valid album title.</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="active" <?php echo (isset($_POST['status']) && $_POST['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo (isset($_POST['status']) && $_POST['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3" 
                                      placeholder="Describe the album content..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        </div>

                        <?php if(!empty($events)): ?>
                        <div class="mb-3">
                            <label for="event_id" class="form-label">Associated Event (Optional)</label>
                            <select class="form-select" id="event_id" name="event_id">
                                <option value="">No Event Association</option>
                                <?php foreach($events as $event): ?>
                                    <option value="<?php echo $event['id']; ?>" 
                                        <?php echo (isset($_POST['event_id']) && $_POST['event_id'] == $event['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($event['title']); ?> (<?php echo date('M j, Y', strtotime($event['start_date'])); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Link this album to a specific event</small>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No upcoming events available for association. You can still create the album without event association.
                        </div>
                        <?php endif; ?>

                        <div class="text-end">
                            <button type="submit" class="btn btn-accent btn-lg">
                                <i class="fas fa-plus me-2"></i>Create Album
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('albumForm');
    
    form.addEventListener('submit', function(e) {
        const title = document.getElementById('title').value.trim();
        
        if (!title) {
            e.preventDefault();
            alert('Please enter an album title.');
            return false;
        }
        
        return true;
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
