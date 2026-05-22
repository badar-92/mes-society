<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/pdf-generator/PDFGenerator.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole('member');

/**
 * Generate QR code with MES logo centered (scannable).
 * Uses error correction level H and a white circular background behind the logo.
 *
 * @param string $url The URL to encode
 * @return string Binary PNG data of the QR code with logo, or empty string on failure
 */
function getQRCodeWithLogo($url) {
    // Step 1: Generate QR code with error correction H (30% recoverable)
    $qrApiUrl = 'https://quickchart.io/qr?text=' . urlencode($url) . '&size=300&margin=2&level=H';
    $qrData = @file_get_contents($qrApiUrl);
    if ($qrData === false) {
        error_log("QR generation failed for URL: $url");
        return '';
    }

    $qrImage = @imagecreatefromstring($qrData);
    if (!$qrImage) {
        return '';
    }

    // Step 2: Fetch MES logo
    $logoUrl = 'https://mesuol.xo.je/mes-society/assets/images/logo-mes.png';
    $logoData = @file_get_contents($logoUrl);
    if ($logoData === false) {
        error_log("Logo fetch failed from $logoUrl");
        imagedestroy($qrImage);
        return $qrData; // Return QR without logo
    }

    $logoImage = @imagecreatefromstring($logoData);
    if (!$logoImage) {
        imagedestroy($qrImage);
        return $qrData;
    }

    // Step 3: Dimensions
    $qrWidth = imagesx($qrImage);
    $qrHeight = imagesy($qrImage);
    $logoWidth = imagesx($logoImage);
    $logoHeight = imagesy($logoImage);

    // Logo size = 20% of QR width (safe for error correction H)
    $targetLogoSize = (int)($qrWidth * 0.24);
    
    // Preserve aspect ratio
    $aspect = $logoWidth / $logoHeight;
    if ($logoWidth > $logoHeight) {
        $newLogoWidth = $targetLogoSize;
        $newLogoHeight = (int)($targetLogoSize / $aspect);
    } else {
        $newLogoHeight = $targetLogoSize;
        $newLogoWidth = (int)($targetLogoSize * $aspect);
    }

    // Resize logo (preserve transparency)
    $resizedLogo = imagecreatetruecolor($newLogoWidth, $newLogoHeight);
    imagealphablending($resizedLogo, false);
    imagesavealpha($resizedLogo, true);
    $transparent = imagecolorallocatealpha($resizedLogo, 0, 0, 0, 127);
    imagefill($resizedLogo, 0, 0, $transparent);
    imagecopyresampled($resizedLogo, $logoImage, 0, 0, 0, 0, $newLogoWidth, $newLogoHeight, $logoWidth, $logoHeight);

    // Step 4: Create a white circular background (cleaner, improves scanning)
    $circleDiameter = max($newLogoWidth, $newLogoHeight) + 10; // 5px padding
    $circleRadius = $circleDiameter / 2;
    $circleX = (int)(($qrWidth - $circleDiameter) / 2);
    $circleY = (int)(($qrHeight - $circleDiameter) / 2);
    
    $circleImg = imagecreatetruecolor($circleDiameter, $circleDiameter);
    imagealphablending($circleImg, false);
    imagesavealpha($circleImg, true);
    $white = imagecolorallocate($circleImg, 255, 255, 255);
    imagefilledellipse($circleImg, $circleRadius, $circleRadius, $circleDiameter, $circleDiameter, $white);
    
    // Merge white circle onto QR
    imagealphablending($qrImage, true);
    imagecopy($qrImage, $circleImg, $circleX, $circleY, 0, 0, $circleDiameter, $circleDiameter);
    imagedestroy($circleImg);
    
    // Step 5: Place logo in center
    $logoDestX = (int)(($qrWidth - $newLogoWidth) / 2);
    $logoDestY = (int)(($qrHeight - $newLogoHeight) / 2);
    imagecopy($qrImage, $resizedLogo, $logoDestX, $logoDestY, 0, 0, $newLogoWidth, $newLogoHeight);

    // Step 6: Output final PNG
    ob_start();
    imagepng($qrImage);
    $finalImageData = ob_get_clean();

    // Cleanup
    imagedestroy($qrImage);
    imagedestroy($logoImage);
    imagedestroy($resizedLogo);

    return $finalImageData;
}

// Handle ID card download
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download_id_card'])) {
    $user = $session->getCurrentUser();
    
    $db = Database::getInstance()->getConnection();
    $user_posts = [];
    try {
        $stmt = $db->prepare("SELECT post_name FROM user_posts WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $user_posts = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch(PDOException $e) {
        error_log("User posts error: " . $e->getMessage());
    }
    $user['posts'] = $user_posts;
    
    $profileUrl = 'https://mesuol.xo.je/mes-society/public/user.php?id=' . $user['id'];
    $qrImageData = getQRCodeWithLogo($profileUrl);
    
    $pdf_generator = new PDFGenerator();
    $pdf_data = $pdf_generator->generateIDCardPDF($user, $qrImageData);
    
    if ($pdf_data === false) {
        die("PDF generation failed. Please try again later.");
    }
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="id_card_' . $user['sap_id'] . '.pdf"');
    header('Content-Length: ' . strlen($pdf_data));
    echo $pdf_data;
    exit;
}

// HTML view
$page_title = "Digital ID Card";
$hidePublicNavigation = true;
require_once '../includes/header.php';

$user = $session->getCurrentUser();
$db = Database::getInstance()->getConnection();
$functions = new Functions();

$user_posts = [];
try {
    $stmt = $db->prepare("SELECT post_name FROM user_posts WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $user_posts = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {
    error_log("User posts error: " . $e->getMessage());
}

$profileUrl = 'https://mesuol.xo.je/mes-society/public/user.php?id=' . $user['id'];
$qrImageWithLogoBinary = getQRCodeWithLogo($profileUrl);
$qrImageBase64 = '';
if (!empty($qrImageWithLogoBinary)) {
    $qrImageBase64 = 'data:image/png;base64,' . base64_encode($qrImageWithLogoBinary);
} else {
    // Fallback: plain QR (no logo)
    $qrImageBase64 = 'https://quickchart.io/qr?text=' . urlencode($profileUrl) . '&size=300&margin=2&level=H';
}
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-3 d-none d-md-block">
            <div class="desktop-sidebar">
                <?php include 'sidebar.php'; ?>
            </div>
        </div>

        <div class="offcanvas offcanvas-start d-md-none" tabindex="-1" id="adminMobileSidebar">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title">Member Menu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body">
                <?php include 'sidebar.php'; ?>
            </div>
        </div>

        <div class="col-md-9">
            <div class="d-md-none d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                <div>
                    <h4 class="mb-0">Digital ID Card</h4>
                    <small class="text-muted">Your Society Identity</small>
                </div>
                <button class="btn btn-accent" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileSidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <div class="d-none d-md-flex justify-content-between align-items-center mb-4">
                <h1>Digital ID Card</h1>
                <form method="POST" class="d-inline">
                   <button type="submit" name="download_id_card" class="btn btn-accent"> 
                        <i class="fas fa-download me-2"></i>Download ID Card 
                    </button> 
                </form>
            </div>

            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="card border-0 shadow-lg">
                        <div class="card-body p-0">
                            <div class="id-card-container" style="background: linear-gradient(135deg, #FF6600 0%, #FF8533 100%); color: white; border-radius: 12px; overflow: hidden; position: relative; border: 3px solid #000; min-height: 400px;">
                                
                                <div class="id-card-header" style="background: #000000; padding: 12px 15px; text-align: center; border-bottom: 2px solid #FF6600; display: flex; align-items: center; justify-content: center;">
                                    <div style="margin-right: 10px;">
                                        <?php
                                        $mes_logo = '../assets/images/logo-mes1.1.png';
                                        if (file_exists($mes_logo)) {
                                            echo '<img src="' . $mes_logo . '" style="height: 35px; width: auto;" alt="MES Logo">';
                                        } else {
                                            echo '<div style="width: 35px; height: 35px; background: #FF6600; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #000; font-weight: bold; font-size: 12px;">MES</div>';
                                        }
                                        ?>
                                    </div>
                                    <div>
                                        <h4 class="mb-0" style="font-weight: 700; font-size: 1.1rem; color: #FF6600; line-height: 1.1;">MECHANICAL ENGINEERING SOCIETY</h4>
                                        <p class="mb-0" style="font-size: 0.8rem; opacity: 0.9; color: white; line-height: 1.1;">University of Lahore</p>
                                    </div>
                                </div>
                                
                                <div class="id-card-body" style="padding: 20px; position: relative;">
                                    
                                    <div class="id-photo-section" style="position: absolute; right: 20px; top: 20px; text-align: center;">
                                        <div class="photo-container" style="width: 100px; height: 100px; border: 3px solid #000; border-radius: 8px; overflow: hidden; background: white;">
                                            <?php
                                            $profile_pic = '../uploads/profile-pictures/' . $user['profile_picture'];
                                            if (file_exists($profile_pic) && $user['profile_picture'] !== 'default-avatar.png') {
                                                echo '<img src="' . $profile_pic . '" style="width: 100%; height: 100%; object-fit: cover;" alt="Photo">';
                                            } else {
                                                echo '<div style="width: 100%; height: 100%; background: #FF6600; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.5rem;">';
                                                echo $functions->getUserInitials($user['name']);
                                                echo '</div>';
                                            }
                                            ?>
                                        </div>
                                        <div class="validity" style="margin-top: 8px; font-size: 0.7rem; opacity: 0.8; color: #000; font-weight: bold;">
                                            Valid: <?php echo date('Y'); ?> - <?php echo date('Y') + 1; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="id-info-section" style="margin-right: 120px;">
                                        <div class="info-row" style="margin-bottom: 10px;">
                                            <div style="font-size: 0.8rem; opacity: 0.8; color: #000; font-weight: bold;">FULL NAME</div>
                                            <div style="font-size: 1rem; font-weight: 600; color: #000;"><?php echo strtoupper($user['name']); ?></div>
                                        </div>
                                        <div class="info-row" style="margin-bottom: 10px;">
                                            <div style="font-size: 0.8rem; opacity: 0.8; color: #000; font-weight: bold;">SAP ID</div>
                                            <div style="font-size: 0.9rem; font-weight: 500; color: #000;"><?php echo $user['sap_id']; ?></div>
                                        </div>
                                        <div class="info-row" style="margin-bottom: 10px;">
                                            <div style="font-size: 0.8rem; opacity: 0.8; color: #000; font-weight: bold;">DEPARTMENT</div>
                                            <div style="font-size: 0.9rem; font-weight: 500; color: #000;"><?php echo strtoupper($user['department']); ?></div>
                                        </div>
                                        <div class="info-row" style="margin-bottom: 10px;">
                                            <div style="font-size: 0.8rem; opacity: 0.8; color: #000; font-weight: bold;">SEMESTER</div>
                                            <div style="font-size: 0.9rem; font-weight: 500; color: #000;"><?php echo $user['semester']; ?></div>
                                        </div>
                                        <?php if (!empty($user_posts)): ?>
                                        <div class="info-row" style="margin-bottom: 10px;">
                                            <div style="font-size: 0.8rem; opacity: 0.8; color: #000; font-weight: bold;">POSITION</div>
                                            <div style="font-size: 0.9rem; font-weight: 500; color: #000; background: #000; color: #FF6600; padding: 5px 10px; border-radius: 5px; display: inline-block; font-weight: bold;">
                                                <?php echo strtoupper(implode(', ', $user_posts)); ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- QR Code Section - Scannable with visible logo -->
                                    <div class="id-qr-section" style="text-align: center; margin-top: 20px; padding-top: 15px; border-top: 2px solid #000;">
                                        <div style="background: white; display: inline-block; padding: 8px; border-radius: 6px; border: 2px solid #000;">
                                            <img src="<?php echo $qrImageBase64; ?>" 
                                                 style="width: 90px; height: 90px; object-fit: contain;" 
                                                 alt="Scannable QR Code with MES Logo">
                                        </div>
                                        <div style="font-size: 0.8rem; margin-top: 5px; opacity: 0.8; color: #000; font-weight: bold;">
                                            Scan to verify membership
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="id-card-footer" style="background: rgba(0, 0, 0, 0.9); padding: 8px 15px; text-align: center; font-size: 0.7rem; opacity: 0.9; color: #FF6600; font-weight: bold;">
                                    <div>Official ID Card - Mechanical Engineering Society</div>
                                    <div>Valid through: <?php echo date('Y-m-d', strtotime('+1 year')); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4 d-md-none">
                        <form method="POST">
                            <button type="submit" name="download_id_card" class="btn btn-accent btn-lg">
                                <i class="fas fa-download me-2"></i>Download ID Card
                            </button>
                        </form>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-header">
                            <h6 class="card-title mb-0"><i class="fas fa-info-circle me-2"></i>ID Card Instructions</h6>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Download and print your ID card</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Always carry with you to society events</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Required for event check-ins and access</li>
                                <li class="mb-0"><i class="fas fa-check text-success me-2"></i> Contact admin if information needs update</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.id-card-container { box-shadow: 0 8px 25px rgba(0,0,0,0.3); }
.id-card-header { position: relative; overflow: hidden; }
.id-card-header::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: linear-gradient(45deg, transparent 40%, rgba(255,255,255,0.1) 50%, transparent 60%);
    animation: shimmer 3s infinite;
}
@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}
@media (max-width: 768px) {
    .id-card-container { transform: scale(0.9); margin: -20px auto; }
}
.container-fluid { padding-left: 10px; padding-right: 10px; }
.offcanvas-header { background: var(--primary-color); color: white; }
.offcanvas-body { padding: 0; }
.offcanvas-body .nav { padding: 1rem; }
</style>

<?php require_once '../includes/footer.php'; ?>