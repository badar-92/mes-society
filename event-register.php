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
$already_registered = false;
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
        $already_registered = $stmt->fetchColumn() > 0;

        if ($already_registered) {
            $_SESSION['info'] = "You are already registered for this event.";
            header("Location: event-details.php?id=" . $event_id);
            exit();
        }
    }

    // Check registration deadline
    if ($event['registration_deadline'] && strtotime($event['registration_deadline']) < time()) {
        $_SESSION['error'] = "Registration deadline has passed.";
        header("Location: event-details.php?id=" . $event_id);
        exit();
    }

    // Check maximum participants
    $stmt = $db->prepare("SELECT COUNT(*) FROM event_registrations WHERE event_id = ?");
    $stmt->execute([$event_id]);
    $registered_count = $stmt->fetchColumn();

    if ($event['max_participants'] && $registered_count >= $event['max_participants']) {
        $_SESSION['error'] = "This event has reached maximum capacity.";
        header("Location: event-details.php?id=" . $event_id);
        exit();
    }

} catch(PDOException $e) {
    error_log("Event registration error: " . $e->getMessage());
    $_SESSION['error'] = "Database error occurred.";
    header("Location: events.php");
    exit();
}

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($is_logged_in) {
            // For logged-in members - use user_id
            $stmt = $db->prepare("INSERT INTO event_registrations (event_id, user_id, registered_at) VALUES (?, ?, NOW())");
            $stmt->execute([$event_id, $_SESSION['user_id']]);
        } else {
            // For non-members (guests) - store directly without creating user account
            $name = $_POST['name'] ?? '';
            $email = $_POST['email'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $sap_id = $_POST['sap_id'] ?? '';
            $department = $_POST['department'] ?? '';
            $semester = $_POST['semester'] ?? '';

            // Validate required fields for guests
            if (empty($name) || empty($email) || empty($phone) || empty($sap_id) || empty($department) || empty($semester)) {
                $_SESSION['error'] = "Please fill all required fields.";
                header("Location: event-register.php?id=" . $event_id);
                exit();
            }

            // Validate university email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/@.*uol\.edu\.pk$/', $email)) {
                $_SESSION['error'] = "Please use a valid University of Lahore email address (@uol.edu.pk).";
                header("Location: event-register.php?id=" . $event_id);
                exit();
            }

            // Check if guest is already registered with this email for this event
            $stmt = $db->prepare("SELECT COUNT(*) FROM event_registrations WHERE event_id = ? AND email = ?");
            $stmt->execute([$event_id, $email]);
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['error'] = "This email is already registered for the event.";
                header("Location: event-register.php?id=" . $event_id);
                exit();
            }

            // Insert guest registration directly without creating user account
            $stmt = $db->prepare("INSERT INTO event_registrations (event_id, name, email, phone, sap_id, department, semester, registered_at) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$event_id, $name, $email, $phone, $sap_id, $department, $semester]);
        }

        $_SESSION['success'] = "Successfully registered for " . $event['title'] . "!";
        header("Location: event-details.php?id=" . $event_id);
        exit();

    } catch(PDOException $e) {
        error_log("Registration error: " . $e->getMessage());
        $_SESSION['error'] = "Failed to register. Please try again.";
    }
}

$page_title = "Register for " . $event['title'];
require_once '../includes/header.php';
?>

<!-- Rest of the HTML form remains the same as your original file -->
<!-- Only the PHP logic for guest registration has been changed -->

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
                                <i class="fas fa-user-plus me-2"></i>Event Registration
                            </h2>
                            <p class="card-subtitle mb-0 opacity-75">
                                Register for: <strong><?php echo $event['title']; ?></strong>
                            </p>
                        </div>
                        <div class="col-12 col-md-4 text-md-end mt-3 mt-md-0">
                            <span class="badge bg-white text-accent fs-6 p-3">
                                <i class="fas fa-users me-2"></i>
                                <?php echo $registered_count; ?>
                                <?php if($event['max_participants']): ?>
                                    / <?php echo $event['max_participants']; ?> registered
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4 p-md-5">
                    <!-- Event Summary Card -->
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

                    <!-- Registration Form -->
                    <form method="POST" action="" class="needs-validation" novalidate>
                        <?php if($is_logged_in): ?>
                            <!-- Logged-in Member Registration -->
                            <div class="alert alert-success">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-check-circle fa-2x me-3 text-success"></i>
                                    <div>
                                        <h6 class="mb-1">Welcome, <?php echo $_SESSION['user_name']; ?>!</h6>
                                        <p class="mb-0">You are registering as a society member. Your profile information will be used for registration.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center py-4">
                                <button type="submit" class="btn btn-accent btn-lg px-5 py-3">
                                    <i class="fas fa-check-circle me-2"></i>
                                    Confirm Registration
                                </button>
                                <p class="text-muted mt-2 small">
                                    By clicking confirm, you agree to participate in this event.
                                </p>
                            </div>

                        <?php else: ?>
                            <!-- Guest Registration Form -->
                            <div class="alert alert-info">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-info-circle fa-2x me-3 text-info"></i>
                                    <div>
                                        <h6 class="mb-1">Guest Registration</h6>
                                        <p class="mb-0">Please provide your details to register for this event. University email is required. Your information will only be used for this event.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-4">
                                <!-- Personal Information -->
                                <div class="col-12">
                                    <h5 class="border-bottom pb-2 mb-4 text-accent">
                                        <i class="fas fa-user me-2"></i>Personal Information
                                    </h5>
                                </div>

                                <div class="col-12 col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo $_POST['name'] ?? ''; ?>" 
                                               placeholder="Enter your full name" required>
                                        <label for="name">Full Name <span class="text-danger">*</span></label>
                                        <div class="invalid-feedback">
                                            Please provide your full name.
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <div class="form-floating">
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo $_POST['email'] ?? ''; ?>" 
                                               placeholder="name@uol.edu.pk" pattern=".*@.*uol\.edu\.pk$" required>
                                        <label for="email">University Email <span class="text-danger">*</span></label>
                                        <div class="invalid-feedback">
                                            Please provide a valid University of Lahore email address (@uol.edu.pk).
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <div class="form-floating">
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo $_POST['phone'] ?? ''; ?>" 
                                               placeholder="+92 300 1234567" required>
                                        <label for="phone">WhatsApp Number <span class="text-danger">*</span></label>
                                        <div class="invalid-feedback">
                                            Please provide your WhatsApp number.
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="sap_id" name="sap_id" 
                                               value="<?php echo $_POST['sap_id'] ?? ''; ?>" 
                                               placeholder="e.g., 70000000" required>
                                        <label for="sap_id">SAP ID <span class="text-danger">*</span></label>
                                        <div class="invalid-feedback">
                                            Please provide your SAP ID.
                                        </div>
                                    </div>
                                </div>

                                <!-- Academic Information -->
                                <div class="col-12 mt-4">
                                    <h5 class="border-bottom pb-2 mb-4 text-accent">
                                        <i class="fas fa-graduation-cap me-2"></i>Academic Information
                                    </h5>
                                </div>

                                <div class="col-12 col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="department" name="department" 
                                               value="<?php echo $_POST['department'] ?? ''; ?>" 
                                               placeholder="e.g., Computer Science" required>
                                        <label for="department">Department <span class="text-danger">*</span></label>
                                        <div class="invalid-feedback">
                                            Please provide your department.
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="semester" name="semester" 
                                               value="<?php echo $_POST['semester'] ?? ''; ?>" 
                                               placeholder="e.g., Semester 3" required>
                                        <label for="semester">Current Semester <span class="text-danger">*</span></label>
                                        <div class="invalid-feedback">
                                            Please provide your current semester.
                                        </div>
                                    </div>
                                </div>

                                <!-- Terms and Submit -->
                                <div class="col-12 mt-4">
                                    <div class="form-check mb-4">
                                        <input class="form-check-input" type="checkbox" id="terms" required>
                                        <label class="form-check-label" for="terms">
                                            I agree to the <a href="#" class="text-accent">terms and conditions</a> and 
                                            confirm that all provided information is accurate.
                                        </label>
                                        <div class="invalid-feedback">
                                            You must agree to the terms and conditions.
                                        </div>
                                    </div>

                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="event-details.php?id=<?php echo $event_id; ?>" class="btn btn-outline-secondary btn-lg px-4 me-md-2">
                                            <i class="fas fa-times me-2"></i>Cancel
                                        </a>
                                        <button type="submit" class="btn btn-accent btn-lg px-5">
                                            <i class="fas fa-paper-plane me-2"></i>Submit Registration
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </form>

                    <!-- Additional Information -->
                    <?php if(!$is_logged_in): ?>
                        <div class="mt-5 pt-4 border-top">
                            <div class="row">
                                <div class="col-12 col-md-6">
                                    <div class="d-flex align-items-start mb-3">
                                        <i class="fas fa-shield-alt text-accent mt-1 me-3"></i>
                                        <div>
                                            <h6 class="mb-1">Data Privacy</h6>
                                            <p class="small text-muted mb-0">
                                                Your information is secure and will only be used for event purposes.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="d-flex align-items-start mb-3">
                                        <i class="fas fa-question-circle text-accent mt-1 me-3"></i>
                                        <div>
                                            <h6 class="mb-1">Need Help?</h6>
                                            <p class="small text-muted mb-0">
                                                Contact us at <a href="mailto:mes@uol.edu.pk" class="text-accent">mes@uol.edu.pk</a>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Login Prompt for Guests -->
            <?php if(!$is_logged_in): ?>
                <div class="card border-accent mt-4">
                    <div class="card-body text-center py-3">
                        <p class="mb-0">
                            Already a society member? 
                            <a href="login.php?redirect=event-register.php?id=<?php echo $event_id; ?>" class="text-accent fw-bold">
                                Login here for faster registration
                            </a>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

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

    // Real-time email validation for university email
    const emailInput = document.getElementById('email');
    if (emailInput) {
        emailInput.addEventListener('input', function() {
            const email = this.value;
            const uolPattern = /@.*uol\.edu\.pk$/;
            
            if (email && !uolPattern.test(email)) {
                this.setCustomValidity('Please use a University of Lahore email address (@uol.edu.pk)');
            } else {
                this.setCustomValidity('');
            }
        });
    }

    // Phone number formatting
    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.startsWith('92')) {
                value = value.substring(2);
            }
            if (value.length > 0) {
                value = '+92 ' + value;
            }
            e.target.value = value;
        });
    }

    // SAP ID validation (numeric only)
    const sapInput = document.getElementById('sap_id');
    if (sapInput) {
        sapInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '');
        });
    }
});
</script>

<style>
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