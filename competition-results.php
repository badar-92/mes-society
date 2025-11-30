<?php
require_once '../includes/header.php';
require_once '../includes/database.php';

$db = Database::getInstance()->getConnection();

$competition_id = $_GET['id'] ?? 0;

// Get competition details
$competition = null;
try {
    $stmt = $db->prepare("SELECT * FROM competitions WHERE id = ? AND status = 'published'");
    $stmt->execute([$competition_id]);
    $competition = $stmt->fetch();
} catch(PDOException $e) {
    error_log("Competition results error: " . $e->getMessage());
}

if (!$competition) {
    header("Location: competitions.php");
    exit();
}

// Get published results
$results = null;
try {
    $stmt = $db->prepare("SELECT * FROM competition_results WHERE competition_id = ? AND published = 1");
    $stmt->execute([$competition_id]);
    $results = $stmt->fetch();
} catch(PDOException $e) {
    error_log("Competition results query error: " . $e->getMessage());
}

$page_title = "Results - " . $competition['title'];
?>

<div class="container py-5">
    <div class="row">
        <div class="col-md-8">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="competitions.php">Competitions</a></li>
                    <li class="breadcrumb-item"><a href="competition-details.php?id=<?php echo $competition_id; ?>"><?php echo htmlspecialchars($competition['title']); ?></a></li>
                    <li class="breadcrumb-item active">Results</li>
                </ol>
            </nav>

            <h1 class="mb-4">Results - <?php echo htmlspecialchars($competition['title']); ?></h1>

            <?php if(!$results): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Results for this competition are not yet available. Please check back later.
                </div>
            <?php else: ?>
                <?php if($results['result_data']): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-trophy me-2"></i>Competition Results
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php echo nl2br(htmlspecialchars($results['result_data'])); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if($results['result_file']): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-download me-2"></i>Download Results
                            </h5>
                        </div>
                        <div class="card-body text-center">
                            <a href="../uploads/competition-results/<?php echo $results['result_file']; ?>" 
                               class="btn btn-primary btn-lg" target="_blank">
                                <i class="fas fa-file-download me-2"></i>Download Result File
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="col-md-4">
            <div class="card sticky-top" style="top: 20px;">
                <div class="card-body">
                    <h5 class="card-title">Competition Info</h5>
                    <ul class="list-unstyled">
                        <li><strong>Title:</strong> <?php echo htmlspecialchars($competition['title']); ?></li>
                        <li><strong>Type:</strong> <?php echo $competition['competition_type'] === 'team' ? 'Team' : 'Individual'; ?></li>
                        <li><strong>Category:</strong> <?php echo htmlspecialchars($competition['category'] ?? 'N/A'); ?></li>
                    </ul>
                    
                    <div class="d-grid gap-2">
                        <a href="competition-details.php?id=<?php echo $competition_id; ?>" class="btn btn-outline-accent">
                            <i class="fas fa-arrow-left me-2"></i>Back to Competition
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>