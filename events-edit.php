<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['super_admin', 'department_head', 'event_planner']);

$db = Database::getInstance()->getConnection();

// Get event ID from URL
$event_id = $_GET['id'] ?? 0;

if (!$event_id) {
    header("Location: events.php");
    exit();
}

// Fetch event details
$event = [];
try {
    $stmt = $db->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        header("Location: events.php");
        exit();
    }
} catch(PDOException $e) {
    error_log("Event fetch error: " . $e->getMessage());
    header("Location: events.php");
    exit();
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $event_type = $_POST['event_type'] ?? '';
    $category = $_POST['category'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $venue = $_POST['venue'] ?? '';
    $max_participants = !empty($_POST['max_participants']) ? intval($_POST['max_participants']) : NULL;
    $registration_deadline = $_POST['registration_deadline'] ?? NULL;
    $status = $_POST['status'] ?? 'draft';
    
    // Validate required fields
    if (empty($title) || empty($description) || empty($start_date) || empty($venue)) {
        $error = "Please fill all required fields";
    } else {
        try {
            // Handle file upload
            $banner_image = $event['banner_image'];
            if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../uploads/event-images/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $fileExtension = pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION);
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array(strtolower($fileExtension), $allowedExtensions)) {
                    $fileName = uniqid() . '.' . $fileExtension;
                    $filePath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $filePath)) {
                        // Delete old banner image if it exists
                        if ($banner_image && file_exists($uploadDir . $banner_image)) {
                            unlink($uploadDir . $banner_image);
                        }
                        $banner_image = $fileName;
                    }
                } else {
                    $error = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
                }
            }
            
            if (!$error) {
                // Update event
                $stmt = $db->prepare("
                    UPDATE events 
                    SET title = ?, description = ?, event_type = ?, category = ?, 
                        start_date = ?, end_date = ?, venue = ?, max_participants = ?, 
                        registration_deadline = ?, banner_image = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                
                $result = $stmt->execute([
                    $title,
                    $description,
                    $event_type,
                    $category,
                    $start_date,
                    $end_date,
                    $venue,
                    $max_participants,
                    $registration_deadline,
                    $banner_image,
                    $status,
                    $event_id
                ]);
                
                if ($result) {
                    $success = "Event updated successfully!";
                    // Refresh event data
                    $stmt = $db->prepare("SELECT * FROM events WHERE id = ?");
                    $stmt->execute([$event_id]);
                    $event = $stmt->fetch();
                } else {
                    $error = "Failed to update event. Please try again.";
                }
            }
            
        } catch(PDOException $e) {
            error_log("Event update error: " . $e->getMessage());
            $error = "Database error: " . $e->getMessage();
        }
    }
}

$page_title = "Edit Event - " . $event['title'];
require_once '../includes/header.php';
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
                <h1>Edit Event</h1>
                <div>
                    <a href="event-details.php?id=<?php echo $event_id; ?>" class="btn btn-info me-2">
                        <i class="fas fa-eye me-2"></i>View Details
                    </a>
                    <a href="events.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Events
                    </a>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Edit Event Details</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Event Title *</label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($event['title']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="event_type" class="form-label">Event Type *</label>
                                    <select class="form-select" id="event_type" name="event_type" required>
                                        <option value="">Select Type</option>
                                        <option value="seminar" <?php echo $event['event_type'] === 'seminar' ? 'selected' : ''; ?>>Seminar</option>
                                        <option value="workshop" <?php echo $event['event_type'] === 'workshop' ? 'selected' : ''; ?>>Workshop</option>
                                        <option value="competition" <?php echo $event['event_type'] === 'competition' ? 'selected' : ''; ?>>Competition</option>
                                        <option value="social" <?php echo $event['event_type'] === 'social' ? 'selected' : ''; ?>>Social Event</option>
                                        <option value="conference" <?php echo $event['event_type'] === 'conference' ? 'selected' : ''; ?>>Conference</option>
                                        <option value="other" <?php echo $event['event_type'] === 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description *</label>
                            <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($event['description']); ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="category" class="form-label">Category</label>
                                    <input type="text" class="form-control" id="category" name="category" 
                                           value="<?php echo htmlspecialchars($event['category'] ?? ''); ?>" placeholder="e.g., Technical, Cultural, Sports">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="venue" class="form-label">Venue *</label>
                                    <input type="text" class="form-control" id="venue" name="venue" 
                                           value="<?php echo htmlspecialchars($event['venue']); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="start_date" class="form-label">Start Date & Time *</label>
                                    <input type="datetime-local" class="form-control" id="start_date" name="start_date" 
                                           value="<?php echo date('Y-m-d\TH:i', strtotime($event['start_date'])); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="end_date" class="form-label">End Date & Time</label>
                                    <input type="datetime-local" class="form-control" id="end_date" name="end_date" 
                                           value="<?php echo $event['end_date'] ? date('Y-m-d\TH:i', strtotime($event['end_date'])) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="registration_deadline" class="form-label">Registration Deadline</label>
                                    <input type="datetime-local" class="form-control" id="registration_deadline" name="registration_deadline" 
                                           value="<?php echo $event['registration_deadline'] ? date('Y-m-d\TH:i', strtotime($event['registration_deadline'])) : ''; ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="max_participants" class="form-label">Maximum Participants</label>
                                    <input type="number" class="form-control" id="max_participants" name="max_participants" 
                                           value="<?php echo $event['max_participants'] ?? ''; ?>" min="1" placeholder="Leave empty for unlimited">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="banner_image" class="form-label">Banner Image</label>
                                    <input type="file" class="form-control" id="banner_image" name="banner_image" accept="image/*">
                                    <small class="text-muted">Recommended size: 1200x600 pixels. Allowed: JPG, PNG, GIF</small>
                                    <?php if($event['banner_image']): ?>
                                        <div class="mt-2">
                                            <p class="mb-1">Current Image:</p>
                                            <img src="../uploads/event-images/<?php echo $event['banner_image']; ?>" 
                                                 alt="Current banner" style="max-height: 100px; border-radius: 5px;">
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="draft" <?php echo $event['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="published" <?php echo $event['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                                <option value="cancelled" <?php echo $event['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-accent btn-lg">
                                <i class="fas fa-save me-2"></i>Update Event
                            </button>
                            <a href="events.php" class="btn btn-secondary btn-lg">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Update end date min when start date changes
    document.getElementById('start_date').addEventListener('change', function() {
        document.getElementById('end_date').min = this.value;
        document.getElementById('registration_deadline').max = this.value;
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>