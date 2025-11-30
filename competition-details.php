<?php
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
    error_log("Competition details error: " . $e->getMessage());
}

if (!$competition) {
    header("Location: competitions.php");
    exit();
}

// Only include header after potential redirects
require_once '../includes/header.php';
$page_title = $competition['title'];
?>

<div class="container py-5">
    <div class="row">
        <div class="col-md-8">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="competitions.php">Competitions</a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($competition['title']); ?></li>
                </ol>
            </nav>

            <h1 class="mb-4"><?php echo htmlspecialchars($competition['title']); ?></h1>

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

            <?php if($competition['banner_image']): ?>
                <img src="<?php echo SITE_URL . '/uploads/competitions/' . $competition['banner_image']; ?>" 
                     alt="<?php echo htmlspecialchars($competition['title']); ?>" 
                     class="img-fluid rounded mb-4">
            <?php endif; ?>

            <div class="competition-details">
                <p class="lead"><?php echo htmlspecialchars($competition['description']); ?></p>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Competition Information</h5>
                                <ul class="list-unstyled">
                                    <li><strong>Type:</strong> <?php echo $competition['competition_type'] === 'team' ? 'Team' : 'Individual'; ?></li>
                                    <li><strong>Category:</strong> <?php echo htmlspecialchars($competition['category'] ?? 'N/A'); ?></li>
                                    <li><strong>Start Date:</strong> <?php echo $competition['start_date'] ? date('F j, Y', strtotime($competition['start_date'])) : 'TBA'; ?></li>
                                    <li><strong>End Date:</strong> <?php echo $competition['end_date'] ? date('F j, Y', strtotime($competition['end_date'])) : 'TBA'; ?></li>
                                    <li><strong>Venue:</strong> <?php echo htmlspecialchars($competition['venue']); ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Registration</h5>
                                <ul class="list-unstyled">
                                    <li><strong>Registration Deadline:</strong> 
                                        <?php echo $competition['registration_deadline'] ? date('F j, Y', strtotime($competition['registration_deadline'])) : 'N/A'; ?>
                                    </li>
                                    <li><strong>Max Participants:</strong> 
                                        <?php echo $competition['max_participants'] ?? 'No limit'; ?>
                                    </li>
                                    <li><strong>Status:</strong> 
                                        <?php 
                                        $regDeadline = $competition['registration_deadline'];
                                        $isRegOpen = $regDeadline && (strtotime($regDeadline) >= time()) && $competition['registration_open'];
                                        echo $isRegOpen ? '<span class="badge bg-success">Open</span>' : '<span class="badge bg-secondary">Closed</span>';
                                        ?>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if($competition['rules']): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Rules & Guidelines</h5>
                            <p><?php echo nl2br(htmlspecialchars($competition['rules'])); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if($competition['eligibility']): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Eligibility</h5>
                            <p><?php echo nl2br(htmlspecialchars($competition['eligibility'])); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if($competition['prize']): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Prizes</h5>
                            <p class="text-success"><?php echo nl2br(htmlspecialchars($competition['prize'])); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card sticky-top" style="top: 20px;">
                <div class="card-body text-center">
                    <?php 
                    $regDeadline = $competition['registration_deadline'];
                    $isRegOpen = $regDeadline && (strtotime($regDeadline) >= time()) && $competition['registration_open'];
                    ?>
                    <?php if($isRegOpen): ?>
                        <a href="competition-register.php?id=<?php echo $competition['id']; ?>" class="btn btn-primary btn-lg w-100 mb-3">
                            <i class="fas fa-user-plus me-2"></i>Register Now
                        </a>
                        <p class="text-muted small">
                            <i class="fas fa-clock me-1"></i>
                            Registration closes: <?php echo date('F j, Y', strtotime($competition['registration_deadline'])); ?>
                        </p>
                    <?php else: ?>
                        <button class="btn btn-secondary btn-lg w-100 mb-3" disabled>
                            <i class="fas fa-clock me-2"></i>Registration Closed
                        </button>
                    <?php endif; ?>

                    <div class="d-grid gap-2">
                        <a href="competitions.php" class="btn btn-outline-accent">
                            <i class="fas fa-arrow-left me-2"></i>Back to Competitions
                        </a>
                        <?php if($isRegOpen): ?>
                            <a href="competition-register.php?id=<?php echo $competition['id']; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-info-circle me-2"></i>View Registration Form
                            </a>
                        <?php endif; ?>
                        
                        <!-- Check if results are available -->
                        <?php
                        $results_available = false;
                        try {
                            $stmt = $db->prepare("SELECT * FROM competition_results WHERE competition_id = ? AND published = 1");
                            $stmt->execute([$competition_id]);
                            $results_available = $stmt->fetch() ? true : false;
                        } catch(PDOException $e) {
                            error_log("Results check error: " . $e->getMessage());
                        }
                        ?>
                        
                        <?php if($results_available): ?>
                            <a href="competition-results.php?id=<?php echo $competition['id']; ?>" class="btn btn-outline-success">
                                <i class="fas fa-trophy me-2"></i>View Results
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>