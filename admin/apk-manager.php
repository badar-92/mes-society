<?php
require_once '../includes/session.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['super_admin']);

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

$db = Database::getInstance()->getConnection();

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['apk_file'])) {
    $version_name = trim($_POST['version_name']);
    $file = $_FILES['apk_file'];
    
    if (empty($version_name)) {
        $error = 'Please enter a version name (e.g., v1.5.0.1)';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'File upload error: ' . $file['error'];
    } elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'apk') {
        $error = 'Only .apk files are allowed.';
    } else {
        $target_dir = '../uploads/apks/';
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        $safe_version = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $version_name);
        $new_filename = 'MES_UOL_' . $safe_version . '.apk';
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            $file_size = filesize($target_file);
            $stmt = $db->prepare("INSERT INTO apk_versions (version_name, file_name, file_path, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$version_name, $new_filename, $target_file, $file_size, $_SESSION['user_id']]);
            $message = 'APK uploaded successfully as ' . $new_filename;
        } else {
            $error = 'Failed to move uploaded file.';
        }
    }
}

// Handle deletion
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $db->prepare("SELECT file_path FROM apk_versions WHERE id = ?");
    $stmt->execute([$id]);
    $apk = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($apk && file_exists($apk['file_path'])) {
        unlink($apk['file_path']);
    }
    $stmt = $db->prepare("DELETE FROM apk_versions WHERE id = ?");
    $stmt->execute([$id]);
    $message = 'APK deleted.';
    header('Location: apk-manager.php');
    exit;
}

// Fetch all APKs
$stmt = $db->query("SELECT * FROM apk_versions ORDER BY uploaded_at DESC");
$apks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "APK Manager";
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

        <!-- Mobile Offcanvas Sidebar -->
        <div class="offcanvas offcanvas-start d-md-none" tabindex="-1" id="adminMobileSidebar">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title">Admin Menu</h5>
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
                    <h4 class="mb-0">APK Manager</h4>
                    <small class="text-muted">Manage App Versions</small>
                </div>
                <button class="btn btn-accent" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileSidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <div class="d-none d-md-flex justify-content-between align-items-center mb-4">
                <h1>APK Manager</h1>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Upload Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-upload me-2"></i>Upload New APK
                </div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="version_name" class="form-label">Version Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="version_name" name="version_name" placeholder="e.g., v1.5.0.1" required>
                            <small class="text-muted">Example: v1.5.0.1</small>
                        </div>
                        <div class="mb-3">
                            <label for="apk_file" class="form-label">APK File <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="apk_file" name="apk_file" accept=".apk" required>
                        </div>
                        <button type="submit" class="btn btn-accent"><i class="fas fa-cloud-upload-alt me-2"></i>Upload APK</button>
                    </form>
                </div>
            </div>

            <!-- APK List Card -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-list me-2"></i>Existing APK Versions
                </div>
                <div class="card-body">
                    <?php if (empty($apks)): ?>
                        <div class="alert alert-info">No APK uploaded yet. Use the form above to upload the first version.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Version</th>
                                        <th>File Name</th>
                                        <th>Size (KB)</th>
                                        <th>Uploaded</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($apks as $apk): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($apk['version_name']) ?></strong></td>
                                            <td><?= htmlspecialchars($apk['file_name']) ?></td>
                                            <td><?= number_format($apk['file_size'] / 1024, 2) ?></td>
                                            <td><?= htmlspecialchars($apk['uploaded_at']) ?></td>
                                            <td class="btn-group">
                                                <a href="<?= htmlspecialchars($apk['file_path']) ?>" class="btn btn-sm btn-success" download title="Download">
                                                    <i class="fas fa-download"></i> Download
                                                </a>
                                                <a href="?delete=<?= $apk['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this APK version? This action cannot be undone.')" title="Delete">
                                                    <i class="fas fa-trash-alt"></i> Delete
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mobile Floating Action Button (optional, same as applications.php) -->
<div class="fab-container d-md-none">
    <button class="fab btn btn-accent rounded-circle" onclick="window.location.href='apk-manager.php'">
        <i class="fas fa-upload"></i>
    </button>
</div>

<style>
/* Floating Action Button */
.fab-container {
    position: fixed;
    bottom: 80px;
    right: 20px;
    z-index: 1030;
}

.fab {
    width: 60px;
    height: 60px;
    border: none;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    transition: all 0.3s ease;
}

.fab:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(0,0,0,0.3);
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

<?php require_once '../includes/footer.php'; ?>