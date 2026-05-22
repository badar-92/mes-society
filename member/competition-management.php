<?php
// member/competition-management.php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['competition_head', 'department_head', 'super_admin']);

$page_title = "Competition Management";
$hidePublicNavigation = true;

$user = $session->getCurrentUser();
$db = Database::getInstance()->getConnection();
$functions = new Functions();

// Handle competition creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_competition'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $competition_type = $_POST['competition_type'] ?? 'individual';
    $category = trim($_POST['category'] ?? '');
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $venue = trim($_POST['venue'] ?? '');
    $max_participants = !empty($_POST['max_participants']) ? (int)$_POST['max_participants'] : null;
    $registration_deadline = !empty($_POST['registration_deadline']) ? $_POST['registration_deadline'] : null;
    $prize = trim($_POST['prize'] ?? '');
    $rules = trim($_POST['rules'] ?? '');
    $eligibility = trim($_POST['eligibility'] ?? '');
    
    // Validate required fields
    $errors = [];
    
    if (empty($title)) {
        $errors[] = "Competition title is required";
    }
    
    if (empty($description)) {
        $errors[] = "Competition description is required";
    }
    
    if (empty($start_date)) {
        $errors[] = "Start date is required";
    } else {
        // Validate start date is in the future
        $startDateTime = new DateTime($start_date);
        $now = new DateTime();
        if ($startDateTime <= $now) {
            $errors[] = "Start date must be in the future";
        }
    }
    
    if (empty($end_date)) {
        $errors[] = "End date is required";
    } else {
        // Validate end date is after start date
        if (!empty($start_date)) {
            $startDateTime = new DateTime($start_date);
            $endDateTime = new DateTime($end_date);
            if ($endDateTime <= $startDateTime) {
                $errors[] = "End date must be after start date";
            }
        }
    }
    
    if (empty($venue)) {
        $errors[] = "Venue is required";
    }
    
    if (empty($registration_deadline)) {
        $errors[] = "Registration deadline is required";
    } else {
        // Validate registration deadline is before start date and in the future
        if (!empty($start_date)) {
            $regDateTime = new DateTime($registration_deadline);
            $startDateTime = new DateTime($start_date);
            $now = new DateTime();
            
            if ($regDateTime >= $startDateTime) {
                $errors[] = "Registration deadline must be before the competition start date";
            }
            
            if ($regDateTime <= $now) {
                $errors[] = "Registration deadline must be in the future";
            }
        }
    }
    
    // Max participants is optional - no validation required
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("INSERT INTO competitions 
                (title, description, competition_type, category, start_date, end_date, venue, 
                 max_participants, registration_deadline, prize, rules, eligibility, 
                 created_by, status, registration_open, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', 1, NOW())");
            
            $stmt->execute([
                $title, $description, $competition_type, $category, $start_date, $end_date, 
                $venue, $max_participants, $registration_deadline, $prize, $rules, $eligibility, 
                $user['id']
            ]);
            
            $competition_id = $db->lastInsertId();
            
            $_SESSION['success_message'] = "Competition draft created successfully!";
            header("Location: competition-management.php?competition_id=" . $competition_id);
            exit;
        } catch(PDOException $e) {
            $_SESSION['error_message'] = "Error creating competition: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "Please fix the following errors:<br>" . implode("<br>", $errors);
        // Store form data in session to repopulate form
        $_SESSION['form_data'] = $_POST;
    }
}

// Handle competition update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_competition'])) {
    $competition_id = $_POST['competition_id'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $competition_type = $_POST['competition_type'] ?? 'individual';
    $category = trim($_POST['category'] ?? '');
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $venue = trim($_POST['venue'] ?? '');
    $max_participants = !empty($_POST['max_participants']) ? (int)$_POST['max_participants'] : null;
    $registration_deadline = !empty($_POST['registration_deadline']) ? $_POST['registration_deadline'] : null;
    $prize = trim($_POST['prize'] ?? '');
    $rules = trim($_POST['rules'] ?? '');
    $eligibility = trim($_POST['eligibility'] ?? '');
    
    // Validate required fields for update
    $errors = [];
    
    if (empty($title)) {
        $errors[] = "Competition title is required";
    }
    
    if (empty($description)) {
        $errors[] = "Competition description is required";
    }
    
    if (empty($start_date)) {
        $errors[] = "Start date is required";
    }
    
    if (empty($end_date)) {
        $errors[] = "End date is required";
    } else {
        // Validate end date is after start date
        if (!empty($start_date)) {
            $startDateTime = new DateTime($start_date);
            $endDateTime = new DateTime($end_date);
            if ($endDateTime <= $startDateTime) {
                $errors[] = "End date must be after start date";
            }
        }
    }
    
    if (empty($venue)) {
        $errors[] = "Venue is required";
    }
    
    if (empty($registration_deadline)) {
        $errors[] = "Registration deadline is required";
    } else {
        // Validate registration deadline is before start date
        if (!empty($start_date)) {
            $regDateTime = new DateTime($registration_deadline);
            $startDateTime = new DateTime($start_date);
            if ($regDateTime >= $startDateTime) {
                $errors[] = "Registration deadline must be before the competition start date";
            }
        }
    }
    
    // Max participants is optional - no validation required
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("UPDATE competitions SET 
                title = ?, description = ?, competition_type = ?, category = ?, 
                start_date = ?, end_date = ?, venue = ?, max_participants = ?, 
                registration_deadline = ?, prize = ?, rules = ?, eligibility = ? 
                WHERE id = ? AND created_by = ?");
            
            $stmt->execute([
                $title, $description, $competition_type, $category, $start_date, $end_date, 
                $venue, $max_participants, $registration_deadline, $prize, $rules, $eligibility, 
                $competition_id, $user['id']
            ]);
            
            $_SESSION['success_message'] = "Competition updated successfully!";
            header("Location: competition-management.php?competition_id=" . $competition_id);
            exit;
        } catch(PDOException $e) {
            $_SESSION['error_message'] = "Error updating competition: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "Please fix the following errors:<br>" . implode("<br>", $errors);
    }
}

// Handle competition publication
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish_competition'])) {
    $competition_id = $_POST['competition_id'] ?? '';
    
    // Validate that all required fields are filled before publishing
    try {
        $stmt = $db->prepare("SELECT * FROM competitions WHERE id = ? AND created_by = ?");
        $stmt->execute([$competition_id, $user['id']]);
        $competition = $stmt->fetch();
        
        if ($competition) {
            $publish_errors = [];
            
            if (empty($competition['title']) || empty($competition['description']) || 
                empty($competition['start_date']) || empty($competition['end_date']) || 
                empty($competition['venue']) || empty($competition['registration_deadline'])) {
                $publish_errors[] = "All required fields must be filled before publishing";
            }
            
            // Check if registration deadline is in the future
            $regDateTime = new DateTime($competition['registration_deadline']);
            $now = new DateTime();
            if ($regDateTime <= $now) {
                $publish_errors[] = "Registration deadline must be in the future";
            }
            
            // Check if start date is in the future
            $startDateTime = new DateTime($competition['start_date']);
            if ($startDateTime <= $now) {
                $publish_errors[] = "Start date must be in the future";
            }
            
            if (empty($publish_errors)) {
                $stmt = $db->prepare("UPDATE competitions SET status = 'published' WHERE id = ? AND created_by = ?");
                $stmt->execute([$competition_id, $user['id']]);
                
                $_SESSION['success_message'] = "Competition published successfully!";
                header("Location: competition-management.php");
                exit;
            } else {
                $_SESSION['error_message'] = "Cannot publish competition:<br>" . implode("<br>", $publish_errors);
                header("Location: competition-management.php?competition_id=" . $competition_id);
                exit;
            }
        }
    } catch(PDOException $e) {
        $_SESSION['error_message'] = "Error publishing competition: " . $e->getMessage();
    }
}

// Get competitions created by this user
$competitions = [];
try {
    $stmt = $db->prepare("SELECT * FROM competitions WHERE created_by = ? ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);
    $competitions = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Competitions query error: " . $e->getMessage());
}

// Get specific competition for editing
$current_competition = null;
if (isset($_GET['competition_id'])) {
    try {
        $stmt = $db->prepare("SELECT * FROM competitions WHERE id = ? AND created_by = ?");
        $stmt->execute([$_GET['competition_id'], $user['id']]);
        $current_competition = $stmt->fetch();
    } catch(PDOException $e) {
        error_log("Competition query error: " . $e->getMessage());
    }
}

// Get registration counts
$registration_counts = [];
try {
    foreach ($competitions as $comp) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM competition_registrations WHERE competition_id = ?");
        $stmt->execute([$comp['id']]);
        $registration_counts[$comp['id']] = $stmt->fetchColumn();
    }
} catch(PDOException $e) {
    error_log("Registration counts error: " . $e->getMessage());
}

// Get form data from session if available (for repopulating after errors)
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

// Include header after all processing and redirects
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Desktop Sidebar (visible on larger screens) -->
        <div class="col-md-3 d-none d-md-block">
            <div class="desktop-sidebar">
                <?php include 'sidebar.php'; ?>
            </div>
        </div>

        <!-- Mobile Offcanvas Sidebar (visible on small screens) -->
        <div class="offcanvas offcanvas-start d-md-none" tabindex="-1" id="adminMobileSidebar">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title">Member Menu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body">
                <?php include 'sidebar.php'; ?>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <!-- Mobile Header Bar -->
            <div class="d-md-none d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                <div>
                    <h4 class="mb-0">Competition Management</h4>
                    <small class="text-muted">Create and manage competitions</small>
                </div>
                <button class="btn btn-accent" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileSidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <div class="d-none d-md-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1>Competition Management</h1>
                    <p class="text-muted mb-0">Create and manage society competitions</p>
                </div>
                <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#createCompetitionModal">
                    <i class="fas fa-plus me-2"></i>Create Competition
                </button>
            </div>

            <!-- Success/Error Messages -->
            <?php if(isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Competitions List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-trophy me-2"></i>My Competitions
                    </h5>
                </div>
                <div class="card-body">
                    <?php if(empty($competitions)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-trophy fa-3x text-muted mb-3"></i>
                            <h5>No Competitions Created</h5>
                            <p class="text-muted">Create your first competition to get started.</p>
                            <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#createCompetitionModal">
                                <i class="fas fa-plus me-2"></i>Create First Competition
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Type</th>
                                        <th>Dates</th>
                                        <th>Registrations</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($competitions as $competition): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($competition['title']); ?></strong>
                                                <?php if($competition['category']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($competition['category']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo ucfirst($competition['competition_type']); ?></span>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php echo date('M j, Y g:i A', strtotime($competition['start_date'])); ?>
                                                    <?php if($competition['end_date']): ?>
                                                        <br>to <?php echo date('M j, Y g:i A', strtotime($competition['end_date'])); ?>
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $registration_counts[$competition['id']] ?? 0; ?> registered</span>
                                                <?php if($competition['max_participants']): ?>
                                                    <br><small class="text-muted">Max: <?php echo $competition['max_participants']; ?></small>
                                                <?php else: ?>
                                                    <br><small class="text-muted">Unlimited</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    switch($competition['status']) {
                                                        case 'published': echo 'success'; break;
                                                        case 'draft': echo 'warning'; break;
                                                        case 'cancelled': echo 'danger'; break;
                                                        default: echo 'secondary';
                                                    }
                                                ?>">
                                                    <?php echo ucfirst($competition['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="competition-management.php?competition_id=<?php echo $competition['id']; ?>" 
                                                       class="btn btn-outline-primary" 
                                                       data-bs-toggle="tooltip" 
                                                       title="Edit Competition">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if($competition['status'] === 'draft'): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="competition_id" value="<?php echo $competition['id']; ?>">
                                                            <button type="submit" 
                                                                    name="publish_competition" 
                                                                    class="btn btn-outline-success"
                                                                    data-bs-toggle="tooltip"
                                                                    title="Publish Competition"
                                                                    onclick="return confirm('Publish this competition? It will be visible to members.')">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <a href="../public/competition-details.php?id=<?php echo $competition['id']; ?>" 
                                                       class="btn btn-outline-info"
                                                       data-bs-toggle="tooltip"
                                                       title="View Public Page"
                                                       target="_blank">
                                                        <i class="fas fa-external-link-alt"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Competition Form (for editing) -->
            <?php if($current_competition): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-edit me-2"></i>Edit Competition: <?php echo htmlspecialchars($current_competition['title']); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="competition_id" value="<?php echo $current_competition['id']; ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Competition Title <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="title" name="title" 
                                               value="<?php echo htmlspecialchars($current_competition['title']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="competition_type" class="form-label">Competition Type <span class="text-danger">*</span></label>
                                        <select class="form-select" id="competition_type" name="competition_type" required>
                                            <option value="individual" <?php echo $current_competition['competition_type'] === 'individual' ? 'selected' : ''; ?>>Individual</option>
                                            <option value="team" <?php echo $current_competition['competition_type'] === 'team' ? 'selected' : ''; ?>>Team</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Competition Description <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($current_competition['description']); ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="category" class="form-label">Category</label>
                                        <input type="text" class="form-control" id="category" name="category" 
                                               value="<?php echo htmlspecialchars($current_competition['category']); ?>" placeholder="e.g., Sports, Academic, Technical">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="venue" class="form-label">Venue <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="venue" name="venue" 
                                               value="<?php echo htmlspecialchars($current_competition['venue']); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="start_date" class="form-label">Start Date & Time <span class="text-danger">*</span></label>
                                        <input type="datetime-local" class="form-control" id="start_date" name="start_date" 
                                               value="<?php echo date('Y-m-d\TH:i', strtotime($current_competition['start_date'])); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="end_date" class="form-label">End Date & Time <span class="text-danger">*</span></label>
                                        <input type="datetime-local" class="form-control" id="end_date" name="end_date" 
                                               value="<?php echo $current_competition['end_date'] ? date('Y-m-d\TH:i', strtotime($current_competition['end_date'])) : ''; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="registration_deadline" class="form-label">Registration Deadline <span class="text-danger">*</span></label>
                                        <input type="datetime-local" class="form-control" id="registration_deadline" name="registration_deadline" 
                                               value="<?php echo $current_competition['registration_deadline'] ? date('Y-m-d\TH:i', strtotime($current_competition['registration_deadline'])) : ''; ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="max_participants" class="form-label">Maximum Participants</label>
                                        <input type="number" class="form-control" id="max_participants" name="max_participants" 
                                               value="<?php echo $current_competition['max_participants']; ?>" min="1">
                                        <div class="form-text">Leave empty for unlimited participants</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="prize" class="form-label">Prize Information</label>
                                        <input type="text" class="form-control" id="prize" name="prize" 
                                               value="<?php echo htmlspecialchars($current_competition['prize']); ?>" placeholder="e.g., Cash prizes, Trophies, Certificates">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="rules" class="form-label">Competition Rules</label>
                                <textarea class="form-control" id="rules" name="rules" rows="3"><?php echo htmlspecialchars($current_competition['rules']); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="eligibility" class="form-label">Eligibility Criteria</label>
                                <textarea class="form-control" id="eligibility" name="eligibility" rows="2"><?php echo htmlspecialchars($current_competition['eligibility']); ?></textarea>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" name="update_competition" class="btn btn-accent">Update Competition</button>
                                <?php if($current_competition['status'] === 'draft'): ?>
                                    <button type="submit" name="publish_competition" class="btn btn-success" 
                                            onclick="return confirm('Publish this competition? It will be visible to members.')">
                                        <i class="fas fa-eye me-2"></i>Publish Competition
                                    </button>
                                <?php endif; ?>
                                <a href="competition-management.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Create Competition Modal -->
<div class="modal fade" id="createCompetitionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Competition</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" id="createCompetitionForm">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        All fields marked with <span class="text-danger">*</span> are required.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="new_title" class="form-label">Competition Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="new_title" name="title" 
                                       value="<?php echo htmlspecialchars($form_data['title'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="new_competition_type" class="form-label">Competition Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="new_competition_type" name="competition_type" required>
                                    <option value="">Select Type</option>
                                    <option value="individual" <?php echo ($form_data['competition_type'] ?? '') === 'individual' ? 'selected' : ''; ?>>Individual</option>
                                    <option value="team" <?php echo ($form_data['competition_type'] ?? '') === 'team' ? 'selected' : ''; ?>>Team</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="new_description" class="form-label">Competition Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="new_description" name="description" rows="3" required><?php echo htmlspecialchars($form_data['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="new_category" class="form-label">Category</label>
                                <input type="text" class="form-control" id="new_category" name="category" 
                                       value="<?php echo htmlspecialchars($form_data['category'] ?? ''); ?>" placeholder="e.g., Sports, Academic, Technical">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="new_venue" class="form-label">Venue <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="new_venue" name="venue" 
                                       value="<?php echo htmlspecialchars($form_data['venue'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="new_start_date" class="form-label">Start Date & Time <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" id="new_start_date" name="start_date" 
                                       value="<?php echo htmlspecialchars($form_data['start_date'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="new_end_date" class="form-label">End Date & Time <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" id="new_end_date" name="end_date" 
                                       value="<?php echo htmlspecialchars($form_data['end_date'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="new_registration_deadline" class="form-label">Registration Deadline <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" id="new_registration_deadline" name="registration_deadline" 
                                       value="<?php echo htmlspecialchars($form_data['registration_deadline'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="new_max_participants" class="form-label">Maximum Participants</label>
                                <input type="number" class="form-control" id="new_max_participants" name="max_participants" 
                                       value="<?php echo htmlspecialchars($form_data['max_participants'] ?? ''); ?>" min="1">
                                <div class="form-text">Leave empty for unlimited participants</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="new_prize" class="form-label">Prize Information</label>
                                <input type="text" class="form-control" id="new_prize" name="prize" 
                                       value="<?php echo htmlspecialchars($form_data['prize'] ?? ''); ?>" placeholder="e.g., Cash prizes, Trophies">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="new_rules" class="form-label">Competition Rules</label>
                        <textarea class="form-control" id="new_rules" name="rules" rows="2" placeholder="Detailed rules and guidelines"><?php echo htmlspecialchars($form_data['rules'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="new_eligibility" class="form-label">Eligibility Criteria</label>
                        <textarea class="form-control" id="new_eligibility" name="eligibility" rows="2" placeholder="Who can participate?"><?php echo htmlspecialchars($form_data['eligibility'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_competition" class="btn btn-accent">Create Competition Draft</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Set minimum datetime for date inputs and add validation
    function initializeDateValidation() {
        const now = new Date();
        const localDateTime = now.toISOString().slice(0, 16);
        
        // For create modal
        const startDateInput = document.getElementById('new_start_date');
        const endDateInput = document.getElementById('new_end_date');
        const regDeadlineInput = document.getElementById('new_registration_deadline');
        
        if (startDateInput) startDateInput.min = localDateTime;
        if (endDateInput) endDateInput.min = localDateTime;
        if (regDeadlineInput) regDeadlineInput.min = localDateTime;

        // For edit form
        const editStartDate = document.getElementById('start_date');
        const editEndDate = document.getElementById('end_date');
        const editRegDeadline = document.getElementById('registration_deadline');
        
        if (editStartDate) editStartDate.min = localDateTime;
        if (editEndDate) editEndDate.min = localDateTime;
        if (editRegDeadline) editRegDeadline.min = localDateTime;

        // Update validation when dates change
        if (startDateInput) {
            startDateInput.addEventListener('change', function() {
                if (endDateInput) endDateInput.min = this.value;
                if (regDeadlineInput) {
                    regDeadlineInput.max = this.value;
                    // Also validate that registration deadline is not after start date
                    if (regDeadlineInput.value && regDeadlineInput.value >= this.value) {
                        regDeadlineInput.setCustomValidity('Registration deadline must be before start date');
                    } else {
                        regDeadlineInput.setCustomValidity('');
                    }
                }
            });
        }

        if (regDeadlineInput) {
            regDeadlineInput.addEventListener('change', function() {
                if (startDateInput && this.value >= startDateInput.value) {
                    this.setCustomValidity('Registration deadline must be before start date');
                } else {
                    this.setCustomValidity('');
                }
            });
        }

        // Similar for edit form
        if (editStartDate) {
            editStartDate.addEventListener('change', function() {
                if (editEndDate) editEndDate.min = this.value;
                if (editRegDeadline) {
                    editRegDeadline.max = this.value;
                    if (editRegDeadline.value && editRegDeadline.value >= this.value) {
                        editRegDeadline.setCustomValidity('Registration deadline must be before start date');
                    } else {
                        editRegDeadline.setCustomValidity('');
                    }
                }
            });
        }

        if (editRegDeadline) {
            editRegDeadline.addEventListener('change', function() {
                if (editStartDate && this.value >= editStartDate.value) {
                    this.setCustomValidity('Registration deadline must be before start date');
                } else {
                    this.setCustomValidity('');
                }
            });
        }
    }

    initializeDateValidation();

    // Form validation for create competition
    const createForm = document.getElementById('createCompetitionForm');
    if (createForm) {
        createForm.addEventListener('submit', function(e) {
            const startDate = document.getElementById('new_start_date').value;
            const endDate = document.getElementById('new_end_date').value;
            const regDeadline = document.getElementById('new_registration_deadline').value;
            const maxParticipants = document.getElementById('new_max_participants').value;

            let isValid = true;
            let errorMessage = '';

            // Check dates
            if (startDate && endDate && endDate <= startDate) {
                isValid = false;
                errorMessage += 'End date must be after start date.\n';
            }

            if (startDate && regDeadline && regDeadline >= startDate) {
                isValid = false;
                errorMessage += 'Registration deadline must be before start date.\n';
            }

            if (maxParticipants && maxParticipants < 1) {
                isValid = false;
                errorMessage += 'Maximum participants must be at least 1 if specified.\n';
            }

            if (!isValid) {
                e.preventDefault();
                alert('Please fix the following errors:\n\n' + errorMessage);
            }
        });
    }
});
</script>

<style>
.table th {
    border-top: none;
    font-weight: 600;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
}

.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.form-label .text-danger {
    font-weight: bold;
}

.alert-info {
    background-color: #d1ecf1;
    border-color: #bee5eb;
    color: #0c5460;
}

/* Mobile responsive adjustments */
@media (max-width: 767.98px) {
    .container-fluid {
        padding-left: 10px;
        padding-right: 10px;
    }
}

/* Offcanvas sidebar styling */
.offcanvas-header {
    background: var(--primary-color);
    color: white;
}

.offcanvas-body {
    padding: 0;
}

.offcanvas-body .nav {
    padding: 1rem;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>