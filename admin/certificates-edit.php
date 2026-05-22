<?php
    ob_start();
require_once '../includes/session.php';
require_once '../includes/functions.php';  // <-- ADDED
$session = new SessionManager();
$session->requireLogin();
$session->requireRole('super_admin');

$page_title = "Edit Certificate";
$hidePublicNavigation = true;
require_once '../includes/header.php';

$db = Database::getInstance()->getConnection();
$functions = new Functions();

$id = $_GET['id'] ?? 0;
if (!$id) {
    header("Location: certificates.php");
    exit;
}

// Fetch certificate
$stmt = $db->prepare("SELECT * FROM certificates WHERE id = ?");
$stmt->execute([$id]);
$cert = $stmt->fetch();
if (!$cert) {
    $_SESSION['error'] = "Certificate not found.";
    header("Location: certificates.php");
    exit;
}

// Fetch events and competitions
$events = $db->query("SELECT id, title FROM events WHERE status = 'published' ORDER BY start_date DESC")->fetchAll();
$competitions = $db->query("SELECT id, title FROM competitions WHERE status = 'published' ORDER BY start_date DESC")->fetchAll();
$users = $db->query("SELECT sap_id, name FROM users WHERE sap_id IS NOT NULL AND sap_id != ''")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $sap_id = trim($_POST['sap_id'] ?? '');
    $serial_number = trim($_POST['serial_number'] ?? '');
    $event_id = !empty($_POST['event_id']) ? $_POST['event_id'] : null;
    $competition_id = !empty($_POST['competition_id']) ? $_POST['competition_id'] : null;

    $errors = [];

    if (empty($name)) {
        $errors[] = "Name is required.";
    }
    if (empty($serial_number)) {
        $errors[] = "Serial number is required.";
    } else {
        // Check uniqueness except current
        $stmt = $db->prepare("SELECT id FROM certificates WHERE serial_number = ? AND id != ?");
        $stmt->execute([$serial_number, $id]);
        if ($stmt->fetch()) {
            $errors[] = "Serial number already exists.";
        }
    }

    $filepath = $cert['file_path'];
    $thumbPath = $cert['thumbnail_path'];

    // Handle file upload if new file provided
    if (isset($_FILES['certificate_file']) && $_FILES['certificate_file']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['certificate_file'];
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            $errors[] = "File type not allowed. Allowed: PDF, JPG, JPEG, PNG";
        } else {
            $uploadDir = UPLOAD_PATH_CERTIFICATES;
            $thumbDir = UPLOAD_PATH_CERTIFICATES_THUMBS;
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);

            $filename = uniqid() . '_' . time() . '.' . $ext;
            $newFilepath = $uploadDir . $filename;
            if (move_uploaded_file($file['tmp_name'], $newFilepath)) {
                // Delete old file
                if (file_exists($cert['file_path'])) unlink($cert['file_path']);
                if ($cert['thumbnail_path'] && file_exists($cert['thumbnail_path']) && strpos($cert['thumbnail_path'], 'pdf-icon.png') === false) {
                    unlink($cert['thumbnail_path']);
                }

                $filepath = 'uploads/certificates/' . $filename;
                $thumbPath = null;

                // Generate new thumbnail
                if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                    $thumbFilename = 'thumb_' . $filename;
                    $thumbPathFull = $thumbDir . $thumbFilename;
                    list($width, $height) = getimagesize($newFilepath);
                    $newWidth = 200;
                    $newHeight = 200;
                    $thumb = imagecreatetruecolor($newWidth, $newHeight);
                    if ($ext == 'jpg' || $ext == 'jpeg') {
                        $source = imagecreatefromjpeg($newFilepath);
                    } elseif ($ext == 'png') {
                        $source = imagecreatefrompng($newFilepath);
                        imagealphablending($thumb, false);
                        imagesavealpha($thumb, true);
                    }
                    imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                    if ($ext == 'jpg' || $ext == 'jpeg') {
                        imagejpeg($thumb, $thumbPathFull, 80);
                    } elseif ($ext == 'png') {
                        imagepng($thumb, $thumbPathFull, 8);
                    }
                    imagedestroy($source);
                    imagedestroy($thumb);
                    $thumbPath = 'uploads/certificates/thumbs/' . $thumbFilename;
                } elseif ($ext == 'pdf') {
                    $thumbPath = 'assets/images/pdf-icon.png';
                }
            } else {
                $errors[] = "Failed to upload new file.";
            }
        }
    }

    if (empty($errors)) {
        $stmt = $db->prepare("UPDATE certificates SET name = ?, sap_id = ?, serial_number = ?, event_id = ?, competition_id = ?, file_path = ?, thumbnail_path = ? WHERE id = ?");
        $stmt->execute([$name, $sap_id ?: null, $serial_number, $event_id, $competition_id, $filepath, $thumbPath, $id]);
        $_SESSION['success'] = "Certificate updated successfully.";
        header("Location: certificates.php");
        exit;
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
            <div class="d-md-none d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                <h4 class="mb-0">Edit Certificate</h4>
                <button class="btn btn-accent" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileSidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Edit Certificate</h1>
                <a href="certificates.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to List
                </a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($cert['name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">SAP ID (Optional)</label>
                                <input type="text" name="sap_id" class="form-control" value="<?php echo htmlspecialchars($cert['sap_id'] ?? ''); ?>" list="sapList">
                                <datalist id="sapList">
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo htmlspecialchars($user['sap_id']); ?>"><?php echo htmlspecialchars($user['name']); ?></option>
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Serial Number <span class="text-danger">*</span></label>
                                <input type="text" name="serial_number" class="form-control" value="<?php echo htmlspecialchars($cert['serial_number']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Event (Optional)</label>
                                <select name="event_id" class="form-select">
                                    <option value="">-- None --</option>
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?php echo $event['id']; ?>" <?php echo ($cert['event_id'] == $event['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($event['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Competition (Optional)</label>
                                <select name="competition_id" class="form-select">
                                    <option value="">-- None --</option>
                                    <?php foreach ($competitions as $comp): ?>
                                        <option value="<?php echo $comp['id']; ?>" <?php echo ($cert['competition_id'] == $comp['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($comp['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Certificate File (Leave empty to keep current)</label>
                                <input type="file" name="certificate_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                <small class="text-muted">Current file: <?php echo basename($cert['file_path']); ?></small>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-accent">Update Certificate</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>