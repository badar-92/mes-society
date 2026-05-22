<?php
require_once '../includes/session.php';
require_once '../includes/database.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['super_admin', 'department_head', 'competition_head']);

$db = Database::getInstance()->getConnection();

$reg_id = $_GET['id'] ?? 0;
$competition_id = $_GET['competition_id'] ?? 0;

if (!$reg_id || !$competition_id) {
    header("Location: competitions.php");
    exit();
}

// Get competition details
$competition = null;
try {
    $stmt = $db->prepare("SELECT * FROM competitions WHERE id = ?");
    $stmt->execute([$competition_id]);
    $competition = $stmt->fetch();
} catch(PDOException $e) {
    error_log("Competition edit error: " . $e->getMessage());
}

if (!$competition) {
    header("Location: competitions.php");
    exit();
}

// Get registration data
$registration = null;
try {
    $stmt = $db->prepare("SELECT * FROM competition_registrations WHERE id = ? AND competition_id = ?");
    $stmt->execute([$reg_id, $competition_id]);
    $registration = $stmt->fetch();
} catch(PDOException $e) {
    error_log("Registration fetch error: " . $e->getMessage());
}

if (!$registration) {
    $_SESSION['error'] = "Registration not found.";
    header("Location: competition-participants.php?id=" . $competition_id);
    exit();
}

// Decode team members
$team_members = [];
if (!empty($registration['team_members'])) {
    $decoded = json_decode($registration['team_members'], true);
    if (is_array($decoded)) {
        $team_members = $decoded;
    }
}

// Determine if from UOL
$is_from_uol = ($registration['university'] === 'University of Lahore') ? 'yes' : 'no';
$other_university = ($is_from_uol === 'no') ? $registration['university'] : '';

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
    $status = $_POST['status'] ?? 'pending';
    
    // University field
    $is_from_uol = $_POST['is_from_uol'] ?? '';
    $other_university = $_POST['other_university'] ?? '';
    if ($is_from_uol === 'yes') {
        $university = '	University of Lahore';
    } elseif ($is_from_uol === 'no') {
        if (empty($other_university)) {
            $errors[] = "Please enter the university name.";
        } else {
            $university = $other_university;
        }
    } else {
        $errors[] = "Please specify if the participant is from University of Lahore.";
    }

    $team_members_updated = [];
    if ($competition['competition_type'] === 'team') {
        $member_names = $_POST['member_name'] ?? [];
        $member_emails = $_POST['member_email'] ?? [];
        $member_phones = $_POST['member_phone'] ?? [];
        $member_sapids = $_POST['member_sapid'] ?? [];
        $member_departments = $_POST['member_department'] ?? [];
        
        for ($i = 0; $i < count($member_names); $i++) {
            if (!empty($member_names[$i])) {
                $team_members_updated[] = [
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
            $team_members_json = !empty($team_members_updated) ? json_encode($team_members_updated) : null;
            $sql = "UPDATE competition_registrations SET 
                        team_name = ?,
                        team_leader_name = ?,
                        team_leader_email = ?,
                        team_leader_phone = ?,
                        team_leader_sapid = ?,
                        team_leader_department = ?,
                        university = ?,
                        team_members = ?,
                        status = ?
                    WHERE id = ? AND competition_id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $team_name,
                $team_leader_name,
                $team_leader_email,
                $team_leader_phone,
                $team_leader_sapid,
                $team_leader_department,
                $university,
                $team_members_json,
                $status,
                $reg_id,
                $competition_id
            ]);
            
            $_SESSION['success'] = "Registration updated successfully.";
            header("Location: competition-participants.php?id=" . $competition_id);
            exit();
        } catch (PDOException $e) {
            error_log("Update error: " . $e->getMessage());
            $errors[] = "Failed to update registration. Please try again.";
        }
    }
}

require_once '../includes/header.php';
$page_title = "Edit Participant - " . $registration['team_leader_name'];
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
                <h1>Edit Participant</h1>
                <div>
                    <a href="competition-participants.php?id=<?php echo $competition_id; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Participants
                    </a>
                </div>
            </div>

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

            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-user-edit me-2"></i>Edit Registration #<?php echo $reg_id; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <?php if($competition['competition_type'] === 'team'): ?>
                            <div class="mb-3">
                                <label for="team_name" class="form-label">Team Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="team_name" name="team_name" required 
                                       value="<?php echo htmlspecialchars($registration['team_name'] ?? ''); ?>"
                                       placeholder="Enter team name">
                            </div>
                        <?php endif; ?>

                        <h5 class="mb-3 border-bottom pb-2">Team Leader Details</h5>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="team_leader_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="team_leader_name" name="team_leader_name" required 
                                       value="<?php echo htmlspecialchars($registration['team_leader_name']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="team_leader_email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="team_leader_email" name="team_leader_email" required 
                                       value="<?php echo htmlspecialchars($registration['team_leader_email']); ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="team_leader_phone" class="form-label">WhatsApp Number</label>
                                <input type="text" class="form-control" id="team_leader_phone" name="team_leader_phone" 
                                       value="<?php echo htmlspecialchars($registration['team_leader_phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="team_leader_sapid" class="form-label">SAP ID</label>
                                <input type="text" class="form-control" id="team_leader_sapid" name="team_leader_sapid" 
                                       value="<?php echo htmlspecialchars($registration['team_leader_sapid'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="team_leader_department" class="form-label">Department</label>
                            <input type="text" class="form-control" id="team_leader_department" name="team_leader_department" 
                                   value="<?php echo htmlspecialchars($registration['team_leader_department'] ?? ''); ?>">
                        </div>

                        <!-- University Question -->
                        <div class="mb-3">
                            <label class="form-label d-block">Are you from University of Lahore? <span class="text-danger">*</span></label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="is_from_uol" id="uol_yes" value="yes" <?php echo ($is_from_uol === 'yes') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="uol_yes">Yes</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="is_from_uol" id="uol_no" value="no" <?php echo ($is_from_uol === 'no') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="uol_no">No</label>
                            </div>
                        </div>

                        <div class="mb-3" id="other_university_div" style="display: <?php echo ($is_from_uol === 'no') ? 'block' : 'none'; ?>;">
                            <label for="other_university" class="form-label">University Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="other_university" name="other_university" 
                                   value="<?php echo htmlspecialchars($other_university); ?>"
                                   placeholder="Enter university name">
                        </div>

                        <?php if($competition['competition_type'] === 'team'): ?>
                            <h5 class="mb-3 border-bottom pb-2">Team Members</h5>
                            <div id="team-members-container">
                                <?php foreach($team_members as $index => $member): ?>
                                    <div class="team-member border p-3 mb-3 rounded">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0">Team Member <?php echo $index + 1; ?></h6>
                                            <button type="button" class="btn btn-outline-danger btn-sm remove-member">
                                                <i class="fas fa-times"></i> Remove
                                            </button>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Full Name</label>
                                                <input type="text" class="form-control" name="member_name[]" value="<?php echo htmlspecialchars($member['name'] ?? ''); ?>" placeholder="Enter member's full name">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Email Address</label>
                                                <input type="email" class="form-control" name="member_email[]" value="<?php echo htmlspecialchars($member['email'] ?? ''); ?>" placeholder="Enter member's email">
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Phone Number</label>
                                                <input type="text" class="form-control" name="member_phone[]" value="<?php echo htmlspecialchars($member['phone'] ?? ''); ?>" placeholder="Enter member's phone">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">SAP ID</label>
                                                <input type="text" class="form-control" name="member_sapid[]" value="<?php echo htmlspecialchars($member['sapid'] ?? ''); ?>" placeholder="Enter member's SAP ID">
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Department</label>
                                            <input type="text" class="form-control" name="member_department[]" value="<?php echo htmlspecialchars($member['department'] ?? ''); ?>" placeholder="Enter member's department">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm mb-3" id="add-member">
                                <i class="fas fa-plus me-1"></i>Add Team Member
                            </button>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="pending" <?php echo ($registration['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo ($registration['status'] === 'approved') ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo ($registration['status'] === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                            <a href="competition-participants.php?id=<?php echo $competition_id; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show/hide other university field
    const radioYes = document.getElementById('uol_yes');
    const radioNo = document.getElementById('uol_no');
    const otherDiv = document.getElementById('other_university_div');
    const otherInput = document.getElementById('other_university');

    function toggleUniversityField() {
        if (radioNo.checked) {
            otherDiv.style.display = 'block';
            otherInput.setAttribute('required', 'required');
        } else {
            otherDiv.style.display = 'none';
            otherInput.removeAttribute('required');
        }
    }

    if (radioYes && radioNo) {
        radioYes.addEventListener('change', toggleUniversityField);
        radioNo.addEventListener('change', toggleUniversityField);
        toggleUniversityField();
    }

    <?php if($competition['competition_type'] === 'team'): ?>
    const container = document.getElementById('team-members-container');
    let memberCount = container.querySelectorAll('.team-member').length;

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

    document.getElementById('add-member')?.addEventListener('click', addMemberField);

    // Attach remove event to existing remove buttons
    document.querySelectorAll('.remove-member').forEach(btn => {
        btn.addEventListener('click', function() {
            const memberDiv = this.closest('.team-member');
            container.removeChild(memberDiv);
            updateMemberNumbers();
        });
    });
    <?php endif; ?>
});
</script>

<?php require_once '../includes/footer.php'; ?>