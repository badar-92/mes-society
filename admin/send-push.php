<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/push_helper.php';

$auth = new Auth();
if (!$auth->isLoggedIn() || (!$auth->hasRole('super_admin') && !$auth->hasRole('department_head'))) {
    die('Access denied');
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $body = $_POST['body'] ?? '';
    $url = $_POST['url'] ?? '/mes-society/public/';

    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT id FROM users WHERE push_enabled = 1");
    $user_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $successCount = 0;
    foreach ($user_ids as $uid) {
        if (sendPushNotification($uid, $title, $body, $url)) {
            $successCount++;
        }
    }

    // Save to internal notifications
    if (!empty($user_ids)) {
        $sql = "INSERT INTO notifications (user_id, title, message, type) VALUES ";
        $values = [];
        foreach ($user_ids as $uid) {
            $sql .= "(?, ?, ?, 'info'),";
            $values[] = $uid;
            $values[] = $title;
            $values[] = $body;
        }
        $sql = rtrim($sql, ',');
        $stmt = $db->prepare($sql);
        $stmt->execute($values);
    }

    $message = "Push notifications sent to $successCount subscriber(s).";
}

$page_title = 'Send Push Notification';
$hidePublicNavigation = true;
require_once '../includes/header.php';
?>

<!-- keep the same HTML form as before, just remove OneSignal references -->

<div class="container-fluid">
    <div class="row">
        <!-- Desktop Sidebar -->
        <div class="col-md-3 d-none d-md-block">
            <div class="desktop-sidebar">
                <?php include 'sidebar.php'; ?>
            </div>
        </div>

        <!-- Mobile Offcanvas Sidebar -->
        <div class="offcanvas offcanvas-start d-md-none" tabindex="-1" id="adminMobileSidebar">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title">Admin Menu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body">
                <?php include 'sidebar.php'; ?>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <!-- Mobile Header -->
            <div class="d-md-none d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                <h4 class="mb-0">Send Push Notification</h4>
                <button class="btn btn-accent" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileSidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <div class="d-none d-md-block mb-4">
                <h1>Send Push Notification</h1>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-body">
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea name="body" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">URL (optional)</label>
                            <input type="text" name="url" class="form-control" value="/mes-society/public/">
                        </div>
                        <button type="submit" class="btn btn-accent">Send to All Subscribers</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>