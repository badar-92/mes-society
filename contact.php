<?php
// Start session at the very top - BEFORE any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Process form submission BEFORE including any other files
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process contact form
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Basic validation
    $errors = [];
    if (empty($name)) $errors[] = "Name is required";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
    if (empty($subject)) $errors[] = "Subject is required";
    if (empty($message)) $errors[] = "Message is required";
    
    if (empty($errors)) {
        try {
            // Get database connection
            require_once '../includes/database.php';
            $db = Database::getInstance()->getConnection();
            
            // Insert into database
            $stmt = $db->prepare("INSERT INTO contact_messages (name, email, subject, message, status, created_at) VALUES (?, ?, ?, ?, 'unread', NOW())");
            $result = $stmt->execute([$name, $email, $subject, $message]);
            
            if ($result) {
                // Store success message in session and redirect to clear form
                $_SESSION['contact_success'] = "Thank you for your message! We'll get back to you soon.";
                header("Location: contact.php");
                exit();
            } else {
                $_SESSION['contact_error'] = "Sorry, there was an error sending your message. Please try again.";
            }
        } catch (PDOException $e) {
            $_SESSION['contact_error'] = "Sorry, there was an error sending your message. Please try again.";
            error_log("Contact form error: " . $e->getMessage());
        }
    } else {
        $_SESSION['contact_error'] = implode("<br>", $errors);
    }
}

// Now include the page header and display the form
$page_title = "Contact Us";
require_once '../includes/header.php';

// Check for success/error messages from session
$success_message = '';
$error_message = '';

if (isset($_SESSION['contact_success'])) {
    $success_message = $_SESSION['contact_success'];
    unset($_SESSION['contact_success']); // Clear the message after displaying
}

if (isset($_SESSION['contact_error'])) {
    $error_message = $_SESSION['contact_error'];
    unset($_SESSION['contact_error']); // Clear the message after displaying
}
?>

<div class="container py-5">
    <h1 class="text-center mb-5">Contact Us</h1>

    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <?php if(!empty($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(!empty($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="post">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Your Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" required 
                                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Your Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" required
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject *</label>
                            <input type="text" class="form-control" id="subject" name="subject" required
                                   value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="message" class="form-label">Message *</label>
                            <textarea class="form-control" id="message" name="message" rows="5" required
                                      placeholder="Please describe your inquiry in detail..."><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                        </div>

                        <div class="text-center">
                            <button type="submit" class="btn btn-accent btn-lg px-5">
                                <i class="fas fa-paper-plane me-2"></i>Send Message
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row mt-5">
                <div class="col-md-4 text-center mb-4">
                    <div class="contact-method">
                        <i class="fas fa-map-marker-alt fa-2x text-accent mb-3"></i>
                        <h5>Address</h5>
                        <p class="text-muted">Department of Mechanical Engineering<br>University of Lahore<br>Lahore, Pakistan</p>
                    </div>
                </div>
                
                <div class="col-md-4 text-center mb-4">
                    <div class="contact-method">
                        <i class="fas fa-envelope fa-2x text-accent mb-3"></i>
                        <h5>Email</h5>
                        <p class="text-muted">mesuolofficial@gmail.com<br></p>
                    </div>
                </div>
                
                <div class="col-md-4 text-center mb-4">
                    <div class="contact-method">
                        <i class="fas fa-phone fa-2x text-accent mb-3"></i>
                        <h5>Phone</h5>
                        <p class="text-muted">+92 313 3150346<br>+92 324 0325852</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>