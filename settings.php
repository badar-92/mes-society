<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';

$session = new SessionManager();
$session->requireLogin();
$session->requireRole(['super_admin', 'department_head']);

$db = Database::getInstance()->getConnection();

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $site_title = $_POST['site_title'] ?? '';
    $admin_email = $_POST['admin_email'] ?? '';
    $contact_phone = $_POST['contact_phone'] ?? '';
    $contact_address = $_POST['contact_address'] ?? '';
    $facebook_url = $_POST['facebook_url'] ?? '';
    $instagram_url = $_POST['instagram_url'] ?? '';
    $twitter_url = $_POST['twitter_url'] ?? '';
    $newsletter_enabled = isset($_POST['newsletter_enabled']) ? 1 : 0;
    $registration_open = isset($_POST['registration_open']) ? 1 : 0;
    $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;

    try {
        // Check if settings table exists
        $checkTable = $db->query("SHOW TABLES LIKE 'settings'");
        if ($checkTable->rowCount() == 0) {
            // Create settings table if it doesn't exist
            $createTable = $db->exec("
                CREATE TABLE IF NOT EXISTS `settings` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `setting_key` varchar(255) NOT NULL,
                    `setting_value` text,
                    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `setting_key` (`setting_key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
            
            if ($createTable === false) {
                throw new Exception("Failed to create settings table.");
            }
        }

        // Update settings in database
        $settings = [
            'site_title' => $site_title,
            'admin_email' => $admin_email,
            'contact_phone' => $contact_phone,
            'contact_address' => $contact_address,
            'facebook_url' => $facebook_url,
            'instagram_url' => $instagram_url,
            'twitter_url' => $twitter_url,
            'newsletter_enabled' => $newsletter_enabled,
            'registration_open' => $registration_open,
            'maintenance_mode' => $maintenance_mode
        ];

        foreach ($settings as $key => $value) {
            $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }

        $_SESSION['success'] = "Settings updated successfully";
    } catch(PDOException $e) {
        error_log("Settings update error: " . $e->getMessage());
        $_SESSION['error'] = "Failed to update settings: " . $e->getMessage();
    } catch(Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }

    header("Location: settings.php");
    exit();
}

$page_title = "System Settings";
require_once '../includes/header.php';

// Get current settings
$current_settings = [];
try {
    $checkTable = $db->query("SHOW TABLES LIKE 'settings'");
    if ($checkTable->rowCount() > 0) {
        $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
        $settings_data = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $current_settings = $settings_data;
    }
} catch(PDOException $e) {
    error_log("Settings fetch error: " . $e->getMessage());
}

// Default values
$default_settings = [
    'site_title' => 'MES Society - University of Lahore',
    'admin_email' => 'mes@uol.edu.pk',
    'contact_phone' => '+92 42 111 865 865',
    'contact_address' => 'Mechanical Engineering Department, University of Lahore',
    'facebook_url' => 'https://facebook.com/mesuol',
    'instagram_url' => 'https://instagram.com/mesuol',
    'twitter_url' => 'https://twitter.com/mesuol',
    'newsletter_enabled' => '1',
    'registration_open' => '1',
    'maintenance_mode' => '0'
];

// Merge with current settings
$settings = array_merge($default_settings, $current_settings);
?>

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
            <!-- Mobile Header Bar -->
            <div class="d-md-none d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                <div>
                    <h4 class="mb-0">System Settings</h4>
                    <small class="text-muted">Configuration Management</small>
                </div>
                <button class="btn btn-accent" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileSidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <div class="d-none d-md-flex justify-content-between align-items-center mb-4">
                <h1>System Settings</h1>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-secondary" onclick="location.reload()">
                        <i class="fas fa-sync-alt me-2"></i>Refresh
                    </button>
                </div>
            </div>

            <!-- Success Message -->
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="row">
                    <!-- General Settings -->
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-cog me-2"></i>General Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="site_title" class="form-label">Site Title</label>
                                    <input type="text" class="form-control" id="site_title" name="site_title" 
                                           value="<?php echo htmlspecialchars($settings['site_title']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="admin_email" class="form-label">Admin Email</label>
                                    <input type="email" class="form-control" id="admin_email" name="admin_email" 
                                           value="<?php echo htmlspecialchars($settings['admin_email']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="contact_phone" class="form-label">Contact Phone</label>
                                    <input type="text" class="form-control" id="contact_phone" name="contact_phone" 
                                           value="<?php echo htmlspecialchars($settings['contact_phone']); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="contact_address" class="form-label">Contact Address</label>
                                    <textarea class="form-control" id="contact_address" name="contact_address" 
                                              rows="3"><?php echo htmlspecialchars($settings['contact_address']); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Social Media Settings -->
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-share-alt me-2"></i>Social Media Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="facebook_url" class="form-label">Facebook URL</label>
                                    <input type="url" class="form-control" id="facebook_url" name="facebook_url" 
                                           value="<?php echo htmlspecialchars($settings['facebook_url']); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="instagram_url" class="form-label">Instagram URL</label>
                                    <input type="url" class="form-control" id="instagram_url" name="instagram_url" 
                                           value="<?php echo htmlspecialchars($settings['instagram_url']); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="twitter_url" class="form-label">Twitter URL</label>
                                    <input type="url" class="form-control" id="twitter_url" name="twitter_url" 
                                           value="<?php echo htmlspecialchars($settings['twitter_url']); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Features -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-sliders-h me-2"></i>System Features
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="newsletter_enabled" name="newsletter_enabled" 
                                           <?php echo $settings['newsletter_enabled'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="newsletter_enabled">
                                        <strong>Newsletter System</strong>
                                        <small class="d-block text-muted">Enable email newsletter functionality</small>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="registration_open" name="registration_open" 
                                           <?php echo $settings['registration_open'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="registration_open">
                                        <strong>Member Registration</strong>
                                        <small class="d-block text-muted">Allow new member applications</small>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" 
                                           <?php echo $settings['maintenance_mode'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="maintenance_mode">
                                        <strong>Maintenance Mode</strong>
                                        <small class="d-block text-muted">Put website under maintenance</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Danger Zone -->
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>Danger Zone
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="d-grid">
                                    <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#clearCacheModal">
                                        <i class="fas fa-broom me-2"></i>Clear Cache
                                    </button>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="d-grid">
                                    <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#backupModal">
                                        <i class="fas fa-database me-2"></i>Backup Database
                                    </button>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="d-grid">
                                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#resetModal">
                                        <i class="fas fa-trash me-2"></i>Reset System
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="mt-4 text-end">
                    <button type="submit" name="update_settings" class="btn btn-accent btn-lg">
                        <i class="fas fa-save me-2"></i>Save All Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Clear Cache Modal -->
<div class="modal fade" id="clearCacheModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Clear System Cache</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to clear all system cache? This will remove temporary files and may improve performance.</p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    This action cannot be undone.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="clearSystemCache()">
                    <i class="fas fa-broom me-2"></i>Clear Cache
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Backup Modal -->
<div class="modal fade" id="backupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Backup Database</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Create a backup of the entire database. This may take a few minutes.</p>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    The backup file will be downloadable for 24 hours.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info" onclick="backupDatabase()">
                    <i class="fas fa-database me-2"></i>Create Backup
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Reset Modal -->
<div class="modal fade" id="resetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">Reset System</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-danger"><strong>Warning: This is a destructive operation!</strong></p>
                <p>This will reset the entire system to factory defaults. All data including users, events, applications, and media will be permanently deleted.</p>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    This action cannot be undone. All data will be lost permanently.
                </div>
                <div class="mb-3">
                    <label for="confirmReset" class="form-label">Type "RESET" to confirm:</label>
                    <input type="text" class="form-control" id="confirmReset" placeholder="RESET">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="resetButton" disabled onclick="resetSystem()">
                    <i class="fas fa-trash me-2"></i>Reset System
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Mobile Floating Action Button -->
<div class="fab-container d-md-none">
    <button class="fab btn btn-accent rounded-circle" data-bs-toggle="modal" data-bs-target="#quickSettingsModal">
        <i class="fas fa-cogs"></i>
    </button>
</div>

<!-- Quick Settings Modal for Mobile -->
<div class="modal fade" id="quickSettingsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Quick Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="list-group">
                    <a href="#" class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#clearCacheModal">
                        <i class="fas fa-broom me-2 text-warning"></i>Clear Cache
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#backupModal">
                        <i class="fas fa-database me-2 text-info"></i>Backup Database
                    </a>
                    <a href="#" class="list-group-item list-group-item-action text-danger" data-bs-toggle="modal" data-bs-target="#resetModal">
                        <i class="fas fa-trash me-2"></i>Reset System
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Reset confirmation
    const confirmReset = document.getElementById('confirmReset');
    const resetButton = document.getElementById('resetButton');
    
    if (confirmReset && resetButton) {
        confirmReset.addEventListener('input', function() {
            resetButton.disabled = this.value !== 'RESET';
        });
    }
});

function clearSystemCache() {
    // Implement cache clearing logic
    alert('Cache cleared successfully!');
    $('#clearCacheModal').modal('hide');
}

function backupDatabase() {
    // Implement backup logic
    alert('Database backup started! You will receive a notification when completed.');
    $('#backupModal').modal('hide');
}

function resetSystem() {
    // Implement system reset logic
    alert('System reset initiated! This may take a few minutes.');
    $('#resetModal').modal('hide');
}
</script>

<style>
/* Floating Action Button */
.fab-container {
    position: fixed;
    bottom: 80px;
    right: 20px;
    z-index: 1030;
}

.fab {
    width: 60px;
    height: 60px;
    border: none;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    transition: all 0.3s ease;
}

.fab:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(0,0,0,0.3);
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