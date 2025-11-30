<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/pdf-generator/PDFGenerator.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole('member');

// Handle ID card download FIRST - before any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download_id_card'])) {
    $user = $session->getCurrentUser();
    
    // Get user's assigned posts
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
    
    $pdf_generator = new PDFGenerator();
    $pdf_data = $pdf_generator->generateIDCardPDF($user);
    
    // Send PDF headers and content
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="id_card_' . $user['sap_id'] . '.pdf"');
    header('Content-Length: ' . strlen($pdf_data));
    echo $pdf_data;
    exit;
}

// Only load HTML if not downloading PDF
$page_title = "Digital ID Card";
$hidePublicNavigation = true;
require_once '../includes/header.php';

$user = $session->getCurrentUser();
$db = Database::getInstance()->getConnection();
$functions = new Functions();

// Get user's assigned posts/positions
$user_posts = [];
try {
    $stmt = $db->prepare("SELECT post_name FROM user_posts WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $user_posts = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {
    error_log("User posts error: " . $e->getMessage());
}
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

            <!-- ID Card Preview -->
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="card border-0 shadow-lg">
                        <div class="card-body p-0">
                            <!-- Elite ID Card Design - Orange Background, Black Header -->
                            <div class="id-card-container" style="background: linear-gradient(135deg, #FF6600 0%, #FF8533 100%); color: white; border-radius: 12px; overflow: hidden; position: relative; border: 3px solid #000; min-height: 400px;">
                                
                                <!-- Header Section - Black with MES Logo -->
                                <div class="id-card-header" style="background: #000000; padding: 12px 15px; text-align: center; border-bottom: 2px solid #FF6600; display: flex; align-items: center; justify-content: center;">
                                    <!-- MES Logo -->
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
                                
                                <!-- Body Section -->
                                <div class="id-card-body" style="padding: 20px; position: relative;">
                                    
                                    <!-- Photo Section -->
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
                                    
                                    <!-- Information Section -->
                                    <div class="id-info-section" style="margin-right: 120px;">
                                        <!-- Name -->
                                        <div class="info-row" style="margin-bottom: 10px;">
                                            <div style="font-size: 0.8rem; opacity: 0.8; color: #000; font-weight: bold;">FULL NAME</div>
                                            <div style="font-size: 1rem; font-weight: 600; color: #000;"><?php echo strtoupper($user['name']); ?></div>
                                        </div>
                                        
                                        <!-- SAP ID -->
                                        <div class="info-row" style="margin-bottom: 10px;">
                                            <div style="font-size: 0.8rem; opacity: 0.8; color: #000; font-weight: bold;">SAP ID</div>
                                            <div style="font-size: 0.9rem; font-weight: 500; color: #000;"><?php echo $user['sap_id']; ?></div>
                                        </div>
                                        
                                        <!-- Department -->
                                        <div class="info-row" style="margin-bottom: 10px;">
                                            <div style="font-size: 0.8rem; opacity: 0.8; color: #000; font-weight: bold;">DEPARTMENT</div>
                                            <div style="font-size: 0.9rem; font-weight: 500; color: #000;"><?php echo strtoupper($user['department']); ?></div>
                                        </div>
                                        
                                        <!-- Semester -->
                                        <div class="info-row" style="margin-bottom: 10px;">
                                            <div style="font-size: 0.8rem; opacity: 0.8; color: #000; font-weight: bold;">SEMESTER</div>
                                            <div style="font-size: 0.9rem; font-weight: 500; color: #000;"><?php echo $user['semester']; ?></div>
                                        </div>
                                        
                                        <!-- Position -->
                                        <?php if (!empty($user_posts)): ?>
                                        <div class="info-row" style="margin-bottom: 10px;">
                                            <div style="font-size: 0.8rem; opacity: 0.8; color: #000; font-weight: bold;">POSITION</div>
                                            <div style="font-size: 0.9rem; font-weight: 500; color: #000; background: #000; color: #FF6600; padding: 5px 10px; border-radius: 5px; display: inline-block; font-weight: bold;">
                                                <?php echo strtoupper(implode(', ', $user_posts)); ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- QR Code Section -->
                                    <div class="id-qr-section" style="text-align: center; margin-top: 20px; padding-top: 15px; border-top: 2px solid #000;">
                                        <div style="background: white; display: inline-block; padding: 8px; border-radius: 6px; border: 2px solid #000;">
                                            <?php
                                            $qr_code_path = '../assets/images/MES UOL.png';
                                            if (file_exists($qr_code_path)) {
                                                echo '<img src="' . $qr_code_path . '" style="width: 80px; height: 80px; object-fit: contain;" alt="QR Code">';
                                            } else {
                                                echo '<div style="width: 80px; height: 80px; background: #f8f9fa; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; color: #000; font-weight: bold; text-align: center; border: 1px solid #000;">';
                                                echo 'QR CODE<br>SCAN TO VERIFY';
                                                echo '</div>';
                                            }
                                            ?>
                                        </div>
                                        <div style="font-size: 0.8rem; margin-top: 5px; opacity: 0.8; color: #000; font-weight: bold;">
                                            Scan to verify membership
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Footer -->
                                <div class="id-card-footer" style="background: rgba(0, 0, 0, 0.9); padding: 8px 15px; text-align: center; font-size: 0.7rem; opacity: 0.9; color: #FF6600; font-weight: bold;">
                                    <div>Official ID Card - Mechanical Engineering Society</div>
                                    <div>Valid through: <?php echo date('Y-m-d', strtotime('+1 year')); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Download Button for Mobile -->
                    <div class="text-center mt-4 d-md-none">
                        <form method="POST">
                            <button type="submit" name="download_id_card" class="btn btn-accent btn-lg">
                                <i class="fas fa-download me-2"></i>Download ID Card
                            </button>
                        </form>
                    </div>
                    
                    <!-- Instructions -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-info-circle me-2"></i>ID Card Instructions
                            </h6>
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
.id-card-container {
    box-shadow: 0 8px 25px rgba(0,0,0,0.3);
}

.id-card-header {
    position: relative;
    overflow: hidden;
}

.id-card-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, transparent 40%, rgba(255,255,255,0.1) 50%, transparent 60%);
    animation: shimmer 3s infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

@media (max-width: 768px) {
    .id-card-container {
        transform: scale(0.9);
        margin: -20px auto;
    }
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

<?php require_once '../includes/footer.php'; ?>