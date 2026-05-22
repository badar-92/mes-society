<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
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
    error_log("Competition results error: " . $e->getMessage());
}

if (!$competition) {
    header("Location: competitions.php");
    exit();
}

// Get existing results
$results = null;
try {
    $stmt = $db->prepare("SELECT * FROM competition_results WHERE competition_id = ?");
    $stmt->execute([$competition_id]);
    $results = $stmt->fetch();
} catch(PDOException $e) {
    error_log("Competition results query error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result_data = $_POST['result_data'] ?? '';
    $publish = isset($_POST['publish']) ? 1 : 0;

    $result_file = null;
    if (isset($_FILES['result_file']) && $_FILES['result_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/competition-results/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_extension = pathinfo($_FILES['result_file']['name'], PATHINFO_EXTENSION);
        $filename = 'results_' . $competition_id . '_' . time() . '.' . $file_extension;
        $destination = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['result_file']['tmp_name'], $destination)) {
            $result_file = $filename;
        } else {
            $_SESSION['error'] = "Failed to upload result file.";
        }
    }

    try {
        if ($results) {
            // Update existing results
            $stmt = $db->prepare("
                UPDATE competition_results 
                SET result_data = ?, result_file = ?, published = ? 
                WHERE competition_id = ?
            ");
            $stmt->execute([$result_data, $result_file ?: $results['result_file'], $publish, $competition_id]);
        } else {
            // Insert new results
            $stmt = $db->prepare("
                INSERT INTO competition_results (competition_id, result_data, result_file, published) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$competition_id, $result_data, $result_file, $publish]);
        }

        $_SESSION['success'] = "Results saved successfully";
        header("Location: competition-results.php?id=" . $competition_id);
        exit();

    } catch (PDOException $e) {
        error_log("Save results error: " . $e->getMessage());
        $_SESSION['error'] = "Failed to save results.";
    }
}

$page_title = "Results - " . $competition['title'];
require_once '../includes/header.php';
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
                <h1>Results - <?php echo htmlspecialchars($competition['title']); ?></h1>
                <a href="competitions.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Competitions
                </a>
            </div>

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

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-line me-2"></i>Competition Results
                    </h5>
                </div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="result_data" class="form-label">Results (Text/HTML)</label>
                            <textarea class="form-control" id="result_data" name="result_data" rows="10"><?php echo $results['result_data'] ?? ''; ?></textarea>
                            <div class="form-text">You can enter the results in text format or use HTML for formatting.</div>
                        </div>

                        <div class="mb-3">
                            <label for="result_file" class="form-label">Result File (PDF, Image, etc.)</label>
                            <input type="file" class="form-control" id="result_file" name="result_file">
                            <?php if($results && $results['result_file']): ?>
                                <div class="mt-2">
                                    <small>Current file: 
                                        <a href="../uploads/competition-results/<?php echo $results['result_file']; ?>" target="_blank">
                                            <?php echo $results['result_file']; ?>
                                        </a>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="publish" name="publish" value="1" 
                                   <?php echo ($results['published'] ?? 0) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="publish">Publish results (make visible to public)</label>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">Save Results</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if($results && $results['published']): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-eye me-2"></i>Preview
                        </h5>
                    </div>
                    <div class="card-body">
                        <h6>Text/HTML Results:</h6>
                        <div class="border p-3 rounded bg-light">
                            <?php echo nl2br(htmlspecialchars($results['result_data'])); ?>
                        </div>

                        <?php if($results['result_file']): ?>
                            <h6 class="mt-3">File:</h6>
                            <a href="../uploads/competition-results/<?php echo $results['result_file']; ?>" target="_blank" class="btn btn-outline-primary">
                                <i class="fas fa-download me-2"></i>Download Result File
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>