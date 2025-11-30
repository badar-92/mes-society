<?php
$page_title = "Events";
require_once '../includes/header.php';
require_once '../includes/database.php';

$db = Database::getInstance()->getConnection();

// Get events (both upcoming and past)
$upcoming_events = [];
$past_events = [];

try {
    $stmt = $db->query("SELECT * FROM events WHERE status = 'published' AND start_date >= NOW() ORDER BY start_date");
    $upcoming_events = $stmt->fetchAll();

    $stmt = $db->query("SELECT * FROM events WHERE status = 'published' AND start_date < NOW() ORDER BY start_date DESC");
    $past_events = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Events query error: " . $e->getMessage());
}
?>

<div class="container py-5">
    <h1 class="text-center mb-5">Events</h1>

    <!-- Upcoming Events -->
    <section class="mb-5">
        <h2 class="mb-4">Upcoming Events</h2>
        <div class="row">
            <?php if(empty($upcoming_events)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No upcoming events at the moment. Please check back later.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach($upcoming_events as $event): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 shadow-sm">
                            <?php if($event['banner_image']): ?>
                                <img src="../uploads/event-images/<?php echo $event['banner_image']; ?>" class="card-img-top" alt="<?php echo $event['title']; ?>" style="height: 200px; object-fit: cover;">
                            <?php else: ?>
                                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                                    <i class="fas fa-calendar-alt fa-3x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $event['title']; ?></h5>
                                <p class="card-text"><?php echo substr($event['description'], 0, 100) . '...'; ?></p>
                                <div class="event-meta">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo date('M j, Y', strtotime($event['start_date'])); ?>
                                    </small>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-map-marker-alt me-1"></i>
                                        <?php echo $event['venue']; ?>
                                    </small>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent">
                                <a href="event-details.php?id=<?php echo $event['id']; ?>" class="btn btn-accent btn-sm">View Details</a>
                                <?php if($event['registration_deadline'] >= date('Y-m-d H:i:s')): ?>
                                    <a href="event-register.php?id=<?php echo $event['id']; ?>" class="btn btn-primary btn-sm">Register</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- Past Events -->
    <section>
        <h2 class="mb-4">Past Events</h2>
        <div class="row">
            <?php if(empty($past_events)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No past events to display.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach($past_events as $event): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 shadow-sm">
                            <?php if($event['banner_image']): ?>
                                <img src="../uploads/event-images/<?php echo $event['banner_image']; ?>" class="card-img-top" alt="<?php echo $event['title']; ?>" style="height: 200px; object-fit: cover;">
                            <?php else: ?>
                                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                                    <i class="fas fa-calendar-alt fa-3x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $event['title']; ?></h5>
                                <p class="card-text"><?php echo substr($event['description'], 0, 100) . '...'; ?></p>
                                <div class="event-meta">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo date('M j, Y', strtotime($event['start_date'])); ?>
                                    </small>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent">
                                <a href="event-details.php?id=<?php echo $event['id']; ?>" class="btn btn-accent btn-sm">View Details</a>
                                <a href="../public/gallery.php?event=<?php echo $event['id']; ?>" class="btn btn-outline-secondary btn-sm">View Photos</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php require_once '../includes/footer.php'; ?>