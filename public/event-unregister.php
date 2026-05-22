<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

$session = new SessionManager();
$db = Database::getInstance()->getConnection();

// Get event ID from URL
$event_id = $_GET['id'] ?? 0;

if (!$event_id) {
    header("Location: events.php");
    exit();
}

// Fetch event details
$event = [];
$is_registered = false;
$is_logged_in = isset($_SESSION['user_id']);

try {
    $stmt = $db->prepare("SELECT * FROM events WHERE id = ? AND status = 'published'");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        $_SESSION['error'] = "Event not found or not published.";
        header("Location: events.php");
        exit();
    }

    // Check if user is already registered (if logged in)
    if ($is_logged_in) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM event_registrations WHERE event_id = ? AND user_id = ?");
        $stmt->execute([$event_id, $_SESSION['user_id']]);
        $is_registered = $stmt->fetchColumn() > 0;

        if (!$is_registered) {
            $_SESSION['error'] = "You are not registered for this event.";
            header("Location: event-details.php?id=" . $event_id);
            exit();
        }
    }

    // Optional: prevent unregistration if event has already started or deadline passed
    // if ($event['start_date'] && strtotime($event['start_date']) < time()) {
    //     $_SESSION['error'] = "Cannot unregister – event has already started.";
    //     header("Location: event-details.php?id=" . $event_id);
    //     exit();
    // }

} catch(PDOException $e) {
    error_log("Event unregistration error: " . $e->getMessage());
    $_SESSION['error'] = "Database error occurred.";
    header("Location: events.php");
    exit();
}

// Handle unregistration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($is_logged_in) {
            // Logged-in member: delete by user_id and event_id
            $stmt = $db->prepare("DELETE FROM event_registrations WHERE event_id = ? AND user_id = ?");
            $stmt->execute([$event_id, $_SESSION['user_id']]);
            $unregistered = $stmt->rowCount() > 0;

            if ($unregistered) {
                $_SESSION['success'] = "You have successfully unregistered from " . $event['title'] . ".";
            } else {
                $_SESSION['error'] = "Could not find your registration.";
            }

        } else {
            // Guest: verify email and delete the specific registration
            $email = $_POST['email'] ?? '';

            if (empty($email)) {
                $_SESSION['error'] = "Please enter the email you used to register.";
                header("Location: event-unregister.php?id=" . $event_id);
                exit();
            }

            // Validate email format (optional but consistent)
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = "Please enter a valid email address.";
                header("Location: event-unregister.php?id=" . $event_id);
                exit();
            }

            // Find the guest registration
            $stmt = $db->prepare("SELECT id FROM event_registrations WHERE event_id = ? AND email = ? AND user_id IS NULL");
            $stmt->execute([$event_id, $email]);
            $registration = $stmt->fetch();

            if (!$registration) {
                $_SESSION['error'] = "No registration found with that email for this event.";
                header("Location: event-unregister.php?id=" . $event_id);
                exit();
            }

            // Delete the guest registration
            $stmt = $db->prepare("DELETE FROM event_registrations WHERE id = ?");
            $stmt->execute([$registration['id']]);

            $_SESSION['success'] = "You have successfully unregistered from " . $event['title'] . ".";
        }

        header("Location: event-details.php?id=" . $event_id);
        exit();

    } catch(PDOException $e) {
        error_log("Unregistration error: " . $e->getMessage());
        $_SESSION['error'] = "Failed to unregister. Please try again.";
    }
}

$page_title = "Unregister from " . $event['title'];
require_once '../includes/header.php';
?>

<!-- Exact same layout and styling as event-register.php, only form content changed -->
<div class="container py-4 py-md-5">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10 col-xl-8">
            <!-- Back Button -->
            <div class="mb-4">
                <a href="event-details.php?id=<?php echo $event_id; ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-2"></i>Back to Event
                </a>
            </div>

            <div class="card shadow-lg border-0">
                <div class="card-header bg-gradient-accent text-white py-4">
                    <div class="row align-items-center">
                        <div class="col-12 col-md-8">
                            <h2 class="card-title h4 mb-2">
                                <i class="fas fa-user-minus me-2"></i>Cancel Registration
                            </h2>
                            <p class="card-subtitle mb-0 opacity-75">
                                Unregister from: <strong><?php echo $event['title']; ?></strong>
                            </p>
                        </div>
                        <div class="col-12 col-md-4 text-md-end mt-3 mt-md-0">
                            <span class="badge bg-white text-accent fs-6 p-3">
                                <i class="fas fa-users me-2"></i>
                                <?php
                                // Show current registrations count
                                $stmt = $db->prepare("SELECT COUNT(*) FROM event_registrations WHERE event_id = ?");
                                $stmt->execute([$event_id]);
                                $registered_count = $stmt->fetchColumn();
                                echo $registered_count;
                                ?>
                                registered
                            </span>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4 p-md-5">
                    <!-- Event Summary Card (identical to register page) -->
                    <div class="card border-accent mb-4">
                        <div class="card-body">
                            <h5 class="card-title text-accent mb-3">
                                <i class="fas fa-info-circle me-2"></i>Event Details
                            </h5>
                            <div class="row g-3">
                                <div class="col-12 col-sm-6 col-md-3">
                                    <div class="d-flex align-items-start">
                                        <i class="fas fa-calendar text-accent mt-1 me-3"></i>
                                        <div>
                                            <small class="text-muted d-block">Date</small>
                                            <strong><?php echo date('F j, Y', strtotime($event['start_date'])); ?></strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-3">
                                    <div class="d-flex align-items-start">
                                        <i class="fas fa-clock text-accent mt-1 me-3"></i>
                                        <div>
                                            <small class="text-muted d-block">Time</small>
                                            <strong><?php echo date('g:i A', strtotime($event['start_date'])); ?></strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-3">
                                    <div class="d-flex align-items-start">
                                        <i class="fas fa-map-marker-alt text-accent mt-1 me-3"></i>
                                        <div>
                                            <small class="text-muted d-block">Venue</small>
                                            <strong><?php echo $event['venue']; ?></strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-3">
                                    <div class="d-flex align-items-start">
                                        <i class="fas fa-tag text-accent mt-1 me-3"></i>
                                        <div>
                                            <small class="text-muted d-block">Type</small>
                                            <strong><?php echo ucfirst($event['event_type']); ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Unregistration Form -->
                    <form method="POST" action="" class="needs-validation" novalidate>
                        <?php if($is_logged_in): ?>
                            <!-- Logged-in Member Unregistration -->
                            <div class="alert alert-warning">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-exclamation-triangle fa-2x me-3 text-warning"></i>
                                    <div>
                                        <h6 class="mb-1">You are currently registered for this event.</h6>
                                        <p class="mb-0">Click the button below to cancel your registration. This action cannot be undone.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center py-4">
                                <button type="submit" class="btn btn-danger btn-lg px-5 py-3" onclick="return confirm('Are you sure you want to cancel your registration?');">
                                    <i class="fas fa-user-minus me-2"></i>
                                    Cancel My Registration
                                </button>
                                <p class="text-muted mt-2 small">
                                    You will be removed from the participant list.
                                </p>
                            </div>

                        <?php else: ?>
                            <!-- Guest Unregistration Form -->
                            <div class="alert alert-info">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-info-circle fa-2x me-3 text-info"></i>
                                    <div>
                                        <h6 class="mb-1">Guest Unregistration</h6>
                                        <p class="mb-0">Enter the email address you used when registering. We'll cancel your registration immediately.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-4">
                                <div class="col-12">
                                    <h5 class="border-bottom pb-2 mb-4 text-accent">
                                        <i class="fas fa-envelope me-2"></i>Verification
                                    </h5>
                                </div>

                                <div class="col-12 col-md-8 mx-auto">
                                    <div class="form-floating">
                                        <input type="email" class="form-control" id="email" name="email"
                                               value="<?php echo $_POST['email'] ?? ''; ?>"
                                               placeholder="name@uol.edu.pk" required>
                                        <label for="email">Your Registered Email <span class="text-danger">*</span></label>
                                        <div class="invalid-feedback">
                                            Please provide the email you used to register.
                                        </div>
                                    </div>
                                    <div class="form-text mt-2">
                                        <i class="fas fa-lock me-1"></i> We'll verify your registration and remove it immediately.
                                    </div>
                                </div>

                                <!-- Confirmation Checkbox -->
                                <div class="col-12 mt-4">
                                    <div class="form-check mb-4">
                                        <input class="form-check-input" type="checkbox" id="confirm_cancel" required>
                                        <label class="form-check-label" for="confirm_cancel">
                                            I confirm that I want to cancel my registration for <strong><?php echo $event['title']; ?></strong>. 
                                            I understand this action is irreversible.
                                        </label>
                                        <div class="invalid-feedback">
                                            You must confirm to cancel your registration.
                                        </div>
                                    </div>

                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="event-details.php?id=<?php echo $event_id; ?>" class="btn btn-outline-secondary btn-lg px-4 me-md-2">
                                            <i class="fas fa-times me-2"></i>Keep Registration
                                        </a>
                                        <button type="submit" class="btn btn-danger btn-lg px-5">
                                            <i class="fas fa-user-minus me-2"></i>Unregister Now
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </form>

                    <!-- Additional Info (mirrors register page) -->
                    <?php if(!$is_logged_in): ?>
                        <div class="mt-5 pt-4 border-top">
                            <div class="row">
                                <div class="col-12 col-md-6">
                                    <div class="d-flex align-items-start mb-3">
                                        <i class="fas fa-shield-alt text-accent mt-1 me-3"></i>
                                        <div>
                                            <h6 class="mb-1">Need Help?</h6>
                                            <p class="small text-muted mb-0">
                                                If you don't remember your registered email, contact us at <a href="mailto:mesuolofficial@gmail.com" class="text-accent">mesuolofficial@gmail.com</a> OR <a href="contact.php "  class="text-accent"> Contact </a>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="d-flex align-items-start mb-3">
                                        <i class="fas fa-question-circle text-accent mt-1 me-3"></i>
                                        <div>
                                            <h6 class="mb-1">Already a member?</h6>
                                            <p class="small text-muted mb-0">
                                                <a href="login.php?redirect=event-unregister.php?id=<?php echo $event_id; ?>" class="text-accent fw-bold">Log in</a> for one‑click unregistration.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Login Prompt for Guests (mirror) -->
            <?php if(!$is_logged_in): ?>
                <div class="card border-accent mt-4">
                    <div class="card-body text-center py-3">
                        <p class="mb-0">
                            Registered as a society member? 
                            <a href="login.php?redirect=event-unregister.php?id=<?php echo $event_id; ?>" class="text-accent fw-bold">
                                Login here to cancel instantly
                            </a>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Same JS validation and enhancements as event-register.php -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Bootstrap form validation
    var forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // Real-time email validation (same pattern, but not requiring @uol.edu.pk for unregister)
    const emailInput = document.getElementById('email');
    if (emailInput) {
        emailInput.addEventListener('input', function() {
            if (this.value && !this.checkValidity()) {
                // Let browser handle validity via 'required' and 'email' type
            }
        });
    }

    // Prevent accidental submission on Enter key (optional)
    const confirmCheckbox = document.getElementById('confirm_cancel');
    if (confirmCheckbox) {
        confirmCheckbox.addEventListener('change', function() {
            // Bootstrap will handle validation automatically
        });
    }
});
</script>

<style>
/* Exact same styles as event-register.php – copied for consistency */
.bg-gradient-accent {
    background: linear-gradient(135deg, var(--accent-color) 0%, #ff8c42 100%);
}

.form-floating > .form-control:focus ~ label,
.form-floating > .form-control:not(:placeholder-shown) ~ label {
    color: var(--accent-color);
}

.form-control:focus {
    border-color: var(--accent-color);
    box-shadow: 0 0 0 0.2rem rgba(255, 102, 0, 0.25);
}

.btn-accent {
    background: linear-gradient(135deg, var(--accent-color) 0%, #ff8c42 100%);
    border: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-accent:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(255, 102, 0, 0.3);
}

.btn-danger {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    border: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
}

.card {
    border-radius: 15px;
    overflow: hidden;
}

.card-header {
    border-bottom: none;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .card-body {
        padding: 1.5rem !important;
    }
    
    .btn-lg {
        padding: 0.75rem 1.5rem;
        font-size: 1rem;
    }
    
    .display-4 {
        font-size: 2rem;
    }
}

@media (max-width: 576px) {
    .container {
        padding-left: 15px;
        padding-right: 15px;
    }
    
    .card-body {
        padding: 1rem !important;
    }
    
    .btn-group .btn {
        font-size: 0.875rem;
    }
}

/* Custom scrollbar for better UX */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

::-webkit-scrollbar-thumb {
    background: var(--accent-color);
    border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
    background: #e55d00;
}
</style>

<?php require_once '../includes/footer.php'; ?>