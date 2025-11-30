<?php
// setup-database.php
require_once 'includes/database.php';

$db = Database::getInstance()->getConnection();

try {
    // Create settings table
    $db->exec("
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
    
    // Add priority column to duties if it doesn't exist
    $db->exec("ALTER TABLE duties ADD COLUMN IF NOT EXISTS `priority` ENUM('low', 'medium', 'high') DEFAULT 'medium'");
    
    // Insert default settings
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
    
    foreach ($default_settings as $key => $value) {
        $stmt = $db->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->execute([$key, $value]);
    }
    
    echo "Database setup completed successfully!";
    
} catch(PDOException $e) {
    echo "Database setup failed: " . $e->getMessage();
}