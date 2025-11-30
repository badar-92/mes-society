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
    error_log("Competition registration error: " . $e->getMessage());
}

if (!$competition) {
    header("Location: competitions.php");
    exit();
}

// Check if registration is open
$regDeadline = $competition['registration_deadline'];
$isRegOpen = $regDeadline && (strtotime($regDeadline) >= time()) && $competition['registration_open'];
if (!$isRegOpen) {
    $_SESSION['error'] = "Registration for this competition is closed.";
    header("Location: competition-details.php?id=" . $competition_id);
    exit();
}

// Handle form submission
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $team_name = $_POST['team_name'] ?? '';
    $team_leader_name = $_POST['team_leader_name'] ?? '';
    $team_leader_email = $_POST['team_leader_email'] ?? '';
    $team_leader_phone = $_POST['team_leader_phone'] ?? '';
    $team_leader_sapid = $_POST['team_leader_sapid'] ?? '';
    $team_leader_department = $_POST['team_leader_department'] ?? '';
    $team_members = [];

    if ($competition['competition_type'] === 'team') {
        // Collect team members
        $member_names = $_POST['member_name'] ?? [];
        $member_emails = $_POST['member_email'] ?? [];
        $member_phones = $_POST['member_phone'] ?? [];
        $member_sapids = $_POST['member_sapid'] ?? [];
        $member_departments = $_POST['member_department'] ?? [];

        for ($i = 0; $i < count($member_names); $i++) {
            if (!empty($member_names[$i])) {
                $team_members[] = [
                    'name' => $member_names[$i],
                    'email' => $member_emails[$i],
                    'phone' => $member_phones[$i],
                    'sapid' => $member_sapids[$i],
                    'department' => $member_departments[$i]
                ];
            }
        }
    }

    // Validation
    if (empty($team_leader_name)) {
        $errors[] = "Team leader name is required.";
    }
    if (empty($team_leader_email)) {
        $errors[] = "Team leader email is required.";
    }
    if (!filter_var($team_leader_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    if ($competition['competition_type'] === 'team' && empty($team_name)) {
        $errors[] = "Team name is required for team competitions.";
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Create competition_registrations table if it doesn't exist
            $checkTable = $db->query("SHOW TABLES LIKE 'competition_registrations'");
            if ($checkTable->rowCount() == 0) {
                $createTable = $db->exec("
                    CREATE TABLE `competition_registrations` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `competition_id` int(11) NOT NULL,
                        `user_id` int(11) DEFAULT NULL,
                        `team_name` varchar(255) DEFAULT NULL,
                        `team_leader_name` varchar(255) NOT NULL,
                        `team_leader_email` varchar(255) NOT NULL,
                        `team_leader_phone` varchar(20) DEFAULT NULL,
                        `team_leader_sapid` varchar(20) DEFAULT NULL,
                        `team_leader_department` varchar(100) DEFAULT NULL,
                        `team_members` text DEFAULT NULL,
                        `registration_date` timestamp DEFAULT CURRENT_TIMESTAMP,
                        `status` enum('pending','approved','rejected') DEFAULT 'pending',
                        PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ");
            }

            // Check if email already registered for this competition
            $checkEmail = $db->prepare("SELECT id FROM competition_registrations WHERE competition_id = ? AND team_leader_email = ?");
            $checkEmail->execute([$competition_id, $team_leader_email]);
            if ($checkEmail->fetch()) {
                $errors[] = "This email has already been used to register for this competition.";
            } else {
                $stmt = $db->prepare("
                    INSERT INTO competition_registrations 
                    (competition_id, team_name, team_leader_name, team_leader_email, team_leader_phone, team_leader_sapid, team_leader_department, team_members) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $team_members_json = !empty($team_members) ? json_encode($team_members) : null;
                $stmt->execute([
                    $competition_id,
                    $team_name,
                    $team_leader_name,
                    $team_leader_email,
                    $team_leader_phone,
                    $team_leader_sapid,
                    $team_leader_department,
                    $team_members_json
                ]);

                $db->commit();
                $success = true;
                $_SESSION['success'] = "Registration successful! We will review your application and get back to you.";
                
                // Clear form data
                unset($_POST);
            }

        } catch (PDOException $e) {
            $db->rollBack();
            error_log("Registration error: " . $e->getMessage());
            $errors[] = "Registration failed. Please try again.";
        }
    }
}

// Only include header and show HTML if we're not redirecting
require_once '../includes/header.php';
$page_title = "Register for " . $competition['title'];
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="competitions.php">Competitions</a></li>
                    <li class="breadcrumb-item"><a href="competition-details.php?id=<?php echo $competition_id; ?>"><?php echo htmlspecialchars($competition['title']); ?></a></li>
                    <li class="breadcrumb-item active">Register</li>
                </ol>
            </nav>

            <h1 class="mb-4">Register for <?php echo htmlspecialchars($competition['title']); ?></h1>

            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <div class="mt-3">
                        <a href="competition-details.php?id=<?php echo $competition_id; ?>" class="btn btn-success me-2">
                            <i class="fas fa-arrow-left me-1"></i>Back to Competition
                        </a>
                        <a href="competitions.php" class="btn btn-outline-secondary">
                            View All Competitions
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if(!empty($errors)): ?>
                <div class="alert alert-danger">
                    <h5>Please fix the following errors:</h5>
                    <ul class="mb-0">
                        <?php foreach($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if(!isset($_SESSION['success'])): ?>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-user-plus me-2"></i>Registration Form
                    </h5>
                </div>
                <div class="card-body">
                    <form method="post" id="registrationForm">
                        <?php if($competition['competition_type'] === 'team'): ?>
                            <div class="mb-3">
                                <label for="team_name" class="form-label">Team Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="team_name" name="team_name" required 
                                       value="<?php echo isset($_POST['team_name']) ? htmlspecialchars($_POST['team_name']) : ''; ?>"
                                       placeholder="Enter your team name">
                                <div class="form-text">Choose a unique name for your team</div>
                            </div>
                        <?php endif; ?>

                        <h5 class="mb-3 border-bottom pb-2">Team Leader Details</h5>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="team_leader_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="team_leader_name" name="team_leader_name" required 
                                       value="<?php echo isset($_POST['team_leader_name']) ? htmlspecialchars($_POST['team_leader_name']) : ''; ?>"
                                       placeholder="Enter your full name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="team_leader_email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="team_leader_email" name="team_leader_email" required 
                                       value="<?php echo isset($_POST['team_leader_email']) ? htmlspecialchars($_POST['team_leader_email']) : ''; ?>"
                                       placeholder="Enter your email address">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="team_leader_phone" class="form-label">WhatsApp Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="team_leader_phone" name="team_leader_phone" required 
                                       value="<?php echo isset($_POST['team_leader_phone']) ? htmlspecialchars($_POST['team_leader_phone']) : ''; ?>"
                                       placeholder="Enter your WhatsApp number">
                                <div class="form-text">We'll use this for important updates</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="team_leader_sapid" class="form-label">SAP ID</label>
                                <input type="text" class="form-control" id="team_leader_sapid" name="team_leader_sapid" 
                                       value="<?php echo isset($_POST['team_leader_sapid']) ? htmlspecialchars($_POST['team_leader_sapid']) : ''; ?>"
                                       placeholder="Enter your SAP ID (if available)">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="team_leader_department" class="form-label">Department <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="team_leader_department" name="team_leader_department" required 
                                   value="<?php echo isset($_POST['team_leader_department']) ? htmlspecialchars($_POST['team_leader_department']) : ''; ?>"
                                   placeholder="Enter your department">
                        </div>

                        <?php if($competition['competition_type'] === 'team'): ?>
                            <h5 class="mb-3 border-bottom pb-2">Team Members</h5>
                            <div id="team-members-container">
                                <!-- Team members will be added here dynamically -->
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm mb-3" id="add-member">
                                <i class="fas fa-plus me-1"></i>Add Team Member
                            </button>
                            <div class="form-text">Add your team members here. Team leader is already included above.</div>
                        <?php endif; ?>

                        <div class="alert alert-info mt-4">
                            <h6><i class="fas fa-info-circle me-2"></i>Important Notes:</h6>
                            <ul class="mb-0">
                                <li>All fields marked with <span class="text-danger">*</span> are required</li>
                                <li>Make sure your email and WhatsApp number are correct</li>
                                <li>You'll receive a confirmation email after registration</li>
                                <li>We'll contact you for further updates</li>
                            </ul>
                        </div>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-paper-plane me-2"></i>Submit Registration
                            </button>
                            <a href="competition-details.php?id=<?php echo $competition_id; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Competition Details
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if($competition['competition_type'] === 'team' && !isset($_SESSION['success'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('team-members-container');
    let memberCount = 0;

    function addMemberField() {
        const memberDiv = document.createElement('div');
        memberDiv.className = 'team-member border p-3 mb-3 rounded';
        memberDiv.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Team Member ${memberCount + 1}</h6>
                <button type="button" class="btn btn-outline-danger btn-sm remove-member">
                    <i class="fas fa-times"></i> Remove
                </button>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control" name="member_name[]" placeholder="Enter member's full name">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-control" name="member_email[]" placeholder="Enter member's email">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Phone Number</label>
                    <input type="text" class="form-control" name="member_phone[]" placeholder="Enter member's phone">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">SAP ID</label>
                    <input type="text" class="form-control" name="member_sapid[]" placeholder="Enter member's SAP ID">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Department</label>
                <input type="text" class="form-control" name="member_department[]" placeholder="Enter member's department">
            </div>
        `;
        container.appendChild(memberDiv);
        memberCount++;

        // Add event listener for remove button
        memberDiv.querySelector('.remove-member').addEventListener('click', function() {
            container.removeChild(memberDiv);
            updateMemberNumbers();
        });
    }

    function updateMemberNumbers() {
        const members = container.querySelectorAll('.team-member');
        members.forEach((member, index) => {
            const header = member.querySelector('h6');
            header.textContent = `Team Member ${index + 1}`;
        });
        memberCount = members.length;
    }

    document.getElementById('add-member').addEventListener('click', addMemberField);

    // Add one member field by default for team competitions
    addMemberField();
});

// Form validation
document.getElementById('registrationForm')?.addEventListener('submit', function(e) {
    const requiredFields = this.querySelectorAll('[required]');
    let valid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            valid = false;
            field.classList.add('is-invalid');
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    if (!valid) {
        e.preventDefault();
        alert('Please fill in all required fields.');
    }
});
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>