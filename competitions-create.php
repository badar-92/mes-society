<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['super_admin', 'department_head', 'competition_head']);

$db = Database::getInstance()->getConnection();
$functions = new Functions();

$error = '';
$success = '';

// Check and create competitions table if it doesn't exist
try {
    $checkTable = $db->query("SHOW TABLES LIKE 'competitions'");
    if ($checkTable->rowCount() == 0) {
        // Create competitions table
        $createTable = $db->exec("
            CREATE TABLE `competitions` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `title` varchar(255) NOT NULL,
                `description` text,
                `competition_type` enum('individual','team') DEFAULT 'individual',
                `category` varchar(100) DEFAULT NULL,
                `start_date` datetime NOT NULL,
                `end_date` datetime NOT NULL,
                `venue` varchar(255) NOT NULL,
                `max_participants` int(11) DEFAULT NULL,
                `registration_deadline` datetime DEFAULT NULL,
                `prize` text,
                `rules` text,
                `eligibility` text,
                `banner_image` varchar(255) DEFAULT NULL,
                `registration_open` tinyint(1) DEFAULT '1',
                `status` enum('draft','published','cancelled') DEFAULT 'draft',
                `created_by` int(11) DEFAULT NULL,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        
        if ($createTable === false) {
            throw new Exception("Failed to create competitions table. Please check database permissions.");
        }
        
        $success = "Competitions table created successfully. You can now create competitions.";
    } else {
        // Check for required columns and add them if missing
        $requiredColumns = [
            'competition_type' => "ALTER TABLE competitions ADD COLUMN competition_type ENUM('individual','team') DEFAULT 'individual'",
            'category' => "ALTER TABLE competitions ADD COLUMN category VARCHAR(100) DEFAULT NULL",
            'registration_deadline' => "ALTER TABLE competitions ADD COLUMN registration_deadline DATETIME DEFAULT NULL",
            'prize' => "ALTER TABLE competitions ADD COLUMN prize TEXT DEFAULT NULL",
            'rules' => "ALTER TABLE competitions ADD COLUMN rules TEXT DEFAULT NULL",
            'eligibility' => "ALTER TABLE competitions ADD COLUMN eligibility TEXT DEFAULT NULL",
            'banner_image' => "ALTER TABLE competitions ADD COLUMN banner_image VARCHAR(255) DEFAULT NULL",
            'registration_open' => "ALTER TABLE competitions ADD COLUMN registration_open TINYINT(1) DEFAULT 1",
            'status' => "ALTER TABLE competitions ADD COLUMN status ENUM('draft','published','cancelled') DEFAULT 'draft'",
            'created_by' => "ALTER TABLE competitions ADD COLUMN created_by INT(11) DEFAULT NULL"
        ];
        
        foreach ($requiredColumns as $column => $sql) {
            $checkColumn = $db->query("SHOW COLUMNS FROM competitions LIKE '$column'");
            if ($checkColumn->rowCount() == 0) {
                $db->exec($sql);
            }
        }
    }
} catch (PDOException $e) {
    error_log("Competitions table check error: " . $e->getMessage());
    $error = "Database configuration error: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $competition_type = $_POST['competition_type'] ?? 'individual';
    $category = $_POST['category'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $venue = $_POST['venue'] ?? '';
    $max_participants = !empty($_POST['max_participants']) ? intval($_POST['max_participants']) : NULL;
    $registration_deadline = $_POST['registration_deadline'] ?? NULL;
    $prize = $_POST['prize'] ?? '';
    $rules = $_POST['rules'] ?? '';
    $eligibility = $_POST['eligibility'] ?? '';
    $status = $_POST['status'] ?? 'draft';
    $registration_open = isset($_POST['registration_open']) ? 1 : 0;
    
    // Validate required fields
    if (empty($title) || empty($description) || empty($start_date) || empty($venue) || empty($registration_deadline)) {
        $error = "Please fill all required fields (Title, Description, Start Date, Venue, and Registration Deadline)";
    } else {
        try {
            // Handle file upload
            $banner_image = null;
            if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../uploads/competitions/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $fileExtension = pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION);
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array(strtolower($fileExtension), $allowedExtensions)) {
                    $fileName = uniqid() . '.' . $fileExtension;
                    $filePath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $filePath)) {
                        $banner_image = $fileName;
                    }
                } else {
                    $error = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
                }
            }
            
            if (!$error) {
                // Insert competition
                $stmt = $db->prepare("
                    INSERT INTO competitions (
                        title, description, competition_type, category, start_date, end_date, 
                        venue, max_participants, registration_deadline, prize, rules, eligibility, 
                        banner_image, registration_open, status, created_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $result = $stmt->execute([
                    $title,
                    $description,
                    $competition_type,
                    $category,
                    $start_date,
                    $end_date,
                    $venue,
                    $max_participants,
                    $registration_deadline,
                    $prize,
                    $rules,
                    $eligibility,
                    $banner_image,
                    $registration_open,
                    $status,
                    $_SESSION['user_id']
                ]);
                
                if ($result) {
                    $_SESSION['success'] = "Competition created successfully!";
                    header("Location: competitions.php");
                    exit();
                } else {
                    $error = "Failed to create competition. Please try again.";
                }
            }
            
        } catch(PDOException $e) {
            error_log("Competition creation error: " . $e->getMessage());
            $error = "Database error: " . $e->getMessage();
        }
    }
}

$page_title = "Create New Competition";
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
                <h1>Create New Competition</h1>
                <a href="competitions.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Competitions
                </a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Competition Details</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Competition Title *</label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="competition_type" class="form-label">Competition Type *</label>
                                    <select class="form-select" id="competition_type" name="competition_type" required>
                                        <option value="">Select Type</option>
                                        <option value="individual" <?php echo ($_POST['competition_type'] ?? '') === 'individual' ? 'selected' : ''; ?>>Individual</option>
                                        <option value="team" <?php echo ($_POST['competition_type'] ?? '') === 'team' ? 'selected' : ''; ?>>Team</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description *</label>
                            <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="category" class="form-label">Category</label>
                                    <input type="text" class="form-control" id="category" name="category" 
                                           value="<?php echo htmlspecialchars($_POST['category'] ?? ''); ?>" placeholder="e.g., Programming, Robotics, Debate">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="venue" class="form-label">Venue *</label>
                                    <input type="text" class="form-control" id="venue" name="venue" 
                                           value="<?php echo htmlspecialchars($_POST['venue'] ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="start_date" class="form-label">Start Date & Time *</label>
                                    <input type="datetime-local" class="form-control" id="start_date" name="start_date" 
                                           value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="end_date" class="form-label">End Date & Time *</label>
                                    <input type="datetime-local" class="form-control" id="end_date" name="end_date" 
                                           value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="registration_deadline" class="form-label">Registration Deadline *</label>
                                    <input type="datetime-local" class="form-control" id="registration_deadline" name="registration_deadline" 
                                           value="<?php echo htmlspecialchars($_POST['registration_deadline'] ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="max_participants" class="form-label">Maximum Participants</label>
                                    <input type="number" class="form-control" id="max_participants" name="max_participants" 
                                           value="<?php echo htmlspecialchars($_POST['max_participants'] ?? ''); ?>" min="1" placeholder="Leave empty for unlimited">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="prize" class="form-label">Prize Information</label>
                                    <input type="text" class="form-control" id="prize" name="prize" 
                                           value="<?php echo htmlspecialchars($_POST['prize'] ?? ''); ?>" placeholder="e.g., Cash prize, Certificates, Trophies">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="rules" class="form-label">Competition Rules</label>
                            <textarea class="form-control" id="rules" name="rules" rows="3" placeholder="Detailed rules and guidelines for participants"><?php echo htmlspecialchars($_POST['rules'] ?? ''); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="eligibility" class="form-label">Eligibility Criteria</label>
                            <textarea class="form-control" id="eligibility" name="eligibility" rows="2" placeholder="Who can participate?"><?php echo htmlspecialchars($_POST['eligibility'] ?? ''); ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="banner_image" class="form-label">Banner Image</label>
                                    <input type="file" class="form-control" id="banner_image" name="banner_image" accept="image/*">
                                    <small class="text-muted">Recommended size: 1200x600 pixels. Allowed: JPG, PNG, GIF</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="draft" <?php echo ($_POST['status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                        <option value="published" <?php echo ($_POST['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Published</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="registration_open" name="registration_open" 
                                   <?php echo isset($_POST['registration_open']) ? 'checked' : 'checked'; ?>>
                            <label class="form-check-label" for="registration_open">Open for registration</label>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-accent btn-lg">
                                <i class="fas fa-plus me-2"></i>Create Competition
                            </button>
                            <button type="reset" class="btn btn-secondary btn-lg">Reset Form</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set minimum datetime to current time
    const now = new Date();
    const currentDateTime = now.toISOString().slice(0, 16);
    
    document.getElementById('start_date').min = currentDateTime;
    document.getElementById('end_date').min = currentDateTime;
    document.getElementById('registration_deadline').min = currentDateTime;
    
    // Update end date min when start date changes
    document.getElementById('start_date').addEventListener('change', function() {
        document.getElementById('end_date').min = this.value;
        document.getElementById('registration_deadline').max = this.value;
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>