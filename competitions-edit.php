<?php
require_once '../includes/session.php';
require_once '../includes/database.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['super_admin', 'department_head', 'competition_head']);

$db = Database::getInstance()->getConnection();

$competition_id = $_GET['id'] ?? 0;

// Get competition details
$competition = null;
try {
    $stmt = $db->prepare("SELECT * FROM competitions WHERE id = ?");
    $stmt->execute([$competition_id]);
    $competition = $stmt->fetch();
} catch(PDOException $e) {
    error_log("Competition edit error: " . $e->getMessage());
}

if (!$competition) {
    header("Location: competitions.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $competition_type = $_POST['competition_type'] ?? 'individual';
    $category = $_POST['category'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $venue = $_POST['venue'] ?? '';
    $max_participants = $_POST['max_participants'] ?? null;
    $registration_deadline = $_POST['registration_deadline'] ?? null;
    $prize = $_POST['prize'] ?? '';
    $rules = $_POST['rules'] ?? '';
    $eligibility = $_POST['eligibility'] ?? '';
    $status = $_POST['status'] ?? 'draft';
    $registration_open = isset($_POST['registration_open']) ? 1 : 0;

    // Handle banner image upload
    $banner_image = $competition['banner_image'];
    if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/competitions/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Delete old banner if exists
        if ($banner_image && file_exists($upload_dir . $banner_image)) {
            unlink($upload_dir . $banner_image);
        }

        $file_extension = pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION);
        $filename = 'competition_' . $competition_id . '_' . time() . '.' . $file_extension;
        $destination = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $destination)) {
            $banner_image = $filename;
        } else {
            $_SESSION['error'] = "Failed to upload banner image.";
        }
    }

    // Validation
    $errors = [];
    if (empty($title)) {
        $errors[] = "Title is required.";
    }
    if (empty($start_date)) {
        $errors[] = "Start date is required.";
    }
    if (empty($end_date)) {
        $errors[] = "End date is required.";
    }
    if (empty($venue)) {
        $errors[] = "Venue is required.";
    }

    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                UPDATE competitions 
                SET title = ?, description = ?, competition_type = ?, category = ?, 
                    start_date = ?, end_date = ?, venue = ?, max_participants = ?, 
                    registration_deadline = ?, prize = ?, rules = ?, eligibility = ?, 
                    banner_image = ?, registration_open = ?, status = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $title,
                $description,
                $competition_type,
                $category,
                $start_date,
                $end_date,
                $venue,
                $max_participants ?: null,
                $registration_deadline ?: null,
                $prize,
                $rules,
                $eligibility,
                $banner_image,
                $registration_open,
                $status,
                $competition_id
            ]);

            $_SESSION['success'] = "Competition updated successfully!";
            header("Location: competitions.php");
            exit();

        } catch (PDOException $e) {
            error_log("Update competition error: " . $e->getMessage());
            $errors[] = "Failed to update competition. Please try again.";
        }
    }
}

// Only include header after potential redirects
require_once '../includes/header.php';
$page_title = "Edit Competition - " . $competition['title'];
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 d-none d-md-block">
            <div class="desktop-sidebar">
                <?php include 'sidebar.php'; ?>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Edit Competition</h1>
                <a href="competitions.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Competitions
                </a>
            </div>

            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if(isset($errors) && !empty($errors)): ?>
                <div class="alert alert-danger">
                    <h5>Please fix the following errors:</h5>
                    <ul class="mb-0">
                        <?php foreach($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-edit me-2"></i>Edit Competition Details
                    </h5>
                </div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Competition Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="title" name="title" required 
                                           value="<?php echo htmlspecialchars($competition['title']); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="draft" <?php echo $competition['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                        <option value="published" <?php echo $competition['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                                        <option value="cancelled" <?php echo $competition['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($competition['description']); ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="competition_type" class="form-label">Competition Type</label>
                                    <select class="form-control" id="competition_type" name="competition_type">
                                        <option value="individual" <?php echo $competition['competition_type'] === 'individual' ? 'selected' : ''; ?>>Individual</option>
                                        <option value="team" <?php echo $competition['competition_type'] === 'team' ? 'selected' : ''; ?>>Team</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="category" class="form-label">Category</label>
                                    <input type="text" class="form-control" id="category" name="category" 
                                           value="<?php echo htmlspecialchars($competition['category']); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control" id="start_date" name="start_date" required 
                                           value="<?php echo date('Y-m-d\TH:i', strtotime($competition['start_date'])); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="end_date" class="form-label">End Date <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control" id="end_date" name="end_date" required 
                                           value="<?php echo date('Y-m-d\TH:i', strtotime($competition['end_date'])); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="venue" class="form-label">Venue <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="venue" name="venue" required 
                                           value="<?php echo htmlspecialchars($competition['venue']); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="max_participants" class="form-label">Max Participants</label>
                                    <input type="number" class="form-control" id="max_participants" name="max_participants" 
                                           value="<?php echo $competition['max_participants']; ?>">
                                    <div class="form-text">Leave empty for no limit</div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="registration_deadline" class="form-label">Registration Deadline</label>
                                    <input type="datetime-local" class="form-control" id="registration_deadline" name="registration_deadline" 
                                           value="<?php echo $competition['registration_deadline'] ? date('Y-m-d\TH:i', strtotime($competition['registration_deadline'])) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="registration_open" name="registration_open" value="1" 
                                           <?php echo $competition['registration_open'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="registration_open">Registration Open</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="prize" class="form-label">Prizes</label>
                            <textarea class="form-control" id="prize" name="prize" rows="3"><?php echo htmlspecialchars($competition['prize']); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="rules" class="form-label">Rules & Guidelines</label>
                            <textarea class="form-control" id="rules" name="rules" rows="5"><?php echo htmlspecialchars($competition['rules']); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="eligibility" class="form-label">Eligibility Criteria</label>
                            <textarea class="form-control" id="eligibility" name="eligibility" rows="3"><?php echo htmlspecialchars($competition['eligibility']); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="banner_image" class="form-label">Banner Image</label>
                            <input type="file" class="form-control" id="banner_image" name="banner_image" accept="image/*">
                            <?php if($competition['banner_image']): ?>
                                <div class="mt-2">
                                    <small>Current image: 
                                        <a href="../uploads/competitions/<?php echo $competition['banner_image']; ?>" target="_blank">
                                            <?php echo $competition['banner_image']; ?>
                                        </a>
                                    </small>
                                    <br>
                                    <img src="../uploads/competitions/<?php echo $competition['banner_image']; ?>" 
                                         alt="Current banner" class="img-thumbnail mt-2" style="max-height: 150px;">
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>Update Competition
                            </button>
                            <a href="competitions.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>