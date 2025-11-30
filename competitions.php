<?php
$page_title = "Competitions";
require_once '../includes/header.php';
require_once '../includes/database.php';

$db = Database::getInstance()->getConnection();

// Get competitions
$competitions = [];
try {
    $stmt = $db->query("SELECT * FROM competitions WHERE status = 'published' ORDER BY start_date DESC");
    $competitions = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Competitions query error: " . $e->getMessage());
}
?>

<div class="container py-5">
    <h1 class="text-center mb-5">Competitions</h1>

    <div class="row">
        <?php if(empty($competitions)): ?>
            <div class="col-12 text-center">
                <div class="alert alert-info">
                    <i class="fas fa-trophy me-2"></i>
                    No competitions available at the moment. Check back soon for exciting challenges!
                </div>
            </div>
        <?php else: ?>
            <?php foreach($competitions as $competition): ?>
                <div class="col-md-6 mb-4">
                    <div class="card h-100 shadow-sm competition-card">
                        <div class="card-header bg-accent text-white">
                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($competition['title']); ?></h5>
                        </div>
                        <div class="card-body">
                            <p class="card-text"><?php echo htmlspecialchars($competition['description']); ?></p>
                            
                            <div class="competition-meta mb-3">
                                <div class="row">
                                    <div class="col-6">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i>
                                            Start: <?php echo $competition['start_date'] ? date('M j, Y', strtotime($competition['start_date'])) : 'TBA'; ?>
                                        </small>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i>
                                            End: <?php echo $competition['end_date'] ? date('M j, Y', strtotime($competition['end_date'])) : 'TBA'; ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="row mt-1">
                                    <div class="col-6">
                                        <small class="text-muted">
                                            <i class="fas fa-users me-1"></i>
                                            Type: <?php echo $competition['competition_type'] === 'team' ? 'Team' : 'Individual'; ?>
                                        </small>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">
                                            <i class="fas fa-map-marker me-1"></i>
                                            Venue: <?php echo htmlspecialchars($competition['venue'] ?? 'TBA'); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <?php if($competition['rules']): ?>
                                <div class="mb-3">
                                    <h6>Rules & Guidelines:</h6>
                                    <p class="small"><?php echo substr(htmlspecialchars($competition['rules']), 0, 150) . '...'; ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if($competition['prize']): ?>
                                <div class="prizes-section">
                                    <h6>Prizes:</h6>
                                    <p class="small text-success"><?php echo htmlspecialchars($competition['prize']); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-transparent">
                            <a href="competition-details.php?id=<?php echo $competition['id']; ?>" class="btn btn-accent btn-sm">
                                <i class="fas fa-info-circle me-1"></i>View Details
                            </a>
                            <?php 
                            $regDeadline = $competition['registration_deadline'];
                            $isRegOpen = $regDeadline && (strtotime($regDeadline) >= time());
                            ?>
                            <?php if($isRegOpen && $competition['registration_open']): ?>
                                <a href="competition-register.php?id=<?php echo $competition['id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-user-plus me-1"></i>Register
                                </a>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-sm" disabled>
                                    <i class="fas fa-clock me-1"></i>Registration Closed
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Competition Categories -->
    <div class="row mt-5">
        <div class="col-12">
            <h2 class="text-center mb-4">Competition Categories</h2>
        </div>
        
        <div class="col-md-4 mb-3">
            <div class="card text-center border-accent">
                <div class="card-body">
                    <i class="fas fa-robot fa-2x text-accent mb-3"></i>
                    <h5>Robotics</h5>
                    <p class="text-muted">Design and build innovative robots</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-3">
            <div class="card text-center border-accent">
                <div class="card-body">
                    <i class="fas fa-cogs fa-2x text-accent mb-3"></i>
                    <h5>CAD Design</h5>
                    <p class="text-muted">3D modeling and design challenges</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-3">
            <div class="card text-center border-accent">
                <div class="card-body">
                    <i class="fas fa-bolt fa-2x text-accent mb-3"></i>
                    <h5>Innovation</h5>
                    <p class="text-muted">Creative problem-solving competitions</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>