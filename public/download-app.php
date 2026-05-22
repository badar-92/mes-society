<?php
$title = "Download MES UOL App";
require_once '../includes/header.php';
require_once '../includes/database.php';

$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT * FROM apk_versions ORDER BY uploaded_at DESC");
$apks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <h1>Download MES UOL Society App</h1>
    <p>Choose the version you want to install on your Android device.</p>
    
    <?php if (empty($apks)): ?>
        <div class="alert alert-warning">No APK available yet. Please check back later.</div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($apks as $apk): ?>
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Version <?= htmlspecialchars($apk['version_name']) ?></h5>
                            <p class="card-text">
                                Uploaded: <?= htmlspecialchars($apk['uploaded_at']) ?><br>
                                Size: <?= round($apk['file_size'] / 1024) ?> KB
                            </p>
                            <a href="<?= htmlspecialchars($apk['file_path']) ?>" class="btn btn-accent" download>
                                Download APK
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <hr>
    <p class="text-muted">Installation: After downloading, open the file on your Android device and allow "Install from unknown sources" if prompted.</p>
</div>

<?php require_once '../includes/footer.php'; ?>