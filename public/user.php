<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Get user ID from query parameter
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($userId <= 0) {
    // Invalid ID, redirect to 404 or homepage
    header('HTTP/1.0 404 Not Found');
    die('User not found.');
}

$db = Database::getInstance()->getConnection();

// Fetch user details with posts
$stmt = $db->prepare("
    SELECT u.*, GROUP_CONCAT(up.post_name) as user_posts
    FROM users u
    LEFT JOIN user_posts up ON u.id = up.user_id
    WHERE u.id = ? AND u.status = 'active'
    GROUP BY u.id
");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // User not found or inactive
    header('HTTP/1.0 404 Not Found');
    die('User not found.');
}

// Process posts into array
$posts = $user['user_posts'] ? explode(',', $user['user_posts']) : [];

// Define post labels (from admin/view-users.php)
$availablePosts = [
    'member' => 'General Member',
    'media_head' => 'Media Head',
    'event_planner' => 'Event Planner',
    'competition_head' => 'Competition Head',
    'hiring_head' => 'Hiring Head',
    'president' => 'President',
    'IT_head' => 'IT Head',
    'department_head' => 'Department Head',
    'treassure_head' => 'Treassure Head'
];

// Page title
$page_title = htmlspecialchars($user['name']) . ' - Profile';

// Include header
require_once '../includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white text-center">
                    <h3 class="mb-0">Member Profile</h3>
                </div>
                <div class="card-body text-center">
                    <!-- Profile Picture -->
                    <div class="mb-4">
                        <?php if ($user['profile_picture']): ?>
                            <img src="<?php echo SITE_URL; ?>/uploads/profile-pictures/<?php echo htmlspecialchars($user['profile_picture']); ?>"
                                 alt="<?php echo htmlspecialchars($user['name']); ?>"
                                 class="rounded-circle img-fluid"
                                 style="width: 150px; height: 150px; object-fit: cover; border: 3px solid #ddd;">
                        <?php else: ?>
                            <div class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center"
                                 style="width: 150px; height: 150px;">
                                <i class="fas fa-user fa-4x text-white"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Name and Verification -->
                    <h2 class="mb-1"><?php echo htmlspecialchars($user['name']); ?></h2>
                    <span class="badge bg-success mb-3 p-2">
                        <i class="fas fa-check-circle me-1"></i> Verified Member of MES
                    </span>

                    <!-- Role Badge -->
                    <div class="mb-3">
                        <span class="badge bg-<?php 
                            switch($user['role']) {
                                case 'super_admin': echo 'danger'; break;
                                case 'department_head': echo 'warning'; break;
                                case 'hiring_head': echo 'info'; break;
                                case 'event_planner': echo 'info'; break;
                                case 'competition_head': echo 'info'; break;
                                case 'media_head': echo 'info'; break;
                                case 'president': echo 'primary'; break;
                                case 'IT_head': echo 'info'; break;
                                case 'treassure_head': echo 'info'; break;
                                default: echo 'secondary';
                            }
                        ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                        </span>
                    </div>

                    <!-- User Details Table -->
                    <div class="table-responsive">
                        <table class="table table-borderless text-start">
                            <tbody>
                                <?php if ($user['sap_id']): ?>
                                <tr>
                                    <th style="width: 30%;">SAP ID:</th>
                                    <td><?php echo htmlspecialchars($user['sap_id']); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($user['department']): ?>
                                <tr>
                                    <th>Department:</th>
                                    <td><?php echo htmlspecialchars($user['department']); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($user['semester']): ?>
                                <tr>
                                    <th>Semester:</th>
                                    <td><?php echo htmlspecialchars($user['semester']); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($user['email']): ?>
                                <tr>
                                    <th>Email:</th>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($user['phone']): ?>
                                <tr>
                                    <th>Phone:</th>
                                    <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <th>Member Since:</th>
                                    <td><?php echo date('F j, Y', strtotime($user['created_at'])); ?></td>
                                </tr>
                                <?php if (!empty($posts)): ?>
                                <tr>
                                    <th>Assigned Posts:</th>
                                    <td>
                                        <?php foreach ($posts as $post): ?>
                                            <span class="badge bg-info me-1 mb-1"><?php echo htmlspecialchars($availablePosts[$post] ?? $post); ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Back Button -->
                    <div class="mt-4">
                        <a href="team.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i> Back to Team
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>