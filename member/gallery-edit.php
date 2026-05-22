<?php
// member/gallery-edit.php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['media_head', 'department_head', 'super_admin']);

$page_title = "Edit Album";
$hidePublicNavigation = true;

$user = $session->getCurrentUser();
$db = Database::getInstance()->getConnection();
$functions = new Functions();

$albumId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch album details
$album = null;
if ($albumId) {
    try {
        $stmt = $db->prepare("SELECT * FROM gallery_albums WHERE id = ?");
        $stmt->execute([$albumId]);
        $album = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Album fetch error: " . $e->getMessage());
        $_SESSION['error_message'] = "Error loading album: " . $e->getMessage();
    }
}

// If album not found, redirect
if (!$album) {
    $_SESSION['error_message'] = "Album not found.";
    header("Location: media-gallery.php");
    exit;
}

// Handle album update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_album'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $event_id = !empty($_POST['event_id']) ? (int)$_POST['event_id'] : null;
    $status = $_POST['status'] ?? 'active';

    if (empty($title)) {
        $_SESSION['error_message'] = "Album title is required.";
    } else {
        try {
            $stmt = $db->prepare("UPDATE gallery_albums SET title = ?, description = ?, event_id = ?, status = ? WHERE id = ?");
            $stmt->execute([$title, $description, $event_id, $status, $albumId]);
            $_SESSION['success_message'] = "Album updated successfully!";
            header("Location: media-gallery.php?album_id=" . $albumId);
            exit;
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error updating album: " . $e->getMessage();
        }
    }
}

// Get events for dropdown
$events = [];
try {
    $stmt = $db->prepare("SELECT id, title FROM events WHERE status = 'published' ORDER BY start_date DESC");
    $stmt->execute();
    $events = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Events query error: " . $e->getMessage());
}

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
                    <h4 class="mb-0">Edit Album</h4>
                    <small class="text-muted"><?php echo htmlspecialchars($album['title']); ?></small>
                </div>
                <button class="btn btn-accent" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileSidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <!-- Desktop Header -->
            <div class="d-none d-md-flex justify-content-between align-items-center mb-4">
                <h1>Edit Album: <?php echo htmlspecialchars($album['title']); ?></h1>
                <a href="media-gallery.php?album_id=<?php echo $albumId; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Album
                </a>
            </div>

            <!-- Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Edit Form -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Album Details</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Album Title *</label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($album['title']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="active" <?php echo $album['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $album['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($album['description']); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="event_id" class="form-label">Associated Event (Optional)</label>
                            <select class="form-select" id="event_id" name="event_id">
                                <option value="">No Event</option>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?php echo $event['id']; ?>" <?php echo $album['event_id'] == $event['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($event['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="text-end">
                            <button type="submit" name="update_album" class="btn btn-accent btn-lg">
                                <i class="fas fa-save me-2"></i>Update Album
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
    border: 1px solid rgba(0,0,0,0.125);
}
.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid rgba(0,0,0,0.125);
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>