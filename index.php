<?php
$page_title = "Home";
require_once '../includes/header.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

$db = Database::getInstance()->getConnection();
$functions = new Functions();

// Get upcoming events
$events = [];
try {
    $stmt = $db->query("SELECT * FROM events WHERE status = 'published' AND start_date >= NOW() ORDER BY start_date LIMIT 5");
    $events = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Events query error: " . $e->getMessage());
}

// Get team members for carousel
$team_members = $functions->getTeamMembers(true);

// Get active competitions
$competitions = [];
try {
    $stmt = $db->query("SELECT * FROM competitions WHERE status = 'published' AND registration_open = 1 AND registration_deadline >= NOW() ORDER BY start_date LIMIT 3");
    $competitions = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Competitions query error: " . $e->getMessage());
}
?>

<!-- Hero Section -->
<section class="hero-section" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%); color: white;">
    <div class="container">
        <div class="row align-items-center min-vh-80">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-4">Mechanical Engineering Society</h1>
                <p class="lead mb-4">University of Lahore - Connecting, Creating, and Innovating Together</p>
                <div class="d-flex gap-3 flex-wrap">
                    <a href="apply.php" class="btn btn-warning btn-lg px-4">Join Our Society</a>
                    <a href="events.php" class="btn btn-outline-light btn-lg px-4">View Events</a>
                </div>
            </div>
            <div class="col-lg-6">
                <img src="../assets/images/logo-mes.png" alt="MES Society" class="img-fluid rounded-3">
            </div>
        </div>
    </div>
</section>

<!-- Quick Stats -->
<section class="py-5 bg-light" id="stats-section">
    <div class="container">
        <div class="row text-center">
            <div class="col-md-3">
                <h2 class="display-4 fw-bold text-accent counter" data-target="150">0</h2>
                <p class="lead">Active Members</p>
            </div>
            <div class="col-md-3">
                <h2 class="display-4 fw-bold text-accent counter" data-target="50">0</h2>
                <p class="lead">Events Conducted</p>
            </div>
            <div class="col-md-3">
                <h2 class="display-4 fw-bold text-accent counter" data-target="30">0</h2>
                <p class="lead">Competitions</p>
            </div>
            <div class="col-md-3">
                <h2 class="display-4 fw-bold text-accent counter" data-target="10">0</h2>
                <p class="lead">Workshops</p>
            </div>
        </div>
    </div>
</section>

<!-- Team Carousel Section -->
<?php if (!empty($team_members)): ?>
<section class="py-5">
    <div class="container">
        <h2 class="text-center mb-5">Meet Our Team</h2>
        <div id="teamCarousel" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-inner">
                <?php foreach($team_members as $index => $member): 
                    $contact_info = json_decode($member['contact_info'], true);
                ?>
                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                    <div class="row justify-content-center">
                        <div class="col-md-8 text-center">
                            <img src="../uploads/profile-pictures/<?php echo $member['profile_picture'] ?? 'default-avatar.png'; ?>" 
                                 alt="<?php echo $member['name']; ?>" 
                                 class="rounded-circle mb-4 team-avatar" 
                                 style="width: 200px; height: 200px; object-fit: cover;">
                            <h3 class="mb-2"><?php echo $member['name']; ?></h3>
                            <h4 class="text-accent mb-3"><?php echo $member['position']; ?></h4>
                            <p class="lead mb-4"><?php echo $member['bio']; ?></p>
                            <?php if ($contact_info): ?>
                            <div class="contact-info">
                                <?php if(isset($contact_info['email'])): ?>
                                    <div class="mb-2">
                                        <i class="fas fa-envelope me-2 text-muted"></i>
                                        <a href="mailto:<?php echo $contact_info['email']; ?>" class="text-decoration-none">
                                            <?php echo $contact_info['email']; ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <?php if(isset($contact_info['phone'])): ?>
                                    <div>
                                        <i class="fas fa-phone me-2 text-muted"></i>
                                        <a href="tel:<?php echo $contact_info['phone']; ?>" class="text-decoration-none">
                                            <?php echo $contact_info['phone']; ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if (count($team_members) > 1): ?>
            <button class="carousel-control-prev" type="button" data-bs-target="#teamCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#teamCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
            <?php endif; ?>
        </div>
        
        <!-- Carousel Indicators -->
        <?php if (count($team_members) > 1): ?>
        <div class="text-center mt-4">
            <?php foreach($team_members as $index => $member): ?>
                <button type="button" data-bs-target="#teamCarousel" data-bs-slide-to="<?php echo $index; ?>" 
                        class="<?php echo $index === 0 ? 'active' : ''; ?> carousel-indicator"
                        aria-label="Slide <?php echo $index + 1; ?>"></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- Upcoming Events -->
<section class="py-5 bg-light">
    <div class="container">
        <h2 class="text-center mb-5">Upcoming Events</h2>
        <div class="row">
            <?php if(empty($events)): ?>
            <div class="col-12 text-center">
                <p class="lead">No upcoming events at the moment. Check back soon!</p>
            </div>
            <?php else: ?>
            <?php foreach($events as $event): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100 shadow-sm">
                    <?php if($event['banner_image']): ?>
                    <img src="../uploads/event-images/<?php echo $event['banner_image']; ?>" class="card-img-top" alt="<?php echo $event['title']; ?>" style="height: 200px; object-fit: cover;">
                    <?php else: ?>
                    <div class="card-img-top bg-secondary d-flex align-items-center justify-content-center" style="height: 200px;">
                        <i class="fas fa-calendar-alt fa-3x text-white"></i>
                    </div>
                    <?php endif; ?>
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><?php echo $event['title']; ?></h5>
                        <p class="card-text flex-grow-1"><?php echo substr($event['description'], 0, 100) . '...'; ?></p>
                        <div class="event-meta mb-3">
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
                        <a href="event-details.php?id=<?php echo $event['id']; ?>" class="btn btn-accent btn-sm mt-auto">View Details</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="text-center mt-4">
            <a href="events.php" class="btn btn-primary">View All Events</a>
        </div>
    </div>
</section>

<!-- Active Competitions Section -->
<section class="py-5">
    <div class="container">
        <h2 class="text-center mb-5">Active Competitions</h2>
        <div class="row">
            <?php if(empty($competitions)): ?>
            <div class="col-12 text-center">
                <p class="lead">No active competitions at the moment. Check back soon for new challenges!</p>
            </div>
            <?php else: ?>
            <?php foreach($competitions as $competition): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100 shadow-sm">
                    <?php if($competition['banner_image']): ?>
                    <img src="../uploads/competitions/<?php echo $competition['banner_image']; ?>" class="card-img-top" alt="<?php echo $competition['title']; ?>" style="height: 200px; object-fit: cover;">
                    <?php else: ?>
                    <div class="card-img-top bg-warning d-flex align-items-center justify-content-center" style="height: 200px;">
                        <i class="fas fa-trophy fa-3x text-white"></i>
                    </div>
                    <?php endif; ?>
                    <div class="card-body d-flex flex-column">
                        <span class="badge bg-warning mb-2"><?php echo ucfirst($competition['competition_type']); ?></span>
                        <h5 class="card-title"><?php echo $competition['title']; ?></h5>
                        <p class="card-text flex-grow-1"><?php echo substr($competition['description'], 0, 100) . '...'; ?></p>
                        <div class="competition-meta mb-3">
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i>
                                Register by: <?php echo date('M j, Y', strtotime($competition['registration_deadline'])); ?>
                            </small>
                            <?php if($competition['prize']): ?>
                            <br>
                            <small class="text-muted">
                                <i class="fas fa-gift me-1"></i>
                                Prize: <?php echo $competition['prize']; ?>
                            </small>
                            <?php endif; ?>
                        </div>
                        <a href="competition-details.php?id=<?php echo $competition['id']; ?>" class="btn btn-warning btn-sm mt-auto">View Details</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="text-center mt-4">
            <a href="competitions.php" class="btn btn-outline-warning">View All Competitions</a>
        </div>
    </div>
</section>

<!-- Call to Action Section -->
<section class="py-5 bg-accent text-white">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h3 class="fw-bold mb-2">Ready to Join MES Society?</h3>
                <p class="mb-0">Become part of our growing community of mechanical engineering enthusiasts and unlock your potential.</p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="apply.php" class="btn btn-light btn-lg">
                    <i class="fas fa-user-plus me-2"></i>Apply Now
                </a>
            </div>
        </div>
    </div>
</section>

<script>
// Number counter animation
function animateCounter(element, target, duration = 2000) {
    let start = 0;
    const increment = target / (duration / 16);
    const timer = setInterval(() => {
        start += increment;
        if (start >= target) {
            element.textContent = target + '+';
            clearInterval(timer);
        } else {
            element.textContent = Math.floor(start) + '+';
        }
    }, 16);
}

// Intersection Observer for counter animation
document.addEventListener('DOMContentLoaded', function() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const counters = entry.target.querySelectorAll('.counter');
                counters.forEach(counter => {
                    const target = parseInt(counter.getAttribute('data-target'));
                    animateCounter(counter, target);
                });
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });

    // Observe the stats section
    const statsSection = document.getElementById('stats-section');
    if (statsSection) {
        observer.observe(statsSection);
    }

    // Auto-advance team carousel
    const teamCarousel = document.getElementById('teamCarousel');
    if (teamCarousel) {
        const carousel = new bootstrap.Carousel(teamCarousel, {
            interval: 5000, // Change slide every 5 seconds
            pause: 'hover',
            wrap: true
        });
    }

    // Initialize all tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<style>
.hero-section {
    padding: 100px 0;
}

.min-vh-80 {
    min-height: 80vh;
}

.team-avatar {
    border: 4px solid var(--accent-color);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.team-avatar:hover {
    transform: scale(1.05);
}

.carousel-indicator {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid var(--accent-color);
    background: transparent;
    margin: 0 5px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.carousel-indicator.active {
    background: var(--accent-color);
    transform: scale(1.2);
}

.carousel-indicator:hover {
    background: var(--accent-color);
    opacity: 0.7;
}

.carousel-control-prev,
.carousel-control-next {
    width: 5%;
    opacity: 0.8;
    transition: opacity 0.3s ease;
}

.carousel-control-prev:hover,
.carousel-control-next:hover {
    opacity: 1;
}

.carousel-control-prev-icon,
.carousel-control-next-icon {
    background-color: var(--accent-color);
    border-radius: 50%;
    padding: 15px;
    background-size: 60%;
}

.card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.btn {
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-2px);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .hero-section {
        padding: 60px 0;
        text-align: center;
    }
    
    .display-4 {
        font-size: 2rem !important;
    }
    
    .team-avatar {
        width: 150px;
        height: 150px;
    }
    
    .carousel-control-prev,
    .carousel-control-next {
        width: 15%;
    }
}

@media (max-width: 576px) {
    .hero-section {
        padding: 40px 0;
    }
    
    .display-4 {
        font-size: 1.8rem !important;
    }
    
    .counter {
        font-size: 2.5rem !important;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>